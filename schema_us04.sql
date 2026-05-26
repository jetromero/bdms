-- US-04: Low inventory thresholds
-- Run this once on the `bdms` database.

CREATE TABLE IF NOT EXISTS `blood_thresholds` (
  `blood_type` varchar(10) NOT NULL,
  `threshold_ml` int(11) NOT NULL DEFAULT 500,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`blood_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `blood_thresholds` (`blood_type`, `threshold_ml`) VALUES
('A+', 500),
('A-', 500),
('B+', 500),
('B-', 500),
('AB+', 500),
('AB-', 500),
('O+', 500),
('O-', 500)
ON DUPLICATE KEY UPDATE threshold_ml = VALUES(threshold_ml);

