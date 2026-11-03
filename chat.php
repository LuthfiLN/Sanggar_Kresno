<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
$user = require_login();
$isAdmin = $user['role'] === 'admin';
$selectedCustomer = $isAdmin ? filter_var($_GET['customer'] ?? null, FILTER_VALIDATE_INT) : (int) $user['id'];
$customers = [];
$customer = null;

if ($isAdmin) {
    $customers = db()->query("SELECT u.id, u.name, u.email, MAX(m.created_at) AS latest_message FROM users u LEFT JOIN messages m ON m.customer_id = u.id WHERE u.role = 'customer' GROUP BY u.id ORDER BY latest_message DESC, u.name")->fetchAll();
    if (!$selectedCustomer && $customers) {
        $selectedCustomer = (int) $customers[0]['id'];
    }
    foreach ($customers as $candidate) {
        if ((int) $candidate['id'] === $selectedCustomer) {
            $customer = $candidate;
            break;
        }
    }
} else {
    $customer = $user;
}

if ($selectedCustomer && !$customer) {
    http_response_code(404);
    exit('Percakapan tidak ditemukan.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $body = trim((string) ($_POST['body'] ?? ''));
    $target = $isAdmin ? filter_var($_POST['customer_id'] ?? null, FILTER_VALIDATE_INT) : (int) $user['id'];
    if ($isAdmin && (!$target || !array_filter($customers, static fn(array $item): bool => (int) $item['id'] === $target))) {
        http_response_code(403);
        exit('Penerima pesan tidak valid.');
    }
    if ($body !== '' && strlen($body) <= 2000) {
        $insert = db()->prepare('INSERT INTO messages (customer_id, sender_role, body) VALUES (?, ?, ?)');
        $insert->execute([$target, $isAdmin ? 'admin' : 'customer', $body]);
    }
    header('Location: chat.php' . ($isAdmin ? '?customer=' . $target : ''));
    exit;
}

$messages = [];
if ($selectedCustomer) {
    $query = db()->prepare('SELECT m.*, u.name AS sender_name FROM messages m JOIN users u ON u.id = m.customer_id WHERE m.customer_id = ? ORDER BY m.id ASC LIMIT 300');
    $query->execute([$selectedCustomer]);
    $messages = $query->fetchAll();
}
$pageTitle = 'Pesan';
if ($isAdmin) {
    $adminActive = 'chat';
    require __DIR__ . '/includes/admin_header.php';
} else {
    require __DIR__ . '/includes/header.php';
}
?>
<main class="page-shell">
    <div class="page-heading">
        <p class="eyebrow">CHAT PREORDER / MEDAN</p>
        <h1><?= $isAdmin ? 'Pesan masuk' : 'Tanya Sanggar Tresno' ?></h1>
        <p><?= $isAdmin ? 'Koordinasikan jadwal, DP, dan pengiriman kue.' : 'Tanyakan pilihan kue, konfirmasi DP, atau jadwal pengiriman.' ?>
        </p>
    </div>
    <div class="chat-layout"><?php if ($isAdmin): ?><aside class="chat-aside">
            <h2>Pelanggan</h2><?php if (!$customers): ?><p>Belum ada akun pelanggan.</p><?php else: ?><div
                class="message-thread-list"><?php foreach ($customers as $item): ?><a class="thread-link"
                    href="chat.php?customer=<?= (int) $item['id'] ?>"
                    <?= (int) $item['id'] === $selectedCustomer ? 'style="border-color:var(--green);background:var(--white)"' : '' ?>><?= e($item['name']) ?><br><small><?= e($item['email']) ?></small></a><?php endforeach; ?>
            </div><?php endif; ?>
        </aside><?php else: ?><aside class="chat-aside">
            <h2>Sanggar Tresno</h2>
            <p>Pesanmu hanya dapat dibaca oleh tim admin dan kamu.</p>
            <p>Balasan akan muncul di percakapan ini.</p>
        </aside><?php endif; ?><section class="chat-main">
            <div class="chat-messages" aria-live="polite"><?php if (!$messages): ?><p
                    style="color:var(--muted);font-size:12px">Belum ada pesan di percakapan ini. Sapa admin untuk
                    memulai.</p>
                <?php endif; ?><?php foreach ($messages as $message): $own = ($isAdmin && $message['sender_role'] === 'admin') || (!$isAdmin && $message['sender_role'] === 'customer'); ?>
                <article class="message <?= $own ? 'message-own' : '' ?>">
                    <?= e($message['body']) ?><small><?= e($own ? 'Anda' : ($isAdmin ? $message['sender_name'] : 'Admin')) ?>
                        &middot; <?= e(date('d M, H:i', strtotime($message['created_at']))) ?></small></article>
                <?php endforeach; ?></div><?php if (!$isAdmin || $customer): ?><form class="chat-compose" method="post">
                <?= csrf_field() ?><?php if ($isAdmin): ?><input type="hidden" name="customer_id"
                    value="<?= (int) $selectedCustomer ?>"><?php endif; ?><textarea name="body" required
                    maxlength="2000" placeholder="Tulis pesan..." aria-label="Tulis pesan"></textarea><button
                    class="button button-dark" type="submit">Kirim</button></form><?php endif; ?>
        </section>
    </div>
</main>
<?php require __DIR__ . ($isAdmin ? '/includes/admin_footer.php' : '/includes/footer.php'); ?>