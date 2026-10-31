<?php
/** TP5 – DELETE : suppression par POST après confirmation côté navigateur. */
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Méthode non autorisée.'); }

$stmt = $pdo->prepare('DELETE FROM etudiant WHERE idEtu = ?');
$stmt->execute([$_POST['idEtu'] ?? '']);
header('Location: index.php?ok=1');
