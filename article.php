<?php
// Définit le fuseau horaire à Paris
date_default_timezone_set('Europe/Paris');
session_start();
$database = "agora";
// Connexion au serveur MySQL
$db_handle = mysqli_connect('localhost', 'root', '');
// Sélectionne la base de données "agora"
$db_found = mysqli_select_db($db_handle, $database);

// Initialisation des variables : l’article à afficher, et les messages à afficher en fonction du type d’achat

$article = null;
$achat_message = "";
$enchere_message = "";
$nego_message = "";

// On initialise la variable qui contiendra l’ID de l’article qu’on veut consulter
$id = null;

// Si la base de données a bien été trouvée
if ($db_found) {

    // On vérifie si on a reçu un id en POST ou en GET et s’il est bien numérique. Si oui, on le convertit en entier.

    if (isset($_POST['id']) && is_numeric($_POST['id'])) {
        $id = intval($_POST['id']);
    } elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $id = intval($_GET['id']);
    }

    // Si un ID a bien été fourni
    if ($id !== null) {

        // On récupère toutes les infos de l’article + la catégorie + les infos du vendeur via une requête SQL

        $sql = "SELECT a.*, c.nom AS categorie, u.nom AS vendeur_nom, u.prenom AS vendeur_prenom, u.email AS vendeur_email
                FROM Article a
                JOIN Categorie c ON a.categorie_id = c.id
                JOIN Utilisateur u ON a.vendeur_id = u.id
                WHERE a.id = $id";

        // On exécute la requête SQL
        $result = mysqli_query($db_handle, $sql);

        // Si on a bien un article correspondant, on le stocke dans la variable $article

        if ($result && mysqli_num_rows($result) == 1) {
            $article = mysqli_fetch_assoc($result);
        }
    }
}

// -------- ACHAT IMMEDIAT --------
if (
    $article && //article existe 
    isset($_POST['achat_direct']) && //bouton d'achat a été cliqué (présence de "achat_direct" en POST)
    isset($_SESSION['user_id']) && //utilisateur co 
    $_SESSION['user_id'] != $article['vendeur_id'] && //utilisateur n'est pas le vendeur de l'article 
    $article['type_vente'] == 'immediat' && // type de vente est "achat immediat"
    $article['statut'] == 'disponible' //article disponible
) {
    // On récupère l'ID de l'acheteur connecté et de l’article en question
    $acheteur_id = intval($_SESSION['user_id']);
    $article_id = intval($article['id']);

    // On cherche si l’utilisateur a déjà un panier existant
    $sql = "SELECT id FROM Panier WHERE acheteur_id=$acheteur_id";
    $result = mysqli_query($db_handle, $sql);

    // S’il a un panier, on récupère son ID
    if ($result && mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);
        $panier_id = $row['id'];

     
    // Sinon, on **crée un nouveau panier** pour cet acheteur, et on récupère son ID    
    } else {
        $sql = "INSERT INTO Panier (acheteur_id, date_creation) VALUES ($acheteur_id, NOW())";
        mysqli_query($db_handle, $sql);
        $panier_id = mysqli_insert_id($db_handle);
    }
    // Ajouter l'article au panier (si pas déjà présent)
    $sql = "SELECT * FROM ArticlePanier WHERE panier_id=$panier_id AND article_id=$article_id";
    $result = mysqli_query($db_handle, $sql);

    // S’il n’est pas dans le panier, on l’ajoute en précisant que c’est un achat immédiat
    if (!$result || mysqli_num_rows($result) == 0) {
        $sql = "INSERT INTO ArticlePanier (panier_id, article_id, mode_achat)
                VALUES ($panier_id, $article_id, 'immediat')";
        mysqli_query($db_handle, $sql);
    }
    // Une fois l’ajout terminé, on redirige vers la page panier pour valider ou visualiser l’achat
    header("Location: panier.php");
    exit();
}

// -------- ENCHERE --------
// On récupère la date et l’heure actuelle au format MySQL
$maintenant = date('Y-m-d H:i:s');

// Initialisation d’un drapeau pour savoir si l’enchère est active
$en_cours = false;

