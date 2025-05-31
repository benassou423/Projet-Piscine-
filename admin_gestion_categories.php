<?php
session_start(); // Démarre la session pour récupérer les infos de l'utilisateur connecté

// Sécurité : si l'utilisateur n'est pas admin, on le redirige vers la page de connexion
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header('Location: compte.php');
    exit();
}

include_once "db.php"; // Inclusion du fichier de connexion à la base de données

$message = ""; // Initialisation d'une variable de message pour l'utilisateur

// ---------- AJOUT D'UNE CATÉGORIE ----------
if (isset($_POST['ajouter_categorie'])) { // Si le formulaire "ajouter catégorie" a été soumis
    $nom = mysqli_real_escape_string($db_handle, trim($_POST['nom'])); // On sécurise le nom saisi (contre injections SQL)

    // Vérifie si une catégorie du même nom existe déjà dans la table
    $check_sql = "SELECT COUNT(*) as count FROM categorie WHERE nom = '$nom'";
    $check_result = mysqli_query($db_handle, $check_sql);
    $row = mysqli_fetch_assoc($check_result); // Récupère le résultat sous forme de tableau associatif

    if ($row['count'] > 0) {
        $message = "Erreur : Cette catégorie existe déjà !"; // Message si doublon
    } else {
        // Si elle n'existe pas, on insère la nouvelle catégorie
        $sql = "INSERT INTO categorie (nom) VALUES ('$nom')";
        if (mysqli_query($db_handle, $sql)) {
            $message = "Catégorie ajoutée avec succès !"; // Succès
        } else {
            $message = "Erreur lors de l'ajout : " . mysqli_error($db_handle); // Affiche l’erreur SQL
        }
    }
}

// ---------- SUPPRESSION SIMPLE D'UNE CATÉGORIE ----------
if (isset($_POST['supprimer_categorie'])) { // Si le bouton de suppression a été cliqué
    $id = intval($_POST['supprimer_categorie']); // On sécurise l'identifiant

    // Vérifie si des articles sont liés à cette catégorie
    $check_articles_sql = "SELECT COUNT(*) as count FROM article WHERE categorie_id = $id";
    $check_result = mysqli_query($db_handle, $check_articles_sql);
    $articles_count = mysqli_fetch_assoc($check_result)['count']; // Nombre d’articles dans cette catégorie

    if ($articles_count > 0) {
        // Si des articles existent, on les affiche (les 5 premiers)
        $articles_sql = "SELECT titre FROM article WHERE categorie_id = $id LIMIT 5";
        $articles_result = mysqli_query($db_handle, $articles_sql);
        $articles_list = [];
        while ($article = mysqli_fetch_assoc($articles_result)) {
            $articles_list[] = $article['titre']; // Ajoute le titre dans un tableau
        }

        // Construit la phrase avec les titres
        $articles_display = implode(', ', $articles_list);
        if ($articles_count > 5) {
            $articles_display .= " (et " . ($articles_count - 5) . " autre(s))";
        }

        // Message d'erreur avec explication
        $message = "Erreur : Impossible de supprimer cette catégorie car elle contient $articles_count article(s) : $articles_display. 
                   <br><strong>Pour supprimer cette catégorie, vous devez d'abord supprimer ou réassigner tous les articles qui y sont associés.</strong>";
    } else {
        // Sinon, on peut la supprimer normalement
        $sql = "DELETE FROM categorie WHERE id = $id";
        if (mysqli_query($db_handle, $sql)) {
            $message = "Catégorie supprimée avec succès !"; // Succès
        } else {
            $message = "Erreur lors de la suppression : " . mysqli_error($db_handle); // Affiche l’erreur SQL
        }
    }
}

