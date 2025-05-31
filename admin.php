<?php
session_start();
// Sécurité : accès réservé à l'admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header('Location: compte.php');
    exit();
}
?>
<!DOCTYPE html> <!-- Déclaration du type de document HTML5 -->
<head>
    <meta charset="UTF-8"> <!-- Définition de l'encodage des caractères -->
    <title>Administration | Agora Francia</title> <!-- Titre de la page affiché dans l'onglet -->

    <!-- Bootstrap pour le style de base -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <!-- Importation de la police Google Fonts "Poppins" -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Lien vers la feuille de style personnalisée du site -->
    <link rel="stylesheet" href="css/style-site.css">

        <style>
        /* Applique la police Poppins sur toute la page */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa; /* Fond gris clair */
        }

        /* Style du titre principal de la page admin */
        .admin-title {
            color: #2c3e50; /* Bleu foncé */
            font-weight: 600;
            font-size: 2.5rem;
            margin-bottom: 2rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Style général des cartes Bootstrap */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Ombre */
            transition: transform 0.2s;
        }

        /* Effet au survol des cartes */
        .card:hover {
            transform: translateY(-5px); /* Légère élévation de la carte */
        }

        /* Tête des cartes avec mise en forme */
        .card-header {
            border-radius: 15px 15px 0 0 !important;
            font-weight: 500;
            font-size: 1.2rem;
            padding: 1.25rem;
        }

        /* Style des boutons personnalisés */
        .btn-custom {
            border-radius: 10px;
            font-weight: 500;
            padding: 12px 20px;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        /* Effet au survol des boutons */
        .btn-custom:hover {
            transform: scale(1.02);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Style spécifique pour le bouton de déconnexion */
        .btn-logout {
            background: linear-gradient(45deg, #e74c3c, #c0392b); /* Dégradé rouge */
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Effet hover pour le bouton logout */
        .btn-logout:hover {
            background: linear-gradient(45deg, #c0392b, #e74c3c); /* Inverse le dégradé */
            transform: scale(1.05);
            color: white;
        }

        /* Style intérieur de la carte */
        .card-body {
            padding: 2rem;
        }

        /* Supprime la marge basse du dernier élément d'une liste non ordonnée */
        .list-unstyled li:last-child {
            margin-bottom: 0 !important;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?> <!-- Inclusion de la barre du haut du site -->

<div class="container mt-5"> <!-- Conteneur principal Bootstrap avec marge haute -->
    <h1 class="admin-title text-center mb-5">Espace Administration</h1> 
    <!-- Titre principal de la page, centré, avec marges -->

    <div class="row justify-content-center"> <!-- Ligne Bootstrap centrée horizontalement -->
        <div class="col-md-6 mb-4"> <!-- Colonne de taille moyenne avec une marge en bas -->
            <div class="card"> <!-- Carte Bootstrap pour encadrer une section -->
                <div class="card-header bg-gradient text-white" style="background-color: #3498db;">
                    Gestion des Utilisateurs
                    <!-- En-tête de la carte avec fond bleu et texte blanc -->
                </div>
                <div class="card-body p-4"> <!-- Corps de la carte avec padding -->
                    <ul class="list-unstyled"> <!-- Liste sans puces -->
                        <li class="mb-3"> <!-- Élément avec marge -->
                            <a href="admin_gestion_acheteurs.php" class="btn btn-custom btn-outline-primary w-100">
                                Gérer les acheteurs
                            </a> <!-- Bouton personnalisé pour accéder à la gestion des acheteurs -->
                        </li>
                        <li class="mb-3">
                            <a href="admin_gestion_vendeurs.php" class="btn btn-custom btn-outline-primary w-100">
                                Gérer les vendeurs
                            </a> <!-- Bouton pour accéder à la gestion des vendeurs -->
                        </li>
                        <li class="mb-3">
                            <a href="admin_gestion_categories.php" class="btn btn-custom btn-outline-primary w-100">
                                Gérer les catégories
                            </a> <!-- Bouton pour accéder à la gestion des catégories d'articles -->
                        </li>
                    </ul>
                </div> 
            </div> 
        </div> 
    </div> 

    <div class="text-center mt-5"> <!-- Section centrée pour le bouton logout -->
        <a href="logout.php" class="btn btn-logout">Se déconnecter</a> 
        <!-- Bouton rouge stylisé pour se déconnecter de la session admin -->
    </div>
</div> <!-- Fin du conteneur principal -->

<?php include 'footer.php'; ?> <!-- Inclusion du pied de page -->

<!-- Script JS de Bootstrap pour activer les composants dynamiques (modal, dropdown, etc.) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
