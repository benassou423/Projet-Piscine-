<?php
session_start();
$database = "agora";
$db_handle = mysqli_connect('localhost', 'root', '');
$db_found = mysqli_select_db($db_handle, $database);

if (!isset($_SESSION['user_id'])) { //on redirige vers la page de connexion si l'utilisateur n'est pas connecté
    header('Location: compte.php');
    exit();
}

// Récupération des achats de l'utilisateur
$sql = "SELECT a.id as achat_id, a.prix_achat, a.date_achat, a.mode_achat,
        art.titre as article_nom, art.description as article_description, 
        art.photo as article_photo, art.prix_initial,
        v.nom as vendeur_nom, v.prenom as vendeur_prenom, v.email as vendeur_email,
        t.id as transaction_id, t.mode_paiement as methode_paiement, 'completee' as transaction_statut
        FROM Achat a
        INNER JOIN Article art ON a.article_id = art.id 
        INNER JOIN Utilisateur v ON a.vendeur_id = v.id
        LEFT JOIN transaction t ON a.transaction_id = t.id
        WHERE a.acheteur_id = ?
        ORDER BY a.date_achat DESC"; //on lie article_id (qu'on appelle art.id, issu de la table Article) à achat.article_id (qu'on appelle a.article_id, issu de la base Achat) et on trie du plus ancien au plus récent

