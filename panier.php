<?php
session_start(); // Démarrage de la session

// Connexion à la base de données
$database = "agora";
$db_handle = mysqli_connect('localhost', 'root', '');
$db_found = mysqli_select_db($db_handle, $database);

// Redirection si l'utilisateur n'est pas connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: compte.php');
    exit();
}

// Traitement de la suppression d’un article du panier
if (isset($_POST['remove_from_cart']) && !empty($_POST['remove_from_cart'])) {
    $article_id = intval($_POST['remove_from_cart']);

    // Récupération de l'ID du panier de l'utilisateur
    $sql = "SELECT id FROM Panier WHERE acheteur_id = ?";
    $stmt = mysqli_prepare($db_handle, $sql);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    // Suppression de l'article du panier
    if ($panier = mysqli_fetch_assoc($result)) {
        $sql = "DELETE FROM ArticlePanier WHERE article_id = ? AND panier_id = ?";
        $stmt = mysqli_prepare($db_handle, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $article_id, $panier['id']);
        mysqli_stmt_execute($stmt);
    }

    header('Location: panier.php'); // Rafraîchissement de la page
    exit();
}

// Traitement du vidage complet du panier
if (isset($_POST['empty_cart'])) {
    $sql = "SELECT id FROM Panier WHERE acheteur_id = ?";
    $stmt = mysqli_prepare($db_handle, $sql);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    // Suppression de tous les articles liés à ce panier
    if ($panier = mysqli_fetch_assoc($result)) {
        $sql = "DELETE FROM ArticlePanier WHERE panier_id = ?";
        $stmt = mysqli_prepare($db_handle, $sql);
        mysqli_stmt_bind_param($stmt, "i", $panier['id']);
        mysqli_stmt_execute($stmt);
    }

    header('Location: panier.php'); // Redirection
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panier - Agora Francia</title>
    <!-- Import des styles Bootstrap et personnalisés -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style-site.css">
</head>
<body>

<?php include 'header.php'; // Inclusion de l'en-tête ?>

<?php
// Récupération de l’ID du panier ou création s’il n’existe pas
$sql = "SELECT id FROM Panier WHERE acheteur_id = ?";
$stmt = mysqli_prepare($db_handle, $sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$panier = mysqli_fetch_assoc($result)) {
    $sql = "INSERT INTO Panier (acheteur_id, date_creation) VALUES (?, NOW())";
    $stmt = mysqli_prepare($db_handle, $sql);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $panier_id = mysqli_insert_id($db_handle);
} else {
    $panier_id = $panier['id'];
}

// Requête pour obtenir les articles présents dans le panier
$sql = "SELECT a.id, a.titre as nom, a.description, a.photo, 
        CASE 
            WHEN ap.mode_achat = 'enchere' THEN COALESCE(a.prix_actuel, a.prix_initial)
            ELSE a.prix_initial 
        END as prix, 
        ap.mode_achat, 1 as nb_articles, 
        CASE 
            WHEN ap.mode_achat = 'enchere' THEN COALESCE(a.prix_actuel, a.prix_initial)
            ELSE a.prix_initial 
        END as sous_total,
        CASE 
            WHEN ap.mode_achat = 'enchere' THEN 1 
            WHEN a.statut = 'disponible' THEN 1 
            ELSE 0 
        END as stock
        FROM Article a 
        INNER JOIN ArticlePanier ap ON a.id = ap.article_id 
        INNER JOIN Panier p ON ap.panier_id = p.id
        WHERE p.id = ?";
$stmt = mysqli_prepare($db_handle, $sql);
mysqli_stmt_bind_param($stmt, "i", $panier_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Récupération des données et calcul du total
$total = 0;
$articles = [];
while ($row = mysqli_fetch_assoc($result)) {
    $articles[] = $row;
    $total += floatval($row['sous_total']);
}
?>

<!-- Contenu principal -->
<div class="container mt-4">
    <h1 class="mb-4">Votre Panier</h1>

    <?php if (empty($articles)): ?>
        <!-- Message si panier vide -->
        <div class="alert alert-info">
            <i class="bi bi-cart-x"></i> Votre panier est vide. <a href="catalogue.php">Continuez vos achats</a>
        </div>
    <?php else: ?>
        <div class="row">
            <!-- Colonne de gauche : liste des articles -->
            <div class="col-md-8">
                <div class="cart-items">
                    <?php foreach ($articles as $article): ?>
                        <div class="card mb-3" id="cart-item-<?= $article['id'] ?>">
                            <div class="row g-0">
                                <div class="col-md-3">
                                    <!-- Affichage image article -->
                                    <?php if (isset($article['photo']) && $article['photo']): ?>
                                        <img src="<?= htmlspecialchars($article['photo']) ?>" class="img-fluid rounded-start cart-img" alt="<?= htmlspecialchars($article['nom'] ?? 'Photo article') ?>">
                                    <?php else: ?>
                                        <!-- Si pas de photo -->
                                        <div class="no-image text-center py-4 bg-light">
                                            <i class="bi bi-image text-muted" style="font-size: 2rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-9">
                                    <div class="card-body">
                                        <!-- Titre + bouton de suppression -->
                                        <div class="d-flex justify-content-between align-items-start">
                                            <h5 class="card-title"><?= htmlspecialchars($article['nom'] ?? 'Article sans nom') ?></h5>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Voulez-vous retirer cet article du panier ?');">
                                                <input type="hidden" name="remove_from_cart" value="<?= $article['id'] ?>">
                                                <button type="submit" class="btn-close"></button>
                                            </form>
                                        </div>

                                        <!-- Description raccourcie -->
                                        <p class="card-text">
                                            <?= htmlspecialchars(substr($article['description'] ?? 'Aucune description disponible', 0, 100)) . '...' ?>
                                        </p>

                                        <!-- Prix et quantité -->
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="input-group cart-qty-group">
                                                <button type="button" class="btn btn-outline-secondary" disabled>-</button>
                                                <input type="number" class="form-control text-center" value="1" min="1" readonly>
                                                <button type="button" class="btn btn-outline-secondary" disabled>+</button>
                                            </div>
                                            <div class="price">
                                                <span class="item-price fw-bold">
                                                    <?= number_format(floatval($article['prix'] ?? 0), 2, ',', ' ') ?> €
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Avertissement si article non disponible -->
                                        <?php if (!$article['stock']): ?>
                                            <div class="alert alert-warning mt-2 mb-0">
                                                <small>Article non disponible</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Colonne de droite : résumé de commande -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Résumé de la commande</h5>

                        <!-- Détail du prix -->
                        <div class="d-flex justify-content-between mb-3">
                            <span>Sous-total</span>
                            <span><?= number_format($total, 2, ',', ' ') ?> €</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="fw-bold">Total</span>
                            <span class="fw-bold"><?= number_format($total, 2, ',', ' ') ?> €</span>
                        </div>

                        <!-- Boutons : paiement + vider -->
                        <div class="d-grid gap-2">
                            <?php
                            $allAvailable = true;
                            foreach ($articles as $article) {
                                if (!$article['stock']) {
                                    $allAvailable = false;
                                    break;
                                }
                            }
                            ?>
                            <a href="paiement.php" class="btn btn-primary w-100 <?= !$allAvailable ? 'disabled' : '' ?>" 
                               <?= !$allAvailable ? 'aria-disabled="true" onclick="return false;"' : '' ?>>
                                <i class="bi bi-credit-card"></i> Procéder au paiement
                            </a>

                            <form method="post" onsubmit="return confirm('Voulez-vous vraiment vider votre panier ?');">
                                <input type="hidden" name="empty_cart" value="1">
                                <button type="submit" class="btn btn-outline-danger w-100">Vider le panier</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; // Inclusion du pied de page ?>
</body>
</html>
