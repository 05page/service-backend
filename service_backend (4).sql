-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mer. 01 oct. 2025 à 23:37
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `service_backend`
--

-- --------------------------------------------------------

--
-- Structure de la table `achats`
--

CREATE TABLE `achats` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `fournisseur_id` bigint(20) UNSIGNED NOT NULL,
  `nom_service` varchar(255) NOT NULL,
  `quantite` int(11) NOT NULL,
  `prix_unitaire` decimal(10,2) DEFAULT NULL,
  `prix_total` decimal(10,2) NOT NULL,
  `numero_achat` varchar(255) DEFAULT NULL,
  `date_commande` date DEFAULT NULL,
  `date_livraison` date DEFAULT NULL,
  `statut` enum('commande','reçu','paye','annule') NOT NULL DEFAULT 'commande',
  `mode_paiement` enum('virement','mobile_money','especes') DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `achats`
--

INSERT INTO `achats` (`id`, `fournisseur_id`, `nom_service`, `quantite`, `prix_unitaire`, `prix_total`, `numero_achat`, `date_commande`, `date_livraison`, `statut`, `mode_paiement`, `description`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 1, 'streaming Crunchyroll', 5, 1800.00, 9000.00, 'ACH-2025-001', '2025-09-26', '2025-09-27', 'reçu', NULL, NULL, 1, '2025-09-26 04:19:34', '2025-10-01 04:32:40'),
(5, 2, 'disque dur', 2, 20000.00, 40000.00, 'ACH-2025-002', '2025-10-01', '2025-10-08', 'commande', NULL, NULL, 1, '2025-10-01 20:36:59', '2025-10-01 20:36:59');

-- --------------------------------------------------------

--
-- Structure de la table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `factures`
--

CREATE TABLE `factures` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `achat_id` bigint(20) UNSIGNED DEFAULT NULL,
  `vente_id` bigint(20) UNSIGNED DEFAULT NULL,
  `numero_facture` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `factures`
--

INSERT INTO `factures` (`id`, `achat_id`, `vente_id`, `numero_facture`, `created_by`, `created_at`, `updated_at`) VALUES
(5, 2, NULL, 'FAC-2025-001', 1, '2025-10-01 18:00:19', '2025-10-01 18:00:19'),
(6, NULL, 8, 'FAC-2025-002', 1, '2025-10-01 18:01:21', '2025-10-01 18:01:21');

-- --------------------------------------------------------

--
-- Structure de la table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `fournisseurs`
--