// ---------- SUPPRESSION EN CASCADE (CATÉGORIE + ARTICLES ASSOCIÉS + DÉPENDANCES) ----------
if (isset($_POST['supprimer_categorie_cascade'])) { // Si suppression forcée demandée
    $id = intval($_POST['supprimer_categorie_cascade']); // On sécurise l'ID

    // On désactive l'autocommit pour démarrer une transaction
    mysqli_autocommit($db_handle, FALSE);

    try {
        // Étape 1 : Récupère les IDs des articles de cette catégorie
        $articles_sql = "SELECT id FROM article WHERE categorie_id = $id";
        $articles_result = mysqli_query($db_handle, $articles_sql);
        $article_ids = [];
        while ($article = mysqli_fetch_assoc($articles_result)) {
            $article_ids[] = $article['id'];
        }

        if (!empty($article_ids)) {
            $article_ids_string = implode(',', $article_ids); // Transforme le tableau en string pour la requête SQL IN

            // Étape 2 : Supprime toutes les transactions liées à ces articles
            $delete_transactions_sql = "DELETE FROM transaction WHERE article_id IN ($article_ids_string)";
            if (!mysqli_query($db_handle, $delete_transactions_sql)) {
                throw new Exception("Erreur lors de la suppression des transactions : " . mysqli_error($db_handle));
            }

            // Étape 3 : Supprime toutes les enchères liées à ces articles
            $delete_encheres_sql = "DELETE FROM enchere WHERE article_id IN ($article_ids_string)";
            if (!mysqli_query($db_handle, $delete_encheres_sql)) {
                throw new Exception("Erreur lors de la suppression des enchères : " . mysqli_error($db_handle));
            }

            // Étape 4 : Supprime tous les articles présents dans les paniers
            $delete_panier_sql = "DELETE FROM articlepanier WHERE article_id IN ($article_ids_string)";
            if (!mysqli_query($db_handle, $delete_panier_sql)) {
                throw new Exception("Erreur lors de la suppression du panier : " . mysqli_error($db_handle));
            }

            // Étape 5 : Supprime toutes les ventes liées aux articles
            $delete_ventes_sql = "DELETE FROM vente WHERE article_id IN ($article_ids_string)";
            if (!mysqli_query($db_handle, $delete_ventes_sql)) {
                throw new Exception("Erreur lors de la suppression des ventes : " . mysqli_error($db_handle));
            }

            // Étape 6 : Les négociations et achats sont en suppression en cascade en base (FOREIGN KEY ON DELETE CASCADE)
            // Pas besoin de suppression manuelle ici

            // Étape 7 : Supprime les articles eux-mêmes
            $delete_articles_sql = "DELETE FROM article WHERE categorie_id = $id";
            if (!mysqli_query($db_handle, $delete_articles_sql)) {
                throw new Exception("Erreur lors de la suppression des articles : " . mysqli_error($db_handle));
            }
        }

        // Étape 8 : Supprime la catégorie elle-même
        $delete_category_sql = "DELETE FROM categorie WHERE id = $id";
        if (!mysqli_query($db_handle, $delete_category_sql)) {
            throw new Exception("Erreur lors de la suppression de la catégorie : " . mysqli_error($db_handle));
        }

        // Valide la transaction
        mysqli_commit($db_handle);

        $nb_articles = count($article_ids);
        $message = "Catégorie supprimée avec succès ! ($nb_articles article(s) et toutes les données associées supprimées)";
    } catch (Exception $e) {
        // Si une erreur se produit, on annule tout avec rollback
        mysqli_rollback($db_handle);
        $message = "Erreur lors de la suppression : " . $e->getMessage();
    }

    // On réactive l'autocommit
    mysqli_autocommit($db_handle, TRUE);
}
?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8"> 
    <title>Gestion des Catégories - Admin | Agora Francia</title> <!-- Titre de l'onglet du navigateur -->
    <!-- Lien vers la bibliothèque CSS Bootstrap pour une mise en page rapide et responsive -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Lien vers la feuille de style personnalisée -->
    <link rel="stylesheet" href="css/style-site.css">
</head>
<body>
<?php include 'header.php'; ?> <!-- Inclusion de l'en-tête commun à toutes les pages -->

