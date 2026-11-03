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
<main class="page-shell"><div class="page-heading"><p class="eyebrow">PREORDER KUE / MEDAN</p><h1>Pesanan saya</h1><p>Lihat jadwal kirim, status DP, dan proses pembuatan kuemu.</p></div><?php if (!$orders): ?><div class="panel"><p>Belum ada preorder kue.</p><a class="button button-dark" href="index.php#koleksi">Pilih kue Lebaran</a></div><?php else: ?><div class="panel table-wrap"><table><thead><tr><th>Preorder</th><th>Tanggal kirim</th><th>Barang</th><th>Total</th><th>DP 50%</th><th>Status pesanan</th><th>Status pembayaran</th><th></th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td>#<?= (int) $order['id'] ?><br><small><?= e(date('d M Y', strtotime($order['created_at']))) ?></small></td><td><?= $order['delivery_date'] ? e(date('d M Y', strtotime($order['delivery_date']))) : 'Belum ditentukan' ?></td><td><?= (int) $order['item_count'] ?> jenis</td><td><?= rupiah($order['total']) ?></td><td><?= rupiah($order['dp_amount']) ?></td><td><span class="status status-<?= e($order['status']) ?>"><?= e(order_status_label($order['status'])) ?></span></td><td><?= e(payment_status_label($order['payment_status'])) ?></td><td><a class="text-link" href="chat.php">Hubungi admin</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></main>
<?php require __DIR__ . '/includes/footer.php'; ?>