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
        // 1. If the admin uploaded a file, save it to public/assets/images/products/
        //    and use the public URL as image_url. This overrides any pasted URL.
        $imageUrlFromUpload = null;
        if (!empty($_FILES['image_file']) && (int) $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $allowedExt = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
            $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
            $size = (int) $_FILES['image_file']['size'];
            $tmp  = $_FILES['image_file']['tmp_name'];
            $mime = function_exists('mime_content_type') ? mime_content_type($tmp) : '';

            if (!in_array($ext, $allowedExt, true)) {
                $errors[] = 'Image must be PNG, JPG, WebP or GIF.';
            } elseif ($size > 4 * 1024 * 1024) {
                $errors[] = 'Image must be under 4 MB.';
            } elseif ($mime !== '' && !str_starts_with($mime, 'image/')) {
                $errors[] = 'Uploaded file is not a valid image.';
            } else {
                $publicProductsDir = PUBLIC_PATH . '/assets/images/products';
                if (!is_dir($publicProductsDir)) @mkdir($publicProductsDir, 0775, true);
                $fname = 'p-' . date('Ymd-His') . '-' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;
                $dest = $publicProductsDir . '/' . $fname;
                if (move_uploaded_file($tmp, $dest)) {
                    $imageUrlFromUpload = asset_url('images/products/' . $fname);
                } else {
                    $errors[] = 'Could not save the uploaded image. Check public/assets/images/ permissions.';
                }
            }
        }

        $payload = [
            'wp_product_id' => trim((string) ($_POST['wp_product_id'] ?? '')) !== '' ? (int) $_POST['wp_product_id'] : null,
            'sku'           => trim((string) ($_POST['sku']           ?? '')),
            'name'          => trim((string) ($_POST['name']          ?? '')),
            'slug'          => trim((string) ($_POST['slug']          ?? '')),
            'category'      => trim((string) ($_POST['category']      ?? '')),
            'routine_group' => trim((string) ($_POST['routine_group'] ?? '')),
            'image_url'     => $imageUrlFromUpload ?: trim((string) ($_POST['image_url'] ?? '')),
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

    <form method="POST" enctype="multipart/form-data" class="card">
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
            <label>Product Image</label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:start">
                <div>
                    <label style="font-size:11px;color:var(--admin-muted);margin-bottom:4px;display:block">Paste image URL (e.g. WordPress media)</label>
                    <input id="image_url" name="image_url" type="url" value="<?= e($data['image_url']) ?>" placeholder="https://elhoe.com/wp-content/uploads/...">
                </div>
                <div>
                    <label style="font-size:11px;color:var(--admin-muted);margin-bottom:4px;display:block">Or upload file (PNG / JPG / WebP, max 4MB)</label>
                    <input id="image_file" name="image_file" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
                    <p class="field-help" style="margin:4px 0 0">Uploading a file overrides the URL above.</p>
                </div>
            </div>
            <?php if (!empty($data['image_url'])): ?>
                <div style="margin-top:10px">
                    <span style="font-size:11px;color:var(--admin-muted)">Current:</span><br>
                    <img src="<?= e($data['image_url']) ?>" alt="" style="max-width:160px;max-height:160px;margin-top:6px;border-radius:8px;border:1px solid var(--admin-border);background:#fff">
                </div>
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
