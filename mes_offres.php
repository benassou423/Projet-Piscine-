<?php
session_start();
include "db.php";

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = intval($_SESSION['user_id']);
$message_success = "";
$message_error = "";

// Traitement des actions
if ($_POST) {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'contre_offre') {
            $negociation_id = intval($_POST['negociation_id']);
            $nouvelle_offre = floatval($_POST['nouvelle_offre']);
            $article_id = intval($_POST['article_id']);
            
            // Vérifier que la négociation existe et appartient à l'utilisateur
            $sql_check = "SELECT n.*, a.titre as article_nom, a.prix_initial as prix_reserve, a.vendeur_id
                         FROM negociation n 
                         JOIN article a ON n.article_id = a.id 
                         WHERE n.id = ? AND n.acheteur_id = ? AND n.etat = 'en_cours'";
            $stmt_check = mysqli_prepare($db_handle, $sql_check);
            mysqli_stmt_bind_param($stmt_check, "ii", $negociation_id, $user_id);
            mysqli_stmt_execute($stmt_check);
            $result_check = mysqli_stmt_get_result($stmt_check);
            
            if ($result_check && mysqli_num_rows($result_check) > 0) {
                $nego = mysqli_fetch_assoc($result_check);
                
                // Vérifier le nombre de tours (max 5 offres par article)
                $sql_count = "SELECT COUNT(*) as nb_tours FROM negociation 
                             WHERE article_id = ? AND acheteur_id = ?";
                $stmt_count = mysqli_prepare($db_handle, $sql_count);
                mysqli_stmt_bind_param($stmt_count, "ii", $article_id, $user_id);
                mysqli_stmt_execute($stmt_count);
                $result_count = mysqli_stmt_get_result($stmt_count);
                $count_data = mysqli_fetch_assoc($result_count);
                  if ($count_data['nb_tours'] < 5) { // Créer une nouvelle négociation
                    $nouveau_tour = $nego['tour'] + 1;                    
                    $stmt_insert = mysqli_prepare($db_handle, "INSERT INTO negociation (article_id, acheteur_id, vendeur_id, tour, offre_acheteur, etat, date_action) 
                                  VALUES (?, ?, ?, ?, ?, 'en_cours', NOW())");
                    mysqli_stmt_bind_param($stmt_insert, "iiiid", $article_id, $user_id, $nego['vendeur_id'], $nouveau_tour, $nouvelle_offre);
                    
                    if (mysqli_stmt_execute($stmt_insert)) {
                        $message_success = "Votre contre-offre a été envoyée avec succès !";
                        mysqli_stmt_close($stmt_insert);
                        
                        // Créer une notification pour le vendeur avec requête préparée et contenu sécurisé
                        $montant_formatte = number_format($nouvelle_offre, 2, ',', ' ');
                        $contenu = sprintf("Nouvelle contre-offre de %s€ sur votre article %s", 
                        $montant_formatte, 
                        $nego['article_nom']);
    
    $stmt_notif = mysqli_prepare($db_handle, 
        "INSERT INTO notification (user_id, contenu, date_creation, article_id) VALUES (?, ?, NOW(), ?)"
    );
    if ($stmt_notif) {
        mysqli_stmt_bind_param($stmt_notif, "isi", $nego['vendeur_id'], $contenu, $article_id);
        mysqli_stmt_execute($stmt_notif);
        mysqli_stmt_close($stmt_notif);
    }
                    } else {
                        $message_error = "Erreur lors de l'envoi de la contre-offre.";
                    }
                } else {
                    $message_error = "Vous avez atteint le maximum de 5 offres pour cet article.";
                }
            } else {
                $message_error = "Négociation non trouvée ou non valide.";
            }
        }
        
        if ($_POST['action'] == 'finaliser') {
            $negociation_id = intval($_POST['negociation_id']);
            
            // Vérifier que l'offre est acceptée
            $sql_check = "SELECT n.*, a.titre as article_nom 
                         FROM negociation n 
                         JOIN article a ON n.article_id = a.id 
                         WHERE n.id = ? AND n.acheteur_id = ? AND n.etat = 'accepte'";
            $stmt_check = mysqli_prepare($db_handle, $sql_check);
            mysqli_stmt_bind_param($stmt_check, "ii", $negociation_id, $user_id);
            mysqli_stmt_execute($stmt_check);
            $result_check = mysqli_stmt_get_result($stmt_check);
            
            if ($result_check && mysqli_num_rows($result_check) > 0) {
                $nego = mysqli_fetch_assoc($result_check);
                // Rediriger vers le paiement
                header("Location: paiement.php?type=negociation&negociation_id=$negociation_id");
                exit;
            } else {
                $message_error = "Offre non trouvée ou non acceptée.";
            }
        }
        
        // Gestion des actions pour les offres reçues (vendeur)
        if ($_POST['action'] == 'accepter_offre') {
            $negociation_id = intval($_POST['negociation_id']);
            
            // Vérifier que la négociation existe et appartient au vendeur
            $sql_check = "SELECT n.*, a.titre as article_nom 
                         FROM negociation n 
                         JOIN article a ON n.article_id = a.id 
                         WHERE n.id = ? AND a.vendeur_id = ? AND n.etat = 'en_cours'";
            $stmt_check = mysqli_prepare($db_handle, $sql_check);
            mysqli_stmt_bind_param($stmt_check, "ii", $negociation_id, $user_id);
            mysqli_stmt_execute($stmt_check);
            $result_check = mysqli_stmt_get_result($stmt_check);
            
            if ($result_check && mysqli_num_rows($result_check) > 0) {
                $nego = mysqli_fetch_assoc($result_check);
                // Accepter l'offre
                $sql_update = "UPDATE negociation SET etat = 'accepte' WHERE id = ?";
                $stmt_update = mysqli_prepare($db_handle, $sql_update);
                mysqli_stmt_bind_param($stmt_update, "i", $negociation_id);
                if (mysqli_stmt_execute($stmt_update)) {
                    $message_success = "Offre acceptée avec succès !";
                    // Créer une notification pour l'acheteur avec requête préparée et contenu sécurisé
                    $montant_formatte = number_format($nego['offre_acheteur'], 2, ',', ' ');
                    $contenu = sprintf("Votre offre de %s€ pour l'article %s a été acceptée",
        $montant_formatte,
        $nego['article_nom']
    );
    
    $stmt_notif = mysqli_prepare($db_handle, 
        "INSERT INTO notification (user_id, contenu, date_creation, article_id) VALUES (?, ?, NOW(), ?)"
    );
    if ($stmt_notif) {
        mysqli_stmt_bind_param($stmt_notif, "isi", $nego['acheteur_id'], $contenu, $nego['article_id']);
        mysqli_stmt_execute($stmt_notif);
        mysqli_stmt_close($stmt_notif);
    }
                } else {
                    $message_error = "Erreur lors de l'acceptation de l'offre.";
                }
            } else {
                $message_error = "Offre non trouvée ou non valide.";
            }
        }
        
        if ($_POST['action'] == 'refuser_offre') {
            $negociation_id = intval($_POST['negociation_id']);
            
            // Vérifier que la négociation existe et appartient au vendeur
            $sql_check = "SELECT n.*, a.titre as article_nom 
                         FROM negociation n 
                         JOIN article a ON n.article_id = a.id 
                         WHERE n.id = ? AND a.vendeur_id = ? AND n.etat = 'en_cours'";
            $stmt_check = mysqli_prepare($db_handle, $sql_check);
            mysqli_stmt_bind_param($stmt_check, "ii", $negociation_id, $user_id);
            mysqli_stmt_execute($stmt_check);
            $result_check = mysqli_stmt_get_result($stmt_check);
            
            if ($result_check && mysqli_num_rows($result_check) > 0) {
                $nego = mysqli_fetch_assoc($result_check);
                // Refuser l'offre
                $sql_update = "UPDATE negociation SET etat = 'refuse' WHERE id = ?";
                $stmt_update = mysqli_prepare($db_handle, $sql_update);
                mysqli_stmt_bind_param($stmt_update, "i", $negociation_id);
                if (mysqli_stmt_execute($stmt_update)) {
                    $message_success = "Offre refusée.";
                    // Créer une notification pour l'acheteur avec requête préparée et contenu sécurisé
                    $montant_formatte = number_format($nego['offre_acheteur'], 2, ',', ' ');
                    $contenu = sprintf("Votre offre de %s€ pour l'article %s a été refusée",
        $montant_formatte,
        $nego['article_nom']
    );
    
    $stmt_notif = mysqli_prepare($db_handle, 
        "INSERT INTO notification (user_id, contenu, date_creation, article_id) VALUES (?, ?, NOW(), ?)"
    );
    if ($stmt_notif) {
        mysqli_stmt_bind_param($stmt_notif, "isi", $nego['acheteur_id'], $contenu, $nego['article_id']);
        mysqli_stmt_execute($stmt_notif);
        mysqli_stmt_close($stmt_notif);
    }
                } else {
                    $message_error = "Erreur lors du refus de l'offre.";
                }
            } else {
                $message_error = "Offre non trouvée ou non valide.";
            }
        }
        
        if ($_POST['action'] == 'contre_offre_vendeur') {
            $negociation_id = intval($_POST['negociation_id']);
            $contre_offre = floatval($_POST['contre_offre']);
            
            // Vérifier que la négociation existe et appartient au vendeur
            $sql_check = "SELECT n.*, a.titre as article_nom 
                         FROM negociation n 
                         JOIN article a ON n.article_id = a.id 
                         WHERE n.id = ? AND a.vendeur_id = ? AND n.etat = 'en_cours'";
            $stmt_check = mysqli_prepare($db_handle, $sql_check);
            mysqli_stmt_bind_param($stmt_check, "ii", $negociation_id, $user_id);
            mysqli_stmt_execute($stmt_check);
            $result_check = mysqli_stmt_get_result($stmt_check);
            
            if ($result_check && mysqli_num_rows($result_check) > 0) {
                $nego = mysqli_fetch_assoc($result_check);
                // Ajouter la contre-offre
                $sql_update = "UPDATE negociation SET contre_offre_vendeur = ? WHERE id = ?";
                $stmt_update = mysqli_prepare($db_handle, $sql_update);
                mysqli_stmt_bind_param($stmt_update, "di", $contre_offre, $negociation_id);
                if (mysqli_stmt_execute($stmt_update)) {
                    $message_success = "Contre-offre envoyée avec succès !";
                    // Créer une notification pour l'acheteur avec requête préparée et contenu sécurisé
                    $montant_formatte = number_format($contre_offre, 2, ',', ' ');
                    $contenu = sprintf("Une contre-offre de %s€ a été faite pour l'article %s",
        $montant_formatte,
        $nego['article_nom']
    );
    
    $stmt_notif = mysqli_prepare($db_handle, 
        "INSERT INTO notification (user_id, contenu, date_creation, article_id) VALUES (?, ?, NOW(), ?)"
    );
    if ($stmt_notif) {
        mysqli_stmt_bind_param($stmt_notif, "isi", $nego['acheteur_id'], $contenu, $nego['article_id']);
        mysqli_stmt_execute($stmt_notif);
        mysqli_stmt_close($stmt_notif);
    }
                } else {
                    $message_error = "Erreur lors de l'envoi de la contre-offre.";
                }
            } else {
                $message_error = "Offre non trouvée ou non valide.";
            }
        }
    }
}

