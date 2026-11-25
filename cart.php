<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$viewer = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);

    if ($action === 'remove' && $productId) {
        unset($_SESSION['cart'][$productId]);
        set_flash('success', 'Produk dihapus dari keranjang belanja.');
    } elseif ($action === 'update' && $productId) {
        $quantity = filter_var($_POST['quantity'] ?? 0, FILTER_VALIDATE_INT);
        if ($quantity <= 0) {
            unset($_SESSION['cart'][$productId]);
            set_flash('success', 'Produk dihapus dari keranjang.');
        } else {
            $product = find_active_product_stock((int) $productId);
            $stock = $product['stock'] ?? false;
            if ($stock === false) {
                unset($_SESSION['cart'][$productId]);
            } else {
                $_SESSION['cart'][$productId] = min(max(1, (int) $quantity), (int) $stock);
            }
        }
    }
    header('Location: ' . app_url('cart.php'));
    exit;
}

$cart = $_SESSION['cart'] ?? [];
$items = [];
$total = 0.0;
if ($cart) {
    $ids = array_values(array_filter(array_map('intval', array_keys($cart))));
    if ($ids) {
        $items = find_cart_products($ids);
        foreach ($items as &$item) {
            $item['quantity'] = min((int) ($cart[$item['id']] ?? 1), (int) $item['stock']);
            $item['subtotal'] = (float) $item['price'] * $item['quantity'];
            $total += $item['subtotal'];
        }
        unset($item);
    }
}

$dpAmount = round($total * 0.5, 2);
$scheduledDate = !empty($_SESSION['preorder_delivery_date']) ? indo_date($_SESSION['preorder_delivery_date']) : indo_date(min_preorder_date());
$fulfillment = $_SESSION['preorder_fulfillment'] ?? 'delivery';

$recentMessages = [];
if ($viewer) {
    $chatQuery = db()->prepare('SELECT * FROM messages WHERE customer_id = ? ORDER BY id DESC LIMIT 3');
    $chatQuery->execute([$viewer['id']]);
    $recentMessages = array_reverse($chatQuery->fetchAll());
        foreach ($recentMessages as &$message) {
            $message['body'] = decrypt_message((string) $message['body']);
        }
        unset($message);
}

