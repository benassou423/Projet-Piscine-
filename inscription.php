<?php
session_start();
$database = "agora";
$db_handle = mysqli_connect('localhost', 'root', ''); //connexion à la db
$db_found = mysqli_select_db($db_handle, $database); // on recupère la bdd agora

$message = "";


if ($_SERVER['REQUEST_METHOD'] == 'POST' && $db_found) {
    // Récupération sécurisée des champs
    $nom      = mysqli_real_escape_string($db_handle, trim($_POST['nom'])); //trim pour enlever les espaces inutiles 
    $prenom   = mysqli_real_escape_string($db_handle, trim($_POST['prenom'])); // mysqli_real_escape_string pour empecher les requètes sql de s'éxécuter
    $email    = mysqli_real_escape_string($db_handle, trim($_POST['email']));
    $password = $_POST['password'];
    $password2 = $_POST['password2'];
    $adresse1 = mysqli_real_escape_string($db_handle, trim($_POST['adresse1']));
    $adresse2 = mysqli_real_escape_string($db_handle, trim($_POST['adresse2']));
    $ville    = mysqli_real_escape_string($db_handle, trim($_POST['ville']));
    $cp       = mysqli_real_escape_string($db_handle, trim($_POST['code_postal']));
    $pays     = mysqli_real_escape_string($db_handle, trim($_POST['pays']));
    $tel      = mysqli_real_escape_string($db_handle, trim($_POST['telephone']));

    // Vérif mot de passe
    if ($password !== $password2) {
        $message = "Les mots de passe ne correspondent pas.";
    } else {
        // Email déjà utilisé ?
        $sql = "SELECT id FROM Utilisateur WHERE email = '$email'";
        $result = mysqli_query($db_handle, $sql);
        if (mysqli_num_rows($result) > 0) { //si on a plus de 1 fois le même email : message d'erreur 
            $message = "Cet email est déjà utilisé.";
        } else {
            // Hash sécurisé
            $hash = password_hash($password, PASSWORD_BCRYPT); //crypte le mot de passe

            $sql = "INSERT INTO Utilisateur 
                (nom, prenom, email, mot_de_passe, role, adresse_ligne1, adresse_ligne2, ville, code_postal, pays, telephone)
                VALUES 
                ('$nom', '$prenom', '$email', '$hash', 'acheteur', '$adresse1', '$adresse2', '$ville', '$cp', '$pays', '$tel')";
            if (mysqli_query($db_handle, $sql)) {
                $message = "Inscription réussie ! Vous pouvez vous connecter.";
            } else {
                $message = "Erreur lors de l'inscription : " . mysqli_error($db_handle);
            }
        }
    }
} elseif (!$db_found) {
    $message = "Base de données non trouvée.";
}
?>


<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Inscription - Agora Francia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="css/style-site.css"></head>
<body>
<?php include 'header.php'; ?>
<div class="container">
    <h2 class="mb-4 text-primary text-center">Inscription Acheteur</h2>
    <?php if ($message): ?>
        <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>
    <form method="post">
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
        <button type="submit" class="btn btn-primary w-100">S'inscrire</button>
    </form>
    <p class="mt-4 text-center">Déjà un compte? <a href="connexion.php">Connexion</a></p>
</div>
<?php include 'footer.php'; ?>
</body>
</html>
