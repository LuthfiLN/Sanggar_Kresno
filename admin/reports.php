<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config.php';
require_admin();
$pdo = db();

$startDate = trim((string) ($_GET['start_date'] ?? date('Y-m-01')));
$endDate = trim((string) ($_GET['end_date'] ?? date('Y-m-d', strtotime('+30 days'))));
$statusFilter = (string) ($_GET['status'] ?? 'semua');

$where = ["o.status <> 'cancelled'"];
$params = [];

if ($startDate !== '') {
    $where[] = 'o.delivery_date >= ?';
    $params[] = $startDate;
}
if ($endDate !== '') {
    $where[] = 'o.delivery_date <= ?';
    $params[] = $endDate;
}
if ($statusFilter !== 'semua') {
    $where[] = 'o.status = ?';
    $params[] = $statusFilter;
}

$whereSql = implode(' AND ', $where);

// Ringkasan Finansial untuk filter terpilih
$summaryStmt = $pdo->prepare("SELECT 
    COUNT(*) AS total_orders,
    COALESCE(SUM(o.total + o.shipping_fee), 0) AS total_revenue,
    COALESCE(SUM(CASE WHEN o.payment_status IN ('dp_paid', 'paid') THEN o.dp_amount ELSE 0 END), 0) AS dp_collected,
    COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN (o.total + o.shipping_fee) WHEN o.payment_status = 'dp_paid' THEN o.dp_amount ELSE 0 END), 0) AS cash_received,
    COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN 0 WHEN o.payment_status = 'dp_paid' THEN ((o.total + o.shipping_fee) - o.dp_amount) ELSE (o.total + o.shipping_fee) END), 0) AS pending_receivables
FROM orders o WHERE $whereSql");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

// Daftar Transaksi Detail Sesuai Filter
$reportStmt = $pdo->prepare("SELECT o.*, u.name AS customer_name, (SELECT GROUP_CONCAT(CONCAT(oi.product_name, ' (x', oi.quantity, ')') SEPARATOR ', ') FROM order_items oi WHERE oi.order_id = o.id) AS item_summary FROM orders o JOIN users u ON u.id = o.user_id WHERE $whereSql ORDER BY o.delivery_date ASC, o.created_at DESC");
$reportStmt->execute($params);
$reportOrders = $reportStmt->fetchAll();

$pageTitle = 'Laporan Penjualan';
$adminActive = 'reports';
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<main class="admin-page-wrap">
    <div class="panel-header-row kelola-orders-header">
        <div>
            <p class="eyebrow">RUANG ADMIN / LAPORAN</p>
            <h1 class="page-main-title">Laporan Penjualan &amp; Pre-Order</h1>
            <p class="page-subtitle">Rekapitulasi transaksi, penerimaan DP 50%, ongkir, dan sisa pelunasan.</p>
        </div>
        <button type="button" class="button button-solid-black" onclick="window.print()">
            &#128438; Cetak / Export PDF
        </button>
    </div>

    <!-- Filter Form -->
    <div class="panel admin-panel-box report-filter-panel no-print">
        <form class="report-filter-form" method="get">
            <div class="filter-col">
                <label for="rep-start">Dari tanggal pengiriman</label>
                <input id="rep-start" type="date" name="start_date" value="<?= e($startDate) ?>">
            </div>
            <div class="filter-col">
                <label for="rep-end">Sampai tanggal pengiriman</label>
                <input id="rep-end" type="date" name="end_date" value="<?= e($endDate) ?>">
            </div>
            <div class="filter-col">
                <label for="rep-status">Status pesanan</label>
                <select id="rep-status" name="status">
                    <option value="semua" <?= $statusFilter === 'semua' ? 'selected' : '' ?>>Semua status</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Menunggu DP</option>
                    <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Sedang dibuat</option>
                    <option value="shipped" <?= $statusFilter === 'shipped' ? 'selected' : '' ?>>Dalam pengiriman</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Selesai</option>
                </select>
            </div>
            <button type="submit" class="button button-solid-black button-small align-end-btn">Tampilkan Laporan</button>
        </form>
    </div>

    <!-- Metric Cards -->
    <section class="admin-stat-quad-grid report-stat-grid">
        <div class="stat-card-white">
            <span class="stat-label">Total Pre-order</span>
            <h2 class="stat-number"><?= (int) $summary['total_orders'] ?></h2>
            <small class="stat-meta">Pesanan aktif</small>
        </div>
        <div class="stat-card-white">
            <span class="stat-label">Total Omset</span>
            <h2 class="stat-number"><?= rupiah($summary['total_revenue']) ?></h2>
            <small class="stat-meta">Termasuk ongkir</small>
        </div>
        <div class="stat-card-white">
            <span class="stat-label">Kas Masuk (DP + Lunas)</span>
            <h2 class="stat-number"><?= rupiah($summary['cash_received']) ?></h2>
            <small class="stat-meta">Dana terverifikasi</small>
        </div>
        <div class="stat-card-white">
            <span class="stat-label">Sisa Piutang Pelunasan</span>
            <h2 class="stat-number"><?= rupiah($summary['pending_receivables']) ?></h2>
            <small class="stat-meta">Belum dilunasi</small>
        </div>
    </section>

    <!-- Tabel Rincian Laporan -->
    <section class="panel admin-panel-box report-table-section">
        <div class="report-print-header print-only">
            <h2>SANGGAR TRESNO &bull; LAPORAN PENJUALAN PRE-ORDER</h2>
            <p>Periode Pengiriman: <?= indo_date($startDate) ?> s/d <?= indo_date($endDate) ?></p>
        </div>

        <div class="table-wrap-clean">
            <table class="admin-data-table report-table">
                <thead>
                    <tr>
                        <th>KODE</th>
                        <th>JADWAL KIRIM</th>
                        <th>PELANGGAN</th>
                        <th>PESANAN KUE</th>
                        <th>ONGKIR</th>
                        <th>TOTAL</th>
                        <th>DP 50%</th>
                        <th>STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$reportOrders): ?>
                        <tr>
                            <td colspan="8" class="empty-table-cell">Tidak ada transaksi dalam rentang tanggal ini.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($reportOrders as $ro): ?>
                        <tr>
                            <td><strong>#<?= e(order_code($ro)) ?></strong></td>
                            <td><?= $ro['delivery_date'] ? e(indo_date($ro['delivery_date'], false)) : '-' ?></td>
                            <td><?= e($ro['recipient_name'] ?: $ro['customer_name']) ?></td>
                            <td><small><?= e($ro['item_summary']) ?></small></td>
                            <td><?= !empty($ro['shipping_fee']) ? rupiah($ro['shipping_fee']) : 'Rp 0' ?></td>
                            <td><strong><?= rupiah((float) $ro['total'] + (int) ($ro['shipping_fee'] ?? 0)) ?></strong></td>
                            <td><?= rupiah($ro['dp_amount']) ?></td>
                            <td>
                                <span class="badge-dp-tag <?= in_array($ro['payment_status'], ['dp_paid', 'paid'], true) ? 'badge-dp-paid' : 'badge-dp-pending' ?>">
                                    <?= e(payment_status_label($ro['payment_status'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
