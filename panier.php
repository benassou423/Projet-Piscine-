<?php
session_start();
// Démarre la session afin d’accéder aux variables de session (user_id, etc.)

// Connexion à la base de données
$database  = "agora";
$db_handle = mysqli_connect('localhost', 'root', 'root');
// mysqli_connect retourne la ressource de connexion ou false en cas d’échec
$db_found  = mysqli_select_db($db_handle, $database);
// mysqli_select_db sélectionne la BDD “agora” pour les prochaines requêtes

// Si l’utilisateur n’est pas connecté (il n’y a pas de user_id en session), on le renvoie vers la page de connexion
if (!isset($_SESSION['user_id'])) {
    header('Location: compte.php');
    exit();
}

// 1) Retirer un article précis du panier
// On vérifie si le formulaire d’envoi comporte “remove_from_cart” et que ce n’est pas vide
if (isset($_POST['remove_from_cart']) && !empty($_POST['remove_from_cart'])) {
    // On convertit la valeur reçue en entier (sécurité contre injection)
    $article_id = intval($_POST['remove_from_cart']);

    // 1.1) Récupérer l'ID du panier associé à cet utilisateur
    // On construit la requête en concaténant directement $_SESSION['user_id']
    $sql    = "SELECT id FROM Panier WHERE acheteur_id = " . $_SESSION['user_id'];
    $result = mysqli_query($db_handle, $sql);
    // mysqli_query exécute la requête SQL et renvoie un résultat ou false en cas d’erreur

    // 1.2) Si la requête renvoie au moins une ligne, alors le panier existe
    if (mysqli_num_rows($result) > 0) {
        // On récupère la première (et unique) ligne : l’ID du panier
        $panier = mysqli_fetch_assoc($result);
        // On prépare la requête DELETE pour effacer l’article du panier
        $sql = "DELETE FROM ArticlePanier 
                WHERE article_id = " . $article_id . " 
                  AND panier_id   = " . $panier['id'];
        // On exécute la suppression
        mysqli_query($db_handle, $sql);
    }

    // 1.3) On redirige à nouveau vers la page panier pour voir le résultat
    header('Location: panier.php');
    exit();
}

// 2) Vider complètement le panier 
// On vérifie si le formulaire d’envoi comporte “empty_cart”
if (isset($_POST['empty_cart'])) {
    // 2.1) Récupérer l'ID du panier de l’utilisateur connecté
    $sql    = "SELECT id FROM Panier WHERE acheteur_id = " . $_SESSION['user_id'];
    $result = mysqli_query($db_handle, $sql);

    // 2.2) Si la requête renvoie au moins une ligne, le panier existe
    if (mysqli_num_rows($result) > 0) {
        // On récupère l’ID du panier
        $panier = mysqli_fetch_assoc($result);
        // On prépare la requête DELETE pour effacer tous les ArticlePanier liés à ce panier
        $sql = "DELETE FROM ArticlePanier 
                WHERE panier_id = " . $panier['id'];
        mysqli_query($db_handle, $sql);
    }

    // 2.3) On redirige vers panier.php pour actualiser l’affichage
    header('Location: panier.php');
    exit();
}

// RÉCUPÉRATION OU CRÉATION DU PANIER DE L'UTILISATEUR
// On utilise ici une requête préparée pour récupérer l’ID du panier
$sql  = "SELECT id FROM Panier WHERE acheteur_id = ?";
$stmt = mysqli_prepare($db_handle, $sql);
// On lie le paramètre “i” (integer) à la valeur $_SESSION['user_id']
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Si aucun panier n’a été trouvé (fetch_assoc renvoie false), on crée un nouveau panier
if (!$panier = mysqli_fetch_assoc($result)) {
    // Requête préparée pour insérer un nouveau panier
    $sql  = "INSERT INTO Panier (acheteur_id, date_creation) VALUES (?, NOW())";
    $stmt = mysqli_prepare($db_handle, $sql);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    // On récupère l’ID du nouveau panier créé
    $panier_id = mysqli_insert_id($db_handle);
} else {
    // Si le panier existait déjà, on garde son ID
    $panier_id = $panier['id'];
}

// RÉCUPÉRATION DES ARTICLES DANS LE PANIER

// Requête SQL qui sélectionne toutes les informations utiles pour chaque article du panier
$sql = "
    SELECT 
        a.id, 
        a.titre       AS nom,         -- Le titre de l'article renommé en “nom”
        a.description,                  -- La description complète
        a.photo,                        -- Le chemin de la photo (URL ou fichier local)

        -- Prix unitaire : si mode_achat = 'enchere', on prend prix_actuel ou prix_initial, sinon prix_initial
        CASE 
            WHEN ap.mode_achat = 'enchere' 
                THEN COALESCE(a.prix_actuel, a.prix_initial)
            ELSE a.prix_initial 
        END AS prix, 

        ap.mode_achat,                  -- Indique “enchere” ou “achat”/“negociation”

        1               AS nb_articles,  -- On fixe la quantité à 1 (pour l’instant)

        -- Sous-total = même calcul que le prix (quantité = 1)
        CASE 
            WHEN ap.mode_achat = 'enchere' 
                THEN COALESCE(a.prix_actuel, a.prix_initial)
            ELSE a.prix_initial 
        END AS sous_total,

        -- Disponibilité : 1 si en mode enchère OU si l'article est en statut 'disponible', sinon 0
        CASE 
            WHEN ap.mode_achat = 'enchere' THEN 1 
            WHEN a.statut      = 'disponible' THEN 1 
            ELSE 0 
        END AS stock

    FROM Article a 
    INNER JOIN ArticlePanier ap ON a.id = ap.article_id 
    INNER JOIN Panier p         ON ap.panier_id = p.id
    WHERE p.id = ?
