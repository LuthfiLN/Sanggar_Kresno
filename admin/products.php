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
        $isActive = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;

        if ($name === '' || $category === '' || $price === false || $price <= 0 || $stock === false || $stock < 0) {
            $error = 'Lengkapi nama produk, kategori, harga valid, dan kuota batch.';
        } elseif ($id) {
            $query = $pdo->prepare('UPDATE products SET name = ?, description = ?, category = ?, price = ?, stock = ?, image_url = ?, is_active = ? WHERE id = ?');
            $query->execute([$name, $description, $category, $price, $stock, $image, $isActive, $id]);
            set_flash('success', 'Produk "' . $name . '" berhasil diperbarui.');
            header('Location: products.php');
            exit;
        } else {
            $query = $pdo->prepare('INSERT INTO products (name, description, category, price, stock, image_url, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $query->execute([$name, $description, $category, $price, $stock, $image, $isActive]);
            set_flash('success', 'Produk baru "' . $name . '" berhasil ditambahkan.');
            header('Location: products.php');
            exit;
        }
    } elseif ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id) {
            $del = $pdo->prepare('DELETE FROM products WHERE id = ?');
            $del->execute([$id]);
            set_flash('success', 'Produk berhasil dihapus dari katalog.');
            header('Location: products.php');
            exit;
        }
    } elseif ($action === 'toggle') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id) {
            $toggle = $pdo->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = ?');
            $toggle->execute([$id]);
            set_flash('success', 'Status visibilitas produk diperbarui.');
            header('Location: products.php');
            exit;
        }
    }
}

