<?php
session_start();

// Vérification que seul l'administrateur peut accéder à cette page
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    // Si l'utilisateur n'est pas admin, redirection vers la page compte
    header('Location: compte.php');
    exit();
}

// Connexion à la base de données "agora"
$database = "agora";
$db_handle = mysqli_connect('localhost', 'root', '');
$db_found = mysqli_select_db($db_handle, $database);

$message_ajout = "";

// Traitement de la rétrogradation d'un vendeur en acheteur
if (isset($_POST['retrograder_vendeur']) && !empty($_POST['retrograder_vendeur'])) {
    $id_vendeur = intval($_POST['retrograder_vendeur']); // conversion sécurisée
    $sql_update = "UPDATE Utilisateur SET role = 'acheteur' WHERE id = ?";
    $stmt = mysqli_prepare($db_handle, $sql_update); // requête préparée
    mysqli_stmt_bind_param($stmt, "i", $id_vendeur); // liaison paramètre ID
    if (mysqli_stmt_execute($stmt)) {
        $message_ajout = "Le vendeur a été rétrogradé en acheteur avec succès.";
        header('Location: admin_gestion_vendeurs.php');
        exit();
    } else {
        $message_ajout = "Erreur lors de la rétrogradation : " . mysqli_error($db_handle);
    }
}

// Traitement de la suppression complète d'un vendeur
if (isset($_POST['supprimer_vendeur']) && !empty($_POST['supprimer_vendeur'])) {
    $id_vendeur = intval($_POST['supprimer_vendeur']);
    mysqli_begin_transaction($db_handle); // 🌐 Début de la transaction
    try {
        // 1. Supprimer les paniers où il était acheteur
        $sql_delete_panier = "DELETE FROM Panier WHERE acheteur_id = ?";
        $stmt = mysqli_prepare($db_handle, $sql_delete_panier);
        mysqli_stmt_bind_param($stmt, "i", $id_vendeur);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // 2. Supprimer les enchères où il était acheteur
        $sql_delete_enchere_acheteur = "DELETE FROM Enchere WHERE acheteur_id = ?";
        $stmt = mysqli_prepare($db_handle, $sql_delete_enchere_acheteur);
        mysqli_stmt_bind_param($stmt, "i", $id_vendeur);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // 3. Supprimer les enchères liées aux articles qu'il a mis en vente
        $sql_delete_enchere_articles = "DELETE e FROM Enchere e INNER JOIN Article a ON e.article_id = a.id WHERE a.vendeur_id = ?";
        $stmt = mysqli_prepare($db_handle, $sql_delete_enchere_articles);
        mysqli_stmt_bind_param($stmt, "i", $id_vendeur);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // 4. Supprimer les transactions où il est acheteur
        $sql_delete_transactions_acheteur = "DELETE FROM Transaction WHERE acheteur_id = ?";
        $stmt = mysqli_prepare($db_handle, $sql_delete_transactions_acheteur);
        mysqli_stmt_bind_param($stmt, "i", $id_vendeur);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // 5. Supprimer les transactions liées à ses articles (vendeur)
        $sql_delete_transactions_vendeur = "DELETE t FROM Transaction t INNER JOIN Article a ON t.article_id = a.id WHERE a.vendeur_id = ?";
        $stmt = mysqli_prepare($db_handle, $sql_delete_transactions_vendeur);
        mysqli_stmt_bind_param($stmt, "i", $id_vendeur);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // 6. Supprimer les alertes créées par lui
        $sql_delete_alertes = "DELETE FROM Alerte WHERE user_id = ?";
        $stmt = mysqli_prepare($db_handle, $sql_delete_alertes);
        mysqli_stmt_bind_param($stmt, "i", $id_vendeur);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // 7. Supprimer les notifications reçues
        $sql_delete_notifs = "DELETE FROM Notification WHERE user_id = ?";
        $stmt = mysqli_prepare($db_handle, $sql_delete_notifs);
        mysqli_stmt_bind_param($stmt, "i", $id_vendeur);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // 8. Supprimer les négociations (en tant que vendeur ou acheteur)
        $sql_delete_nego = "DELETE FROM Negociation WHERE vendeur_id = ? OR acheteur_id = ?";
        $stmt = mysqli_prepare($db_handle, $sql_delete_nego);
        mysqli_stmt_bind_param($stmt, "ii", $id_vendeur, $id_vendeur);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // 9. Supprimer tous ses articles mis en vente
        $sql_delete_articles = "DELETE FROM Article WHERE vendeur_id = ?";
        $stmt = mysqli_prepare($db_handle, $sql_delete_articles);
        mysqli_stmt_bind_param($stmt, "i", $id_vendeur);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // 10. Enfin, supprimer l'utilisateur de la table Utilisateur
        $sql_delete_user = "DELETE FROM Utilisateur WHERE id = ? AND role = 'vendeur'";
        $stmt = mysqli_prepare($db_handle, $sql_delete_user);
        mysqli_stmt_bind_param($stmt, "i", $id_vendeur);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        //Fin de transaction : on valide tout
        mysqli_commit($db_handle);
        $message_ajout = "Le vendeur et toutes ses données associées ont été supprimés avec succès.";
        header('Location: admin_gestion_vendeurs.php');
        exit();
    } catch (Exception $e) {
        // En cas d'erreur : rollback pour annuler tous les changements
        mysqli_rollback($db_handle);
        $message_ajout = "Erreur lors de la suppression du vendeur : " . $e->getMessage();
    }
}

