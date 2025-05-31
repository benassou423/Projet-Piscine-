<?php
session_start(); // Démarrage de la session pour accéder aux variables de session

// Sécurité : accès réservé à l'admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header('Location: compte.php'); // Redirection si l'utilisateur n'est pas admin
    exit(); // Arrêt de l'exécution du script
}

$database = "agora"; // Nom de la base de données
$db_handle = mysqli_connect('localhost', 'root', ''); // Connexion au serveur MySQL
$db_found = mysqli_select_db($db_handle, $database); // Sélection de la base de données

$message_ajout = ""; // Variable pour stocker les messages de confirmation ou d’erreur

// Traitement promotion acheteur en vendeur
if (isset($_POST['promouvoir_acheteur']) && !empty($_POST['promouvoir_acheteur'])) {
    $id_acheteur = intval($_POST['promouvoir_acheteur']); // Sécurisation de l'ID de l'acheteur

    $sql_update = "UPDATE Utilisateur SET role = 'vendeur' WHERE id = ?"; // Prépare la requête pour changer le rôle
    $stmt = mysqli_prepare($db_handle, $sql_update); // Préparation de la requête
    mysqli_stmt_bind_param($stmt, "i", $id_acheteur); // Liaison de l'ID acheteur en paramètre sécurisé

    if (mysqli_stmt_execute($stmt)) {
        $message_ajout = "L'acheteur a été promu en vendeur avec succès."; // Message de succès
        header('Location: admin_gestion_acheteurs.php'); // Rafraîchit la page
        exit();
    } else {
        $message_ajout = "Erreur lors de la promotion : " . mysqli_error($db_handle); // Message d’erreur si requête échoue
    }
}

// Traitement suppression acheteur
if (isset($_POST['supprimer_acheteur']) && !empty($_POST['supprimer_acheteur'])) {
    $id_acheteur = intval($_POST['supprimer_acheteur']); // Sécurise l'ID acheteur
    mysqli_begin_transaction($db_handle); // Démarre une transaction MySQL

    try {
        //1. Suppression du panier
      // Préparation de la requête SQL pour supprimer tous les articles du panier appartenant à l'acheteur
        $sql_delete_panier = "DELETE FROM Panier WHERE acheteur_id = ?";

        // Prépare une requête SQL sécurisée avec un paramètre (évite les injections SQL)
        $stmt = mysqli_prepare($db_handle, $sql_delete_panier);

        // Lie la variable $id_acheteur (entier) au paramètre de la requête préparée
        mysqli_stmt_bind_param($stmt, "i", $id_acheteur);

        // Exécute la requête : supprime les lignes du panier correspondant à cet acheteur
        mysqli_stmt_execute($stmt);

        // Ferme proprement la requête préparée pour libérer les ressources
        mysqli_stmt_close($stmt);


        // 2. Suppression des enchères
        $sql_delete_encheres = "DELETE FROM Enchere WHERE acheteur_id = ?";
        $stmt = mysqli_prepare($db_handle, $sql_delete_encheres);
        mysqli_stmt_bind_param($stmt, "i", $id_acheteur);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // 3. Suppression des transactions
        $sql_delete_transactions = "DELETE FROM Transaction WHERE acheteur_id = ?";
        $stmt = mysqli_prepare($db_handle, $sql_delete_transactions);
        mysqli_stmt_bind_param($stmt, "i", $id_acheteur);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // 4. Suppression des alertes
        $sql_delete_alertes = "DELETE FROM Alerte WHERE user_id = ?";
        $stmt = mysqli_prepare($db_handle, $sql_delete_alertes);
        mysqli_stmt_bind_param($stmt, "i", $id_acheteur);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // 5. Suppression des notifications
        $sql_delete_notifs = "DELETE FROM Notification WHERE user_id = ?";
        $stmt = mysqli_prepare($db_handle, $sql_delete_notifs);
        mysqli_stmt_bind_param($stmt, "i", $id_acheteur);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // 6. Suppression des négociations
        $sql_delete_nego = "DELETE FROM Negociation WHERE acheteur_id = ?";
        $stmt = mysqli_prepare($db_handle, $sql_delete_nego);
        mysqli_stmt_bind_param($stmt, "i", $id_acheteur);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // 7. Suppression de l'utilisateur
        $sql_delete_user = "DELETE FROM Utilisateur WHERE id = ? AND role = 'acheteur'";
        $stmt = mysqli_prepare($db_handle, $sql_delete_user);
        mysqli_stmt_bind_param($stmt, "i", $id_acheteur);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($db_handle); // Valide toutes les suppressions si tout s’est bien passé
        $message_ajout = "L'acheteur et toutes ses données associées ont été supprimés avec succès."; // Message de succès
        header('Location: admin_gestion_acheteurs.php'); // Rafraîchit la page
        exit();
    } catch (Exception $e) {
        mysqli_rollback($db_handle); // Annule toutes les opérations en cas d'erreur
        $message_ajout = "Erreur lors de la suppression de l'acheteur : " . $e->getMessage(); // Message d’erreur
    }
}
?>
<!DOCTYPE html> 
<head>
    <meta charset="UTF-8">
    <title>Gestion des acheteurs - Admin | Agora Francia</title> <!-- Titre affiché dans l'onglet du navigateur -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"> <!-- Inclusion de Bootstrap via CDN -->
    <link rel="stylesheet" href="css/style-site.css"> <!-- Inclusion du CSS personnalisé du site -->
