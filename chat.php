<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
$user = require_login();
$isAdmin = $user['role'] === 'admin';
$pdo = db();

$selectedOrder = $isAdmin ? filter_var($_GET['order'] ?? null, FILTER_VALIDATE_INT) : filter_var($_GET['order'] ?? null, FILTER_VALIDATE_INT);
$orders = [];
$order = null;
$customer = null;

if ($isAdmin) {
    $orders = $pdo->query("
        SELECT o.id, o.user_id, o.total, o.shipping_fee, o.dp_amount, o.address, o.payment_status, o.status, o.delivery_date, o.created_at,
               u.name AS customer_name, u.email AS customer_email,
               (SELECT MAX(m.created_at) FROM messages m WHERE m.order_id = o.id) AS latest_message,
               (SELECT COUNT(*) FROM messages m WHERE m.order_id = o.id) AS message_count
        FROM orders o
        JOIN users u ON u.id = o.user_id
        ORDER BY latest_message DESC, o.created_at DESC
    ")->fetchAll();

    if (!$selectedOrder && $orders) {
        $selectedOrder = (int) $orders[0]['id'];
    }

    foreach ($orders as $o) {
        if ((int) $o['id'] === $selectedOrder) {
            $order = $o;
            $customer = ['id' => (int) $o['user_id'], 'name' => $o['customer_name'], 'email' => $o['customer_email']];
            break;
        }
    }
} else {
    $orders = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
    $orders->execute([$user['id']]);
    $orders = $orders->fetchAll();

    if (!$selectedOrder && $orders) {
        $selectedOrder = (int) $orders[0]['id'];
    }

    foreach ($orders as $o) {
        if ((int) $o['id'] === $selectedOrder) {
            $order = $o;
            break;
        }
    }
    $customer = $user;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'send_message';

    if ($action === 'send_message') {
        $body = trim((string) ($_POST['body'] ?? ''));
        $targetOrder = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);

        if ($body !== '' && strlen($body) <= 2000 && $targetOrder) {
            $targetOrderOwner = null;
            $ownerCheck = $pdo->prepare('SELECT user_id FROM orders WHERE id = ?');
            $ownerCheck->execute([$targetOrder]);
            $row = $ownerCheck->fetch();
            if ($row) {
                $targetOrderOwner = (int) $row['user_id'];
            }

            if ($targetOrderOwner !== null) {
                $insert = $pdo->prepare('INSERT INTO messages (order_id, customer_id, sender_role, body) VALUES (?, ?, ?, ?)');
                $insert->execute([$targetOrder, $targetOrderOwner, $isAdmin ? 'admin' : 'customer', encrypt_message($body)]);
            }
        }
        header('Location: ' . app_url('chat.php?order=' . $targetOrder));
        exit;
    }

    if ($isAdmin && $action === 'set_shipping') {
        $targetOrderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
        $shippingFee = max(0, (int) ($_POST['shipping_fee'] ?? 0));

        if ($targetOrderId) {
            $update = $pdo->prepare('UPDATE orders SET shipping_fee = ? WHERE id = ?');
            $update->execute([$shippingFee, $targetOrderId]);

            $orderRow = $pdo->prepare('SELECT user_id FROM orders WHERE id = ?');
            $orderRow->execute([$targetOrderId]);
            $ord = $orderRow->fetch();
            $custId = $ord ? (int) $ord['user_id'] : 0;

            $notifMsg = 'Ongkir telah disepakati sebesar ' . rupiah($shippingFee) . ' dan diterapkan ke pesanan #' . order_code($targetOrderId) . '.';
            $insertMsg = $pdo->prepare("INSERT INTO messages (order_id, customer_id, sender_role, body) VALUES (?, ?, 'admin', ?)");
            $insertMsg->execute([$targetOrderId, $custId, encrypt_message($notifMsg)]);

            set_flash('success', 'Ongkir berhasil diterapkan ke pesanan.');
        }
        header('Location: ' . app_url('chat.php?order=' . $targetOrderId));
        exit;
    }

    if ($isAdmin && $action === 'adjust_delivery_date') {
        $targetOrderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
        $newDate = trim((string) ($_POST['delivery_date'] ?? ''));

        if ($targetOrderId && $newDate !== '') {
            $update = $pdo->prepare('UPDATE orders SET delivery_date = ? WHERE id = ?');
            $update->execute([$newDate, $targetOrderId]);

            $orderRow = $pdo->prepare('SELECT user_id FROM orders WHERE id = ?');
            $orderRow->execute([$targetOrderId]);
            $ord = $orderRow->fetch();
            $custId = $ord ? (int) $ord['user_id'] : 0;

            $notifMsg = 'Jadwal pengiriman pesanan #' . order_code($targetOrderId) . ' telah disesuaikan menjadi: ' . indo_date($newDate) . ' (13.00-16.00 WIB) sesuai kesepakatan bersama.';
            $insertMsg = $pdo->prepare("INSERT INTO messages (order_id, customer_id, sender_role, body) VALUES (?, ?, 'admin', ?)");
            $insertMsg->execute([$targetOrderId, $custId, encrypt_message($notifMsg)]);

            set_flash('success', 'Jadwal pengiriman pesanan #' . order_code($targetOrderId) . ' berhasil disesuaikan.');
        }
        header('Location: ' . app_url('chat.php?order=' . $targetOrderId));
        exit;
    }
}

