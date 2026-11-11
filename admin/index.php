<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config.php';
require_admin();
$pdo = db();

$greeting = greeting_time();
$todayStr = indo_date(date('Y-m-d'));

$nextBatch = $pdo->query("SELECT MIN(delivery_date) FROM orders WHERE status <> 'cancelled' AND delivery_date >= CURDATE()")->fetchColumn();
$requestedBatch = trim((string) ($_GET['batch'] ?? ''));
$batchDate = DateTimeImmutable::createFromFormat('!Y-m-d', $requestedBatch ?: (string) ($nextBatch ?: date('Y-m-d', strtotime('+3 days'))));

if (!$batchDate) {
    $batchDate = min_preorder_datetime();
}
$batch = $batchDate->format('Y-m-d');
$batchFormatted = indo_date($batch);

$statsQuery = $pdo->prepare("SELECT 
    COUNT(*) AS total_orders,
    SUM(CASE WHEN payment_status = 'dp_paid' THEN 1 ELSE 0 END) AS dp_paid_count,
    SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) AS pending_count,
    COALESCE(SUM(CASE WHEN payment_status IN ('dp_paid', 'paid') THEN dp_amount ELSE 0 END), 0) AS dp_received,
    COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN (total + shipping_fee) WHEN payment_status = 'dp_paid' THEN ((total + shipping_fee) - dp_amount) ELSE (total + shipping_fee) END), 0) AS remaining_bill
FROM orders WHERE delivery_date = ? AND status <> 'cancelled'");
$statsQuery->execute([$batch]);
$batchStats = $statsQuery->fetch();

$unreadChatCount = (int) $pdo->query("SELECT COUNT(DISTINCT customer_id) FROM messages WHERE id IN (SELECT MAX(id) FROM messages GROUP BY customer_id) AND sender_role = 'customer'")->fetchColumn();

$ordersQuery = $pdo->prepare("SELECT o.*, u.name AS customer_name, (SELECT GROUP_CONCAT(CONCAT(oi.product_name, ' x', oi.quantity) SEPARATOR ', ') FROM order_items oi WHERE oi.order_id = o.id) AS item_summary FROM orders o JOIN users u ON u.id = o.user_id WHERE o.delivery_date = ? AND o.status <> 'cancelled' ORDER BY o.created_at DESC LIMIT 10");
$ordersQuery->execute([$batch]);
$orders = $ordersQuery->fetchAll();

$productionQuery = $pdo->prepare("SELECT p.name, p.category, SUM(oi.quantity) AS quantity FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN products p ON p.id = oi.product_id WHERE o.delivery_date = ? AND o.payment_status IN ('dp_paid', 'paid') AND o.status IN ('pending', 'processing') GROUP BY p.id, p.name, p.category ORDER BY quantity DESC");
$productionQuery->execute([$batch]);
$productionItems = $productionQuery->fetchAll();