// Si l’article est bien en vente par enchère, on vérifie que la date actuelle est comprise entre la date de début et la date de fin
if ($article && $article['type_vente'] == 'enchere') {
    $en_cours = ($maintenant >= $article['date_debut_enchere'] && $maintenant <= $article['date_fin_enchere']);
}
// Charger le système d'enchères automatiques (dans un fichier séparé)
require_once 'systeme_enchere_automatique.php';

if (
    $article &&  // L’article existe
    $article['type_vente'] == 'enchere' &&  // Le type de vente est bien une enchère
    $en_cours &&  // L’enchère est en cours
    isset($_POST['placer_enchere']) && // Le formulaire a bien été soumis
    isset($_POST['prix_max']) &&  // Un prix max a été envoyé
    isset($_SESSION['user_id']) && // L’utilisateur est connecté
    $_SESSION['user_id'] != $article['vendeur_id']  // L’utilisateur n’est pas le vendeur
) {
    $prix_max = floatval($_POST['prix_max']); // On convertit le prix maximum proposé en nombre à virgule flottante

    // On récupère l’ID de l’acheteur et de l’article
    $acheteur_id = intval($_SESSION['user_id']);
    $article_id = intval($article['id']);
    
    // Utiliser le nouveau système d'enchères automatiques
    $systeme_enchere = new SystemeEnchereAutomatique($db_handle);
    // On tente de placer l’enchère
    $resultat = $systeme_enchere->placerEnchereAutomatique($article_id, $acheteur_id, $prix_max);
    

    // Si l’enchère a été placée avec succès
    if ($resultat['success']) {
        $enchere_message = $resultat['message'];
        
        // Notifier le vendeur
        $vendeur_id = $article['vendeur_id'];
        $acheteur_nom = $_SESSION['user_prenom']." ".$_SESSION['user_nom'];
        $contenu = "Nouvelle enchère automatique de $acheteur_nom sur l'article : ".$article['titre']." (enchère max : ".number_format($prix_max,2,',',' ')." €)";
        $contenu_sql = mysqli_real_escape_string($db_handle, $contenu);
        $date_notif = date('Y-m-d H:i:s');
        $sql_notif = "INSERT INTO Notification (user_id, contenu, date_creation, article_id)
                      VALUES ($vendeur_id, '$contenu_sql', '$date_notif', $article_id)";
        mysqli_query($db_handle, $sql_notif);
        
        // On recharge les infos de l’article pour avoir les données à jour (ex : nouveau prix actuel)
        $sql = "SELECT a.*, c.nom AS categorie, u.nom AS vendeur_nom, u.prenom AS vendeur_prenom, u.email AS vendeur_email
                FROM Article a
                JOIN Categorie c ON a.categorie_id = c.id
                JOIN Utilisateur u ON a.vendeur_id = u.id
                WHERE a.id = $article_id";
        $result = mysqli_query($db_handle, $sql);
        if ($result && mysqli_num_rows($result) == 1) {
            $article = mysqli_fetch_assoc($result);
        }

    // Sinon, on affiche le message d’erreur retourné par la classe    
    } else {
        $enchere_message = $resultat['message'];
    }
}

