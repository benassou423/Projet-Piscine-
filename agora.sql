-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mar. 27 mai 2025 à 18:06
-- Version du serveur : 9.1.0
-- Version de PHP : 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `agora`
--

-- --------------------------------------------------------

--
-- Structure de la table `achat`
--

DROP TABLE IF EXISTS `achat`;
CREATE TABLE IF NOT EXISTS `achat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `acheteur_id` int NOT NULL,
  `vendeur_id` int NOT NULL,
  `article_id` int NOT NULL,
  `transaction_id` int DEFAULT NULL,
  `prix_achat` decimal(10,2) NOT NULL,
  `mode_achat` enum('achat','negociation','enchere') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'achat',
  `date_achat` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `statut` enum('en_attente','confirme','annule') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'confirme',
  PRIMARY KEY (`id`),
  KEY `acheteur_id` (`acheteur_id`),
  KEY `vendeur_id` (`vendeur_id`),
  KEY `article_id` (`article_id`),
  KEY `transaction_id` (`transaction_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `achat`
--

INSERT INTO `achat` (`id`, `acheteur_id`, `vendeur_id`, `article_id`, `transaction_id`, `prix_achat`, `mode_achat`, `date_achat`, `statut`) VALUES
(1, 17, 4, 27, 10, 1.00, 'achat', '2025-05-27 19:58:59', 'confirme');

-- --------------------------------------------------------

--
-- Structure de la table `alerte`
--

DROP TABLE IF EXISTS `alerte`;
CREATE TABLE IF NOT EXISTS `alerte` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `categorie_id` int DEFAULT NULL,
  `type_vente` varchar(15) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mots_cles` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `prix_min` float DEFAULT NULL,
  `prix_max` float DEFAULT NULL,
  `date_creation` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `categorie_id` (`categorie_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `alerte`
--

INSERT INTO `alerte` (`id`, `user_id`, `categorie_id`, `type_vente`, `mots_cles`, `prix_min`, `prix_max`, `date_creation`) VALUES
(1, 4, 2, 'immediat', '', 0.01, 0, '2025-05-26 23:25:10'),
(2, 4, 2, 'negociation', '', NULL, NULL, '2025-05-26 23:25:26'),
(6, 4, 2, 'immediat', '', 0, NULL, '2025-05-27 13:25:51');

-- --------------------------------------------------------

--
-- Structure de la table `article`
--

DROP TABLE IF EXISTS `article`;
CREATE TABLE IF NOT EXISTS `article` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `qualite` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `defaut` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `photo` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `video` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `prix_initial` decimal(10,2) NOT NULL,
  `categorie_id` int NOT NULL,
  `vendeur_id` int NOT NULL,
  `type_vente` enum('immediat','negociation','enchere') COLLATE utf8mb4_general_ci NOT NULL,
  `statut` enum('disponible','vendu','retire') COLLATE utf8mb4_general_ci DEFAULT 'disponible',
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_debut_enchere` datetime DEFAULT NULL,
  `date_fin_enchere` datetime DEFAULT NULL,
  `prix_actuel` float DEFAULT NULL,
  `type_marchandise` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'regulier',
  PRIMARY KEY (`id`),
  KEY `categorie_id` (`categorie_id`),
  KEY `vendeur_id` (`vendeur_id`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `article`
--

INSERT INTO `article` (`id`, `titre`, `description`, `qualite`, `defaut`, `photo`, `video`, `prix_initial`, `categorie_id`, `vendeur_id`, `type_vente`, `statut`, `date_creation`, `date_debut_enchere`, `date_fin_enchere`, `prix_actuel`, `type_marchandise`) VALUES
(7, 'azd', 'azd', NULL, NULL, 'images/art_6834baf1840d0.png', NULL, 21.00, 2, 4, 'immediat', 'vendu', '2025-05-26 21:03:13', NULL, NULL, NULL, 'regulier'),
(15, 'v', 'df', NULL, NULL, 'images/art_6834c43ec419f.png', NULL, 0.01, 8, 4, 'enchere', 'disponible', '2025-05-26 21:42:54', '2025-05-26 21:42:00', '2025-05-26 21:42:00', 0.01, 'regulier'),
(19, 'Z', 'e', NULL, NULL, 'images/art_6834d38304e32.png', NULL, 12.00, 5, 4, 'negociation', 'vendu', '2025-05-26 22:48:03', NULL, NULL, NULL, 'regulier'),
(21, 'r\"', 'rz\"', NULL, NULL, '', NULL, 98.00, 6, 4, 'immediat', 'disponible', '2025-05-26 23:10:58', NULL, NULL, NULL, 'regulier'),
(24, 'les misérables', 'zf', NULL, NULL, '', NULL, 0.01, 2, 4, 'immediat', 'vendu', '2025-05-26 23:26:20', NULL, NULL, NULL, 'rare'),
(25, 'fez', 'rza', NULL, NULL, '', NULL, 0.00, 2, 4, 'immediat', 'vendu', '2025-05-26 23:27:17', NULL, NULL, NULL, 'rare'),
(27, 'aée', 'eé', NULL, NULL, 'images/art_6834df4691feb.png', NULL, 1.00, 5, 4, 'immediat', 'vendu', '2025-05-26 23:38:14', NULL, NULL, NULL, 'haut_de_gamme'),
(30, 'un bg', 'il est mignon', NULL, NULL, 'images/art_68359372cb96a.png', NULL, 1000.00, 6, 4, 'immediat', 'vendu', '2025-05-27 12:26:58', NULL, NULL, NULL, 'rare'),
(38, 'test 2 supprimer', 'blabal', NULL, NULL, '', NULL, 25.00, 2, 4, 'enchere', 'disponible', '2025-05-27 18:23:00', '2000-07-15 15:00:00', '2222-02-16 23:55:00', 25, 'rare'),
(39, 'un bg', 'beau', NULL, NULL, 'images/art_6835edcce30e7.png', NULL, 250.00, 4, 4, 'negociation', 'disponible', '2025-05-27 18:52:28', NULL, NULL, NULL, 'rare');

-- --------------------------------------------------------

--
-- Structure de la table `articlepanier`
--

DROP TABLE IF EXISTS `articlepanier`;
CREATE TABLE IF NOT EXISTS `articlepanier` (
  `panier_id` int NOT NULL,
  `article_id` int NOT NULL,
  `mode_achat` enum('immediat','negociation','enchere') COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`panier_id`,`article_id`),
  KEY `article_id` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `articlepanier`
--

INSERT INTO `articlepanier` (`panier_id`, `article_id`, `mode_achat`) VALUES
(6, 21, 'immediat');

-- --------------------------------------------------------

--
-- Structure de la table `carte`
--

DROP TABLE IF EXISTS `carte`;
CREATE TABLE IF NOT EXISTS `carte` (
  `id` int NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int NOT NULL,
  `type` enum('Visa','MasterCard','Amex','PayPal') COLLATE utf8mb4_general_ci NOT NULL,
  `numero` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `nom_affiche` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `expiration` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `cvv` varchar(4) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `utilisateur_id` (`utilisateur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `categorie`
