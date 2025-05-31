<?php
session_start(); // Démarre une session PHP pour accéder aux variables de session

// Accès réservé aux vendeurs ou administrateurs
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['vendeur', 'admin'])) {
    header('Location: compte.php'); // Redirige l'utilisateur vers la page compte s'il n'est pas autorisé
    exit(); // Arrête l'exécution du script après redirection
}

$database = "agora"; // Nom de la base de données
$db_handle = mysqli_connect('localhost', 'root', ''); // Connexion au serveur MySQL (localhost avec utilisateur root)
$db_found = mysqli_select_db($db_handle, $database); // Sélection de la base de données "agora"

$message = ""; // Variable pour stocker un éventuel message de retour (succès ou erreur)

// Charger les catégories pour le menu déroulant
$categories = []; // Initialise un tableau vide pour stocker les catégories
if ($db_found) {
    $sql_cat = "SELECT id, nom FROM Categorie ORDER BY nom ASC"; // Requête SQL pour récupérer les catégories triées par nom
    $result_cat = mysqli_query($db_handle, $sql_cat); // Exécute la requête SQL
    while ($row = mysqli_fetch_assoc($result_cat)) {
        $categories[] = $row; // Ajoute chaque catégorie au tableau $categories
    }
}

// Traitement du formulaire d'ajout d'article
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $db_found) {

    // Sécurise et récupère les données saisies dans le formulaire
    $titre = mysqli_real_escape_string($db_handle, trim($_POST['titre'])); // Titre de l'article (échappé et nettoyé)

    $description = mysqli_real_escape_string($db_handle, trim($_POST['description'])); // Description (échappée et nettoyée)

    $prix = floatval($_POST['prix']); // Prix de départ de l'article, converti en float
    $categorie_id = intval($_POST['categorie_id']); // ID de la catégorie sélectionnée, converti en entier
    $type_vente = mysqli_real_escape_string($db_handle, $_POST['type_vente']); // Type de vente (immédiat, enchère, etc.)
    $type_marchandise = mysqli_real_escape_string($db_handle, $_POST['type_marchandise']); // Type de marchandise (rare, haut de gamme, etc.)
    $vendeur_id = intval($_SESSION['user_id']); // ID du vendeur actuel, récupéré depuis la session

    // Préparation des variables spécifiques si l’article est une enchère
    $date_debut = null; // Initialisation à null : sera défini si enchère
    $date_fin = null; // Idem pour la date de fin
    $prix_actuel = $prix; // Le prix actuel est initialisé avec le prix de départ (utile pour enchères)

    if ($type_vente == "enchere") {

        // Récupère les dates d’enchère depuis le formulaire
        $date_debut = mysqli_real_escape_string($db_handle, $_POST['date_debut_enchere']);
        $date_fin = mysqli_real_escape_string($db_handle, $_POST['date_fin_enchere']);

        // Transforme le format HTML5 (ex : "2025-05-31T20:00") en format SQL ("2025-05-31 20:00:00")
        $date_debut = str_replace("T", " ", $date_debut) . ":00";
        $date_fin = str_replace("T", " ", $date_fin) . ":00";
    }


    // Gérer la photo (optionnelle, upload dans /images/)
    $photo = ''; // Initialise la variable photo avec une chaîne vide par défaut

    // Vérifie si une photo a été uploadée sans erreur
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION); // Récupère l'extension du fichier (ex : jpg, png)
        $filename = uniqid("art_") . '.' . $ext; // Génère un nom de fichier unique (ex : art_648aef8c2c3d3.jpg)
        $dest = 'images/' . $filename; // Chemin de destination dans le dossier /images/

        // Déplace le fichier uploadé depuis le dossier temporaire vers le dossier images/
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
            $photo = $dest; // Met à jour la variable $photo avec le chemin du fichier uploadé
        }
    }

    // Construction de la requête SQL adaptée selon le type de vente
    if ($type_vente == "enchere") {
        // Requête pour les articles en enchère (avec prix_actuel, dates début/fin)
        $sql = "INSERT INTO Article
            (titre, description, prix_initial, prix_actuel, categorie_id, vendeur_id, type_vente, type_marchandise, photo, statut, date_debut_enchere, date_fin_enchere)
            VALUES
            ('$titre', '$description', $prix, $prix_actuel, $categorie_id, $vendeur_id, '$type_vente', '$type_marchandise', '$photo', 'disponible', '$date_debut', '$date_fin')";
    } else {
        // Requête pour les articles à achat immédiat ou négociation (pas de prix_actuel ni de dates)
        $sql = "INSERT INTO Article
            (titre, description, prix_initial, categorie_id, vendeur_id, type_vente, type_marchandise, photo, statut)
            VALUES
            ('$titre', '$description', $prix, $categorie_id, $vendeur_id, '$type_vente', '$type_marchandise', '$photo', 'disponible')";
    }

    // Exécution de la requête SQL d’insertion
    if (mysqli_query($db_handle, $sql)) {
        $message = "Article ajouté avec succès !"; // Message de succès
    } else {
        $message = "Erreur lors de l'ajout : " . mysqli_error($db_handle); // Message d'erreur SQL
    }

    // Après INSERT INTO Article ...
    $article_id = mysqli_insert_id($db_handle); // Récupère l'ID de l'article inséré

    // Récupère toutes les alertes enregistrées dans la base
    $sql_alertes = "SELECT * FROM Alerte WHERE 1";
    $result_alertes = mysqli_query($db_handle, $sql_alertes);

    // Pour chaque alerte enregistrée, on vérifie si elle correspond à l’article ajouté
    while ($alerte = mysqli_fetch_assoc($result_alertes)) {
        $match = true;

        // Si la catégorie ne correspond pas, on ignore
        if ($alerte['categorie_id'] && $alerte['categorie_id'] != $categorie_id) $match = false;

        // Si le type de vente ne correspond pas
        if ($alerte['type_vente'] && $alerte['type_vente'] != $type_vente) $match = false;

        // Si le prix est en dehors des bornes définies
        if ($alerte['prix_min'] && $prix < $alerte['prix_min']) $match = false;
        if ($alerte['prix_max'] && $prix > $alerte['prix_max']) $match = false;

        // Si les mots-clés ne sont pas trouvés dans le titre ou la description
        if ($alerte['mots_cles'] && stripos($titre . ' ' . $description, $alerte['mots_cles']) === false) $match = false;

        // Si toutes les conditions sont remplies → on envoie une notification à l'utilisateur
        if ($match) {
            $user_id = $alerte['user_id']; // ID du destinataire de la notification
            $contenu = "Un nouvel article correspondant à votre alerte est disponible : <a href='article.php?id=$article_id'>$titre</a>"; // Message de la notification
            $contenu_sql = mysqli_real_escape_string($db_handle, $contenu); // Protection contre injection
            $date_notif = date('Y-m-d H:i:s'); // Date et heure actuelle
            $sql_notif = "INSERT INTO Notification (user_id, contenu, date_creation, article_id)
                          VALUES ($user_id, '$contenu_sql', '$date_notif', $article_id)"; // Requête d’insertion de la notification
            mysqli_query($db_handle, $sql_notif); // Exécution
        }
    }
}
?>
<!DOCTYPE html> <!-- Déclare un document HTML5 -->
<head>
    <meta charset="UTF-8"> <!-- Définition de l'encodage en UTF-8 -->
    <title>Ajouter un article | Agora Francia</title> <!-- Titre affiché dans l'onglet du navigateur -->

    <!-- Intégration de Bootstrap depuis CDN pour les styles -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <!-- Feuille de style personnalisée du site -->
    <link rel="stylesheet" href="css/style-site.css">

    <?php include 'header.php'; ?> <!-- Inclusion du fichier PHP contenant le header/navigation -->

    <script>
    // Fonction JS qui affiche ou masque les champs "enchère" selon le type de vente sélectionné
    function toggleEnchereFields() {
        var typeVente = document.getElementById('type_vente').value; // Récupère la valeur du type de vente
        var enchereFields = document.getElementById('enchere-fields'); // Cible le bloc des champs enchère
        if (typeVente === "enchere") {
            enchereFields.style.display = "block"; // Affiche si enchère sélectionnée
        } else {
            enchereFields.style.display = "none"; // Cache sinon
        }
    }
    </script>
