<?php
session_start();
$database = "agora"; // Mets ici le nom réel de ta BDD !
$db_handle = mysqli_connect('localhost', 'root', 'root');
$db_found = mysqli_select_db($db_handle, $database);

$message_connexion = "";
$message_inscription = "";

// Traitement connexion
if (isset($_POST['action']) && $_POST['action'] == 'connexion' && $db_found) {
    $email = mysqli_real_escape_string($db_handle, trim($_POST['email']));
    $password = $_POST['password'];
    $sql = "SELECT id, nom, prenom, email, mot_de_passe, role FROM Utilisateur WHERE email = '$email'";
    $result = mysqli_query($db_handle, $sql);
    if ($result && mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        if (password_verify($password, $user['mot_de_passe'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nom'] = $user['nom'];
            $_SESSION['user_prenom'] = $user['prenom'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            header("Location: compte.php");
            exit();
        } else {
            $message_connexion = "Mot de passe incorrect.";
        }
    } else {
        $message_connexion = "Utilisateur non trouvé.";
    }
}

// Traitement inscription
if (isset($_POST['action']) && $_POST['action'] == 'inscription' && $db_found) {
    $nom      = mysqli_real_escape_string($db_handle, trim($_POST['nom']));
    $prenom   = mysqli_real_escape_string($db_handle, trim($_POST['prenom']));
    $email    = mysqli_real_escape_string($db_handle, trim($_POST['email']));
    $password = $_POST['password'];
    $password2 = $_POST['password2'];
    $adresse1 = mysqli_real_escape_string($db_handle, trim($_POST['adresse1']));
    $adresse2 = mysqli_real_escape_string($db_handle, trim($_POST['adresse2']));
    $ville    = mysqli_real_escape_string($db_handle, trim($_POST['ville']));
    $cp       = mysqli_real_escape_string($db_handle, trim($_POST['code_postal']));
    $pays     = mysqli_real_escape_string($db_handle, trim($_POST['pays']));
    $tel      = mysqli_real_escape_string($db_handle, trim($_POST['telephone']));
    if ($password !== $password2) {
        $message_inscription = "Les mots de passe ne correspondent pas.";
    } else {
        $sql = "SELECT id FROM Utilisateur WHERE email = '$email'";
        $result = mysqli_query($db_handle, $sql);
        if (mysqli_num_rows($result) > 0) {
            $message_inscription = "Cet email est déjà utilisé.";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $sql = "INSERT INTO Utilisateur 
                (nom, prenom, email, mot_de_passe, role, adresse_ligne1, adresse_ligne2, ville, code_postal, pays, telephone)
                VALUES 
                ('$nom', '$prenom', '$email', '$hash', 'acheteur', '$adresse1', '$adresse2', '$ville', '$cp', '$pays', '$tel')";
            if (mysqli_query($db_handle, $sql)) {
                $message_inscription = "Inscription réussie ! Vous pouvez vous connecter.";
            } else {
                $message_inscription = "Erreur lors de l'inscription : " . mysqli_error($db_handle);
            }
        }
    }
} elseif (!$db_found) {
    $message_connexion = "Base de données non trouvée.";
    $message_inscription = "Base de données non trouvée.";
}

// Affichage compte connecté
if (isset($_SESSION['user_id'])) {
    $prenom = htmlspecialchars($_SESSION['user_prenom']);
    $nom = htmlspecialchars($_SESSION['user_nom']);
    $role = htmlspecialchars($_SESSION['user_role']);
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Mon compte - Agora Francia</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body>
    <div class="container">
        <h2 class="mb-4 text-primary text-center">Bienvenue $prenom $nom</h2>
        <p class="text-center">Vous êtes connecté en tant que <strong>$role</strong>.</p>
        <div class="d-grid gap-2 mt-4">
            <a href="logout.php" class="btn btn-danger">Se déconnecter</a>
            <a href="index.php" class="btn btn-secondary">Retour à l'accueil</a>
        </div>
    </div>
    </body>
    </html>
    HTML;
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Votre Compte - Agora Francia</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <h2 class="mb-4 text-primary text-center">Votre Compte Agora Francia</h2>
    <ul class="nav nav-tabs" id="tab-compte" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="connexion-tab" data-bs-toggle="tab" data-bs-target="#connexion" type="button" role="tab">Connexion</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="inscription-tab" data-bs-toggle="tab" data-bs-target="#inscription" type="button" role="tab">Inscription</button>
        </li>
    </ul>
    <div class="tab-content" id="tabContentCompte">
        <!-- Connexion -->
        <div class="tab-pane fade show active" id="connexion" role="tabpanel">
            <?php if ($message_connexion): ?>
                <div class="alert alert-danger"><?= $message_connexion ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="connexion">
                <div class="mb-3"><input required name="email" type="email" class="form-control" placeholder="Email"></div>
                <div class="mb-3"><input required name="password" type="password" class="form-control" placeholder="Mot de passe"></div>
                <button type="submit" class="btn btn-primary w-100">Se connecter</button>
            </form>
        </div>
        <!-- Inscription -->
        <div class="tab-pane fade" id="inscription" role="tabpanel">
            <?php if ($message_inscription): ?>
                <div class="alert alert-info"><?= $message_inscription ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="inscription">
                <div class="mb-2"><input required name="nom" type="text" class="form-control" placeholder="Nom"></div>
                <div class="mb-2"><input required name="prenom" type="text" class="form-control" placeholder="Prénom"></div>
                <div class="mb-2"><input required name="email" type="email" class="form-control" placeholder="Email"></div>
                <div class="mb-2"><input required name="password" type="password" class="form-control" placeholder="Mot de passe"></div>
                <div class="mb-2"><input required name="password2" type="password" class="form-control" placeholder="Confirmer mot de passe"></div>
                <div class="mb-2"><input name="adresse1" type="text" class="form-control" placeholder="Adresse (ligne 1)"></div>
                <div class="mb-2"><input name="adresse2" type="text" class="form-control" placeholder="Adresse (ligne 2)"></div>
                <div class="mb-2"><input name="ville" type="text" class="form-control" placeholder="Ville"></div>
                <div class="mb-2"><input name="code_postal" type="text" class="form-control" placeholder="Code postal"></div>
                <div class="mb-2"><input name="pays" type="text" class="form-control" placeholder="Pays"></div>
                <div class="mb-3"><input name="telephone" type="text" class="form-control" placeholder="Téléphone"></div>
                <button type="submit" class="btn btn-success w-100">S'inscrire</button>
            </form>
        </div>
    </div>
</div>
<!-- Bootstrap JS for tabs -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Afficher l’onglet inscription si erreur lors de l’inscription
<?php if (!empty($message_inscription)) : ?>
    var tab = new bootstrap.Tab(document.getElementById('inscription-tab'));
    tab.show();
<?php endif; ?>
</script>
</body>
</html>
