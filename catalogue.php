<?php
session_start();
date_default_timezone_set('Europe/Paris');

include_once "db.php";

// Filtre type de vente
$type_vente = isset($_GET['type_vente']) ? $_GET['type_vente'] : '';
$filtre_vente_sql = '';
if ($type_vente && in_array($type_vente, ['immediat','negociation','enchere'])) {
    $filtre_vente_sql = " AND type_vente='$type_vente'";
}

// Prépare les 3 lignes de types de marchandises
$types_marchandise = [
    'rare' => 'Articles rares',
    'haut_de_gamme' => 'Articles hautes de gamme',
    'regulier' => 'Articles réguliers'
];
$articles_par_type = [];
foreach ($types_marchandise as $type_code => $type_label) {
    $sql = "SELECT * FROM Article WHERE statut='disponible' AND type_marchandise='$type_code' $filtre_vente_sql ORDER BY date_creation DESC";
    $res = mysqli_query($db_handle, $sql);
    $articles_par_type[$type_label] = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $articles_par_type[$type_label][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tout Parcourir | Agora Francia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container my-4">
    <h1 class="mb-4 text-center text-primary">Tout Parcourir</h1>
    <a href="index.php" class="btn btn-secondary mb-3">
    &larr; Retour au menu
</a>


    <!-- Filtres par type de vente -->
    <form method="get" class="mb-4">
        <div class="row justify-content-center">
            <div class="col-auto">
                <select name="type_vente" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Type d'achat --</option>
                    <option value="immediat" <?= ($type_vente=='immediat'?'selected':'') ?>>Achat immédiat</option>
                    <option value="negociation" <?= ($type_vente=='negociation'?'selected':'') ?>>Négociation vendeur-client</option>
                    <option value="enchere" <?= ($type_vente=='enchere'?'selected':'') ?>>Meilleure offre (enchère)</option>
                </select>
            </div>
        </div>
    </form>

    <?php foreach ($articles_par_type as $type_label => $liste): ?>
        <h3 class="mt-4 mb-3"><?= $type_label ?></h3>
        <div class="row">
            <?php if (empty($liste)): ?>
                <div class="col-12 text-muted"><em>Aucun article dans cette catégorie.</em></div>
            <?php else: ?>
                <?php foreach ($liste as $art): ?>
                    <div class="col-md-4 col-lg-3 mb-4">
                        <div class="card h-100 shadow-sm">
                            <img src="<?= $art['photo'] ?: 'images/placeholder.png' ?>" class="card-img-top" style="max-height:110px;object-fit:contain;">
                            <div class="card-body">
                                <h6 class="card-title"><?= $art['titre'] ?></h6>
                                <span class="badge bg-info mb-1"><?= ucfirst($art['type_vente']) ?></span>
                                <p class="mb-2"><?= number_format($art['prix_initial'],2,',',' ') ?> €</p>
                                <a href="article.php?id=<?= $art['id'] ?>" class="btn btn-outline-primary btn-sm w-100">Voir</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
