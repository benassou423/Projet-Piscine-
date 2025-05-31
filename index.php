<?php
session_start();
include_once "db.php";  // Connexion à la base de données (fichier db.php contient mysqli_connect ou similaire)

// Vérification périodique des enchères expirées (on ne vérifie qu'une fois toutes les 5 minutes)
if (!isset($_SESSION['encheres_checked']) || (time() - $_SESSION['encheres_checked']) > 300) {
    // - Si la variable de session 'encheres_checked' n'existe pas (première visite ou nouvelle session)
    // - OU si le temps actuel (time()) moins la dernière vérification en session > 300 secondes (5 minutes)
    include_once "check_expired_auctions.php"; 
    // Inclut le script qui :
    // parcourt les enchères en base,
    // détecte celles dont la date d’expiration est passée,
    // met à jour leur statut (ex. 'terminée' ou 'expirée') et envoie éventuellement des notifications.
    $_SESSION['encheres_checked'] = time(); 
    // On enregistre l’horodatage actuel pour éviter de relancer cette vérification à chaque rechargement.
}

// Récupération des 6 derniers articles disponibles (sélection du jour)
$selection = []; // Tableau vide qui contiendra les articles à afficher
$sql_sel = "SELECT * FROM Article WHERE statut='disponible' ORDER BY date_creation DESC LIMIT 6";

$res_sel = mysqli_query($db_handle, $sql_sel);
if (!$res_sel) {
    
}

while ($row = mysqli_fetch_assoc($res_sel)) {
    // Tant que chaque ligne (article) existe dans le résultat,
   
    $selection[] = $row;
    // On ajoute la ligne complète (tous les champs) dans le tableau $selection
}


// Récupération de 3 articles aléatoires pour le carrousel (articles mis en avant)

$carrousel = []; // Tableau vide qui contiendra 3 articles au hasard
$sql_car = "SELECT * FROM Article WHERE statut='disponible' ORDER BY RAND() LIMIT 3";

$res_car = mysqli_query($db_handle, $sql_car);
if (!$res_car) {
    // En cas d’erreur SQL, gérer ou loguer
}

