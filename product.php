<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$productId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$productId) {
    header('Location: ' . app_url('index.php'));
    exit;
}

$query = db()->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1');
$query->execute([$productId]);
$product = $query->fetch();

if (!$product) {
    set_flash('error', 'Produk tidak ditemukan atau sedang tidak aktif.');
    header('Location: ' . app_url('index.php'));
    exit;
}

$minimumDeliveryDate = min_preorder_datetime();
$minDateStr = $minimumDeliveryDate->format('Y-m-d');

$savedDeliveryDate = trim((string) ($_SESSION['preorder_delivery_date'] ?? ''));
$parsedSavedDeliveryDate = DateTimeImmutable::createFromFormat('!Y-m-d', $savedDeliveryDate);
if ($parsedSavedDeliveryDate && $parsedSavedDeliveryDate < $minimumDeliveryDate) {
    unset($_SESSION['preorder_delivery_date']);
    $parsedSavedDeliveryDate = null;
    $savedDeliveryDate = '';
}
$defaultDeliveryDate = $parsedSavedDeliveryDate && $parsedSavedDeliveryDate->format('Y-m-d') === $savedDeliveryDate && $parsedSavedDeliveryDate >= $minimumDeliveryDate
    ? $savedDeliveryDate
    : $minDateStr;

$defaultFulfillment = $_SESSION['preorder_fulfillment'] ?? 'delivery';
$pageTitle = $product['name'] . ' - Detail Pre-order';
$currentNav = 'menu';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell product-detail-shell">
    <!-- Breadcrumb Sesuai Gambar 3.25 & 3.35 -->
    <nav class="product-breadcrumb-bar" aria-label="Breadcrumb">
        <a href="<?= e(app_url('index.php#koleksi')) ?>">&larr; Menu pre-order</a>
        <span class="sep">/</span>
        <a href="<?= e(app_url('index.php?category=' . urlencode(strtolower($product['category'] ?? 'makanan')))) ?>"><?= e($product['category'] ?: 'Kue Kering') ?></a>
        <span class="sep">/</span>
        <span class="current-item"><?= e($product['name']) ?></span>
    </nav>

    <!-- Detail Layout Grid: Kiri Galeri Gambar, Kanan Form Opsi Preorder -->
    <section class="product-main-grid">
        <!-- Galeri Gambar -->
        <div class="product-gallery-side">
            <div class="product-main-image-frame">
                <img id="mainProductImg" src="<?= e(asset_url($product['image_url'] ?: 'assets/product-nastar.png')) ?>" alt="<?= e($product['name']) ?>">
            </div>
            <!-- Thumbnails Gallery Sesuai Mockup Gambar 3.25 -->
            <div class="product-thumbs-row">
                <div class="thumb-box is-active" onclick="changeThumb(this, '<?= e(asset_url($product['image_url'] ?: 'assets/product-nastar.png')) ?>')">
                    <img src="<?= e(asset_url($product['image_url'] ?: 'assets/product-nastar.png')) ?>" alt="Thumbnail 1">
                </div>
                <div class="thumb-box" onclick="changeThumb(this, '<?= e(asset_url('assets/product-kastengel.png')) ?>')">
                    <img src="<?= e(asset_url('assets/product-kastengel.png')) ?>" alt="Thumbnail 2">
                </div>
                <div class="thumb-box" onclick="changeThumb(this, '<?= e(asset_url('assets/product-putri-salju.png')) ?>')">
                    <img src="<?= e(asset_url('assets/product-putri-salju.png')) ?>" alt="Thumbnail 3">
                </div>
            </div>
        </div>

        <!-- Copy & Order Form -->
        <div class="product-info-side">
            <div class="product-badge-category">
                PRE-ORDER &bull; <?= e(strtoupper($product['category'] ?: 'KUE KERING')) ?>
            </div>
            <h1 class="product-title-large"><?= e($product['name']) ?></h1>
            <p class="product-subtitle-text">Detail produk dan opsi pemesanan pre-order Medan.</p>
            
            <div class="product-price-tag">
                <span class="price-val" id="singlePriceDisplay"><?= rupiah($product['price']) ?></span>
                <small class="price-unit">/ toples &bull; dibuat segar</small>
            </div>

            <!-- Form Pemesanan -->
            <form class="product-order-clean-form" method="post" action="<?= e(app_url('index.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">

                <!-- Pilih Tanggal Pre-order: MINIMAL 3 HARI SESUAI REQUEST USER -->
                <div class="form-row-clean">
                    <label for="delivery_date">Pilih tanggal pre-order</label>
                    <input type="date" id="delivery_date" name="delivery_date"
                        min="<?= e($minDateStr) ?>"
                        value="<?= e($defaultDeliveryDate) ?>"
                        required
                        onchange="validateDate(this)"
                        oninput="validateDate(this)">
                    <small class="form-hint-info">
                        Pemesanan minimal 3 hari sebelum pengiriman. Jadwal dapat disesuaikan kembali oleh admin melalui chat jika antrean dapur sedang penuh.
                    </small>
                </div>

                <!-- Metode Penerimaan -->
                <div class="form-row-clean">
                    <label for="fulfillment">Metode penerimaan</label>
                    <select id="fulfillment" name="fulfillment">
                        <option value="delivery" <?= $defaultFulfillment === 'delivery' ? 'selected' : '' ?>>Antar ke alamat &bull; Medan</option>
                        <option value="pickup" <?= $defaultFulfillment === 'pickup' ? 'selected' : '' ?>>Ambil sendiri di Toko</option>
                    </select>
                    <small class="form-hint-info">
                        Ongkir disepakati dengan admin di keranjang. Ambil sendiri juga tersedia tanpa ongkir.
                    </small>
                </div>

                <!-- Jumlah / Kuantitas Stepper -->
                <div class="form-row-clean">
                    <label for="product-quantity">Jumlah</label>
                    <div class="qty-stepper-box">
                        <button type="button" class="stepper-btn-clean" onclick="stepQty(-1)" aria-label="Kurangi kuantitas">&minus;</button>
                        <input id="product-quantity" type="number" name="quantity" value="1" min="1" max="<?= max(1, (int) $product['stock']) ?>" readonly required>
                        <button type="button" class="stepper-btn-clean" onclick="stepQty(1)" aria-label="Tambah kuantitas">&plus;</button>
                        <span class="stock-avail-badge">Minimal 1 &bull; Kuota: <?= (int) $product['stock'] ?> toples</span>
                    </div>
                </div>

                <!-- Tombol Submit Dinamis Harga -->
                <button class="button button-solid-black button-full button-large" type="submit" id="addToCartBtn" <?= (int) $product['stock'] < 1 ? 'disabled' : '' ?>>
                    Tambah ke keranjang &bull; <span id="totalPriceBtnText"><?= rupiah($product['price']) ?></span>
                </button>
            </form>

            <!-- Card Notice DP Sesuai Gambar 3.25 & 3.35 -->
            <div class="notice-dp-pill">
                <span class="check-icon">&#10003;</span>
                <div>
                    <strong>Pesanan dibuat setelah DP diterima</strong>
                    <p>DP 50% dari total pesanan. Pelunasan dan ongkir diselesaikan sebelum pesanan dikirim.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 3 Kolom Fakta & Info Sesuai Mockup Gambar 3.25 & 3.35 -->
    <section class="product-facts-tri-grid">
        <article class="fact-card">
            <h3>Isi &amp; bahan</h3>
            <p><?= nl2br(e($product['description'] ?: 'Menggunakan bahan berkualitas tinggi, mentega wisman pilihan, dan tanpa bahan pengawet.')) ?></p>
        </article>
        <article class="fact-card">
            <h3>Penyimpanan</h3>
            <p>Simpan di wadah kedap udara pada suhu ruang sejuk dan kering. Tahan hingga 2-3 bulan jika wadah tertutup rapat.</p>
        </article>
        <article class="fact-card">
            <h3>Pengiriman &amp; pengambilan</h3>
            <p>Pengiriman menjangkau seluruh area Kota Medan dan sekitarnya. Ongkir dibahas dan disepakati lewat fitur chat internal.</p>
        </article>
    </section>
