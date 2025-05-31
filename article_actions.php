<?php
date_default_timezone_set('Europe/Paris');
session_start();
include_once "db.php";

$achat_message = "";
$enchere_message = "";
$nego_message = "";

// Ici on récupère l'article 
$article = null;
$id = null;

// Vérifie si la connexion à la base de données a bien été établie 
if ($db_found) {
    // Si un ID est envoyé via un formulaire (POST) et que c’est bien un nombre :
    if (isset($_POST['id']) && is_numeric($_POST['id'])) {
        // le convertit en entier pour éviter toute injection ou erreur
        $id = intval($_POST['id']);
    // Sinon, on regarde si un ID est dans l’URL (GET) et que c’est bien un nombre
    } elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
        // convertit ...
        $id = intval($_GET['id']);
    }
    // Si on a bien récupéré un ID valide
    if ($id !== null) {
        // Requête SQL qui récupère toutes les infos de l’article + la catégorie + les infos du vendeur
        $sql = "SELECT a.*, c.nom AS categorie, u.nom AS vendeur_nom, u.prenom AS vendeur_prenom, u.email AS vendeur_email
                FROM Article a
                JOIN Categorie c ON a.categorie_id = c.id
                JOIN Utilisateur u ON a.vendeur_id = u.id
                WHERE a.id = $id";

        // Exécute la requête SQL sur la base de données        
        $result = mysqli_query($db_handle, $sql);
        // Si la requête a fonctionné ET qu’on a trouvé exactement 1 article :
        if ($result && mysqli_num_rows($result) == 1) {
            // alors on stocke les infos de l’article dans un tableau associatif
            $article = mysqli_fetch_assoc($result);
        }
    }
}
// Si l’article a bien été trouvé, on continue le traitement
if ($article) {
    // Si le type de vente est "achat immédiat" :
    if ($article['type_vente'] == 'immediat') {
        //on inclut le php qui gère l’achat direct
        include_once "actions/action_achat.php";
    // Sinon si le type est "enchère" :
    } elseif ($article['type_vente'] == 'enchere') {
        //inclut le php qui gère les enchères
        include_once "actions/action_enchere.php";
      //Sinon si type de vente est "negociation"  
    } elseif ($article['type_vente'] == 'negociation') {
        //Inclut le php qui gère les negociations
        include_once "actions/action_nego.php";
    }
}
?>
