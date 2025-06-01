<?php
session_start();
date_default_timezone_set('Europe/Paris');

$database = "agora";
$db_handle = mysqli_connect('localhost', 'root', '');
$db_found = mysqli_select_db($db_handle, $database);

// Vérifie que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: compte.php');
    exit();
}
$user_id = intval($_SESSION['user_id']);

// Marque toutes les notifs comme lues à l'ouverture de la page
mysqli_query($db_handle, "UPDATE Notification SET lu=1 WHERE user_id=$user_id AND lu=0");

// Sélectionne toutes les notifications pour l'utilisateur connecté (pas de double SELECT !)
$sql = "SELECT * FROM Notification WHERE user_id=$user_id ORDER BY date_creation DESC";
$result = mysqli_query($db_handle, $sql);

// DEBUG — Affiche toutes les notifications reçues (en haut de page)
$allNotifs = [];
mysqli_data_seek($result, 0);
while ($row = mysqli_fetch_assoc($result)) {
    $allNotifs[] = $row;
}
// Remet le curseur à zéro pour l’affichage réel
mysqli_data_seek($result, 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes notifications</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style-site.css">
</head>
<body>
<div class="container my-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="index.php" class="btn btn-secondary">&larr; Retour au menu</a> <!-- larr = left arrow -->
        <h2 class="text-primary mb-0">Mes notifications</h2>
        <a href="alertes.php" class="btn btn-outline-primary">+ Créer une alerte</a>
    </div>



    <?php if (empty($allNotifs)): ?>
        <div class="alert alert-secondary">Aucune notification reçue.</div>
    <?php endif; ?>

    <?php while ($notif = mysqli_fetch_assoc($result)): ?>
        <div class="alert <?= $notif['lu'] ? 'alert-secondary' : 'alert-info' ?>"> <!-- si la notif est lu on met alert secondary sinon alert info -->
            <b>[<?= $notif['id'] ?>]</b> <!-- pour écrire la variable notif[id] -->
            <?= htmlspecialchars($notif['contenu']) ?><br>
            <small><?= $notif['date_creation'] ?></small>
            <?php if (!empty($notif['article_id'])): ?>
                <div>
                    <a href="article.php?id=<?= $notif['article_id'] ?>" class="btn btn-link btn-sm p-0">Voir l'article</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>

</div>
</body>
</html>
