<?php
$id = $_GET['id'] ?? null;
/** TP5 – Formulaire partagé création / modification.
 *  GET ?id=XXX : charge l'étudiant à modifier ; sans id : formulaire vierge. */
require 'config.php';

$etu = array_fill_keys(CHAMPS, '');
if ($id !== null) {
    $stmt = $pdo->prepare('SELECT * FROM etudiant WHERE idEtu = ?');
    $stmt->execute([$id]);
    $etu = $stmt->fetch() ?: exit('Étudiant introuvable.');
}
$libelles = ['idEtu'=>'Matricule','nomEtu'=>'Nom','prenomEtu'=>'Prénom','sexeEtu'=>'Sexe',
  'dateNaiss'=>'Date de naissance','lieuNaiss'=>'Lieu de naissance','emailEtu'=>'Email',
  'numTelEtu'=>'Téléphone','regEtu'=>'Région','departEtu'=>'Département','arrondisEtu'=>'Arrondissement',
  'mentionEtu'=>'Mention','cycleEtu'=>'Cycle','ParcoursEtu'=>'Parcours'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $id ? 'Modifier' : 'Créer' ?> un étudiant</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width:680px">
  <h1 class="h3 text-primary mb-3"><?= $id ? 'Modifier' : 'Nouvel' ?> étudiant</h1>
  <form method="post" action="save.php" class="bg-white p-4 rounded shadow-sm">
    <input type="hidden" name="mode" value="<?= $id ? 'update' : 'create' ?>">
    <?php foreach (CHAMPS as $c): ?>
      <div class="mb-3">
        <label class="form-label" for="<?= $c ?>"><?= $libelles[$c] ?></label>
        <?php if ($c === 'sexeEtu'): ?>
          <select class="form-select" id="<?= $c ?>" name="<?= $c ?>" required <?= ($id && $c === 'idEtu') ? 'readonly' : '' ?>>
            <option value="">-- Choisir --</option>
            <option value="Masculin" <?= $etu[$c] === 'Masculin' ? 'selected' : '' ?>>Masculin</option>
            <option value="Féminin" <?= $etu[$c] === 'Féminin' ? 'selected' : '' ?>>Féminin</option>
            <option value="Autre" <?= $etu[$c] === 'Autre' ? 'selected' : '' ?>>Autre</option>
          </select>
        <?php else: ?>
          <input class="form-control" id="<?= $c ?>" name="<?= $c ?>"
                 type="<?= $c === 'dateNaiss' ? 'date' : ($c === 'emailEtu' ? 'email' : 'text') ?>"
                 value="<?= h($etu[$c]) ?>" required
                 <?= ($id && $c === 'idEtu') ? 'readonly' : '' ?>>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <button class="btn btn-primary">Enregistrer</button>
    <a class="btn btn-link" href="index.php">Annuler</a>
  </form>
</div>
</body>
</html>
