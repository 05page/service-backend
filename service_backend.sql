-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : dim. 31 août 2025 à 22:20
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
  `stock_id` bigint(20) UNSIGNED NOT NULL,
  `fournisseur_id` bigint(20) UNSIGNED NOT NULL,
  `quantite` int(11) NOT NULL,
  `prix_total` decimal(10,2) NOT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `vente_id` bigint(20) UNSIGNED NOT NULL,
  `numero_facture` varchar(255) NOT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(1, 'Netflix', 'contact@netflix.com', '0600000001', 'Yopougon', 'Fournit des comptes Netflix premium', 1, 1, '2025-08-30 17:36:03', '2025-08-31 19:42:59'),
(2, 'MyCanal', 'mycanal@gmail.com.com', '0600000002', 'Angré', 'Fournit des comptes MyCanal', 1, 1, '2025-08-30 17:36:03', '2025-08-30 17:36:03'),
(3, 'Vergine', 'vergine@gmail.com', '0600000003', 'Cocody', 'Fournit des mocassins', 1, 1, '2025-08-30 17:36:03', '2025-08-30 17:36:03'),
(4, 'Spotify', 'spo@gmail.com', '0710073748', 'angré', 'Service de streamin', 1, 1, '2025-08-31 20:01:15', '2025-08-31 20:01:15');

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
(10, '2025_08_25_144956_factures', 1);

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
(1, 4, 1, 'Accès au module fournisseurs', 'fournisseurs', 1, '2025-08-30 17:17:06', '2025-08-30 17:17:06'),
(2, 4, 1, 'Accès au module services', 'services', 1, '2025-08-30 17:17:06', '2025-08-30 17:17:06'),
(3, 4, 1, 'Accès au module stock', 'stock', 1, '2025-08-30 17:17:06', '2025-08-30 17:17:06'),
(4, 4, 1, 'Accès au module ventes', 'ventes', 1, '2025-08-30 17:17:06', '2025-08-30 17:17:06'),
(5, 4, 1, 'Accès au module achats', 'achats', 1, '2025-08-30 17:17:06', '2025-08-30 17:17:06'),
(6, 4, 1, 'Accès au module factures', 'factures', 1, '2025-08-30 17:17:06', '2025-08-30 17:17:06'),
(7, 5, 1, 'Accès au module fournisseurs', 'fournisseurs', 1, '2025-08-30 17:17:06', '2025-08-30 17:17:06'),
(8, 5, 1, 'Accès au module services', 'services', 1, '2025-08-30 17:17:06', '2025-08-30 17:17:06'),
(9, 5, 1, 'Accès au module stock', 'stock', 1, '2025-08-30 17:17:06', '2025-08-30 17:17:06'),
(10, 5, 1, 'Accès au module ventes', 'ventes', 1, '2025-08-30 17:17:06', '2025-08-30 17:17:06'),
(11, 5, 1, 'Accès au module achats', 'achats', 1, '2025-08-30 17:17:06', '2025-08-30 17:17:06'),
(12, 5, 1, 'Accès au module factures', 'factures', 1, '2025-08-30 17:17:06', '2025-08-30 17:17:06');

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
(1, 'App\\Models\\User', 1, 'auth_token', '4aa775752f3c5bcf25a4daae3f339b31d9887c67cdf32f128d1bccfb6ab5e7d3', '[\"*\"]', NULL, NULL, '2025-08-30 16:42:08', '2025-08-30 16:42:08'),
(2, 'App\\Models\\User', 1, 'auth_token', '6447510ca32624e628d6dc9e4a22aeb2b23d12dcde1faca774ba4b6eac63279e', '[\"*\"]', '2025-08-31 20:09:33', NULL, '2025-08-30 19:55:19', '2025-08-31 20:09:33');

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
  `nom_produit` text NOT NULL,
  `code_produit` varchar(255) NOT NULL,
  `categorie` varchar(255) DEFAULT NULL,
  `fournisseur_id` bigint(20) UNSIGNED NOT NULL,
  `quantite` int(11) NOT NULL DEFAULT 0,
  `quantite_min` int(11) NOT NULL DEFAULT 0,
  `prix_achat` int(11) NOT NULL,
  `prix_vente` int(11) NOT NULL,
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