// Traitement ajout vendeur : ce bloc s'exécute si le formulaire d'ajout a été soumis avec la méthode POST et si la base est bien connectée
if (isset(\$_POST['ajouter_vendeur']) && \$_SERVER['REQUEST_METHOD'] == 'POST' && \$db_found) {
    // Récupère l'email envoyé par le formulaire et le sécurise
    \$email = mysqli_real_escape_string(\$db_handle, trim(\$_POST['email']));
    
    // Prépare une requête pour vérifier si un utilisateur avec cet email existe déjà
    \$sql = "SELECT id, role, nom, prenom FROM Utilisateur WHERE email = ?";
    \$stmt = mysqli_prepare(\$db_handle, \$sql);
    mysqli_stmt_bind_param(\$stmt, "s", \$email);
    mysqli_stmt_execute(\$stmt);
    \$result = mysqli_stmt_get_result(\$stmt);
    
    if (mysqli_num_rows(\$result) > 0) {
        // Si un utilisateur existe déjà avec cet email
        \$user = mysqli_fetch_assoc(\$result);
        if (\$user['role'] == 'vendeur') {
            // Si c'est déjà un vendeur, on affiche une erreur
            \$message_ajout = "Cet email est déjà utilisé par un vendeur !";
        } else {
            // Sinon, on le convertit en vendeur
            \$sql = "UPDATE Utilisateur SET role = 'vendeur' WHERE id = ?";
            \$stmt = mysqli_prepare(\$db_handle, \$sql);
            mysqli_stmt_bind_param(\$stmt, "i", \$user['id']);
            
            // Gestion de la photo si un fichier a été envoyé
            if (isset(\$_FILES['photo']) && \$_FILES['photo']['error'] == 0) {
                \$target_dir = "images/";
                if (!file_exists(\$target_dir)) {
                    mkdir(\$target_dir, 0777, true);
                }
                
                // Vérifie l'extension de l'image
                \$extension = strtolower(pathinfo(\$_FILES['photo']['name'], PATHINFO_EXTENSION));
                \$allowed = array('jpg', 'jpeg', 'png', 'gif');
                
                if (in_array(\$extension, \$allowed)) {
                    \$photo_name = uniqid('profile_') . '.' . \$extension;
                    \$target_file = \$target_dir . \$photo_name;
                    
                    // Déplace le fichier uploadé vers le dossier images
                    if (move_uploaded_file(\$_FILES['photo']['tmp_name'], \$target_file)) {
                        // Met à jour la requête SQL pour inclure la photo
                        \$sql = "UPDATE Utilisateur SET role = 'vendeur', photo = ? WHERE id = ?";
                        \$stmt = mysqli_prepare(\$db_handle, \$sql);
                        mysqli_stmt_bind_param(\$stmt, "si", \$target_file, \$user['id']);
                    }
                }
            }
            
            // Exécution de la mise à jour de rôle (et photo si existante)
            if (mysqli_stmt_execute(\$stmt)) {
                \$message_ajout = "Le compte de {\$user['prenom']} {\$user['nom']} a été converti en compte vendeur avec succès !";
            } else {
                \$message_ajout = "Erreur lors de la conversion du compte : " . mysqli_error(\$db_handle);
            }
        }
    } else {
        // Si l'utilisateur n'existe pas : on crée un nouveau vendeur
        if (empty(\$_POST['nom']) || empty(\$_POST['prenom']) || empty(\$_POST['password']) || 
            empty(\$_POST['password2']) || empty(\$_POST['adresse1']) || empty(\$_POST['ville']) || 
            empty(\$_POST['code_postal']) || empty(\$_POST['pays']) || empty(\$_POST['telephone'])) {
            \$message_ajout = "Tous les champs marqués d'une étoile sont requis pour créer un nouveau compte vendeur.";
        } else {
            // Sécurisation des champs du formulaire
            \$nom = mysqli_real_escape_string(\$db_handle, trim(\$_POST['nom']));
            \$prenom = mysqli_real_escape_string(\$db_handle, trim(\$_POST['prenom']));
            \$password = \$_POST['password'];
            \$password2 = \$_POST['password2'];
            \$adresse1 = mysqli_real_escape_string(\$db_handle, trim(\$_POST['adresse1']));
            \$adresse2 = mysqli_real_escape_string(\$db_handle, trim(\$_POST['adresse2']));
            \$ville = mysqli_real_escape_string(\$db_handle, trim(\$_POST['ville']));
            \$code_postal = mysqli_real_escape_string(\$db_handle, trim(\$_POST['code_postal']));
            \$pays = mysqli_real_escape_string(\$db_handle, trim(\$_POST['pays']));
            \$telephone = mysqli_real_escape_string(\$db_handle, trim(\$_POST['telephone']));

            if (\$password !== \$password2) {
                // Vérifie que les mots de passe sont identiques
                \$message_ajout = "Les mots de passe ne correspondent pas.";
            } else {
                // Hash du mot de passe pour sécurisation
                \$hash = password_hash(\$password, PASSWORD_BCRYPT);
                
                // Gestion de la photo (optionnelle)
                \$photo_path = null;
                if (isset(\$_FILES['photo']) && \$_FILES['photo']['error'] == 0) {
                    \$target_dir = "images/";
                    if (!file_exists(\$target_dir)) {
                        mkdir(\$target_dir, 0777, true);
                    }
                    
                    \$extension = strtolower(pathinfo(\$_FILES['photo']['name'], PATHINFO_EXTENSION));
                    \$allowed = array('jpg', 'jpeg', 'png', 'gif');
                    
                    if (in_array(\$extension, \$allowed)) {
                        \$photo_name = uniqid('profile_') . '.' . \$extension;
                        \$target_file = \$target_dir . \$photo_name;
                        
                        if (move_uploaded_file(\$_FILES['photo']['tmp_name'], \$target_file)) {
                            \$photo_path = \$target_file;
                        }
                    }
                }
                
                // Prépare l'insertion du nouveau vendeur en base
                \$sql = "INSERT INTO Utilisateur (nom, prenom, email, mot_de_passe, role, photo) VALUES (?, ?, ?, ?, 'vendeur', ?)";
                \$stmt = mysqli_prepare(\$db_handle, \$sql);
                
                if (\$stmt) {
                    mysqli_stmt_bind_param(\$stmt, "sssss",
                        \$nom, \$prenom, \$email, \$hash, \$photo_path
                    );
                    
                    if (mysqli_stmt_execute(\$stmt)) {
                        \$message_ajout = "Nouveau vendeur ajouté avec succès !";
                        header('Location: admin_gestion_vendeurs.php');
                        exit();
                    } else {
                        \$message_ajout = "Erreur lors de l'ajout : " . mysqli_error(\$db_handle);
                    }
                    mysqli_stmt_close(\$stmt);
                } else {
                    \$message_ajout = "Erreur de préparation de la requête : " . mysqli_error(\$db_handle);
                }
            }
        }
    }
}
?>
<!DOCTYPE html> 
<head>
    <meta charset="UTF-8"> 
    <title>Gestion des vendeurs - Admin | Agora Francia</title> <!-- Titre affiché dans l'onglet du navigateur -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"> <!-- Lien vers la feuille CSS Bootstrap via CDN -->
    <link rel="stylesheet" href="css/style-site.css"> <!-- Lien vers la feuille CSS personnalisée du site -->