$stmt = mysqli_prepare($db_handle, $sql); // prepare seulement la requete avant qu'on puisse l'éxécuter en remplaçant le ? par l'id de l'utilisateur connecté
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']); //la requete avec l'id à la place du ? (ligne 22)
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$achats = [];
while ($row = mysqli_fetch_assoc($result)) { //on lit $result ligne par ligne 
    $achats[] = $row; // on met ces lignes dans le tableau achats (article_nom, prix_achat etc...)
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Achats - Agora Francia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style-site.css">
    <style>
        .purchase-card {
            border: none;
            background: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .purchase-card:hover {
            transform: translateY(-2px);
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
        }
        
        .purchase-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 10px;
        }
        
        .no-image-placeholder {
            width: 100px;
            height: 100px;
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .vendor-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .success-message {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 15px;
        }

        .nav-tabs .nav-link {
            color: #495057;
            border: none;
            padding: 1rem 2rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link:hover {
            color: #0d6efd;
            border: none;
            background: rgba(13, 110, 253, 0.1);
        }

        .nav-tabs .nav-link.active {
            color: #0d6efd;
            border: none;
            border-bottom: 3px solid #0d6efd;
            background: transparent;
        }

        .tab-content {
            padding-top: 2rem;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb"> <!-- montre à l’utilisateur où il est dans le site -->
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                    <li class="breadcrumb-item"><a href="compte.php">Mon compte</a></li>
                    <li class="breadcrumb-item active">Mes achats</li>
                </ol>
            </nav>
        </div>
    </div>    <!-- Onglets -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= !isset($_GET['view']) || $_GET['view'] === 'achats' ? 'active' : '' ?>" href="?view=achats">
                <i class="bi bi-bag-check"></i> Mes Achats
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= isset($_GET['view']) && $_GET['view'] === 'ventes' ? 'active' : '' ?>" href="?view=ventes">
                <i class="bi bi-shop"></i> Mes Ventes
            </a>
        </li>
    </ul>

    <!-- Messages de succès -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert success-message alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Messages d'erreur -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>    <?php if (!isset($_GET['view']) || $_GET['view'] === 'achats'): ?>
            <!-- Vue des achats -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-bag-check text-primary"></i> Mes Achats</h1>
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="bi bi-plus-circle"></i> Continuer mes achats
                </a>
            </div>

            <?php if (empty($achats)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-bag-x" style="font-size: 4rem; color: #6c757d;"></i>
                    <h3 class="mt-3 text-muted">Aucun achat effectué</h3>
                    <p class="text-muted">Vous n'avez pas encore effectué aucun achat.</p>
                    <a href="index.php" class="btn btn-primary">
                        <i class="bi bi-search"></i> Découvrir les articles
                    </a>
                </div>
            <?php else: ?>
                <!-- Liste des achats -->
                <div class="row">
                    <?php foreach ($achats as $achat): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card purchase-card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-start mb-3">
                                        <?php if ($achat['article_photo']): ?>
                                            <img src="<?= htmlspecialchars($achat['article_photo']) ?>" 
                                                 class="purchase-image me-3" 
                                                 alt="<?= htmlspecialchars($achat['article_nom']) ?>">
                                        <?php else: ?>
                                            <div class="no-image-placeholder me-3">
                                                <i class="bi bi-image text-muted" style="font-size: 2rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="flex-grow-1">
                                            <h5 class="card-title mb-1"><?= htmlspecialchars($achat['article_nom']) ?></h5>
                                            <div class="vendor-info d-inline-block mb-2">
                                                <i class="bi bi-person"></i>
                                                <?= htmlspecialchars($achat['vendeur_prenom'] . ' ' . $achat['vendeur_nom']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <p class="text-muted small mb-3">
                                        <?= htmlspecialchars(substr($achat['article_description'], 0, 100)) ?>
                                        <?= strlen($achat['article_description']) > 100 ? '...' : '' ?>
                                    </p>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <div class="fw-bold text-success h5 mb-0">
                                                <?= number_format($achat['prix_achat'], 2, ',', ' ') ?> €
                                            </div>
                                            <small class="text-muted">
                                                <?= date('d/m/Y à H:i', strtotime($achat['date_achat'])) ?>
                                            </small>
                                        </div>
                                        
                                        <div class="text-end">
                                            <?php if ($achat['transaction_statut'] === 'completee'): ?>
                                                <span class="badge bg-success status-badge">
                                                    <i class="bi bi-check-circle"></i> Payé
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning status-badge">
                                                    <i class="bi bi-clock"></i> En attente
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($achat['methode_paiement']): ?>
                                                <div class="mt-1">
                                                    <small class="text-muted">
                                                        <?php
                                                        switch($achat['methode_paiement']) {
                                                            case 'carte':
                                                                echo '<i class="bi bi-credit-card"></i> Carte bancaire';
                                                                break;
                                                            case 'virement':
                                                                echo '<i class="bi bi-bank"></i> Virement';
                                                                break;
                                                            case 'apple_pay':
                                                                echo '<i class="bi bi-apple"></i> Apple Pay';
                                                                break;
                                                            default:
                                                                echo $achat['methode_paiement'];
                                                        }
                                                        ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>                            </div>
                                    
                                    <div class="d-flex flex-column gap-2 mt-3">
                                        <div class="d-flex gap-2">
                                            <?php if ($achat['transaction_id']): ?>
                                                <button class="btn btn-outline-primary btn-sm flex-fill" 
                                                        onclick="showTransactionDetails(<?= $achat['transaction_id'] ?>)">
                                                    <i class="bi bi-receipt"></i> Détails
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-outline-secondary btn-sm flex-fill" 
                                                    onclick="contactVendor('<?= htmlspecialchars($achat['vendeur_email']) ?>')">
                                                <i class="bi bi-envelope"></i> Contacter
                                            </button>
                                        </div>
                                        
                                        <button class="btn btn-outline-danger btn-sm w-100" 
                                                onclick="confirmDelete(<?= $achat['achat_id'] ?>, '<?= htmlspecialchars(addslashes($achat['article_nom'])) ?>')">
                                            <i class="bi bi-trash"></i> Supprimer de l'historique
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>            <?php endforeach; ?>
                </div>        
                
                <!-- Statistiques des achats -->
                <div class="row mt-5">
                    <div class="col-md-4">
                        <div class="card text-center border-0" style="background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%); border-radius: 15px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4); border: 1px solid #d4af37;">
                            <div class="card-body">
                                <i class="bi bi-bag-check-fill" style="font-size: 2rem; color: #d4af37;"></i>
                                <h4 class="mt-2" style="color: #ffffff;"><?= count($achats) ?></h4>
                                <p class="mb-0" style="color: #d4af37;">Achats effectués</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card text-center border-0" style="background: linear-gradient(135deg, #1a1a1a 0%, #2c2c2c 100%); border-radius: 15px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4); border: 1px solid #ffcc00;">
                            <div class="card-body">
                                <i class="bi bi-currency-euro" style="font-size: 2rem; color: #ffcc00;"></i>
                                <h4 class="mt-2" style="color: #ffffff;">
                                    <?= number_format(array_sum(array_column($achats, 'prix_achat')), 2, ',', ' ') ?> €
                                </h4>
                                <p class="mb-0" style="color: #ffcc00;">Total dépensé</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card text-center border-0" style="background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%); border-radius: 15px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4); border: 1px solid #ffd700;">
                            <div class="card-body">
                                <i class="bi bi-calendar-check" style="font-size: 2rem; color: #ffd700;"></i>
                                <h4 class="mt-2" style="color: #ffffff;">
                                    <?php
                                    $recentPurchases = array_filter($achats, function($achat) {
                                        return strtotime($achat['date_achat']) > strtotime('-30 days');
                                    });
                                    echo count($recentPurchases);
                                    ?>
                                </h4>
                                <p class="mb-0" style="color: #ffd700;">Ce mois-ci</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Vue des ventes -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-shop text-primary"></i> Mes Ventes</h1>
                <a href="ajout_article.php" class="btn btn-outline-primary">
                    <i class="bi bi-plus-circle"></i> Mettre un article en vente
                </a>
            </div>

            <?php
            // Statistiques des ventes
            $query_stats = "SELECT 
                COUNT(DISTINCT a.id) as total_ventes,
                SUM(a.prix_achat) as total_revenus,
                COUNT(DISTINCT CASE WHEN a.statut = 'terminé' THEN a.id END) as ventes_terminees,
                COUNT(DISTINCT CASE WHEN a.statut = 'en_cours' THEN a.id END) as ventes_en_cours
                FROM Achat a
                WHERE a.vendeur_id = ?";
            
            $stmt_stats = mysqli_prepare($db_handle, $query_stats);
            mysqli_stmt_bind_param($stmt_stats, "i", $_SESSION['user_id']);
            mysqli_stmt_execute($stmt_stats);
            $result_stats = mysqli_stmt_get_result($stmt_stats);
            $stats = mysqli_fetch_assoc($result_stats);
            ?>

            <!-- Statistiques des ventes -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white" style="background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%); border-radius: 15px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4); border: 1px solid #d4af37;">
                        <div class="card-body">
                            <h5 class="card-title">Total des ventes</h5>
                            <p class="card-text h2"><?= $stats['total_ventes'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white" style="background: linear-gradient(135deg, #1a1a1a 0%, #2c2c2c 100%); border-radius: 15px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4); border: 1px solid #ffcc00;">
                        <div class="card-body">
                            <h5 class="card-title">Revenus totaux</h5>
                            <p class="card-text h2"><?= number_format($stats['total_revenus'] ?? 0, 2, ',', ' ') ?> €</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white" style="background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%); border-radius: 15px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4); border: 1px solid #ffd700;">
                        <div class="card-body">
                            <h5 class="card-title">Ventes terminées</h5>
                            <p class="card-text h2"><?= $stats['ventes_terminees'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white" style="background: linear-gradient(135deg, #1a1a1a 0%, #2c2c2c 100%); border-radius: 15px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4); border: 1px solid #ffd700;">
                        <div class="card-body">
                            <h5 class="card-title">Ventes en cours</h5>
                            <p class="card-text h2"><?= $stats['ventes_en_cours'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste des ventes -->
            <div class="row">
                <div class="col-12">                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1><i class="bi bi-shop text-primary"></i> Mes Ventes</h1>
                        <a href="ajout_article.php" class="btn btn-outline-primary">
                            <i class="bi bi-plus-circle"></i> Mettre un article en vente
                        </a>
                    </div>

                    <?php
                    $query_ventes = "SELECT 
                        a.id as achat_id,
                        a.date_achat,
                        a.prix_achat,
                        a.mode_achat,
                        a.statut,
                        ar.titre,
                        ar.photo,
                        ar.description,
                        t.mode_paiement,
                        t.date_paiement,
                        u.nom as acheteur_nom,
                        u.prenom as acheteur_prenom,
                        u.email as acheteur_email
                    FROM Achat a
                    JOIN Article ar ON a.article_id = ar.id
                    LEFT JOIN transaction t ON a.transaction_id = t.id
                    JOIN Utilisateur u ON a.acheteur_id = u.id
                    WHERE a.vendeur_id = ?
                    ORDER BY a.date_achat DESC";

                    $stmt_ventes = mysqli_prepare($db_handle, $query_ventes);
                    mysqli_stmt_bind_param($stmt_ventes, "i", $_SESSION['user_id']);
                    mysqli_stmt_execute($stmt_ventes);
                    $result_ventes = mysqli_stmt_get_result($stmt_ventes);
                    $ventes = [];
                    while ($row = mysqli_fetch_assoc($result_ventes)) {
                        $ventes[] = $row;
                    }

                    if (count($ventes) > 0):
                        foreach ($ventes as $vente): ?>
                            <div class="card purchase-card mb-3">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-2">
                                            <?php if ($vente['photo']): ?>
                                                <img src="<?= htmlspecialchars($vente['photo']) ?>" 
                                                     class="purchase-image" 
                                                     alt="<?= htmlspecialchars($vente['titre']) ?>">
                                            <?php else: ?>
                                                <div class="no-image-placeholder">
                                                    <i class="bi bi-image text-muted" style="font-size: 2rem;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-7">
                                            <h5 class="card-title"><?= htmlspecialchars($vente['titre']) ?></h5>
                                            <p class="card-text text-muted"><?= htmlspecialchars($vente['description']) ?></p>
                                            <div class="vendor-info d-inline-block">
                                                <i class="bi bi-person"></i>
                                                Acheté par <?= htmlspecialchars($vente['acheteur_prenom'] . ' ' . $vente['acheteur_nom']) ?>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-end">
                                                <h5 class="text-success mb-2"><?= number_format($vente['prix_achat'], 2, ',', ' ') ?> €</h5>
                                                <span class="badge bg-<?= $vente['statut'] === 'terminé' ? 'success' : 'warning' ?> status-badge">
                                                    <?= ucfirst($vente['statut']) ?>
                                                </span>
                                                <?php if ($vente['mode_paiement']): ?>
                                                    <div class="mt-2">
                                                        <small class="text-muted">
                                                            <?php
                                                            switch($vente['mode_paiement']) {
                                                                case 'carte':
                                                                    echo '<i class="bi bi-credit-card"></i> Carte bancaire';
                                                                    break;
                                                                case 'virement':
                                                                    echo '<i class="bi bi-bank"></i> Virement';
                                                                    break;
                                                                case 'apple_pay':
                                                                    echo '<i class="bi bi-apple"></i> Apple Pay';
                                                                    break;
                                                                default:
                                                                    echo htmlspecialchars($vente['mode_paiement']);
                                                            }
                                                            ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="mt-3">
                                                    <button class="btn btn-outline-primary btn-sm" 
                                                            onclick="contactAcheteur('<?= htmlspecialchars(addslashes($vente['acheteur_email'])) ?>')">
                                                        <i class="bi bi-envelope"></i> Contacter l'acheteur
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach;
                    else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-shop" style="font-size: 4rem; color: #6c757d;"></i>                            <h3 class="mt-3 text-muted">Aucune vente effectuée</h3>
                            <p class="text-muted">Vous n'avez pas encore vendu d'articles.</p>
                            <a href="ajout_article.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Mettre un article en vente
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="bi bi-exclamation-triangle text-warning"></i> Confirmer la suppression
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer cet achat ?</p>
                <div class="alert alert-warning">
                    <strong>Article :</strong> <span id="articleName"></span><br>
                    <small><i class="bi bi-info-circle"></i> Cette action est irréversible et supprimera définitivement toutes les données associées à cet achat.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Annuler
                </button>
                <form id="deleteForm" method="POST" action="supprimer_achat.php" style="display: inline;">
                    <input type="hidden" name="achat_id" id="achatIdToDelete">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Supprimer définitivement
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() { //récupère les paramètres dans l'URL
    const params = new URLSearchParams(window.location.search); 
    const currentView = params.get('view') || 'achats'; //vue actuelle ou achats par defaut
    
    // Mettre à jour l'URL quand on clique sur un onglet
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const newView = this.getAttribute('href').split('=')[1];
            window.location.href = `?view=${newView}`;
        });
    });
});

// Fonctions utilitaires
function confirmDelete(achatId, articleName) {
    document.getElementById('achatIdToDelete').value = achatId;
    document.getElementById('articleName').textContent = articleName;
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal')); // nouvelle modale à afficher
    deleteModal.show(); //affiche le msg de confirmation (modale)
}

function contactVendor(email) {
    if (email) {
        window.location.href = 'mailto:' + email + '?subject=Question concernant votre article';
    } else {
        alert('Email du vendeur non disponible.');
    }
}

function contactAcheteur(email) {
    if (email) {
        window.location.href = 'mailto:' + email + '?subject=À propos de votre achat sur Agora Francia';
    } else {
        alert('Email de l\'acheteur non disponible.');
    }
}

function showTransactionDetails(transactionId) {
    alert('Détails de la transaction #' + transactionId + '\n\nCette fonctionnalité sera bientôt disponible.');
}
</script>

<?php include 'footer.php'; ?>
</body>
</html>