</head>

<body>
<div class="container"> <!-- Conteneur Bootstrap -->
    <h2 class="mb-4 text-primary text-center">Ajouter un article</h2> <!-- Titre principal de la page -->

    <?php if ($message): ?> <!-- Affiche un message si une variable est définie (succès ou erreur) -->
        <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data"> <!-- Formulaire d'ajout d'article -->
        <div class="mb-2">
            <input required name="titre" type="text" class="form-control" placeholder="Titre de l'article">
            <!-- Champ pour le titre de l’article -->
        </div>

        <div class="mb-2">
            <textarea required name="description" class="form-control" placeholder="Description"></textarea>
            <!-- Zone de texte pour la description -->
        </div>

        <div class="mb-2">
            <input required name="prix" type="number" min="0" step="0.01" class="form-control" placeholder="Prix de départ (€)">
            <!-- Champ numérique pour le prix -->
        </div>

        <div class="mb-2">
            <select name="categorie_id" class="form-control" required>
                <option value="">-- Catégorie --</option>
                <!-- Boucle d’affichage des catégories dans la liste -->
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
            <!-- Sélecteur du type de vente avec fonction JS liée -->
        </div>

        <!-- Champs additionnels pour l’enchère, masqués par défaut -->
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
            <!-- Choix du type de marchandise pour l’article -->
        </div>

        <div class="mb-2">
            <input type="file" name="photo" class="form-control">
            <small class="text-muted">Photo (optionnelle, jpg/png/gif)</small>
            <!-- Upload d’image avec extensions recommandées -->
        </div>

        <button type="submit" class="btn btn-success w-100">Ajouter l'article</button>
        <!-- Bouton principal pour envoyer le formulaire -->
    </form>

    <a href="index.php" class="btn btn-secondary mt-4">Retour à l'accueil</a>
    <!-- Lien de retour à la page principale -->
</div>

<?php include 'footer.php'; ?> <!-- Inclusion du footer -->

<script>
    // Active les champs enchère au chargement si besoin 
    document.addEventListener('DOMContentLoaded', function() {
        toggleEnchereFields();
    });
</script>
</body>
</html>
