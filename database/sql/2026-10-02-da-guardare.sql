-- "Da guardare": equivale alla migration 2026_10_02_300000_create_decisioni_e_eventi_da_vedere.
-- Da importare in phpMyAdmin (database omniapp_rollisalpa) PRIMA del push che la introduce.

SET NAMES utf8mb4;

CREATE TABLE `decisioni` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `codice` varchar(40) NOT NULL,
  `ordine` int unsigned NOT NULL DEFAULT '0',
  `testo` text NOT NULL,
  `fonte` varchar(255) DEFAULT NULL,
  `macchine` json DEFAULT NULL,
  `data` date DEFAULT NULL,
  `fatta` tinyint(1) NOT NULL DEFAULT '0',
  `fatta_il` date DEFAULT NULL,
  `fatta_web` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `decisioni_project_id_codice_unique` (`project_id`,`codice`),
  CONSTRAINT `decisioni_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `events`
  ADD COLUMN `da_vedere` tinyint(1) NOT NULL DEFAULT '0',
  ADD COLUMN `visto_at` timestamp NULL DEFAULT NULL;

INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_02_300000_create_decisioni_e_eventi_da_vedere', 4);