<div class="container"> <!-- Conteneur Bootstrap -->
    <h2 class="mb-4 text-primary text-center">Gestion des Catégories</h2> <!-- Titre principal de la page -->

    <?php if ($message): ?> <!-- Si un message existe (succès ou erreur), on l'affiche -->
        <!-- Choisit la classe Bootstrap alert-danger (rouge) si le mot "Erreur" est présent, sinon alert-success -->
        <div class="alert <?= strpos($message, 'Erreur') !== false ? 'alert-danger' : 'alert-success' ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- --------- FORMULAIRE D’AJOUT D’UNE NOUVELLE CATÉGORIE --------- -->
    <div class="card mb-4">
        <div class="card-header">Ajouter une catégorie</div>
        <div class="card-body">
            <form method="post"> <!-- Formulaire en POST -->
                <div class="mb-3">
                    <!-- Champ texte obligatoire pour le nom de la catégorie -->
                    <input required name="nom" type="text" class="form-control" placeholder="Nom de la catégorie">
                </div>
                <!-- Bouton de validation du formulaire -->
                <button type="submit" name="ajouter_categorie" class="btn btn-success">Ajouter</button>
            </form>
        </div>
    </div>

    <!-- --------- LISTE DES CATÉGORIES AVEC ACTIONS --------- -->
    <div class="card">
        <div class="card-header">Liste des catégories</div>
        <div class="card-body">
            <?php
            // Requête SQL pour récupérer toutes les catégories + nombre d'articles associés
            $sql = "SELECT c.*, COUNT(a.id) as nb_articles 
                    FROM categorie c 
                    LEFT JOIN article a ON c.id = a.categorie_id 
                    GROUP BY c.id 
                    ORDER BY c.nom";
            $result = mysqli_query($db_handle, $sql); // Exécution de la requête

            if (mysqli_num_rows($result) > 0): // Si on a au moins une catégorie
            ?>
            <div class="table-responsive"> <!-- Table responsive Bootstrap -->
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nom</th> <!-- Colonne nom de la catégorie -->
                            <th>Nb d'articles</th> <!-- Nombre d'articles liés -->
                            <th>Actions</th> <!-- Actions de gestion -->
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($cat = mysqli_fetch_assoc($result)): ?> <!-- Boucle sur chaque catégorie -->
                        <tr>
                            <td><?= htmlspecialchars($cat['nom']) ?></td> <!-- Affiche le nom en échappant les caractères spéciaux -->
                            <td>
                                <?= $cat['nb_articles'] ?> article(s) <!-- Affiche le nombre d’articles -->
                                <?php if ($cat['nb_articles'] > 0): ?>
                                    <br><small class="text-muted">
                                        <?php
                                        // Requête SQL pour les titres des 3 premiers articles de cette catégorie
                                        $articles_sql = "SELECT titre FROM article WHERE categorie_id = {$cat['id']} LIMIT 3";
                                        $articles_result = mysqli_query($db_handle, $articles_sql);
                                        $articles_list = [];
                                        while ($article = mysqli_fetch_assoc($articles_result)) {
                                            $articles_list[] = htmlspecialchars($article['titre']); // On échappe le titre pour éviter le XSS
                                        }
                                        echo implode(', ', $articles_list); // Affiche les titres séparés par des virgules
                                        if ($cat['nb_articles'] > 3) {
                                            echo " (...)"; // Ajoute "..." si plus de 3 articles
                                        }
                                        ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($cat['nb_articles'] == 0): ?>
                                    <!-- Si la catégorie est vide : suppression simple -->
                                    <form method="post" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ?');">
                                        <input type="hidden" name="supprimer_categorie" value="<?= $cat['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                                    </form>
                                <?php else: ?>
                                    <!-- Si des articles sont présents : suppression en cascade avec confirmation renforcée -->
                                    <form method="post" class="d-inline"
                                          onsubmit="return confirm('⚠️ ATTENTION SUPPRESSION COMPLÈTE ⚠️\n\nSupprimer cette catégorie supprimera DÉFINITIVEMENT :\n• La catégorie\n• TOUS les <?= $cat['nb_articles'] ?> articles\n• Toutes les transactions liées\n• Toutes les enchères en cours\n• Tous les éléments de panier\n• Toutes les ventes\n• Toutes les négociations\n• Tous les achats\n\n🚨 CETTE ACTION EST IRRÉVERSIBLE 🚨\n\nÊtes-vous ABSOLUMENT sûr de vouloir tout supprimer ?');">
                                        <input type="hidden" name="supprimer_categorie_cascade" value="<?= $cat['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-exclamation-triangle"></i> Supprimer TOUT
                                        </button>
                                    </form>
                                    <!-- Message visuel de confirmation -->
                                    <br>
                                    <small class="text-danger">
                                        <strong>⚠️ Suppression complète : catégorie + <?= $cat['nb_articles'] ?> article(s) + toutes données associées</strong>
                                    </small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?> <!-- Si aucune catégorie n'existe -->
                <p class="text-muted">Aucune catégorie enregistrée.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lien retour vers la page d'administration -->
    <a href="admin.php" class="btn btn-secondary mt-3">Retour à l'administration</a>
</div>

<?php include 'footer.php'; ?> <!-- Inclusion du pied de page commun -->
</body>
</html>
