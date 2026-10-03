-- Persone e compiti: equivale alla migration 2026_10_03_000000_create_persone_e_compiti.
-- Da importare in phpMyAdmin (database omniapp_rollisalpa) PRIMA del push che la introduce.

SET NAMES utf8mb4;

CREATE TABLE `persone` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `codice` varchar(40) NOT NULL,
  `ordine` int unsigned NOT NULL DEFAULT '0',
  `nome` varchar(255) NOT NULL,
  `azienda` varchar(255) DEFAULT NULL,
  `gruppo` varchar(60) DEFAULT NULL,
  `ruolo` text,
  `macchine` json DEFAULT NULL,
  `contatti` varchar(255) DEFAULT NULL,
  `fonte` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `persone_project_id_codice_unique` (`project_id`,`codice`),
  CONSTRAINT `persone_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `compiti` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `codice` varchar(40) DEFAULT NULL,
  `persona` varchar(40) DEFAULT NULL,
  `testo` text NOT NULL,
  `macchine` json DEFAULT NULL,
  `fonte` varchar(255) DEFAULT NULL,
  `scadenza` date DEFAULT NULL,
  `stato` varchar(12) NOT NULL DEFAULT 'aperto',
  `assegnato_il` date DEFAULT NULL,
  `chiuso_il` date DEFAULT NULL,
  `esito` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `compiti_project_id_codice_unique` (`project_id`,`codice`),
  KEY `compiti_project_id_stato_index` (`project_id`,`stato`),
  CONSTRAINT `compiti_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_03_000000_create_persone_e_compiti', 6);
