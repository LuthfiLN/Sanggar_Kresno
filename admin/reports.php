<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config.php';
require_admin();
$pdo = db();
$summary = $pdo->query("SELECT COUNT(*) AS order_count, COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total WHEN payment_status = 'dp_paid' THEN dp_amount ELSE 0 END), 0) AS received_amount, COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_count FROM orders")->fetch();
$monthly = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS order_count, COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total WHEN payment_status = 'dp_paid' THEN dp_amount ELSE 0 END), 0) AS received_amount FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month DESC")->fetchAll();
$pageTitle = 'Laporan penjualan';
require dirname(__DIR__) . '/includes/header.php';
?>
<main class="page-shell"><div class="page-heading"><p class="eyebrow">ADMIN / LAPORAN MEDAN</p><h1>Ringkasan preorder</h1><p>Kas masuk mencakup DP terverifikasi dan pelunasan yang sudah diterima.</p></div><p><a class="text-link" href="index.php">&#8592; Kembali ke ringkasan</a></p><section class="admin-grid"><div class="stat"><span>Semua preorder</span><strong><?= (int) $summary['order_count'] ?></strong></div><div class="stat"><span>Menunggu DP</span><strong><?= (int) $summary['pending_count'] ?></strong></div><div class="stat"><span>Kas terverifikasi</span><strong><?= rupiah($summary['received_amount']) ?></strong></div></section><section class="admin-section"><h2>12 bulan terakhir</h2><div class="panel table-wrap"><table><thead><tr><th>Bulan</th><th>Jumlah preorder</th><th>Kas terverifikasi</th></tr></thead><tbody><?php if (!$monthly): ?><tr><td colspan="3">Belum ada data penjualan.</td></tr><?php endif; ?><?php foreach ($monthly as $row): ?><tr><td><?= e(date('F Y', strtotime($row['month'] . '-01'))) ?></td><td><?= (int) $row['order_count'] ?></td><td><?= rupiah($row['received_amount']) ?></td></tr><?php endforeach; ?></tbody></table></div></section></main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>