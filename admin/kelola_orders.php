<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config.php';
require_admin();
$pdo = db();
$allowedStatuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $status = (string) ($_POST['status'] ?? '');
    $paymentStatus = (string) ($_POST['payment_status'] ?? 'unpaid');
    if (!$orderId || !in_array($status, $allowedStatuses, true) || !in_array($paymentStatus, ['unpaid', 'dp_paid', 'paid'], true)) {
        $error = 'Pembaruan preorder tidak valid.';
    } else {
        try {
            $pdo->beginTransaction();
            $currentQuery = $pdo->prepare('SELECT status, payment_status FROM orders WHERE id = ? FOR UPDATE');
            $currentQuery->execute([$orderId]);
            $currentOrder = $currentQuery->fetch();
            if (!$currentOrder) {
                throw new RuntimeException('Preorder tidak ditemukan.');
            }
            $currentStatus = $currentOrder['status'];
            $currentPayment = $currentOrder['payment_status'];
            if ($currentStatus === 'cancelled' && $status !== 'cancelled') {
                throw new RuntimeException('Preorder batal tidak dapat dibuka kembali. Buat preorder baru agar stok tercatat benar.');
            }
            if (in_array($currentStatus, ['shipped', 'completed'], true) && $status === 'cancelled') {
                throw new RuntimeException('Preorder yang sudah dikirim atau selesai tidak dapat dibatalkan.');
            }
            if ($currentPayment === 'unpaid' && $paymentStatus === 'paid') {
                throw new RuntimeException('Verifikasi DP 50% terlebih dahulu sebelum menandai pelunasan.');
            }
            if (($currentPayment === 'paid' && $paymentStatus !== 'paid') || ($currentPayment === 'dp_paid' && $paymentStatus === 'unpaid')) {
                throw new RuntimeException('Status pembayaran yang sudah diverifikasi tidak dapat diturunkan.');
            }
            if ($paymentStatus === 'dp_paid' && $status === 'pending') {
                $status = 'processing';
            }
            if ($status === 'processing' && $paymentStatus === 'unpaid') {
                throw new RuntimeException('Produksi hanya boleh dimulai setelah DP 50% terverifikasi.');
            }
            if ($status === 'shipped' && ($paymentStatus !== 'paid' || !in_array($currentStatus, ['processing', 'shipped'], true))) {
                throw new RuntimeException('Preorder hanya dapat dikirim setelah dibuat dan pelunasan 100% selesai.');
            }
            if ($status === 'completed' && !in_array($currentStatus, ['shipped', 'completed'], true)) {
                throw new RuntimeException('Tandai preorder sebagai terkirim sebelum menyelesaikannya.');
            }
            if ($currentStatus !== 'cancelled' && $status === 'cancelled') {
                $itemsQuery = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? AND product_id IS NOT NULL');
                $itemsQuery->execute([$orderId]);
                $restoreStock = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
                foreach ($itemsQuery->fetchAll() as $item) {
                    $restoreStock->execute([$item['quantity'], $item['product_id']]);
                }
            }
            $update = $pdo->prepare('UPDATE orders SET status = ?, payment_status = ? WHERE id = ?');
            $update->execute([$status, $paymentStatus, $orderId]);
            $pdo->commit();
            set_flash('success', 'Preorder #' . $orderId . ' diperbarui.');
            header('Location: orders.php');
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception instanceof PDOException ? 'Preorder belum dapat diperbarui. Coba kembali.' : $exception->getMessage();
        }
    }
}

$orders = $pdo->query("SELECT o.*, u.name AS customer_name, u.email AS customer_email, (SELECT GROUP_CONCAT(CONCAT(oi.product_name, ' x', oi.quantity) SEPARATOR ', ') FROM order_items oi WHERE oi.order_id = o.id) AS item_summary FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC")->fetchAll();
$pageTitle = 'Kelola preorder';
require dirname(__DIR__) . '/includes/header.php';
?>
<main class="page-shell">
    <div class="page-heading">
        <p class="eyebrow">ADMIN / PREORDER MEDAN</p>
        <h1>Pesanan kue</h1>
        <p>Verifikasi DP 50% sebelum produksi, lalu verifikasi pelunasan sebelum kue dikirim.</p>
    </div>
    <p><a class="text-link" href="index.php">&#8592; Kembali ke ringkasan</a></p>
    <?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?>
    <div class="panel table-wrap">
        <table>
            <thead><tr><th>Preorder</th><th>Pelanggan</th><th>Jadwal & alamat</th><th>Kue</th><th>Status produksi</th><th>Pembayaran</th><th>Chat</th></tr></thead>
            <tbody>
                <?php if (!$orders): ?><tr><td colspan="7">Belum ada preorder masuk.</td></tr><?php endif; ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?= (int) $order['id'] ?><br><small><?= e(date('d M Y, H:i', strtotime($order['created_at']))) ?></small><br>Total <?= rupiah($order['total']) ?><br><strong>DP 50%: <?= rupiah($order['dp_amount']) ?></strong></td>
                        <td><?= e($order['customer_name']) ?><br><small><?= e($order['customer_email']) ?></small><br><small><?= e($order['phone']) ?></small></td>
                        <td><?= $order['delivery_date'] ? e(date('d M Y', strtotime($order['delivery_date']))) : 'Belum ditentukan' ?><br><?= e($order['recipient_name']) ?><br><small><?= nl2br(e($order['address'])) ?></small><?php if ($order['notes']): ?><br><small>Catatan: <?= e($order['notes']) ?></small><?php endif; ?></td>
                        <td><?= e($order['item_summary'] ?? '') ?></td>
                        <td>
                            <form method="post" class="form-stack">
                                <?= csrf_field() ?>
                                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                <select name="status" aria-label="Status preorder #<?= (int) $order['id'] ?>">
                                    <?php foreach ($allowedStatuses as $status): ?>
                                        <option value="<?= e($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= e(order_status_label($status)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="payment_status" aria-label="Status pembayaran preorder #<?= (int) $order['id'] ?>">
                                    <option value="unpaid" <?= $order['payment_status'] === 'unpaid' ? 'selected' : '' ?>>DP 50% belum dibayar</option>
                                    <option value="dp_paid" <?= $order['payment_status'] === 'dp_paid' ? 'selected' : '' ?>>DP 50% terverifikasi</option>
                                    <option value="paid" <?= $order['payment_status'] === 'paid' ? 'selected' : '' ?>>Lunas 100%</option>
                                </select>
                                <button class="button button-outline button-small" type="submit">Simpan status</button>
                            </form>
                        </td>
                        <td><a class="text-link" href="../chat.php?customer=<?= (int) $order['user_id'] ?>">Buka chat</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>