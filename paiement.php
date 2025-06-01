<?php
session_start();
$database = "agora";
$db_handle = mysqli_connect('localhost', 'root', '');
$db_found = mysqli_select_db($db_handle, $database);

if (!isset($_SESSION['user_id'])) {
    header('Location: compte.php');
    exit();
}

$type_achat = $_GET['type'] ?? 'panier';
$articles = [];
$total = 0;

// Gestion du formulaire de finalisation d'enchère
if (isset($_POST['action']) && $_POST['action'] == 'finaliser_enchere') {
    $enchere_id = intval($_POST['enchere_id']);
    $article_id = intval($_POST['article_id']);
    $prix = floatval($_POST['prix']);
    
    // Vérifier que l'enchère appartient à l'utilisateur connecté
    $sql = "SELECT e.id, e.article_id, a.titre 
            FROM enchere e 
            JOIN article a ON e.article_id = a.id 
            WHERE e.id = ? AND e.acheteur_id = ? AND a.date_fin_enchere < NOW()";
    $stmt = mysqli_prepare($db_handle, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $enchere_id, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($enchere = mysqli_fetch_assoc($result)) {
        // Créer ou récupérer le panier de l'utilisateur
        $sql = "SELECT id FROM Panier WHERE acheteur_id = ?";
        $stmt = mysqli_prepare($db_handle, $sql);
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($panier = mysqli_fetch_assoc($result)) {
            $panier_id = $panier['id'];
        } else {
            // Créer un nouveau panier
            $sql = "INSERT INTO Panier (acheteur_id, date_creation) VALUES (?, NOW())";
            $stmt = mysqli_prepare($db_handle, $sql);
            mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
            mysqli_stmt_execute($stmt);
            $panier_id = mysqli_insert_id($db_handle);
        }        // Vérifier si l'article n'est pas déjà dans le panier
        $sql = "SELECT COUNT(*) as count FROM ArticlePanier WHERE panier_id = ? AND article_id = ?";
        $stmt = mysqli_prepare($db_handle, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $panier_id, $article_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $row = mysqli_fetch_assoc($result);
        if ($row['count'] == 0) {
            // Ajouter l'article au panier avec le mode d'achat enchère
            $sql = "INSERT INTO ArticlePanier (panier_id, article_id, mode_achat) VALUES (?, ?, 'enchere')";
            $stmt = mysqli_prepare($db_handle, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $panier_id, $article_id);
            mysqli_stmt_execute($stmt);
        }
          // Marquer l'enchère comme finalisée (pas supprimée)
        $sql = "UPDATE enchere SET etat = 'finalise' WHERE id = ?";
        $stmt = mysqli_prepare($db_handle, $sql);
        mysqli_stmt_bind_param($stmt, "i", $enchere_id);
        mysqli_stmt_execute($stmt);
        
        $_SESSION['success'] = "L'article \"" . $enchere['titre'] . "\" a été ajouté à votre panier !";
        header('Location: panier.php');
        exit();
    } else {
        $_SESSION['error'] = "Enchère non trouvée ou non valide.";
        header('Location: mes_encheres.php');
        exit();
    }
}

if ($type_achat == 'negociation') {
    // Gestion des négociations
    $negociation_id = intval($_GET['negociation_id']);
    
    $sql = "SELECT n.id as negociation_id, n.offre_acheteur as prix, a.id, a.titre, a.description, a.photo,
            u.nom as vendeur_nom, u.prenom as vendeur_prenom, a.vendeur_id
            FROM negociation n 
            JOIN Article a ON n.article_id = a.id 
            JOIN Utilisateur u ON a.vendeur_id = u.id
            WHERE n.id = ? AND n.acheteur_id = ? AND n.etat = 'accepte'";
    $stmt = mysqli_prepare($db_handle, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $negociation_id, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($nego = mysqli_fetch_assoc($result)) {
        $articles[] = array_merge($nego, [
            'nb_articles' => 1,
            'sous_total' => $nego['prix'],
            'stock' => 1,
            'mode_achat' => 'negociation'
        ]);
        $total = $nego['prix'];
    } else {
        $_SESSION['error'] = "Négociation non trouvée ou non acceptée.";
        header('Location: mes_offres.php');
        exit();
    }
    
} else {
    // Gestion du panier (code existant)
    $sql = "SELECT id FROM Panier WHERE acheteur_id = ?";
    $stmt = mysqli_prepare($db_handle, $sql);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$panier = mysqli_fetch_assoc($result)) {
        header('Location: panier.php');
        exit();
    }    $panier_id = $panier['id'];
    
    // Récupération des articles du panier
    $sql = "SELECT a.id, a.titre, a.description, a.photo, 
            CASE WHEN ap.mode_achat = 'enchere' THEN COALESCE(a.prix_actuel, a.prix_initial)
                 ELSE a.prix_initial 
            END as prix,            ap.mode_achat, 1 as nb_articles, 
            CASE WHEN ap.mode_achat = 'enchere' THEN COALESCE(a.prix_actuel, a.prix_initial)
                 ELSE a.prix_initial 
            END as sous_total,            CASE WHEN ap.mode_achat = 'enchere' THEN 1 
                 WHEN a.statut = 'disponible' THEN 1 
                 ELSE 0 
            END as stock,
            u.nom as vendeur_nom, u.prenom as vendeur_prenom, a.vendeur_id,            CASE WHEN ap.mode_achat = 'enchere' THEN e.id
                 ELSE NULL 
            END as enchere_id
            FROM Article a 
            INNER JOIN ArticlePanier ap ON a.id = ap.article_id
            INNER JOIN Panier p ON ap.panier_id = p.id
            INNER JOIN Utilisateur u ON a.vendeur_id = u.id
            LEFT JOIN enchere e ON a.id = e.article_id AND e.acheteur_id = ? AND e.etat = 'panier'
            WHERE p.id = ?";
    $stmt = mysqli_prepare($db_handle, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $_SESSION['user_id'], $panier_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $articles[] = $row;
        $total += floatval($row['sous_total']);
    }

    if (empty($articles)) {
        header('Location: panier.php');
        exit();
    }
}

// Vérification que tous les articles sont disponibles
$allAvailable = true;
if ($type_achat == 'panier') {
    foreach ($articles as $article) {
        // Les enchères finalisées sont toujours disponibles
        if ($article['mode_achat'] != 'enchere' && !$article['stock']) {
            $allAvailable = false;
            break;
        }
    }
    
    if (!$allAvailable) {
        $_SESSION['error'] = "Certains articles de votre panier ne sont plus disponibles.";
        header('Location: panier.php');
        exit();
    }
}

// Traitement du paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $payment_method = $_POST['payment_method'] ?? '';
    $payment_data = [];
    $validation_errors = [];
    
    switch ($payment_method) {
        case 'carte':
            $card_number = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
            $card_name = trim($_POST['card_name'] ?? '');
            $card_expiry = $_POST['card_expiry'] ?? '';
            $card_cvv = $_POST['card_cvv'] ?? '';
            
            // Validation des données de carte
            if (strlen($card_number) !== 16 || !ctype_digit($card_number)) {
                $validation_errors[] = "Le numéro de carte doit contenir exactement 16 chiffres.";
            }
            
            if (empty($card_name) || strlen($card_name) < 2) {
                $validation_errors[] = "Le nom sur la carte est requis.";
            }
            
            if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $card_expiry)) {
                $validation_errors[] = "La date d'expiration doit être au format MM/AA.";
            } else {
                list($month, $year) = explode('/', $card_expiry);
                $expiry_date = new DateTime('20' . $year . '-' . $month . '-01');
                $current_date = new DateTime();
                if ($expiry_date < $current_date) {
                    $validation_errors[] = "La carte a expiré.";
                }
            }
            
            if (strlen($card_cvv) < 3 || strlen($card_cvv) > 4 || !ctype_digit($card_cvv)) {
                $validation_errors[] = "Le CVV doit contenir 3 ou 4 chiffres.";
            }
            
            $payment_data = [
                'card_number' => $card_number,
                'card_name' => $card_name,
                'card_expiry' => $card_expiry,
                'card_cvv' => $card_cvv
            ];
            break;
            
        case 'virement':
            $payment_data = [
                'bank_account' => $_POST['bank_account'] ?? '',
                'bank_name' => $_POST['bank_name'] ?? ''
            ];
            break;
            
        case 'apple_pay':
            $payment_data = [
                'apple_pay_token' => $_POST['apple_pay_token'] ?? 'generated_token'
            ];
            break;    }
      // Si il y a des erreurs de validation, on les affiche
    if (!empty($validation_errors)) {
        $error_message = implode('<br>', $validation_errors);
    } else {
        // Simulation du traitement du paiement (site fictif)
        $payment_success = true; // En réalité, ici on ferait appel à l'API de paiement
          if ($payment_success) {
            mysqli_autocommit($db_handle, false);
            $transaction_success = true;
            
            try {
                // Traiter chaque article
                foreach ($articles as $article) {
                    // Mapper les méthodes de paiement aux valeurs de la base de données
                    $db_payment_method = $payment_method;
                    switch ($payment_method) {
                        case 'virement':
                            $db_payment_method = 'vierement';// Temporaire pour le site fictif
                            break;
                        case 'apple_pay':
                            $db_payment_method = 'Applepay';//Temporaire pour le site fictif
                            break;
                        case 'carte':
                            $db_payment_method = 'carte';
                            break;
                    }
                    
                    // Créer la transaction pour chaque article
                    $sql = "INSERT INTO transaction (acheteur_id, article_id, montant, mode_paiement, date_paiement) VALUES (?, ?, ?, ?, NOW())";
                    $stmt = mysqli_prepare($db_handle, $sql);
                    mysqli_stmt_bind_param($stmt, "iids", $_SESSION['user_id'], $article['id'], $article['sous_total'], $db_payment_method);
                    if (!mysqli_stmt_execute($stmt)) throw new Exception("Erreur transaction");
                    $transaction_id = mysqli_insert_id($db_handle);
                    
                    // Déterminer le mode d'achat et créer l'achat
                    $mode_achat = $article['mode_achat'] ?? 'achat';
                    $prix_achat = $article['sous_total'];
                    
                    $sql = "INSERT INTO Achat (acheteur_id, vendeur_id, article_id, transaction_id, prix_achat, mode_achat, date_achat) 
                            SELECT ?, a.vendeur_id, a.id, ?, ?, ?, NOW() 
                            FROM Article a WHERE a.id = ?";
                    $stmt = mysqli_prepare($db_handle, $sql);
                    mysqli_stmt_bind_param($stmt, "iidsi", $_SESSION['user_id'], $transaction_id, $prix_achat, $mode_achat, $article['id']);
                    if (!mysqli_stmt_execute($stmt)) throw new Exception("Erreur achat");
                    
                    // Marquer l'article comme vendu
                    $sql = "UPDATE Article SET statut = 'vendu' WHERE id = ?";
                    $stmt = mysqli_prepare($db_handle, $sql);
                    mysqli_stmt_bind_param($stmt, "i", $article['id']);
                    if (!mysqli_stmt_execute($stmt)) throw new Exception("Erreur statut article");
                      // Gestion spécifique selon le type d'achat
                    if ($type_achat == 'enchere' || ($type_achat == 'panier' && $article['mode_achat'] == 'enchere')) {
                        // Marquer l'enchère comme terminée et les autres comme perdues
                        $enchere_id = $article['enchere_id'] ?? null;
                        
                        if ($enchere_id) {
                            // Marquer l'enchère comme finalisée
                            $sql_update_enchere = "UPDATE enchere SET etat = 'finalise' WHERE id = ?";
                            $stmt_update = mysqli_prepare($db_handle, $sql_update_enchere);
                            mysqli_stmt_bind_param($stmt_update, "i", $enchere_id);
                            if (!mysqli_stmt_execute($stmt_update)) throw new Exception("Erreur mise à jour enchère");
                            
                            // Notifier les autres enchérisseurs qu'ils ont perdu
                            $sql = "SELECT DISTINCT e.acheteur_id, a.titre as article_nom 
                                   FROM enchere e 
                                   JOIN Article a ON e.article_id = a.id 
                                   WHERE e.article_id = ? AND e.acheteur_id != ? AND e.id != ?";
                            $stmt = mysqli_prepare($db_handle, $sql);
                            mysqli_stmt_bind_param($stmt, "iii", $article['id'], $_SESSION['user_id'], $enchere_id);
                            mysqli_stmt_execute($stmt);
                            $result_perdants = mysqli_stmt_get_result($stmt);
                            
                            while ($perdant = mysqli_fetch_assoc($result_perdants)) {
                                $contenu = "Vous avez perdu l'enchère pour l'article '" . mysqli_real_escape_string($db_handle, $perdant['article_nom']) . "'";
                                $sql_notif = "INSERT INTO notification (user_id, contenu, date_creation, article_id) VALUES (?, ?, NOW(), ?)";
                                $stmt_notif = mysqli_prepare($db_handle, $sql_notif);
                                mysqli_stmt_bind_param($stmt_notif, "isi", $perdant['acheteur_id'], $contenu, $article['id']);
                                mysqli_stmt_execute($stmt_notif);
                            }
                            
                            // Notifier le vendeur
                            $contenu = "Votre article '" . mysqli_real_escape_string($db_handle, $article['titre']) . "' a été vendu aux enchères pour " . number_format($prix_achat, 2) . "€";
                            $sql_notif = "INSERT INTO notification (user_id, contenu, date_creation, article_id) VALUES (?, ?, NOW(), ?)";
                            $stmt_notif = mysqli_prepare($db_handle, $sql_notif);
                            mysqli_stmt_bind_param($stmt_notif, "isi", $article['vendeur_id'], $contenu, $article['id']);
                            mysqli_stmt_execute($stmt_notif);
                        }
                    } elseif ($type_achat == 'negociation') {
                        // Marquer la négociation comme terminée
                        $negociation_id = $article['negociation_id'];
                        
                        // Marquer cette négociation comme finalisée
                        $sql = "UPDATE negociation SET etat = 'finalise' WHERE id = ?";
                        $stmt = mysqli_prepare($db_handle, $sql);
                        mysqli_stmt_bind_param($stmt, "i", $negociation_id);
                        if (!mysqli_stmt_execute($stmt)) throw new Exception("Erreur mise à jour négociation");
                        
                        // Notifier le vendeur
                        $contenu = "Votre offre pour l'article '" . mysqli_real_escape_string($db_handle, $article['titre']) . "' a été acceptée pour " . number_format($prix_achat, 2) . "€";
                        $sql_notif = "INSERT INTO notification (user_id, contenu, date_creation, article_id) VALUES (?, ?, NOW(), ?)";
                        $stmt_notif = mysqli_prepare($db_handle, $sql_notif);
                        mysqli_stmt_bind_param($stmt_notif, "isi", $article['vendeur_id'], $contenu, $article['id']);
                        mysqli_stmt_execute($stmt_notif);
                    }
                }
                
                // Vider le panier seulement si c'est un achat de panier
                if ($type_achat == 'panier') {
                    $sql = "DELETE FROM ArticlePanier WHERE panier_id = ?";
                    $stmt = mysqli_prepare($db_handle, $sql);
                    mysqli_stmt_bind_param($stmt, "i", $panier_id);
                    if (!mysqli_stmt_execute($stmt)) throw new Exception("Erreur vidage panier");
                }
                
                mysqli_commit($db_handle);
                
            } catch (Exception $e) {
                mysqli_rollback($db_handle);
                $transaction_success = false;
                $error_message = "Une erreur est survenue lors du traitement de votre commande.";
            }
            
            mysqli_autocommit($db_handle, true);
            
            if ($transaction_success) {
                $_SESSION['success'] = "Paiement effectué avec succès ! Vos achats ont été confirmés.";
                header('Location: mes_achats.php');
                exit();
            }
        } else {
            $error_message = "Une erreur est survenue lors du traitement de votre paiement. Veuillez réessayer.";
        }
    }
}

