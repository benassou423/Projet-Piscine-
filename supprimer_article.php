<?php
session_start();
// Sécurité : accès réservé aux vendeurs et admins
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['vendeur', 'admin'])) {
    header('Location: compte.php');
    exit();
}

include_once "db.php";
if (!$db_handle) {
    die("Erreur de connexion à la base de données");
}

$db_found = mysqli_select_db($db_handle, "agora");
if (!$db_found) {
    die("Erreur de connexion à la base de données Agora");
}

$message = "";

// Traitement de la suppression d'article
if (isset($_POST['supprimer_article'])) {
    // On récupère l'ID de l'article à supprimer depuis le formulaire
    $id_article = intval($_POST['supprimer_article']);
    
    // Vérifier que l'article existe et, si l'utilisateur n'est pas admin, qu'il lui appartient
    if ($_SESSION['user_role'] !== 'admin') {
        // Requête pour sélectionner l'article en vérifiant le vendeur_id
        $sql_check = "SELECT * FROM Article WHERE id = $id_article";
        $result_check = mysqli_query($db_handle, $sql_check);
        
        // Si l'article n'existe pas
        if (mysqli_num_rows($result_check) == 0) {
            // On redirige vers le catalogue sans essayer de supprimer
            header('Location: catalogue.php');
            exit();
        } else {
            // On récupère le vendeur_id de l'article
            $data = mysqli_fetch_assoc($result_check);
            if ($data['vendeur_id'] != $_SESSION['user_id']) {
                // Si l'article n'appartient pas au vendeur connecté, on redirige
                header('Location: catalogue.php');
                exit();
            }
        }
    } else {
        // Si c'est un admin, on veut juste vérifier que l'article existe avant suppression
        $sql_check = "SELECT * FROM Article WHERE id = $id_article";
        $result_check = mysqli_query($db_handle, $sql_check);
        if (mysqli_num_rows($result_check) == 0) {
            header('Location: catalogue.php');
            exit();
        }
    }

    // À ce stade, l'article existe et l'utilisateur est autorisé à le supprimer
    // On va supprimer toutes les entrées liées avant de supprimer l'article lui-même
    
    // 1. Supprimer dans ArticlePanier
    $sql1 = "DELETE FROM ArticlePanier WHERE article_id = $id_article";
    mysqli_query($db_handle, $sql1);
    
    // 2. Supprimer dans Enchere
    $sql2 = "DELETE FROM Enchere WHERE article_id = $id_article";
    mysqli_query($db_handle, $sql2);
    
    // 3. Supprimer dans Transaction
    $sql3 = "DELETE FROM Transaction WHERE article_id = $id_article";
    mysqli_query($db_handle, $sql3);
    
    // 4. Supprimer dans Negociation
    $sql4 = "DELETE FROM Negociation WHERE article_id = $id_article";
    mysqli_query($db_handle, $sql4);
    
    // 5. Supprimer dans Notification
    $sql5 = "DELETE FROM Notification WHERE article_id = $id_article";
    mysqli_query($db_handle, $sql5);
    
    // 6. Enfin, supprimer l'article lui-même
    $sql6 = "DELETE FROM Article WHERE id = $id_article";
    $result_delete = mysqli_query($db_handle, $sql6);
    
    if ($result_delete) {
        // Si la suppression a réussi
        $_SESSION['success_message'] = "Article supprimé avec succès !";
    } else {
        // En cas d'erreur MySQL, on stocke un message d'erreur
        $_SESSION['error_message'] = "Erreur lors de la suppression de l'article.";
    }
    
    // Après tout, rediriger vers l'accueil
    header('Location: index.php');
    exit();
}
?>
