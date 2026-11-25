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
$order = find_order_for_viewer((int) $orderId, (int) $user['id'], (string) $user['role']);

if (!$order) {
    set_flash('error', 'Pesanan tidak ditemukan.');
    header('Location: ' . app_url('orders.php'));
    exit;
}

$items = list_order_items((int) $orderId);

$isDp = $order['payment_status'] === 'unpaid';

$sisaPelunasan = max(0, (float) $order['total'] - (float) $order['dp_amount']);
if (!empty($order['shipping_fee'])) {
    $sisaPelunasan += (float) $order['shipping_fee'];
}

$amountToPay = $isDp ? (float) $order['dp_amount'] : $sisaPelunasan;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'simulate_pay') {
        if ($isDp) {
            $update = $pdo->prepare("UPDATE orders SET payment_status = 'dp_paid', status = CASE WHEN status = 'pending' THEN 'processing' ELSE status END WHERE id = ?");
            $update->execute([$orderId]);
            set_flash('success', 'Pembayaran DP 50% berhasil diverifikasi! Pesananmu masuk tahap persiapan produksi.');
        } else {
            $update = $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?");
            $update->execute([$orderId]);
            set_flash('success', 'Pelunasan berhasil diverifikasi! Pembayaran pesanan Anda sudah lunas.');
        }
        header('Location: ' . app_url('order_detail.php?id=' . $orderId));
        exit;
    }
}

