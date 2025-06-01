<?php
session_start();
date_default_timezone_set('Europe/Paris');
include "db.php";

// Charger le système d'enchères automatiques
require_once 'systeme_enchere_automatique.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit;
}

$user_id = intval($_SESSION['user_id']);
$message_success = "";
$message_error = "";

// Traitement des actions (enchère automatique)
if ($_POST) {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'enchere_automatique') {
            $article_id = intval($_POST['article_id']);
            $montant_max = floatval($_POST['montant_max']);
            
            // Utiliser le nouveau système d'enchères automatiques
            $systeme_enchere = new SystemeEnchereAutomatique($db_handle);
            $resultat = $systeme_enchere->placerEnchereAutomatique($article_id, $user_id, $montant_max);
            
            if ($resultat['success']) {
                $message_success = $resultat['message'];
            } else {
                $message_error = $resultat['message'];
            }
        }
        
        if ($_POST['action'] == 'finaliser') {
            $enchere_id = intval($_POST['enchere_id']);
            
            // Marquer l'enchère comme finalisée
            $sql_finaliser = "UPDATE enchere SET etat = 'finalise' WHERE id = ? AND acheteur_id = ?";
            $stmt = mysqli_prepare($db_handle, $sql_finaliser);
            mysqli_stmt_bind_param($stmt, "ii", $enchere_id, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $message_success = "Enchère finalisée avec succès !";
            } else {
                $message_error = "Erreur lors de la finalisation.";
            }
        }
    }
}

// Vérifier les enchères expirées avant d'afficher
$systeme_enchere = new SystemeEnchereAutomatique($db_handle);
$systeme_enchere->verifierEncheresExpirees();

// Les statuts sont maintenant calculés dynamiquement dans la requête SELECT pour éviter les problèmes MySQL

// Récupérer les informations utilisateur
$sql_user = "SELECT prenom, nom FROM utilisateur WHERE id = ?";
$stmt = mysqli_prepare($db_handle, $sql_user);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result_user = mysqli_stmt_get_result($stmt);
$user_info = mysqli_fetch_assoc($result_user);

// Récupérer les enchères actives de l'utilisateur
$sql_encheres_actives = "SELECT e.*, a.titre, a.prix_initial as prix_depart, a.prix_actuel, a.date_fin_enchere, a.photo as image_path, a.statut as article_statut
                        FROM enchere e
                        JOIN article a ON e.article_id = a.id
                        WHERE e.acheteur_id = ?
                        ORDER BY a.date_fin_enchere DESC";

$stmt = mysqli_prepare($db_handle, $sql_encheres_actives);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result_encheres = mysqli_stmt_get_result($stmt);

// Calculer les statistiques
$stats = [
    'total_encheres' => 0,
    'encheres_actives' => 0,
    'encheres_gagnees' => 0,
    'encheres_perdues' => 0,
    'montant_total_engage' => 0
];

