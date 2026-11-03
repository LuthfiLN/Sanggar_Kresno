<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
$productId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$productId) {
    http_response_code(404);
    exit('Produk tidak ditemukan.');
}
$query = db()->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1');
$query->execute([$productId]);
$product = $query->fetch();
if (!$product) {
    http_response_code(404);
    exit('Produk tidak ditemukan.');
}
$minimumDeliveryDate = new DateTimeImmutable('today +3 days');
$savedDeliveryDate = trim((string) ($_SESSION['preorder_delivery_date'] ?? ''));
$parsedSavedDeliveryDate = DateTimeImmutable::createFromFormat('!Y-m-d', $savedDeliveryDate);
$defaultDeliveryDate = $parsedSavedDeliveryDate && $parsedSavedDeliveryDate->format('Y-m-d') === $savedDeliveryDate && $parsedSavedDeliveryDate >= $minimumDeliveryDate
    ? $savedDeliveryDate
    : $minimumDeliveryDate->format('Y-m-d');
$defaultFulfillment = $_SESSION['preorder_fulfillment'] ?? 'delivery';
$pageTitle = $product['name'];
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell product-detail-page">
    <p class="product-breadcrumb"><a href="index.php#koleksi">&#8592; Menu pre-order</a> /
        <?= e($product['category']) ?> / <?= e($product['name']) ?></p>
    <section class="product-detail-grid">
        <div class="product-gallery">
            <div class="product-detail-image"><img
                    src="<?= e(asset_url($product['image_url'] ?: 'assets/product-nastar.png')) ?>"
                    alt="<?= e($product['name']) ?>"></div>
            <div class="product-gallery-caption"><span>Foto produk</span><span>Dapur Tresno / Medan</span></div>
        </div>
        <div class="product-detail-copy">
            <p class="eyebrow">PRE-ORDER / <?= e(strtoupper($product['category'])) ?></p>
            <h1><?= e($product['name']) ?></h1>
            <p class="product-subtitle">Dibuat segar setelah pembayaran DP dikonfirmasi.</p>
            <p class="product-detail-price"><?= rupiah($product['price']) ?><small> / toples</small></p>
            <form class="product-order-form" method="post" action="<?= e(app_url('index.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <label>Tanggal pre-order<input type="date" name="delivery_date"
                        min="<?= e($minimumDeliveryDate->format('Y-m-d')) ?>"
                        value="<?= e($defaultDeliveryDate) ?>" required><small>Pilih tanggal minimal 3 hari setelah pemesanan. Jadwal dan
                        ketersediaan dikonfirmasi admin.</small></label>
                <label>Metode penerimaan<select name="fulfillment">
                        <option value="delivery" <?= $defaultFulfillment === 'delivery' ? 'selected' : '' ?>>Antar ke
                            alamat</option>
                        <option value="pickup" <?= $defaultFulfillment === 'pickup' ? 'selected' : '' ?>>Ambil sendiri
                        </option>
                    </select><small>Ongkir area Medan dinegosiasikan dengan admin di keranjang.</small></label>
                <div class="product-quantity-row"><label for="product-quantity">Jumlah</label><input
                        id="product-quantity" type="number" name="quantity" value="1" min="1"
                        max="<?= max(1, (int) $product['stock']) ?>" required><span>Kuota preorder:
                        <?= (int) $product['stock'] ?></span></div>
                <button class="button button-dark" type="submit"
                    <?= (int) $product['stock'] < 1 ? 'disabled' : '' ?>>Tambah ke keranjang <span
                        aria-hidden="true">&#8594;</span></button>
            </form>
            <div class="product-payment-note"><strong>Pesanan dibuat setelah DP diterima</strong><span>DP 50% dari total
                    produk. Pelunasan dilakukan sebelum pesanan dikirim.</span></div>
        </div>
    </section>
    <section class="product-detail-facts">
        <article>
            <h2>Deskripsi</h2>
            <p><?= nl2br(e($product['description'])) ?></p>
        </article>
        <article>
            <h2>Penyimpanan</h2>
            <p>Simpan dalam wadah tertutup di tempat sejuk dan kering.</p>
        </article>
        <article>
            <h2>Pengiriman & pengambilan</h2>
            <p>Pengiriman tersedia di Medan dan sekitarnya. Ongkir disepakati melalui chat sebelum pembayaran DP.</p>
        </article>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>