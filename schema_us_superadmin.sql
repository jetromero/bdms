-- Adds `superadmin` role and seed account (username: superadmin, password: bdms25 — change after first login).
-- Run once on existing `bdms` databases that already have `staff_users` from schema_us06.sql.

ALTER TABLE `staff_users`
  MODIFY COLUMN `role` enum('administrator','staff','superadmin') NOT NULL DEFAULT 'staff';

INSERT INTO `staff_users` (`username`, `email`, `display_name`, `password_hash`, `role`, `is_active`)
SELECT
  'superadmin',
  'superadmin@bdms.local',
  'Super Administrator',
  '$2y$10$teWZOYHFqLNV46q0iBMhBO3kEs1uoub71vjDFBLcLZiDDwPUwUqPO',
  'superadmin',
  1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `staff_users` WHERE `username` = 'superadmin' LIMIT 1);
