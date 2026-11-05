<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'add') {
    verify_csrf();
    $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
    $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT);
    $deliveryDate = trim((string) ($_POST['delivery_date'] ?? ''));
    $parsedDeliveryDate = DateTimeImmutable::createFromFormat('!Y-m-d', $deliveryDate);
    $minimumDeliveryDate = new DateTimeImmutable('today +3 days');
    if (!$parsedDeliveryDate || $parsedDeliveryDate->format('Y-m-d') !== $deliveryDate || $parsedDeliveryDate < $minimumDeliveryDate) {
        set_flash('error', 'Tanggal pre-order paling cepat 3 hari setelah pemesanan.');
    } else {
        $query = db()->prepare('SELECT id, stock FROM products WHERE id = ? AND is_active = 1');
        $query->execute([$productId]);
        $product = $query->fetch();
        if (!$product || (int) $product['stock'] < 1 || !$quantity || $quantity < 1) {
            set_flash('error', 'Produk sedang tidak tersedia.');
        } else {
            $_SESSION['cart'] ??= [];
            $inCart = (int) ($_SESSION['cart'][$productId] ?? 0);
            $_SESSION['cart'][$productId] = min($inCart + $quantity, (int) $product['stock']);
            $_SESSION['preorder_delivery_date'] = $deliveryDate;
            $fulfillment = (string) ($_POST['fulfillment'] ?? 'delivery');
            $_SESSION['preorder_fulfillment'] = in_array($fulfillment, ['delivery', 'pickup'], true) ? $fulfillment : 'delivery';
            set_flash('success', 'Produk ditambahkan ke tas belanja.');
        }
    }
    header('Location: ' . app_url('index.php#koleksi'));
    exit;
}

$search = trim((string) ($_GET['q'] ?? ''));
$category = (string) ($_GET['category'] ?? 'semua');
$conditions = ['is_active = 1'];
$parameters = [];
if ($search !== '') {
    $conditions[] = '(name LIKE ? OR description LIKE ? OR category LIKE ?)';
    $term = '%' . $search . '%';
    array_push($parameters, $term, $term, $term);
}
if ($category === 'makanan') {
    $conditions[] = "(category LIKE '%kue%' OR category LIKE '%makanan%')";
} elseif ($category === 'minuman') {
    $conditions[] = "category LIKE '%minuman%'";
} else {
    $category = 'semua';
}
$productsQuery = db()->prepare('SELECT * FROM products WHERE ' . implode(' AND ', $conditions) . ' ORDER BY created_at DESC');
$productsQuery->execute($parameters);
$products = $productsQuery->fetchAll();
$pageTitle = 'Preorder kue Lebaran di Medan';
require __DIR__ . '/includes/header.php';
?>
<main>
    <section class="hero">
        <div class="hero-copy">
            <p class="eyebrow">Sanggar Tresno</p>
            <h1>Lebih nikmat,<br><span>lebih hangat</span><br>dengan kue rumah.</h1>
            <p>Nastar, kastengel, dan kue favorit lainnya dibuat segar sesuai preorder. Pembayaran DP 50% sebelum mulai
                dipanggang.</p>
            <div class="hero-actions"><a class="button button-dark" href="#koleksi">Pilih Kue <span
                        aria-hidden="true">&#8594;</span></a><a class="text-link" href="chat.php">Tanya jadwal</a></div>
        </div>
        <div class="hero-image" role="img" aria-label="Aneka kue Lebaran rumahan"><span class="hero-stamp">Fresh from
                our oven</span></div>
    </section>

    <div class="ticker"><span>&bull; PREORDER</span> &bull; DIBUAT SETELAH DP 50%<span>&bull; HOMEMADE</span></div>

    <section class="catalog" id="koleksi">
        <div class="section-top">
            <div>
                <p class="eyebrow">DIBUAT BERDASARKAN PESANAN</p>
                <h2>Daftar produk</h2>
                <p>Pilih produk dari jadwal pre-order.</p>
            </div>
            <div class="schedule-info">
                <div>
                    <small>Tutup pre-order, 18:00 WIB • Antar & ambil sendiri</small>
                </div>
            </div>
        </div>

        <div class="catalog-filters">
            <form class="search-form" method="get" action="<?= e(app_url('index.php')) ?>">
                <input type="search" name="q" placeholder="Cari produk..." value="<?= e($search) ?>"
                    aria-label="Cari produk">
                <button type="submit">Cari</button>
            </form>
            <div class="category-tabs">
                <a href="?category=semua" class="tab-btn <?= $category === 'semua' ? 'active' : '' ?>">Semua</a>
                <a href="?category=minuman" class="tab-btn <?= $category === 'minuman' ? 'active' : '' ?>">Minuman</a>
                <a href="?category=makanan" class="tab-btn <?= $category === 'makanan' ? 'active' : '' ?>">Makanan</a>
                <span class="item-count"><?= count($products) ?> produk • Harga per toples/kemasan</span>
            </div>
        </div>

        <div class="product-grid">
            <?php if (!$products): ?>
            <div class="empty-state">Belum ada produk yang cocok. Coba kata pencarian lain.</div>
            <?php endif; ?>
            <?php foreach ($products as $index => $product): ?>
            <article class="product-card" style="animation-delay: <?= (int) $index * 70 ?>ms">
                <a class="product-image" href="product.php?id=<?= (int) $product['id'] ?>"
                    aria-label="Lihat detail <?= e($product['name']) ?>">
                    <img src="<?= e(asset_url($product['image_url'] ?: 'assets/product-nastar.png')) ?>"
                        alt="<?= e($product['name']) ?>" loading="lazy">
                    <span class="product-category">Pre-order</span>
                </a>
                <div class="product-info">
                    <h3><?= e($product['name']) ?></h3>
                    <p><?= e($product['description']) ?></p>
                    <div class="product-bottom">
                        <span class="product-price"><?= rupiah($product['price']) ?></span>
                        <a class="button button-outline button-small"
                            href="product.php?id=<?= (int) $product['id'] ?>">Lihat produk</a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="story-band">
        <!-- Bagian story band tetap sama -->
        <div class="story-image" role="img" aria-label="Kue kering buatan rumah"></div>
        <div class="story-copy">
            <p class="eyebrow">PREORDER YANG LEBIH SEGAR</p>
            <h2>Adonan disiapkan setelah DP masuk, bukan disimpan lama di rak.</h2>
            <p>Bayar DP 50% untuk mengunci pesanan. Kami mulai memanggang setelah pembayaran diverifikasi, kemudian
                kirim setelah pelunasan dan ongkir dikonfirmasi.</p>
            <a class="text-link" href="chat.php">Tanya admin Sanggar Tresno &#8594;</a>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>