</head>
<body>
<?php include 'header.php'; ?> <!-- Inclusion du fichier header PHP (souvent barre de navigation ou entête commun) -->

<div class="container"> <!-- Conteneur Bootstrap centré avec marges -->
    <h2 class="mb-4 text-primary text-center">Gestion des vendeurs</h2> <!-- Titre principal avec marge bas, texte bleu et centré -->

    <?php if ($message_ajout): ?> <!-- Si un message d'ajout existe (ex: vendeur ajouté) -->
        <div class="alert alert-info"><?= htmlspecialchars($message_ajout) ?></div> <!-- Affiche une alerte d'information -->
    <?php endif; ?>

    <!-- Ajout d'un vendeur -->
    <div class="card mb-4"> <!-- Carte Bootstrap avec marge bas -->
        <div class="card-header">Ajouter un vendeur</div> <!-- En-tête de la carte -->
        <div class="card-body"> <!-- Corps de la carte -->
            <form method="post" class="mb-3" enctype="multipart/form-data" id="vendeurForm"> <!-- Formulaire d'ajout de vendeur -->
                <div class="mb-2">
                    <input required name="email" type="email" class="form-control" placeholder="Email ECE" id="emailInput"> <!-- Champ email obligatoire -->
                    <small class="text-muted">Commencez par entrer l'email pour vérifier si le compte existe déjà</small> <!-- Aide utilisateur -->
                </div>

                <div id="newVendeurFields" style="display: none;"> <!-- Champs masqués par défaut, visibles si email nouveau -->
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <input name="nom" type="text" class="form-control" placeholder="Nom *" required> <!-- Nom obligatoire -->
                        </div>
                        <div class="col-md-6 mb-2">
                            <input name="prenom" type="text" class="form-control" placeholder="Prénom *" required> <!-- Prénom obligatoire -->
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <input name="password" type="password" class="form-control" placeholder="Mot de passe *" required> <!-- Mot de passe -->
                        </div>
                        <div class="col-md-6 mb-2">
                            <input name="password2" type="password" class="form-control" placeholder="Confirmer mot de passe *" required> <!-- Confirmation -->
                        </div>
                    </div>

                    <div class="mb-2">
                        <input name="adresse1" type="text" class="form-control" placeholder="Adresse (ligne 1) *" required> <!-- Adresse ligne 1 -->
                    </div>
                    <div class="mb-2">
                        <input name="adresse2" type="text" class="form-control" placeholder="Adresse (ligne 2)"> <!-- Adresse ligne 2 optionnelle -->
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <input name="ville" type="text" class="form-control" placeholder="Ville *" required> <!-- Ville -->
                        </div>
                        <div class="col-md-3 mb-2">
                            <input name="code_postal" type="text" class="form-control" placeholder="Code postal *" required> <!-- Code postal -->
                        </div>
                        <div class="col-md-3 mb-2">
                            <input name="pays" type="text" class="form-control" placeholder="Pays *" required> <!-- Pays -->
                        </div>
                    </div>

                    <div class="mb-2">
                        <input name="telephone" type="tel" class="form-control" placeholder="Téléphone *" required> <!-- Téléphone -->
                    </div>
                </div>

                <div class="mb-2">
                    <label>Photo de profil</label> <!-- Étiquette champ image -->
                    <input type="file" name="photo" class="form-control" accept="image/*"> <!-- Upload de photo de profil -->
                </div>

                <button type="submit" name="ajouter_vendeur" class="btn btn-success w-100">Créer le vendeur</button> <!-- Bouton de validation -->
            </form>

            <div id="emailError" class="alert alert-danger" style="display: none;">
                Cet email correspond déjà à un compte vendeur ! <!-- Message d'erreur si email déjà vendeur -->
            </div>
            <div id="emailWarning" class="alert alert-warning" style="display: none;">
                Cet email correspond à un compte acheteur. Voulez-vous le convertir en compte vendeur ? <!-- Alerte conversion -->
                <div class="mt-2">
                    <button type="button" id="confirmConversion" class="btn btn-warning btn-sm">Oui, convertir en vendeur</button>
                    <button type="button" id="cancelConversion" class="btn btn-secondary btn-sm ms-2">Annuler</button>
                </div>
            </div>

            <script>
            let isAcheteur = false; // Variable pour savoir si l'utilisateur est un acheteur existant
            document.getElementById('emailInput').addEventListener('blur', function() {
                const email = this.value; // Récupère l'email saisi
                const errorDiv = document.getElementById('emailError'); // Sélection des divs d'alerte
                const warningDiv = document.getElementById('emailWarning');
                const submitButton = document.querySelector('button[name="ajouter_vendeur"]');
                
                if (email) {
                    fetch('check_email.php?email=' + encodeURIComponent(email)) // Requête AJAX GET vers le script PHP
                        .then(response => response.json()) // Réponse en JSON attendue
                        .then(data => {
                            const newVendeurFields = document.getElementById('newVendeurFields');
                            const inputs = newVendeurFields.querySelectorAll('input'); // Tous les inputs du bloc

                            if (data.exists && data.role === 'vendeur') {
                                // Cas : email déjà associé à un vendeur
                                errorDiv.style.display = 'block';
                                warningDiv.style.display = 'none';
                                newVendeurFields.style.display = 'none';
                                submitButton.style.display = 'none';
                                inputs.forEach(input => input.required = false); // Rend tous les champs non requis
                                isAcheteur = false;
                            } else if (data.exists && data.role === 'acheteur') {
                                // Cas : email existant pour un acheteur
                                errorDiv.style.display = 'none';
                                warningDiv.style.display = 'block';
                                newVendeurFields.style.display = 'none';
                                submitButton.style.display = 'none';
                                inputs.forEach(input => input.required = false);
                                isAcheteur = true;
                            } else {
                                // Cas : nouvel email => on montre tous les champs
                                errorDiv.style.display = 'none';
                                warningDiv.style.display = 'none';
                                newVendeurFields.style.display = 'block';
                                submitButton.style.display = 'block';
                                submitButton.disabled = false;
                                inputs.forEach(input => {
                                    if (input.name !== 'adresse2') {
                                        input.required = true; // Tous sauf l’adresse 2 doivent être requis
                                    }
                                });
                            }
                        });
                }
            });
                        // Gestion des boutons de confirmation
            document.getElementById('confirmConversion').addEventListener('click', function() {
                const warningDiv = document.getElementById('emailWarning'); // Sélection de l'alerte de conversion
                const submitButton = document.querySelector('button[name="ajouter_vendeur"]'); // Bouton de soumission du formulaire
                
                warningDiv.style.display = 'none'; // Masquer l'alerte de conversion
                submitButton.style.display = 'block'; // Afficher le bouton de validation
                if (isAcheteur) {
                    submitButton.textContent = 'Convertir en vendeur'; // Modifier le texte du bouton
                    submitButton.classList.remove('btn-success'); // Retirer la classe Bootstrap verte
                    submitButton.classList.add('btn-warning'); // Ajouter la classe Bootstrap orange
                }
            });

            document.getElementById('cancelConversion').addEventListener('click', function() {
                const warningDiv = document.getElementById('emailWarning'); // Alerte affichée pour les acheteurs
                const submitButton = document.querySelector('button[name="ajouter_vendeur"]'); // Bouton de soumission
                const emailInput = document.getElementById('emailInput'); // Champ email
                
                warningDiv.style.display = 'none'; // Masquer l'avertissement
                submitButton.style.display = 'none'; // Masquer le bouton
                emailInput.value = ''; // Réinitialiser le champ email
                isAcheteur = false; // Réinitialisation du statut
            });
            </script> <!-- Fin du script JavaScript -->
        </div>
    </div>

    <!-- Liste des vendeurs -->
    <div class="card"> <!-- Carte Bootstrap contenant la liste des vendeurs -->
        <div class="card-header">Liste des vendeurs</div> <!-- Titre de la section -->
        <div class="card-body">
            <?php
            // Requête SQL pour récupérer tous les utilisateurs avec le rôle vendeur
            $sql = "SELECT * FROM Utilisateur WHERE role='vendeur' ORDER BY nom, prenom";
            $result = mysqli_query($db_handle, $sql);
            if ($result && mysqli_num_rows($result) > 0): // Vérifie si des vendeurs sont trouvés
            ?>
            <div class="table-responsive"> <!-- Table responsive pour affichage sur mobile -->
                <table class="table"> <!-- Tableau Bootstrap -->
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($vendeur = mysqli_fetch_assoc($result)): ?> <!-- Boucle sur chaque vendeur -->
                        <tr>
                            <td>
                <?php if (isset($vendeur['photo']) && $vendeur['photo']): ?> <!-- Si une photo est présente -->
                    <img src="<?= htmlspecialchars($vendeur['photo']) ?>" alt="photo" style="width:50px;height:50px;object-fit:cover;"> <!-- Affichage de la photo -->
                <?php else: ?> <!-- Si aucune photo n'est fournie -->
                    <div class="bg-secondary" style="width:50px;height:50px;"></div> <!-- Placeholder gris -->
                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($vendeur['nom']) ?></td> <!-- Affiche le nom -->
                            <td><?= htmlspecialchars($vendeur['prenom']) ?></td> <!-- Affiche le prénom -->
                            <td><?= htmlspecialchars($vendeur['email']) ?></td> <!-- Affiche l'email -->
                            <td>
                                <!-- Formulaire pour rétrograder un vendeur -->
                                <form method="post" class="d-inline me-1" onsubmit="return confirm('Voulez-vous rétrograder ce vendeur en acheteur ? Ses articles seront conservés mais non visibles jusqu\'à ce qu\'il redevienne vendeur.');">
                                    <input type="hidden" name="retrograder_vendeur" value="<?= (int)$vendeur['id'] ?>"> <!-- ID du vendeur à rétrograder -->
                                    <button type="submit" class="btn btn-warning btn-sm">Rétrograder</button>
                                </form>
                                <!-- Formulaire pour supprimer un vendeur -->
                                <form method="post" class="d-inline" onsubmit="return confirm('ATTENTION : Êtes-vous sûr de vouloir supprimer définitivement ce vendeur ? Cette action supprimera tous ses articles et transactions.');">
                                    <input type="hidden" name="supprimer_vendeur" value="<?= (int)$vendeur['id'] ?>"> <!-- ID du vendeur à supprimer -->
                                    <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?> <!-- Fin de boucle vendeur -->
                    </tbody>
                </table>
            </div>
            <?php else: ?> <!-- Si aucun vendeur n'existe -->
                <p class="text-muted text-center">Aucun vendeur enregistré.</p> <!-- Message d'information -->
            <?php endif; ?> <!-- Fin de la condition PHP -->
        </div>
    </div>

    <a href="admin.php" class="btn btn-secondary mt-3">Retour à l'espace admin</a> <!-- Lien de retour -->
</div>

<?php include 'footer.php'; ?> <!-- Inclusion du pied de page -->
</body>
</html> 

