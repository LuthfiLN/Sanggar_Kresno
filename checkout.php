<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
$user = require_login();
$cart = $_SESSION['cart'] ?? [];
if (!$cart) {
    set_flash('error', 'Tas belanja masih kosong.');
    header('Location: cart.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $recipient = trim((string) ($_POST['recipient_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $deliveryDateInput = trim((string) ($_POST['delivery_date'] ?? ''));
    $fulfillment = (string) ($_POST['fulfillment'] ?? ($_SESSION['preorder_fulfillment'] ?? 'delivery'));
    $deliveryDate = DateTimeImmutable::createFromFormat('!Y-m-d', $deliveryDateInput);
    $minimumDeliveryDate = new DateTimeImmutable('today +3 days');
    $notes = trim((string) ($_POST['notes'] ?? ''));
    if ($recipient === '' || $phone === '' || !in_array($fulfillment, ['delivery', 'pickup'], true) || ($fulfillment === 'delivery' && $address === '') || !$deliveryDate || $deliveryDate->format('Y-m-d') !== $deliveryDateInput) {
        $error = 'Lengkapi nama, nomor WhatsApp, metode penerimaan, alamat antar, dan tanggal kirim.';
    } elseif ($deliveryDate < $minimumDeliveryDate) {
        $error = 'Tanggal preorder paling cepat 3 hari setelah pemesanan.';
    } elseif (strlen($recipient) > 120 || strlen($phone) > 40 || strlen($notes) > 500) {
        $error = 'Ada isian yang melebihi batas karakter.';
    } else {
        $ids = array_values(array_filter(array_map('intval', array_keys($cart))));
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $query = $pdo->prepare("SELECT id, name, price, stock FROM products WHERE is_active = 1 AND id IN ($placeholders) FOR UPDATE");
            $query->execute($ids);
            $products = $query->fetchAll();
            if (count($products) !== count($ids)) {
                throw new RuntimeException('Ada produk yang tidak lagi tersedia. Silakan periksa tas belanja.');
            }
            $total = 0.0;
            foreach ($products as $product) {
                $quantity = (int) ($cart[$product['id']] ?? 0);
                if ($quantity < 1 || $quantity > (int) $product['stock']) {
                    throw new RuntimeException('Stok ' . $product['name'] . ' tidak mencukupi. Perbarui tas belanja.');
                }
                $total += (float) $product['price'] * $quantity;
            }
            $dpAmount = round($total * 0.5, 2);
            $order = $pdo->prepare('INSERT INTO orders (user_id, recipient_name, phone, address, delivery_date, notes, total, dp_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $savedAddress = $fulfillment === 'pickup' ? 'Ambil sendiri' : $address;
            $order->execute([$user['id'], $recipient, $phone, $savedAddress, $deliveryDateInput, $notes, $total, $dpAmount]);
            $orderId = (int) $pdo->lastInsertId();
            $insertItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, price, quantity) VALUES (?, ?, ?, ?, ?)');
            $reduceStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
            foreach ($products as $product) {
                $quantity = (int) $cart[$product['id']];
                $insertItem->execute([$orderId, $product['id'], $product['name'], $product['price'], $quantity]);
                $reduceStock->execute([$quantity, $product['id']]);
            }
            $pdo->commit();
            unset($_SESSION['cart']);
            unset($_SESSION['preorder_delivery_date'], $_SESSION['preorder_fulfillment']);
            set_flash('success', 'Preorder #' . $orderId . ' tercatat. Kue mulai dibuat setelah DP 50% terkonfirmasi. Admin akan menghubungi Anda untuk pembayaran dan ongkir Medan.');
            header('Location: orders.php');
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception instanceof PDOException ? 'Pesanan belum dapat disimpan. Coba kembali sebentar lagi.' : $exception->getMessage();
        }
    }
}

$minimumDeliveryDate = new DateTimeImmutable('today +3 days');
$savedDeliveryDate = trim((string) ($_SESSION['preorder_delivery_date'] ?? ''));
$parsedSavedDeliveryDate = DateTimeImmutable::createFromFormat('!Y-m-d', $savedDeliveryDate);
$defaultDeliveryDate = $parsedSavedDeliveryDate && $parsedSavedDeliveryDate->format('Y-m-d') === $savedDeliveryDate && $parsedSavedDeliveryDate >= $minimumDeliveryDate
    ? $savedDeliveryDate
    : $minimumDeliveryDate->format('Y-m-d');

