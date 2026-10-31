<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiController
{
    #[Route('/api/{path}', name: 'api_options', requirements: ['path' => '.+'], methods: ['OPTIONS'])]
    public function options(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept',
            'Access-Control-Max-Age' => '3600',
        ]);
    }

    #[Route('/api/v1/health', name: 'api_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json(['status' => 'ok', 'api' => 'v1']);
    }

    #[Route('/api/v1/dashboard', name: 'api_dashboard', methods: ['GET'])]
    public function dashboard(Connection $connection): JsonResponse
    {
        $summary = [
            'products' => (int) $connection->fetchOne('SELECT COUNT(*) FROM Produit'),
            'customers' => (int) $connection->fetchOne('SELECT COUNT(*) FROM Client'),
            'orders' => (int) $connection->fetchOne('SELECT COUNT(*) FROM Commande'),
            'units' => (int) $connection->fetchOne('SELECT COALESCE(SUM(qteCmd), 0) FROM ligneCommande'),
            'revenue' => (float) $connection->fetchOne('SELECT COALESCE(SUM(l.qteCmd * p.prixUProd), 0) FROM ligneCommande l INNER JOIN Produit p ON p.idProd = l.idProd'),
            'monthRevenue' => (float) $connection->fetchOne(
                'SELECT COALESCE(SUM(l.qteCmd * p.prixUProd), 0)
                 FROM Commande c INNER JOIN ligneCommande l ON l.idCmd = c.idCmd
                 INNER JOIN Produit p ON p.idProd = l.idProd
                 WHERE c.dateCmd >= DATE_FORMAT(CURRENT_DATE(), "%Y-%m-01")
                   AND c.dateCmd < DATE_ADD(DATE_FORMAT(CURRENT_DATE(), "%Y-%m-01"), INTERVAL 1 MONTH)'
            ),
        ];
        $monthlyRows = $connection->fetchAllAssociative(
            'SELECT DATE_FORMAT(c.dateCmd, "%Y-%m") AS month, SUM(l.qteCmd * p.prixUProd) AS revenue
             FROM Commande c INNER JOIN ligneCommande l ON l.idCmd = c.idCmd
             INNER JOIN Produit p ON p.idProd = l.idProd
             WHERE c.dateCmd >= DATE_SUB(DATE_FORMAT(CURRENT_DATE(), "%Y-%m-01"), INTERVAL 5 MONTH)
             GROUP BY month ORDER BY month'
        );
        $monthlyByKey = [];
        foreach ($monthlyRows as $row) {
            $monthlyByKey[$row['month']] = (float) $row['revenue'];
        }
        $monthlySales = [];
        $monthStart = new \DateTimeImmutable('first day of this month');
        for ($offset = 5; $offset >= 0; --$offset) {
            $month = $monthStart->modify('-' . $offset . ' months');
            $key = $month->format('Y-m');
            $monthlySales[] = ['month' => $key, 'revenue' => $monthlyByKey[$key] ?? 0.0];
        }

        return $this->json([
            'summary' => $summary,
            'monthlySales' => $monthlySales,
            'topProducts' => $connection->fetchAllAssociative(
                'SELECT p.idProd AS id, p.designProd AS name, SUM(l.qteCmd) AS units,
                        SUM(l.qteCmd * p.prixUProd) AS revenue
                 FROM ligneCommande l INNER JOIN Produit p ON p.idProd = l.idProd
                 GROUP BY p.idProd, p.designProd ORDER BY units DESC LIMIT 5'
            ),
            'topCustomers' => $connection->fetchAllAssociative(
                'SELECT cl.idCli AS id, cl.prenomCli AS firstName, cl.nomCli AS lastName,
                        COUNT(DISTINCT c.idCmd) AS orderCount,
                        COALESCE(SUM(l.qteCmd * p.prixUProd), 0) AS revenue
                 FROM Client cl INNER JOIN Commande c ON c.idCli = cl.idCli
                 LEFT JOIN ligneCommande l ON l.idCmd = c.idCmd
                 LEFT JOIN Produit p ON p.idProd = l.idProd
                 GROUP BY cl.idCli, cl.nomCli, cl.prenomCli
                 ORDER BY revenue DESC LIMIT 5'
            ),
            'lowStock' => $connection->fetchAllAssociative(
                'SELECT idProd AS id, designProd AS name, qteStock AS stock
                 FROM Produit WHERE qteStock < 5 ORDER BY qteStock LIMIT 6'
            ),
            'recentOrders' => $connection->fetchAllAssociative(
                'SELECT c.idCmd AS id, c.dateCmd AS date, cl.prenomCli AS firstName,
                        cl.nomCli AS lastName, COALESCE(SUM(l.qteCmd * p.prixUProd), 0) AS total
                 FROM Commande c INNER JOIN Client cl ON cl.idCli = c.idCli
                 LEFT JOIN ligneCommande l ON l.idCmd = c.idCmd
                 LEFT JOIN Produit p ON p.idProd = l.idProd
                 GROUP BY c.idCmd, c.dateCmd, cl.nomCli, cl.prenomCli
                 ORDER BY c.dateCmd DESC LIMIT 6'
            ),
        ]);
    }

    #[Route('/api/v1/products', name: 'api_products', methods: ['GET'])]
    public function products(Connection $connection): JsonResponse
    {
        return $this->json(['items' => $connection->fetchAllAssociative(
            'SELECT idProd AS id, designProd AS name, qteStock AS stock,
                    prixUProd AS unitPrice, photoProd AS photo
             FROM Produit ORDER BY designProd'
        )]);
    }

    #[Route('/api/v1/products', name: 'api_product_create', methods: ['POST'])]
    public function createProduct(Request $request, Connection $connection): JsonResponse
    {
        $data = $this->body($request);
        $error = $this->validateProduct($data);
        if ($error !== null) {
            return $this->json(['error' => $error], Response::HTTP_BAD_REQUEST);
        }

        $connection->insert('Produit', [
            'designProd' => trim($data['name']),
            'qteStock' => (int) $data['stock'],
            'prixUProd' => (float) $data['unitPrice'],
            'photoProd' => trim((string) ($data['photo'] ?? '')) ?: null,
        ]);

        return $this->json(['id' => (int) $connection->lastInsertId()], Response::HTTP_CREATED);
    }

    #[Route('/api/v1/products/{id}', name: 'api_product_update', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    public function updateProduct(int $id, Request $request, Connection $connection): JsonResponse
    {
        if (!$connection->fetchOne('SELECT idProd FROM Produit WHERE idProd = ?', [$id])) {
            return $this->json(['error' => 'Produit introuvable.'], Response::HTTP_NOT_FOUND);
        }
        $data = $this->body($request);
        $error = $this->validateProduct($data);
        if ($error !== null) {
            return $this->json(['error' => $error], Response::HTTP_BAD_REQUEST);
        }

        $connection->update('Produit', [
            'designProd' => trim($data['name']),
            'qteStock' => (int) $data['stock'],
            'prixUProd' => (float) $data['unitPrice'],
            'photoProd' => trim((string) ($data['photo'] ?? '')) ?: null,
        ], ['idProd' => $id]);

        return $this->json(['status' => 'updated']);
    }

    #[Route('/api/v1/customers', name: 'api_customers', methods: ['GET'])]
    public function customers(Connection $connection): JsonResponse
    {
        return $this->json(['items' => $connection->fetchAllAssociative(
            'SELECT cl.idCli AS id, cl.nomCli AS lastName, cl.prenomCli AS firstName,
                    cl.email, cl.photo, cl.idRegion AS regionId, r.nomRegion AS region,
                    COUNT(DISTINCT c.idCmd) AS orderCount
             FROM Client cl LEFT JOIN Region r ON r.idRegion = cl.idRegion
             LEFT JOIN Commande c ON c.idCli = cl.idCli
             GROUP BY cl.idCli, cl.nomCli, cl.prenomCli, cl.email, cl.photo, cl.idRegion, r.nomRegion
             ORDER BY cl.nomCli, cl.prenomCli'
        )]);
    }

    #[Route('/api/v1/regions', name: 'api_regions', methods: ['GET'])]
    public function regions(Connection $connection): JsonResponse
    {
        return $this->json(['items' => $connection->fetchAllAssociative('SELECT idRegion AS id, nomRegion AS name FROM Region ORDER BY nomRegion')]);
    }

    #[Route('/api/v1/customers', name: 'api_customer_create', methods: ['POST'])]
    public function createCustomer(Request $request, Connection $connection): JsonResponse
    {
        $data = $this->body($request);
        $error = $this->validateCustomer($data, $connection);
        if ($error !== null) {
            return $this->json(['error' => $error], Response::HTTP_BAD_REQUEST);
        }

        $connection->insert('Client', $this->customerValues($data));
        return $this->json(['id' => (int) $connection->lastInsertId()], Response::HTTP_CREATED);
    }

    #[Route('/api/v1/customers/{id}', name: 'api_customer_update', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    public function updateCustomer(int $id, Request $request, Connection $connection): JsonResponse
    {
        if (!$connection->fetchOne('SELECT idCli FROM Client WHERE idCli = ?', [$id])) {
            return $this->json(['error' => 'Client introuvable.'], Response::HTTP_NOT_FOUND);
        }
        $data = $this->body($request);
        $error = $this->validateCustomer($data, $connection);
        if ($error !== null) {
            return $this->json(['error' => $error], Response::HTTP_BAD_REQUEST);
        }

        $connection->update('Client', $this->customerValues($data), ['idCli' => $id]);
        return $this->json(['status' => 'updated']);
    }

    #[Route('/api/v1/customers/{id}', name: 'api_customer_delete', requirements: ['id' => '\\d+'], methods: ['DELETE'])]
    public function deleteCustomer(int $id, Connection $connection): JsonResponse
    {
        if (!$connection->fetchOne('SELECT idCli FROM Client WHERE idCli = ?', [$id])) {
            return $this->json(['error' => 'Client introuvable.'], Response::HTTP_NOT_FOUND);
        }
        if ($connection->fetchOne('SELECT idCmd FROM Commande WHERE idCli = ? LIMIT 1', [$id])) {
            return $this->json(['error' => 'Ce client possède des commandes.'], Response::HTTP_CONFLICT);
        }

        $connection->delete('Client', ['idCli' => $id]);
        return $this->json(['status' => 'deleted']);
    }

    #[Route('/api/v1/orders', name: 'api_orders', methods: ['GET'])]
    public function orders(Connection $connection): JsonResponse
    {
        return $this->json(['items' => $connection->fetchAllAssociative(
            'SELECT c.idCmd AS id, c.dateCmd AS date, cl.idCli AS customerId,
                    cl.prenomCli AS firstName, cl.nomCli AS lastName, cl.email,
                    COUNT(l.idProd) AS productCount, COALESCE(SUM(l.qteCmd), 0) AS units,
                    COALESCE(SUM(l.qteCmd * p.prixUProd), 0) AS total
             FROM Commande c INNER JOIN Client cl ON cl.idCli = c.idCli
             LEFT JOIN ligneCommande l ON l.idCmd = c.idCmd
             LEFT JOIN Produit p ON p.idProd = l.idProd
             GROUP BY c.idCmd, c.dateCmd, cl.idCli, cl.nomCli, cl.prenomCli, cl.email
             ORDER BY c.dateCmd DESC'
        )]);
    }

    #[Route('/api/v1/orders', name: 'api_order_create', methods: ['POST'])]
    public function createOrder(Request $request, Connection $connection): JsonResponse
    {
        $data = $this->body($request);
        $error = $this->validateOrder($data, $connection);
        if ($error !== null) {
            return $this->json(['error' => $error], Response::HTTP_BAD_REQUEST);
        }

        $connection->insert('Commande', [
            'idCli' => (int) $data['customerId'],
            'dateCmd' => $data['date'] . ' 00:00:00',
        ]);
        return $this->json(['id' => (int) $connection->lastInsertId()], Response::HTTP_CREATED);
    }

    #[Route('/api/v1/orders/{id}', name: 'api_order_detail', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function orderDetail(int $id, Connection $connection): JsonResponse
    {
        $order = $connection->fetchAssociative(
            'SELECT c.idCmd AS id, c.dateCmd AS date, cl.idCli AS customerId,
                    cl.nomCli AS lastName, cl.prenomCli AS firstName, cl.email,
                    COALESCE(SUM(l.qteCmd * p.prixUProd), 0) AS total,
                    COALESCE(SUM(l.qteCmd), 0) AS units
             FROM Commande c INNER JOIN Client cl ON cl.idCli = c.idCli
             LEFT JOIN ligneCommande l ON l.idCmd = c.idCmd
             LEFT JOIN Produit p ON p.idProd = l.idProd
             WHERE c.idCmd = ?
             GROUP BY c.idCmd, c.dateCmd, cl.idCli, cl.nomCli, cl.prenomCli, cl.email',
            [$id]
        );
        if (!$order) {
            return $this->json(['error' => 'Commande introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $order['lines'] = $connection->fetchAllAssociative(
            'SELECT l.idProd AS productId, l.qteCmd AS quantity,
                    p.designProd AS name, p.prixUProd AS unitPrice,
                    l.qteCmd * p.prixUProd AS total
             FROM ligneCommande l INNER JOIN Produit p ON p.idProd = l.idProd
             WHERE l.idCmd = ? ORDER BY p.designProd',
            [$id]
        );
        $order['products'] = $connection->fetchAllAssociative(
            'SELECT idProd AS id, designProd AS name, qteStock AS stock, prixUProd AS unitPrice
             FROM Produit WHERE qteStock > 0 ORDER BY designProd'
        );

        return $this->json($order);
    }

    #[Route('/api/v1/orders/{id}', name: 'api_order_update', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    public function updateOrder(int $id, Request $request, Connection $connection): JsonResponse
    {
        if (!$connection->fetchOne('SELECT idCmd FROM Commande WHERE idCmd = ?', [$id])) {
            return $this->json(['error' => 'Commande introuvable.'], Response::HTTP_NOT_FOUND);
        }
        $data = $this->body($request);
        $error = $this->validateOrder($data, $connection);
        if ($error !== null) {
            return $this->json(['error' => $error], Response::HTTP_BAD_REQUEST);
        }

        $connection->update('Commande', [
            'idCli' => (int) $data['customerId'],
            'dateCmd' => $data['date'] . ' 00:00:00',
        ], ['idCmd' => $id]);
        return $this->json(['status' => 'updated']);
    }

    #[Route('/api/v1/orders/{id}', name: 'api_order_delete', requirements: ['id' => '\\d+'], methods: ['DELETE'])]
    public function deleteOrder(int $id, Connection $connection): JsonResponse
    {
        $lines = $connection->fetchAllAssociative('SELECT idProd, qteCmd FROM ligneCommande WHERE idCmd = ?', [$id]);
        if (!$connection->fetchOne('SELECT idCmd FROM Commande WHERE idCmd = ?', [$id])) {
            return $this->json(['error' => 'Commande introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $connection->beginTransaction();
        try {
            $connection->delete('Commande', ['idCmd' => $id]);
            foreach ($lines as $line) {
                $connection->executeStatement('UPDATE Produit SET qteStock = qteStock + ? WHERE idProd = ?', [(int) $line['qteCmd'], (int) $line['idProd']]);
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $this->json(['status' => 'deleted', 'stockRestored' => true]);
    }

    #[Route('/api/v1/orders/{orderId}/lines', name: 'api_order_line_create', requirements: ['orderId' => '\\d+'], methods: ['POST'])]
    public function createOrderLine(int $orderId, Request $request, Connection $connection): JsonResponse
    {
        $data = $this->body($request);
        $productId = filter_var($data['productId'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT);
        if (!$productId || !$quantity || $quantity < 1 || !$connection->fetchOne('SELECT idCmd FROM Commande WHERE idCmd = ?', [$orderId])) {
            return $this->json(['error' => 'Commande, produit ou quantité invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $connection->beginTransaction();
        try {
            $product = $connection->fetchAssociative('SELECT qteStock FROM Produit WHERE idProd = ? FOR UPDATE', [$productId]);
            if (!$product) {
                $connection->rollBack();
                return $this->json(['error' => 'Produit introuvable.'], Response::HTTP_NOT_FOUND);
            }
            if ((int) $product['qteStock'] < $quantity) {
                $connection->rollBack();
                return $this->json(['error' => 'Stock insuffisant.'], Response::HTTP_CONFLICT);
            }

            $existingQuantity = $connection->fetchOne('SELECT qteCmd FROM ligneCommande WHERE idCmd = ? AND idProd = ? FOR UPDATE', [$orderId, $productId]);
            if ($existingQuantity !== false) {
                $connection->update('ligneCommande', ['qteCmd' => (int) $existingQuantity + $quantity], ['idCmd' => $orderId, 'idProd' => $productId]);
            } else {
                $connection->insert('ligneCommande', ['idCmd' => $orderId, 'idProd' => $productId, 'qteCmd' => $quantity]);
            }
            $connection->executeStatement('UPDATE Produit SET qteStock = qteStock - ? WHERE idProd = ?', [$quantity, $productId]);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $this->json(['status' => 'created']);
    }

    #[Route('/api/v1/orders/{orderId}/lines/{productId}', name: 'api_order_line_delete', requirements: ['orderId' => '\\d+', 'productId' => '\\d+'], methods: ['DELETE'])]
    public function deleteOrderLine(int $orderId, int $productId, Connection $connection): JsonResponse
    {
        $quantity = $connection->fetchOne('SELECT qteCmd FROM ligneCommande WHERE idCmd = ? AND idProd = ?', [$orderId, $productId]);
        if ($quantity === false) {
            return $this->json(['error' => 'Ligne de commande introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $connection->beginTransaction();
        try {
            $connection->delete('ligneCommande', ['idCmd' => $orderId, 'idProd' => $productId]);
            $connection->executeStatement('UPDATE Produit SET qteStock = qteStock + ? WHERE idProd = ?', [(int) $quantity, $productId]);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $this->json(['status' => 'deleted', 'stockRestored' => (int) $quantity]);
    }

    private function body(Request $request): array
    {
        $data = json_decode($request->getContent(), true);
        return is_array($data) ? $data : [];
    }

    private function validateProduct(array $data): ?string
    {
        $name = trim((string) ($data['name'] ?? ''));
        $stock = filter_var($data['stock'] ?? null, FILTER_VALIDATE_INT);
        $price = filter_var($data['unitPrice'] ?? null, FILTER_VALIDATE_FLOAT);
        $photo = trim((string) ($data['photo'] ?? ''));
        if ($name === '' || mb_strlen($name) > 255 || $stock === false || $stock < 0
            || $price === false || !is_finite((float) $price) || $price < 0 || $price > 9999999999.99
            || ($photo !== '' && filter_var($photo, FILTER_VALIDATE_URL) === false)) {
            return 'Nom, stock, prix ou URL de photo invalide.';
        }
        return null;
    }

    private function validateCustomer(array $data, Connection $connection): ?string
    {
        $firstName = trim((string) ($data['firstName'] ?? ''));
        $lastName = trim((string) ($data['lastName'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $photo = trim((string) ($data['photo'] ?? ''));
        $regionId = ($data['regionId'] ?? '') === '' ? null : filter_var($data['regionId'], FILTER_VALIDATE_INT);
        $regionValid = $regionId === null || ($regionId !== false && $connection->fetchOne('SELECT idRegion FROM Region WHERE idRegion = ?', [$regionId]));
        if ($firstName === '' || $lastName === '' || mb_strlen($firstName) > 150 || mb_strlen($lastName) > 150
            || mb_strlen($email) > 190 || mb_strlen($photo) > 255
            || ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false)
            || ($photo !== '' && filter_var($photo, FILTER_VALIDATE_URL) === false) || !$regionValid) {
            return 'Nom, prénom, e-mail, région ou URL de photo invalide.';
        }
        return null;
    }

    private function customerValues(array $data): array
    {
        $regionId = ($data['regionId'] ?? '') === '' ? null : (int) $data['regionId'];
        return [
            'nomCli' => trim((string) $data['lastName']),
            'prenomCli' => trim((string) $data['firstName']),
            'email' => trim((string) ($data['email'] ?? '')) ?: null,
            'photo' => trim((string) ($data['photo'] ?? '')) ?: null,
            'idRegion' => $regionId,
        ];
    }

    private function validateOrder(array $data, Connection $connection): ?string
    {
        $customerId = filter_var($data['customerId'] ?? null, FILTER_VALIDATE_INT);
        $date = (string) ($data['date'] ?? '');
        $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$customerId || !$parsedDate || $parsedDate->format('Y-m-d') !== $date
            || !$connection->fetchOne('SELECT idCli FROM Client WHERE idCli = ?', [$customerId])) {
            return 'Client ou date de commande invalide.';
        }
        return null;
    }

    private function json(array $data, int $status = Response::HTTP_OK): JsonResponse
    {
        $response = new JsonResponse($data, $status);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        return $response;
    }
}