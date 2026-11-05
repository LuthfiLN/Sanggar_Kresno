<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);

    if ($action === 'remove' && $productId) {
        unset($_SESSION['cart'][$productId]);
        set_flash('success', 'Produk dihapus dari tas belanja.');
    } elseif ($action === 'update' && $productId) {
        $quantity = filter_var($_POST['quantity'] ?? 0, FILTER_VALIDATE_INT);

        // Jika kuantitas dikurangi hingga 0 atau kurang, hapus produk dari keranjang
        if ($quantity <= 0) {
            unset($_SESSION['cart'][$productId]);
            set_flash('success', 'Produk dihapus dari tas belanja.');
        } else {
            $stockQuery = db()->prepare('SELECT stock FROM products WHERE id = ? AND is_active = 1');
            $stockQuery->execute([$productId]);
            $stock = $stockQuery->fetchColumn();
            if ($stock === false) {
                unset($_SESSION['cart'][$productId]);
            } else {
                $_SESSION['cart'][$productId] = min(max(1, (int) $quantity), (int) $stock);
            }
        }
    }
    header('Location: cart.php');
    exit;
}

$cart = $_SESSION['cart'] ?? [];
$items = [];
$total = 0.0;
if ($cart) {
    $ids = array_values(array_filter(array_map('intval', array_keys($cart))));
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = db()->prepare("SELECT id, name, price, stock, image_url FROM products WHERE is_active = 1 AND id IN ($placeholders)");
        $query->execute($ids);
        $items = $query->fetchAll();
        foreach ($items as &$item) {
            $item['quantity'] = min((int) ($cart[$item['id']] ?? 1), (int) $item['stock']);
            $item['subtotal'] = (float) $item['price'] * $item['quantity'];
            $total += $item['subtotal'];
        }
        unset($item);
    }
}
$pageTitle = 'Tas belanja';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell">
    <nav class="checkout-steps" aria-label="Tahap pemesanan">
        <span class="is-current"><b>1</b> Keranjang</span>
        <span><b>2</b> Checkout</span>
        <span><b>3</b> Konfirmasi DP</span>
    </nav>

    <div class="page-heading">
        <p class="eyebrow">PILIHANMU</p>
        <h1>Keranjang kamu</h1>
        <p><?= array_sum(array_map('intval', $cart)) ?> item. Periksa kue dan jadwal sebelum lanjut checkout.</p>
    </div>

    <?php if (!$items): ?>
    <div class="panel">
        <p>Tasmu masih kosong.</p>
        <a class="button button-dark" href="index.php#koleksi">Lihat koleksi</a>
    </div>
    <?php else: ?>
    <div class="cart-layout">
        <section class="cart-main-column">
            <div class="panel cart-products-panel">
                <h2 class="checkout-section-title">Produk pesanan</h2>

                <?php foreach ($items as $item): ?>
                <article class="cart-item">
                    <img src="<?= e(asset_url($item['image_url'])) ?>" alt="<?= e($item['name']) ?>">

                    <div class="cart-item-details">
                        <h3><?= e($item['name']) ?></h3>
                        <p class="cart-item-meta"><?= (int) $item['quantity'] ?> &times; <?= rupiah($item['price']) ?>
                        </p>

                        <!-- Stepper Tombol - dan + yang langsung kirim update -->
                        <form method="post" class="cart-stepper">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">

                            <button type="submit" name="quantity" value="<?= (int)$item['quantity'] - 1 ?>"
                                class="stepper-btn" aria-label="Kurangi kuantitas">&minus;</button>
                            <span class="stepper-count"><?= (int) $item['quantity'] ?></span>
                            <button type="submit" name="quantity" value="<?= (int)$item['quantity'] + 1 ?>"
                                class="stepper-btn" aria-label="Tambah kuantitas"
                                <?= (int)$item['quantity'] >= (int)$item['stock'] ? 'disabled' : '' ?>>&plus;</button>
                        </form>
                    </div>

                    <div class="cart-item-price-right">
                        <strong><?= rupiah($item['subtotal']) ?></strong>
                    </div>
                </article>
                <?php endforeach; ?>

                <a class="text-link cart-add-more" href="index.php#koleksi">+ Tambah produk</a>
            </div>

            <section class="cart-negotiation">
                <div>
                    <p class="eyebrow">PENGIRIMAN MEDAN</p>
                    <h2>Sepakati ongkir</h2>
                    <p><?= ($_SESSION['preorder_fulfillment'] ?? 'delivery') === 'pickup' ? 'Pilihan saat ini: ambil sendiri.' : 'Ongkir belum ditambahkan ke total. Diskusikan dengan admin sebelum checkout.' ?>
                    </p>
                </div>
                <a class="button button-outline" href="chat.php">Chat admin</a>
            </section>
        </section>

        <aside class="cart-summary-column">
            <section class="summary-panel">
                <h2>Ringkasan pesanan</h2>
                <p class="order-reference">Keranjang &middot; <?= array_sum(array_map('intval', $cart)) ?> item</p>
                <div class="summary-row"><span>Subtotal produk</span><span><?= rupiah($total) ?></span></div>
                <div class="summary-row"><span>Ongkir</span><span>Belum disepakati</span></div>
                <div class="summary-row summary-total"><span>Total sementara</span><span><?= rupiah($total) ?></span>
                </div>
                <div class="dp-summary">
                    <strong>DP 50% sebelum dibuat</strong>
                    <b><?= rupiah(round($total * 0.5, 2)) ?></b>
                    <small>Sisa pelunasan <?= rupiah(round($total * 0.5, 2)) ?></small>
                </div>
                <a class="button button-dark" href="checkout.php">Lanjut checkout <span
                        aria-hidden="true">&#8594;</span></a>
                <p class="payment-note">Jumlah akhir menunggu kesepakatan ongkir. Pesanan diproses setelah DP
                    dikonfirmasi.</p>
            </section>
            <p class="cart-delivery-note">
                Jadwal:
                <?= !empty($_SESSION['preorder_delivery_date']) ? e(date('D, d M Y', strtotime($_SESSION['preorder_delivery_date']))) : 'Pilih saat checkout' ?><br>
                Metode:
                <?= ($_SESSION['preorder_fulfillment'] ?? 'delivery') === 'pickup' ? 'Ambil sendiri' : 'Antar ke alamat' ?>
            </p>
        </aside>
    </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>