// -------- NEGOCIATION --------
// Acheteur propose une offre
if (
    $article &&  // L’article existe
    $article['type_vente'] == 'negociation' &&  // Le type de vente est "négociation"
    isset($_POST['faire_offre']) &&  // Le bouton "faire offre" a été cliqué
    isset($_POST['prix_offre']) &&  // L’utilisateur a bien proposé un prix
    isset($_SESSION['user_id']) && // L’utilisateur est connecté
    $_SESSION['user_id'] != $article['vendeur_id']  // L’utilisateur n’est pas le vendeur
) { 

    // On sécurise et extrait toutes les infos nécessaires : identifiants et prix de l’offre
    $acheteur_id = intval($_SESSION['user_id']);
    $vendeur_id = intval($article['vendeur_id']);
    $article_id = intval($article['id']);
    $prix_offre = floatval($_POST['prix_offre']);

    // On cherche s’il existe déjà une négociation en cours entre cet acheteur et cet article
    $sql = "SELECT * FROM Negociation WHERE article_id=$article_id AND acheteur_id=$acheteur_id AND etat='en cours' ORDER BY tour DESC LIMIT 1";
    $res = mysqli_query($db_handle, $sql);

    // Si une négociation existe déjà, on la récupère
    if ($res && mysqli_num_rows($res) > 0) {
        $nego = mysqli_fetch_assoc($res);

        // Si l’utilisateur a déjà fait 5 propositions, on bloque la négociation
        if ($nego['tour'] >= 5) {
            // Notification au vendeur
            $nego_message = "Vous avez atteint la limite de 5 tentatives de négociation.";
           
        // Sinon, on enregistre une nouvelle offre avec le numéro de tour augmenté
        } else {
            $tour = $nego['tour'] + 1;
            $sql2 = "INSERT INTO Negociation (article_id, acheteur_id, vendeur_id, tour, offre_acheteur, date_action) 
                     VALUES ($article_id, $acheteur_id, $vendeur_id, $tour, $prix_offre, NOW())";
            mysqli_query($db_handle, $sql2);
            $nego_message = "Votre nouvelle offre a été soumise au vendeur.";
            // Notification au vendeur

// On envoie une notification au vendeur            
if (isset($vendeur_id)) {
    $acheteur_nom = $_SESSION['user_prenom'] . " " . $_SESSION['user_nom'];
    $contenu = "Nouvelle offre de $acheteur_nom sur l'article : " . $article['titre'] . " (" . number_format($prix_offre,2,',',' ') . " €)";
    $contenu_sql = mysqli_real_escape_string($db_handle, $contenu);
    $date_notif = date('Y-m-d H:i:s');
  $sql_notif = "INSERT INTO Notification (user_id, contenu, date_creation, article_id)
              VALUES ($acheteur_id, '$contenu_sql', '$date_notif', $article_id)";

    mysqli_query($db_handle, $sql_notif);
}

        }

    // Si c’est la première négociation, on crée une nouvelle entrée avec tour = 1    
    } else {
        $sql2 = "INSERT INTO Negociation (article_id, acheteur_id, vendeur_id, tour, offre_acheteur, date_action) 
                 VALUES ($article_id, $acheteur_id, $vendeur_id, 1, $prix_offre, NOW())";
        mysqli_query($db_handle, $sql2);
        $nego_message = "Votre offre a été soumise au vendeur.";
    }
}

// Si l’article est en négociation, que l’acheteur est connecté et qu’il accepte une contre-offre
if (
    $article &&
    $article['type_vente'] == 'negociation' &&
    isset($_POST['accept_contre_offre']) &&
    isset($_POST['tour']) &&
    isset($_SESSION['user_id']) &&
    $_SESSION['user_id'] != $article['vendeur_id']
) {

    // On récupère les informations nécessaires à la mise à jour
    $acheteur_id = intval($_SESSION['user_id']);
    $tour = intval($_POST['tour']);
    $article_id = intval($article['id']);

    // Marque la négo comme acceptée
    $sql = "UPDATE Negociation SET etat='accepte' WHERE article_id=$article_id AND acheteur_id=$acheteur_id AND tour=$tour";
    mysqli_query($db_handle, $sql);

    // On marque l’article comme vendu
    $sql2 = "UPDATE Article SET statut='vendu' WHERE id=$article_id";
    mysqli_query($db_handle, $sql2);

    // Message de confirmation pour l’acheteur
    $nego_message = "Vous avez accepté la contre-offre du vendeur !";


    // Le vendeur reçoit une notification indiquant que sa contre-offre a été acceptée
    $contenu = "L'acheteur a accepté votre contre-offre sur l'article « " . $article['titre'] . " » !";
    $contenu_sql = mysqli_real_escape_string($db_handle, $contenu);
    $date_notif = date('Y-m-d H:i:s');
    $vendeur_id = intval($article['vendeur_id']);
    $sql_notif = "INSERT INTO Notification (user_id, contenu, date_creation, article_id)
                  VALUES ($vendeur_id, '$contenu_sql', '$date_notif', $article_id)";
    mysqli_query($db_handle, $sql_notif);
}