$orderCode = 'SR-' . date('y', strtotime($order['created_at'])) . str_pad((string)$orderId, 4, '0', STR_PAD_LEFT);
$vaNumber = '80777 ' . substr(preg_replace('/\D/', '', (string) $order['phone']) ?: '081234567810', 0, 12);
$pageTitle = ($isDp ? 'Pembayaran DP' : 'Pelunasan') . ' - ' . $orderCode;
$currentNav = 'orders';
require __DIR__ . '/app/Views/header.php';
?>
<main class="page-shell payment-page">
    <nav class="checkout-steps" aria-label="Tahap pemesanan">
        <span class="is-complete"><b>&#10003;</b> 1 Keranjang</span>
        <span class="is-complete"><b>&#10003;</b> 2 Data penerima</span>
        <span class="is-current"><b>3</b> <?= $isDp ? 'Pembayaran DP' : 'Pelunasan' ?></span>
    </nav>

    <div class="page-heading">
        <p class="eyebrow">PESANAN #<?= e($orderCode) ?></p>
        <h1>Selesaikan <?= $isDp ? 'DP kamu' : 'Pelunasan' ?></h1>
        <p>Pesanan tersimpan. Bayar
            <?= $isDp ? 'DP 50% untuk mengonfirmasi slot pre-order dapur kami.' : 'sisa tagihan agar pesanan dapat segera dikirim.' ?>
        </p>
    </div>

    <div class="payment-grid-layout">
        <div class="payment-main-col">
            <div class="panel payment-status-banner">
                <div class="pulse-indicator"></div>
                <div>
                    <strong>Status: Menunggu Pembayaran (Pending)</strong>
                    <small>Batas konfirmasi: <?= e(date('d M Y', strtotime($order['delivery_date'] ?? '+3 days'))) ?>,
                        18:00 WIB.</small>
                </div>
            </div>

            <section class="panel qris-box">
                <div class="qris-header">
                    <div class="qris-logo">
                        <span class="qris-badge">QRIS</span>
                        <span class="qris-sub">QR Indonesian Standard</span>
                    </div>
                    <span class="method-tag">Metode otomatis</span>
                </div>

                <div class="qris-body">
                    <p class="qris-title-amount">TOTAL <?= $isDp ? 'DP' : 'PELUNASAN' ?> YANG DIBAYAR</p>
                    <h2 class="qris-nominal"><?= rupiah($amountToPay) ?></h2>

                    <div class="qris-code-frame">
                        <svg class="qris-qr-svg" viewBox="0 0 200 200" width="180" height="180"
                            aria-label="QR Code QRIS">
                            <rect width="200" height="200" fill="#ffffff" rx="8" />
                            <rect x="20" y="20" width="46" height="46" fill="#183c33" rx="4" />
                            <rect x="27" y="27" width="32" height="32" fill="#ffffff" rx="2" />
                            <rect x="33" y="33" width="20" height="20" fill="#183c33" rx="2" />
                            <rect x="134" y="20" width="46" height="46" fill="#183c33" rx="4" />
                            <rect x="141" y="27" width="32" height="32" fill="#ffffff" rx="2" />
                            <rect x="147" y="33" width="20" height="20" fill="#183c33" rx="2" />
                            <rect x="20" y="134" width="46" height="46" fill="#183c33" rx="4" />
                            <rect x="27" y="141" width="32" height="32" fill="#ffffff" rx="2" />
                            <rect x="33" y="147" width="20" height="20" fill="#183c33" rx="2" />
                            <rect x="80" y="80" width="40" height="40" fill="#c85f43" rx="6" />
                            <text x="100" y="105" font-family="'DM Serif Display', Georgia, serif" font-size="18"
                                fill="#ffffff" text-anchor="middle" font-weight="bold">ST</text>
                        </svg>
                    </div>

                    <p class="qris-merchant">NMID: ID1029384756201 &middot; <strong>SANGGAR KRESNO</strong></p>

                    <ol class="payment-steps-list">
                        <li><span>01</span> Buka aplikasi m-Banking atau e-Wallet (GoPay, OVO, Dana, ShopeePay).</li>
                        <li><span>02</span> Scan kode QRIS di atas dan pastikan nama penerima <strong>SANGGAR
                                KRESNO</strong>.</li>
                        <li><span>03</span> Selesaikan pembayaran sebesar <strong><?= rupiah($amountToPay) ?></strong>.
                        </li>
                    </ol>

                    <form method="post" class="qris-action-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="simulate_pay">
                        <button type="submit" class="button button-dark button-large button-full">
                            &#10003; Simulasi Bayar <?= $isDp ? 'DP' : 'Pelunasan' ?>
                        </button>
                    </form>
                    <small class="qris-hint">Simulasi pembayaran instan untuk pengujian alur pre-order sistem.</small>
                </div>
            </section>

            <section class="panel va-box">
                <div class="va-header">
                    <div>
                        <h3>Alternatif: Virtual Account BCA</h3>
                        <p style="font-size: 11px; color: var(--muted); margin: 0;">Bisa transfer via ATM, m-BCA, atau
                            KlikBCA.</p>
                    </div>
                    <span class="va-bank-badge">BCA</span>
                </div>
                <div class="va-number-card">
                    <div>
                        <span class="va-label">NOMOR VIRTUAL ACCOUNT</span>
                        <strong class="va-digits" id="vaNumberDisplay"><?= e($vaNumber) ?></strong>
                        <small>a.n. SANGGAR KRESNO - <?= rupiah($amountToPay) ?></small>
                    </div>
                    <button type="button" class="button button-outline button-small" onclick="copyVA()">
                        Salin nomor VA
                    </button>
                </div>
                <p class="va-note">m-BCA &rarr; m-Transfer &rarr; BCA Virtual Account &rarr; Masukkan nomor &rarr;
                    Konfirmasi nominal.</p>
            </section>
        </div>

        <aside class="payment-aside-col">
            <section class="summary-panel">
                <h2>Ringkasan pesanan</h2>
                <p class="order-reference">Kode: <strong>#<?= e($orderCode) ?></strong> &middot; <?= count($items) ?>
                    menu</p>

                <div class="payment-items-preview">
                    <?php foreach ($items as $item): ?>
                    <div class="payment-item-row">
                        <span><?= e($item['product_name']) ?> &times; <?= (int) $item['quantity'] ?></span>
                        <span><?= rupiah((float) $item['price'] * (int) $item['quantity']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="summary-row">
                    <span>Subtotal produk</span>
                    <span><?= rupiah($order['total']) ?></span>
                </div>

                <?php if (!empty($order['shipping_fee'])): ?>
                <div class="summary-row">
                    <span>Ongkir</span>
                    <span><?= rupiah($order['shipping_fee']) ?></span>
                </div>
                <div class="summary-row summary-total">
                    <span>Total pesanan</span>
                    <span><?= rupiah((float) $order['total'] + (float) $order['shipping_fee']) ?></span>
                </div>
                <?php else: ?>
                <div class="summary-row">
                    <span>Ongkir</span>
                    <span>Belum disepakati</span>
                </div>
                <div class="summary-row summary-total">
                    <span>Total pesanan</span>
                    <span><?= rupiah($order['total']) ?></span>
                </div>
                <?php endif; ?>

                <div class="dp-highlight-card">
                    <div class="dp-badge"><?= $isDp ? 'Wajib di Awal' : 'Selesaikan Pembayaran' ?></div>
                    <div class="dp-row">
                        <strong><?= $isDp ? 'DP 50%' : 'Pelunasan' ?> &middot; Bayar sekarang</strong>
                        <b class="dp-val"><?= rupiah($amountToPay) ?></b>
                    </div>
                    <?php if ($isDp): ?>
                    <small>Sisa pelunasan selanjutnya: <?= rupiah($sisaPelunasan) ?>.</small>
                    <?php endif; ?>
                </div>
            </section>

            <section class="panel schedule-aside-panel">
                <h3>Jadwal pesanan</h3>
                <p class="schedule-date-highlight">
                    <?= e(date('l, d M Y', strtotime($order['delivery_date'] ?? '+3 days'))) ?>
                </p>
                <p class="schedule-address">
                    <strong>Penerima:</strong> <?= e($order['recipient_name']) ?> (<?= e($order['phone']) ?>)<br>
                    <strong>Tujuan:</strong> <?= e($order['address']) ?>
                </p>
                <p class="schedule-notice">
                    Pesanan
                    <?= $isDp ? 'mulai diproduksi setelah pembayaran DP terkonfirmasi' : 'akan dikirim setelah pembayaran lunas' ?>.
                </p>
                <a class="button button-outline button-full" href="<?= e(app_url('chat.php')) ?>">
                    Hubungi Admin / Chat
                </a>
            </section>
        </aside>
    </div>
</main>

<script>
function copyVA() {
    const text = document.getElementById('vaNumberDisplay').innerText.replace(/\s+/g, '');
    navigator.clipboard.writeText(text).then(function() {
        alert('Nomor Virtual Account disalin: ' + text);
    }).catch(function() {
        alert('Gagal menyalin. Silakan salin manual: ' + text);
    });
}
</script>

<?php require __DIR__ . '/app/Views/footer.php'; ?>