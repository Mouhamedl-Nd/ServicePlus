-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : jeu. 09 juil. 2026 à 00:08
-- Version du serveur : 9.1.0
-- Version de PHP : 8.4.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `servicesplus`
--

-- --------------------------------------------------------

--
-- Structure de la table `annonces`
--

DROP TABLE IF EXISTS `annonces`;
CREATE TABLE IF NOT EXISTS `annonces` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `categorie_id` int NOT NULL,
  `titre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ville` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `budget` decimal(10,2) DEFAULT NULL,
  `date_souhaitee` datetime NOT NULL,
  `urgence` tinyint(1) NOT NULL DEFAULT '0',
  `statut` enum('ouverte','en_discussion','attribuee','terminee','annulee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouverte',
  `prestataire_choisi_id` int DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `categorie_id` (`categorie_id`),
  KEY `prestataire_choisi_id` (`prestataire_choisi_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `annonces`
--

INSERT INTO `annonces` (`id`, `client_id`, `categorie_id`, `titre`, `description`, `adresse`, `ville`, `budget`, `date_souhaitee`, `urgence`, `statut`, `prestataire_choisi_id`, `date_creation`) VALUES
(3, 2, 8, 'Villa à vendre à Dakar - Parcelles Assainies', 'GVJKJVCVBJKLKN', 'parcelle Assénie U14 villa N°145', 'Dakar', 32455543.00, '2026-07-08 00:00:00', 1, 'ouverte', NULL, '2026-07-08 02:58:01');

-- --------------------------------------------------------

--
-- Structure de la table `avis`
--

DROP TABLE IF EXISTS `avis`;
CREATE TABLE IF NOT EXISTS `avis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reservation_id` int NOT NULL,
  `client_id` int NOT NULL,
  `prestataire_id` int NOT NULL,
  `note` tinyint NOT NULL,
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reservation_id` (`reservation_id`),
  KEY `client_id` (`client_id`),
  KEY `prestataire_id` (`prestataire_id`)
) ;

-- --------------------------------------------------------

--
-- Structure de la table `candidatures`
--

DROP TABLE IF EXISTS `candidatures`;
CREATE TABLE IF NOT EXISTS `candidatures` (
  `id` int NOT NULL AUTO_INCREMENT,
  `annonce_id` int NOT NULL,
  `prestataire_id` int NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `tarif_propose` decimal(10,2) DEFAULT NULL,
  `statut` enum('en_attente','acceptee','refusee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_candidature` (`annonce_id`,`prestataire_id`),
  KEY `prestataire_id` (`prestataire_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icone` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom` (`nom`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`id`, `nom`, `icone`) VALUES
(1, 'Plomberie', '🔧'),
(2, 'Électricité', '⚡'),
(3, 'Ménage', '🧹'),
(4, 'Peinture', '🎨'),
(5, 'Jardinage', '🌿'),
(6, 'Climatisation', '❄️'),
(7, 'Serrurerie', '🚪'),
(8, 'Maçonnerie', '🏗️'),
(9, 'Salle de bain', '🛁'),
(10, 'Menuiserie', '🪚');

-- --------------------------------------------------------

--
-- Structure de la table `faq`
--

DROP TABLE IF EXISTS `faq`;
CREATE TABLE IF NOT EXISTS `faq` (
  `id` int NOT NULL AUTO_INCREMENT,
  `categorie` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `question` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reponse` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `tags` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `populaire` tinyint(1) NOT NULL DEFAULT '0',
  `votes_utile` int NOT NULL DEFAULT '0',
  `votes_inutile` int NOT NULL DEFAULT '0',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `faq`
--

INSERT INTO `faq` (`id`, `categorie`, `question`, `reponse`, `tags`, `populaire`, `votes_utile`, `votes_inutile`, `date_creation`) VALUES
(1, 'compte', 'Comment créer un compte sur ServicesPlus ?', 'Cliquez sur \'S\'inscrire\' en haut à droite de la page. Choisissez votre type de compte (Client ou Prestataire), renseignez vos informations personnelles (nom, email, téléphone, ville), créez un mot de passe sécurisé, puis acceptez les conditions d\'utilisation. Votre compte est créé immédiatement.', 'Inscription,Compte', 1, 0, 0, '2026-07-08 02:51:14'),
(2, 'compte', 'Comment modifier mes informations personnelles ?', 'Connectez-vous à votre compte, puis rendez-vous dans \'Mon Profil\' via le menu en haut à droite. Vous pouvez y modifier votre prénom, nom, email, téléphone et ville. N\'oubliez pas de cliquer sur \'Sauvegarder\'.', 'Profil,Paramètres', 0, 0, 0, '2026-07-08 02:51:14'),
(3, 'compte', 'J\'ai oublié mon mot de passe, que faire ?', 'Sur la page de connexion, cliquez sur \'Mot de passe oublié ?\' ou contactez directement l\'administrateur qui pourra réinitialiser votre accès.', 'Mot de passe,Sécurité', 1, 0, 0, '2026-07-08 02:51:14'),
(4, 'compte', 'Comment supprimer mon compte ?', 'Contactez notre support via la page Contact en précisant votre demande de suppression. Toutes vos données, réservations et historiques seront définitivement effacés.', 'Compte,Données', 0, 0, 0, '2026-07-08 02:51:14'),
(5, 'compte', 'Puis-je avoir plusieurs comptes ?', 'Non, vous ne pouvez avoir qu\'un seul compte par adresse email. Un compte est soit client, soit prestataire.', 'Compte,Règles', 0, 0, 0, '2026-07-08 02:51:14'),
(6, 'recherche', 'Comment trouver un prestataire près de moi ?', 'Rendez-vous sur la page \'Recherche\', entrez votre ville et sélectionnez le service dont vous avez besoin. Vous pouvez affiner les résultats avec les filtres : catégorie, note minimum et tarif maximum.', 'Recherche,Localisation', 1, 0, 0, '2026-07-08 02:51:14'),
(7, 'recherche', 'Comment les prestataires sont-ils vérifiés ?', 'Chaque prestataire doit fournir une pièce d\'identité valide lors de son inscription. Notre équipe admin vérifie manuellement chaque dossier avant de valider le compte.', 'Vérification,Sécurité', 0, 0, 0, '2026-07-08 02:51:14'),
(8, 'recherche', 'Que signifie le statut \'Validé\' ?', 'Un prestataire \'Validé\' a été vérifié et approuvé par notre équipe admin après examen de son dossier. C\'est une garantie de sérieux.', 'Badge,Certification', 1, 0, 0, '2026-07-08 02:51:14'),
(9, 'reservation', 'Comment faire une réservation ?', '1) Trouvez votre prestataire via la Recherche. 2) Consultez son profil. 3) Cliquez sur \'Réserver\'. 4) Choisissez la date, l\'heure et la durée. 5) Renseignez votre adresse. 6) Confirmez. Vous recevrez une notification de confirmation.', 'Réservation,Guide', 1, 0, 0, '2026-07-08 02:51:14'),
(10, 'reservation', 'Puis-je annuler ou modifier une réservation ?', 'Oui, vous pouvez annuler une réservation depuis votre Dashboard dans \'Mes réservations\', tant qu\'elle n\'est pas encore terminée.', 'Annulation,Modification', 0, 0, 0, '2026-07-08 02:51:14'),
(11, 'reservation', 'Que faire si le prestataire ne se présente pas ?', 'Contactez notre support via la page Contact. Nous intervenons pour vous trouver un remplaçant ou étudier un remboursement.', 'Assistance,Remboursement', 1, 0, 0, '2026-07-08 02:51:14'),
(12, 'paiement', 'Quels modes de paiement sont acceptés ?', 'ServicesPlus accepte : Orange Money, Wave, la carte bancaire et le paiement en espèces directement au prestataire.', 'Paiement,Sécurité', 1, 0, 0, '2026-07-08 02:51:14'),
(13, 'paiement', 'Quand est débité le paiement ?', 'Le paiement est confirmé au moment de la réservation. Pour les paiements en espèces, le règlement se fait directement au prestataire après l\'intervention.', 'Débit,Remboursement', 0, 0, 0, '2026-07-08 02:51:14'),
(14, 'paiement', 'Le paiement est-il sécurisé ?', 'Les moyens de paiement mobiles (Orange Money, Wave) utilisés sont ceux de vos opérateurs habituels et sécurisés par leurs propres systèmes.', 'Sécurité,Paiement', 0, 0, 0, '2026-07-08 02:51:14'),
(15, 'prestataire', 'Comment devenir prestataire sur ServicesPlus ?', 'Créez un compte en choisissant \'Prestataire\', remplissez votre profil professionnel (service, tarif, zone d\'intervention) et téléversez votre pièce d\'identité. Notre équipe valide votre dossier avant de vous laisser accéder à votre espace.', 'Inscription,Prestataire', 1, 0, 0, '2026-07-08 02:51:14'),
(16, 'prestataire', 'Comment recevoir mes paiements ?', 'Les paiements sont réglés selon la méthode choisie par le client au moment de la réservation (Orange Money, Wave, carte ou espèces directement).', 'Paiement,Revenus', 1, 0, 0, '2026-07-08 02:51:14'),
(17, 'securite', 'Comment protéger mon compte ?', 'Utilisez un mot de passe fort (min. 8 caractères, majuscules, chiffres), ne le partagez jamais et déconnectez-vous sur les appareils partagés.', 'Sécurité,Mot de passe', 1, 0, 0, '2026-07-08 02:51:14'),
(18, 'securite', 'Mes données personnelles sont-elles protégées ?', 'Vos données ne sont jamais vendues à des tiers et servent uniquement au fonctionnement de la plateforme.', 'RGPD,Données', 0, 0, 0, '2026-07-08 02:51:14'),
(19, 'technique', 'L\'application ne fonctionne pas, que faire ?', 'Essayez : 1) Rafraîchir la page. 2) Vider le cache du navigateur. 3) Essayer un autre navigateur. 4) Vérifier votre connexion. 5) Contacter le support si le problème persiste.', 'Bug,Support', 0, 0, 0, '2026-07-08 02:51:14'),
(20, 'technique', 'Je ne reçois pas les notifications.', 'Vérifiez que votre compte est bien actif et que vous êtes connecté. Les notifications apparaissent dans la cloche en haut de votre espace.', 'Notifications', 0, 0, 0, '2026-07-08 02:51:14'),
(21, 'autre', 'Puis-je utiliser ServicesPlus depuis mon téléphone ?', 'Oui, ServicesPlus est entièrement responsive et fonctionne sur mobile, tablette et ordinateur depuis n\'importe quel navigateur.', 'Mobile,Application', 0, 0, 0, '2026-07-08 02:51:14');

-- --------------------------------------------------------

--
-- Structure de la table `favoris`
--

DROP TABLE IF EXISTS `favoris`;
CREATE TABLE IF NOT EXISTS `favoris` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `prestataire_id` int NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_favori` (`client_id`,`prestataire_id`),
  KEY `prestataire_id` (`prestataire_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `logs_admin`
--

DROP TABLE IF EXISTS `logs_admin`;
CREATE TABLE IF NOT EXISTS `logs_admin` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cible_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cible_id` int NOT NULL,
  `date_action` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

DROP TABLE IF EXISTS `messages`;
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `annonce_id` int NOT NULL,
  `expediteur_id` int NOT NULL,
  `destinataire_id` int NOT NULL,
  `contenu` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `lu` tinyint(1) NOT NULL DEFAULT '0',
  `date_envoi` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `annonce_id` (`annonce_id`),
  KEY `expediteur_id` (`expediteur_id`),
  KEY `destinataire_id` (`destinataire_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `messages_contact`
--

DROP TABLE IF EXISTS `messages_contact`;
CREATE TABLE IF NOT EXISTS `messages_contact` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sujet` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `traite` tinyint(1) NOT NULL DEFAULT '0',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `titre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `lien` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lu` tinyint(1) NOT NULL DEFAULT '0',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `titre`, `message`, `type`, `lien`, `lu`, `date_creation`) VALUES
(1, 1, 'Nouveau prestataire à valider', 'Ibrahima Faye vient de s\'inscrire comme prestataire et attend votre validation.', 'prestataire', 'gestion_prestataires.php', 1, '2026-07-08 03:08:17'),
(2, 3, 'Compte validé ✅', 'Votre dossier prestataire a été validé. Vous pouvez maintenant vous connecter.', 'valide', NULL, 0, '2026-07-08 03:09:28'),
(3, 3, 'Nouvelle réservation 📅', 'Vous avez une nouvelle réservation le 2026-07-08 à 14:00.', 'reservation', '../espace_prestataire/dashboard_prestataire.php', 0, '2026-07-08 21:42:41');

-- --------------------------------------------------------

--
-- Structure de la table `prestataire_profils`
--

DROP TABLE IF EXISTS `prestataire_profils`;
CREATE TABLE IF NOT EXISTS `prestataire_profils` (
  `user_id` int NOT NULL,
  `categorie_id` int NOT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `tarif_horaire` decimal(10,2) NOT NULL DEFAULT '0.00',
  `experience` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '1 a 3 ans',
  `piece_identite` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut_validation` enum('en_attente','valide','refuse') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `disponible` tinyint(1) NOT NULL DEFAULT '1',
  `note_moyenne` decimal(3,2) NOT NULL DEFAULT '0.00',
  `nb_avis` int NOT NULL DEFAULT '0',
  `admin_validateur_id` int DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  KEY `categorie_id` (`categorie_id`),
  KEY `admin_validateur_id` (`admin_validateur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `prestataire_profils`
--

INSERT INTO `prestataire_profils` (`user_id`, `categorie_id`, `bio`, `tarif_horaire`, `experience`, `piece_identite`, `statut_validation`, `disponible`, `note_moyenne`, `nb_avis`, `admin_validateur_id`, `date_validation`) VALUES
(3, 2, 'BBBNHNGFGGFFFFG\nZone d\'intervention : DAKAR', 26667.00, '5 à 10 ans', 'uploads/identite/spf_6a4dbf21dab35.jpeg', 'valide', 1, 0.00, 0, 1, '2026-07-08 03:09:28');

-- --------------------------------------------------------

--
-- Structure de la table `reservations`
--

DROP TABLE IF EXISTS `reservations`;
CREATE TABLE IF NOT EXISTS `reservations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `annonce_id` int DEFAULT NULL,
  `client_id` int NOT NULL,
  `prestataire_id` int NOT NULL,
  `categorie_id` int NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `adresse` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ville` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_reservation` datetime NOT NULL,
  `duree_heures` int NOT NULL DEFAULT '1',
  `montant` decimal(10,2) NOT NULL DEFAULT '0.00',
  `methode_paiement` enum('orange_money','wave','especes','carte') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'especes',
  `statut` enum('en_attente','confirmee','en_cours','terminee','annulee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'confirmee',
  `reference` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `annonce_id` (`annonce_id`),
  KEY `client_id` (`client_id`),
  KEY `prestataire_id` (`prestataire_id`),
  KEY `categorie_id` (`categorie_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `reservations`
--

INSERT INTO `reservations` (`id`, `annonce_id`, `client_id`, `prestataire_id`, `categorie_id`, `description`, `adresse`, `ville`, `date_reservation`, `duree_heures`, `montant`, `methode_paiement`, `statut`, `reference`, `date_creation`) VALUES
(1, NULL, 2, 3, 2, 'cc cv', 'parcelle Assénie U14 villa N°145', 'Thiès', '2026-07-08 14:00:00', 2, 56001.00, 'wave', 'confirmee', 'SP-2026-764FD6', '2026-07-08 21:42:41');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role` enum('client','prestataire','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'client',
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ville` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` enum('actif','suspendu') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `role`, `nom`, `prenom`, `email`, `mot_de_passe`, `telephone`, `ville`, `photo`, `statut`, `date_creation`) VALUES
(1, 'admin', 'Admin', 'ServicesPlus', 'admin@servicesplus.sn', '$2b$10$5cSxt/Kh03TtVC/.b/733eMS4xLZ737wtjU3crkj19Ut/u9jeB1SC', '771234567', 'Dakar', NULL, 'actif', '2026-07-08 02:51:13'),
(2, 'client', 'Faye', 'Ibrahima', 'ibsibzo97@gmail.com', '$2y$12$aZmuamOkLiNE6KK3SkkzquKf.32uIRc0E.iekm4U61rASPtEfhQvO', '+221703362964', 'Dakar', 'uploads/profils/spf_6a4dbc780ec40.jpeg', 'actif', '2026-07-08 02:56:56'),
(3, 'prestataire', 'Faye', 'Ibrahima', 'ibrahima.faye42@unchk.edu.sn', '$2y$12$IjJfbo0ZIUuTRtU8.9svteHuOpCIkLl2c67o7qp.s/XbmhkehrfSK', '+221773588475', 'Thiès', 'uploads/profils/spf_6a4dbf21d9ef7.jpeg', 'actif', '2026-07-08 03:08:17');

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `annonces`
--
ALTER TABLE `annonces`
  ADD CONSTRAINT `annonces_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `annonces_ibfk_2` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `annonces_ibfk_3` FOREIGN KEY (`prestataire_choisi_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `avis`
--
ALTER TABLE `avis`
  ADD CONSTRAINT `avis_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `avis_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `avis_ibfk_3` FOREIGN KEY (`prestataire_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `candidatures`
--
ALTER TABLE `candidatures`
  ADD CONSTRAINT `candidatures_ibfk_1` FOREIGN KEY (`annonce_id`) REFERENCES `annonces` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `candidatures_ibfk_2` FOREIGN KEY (`prestataire_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `favoris`
--
ALTER TABLE `favoris`
  ADD CONSTRAINT `favoris_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favoris_ibfk_2` FOREIGN KEY (`prestataire_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `logs_admin`
--
ALTER TABLE `logs_admin`
  ADD CONSTRAINT `logs_admin_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`annonce_id`) REFERENCES `annonces` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`expediteur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`destinataire_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `prestataire_profils`
--
ALTER TABLE `prestataire_profils`
  ADD CONSTRAINT `prestataire_profils_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prestataire_profils_ibfk_2` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `prestataire_profils_ibfk_3` FOREIGN KEY (`admin_validateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`annonce_id`) REFERENCES `annonces` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_3` FOREIGN KEY (`prestataire_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_4` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
