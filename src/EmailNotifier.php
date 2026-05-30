<?php
declare(strict_types=1);

namespace Elhoe;

/**
 * Builds and dispatches scan notification emails to admin.
 *
 * Hooked from VerifyService after every scan + after registrations.
 * Throttled by `email_notify_max_per_hour` (rate limited per-hour, all events).
 */
final class EmailNotifier
{
    /**
     * Notify admin about a scan event.
     *
     * @param array $context Keys:
     *   result       - scan result enum
     *   code_input   - raw user input
     *   code         - matched code row (or null)
     *   product      - product row (or null)
     *   ip, city, country, user_agent
     */
    public static function notifyScan(array $context): void
    {
        try {
            if (Settings::get('email_notify_enabled', '0') !== '1') return;

            $result = (string) ($context['result'] ?? '');
            $isOk     = in_array($result, ['valid_universal','valid_unique_first','valid_unique_returning','already_registered'], true);
            $isInvalid    = in_array($result, ['invalid','malformed','rate_limited'], true);
            $isQuarantine = $result === 'quarantined';

            if ($isOk     && Settings::get('email_notify_on_valid', '0')   !== '1') return;
            if ($isInvalid && Settings::get('email_notify_on_invalid', '0') !== '1') return;
            if ($isQuarantine && Settings::get('email_notify_on_quarantine', '0') !== '1') return;

            if (!self::withinRateLimit()) {
                Logger::warning('EmailNotifier: hourly cap reached, skipping');
                return;
            }

            $recipients = self::resolveRecipients();
            if (!$recipients) return;

            [$subject, $html] = self::renderScanEmail($context);

            $r = Mailer::send($recipients, $subject, $html);
            if (!$r['ok']) {
                Logger::error('EmailNotifier scan send failed', ['err' => $r['error']]);
            }
        } catch (\Throwable $e) {
            Logger::error('EmailNotifier::notifyScan crashed', ['err' => $e->getMessage()]);
        }
    }

    /** Notify admin about a new customer registration (unique mode). */
    public static function notifyRegistration(array $context): void
    {
        try {
            if (Settings::get('email_notify_enabled',         '0') !== '1') return;
            if (Settings::get('email_notify_on_registration', '0') !== '1') return;
            if (!self::withinRateLimit()) return;

            $recipients = self::resolveRecipients();
            if (!$recipients) return;

            [$subject, $html] = self::renderRegistrationEmail($context);
            $r = Mailer::send($recipients, $subject, $html);
            if (!$r['ok']) {
                Logger::error('EmailNotifier reg send failed', ['err' => $r['error']]);
            }
        } catch (\Throwable $e) {
            Logger::error('EmailNotifier::notifyRegistration crashed', ['err' => $e->getMessage()]);
        }
    }

