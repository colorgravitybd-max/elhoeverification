<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use Elhoe\Settings;
use Elhoe\CSRF;
use Elhoe\PixelDispatcher;
use Elhoe\GTMHelper;

elhoe_start_session();

$brand     = Settings::get('brand_name', 'ELHOE');
$tagline   = Settings::get('brand_tagline', 'Redefine Your Skincare Journey');
$logoUrl   = Settings::get('brand_logo_url', '');
$supportEmail = Settings::get('support_email', '');
$primaryColor = Settings::get('brand_primary_color', '#3E5641');
$accentColor  = Settings::get('brand_accent_color', '#A4B494');
$bgColor      = Settings::get('brand_bg_color', '#F5F1E8');
$goldColor    = Settings::get('brand_gold_color', '#B49A6A');
$premiumMode  = Settings::get('brand_premium_mode', '1') === '1';

// Local Distributor block
$distEnabled  = Settings::get('distributor_enabled', '1') === '1';
$distName     = Settings::get('distributor_name', '');
$distAddress  = Settings::get('distributor_address', '');
$distEmail    = Settings::get('distributor_email', '');
$distPhone    = Settings::get('distributor_phone', '');
$distWhatsapp = Settings::get('distributor_whatsapp', '');
$distCountry  = Settings::get('distributor_country', '');

// Sanitize WhatsApp number for the wa.me URL (digits only, optional leading country)
$waDigits = preg_replace('/\D/', '', (string) $distWhatsapp);
$waLink   = $waDigits ? 'https://wa.me/' . $waDigits : '';
$telLink  = $distPhone ? 'tel:' . preg_replace('/\s+/', '', $distPhone) : '';

// Derive the path component of APP_URL (e.g. "/checker") so we can build
// SAME-ORIGIN relative URLs. This avoids CORS errors when visitors arrive
// on the site via either elhoe.com or www.elhoe.com.
$appUrlPath = '/checker';
$parsed = parse_url((string) env('APP_URL', ''));
if (isset($parsed['path']) && $parsed['path'] !== '') {
    $appUrlPath = '/' . trim($parsed['path'], '/');
}

// Pre-fill code from QR scan ?code=xxxxx
$prefill = isset($_GET['code']) ? preg_replace('/[^0-9]/', '', (string) $_GET['code']) : '';
$prefill = is_string($prefill) ? substr($prefill, 0, 20) : '';

$csrf = CSRF::token();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($brand) ?> Product Verification</title>
<meta name="description" content="Verify the authenticity of your <?= e($brand) ?> skincare product.">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="<?= e($primaryColor) ?>">
<meta name="csrf-token" content="<?= e($csrf) ?>">

<link rel="manifest" href="<?= e($appUrlPath) ?>/manifest.json">
<link rel="icon" type="image/svg+xml" href="<?= e($appUrlPath) ?>/public/assets/images/favicon.svg">
<link rel="apple-touch-icon" href="<?= e($appUrlPath) ?>/public/assets/images/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap">

<link rel="stylesheet" href="<?= e($appUrlPath) ?>/public/assets/css/style.css?v=3">

<style>
:root {
    --color-primary: <?= e($primaryColor) ?>;
    --color-accent:  <?= e($accentColor)  ?>;
    --color-bg:      <?= e($bgColor)      ?>;
    --color-gold:    <?= e($goldColor)    ?>;
}
</style>

<?= PixelDispatcher::renderPixelHead() ?>
<?= GTMHelper::renderHead() ?>
</head>
<body class="page-checker<?= $premiumMode ? ' is-premium' : '' ?>">
<?= GTMHelper::renderBody() ?>

<header class="site-header">
    <div class="container header-inner">
        <a href="<?= e(public_url()) ?>" class="brand-link" aria-label="<?= e($brand) ?>">
            <?php if ($logoUrl): ?>
                <img src="<?= e($logoUrl) ?>" alt="<?= e($brand) ?>" class="brand-logo">
            <?php else: ?>
                <span class="brand-text"><?= e($brand) ?></span>
            <?php endif; ?>
        </a>
        <a href="https://elhoe.com" class="back-link" rel="noopener">Visit shop &rarr;</a>
    </div>
