<?php
session_start();
// On définit la base de données et on se connecte
$database = "agora";
$db_handle = mysqli_connect('localhost', 'root', 'root');
$db_found = mysqli_select_db($db_handle, $database);

// Vérifier que l'utilisateur est bien connecté
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour effectuer cette action.";
    header('Location: compte.php');
    exit();
}

// Vérifier que l'ID de l'achat est fourni et qu'il est numérique
if (!isset($_POST['achat_id']) || !is_numeric($_POST['achat_id'])) {
    $_SESSION['error'] = "ID d'achat invalide.";
    header('Location: mes_achats.php');
    exit();
}

$achat_id = intval($_POST['achat_id']);
$user_id  = $_SESSION['user_id'];

// 1) Vérifier que l'achat existe et qu'il appartient à l'utilisateur connecté
$sql_check = "SELECT id, transaction_id, article_id 
              FROM Achat 
              WHERE id = $achat_id 
                AND acheteur_id = $user_id";
$result_check = mysqli_query($db_handle, $sql_check);

if (mysqli_num_rows($result_check) == 0) {
    // Si aucun enregistrement trouvé, on arrête et on redirige
    $_SESSION['error'] = "Achat non trouvé ou vous n'avez pas l'autorisation de le supprimer.";
    header('Location: mes_achats.php');
    exit();
}

// Si on arrive ici, l'achat existe et appartient bien à l'utilisateur
$data = mysqli_fetch_assoc($result_check);
$transaction_id = $data['transaction_id'];
$article_id     = $data['article_id'];

// 2) On supprime d'abord l'achat dans la table Achat
$sql_delete_achat = "DELETE FROM Achat 
                     WHERE id = $achat_id 
                       AND acheteur_id = $user_id";
$result_delete_achat = mysqli_query($db_handle, $sql_delete_achat);

if (!$result_delete_achat) {
    // Si la suppression de l'achat a échoué, on renseigne le message d'erreur
    $_SESSION['error'] = "Erreur lors de la suppression de l'achat.";
    header('Location: mes_achats.php');
    exit();
}

// 3) Si une transaction existait (transaction_id non nul), on la supprime aussi
if (!empty($transaction_id)) {
    $sql_delete_transaction = "DELETE FROM transaction 
                               WHERE id = $transaction_id";
    $result_delete_transaction = mysqli_query($db_handle, $sql_delete_transaction);

    if (!$result_delete_transaction) {
        // Si la suppression de la transaction a échoué, on le signale
        $_SESSION['error'] = "Erreur lors de la suppression de la transaction associée.";
        header('Location: mes_achats.php');
        exit();
    }
}

// 4) Si tout s'est bien passé, on met un message de succès en session
$_SESSION['success'] = "Achat supprimé avec succès.";

// 5) On redirige vers la page des achats
header('Location: mes_achats.php');
exit();
?>