// Filter dan pencarian produk
$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? 'semua');

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(name LIKE ? OR category LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter === 'aktif') {
    $where[] = 'is_active = 1';
} elseif ($statusFilter === 'nonaktif') {
    $where[] = 'is_active = 0';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare("SELECT * FROM products $whereSql ORDER BY created_at DESC");
$stmt->execute($params);
$products = $stmt->fetchAll();

$editProduct = null;
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
if ($editId) {
    $q = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $q->execute([$editId]);
    $editProduct = $q->fetch() ?: null;
}

$pageTitle = 'Kelola Produk & Pre-Order';
$adminActive = 'products';
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<main class="admin-page-wrap">
    <!-- Header Kelola Produk Sesuai Gambar 3.27 & 3.37 -->
    <div class="panel-header-row kelola-products-header">
        <div>
            <p class="eyebrow">RUANG ADMIN / PRODUK</p>
            <h1 class="page-main-title">Produk &amp; pre-order</h1>
            <p class="page-subtitle">Kelola menu, harga, dan kuota setiap batch pre-order.</p>
        </div>
        <a class="button button-solid-black" href="products.php#form-produk">
            &plus; Tambah produk
        </a>
    </div>

    <?php if ($error): ?>
        <div class="notice notice-error"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Filter Bar: Search, Status -->
    <div class="products-filter-bar">
        <form class="products-search-form" method="get">
            <div class="search-input-field">
                <label for="prod-search">Cari produk</label>
                <input id="prod-search" type="text" name="q" placeholder="Nama produk atau kategori..." value="<?= e($search) ?>">
            </div>

            <div class="search-input-field">
                <label for="prod-status">Status katalog</label>
                <select id="prod-status" name="status" onchange="this.form.submit()">
                    <option value="semua" <?= $statusFilter === 'semua' ? 'selected' : '' ?>>Semua status</option>
                    <option value="aktif" <?= $statusFilter === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="nonaktif" <?= $statusFilter === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <button type="submit" class="button button-solid-black button-small align-end-btn">Terapkan</button>
        </form>
    </div>

    <!-- Layout 2 Kolom: Kiri Daftar Produk, Kanan Form Edit/Tambah Produk -->
    <div class="products-management-grid">
        <!-- Kolom Kiri: Tabel Daftar Produk Sesuai Gambar 3.27 & 3.37 -->
        <div class="products-table-col">
            <section class="panel admin-panel-box">
                <h2 class="section-clean-title">Daftar produk &bull; <?= count($products) ?></h2>

                <div class="table-wrap-clean">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>PRODUK</th>
                                <th>HARGA</th>
                                <th>KUOTA</th>
                                <th>STATUS</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$products): ?>
                                <tr>
                                    <td colspan="5" class="empty-table-cell">Tidak ada produk ditemukan.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td>
                                        <div class="prod-table-cell-flex">
                                            <img class="prod-table-thumb" src="<?= e(asset_url($p['image_url'] ?: 'assets/product-nastar.png')) ?>" alt="<?= e($p['name']) ?>">
                                            <div>
                                                <strong><?= e($p['name']) ?></strong>
                                                <small class="cell-sub-item"><?= e($p['category']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?= rupiah($p['price']) ?></strong>
                                    </td>
                                    <td>
                                        <span><?= (int) $p['stock'] ?> toples</span>
                                    </td>
                                    <td>
                                        <form method="post" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <button type="submit" class="status-toggle-btn <?= $p['is_active'] ? 'is-active' : 'is-inactive' ?>" title="Klik untuk ubah visibilitas">
                                                <?= $p['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="table-action-btns">
                                            <a class="button button-outline-dark button-small" href="products.php?edit=<?= (int) $p['id'] ?>#form-produk">
                                                Edit
                                            </a>
                                            <form method="post" onsubmit="return confirm('Yakin ingin menghapus produk ini?');" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                                <button type="submit" class="button button-outline-dark button-small btn-danger" title="Hapus">
                                                    Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Kolom Kanan: Form Tambah/Ubah Produk Sesuai Gambar 3.27 & 3.37 -->
        <div class="products-form-col" id="form-produk">
            <section class="panel admin-panel-box">
                <h2 class="section-clean-title"><?= $editProduct ? 'Edit ' . e($editProduct['name']) : 'Tambah produk baru' ?></h2>
                <small class="panel-sub"><?= $editProduct ? 'Perbarui data produk atau kuota batch.' : 'Lengkapi informasi menu pre-order baru.' ?></small>

                <form class="form-stack admin-product-form" method="post" action="products.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int) ($editProduct['id'] ?? 0) ?>">

                    <!-- Preview Foto Produk -->
                    <div class="product-form-preview-box">
                        <img id="formImgPreview" src="<?= e(asset_url($editProduct['image_url'] ?? 'assets/product-nastar.png')) ?>" alt="Preview">
                    </div>

                    <div class="form-group-clean">
                        <label for="image_url">Foto produk (Path aset / URL)</label>
                        <input id="image_url" type="text" name="image_url" placeholder="assets/product-nastar.png" value="<?= e($editProduct['image_url'] ?? '') ?>" oninput="document.getElementById('formImgPreview').src = this.value || 'assets/product-nastar.png'">
                    </div>

                    <div class="form-group-clean">
                        <label for="name">Nama produk</label>
                        <input id="name" type="text" name="name" required maxlength="180" placeholder="Misal: Nastar Nanas Premium" value="<?= e($editProduct['name'] ?? '') ?>">
                    </div>

                    <div class="form-group-clean">
                        <label for="category">Kategori</label>
                        <select id="category" name="category" required>
                            <option value="Kue Kering" <?= ($editProduct['category'] ?? '') === 'Kue Kering' ? 'selected' : '' ?>>Kue Kering</option>
                            <option value="Makanan" <?= ($editProduct['category'] ?? '') === 'Makanan' ? 'selected' : '' ?>>Makanan</option>
                            <option value="Minuman" <?= ($editProduct['category'] ?? '') === 'Minuman' ? 'selected' : '' ?>>Minuman</option>
                        </select>
                    </div>

                    <div class="form-group-clean">
                        <label for="price">Harga per toples / porsi (Rp)</label>
                        <input id="price" type="number" name="price" min="1" step="1000" required placeholder="Misal: 150000" value="<?= e($editProduct['price'] ?? '') ?>">
                    </div>

                    <div class="form-grid-2col">
                        <div class="form-group-clean">
                            <label for="stock">Kuota batch / Stok</label>
                            <input id="stock" type="number" name="stock" min="0" required value="<?= e($editProduct['stock'] ?? '20') ?>">
                        </div>
                        <div class="form-group-clean">
                            <label for="min-buy">Minimum beli</label>
                            <input id="min-buy" type="number" value="1" readonly>
                        </div>
                    </div>

                    <div class="form-group-clean">
                        <label for="description">Deskripsi produk</label>
                        <textarea id="description" name="description" rows="3" placeholder="Jelaskan bahan dan rasa..."><?= e($editProduct['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group-clean">
                        <label for="is_active">Status katalog</label>
                        <select id="is_active" name="is_active">
                            <option value="1" <?= ($editProduct['is_active'] ?? 1) == 1 ? 'selected' : '' ?>>Aktif (Tampil di katalog)</option>
                            <option value="0" <?= isset($editProduct['is_active']) && $editProduct['is_active'] == 0 ? 'selected' : '' ?>>Nonaktif (Disembunyikan)</option>
                        </select>
                    </div>

                    <div class="form-action-row">
                        <button class="button button-solid-black button-full" type="submit">
                            <?= $editProduct ? 'Simpan perubahan' : 'Tambahkan produk' ?>
                        </button>
                        <?php if ($editProduct): ?>
                            <a class="button button-outline-dark button-full" href="products.php">Batalkan</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>
        </div>
    </div>
</main>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
