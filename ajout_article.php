<?php
session_start();
date_default_timezone_set('Europe/Paris');

// Accès réservé aux vendeurs ou admin
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['vendeur', 'admin'])) {
    header('Location: compte.php');
    exit();
}

$database = "agora";
$db_handle = mysqli_connect('localhost', 'root', 'root');
$db_found = mysqli_select_db($db_handle, $database);

$message = "";

// Charger les catégories pour le select
$categories = [];
if ($db_found) {
    $sql_cat = "SELECT id, nom FROM Categorie ORDER BY nom ASC";
    $result_cat = mysqli_query($db_handle, $sql_cat);
    while ($row = mysqli_fetch_assoc($result_cat)) {
        $categories[] = $row;
    }
}

// Traitement ajout article
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $db_found) {
    $titre = mysqli_real_escape_string($db_handle, trim($_POST['titre']));
    $description = mysqli_real_escape_string($db_handle, trim($_POST['description']));
    $prix = floatval($_POST['prix']);
    $categorie_id = intval($_POST['categorie_id']);
    $type_vente = mysqli_real_escape_string($db_handle, $_POST['type_vente']);
    $type_marchandise = mysqli_real_escape_string($db_handle, $_POST['type_marchandise']);
    $vendeur_id = intval($_SESSION['user_id']);

    // Champs spéciaux enchère
    $date_debut = null;
    $date_fin = null;
    $prix_actuel = $prix;

    if ($type_vente == "enchere") {
        // Conversion du format HTML5 "2025-05-31T20:00" en "2025-05-31 20:00:00"
        $date_debut = mysqli_real_escape_string($db_handle, $_POST['date_debut_enchere']);
        $date_fin = mysqli_real_escape_string($db_handle, $_POST['date_fin_enchere']);
        $date_debut = str_replace("T", " ", $date_debut) . ":00";
        $date_fin = str_replace("T", " ", $date_fin) . ":00";
    }

    // Gérer la photo (optionnelle, upload dans /images/)
    $photo = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $filename = uniqid("art_") . '.' . $ext;
        $dest = 'images/' . $filename;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
            $photo = $dest;
        }
    }

    // Construction de la requête SQL adaptée
    if ($type_vente == "enchere") {
        $sql = "INSERT INTO Article
            (titre, description, prix_initial, prix_actuel, categorie_id, vendeur_id, type_vente, type_marchandise, photo, statut, date_debut_enchere, date_fin_enchere)
            VALUES
            ('$titre', '$description', $prix, $prix_actuel, $categorie_id, $vendeur_id, '$type_vente', '$type_marchandise', '$photo', 'disponible', '$date_debut', '$date_fin')";
    } else {
        $sql = "INSERT INTO Article
            (titre, description, prix_initial, categorie_id, vendeur_id, type_vente, type_marchandise, photo, statut)
            VALUES
            ('$titre', '$description', $prix, $categorie_id, $vendeur_id, '$type_vente', '$type_marchandise', '$photo', 'disponible')";
    }
    if (mysqli_query($db_handle, $sql)) {
        $message = "Article ajouté avec succès !";
    } else {
        $message = "Erreur lors de l'ajout : " . mysqli_error($db_handle);
    }

    // --- DEBUG ALERTES ET NOTIFICATIONS ---
    $article_id = mysqli_insert_id($db_handle);
    $createur_id = intval($_SESSION['user_id']); // IMPORTANT !

    $sql_alertes = "SELECT * FROM Alerte";
    $result_alertes = mysqli_query($db_handle, $sql_alertes);

    while ($alerte = mysqli_fetch_assoc($result_alertes)) {
        // Debug chaque alerte
        error_log("DEBUG Alerte id: {$alerte['id']} user_id: {$alerte['user_id']}");

        if ($alerte['user_id'] == $createur_id) {
            error_log("DEBUG → skip, l'utilisateur est le créateur");
            continue;
        }

        $match = true;

        // Catégorie
        if ($alerte['categorie_id'] && $alerte['categorie_id'] != $categorie_id) {
            $match = false; error_log("DEBUG → no match sur categorie");
        }
        // Type de vente
        if ($alerte['type_vente'] && $alerte['type_vente'] != $type_vente) {
            $match = false; error_log("DEBUG → no match sur type_vente");
        }
        // Prix min
        if ($alerte['prix_min'] !== null && $alerte['prix_min'] !== "" && $prix < $alerte['prix_min']) {
            $match = false; error_log("DEBUG → no match sur prix_min");
        }
        // Prix max
        if ($alerte['prix_max'] !== null && $alerte['prix_max'] !== "" && $prix > $alerte['prix_max']) {
            $match = false; error_log("DEBUG → no match sur prix_max");
        }
        // Mots-clés (titre ou description)
        if ($alerte['mots_cles']) {
            $motcle = strtolower($alerte['mots_cles']);
            $champ = strtolower($titre . " " . $description);
            if (strpos($champ, $motcle) === false) {
                $match = false; error_log("DEBUG → no match sur mots_cles");
            }
        }

        error_log("DEBUG Alerte id: {$alerte['id']} match=" . ($match ? "OUI" : "NON"));
        if ($match) {
            $user_id = intval($alerte['user_id']);
            $contenu = "Un nouvel article correspondant à votre alerte est disponible : \"$titre\"";
            $contenu_sql = mysqli_real_escape_string($db_handle, $contenu);
            $date_notif = date('Y-m-d H:i:s');
            $sql_notif = "INSERT INTO Notification (user_id, contenu, date_creation, article_id)
                          VALUES ($user_id, '$contenu_sql', '$date_notif', $article_id)";
            $rNotif = mysqli_query($db_handle, $sql_notif);
            error_log("DEBUG Notif envoyée à user $user_id : " . ($rNotif ? "OUI" : "NON"));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un article | Agora Francia</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script>
    // Affiche/masque les champs enchère selon le type sélectionné
    function toggleEnchereFields() {
        var typeVente = document.getElementById('type_vente').value;
        var enchereFields = document.getElementById('enchere-fields');
        if (typeVente === "enchere") {
            enchereFields.style.display = "block";
        } else {
            enchereFields.style.display = "none";
        }
    }
    </script>
</head>
<body>
<div class="container">
    <h2 class="mb-4 text-primary text-center">Ajouter un article</h2>
    <?php if ($message): ?>
        <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <div class="mb-2"><input required name="titre" type="text" class="form-control" placeholder="Titre de l'article"></div>
        <div class="mb-2"><textarea required name="description" class="form-control" placeholder="Description"></textarea></div>
        <div class="mb-2"><input required name="prix" type="number" min="0" step="0.01" class="form-control" placeholder="Prix de départ (€)"></div>
        <div class="mb-2">
            <select name="categorie_id" class="form-control" required>
                <option value="">-- Catégorie --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= $cat['nom'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2">
            <select name="type_vente" id="type_vente" class="form-control" onchange="toggleEnchereFields()" required>
                <option value="">-- Type de vente --</option>
                <option value="immediat">Achat immédiat</option>
                <option value="negociation">Négociation</option>
                <option value="enchere">Enchère</option>
            </select>
        </div>
        <!-- Champs pour enchère, cachés par défaut -->
        <div class="mb-2" id="enchere-fields" style="display:none;">
            <label>Date de début de l'enchère :</label>
            <input type="datetime-local" name="date_debut_enchere" class="form-control" />
            <label>Date de fin de l'enchère :</label>
            <input type="datetime-local" name="date_fin_enchere" class="form-control" />
        </div>
        <div class="mb-2">
            <select name="type_marchandise" class="form-control" required>
                <option value="">-- Type de marchandise --</option>
                <option value="rare">Article rare</option>
                <option value="haut_de_gamme">Article haut de gamme</option>
                <option value="regulier">Article régulier</option>
            </select>
        </div>
        <div class="mb-2">
            <input type="file" name="photo" class="form-control">
            <small class="text-muted">Photo (optionnelle, jpg/png/gif)</small>
        </div>
        <button type="submit" class="btn btn-success w-100">Ajouter l'article</button>
    </form>
    <a href="index.php" class="btn btn-secondary mt-4">Retour à l'accueil</a>
</div>
<script>
    // Assure l'affichage correct au chargement si modification ou retour sur la page
    document.addEventListener('DOMContentLoaded', function() {
        toggleEnchereFields();
    });
</script>
</body>
</html>
