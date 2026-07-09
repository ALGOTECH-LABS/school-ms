CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `user_name` varchar(150) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `action` varchar(191) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `method` varchar(10) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `al_created_idx` (`created_at`),
  KEY `al_user_idx` (`user_id`),
  KEY `al_action_idx` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
