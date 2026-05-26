-- US-06: Staff user accounts for login and admin user management (run once on existing `bdms` database).

CREATE TABLE IF NOT EXISTS `staff_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL,
  `email` varchar(255) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('administrator','staff') NOT NULL DEFAULT 'staff',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed default administrator if none exists (username: admin, password: bdms25 — change after first login).
INSERT INTO `staff_users` (`username`, `email`, `display_name`, `password_hash`, `role`, `is_active`)
SELECT
  'admin',
  'admin@bdms.local',
  'System Administrator',
  '$2y$10$teWZOYHFqLNV46q0iBMhBO3kEs1uoub71vjDFBLcLZiDDwPUwUqPO',
  'administrator',
  1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `staff_users` WHERE `username` = 'admin' LIMIT 1);