while ($row = mysqli_fetch_assoc($res_car)) {
    // On parcourt les 3 lignes obtenues
    $carrousel[] = $row;
    // On les stocke dans le tableau $carrousel
}
?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <title>Accueil | Agora Francia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Inclusion de Bootstrap 5 pour la grille, les composants et le responsive -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Inclusion des polices Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Inclusion des icônes Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style-site.css">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden; 
        }
        .hero-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 6rem 0;            
            margin-bottom: 4rem;        
            border-radius: 0 0 3rem 3rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            position: relative;         
            overflow: hidden;           
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="none" stroke="%23f1f5f9" stroke-width="0.5"/></svg>') repeat;
            
            opacity: 0.4;
            z-index: 0;   
        }
       
        .hero-section .container {
            position: relative;
            z-index: 1;
        }
        
        h1 {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            font-size: 3.5rem;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }
        .lead {
            font-family: 'Poppins', sans-serif;
            font-weight: 300;
            font-size: 1.35rem;
            color: #505d6b;
            line-height: 1.8;
            margin-bottom: 2rem;
        }

       
        .carousel {
            border-radius: 1.5rem;               
            overflow: hidden;                   
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12); 
        }
        .carousel-inner {
            border-radius: 1.5rem;
        }
        .carousel-item {
            transition: transform 0.8s ease-in-out;
        }
        .carousel-image-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(rgba(0,0,0,0), rgba(0,0,0,0.7));
            z-index: 1;
        }
        .carousel-caption {
            z-index: 2;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 1.5rem;
            padding: 2rem;
            backdrop-filter: blur(8px); 
            max-width: 600px;
            margin: 0 auto;
            bottom: 2rem;             
        }
        .carousel-indicators {
            margin-bottom: 1rem;
        }
        .carousel-indicators [data-bs-target] {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.5);
            border: 2px solid #fff;
            margin: 0 6px;
        }
        .carousel-indicators .active {
            background-color: #fff; 
        }
        .badge {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .carousel .btn-discover {
            margin-top: 1rem;
            background: rgba(255, 255, 255, 0.9);
            color: #2c3e50;
            border: none;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
        }
        .carousel .btn-discover:hover {
            background: #ffffff;
            transform: translateY(-2px);
        }

        .card {
            border: none;
            border-radius: 1.5rem;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08);
            background: #ffffff;
            overflow: hidden;
            height: 100%;           
            display: flex;
            flex-direction: column;  
        }
        .card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 16px 32px rgba(0, 0, 0, 0.12);
        }
        .card::after {
            
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: linear-gradient(120deg, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0) 100%);
            transition: opacity 0.4s;
            opacity: 0;
        }
        .card:hover::after {
            opacity: 1;
        }
        .card-img-top {
            height: 240px;            
            object-fit: cover;         
            border-radius: 1.5rem 1.5rem 0 0; 
            transition: transform 0.4s ease;
        }
        .card:hover .card-img-top {
            transform: scale(1.05);    
        }
        .card-body {
            padding: 1.75rem;
            background: #ffffff;
            flex-grow: 1;             
            display: flex;
            flex-direction: column;
        }
        .card-title {
            font-family: 'Playfair Display', serif;
            font-weight: 600;
            font-size: 1.1rem;
            color: #2c3e50;
            margin-bottom: 1rem;
            flex-grow: 1; 
        }
        .price {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: #2ecc71;
            font-size: 1.35rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .price::before {
            content: '€';
            font-size: 1rem;
            opacity: 0.8;
        }
        .btn-discover {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 0.875rem 1.5rem;
            margin-top: auto; 
            width: 100%;
            background-color: #ffffff;
        }
        .btn-discover span {
            flex-grow: 1;
            text-align: center;
        }
        .btn-discover i {
            margin-left: auto;
        }
        .btn-discover:hover i {
            transform: translateX(5px);
        }

        .products-slider {
            overflow-x: auto;        
            scroll-behavior: smooth;  
            white-space: nowrap;      
            padding: 1rem 0;          
            margin: 0 -1rem;          
            position: relative;
        }
        .products-row {
           
        }
        .product-card {
            display: inline-block;   
            width: 320px;            
            margin-right: 20px;      
            vertical-align: top;
        }
        .products-container {
            position: relative;
            padding: 0 2rem;         
        }
        .scroll-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 44px; height: 44px;
            border-radius: 50%;
            background: #ffffff;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .scroll-btn:hover {
            background: #f8f9fa;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
            transform: translateY(-50%) scale(1.05);
        }
        .scroll-btn.prev {
            left: 0;  
        }
        .scroll-btn.next {
            right: 0; 
        }
        .scroll-btn i {
            font-size: 1.25rem;
            color: #2c3e50;
        }
        .product-link {
            text-decoration: none; 
            color: inherit;        
            display: block;         
        }
        .product-link:hover {
            color: inherit;
        }

       
        .contact-section {
            background: #ffffff;
            padding: 4rem 0;
            margin-top: 4rem;
            border-radius: 2rem 2rem 0 0;
        }
        .contact-info {
            padding: 2rem;
            background: #f8f9fa;
            border-radius: 1rem;
            margin-bottom: 2rem;
        }
        .contact-info i {
            font-size: 1.5rem;
            color: #4a90e2;
            margin-right: 1rem;
        }
        .contact-info p {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }
        .map-container {
            height: 400px;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08);
        }
    </style>
</head>
<body>

<?php include 'header.php'; // Barre de navigation et logo ?>