--

DROP TABLE IF EXISTS `categorie`;
CREATE TABLE IF NOT EXISTS `categorie` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `categorie`
--

INSERT INTO `categorie` (`id`, `nom`) VALUES
(1, 'Objets d\'art'),
(2, 'Accessoires VIP'),
(3, 'Matériel scolaire'),
(4, 'Livres'),
(5, 'Informatique'),
(6, 'Décoration'),
(7, 'Textile'),
(8, 'Instruments de musique');

-- --------------------------------------------------------

--
-- Structure de la table `enchere`
--

DROP TABLE IF EXISTS `enchere`;
CREATE TABLE IF NOT EXISTS `enchere` (
  `id` int NOT NULL AUTO_INCREMENT,
  `article_id` int NOT NULL,
  `acheteur_id` int NOT NULL,
  `date_offre` datetime DEFAULT CURRENT_TIMESTAMP,
  `prix_max` float NOT NULL,
  `date_enchere` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`),
  KEY `acheteur_id` (`acheteur_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `methodepaiement`
--

DROP TABLE IF EXISTS `methodepaiement`;
CREATE TABLE IF NOT EXISTS `methodepaiement` (
  `id` int NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int NOT NULL,
  `type_methode` enum('carte','virement','apple_pay','paypal') NOT NULL,
  `nom_affichage` varchar(100) NOT NULL,
  `details_cryptes` text,
  `est_defaut` tinyint(1) DEFAULT '0',
  `date_ajout` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_expiration` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `utilisateur_id` (`utilisateur_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `negociation`
--

DROP TABLE IF EXISTS `negociation`;
CREATE TABLE IF NOT EXISTS `negociation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `article_id` int NOT NULL,
  `acheteur_id` int NOT NULL,
  `vendeur_id` int NOT NULL,
  `tour` int NOT NULL,
  `offre_acheteur` float DEFAULT NULL,
  `contre_offre_vendeur` float DEFAULT NULL,
  `etat` enum('en cours','accepte','refuse','expire') COLLATE utf8mb4_general_ci DEFAULT 'en cours',
  `date_action` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`),
  KEY `acheteur_id` (`acheteur_id`),
  KEY `vendeur_id` (`vendeur_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `negociation`
--

INSERT INTO `negociation` (`id`, `article_id`, `acheteur_id`, `vendeur_id`, `tour`, `offre_acheteur`, `contre_offre_vendeur`, `etat`, `date_action`) VALUES
(11, 39, 17, 4, 1, 0.3, NULL, 'en cours', '2025-05-27 19:34:24');

-- --------------------------------------------------------

--
-- Structure de la table `notification`
--

DROP TABLE IF EXISTS `notification`;
CREATE TABLE IF NOT EXISTS `notification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `contenu` text COLLATE utf8mb4_general_ci NOT NULL,
  `date_creation` datetime NOT NULL,
  `lu` tinyint(1) DEFAULT '0',
  `article_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `notification`
--

INSERT INTO `notification` (`id`, `user_id`, `contenu`, `date_creation`, `lu`, `article_id`) VALUES
(3, 4, 'Le vendeur a fait une contre-offre (1 222,00 €) sur l\'article « 12 ». Consultez votre historique de négociation !', '2025-05-26 22:41:03', 1, NULL),
(5, 4, 'Le vendeur a fait une contre-offre (20 000,00 €) sur l\'article « 12 ». Consultez votre historique de négociation !', '2025-05-26 22:42:53', 1, NULL),
(7, 4, 'Votre offre sur l\'article « 12 » a été refusée par le vendeur.', '2025-05-26 22:43:44', 1, NULL),
(8, 4, 'Votre offre sur l\'article « 12 » a été acceptée par le vendeur !', '2025-05-26 22:43:44', 1, NULL),
(12, 4, 'Le vendeur a fait une contre-offre (0,00 €) sur l\'article « fzfze ». Consultez votre historique de négociation !', '2025-05-26 23:04:48', 1, 20),
(13, 4, 'Le vendeur a fait une contre-offre (123,00 €) sur l\'article « fzfze ». Consultez votre historique de négociation !', '2025-05-26 23:04:52', 1, 20),
(15, 4, 'Un nouvel article correspondant à votre alerte est disponible : \"les misérables\"', '2025-05-26 21:26:20', 1, 24),
(30, 4, 'Un nouvel article correspondant à votre alerte est disponible : <a href=\'article.php?id=32\'>un bg</a>', '2025-05-27 11:27:07', 1, 32),
(32, 4, 'Un nouvel article correspondant à votre alerte est disponible : <a href=\'article.php?id=32\'>un bg</a>', '2025-05-27 11:27:07', 1, 32),
(33, 4, 'Un nouvel article correspondant à votre alerte est disponible : <a href=\'article.php?id=33\'>rox en l\\\'air</a>', '2025-05-27 12:37:03', 1, 33);

-- --------------------------------------------------------

--
-- Structure de la table `panier`
--

DROP TABLE IF EXISTS `panier`;
CREATE TABLE IF NOT EXISTS `panier` (
  `id` int NOT NULL AUTO_INCREMENT,
  `acheteur_id` int NOT NULL,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `acheteur_id` (`acheteur_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `panier`
--

INSERT INTO `panier` (`id`, `acheteur_id`, `date_creation`) VALUES
(1, 4, '2025-05-26 21:02:56'),
(6, 17, '2025-05-27 17:04:48');

-- --------------------------------------------------------

--
-- Structure de la table `transaction`
--

DROP TABLE IF EXISTS `transaction`;
CREATE TABLE IF NOT EXISTS `transaction` (
  `id` int NOT NULL AUTO_INCREMENT,
  `acheteur_id` int NOT NULL,
  `article_id` int NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `mode_paiement` enum('carte','paypal','cheque-cadeau') COLLATE utf8mb4_general_ci NOT NULL,
  `date_paiement` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `acheteur_id` (`acheteur_id`),
  KEY `article_id` (`article_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `transaction`
--

INSERT INTO `transaction` (`id`, `acheteur_id`, `article_id`, `montant`, `mode_paiement`, `date_paiement`) VALUES
(9, 17, 27, 1.00, 'carte', '2025-05-27 19:52:50'),
(10, 17, 27, 1.00, 'carte', '2025-05-27 19:58:59');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

DROP TABLE IF EXISTS `utilisateur`;
CREATE TABLE IF NOT EXISTS `utilisateur` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `mot_de_passe` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('acheteur','vendeur','admin') COLLATE utf8mb4_general_ci NOT NULL,
  `adresse_ligne1` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `adresse_ligne2` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ville` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `code_postal` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pays` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telephone` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `photo` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `image_fond` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `utilisateur`
--

INSERT INTO `utilisateur` (`id`, `nom`, `prenom`, `email`, `mot_de_passe`, `role`, `adresse_ligne1`, `adresse_ligne2`, `ville`, `code_postal`, `pays`, `telephone`, `photo`, `image_fond`) VALUES
(1, 'lucas', 'leblond', 'lucassurf0907@gmail.com', '$2y$10$o22r37Sbxk/kE7K91QpDlueSP52TV50ETHtQHh20BcNhk/xuhPsty', 'acheteur', '4 route de dampierre', '', 'Lévis saint nom', '78320', 'france', '0695384996', NULL, NULL),
(3, 'admin', 'admin', 'admin@gmail.fr', 'admin1234', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'Admin', 'Admin', 'admin@agora.fr', '$2y$10$NhRDxNC/YVmR45iklfIU8.Zkj/Ky5UptSJOQ2u1tpsdurSDK5lTAu', 'admin', '', '', '', '', '', '', 'images/profile_6835f2b4cd307.png', NULL),
(17, 'Assouline', 'Benjamin', 'benassou423@gmail.com', '$2y$10$ZUzAWMuFa8kCiLrOxFnZseGuv..rQAnK6yutdCNEjPM8qITG7j87q', 'vendeur', '3bis rue vergniaud', '3e etage; interphone assouline', 'LEVALLOIS PERRET', '92300', 'France', '0768662789', 'images/profile_6835fe6fbb323.png', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `vente`
--

DROP TABLE IF EXISTS `vente`;
CREATE TABLE IF NOT EXISTS `vente` (
  `id` int NOT NULL AUTO_INCREMENT,
  `article_id` int NOT NULL,
  `type` enum('immediat','negociation','enchere') COLLATE utf8mb4_general_ci NOT NULL,
  `date_debut` datetime DEFAULT NULL,
  `date_fin` datetime DEFAULT NULL,
  `prix_min` decimal(10,2) DEFAULT NULL,
  `prix_final` decimal(10,2) DEFAULT NULL,
  `gagnant_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `article_id` (`article_id`),
  KEY `gagnant_id` (`gagnant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `achat`
--
ALTER TABLE `achat`
  ADD CONSTRAINT `achat_ibfk_1` FOREIGN KEY (`acheteur_id`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `achat_ibfk_2` FOREIGN KEY (`vendeur_id`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `achat_ibfk_3` FOREIGN KEY (`article_id`) REFERENCES `article` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `achat_ibfk_4` FOREIGN KEY (`transaction_id`) REFERENCES `transaction` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `alerte`
--
ALTER TABLE `alerte`
  ADD CONSTRAINT `alerte_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `utilisateur` (`id`),
  ADD CONSTRAINT `alerte_ibfk_2` FOREIGN KEY (`categorie_id`) REFERENCES `categorie` (`id`);

--
-- Contraintes pour la table `article`
--
ALTER TABLE `article`
  ADD CONSTRAINT `article_ibfk_1` FOREIGN KEY (`categorie_id`) REFERENCES `categorie` (`id`),
  ADD CONSTRAINT `article_ibfk_2` FOREIGN KEY (`vendeur_id`) REFERENCES `utilisateur` (`id`);

--
-- Contraintes pour la table `articlepanier`
--
ALTER TABLE `articlepanier`
  ADD CONSTRAINT `articlepanier_ibfk_1` FOREIGN KEY (`panier_id`) REFERENCES `panier` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `articlepanier_ibfk_2` FOREIGN KEY (`article_id`) REFERENCES `article` (`id`);

--
-- Contraintes pour la table `carte`
--
ALTER TABLE `carte`
  ADD CONSTRAINT `carte_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur` (`id`);

--
-- Contraintes pour la table `enchere`
--
ALTER TABLE `enchere`
  ADD CONSTRAINT `enchere_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `article` (`id`),
  ADD CONSTRAINT `enchere_ibfk_2` FOREIGN KEY (`acheteur_id`) REFERENCES `utilisateur` (`id`);

--
-- Contraintes pour la table `negociation`
--
ALTER TABLE `negociation`
  ADD CONSTRAINT `negociation_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `article` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `negociation_ibfk_2` FOREIGN KEY (`acheteur_id`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `negociation_ibfk_3` FOREIGN KEY (`vendeur_id`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `notification`
--
ALTER TABLE `notification`
  ADD CONSTRAINT `notification_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `panier`
--
ALTER TABLE `panier`
  ADD CONSTRAINT `panier_ibfk_1` FOREIGN KEY (`acheteur_id`) REFERENCES `utilisateur` (`id`);

--
-- Contraintes pour la table `transaction`
--
ALTER TABLE `transaction`
  ADD CONSTRAINT `transaction_ibfk_1` FOREIGN KEY (`acheteur_id`) REFERENCES `utilisateur` (`id`),
  ADD CONSTRAINT `transaction_ibfk_2` FOREIGN KEY (`article_id`) REFERENCES `article` (`id`);

--
-- Contraintes pour la table `vente`
--
ALTER TABLE `vente`
  ADD CONSTRAINT `vente_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `article` (`id`),
  ADD CONSTRAINT `vente_ibfk_2` FOREIGN KEY (`gagnant_id`) REFERENCES `utilisateur` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
