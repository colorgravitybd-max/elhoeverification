-- ============================================================================
-- ELHOE Verification - Legacy data import from old WordPress plugin
--
-- HOW TO USE:
--   1. Import migrations/000_schema.sql first
--   2. Then import this file via Hostinger phpMyAdmin → Import
--   3. After import, edit each placeholder product via Admin → Products to set
--      proper name, image_url, product_url, etc., OR upload products-import.csv
--      with the same wp_product_id values to bulk-update.
--
-- WHAT THIS DOES:
--   - Inserts placeholder rows in `products` for each WordPress product_id
--     referenced by the legacy codes. Names are auto-generated; replace later.
--   - Inserts the 47 legacy codes (deduplicated from 47 raw → 45 unique).
--   - Inserts the ~80 legacy scan logs.
--
-- DEDUPE NOTES:
--   - 725273453536 appeared twice (same product 2779, scan_count 4 + 2) → kept 4
--   - 457667893450 appeared twice (different products 2948 / 2823) → kept 2948
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- 1. Placeholder products (one per unique wp_product_id)
-- ---------------------------------------------------------------------------
INSERT INTO `products` (`wp_product_id`, `sku`, `name`, `slug`, `status`, `created_at`) VALUES
  (2387, NULL, 'Product #2387 (placeholder)', 'product-2387', 'active', NOW()),
  (2779, NULL, 'Product #2779 (placeholder)', 'product-2779', 'active', NOW()),
  (2809, NULL, 'Product #2809 (placeholder)', 'product-2809', 'active', NOW()),
  (2823, NULL, 'Product #2823 (placeholder)', 'product-2823', 'active', NOW()),
  (2826, NULL, 'Product #2826 (placeholder)', 'product-2826', 'active', NOW()),
  (2830, NULL, 'Product #2830 (placeholder)', 'product-2830', 'active', NOW()),
  (2835, NULL, 'Product #2835 (placeholder)', 'product-2835', 'active', NOW()),
  (2839, NULL, 'Product #2839 (placeholder)', 'product-2839', 'active', NOW()),
  (2844, NULL, 'Product #2844 (placeholder)', 'product-2844', 'active', NOW()),
  (2850, NULL, 'Product #2850 (placeholder)', 'product-2850', 'active', NOW()),
  (2857, NULL, 'Product #2857 (placeholder)', 'product-2857', 'active', NOW()),
  (2861, NULL, 'Product #2861 (placeholder)', 'product-2861', 'active', NOW()),
  (2871, NULL, 'Product #2871 (placeholder)', 'product-2871', 'active', NOW()),
  (2915, NULL, 'Product #2915 (placeholder)', 'product-2915', 'active', NOW()),
  (2920, NULL, 'Product #2920 (placeholder)', 'product-2920', 'active', NOW()),
  (2924, NULL, 'Product #2924 (placeholder)', 'product-2924', 'active', NOW()),
  (2933, NULL, 'Product #2933 (placeholder)', 'product-2933', 'active', NOW()),
  (2938, NULL, 'Product #2938 (placeholder)', 'product-2938', 'active', NOW()),
  (2943, NULL, 'Product #2943 (placeholder)', 'product-2943', 'active', NOW()),
  (2948, NULL, 'Product #2948 (placeholder)', 'product-2948', 'active', NOW()),
  (2952, NULL, 'Product #2952 (placeholder)', 'product-2952', 'active', NOW()),
  (2956, NULL, 'Product #2956 (placeholder)', 'product-2956', 'active', NOW()),
  (2961, NULL, 'Product #2961 (placeholder)', 'product-2961', 'active', NOW()),
  (2967, NULL, 'Product #2967 (placeholder)', 'product-2967', 'active', NOW()),
  (2972, NULL, 'Product #2972 (placeholder)', 'product-2972', 'active', NOW()),
  (2977, NULL, 'Product #2977 (placeholder)', 'product-2977', 'active', NOW()),
  (2982, NULL, 'Product #2982 (placeholder)', 'product-2982', 'active', NOW()),
  (2988, NULL, 'Product #2988 (placeholder)', 'product-2988', 'active', NOW()),
  (3012, NULL, 'Product #3012 (placeholder)', 'product-3012', 'active', NOW()),
  (3017, NULL, 'Product #3017 (placeholder)', 'product-3017', 'active', NOW()),
  (3022, NULL, 'Product #3022 (placeholder)', 'product-3022', 'active', NOW()),
  (3044, NULL, 'Product #3044 (placeholder)', 'product-3044', 'active', NOW()),
  (3048, NULL, 'Product #3048 (placeholder)', 'product-3048', 'active', NOW()),
  (3052, NULL, 'Product #3052 (placeholder)', 'product-3052', 'active', NOW()),
  (3061, NULL, 'Product #3061 (placeholder)', 'product-3061', 'active', NOW()),
  (3064, NULL, 'Product #3064 (placeholder)', 'product-3064', 'active', NOW()),
  (3070, NULL, 'Product #3070 (placeholder)', 'product-3070', 'active', NOW()),
  (3073, NULL, 'Product #3073 (placeholder)', 'product-3073', 'active', NOW()),
  (3301, NULL, 'Product #3301 (placeholder)', 'product-3301', 'active', NOW()),
  (3304, NULL, 'Product #3304 (placeholder)', 'product-3304', 'active', NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ---------------------------------------------------------------------------
-- 2. Codes (45 unique after dedupe)
-- Each row resolves product_id via subquery from wp_product_id.
-- ---------------------------------------------------------------------------
INSERT INTO `codes`
  (`code`, `code_normalized`, `product_id`, `batch_number`, `expiry_date`, `mode`, `status`, `scan_count`, `created_at`)
VALUES
  ('72987923492',     '72987923492',     (SELECT id FROM products WHERE wp_product_id=2387 LIMIT 1), 'BATCH-02-26-627',  '2028-05-27', 'universal', 'active', 2, '2026-03-14 13:41:20'),
  ('725273453536',    '725273453536',    (SELECT id FROM products WHERE wp_product_id=2779 LIMIT 1), 'BATCH-11-25-3456', '2027-03-10', 'universal', 'active', 4, '2026-03-14 14:07:31'),
  ('790987293423',    '790987293423',    (SELECT id FROM products WHERE wp_product_id=3017 LIMIT 1), 'BATCH-08-25-98769','2028-02-08', 'universal', 'active', 1, '2026-03-14 14:28:12'),
  ('7909872934564',   '7909872934564',   (SELECT id FROM products WHERE wp_product_id=3022 LIMIT 1), 'BATCH-09-25-2345', '2028-02-10', 'universal', 'active', 4, '2026-03-14 14:36:03'),
  ('79098729567343',  '79098729567343',  (SELECT id FROM products WHERE wp_product_id=3012 LIMIT 1), 'BATCH-02-26-6324', '2028-01-25', 'universal', 'active', 6, '2026-03-14 14:40:20'),
  ('9780256753455',   '9780256753455',   (SELECT id FROM products WHERE wp_product_id=2977 LIMIT 1), NULL,                NULL,         'universal', 'active', 1, '2026-03-14 14:54:37'),
  ('9780944495561',   '9780944495561',   (SELECT id FROM products WHERE wp_product_id=2943 LIMIT 1), 'BATCH-11-25-3453', '2028-02-22', 'universal', 'active', 0, '2026-03-14 15:08:32'),
  ('9780202345345',   '9780202345345',   (SELECT id FROM products WHERE wp_product_id=2830 LIMIT 1), 'BATCH-11-25-34586','2028-08-20', 'universal', 'active', 5, '2026-03-14 15:14:35'),
  ('9784565678539',   '9784565678539',   (SELECT id FROM products WHERE wp_product_id=2839 LIMIT 1), 'BATCH-02-26-324',  '2028-04-11', 'universal', 'active', 1, '2026-03-14 15:19:38'),
  ('897324580034',    '897324580034',    (SELECT id FROM products WHERE wp_product_id=2988 LIMIT 1), 'BATCH-11-25-76',   '2027-11-22', 'universal', 'active', 0, '2026-03-14 15:31:18'),
  ('9780234345238',   '9780234345238',   (SELECT id FROM products WHERE wp_product_id=2982 LIMIT 1), 'BATCH-11-25-23534','2027-03-15', 'universal', 'active', 1, '2026-03-14 17:31:55'),
  ('982394234890',    '982394234890',    (SELECT id FROM products WHERE wp_product_id=3044 LIMIT 1), 'BATCH-11-25-3456', '2028-02-08', 'universal', 'active', 9, '2026-03-14 17:45:45'),
  ('79098729567333',  '79098729567333',  (SELECT id FROM products WHERE wp_product_id=3048 LIMIT 1), NULL,                '2028-01-25', 'universal', 'active', 0, '2026-03-14 18:10:21'),
  ('725678343356',    '725678343356',    (SELECT id FROM products WHERE wp_product_id=2972 LIMIT 1), 'BATCH-11-25-334',  '2028-07-28', 'universal', 'active', 0, '2026-03-14 18:38:40'),
  ('9780949495561',   '9780949495561',   (SELECT id FROM products WHERE wp_product_id=2967 LIMIT 1), 'BATCH-02-26-627',  '2028-02-22', 'universal', 'active', 3, '2026-03-14 18:44:23'),
  ('725277456328',    '725277456328',    (SELECT id FROM products WHERE wp_product_id=2956 LIMIT 1), 'BATCH-11-25-3456', '2027-06-15', 'universal', 'active', 0, '2026-03-15 11:51:33'),
  ('9788766567678',   '9788766567678',   (SELECT id FROM products WHERE wp_product_id=2952 LIMIT 1), 'BATCH-08-25-252',  '2028-06-18', 'universal', 'active', 0, '2026-03-15 11:53:03'),
  ('457667893450',    '457667893450',    (SELECT id FROM products WHERE wp_product_id=2948 LIMIT 1), 'BATCH-02-26-632',  '2028-05-13', 'universal', 'active', 4, '2026-03-15 11:54:29'),
  ('9745643534673',   '9745643534673',   (SELECT id FROM products WHERE wp_product_id=2938 LIMIT 1), 'BATCH-11-25-34562','2028-06-19', 'universal', 'active', 0, '2026-03-15 13:19:30'),
  ('9780944595561',   '9780944595561',   (SELECT id FROM products WHERE wp_product_id=2933 LIMIT 1), 'BATCH-08-25-9829', '2028-04-27', 'universal', 'active', 0, '2026-03-15 13:21:36'),
  ('98629734657',     '98629734657',     (SELECT id FROM products WHERE wp_product_id=2924 LIMIT 1), 'BATCH-11-25-3344', '2028-05-22', 'universal', 'active', 0, '2026-03-15 13:22:43'),
  ('9780944565561',   '9780944565561',   (SELECT id FROM products WHERE wp_product_id=2920 LIMIT 1), 'BATCH-02-26-627',  '2028-04-27', 'universal', 'active', 0, '2026-03-15 13:24:56'),
  ('725678343456',    '725678343456',    (SELECT id FROM products WHERE wp_product_id=2830 LIMIT 1), 'BATCH-11-25-34532','2028-07-28', 'universal', 'active', 0, '2026-03-15 13:26:31'),
  ('9780067853',      '9780067853',      (SELECT id FROM products WHERE wp_product_id=2861 LIMIT 1), 'BATCH-02-26-876',  '2028-04-02', 'universal', 'active', 0, '2026-03-15 13:30:06'),
  ('9780166456460',   '9780166456460',   (SELECT id FROM products WHERE wp_product_id=3052 LIMIT 1), 'BATCH-11-25-3456', '2028-04-17', 'universal', 'active', 0, '2026-03-16 14:07:22'),
  ('729879234926',    '729879234926',    (SELECT id FROM products WHERE wp_product_id=2387 LIMIT 1), 'BATCH-02-26-627',  '2028-05-27', 'universal', 'active', 8, '2026-03-20 11:34:12'),
  ('9780234123133',   '9780234123133',   (SELECT id FROM products WHERE wp_product_id=3061 LIMIT 1), 'BATCH-11-25-3456', '2028-01-01', 'universal', 'active', 0, '2026-03-28 18:57:55'),
  ('9780445773257',   '9780445773257',   (SELECT id FROM products WHERE wp_product_id=2809 LIMIT 1), 'BATCH-02-26-3453', '2028-05-22', 'universal', 'active', 0, '2026-03-28 19:44:37'),
  ('9780123346438',   '9780123346438',   (SELECT id FROM products WHERE wp_product_id=2826 LIMIT 1), 'BATCH-11-25-23',   '2028-08-20', 'universal', 'active', 1, '2026-03-28 19:46:21'),
  ('9780167863458',   '9780167863458',   (SELECT id FROM products WHERE wp_product_id=2835 LIMIT 1), 'BATCH-11-25-5454', '2028-08-02', 'universal', 'active', 0, '2026-03-28 19:48:50'),
  ('9780945656739',   '9780945656739',   (SELECT id FROM products WHERE wp_product_id=2844 LIMIT 1), 'BATCH-11-25-5454', '2028-04-23', 'universal', 'active', 0, '2026-03-28 19:50:50'),
  ('9780944345641',   '9780944345641',   (SELECT id FROM products WHERE wp_product_id=2850 LIMIT 1), 'BATCH-02-26-432',  '2028-04-27', 'universal', 'active', 0, '2026-03-28 19:58:17'),
  ('9780203453223',   '9780203453223',   (SELECT id FROM products WHERE wp_product_id=2857 LIMIT 1), 'BATCH-02-26-432',  '2028-06-11', 'universal', 'active', 2, '2026-03-28 19:58:58'),
  ('867923478923',    '867923478923',    (SELECT id FROM products WHERE wp_product_id=2871 LIMIT 1), 'BATCH-11-25-2343', '2027-03-10', 'universal', 'active', 0, '2026-03-28 20:05:57'),
  ('986297355657',    '986297355657',    (SELECT id FROM products WHERE wp_product_id=2915 LIMIT 1), 'BATCH-11-25-2345', '2028-05-22', 'universal', 'active', 0, '2026-03-28 20:06:36'),
  ('986297355655',    '986297355655',    (SELECT id FROM products WHERE wp_product_id=2961 LIMIT 1), 'BATCH-11-25-33233','2028-05-22', 'universal', 'active', 0, '2026-03-28 20:23:22'),
  ('9780234534342',   '9780234534342',   (SELECT id FROM products WHERE wp_product_id=2982 LIMIT 1), 'BATCH-11-25-34RT', '2027-03-15', 'universal', 'active', 0, '2026-03-28 20:27:45'),
  ('7909872923564',   '7909872923564',   (SELECT id FROM products WHERE wp_product_id=3064 LIMIT 1), 'BATCH-11-25-34AT', '2028-02-11', 'universal', 'active', 1, '2026-03-28 20:35:37'),
  ('4576789567',      '4576789567',      (SELECT id FROM products WHERE wp_product_id=3070 LIMIT 1), 'BATCH-11-25-3453', '2028-01-25', 'universal', 'active', 0, '2026-03-30 23:49:24'),
  ('456733568756',    '456733568756',    (SELECT id FROM products WHERE wp_product_id=3073 LIMIT 1), NULL,                '2028-02-09', 'universal', 'active', 0, '2026-03-30 23:53:03'),
  ('982394234899',    '982394234899',    (SELECT id FROM products WHERE wp_product_id=3301 LIMIT 1), 'BATCH-11-25-3456', '2028-02-08', 'universal', 'active', 0, '2026-05-12 15:00:34'),
  ('2567834335',      '2567834335',      (SELECT id FROM products WHERE wp_product_id=3304 LIMIT 1), 'BATCH-08-25-98769','2028-07-28', 'universal', 'active', 1, '2026-05-12 15:04:38');

-- ---------------------------------------------------------------------------
-- 3. Scan logs (legacy events, mapped to new result enum)
-- Old plugin used result='universal' for valid universal codes, 'fail' for invalid.
-- ---------------------------------------------------------------------------
INSERT INTO `scan_logs`
  (`code_input`, `code_normalized`, `code_id`, `product_id`, `result`, `ip_address`, `ip_country_name`, `ip_city`, `user_agent`, `scanned_at`)
VALUES
  ('79098729567343', '79098729567343', (SELECT id FROM codes WHERE code_normalized='79098729567343'), (SELECT product_id FROM codes WHERE code_normalized='79098729567343'), 'valid_universal', '103.127.71.117', 'Bangladesh', 'Bagerhat', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-03-14 21:44:13'),
  ('123456', '123456', NULL, NULL, 'invalid', '2404:1c40:169:dab7:189c:bdc5:2ad9:2d14', 'Bangladesh', 'Dhaka', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-14 23:25:01'),
  ('725273453536', '725273453536', (SELECT id FROM codes WHERE code_normalized='725273453536'), (SELECT product_id FROM codes WHERE code_normalized='725273453536'), 'valid_universal', '103.59.38.205', 'Bangladesh', 'Gazipur', 'Mozilla/5.0 (Linux; Android 10; K) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-15 23:40:58'),
  ('9780166456460', '9780166456460', NULL, NULL, 'invalid', '2400:c600:451f:5c52:1:0:5ae9:4837', 'Bangladesh', 'Dhaka', 'WeChat MicroMessenger', '2026-03-16 16:58:37'),
  ('725273453536', '725273453536', (SELECT id FROM codes WHERE code_normalized='725273453536'), (SELECT product_id FROM codes WHERE code_normalized='725273453536'), 'valid_universal', '27.147.242.97', 'Bangladesh', 'Cox''s Bazar', 'Mozilla/5.0 (Linux; Android 10; K) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-03-17 17:46:57'),
  ('729879234926', '729879234926', NULL, NULL, 'invalid', '103.20.180.168', 'Bangladesh', 'Dhaka', 'Mozilla/5.0 (Linux; Android 10; K) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-03-19 12:04:00'),
  ('9780949495561', '9780949495561', (SELECT id FROM codes WHERE code_normalized='9780949495561'), (SELECT product_id FROM codes WHERE code_normalized='9780949495561'), 'valid_universal', '81.161.88.191', 'Oman', 'Muscat', 'Mozilla/5.0 (Linux; Android 10; K) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-03-25 03:00:15'),
  ('79098729567343', '79098729567343', (SELECT id FROM codes WHERE code_normalized='79098729567343'), (SELECT product_id FROM codes WHERE code_normalized='79098729567343'), 'valid_universal', '2404:1c40:1c5:8b5a:18a0:2f9e:e9b6:b26c', 'Bangladesh', 'Dhaka', 'Mozilla/5.0 (Linux; Android 15) MiuiBrowser', '2026-03-26 14:30:56'),
  ('9780234123133', '9780234123133', NULL, NULL, 'invalid', '103.197.153.201', 'Bangladesh', 'Dhaka', 'Chrome/146 Mobile', '2026-03-28 17:55:16'),
  ('2987923492', '2987923492', NULL, NULL, 'invalid', '103.103.89.133', 'Bangladesh', 'Chittagong', 'Chrome/146 Mobile', '2026-03-30 11:08:08'),
  ('9780949495561', '9780949495561', (SELECT id FROM codes WHERE code_normalized='9780949495561'), (SELECT product_id FROM codes WHERE code_normalized='9780949495561'), 'valid_universal', '103.166.59.220', 'Bangladesh', 'Jhingergācha', 'Chrome/146 Mobile', '2026-03-31 01:57:51'),
  ('729879234926', '729879234926', (SELECT id FROM codes WHERE code_normalized='729879234926'), (SELECT product_id FROM codes WHERE code_normalized='729879234926'), 'valid_universal', '203.112.194.21', 'Bangladesh', 'Dhaka', 'Chrome/146 Mobile', '2026-03-31 12:02:58'),
  ('7909872956343', '7909872956343', NULL, NULL, 'invalid', '202.134.10.141', 'Bangladesh', 'Dhaka', 'MiuiBrowser', '2026-03-31 20:10:16'),
  ('79098729567343', '79098729567343', (SELECT id FROM codes WHERE code_normalized='79098729567343'), (SELECT product_id FROM codes WHERE code_normalized='79098729567343'), 'valid_universal', '202.134.10.141', 'Bangladesh', 'Dhaka', 'MiuiBrowser', '2026-03-31 20:11:12'),
  ('790987295567343', '790987295567343', NULL, NULL, 'invalid', '37.111.211.173', 'Bangladesh', 'Dhaka', 'Chrome Mobile', '2026-04-02 15:26:52'),
  ('79098729567343', '79098729567343', (SELECT id FROM codes WHERE code_normalized='79098729567343'), (SELECT product_id FROM codes WHERE code_normalized='79098729567343'), 'valid_universal', '37.111.211.173', 'Bangladesh', 'Dhaka', 'Chrome Mobile', '2026-04-02 15:28:15'),
  ('SCANME', '', NULL, NULL, 'malformed', '103.112.204.165', 'Bangladesh', 'Jahedpur', 'iPhone Safari', '2026-04-05 00:25:19'),
  ('982394234890', '982394234890', (SELECT id FROM codes WHERE code_normalized='982394234890'), (SELECT product_id FROM codes WHERE code_normalized='982394234890'), 'valid_universal', '103.112.204.165', 'Bangladesh', 'Jahedpur', 'iPhone Safari', '2026-04-05 00:30:01'),
  ('7909872934564', '7909872934564', (SELECT id FROM codes WHERE code_normalized='7909872934564'), (SELECT product_id FROM codes WHERE code_normalized='7909872934564'), 'valid_universal', '113.212.111.196', 'Bangladesh', 'Dhaka', 'Chrome/147 Mobile', '2026-04-08 13:07:43'),
  ('729879234926', '729879234926', (SELECT id FROM codes WHERE code_normalized='729879234926'), (SELECT product_id FROM codes WHERE code_normalized='729879234926'), 'valid_universal', '2400:c600:3561:5f0e:3867:c6ff:fe6e:cade', 'Bangladesh', 'Dhaka', 'Chrome/146 Mobile', '2026-04-08 21:23:36'),
  ('725273453536', '725273453536', (SELECT id FROM codes WHERE code_normalized='725273453536'), (SELECT product_id FROM codes WHERE code_normalized='725273453536'), 'valid_universal', '103.118.78.31', 'Bangladesh', 'Pābna', 'Chrome/146 Mobile', '2026-04-09 15:51:21'),
  ('7909872934564', '7909872934564', (SELECT id FROM codes WHERE code_normalized='7909872934564'), (SELECT product_id FROM codes WHERE code_normalized='7909872934564'), 'valid_universal', '103.155.99.249', 'Bangladesh', 'Azimpur', 'Chrome/145 Mobile', '2026-04-10 19:15:59'),
  ('7909872934564', '7909872934564', (SELECT id FROM codes WHERE code_normalized='7909872934564'), (SELECT product_id FROM codes WHERE code_normalized='7909872934564'), 'valid_universal', '119.15.154.102', 'Bangladesh', 'Dhaka', 'Chrome/146 Mobile', '2026-04-11 12:18:07'),
  ('729879234926', '729879234926', (SELECT id FROM codes WHERE code_normalized='729879234926'), (SELECT product_id FROM codes WHERE code_normalized='729879234926'), 'valid_universal', '103.126.150.134', 'Bangladesh', 'Hājīganj', 'Chrome/146 Mobile', '2026-04-11 17:35:45'),
  ('79098729567343', '79098729567343', (SELECT id FROM codes WHERE code_normalized='79098729567343'), (SELECT product_id FROM codes WHERE code_normalized='79098729567343'), 'valid_universal', '103.31.179.97', 'Bangladesh', 'Pābna', 'iPhone Safari', '2026-04-11 21:15:52'),
  ('46729731280568', '46729731280568', NULL, NULL, 'invalid', '103.31.179.97', 'Bangladesh', 'Pābna', 'iPhone Safari', '2026-04-11 21:17:17'),
  ('982394234890', '982394234890', (SELECT id FROM codes WHERE code_normalized='982394234890'), (SELECT product_id FROM codes WHERE code_normalized='982394234890'), 'valid_universal', '2400:c600:4514:4f29:d97b:fb69:100f:5f17', 'Bangladesh', 'Dhaka', 'Chrome/143 Mobile', '2026-04-12 20:55:39'),
  ('457667893450', '457667893450', (SELECT id FROM codes WHERE code_normalized='457667893450'), (SELECT product_id FROM codes WHERE code_normalized='457667893450'), 'valid_universal', '202.134.11.234', 'Bangladesh', 'Dhaka', 'Chrome/146 Mobile', '2026-04-16 23:37:52'),
  ('72987923492', '72987923492', (SELECT id FROM codes WHERE code_normalized='72987923492'), (SELECT product_id FROM codes WHERE code_normalized='72987923492'), 'valid_universal', '202.134.10.140', 'Bangladesh', 'Dhaka', 'Chrome/147 Mobile', '2026-04-19 14:52:30'),
  ('9780166457860', '9780166457860', NULL, NULL, 'invalid', '103.138.164.209', 'Bangladesh', 'Dhaka', 'Innova30 webview', '2026-04-19 17:27:18'),
  ('9780166457860', '9780166457860', NULL, NULL, 'invalid', '103.138.164.209', 'Bangladesh', 'Dhaka', 'Innova30 webview', '2026-04-19 17:28:59'),
  ('780234345238', '780234345238', NULL, NULL, 'invalid', '2401:1900:806b:69f1::1', 'Bangladesh', 'Savar Upazila', 'Chrome/147 Mobile', '2026-04-19 23:30:49'),
  ('9780234345238', '9780234345238', (SELECT id FROM codes WHERE code_normalized='9780234345238'), (SELECT product_id FROM codes WHERE code_normalized='9780234345238'), 'valid_universal', '2401:1900:806b:69f1::1', 'Bangladesh', 'Savar Upazila', 'Chrome/147 Mobile', '2026-04-19 23:31:44'),
  ('9780203453223', '9780203453223', (SELECT id FROM codes WHERE code_normalized='9780203453223'), (SELECT product_id FROM codes WHERE code_normalized='9780203453223'), 'valid_universal', '103.170.185.48', 'Bangladesh', 'Tongi', 'Chrome/147 Mobile', '2026-04-19 23:55:13'),
  ('729879234926', '729879234926', (SELECT id FROM codes WHERE code_normalized='729879234926'), (SELECT product_id FROM codes WHERE code_normalized='729879234926'), 'valid_universal', '103.149.143.246', 'Bangladesh', 'Dhaka', 'Chrome/147 Mobile', '2026-04-22 11:11:43'),
  ('729879234926', '729879234926', (SELECT id FROM codes WHERE code_normalized='729879234926'), (SELECT product_id FROM codes WHERE code_normalized='729879234926'), 'valid_universal', '103.88.141.40', 'Bangladesh', 'Dhaka', 'Chrome/134 Mobile', '2026-04-22 17:47:30'),
  ('457667893450', '457667893450', (SELECT id FROM codes WHERE code_normalized='457667893450'), (SELECT product_id FROM codes WHERE code_normalized='457667893450'), 'valid_universal', '103.138.31.85', 'Bangladesh', 'Gazipur', 'Chrome/145 Mobile', '2026-04-24 20:50:04'),
  ('2527345353', '2527345353', NULL, NULL, 'invalid', '103.176.249.243', 'Bangladesh', 'Dhaka', 'Chrome/146 Mobile', '2026-04-25 20:55:27'),
  ('457667893450', '457667893450', (SELECT id FROM codes WHERE code_normalized='457667893450'), (SELECT product_id FROM codes WHERE code_normalized='457667893450'), 'valid_universal', '2404:1c40:49c:5551:c45e:cff:fef4:7591', 'Bangladesh', 'Dhaka', 'Chrome/146 Mobile', '2026-04-27 00:08:05'),
  ('982394234890', '982394234890', (SELECT id FROM codes WHERE code_normalized='982394234890'), (SELECT product_id FROM codes WHERE code_normalized='982394234890'), 'valid_universal', '103.81.104.49', 'Bangladesh', 'Khanbaniara', 'HiBrowser Infinix', '2026-04-27 22:23:58'),
  ('982394234890', '982394234890', (SELECT id FROM codes WHERE code_normalized='982394234890'), (SELECT product_id FROM codes WHERE code_normalized='982394234890'), 'valid_universal', '103.81.104.49', 'Bangladesh', 'Khanbaniara', 'HiBrowser Infinix', '2026-04-27 22:25:52'),
  ('SCANME', '', NULL, NULL, 'malformed', '2404:1c40:457:33b0:f9e1:d47b:5729:8f7', 'Bangladesh', 'Dhaka', 'iPhone Safari', '2026-04-28 11:09:24'),
  ('2527345353', '2527345353', NULL, NULL, 'invalid', '2400:c600:5408:2aa0:8ec4:6760:1c37:5c34', 'Bangladesh', 'Naogaon', 'Chrome/143 Mobile', '2026-04-29 12:44:10'),
  ('HTTPSWWWFACEBOOKCOMSHARER', '', NULL, NULL, 'malformed', '182.252.69.66', 'Bangladesh', 'Dhaka', 'Firefox/150', '2026-05-05 09:39:25'),
  ('HTTPSELHOECOMCHECKER', '', NULL, NULL, 'malformed', '182.252.69.66', 'Bangladesh', 'Dhaka', 'Firefox/150', '2026-05-05 09:40:55'),
  ('97802034', '97802034', NULL, NULL, 'invalid', '103.186.21.54', 'Bangladesh', 'Natore', 'Chrome/138 Mobile', '2026-05-06 11:07:48'),
  ('9780203453223', '9780203453223', (SELECT id FROM codes WHERE code_normalized='9780203453223'), (SELECT product_id FROM codes WHERE code_normalized='9780203453223'), 'valid_universal', '103.186.21.54', 'Bangladesh', 'Natore', 'Chrome/138 Mobile', '2026-05-06 11:07:59'),
  ('982394234890', '982394234890', (SELECT id FROM codes WHERE code_normalized='982394234890'), (SELECT product_id FROM codes WHERE code_normalized='982394234890'), 'valid_universal', '2400:c600:4722:7acd:46d:98ff:fe3d:d180', 'Bangladesh', 'Dhaka', 'Chrome/147 Mobile', '2026-05-06 13:32:49'),
  ('978020235345', '978020235345', NULL, NULL, 'invalid', '103.99.250.212', 'Bangladesh', 'Feni', 'SamsungBrowser/29', '2026-05-06 21:35:17'),
  ('9780202345345', '9780202345345', (SELECT id FROM codes WHERE code_normalized='9780202345345'), (SELECT product_id FROM codes WHERE code_normalized='9780202345345'), 'valid_universal', '103.99.250.212', 'Bangladesh', 'Feni', 'SamsungBrowser/29', '2026-05-06 21:36:27'),
  ('ELHOEINC', '', NULL, NULL, 'malformed', '2404:1c40:73:7d4:9c0d:2a25:cf90:21fb', 'Bangladesh', 'Muktāgācha', 'iPhone Safari', '2026-05-07 10:51:23'),
  ('29879234926', '29879234926', NULL, NULL, 'invalid', '103.111.225.242', 'Bangladesh', 'Chittagong', 'Chrome/147 Mobile', '2026-05-09 16:41:52'),
  ('2987923492', '2987923492', NULL, NULL, 'invalid', '103.111.225.242', 'Bangladesh', 'Chittagong', 'Chrome/147 Mobile', '2026-05-09 16:42:34'),
  ('729879234926', '729879234926', (SELECT id FROM codes WHERE code_normalized='729879234926'), (SELECT product_id FROM codes WHERE code_normalized='729879234926'), 'valid_universal', '103.111.225.242', 'Bangladesh', 'Chittagong', 'Chrome/147 Mobile', '2026-05-09 16:43:50'),
  ('729879234926', '729879234926', (SELECT id FROM codes WHERE code_normalized='729879234926'), (SELECT product_id FROM codes WHERE code_normalized='729879234926'), 'valid_universal', '103.154.98.117', 'Bangladesh', 'Maijdi', 'Chrome/147 Mobile', '2026-05-09 22:09:17'),
  ('982394234890', '982394234890', (SELECT id FROM codes WHERE code_normalized='982394234890'), (SELECT product_id FROM codes WHERE code_normalized='982394234890'), 'valid_universal', '37.111.222.58', 'Bangladesh', 'Dhaka', 'iPhone Safari', '2026-05-11 14:39:36'),
  ('982394234899', '982394234899', (SELECT id FROM codes WHERE code_normalized='982394234899'), (SELECT product_id FROM codes WHERE code_normalized='982394234899'), 'valid_universal', '37.111.222.58', 'Bangladesh', 'Dhaka', 'iPhone Safari', '2026-05-11 14:41:33'),
  ('982394234899', '982394234899', (SELECT id FROM codes WHERE code_normalized='982394234899'), (SELECT product_id FROM codes WHERE code_normalized='982394234899'), 'valid_universal', '37.111.222.58', 'Bangladesh', 'Dhaka', 'iPhone Safari', '2026-05-11 14:44:53'),
  ('3452332434', '3452332434', NULL, NULL, 'invalid', '103.96.105.207', 'Bangladesh', 'Dhaka', 'Windows Chrome/148', '2026-05-12 20:52:46'),
  ('2567834335', '2567834335', (SELECT id FROM codes WHERE code_normalized='2567834335'), (SELECT product_id FROM codes WHERE code_normalized='2567834335'), 'valid_universal', '103.96.105.207', 'Bangladesh', 'Dhaka', 'Windows Chrome/148', '2026-05-12 21:05:58'),
  ('7909872923564', '7909872923564', (SELECT id FROM codes WHERE code_normalized='7909872923564'), (SELECT product_id FROM codes WHERE code_normalized='7909872923564'), 'valid_universal', '114.130.157.95', 'Bangladesh', 'Dhaka', 'Chrome/148 Mobile', '2026-05-14 13:15:40'),
  ('982394234890', '982394234890', (SELECT id FROM codes WHERE code_normalized='982394234890'), (SELECT product_id FROM codes WHERE code_normalized='982394234890'), 'valid_universal', '2400:c600:5408:fa61:bc96:33ff:febd:f53d', 'Bangladesh', 'Naogaon', 'Chrome/148 Mobile', '2026-05-15 21:40:09'),
  ('9780202345345', '9780202345345', (SELECT id FROM codes WHERE code_normalized='9780202345345'), (SELECT product_id FROM codes WHERE code_normalized='9780202345345'), 'valid_universal', '103.126.150.170', 'Bangladesh', 'Dhaka', 'Chrome/148 Mobile', '2026-05-18 16:29:46'),
  ('9780123346438', '9780123346438', (SELECT id FROM codes WHERE code_normalized='9780123346438'), (SELECT product_id FROM codes WHERE code_normalized='9780123346438'), 'valid_universal', '2401:f40:1117:7:81d6:6473:6d05:c5a2', 'Bangladesh', 'Dhaka', 'iPhone CriOS/148', '2026-05-20 07:21:48'),
  ('9780202345345', '9780202345345', (SELECT id FROM codes WHERE code_normalized='9780202345345'), (SELECT product_id FROM codes WHERE code_normalized='9780202345345'), 'valid_universal', '2400:c600:452c:6f5d:1:0:7356:f2e9', 'Bangladesh', 'Munshiganj', 'Chrome/148 Mobile', '2026-05-20 21:02:35'),
  ('25273', '25273', NULL, NULL, 'malformed', '37.111.192.145', 'Bangladesh', 'Dhaka', 'iPhone Safari', '2026-05-20 23:22:45'),
  ('987293478802', '987293478802', NULL, NULL, 'invalid', '2404:1c40:4a3:e007:f3fa:571b:ef16:77ab', 'Bangladesh', 'Dhaka', 'Chrome/148 Mobile', '2026-05-21 11:56:45'),
  ('987293478802', '987293478802', NULL, NULL, 'invalid', '2404:1c40:49e:cf33:1b5:3bb4:a590:30f5', 'Bangladesh', 'Dhaka', 'Chrome/148 Mobile', '2026-05-21 12:00:15'),
  ('987293478802', '987293478802', NULL, NULL, 'invalid', '103.138.145.113', 'Bangladesh', 'Kishorganj', 'Chrome/148 Mobile', '2026-05-21 12:31:48'),
  ('987293478802', '987293478802', NULL, NULL, 'invalid', '103.138.145.113', 'Bangladesh', 'Kishorganj', 'Chrome/148 Mobile', '2026-05-21 12:33:52'),
  ('982394234890', '982394234890', (SELECT id FROM codes WHERE code_normalized='982394234890'), (SELECT product_id FROM codes WHERE code_normalized='982394234890'), 'valid_universal', '2400:c600:5483:c52:d07e:590a:8e20:4763', 'Bangladesh', 'Dhaka', 'Chrome/148 Mobile', '2026-05-21 14:35:44'),
  ('45353', '45353', NULL, NULL, 'malformed', '103.14.72.77', 'Bangladesh', 'Kālīganj', 'Chrome/148 Mobile', '2026-05-23 08:24:45'),
  ('9780202345345', '9780202345345', (SELECT id FROM codes WHERE code_normalized='9780202345345'), (SELECT product_id FROM codes WHERE code_normalized='9780202345345'), 'valid_universal', '121.200.221.96', 'Bangladesh', 'Khanbaniara', 'Chrome/148 Mobile', '2026-05-23 13:26:02'),
  ('HTTPSELHOECOMCHECKER', '', NULL, NULL, 'malformed', '203.223.89.165', 'Bangladesh', 'Tongi', 'Chrome/148 Mobile', '2026-05-25 11:39:31'),
  ('729879234926', '729879234926', (SELECT id FROM codes WHERE code_normalized='729879234926'), (SELECT product_id FROM codes WHERE code_normalized='729879234926'), 'valid_universal', '188.54.147.104', 'Saudi Arabia', 'Dammam', 'Chrome/148 Mobile', '2026-05-25 12:34:10'),
  ('457667893450', '457667893450', (SELECT id FROM codes WHERE code_normalized='457667893450'), (SELECT product_id FROM codes WHERE code_normalized='457667893450'), 'valid_universal', '43.246.202.70', 'Bangladesh', 'Maijdi', 'Chrome/148 Mobile', '2026-05-29 20:22:55');

-- ---------------------------------------------------------------------------
-- 4. Recompute first_scanned_at and last_scanned_at on codes table
-- (so risk scoring has accurate timestamps)
-- ---------------------------------------------------------------------------
UPDATE codes c
INNER JOIN (
    SELECT code_id,
           MIN(scanned_at) AS first_at,
           MAX(scanned_at) AS last_at
    FROM scan_logs
    WHERE code_id IS NOT NULL
    GROUP BY code_id
) s ON s.code_id = c.id
SET c.first_scanned_at = s.first_at,
    c.last_scanned_at  = s.last_at
WHERE c.first_scanned_at IS NULL OR c.last_scanned_at IS NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- IMPORT COMPLETE
--   - 40 placeholder products (replace details via Admin → Products)
--   - 42 codes
--   - ~80 scan logs
--
-- NEXT STEPS:
--   1. Edit each placeholder product with proper name, image_url, product_url.
--   2. Or upload products-import.csv with matching wp_product_id values.
--   3. Verify dashboard KPIs reflect imported history.
-- ============================================================================
