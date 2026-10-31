<?php
/**
 * TP5 – Connexion PDO à MySQL (XAMPP : utilisateur root, mot de passe vide).
 * PDO + requêtes préparées = protection contre les injections SQL.
 * La base utilisée est : universite
 */
declare(strict_types=1);

$dsn = 'mysql:host=localhost;dbname=universite;charset=utf8mb4';
try {
    $pdo = new PDO($dsn, 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log($e->getMessage());
    exit('Connexion à la base impossible.');
}

/** Échappement HTML pour tout affichage (protection contre le XSS). */
function h(?string $valeur): string {
    return htmlspecialchars($valeur ?? '', ENT_QUOTES, 'UTF-8');
}

/** Liste des champs du formulaire étudiant. */
const CHAMPS = ['idEtu','nomEtu','prenomEtu','sexeEtu','dateNaiss','lieuNaiss','emailEtu','numTelEtu',
                'regEtu','departEtu','arrondisEtu','mentionEtu','cycleEtu','ParcoursEtu'];
