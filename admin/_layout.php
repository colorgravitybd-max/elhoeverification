<?php
/**
 * Admin layout helpers.
 *
 * Usage:
 *   $page_title = 'Codes';
 *   $active = 'codes';
 *   require __DIR__ . '/_layout.php';
 *   layout_head($page_title, $active);
 *   ... your content ...
 *   layout_foot();
 */
declare(strict_types=1);

use Elhoe\Auth;
use Elhoe\CSRF;
use Elhoe\Settings;

if (!function_exists('layout_head')) {
    function layout_head(string $pageTitle, string $active = ''): void
    {
        $user  = Auth::user();
        $brand = Settings::get('brand_name', 'ELHOE');
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e($brand) ?> Admin</title>
<meta name="robots" content="noindex,nofollow">
<meta name="csrf-token" content="<?= e(CSRF::token()) ?>">
<link rel="icon" type="image/svg+xml" href="<?= e(asset_url('images/favicon.svg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:wght@500;600&family=JetBrains+Mono:wght@500&display=swap">
<link rel="stylesheet" href="<?= e(asset_url('css/admin.css')) ?>?v=1">
</head>
<body class="admin">

<aside class="sidebar">
    <div class="sidebar-brand">
        <a href="<?= e(admin_url('dashboard.php')) ?>"><?= e($brand) ?> <small>admin</small></a>
    </div>
    <nav class="sidebar-nav">
        <?php foreach (admin_menu() as $item):
            $isActive = $item['key'] === $active;
        ?>
        <a href="<?= e(admin_url($item['href'])) ?>" class="<?= $isActive ? 'is-active' : '' ?>">
            <?= $item['icon'] ?>
            <span><?= e($item['label']) ?></span>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="user-pill">
            <span class="user-pill-avatar"><?= e(strtoupper(substr($user['username'] ?? 'A', 0, 1))) ?></span>
            <span class="user-pill-name"><?= e($user['username'] ?? '') ?></span>
        </div>
        <a href="<?= e(admin_url('logout.php')) ?>" class="btn btn-ghost btn-sm">Sign out</a>
    </div>
</aside>

<main class="content">
    <header class="topbar">
        <div>
            <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
        </div>
        <div class="topbar-actions">
            <a href="<?= e(public_url()) ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">Open Checker ↗</a>
        </div>
    </header>
    <div class="content-body">
    <?php
    // flash
    if (!empty($_SESSION['_flash'])):
        foreach ($_SESSION['_flash'] as $f): ?>
            <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
        <?php endforeach;
        unset($_SESSION['_flash']);
    endif;
    }
}

if (!function_exists('layout_foot')) {
    function layout_foot(): void { ?>
    </div>
</main>

<script src="<?= e(asset_url('js/admin.js')) ?>?v=1" defer></script>
</body>
</html>
<?php } }

if (!function_exists('admin_menu')) {
    function admin_menu(): array
    {
        $u = Auth::user();
        $items = [
            ['key' => 'dashboard',   'href' => 'dashboard.php',         'label' => 'Dashboard',     'icon' => svg_icon('grid')],
            ['key' => 'codes',       'href' => 'codes/list.php',        'label' => 'Codes',         'icon' => svg_icon('hash')],
            ['key' => 'products',    'href' => 'products/list.php',     'label' => 'Products',      'icon' => svg_icon('package')],
            ['key' => 'customers',   'href' => 'customers/list.php',    'label' => 'Customers',     'icon' => svg_icon('users')],
            ['key' => 'analytics',   'href' => 'analytics/overview.php','label' => 'Analytics',     'icon' => svg_icon('chart')],
            ['key' => 'integrations','href' => 'integrations/meta.php', 'label' => 'Integrations',  'icon' => svg_icon('plug')],
            ['key' => 'settings',    'href' => 'settings/general.php',  'label' => 'Settings',      'icon' => svg_icon('cog')],
        ];
        // Audit log only super_admin
        if (($u['role'] ?? '') === 'super_admin') {
            $items[] = ['key' => 'audit', 'href' => 'audit-log.php', 'label' => 'Audit Log', 'icon' => svg_icon('clock')];
        }
        return $items;
    }
}
if (!function_exists('svg_icon')) {
    function svg_icon(string $name): string
    {
        $svgs = [
            'grid'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
            'hash'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/></svg>',
            'package' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16.5 9.4 7.55 4.24"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.27 6.96 8.73 5.05 8.73-5.05"/><path d="M12 22.08V12"/></svg>',
            'users'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'chart'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-5"/></svg>',
            'plug'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 2v6"/><path d="M15 2v6"/><path d="M5 8h14v3a7 7 0 0 1-14 0Z"/><path d="M12 18v4"/></svg>',
            'cog'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.36.13.7.31 1 .54.32.22.59.49.81.81.13.32.21.66.24 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg>',
            'clock'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        ];
        return $svgs[$name] ?? '';
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        elhoe_start_session();
        $_SESSION['_flash'] = $_SESSION['_flash'] ?? [];
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): void
    {
        header('Location: ' . $url, true, $status);
        exit;
    }
}