$messages = [];
if ($selectedOrder) {
    $q = $pdo->prepare('SELECT m.*, u.name AS customer_name FROM messages m JOIN users u ON u.id = m.customer_id WHERE m.order_id = ? ORDER BY m.id ASC LIMIT 300');
    $q->execute([$selectedOrder]);
    $messages = $q->fetchAll();
    foreach ($messages as &$message) {
        $message['body'] = decrypt_message((string) $message['body']);
    }
    unset($message);
}

$latestOrder = $order;
$orderItems = [];
if ($selectedOrder) {
    $itemsQ = $pdo->prepare('SELECT product_name, price, quantity FROM order_items WHERE order_id = ?');
    $itemsQ->execute([$selectedOrder]);
    $orderItems = $itemsQ->fetchAll();
}

$pageTitle = $isAdmin ? 'Inbox Negosiasi' : 'Chat Admin';
$currentNav = 'chat';

if ($isAdmin) {
    $adminActive = 'chat';
    require __DIR__ . '/includes/admin_header.php';
} else {
    require __DIR__ . '/includes/header.php';
}
?>
<main class="page-shell chat-shell">
    <div class="page-heading">
        <p class="eyebrow"><?= $isAdmin ? 'RUANG ADMIN / NEGOSIASI' : 'CHAT PREORDER / MEDAN' ?></p>
        <h1><?= $isAdmin ? 'Inbox negosiasi' : 'Tanya Sanggar Tresno' ?></h1>
        <p><?= $isAdmin ? 'Sepakati ongkir dan jadwal pengiriman per pesanan.' : 'Diskusikan ongkir dan jadwal pengiriman per pesanan.' ?>
        </p>
    </div>

    <div class="chat-main-grid-layout <?= $isAdmin ? 'admin-chat-mode' : 'user-chat-mode' ?>">
        <?php if ($isAdmin): ?>
        <aside class="chat-thread-sidebar panel">
            <div class="thread-search-box">
                <input type="text" id="filterOrderInput" placeholder="Cari pesanan / pelanggan..."
                    onkeyup="filterOrders()">
            </div>
            <div class="thread-list-scroll" id="orderListScroll">
                <?php if (!$orders): ?>
                <p class="empty-hint-text">Belum ada pesanan.</p>
                <?php else: ?>
                <?php foreach ($orders as $oItem): ?>
                <?php $isAct = ((int) $oItem['id'] === $selectedOrder); ?>
                <a class="thread-user-item <?= $isAct ? 'is-active' : '' ?>"
                    href="<?= e(app_url('chat.php?order=' . (int) $oItem['id'])) ?>">
                    <div class="thread-user-avatar"><?= strtoupper(substr($oItem['customer_name'], 0, 1)) ?></div>
                    <div class="thread-user-text">
                        <strong class="user-item-name">#<?= e(order_code($oItem)) ?></strong>
                        <small><?= e($oItem['customer_name']) ?> &bull; <?= e($oItem['customer_email']) ?></small>
                        <?php if ($oItem['latest_message']): ?>
                        <span class="thread-time"><?= date('d M, H:i', strtotime($oItem['latest_message'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="thread-order-status status-badge-clean status-<?= e($oItem['status']) ?>">
                        <?= e(order_status_label($oItem['status'])) ?>
                    </span>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>
        <?php endif; ?>

        <section class="chat-conversation-panel panel">
            <header class="chat-room-header">
                <div>
                    <?php if ($isAdmin): ?>
                    <h2><?= e($customer['name'] ?? 'Pelanggan') ?></h2>
                    <?php else: ?>
                    <h2>Admin Sanggar Tresno</h2>
                    <?php endif; ?>
                    <small>Pre-order Kue Lebaran Rumahan</small>
                </div>
                <?php if ($latestOrder): ?>
                <span class="chat-order-pill">Pesanan #<?= e(order_code($latestOrder)) ?></span>
                <?php endif; ?>
            </header>

            <?php if ($isAdmin && $latestOrder): ?>
            <div class="negotiation-action-bar">
                <form method="post" class="nego-inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="set_shipping">
                    <input type="hidden" name="order_id" value="<?= (int) $latestOrder['id'] ?>">
                    <label>
                        <span>Ongkir final (Rp)</span>
                        <input type="number" name="shipping_fee"
                            value="<?= (int) ($latestOrder['shipping_fee'] ?? 0) ?>" step="1000" min="0" required>
                    </label>
                    <button type="submit" class="button button-solid-black button-small">Terapkan Ongkir</button>
                </form>

                <form method="post" class="nego-inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="adjust_delivery_date">
                    <input type="hidden" name="order_id" value="<?= (int) $latestOrder['id'] ?>">
                    <label>
                        <span>Jadwal baru (kesepakatan chat)</span>
                        <input type="date" name="delivery_date"
                            value="<?= e($latestOrder['delivery_date'] ?? date('Y-m-d', strtotime('+3 days'))) ?>"
                            required>
                    </label>
                    <button type="submit" class="button button-outline-dark button-small">Perbarui Jadwal</button>
                </form>
            </div>
            <?php endif; ?>

            <div class="chat-messages-container" id="chatBox">
                <?php if (!$messages): ?>
                <div class="chat-empty-start">
                    <p>Belum ada percakapan. Sapa untuk memulai negosiasi ongkir dan jadwal pre-order.</p>
                </div>
                <?php endif; ?>

                <?php foreach ($messages as $msg): ?>
                <?php
                    $isOwn = ($isAdmin && $msg['sender_role'] === 'admin') || (!$isAdmin && $msg['sender_role'] === 'customer');
                    $senderLabel = $isOwn ? 'Anda' : ($isAdmin ? ($msg['customer_name'] ?? 'Pelanggan') : 'Admin Sanggar Tresno');
                    ?>
                <article class="chat-bubble-row <?= $isOwn ? 'is-own' : 'is-other' ?>">
                    <div class="chat-bubble-content">
                        <p class="bubble-text"><?= nl2br(e($msg['body'])) ?></p>
                        <span class="bubble-meta"><?= e($senderLabel) ?> &bull;
                            <?= date('H:i', strtotime($msg['created_at'])) ?></span>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <form class="chat-compose-form" method="post" action="<?= e(app_url('chat.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send_message">
                <input type="hidden" name="order_id" value="<?= (int) $selectedOrder ?>">
                <textarea name="body" placeholder="Tulis pesan negosiasi atau pertanyaan..." required maxlength="2000"
                    rows="2" aria-label="Tulis pesan"></textarea>
                <button class="button button-solid-black" type="submit">Kirim</button>
            </form>
        </section>

        <?php if ($latestOrder): ?>
        <aside class="chat-order-aside panel">
            <h3>Pesanan terkait</h3>
            <p class="order-reference">#<?= e(order_code($latestOrder)) ?></p>

            <div class="order-aside-badge-row">
                <span class="status-badge-clean status-<?= e($latestOrder['payment_status']) ?>">
                    <?= e(payment_status_label($latestOrder['payment_status'])) ?>
                </span>
                <span class="status-badge-clean status-<?= e($latestOrder['status']) ?>">
                    <?= e(order_status_label($latestOrder['status'])) ?>
                </span>
            </div>

            <div class="chat-order-items-mini">
                <?php foreach ($orderItems as $oItem): ?>
                <div class="order-mini-row">
                    <span><?= e($oItem['product_name']) ?> &times; <?= (int) $oItem['quantity'] ?></span>
                    <span><?= rupiah((float) $oItem['price'] * (int) $oItem['quantity']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="chat-order-calc-table">
                <div class="calc-row">
                    <span>Produk</span>
                    <span><?= rupiah($latestOrder['total']) ?></span>
                </div>
                <div class="calc-row">
                    <span>Ongkir final</span>
                    <strong><?= !empty($latestOrder['shipping_fee']) ? rupiah($latestOrder['shipping_fee']) : 'Dinegosiasikan' ?></strong>
                </div>
                <div class="calc-row calc-total">
                    <span>Total pesanan</span>
                    <span><?= rupiah((float) $latestOrder['total'] + (int) ($latestOrder['shipping_fee'] ?? 0)) ?></span>
                </div>
                <div class="calc-row">
                    <span>DP 50%</span>
                    <strong><?= rupiah($latestOrder['dp_amount']) ?></strong>
                </div>
                <div class="calc-row">
                    <span>Sisa pelunasan</span>
                    <span><?= rupiah(max(0, (float) $latestOrder['total'] + (int) ($latestOrder['shipping_fee'] ?? 0) - (float) $latestOrder['dp_amount'])) ?></span>
                </div>
            </div>

            <div class="chat-order-address-box">
                <strong>Tujuan Pengantaran</strong>
                <p><?= nl2br(e($latestOrder['address'])) ?></p>
                <small>Jadwal:
                    <?= e(indo_date($latestOrder['delivery_date'] ?? date('Y-m-d', strtotime('+3 days')))) ?></small>
            </div>

            <?php if ($isAdmin): ?>
            <a class="button button-outline-dark button-full button-small"
                href="<?= e(app_url('admin/kelola_orders.php?order=' . (int) $latestOrder['id'])) ?>">
                Buka di Kelola Pesanan &rarr;
            </a>
            <?php else: ?>
            <a class="button button-outline-dark button-full button-small"
                href="<?= e(app_url('order_detail.php?id=' . (int) $latestOrder['id'])) ?>">
                Lihat Rincian Lengkap &rarr;
            </a>
            <?php endif; ?>
        </aside>
        <?php endif; ?>
    </div>
</main>

<script>
function filterOrders() {
    const query = document.getElementById('filterOrderInput').value.toLowerCase();
    const items = document.querySelectorAll('.thread-user-item');
    items.forEach(item => {
        const text = item.innerText.toLowerCase();
        item.style.display = text.includes(query) ? 'flex' : 'none';
    });
}

window.addEventListener('DOMContentLoaded', () => {
    const box = document.getElementById('chatBox');
    if (box) {
        box.scrollTop = box.scrollHeight;
    }
});
</script>

<?php require __DIR__ . ($isAdmin ? '/includes/admin_footer.php' : '/includes/footer.php'); ?>