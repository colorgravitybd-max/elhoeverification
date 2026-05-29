<?php
/**
 * Admin > Audit Log (super_admin only)
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/_layout.php';

use Elhoe\Auth;
use Elhoe\Database;

Auth::requireRole('super_admin');

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 100;
$total = (int) Database::scalar("SELECT COUNT(*) FROM audit_log");
$rows  = Database::all(
    "SELECT * FROM audit_log ORDER BY created_at DESC LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage)
);
$pages = max(1, (int) ceil($total / $perPage));

layout_head('Audit Log', 'audit');
?>

<div class="section-head">
    <h2><?= number_format($total) ?> events</h2>
</div>

<div class="table-wrap">
    <table class="data">
        <thead>
            <tr><th>When</th><th>Admin</th><th>Action</th><th>Entity</th><th>IP</th><th>Details</th></tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-center muted" style="padding:30px">No audit events yet.</td></tr>
        <?php else: foreach ($rows as $r): ?>
            <tr>
                <td class="col-mono" style="font-size:12px"><?= e($r['created_at']) ?></td>
                <td><?= e($r['admin_username'] ?? '—') ?></td>
                <td class="col-mono"><?= e($r['action']) ?></td>
                <td>
                    <?php if ($r['entity_type'] && $r['entity_id']): ?>
                        <code><?= e($r['entity_type']) ?>#<?= (int) $r['entity_id'] ?></code>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td class="col-mono" style="font-size:11px"><?= e($r['ip_address']) ?></td>
                <td style="font-size:11px;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e((string) $r['details']) ?>">
                    <?= e((string) $r['details']) ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
<div class="pagination">
    <?php
        $window = 5;
        $from = max(1, $page - $window);
        $to   = min($pages, $page + $window);
        for ($i = $from; $i <= $to; $i++):
    ?>
        <?php if ($i === $page): ?>
            <span class="current"><?= $i ?></span>
        <?php else: ?>
            <a href="?page=<?= $i ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php layout_foot(); ?>
