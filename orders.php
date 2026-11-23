<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
$user = require_login();

$orders = list_orders_for_user((int) $user['id']);

$pageTitle = 'Pesanan Saya';
$currentNav = 'orders';
require __DIR__ . '/app/Views/header.php';
?>
<main class="page-shell">
    <div class="page-heading">
        <p class="eyebrow">PREORDER KUE / MEDAN</p>
        <h1>Pesanan saya</h1>
        <p>Pantau tahapan pembuatan kue, status verifikasi DP, dan jadwal pengantaran.</p>
    </div>

    <?php if (!$orders): ?>
        <div class="panel empty-state-box">
            <div class="empty-icon">&#128221;</div>
            <h2>Belum ada pesanan pre-order</h2>
            <p>Pilih kue Lebaran favoritmu sekarang dan tentukan jadwal pengiriman ke rumahmu.</p>
            <a class="button button-solid-black" href="<?= e(app_url('index.php#koleksi')) ?>">Pilih Kue Lebaran &rarr;</a>
        </div>
    <?php else: ?>
        <div class="orders-list-stack">
            <?php foreach ($orders as $order): ?>
                <?php
                $orderCode = order_code($order);
                $isDpPaid = in_array($order['payment_status'], ['dp_paid', 'paid'], true);
                ?>
                <article class="order-card-row">
                    <div class="order-card-meta">
                        <span class="order-ref-badge">#<?= e($orderCode) ?></span>
                        <span class="order-date-text">&bull; <?= indo_date($order['created_at']) ?></span>
                        <h2 class="order-card-title"><?= (int) $order['item_count'] ?> jenis kue</h2>
                        <p class="order-card-schedule">
                            Jadwal: <strong><?= $order['delivery_date'] ? e(indo_date($order['delivery_date'])) : 'Menunggu kesepakatan' ?></strong>
                            &bull; DP <?= rupiah($order['dp_amount']) ?>
                        </p>
                    </div>

                    <div class="order-card-statuses">
                        <span class="status-badge-clean status-<?= e($order['status']) ?>">
                            <?= e(order_status_label($order['status'])) ?>
                        </span>
                        <small class="dp-status-sub <?= $isDpPaid ? 'text-success' : 'text-warning' ?>">
                            <?= e(payment_status_label($order['payment_status'])) ?>
                        </small>
                    </div>

                    <div class="order-card-actions">
                        <strong class="order-card-total"><?= rupiah((float) $order['total'] + (int) ($order['shipping_fee'] ?? 0)) ?></strong>
                        <div class="action-btn-group">
                            <a class="button button-outline-dark button-small" href="<?= e(app_url('order_detail.php?id=' . (int) $order['id'])) ?>">
                                Lihat rincian &rarr;
                            </a>
                            <?php if (!$isDpPaid): ?>
                                <a class="button button-solid-black button-small" href="<?= e(app_url('payment.php?id=' . (int) $order['id'])) ?>">
                                    Bayar DP
                                </a>
                            <?php endif; ?>
                            <a class="text-link-primary" href="<?= e(app_url('chat.php?order=' . (int) $order['id'])) ?>">Chat admin</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/app/Views/footer.php'; ?>