// Récupération des informations utilisateur pour le formulaire
$sql = "SELECT nom, prenom, email, adresse_ligne1, ville, code_postal FROM Utilisateur WHERE id = ?";
$stmt = mysqli_prepare($db_handle, $sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$user_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - Agora Francia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style-site.css">    <style>
        /* Mise en page responsive pour le paiement */
        .payment-container {
            max-width: 1400px;
        }
        
        /* Colonnes côte à côte sur grands écrans */
        @media (min-width: 992px) {
            .row.payment-row {
                display: flex;
                align-items: flex-start;
            }
            
            .payment-form-column {
                flex: 0 0 65%;
                max-width: 65%;
                padding-right: 30px;
            }
            
            .order-summary-column {
                flex: 0 0 35%;
                max-width: 35%;
                position: sticky;
                top: 20px;
            }
        }
          /* Styles pour les méthodes de paiement - design amélioré */
        .payment-methods-grid {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .payment-method {
            flex: 1;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
            position: relative;
            overflow: hidden;
        }
        
        .payment-method:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(185, 151, 91, 0.1), transparent);
            transition: left 0.5s;
        }
        
        .payment-method:hover {
            border-color: #b9975b;
            box-shadow: 0 8px 25px rgba(185, 151, 91, 0.2);
            transform: translateY(-3px) scale(1.01);
        }
        
        .payment-method:hover:before {
            left: 100%;
        }
        
        .payment-method.selected,
        .payment-method.active {
            border-color: #b9975b;
            background: linear-gradient(135deg, #fff9f3 0%, #f8f4ee 100%);
            box-shadow: 0 10px 30px rgba(185, 151, 91, 0.25);
            transform: translateY(-2px) scale(1.02);
        }
          .payment-method .method-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
            display: block;
            transition: transform 0.3s ease;
            cursor: pointer;
            user-select: none;
            pointer-events: none;
        }
        
        .payment-method:hover .method-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        .payment-method.active .method-icon {
            color: #b9975b;
            transform: scale(1.1);
        }
        
        .payment-method h6 {
            margin-bottom: 5px;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .payment-method.active h6 {
            color: #b9975b;
        }
        
        .payment-method small {
            color: #6c757d;
            transition: color 0.3s ease;
        }
        
        .payment-method.active small {
            color: #8b6914;
        }
          /* Formulaires de paiement - Style amélioré */
        .payment-details {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 15px;
            padding: 30px;
            margin-top: 25px;
            border: 2px solid #e9ecef;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .payment-details:hover {
            box-shadow: 0 12px 35px rgba(0,0,0,0.12);
            border-color: #b9975b;
        }
        
        .payment-details h6 {
            color: #b9975b;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #b9975b;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
          /* Amélioration des champs de saisie avec animations */
        .payment-details .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        
        .payment-details .form-control:focus {
            border-color: #b9975b;
            box-shadow: 0 0 0 0.2rem rgba(185, 151, 91, 0.25);
            transform: translateY(-1px);
        }
        
        .payment-details .form-control:hover:not(:focus) {
            border-color: #b9975b;
            transform: translateY(-1px);
        }
        
        .payment-details .form-control.is-valid {
            border-color: #28a745;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='m2.3 6.73.45.37a.72.72 0 0 0 1.07-.31l2.72-4.2a.72.72 0 0 0-.79-1.1L3.8 2.47a.72.72 0 0 0-.39.39l-.69 1.97a.72.72 0 0 0 .39.9'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .payment-details .form-control.is-invalid {
            border-color: #dc3545;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 4.6 1.4 1.4m0-1.4-1.4 1.4'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .payment-details .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            transition: color 0.3s ease;
        }
        
        .payment-details .form-control:focus + .form-label,
        .payment-details .form-control:focus ~ .form-label {
            color: #b9975b;
        }
        
        /* Animation pour les boutons */
        .btn-pulse {
            animation: pulse 0.6s ease-in-out;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
        }
        
        .btn-success:disabled {
            background: #6c757d;
            transform: none;
            box-shadow: none;
        }
        
        .btn-outline-secondary {
            transition: all 0.3s ease;
        }
        
        .btn-outline-secondary:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.2);
            background: linear-gradient(135deg, #181818 0%, #2c2c2c 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .order-summary h4 {
            color: #b9975b;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .article-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid rgba(185, 151, 91, 0.3);
        }
        
        .secure-badge {
            background: linear-gradient(45deg, #b9975b, #d4af6a);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        
        /* Bouton Apple Pay */
        .apple-pay-btn {
            background: #000;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 15px;
            width: 100%;
            font-size: 1.1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
        }
        
        .apple-pay-btn:hover {
            background: #333;
            color: white;
        }
          /* Responsive pour mobile */
        @media (max-width: 991px) {
            .payment-methods-grid {
                flex-direction: column;
            }
            
            .order-summary-column {
                margin-top: 30px;
            }
        }
        
        @media (max-width: 768px) {
            .payment-methods-grid {
                gap: 10px;
            }
            
            .payment-method {
                padding: 15px 10px;
            }
            
            .payment-method .method-icon {
                font-size: 2rem;
            }
        }
        
        /* Animation de traitement du paiement */
        .payment-processing {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .processing-content {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            max-width: 400px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .processing-step {
            display: none;
        }
        
        .processing-step.active {
            display: block;
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #b9975b;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .success-animation {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
            animation: bounce 0.6s ease-in-out;
        }
        
        @keyframes bounce {
            0%, 20%, 60%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            80% { transform: translateY(-5px); }
        }
        
        .processing-step h4 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .processing-step p {
            color: #666;
            margin: 0;
        }
        
        .payment-method label {
            cursor: pointer;
            user-select: none;
            width: 100%;
            height: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .payment-method label * {
            pointer-events: none;
            cursor: inherit;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container mt-4 payment-container">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                    <li class="breadcrumb-item"><a href="panier.php">Panier</a></li>
                    <li class="breadcrumb-item active">Paiement</li>
                </ol>
            </nav>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>    <div class="row payment-row">
        <!-- Formulaire de paiement -->
        <div class="payment-form-column">
            <div class="card border-0 shadow-sm">
                <div class="card-body">                    <h2 class="card-title mb-4">
                        <i class="bi bi-credit-card text-primary"></i> 
                        <?php
                        switch ($type_achat) {
                            case 'enchere':
                                echo 'Finaliser votre enchère gagnante';
                                break;
                            case 'negociation':
                                echo 'Finaliser votre offre acceptée';
                                break;
                            default:
                                echo 'Finaliser votre commande';
                        }
                        ?>
                    </h2>

                    <form method="POST" id="paymentForm">
                        <input type="hidden" name="process_payment" value="1">
                        
                        <!-- Sélection de la méthode de paiement -->
                        <h5 class="mb-3">Choisissez votre méthode de paiement</h5>
                        
                        <div class="payment-methods-grid">
                            <div class="payment-method" data-method="carte">
                                <input type="radio" name="payment_method" value="carte" id="carte" class="d-none">
                                <label for="carte" class="w-100">
                                    <i class="bi bi-credit-card method-icon text-primary"></i>
                                    <h6>Carte bancaire</h6>
                                    <small>Visa, Mastercard, CB</small>
                                </label>
                            </div>
                            
                            <div class="payment-method" data-method="virement">
                                <input type="radio" name="payment_method" value="virement" id="virement" class="d-none">
                                <label for="virement" class="w-100">
                                    <i class="bi bi-bank method-icon text-success"></i>
                                    <h6>Virement bancaire</h6>
                                    <small>Paiement sécurisé</small>
                                </label>
                            </div>
                            
                            <div class="payment-method" data-method="apple_pay">
                                <input type="radio" name="payment_method" value="apple_pay" id="apple_pay" class="d-none">
                                <label for="apple_pay" class="w-100">
                                    <i class="bi bi-apple method-icon text-dark"></i>
                                    <h6>Apple Pay</h6>
                                    <small>Paiement rapide</small>
                                </label>
                            </div>
                        </div>

                        <!-- Détails de paiement -->
                        <div id="payment-details" class="payment-details" style="display: none;">                            <!-- Formulaire carte bancaire -->
                            <div id="carte-details" style="display: none;">
                                <h6><i class="bi bi-credit-card"></i> Informations de carte bancaire</h6>
                                
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label for="card_number" class="form-label">
                                            <i class="bi bi-credit-card-2-front"></i> Numéro de carte
                                        </label>
                                        <input type="text" class="form-control" id="card_number" name="card_number" 
                                               placeholder="1234 5678 9012 3456" maxlength="19">
                                        <div class="invalid-feedback">Veuillez saisir un numéro de carte valide (16 chiffres)</div>
                                    </div>
                                    
                                    <div class="col-md-8 mb-3">
                                        <label for="card_name" class="form-label">
                                            <i class="bi bi-person"></i> Nom du titulaire
                                        </label>
                                        <input type="text" class="form-control" id="card_name" name="card_name" 
                                               value="<?= htmlspecialchars($user_info['prenom'] . ' ' . $user_info['nom']) ?>">
                                        <div class="invalid-feedback">Le nom doit contenir au moins 2 caractères</div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="card_cvv" class="form-label">
                                            <i class="bi bi-shield-lock"></i> CVV
                                        </label>
                                        <input type="text" class="form-control" id="card_cvv" name="card_cvv" 
                                               placeholder="123" maxlength="4">
                                        <div class="invalid-feedback">CVV requis (3-4 chiffres)</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="card_expiry" class="form-label">
                                            <i class="bi bi-calendar"></i> Date d'expiration
                                        </label>
                                        <input type="text" class="form-control" id="card_expiry" name="card_expiry" 
                                               placeholder="MM/AA" maxlength="5">
                                        <div class="invalid-feedback">Format: MM/AA</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3 d-flex align-items-end">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="save_card">
                                            <label class="form-check-label" for="save_card">
                                                <small>Sauvegarder pour les prochains achats</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-shield-check"></i>
                                    <strong>Sécurisé :</strong> Vos données bancaires sont cryptées et protégées selon les standards PCI DSS.
                                </div>
                            </div>

                            <!-- Formulaire virement -->
                            <div id="virement-details" style="display: none;">
                                <h6><i class="bi bi-bank"></i> Informations de virement</h6>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>Instructions :</strong> Vous recevrez les détails de virement par email après validation de la commande.
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="bank_name" class="form-label">Nom de votre banque</label>
                                        <input type="text" class="form-control" id="bank_name" name="bank_name" 
                                               placeholder="Ex: Crédit Agricole">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="bank_account" class="form-label">IBAN (optionnel)</label>
                                        <input type="text" class="form-control" id="bank_account" name="bank_account" 
                                               placeholder="FR76 XXXX XXXX XXXX XXXX XXXX XXX">
                                    </div>
                                </div>
                            </div>

                            <!-- Formulaire Apple Pay -->
                            <div id="apple_pay-details" style="display: none;">
                                <h6><i class="bi bi-apple"></i> Apple Pay</h6>
                                <p class="text-muted">Utilisez Touch ID ou Face ID pour payer en toute sécurité.</p>
                                <button type="button" class="apple-pay-btn" onclick="processApplePay()">
                                    <i class="bi bi-apple"></i>
                                    Payer avec Apple Pay
                                </button>
                                <input type="hidden" name="apple_pay_token" id="apple_pay_token">
                            </div>
                        </div>                        <!-- Adresse de facturation -->
                        <div class="mt-4">
                            <h5>Adresse de facturation</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="billing_name" class="form-label">Nom complet</label>
                                    <input type="text" class="form-control" id="billing_name" 
                                           value="<?= htmlspecialchars($user_info['prenom'] . ' ' . $user_info['nom']) ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="billing_email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="billing_email" 
                                           value="<?= htmlspecialchars($user_info['email']) ?>" readonly>
                                </div>
                                <div class="col-12 mb-3">
                                    <label for="billing_address" class="form-label">Adresse</label>
                                    <input type="text" class="form-control" id="billing_address" name="billing_address"
                                           value="<?= htmlspecialchars($user_info['adresse_ligne1'] ?? '') ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="billing_city" class="form-label">Ville</label>
                                    <input type="text" class="form-control" id="billing_city" 
                                           value="<?= htmlspecialchars($user_info['ville'] ?? '') ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="billing_postal" class="form-label">Code postal</label>
                                    <input type="text" class="form-control" id="billing_postal" 
                                           value="<?= htmlspecialchars($user_info['code_postal'] ?? '') ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <a href="panier.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Retour au panier
                            </a>
                            <button type="submit" class="btn btn-success btn-lg" id="submitPayment" disabled>
                                <i class="bi bi-lock-fill"></i> Finaliser le paiement
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Résumé de commande -->
        <div class="order-summary-column">
            <div class="order-summary">
                <h4 class="mb-4">
                    <i class="bi bi-receipt"></i> Résumé de votre commande
                </h4>
                
                <div class="mb-3">
                    <span class="secure-badge">
                        <i class="bi bi-shield-check"></i>
                        Paiement sécurisé
                    </span>
                </div>

                <?php foreach ($articles as $article): ?>
                    <div class="article-item text-dark">
                        <div class="d-flex align-items-center">
                            <?php if ($article['photo']): ?>
                                <img src="<?= htmlspecialchars($article['photo']) ?>" 
                                     class="img-thumbnail me-3" style="width: 60px; height: 60px; object-fit: cover;">
                            <?php endif; ?>
                            <div class="flex-grow-1">                                <h6 class="mb-1"><?= htmlspecialchars($article['titre']) ?></h6>
                                <small class="text-muted">
                                    Vendeur: <?= htmlspecialchars($article['vendeur_prenom'] . ' ' . $article['vendeur_nom']) ?>
                                </small>
                                <div class="fw-bold"><?= number_format($article['prix'], 2, ',', ' ') ?> €</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <hr class="my-4" style="border-color: rgba(255,255,255,0.3);">
                
                <div class="d-flex justify-content-between mb-2">
                    <span>Sous-total:</span>
                    <span><?= number_format($total, 2, ',', ' ') ?> €</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Frais de service:</span>
                    <span>Gratuit</span>
                </div>
                <hr style="border-color: rgba(255,255,255,0.3);">
                <div class="d-flex justify-content-between">
                    <h5>Total:</h5>
                    <h5><?= number_format($total, 2, ',', ' ') ?> €</h5>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Overlay de traitement du paiement -->
<div id="paymentProcessing" class="payment-processing">
    <div class="processing-content">
        <div id="processingStep1" class="processing-step">
            <div class="spinner"></div>
            <h4>Traitement de votre paiement...</h4>
            <p>Vérification des informations bancaires</p>
        </div>
        
        <div id="processingStep2" class="processing-step" style="display: none;">
            <div class="spinner"></div>
            <h4>Validation en cours...</h4>
            <p>Autorisation de la transaction</p>
        </div>
        
        <div id="processingStep3" class="processing-step" style="display: none;">
            <div class="success-animation">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <h4>Paiement confirmé !</h4>
            <p>Votre commande a été validée avec succès</p>
        </div>
        
        <div id="processingError" class="processing-step" style="display: none;">
            <div style="color: #dc3545; font-size: 4rem;">
                <i class="bi bi-x-circle-fill"></i>
            </div>
            <h4>Erreur de paiement</h4>
            <p id="errorMessage">Une erreur est survenue</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethods = document.querySelectorAll('.payment-method');
    const paymentDetails = document.getElementById('payment-details');
    const submitButton = document.getElementById('submitPayment');
    const paymentForm = document.getElementById('paymentForm');
    
    // Variables de validation
    let validationState = {
        cardNumber: false,
        cardName: false,
        cardExpiry: false,
        cardCvv: false
    };
    
// Gestionnaire pour les méthodes de paiement avec effets visuels améliorés (Bootstrap)
paymentMethods.forEach(method => {
    method.addEventListener('click', function() {
        const methodType = this.dataset.method;
        const radio = this.querySelector('input[type="radio"]');
        
        // Réinitialise toutes les méthodes de paiement avec effet de transition
        paymentMethods.forEach(m => {
            m.classList.remove('active', 'selected');
            // Applique un effet visuel de remise à l’échelle
            m.style.transform = 'scale(1)';
            m.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        });
        
        // Masque toutes les sections de détails de paiement avec effet de fondu
        const allDetails = document.querySelectorAll('#payment-details > div');
        allDetails.forEach(detail => {
            detail.style.opacity = '0';
            detail.style.transform = 'translateY(-10px)';
            setTimeout(() => detail.style.display = 'none', 200);
        });
        
        // Active la méthode sélectionnée avec mise en valeur visuelle
        this.classList.add('active', 'selected');
        this.style.transform = 'scale(1.02)';
        radio.checked = true;
        
        // Animation visuelle sur le bouton sélectionné (effet tactile)
        this.style.background = 'linear-gradient(135deg, #fff9f3 0%, #f0e6d2 100%)';
        setTimeout(() => {
            this.style.background = 'linear-gradient(135deg, #fff9f3 0%, #f8f4ee 100%)';
        }, 150);
        
        // Affiche le conteneur des détails de paiement avec effet de fondu progressif
        setTimeout(() => {
            paymentDetails.style.display = 'block';
            paymentDetails.style.opacity = '0';
            paymentDetails.style.transform = 'translateY(20px)';
            
            requestAnimationFrame(() => {
                paymentDetails.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                paymentDetails.style.opacity = '1';
                paymentDetails.style.transform = 'translateY(0)';
            });
        }, 250);
        
        // Affiche les détails spécifiques à la méthode choisie avec effet latéral
        setTimeout(() => {
            const methodDetails = document.getElementById(methodType + '-details');
            if (methodDetails) {
                methodDetails.style.display = 'block';
                methodDetails.style.opacity = '0';
                methodDetails.style.transform = 'translateX(-20px)';
                
                requestAnimationFrame(() => {
                    methodDetails.style.transition = 'all 0.3s ease-out';
                    methodDetails.style.opacity = '1';
                    methodDetails.style.transform = 'translateX(0)';
                });
            }
        }, 400);
        
        // Active le bouton de soumission avec animation si la méthode ne demande pas de carte
        if (methodType !== 'carte') {
            setTimeout(() => {
                submitButton.disabled = false;
                submitButton.classList.add('btn-pulse');
                setTimeout(() => submitButton.classList.remove('btn-pulse'), 600);
            }, 500);
        } else {
            checkCardValidation();
        }
    });

    // Effets au survol pour les méthodes non sélectionnées
    method.addEventListener('mouseenter', function() {
        if (!this.classList.contains('active')) {
            this.style.transform = 'translateY(-3px) scale(1.01)';
            this.style.boxShadow = '0 8px 25px rgba(185, 151, 91, 0.2)';
        }
    });

    method.addEventListener('mouseleave', function() {
        if (!this.classList.contains('active')) {
            this.style.transform = 'translateY(0) scale(1)';
            this.style.boxShadow = '0 4px 15px rgba(185, 151, 91, 0.15)';
        }
    });
});
    
    // Validation du champ numéro de carte avec effet visuel intégré
    const cardNumber = document.getElementById('card_number');
    if (cardNumber) {
        cardNumber.addEventListener('input', function() {
            // Formate le numéro par groupe de 4 chiffres
            let value = this.value.replace(/\D/g, '');
            value = value.replace(/(\\d{4})(?=\\d)/g, '$1 ');
            this.value = value;

            // Vérifie si le numéro est complet (16 chiffres)
            const cleanNumber = this.value.replace(/\\s/g, '');
            if (cleanNumber.length === 16) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
                this.style.borderColor = '#28a745';
                validationState.cardNumber = true;
                showFieldSuccess(this); // effet visuel sur champ valide
            } else {
                this.classList.remove('is-valid');
                if (cleanNumber.length > 0) {
                    this.classList.add('is-invalid');
                    this.style.borderColor = '#dc3545';
                }
                validationState.cardNumber = false;
            }
            checkCardValidation(); // met à jour l'état global du formulaire
        });
}
    
    // Validation du nom sur la carte
    const cardName = document.getElementById('card_name');
    if (cardName) {
        cardName.addEventListener('input', function() {
            if (this.value.trim().length >= 2) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
                this.style.borderColor = '#28a745';
                validationState.cardName = true;
                showFieldSuccess(this);
            } else {
                this.classList.remove('is-valid');
                if (this.value.length > 0) {
                    this.classList.add('is-invalid');
                    this.style.borderColor = '#dc3545';
                }
                validationState.cardName = false;
            }
            checkCardValidation();
        });
    }
    
    // Validation de la date d'expiration
    const cardExpiry = document.getElementById('card_expiry');
    if (cardExpiry) {
        cardExpiry.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0,2) + '/' + value.substring(2,4);
            }
            this.value = value;
            
            // Validation de la date
            if (value.length === 5) {
                const [month, year] = value.split('/');
                const currentDate = new Date();
                const currentYear = currentDate.getFullYear() % 100;
                const currentMonth = currentDate.getMonth() + 1;
                
                if (month >= 1 && month <= 12 && year >= currentYear) {
                    if (year > currentYear || (year == currentYear && month >= currentMonth)) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                        this.style.borderColor = '#28a745';
                        validationState.cardExpiry = true;
                        showFieldSuccess(this);
                    } else {
                        this.classList.remove('is-valid');
                        this.classList.add('is-invalid');
                        this.style.borderColor = '#dc3545';
                        validationState.cardExpiry = false;
                    }
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                    this.style.borderColor = '#dc3545';
                    validationState.cardExpiry = false;
                }
            } else {
                this.classList.remove('is-valid');
                if (value.length > 0) {
                    this.classList.add('is-invalid');
                    this.style.borderColor = '#dc3545';
                }
                validationState.cardExpiry = false;
            }
            checkCardValidation();
        });
    }
    
    // Validation du CVV
    const cardCvv = document.getElementById('card_cvv');
    if (cardCvv) {
        cardCvv.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
            
            if (this.value.length >= 3 && this.value.length <= 4) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
                this.style.borderColor = '#28a745';
                validationState.cardCvv = true;
                showFieldSuccess(this);
            } else {
                this.classList.remove('is-valid');
                if (this.value.length > 0) {
                    this.classList.add('is-invalid');
                    this.style.borderColor = '#dc3545';
                }
                validationState.cardCvv = false;
            }
            checkCardValidation();
        });
    }
    
    // Fonction d'animation de succès pour les champs
    function showFieldSuccess(field) {
        field.style.transform = 'scale(1.02)';
        setTimeout(() => {
            field.style.transform = 'scale(1)';
        }, 150);
    }
    
    // Fonction de vérification de validation globale pour carte
    function checkCardValidation() {
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
        if (selectedMethod && selectedMethod.value === 'carte') {
            const allValid = Object.values(validationState).every(state => state === true);
            
            if (allValid !== !submitButton.disabled) {
                submitButton.disabled = !allValid;
                
                if (allValid) {
                    submitButton.classList.add('btn-pulse');
                    setTimeout(() => submitButton.classList.remove('btn-pulse'), 600);
                }
            }
        }
    }
    
    // Gestion de la soumission du formulaire avec animation
    paymentForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
        if (!selectedMethod) {
            showToast('Veuillez sélectionner une méthode de paiement.', 'warning');
            return;
        }
        
        // Pour carte bancaire, vérifier validation
        if (selectedMethod.value === 'carte') {
            const allValid = Object.values(validationState).every(state => state === true);
            if (!allValid) {
                showToast('Veuillez corriger les erreurs dans les informations de carte bancaire.', 'danger');
                return;
            }
        }
        
        // Lancer l'animation de traitement
        showPaymentProcessing();
        
        // Simuler le traitement du paiement
        setTimeout(() => showProcessingStep2(), 2000);
        setTimeout(() => showProcessingStep3(), 4000);
        setTimeout(() => this.submit(), 6000);
    });
    
    // Fonction toast pour les messages
    function showToast(message, type = 'info') {
        const toastHtml = `
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', toastHtml);
        const toast = new bootstrap.Toast(document.querySelector('.toast:last-child'));
        toast.show();
        
        setTimeout(() => {
            const toastElement = document.querySelector('.toast:last-child');
            if (toastElement) toastElement.remove();
        }, 5000);
    }
});

function showPaymentProcessing() {
    document.getElementById('paymentProcessing').style.display = 'flex';
}

function showProcessingStep2() {
    document.getElementById('processingStep1').style.display = 'none';
    document.getElementById('processingStep2').style.display = 'block';
}

function showProcessingStep3() {
    document.getElementById('processingStep2').style.display = 'none';
    document.getElementById('processingStep3').style.display = 'block';
}

function showProcessingError(message) {
    document.querySelectorAll('.processing-step').forEach(step => {
        step.style.display = 'none';
    });
    document.getElementById('errorMessage').textContent = message;
    document.getElementById('processingError').style.display = 'block';
    
    setTimeout(() => {
        document.getElementById('paymentProcessing').style.display = 'none';
    }, 3000);
}

function processApplePay() {
    // Simulation Apple Pay
    const token = 'apple_pay_token_' + Date.now();
    document.getElementById('apple_pay_token').value = token;
    
    setTimeout(() => {
        alert('Apple Pay configuré avec succès !');
        document.getElementById('submitPayment').disabled = false;
    }, 1000);
}
</script>

<?php include 'footer.php'; ?>
</body>
</html>
