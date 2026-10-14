-- Cassaforte delle credenziali: equivale alla migration 2026_10_14_000000_create_credenziali_tables.
-- Da importare in phpMyAdmin (database omniapp_rollisalpa). Il codice e' gia' in linea: finche' le
-- tabelle mancano, il sito mostra "Cassaforte non ancora attiva" e non salva nulla.
-- utente, segreto e note contengono solo testo cifrato con APP_KEY: se APP_KEY cambia, non si leggono piu'.

SET NAMES utf8mb4;

CREATE TABLE `credenziali` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `oggetto_tipo` varchar(10) NOT NULL,
  `oggetto_codice` varchar(20) NOT NULL,
  `etichetta` varchar(150) NOT NULL,
  `indirizzo` varchar(255) DEFAULT NULL,
  `utente` text,
  `segreto` text,
  `note` text,
  `fonte` varchar(255) DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `credenziali_project_id_oggetto_tipo_oggetto_codice_index` (`project_id`,`oggetto_tipo`,`oggetto_codice`),
  KEY `credenziali_user_id_foreign` (`user_id`),
  CONSTRAINT `credenziali_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `credenziali_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `credenziali_accessi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `credenziale_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `azione` varchar(20) NOT NULL,
  `nota` varchar(250) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `credenziali_accessi_credenziale_id_index` (`credenziale_id`),
  KEY `credenziali_accessi_user_id_foreign` (`user_id`),
  CONSTRAINT `credenziali_accessi_credenziale_id_foreign` FOREIGN KEY (`credenziale_id`) REFERENCES `credenziali` (`id`) ON DELETE SET NULL,
  CONSTRAINT `credenziali_accessi_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_14_000000_create_credenziali_tables', 8);
