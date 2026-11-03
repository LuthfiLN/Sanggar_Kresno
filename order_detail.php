<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
$user = require_login();
$orderId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$orderId) {
    http_response_code(404);
    exit('Preorder tidak ditemukan.');
}
$orderQuery = db()->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
$orderQuery->execute([$orderId, $user['id']]);
$order = $orderQuery->fetch();
if (!$order) {
    http_response_code(404);
    exit('Preorder tidak ditemukan.');
}
$itemsQuery = db()->prepare('SELECT product_name, price, quantity FROM order_items WHERE order_id = ? ORDER BY id');
$itemsQuery->execute([$orderId]);
$items = $itemsQuery->fetchAll();
$statusOrder = ['pending', 'processing', 'shipped', 'completed'];
$statusPosition = array_search($order['status'], $statusOrder, true);
$statusPosition = $statusPosition === false ? -1 : $statusPosition;
$pageTitle = 'Detail preorder #' . $orderId;
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell order-detail-page">
    <p class="product-breadcrumb"><a href="orders.php">&#8592; Pesanan saya</a> / Preorder #<?= (int) $order['id'] ?>
    </p>
    <div class="order-detail-heading">
        <div>
            <p class="eyebrow">PREORDER / MEDAN</p>
            <h1><?= $order['status'] === 'completed' ? 'Pesanan selesai' : 'Status preorder' ?></h1>
            <p>Terima kasih, <?= e($order['recipient_name']) ?>. Pantau tahapan dan pembayaran pesananmu di sini.</p>
        </div>
        <span class="status status-<?= e($order['status']) ?>"><?= e(order_status_label($order['status'])) ?></span>
    </div>
    <div class="order-detail-layout">
        <div class="order-detail-main">
            <section class="panel order-progress-panel">
                <h2>Perjalanan pesanan</h2>
                <ol class="order-progress">
                    <?php foreach ($statusOrder as $index => $status): ?>
                    <?php if ($status === 'processing' && $order['payment_status'] === 'unpaid') {
                            $label = 'Menunggu DP';
                        } else {
                            $label = order_status_label($status);
                        } ?>
                    <li
                        class="<?= $index <= $statusPosition ? 'is-complete' : '' ?> <?= $index === $statusPosition ? 'is-current' : '' ?>">
                        <span
                            class="order-progress-dot"><?= $index < $statusPosition ? '&#10003;' : (int) ($index + 1) ?></span>
                        <div><strong><?= e($label) ?></strong><small><?php if ($status === 'pending'): ?>Preorder
                                tercatat, menunggu verifikasi DP.<?php elseif ($status === 'processing'): ?>Kue
                                disiapkan setelah DP diterima.<?php elseif ($status === 'shipped'): ?>Pesanan sedang
                                dalam pengiriman.<?php else: ?>Konfirmasi pesanan setelah
                                diterima.<?php endif; ?></small></div>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <div class="delivery-date-box"><strong>Jadwal
                        pengiriman</strong><span><?= $order['delivery_date'] ? e(date('l, d M Y', strtotime($order['delivery_date']))) : 'Belum ditentukan' ?></span><small><?= e($order['address']) ?></small>
                </div>
            </section>
            <section class="panel order-items-panel">
                <h2>Produk & tujuan</h2>
                <?php foreach ($items as $item): ?>
                <div class="order-detail-item">
                    <div><strong><?= e($item['product_name']) ?></strong><small><?= (int) $item['quantity'] ?> toples
                            &middot; <?= rupiah($item['price']) ?> per toples</small></div>
                    <b><?= rupiah((float) $item['price'] * (int) $item['quantity']) ?></b>
                </div>
                <?php endforeach; ?>
                <div class="order-recipient"><strong><?= e($order['recipient_name']) ?> &middot;
                        <?= e($order['phone']) ?></strong><span><?= e($order['address']) ?></span><?php if ($order['notes']): ?><small>Catatan:
                        <?= e($order['notes']) ?></small><?php endif; ?></div>
            </section>
        </div>
        <aside class="order-detail-aside">
            <section class="summary-panel">
                <h2>Rincian pembayaran</h2>
                <p class="order-reference">Preorder #<?= (int) $order['id'] ?> &middot; <?= count($items) ?> jenis kue
                </p>
                <div class="summary-row"><span>Subtotal produk</span><span><?= rupiah($order['total']) ?></span></div>
                <div class="summary-row summary-total"><span>Total
                        produk</span><span><?= rupiah($order['total']) ?></span></div>
                <div class="dp-summary">
                    <strong><?= e(payment_status_label($order['payment_status'])) ?></strong><b><?= rupiah($order['dp_amount']) ?></b><small>Sisa
                        pelunasan <?= rupiah(max(0, (float) $order['total'] - (float) $order['dp_amount'])) ?></small>
                </div>
                <p class="payment-note">Ongkir dinegosiasikan melalui chat. Admin mengonfirmasi pembayaran secara
                    manual.</p>
            </section>
            <section class="checkout-rule"><strong>Perlu bantuan?</strong>
                <p>Tanyakan pembayaran, ongkir, atau jadwal preorder kepada admin.</p><a class="button button-outline"
                    href="chat.php">Chat admin</a>
            </section>
        </aside>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>