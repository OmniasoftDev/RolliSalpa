-- Appunti dal sito: equivale alla migration 2026_10_02_100000_create_appunti_tables.
-- Da importare in phpMyAdmin (database omniapp_rollisalpa) PRIMA del push che la introduce.

SET NAMES utf8mb4;

CREATE TABLE `appunti` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `tipo` varchar(20) NOT NULL,
  `macchine` json DEFAULT NULL,
  `testo` text NOT NULL,
  `elaborato_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `appunti_project_id_created_at_index` (`project_id`,`created_at`),
  KEY `appunti_user_id_foreign` (`user_id`),
  CONSTRAINT `appunti_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appunti_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appunti_allegati` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `appunto_id` bigint unsigned NOT NULL,
  `nome` varchar(255) NOT NULL,
  `percorso` varchar(255) NOT NULL,
  `mime` varchar(100) NOT NULL,
  `dimensione` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `appunti_allegati_appunto_id_foreign` (`appunto_id`),
  CONSTRAINT `appunti_allegati_appunto_id_foreign` FOREIGN KEY (`appunto_id`) REFERENCES `appunti` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_02_100000_create_appunti_tables', 2);