CREATE TABLE `fournisseurs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nom_fournisseurs` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telephone` varchar(255) DEFAULT NULL,
  `adresse` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `fournisseurs`
--

INSERT INTO `fournisseurs` (`id`, `nom_fournisseurs`, `email`, `telephone`, `adresse`, `description`, `created_by`, `actif`, `created_at`, `updated_at`) VALUES
(1, 'lycoris', 'lycoris@gmail.com', '0708772062', 'riviera 2', 'streaming Crunchyroll', 1, 1, '2025-09-25 05:55:52', '2025-09-29 18:22:43'),
(2, 'Mkd', 'mkd@gmail.com', '0796452168', 'yopougon', 'disque dur', 1, 1, '2025-09-29 18:24:21', '2025-09-29 18:24:21');

-- --------------------------------------------------------

--
-- Structure de la table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000001_create_cache_table', 1),
(2, '0001_01_01_000002_create_jobs_table', 1),
(3, '2025_08_09_124216_create_personal_access_tokens_table', 1),
(4, '2025_08_11_160951_users', 1),
(5, '2025_08_21_101619_fournisseurs', 1),
(6, '2025_08_22_112007_permissions', 1),
(7, '2025_08_25_144922_stock', 1),
(8, '2025_08_25_144937_ventes', 1),
(9, '2025_08_25_144946_achats', 1),
(10, '2025_09_15_100536_factures', 1),
(11, '2025_09_30_095344_create_password_reset_tokens_table', 2);

-- --------------------------------------------------------

--
-- Structure de la table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`email`, `token`, `created_at`) VALUES
('jddibi16@gmail.com', '$2y$12$6QKeSUIaKIVo6cz4.ywyl.tQVyj8IBpSZbqeQZrNE6hEfemyxabmG', '2025-09-30 11:39:31'),
('jeandaviddibi47@gmail.com', '$2y$12$KtvZHp6aGlxDRCiXAWmCBupQbf8dny2Hkkk118x/NTcANt9shbUz2', '2025-09-30 12:49:05');

-- --------------------------------------------------------

--
-- Structure de la table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `description` varchar(255) NOT NULL,
  `module` enum('fournisseurs','services','stock','ventes','achats','factures') NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `permissions`
--

INSERT INTO `permissions` (`id`, `user_id`, `created_by`, `description`, `module`, `active`, `created_at`, `updated_at`) VALUES
(1, 6, 1, 'test', 'stock', 1, '2025-10-01 02:18:30', '2025-10-01 18:53:11'),
(2, 6, 1, 'test', 'achats', 1, '2025-10-01 02:24:13', '2025-10-01 18:53:13'),
(3, 6, 1, 'test', 'ventes', 1, '2025-10-01 04:43:10', '2025-10-01 18:53:18');

-- --------------------------------------------------------

--
-- Structure de la table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `personal_access_tokens`
--

INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(11, 'App\\Models\\User', 5, 'auth_token', 'a07344d618a852540783c478dbbfe3f6b4a94b447544f3008ee575647c8c15be', '[\"*\"]', '2025-09-29 23:42:02', NULL, '2025-09-29 23:33:20', '2025-09-29 23:42:02'),
(14, 'App\\Models\\User', 5, 'auth_token', 'b471c90bde1a13e27f72154a4ac84f27a623a9f04d67e6b345965e3c777ee98d', '[\"*\"]', NULL, NULL, '2025-09-30 08:56:15', '2025-09-30 08:56:15'),
(15, 'App\\Models\\User', 5, 'auth_token', '18046666d9c6ee782cb8db58ada92460050d96df8e85dfb7ee5a3d2c84f7a6d3', '[\"*\"]', '2025-09-30 22:33:01', NULL, '2025-09-30 09:28:09', '2025-09-30 22:33:01'),
(17, 'App\\Models\\User', 6, 'auth_token', 'b8dc83018c5e615ea765db3d04b16369aeb3d14ba5976bda33954c2136f54ddf', '[\"*\"]', '2025-09-30 11:50:49', NULL, '2025-09-30 11:49:34', '2025-09-30 11:50:49'),
(20, 'App\\Models\\User', 6, 'auth_token', '6b65e0c0cc76e142e57a57c2e903987aeb2c000cd76249d79141059b27df344a', '[\"*\"]', '2025-10-01 21:31:55', NULL, '2025-09-30 13:30:19', '2025-10-01 21:31:55'),
(23, 'App\\Models\\User', 1, 'auth_token', '7446ffa646ad430bf65bb749e8509910d02fe9966eb966b73f3ae2133dfd1984', '[\"*\"]', '2025-10-01 21:24:58', NULL, '2025-09-30 22:13:26', '2025-10-01 21:24:58'),
(24, 'App\\Models\\User', 1, 'auth_token', '6dc2a21735ae7cd4edb7f7db1740e42ad4092ad558d0784577065a9170165cf9', '[\"*\"]', '2025-10-01 06:09:41', NULL, '2025-09-30 22:19:46', '2025-10-01 06:09:41');

-- --------------------------------------------------------

--
-- Structure de la table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` text NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `stock`
--

