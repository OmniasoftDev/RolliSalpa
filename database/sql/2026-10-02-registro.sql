-- Registro controlli e mail: equivale alla migration 2026_10_02_400000_create_registro_controlli_e_mail.
-- Da importare in phpMyAdmin (database omniapp_rollisalpa) PRIMA del push che la introduce.

SET NAMES utf8mb4;

CREATE TABLE `controlli` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codice` varchar(19) NOT NULL,
  `inizio` datetime NOT NULL,
  `fine` datetime DEFAULT NULL,
  `esito` varchar(20) NOT NULL,
  `outlook` varchar(200) DEFAULT NULL,
  `mail_finestra` int unsigned NOT NULL DEFAULT '0',
  `mail_nuove` int unsigned NOT NULL DEFAULT '0',
  `appunti` int unsigned NOT NULL DEFAULT '0',
  `passi` json DEFAULT NULL,
  `firma_pc` varchar(64) DEFAULT NULL,
  `firma_server` varchar(64) DEFAULT NULL,
  `quadra` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `controlli_codice_unique` (`codice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mail_registro` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codice` varchar(40) NOT NULL,
  `ricevuta` datetime NOT NULL,
  `cartella` varchar(120) DEFAULT NULL,
  `da` varchar(255) DEFAULT NULL,
  `a` text,
  `cc` text,
  `oggetto` varchar(500) DEFAULT NULL,
  `testo` mediumtext,
  `allegati` json DEFAULT NULL,
  `inviata` tinyint(1) NOT NULL DEFAULT '0',
  `elaborata_at` datetime DEFAULT NULL,
  `trovata_controllo` varchar(19) DEFAULT NULL,
  `vista_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mail_registro_codice_unique` (`codice`),
  KEY `mail_registro_ricevuta_index` (`ricevuta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_02_400000_create_registro_controlli_e_mail', 5);
