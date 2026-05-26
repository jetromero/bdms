-- Password reset OTP table for staff email recovery.

CREATE TABLE IF NOT EXISTS `staff_password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `otp_expires_at` datetime NOT NULL,
  `otp_attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `verified_at` datetime DEFAULT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  KEY `email` (`email`),
  KEY `otp_expires_at` (`otp_expires_at`),
  KEY `consumed_at` (`consumed_at`),
  CONSTRAINT `fk_staff_password_resets_staff`
    FOREIGN KEY (`staff_id`) REFERENCES `staff_users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
