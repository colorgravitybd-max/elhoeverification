-- ============================================================================
-- ELHOE Verification - Seed Initial Admin User
--
-- Default credentials:
--   Username: admin
--   Email:    admin@elhoe.com
--   Password: ChangeMe123!
--   Role:     super_admin
--
-- IMPORTANT:
--   1. Change the password IMMEDIATELY after first login
--      (Admin Panel → Settings → Admins → Reset Password)
--   2. Delete or change the email to your real address
--   3. The password hash below is bcrypt cost=12 of "ChangeMe123!"
-- ============================================================================

INSERT INTO `admin_users` (`username`, `email`, `password_hash`, `role`, `status`)
VALUES (
    'admin',
    'admin@elhoe.com',
    '$2y$12$xrANHm1YZMeCbdj9jgIOQ.52rya8m5GxBcickddPRyy0jLEGfbl/q',
    'super_admin',
    'active'
)
ON DUPLICATE KEY UPDATE
    `email`         = VALUES(`email`),
    `password_hash` = VALUES(`password_hash`),
    `role`          = VALUES(`role`),
    `status`        = VALUES(`status`);
