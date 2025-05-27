<?php
session_start();
include_once "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: compte.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$alerte_message = "";

// Charger toutes les catégories
$categories = [];
$sql_cat = "SELECT id, nom FROM Categorie ORDER BY nom ASC";
$res_cat = mysqli_query($db_handle, $sql_cat);
while ($row = mysqli_fetch_assoc($res_cat)) {
    $categories[] = $row;
}

// Traitement création d'alerte
if (isset($_POST['ajouter_alerte'])) {
    $categorie_id = !empty($_POST['categorie_id']) ? intval($_POST['categorie_id']) : null;
    $type_vente = !empty($_POST['type_vente']) ? mysqli_real_escape_string($db_handle, $_POST['type_vente']) : null;
    $mots_cles = !empty($_POST['mots_cles']) ? mysqli_real_escape_string($db_handle, $_POST['mots_cles']) : '';
    $prix_min = isset($_POST['prix_min']) && $_POST['prix_min'] !== '' ? floatval($_POST['prix_min']) : null;
    $prix_max = isset($_POST['prix_max']) && $_POST['prix_max'] !== '' ? floatval($_POST['prix_max']) : null;

    $sql = "INSERT INTO Alerte (user_id, categorie_id, type_vente, mots_cles, prix_min, prix_max, date_creation)
            VALUES ($user_id, " . ($categorie_id ? $categorie_id : "NULL") . ", " . ($type_vente ? "'$type_vente'" : "NULL") . ", '$mots_cles', " . ($prix_min !== null ? $prix_min : "NULL") . ", " . ($prix_max !== null ? $prix_max : "NULL") . ", NOW())";
    if (mysqli_query($db_handle, $sql)) {
        $alerte_message = "Alerte créée ! Vous serez notifié lorsqu'un article correspondant sera ajouté.";
    } else {
        $alerte_message = "Erreur lors de la création de l'alerte.";
    }
}

// Suppression d'une alerte
if (isset($_GET['suppr']) && is_numeric($_GET['suppr'])) {
    $id_alerte = intval($_GET['suppr']);
    mysqli_query($db_handle, "DELETE FROM Alerte WHERE id=$id_alerte AND user_id=$user_id");
    $alerte_message = "Alerte supprimée.";
}

// Récupère les alertes de l'utilisateur
$mes_alertes = [];
$res_alert = mysqli_query($db_handle, "SELECT * FROM Alerte WHERE user_id=$user_id ORDER BY date_creation DESC");
while ($row = mysqli_fetch_assoc($res_alert)) {
    $mes_alertes[] = $row;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes alertes | Agora Francia</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container my-4">
    <h2 class="mb-4 text-primary text-center">Mes alertes d'articles</h2>
    <a href="index.php" class="btn btn-secondary mb-3">&larr; Retour au menu</a>

    <?php if ($alerte_message): ?>
        <div class="alert alert-info"><?= $alerte_message ?></div>
    <?php endif; ?>

    <!-- Formulaire de création d'alerte -->
    <form method="post" class="mb-4 border rounded p-3 bg-light">
        <h5>Créer une alerte personnalisée</h5>
        <div class="row g-2">
            <div class="col-md-3">
                <select name="categorie_id" class="form-control">
                    <option value="">-- Catégorie --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= $cat['nom'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="type_vente" class="form-control">
                    <option value="">-- Type de vente --</option>
                    <option value="immediat">Achat immédiat</option>
                    <option value="negociation">Négociation</option>
                    <option value="enchere">Enchère</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="mots_cles" class="form-control" placeholder="Mots-clés (optionnel)">
            </div>
            <div class="col-md-1">
                <input type="number" step="0.01" name="prix_min" class="form-control" placeholder="Prix min">
            </div>
            <div class="col-md-1">
                <input type="number" step="0.01" name="prix_max" class="form-control" placeholder="Prix max">
            </div>
            <div class="col-md-1">
                <button type="submit" name="ajouter_alerte" class="btn btn-primary w-100">Alerter</button>
            </div>
        </div>
    </form>

    <h5 class="mb-3">Mes alertes actives</h5>
    <?php if (empty($mes_alertes)): ?>
        <div class="alert alert-secondary">Vous n'avez pas encore d'alerte enregistrée.</div>
    <?php else: ?>
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Catégorie</th>
                    <th>Type de vente</th>
                    <th>Mots-clés</th>
                    <th>Prix min</th>
                    <th>Prix max</th>
                    <th>Date création</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($mes_alertes as $a): ?>
                <tr>
                    <td>
                        <?php
                        if ($a['categorie_id']) {
                            $catname = "?";
                            foreach ($categories as $cat) {
                                if ($cat['id'] == $a['categorie_id']) $catname = $cat['nom'];
                            }
                            echo $catname;
                        } else {
                            echo "-";
                        }
                        ?>
                    </td>
                    <td><?= $a['type_vente'] ?: '-' ?></td>
                    <td><?= $a['mots_cles'] ?: '-' ?></td>
                    <td><?= $a['prix_min'] !== null ? number_format($a['prix_min'],2,',',' ') . ' €' : '-' ?></td>
                    <td><?= $a['prix_max'] !== null ? number_format($a['prix_max'],2,',',' ') . ' €' : '-' ?></td>
                    <td><?= $a['date_creation'] ?></td>
                    <td>
                        <a href="alertes.php?suppr=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer cette alerte ?')">Supprimer</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <a href="notifications.php" class="btn btn-outline-primary mt-3">Voir mes notifications</a>
</div>
</body>
</html>