$pageTitle = 'Ringkasan Batch';
$adminActive = 'overview';
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<main class="admin-page-wrap">
    <div class="admin-dashboard-hero">
        <div class="dash-hero-text">
            <span class="dash-date-badge"><?= strtoupper($todayStr) ?></span>
            <h1 class="dash-greeting"><?= e($greeting) ?></h1>
            <p class="dash-subtext">Yang perlu disiapkan untuk batch pengiriman berikutnya.</p>
        </div>

        <form class="batch-selector-form" method="get">
            <label for="batch-date-picker">Pilih Batch Pre-order</label>
            <div class="batch-input-group">
                <input id="batch-date-picker" type="date" name="batch" value="<?= e($batch) ?>"
                    onchange="this.form.submit()">
                <button type="submit" class="button button-solid-black button-small">Tampilkan</button>
            </div>
        </form>
    </div>

    <section class="admin-stat-quad-grid">
        <div class="stat-card-white">
            <span class="stat-label">Pesanan aktif</span>
            <h2 class="stat-number"><?= (int) $batchStats['total_orders'] ?></h2>
            <small class="stat-meta"><?= (int) $batchStats['dp_paid_count'] ?> DP Paid &bull;
                <?= (int) $batchStats['pending_count'] ?> Pending</small>
        </div>

        <div class="stat-card-white">
            <span class="stat-label">DP diterima</span>
            <h2 class="stat-number"><?= rupiah($batchStats['dp_received']) ?></h2>
            <small class="stat-meta">Total DP batch <?= e($batchFormatted) ?></small>
        </div>

        <div class="stat-card-white">
            <span class="stat-label">Perlu balasan</span>
            <h2 class="stat-number"><?= $unreadChatCount ?></h2>
            <small class="stat-meta">Negosiasi ongkir</small>
        </div>

        <div class="stat-card-white">
            <span class="stat-label">Sisa tagihan</span>
            <h2 class="stat-number"><?= rupiah($batchStats['remaining_bill']) ?></h2>
            <small class="stat-meta">Dari <?= (int) $batchStats['dp_paid_count'] ?> pesanan DP Paid</small>
        </div>
    </section>

    <div class="admin-overview-columns">
        <div class="overview-left-col">
            <section class="panel admin-panel-box">
                <div class="panel-header-row">
                    <div>
                        <h2>Pesanan terbaru &bull; <?= e($batchFormatted) ?></h2>
                        <small class="panel-sub">Daftar pesanan pre-order masuk untuk jadwal pengantaran ini.</small>
                    </div>
                    <a class="text-link-primary" href="kelola_orders.php">Lihat semua pesanan &rarr;</a>
                </div>

                <div class="table-wrap-clean">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>PESANAN</th>
                                <th>PELANGGAN / ITEM</th>
                                <th>DP</th>
                                <th>TOTAL</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$orders): ?>
                                <tr>
                                    <td colspan="5" class="empty-table-cell">Belum ada pesanan untuk jadwal batch ini.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($orders as $o): ?>
                                <?php $isPaid = in_array($o['payment_status'], ['dp_paid', 'paid'], true); ?>
                                <tr>
                                    <td>
                                        <strong>#<?= e(order_code($o)) ?></strong>
                                    </td>
                                    <td>
                                        <strong><?= e($o['customer_name']) ?></strong>
                                        <small class="cell-sub-item"><?= e($o['item_summary'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge-dp-tag <?= $isPaid ? 'badge-dp-paid' : 'badge-dp-pending' ?>">
                                            <?= $isPaid ? 'DP Paid' : 'Pending' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= rupiah((float) $o['total'] + (int) ($o['shipping_fee'] ?? 0)) ?></strong>
                                    </td>
                                    <td>
                                        <a class="button button-outline-dark button-small"
                                            href="kelola_orders.php?order=<?= (int) $o['id'] ?>">Detail</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel admin-panel-box followup-box">
                <h2>Daftar tindak lanjut</h2>
                <div class="followup-list">
                    <div class="followup-item">
                        <span class="warn-dot">&#9888;</span>
                        <div>
                            <strong><?= (int) $batchStats['pending_count'] ?> pesanan belum membayar DP</strong>
                            <p>Batas DP hari ini, 18.00 WIB. Kirim pengingat sebelum batch ditutup.</p>
                        </div>
                    </div>
                    <div class="followup-item">
                        <span class="chat-dot">&#128172;</span>
                        <div>
                            <strong>Negosiasi ongkir: <?= $unreadChatCount ?> percakapan menunggu</strong>
                            <p>Tanggapi pertanyaan ongkir dan sepakati jadwal pengiriman.</p>
                        </div>
                        <a class="button button-solid-black button-small" href="<?= e(app_url('chat.php')) ?>">Buka
                            inbox negosiasi</a>
                    </div>
                </div>
            </section>
        </div>

        <aside class="overview-right-col">
            <section class="panel admin-panel-box">
                <h2>Kebutuhan produksi</h2>
                <small class="panel-sub">Jadwal <?= e($batchFormatted) ?> &bull; Hanya pesanan DP Paid</small>

                <div class="production-summary-list">
                    <?php if (!$productionItems): ?>
                        <p class="empty-hint-text">Belum ada pesanan dengan DP terverifikasi untuk batch ini.</p>
                    <?php endif; ?>
                    <?php foreach ($productionItems as $pItem): ?>
                        <div class="production-summary-row">
                            <span class="prod-name"><?= e($pItem['name']) ?></span>
                            <strong class="prod-qty"><?= (int) $pItem['quantity'] ?> toples</strong>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="batch-card-foot">
                    <button type="button" class="button button-outline-dark button-full button-small"
                        onclick="window.print()">
                        &#128438; Unduh / Cetak daftar produksi
                    </button>
                </div>
            </section>

            <section class="panel admin-panel-box schedule-box">
                <h2>Jadwal batch</h2>
                <h3 class="schedule-batch-date"><?= e($batchFormatted) ?></h3>
                <p class="schedule-time-desc">
                    10.00: Batas pelunasan 100%<br>
                    10.00&ndash;12.00: Ambil sendiri di toko<br>
                    13.00&ndash;16.00: Pengiriman kurir
                </p>
                <a class="button button-solid-black button-full button-small"
                    href="kelola_orders.php?date=<?= e($batch) ?>">
                    Kelola batch ini &rarr;
                </a>
            </section>
        </aside>
    </div>
</main>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>