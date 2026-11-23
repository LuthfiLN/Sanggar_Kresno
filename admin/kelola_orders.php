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
    $deliveryDateInput = trim((string) ($_POST['delivery_date'] ?? ''));
    $shippingFee = max(0, (int) ($_POST['shipping_fee'] ?? 0));

    if (!$orderId || !in_array($status, $allowedStatuses, true) || !in_array($paymentStatus, ['unpaid', 'dp_paid', 'paid'], true)) {
        $error = 'Data pembaruan pesanan tidak valid.';
    } else {
        try {
            $pdo->beginTransaction();
            $currQ = $pdo->prepare('SELECT status, payment_status, delivery_date FROM orders WHERE id = ? FOR UPDATE');
            $currQ->execute([$orderId]);
            $currentOrder = $currQ->fetch();

            if (!$currentOrder) {
                throw new RuntimeException('Pesanan tidak ditemukan.');
            }

            if ($paymentStatus === 'dp_paid' && $status === 'pending') {
                $status = 'processing';
            }

            if ($currentOrder['status'] !== 'cancelled' && $status === 'cancelled') {
                $itemsQuery = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? AND product_id IS NOT NULL');
                $itemsQuery->execute([$orderId]);
                $restoreStock = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
                foreach ($itemsQuery->fetchAll() as $item) {
                    $restoreStock->execute([$item['quantity'], $item['product_id']]);
                }
            }

            $update = $pdo->prepare('UPDATE orders SET status = ?, payment_status = ?, delivery_date = COALESCE(?, delivery_date), shipping_fee = ? WHERE id = ?');
            $update->execute([$status, $paymentStatus, $deliveryDateInput !== '' ? $deliveryDateInput : null, $shippingFee, $orderId]);

            $pdo->commit();
            set_flash('success', 'Pesanan #' . order_code($orderId) . ' berhasil diperbarui.');
            header('Location: kelola_orders.php?order=' . $orderId);
            exit;
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $ex->getMessage();
        }
    }
}

$tab = (string) ($_GET['tab'] ?? 'semua');
$search = trim((string) ($_GET['q'] ?? ''));
$filterDate = trim((string) ($_GET['date'] ?? ''));

$whereClauses = [];
$params = [];

if ($tab === 'dp_paid') {
    $whereClauses[] = "o.payment_status IN ('dp_paid', 'paid')";
} elseif ($tab === 'pending') {
    $whereClauses[] = "o.payment_status = 'unpaid'";
}

if ($search !== '') {
    $whereClauses[] = "(u.name LIKE ? OR o.recipient_name LIKE ? OR o.id = ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = is_numeric($search) ? (int) $search : 0;
}

if ($filterDate !== '') {
    $whereClauses[] = "o.delivery_date = ?";
    $params[] = $filterDate;
}

$whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$ordersQuery = $pdo->prepare("SELECT o.*, u.name AS customer_name, u.email AS customer_email, (SELECT GROUP_CONCAT(CONCAT(oi.product_name, ' x', oi.quantity) SEPARATOR ', ') FROM order_items oi WHERE oi.order_id = o.id) AS item_summary FROM orders o JOIN users u ON u.id = o.user_id $whereSql ORDER BY o.created_at DESC");
$ordersQuery->execute($params);
$orders = $ordersQuery->fetchAll();

$totalAll = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$totalDpPaid = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status IN ('dp_paid', 'paid')")->fetchColumn();
$totalPending = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'unpaid'")->fetchColumn();

$selectedOrderId = filter_var($_GET['order'] ?? null, FILTER_VALIDATE_INT);
$selectedOrder = null;
$selectedOrderItems = [];

if ($selectedOrderId) {
    foreach ($orders as $ord) {
        if ((int) $ord['id'] === $selectedOrderId) {
            $selectedOrder = $ord;
            break;
        }
    }
    if (!$selectedOrder) {
        $singleQ = $pdo->prepare('SELECT o.*, u.name AS customer_name, u.email AS customer_email FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?');
        $singleQ->execute([$selectedOrderId]);
        $selectedOrder = $singleQ->fetch() ?: null;
    }
} elseif ($orders) {
    $selectedOrder = $orders[0];
}

if ($selectedOrder) {
    $itemsQ = $pdo->prepare('SELECT product_name, price, quantity FROM order_items WHERE order_id = ?');
    $itemsQ->execute([$selectedOrder['id']]);
    $selectedOrderItems = $itemsQ->fetchAll();
}

