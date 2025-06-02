-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : lun. 02 juin 2025 à 10:53
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
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `achat`
--

INSERT INTO `achat` (`id`, `acheteur_id`, `vendeur_id`, `article_id`, `transaction_id`, `prix_achat`, `mode_achat`, `date_achat`, `statut`) VALUES
(32, 20, 17, 75, 41, 68.00, 'negociation', '2025-05-29 14:51:54', 'confirme');

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
) ENGINE=InnoDB AUTO_INCREMENT=79 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `article`
--

INSERT INTO `article` (`id`, `titre`, `description`, `qualite`, `defaut`, `photo`, `video`, `prix_initial`, `categorie_id`, `vendeur_id`, `type_vente`, `statut`, `date_creation`, `date_debut_enchere`, `date_fin_enchere`, `prix_actuel`, `type_marchandise`) VALUES
(72, 'Vase dorée', 'En or pure', NULL, NULL, 'images/art_6838556419434.png', NULL, 500.00, 6, 17, 'immediat', 'disponible', '2025-05-29 14:39:00', NULL, NULL, NULL, 'rare'),
(73, 'Vase émeraude', 'en émeraude vert', NULL, NULL, 'images/art_6838559094f72.png', NULL, 300.00, 6, 17, 'immediat', 'disponible', '2025-05-29 14:39:44', NULL, NULL, NULL, 'haut_de_gamme'),
(74, 'Statue de bronze', 'Réel en bronze 2m10', NULL, NULL, 'images/art_683855b7efd40.png', NULL, 1200.00, 2, 17, 'immediat', 'disponible', '2025-05-29 14:40:23', NULL, NULL, NULL, 'regulier'),
(75, 'Vase platré', 'Excellente état', NULL, NULL, 'images/art_6838569ad7e72.png', NULL, 180.00, 10, 17, 'negociation', 'vendu', '2025-05-29 14:44:10', NULL, NULL, NULL, 'rare'),
(76, 'Statuette Dauphine Atlante', 'De atlantide', NULL, NULL, 'images/art_683856ee10557.png', NULL, 547.00, 1, 17, 'enchere', 'disponible', '2025-05-29 14:45:34', '2025-05-15 15:00:00', '2025-05-30 15:00:00', 547, 'rare'),
(77, 'Vase de Souason', 'De souasse', NULL, NULL, 'images/art_6838571982f64.png', NULL, 678.00, 7, 17, 'negociation', 'disponible', '2025-05-29 14:46:17', NULL, NULL, NULL, 'haut_de_gamme'),
(78, 'Sonnette gauloise', 'En excellente état', NULL, NULL, 'images/art_68385760bc521.png', NULL, 320.00, 1, 17, 'enchere', 'vendu', '2025-05-29 14:47:28', '2020-11-11 10:00:00', '2025-05-29 15:00:00', 321, 'rare');

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
(8, 72, 'immediat'),
(9, 72, 'immediat');

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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `categorie`
--

INSERT INTO `categorie` (`id`, `nom`) VALUES
(1, 'Objets d\'art'),
(2, 'Accessoires VIP'),
(4, 'Livres'),
(6, 'Décoration'),
(7, 'Textile'),
(10, 'Vase');

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
  `etat` enum('en_cours','gagnant','perdu','finalise') COLLATE utf8mb4_general_ci DEFAULT 'en_cours',
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`),
  KEY `acheteur_id` (`acheteur_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `enchere`
--

