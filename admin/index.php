<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config.php';
require_admin();
$pdo = db();
$stats = [
    'orders' => (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'pending' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
    'products' => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn(),
    'revenue' => (float) $pdo->query("SELECT COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total WHEN payment_status = 'dp_paid' THEN dp_amount ELSE 0 END), 0) FROM orders")->fetchColumn(),
];
$orders = $pdo->query('SELECT o.*, u.name AS customer_name FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC LIMIT 12')->fetchAll();
$pageTitle = 'Dashboard admin';
require dirname(__DIR__) . '/includes/header.php';
?>
<main class="page-shell"><div class="page-heading"><p class="eyebrow">DASHBOARD ADMIN</p><h1>Ringkasan preorder</h1><p>Selamat datang, <?= e(current_user()['name']) ?>. Verifikasi DP sebelum produksi dan pelunasan sebelum pengiriman.</p></div><section class="admin-grid"><div class="stat"><span>Semua preorder</span><strong><?= $stats['orders'] ?></strong></div><div class="stat"><span>Menunggu DP</span><strong><?= $stats['pending'] ?></strong></div><div class="stat"><span>Menu aktif</span><strong><?= $stats['products'] ?></strong></div><div class="stat"><span>Pembayaran diterima</span><strong><?= rupiah($stats['revenue']) ?></strong></div></section><div class="admin-columns"><section class="admin-section"><h2>Akses cepat</h2><div class="panel form-stack"><a class="button button-dark" href="products.php">Kelola menu kue</a><a class="button button-outline" href="kelola_orders.php">Kelola preorder</a><a class="button button-outline" href="reports.php">Laporan penjualan</a><a class="button button-outline" href="../chat.php">Buka chat pelanggan</a><p class="payment-note">Pembayaran online belum terhubung. Cocokkan transfer secara manual sebelum mengubah status DP.</p></div></section><section class="admin-section"><h2>Preorder terbaru</h2><div class="panel table-wrap"><table><thead><tr><th>No.</th><th>Pelanggan</th><th>Total</th><th>DP</th><th>Status</th><th></th></tr></thead><tbody><?php if (!$orders): ?><tr><td colspan="6">Belum ada preorder masuk.</td></tr><?php endif; ?><?php foreach ($orders as $order): ?><tr><td>#<?= (int) $order['id'] ?></td><td><?= e($order['customer_name']) ?></td><td><?= rupiah($order['total']) ?></td><td><?= rupiah($order['dp_amount']) ?></td><td><span class="status status-<?= e($order['status']) ?>"><?= e(order_status_label($order['status'])) ?></span></td><td><a class="text-link" href="kelola_orders.php">Kelola</a></td></tr><?php endforeach; ?></tbody></table></div></section></div></main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>