$cartTotal = 0.0;
$cartIds = array_values(array_filter(array_map('intval', array_keys($cart))));
if ($cartIds) {
    $placeholders = implode(',', array_fill(0, count($cartIds), '?'));
    $cartQuery = db()->prepare("SELECT id, price FROM products WHERE is_active = 1 AND id IN ($placeholders)");
    $cartQuery->execute($cartIds);
    foreach ($cartQuery->fetchAll() as $cartProduct) {
        $cartTotal += (float) $cartProduct['price'] * (int) ($cart[$cartProduct['id']] ?? 0);
    }
}

$pageTitle = 'Pemesanan';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell checkout-page">
    <nav class="checkout-steps" aria-label="Tahap pemesanan"><span class="is-current"><b>1</b> Keranjang</span><span
            class="is-current"><b>2</b> Data penerima</span><span><b>3</b> Konfirmasi DP</span></nav>
    <div class="page-heading">
        <p class="eyebrow">PREORDER KUE / MEDAN</p>
        <h1>Data pesanan</h1>
        <p>Periksa jadwal dan tujuan penerimaan sebelum preorder dicatat.</p>
    </div>
    <?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?>
    <div class="checkout-layout">
        <form class="panel form-stack" method="post">
            <?= csrf_field() ?>
            <h2 class="checkout-section-title">Penerima & jadwal</h2>
            <div class="form-grid">
                <label>Nama penerima<input name="recipient_name" required maxlength="120"
                        value="<?= e($_POST['recipient_name'] ?? $user['name']) ?>"></label>
                <label>Nomor WhatsApp<input name="phone" required maxlength="40" inputmode="tel"
                        value="<?= e($_POST['phone'] ?? '') ?>"></label>
            </div>
            <label>Tanggal pengiriman<input type="date" name="delivery_date"
                    min="<?= e($minimumDeliveryDate->format('Y-m-d')) ?>" required
                    value="<?= e($_POST['delivery_date'] ?? $defaultDeliveryDate) ?>"><small>Jadwal
                    pre-order paling cepat 3 hari setelah pemesanan.</small></label>
            <label>Metode penerimaan<select name="fulfillment">
                    <option value="delivery"
                        <?= ($_POST['fulfillment'] ?? ($_SESSION['preorder_fulfillment'] ?? 'delivery')) === 'delivery' ? 'selected' : '' ?>>
                        Antar ke alamat</option>
                    <option value="pickup"
                        <?= ($_POST['fulfillment'] ?? ($_SESSION['preorder_fulfillment'] ?? 'delivery')) === 'pickup' ? 'selected' : '' ?>>
                        Ambil sendiri</option>
                </select></label>
            <label>Alamat pengiriman<textarea name="address" maxlength="2000"
                    placeholder="Wajib untuk pengantaran: jalan, kelurahan, kecamatan, Kota Medan"><?= e($_POST['address'] ?? '') ?></textarea><small>Kosongkan
                    jika memilih ambil sendiri.</small></label>
            <label>Catatan pesanan <small>Opsional</small><textarea name="notes" maxlength="500"
                    placeholder="Ucapan kartu atau catatan untuk admin..."><?= e($_POST['notes'] ?? '') ?></textarea></label>
            <button class="button button-dark" type="submit">Konfirmasi preorder</button>
        </form>
        <aside class="checkout-aside">
            <section class="summary-panel">
                <h2>Ringkasan pesanan</h2>
                <div class="summary-row"><span><?= array_sum(array_map('intval', $cart)) ?>
                        item</span><span><?= rupiah($cartTotal) ?></span></div>
                <div class="summary-row"><span>DP 50% sebelum
                        dibuat</span><strong><?= rupiah(round($cartTotal * 0.5, 2)) ?></strong></div>
                <div class="summary-row"><span>Ongkir</span><span>Dinegosiasikan</span></div>
                <p class="payment-note">Admin mengonfirmasi ongkir lewat chat. Pesanan diproses setelah DP diterima.</p>
                <a class="text-link" href="chat.php">Tanya admin</a>
            </section>
            <section class="checkout-rule"><strong>Setelah konfirmasi</strong>
                <p>Admin akan memeriksa jadwal, mengirim rincian DP, dan membantu negosiasi ongkir. Produksi dimulai
                    setelah DP 50% terverifikasi.</p>
            </section>
        </aside>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>