$pageTitle = 'Kelola Pesanan Masuk';
$adminActive = 'orders';
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<main class="admin-page-wrap">
    <div class="panel-header-row kelola-orders-header">
        <div>
            <p class="eyebrow">RUANG ADMIN / PESANAN</p>
            <h1 class="page-main-title">Pesanan masuk</h1>
            <p class="page-subtitle">Pantau DP, pelunasan, ongkir, dan jadwal pemenuhan.</p>
        </div>
        <button type="button" class="button button-outline-dark" onclick="window.print()">
            &#128438; Ekspor pesanan &darr;
        </button>
    </div>

    <?php if ($error): ?>
    <div class="notice notice-error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="orders-filter-container">
        <div class="filter-tabs-row">
            <a href="?tab=semua" class="tab-chip <?= $tab === 'semua' ? 'is-active' : '' ?>">Semua &bull;
                <?= $totalAll ?></a>
            <a href="?tab=dp_paid" class="tab-chip <?= $tab === 'dp_paid' ? 'is-active' : '' ?>">DP Paid &bull;
                <?= $totalDpPaid ?></a>
            <a href="?tab=pending" class="tab-chip <?= $tab === 'pending' ? 'is-active' : '' ?>">Pending &bull;
                <?= $totalPending ?></a>
        </div>

        <form class="orders-search-grid" method="get">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <div class="search-input-field">
                <label for="search-input">Cari pesanan</label>
                <input id="search-input" type="text" name="q" placeholder="Nama atau nomor pesanan..."
                    value="<?= e($search) ?>">
            </div>
            <div class="search-input-field">
                <label for="date-input">Tanggal pemenuhan</label>
                <input id="date-input" type="date" name="date" value="<?= e($filterDate) ?>"
                    onchange="this.form.submit()">
            </div>
            <button type="submit" class="button button-solid-black button-small align-end-btn">Filter</button>
        </form>
    </div>

    <div class="orders-master-detail-grid">
        <div class="orders-list-col">
            <section class="panel admin-panel-box">
                <h2 class="section-clean-title">Daftar pesanan</h2>

                <div class="table-wrap-clean">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Pelanggan</th>
                                <th>DP</th>
                                <th>Total</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$orders): ?>
                            <tr>
                                <td colspan="5" class="empty-table-cell">Tidak ada pesanan yang sesuai filter.</td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach ($orders as $ord): ?>
                            <?php
                                $isCur = ($selectedOrder && (int) $ord['id'] === (int) $selectedOrder['id']);
                                $isPaid = in_array($ord['payment_status'], ['dp_paid', 'paid'], true);
                                ?>
                            <tr class="<?= $isCur ? 'tr-selected' : '' ?>">
                                <td data-label="No">
                                    <strong>#<?= sprintf('%03d', $ord['id']) ?></strong>
                                </td>
                                <td data-label="Pelanggan">
                                    <strong><?= e($ord['recipient_name'] ?: $ord['customer_name']) ?></strong>
                                    <small class="cell-sub-item">Antar:
                                        <?= $ord['delivery_date'] ? e(indo_date($ord['delivery_date'])) : 'Menunggu jadwal' ?></small>
                                </td>
                                <td data-label="DP">
                                    <span class="badge-dp-tag <?= $isPaid ? 'badge-dp-paid' : 'badge-dp-pending' ?>">
                                        <?= $isPaid ? 'DP Paid' : 'Pending' ?>
                                    </span>
                                </td>
                                <td data-label="Total">
                                    <strong><?= rupiah((float) $ord['total'] + (int) ($ord['shipping_fee'] ?? 0)) ?></strong>
                                </td>
                                <td data-label="Aksi">
                                    <a class="button button-outline-dark button-small <?= $isCur ? 'button-active' : '' ?>"
                                        href="kelola_orders.php?tab=<?= e($tab) ?>&order=<?= (int) $ord['id'] ?>&q=<?= urlencode($search) ?>&date=<?= urlencode($filterDate) ?>">
                                        <?= $isCur ? 'Terpilih' : 'Detail' ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="orders-detail-col">
            <?php if ($selectedOrder): ?>
            <?php
                $orderCode = order_code($selectedOrder);
                $isDpPaid = in_array($selectedOrder['payment_status'], ['dp_paid', 'paid'], true);
                $totalWithShip = (float) $selectedOrder['total'] + (int) ($selectedOrder['shipping_fee'] ?? 0);
                $remainingVal = max(0.0, $totalWithShip - (float) $selectedOrder['dp_amount']);
                ?>
            <section class="panel admin-panel-box order-detail-card-panel">
                <div class="selected-order-header">
                    <div>
                        <span class="sel-order-code">#<?= e($orderCode) ?></span>
                        <div class="sel-tags-row">
                            <span class="badge-dp-tag <?= $isDpPaid ? 'badge-dp-paid' : 'badge-dp-pending' ?>">
                                <?= $isDpPaid ? 'DP Paid' : 'Pending' ?>
                            </span>
                            <span class="status-badge-clean status-<?= e($selectedOrder['status']) ?>">
                                <?= e(order_status_label($selectedOrder['status'])) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="sel-customer-profile">
                    <h3><?= e($selectedOrder['recipient_name'] ?: $selectedOrder['customer_name']) ?></h3>
                    <p><?= e($selectedOrder['phone']) ?> &bull; Dibuat <?= indo_date($selectedOrder['created_at']) ?>
                    </p>
                </div>

                <!-- Items List -->
                <div class="sel-items-list">
                    <?php foreach ($selectedOrderItems as $sItem): ?>
                    <div class="sel-item-row">
                        <div>
                            <strong><?= e($sItem['product_name']) ?></strong>
                            <small><?= (int) $sItem['quantity'] ?> toples &times; <?= rupiah($sItem['price']) ?></small>
                        </div>
                        <strong><?= rupiah((float) $sItem['price'] * (int) $sItem['quantity']) ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="sel-calc-summary">
                    <div class="calc-row">
                        <span>Subtotal</span>
                        <span><?= rupiah($selectedOrder['total']) ?></span>
                    </div>
                    <div class="calc-row">
                        <span>Ongkir disepakati</span>
                        <span><?= !empty($selectedOrder['shipping_fee']) ? rupiah($selectedOrder['shipping_fee']) : 'Rp 0' ?></span>
                    </div>
                    <div class="calc-row calc-total">
                        <span>Total</span>
                        <strong><?= rupiah($totalWithShip) ?></strong>
                    </div>
                    <div class="calc-row dp-row">
                        <span>DP diterima &bull; 50%</span>
                        <strong><?= $isDpPaid ? rupiah($selectedOrder['dp_amount']) : 'Rp 0' ?></strong>
                    </div>
                    <div class="calc-row remaining-row">
                        <span>Sisa pelunasan</span>
                        <strong><?= rupiah($remainingVal) ?></strong>
                    </div>
                </div>

                <div class="quick-reminder-row">
                    <a class="button button-outline-dark button-full button-small"
                        href="../chat.php?customer=<?= (int) $selectedOrder['user_id'] ?>">
                        &#128172; Buka chat / Kirim pengingat ke pelanggan
                    </a>
                </div>

                <div class="sel-fulfillment-section">
                    <h4>Tujuan &amp; pemenuhan</h4>
                    <p class="fulfillment-dest">
                        <strong><?= e($selectedOrder['address']) ?></strong><br>
                        <?php if ($selectedOrder['notes']): ?>
                        <small>Catatan: "<?= e($selectedOrder['notes']) ?>"</small>
                        <?php endif; ?>
                    </p>

                    <form method="post" class="form-stack admin-order-update-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= (int) $selectedOrder['id'] ?>">

                        <div class="form-group-clean">
                            <label for="delivery-date-input">
                                <strong>Jadwal pengiriman baru</strong>
                                <small>(Bisa disesuaikan admin jika antrean dapur padat setelah chat)</small>
                            </label>
                            <input id="delivery-date-input" type="date" name="delivery_date"
                                value="<?= e($selectedOrder['delivery_date'] ?? '') ?>" required>
                        </div>

                        <div class="form-group-clean">
                            <label for="shipping-fee-input">
                                <strong>Atur Ongkir Final (Rp)</strong>
                            </label>
                            <input id="shipping-fee-input" type="number" name="shipping_fee"
                                value="<?= (int) ($selectedOrder['shipping_fee'] ?? 0) ?>" min="0" step="1000">
                        </div>

                        <div class="form-group-clean">
                            <label for="status-select"><strong>Status pemenuhan</strong></label>
                            <select id="status-select" name="status">
                                <?php foreach ($allowedStatuses as $st): ?>
                                <option value="<?= e($st) ?>" <?= $selectedOrder['status'] === $st ? 'selected' : '' ?>>
                                    <?= e(order_status_label($st)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group-clean">
                            <label for="payment-status-select"><strong>Status pembayaran</strong></label>
                            <select id="payment-status-select" name="payment_status">
                                <option value="unpaid"
                                    <?= $selectedOrder['payment_status'] === 'unpaid' ? 'selected' : '' ?>>DP 50% belum
                                    dibayar</option>
                                <option value="dp_paid"
                                    <?= $selectedOrder['payment_status'] === 'dp_paid' ? 'selected' : '' ?>>DP 50%
                                    terverifikasi</option>
                                <option value="paid"
                                    <?= $selectedOrder['payment_status'] === 'paid' ? 'selected' : '' ?>>Lunas 100%
                                </option>
                            </select>
                        </div>

                        <button class="button button-solid-black button-full" type="submit">
                            &#10003; Simpan status &amp; jadwal baru
                        </button>
                    </form>
                </div>
            </section>
            <?php else: ?>
            <div class="panel empty-select-box">
                <p>Pilih pesanan dari daftar di sebelah kiri untuk melihat detail dan melakukan penyesuaian jadwal.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>