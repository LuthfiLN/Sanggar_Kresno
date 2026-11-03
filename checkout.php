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
    $deliveryDate = DateTimeImmutable::createFromFormat('!Y-m-d', $deliveryDateInput);
    $notes = trim((string) ($_POST['notes'] ?? ''));
    if ($recipient === '' || $phone === '' || $address === '' || !$deliveryDate || $deliveryDate->format('Y-m-d') !== $deliveryDateInput) {
        $error = 'Lengkapi nama, nomor WhatsApp, alamat, dan tanggal kirim.';
    } elseif ($deliveryDate <= new DateTimeImmutable('today')) {
        $error = 'Tanggal preorder harus minimal untuk besok.';
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
            $order->execute([$user['id'], $recipient, $phone, $address, $deliveryDateInput, $notes, $total, $dpAmount]);
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

$pageTitle = 'Pemesanan';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell"><div class="page-heading"><p class="eyebrow">PREORDER KUE / MEDAN</p><h1>Atur pesananmu</h1><p>Kue dibuat segar sesuai jadwal. DP 50% wajib terkonfirmasi sebelum proses pembuatan dimulai.</p></div><?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?><div class="checkout-layout"><form class="panel form-stack" method="post"><?= csrf_field() ?><div class="form-grid"><label>Nama penerima<input name="recipient_name" required maxlength="120" value="<?= e($_POST['recipient_name'] ?? $user['name']) ?>"></label><label>Nomor WhatsApp<input name="phone" required maxlength="40" inputmode="tel" value="<?= e($_POST['phone'] ?? '') ?>"></label></div><label>Tanggal pengiriman<input type="date" name="delivery_date" min="<?= e((new DateTimeImmutable('tomorrow'))->format('Y-m-d')) ?>" required value="<?= e($_POST['delivery_date'] ?? '') ?>"><small>Pilih tanggal mulai besok. Layanan preorder dan pengiriman berlokasi di Medan.</small></label><label>Alamat pengiriman di Medan<textarea name="address" required maxlength="2000" placeholder="Jalan, kelurahan, kecamatan, Kota Medan"><?= e($_POST['address'] ?? '') ?></textarea></label><label>Catatan kue atau pengiriman <small>Opsional</small><textarea name="notes" maxlength="500" placeholder="Ucapan kartu, detail rasa, waktu pengiriman..."><?= e($_POST['notes'] ?? '') ?></textarea></label><button class="button button-dark" type="submit">Kirim preorder</button></form><aside class="summary-panel"><h2>Alur pembayaran</h2><div class="summary-row"><span>DP sebelum produksi</span><strong>50%</strong></div><div class="summary-row"><span>Pelunasan</span><span>Sebelum dikirim</span></div><p class="payment-note">Admin mengirim nominal DP dan instruksi pembayaran melalui chat. Produksi dimulai sesudah pembayaran DP diverifikasi; ongkir Medan dikonfirmasi terpisah.</p><a class="text-link" href="chat.php">Tanya admin soal preorder</a></aside></div></main>
<?php require __DIR__ . '/includes/footer.php'; ?>