</head>
<body>
<?php include 'header.php'; ?> <!-- Inclusion de l'en-tête (menu/navigation) du site -->

<div class="container"> <!-- Conteneur Bootstrap centré avec marges -->
    <h2 class="mb-4 text-primary text-center">Gestion des acheteurs</h2> <!-- Titre principal centré en bleu -->

    <?php if ($message_ajout): ?> <!-- Affiche un message si une action (promotion/suppression) a été effectuée -->
        <div class="alert alert-info"><?= htmlspecialchars($message_ajout) ?></div> <!-- Encadre le message avec une alerte bleue -->
    <?php endif; ?>

    <!-- Liste des acheteurs -->
    <div class="card"> <!-- Carte Bootstrap contenant le tableau -->
        <div class="card-header">Liste des acheteurs</div> <!-- En-tête de la carte -->
        <div class="card-body"> <!-- Corps de la carte -->
            <?php
            // Requête SQL pour récupérer tous les utilisateurs ayant le rôle "acheteur"
            $sql = "SELECT * FROM Utilisateur WHERE role='acheteur' ORDER BY nom, prenom";
            $result = mysqli_query($db_handle, $sql); // Exécution de la requête
            if ($result && mysqli_num_rows($result) > 0): // Si des acheteurs sont trouvés
            ?>
            <div class="table-responsive"> <!-- Permet le défilement horizontal du tableau sur petits écrans -->
                <table class="table"> <!-- Tableau Bootstrap -->
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Email</th>
                            <th>Actions</th> <!-- Colonne pour les boutons d'action -->
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($acheteur = mysqli_fetch_assoc($result)): ?> <!-- Boucle sur chaque acheteur -->
                        <tr>
                            <td>
                                <?php if (isset($acheteur['photo']) && $acheteur['photo']): ?> <!-- Si une photo est présente -->
                                    <img src="<?= htmlspecialchars($acheteur['photo']) ?>" alt="photo" style="width:50px;height:50px;object-fit:cover;"> <!-- Affiche la photo -->
                                <?php else: ?> <!-- Si aucune photo n'est fournie -->
                                    <div class="bg-secondary" style="width:50px;height:50px;"></div> <!-- Affiche un carré gris par défaut -->
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($acheteur['nom']) ?></td> <!-- Nom sécurisé (HTML échappé) -->
                            <td><?= htmlspecialchars($acheteur['prenom']) ?></td> <!-- Prénom -->
                            <td><?= htmlspecialchars($acheteur['email']) ?></td> <!-- Email -->

                            <td>
                                <!-- Formulaire de promotion en vendeur -->
                                <form method="post" class="d-inline me-1" onsubmit="return confirm('Voulez-vous promouvoir cet acheteur en vendeur ?');">
                                    <input type="hidden" name="promouvoir_acheteur" value="<?= (int)$acheteur['id'] ?>"> <!-- ID de l'acheteur -->
                                    <button type="submit" class="btn btn-success btn-sm">Promouvoir en vendeur</button> <!-- Bouton promotion -->
                                </form>

                                <!-- Formulaire de suppression de l'acheteur -->
                                <form method="post" class="d-inline" onsubmit="return confirm('ATTENTION : Êtes-vous sûr de vouloir supprimer définitivement cet acheteur ? Cette action supprimera toutes ses transactions et données associées.');">
                                    <input type="hidden" name="supprimer_acheteur" value="<?= (int)$acheteur['id'] ?>"> <!-- ID de l'acheteur -->
                                    <button type="submit" class="btn btn-danger btn-sm">Supprimer</button> <!-- Bouton suppression -->
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?> <!-- Fin de la boucle while -->
                    </tbody>
                </table>
            </div>
            <?php else: ?> <!-- Si aucun acheteur trouvé -->
                <p class="text-muted text-center">Aucun acheteur enregistré.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bouton de retour vers la page admin principale -->
    <a href="admin.php" class="btn btn-secondary mt-3">Retour à l'espace admin</a>
</div>

<?php include 'footer.php'; ?> <!-- Inclusion du pied de page -->
</body>
</html>