// Récupérer les négociations où l'utilisateur est acheteur
$sql_mes_offres = "SELECT n.*, a.titre as nom, a.prix_initial as prix_reserve, a.photo, a.vendeur_id,
                         (SELECT COUNT(*) FROM negociation n2 WHERE n2.article_id = n.article_id AND n2.acheteur_id = n.acheteur_id) as nb_offres_article
                    FROM negociation n 
                    JOIN article a ON n.article_id = a.id 
                    WHERE n.acheteur_id = ? 
                    ORDER BY n.date_action DESC";
                    $stmt_mes_offres = mysqli_prepare($db_handle, $sql_mes_offres);
                    mysqli_stmt_bind_param($stmt_mes_offres, "i", $user_id);
                    mysqli_stmt_execute($stmt_mes_offres);
                    $result_mes_offres = mysqli_stmt_get_result($stmt_mes_offres);

// Récupérer les négociations où l'utilisateur est vendeur
$sql_offres_recues = "SELECT n.*, a.titre as nom, a.prix_initial as prix_reserve, a.photo, a.vendeur_id,
                            u.prenom as acheteur_prenom, u.nom as acheteur_nom
                     FROM negociation n 
                     JOIN article a ON n.article_id = a.id 
                     JOIN utilisateur u ON n.acheteur_id = u.id
                     WHERE a.vendeur_id = ? 
                     ORDER BY n.date_action DESC";
