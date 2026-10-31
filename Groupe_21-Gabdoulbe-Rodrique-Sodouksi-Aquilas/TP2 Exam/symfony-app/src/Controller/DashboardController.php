<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(Connection $connection): Response
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
        $topProducts = $connection->fetchAllAssociative(
            'SELECT p.designProd, SUM(l.qteCmd) AS units, SUM(l.qteCmd * p.prixUProd) AS revenue
             FROM ligneCommande l INNER JOIN Produit p ON p.idProd = l.idProd
             GROUP BY p.idProd, p.designProd ORDER BY units DESC LIMIT 5'
        );
        $recentOrders = $connection->fetchAllAssociative(
            'SELECT c.idCmd, c.dateCmd, cl.nomCli, cl.prenomCli,
                    COALESCE(SUM(l.qteCmd * p.prixUProd), 0) AS total
             FROM Commande c INNER JOIN Client cl ON cl.idCli = c.idCli
             LEFT JOIN ligneCommande l ON l.idCmd = c.idCmd
             LEFT JOIN Produit p ON p.idProd = l.idProd
             GROUP BY c.idCmd, c.dateCmd, cl.nomCli, cl.prenomCli
             ORDER BY c.dateCmd DESC LIMIT 6'
        );
        $monthlyRows = $connection->fetchAllAssociative(
            'SELECT DATE_FORMAT(c.dateCmd, "%Y-%m") AS month, SUM(l.qteCmd * p.prixUProd) AS revenue
             FROM Commande c INNER JOIN ligneCommande l ON l.idCmd = c.idCmd
             INNER JOIN Produit p ON p.idProd = l.idProd
             WHERE c.dateCmd >= DATE_SUB(DATE_FORMAT(CURRENT_DATE(), "%Y-%m-01"), INTERVAL 5 MONTH)
             GROUP BY month ORDER BY month'
        );
        $monthlyRevenue = [];
        foreach ($monthlyRows as $row) {
            $monthlyRevenue[$row['month']] = (float) $row['revenue'];
        }
        $monthlySales = [];
        $currentMonth = new \DateTimeImmutable('first day of this month');
        for ($monthOffset = 5; $monthOffset >= 0; --$monthOffset) {
            $month = $currentMonth->modify('-' . $monthOffset . ' months');
            $key = $month->format('Y-m');
            $monthlySales[] = [
                'label' => $month->format('m/Y'),
                'revenue' => $monthlyRevenue[$key] ?? 0.0,
            ];
        }
        $topCustomers = $connection->fetchAllAssociative(
            'SELECT cl.idCli, cl.nomCli, cl.prenomCli, COUNT(DISTINCT c.idCmd) AS order_count,
                    COALESCE(SUM(l.qteCmd * p.prixUProd), 0) AS revenue
             FROM Client cl INNER JOIN Commande c ON c.idCli = cl.idCli
             LEFT JOIN ligneCommande l ON l.idCmd = c.idCmd
             LEFT JOIN Produit p ON p.idProd = l.idProd
             GROUP BY cl.idCli, cl.nomCli, cl.prenomCli
             ORDER BY revenue DESC LIMIT 5'
        );
        $lowStock = $connection->fetchAllAssociative(
            'SELECT idProd, designProd, qteStock FROM Produit
             WHERE qteStock < 5 ORDER BY qteStock, designProd LIMIT 6'
        );
        $maxMonthlyRevenue = max(array_column($monthlySales, 'revenue')) ?: 1;
        foreach ($monthlySales as &$month) {
            $month['percentage'] = round($month['revenue'] / $maxMonthlyRevenue * 100);
        }
        unset($month);

        return $this->render('dashboard/index.html.twig', [
            'summary' => $summary,
            'topProducts' => $topProducts,
            'recentOrders' => $recentOrders,
            'monthlySales' => $monthlySales,
            'topCustomers' => $topCustomers,
            'lowStock' => $lowStock,
        ]);
    }

    #[Route('/produits', name: 'app_products', methods: ['GET'])]
    public function products(Connection $connection): Response
    {
        return $this->render('product/index.html.twig', [
            'products' => $connection->fetchAllAssociative('SELECT idProd, designProd, qteStock, prixUProd, photoProd FROM Produit ORDER BY designProd'),
        ]);
    }

    #[Route('/produits/nouveau', name: 'app_product_new', methods: ['GET', 'POST'])]
    #[Route('/produits/{id}/modifier', name: 'app_product_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function productForm(Request $request, Connection $connection, ?int $id = null): Response
    {
        $product = $id === null ? null : $connection->fetchAssociative('SELECT * FROM Produit WHERE idProd = ?', [$id]);
        if ($id !== null && !$product) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('product_form', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton de formulaire invalide.');
            }
            $design = trim((string) $request->request->get('designProd'));
            $stock = filter_var($request->request->get('qteStock'), FILTER_VALIDATE_INT);
            $price = filter_var($request->request->get('prixUProd'), FILTER_VALIDATE_FLOAT);
            $photo = trim((string) $request->request->get('photoProd'));
            $photoValid = $photo === '' || filter_var($photo, FILTER_VALIDATE_URL) !== false;
            if ($design === '' || mb_strlen($design) > 255 || $stock === false || $stock < 0
                || $price === false || !is_finite((float) $price) || $price < 0 || $price > 9999999999.99
                || !$photoValid) {
                $this->addFlash('error', 'Vérifiez le libellé, le stock, le prix et l’URL de la photo.');
            } else {
                $values = ['designProd' => $design, 'qteStock' => $stock, 'prixUProd' => $price, 'photoProd' => $photo ?: null];
                if ($id === null) {
                    $connection->insert('Produit', $values);
                    $this->addFlash('success', 'Le produit a été créé.');
                } else {
                    $connection->update('Produit', $values, ['idProd' => $id]);
                    $this->addFlash('success', 'Le produit a été modifié.');
                }
                return $this->redirectToRoute('app_products');
            }
        }

        return $this->render('product/form.html.twig', ['product' => $product]);
    }

    #[Route('/commandes', name: 'app_orders', methods: ['GET'])]
    public function orders(Connection $connection): Response
    {
        $orders = $connection->fetchAllAssociative(
            'SELECT c.idCmd, c.dateCmd, cl.nomCli, cl.prenomCli, cl.email,
                    COUNT(l.idProd) AS product_count, COALESCE(SUM(l.qteCmd), 0) AS units,
                    COALESCE(SUM(l.qteCmd * p.prixUProd), 0) AS total
             FROM Commande c INNER JOIN Client cl ON cl.idCli = c.idCli
             LEFT JOIN ligneCommande l ON l.idCmd = c.idCmd
             LEFT JOIN Produit p ON p.idProd = l.idProd
             GROUP BY c.idCmd, c.dateCmd, cl.nomCli, cl.prenomCli, cl.email
             ORDER BY c.dateCmd DESC'
        );
        $lines = $connection->fetchAllAssociative(
            'SELECT l.idCmd, l.idProd, l.qteCmd, p.designProd, p.prixUProd
             FROM ligneCommande l INNER JOIN Produit p ON p.idProd = l.idProd'
        );
        $linesByOrder = [];
        foreach ($lines as $line) {
            $linesByOrder[$line['idCmd']][] = $line;
        }
        foreach ($orders as &$order) {
            $order['lines'] = $linesByOrder[$order['idCmd']] ?? [];
        }
        unset($order);

        return $this->render('order/index.html.twig', [
            'orders' => $orders,
            'products' => $connection->fetchAllAssociative('SELECT idProd, designProd, qteStock FROM Produit WHERE qteStock > 0 ORDER BY designProd'),
        ]);
    }

    #[Route('/commandes/nouvelle', name: 'app_order_new', methods: ['GET', 'POST'])]
    #[Route('/commandes/{id}/modifier', name: 'app_order_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function orderForm(Request $request, Connection $connection, ?int $id = null): Response
    {
        $order = $id === null ? null : $connection->fetchAssociative('SELECT idCmd, dateCmd, idCli FROM Commande WHERE idCmd = ?', [$id]);
        if ($id !== null && !$order) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        $formData = [
            'idCli' => $order['idCli'] ?? '',
            'dateCmd' => isset($order['dateCmd']) ? substr((string) $order['dateCmd'], 0, 10) : date('Y-m-d'),
        ];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('order_form', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton de formulaire invalide.');
            }
            $formData = [
                'idCli' => trim((string) $request->request->get('idCli')),
                'dateCmd' => trim((string) $request->request->get('dateCmd')),
            ];
            $customerId = filter_var($formData['idCli'], FILTER_VALIDATE_INT);
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $formData['dateCmd']);
            if (!$customerId || !$date || $date->format('Y-m-d') !== $formData['dateCmd']
                || !$connection->fetchOne('SELECT idCli FROM Client WHERE idCli = ?', [$customerId])) {
                $this->addFlash('error', 'Sélectionnez un client existant et une date valide.');
            } else {
                $values = ['idCli' => $customerId, 'dateCmd' => $date->format('Y-m-d 00:00:00')];
                if ($id === null) {
                    $connection->insert('Commande', $values);
                    $this->addFlash('success', 'La commande a été créée.');
                } else {
                    $connection->update('Commande', $values, ['idCmd' => $id]);
                    $this->addFlash('success', 'La commande a été modifiée.');
                }
                return $this->redirectToRoute('app_orders');
            }
        }

        return $this->render('order/form.html.twig', [
            'order' => $order,
            'formData' => $formData,
            'customers' => $connection->fetchAllAssociative('SELECT idCli, nomCli, prenomCli FROM Client ORDER BY nomCli, prenomCli'),
        ]);
    }

    #[Route('/commandes/{id}', name: 'app_order_detail', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function orderDetail(int $id, Connection $connection): Response
    {
        $order = $connection->fetchAssociative(
            'SELECT c.idCmd, c.dateCmd, cl.idCli, cl.nomCli, cl.prenomCli, cl.email,
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
            throw $this->createNotFoundException('Commande introuvable.');
        }

        return $this->render('order/detail.html.twig', [
            'order' => $order,
            'lines' => $connection->fetchAllAssociative(
                'SELECT l.idProd, l.qteCmd, p.designProd, p.prixUProd,
                        l.qteCmd * p.prixUProd AS line_total
                 FROM ligneCommande l INNER JOIN Produit p ON p.idProd = l.idProd
                 WHERE l.idCmd = ? ORDER BY p.designProd',
                [$id]
            ),
        ]);
    }

    #[Route('/commandes/{id}/supprimer', name: 'app_order_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function deleteOrder(int $id, Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('delete_order_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de formulaire invalide.');
        }
        $lines = $connection->fetchAllAssociative('SELECT idProd, qteCmd FROM ligneCommande WHERE idCmd = ?', [$id]);
        if (!$connection->fetchOne('SELECT idCmd FROM Commande WHERE idCmd = ?', [$id])) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        $connection->beginTransaction();
        try {
            $connection->delete('Commande', ['idCmd' => $id]);
            foreach ($lines as $line) {
                $connection->executeStatement(
                    'UPDATE Produit SET qteStock = qteStock + ? WHERE idProd = ?',
                    [(int) $line['qteCmd'], (int) $line['idProd']]
                );
            }
            $connection->commit();
            $this->addFlash('success', 'La commande a été supprimée et les stocks ont été restitués.');
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $this->redirectToRoute('app_orders');
    }

    #[Route('/clients', name: 'app_customers', methods: ['GET'])]
    public function customers(Connection $connection): Response
    {
        return $this->render('customer/index.html.twig', [
            'customers' => $connection->fetchAllAssociative(
                'SELECT cl.idCli, cl.nomCli, cl.prenomCli, cl.email, r.nomRegion,
                        COUNT(c.idCmd) AS order_count
                 FROM Client cl LEFT JOIN Region r ON r.idRegion = cl.idRegion
                 LEFT JOIN Commande c ON c.idCli = cl.idCli
                 GROUP BY cl.idCli, cl.nomCli, cl.prenomCli, cl.email, r.nomRegion
                 ORDER BY cl.nomCli, cl.prenomCli'
            ),
        ]);
    }

    #[Route('/clients/nouveau', name: 'app_customer_new', methods: ['GET', 'POST'])]
    #[Route('/clients/{id}/modifier', name: 'app_customer_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function customerForm(Request $request, Connection $connection, ?int $id = null): Response
    {
        $customer = $id === null ? null : $connection->fetchAssociative('SELECT * FROM Client WHERE idCli = ?', [$id]);
        if ($id !== null && !$customer) {
            throw $this->createNotFoundException('Client introuvable.');
        }
        $formData = [
            'nomCli' => $customer['nomCli'] ?? '',
            'prenomCli' => $customer['prenomCli'] ?? '',
            'email' => $customer['email'] ?? '',
            'photo' => $customer['photo'] ?? '',
            'idRegion' => $customer['idRegion'] ?? '',
        ];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('customer_form', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton de formulaire invalide.');
            }
            foreach (array_keys($formData) as $field) {
                $formData[$field] = trim((string) $request->request->get($field));
            }
            $regionId = $formData['idRegion'] === '' ? null : filter_var($formData['idRegion'], FILTER_VALIDATE_INT);
            $emailValid = $formData['email'] === '' || filter_var($formData['email'], FILTER_VALIDATE_EMAIL) !== false;
            $regionValid = $regionId === null || ($regionId !== false && $connection->fetchOne('SELECT idRegion FROM Region WHERE idRegion = ?', [$regionId]));
            $photoValid = $formData['photo'] === '' || filter_var($formData['photo'], FILTER_VALIDATE_URL) !== false;
            if ($formData['nomCli'] === '' || $formData['prenomCli'] === ''
                || mb_strlen($formData['nomCli']) > 150 || mb_strlen($formData['prenomCli']) > 150
                || mb_strlen($formData['email']) > 190 || mb_strlen($formData['photo']) > 255
                || !$emailValid || !$regionValid || !$photoValid) {
                $this->addFlash('error', 'Vérifiez le nom, le prénom, l’adresse e-mail, la région et l’URL de la photo.');
            } else {
                $values = [
                    'nomCli' => $formData['nomCli'],
                    'prenomCli' => $formData['prenomCli'],
                    'email' => $formData['email'] ?: null,
                    'photo' => $formData['photo'] ?: null,
                    'idRegion' => $regionId ?: null,
                ];
                if ($id === null) {
                    $connection->insert('Client', $values);
                    $this->addFlash('success', 'Le client a été créé.');
                } else {
                    $connection->update('Client', $values, ['idCli' => $id]);
                    $this->addFlash('success', 'Le client a été modifié.');
                }
                return $this->redirectToRoute('app_customers');
            }
        }

        return $this->render('customer/form.html.twig', [
            'customer' => $customer,
            'formData' => $formData,
            'regions' => $connection->fetchAllAssociative('SELECT idRegion, nomRegion FROM Region ORDER BY nomRegion'),
        ]);
    }

    #[Route('/clients/{id}/supprimer', name: 'app_customer_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function deleteCustomer(int $id, Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('delete_customer_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de formulaire invalide.');
        }
        if (!$connection->fetchOne('SELECT idCli FROM Client WHERE idCli = ?', [$id])) {
            throw $this->createNotFoundException('Client introuvable.');
        }
        if ($connection->fetchOne('SELECT idCmd FROM Commande WHERE idCli = ? LIMIT 1', [$id])) {
            $this->addFlash('error', 'Ce client possède des commandes. Supprimez ou réattribuez-les avant de supprimer le client.');
        } else {
            $connection->delete('Client', ['idCli' => $id]);
            $this->addFlash('success', 'Le client a été supprimé.');
        }

        return $this->redirectToRoute('app_customers');
    }

    #[Route('/commandes/{orderId}/lignes/ajouter', name: 'app_order_line_add', requirements: ['orderId' => '\\d+'], methods: ['POST'])]
    public function addOrderLine(int $orderId, Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('order_line_' . $orderId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de formulaire invalide.');
        }
        $productId = filter_var($request->request->get('idProd'), FILTER_VALIDATE_INT);
        $quantity = filter_var($request->request->get('qteCmd'), FILTER_VALIDATE_INT);
        if (!$connection->fetchOne('SELECT idCmd FROM Commande WHERE idCmd = ?', [$orderId]) || !$productId || !$quantity || $quantity < 1) {
            $this->addFlash('error', 'Sélectionnez une commande, un produit et une quantité valide.');
            return $this->redirectToRoute('app_orders');
        }

        $connection->beginTransaction();
        try {
            $product = $connection->fetchAssociative('SELECT qteStock FROM Produit WHERE idProd = ? FOR UPDATE', [$productId]);
            if (!$product) {
                throw new \DomainException('Le produit sélectionné est introuvable.');
            }
            if ((int) $product['qteStock'] < $quantity) {
                throw new \DomainException('Le stock est insuffisant pour cette quantité.');
            }
            $requestedQuantity = $quantity;
            $existingQuantity = $connection->fetchOne('SELECT qteCmd FROM ligneCommande WHERE idCmd = ? AND idProd = ? FOR UPDATE', [$orderId, $productId]);
            if ($existingQuantity !== false) {
                $quantity += (int) $existingQuantity;
                $connection->update('ligneCommande', ['qteCmd' => $quantity], ['idCmd' => $orderId, 'idProd' => $productId]);
            } else {
                $connection->insert('ligneCommande', ['idCmd' => $orderId, 'idProd' => $productId, 'qteCmd' => $quantity]);
            }
            $connection->executeStatement('UPDATE Produit SET qteStock = qteStock - ? WHERE idProd = ?', [$requestedQuantity, $productId]);
            $connection->commit();
            $this->addFlash('success', 'Le produit a été ajouté à la commande.');
        } catch (\Throwable $exception) {
            $connection->rollBack();
            $this->addFlash('error', $exception instanceof \DomainException ? $exception->getMessage() : 'Impossible de mettre à jour cette commande.');
        }

        return $this->redirectToRoute('app_orders');
    }

    #[Route('/commandes/{orderId}/lignes/{productId}/supprimer', name: 'app_order_line_delete', requirements: ['orderId' => '\\d+', 'productId' => '\\d+'], methods: ['POST'])]
    public function deleteOrderLine(int $orderId, int $productId, Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('delete_line_' . $orderId . '_' . $productId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de formulaire invalide.');
        }
        $quantity = $connection->fetchOne('SELECT qteCmd FROM ligneCommande WHERE idCmd = ? AND idProd = ?', [$orderId, $productId]);
        if ($quantity !== false) {
            $connection->beginTransaction();
            try {
                $connection->delete('ligneCommande', ['idCmd' => $orderId, 'idProd' => $productId]);
                $connection->executeStatement('UPDATE Produit SET qteStock = qteStock + ? WHERE idProd = ?', [(int) $quantity, $productId]);
                $connection->commit();
                $this->addFlash('success', 'Le produit a été retiré de la commande et le stock restauré.');
            } catch (\Throwable $exception) {
                $connection->rollBack();
                throw $exception;
            }
        }

        return $this->redirectToRoute('app_orders');
    }
}