</header>

<main class="container main-checker">
    <section class="hero">
        <?php if ($premiumMode): ?>
        <div class="hero-ornament" aria-hidden="true">
          <svg width="220" height="80" viewBox="0 0 200 80" xmlns="http://www.w3.org/2000/svg">
            <path d="M100 12 C 60 12, 30 32, 30 52" stroke="currentColor" stroke-width="1.2" fill="none" opacity="0.5"/>
            <path d="M100 12 C 140 12, 170 32, 170 52" stroke="currentColor" stroke-width="1.2" fill="none" opacity="0.5"/>
            <ellipse cx="46" cy="46" rx="9" ry="3.5" fill="currentColor" opacity="0.55"/>
            <ellipse cx="154" cy="46" rx="9" ry="3.5" fill="currentColor" opacity="0.55"/>
            <ellipse cx="34" cy="56" rx="6" ry="2.4" fill="currentColor" opacity="0.4"/>
            <ellipse cx="166" cy="56" rx="6" ry="2.4" fill="currentColor" opacity="0.4"/>
            <circle cx="100" cy="6" r="2.5" fill="currentColor" opacity="0.7"/>
          </svg>
        </div>
        <p class="hero-eyebrow"><span></span> Authenticity Verification <span></span></p>
        <?php endif; ?>
        <h1 class="hero-title"><?= $premiumMode ? 'Verify your' : 'Authenticate your' ?><br><span class="accent"><?= e($brand) ?></span> product</h1>
        <p class="hero-tagline"><?= e($tagline) ?></p>

        <form id="verify-form" class="verify-form" autocomplete="off" novalidate>
            <label for="code-input" class="sr-only">Product code</label>
            <div class="input-row">
                <input
                    id="code-input"
                    name="code"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="off"
                    spellcheck="false"
                    maxlength="20"
                    placeholder="Enter your product code"
                    value="<?= e($prefill) ?>"
                    aria-describedby="code-help"
                    required
                >
                <button type="button" id="paste-btn" class="icon-btn" aria-label="Paste from clipboard" title="Paste">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path></svg>
                </button>
            </div>
            <p id="code-help" class="form-help">Find the code on your product label or packaging.</p>

            <button type="submit" class="btn btn-primary btn-block" id="verify-btn">
                <span class="btn-text">Verify Authenticity</span>
                <span class="btn-spinner" hidden>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity="0.3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                </span>
            </button>
        </form>

        <div id="result-mount" class="result-mount" aria-live="polite"></div>
    </section>

    <section class="trust-row">
        <div class="trust-item">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 6v6c0 5 3.6 9.4 8 10 4.4-.6 8-5 8-10V6l-8-4Z"/><path d="m9 12 2 2 4-4"/></svg>
            <h3>Genuine</h3>
            <p>Every code is unique to a verified <?= e($brand) ?> product.</p>
        </div>
        <div class="trust-item">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
            <h3>Instant</h3>
            <p>Verification takes less than a second.</p>
        </div>
        <div class="trust-item">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12 12 3l9 9"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/></svg>
            <h3>Trusted</h3>
            <p>Used by thousands of customers worldwide.</p>
        </div>
    </section>

    <?php if ($distEnabled && ($distName || $distEmail || $distPhone)): ?>
    <section class="distributor">
        <div class="dist-card">
            <div class="dist-head">
                <p class="dist-eyebrow">Local Distributor<?= $distCountry ? ' &mdash; ' . e($distCountry) : '' ?></p>
                <h2><?= e($distName ?: 'Distributor') ?></h2>
            </div>

            <div class="dist-grid">
                <?php if ($distAddress): ?>
                <div class="dist-row">
                    <svg class="dist-icon" width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    <div>
                        <p class="dist-label">Address</p>
                        <p class="dist-val"><?= nl2br(e($distAddress)) ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($distEmail): ?>
                <div class="dist-row">
                    <svg class="dist-icon" width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                    <div>
                        <p class="dist-label">Email</p>
                        <p class="dist-val"><a href="mailto:<?= e($distEmail) ?>"><?= e($distEmail) ?></a></p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($distPhone): ?>
                <div class="dist-row">
                    <svg class="dist-icon" width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.37 1.9.72 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.35 1.85.59 2.81.72a2 2 0 0 1 1.72 2Z"/></svg>
                    <div>
                        <p class="dist-label">Call</p>
                        <p class="dist-val"><a href="<?= e($telLink) ?>"><?= e($distPhone) ?></a></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($waLink): ?>
            <a class="dist-whatsapp" href="<?= e($waLink) ?>" target="_blank" rel="noopener">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M20.52 3.48A11.85 11.85 0 0 0 12.06 0C5.5 0 .19 5.31.19 11.86c0 2.09.55 4.13 1.59 5.93L0 24l6.36-1.66a11.86 11.86 0 0 0 5.7 1.45h.01c6.55 0 11.86-5.31 11.86-11.86 0-3.17-1.23-6.15-3.41-8.45ZM12.07 21.78c-1.78 0-3.52-.48-5.04-1.38l-.36-.21-3.77.99 1-3.67-.23-.38a9.86 9.86 0 1 1 18.32-5.21c0 5.45-4.43 9.86-9.92 9.86Zm5.43-7.39c-.3-.15-1.77-.87-2.04-.97-.27-.1-.47-.15-.67.15s-.77.97-.95 1.17c-.17.2-.35.22-.65.07-.3-.15-1.27-.47-2.42-1.5-.9-.8-1.5-1.79-1.68-2.09-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51l-.57-.01c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.49s1.07 2.89 1.22 3.09c.15.2 2.1 3.21 5.09 4.5.71.31 1.27.49 1.7.63.71.23 1.36.2 1.87.12.57-.08 1.77-.72 2.02-1.42.25-.7.25-1.3.17-1.42-.07-.12-.27-.2-.57-.34Z"/></svg>
                <span>Chat on WhatsApp</span>
            </a>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="faq">
        <h2>Frequently Asked Questions</h2>
        <details>
            <summary>Where do I find the code?</summary>
            <p>Look for a numeric code printed on your product label or packaging &mdash; typically near the batch number.</p>
        </details>
        <details>
            <summary>What if my code is invalid?</summary>
            <p>If the code does not match our records, the product may be a counterfeit. <?= $supportEmail ? 'Please contact <a href="mailto:' . e($supportEmail) . '">' . e($supportEmail) . '</a>.' : 'Please reach out to our support team.' ?></p>
        </details>
        <details>
            <summary>Is my data safe?</summary>
            <p>We only store information you provide during registration to validate your purchase. We never share or sell your data.</p>
        </details>
    </section>
</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <p>&copy; <?= date('Y') ?> <?= e($brand) ?>. All rights reserved.</p>
        <p class="footer-links">
            <a href="https://elhoe.com" rel="noopener">Shop</a>
            <?php if ($supportEmail): ?>
                · <a href="mailto:<?= e($supportEmail) ?>">Contact</a>
            <?php endif; ?>
        </p>
    </div>
</footer>

<script src="<?= e($appUrlPath) ?>/public/assets/js/checker.js?v=3" defer></script>
<script>
window.ELHOE_CONFIG = {
    // Path-only (no host) so the API call stays SAME-ORIGIN regardless of
    // whether the user lands on elhoe.com or www.elhoe.com.
    apiBase: <?= json_encode($appUrlPath . '/api') ?>,
    csrfToken: <?= json_encode($csrf) ?>,
    supportEmail: <?= json_encode($supportEmail) ?>,
    autoVerify: <?= $prefill !== '' ? 'true' : 'false' ?>
};
</script>
</body>
</html>