INSERT INTO `stock` (`id`, `nom_produit`, `code_produit`, `categorie`, `fournisseur_id`, `quantite`, `quantite_min`, `prix_achat`, `prix_vente`, `description`, `statut`, `actif`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'compte netflix', '503qSF', 'Services', 1, 1, 3, 5000, 6500, NULL, 'disponible', 1, 1, '2025-08-30 20:19:36', '2025-08-31 03:06:40');

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
  `password` varchar(255) NOT NULL,
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
(1, 'Dibi Jean David Raymond', 'jeandaviddibi16@gmail.com', '0703195229', 'cocody', 'admin', '$2y$12$I.uERnlitUTsk62W55V.mO7zi9BPCd6Rtab6j9Dqmck4.FhST2sPy', 'A3m83YaD', NULL, 1, '2025-08-30 16:43:48', NULL, '2025-08-30 16:41:25', '2025-08-30 16:43:48'),
(2, 'Admin Two', 'admin1@example.com', '0100000001', 'Adresse Admin 1', 'admin', '$2y$12$4qQSLNoC66pSb3JX9vaEM.2FaOwtjarKlNwiRuyyv49im5wTf.PCq', 'fgql912e', NULL, 1, NULL, NULL, '2025-08-30 16:59:07', '2025-08-30 16:59:07'),
(3, 'Admin three', 'admin2@example.com', '0100000002', 'Adresse Admin 2', 'admin', '$2y$12$0Ht5teCSBLT119ponBq.ruYuyN0zbXYPHr3SeRQTtfGxPZMxYKD4K', 'vPJF5v90', NULL, 1, NULL, NULL, '2025-08-30 16:59:08', '2025-08-30 16:59:08'),
(4, 'Employe 1', 'employe1@example.com', '0200000001', 'Adresse Employe 1', 'employe', 'Password_123', NULL, '2025-08-30 17:03:48', 1, '2025-08-30 17:04:15', 1, '2025-08-30 16:59:09', '2025-08-30 17:03:48'),
(5, 'Employe 2', 'employe2@example.com', '0200000002', 'Adresse Employe 2', 'employe', '$2y$12$Z7YF9W9kCm/URzHIX/MFKerPu9JkMsX.WicyfoGVasigy0ZejuLqC', NULL, '2025-08-30 17:04:59', 1, '2025-08-30 17:05:39', 1, '2025-08-30 16:59:10', '2025-08-30 17:04:59'),
(6, 'Employe 3', 'employe3@example.com', '0200000003', 'Adresse Employe 3', 'employe', '$2y$12$xWJ3866CWbBFCEoRSmKqmeCG0sKL4YXhUdDzBYok/euvPvX5RT8oa', NULL, '2025-08-30 17:05:19', 1, '2025-08-30 17:05:39', 2, '2025-08-30 16:59:11', '2025-08-30 17:05:19');

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
  `quantite` int(11) DEFAULT NULL,
  `prix_total` decimal(10,2) NOT NULL,
  `statut` enum('en_attente','payé','annulé') NOT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ventes`
--

INSERT INTO `ventes` (`id`, `stock_id`, `reference`, `nom_client`, `numero`, `quantite`, `prix_total`, `statut`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'wOfe0a', 'david', '0779523470', 5, 32500.00, 'payé', 1, '2025-08-31 00:15:59', '2025-08-31 02:40:56'),
(2, 1, 'VEN-2025-001', 'franck', '0770244358', 2, 13000.00, 'en_attente', 1, '2025-08-31 03:06:40', '2025-08-31 03:06:40');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `achats`
--
ALTER TABLE `achats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `achats_stock_id_foreign` (`stock_id`),
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
  ADD KEY `stock_fournisseur_id_foreign` (`fournisseur_id`),
  ADD KEY `stock_created_by_foreign` (`created_by`);

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
  ADD KEY `ventes_stock_id_foreign` (`stock_id`),
  ADD KEY `ventes_created_by_foreign` (`created_by`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `achats`
--
ALTER TABLE `achats`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `factures`
--
ALTER TABLE `factures`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `fournisseurs`
--
ALTER TABLE `fournisseurs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT pour la table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `stock`
--
ALTER TABLE `stock`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `ventes`
--
ALTER TABLE `ventes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `achats`
--
ALTER TABLE `achats`
  ADD CONSTRAINT `achats_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `achats_fournisseur_id_foreign` FOREIGN KEY (`fournisseur_id`) REFERENCES `fournisseurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `achats_stock_id_foreign` FOREIGN KEY (`stock_id`) REFERENCES `stock` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `factures`
--
ALTER TABLE `factures`
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
  ADD CONSTRAINT `stock_fournisseur_id_foreign` FOREIGN KEY (`fournisseur_id`) REFERENCES `fournisseurs` (`id`) ON DELETE CASCADE;

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