$pageTitle = 'Keranjang Checkout';
$currentNav = 'cart';
require __DIR__ . '/app/Views/header.php';
?>
<main class="page-shell cart-page">
    <nav class="checkout-steps" aria-label="Tahap pemesanan">
        <span class="is-current"><b>1</b> Keranjang</span>
        <span><b>2</b> Data penerima</span>
        <span><b>3</b> Pembayaran DP</span>
    </nav>

    <div class="page-heading">
        <p class="eyebrow">PESANAN PRE-ORDER / MEDAN</p>
        <h1>Keranjang kamu</h1>
        <p><?= array_sum(array_map('intval', $cart)) ?> item untuk <?= e($scheduledDate) ?>. Periksa pesanan dan ongkir
            sebelum lanjut checkout.</p>
    </div>

    <?php if (!$items): ?>
    <div class="panel empty-cart-box">
        <div class="empty-icon">&#128722;</div>
        <h2>Keranjang belanjamu masih kosong</h2>
        <p>Pilih aneka kue kering rumahan buatan Sanggar Kresno yang dibuat segar sesuai batch.</p>
        <a class="button button-solid-black" href="<?= e(app_url('index.php#koleksi')) ?>">Pilih menu kue &rarr;</a>
    </div>
    <?php else: ?>
    <div class="cart-layout-grid">
        <div class="cart-main-stack">
            <section class="panel cart-card-section">
                <h2 class="cart-section-title">Produk pesanan</h2>

                <div class="cart-items-list">
                    <?php foreach ($items as $item): ?>
                    <article class="cart-row-item">
                        <div class="cart-row-thumb">
                            <img src="<?= e(asset_url($item['image_url'] ?: 'assets/product-nastar.png')) ?>"
                                alt="<?= e($item['name']) ?>">
                        </div>
                        <div class="cart-row-details">
                            <h3><?= e($item['name']) ?></h3>
                            <span class="cart-row-meta"><?= (int) $item['quantity'] ?> &times;
                                <?= rupiah($item['price']) ?></span>

                            <form method="post" class="cart-item-stepper-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">

                                <div class="mini-stepper">
                                    <button type="submit" name="quantity" value="<?= (int) $item['quantity'] - 1 ?>"
                                        class="mini-step-btn" title="Kurangi">&minus;</button>
                                    <span class="mini-step-val"><?= (int) $item['quantity'] ?></span>
                                    <button type="submit" name="quantity" value="<?= (int) $item['quantity'] + 1 ?>"
                                        class="mini-step-btn" title="Tambah"
                                        <?= (int) $item['quantity'] >= (int) $item['stock'] ? 'disabled' : '' ?>>&plus;</button>
                                </div>
                            </form>
                        </div>
                        <div class="cart-row-price">
                            <strong><?= rupiah($item['subtotal']) ?></strong>
                            <form method="post" class="cart-remove-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                                <button type="submit" class="cart-del-btn" title="Hapus dari keranjang">&times;
                                    Hapus</button>
                            </form>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>

                <div class="cart-add-more-row">
                    <a class="button button-outline-dark button-small" href="<?= e(app_url('index.php#koleksi')) ?>">+
                        Tambah produk lain</a>
                </div>
            </section>

            <section class="panel cart-card-section">
                <h2 class="cart-section-title">Penerimaan &amp; jadwal</h2>
                <div class="delivery-preview-box">
                    <div class="delivery-details-text">
                        <strong><?= $viewer ? e($viewer['name']) : 'Pelanggan' ?></strong>
                        <p>Metode:
                            <strong><?= $fulfillment === 'pickup' ? 'Ambil sendiri di Toko (Medan)' : 'Antar ke alamat' ?></strong>
                        </p>
                        <small class="delivery-schedule-tag">&#128197; Jadwal pre-order: <?= e($scheduledDate) ?> &bull;
                            13.00&ndash;16.00 WIB</small>
                    </div>
                    <a class="text-link-primary" href="<?= e(app_url('checkout.php')) ?>">Ubah data penerima &rarr;</a>
                </div>
            </section>

            <section class="panel cart-card-section chat-preview-card">
                <div class="chat-preview-header">
                    <div>
                        <h2 class="cart-section-title">Negosiasi ongkir &bull; Medan</h2>
                        <small class="chat-sub">Diskusikan ongkos kirim dan waktu kirim terbaik dengan admin
                            kami.</small>
                    </div>
                    <span class="badge-negotiation">Tawar-menawar langsung</span>
                </div>

                <div class="chat-mini-history">
                    <?php if ($recentMessages): ?>
                    <?php foreach ($recentMessages as $msg): ?>
                    <div class="mini-chat-bubble <?= $msg['sender_role'] === 'customer' ? 'from-me' : 'from-admin' ?>">
                        <small><?= $msg['sender_role'] === 'customer' ? 'Anda' : 'Admin' ?>:</small>
                        <p><?= e($msg['body']) ?></p>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <p class="chat-empty-hint">Belum ada obrolan ongkir. Klik tombol di bawah untuk menyepakati ongkos
                        kirim dengan admin.</p>
                    <?php endif; ?>
                </div>

                <div class="chat-preview-actions">
                    <a class="button button-outline-dark button-full" href="<?= e(app_url('chat.php')) ?>">
                        Buka ruang obrolan / Chat admin &rarr;
                    </a>
                </div>
            </section>
        </div>

        <aside class="cart-aside-stack">
            <section class="summary-panel cart-sticky-summary">
                <h2>Ringkasan pesanan</h2>
                <p class="order-reference">Pre-order &bull; <?= array_sum(array_map('intval', $cart)) ?> item</p>

                <div class="summary-row">
                    <span>Subtotal produk</span>
                    <span><?= rupiah($total) ?></span>
                </div>
                <div class="summary-row">
                    <span>Ongkir</span>
                    <span class="ongkir-highlight">Dinegosiasikan via chat</span>
                </div>
                <div class="summary-row summary-total">
                    <span>Total pesanan</span>
                    <span><?= rupiah($total) ?></span>
                </div>

                <div class="dp-highlight-card">
                    <div class="dp-badge">Wajib di Awal</div>
                    <div class="dp-row">
                        <strong>DP 50% - Bayar sekarang</strong>
                        <b class="dp-val"><?= rupiah($dpAmount) ?></b>
                    </div>
                    <small>Sisa pelunasan <?= rupiah($dpAmount) ?> dibayar sebelum pesanan dikirim.</small>
                </div>

                <a class="button button-solid-black button-full button-large" href="<?= e(app_url('checkout.php')) ?>">
                    Lanjut checkout &rarr;
                </a>

                <div class="cart-guarantee-note">
                    <span class="shield-icon">&#128737;</span>
                    <p>Kue dibuat higienis setelah pembayaran DP 50% terverifikasi. Tidak ada perubahan pesanan tanpa
                        konfirmasi pelanggan.</p>
                </div>
            </section>
        </aside>
    </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/app/Views/footer.php'; ?>