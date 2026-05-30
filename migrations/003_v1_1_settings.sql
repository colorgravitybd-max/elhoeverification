-- ============================================================================
-- ELHOE Verification - v1.1 settings additions
--
-- IDEMPOTENT - safe to import multiple times. Adds:
--   * Distributor / contact info shown on customer page
--   * Email notification settings (scan alerts)
--   * SMTP settings (optional - falls back to PHP mail())
--   * Brand "premium" toggle (subtle gold accent on customer page)
--
-- After importing this file, configure values via:
--   Admin -> Settings -> General  (distributor + premium toggle)
--   Admin -> Settings -> Email    (notifications + SMTP)
-- ============================================================================

INSERT INTO `settings` (`key_name`, `value`) VALUES
  -- Distributor block on customer-facing /checker page
  ('distributor_enabled',  '1'),
  ('distributor_name',     'ELHOE'),
  ('distributor_address',  'ELHOE Inc. Uttara, 1230, Uttara Sector 7, Dhaka - North, Dhaka, Bangladesh'),
  ('distributor_email',    'bd@elhoe.com'),
  ('distributor_phone',    '+8801990800951'),
  ('distributor_whatsapp', '+8801990800951'),
  ('distributor_country',  'Bangladesh'),

  -- Premium theme variant
  ('brand_premium_mode',   '1'),
  ('brand_gold_color',     '#B49A6A'),

  -- Email notification settings
  ('email_notify_enabled',          '1'),
  ('email_notify_recipient',        ''),                -- comma separated list, fallback admin_alert_email
  ('email_notify_from_name',        'ELHOE Verification'),
  ('email_notify_from_address',     'no-reply@elhoe.com'),
  ('email_notify_on_valid',         '1'),               -- send email on genuine scan
  ('email_notify_on_invalid',       '1'),               -- send email on counterfeit / failed scan
  ('email_notify_on_quarantine',    '1'),               -- send email when a code auto-quarantines
  ('email_notify_on_registration',  '1'),               -- send email when customer registers
  ('email_notify_max_per_hour',     '60'),              -- safety cap (Hostinger limit ~100/hr)

  -- SMTP transport (optional - leave blank to use PHP mail())
  ('smtp_enabled',  '0'),
  ('smtp_host',     'smtp.hostinger.com'),
  ('smtp_port',     '465'),
  ('smtp_username', ''),
  ('smtp_password', ''),
  ('smtp_secure',   'ssl')                              -- ssl | tls | ''
ON DUPLICATE KEY UPDATE `value` = `settings`.`value`;   -- DO NOT overwrite existing values