INSERT INTO `enchere` (`id`, `article_id`, `acheteur_id`, `date_offre`, `prix_max`, `date_enchere`, `etat`) VALUES
(20, 78, 20, '2025-05-29 14:50:02', 500, '2025-05-29 15:00:00', 'gagnant');

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
  `tour` int NOT NULL DEFAULT '1',
  `offre_acheteur` decimal(10,2) NOT NULL,
  `contre_offre_vendeur` decimal(10,2) DEFAULT NULL,
  `etat` enum('en_cours','accepte','refuse','expire') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'en_cours',
  `date_action` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_expiration` datetime DEFAULT NULL,
  `commentaire_acheteur` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `commentaire_vendeur` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`),
  KEY `acheteur_id` (`acheteur_id`),
  KEY `vendeur_id` (`vendeur_id`),
  KEY `etat` (`etat`),
  KEY `date_action` (`date_action`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `negociation`
--

INSERT INTO `negociation` (`id`, `article_id`, `acheteur_id`, `vendeur_id`, `tour`, `offre_acheteur`, `contre_offre_vendeur`, `etat`, `date_action`, `date_expiration`, `commentaire_acheteur`, `commentaire_vendeur`) VALUES
(30, 75, 20, 17, 1, 20.00, NULL, 'en_cours', '2025-05-29 14:50:21', NULL, NULL, NULL),
(31, 75, 20, 17, 2, 68.00, NULL, '', '2025-05-29 14:50:37', NULL, NULL, NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=123 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `notification`
--

INSERT INTO `notification` (`id`, `user_id`, `contenu`, `date_creation`, `lu`, `article_id`) VALUES
(107, 17, 'Nouvelle enchère automatique de Benjamin Assouline sur l\'article : Sonnette gauloise (enchère max : 350,00 €)', '2025-05-29 14:49:30', 1, 78),
(108, 17, 'Nouvelle contre-offre de 68,00€ sur votre article Vase platré', '2025-05-29 14:50:37', 1, 75),
(109, 20, 'Votre offre de 68,00€ pour l\'article Vase platré a été acceptée', '2025-05-29 14:51:25', 1, 75),
(110, 17, 'Votre offre pour l\'article \'Vase platré\' a été acceptée pour 68.00€', '2025-05-29 14:51:54', 1, 75),
(111, 17, 'L\'enchère pour votre article \'Statuette Dauphine Atlante\' s\'est terminée sans aucune offre.', '2025-05-31 12:28:02', 1, 76),
(112, 20, 'Félicitations ! Vous avez gagné l\'enchère pour l\'article \'Sonnette gauloise\' pour 321.00€. Vous pouvez maintenant finaliser votre achat.', '2025-05-31 12:28:02', 1, 78),
(113, 17, 'Votre article \'Sonnette gauloise\' a été vendu aux enchères pour 321.00€.', '2025-05-31 12:28:02', 1, 78),
(114, 17, 'L\'enchère pour votre article \'Statuette Dauphine Atlante\' s\'est terminée sans aucune offre.', '2025-05-31 12:28:52', 1, 76),
(115, 17, 'L\'enchère pour votre article \'Statuette Dauphine Atlante\' s\'est terminée sans aucune offre.', '2025-05-31 12:29:21', 1, 76),
(116, 17, 'L\'enchère pour votre article \'Statuette Dauphine Atlante\' s\'est terminée sans aucune offre.', '2025-05-31 12:30:38', 1, 76),
(117, 17, 'L\'enchère pour votre article \'Statuette Dauphine Atlante\' s\'est terminée sans aucune offre.', '2025-05-31 12:31:27', 1, 76),
(118, 17, 'L\'enchère pour votre article \'Statuette Dauphine Atlante\' s\'est terminée sans aucune offre.', '2025-05-31 12:31:49', 0, 76),
(119, 17, 'L\'enchère pour votre article \'Statuette Dauphine Atlante\' s\'est terminée sans aucune offre.', '2025-05-31 13:22:23', 0, 76),
(120, 17, 'L\'enchère pour votre article \'Statuette Dauphine Atlante\' s\'est terminée sans aucune offre.', '2025-06-01 20:53:56', 0, 76),
(121, 17, 'L\'enchère pour votre article \'Statuette Dauphine Atlante\' s\'est terminée sans aucune offre.', '2025-06-01 20:54:42', 0, 76),
(122, 17, 'L\'enchère pour votre article \'Statuette Dauphine Atlante\' s\'est terminée sans aucune offre.', '2025-06-01 20:54:45', 0, 76);

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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `panier`
--

INSERT INTO `panier` (`id`, `acheteur_id`, `date_creation`) VALUES
(8, 20, '2025-05-29 14:49:40'),
(9, 4, '2025-06-01 20:54:07');

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
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `transaction`
--

INSERT INTO `transaction` (`id`, `acheteur_id`, `article_id`, `montant`, `mode_paiement`, `date_paiement`) VALUES
(41, 20, 75, 68.00, 'paypal', '2025-05-29 14:51:54');

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
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `utilisateur`
--

INSERT INTO `utilisateur` (`id`, `nom`, `prenom`, `email`, `mot_de_passe`, `role`, `adresse_ligne1`, `adresse_ligne2`, `ville`, `code_postal`, `pays`, `telephone`, `photo`, `image_fond`) VALUES
(1, 'lucas', 'leblond', 'lucassurf0907@gmail.com', '$2y$10$o22r37Sbxk/kE7K91QpDlueSP52TV50ETHtQHh20BcNhk/xuhPsty', 'vendeur', '4 route de dampierre', '', 'Lévis saint nom', '78320', 'france', '0695384996', NULL, NULL),
(3, 'admin', 'admin', 'admin@gmail.fr', 'admin1234', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'Admin', 'Admin', 'admin@agora.fr', '$2y$10$NhRDxNC/YVmR45iklfIU8.Zkj/Ky5UptSJOQ2u1tpsdurSDK5lTAu', 'admin', '', '', '', '', '', '', 'images/profile_683ada1de40a6.png', NULL),
(17, 'Assouline', 'Benjamin', 'benassou423@gmail.com', '$2y$10$ZUzAWMuFa8kCiLrOxFnZseGuv..rQAnK6yutdCNEjPM8qITG7j87q', 'vendeur', '3bis rue vergniaud', '3e etage; interphone assouline', 'LEVALLOIS PERRET', '92300', 'France', '0768662789', 'images/profile_6838493dab804.png', NULL),
(19, 'Assouline', 'Benjamin', 'assoulineben@gmail.com', '$2y$10$HVCGpPAmxuevYoWWI1eNSOqo6CzGC17bUPcHi0M4WN6BlaH.9eVR.', 'acheteur', '3bis rue vergniaud', '3e etage; interphone assouline', 'LEVALLOIS PERRET', '92300', 'France', '0768662789', NULL, NULL),
(20, 'Assouline', 'Benjamin', 'letrou@gmail.com', '$2y$10$vf1cERQ4KyQ4Su0JRRZNue4lcOJaIPNLwDX0Y4e04gqnlDrkP52cq', 'acheteur', '3bis rue vergniaud', '3e etage; interphone assouline', 'LEVALLOIS PERRET', '92300', 'France', '0768662789', NULL, NULL);

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