CREATE TABLE `stock` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `achat_id` bigint(20) UNSIGNED NOT NULL,
  `code_produit` varchar(255) NOT NULL,
  `categorie` varchar(255) DEFAULT NULL,
  `quantite` int(11) NOT NULL,
  `quantite_min` int(11) NOT NULL DEFAULT 0,
  `prix_vente` decimal(10,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `statut` enum('disponible','alerte','rupture') NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `stock`
--

INSERT INTO `stock` (`id`, `achat_id`, `code_produit`, `categorie`, `quantite`, `quantite_min`, `prix_vente`, `description`, `statut`, `actif`, `created_by`, `created_at`, `updated_at`) VALUES
(4, 2, 'STCK-2025-003', 'services', 3, 1, 2300.00, NULL, 'disponible', 1, 6, '2025-10-01 15:18:01', '2025-10-01 18:01:16');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `telephone` varchar(255) NOT NULL,
  `adresse` varchar(255) NOT NULL,
  `role` enum('admin','employe','intermediaire') NOT NULL DEFAULT 'admin',
  `password` varchar(255) DEFAULT NULL,
  `activation_code` varchar(255) DEFAULT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `fullname`, `email`, `telephone`, `adresse`, `role`, `password`, `activation_code`, `activated_at`, `active`, `email_verified_at`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Dibi jd', 'jeandaviddibi16@gmail.com', '0703195229', 'yopougon', 'admin', '$2y$12$UVnl0WPA7l8K8x9IfILnN.l5ChYdL2xvJb0ov4wTFTVVbjRD8Ve0a', 'vnFmsDhx', NULL, 0, '2025-09-25 04:56:56', NULL, '2025-09-25 04:54:43', '2025-10-01 18:27:52'),
(5, 'Raymond Diby', 'jddibi16@gmail.com', '0708772062', 'yopougon', 'employe', '$2y$12$vybsyoDbRFBDiwLm34Xe4.L8R8nwSKoEDVdZqW/hPgSjDcPGgUV1q', NULL, '2025-09-29 23:31:53', 1, '2025-09-29 23:31:53', 1, '2025-09-29 23:30:38', '2025-09-30 09:28:56'),
(6, 'Kouassi Dibi jean david', 'jeandaviddibi47@gmail.com', '0708776056', 'yopougon', 'employe', '$2y$12$Mw7j1Tp6/3.G17FwW3wFB.JWzFI6ocn/UBOx4UIk6W61ALCHjbwgC', NULL, '2025-09-30 11:48:10', 1, '2025-09-30 11:48:10', 1, '2025-09-30 11:46:35', '2025-10-01 18:53:45');

-- --------------------------------------------------------

--
-- Structure de la table `ventes`
--

CREATE TABLE `ventes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `stock_id` bigint(20) UNSIGNED NOT NULL,
  `reference` varchar(255) NOT NULL,
  `nom_client` varchar(255) NOT NULL,
  `numero` varchar(255) NOT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `quantite` int(11) DEFAULT NULL,
  `prix_total` decimal(10,2) NOT NULL,
  `statut` enum('en attente','paye','annule') NOT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ventes`
--

INSERT INTO `ventes` (`id`, `stock_id`, `reference`, `nom_client`, `numero`, `adresse`, `quantite`, `prix_total`, `statut`, `created_by`, `created_at`, `updated_at`) VALUES
(8, 4, 'VEN-2025-001', 'Kouassi Horlane', '0708772062', 'Yamoussoukro', 2, 4600.00, 'paye', 1, '2025-10-01 18:01:16', '2025-10-01 18:01:16');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `achats`
--
ALTER TABLE `achats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `achats_numero_achat_unique` (`numero_achat`),
  ADD KEY `achats_fournisseur_id_foreign` (`fournisseur_id`),
  ADD KEY `achats_created_by_foreign` (`created_by`);

--
-- Index pour la table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Index pour la table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Index pour la table `factures`
--
ALTER TABLE `factures`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `factures_numero_facture_unique` (`numero_facture`),
  ADD KEY `factures_achat_id_foreign` (`achat_id`),
  ADD KEY `factures_vente_id_foreign` (`vente_id`),
  ADD KEY `factures_created_by_foreign` (`created_by`);

--
-- Index pour la table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Index pour la table `fournisseurs`
--
ALTER TABLE `fournisseurs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fournisseurs_actif_index` (`actif`),
  ADD KEY `fournisseurs_nom_fournisseurs_index` (`nom_fournisseurs`),
  ADD KEY `fournisseurs_created_by_index` (`created_by`);

--
-- Index pour la table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Index pour la table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD KEY `password_reset_tokens_email_index` (`email`);

--
-- Index pour la table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `permissions_user_id_foreign` (`user_id`),
  ADD KEY `permissions_created_by_foreign` (`created_by`),
  ADD KEY `permissions_module_active_index` (`module`,`active`);

--
-- Index pour la table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Index pour la table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Index pour la table `stock`
--
ALTER TABLE `stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stock_code_produit_unique` (`code_produit`),
  ADD KEY `stock_created_by_foreign` (`created_by`),
  ADD KEY `achat_id` (`achat_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD KEY `users_created_by_foreign` (`created_by`);

--
-- Index pour la table `ventes`
--
ALTER TABLE `ventes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ventes_reference_unique` (`reference`),
  ADD KEY `ventes_stock_id_foreign` (`stock_id`),
  ADD KEY `ventes_created_by_foreign` (`created_by`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `achats`
--
ALTER TABLE `achats`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `factures`
--
ALTER TABLE `factures`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `fournisseurs`
--
ALTER TABLE `fournisseurs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT pour la table `stock`
--
ALTER TABLE `stock`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `ventes`
--
ALTER TABLE `ventes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `achats`
--
ALTER TABLE `achats`
  ADD CONSTRAINT `achats_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `achats_fournisseur_id_foreign` FOREIGN KEY (`fournisseur_id`) REFERENCES `fournisseurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `factures`
--
ALTER TABLE `factures`
  ADD CONSTRAINT `factures_achat_id_foreign` FOREIGN KEY (`achat_id`) REFERENCES `achats` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `factures_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `factures_vente_id_foreign` FOREIGN KEY (`vente_id`) REFERENCES `ventes` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `permissions`
--
ALTER TABLE `permissions`
  ADD CONSTRAINT `permissions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `permissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `stock`
--
ALTER TABLE `stock`
  ADD CONSTRAINT `stock_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_ibfk_1` FOREIGN KEY (`achat_id`) REFERENCES `achats` (`id`);

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `ventes`
--
ALTER TABLE `ventes`
  ADD CONSTRAINT `ventes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ventes_stock_id_foreign` FOREIGN KEY (`stock_id`) REFERENCES `stock` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