    // -----------------------------------------------------------------
    // Resolve recipients
    // -----------------------------------------------------------------
    private static function resolveRecipients(): array
    {
        $list = (string) Settings::get('email_notify_recipient', '');
        if ($list === '') {
            $list = (string) Settings::get('admin_alert_email', '');
        }
        if ($list === '') return [];
        $emails = array_map('trim', explode(',', $list));
        return array_values(array_filter($emails, fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
    }

    // -----------------------------------------------------------------
    // Rate limit (per-hour cap, ALL events combined)
    // -----------------------------------------------------------------
    private static function withinRateLimit(): bool
    {
        $cap = (int) Settings::get('email_notify_max_per_hour', 60);
        if ($cap <= 0) return false;
        $r = RateLimiter::hit('system', 'email_notify', $cap, 3600);
        return (bool) $r['allowed'];
    }

    // -----------------------------------------------------------------
    // Templates
    // -----------------------------------------------------------------
    private static function renderScanEmail(array $ctx): array
    {
        $result   = (string) ($ctx['result'] ?? 'unknown');
        $code     = $ctx['code']      ?? null;
        $product  = $ctx['product']   ?? null;
        $codeIn   = (string) ($ctx['code_input'] ?? '');
        $ip       = (string) ($ctx['ip']        ?? '');
        $city     = (string) ($ctx['city']      ?? '');
        $country  = (string) ($ctx['country']   ?? '');
        $ua       = (string) ($ctx['user_agent'] ?? '');

        $isOk = in_array($result, ['valid_universal','valid_unique_first','valid_unique_returning','already_registered'], true);
        $isQuar = $result === 'quarantined';

        $tag = $isQuar ? '⚠ Quarantined'
             : ($isOk ? '✓ Genuine'
             : '✗ Failed');
        $color = $isQuar ? '#C97B3F' : ($isOk ? '#5B8C5A' : '#A94442');

        $subject = sprintf('[ELHOE Verify] %s scan%s%s',
            $tag,
            $product ? ' - ' . $product['name'] : '',
            $city ? ' (' . $city . ')' : ''
        );

        $brand = Settings::get('brand_name', 'ELHOE');
        $appUrl = rtrim((string) env('APP_URL', ''), '/');

        $html = self::wrapEmail($brand, '
            <h2 style="color:' . e($color) . ';margin:0 0 16px;font-family:Georgia,serif;font-size:22px">' . e($tag) . ' Scan</h2>
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 18px;border-collapse:collapse">
              ' . self::row('Result', $result) . '
              ' . self::row('Product', $product ? ($product['name'] ?? '-') . (isset($product['sku']) && $product['sku'] ? ' (' . $product['sku'] . ')' : '') : '-') . '
              ' . self::row('Code Entered', $codeIn) . '
              ' . self::row('Matched Code', $code['code'] ?? '-') . '
              ' . self::row('Batch', $code['batch_number'] ?? '-') . '
              ' . self::row('Mode', $code['mode'] ?? '-') . '
              ' . self::row('IP', $ip) . '
              ' . self::row('Location', trim($city . ', ' . $country, ', ')) . '
              ' . self::row('Time', date('Y-m-d H:i:s T')) . '
              ' . self::row('Device', mb_substr($ua, 0, 80)) . '
            </table>
            ' . ($code && !empty($code['id']) ? '
            <p style="margin:16px 0 0">
              <a href="' . e($appUrl) . '/admin/codes/edit.php?id=' . (int) $code['id'] . '"
                 style="background:#3E5641;color:#fff;text-decoration:none;padding:10px 18px;border-radius:999px;font-weight:600;font-size:13px;display:inline-block">
                Inspect this code
              </a>
            </p>' : '') . '
        ');

        return [$subject, $html];
    }

    private static function renderRegistrationEmail(array $ctx): array
    {
        $customer = $ctx['customer'] ?? [];
        $product  = $ctx['product']  ?? null;
        $code     = $ctx['code']     ?? null;

        $name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?: '(no name)';
        $email = (string) ($customer['email'] ?? '');

        $subject = '[ELHOE Verify] New customer registration: ' . $name;
        $brand = Settings::get('brand_name', 'ELHOE');
        $appUrl = rtrim((string) env('APP_URL', ''), '/');

        $html = self::wrapEmail($brand, '
            <h2 style="color:#5B8C5A;margin:0 0 16px;font-family:Georgia,serif;font-size:22px">New Customer Registration</h2>
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 18px;border-collapse:collapse">
              ' . self::row('Name', $name) . '
              ' . self::row('Email', $email ? '<a href="mailto:' . e($email) . '">' . e($email) . '</a>' : '-') . '
              ' . self::row('Phone', $customer['phone'] ?? '-') . '
              ' . self::row('City', $customer['city'] ?? '-') . '
              ' . self::row('Marketing Opt-in', !empty($customer['consent_marketing']) ? 'Yes' : 'No') . '
              ' . self::row('Product', $product ? $product['name'] ?? '-' : '-') . '
              ' . self::row('Code', $code['code'] ?? '-') . '
              ' . self::row('Time', date('Y-m-d H:i:s T')) . '
            </table>
            <p style="margin:18px 0 0">
              <a href="' . e($appUrl) . '/admin/customers/list.php"
                 style="background:#3E5641;color:#fff;text-decoration:none;padding:10px 18px;border-radius:999px;font-weight:600;font-size:13px;display:inline-block">
                View VIP list
              </a>
            </p>
        ');

        return [$subject, $html];
    }

    private static function row(string $label, $value): string
    {
        $v = (string) ($value ?? '');
        if ($v === '') $v = '-';
        // Some values might already be escaped HTML (e.g. mailto links). Heuristic:
        $isHtml = str_starts_with($v, '<a ');
        return '
        <tr>
          <td style="padding:6px 12px 6px 0;color:#888;font-size:12px;width:140px;vertical-align:top">' . e($label) . '</td>
          <td style="padding:6px 0;font-size:13px;color:#222">' . ($isHtml ? $v : e($v)) . '</td>
        </tr>';
    }

    private static function wrapEmail(string $brand, string $inner): string
    {
        $brandSafe = e($brand);
        return '<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>' . $brandSafe . ' Notification</title></head>
<body style="margin:0;padding:0;background:#F5F1E8;font-family:Helvetica,Arial,sans-serif;color:#222">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F1E8;padding:24px 0">
    <tr><td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 6px 20px rgba(0,0,0,.06)">
        <tr><td style="background:#3E5641;color:#fff;padding:18px 24px;font-family:Georgia,serif;font-size:22px">' . $brandSafe . '</td></tr>
        <tr><td style="padding:24px">' . $inner . '</td></tr>
        <tr><td style="background:#FAF7EE;padding:14px 24px;font-size:11px;color:#888">
          You received this because you enabled scan notifications in the ELHOE Verification admin panel.
          To stop these emails, log into the admin panel &rarr; Settings &rarr; Email and disable notifications.
        </td></tr>
      </table>
    </td></tr>
  </table>
</body></html>';
    }
}
