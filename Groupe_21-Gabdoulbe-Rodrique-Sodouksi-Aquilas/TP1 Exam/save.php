<?php
/** TP5 – CREATE et UPDATE : traitement du formulaire (méthode POST uniquement). */
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Méthode non autorisée.'); }

// Revalidation serveur : on ne fait jamais confiance au navigateur
$d = [];
foreach (CHAMPS as $c) {
    $d[$c] = trim($_POST[$c] ?? '');
    if ($d[$c] === '') { header('Location: index.php?err=' . urlencode("Champ manquant : $c")); exit; }
}
if (!filter_var($d['emailEtu'], FILTER_VALIDATE_EMAIL)) {
    header('Location: index.php?err=' . urlencode('Email invalide.')); exit;
}

try {
    if (($_POST['mode'] ?? '') === 'update') {
        // Le matricule sert de critère WHERE et n'est pas modifié
        $set = implode(', ', array_map(fn($c) => "$c = :$c", array_diff(CHAMPS, ['idEtu'])));
        $pdo->prepare("UPDATE etudiant SET $set WHERE idEtu = :idEtu")->execute($d);
    } else {
        $cols = implode(', ', CHAMPS);
        $params = implode(', ', array_map(fn($c) => ":$c", CHAMPS));
        $pdo->prepare("INSERT INTO etudiant ($cols) VALUES ($params)")->execute($d);
    }
    header('Location: index.php?ok=1');
} catch (PDOException $e) {
    error_log($e->getMessage());
    $msg = ($e->getCode() === '23000') ? 'Matricule ou email déjà utilisé.' : 'Erreur lors de l\'enregistrement.';
    header('Location: index.php?err=' . urlencode($msg));
}
