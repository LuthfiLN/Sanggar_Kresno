<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
$user = require_login();

$orderId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$orderId) {
    header('Location: ' . app_url('orders.php'));
    exit;
}

$pdo = db();
$orderQuery = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND (user_id = ? OR ? = "admin")');
$orderQuery->execute([$orderId, $user['id'], $user['role']]);
$order = $orderQuery->fetch();

if (!$order) {
    set_flash('error', 'Pesanan tidak ditemukan.');
    header('Location: ' . app_url('orders.php'));
    exit;
}

$itemsQuery = $pdo->prepare('SELECT product_name, price, quantity FROM order_items WHERE order_id = ? ORDER BY id');
$itemsQuery->execute([$orderId]);
$items = $itemsQuery->fetchAll();

$isDpPaid = in_array($order['payment_status'], ['dp_paid', 'paid'], true);
$isFullyPaid = $order['payment_status'] === 'paid';

$stepStatus = 1;
if ($isDpPaid) {
    $stepStatus = 2;
}
if ($order['status'] === 'processing') {
    $stepStatus = 3;
} elseif ($order['status'] === 'shipped') {
    $stepStatus = 4;
} elseif ($order['status'] === 'completed') {
    $stepStatus = 5;
}

$orderCode = order_code($order);
$totalWithShipping = (float) $order['total'] + (int) ($order['shipping_fee'] ?? 0);
$paidAmount = $isDpPaid ? (float) $order['dp_amount'] : 0.0;
if ($isFullyPaid) {
    $paidAmount = $totalWithShipping;
}
$remainingAmount = max(0.0, $totalWithShipping - $paidAmount);

