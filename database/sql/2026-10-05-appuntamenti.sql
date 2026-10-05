-- Appuntamenti: equivale alla migration 2026_10_05_000000_create_appuntamenti_table.
-- Da importare in phpMyAdmin (database omniapp_rollisalpa) PRIMA del push che la introduce.

SET NAMES utf8mb4;

CREATE TABLE `appuntamenti` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `codice` varchar(60) NOT NULL,
  `origine` varchar(10) NOT NULL DEFAULT 'pc',
  `titolo` text NOT NULL,
  `dettaglio` text,
  `luogo` varchar(255) DEFAULT NULL,
  `fonte` varchar(255) DEFAULT NULL,
  `macchine` json DEFAULT NULL,
  `inizio` datetime NOT NULL,
  `fine` datetime NOT NULL,
  `stato` varchar(12) NOT NULL DEFAULT 'proposto',
  `deciso_il` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `appuntamenti_project_id_codice_unique` (`project_id`,`codice`),
  KEY `appuntamenti_project_id_stato_index` (`project_id`,`stato`),
  CONSTRAINT `appuntamenti_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_05_000000_create_appuntamenti_table', 7);
