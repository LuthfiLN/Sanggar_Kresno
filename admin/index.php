<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config.php';
require_admin();
$pdo = db();
$nextBatch = $pdo->query("SELECT MIN(delivery_date) FROM orders WHERE status <> 'cancelled' AND delivery_date >= CURDATE()")->fetchColumn();
$requestedBatch = trim((string) ($_GET['batch'] ?? ''));
$batchDate = DateTimeImmutable::createFromFormat('!Y-m-d', $requestedBatch ?: (string) ($nextBatch ?: date('Y-m-d')));
if (!$batchDate || $batchDate->format('Y-m-d') !== ($requestedBatch ?: (string) ($nextBatch ?: date('Y-m-d')))) {
    $batchDate = new DateTimeImmutable('today');
}
$batch = $batchDate->format('Y-m-d');

$statsQuery = $pdo->prepare("SELECT COUNT(*) AS order_count, SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count, COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total WHEN payment_status = 'dp_paid' THEN dp_amount ELSE 0 END), 0) AS received_amount FROM orders WHERE delivery_date = ? AND status <> 'cancelled'");
$statsQuery->execute([$batch]);
$batchStats = $statsQuery->fetch();
$stats = [
    'orders' => (int) $batchStats['order_count'],
    'pending' => (int) $batchStats['pending_count'],
    'products' => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn(),
    'revenue' => (float) $batchStats['received_amount'],
];
$ordersQuery = $pdo->prepare("SELECT o.*, u.name AS customer_name, (SELECT GROUP_CONCAT(CONCAT(oi.product_name, ' x', oi.quantity) SEPARATOR ', ') FROM order_items oi WHERE oi.order_id = o.id) AS item_summary FROM orders o JOIN users u ON u.id = o.user_id WHERE o.delivery_date = ? AND o.status <> 'cancelled' ORDER BY o.created_at DESC LIMIT 10");
$ordersQuery->execute([$batch]);
$orders = $ordersQuery->fetchAll();
$productionQuery = $pdo->prepare("SELECT p.name, p.category, SUM(oi.quantity) AS quantity FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN products p ON p.id = oi.product_id WHERE o.delivery_date = ? AND o.payment_status IN ('dp_paid', 'paid') AND o.status = 'processing' GROUP BY p.id, p.name, p.category ORDER BY p.name");
$productionQuery->execute([$batch]);
$productionItems = $productionQuery->fetchAll();
$pageTitle = 'Ringkasan batch';
$adminActive = 'overview';
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<main class="page-shell admin-dashboard">
    <div class="admin-dashboard-heading">
        <div>
            <p class="eyebrow">RINGKASAN BATCH</p>
            <h1>Pesanan yang perlu disiapkan</h1>
            <p>Angka kas mencakup DP terverifikasi dan pelunasan untuk batch terpilih.</p>
        </div>
        <form class="batch-picker" method="get"><label for="batch-date">Batch pengiriman</label><input id="batch-date"
                type="date" name="batch" value="<?= e($batch) ?>"><button class="button button-outline button-small"
                type="submit">Tampilkan</button></form>
    </div>
    <section class="admin-grid">
        <div class="stat"><span>Preorder batch ini</span><strong><?= $stats['orders'] ?></strong></div>
        <div class="stat"><span>Menunggu DP</span><strong><?= $stats['pending'] ?></strong></div>
        <div class="stat"><span>Menu aktif</span><strong><?= $stats['products'] ?></strong></div>
        <div class="stat"><span>Kas terverifikasi</span><strong><?= rupiah($stats['revenue']) ?></strong></div>
    </section>
    <div class="admin-dashboard-grid">
        <section class="admin-section">
            <div class="section-top admin-section-top">
                <div>
                    <p class="eyebrow">BATCH <?= e(date('d M Y', strtotime($batch))) ?></p>
                    <h2>Preorder terbaru</h2>
                </div><a class="text-link" href="kelola_orders.php">Semua preorder &#8594;</a>
            </div>
            <div class="panel table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Pelanggan / kue</th>
                            <th>DP</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$orders): ?><tr>
                            <td colspan="5">Belum ada preorder untuk batch ini.</td>
                        </tr><?php endif; ?>
                        <?php foreach ($orders as $order): ?><tr>
                            <td>#<?= (int) $order['id'] ?></td>
                            <td><?= e($order['customer_name']) ?><br><small><?= e($order['item_summary'] ?? '') ?></small>
                            </td>
                            <td><?= rupiah($order['dp_amount']) ?></td>
                            <td><?= rupiah($order['total']) ?></td>
                            <td><span
                                    class="status status-<?= e($order['status']) ?>"><?= e(order_status_label($order['status'])) ?></span>
                            </td>
                        </tr><?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <aside class="admin-section">
            <h2>Kebutuhan produksi</h2>
            <div class="panel production-panel">
                <p>Hanya preorder dengan DP yang sudah terverifikasi.</p>
                <?php if (!$productionItems): ?><div class="empty-state">Belum ada kue yang masuk tahap produksi.</div>
                <?php endif; ?>
                <?php foreach ($productionItems as $item): ?><div class="production-item">
                    <span><?= e($item['name']) ?><small><?= e($item['category']) ?></small></span><strong><?= (int) $item['quantity'] ?>
                        toples</strong></div><?php endforeach; ?>
            </div>
            <h2 class="quick-actions-title">Akses cepat</h2>
            <div class="panel form-stack"><a class="button button-dark" href="products.php">Kelola produk</a><a
                    class="button button-outline" href="kelola_orders.php">Kelola pesanan</a><a
                    class="button button-outline" href="reports.php">Laporan penjualan</a><a
                    class="button button-outline" href="users.php">Akun admin</a></div>
        </aside>
    </div>
</main>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>