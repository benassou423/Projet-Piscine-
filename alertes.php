<?php
session_start(); // Démarre une session PHP 
include_once "db.php"; // Inclusion du fichier de connexion à la base de données

// Vérifie que l'utilisateur est bien connecté, sinon redirige vers la page de connexion
if (!isset($_SESSION['user_id'])) {
    header("Location: compte.php"); // Redirection vers la page de connexion
    exit(); 
}

$user_id = intval($_SESSION['user_id']); // Récupère l'ID utilisateur connecté, en sécurisant avec intval
$alerte_message = ""; // Initialisation du message d’alerte

// ----- CHARGEMENT DES CATÉGORIES POUR LE FORMULAIRE -----

$categories = []; // Tableau vide pour stocker les catégories
$sql_cat = "SELECT id, nom FROM Categorie ORDER BY nom ASC"; // Requête pour récupérer toutes les catégories
$res_cat = mysqli_query($db_handle, $sql_cat); // Exécution de la requête

// Remplit le tableau $categories avec les résultats
while ($row = mysqli_fetch_assoc($res_cat)) {
    $categories[] = $row; // Ajoute chaque ligne (catégorie) au tableau
}

// ----- TRAITEMENT DE LA CRÉATION D’UNE ALERTE -----

if (isset($_POST['ajouter_alerte'])) { // Si l'utilisateur a soumis le formulaire d'alerte
    // Récupère les champs du formulaire, avec vérifications et sécurisations
    
    $categorie_id = !empty($_POST['categorie_id']) ? intval($_POST['categorie_id']) : null; // ID catégorie, ou null
    $type_vente = !empty($_POST['type_vente']) ? mysqli_real_escape_string($db_handle, $_POST['type_vente']) : null; // Type de vente échappé ou null
    $mots_cles = !empty($_POST['mots_cles']) ? mysqli_real_escape_string($db_handle, $_POST['mots_cles']) : ''; // Mots-clés échappés ou chaîne vide
    $prix_min = isset($_POST['prix_min']) && $_POST['prix_min'] !== '' ? floatval($_POST['prix_min']) : null; // Prix minimum ou null
    $prix_max = isset($_POST['prix_max']) && $_POST['prix_max'] !== '' ? floatval($_POST['prix_max']) : null; // Prix maximum ou null

    // Prépare la requête d’insertion dans la table Alerte avec les données récupérées
    $sql = "INSERT INTO Alerte (user_id, categorie_id, type_vente, mots_cles, prix_min, prix_max, date_creation)
            VALUES ($user_id, " . ($categorie_id ? $categorie_id : "NULL") . ", " . ($type_vente ? "'$type_vente'" : "NULL") . ", '$mots_cles', " . ($prix_min !== null ? $prix_min : "NULL") . ", " . ($prix_max !== null ? $prix_max : "NULL") . ", NOW())";
    
    // Exécute la requête d’insertion et affiche un message en fonction du résultat
    if (mysqli_query($db_handle, $sql)) {
        $alerte_message = "Alerte créée ! Vous serez notifié lorsqu'un article correspondant sera ajouté."; // Succès
    } else {
        $alerte_message = "Erreur lors de la création de l'alerte."; // Échec
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
<head>
    <meta charset="UTF-8"> 
    <title>Mes alertes | Agora Francia</title> <!-- Titre de l'onglet du navigateur -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"> <!-- Inclusion de Bootstrap -->
    <link rel="stylesheet" href="css/style-site.css"> <!-- Inclusion du fichier CSS personnalisé -->
</head>
<body>
<?php include 'header.php'; ?> <!-- Inclusion du header (barre de navigation) -->

<div class="container my-4"> <!-- Conteneur Bootstrap avec marge verticale -->
    <h2 class="mb-4 text-primary text-center">Mes alertes d'articles</h2> <!-- Titre principal centré avec couleur bleue -->
    <a href="index.php" class="btn btn-secondary mb-3">&larr; Retour au menu</a> <!-- Bouton de retour vers la page d'accueil -->

    <?php if ($alerte_message): ?> <!-- Si un message d'alerte existe, on l'affiche -->
        <div class="alert alert-info"><?= $alerte_message ?></div> <!-- Message d'information (succès ou erreur) -->
    <?php endif; ?>

    <!-- Formulaire de création d'alerte -->
    <form method="post" class="mb-4 border rounded p-3 bg-light"> <!-- Formulaire POST avec style Bootstrap -->
        <h5>Créer une alerte personnalisée</h5> <!-- Sous-titre -->
        <div class="row g-2"> <!-- Ligne Bootstrap avec espacement entre colonnes -->

            <div class="col-md-3"> <!-- Colonne pour le champ catégorie -->
                <select name="categorie_id" class="form-control"> <!-- Liste déroulante des catégories -->
                    <option value="">-- Catégorie --</option> <!-- Option par défaut -->
                    <?php foreach ($categories as $cat): ?> <!-- Boucle sur toutes les catégories -->
                        <option value="<?= $cat['id'] ?>"><?= $cat['nom'] ?></option> <!-- Affichage de chaque option -->
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3"> <!-- Colonne pour le champ type de vente -->
                <select name="type_vente" class="form-control"> <!-- Liste déroulante pour le type de vente -->
                    <option value="">-- Type de vente --</option> <!-- Option par défaut -->
                    <option value="immediat">Achat immédiat</option> <!-- Option achat immédiat -->
                    <option value="negociation">Négociation</option> <!-- Option négociation -->
                    <option value="enchere">Enchère</option> <!-- Option enchère -->
                </select>
            </div>

            <div class="col-md-3">
    <input type="text" name="mots_cles" class="form-control" placeholder="Mots-clés (optionnel)">
    <!-- Champ texte pour ajouter des mots-clés optionnels dans l'alerte -->
</div>
<div class="col-md-1">
    <input type="number" step="0.01" name="prix_min" class="form-control" placeholder="Prix min">
    <!-- Champ numérique pour définir un prix minimum -->
</div>
<div class="col-md-1">
    <input type="number" step="0.01" name="prix_max" class="form-control" placeholder="Prix max">
    <!-- Champ numérique pour définir un prix maximum -->
</div>
<div class="col-md-1">
    <button type="submit" name="ajouter_alerte" class="btn btn-primary w-100">Alerter</button>
    <!-- Bouton pour soumettre le formulaire et créer l'alerte -->
</div>
</div> <!-- Fin de la ligne contenant tous les champs du formulaire -->
</form> <!-- Fin du formulaire de création d'alerte -->

<h5 class="mb-3">Mes alertes actives</h5> <!-- Titre pour la section des alertes enregistrées -->

<?php if (empty($mes_alertes)): ?> <!-- Si aucune alerte enregistrée -->
    <div class="alert alert-secondary">Vous n'avez pas encore d'alerte enregistrée.</div>
<?php else: ?> <!-- Sinon, affichage des alertes existantes -->
    <table class="table table-bordered align-middle"> <!-- Table Bootstrap avec bordures -->
        <thead class="table-light"> <!-- En-tête claire -->
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
        <?php foreach ($mes_alertes as $a): ?> <!-- Boucle sur chaque alerte enregistrée -->
            <tr>
                <td>
                    <?php
                    if ($a['categorie_id']) {
                        $catname = "?"; // Valeur par défaut si jamais non trouvé
                        foreach ($categories as $cat) { // Recherche du nom de la catégorie associée
                            if ($cat['id'] == $a['categorie_id']) $catname = $cat['nom'];
                        }
                        echo $catname;
                    } else {
                        echo "-"; // Aucun ID de catégorie spécifié
                    }
                    ?>
                </td>
                <td><?= $a['type_vente'] ?: '-' ?></td> <!-- Type de vente ou tiret si vide -->
                <td><?= $a['mots_cles'] ?: '-' ?></td> <!-- Mots-clés ou tiret si vide -->
                <td><?= $a['prix_min'] !== null ? number_format($a['prix_min'],2,',',' ') . ' €' : '-' ?></td> <!-- Affiche prix min formaté ou tiret -->
                <td><?= $a['prix_max'] !== null ? number_format($a['prix_max'],2,',',' ') . ' €' : '-' ?></td> <!-- Affiche prix max formaté ou tiret -->
                <td><?= $a['date_creation'] ?></td> <!-- Date de création de l'alerte -->
                <td>
                    <a href="alertes.php?suppr=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer cette alerte ?')">Supprimer</a>
                    <!-- Lien pour supprimer l'alerte, avec confirmation JavaScript -->
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<a href="notifications.php" class="btn btn-outline-primary mt-3">Voir mes notifications</a>
<!-- Lien vers la page des notifications de l’utilisateur -->

</div> <!-- Fin du conteneur principal -->

<?php include 'footer.php'; ?> <!-- Inclusion du pied de page -->
</body>
</html>
