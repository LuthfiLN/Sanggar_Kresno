<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'add') {
    verify_csrf();
    $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
    $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT);
    $deliveryDate = trim((string) ($_POST['delivery_date'] ?? ''));
    $parsedDeliveryDate = DateTimeImmutable::createFromFormat('!Y-m-d', $deliveryDate);
    $minimumDeliveryDate = min_preorder_datetime();

    if (!$parsedDeliveryDate || $parsedDeliveryDate->format('Y-m-d') !== $deliveryDate || $parsedDeliveryDate < $minimumDeliveryDate) {
        set_flash('error', 'Tanggal pre-order tidak bisa dipilih untuk besok atau lusa. Pemesanan minimal 3 hari setelah dipesan agar kue dapat dibuat segar.');
    } else {
        $product = find_active_product_stock((int) $productId);

        if (!$product || (int) $product['stock'] < 1 || !$quantity || $quantity < 1) {
            set_flash('error', 'Produk sedang tidak tersedia.');
        } else {
            $_SESSION['cart'] ??= [];
            $inCart = (int) ($_SESSION['cart'][$productId] ?? 0);
            $_SESSION['cart'][$productId] = min($inCart + $quantity, (int) $product['stock']);
            $_SESSION['preorder_delivery_date'] = $deliveryDate;
            $fulfillment = (string) ($_POST['fulfillment'] ?? 'delivery');
            $_SESSION['preorder_fulfillment'] = in_array($fulfillment, ['delivery', 'pickup'], true) ? $fulfillment : 'delivery';
            set_flash('success', 'Produk ditambahkan ke keranjang.');
        }
    }
    header('Location: ' . app_url('cart.php'));
    exit;
}

$search = trim((string) ($_GET['q'] ?? ''));
$category = (string) ($_GET['category'] ?? 'semua');
if (!in_array($category, ['semua', 'makanan', 'minuman'], true)) {
    $category = 'semua';
}
$products = search_active_products($search, $category);

$nextBatchDate = indo_date(date('Y-m-d', strtotime('+3 days')));
$pageTitle = 'Katalog Pre-Order';
$currentNav = 'menu';
require __DIR__ . '/app/Views/header.php';
?>
<main class="page-shell">
    <div class="catalog-header-wrap">
        <div class="catalog-header-left">
            <p class="eyebrow">Sanggar Kresno / MEDAN</p>
            <h1 class="catalog-main-title">Daftar produk</h1>
            <p class="catalog-subtitle">Pilih produk dan jadwal pre-order.</p>
        </div>

        <div class="catalog-schedule-card">
            <div class="schedule-calendar-icon">&#128197;</div>
            <div class="schedule-text">
                <strong>Pre-order &bull; <?= e($nextBatchDate) ?></strong>
                <small>Tutup pre-order 18:00 WIB &bull; Antar &amp; ambil sendiri</small>
            </div>
            <span class="schedule-badge">Produk <?= count($products) ?> &bull; Pre-order</span>
        </div>
    </div>

    <div class="catalog-toolbar">
        <form class="search-form-clean" method="get" action="<?= e(app_url('index.php')) ?>">
            <div class="search-input-wrapper">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="search" name="q" placeholder="Cari produk..." value="<?= e($search) ?>"
                    aria-label="Cari produk">
            </div>
            <?php if ($category !== 'semua'): ?>
            <input type="hidden" name="category" value="<?= e($category) ?>">
            <?php endif; ?>
        </form>

        <div class="catalog-category-filter">
            <div class="category-tabs-clean">
                <a href="?category=semua<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
                    class="tab-clean <?= $category === 'semua' ? 'is-active' : '' ?>">Semua</a>
                <a href="?category=minuman<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
                    class="tab-clean <?= $category === 'minuman' ? 'is-active' : '' ?>">Minuman</a>
                <a href="?category=makanan<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
                    class="tab-clean <?= $category === 'makanan' ? 'is-active' : '' ?>">Makanan</a>
            </div>
            <span class="category-count-label"><?= count($products) ?> produk &bull; Harga per porsi / kemasan</span>
        </div>
    </div>

    <section class="products-grid-container" id="koleksi">
        <?php if (!$products): ?>
        <div class="panel empty-catalog-state">
            <p>Belum ada produk yang cocok dengan pencarian "<strong><?= e($search) ?></strong>".</p>
            <a class="button button-outline-dark" href="<?= e(app_url('index.php')) ?>">Tampilkan semua produk</a>
        </div>
        <?php endif; ?>

        <div class="products-quad-grid">
            <?php foreach ($products as $index => $product): ?>
            <?php
                $isFav = ($index === 0);
                $tagLabel = $isFav ? 'Favorit' : 'Pre-order';
                $tagClass = $isFav ? 'tag-favorite' : 'tag-preorder';
                ?>
            <article class="product-card-clean">
                <div class="product-card-thumb">
                    <span class="product-tag-pill <?= $tagClass ?>"><?= e($tagLabel) ?></span>
                    <a href="<?= e(app_url('product.php?id=' . (int) $product['id'])) ?>">
                        <img src="<?= e(asset_url($product['image_url'] ?: 'assets/product-nastar.png')) ?>"
                            alt="<?= e($product['name']) ?>" loading="lazy">
                    </a>
                </div>
                <div class="product-card-content">
                    <h2 class="product-card-name">
                        <a
                            href="<?= e(app_url('product.php?id=' . (int) $product['id'])) ?>"><?= e($product['name']) ?></a>
                    </h2>
                    <p class="product-card-desc">
                        <?= e($product['description'] ?: 'Dibuat segar sesuai pesanan dengan resep rumahan.') ?>
                    </p>
                    <p class="product-card-unit"><?= e($product['category'] ?? 'toples') ?></p>
                    <div class="product-card-foot">
                        <strong class="product-card-price"><?= rupiah($product['price']) ?></strong>
                        <a class="product-card-link-btn"
                            href="<?= e(app_url('product.php?id=' . (int) $product['id'])) ?>">
                            Lihat produk <span class="arrow-icon">&#8599;</span>
                        </a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="how-to-order-steps" id="cara-pesan">
        <div class="step-card-clean">
            <span class="step-card-num">01</span>
            <div class="step-card-body">
                <h3>Pilih jadwal</h3>
                <p>Semua dibuat sesuai batch pre-order minimal 3 hari sebelum pengiriman.</p>
            </div>
        </div>
        <div class="step-card-clean">
            <span class="step-card-num">02</span>
            <div class="step-card-body">
                <h3>Sepakati ongkir</h3>
                <p>ngobrol dulu dengan admin melalui fitur chat internal.</p>
            </div>
        </div>
        <div class="step-card-clean">
            <span class="step-card-num">03</span>
            <div class="step-card-body">
                <h3>Bayar DP 50%</h3>
                <p>QRIS atau Virtual Account otomatis, sisanya dilunasi sebelum pesanan dikirim.</p>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/app/Views/footer.php'; ?>