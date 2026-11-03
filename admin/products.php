<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config.php';
require_admin();
$pdo = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
        $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);
        $image = trim((string) ($_POST['image_url'] ?? ''));
        if ($name === '' || $description === '' || $category === '' || $price === false || $price <= 0 || $stock === false || $stock < 0 || strlen($image) > 1000) {
            $error = 'Lengkapi nama, deskripsi, kategori, harga valid, dan stok. URL gambar maksimal 1000 karakter.';
        } elseif ($id) {
            $query = $pdo->prepare('UPDATE products SET name = ?, description = ?, category = ?, price = ?, stock = ?, image_url = ? WHERE id = ?');
            $query->execute([$name, $description, $category, $price, $stock, $image, $id]);
            set_flash('success', 'Produk diperbarui.');
            header('Location: products.php');
            exit;
        } else {
            $query = $pdo->prepare('INSERT INTO products (name, description, category, price, stock, image_url) VALUES (?, ?, ?, ?, ?, ?)');
            $query->execute([$name, $description, $category, $price, $stock, $image]);
            set_flash('success', 'Produk baru ditambahkan.');
            header('Location: products.php');
            exit;
        }
    } elseif ($action === 'toggle') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id) {
            $query = $pdo->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = ?');
            $query->execute([$id]);
            set_flash('success', 'Status tampilan produk diperbarui.');
            header('Location: products.php');
            exit;
        }
    }
}

$editProduct = null;
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
if ($editId) {
    $query = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $query->execute([$editId]);
    $editProduct = $query->fetch() ?: null;
}
$products = $pdo->query('SELECT * FROM products ORDER BY created_at DESC')->fetchAll();
$pageTitle = 'Kelola produk';
require dirname(__DIR__) . '/includes/header.php';
?>
<main class="page-shell"><div class="page-heading"><p class="eyebrow">ADMIN / MENU KUE</p><h1>Kelola menu preorder</h1><p>Atur harga, ilustrasi menu, dan kuota preorder setiap batch.</p></div><p><a class="text-link" href="index.php">&#8592; Kembali ke ringkasan</a></p><?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?><div class="admin-columns"><section class="admin-section"><h2><?= $editProduct ? 'Ubah menu' : 'Tambah kue' ?></h2><form class="panel form-stack" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($editProduct['id'] ?? 0) ?>"><label>Nama kue<input name="name" required maxlength="180" value="<?= e($editProduct['name'] ?? '') ?>"></label><label>Kategori<input name="category" required maxlength="100" value="<?= e($editProduct['category'] ?? 'Kue Kering') ?>"></label><label>Deskripsi<textarea name="description" required><?= e($editProduct['description'] ?? '') ?></textarea></label><div class="form-grid"><label>Harga per toples (Rp)<input type="number" name="price" min="1" step="1" required value="<?= e($editProduct['price'] ?? '') ?>"></label><label>Kuota preorder<input type="number" name="stock" min="0" step="1" required value="<?= e($editProduct['stock'] ?? '20') ?>"></label></div><label>Path aset atau URL gambar<input type="text" name="image_url" maxlength="1000" value="<?= e($editProduct['image_url'] ?? '') ?>" placeholder="assets/product-nastar.png"></label><button class="button button-dark" type="submit"><?= $editProduct ? 'Simpan perubahan' : 'Tambahkan kue' ?></button><?php if ($editProduct): ?><a class="text-link" href="products.php">Batal mengubah</a><?php endif; ?></form></section><section class="admin-section"><h2>Menu preorder</h2><div class="panel table-wrap"><table><thead><tr><th>Kue</th><th>Harga/toples</th><th>Sisa kuota</th><th>Status</th><th></th></tr></thead><tbody><?php if (!$products): ?><tr><td colspan="5">Belum ada menu kue.</td></tr><?php endif; ?><?php foreach ($products as $product): ?><tr><td><?= $product['image_url'] ? '<img class="admin-thumb" src="' . e(asset_url($product['image_url'])) . '" alt=""> ' : '' ?><?= e($product['name']) ?></td><td><?= rupiah($product['price']) ?></td><td><?= (int) $product['stock'] ?></td><td><?= $product['is_active'] ? 'Aktif' : 'Disembunyikan' ?></td><td class="admin-actions"><a class="text-link" href="products.php?edit=<?= (int) $product['id'] ?>">Ubah</a><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $product['id'] ?>"><button class="button button-outline button-small" type="submit"><?= $product['is_active'] ? 'Sembunyikan' : 'Aktifkan' ?></button></form></td></tr><?php endforeach; ?></tbody></table></div></section></div></main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>