</main>

<script>
const basePrice = <?= (float) $product['price'] ?>;
const minDate = "<?= e($minDateStr) ?>";
const maxStock = <?= max(1, (int) $product['stock']) ?>;

function stepQty(delta) {
    const input = document.getElementById('product-quantity');
    let current = parseInt(input.value) || 1;
    let next = current + delta;
    if (next < 1) next = 1;
    if (next > maxStock) next = maxStock;
    input.value = next;
    updateTotalBtn(next);
}

function updateTotalBtn(qty) {
    const total = basePrice * qty;
    const formatted = 'Rp ' + total.toLocaleString('id-ID');
    document.getElementById('totalPriceBtnText').innerText = formatted;
}

function validateDate(input) {
    if (!input.value || input.value < minDate) {
        alert('Tanggal pre-order tidak bisa dipilih untuk hari ini, besok, atau lusa. Pemesanan minimal 3 hari dari sekarang (paling cepat ' + minDate + ').\n\nJadwal pengiriman dapat disesuaikan kembali oleh admin melalui chat jika antrean dapur padat.');
        input.value = minDate;
    }
}

document.querySelector('.product-order-clean-form')?.addEventListener('submit', function(e) {
    const input = document.getElementById('delivery_date');
    if (input && (!input.value || input.value < minDate)) {
        e.preventDefault();
        alert('Tanggal pre-order minimal 3 hari setelah dipesan.');
        input.value = minDate;
        input.focus();
    }
});

function changeThumb(el, src) {
    document.getElementById('mainProductImg').src = src;
    document.querySelectorAll('.thumb-box').forEach(b => b.classList.remove('is-active'));
    el.classList.add('is-active');
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