$encheres = [];
while ($row = mysqli_fetch_assoc($result_encheres)) {
    // Calculer le temps restant en utilisant le bon nom de colonne
    // Assurons-nous d'utiliser le même fuseau horaire partout
    $date_fin_timestamp = strtotime($row['date_fin_enchere']);
    $temps_restant = $date_fin_timestamp - time();
    
    // Stocker le temps restant dans le tableau pour éviter les recalculs
    $row['temps_restant'] = $temps_restant;
      // Déterminer le statut réel de l'enchère
    if ($row['etat'] == 'finalise') {
        // L'enchère a déjà été finalisée (achat effectué)
        $row['statut_enchere'] = 'finalise';
        $stats['encheres_gagnees']++; // Compter comme gagnée
    } elseif ($temps_restant > 0) {
        // L'enchère est encore active
        $row['statut_enchere'] = 'en_cours';
        $stats['encheres_actives']++;
        $stats['montant_total_engage'] += $row['prix_max'];
    } else {
        // L'enchère est terminée - vérifier si l'utilisateur a gagné
        // Pour cela, on doit vérifier s'il a l'enchère maximum sur cet article
        $sql_check_winner = "SELECT MAX(prix_max) as max_prix FROM enchere WHERE article_id = ?";
        $stmt_check = mysqli_prepare($db_handle, $sql_check_winner);
        mysqli_stmt_bind_param($stmt_check, "i", $row['article_id']);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        $winner_data = mysqli_fetch_assoc($result_check);
          if ($winner_data && $row['prix_max'] >= $winner_data['max_prix']) {
            // Vérifier si c'est la première enchère avec ce montant (en cas d'égalité)
            $sql_first_bid = "SELECT id FROM enchere WHERE article_id = ? AND prix_max = ? ORDER BY date_enchere ASC LIMIT 1";
            $stmt_first = mysqli_prepare($db_handle, $sql_first_bid);
            mysqli_stmt_bind_param($stmt_first, "id", $row['article_id'], $winner_data['max_prix']);
            mysqli_stmt_execute($stmt_first);
            $result_first = mysqli_stmt_get_result($stmt_first);
            $first_bid = mysqli_fetch_assoc($result_first);
            
            if ($first_bid && $first_bid['id'] == $row['id']) {
                $row['statut_enchere'] = 'gagnant'; // Utiliser 'gagnant' pour correspondre à la base de données
                $stats['encheres_gagnees']++;
                
                // Mettre à jour la base de données si ce n'est pas déjà fait
                if ($row['etat'] == 'en_cours') {
                    $sql_update = "UPDATE enchere SET etat = 'gagnant' WHERE id = ?";
                    $stmt_update = mysqli_prepare($db_handle, $sql_update);
                    mysqli_stmt_bind_param($stmt_update, "i", $row['id']);
                    mysqli_stmt_execute($stmt_update);
                }
            } else {
                $row['statut_enchere'] = 'perdu'; // Utiliser 'perdu' pour correspondre à la base de données
                $stats['encheres_perdues']++;
                
                // Mettre à jour la base de données si ce n'est pas déjà fait
                if ($row['etat'] == 'en_cours') {
                    $sql_update = "UPDATE enchere SET etat = 'perdu' WHERE id = ?";
                    $stmt_update = mysqli_prepare($db_handle, $sql_update);
                    mysqli_stmt_bind_param($stmt_update, "i", $row['id']);
                    mysqli_stmt_execute($stmt_update);
                }
            }
        } else {
            $row['statut_enchere'] = 'perdu'; // Utiliser 'perdu' pour correspondre à la base de données
            $stats['encheres_perdues']++;
            
            // Mettre à jour la base de données si ce n'est pas déjà fait
            if ($row['etat'] == 'en_cours') {
                $sql_update = "UPDATE enchere SET etat = 'perdu' WHERE id = ?";
                $stmt_update = mysqli_prepare($db_handle, $sql_update);
                mysqli_stmt_bind_param($stmt_update, "i", $row['id']);
                mysqli_stmt_execute($stmt_update);
            }
        }
    }
    
    $encheres[] = $row;
    $stats['total_encheres']++;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Enchères - Agora Francia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style-site.css">    <style>
        .bid-card {
            border: 2px solid #d4af37;
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s ease;
            background: #ffffff;
        }
        
        .bid-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(212, 175, 55, 0.4);
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
        }
        
        .bid-image {
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
            background: linear-gradient(135deg, #d4af37 0%, #ffd700 100%);
            color: #000;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .success-message {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 15px;
        }
        
        .bid-card .card-body {
            background: #ffffff;
            color: #333333;
        }
        
        .bid-card .card-title {
            color: #d4af37;
        }
        
        .bid-card .card-title a {
            color: #d4af37;
        }
        
        .bid-card .card-title a:hover {
            color: #b8941f;
        }
        
        .text-muted {
            color: #6c757d !important;
        }
        
        .fw-bold.text-success {
            color: #28a745 !important;
        }
        
        .fw-bold.text-warning {
            color: #d4af37 !important;
        }        
        .aide-automatique {
            background: #ffffff;
            border: 2px solid #d4af37;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            color: #333333;
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.2);
        }
        
        .aide-automatique h3 {
            color: #d4af37;
            margin-top: 0;
            text-shadow: none;
        }
        
        .btn-outline-primary {
            border-color: #d4af37;
            color: #d4af37;
        }
        
        .btn-outline-primary:hover {
            background-color: #d4af37;
            border-color: #d4af37;
            color: #fff;
        }
        
        .btn-outline-secondary {
            border-color: #6c757d;
            color: #6c757d;
        }
        
        .btn-outline-secondary:hover {
            background-color: #6c757d;
            border-color: #6c757d;
            color: #fff;
        }
        
        .btn-outline-danger {
            border-color: #dc3545;
            color: #dc3545;
        }
        
        .btn-outline-danger:hover {
            background-color: #dc3545;
            border-color: #dc3545;
            color: #fff;
        }
        
        body {
            background: linear-gradient(135deg, #000000 0%, #1a1a1a 100%);
            color: #ffffff;
            min-height: 100vh;
        }
        
        h1 {
            color: #d4af37;
        }
        
        .breadcrumb {
            background: transparent;
        }
        
        .breadcrumb-item a {
            color: #d4af37;
        }
        
        .breadcrumb-item.active {
            color: #cccccc;
        }
        
        .form-control {
            border: 1px solid #d4af37;
        }
        
        .form-control:focus {
            border-color: #d4af37;
            box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.25);
        }
        
        .alert-success {
            background-color: #d1edcc;
            border-color: #b3d4aa;
            color: #155724;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                    <li class="breadcrumb-item"><a href="compte.php">Mon compte</a></li>
                    <li class="breadcrumb-item active">Mes enchères</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Messages de succès -->
    <?php if ($message_success): ?>
        <div class="alert success-message alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($message_success) ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Messages d'erreur -->
    <?php if ($message_error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($message_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-hammer text-warning"></i> Mes Enchères</h1>
        <a href="index.php" class="btn btn-outline-primary">
            <i class="bi bi-plus-circle"></i> Participer aux enchères
        </a>
    </div>

    <!-- Aide sur les enchères automatiques -->
    <div class="aide-automatique mb-4">
        <h3><i class="bi bi-robot"></i> Comment fonctionnent les enchères automatiques ?</h3>
        <ul class="mb-0">
            <li><strong>Enchère maximale :</strong> Le montant maximum que vous êtes prêt à payer</li>
            <li><strong>Prix actuel :</strong> Le prix minimum pour remporter l'enchère actuellement</li>
            <li><strong>Système automatique :</strong> Nous enchérissons pour vous jusqu'à votre maximum</li>
            <li><strong>Prix final :</strong> Vous ne payez que le minimum nécessaire pour gagner</li>
        </ul>
    </div>

    <?php if (empty($encheres)): ?>
        <div class="text-center py-5">
            <i class="bi bi-hammer" style="font-size: 4rem; color: #6c757d;"></i>
            <h3 class="mt-3 text-muted">Aucune enchère trouvée</h3>
            <p class="text-muted">Vous n'avez pas encore participé à des enchères.</p>
            <a href="index.php" class="btn btn-primary">
                <i class="bi bi-search"></i> Découvrir les articles
            </a>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($encheres as $enchere): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card bid-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start mb-3">
                                <?php if ($enchere['image_path']): ?>
                                    <img src="<?= htmlspecialchars($enchere['image_path']) ?>" 
                                         class="bid-image me-3" 
                                         alt="<?= htmlspecialchars($enchere['titre']) ?>">
                                <?php else: ?>
                                    <div class="no-image-placeholder me-3">
                                        <i class="bi bi-image text-muted" style="font-size: 2rem;"></i>
                                    </div>
                                <?php endif; ?>
                                  <div class="flex-grow-1">
                                    <h5 class="card-title mb-1">
                                        <a href="article.php?id=<?= $enchere['article_id'] ?>" 
                                           style="text-decoration: none;">
                                            <?= htmlspecialchars($enchere['titre']) ?>
                                        </a>
                                    </h5>
                                    <div class="vendor-info d-inline-block mb-2">
                                        <i class="bi bi-hammer"></i>
                                        Enchère automatique
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <div class="fw-bold text-success h5 mb-0">
                                        <?= number_format($enchere['prix_actuel'], 2, ',', ' ') ?> €
                                    </div>
                                    <small class="text-muted">Prix actuel</small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-warning h6 mb-0">
                                        <?= number_format($enchere['prix_max'], 2, ',', ' ') ?> €
                                    </div>
                                    <small class="text-muted">Votre maximum</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <?php
                                // Utiliser le temps restant calculé précédemment
                                if ($enchere['temps_restant'] > 0) {
                                    $jours = floor($enchere['temps_restant'] / 86400);
                                    $heures = floor(($enchere['temps_restant'] % 86400) / 3600);
                                    $minutes = floor(($enchere['temps_restant'] % 3600) / 60);
                                    echo "<small class='text-danger fw-bold'><i class='bi bi-clock'></i> {$jours}j {$heures}h {$minutes}m restantes</small>";
                                } else {
                                    echo "<small class='text-muted'><i class='bi bi-clock-history'></i> Enchère terminée le " . date('d/m/Y à H:i', strtotime($enchere['date_fin_enchere'])) . "</small>";
                                }
                                ?>
                            </div>

                            <div class="mb-3">
                                <?php
                                switch ($enchere['statut_enchere']) {
                                    case 'en_cours':
                                        echo '<span class="badge bg-success status-badge"><i class="bi bi-play-circle"></i> En cours</span>';
                                        break;
                                    case 'gagnant':
                                        echo '<span class="badge bg-warning status-badge"><i class="bi bi-trophy"></i> Gagnée</span>';
                                        break;
                                    case 'perdu':
                                        echo '<span class="badge bg-danger status-badge"><i class="bi bi-x-circle"></i> Perdue</span>';
                                        break;
                                    case 'finalise':
                                        echo '<span class="badge bg-primary status-badge"><i class="bi bi-check-circle"></i> Finalisée</span>';
                                        break;
                                    default:
                                        echo '<span class="badge bg-secondary status-badge">Inconnue</span>';
                                        break;
                                }
                                ?>
                            </div>
                            
                            <div class="d-flex flex-column gap-2 mt-3">
                                <?php if ($enchere['statut_enchere'] == 'en_cours'): ?>
                                    <form method="POST" class="d-flex gap-2">
                                        <input type="hidden" name="action" value="enchere_automatique">
                                        <input type="hidden" name="article_id" value="<?= $enchere['article_id'] ?>">
                                        <input type="number" 
                                               name="montant_max" 
                                               step="0.01" 
                                               min="<?= max($enchere['prix_actuel'] + 1, $enchere['prix_max'] + 1) ?>" 
                                               placeholder="Nouveau max (€)"
                                               class="form-control form-control-sm"
                                               required>
                                        <button type="submit" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-arrow-up"></i>
                                        </button>
                                    </form>
                                <?php elseif ($enchere['statut_enchere'] == 'gagnant' && $enchere['etat'] != 'finalise'): ?>
                                    <form method="POST" action="paiement.php">
                                        <input type="hidden" name="action" value="finaliser_enchere">
                                        <input type="hidden" name="enchere_id" value="<?= $enchere['id'] ?>">
                                        <input type="hidden" name="article_id" value="<?= $enchere['article_id'] ?>">
                                        <input type="hidden" name="prix" value="<?= $enchere['prix_actuel'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm w-100">
                                            <i class="bi bi-credit-card"></i> Finaliser l'achat
                                        </button>
                                    </form>
                                    <div class="alert alert-success p-2 mt-2 mb-0">
                                        <small>
                                            <i class="bi bi-currency-euro"></i> Vous payerez: <strong><?= number_format($enchere['prix_actuel'], 2) ?>€</strong>
                                            <br>Économie: <?= number_format($enchere['prix_max'] - $enchere['prix_actuel'], 2) ?>€
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Statistiques -->
        <div class="row mt-5">
            <div class="col-md-3">
                <div class="card text-center border-0" style="background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%); border-radius: 15px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4); border: 1px solid #d4af37;">
                    <div class="card-body">
                        <i class="bi bi-hammer" style="font-size: 2rem; color: #d4af37;"></i>
                        <h4 class="mt-2" style="color: #ffffff;"><?= $stats['total_encheres'] ?></h4>
                        <p class="mb-0" style="color: #d4af37;">Enchères total</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card text-center border-0" style="background: linear-gradient(135deg, #1a1a1a 0%, #2c2c2c 100%); border-radius: 15px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4); border: 1px solid #ffcc00;">
                    <div class="card-body">
                        <i class="bi bi-play-circle" style="font-size: 2rem; color: #ffcc00;"></i>
                        <h4 class="mt-2" style="color: #ffffff;"><?= $stats['encheres_actives'] ?></h4>
                        <p class="mb-0" style="color: #ffcc00;">En cours</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card text-center border-0" style="background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%); border-radius: 15px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4); border: 1px solid #ffd700;">
                    <div class="card-body">
                        <i class="bi bi-trophy" style="font-size: 2rem; color: #ffd700;"></i>
                        <h4 class="mt-2" style="color: #ffffff;"><?= $stats['encheres_gagnees'] ?></h4>
                        <p class="mb-0" style="color: #ffd700;">Gagnées</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card text-center border-0" style="background: linear-gradient(135deg, #1a1a1a 0%, #2c2c2c 100%); border-radius: 15px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4); border: 1px solid #daa520;">
                    <div class="card-body">
                        <i class="bi bi-currency-euro" style="font-size: 2rem; color: #daa520;"></i>
                        <h4 class="mt-2" style="color: #ffffff;">
                            <?= number_format($stats['montant_total_engage'], 2, ',', ' ') ?> €
                        </h4>
                        <p class="mb-0" style="color: #daa520;">Montant engagé</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-refresh pour les enchères en cours
setTimeout(function() {
    if (document.querySelector('.badge.bg-success')) {
        location.reload();
    }
}, 30000); // Refresh toutes les 30 secondes s'il y a des enchères actives
</script>

<?php include 'footer.php'; ?>
</body>
</html>