";
$stmt = mysqli_prepare($db_handle, $sql);
// On lie le paramètre “i” (integer) avec l’ID du panier récupéré ou créé précédemment
mysqli_stmt_bind_param($stmt, "i", $panier_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// On initialise le total et un tableau vide pour stocker les articles
$total    = 0;
$articles = [];

// Parcours de chaque ligne de résultat : chaque $row est un article du panier
while ($row = mysqli_fetch_assoc($result)) {
    $articles[] = $row;
    // On ajoute le sous-total (converti en float) au total général
    $total     += floatval($row['sous_total']);
}

// Vérifier si tous les articles sont disponibles (stock = 1)

$allAvailable = true;
foreach ($articles as $article) {
    // Si au moins un article a 'stock' = 0, on marque comme indisponible
    if (!$article['stock']) {
        $allAvailable = false;
        break;
    }
}
?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panier - Agora Francia</title>
    <!-- Chargement de Bootstrap 5 pour le style (grille, cartes, boutons, etc.) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chargement des icônes Bootstrap Icons (icônes “cart-x”, “credit-card”, etc.) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Feuille de style personnalisée -->
    <link rel="stylesheet" href="css/style-site.css">
</head>
<body>
<?php include 'header.php'; 
// Inclusion de l’en-tête commun (logo, menu de navigation, etc.) ?>

<div class="container mt-4">
    <h1 class="mb-4">Votre Panier</h1>

    <!-- Si le panier est vide, on affiche un message d’information -->
    <?php if (empty($articles)): ?>
        <div class="alert alert-info">
            <i class="bi bi-cart-x"></i> Votre panier est vide. <a href="catalogue.php">Continuez vos achats</a>
        </div>
    <?php else: ?>
        <div class="row">
            <!-- SECTION : liste des articles (colonne à gauche) -->
            <div class="col-md-8">
                <div class="cart-items">
                    <?php foreach ($articles as $article): ?>
                        <!-- Chaque article est affiché dans une carte Bootstrap -->
                        <div class="card mb-3" id="cart-item-<?= $article['id'] ?>">
                            <div class="row g-0">
                                <!-- Colonne image (3/12) -->
                                <div class="col-md-3">
                                    <?php if (!empty($article['photo'])): ?>
                                        <!-- Si l’article possède une photo -->
                                        <img src="<?= htmlspecialchars($article['photo']) ?>"
                                             class="img-fluid rounded-start cart-img"
                                             alt="<?= htmlspecialchars($article['nom'] ?? 'Photo article') ?>">
                                    <?php else: ?>
                                        <!-- Sinon, on met un placeholder (icône) -->
                                        <div class="no-image text-center py-4 bg-light">
                                            <i class="bi bi-image text-muted" style="font-size: 2rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <!-- Colonne détails (9/12) -->
                                <div class="col-md-9">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <!-- Titre de l'article -->
                                            <h5 class="card-title">
                                                <?= htmlspecialchars($article['nom'] ?? 'Article sans nom') ?>
                                            </h5>
                                            <!-- Formulaire pour retirer l'article (bouton “X”) -->
                                            <form method="post" class="d-inline"
                                                  onsubmit="return confirm('Voulez-vous retirer cet article du panier ?');">
                                                <input type="hidden" name="remove_from_cart" value="<?= $article['id'] ?>">
                                                <button type="submit" class="btn-close"></button>
                                            </form>
                                        </div>
                                        <!-- Description tronquée à 100 caractères -->
                                        <p class="card-text">
                                            <?= htmlspecialchars(substr($article['description'] ?? 'Aucune description disponible', 0, 100)) . '...' ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <!-- Prix unitaire formaté -->
                                            <div class="price">
                                                <span class="item-price fw-bold">
                                                    <?= number_format(floatval($article['prix'] ?? 0), 2, ',', ' ') ?> €
                                                </span>
                                            </div>
                                        </div>
                                        <!-- Avertissement si l’article n’est plus en stock -->
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

            <!-- SECTION : résumé de la commande (colonne à droite) -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Résumé de la commande</h5>
                        <!-- Sous-total -->
                        <div class="d-flex justify-content-between mb-3">
                            <span>Sous-total</span>
                            <span><?= number_format($total, 2, ',', ' ') ?> €</span>
                        </div>
                        <hr>
                        <!-- Total (pour l’instant identique au sous-total) -->
                        <div class="d-flex justify-content-between mb-3">
                            <span class="fw-bold">Total</span>
                            <span class="fw-bold"><?= number_format($total, 2, ',', ' ') ?> €</span>
                        </div>
                        <div class="d-grid gap-2">
                            <!-- Bouton « Procéder au paiement » : désactivé si un article en rupture -->
                            <a href="paiement.php"
                               class="btn btn-primary w-100 <?= !$allAvailable ? 'disabled' : '' ?>"
                               <?= !$allAvailable ? 'aria-disabled="true" onclick="return false;"' : '' ?>>
                                <i class="bi bi-credit-card"></i> Procéder au paiement
                            </a>
                            <!-- Formulaire pour vider entièrement le panier -->
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

<?php include 'footer.php'; 
// Inclusion du pied de page commun (liens, mentions légales, etc.) ?>

</body>
</html>
