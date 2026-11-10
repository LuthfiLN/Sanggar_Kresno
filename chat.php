<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
$user = require_login();
$isAdmin = $user['role'] === 'admin';
$pdo = db();

$selectedCustomer = $isAdmin ? filter_var($_GET['customer'] ?? null, FILTER_VALIDATE_INT) : (int) $user['id'];
$customers = [];
$customer = null;

if ($isAdmin) {
    $customers = $pdo->query("SELECT u.id, u.name, u.email, MAX(m.created_at) AS latest_message, (SELECT o.id FROM orders o WHERE o.user_id = u.id ORDER BY o.created_at DESC LIMIT 1) AS latest_order_id FROM users u LEFT JOIN messages m ON m.customer_id = u.id WHERE u.role = 'customer' GROUP BY u.id ORDER BY latest_message DESC, u.name ASC")->fetchAll();

    if (!$selectedCustomer && $customers) {
        $selectedCustomer = (int) $customers[0]['id'];
    }

    foreach ($customers as $c) {
        if ((int) $c['id'] === $selectedCustomer) {
            $customer = $c;
            break;
        }
    }
} else {
    $customer = $user;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'send_message';

    if ($action === 'send_message') {
        $body = trim((string) ($_POST['body'] ?? ''));
        $target = $isAdmin ? filter_var($_POST['customer_id'] ?? null, FILTER_VALIDATE_INT) : (int) $user['id'];

        if ($body !== '' && strlen($body) <= 2000 && $target) {
            $insert = $pdo->prepare('INSERT INTO messages (customer_id, sender_role, body) VALUES (?, ?, ?)');
            $insert->execute([$target, $isAdmin ? 'admin' : 'customer', $body]);
        }
        header('Location: ' . app_url('chat.php' . ($isAdmin ? '?customer=' . $target : '')));
        exit;
    }

    if ($isAdmin && $action === 'set_shipping') {
        $targetOrderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
        $shippingFee = max(0, (int) ($_POST['shipping_fee'] ?? 0));

        if ($targetOrderId) {
            $update = $pdo->prepare('UPDATE orders SET shipping_fee = ? WHERE id = ?');
            $update->execute([$shippingFee, $targetOrderId]);

            $notifMsg = 'Ongkir telah disepakati sebesar ' . rupiah($shippingFee) . ' dan diterapkan ke pesanan #' . order_code($targetOrderId) . '.';
            $insertMsg = $pdo->prepare("INSERT INTO messages (customer_id, sender_role, body) VALUES (?, 'admin', ?)");
            $insertMsg->execute([$selectedCustomer, $notifMsg]);

            set_flash('success', 'Ongkir berhasil diterapkan ke pesanan.');
        }
        header('Location: ' . app_url('chat.php?customer=' . $selectedCustomer));
        exit;
    }

    if ($isAdmin && $action === 'adjust_delivery_date') {
        $targetOrderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
        $newDate = trim((string) ($_POST['delivery_date'] ?? ''));

        if ($targetOrderId && $newDate !== '') {
            $update = $pdo->prepare('UPDATE orders SET delivery_date = ? WHERE id = ?');
            $update->execute([$newDate, $targetOrderId]);

            $notifMsg = 'Jadwal pengiriman pesanan #' . order_code($targetOrderId) . ' telah disesuaikan menjadi: ' . indo_date($newDate) . ' (13.00-16.00 WIB) sesuai kesepakatan bersama.';
            $insertMsg = $pdo->prepare("INSERT INTO messages (customer_id, sender_role, body) VALUES (?, 'admin', ?)");
            $insertMsg->execute([$selectedCustomer, $notifMsg]);

            set_flash('success', 'Jadwal pengiriman pesanan #' . order_code($targetOrderId) . ' berhasil disesuaikan.');
        }
        header('Location: ' . app_url('chat.php?customer=' . $selectedCustomer));
        exit;
    }
}

$messages = [];
if ($selectedCustomer) {
    $q = $pdo->prepare('SELECT m.*, u.name AS customer_name FROM messages m JOIN users u ON u.id = m.customer_id WHERE m.customer_id = ? ORDER BY m.id ASC LIMIT 300');
    $q->execute([$selectedCustomer]);
    $messages = $q->fetchAll();
}

$latestOrder = null;
$orderItems = [];
if ($selectedCustomer) {
    $orderQ = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
    $orderQ->execute([$selectedCustomer]);
    $latestOrder = $orderQ->fetch();

    if ($latestOrder) {
        $itemsQ = $pdo->prepare('SELECT product_name, price, quantity FROM order_items WHERE order_id = ?');
        $itemsQ->execute([$latestOrder['id']]);
        $orderItems = $itemsQ->fetchAll();
    }
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
        <p><?= $isAdmin ? 'Sepakati ongkir dengan pelanggan dan sesuaikan jadwal pengiriman pesanan.' : 'Diskusikan pilihan kue, penentuan ongkir Medan, atau penyesuaian jadwal pengiriman.' ?>
        </p>
    </div>

    <div class="chat-main-grid-layout <?= $isAdmin ? 'admin-chat-mode' : 'user-chat-mode' ?>">
        <?php if ($isAdmin): ?>
        <aside class="chat-thread-sidebar panel">
            <div class="thread-search-box">
                <input type="text" id="filterCustomerInput" placeholder="Cari pelanggan..." onkeyup="filterCustomers()">
            </div>
            <div class="thread-list-scroll" id="customerListScroll">
                <?php if (!$customers): ?>
                <p class="empty-hint-text">Belum ada pelanggan terdaftar.</p>
                <?php else: ?>
                <?php foreach ($customers as $cItem): ?>
                <?php $isAct = ((int) $cItem['id'] === $selectedCustomer); ?>
                <a class="thread-user-item <?= $isAct ? 'is-active' : '' ?>"
                    href="<?= e(app_url('chat.php?customer=' . (int) $cItem['id'])) ?>">
                    <div class="thread-user-avatar"><?= strtoupper(substr($cItem['name'], 0, 1)) ?></div>
                    <div class="thread-user-text">
                        <strong class="user-item-name"><?= e($cItem['name']) ?></strong>
                        <small><?= e($cItem['email']) ?></small>
                        <?php if ($cItem['latest_message']): ?>
                        <span class="thread-time"><?= date('d M, H:i', strtotime($cItem['latest_message'])) ?></span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>
        <?php endif; ?>

        <section class="chat-conversation-panel panel">
            <header class="chat-room-header">
                <div>
                    <h2><?= $isAdmin ? e($customer['name'] ?? 'Pelanggan') : 'Admin Sanggar Tresno' ?></h2>
                    <small>Pre-order Kue Lebaran Rumahan Medan</small>
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
                <?php if ($isAdmin): ?>
                <input type="hidden" name="customer_id" value="<?= (int) $selectedCustomer ?>">
                <?php endif; ?>
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
function filterCustomers() {
    const query = document.getElementById('filterCustomerInput').value.toLowerCase();
    const items = document.querySelectorAll('.thread-user-item');
    items.forEach(item => {
        const name = item.querySelector('.user-item-name').innerText.toLowerCase();
        item.style.display = name.includes(query) ? 'flex' : 'none';
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