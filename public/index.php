<?php
/**
 * ELHOE Verification - public landing & verify page.
 * Mobile-first. Numeric input. PWA-ready.
 */
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

<link rel="manifest" href="<?= e(asset_url('../manifest.json')) ?>">
<link rel="icon" type="image/svg+xml" href="<?= e(asset_url('images/favicon.svg')) ?>">
<link rel="apple-touch-icon" href="<?= e(asset_url('images/apple-touch-icon.png')) ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap">

<link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>?v=1">

<style>
:root {
    --color-primary: <?= e($primaryColor) ?>;
    --color-accent:  <?= e($accentColor)  ?>;
    --color-bg:      <?= e($bgColor)      ?>;
}
</style>

<?= PixelDispatcher::renderPixelHead() ?>
<?= GTMHelper::renderHead() ?>
</head>
<body class="page-checker">
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
        <h1 class="hero-title">Authenticate your<br><span class="accent">ELHOE</span> product</h1>
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
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 2 4 6v6c0 5 3.6 9.4 8 10 4.4-.6 8-5 8-10V6l-8-4Z"/><path d="m9 12 2 2 4-4"/></svg>
            <h3>Genuine</h3>
            <p>Every code is unique to a verified ELHOE product.</p>
        </div>
        <div class="trust-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
            <h3>Instant</h3>
            <p>Verification takes less than a second.</p>
        </div>
        <div class="trust-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 12 12 3l9 9"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/></svg>
            <h3>Trusted</h3>
            <p>Used by thousands of customers worldwide.</p>
        </div>
    </section>

    <section class="faq">
        <h2>Frequently Asked Questions</h2>
        <details>
            <summary>Where do I find the code?</summary>
            <p>Look for a numeric code printed on your product label or packaging — typically near the batch number.</p>
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

<script src="<?= e(asset_url('js/checker.js')) ?>?v=1" defer></script>
<script>
window.ELHOE_CONFIG = {
    apiBase: <?= json_encode(rtrim((string) env('APP_URL', ''), '/') . '/api') ?>,
    csrfToken: <?= json_encode($csrf) ?>,
    supportEmail: <?= json_encode($supportEmail) ?>,
    autoVerify: <?= $prefill !== '' ? 'true' : 'false' ?>
};
</script>
</body>
</html>
