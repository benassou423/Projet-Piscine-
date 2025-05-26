<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// Connexion MySQLi
$host     = 'localhost';
$user     = 'root';
$password = 'root';
$database = 'piscine';

$db = mysqli_connect($host, $user, $password, $database);
if (!$db) {
    die('Erreur de connexion MySQL : ' . mysqli_connect_error());
}

$errors = [];
$success = false;

// Si formulaire soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération & nettoyage
    $nom      = trim($_POST['nom'] ?? '');
    $prenom   = trim($_POST['prenom'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $mdp      = $_POST['mot_de_passe'] ?? '';
    $mdp2     = $_POST['mot_de_passe2'] ?? '';
    $role     = $_POST['role'] ?? '';

    // Validations
    if ($nom === '' || $prenom === '') {
        $errors[] = "Le nom et le prénom sont obligatoires.";
    }
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Adresse e-mail invalide.";
    }
    if (strlen($mdp) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    }
    if ($mdp !== $mdp2) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }
    $allowedRoles = ['client','prestataire','admin'];
    if (! in_array($role, $allowedRoles, true)) {
        $errors[] = "Rôle invalide.";
    }

    // Unicité de l’email
    if (empty($errors)) {
        $sql  = "SELECT COUNT(*) FROM utilisateur WHERE email = ?";
        $stmt = mysqli_prepare($db, $sql);
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        if ($count > 0) {
            $errors[] = "Cet e-mail est déjà utilisé.";
        }
    }

    // Insertion si pas d’erreur
    if (empty($errors)) {
        $hash = password_hash($mdp, PASSWORD_DEFAULT);
        $sql  = "INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role)
                 VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($db, $sql);
        mysqli_stmt_bind_param($stmt, 'sssss', $nom, $prenom, $email, $hash, $role);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Inscription – Résultat</title>
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
  >
</head>
<body class="bg-light">
  <div class="container py-5">
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>

      <?php if ($success): ?>
        <div class="alert alert-success">
          ✅ Votre compte a bien été créé.
        </div>
        <p class="text-center">
          <a href="compte.html" class="btn btn-primary">Se connecter</a>
        </p>

      <?php else: ?>
        <div class="alert alert-danger">
          <h5>⚠️ Erreurs lors de l’inscription :</h5>
          <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <p><a href="inscription.html" class="btn btn-outline-primary">← Revenir au formulaire</a></p>
      <?php endif; ?>

    <?php else: ?>
      <div class="alert alert-info">
        Merci de passer par <a href="inscription.html">le formulaire d’inscription</a>.
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
