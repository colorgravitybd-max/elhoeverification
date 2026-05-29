<?php
/**
 * Admin > Products > Add / Edit
 */
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../_layout.php';

use Elhoe\Auth;
use Elhoe\CSRF;
use Elhoe\ProductService;

Auth::require();

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;
$product = $isEdit ? ProductService::find($id) : null;
if ($isEdit && !$product) {
    flash('error', 'Product not found.');
    redirect(admin_url('products/list.php'));
}

$errors = [];
$data = $product ?: [
    'wp_product_id' => '', 'sku' => '', 'name' => '', 'slug' => '',
    'category' => '', 'routine_group' => '', 'image_url' => '',
    'product_url' => '', 'description' => '', 'status' => 'active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['_csrf'] ?? null)) {
        $errors[] = 'Session expired. Please retry.';
    } else {
        $payload = [
            'wp_product_id' => trim((string) ($_POST['wp_product_id'] ?? '')) !== '' ? (int) $_POST['wp_product_id'] : null,
            'sku'           => trim((string) ($_POST['sku']           ?? '')),
            'name'          => trim((string) ($_POST['name']          ?? '')),
            'slug'          => trim((string) ($_POST['slug']          ?? '')),
            'category'      => trim((string) ($_POST['category']      ?? '')),
            'routine_group' => trim((string) ($_POST['routine_group'] ?? '')),
            'image_url'     => trim((string) ($_POST['image_url']     ?? '')),
            'product_url'   => trim((string) ($_POST['product_url']   ?? '')),
            'description'   => trim((string) ($_POST['description']   ?? '')),
            'status'        => in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active',
        ];
        if ($payload['name'] === '') {
            $errors[] = 'Name is required.';
        }
        if ($payload['product_url'] !== '' && !filter_var($payload['product_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Product URL is not valid.';
        }
        if ($payload['image_url'] !== '' && !filter_var($payload['image_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Image URL is not valid.';
        }
        if (!$errors) {
            try {
                if ($isEdit) {
                    ProductService::update($id, $payload);
                    flash('success', 'Product updated.');
                } else {
                    $newId = ProductService::create($payload);
                    flash('success', 'Product created.');
                    $id = $newId;
                }
                redirect(admin_url('products/edit.php?id=' . $id));
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
        $data = $payload;
    }
}

layout_head($isEdit ? 'Edit Product' : 'Add Product', 'products');
?>

<div style="max-width:760px">
    <p class="muted"><a href="<?= e(admin_url('products/list.php')) ?>">← Back to products</a></p>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="POST" class="card">
        <?= CSRF::field() ?>

        <div class="field">
            <label for="name">Product Name *</label>
            <input id="name" name="name" required maxlength="255" value="<?= e($data['name']) ?>">
        </div>

        <div class="field-row">
            <div class="field">
                <label for="sku">SKU</label>
                <input id="sku" name="sku" maxlength="64" value="<?= e($data['sku']) ?>">
            </div>
            <div class="field">
                <label for="wp_product_id">WordPress Product ID</label>
                <input id="wp_product_id" name="wp_product_id" type="number" value="<?= e($data['wp_product_id']) ?>">
                <p class="field-help">Original WP post ID (preserves legacy mapping).</p>
            </div>
            <div class="field">
                <label for="slug">Slug</label>
                <input id="slug" name="slug" maxlength="255" value="<?= e($data['slug']) ?>">
                <p class="field-help">Auto-generated if empty.</p>
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="category">Category</label>
                <input id="category" name="category" maxlength="100" value="<?= e($data['category']) ?>" list="cats">
                <datalist id="cats">
                    <?php foreach (ProductService::categories() as $c): ?>
                        <option value="<?= e($c) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="field">
                <label for="routine_group">Routine Group</label>
                <input id="routine_group" name="routine_group" maxlength="100" value="<?= e($data['routine_group']) ?>" list="groups">
                <datalist id="groups">
                    <?php foreach (ProductService::routineGroups() as $g): ?>
                        <option value="<?= e($g) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <p class="field-help">Used for "Complete Your Routine" suggestions.</p>
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="active"   <?= $data['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $data['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>

        <div class="field">
            <label for="image_url">Image URL</label>
            <input id="image_url" name="image_url" type="url" value="<?= e($data['image_url']) ?>" placeholder="https://elhoe.com/wp-content/uploads/...">
            <?php if (!empty($data['image_url'])): ?>
                <img src="<?= e($data['image_url']) ?>" alt="" style="max-width:120px;max-height:120px;margin-top:8px;border-radius:8px;border:1px solid var(--admin-border)">
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="product_url">Product URL (Buy Again link)</label>
            <input id="product_url" name="product_url" type="url" value="<?= e($data['product_url']) ?>" placeholder="https://elhoe.com/product/...">
            <p class="field-help">Customer clicks the product image and "Buy Again" button to land here.</p>
        </div>

        <div class="field">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4"><?= e($data['description']) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Product' ?></button>
        <a href="<?= e(admin_url('products/list.php')) ?>" class="btn btn-ghost">Cancel</a>
    </form>
</div>

<?php layout_foot(); ?>
