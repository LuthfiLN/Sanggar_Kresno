<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
$user = require_login();
$query = db()->prepare('SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC');
$query->execute([$user['id']]);
$orders = $query->fetchAll();
$pageTitle = 'Pesanan saya';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell">
    <div class="page-heading">
        <p class="eyebrow">PREORDER KUE / MEDAN</p>
        <h1>Pesanan saya</h1>
        <p>Lihat jadwal kirim, status DP, dan proses pembuatan kuemu.</p>
    </div>
    <?php if (!$orders): ?>
    <div class="panel">
        <p>Belum ada preorder kue.</p><a class="button button-dark" href="index.php#koleksi">Pilih kue Lebaran</a>
    </div>
    <?php else: ?>
    <div class="order-list">
        <?php foreach ($orders as $order): ?>
        <article class="order-list-item">
            <div class="order-list-main">
                <span class="order-reference">#<?= (int) $order['id'] ?> &middot;
                    <?= e(date('d M Y', strtotime($order['created_at']))) ?></span>
                <h2><?= (int) $order['item_count'] ?> jenis kue</h2>
                <p>Jadwal:
                    <?= $order['delivery_date'] ? e(date('d M Y', strtotime($order['delivery_date']))) : 'Belum ditentukan' ?>
                    &middot; DP <?= rupiah($order['dp_amount']) ?></p>
            </div>
            <div class="order-list-status"><span
                    class="status status-<?= e($order['status']) ?>"><?= e(order_status_label($order['status'])) ?></span><small><?= e(payment_status_label($order['payment_status'])) ?></small>
            </div>
            <div class="order-list-actions"><strong><?= rupiah($order['total']) ?></strong><a
                    class="button button-outline button-small"
                    href="order_detail.php?id=<?= (int) $order['id'] ?>">Lihat rincian</a><a class="text-link"
                    href="chat.php">Chat admin</a></div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>