<!-- SECTION HERO -->
<div class="hero-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <!-- Titre principal -->
                <h1 class="mb-4">Bienvenue sur Agora Francia</h1>
                <!-- Texte d’accroche -->
                <p class="lead mb-4">
                    Découvrez notre plateforme d'achat, de vente et d'enchères exclusive. 
                    L'endroit idéal pour dénicher des trésors uniques et réaliser des affaires exceptionnelles.
                </p>
                <?php if (!isset($_SESSION['user_id'])): // Si l’utilisateur n’est pas connecté ?>
                    <!-- Boutons Inscription / Connexion -->
                    <div class="mt-4">
                        <a href="compte.php?action=inscription" class="btn btn-primary me-3">Rejoignez-nous</a>
                        <!-- me-3 = margin-end 1rem, espace vers la droite -->
                        <a href="compte.php?action=login" class="btn btn-outline-primary">Connexion</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Messages flash : succès et erreur -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['success_message']); // Échappe tout caractère spécial pour éviter XSS ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); // Supprime le message pour qu’il ne réapparaisse pas ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>


    <!-- CARROUSEL : n’apparaît que si le tableau $carrousel n’est pas vide
         -->
    <?php if (!empty($carrousel)): ?>
        <h2>Les articles mis en avant</h2>
        <div id="myCarousel" class="carousel slide mb-5" data-bs-ride="carousel">
            <!-- Indicateurs : un bouton par diapositive -->
            <div class="carousel-indicators">
                <?php foreach ($carrousel as $i => $art): ?>
                    <button type="button"
                            data-bs-target="#myCarousel"
                            data-bs-slide-to="<?= $i ?>"
                            <?= ($i === 0) ? 'class="active" aria-current="true"' : '' ?>
                            aria-label="Slide <?= $i + 1 ?>">
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Wrapper des diapositives -->
            <div class="carousel-inner">
                <?php foreach ($carrousel as $i => $art): ?>
                    <!-- .carousel-item : chaque diapositive du carrousel -->
                    <div class="carousel-item <?= ($i === 0) ? 'active' : '' ?>">
                        <!-- L’attribut « active » sur la première diapositive pour l’afficher par défaut -->

                        <!-- Image de l’article ou placeholder si pas de photo définie -->
                        <img 
                            src="<?= htmlspecialchars($art['photo'] ?: 'images/placeholder.png'); ?>" 
                            class="d-block w-100" 
                            alt="<?= htmlspecialchars($art['titre']); ?>" 
                            style="height: 500px; object-fit: cover;"
                        >
                        <!-- 
                            d-block w-100 : classes Bootstrap pour forcer l’image à s’étendre sur 100 % du conteneur
                            object-fit: cover : recadre l’image de façon à ne pas déformer
                        -->

                        <!-- Légende superposée sur l’image (texte et bouton) -->
                        <div class="carousel-caption">
                            <!-- Badge « Nouveau » -->
                            <span class="badge bg-primary mb-2">Nouveau</span>
                            <!-- Titre de l’article -->
                            <h5><?= htmlspecialchars($art['titre']); ?></h5>
                            <!-- Extrait de description : strip_tags enlève le HTML, substr tronque à 100 caractères -->
                            <p><?= htmlspecialchars(substr(strip_tags($art['description']), 0, 100)); ?>…</p>
                            <!-- Lien vers la page détaillée de l’article -->
                            <a href="article.php?id=<?= $art['id']; ?>" class="btn btn-light btn-discover">
                                Découvrir <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Contrôles du carrousel : flèches gauche & droite -->
            <button
                class="carousel-control-prev"    
                type="button"
                data-bs-target="#myCarousel"     
                data-bs-slide="prev">            
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Précédent</span> <!-- Pour les lecteurs d’écran -->
            </button>
            <button
                class="carousel-control-next"    
                type="button"
                data-bs-target="#myCarousel"     
                data-bs-slide="next">            
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Suivant</span>
            </button>
        </div>
    <?php endif; ?>


    <!--SÉLECTION DU JOUR : 6 derniers articles disponibles-->
    <h2 class="selection-title mb-5">Notre Sélection du Jour</h2>
    <div class="products-container">
        <!-- Bouton pour défiler vers la gauche -->
        <button class="scroll-btn prev">
            <i class="bi bi-chevron-left"></i>
        </button>
        <!-- Bouton pour défiler vers la droite -->
        <button class="scroll-btn next">
            <i class="bi bi-chevron-right"></i>
        </button>

        <div class="products-slider">
            <!-- Conteneur horizontal scrollable des cartes -->
            <?php foreach ($selection as $i => $art): ?>  <!-- Boucle sur chaque article -->
                <div class="product-card">
                    <!-- Lien vers la page détaillée de l’article -->
                    <a href="article.php?id=<?= $art['id']; ?>" class="product-link">
                        <div class="card h-100">
                            <!-- Image du produit ou placeholder si pas de photo -->
                            <img 
                                src="<?= htmlspecialchars($art['photo'] ?: 'images/placeholder.png'); ?>" 
                                class="card-img-top" 
                                alt="<?= htmlspecialchars($art['titre']); ?>"
                            >
                            <div class="card-body">
                                <!-- Titre du produit -->
                                <h6 class="card-title"><?= htmlspecialchars($art['titre']); ?></h6>
                                <!-- Prix  -->
                                <div class="price"><?= number_format($art['prix_initial'], 2, ',', ' '); ?></div>
                                <!-- Bouton « Découvrir » -->
                                <div class="btn btn-outline-primary btn-discover">
                                    <span>Découvrir</span>
                                    <i class="bi bi-arrow-right"></i>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!--JAVASCRIPT pour gérer le défilement horizontal (scroll natif)-->
    <script>
      // On récupère le conteneur scrollable et les boutons
      const slider = document.querySelector('.products-slider');       // Conteneur des cartes
      const btnPrev = document.querySelector('.scroll-btn.prev');      // Bouton « Précédent »
      const btnNext = document.querySelector('.scroll-btn.next');      // Bouton « Suivant »
      const cardWidth = 340; // Largeur approximative d’une carte (idem CSS)

      // Met à jour l’état des boutons (activé / désactivé)
      function updateButtons() {
        // slider.scrollLeft = position horizontale du scroll ; 0 = tout à gauche
        btnPrev.disabled = (slider.scrollLeft === 0);
        // slider.clientWidth = largeur visible du conteneur
        // slider.scrollWidth = largeur totale du contenu (cartes incluses)
        btnNext.disabled = (slider.scrollLeft + slider.clientWidth >= slider.scrollWidth);
      }

      // Au clic sur le bouton « Suivant », on décale horizontalement vers la droite
      btnNext.addEventListener('click', () => {
        slider.scrollBy({ left: cardWidth, behavior: 'smooth' });
        setTimeout(updateButtons, 300);
      });

      // Au clic sur le bouton « Précédent », on déplace vers la gauche
      btnPrev.addEventListener('click', () => {
        slider.scrollBy({ left: -cardWidth, behavior: 'smooth' });
        setTimeout(updateButtons, 300);
      });

      // Initialisation : désactive le bouton « Précédent » si on est déjà tout à gauche
      updateButtons();
    </script>