// Vendeur répond (accepte, refuse, contre-offre)
if (
    $article &&
    $article['type_vente'] == 'negociation' &&
    isset($_POST['repondre_nego']) &&
    isset($_SESSION['user_id']) &&
    $_SESSION['user_id'] == $article['vendeur_id'] &&
    isset($_POST['acheteur_id']) &&
    isset($_POST['tour'])
) {

    // On récupère l’ID de l’acheteur, le numéro de tour et l’article concerné
    $acheteur_id = intval($_POST['acheteur_id']);
    $tour = intval($_POST['tour']);
    $article_id = intval($article['id']);


    // Le vendeur accepte l’offre → la négociation passe à l’état "accepte"
    if (isset($_POST['accepter'])) { // Si le bouton "Accepter" a été cliqué dans le formulaire du vendeur

        // On met à jour la négociation pour indiquer qu’elle est acceptée
        $sql = "UPDATE Negociation SET etat='accepte' WHERE article_id=$article_id AND acheteur_id=$acheteur_id AND tour=$tour";
        mysqli_query($db_handle, $sql);

        // Message à afficher côté vendeur pour confirmer l’action
        $nego_message = "Vous avez accepté l'offre de l'acheteur !";

    // NOTIF à l'acheteur quand c'est refusée 
    $contenu = "Votre offre sur l'article « " . $article['titre'] . " » a été refusée par le vendeur.";

    // On sécurise le message en échappant les caractères spéciaux pour l'injection SQL
    $contenu_sql = mysqli_real_escape_string($db_handle, $contenu);

    // Date de création de la notification (au format compatible MySQL)
    $date_notif = date('Y-m-d H:i:s');
 $sql_notif = "INSERT INTO Notification (user_id, contenu, date_creation, article_id)
              VALUES ($acheteur_id, '$contenu_sql', '$date_notif', $article_id)";
    mysqli_query($db_handle, $sql_notif);

    // l’acheteur est notifié que son offre a été acceptée
    $sql_user = "SELECT prenom, nom FROM Utilisateur WHERE id=$acheteur_id";
    $res_user = mysqli_query($db_handle, $sql_user);
    $a = mysqli_fetch_assoc($res_user);

    // On récupère les infos de l’acheteur pour personnaliser le message de notification
    $nom_acheteur = $a ? $a['prenom'] . " " . $a['nom'] : "Acheteur";
    $contenu = "Votre offre sur l'article « " . $article['titre'] . " » a été acceptée par le vendeur !";

    // Message clair et correct à envoyer : confirmation d’acceptation
    $contenu_sql = mysqli_real_escape_string($db_handle, $contenu);
    $date_notif = date('Y-m-d H:i:s');
 $sql_notif = "INSERT INTO Notification (user_id, contenu, date_creation, article_id)
              VALUES ($acheteur_id, '$contenu_sql', '$date_notif', $article_id)";
    mysqli_query($db_handle, $sql_notif);

    // Le vendeur a refusé l’offre → la négociation est marquée comme "refuse"
    } elseif (isset($_POST['refuser'])) {
        $sql = "UPDATE Negociation SET etat='refuse' WHERE article_id=$article_id AND acheteur_id=$acheteur_id AND tour=$tour";
        mysqli_query($db_handle, $sql);
        $nego_message = "Vous avez refusé l'offre.";

    // Le vendeur propose un nouveau prix, enregistré comme contre-offre    
    } elseif (isset($_POST['contre_offre']) && isset($_POST['prix_contre_offre'])) {
        $prix_contre = floatval($_POST['prix_contre_offre']);
        $sql = "UPDATE Negociation SET contre_offre_vendeur=$prix_contre WHERE article_id=$article_id AND acheteur_id=$acheteur_id AND tour=$tour";
        mysqli_query($db_handle, $sql);
        $nego_message = "Votre contre-offre a été envoyée à l'acheteur.";
           // NOTIF à l'acheteur
    $contenu = "Le vendeur a fait une contre-offre (" . number_format($prix_contre,2,',',' ') . " €) sur l'article « " . $article['titre'] . " ». Consultez votre historique de négociation !";
    $contenu_sql = mysqli_real_escape_string($db_handle, $contenu);
    $date_notif = date('Y-m-d H:i:s');
    $sql_notif = "INSERT INTO Notification (user_id, contenu, date_creation, article_id)
              VALUES ($acheteur_id, '$contenu_sql', '$date_notif', $article_id)";

    mysqli_query($db_handle, $sql_notif);
    }
}
?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <title>Article | Agora Francia</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="css/style-site.css">    <style>

    /* Limite les dimensions de l'image */
        .fiche-photo {max-width: 380px; max-height: 280px; object-fit: contain; background: #f9f9f9;}
        /* Style du bloc article */
        .fiche-bloc {background: #fff; border-radius: 10px; box-shadow: 0 0 8px #bbb; padding: 22px;}
    </style>
</head>
<body>

<!-- Inclusion de l'en-tête du site (menu, logo, etc.) -->
<?php include 'header.php'; ?>
    </style>
</head>
<body>
<!-- Conteneur principal avec marge verticale -->
<div class="container my-4">

     <!-- Si aucun article trouvé -->
    <?php if (!$article): ?>
        <div class="alert alert-warning text-center">Article introuvable.</div>
        <a href="catalogue.php" class="btn btn-secondary">Retour au catalogue</a>

    <!-- Sinon on affiche l’article -->    
    <?php else: ?>
        <div class="row fiche-bloc"> <!-- Bloc contenant l’article en 2 colonnes -->
            <div class="col-md-5 text-center"> <!-- Colonne image -->
                <?php if ($article['photo']): ?> <!-- Si une photo est disponible -->
                    <img src="<?= $article['photo'] ?>" alt="photo article" class="fiche-photo img-fluid mb-3"> <!-- Affichage de la photo -->
                <?php else: ?>
                    <img src="images/placeholder.png" alt="aperçu" class="fiche-photo img-fluid mb-3">
                <?php endif; ?>
            </div>
            <div class="col-md-7">
                <h2><?= $article['titre'] ?></h2>
                <span class="badge bg-info mb-2"><?= $article['categorie'] ?></span>
                <h4 class="text-success">
                    <?php
                    if ($article['type_vente'] == 'immediat') {
                        // Affiche prix initial si vente immédiate
                        echo number_format($article['prix_initial'],2,',',' ') . " €";
                    } else {  // Sinon prix actuel
                        echo number_format($article['prix_actuel'] ?? $article['prix_initial'], 2, ',', ' ');

                    }
                    ?>
                </h4>
                <!-- Description avec retours à la ligne -->
                <p class="my-3"><?= nl2br($article['description']) ?></p>
                <ul class="mb-3 list-unstyled">
                    <li><strong>Type de vente :</strong> <?= $article['type_vente'] ?></li>
                    <li><strong>Vendeur :</strong> <?= $article['vendeur_prenom'].' '.$article['vendeur_nom'] ?> (<?= $article['vendeur_email'] ?>)</li>
                    <li><strong>Statut :</strong> <?= $article['statut'] ?></li>
                </ul>

               <!-- BLOC ACHAT IMMÉDIAT -->
                <?php if (
                    $article['type_vente'] == 'immediat' &&                 // Vérifie que le type de vente est immédiat
                    isset($_SESSION['user_id']) &&                         // Vérifie que l'utilisateur est connecté
                    $_SESSION['user_id'] != $article['vendeur_id'] &&      // Vérifie que l'utilisateur n'est pas le vendeur
                    $article['statut'] == 'disponible'                     // Vérifie que l'article est encore disponible
                ): ?>
                    <form method="post" class="my-3"> <!-- Formulaire qui envoie une demande d’achat immédiat -->
                        <input type="hidden" name="id" value="<?= $article['id'] ?>"> <!-- Champ caché pour transmettre l’ID de l’article -->
                        <button type="submit" name="achat_direct" class="btn btn-success btn-lg">Acheter maintenant</button> <!-- Bouton vert d’achat immédiat -->
                    </form>
                <?php endif; ?>

                <!-- BLOC ENCHÈRE -->
                <?php if ($article['type_vente'] == 'enchere'): ?> <!-- Si l’article est en mode enchère -->
                    <div class="my-3"> <!-- Bloc avec marge -->
                        <div>Enchère&nbsp;: <strong><?= $article['date_debut_enchere'] ?></strong> → <strong><?= $article['date_fin_enchere'] ?></strong></div> <!-- Affiche la période de l’enchère -->
                        <div>Prix actuel : <strong><?= number_format($article['prix_actuel'] ?? $article['prix_initial'],2,',',' ') ?> €</strong></div> <!-- Affiche le prix actuel ou initial -->
                        <?php if ($en_cours): ?> <!-- Si l’enchère est en cours -->
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $article['vendeur_id']): ?> <!-- Si utilisateur connecté ≠ vendeur -->
                                <form method="post" class="mt-2"> <!-- Formulaire pour soumettre une enchère -->
                                    <input type="hidden" name="id" value="<?= $article['id'] ?>"> <!-- ID article caché -->
                                    <input type="number" name="prix_max" min="<?= $article['prix_actuel']+1 ?>" step="1" required placeholder="Votre enchère maximum (€)"> <!-- Champ pour entrer le prix max -->
                                    <button type="submit" name="placer_enchere" class="btn btn-warning btn-sm">Placer mon enchère</button> <!-- Bouton pour soumettre -->
                                </form>
                                <?php if ($enchere_message): ?><div class="alert alert-info mt-2"><?= $enchere_message ?></div><?php endif; ?> <!-- Message retour du système d'enchères -->
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-secondary mt-2">Cette enchère est terminée ou pas encore ouverte.</div> <!-- Info si enchère inactive -->
                        <?php endif; ?>
                    </div>
                <?php endif; ?>


                <!-- BLOC NEGOCIATION -->
                <?php if ($article['type_vente'] == 'negociation'): ?> <!-- Si l'article est en mode négociation -->
                    <div class="my-4 p-3 bg-light border rounded"> <!-- Bloc visuel de la négociation -->
                        <h5 class="mb-3">Négociation</h5> <!-- Titre de la section -->

                        <?php if ($nego_message): ?> <!-- Si un message (succès/erreur) est défini -->
                            <div class="alert alert-info"><?= $nego_message ?></div> <!-- On l'affiche dans une alerte Bootstrap -->
                        <?php endif; ?>

                        <!-- Historique des négociations -->
                        <?php
                        $nego_affichee = false; // Flag pour savoir si on a affiché un historique

                        if (isset($_SESSION['user_id'])) { // Si l'utilisateur est connecté
                            $user_id = intval($_SESSION['user_id']); // On récupère son ID
                            $sql = "SELECT * FROM Negociation WHERE article_id=$id AND (acheteur_id=$user_id OR vendeur_id=$user_id) ORDER BY tour ASC"; // Récupère les échanges liés à cet utilisateur
                            $res = mysqli_query($db_handle, $sql); // Exécution de la requête
                            if ($res && mysqli_num_rows($res) > 0) { // Si on a au moins une ligne
                                echo '<table class="table table-sm"><thead><tr>
                                        <th>Tour</th><th>Offre acheteur</th><th>Contre-offre vendeur</th><th>Etat</th><th>Date</th>
                                    </tr></thead><tbody>'; // Début tableau

                                while ($n = mysqli_fetch_assoc($res)) { // Boucle sur chaque ligne de négociation
                                    echo "<tr>
                                        <td>{$n['tour']}</td>
                                        <td>" . ($n['offre_acheteur'] ? number_format($n['offre_acheteur'],2,',',' ') . " €" : "-") . "</td>
                                        <td>" . ($n['contre_offre_vendeur'] ? number_format($n['contre_offre_vendeur'],2,',',' ') . " €" : "-") . "</td>
                                        <td>{$n['etat']}</td>
                                        <td>{$n['date_action']}</td>
                                    </tr>"; // Affiche les infos d'un tour : offres, état et date
                                }

                                echo '</tbody></table>'; // Fin du tableau
                                $nego_affichee = true; // On a bien affiché quelque chose
                            }
                        }

                        // Formulaire pour l'acheteur
                        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $article['vendeur_id']) { // L'utilisateur connecté est un acheteur
                            $sql = "SELECT * FROM Negociation WHERE article_id=$id AND acheteur_id={$_SESSION['user_id']} ORDER BY tour DESC LIMIT 1"; // On récupère sa dernière négo
                            $res = mysqli_query($db_handle, $sql);
                            $nego = $res && mysqli_num_rows($res) ? mysqli_fetch_assoc($res) : null; // Soit on a une négo, soit null

                            // 1. Si contre-offre du vendeur, proposer le bouton accepter la contre-offre
                            if ($nego && $nego['etat'] == 'en cours' && $nego['contre_offre_vendeur'] && $nego['contre_offre_vendeur'] > 0) {
                                echo '<div class="alert alert-warning mb-2">
                                        Le vendeur vous propose une contre-offre de <b>' . number_format($nego['contre_offre_vendeur'],2,',',' ') . ' €</b>.
                                      </div>
                                      <form method="post" class="d-inline-block me-2">
                                        <input type="hidden" name="accept_contre_offre" value="1"> <!-- Signal que l’on accepte la contre-offre -->
                                        <input type="hidden" name="tour" value="'.$nego['tour'].'"> <!-- Numéro du tour pour le suivre -->
                                        <button type="submit" class="btn btn-success btn-sm">Accepter la contre-offre</button> <!-- Bouton vert d’acceptation -->
                                      </form>';
                            }


                            if (!$nego || ($nego['etat'] == 'en cours' && $nego['tour'] < 5 && $nego['contre_offre_vendeur'])) {
                                echo '<form method="post" class="mt-3">
                                    <div class="input-group">
                                        <input type="number" name="prix_offre" step="0.01" min="0" class="form-control" placeholder="Votre offre (€)" required>
                                        <button type="submit" name="faire_offre" class="btn btn-success">Proposer</button>
                                    </div>
                                </form>';
                            } elseif ($nego && $nego['etat'] == 'en cours' && !$nego['contre_offre_vendeur']) {
                                echo '<div class="alert alert-secondary">Offre en attente de réponse du vendeur.</div>';
                            } elseif ($nego && $nego['etat'] == 'accepte') {
                                echo '<div class="alert alert-success">Votre offre a été acceptée ! L\'article est à vous.</div>';
                            } elseif ($nego && $nego['etat'] == 'refuse') {
                                echo '<div class="alert alert-danger">Votre offre a été refusée.</div>';
                            }
                        }

                        // Formulaire pour le vendeur
                        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $article['vendeur_id']) {
                            $sql = "SELECT * FROM Negociation WHERE article_id=$id AND etat='en cours' AND (contre_offre_vendeur IS NULL OR contre_offre_vendeur=0) ORDER BY date_action ASC LIMIT 1";
                            $res = mysqli_query($db_handle, $sql);
                            if ($res && $nego = mysqli_fetch_assoc($res)) {
                                echo '<form method="post" class="mt-3">
                                    <input type="hidden" name="acheteur_id" value="'.$nego['acheteur_id'].'">
                                    <input type="hidden" name="tour" value="'.$nego['tour'].'">
                                    <div class="mb-2">
                                        <button type="submit" name="accepter" class="btn btn-success" name="repondre_nego">Accepter l\'offre ('.$nego['offre_acheteur'].' €)</button>
                                        <button type="submit" name="refuser" class="btn btn-danger" name="repondre_nego">Refuser</button>
                                    </div>
                                    <div class="input-group mb-2">
                                        <input type="number" name="prix_contre_offre" step="0.01" min="0" class="form-control" placeholder="Votre contre-offre (€)">
                                        <button type="submit" name="contre_offre" class="btn btn-warning" name="repondre_nego">Contre-offre</button>
                                    </div>
                                    <input type="hidden" name="repondre_nego" value="1">
                                </form>';
                            }
                        }
                        ?>
                    </div>
                <?php endif; ?>

                <?php 
                // Bouton de suppression pour admin ou vendeur (sur ses propres articles)
                if (isset($_SESSION['user_role']) && 
                    ($_SESSION['user_role'] === 'admin' || 
                    ($_SESSION['user_role'] === 'vendeur' && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $article['vendeur_id']))): 
                ?>
                    <form method="post" action="supprimer_article.php" class="mb-3" 
                          onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet article ? Cette action est irréversible.');"> <!-- Confirmation avant suppression -->
                        <input type="hidden" name="supprimer_article" value="<?= $article['id'] ?>"> <!-- ID de l'article à supprimer -->
                        <button type="submit" class="btn btn-danger"> <!-- Bouton rouge de suppression -->
                            <i class="bi bi-trash"></i> Supprimer l'article
                        </button>
                    </form>
                <?php endif; ?>

                <a href="catalogue.php" class="btn btn-secondary mt-2">Retour au catalogue</a> <!-- Lien retour -->
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
</body>
</html>