$stmt_offres_recues = mysqli_prepare($db_handle, $sql_offres_recues);
mysqli_stmt_bind_param($stmt_offres_recues, "i", $user_id);
mysqli_stmt_execute($stmt_offres_recues);
$result_offres_recues = mysqli_stmt_get_result($stmt_offres_recues);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Offres - Agora Francia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style-site.css" rel="stylesheet">
    <style>
        .offre-card {
            background: linear-gradient(135deg, #f8f6f0 0%, #f1ede3 100%);
            border: 2px solid #d4c4a0;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(185, 151, 91, 0.1);
            transition: all 0.3s ease;
        }
        
        .offre-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 48px rgba(185, 151, 91, 0.15);
        }
        
        .offre-card-vendeur {
            background: linear-gradient(135deg, #e8f5e8 0%, #d4f4d4 100%);
            border: 2px solid #90c690;
        }
        
        .statut-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .statut-en_cours {
            background: linear-gradient(135deg, #ffc107, #ffca2c);
            color: #333;
        }
        
        .statut-accepte {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .statut-refuse {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }
        
        .statut-expire {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }
        
        .prix-offre {
            font-size: 1.4rem;
            font-weight: 700;
            color: #b9975b;
        }
        
        .btn-contre-offre {
            background: linear-gradient(135deg, #b9975b, #d4af37);
            border: none;
            color: white;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-contre-offre:hover {
            background: linear-gradient(135deg, #a0824f, #b8962e);
            transform: translateY(-1px);
            color: white;
        }
        
        .btn-finaliser {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            color: white;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-finaliser:hover {
            background: linear-gradient(135deg, #218838, #1ca085);
            transform: translateY(-1px);
            color: white;
        }
        
        .btn-accepter {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            color: white;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .btn-refuser {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            color: white;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .statistics-container {
            background: linear-gradient(135deg, #181818 0%, #2c2c2c 100%);
            border: 2px solid #b9975b;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            color: #fffdfa;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #b9975b 0%, #d4af37 100%);
            color: #181818;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            font-weight: 600;
            box-shadow: 0 4px 16px rgba(185, 151, 91, 0.3);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .tour-indicator {
            background: #b9975b;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .image-clickable {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .image-clickable:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
        }
        
        .section-header {
            background: linear-gradient(135deg, #b9975b, #d4af37);
            color: white;
            padding: 15px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="text-center mb-4">
                    <i class="bi bi-chat-dots"></i> Mes Offres
                </h1>
                
                <?php if ($message_success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($message_success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($message_error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($message_error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Onglets Bootstrap -->
                <ul class="nav nav-pills nav-justified mb-4" id="offresTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="mes-offres-tab" data-bs-toggle="pill" data-bs-target="#mes-offres" type="button" role="tab">
                            <i class="bi bi-send"></i> Mes offres faites
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="offres-recues-tab" data-bs-toggle="pill" data-bs-target="#offres-recues" type="button" role="tab">
                            <i class="bi bi-inbox"></i> Offres reçues
                        </button>
                    </li>
                </ul>
                
                <!-- Contenu des onglets -->
                <div class="tab-content" id="offresTabContent">
                    <!-- Mes offres faites -->
                    <div class="tab-pane fade show active" id="mes-offres" role="tabpanel">
                        <div class="section-header">
                            <h3><i class="bi bi-send"></i> Offres que j'ai faites</h3>
                            <p class="mb-0">Gérez vos négociations en cours et finalisez vos achats</p>
                        </div>
                        
                        <?php if ($result_mes_offres && mysqli_num_rows($result_mes_offres) > 0): ?>
                            <div class="row">
                                <?php 
                                $offres_groupees = [];
                                while ($nego = mysqli_fetch_assoc($result_mes_offres)) {
                                    $article_id = $nego['article_id'];
                                    if (!isset($offres_groupees[$article_id])) {
                                        $offres_groupees[$article_id] = [
                                            'article' => $nego,
                                            'offres' => []
                                        ];
                                    }
                                    $offres_groupees[$article_id]['offres'][] = $nego;
                                }
                                
                                foreach ($offres_groupees as $article_id => $data):
                                    $article = $data['article'];
                                    $offres = $data['offres'];
                                    $derniere_offre = $offres[0];
                                    $statut_global = $derniere_offre['etat'];
                                    $peut_contre_offrir = $statut_global == 'en_cours' && count($offres) < 5;
                                ?>
                                    <div class="col-lg-6">
                                        <div class="offre-card">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <?php if ($article['photo']): ?>
                                                        <img src="<?= htmlspecialchars($article['photo']) ?>" 
                                                             class="img-fluid rounded image-clickable" 
                                                             alt="<?= htmlspecialchars($article['nom']) ?>"
                                                             style="width: 100%; height: 150px; object-fit: cover;"
                                                             onclick="window.location.href='article.php?id=<?= $article_id ?>'">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded d-flex align-items-center justify-content-center image-clickable" 
                                                             style="height: 150px;"
                                                             onclick="window.location.href='article.php?id=<?= $article_id ?>'">
                                                            <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-8">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h5 class="mb-0"><?= htmlspecialchars($article['nom']) ?></h5>
                                                        <div class="d-flex gap-2">
                                                            <span class="tour-indicator">
                                                                <?= count($offres) ?>/5 offres
                                                            </span>
                                                            <span class="statut-badge statut-<?= $statut_global ?>">
                                                                <?php
                                                                switch ($statut_global) {
                                                                    case 'en_cours': echo 'En attente'; break;
                                                                    case 'accepte': echo 'Acceptée'; break;
                                                                    case 'refuse': echo 'Refusée'; break;
                                                                    case 'expire': echo 'Expirée'; break;
                                                                }
                                                                ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-2">
                                                        <strong>Dernière offre :</strong> 
                                                        <span class="prix-offre"><?= number_format($derniere_offre['offre_acheteur'], 2) ?>€</span>
                                                    </div>
                                                    
                                                    <?php if ($derniere_offre['contre_offre_vendeur']): ?>
                                                        <div class="mb-2 text-info">
                                                            <strong>Contre-offre vendeur :</strong> <?= number_format($derniere_offre['contre_offre_vendeur'], 2) ?>€
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="mb-3">
                                                        <small class="text-muted">
                                                            <i class="bi bi-clock"></i> 
                                                            <?= date('d/m/Y H:i', strtotime($derniere_offre['date_action'])) ?>
                                                        </small>
                                                    </div>
                                                    
                                                    <div class="btn-group-vertical w-100 mt-3" role="group">
                                                        <?php if ($statut_global == 'accepte'): ?>
                                                            <form method="post" style="margin: 0;">
                                                                <input type="hidden" name="action" value="finaliser">
                                                                <input type="hidden" name="negociation_id" value="<?= $derniere_offre['id'] ?>">
                                                                <button type="submit" class="btn btn-finaliser w-100 mb-2">
                                                                    <i class="bi bi-credit-card"></i> Finaliser l'achat
                                                                </button>
                                                            </form>
                                                        <?php elseif ($peut_contre_offrir): ?>
                                                            <button type="button" class="btn btn-contre-offre mb-2" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#modalContreOffre<?= $article_id ?>">
                                                                <i class="bi bi-arrow-up-circle"></i> Faire une contre-offre
                                                            </button>
                                                        <?php elseif ($statut_global == 'en_cours' && count($offres) >= 5): ?>
                                                            <button type="button" class="btn btn-secondary mb-2" disabled>
                                                                <i class="bi bi-x-circle"></i> Limite d'offres atteinte (5/5)
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <a href="article.php?id=<?= $article_id ?>" 
                                                           class="btn btn-outline-secondary">
                                                            <i class="bi bi-eye"></i> Voir l'article
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Modale pour contre-offre -->
                                    <?php if ($peut_contre_offrir): ?>
                                        <div class="modal fade" id="modalContreOffre<?= $article_id ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Contre-offre pour "<?= htmlspecialchars($article['nom']) ?>"</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="post">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="contre_offre">
                                                            <input type="hidden" name="negociation_id" value="<?= $derniere_offre['id'] ?>">
                                                            <input type="hidden" name="article_id" value="<?= $article_id ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Votre dernière offre :</label>
                                                                <div class="h5 text-warning"><?= number_format($derniere_offre['offre_acheteur'], 2) ?>€</div>
                                                            </div>
                                                            
                                                            <?php if ($derniere_offre['contre_offre_vendeur']): ?>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Contre-offre du vendeur :</label>
                                                                    <div class="h5 text-info"><?= number_format($derniere_offre['contre_offre_vendeur'], 2) ?>€</div>
                                                                </div>
                                                            <?php endif; ?>
                                                            
                                                            <div class="mb-3">
                                                                <label for="nouvelle_offre<?= $article_id ?>" class="form-label">Nouvelle offre :</label>
                                                                <input type="number" step="0.01" 
                                                                       min="0.01"
                                                                       class="form-control" 
                                                                       id="nouvelle_offre<?= $article_id ?>" 
                                                                       name="nouvelle_offre" required>
                                                                <div class="form-text">
                                                                    Offre <?= count($offres) + 1 ?>/5 pour cet article
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                            <button type="submit" class="btn btn-contre-offre">
                                                                <i class="bi bi-send"></i> Envoyer l'offre
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-send" style="font-size: 4rem; color: #b9975b;"></i>
                                <h3 class="mt-3">Aucune offre faite</h3>
                                <p class="text-muted">Vous n'avez fait aucune offre pour le moment.</p>
                                <a href="catalogue.php" class="btn btn-contre-offre">
                                    <i class="bi bi-search"></i> Découvrir les articles
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Offres reçues -->
                    <div class="tab-pane fade" id="offres-recues" role="tabpanel">
                        <div class="section-header">
                            <h3><i class="bi bi-inbox"></i> Offres reçues sur mes articles</h3>
                            <p class="mb-0">Gérez les offres reçues et négociez avec les acheteurs</p>
                        </div>
                        
                        <?php if ($result_offres_recues && mysqli_num_rows($result_offres_recues) > 0): ?>
                            <div class="row">
                                <?php 
                                $offres_recues_groupees = [];
                                while ($nego = mysqli_fetch_assoc($result_offres_recues)) {
                                    $key = $nego['article_id'] . '_' . $nego['acheteur_id'];
                                    if (!isset($offres_recues_groupees[$key])) {
                                        $offres_recues_groupees[$key] = [
                                            'article' => $nego,
                                            'offres' => []
                                        ];
                                    }
                                    $offres_recues_groupees[$key]['offres'][] = $nego;
                                }
                                
                                foreach ($offres_recues_groupees as $key => $data):
                                    $article = $data['article'];
                                    $offres = $data['offres'];
                                    $derniere_offre = $offres[0];
                                    $statut_global = $derniere_offre['etat'];
                                    $article_id = $article['article_id'];
                                ?>
                                    <div class="col-lg-6">
                                        <div class="offre-card offre-card-vendeur">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <?php if ($article['photo']): ?>
                                                        <img src="<?= htmlspecialchars($article['photo']) ?>" 
                                                             class="img-fluid rounded image-clickable" 
                                                             alt="<?= htmlspecialchars($article['nom']) ?>"
                                                             style="width: 100%; height: 150px; object-fit: cover;"
                                                             onclick="window.location.href='article.php?id=<?= $article_id ?>'">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded d-flex align-items-center justify-content-center image-clickable" 
                                                             style="height: 150px;"
                                                             onclick="window.location.href='article.php?id=<?= $article_id ?>'">
                                                            <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-8">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h5 class="mb-0"><?= htmlspecialchars($article['nom']) ?></h5>
                                                        <span class="statut-badge statut-<?= $statut_global ?>">
                                                            <?php
                                                            switch ($statut_global) {
                                                                case 'en_cours': echo 'En attente'; break;
                                                                case 'accepte': echo 'Acceptée'; break;
                                                                case 'refuse': echo 'Refusée'; break;
                                                                case 'expire': echo 'Expirée'; break;
                                                            }
                                                            ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="mb-2">
                                                        <strong>Acheteur :</strong> <?= htmlspecialchars($article['acheteur_prenom'] . ' ' . $article['acheteur_nom']) ?>
                                                    </div>
                                                    
                                                    <div class="mb-2">
                                                        <strong>Offre reçue :</strong> 
                                                        <span class="prix-offre"><?= number_format($derniere_offre['offre_acheteur'], 2) ?>€</span>
                                                    </div>
                                                    
                                                    <?php if ($derniere_offre['contre_offre_vendeur']): ?>
                                                        <div class="mb-2 text-info">
                                                            <strong>Ma contre-offre :</strong> <?= number_format($derniere_offre['contre_offre_vendeur'], 2) ?>€
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="mb-3">
                                                        <small class="text-muted">
                                                            <i class="bi bi-clock"></i> 
                                                            <?= date('d/m/Y H:i', strtotime($derniere_offre['date_action'])) ?>
                                                        </small>
                                                    </div>
                                                    
                                                    <?php if ($statut_global == 'en_cours'): ?>
                                                        <div class="btn-group w-100 mb-2" role="group">
                                                            <form method="post" style="display: inline;">
                                                                <input type="hidden" name="action" value="accepter_offre">
                                                                <input type="hidden" name="negociation_id" value="<?= $derniere_offre['id'] ?>">
                                                                <button type="submit" class="btn btn-accepter">
                                                                    <i class="bi bi-check-circle"></i> Accepter
                                                                </button>
                                                            </form>
                                                            
                                                            <button type="button" class="btn btn-contre-offre" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#modalContreOffreVendeur<?= $key ?>">
                                                                <i class="bi bi-arrow-left-right"></i> Contre-offre
                                                            </button>
                                                            
                                                            <form method="post" style="display: inline;">
                                                                <input type="hidden" name="action" value="refuser_offre">
                                                                <input type="hidden" name="negociation_id" value="<?= $derniere_offre['id'] ?>">
                                                                <button type="submit" class="btn btn-refuser" 
                                                                        onclick="return confirm('Êtes-vous sûr de vouloir refuser cette offre ?')">
                                                                    <i class="bi bi-x-circle"></i> Refuser
                                                                </button>
                                                            </form>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <a href="article.php?id=<?= $article_id ?>" 
                                                       class="btn btn-outline-secondary w-100">
                                                        <i class="bi bi-eye"></i> Voir l'article
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Modal pour contre-offre vendeur -->
                                    <?php if ($statut_global == 'en_cours'): ?>
                                        <div class="modal fade" id="modalContreOffreVendeur<?= $key ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Contre-offre pour "<?= htmlspecialchars($article['nom']) ?>"</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="post">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="contre_offre_vendeur">
                                                            <input type="hidden" name="negociation_id" value="<?= $derniere_offre['id'] ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Offre de l'acheteur :</label>
                                                                <div class="h5 text-warning"><?= number_format($derniere_offre['offre_acheteur'], 2) ?>€</div>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Prix initial de l'article :</label>
                                                                <div class="h6 text-muted"><?= number_format($article['prix_reserve'], 2) ?>€</div>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label for="contre_offre<?= $key ?>" class="form-label">Votre contre-offre :</label>
                                                                <input type="number" step="0.01" 
                                                                       min="0.01"
                                                                       class="form-control" 
                                                                       id="contre_offre<?= $key ?>" 
                                                                       name="contre_offre" required
                                                                       value="<?= $derniere_offre['offre_acheteur'] ?>">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                            <button type="submit" class="btn btn-contre-offre">
                                                                <i class="bi bi-send"></i> Envoyer la contre-offre
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox" style="font-size: 4rem; color: #b9975b;"></i>
                                <h3 class="mt-3">Aucune offre reçue</h3>
                                <p class="text-muted">Vous n'avez reçu aucune offre sur vos articles pour le moment.</p>
                                <a href="ajout_article.php" class="btn btn-contre-offre">
                                    <i class="bi bi-plus-circle"></i> Ajouter un article
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