$scheduledDateStr = $order['delivery_date'] ? indo_date($order['delivery_date']) : 'Belum ditentukan';
$pageTitle = 'Status Preorder #' . $orderCode;
$currentNav = 'orders';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell order-detail-page">
    <nav class="product-breadcrumb-bar" aria-label="Breadcrumb">
        <a href="<?= e(app_url('orders.php')) ?>">&larr; Riwayat Pesanan</a>
        <span class="sep">/</span>
        <span class="current-item">Pre-order #<?= e($orderCode) ?></span>
    </nav>

    <div class="order-confirmed-banner">
        <div class="confirmed-left">
            <div class="confirmed-title-row">
                <span class="confirmed-check">&#10003;</span>
                <div>
                    <h1><?= $isDpPaid ? 'Pesananmu terkonfirmasi!' : 'Menunggu pembayaran DP' ?></h1>
                    <p><?= $isDpPaid ? 'Terima kasih, ' . e($order['recipient_name']) . '. DP sudah diterima, sekarang giliran dapur kami menyiapkannya.' : 'Pesanan telah dicatat. Selesaikan pembayaran DP 50% untuk mengamankan slot batch produksi.' ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="confirmed-meta-tags">
            <span class="order-tag-num">#<?= e($orderCode) ?></span>
            <span class="status-badge-clean status-<?= e($order['payment_status']) ?>">
                <?= $isDpPaid ? 'DP Paid &bull; Terverifikasi' : 'Menunggu DP 50%' ?>
            </span>
            <span class="status-badge-clean status-<?= e($order['status']) ?>">
                <?= e(order_status_label($order['status'])) ?>
            </span>
        </div>
    </div>

    <div class="order-detail-layout-grid">
        <div class="order-detail-main-stack">
            <section class="panel order-stepper-panel">
                <h2 class="panel-inner-title">Perjalanan pesanan</h2>

                <div class="delivery-highlight-pill">
                    <span class="pill-icon">&#128197;</span>
                    <div>
                        <strong>Dijadwalkan untuk <?= e($scheduledDateStr) ?></strong>
                        <small>Antar 13.00&ndash;16.00 WIB &bull; <?= e($order['address']) ?></small>
                    </div>
                </div>

                <ol class="journey-stepper-list">
                    <li
                        class="journey-step <?= $stepStatus >= 1 ? 'is-done' : '' ?> <?= $stepStatus === 1 ? 'is-current' : '' ?>">
                        <span class="journey-dot"><?= $stepStatus > 1 ? '&#10003;' : '1' ?></span>
                        <div class="journey-info">
                            <strong>Pesanan dibuat</strong>
                            <small><?= indo_date($order['created_at']) ?>,
                                <?= date('H.i', strtotime($order['created_at'])) ?> WIB</small>
                        </div>
                    </li>

                    <li
                        class="journey-step <?= $stepStatus >= 2 ? 'is-done' : '' ?> <?= $stepStatus === 2 ? 'is-current' : '' ?>">
                        <span class="journey-dot"><?= $stepStatus > 2 ? '&#10003;' : '2' ?></span>
                        <div class="journey-info">
                            <strong>DP diterima &amp; diverifikasi</strong>
                            <small><?= $isDpPaid ? 'DP 50% (' . rupiah($order['dp_amount']) . ') terverifikasi otomatis via QRIS/VA' : 'Menunggu pembayaran DP 50%' ?></small>
                        </div>
                    </li>

                    <li
                        class="journey-step <?= $stepStatus >= 3 ? 'is-done' : '' ?> <?= $stepStatus === 3 ? 'is-current' : '' ?>">
                        <span class="journey-dot"><?= $stepStatus > 3 ? '&#10003;' : '3' ?></span>
                        <div class="journey-info">
                            <strong>Menunggu jadwal produksi &amp; memanggang</strong>
                            <small><?= $stepStatus >= 3 ? 'Dapur menyiapkan bahan & memanggang kue segar' : 'Dimulai setelah DP terverifikasi' ?></small>
                        </div>
                    </li>

                    <li
                        class="journey-step <?= $stepStatus >= 4 ? 'is-done' : '' ?> <?= $stepStatus === 4 ? 'is-current' : '' ?>">
                        <span class="journey-dot"><?= $stepStatus > 4 ? '&#10003;' : '4' ?></span>
                        <div class="journey-info">
                            <strong>Dikirim ke alamatmu / Siap diambil</strong>
                            <small>Estimasi: <?= e($scheduledDateStr) ?> (13.00&ndash;16.00 WIB)</small>
                        </div>
                    </li>

                    <li
                        class="journey-step <?= $stepStatus >= 5 ? 'is-done' : '' ?> <?= $stepStatus === 5 ? 'is-current' : '' ?>">
                        <span class="journey-dot">5</span>
                        <div class="journey-info">
                            <strong>Pesanan diterima</strong>
                            <small>Konfirmasi selesai setelah kue dinikmati</small>
                        </div>
                    </li>
                </ol>
            </section>

            <section class="panel order-items-target-panel">
                <h2 class="panel-inner-title">Pesanan &amp; tujuan</h2>

                <div class="order-items-table-list">
                    <?php foreach ($items as $item): ?>
                    <div class="order-item-clean-row">
                        <div class="item-meta-group">
                            <strong><?= e($item['product_name']) ?></strong>
                            <small><?= (int) $item['quantity'] ?> toples &times; <?= rupiah($item['price']) ?></small>
                        </div>
                        <strong
                            class="item-subtotal-val"><?= rupiah((float) $item['price'] * (int) $item['quantity']) ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="recipient-card-clean">
                    <strong><?= e($order['recipient_name']) ?> &bull; <?= e($order['phone']) ?></strong>
                    <p class="recipient-addr"><?= nl2br(e($order['address'])) ?></p>
                    <?php if ($order['notes']): ?>
                    <p class="recipient-notes">Catatan: "<?= e($order['notes']) ?>"</p>
                    <?php endif; ?>
                    <div class="ongkir-status-tag">
                        <?php if (!empty($order['shipping_fee'])): ?>
                        <span class="tag-done">&#10003; Ongkir <?= rupiah($order['shipping_fee']) ?> (Disepakati)</span>
                        <?php else: ?>
                        <span class="tag-waiting">Ongkir dinegosiasikan via chat</span>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>

        <aside class="order-detail-aside-stack">
            <!-- Rincian Pembayaran -->
            <section class="summary-panel">
                <h2>Rincian pembayaran</h2>
                <p class="order-reference">#<?= e($orderCode) ?> &bull; <?= count($items) ?> item</p>

                <div class="summary-row">
                    <span>Subtotal produk</span>
                    <span><?= rupiah($order['total']) ?></span>
                </div>
                <div class="summary-row">
                    <span>Ongkir disepakati</span>
                    <span><?= !empty($order['shipping_fee']) ? rupiah($order['shipping_fee']) : 'Dinegosiasikan' ?></span>
                </div>
                <div class="summary-row summary-total">
                    <span>Total pesanan</span>
                    <span><?= rupiah($totalWithShipping) ?></span>
                </div>
                <div class="summary-row dp-paid-row">
                    <span>DP 50% <?= $isDpPaid ? 'sudah dibayar' : 'belum dibayar' ?></span>
                    <strong><?= $isDpPaid ? rupiah($order['dp_amount']) : 'Rp 0' ?></strong>
                </div>
                <div class="summary-row remaining-row">
                    <span>Sisa pelunasan</span>
                    <span><?= rupiah($remainingAmount) ?></span>
                </div>
                <small class="summary-note">Pelunasan paling lambat <?= e($scheduledDateStr) ?>, 10:00 WIB, sebelum
                    pesanan dikirim.</small>
            </section>

            <section class="panel action-card-panel">
                <span class="card-eyebrow">TAGIHAN SAAT INI</span>
                <?php if (!$isDpPaid): ?>
                <h3 class="action-amount-large"><?= rupiah($order['dp_amount']) ?></h3>
                <p class="action-desc">DP 50% wajib dibayar terlebih dahulu untuk mengonfirmasi pesanan ke dapur.</p>
                <a class="button button-solid-black button-full"
                    href="<?= e(app_url('payment.php?id=' . (int) $order['id'])) ?>">
                    Bayar DP 50% Sekarang &rarr;
                </a>
                <?php else: ?>
                <h3 class="action-amount-large"><?= rupiah($remainingAmount) ?></h3>
                <p class="action-desc">Sisa pelunasan produk dan ongkir sebelum kurir berangkat.</p>
                <button type="button" class="button button-solid-black button-full"
                    onclick="alert('Pelunasan dapat ditransfer via QRIS/VA sebelum tanggal pengiriman. Silakan konfirmasi via Chat Admin.')">
                    Lihat instruksi pelunasan
                </button>
                <?php endif; ?>

                <button type="button" class="button button-outline-dark button-full button-print"
                    onclick="window.print()">
                    &#128438; Unduh ringkasan pesanan
                </button>
            </section>

            <section class="panel proof-dp-panel">
                <h3>Bukti DP</h3>
                <div class="proof-row">
                    <span>Status</span>
                    <strong><?= $isDpPaid ? 'Terverifikasi' : 'Menunggu' ?></strong>
                </div>
                <div class="proof-row">
                    <span>Metode</span>
                    <strong>QRIS / Virtual Account</strong>
                </div>
                <div class="proof-row">
                    <span>Waktu</span>
                    <span><?= indo_date($order['created_at']) ?></span>
                </div>
                <div class="proof-actions">
                        <a class="button button-outline-dark button-full button-small" href="<?= e(app_url('chat.php?order=' . (int) $order['id'])) ?>">
                            Chat admin toko
                        </a>
                </div>
            </section>
        </aside>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>