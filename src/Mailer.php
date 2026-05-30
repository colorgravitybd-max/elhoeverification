<?php
declare(strict_types=1);

namespace Elhoe;

/**
 * Lightweight Mailer.
 *
 * Sends via PHP mail() by default. Optionally sends via SMTP (no Composer
 * deps - uses raw fsockopen). Configurable from Admin > Settings > Email.
 *
 * Always sends as multipart/alternative with both plain-text and HTML parts
 * for maximum deliverability.
 */
final class Mailer
{
    /**
     * Send an email.
     *
     * @param string|array $to        Recipient address(es).
     * @param string       $subject   Subject line.
     * @param string       $html      HTML body.
     * @param string|null  $text      Plain-text body. Auto-derived from HTML if null.
     * @param array        $opts      from_name, from_email, reply_to, headers
     * @return array{ok:bool, error:?string}
     */
    public static function send($to, string $subject, string $html, ?string $text = null, array $opts = []): array
    {
        $to = is_array($to) ? $to : array_map('trim', explode(',', (string) $to));
        $to = array_values(array_filter($to, fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
        if (!$to) {
            return ['ok' => false, 'error' => 'No valid recipient address.'];
        }

        $fromName  = $opts['from_name']  ?? Settings::get('email_notify_from_name',    'ELHOE Verification');
        $fromEmail = $opts['from_email'] ?? Settings::get('email_notify_from_address', 'no-reply@elhoe.com');

        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid From: address: ' . $fromEmail];
        }

        if ($text === null) {
            $text = self::htmlToText($html);
        }

        $useSmtp = (Settings::get('smtp_enabled', '0') === '1') && (Settings::get('smtp_host', '') !== '');

        try {
            if ($useSmtp) {
                self::sendSmtp($to, $subject, $html, $text, $fromName, $fromEmail);
            } else {
                self::sendMail($to, $subject, $html, $text, $fromName, $fromEmail);
            }
            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            Logger::error('Mailer send failed', ['err' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------
    // PHP mail() transport
    // -------------------------------------------------------------------
    private static function sendMail(array $to, string $subject, string $html, string $text, string $fromName, string $fromEmail): void
    {
        $boundary = '_elhoe_' . bin2hex(random_bytes(8));
        $headers  = self::buildHeaders($fromName, $fromEmail, $boundary);
        $body     = self::buildMultipartBody($html, $text, $boundary);
        $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        foreach ($to as $rcpt) {
            // additional_parameters: -f sets envelope sender (helps SPF)
            $params = '-f' . $fromEmail;
            $ok = @mail($rcpt, $subjectEnc, $body, $headers, $params);
            if (!$ok) {
                throw new \RuntimeException("mail() returned false for {$rcpt}");
            }
        }
    }

    // -------------------------------------------------------------------
    // SMTP transport (raw, no library)
    // -------------------------------------------------------------------
    private static function sendSmtp(array $to, string $subject, string $html, string $text, string $fromName, string $fromEmail): void
    {
        $host   = (string) Settings::get('smtp_host', '');
        $port   = (int)    Settings::get('smtp_port', 465);
        $user   = (string) Settings::get('smtp_username', '');
        $pass   = (string) Settings::get('smtp_password', '');
        $secure = strtolower((string) Settings::get('smtp_secure', 'ssl'));

        if ($host === '' || $port <= 0) {
            throw new \RuntimeException('SMTP host/port not configured.');
        }

        $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host;
        $errno = 0; $errstr = '';
        $sock = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 20);
        if (!$sock) {
            throw new \RuntimeException("SMTP connect failed: {$errstr}");
        }
        stream_set_timeout($sock, 20);

        $read = function () use ($sock): string {
            $out = '';
            while (!feof($sock)) {
                $line = fgets($sock, 515);
                if ($line === false) break;
                $out .= $line;
                if (isset($line[3]) && $line[3] === ' ') break;
            }
            return $out;
        };
        $write = function (string $cmd, string $expect) use ($sock, $read): string {
            fwrite($sock, $cmd . "\r\n");
            $resp = $read();
            $code = substr($resp, 0, 3);
            if (strpos($expect, $code) === false) {
                throw new \RuntimeException("SMTP {$cmd}: expected {$expect}, got {$resp}");
            }
            return $resp;
        };

        $banner = $read();
        if (substr($banner, 0, 3) !== '220') {
            fclose($sock);
            throw new \RuntimeException("SMTP banner: {$banner}");
        }

        $hostname = $_SERVER['HTTP_HOST'] ?? 'elhoe.com';
        $write('EHLO ' . $hostname, '250');

        if ($secure === 'tls') {
            $write('STARTTLS', '220');
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('SMTP STARTTLS failed.');
            }
            $write('EHLO ' . $hostname, '250');
        }

        if ($user !== '') {
            $write('AUTH LOGIN', '334');
            $write(base64_encode($user), '334');
            $write(base64_encode($pass), '235');
        }

        $write('MAIL FROM:<' . $fromEmail . '>', '250');
        foreach ($to as $rcpt) {
            $write('RCPT TO:<' . $rcpt . '>', '250 251');
        }
        $write('DATA', '354');

        $boundary = '_elhoe_' . bin2hex(random_bytes(8));
        $headers  = "From: " . self::encodeHeader($fromName) . " <{$fromEmail}>\r\n";
        $headers .= "To: " . implode(', ', $to) . "\r\n";
        $headers .= "Reply-To: <{$fromEmail}>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "Message-ID: <" . bin2hex(random_bytes(12)) . "@" . $hostname . ">\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $headers .= "X-Mailer: ELHOE-Verification\r\n";

        $body = self::buildMultipartBody($html, $text, $boundary);

        // Dot-stuffing per RFC 5321
        $payload = preg_replace('/^\./m', '..', $headers . "\r\n" . $body);
        fwrite($sock, $payload . "\r\n.\r\n");
        $resp = $read();
        if (substr($resp, 0, 3) !== '250') {
            throw new \RuntimeException("SMTP DATA reject: {$resp}");
        }
        $write('QUIT', '221');
        fclose($sock);
    }

    private static function buildHeaders(string $fromName, string $fromEmail, string $boundary): string
    {
        $h  = 'From: ' . self::encodeHeader($fromName) . ' <' . $fromEmail . '>' . "\r\n";
        $h .= 'Reply-To: <' . $fromEmail . '>' . "\r\n";
        $h .= 'MIME-Version: 1.0' . "\r\n";
        $h .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n";
        $h .= 'X-Mailer: ELHOE-Verification' . "\r\n";
        return $h;
    }

    private static function buildMultipartBody(string $html, string $text, string $boundary): string
    {
        $b  = "--{$boundary}\r\n";
        $b .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $b .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $b .= $text . "\r\n\r\n";
        $b .= "--{$boundary}\r\n";
        $b .= "Content-Type: text/html; charset=UTF-8\r\n";
        $b .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $b .= $html . "\r\n\r\n";
        $b .= "--{$boundary}--\r\n";
        return $b;
    }

    private static function encodeHeader(string $value): string
    {
        if (preg_match('/[\x80-\xFF]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    private static function htmlToText(string $html): string
    {
        $t = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $t = preg_replace('#</p>#i', "\n\n", $t) ?? $t;
        $t = preg_replace('#</h\d>#i', "\n\n", $t) ?? $t;
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/[ \t]+/', ' ', $t) ?? $t);
    }
}