</div>


<!--SECTION CONTACT : adresse, téléphone, email et Google Maps intégrée-->
<section class="contact-section">
    <div class="container">
        <h2 class="selection-title mb-5">Contactez-nous</h2>
        <div class="row">
            <!-- Colonne gauche : coordonnées -->
            <div class="col-md-6">
                <div class="contact-info">
                    <p><i class="bi bi-geo-alt"></i> 123 Avenue des Champs-Élysées, 75008 Paris</p>
                    <p><i class="bi bi-telephone"></i> +33 (0)1 23 45 67 89</p>
                    <p><i class="bi bi-envelope"></i> contact@agorafrancia.fr</p>
                    <p><i class="bi bi-clock"></i> Lun-Ven: 9h-18h</p>
                </div>
            </div>
            <!-- Colonne droite : carte Google Maps -->
            <div class="col-md-6">
                <div class="map-container">
                    <iframe 
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2624.2159385663765!2d2.2958076!3d48.8687751!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x47e66fc4f8140903%3A0x9b63ed9bdb7413c3!2sChamps-%C3%89lys%C3%A9es!5e0!3m2!1sfr!2sfr!4v1680000000000!5m2!1sfr!2sfr" 
                        width="100%" 
                        height="100%" 
                        style="border:0;" 
                        allowfullscreen 
                        loading="lazy" 
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include "footer.php"; // Pied de page commun (links, mentions légales, etc.) ?>

<!-- Script Bootstrap 5 pour activer les composants JS (carrousel, modals, etc.) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
