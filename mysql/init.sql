-- ============================================================
--  VU KNF Lab - Database Schema
--  Auto-executed by MySQL on first container start via
--  /docker-entrypoint-initdb.d/
-- ============================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- -----------------------------------------------------
--  Users & Admins
-- -----------------------------------------------------

CREATE TABLE `cyber_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vu_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 - individual, 2 - team',
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `points` int DEFAULT '0',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `verify_token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_login` datetime DEFAULT NULL,
  `last_ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `newsletter` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username_UNIQUE` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cyber_admins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vu_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `main` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `vu_id_UNIQUE` (`vu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  Challenges & Categories
-- -----------------------------------------------------

CREATE TABLE `cyber_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cyber_partners` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `picture` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cyber_challenges` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(255) NOT NULL,
  `partner_id` int NOT NULL,
  `created_by` int NOT NULL DEFAULT '1',
  `name` varchar(255) NOT NULL,
  `descr` text NOT NULL,
  `content` longtext NOT NULL,
  `answer` varchar(255) NOT NULL,
  `points` int NOT NULL,
  `hidden` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cyber_challenges_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cat_id` int NOT NULL,
  `challenge_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cyber_challenges_files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `challenge_id` int NOT NULL,
  `name` text NOT NULL,
  `file_url` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cyber_challenges_submitted` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `challenge_id` int NOT NULL,
  `points_given` int NOT NULL,
  `created` datetime NOT NULL,
  `ip` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_challenge_unique` (`user_id`, `challenge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cyber_challenges_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `created_time` bigint NOT NULL,
  `created` datetime NOT NULL,
  `user_id` int NOT NULL,
  `challenge_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  Groups
-- -----------------------------------------------------

CREATE TABLE `cyber_groups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `created_by` int NOT NULL DEFAULT '1',
  `public_id` varchar(255) NOT NULL,
  `name` text NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cyber_groups_challenges` (
  `id` int NOT NULL AUTO_INCREMENT,
  `group_id` int NOT NULL,
  `challenge_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cyber_users_groups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `group_id` int NOT NULL,
  `vu_id` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  Content & Settings
-- -----------------------------------------------------

CREATE TABLE `cyber_pages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `alias` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cyber_emails` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `sent` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cyber_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `val1` text,
  `val2` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cyber_feedback` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `feedback` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cyber_certs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(100) NOT NULL,
  `user_id` int NOT NULL,
  `user_type` tinyint NOT NULL,
  `cert_num` int NOT NULL,
  `full_name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  Laravel framework tables
-- -----------------------------------------------------

CREATE TABLE `password_resets` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  Views (ranking)
-- -----------------------------------------------------

CREATE VIEW `cyber_ranking_table` AS
SELECT
  ROW_NUMBER() OVER (ORDER BY `cyber_users`.`points` DESC) AS `position`,
  `cyber_users`.`id`,
  `cyber_users`.`points`,
  `cyber_users`.`email`,
  `cyber_users`.`username`,
  `cyber_users`.`user_type`,
  `cyber_challenges_submitted`.`created` AS `last_challenge`
FROM `cyber_users`
LEFT JOIN `cyber_challenges_submitted`
  ON `cyber_challenges_submitted`.`user_id` = `cyber_users`.`id`
GROUP BY `cyber_users`.`id`
ORDER BY `cyber_users`.`points` DESC, `last_challenge`;

CREATE VIEW `cyber_users_ranks` AS
SELECT
  ROW_NUMBER() OVER w AS `user_rank`,
  `cyber_ranking_table`.`id` AS `user_id`,
  `cyber_ranking_table`.`email`,
  `cyber_ranking_table`.`user_type`,
  `cyber_ranking_table`.`username`,
  `cyber_ranking_table`.`points`,
  `cyber_ranking_table`.`last_challenge`
FROM `cyber_ranking_table`
WHERE `cyber_ranking_table`.`last_challenge` IS NOT NULL
WINDOW w AS (ORDER BY `cyber_ranking_table`.`points` DESC);

-- -----------------------------------------------------
--  Seed data
-- -----------------------------------------------------

INSERT INTO `cyber_settings` (`name`, `val1`, `val2`) VALUES
  ('event_date', '2023-02-25 09:00:00 - 2029-02-25 21:00:00', NULL),
  ('reg_open', '1', NULL);

INSERT INTO `cyber_admins` (`vu_id`, `email`, `password`, `main`) VALUES
  ('admin', 'admin@knf.vu.lt', '$2y$10$g.W8gNdJchkWdlp2rvql/Oba9Fs3mL6V/ReResks7LLMKK1CVIMna', 1);

INSERT INTO `cyber_categories` (`name`) VALUES
  ('Cryptography'),
  ('Digital Forensics'),
  ('Steganography'),
  ('OSINT'),
  ('Web Exploitation'),
  ('Network Security'),
  ('Reverse Engineering');
