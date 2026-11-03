<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
    if ($action === 'remove' && $productId) {
        unset($_SESSION['cart'][$productId]);
        set_flash('success', 'Produk dihapus dari tas belanja.');
    } elseif ($action === 'update' && $productId) {
        $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT);
        $stockQuery = db()->prepare('SELECT stock FROM products WHERE id = ? AND is_active = 1');
        $stockQuery->execute([$productId]);
        $stock = $stockQuery->fetchColumn();
        if ($stock === false) {
            unset($_SESSION['cart'][$productId]);
        } else {
            $_SESSION['cart'][$productId] = min(max(1, (int) $quantity), (int) $stock);
        }
        set_flash('success', 'Jumlah produk diperbarui.');
    }
    header('Location: cart.php');
    exit;
}

$cart = $_SESSION['cart'] ?? [];
$items = [];
$total = 0.0;
if ($cart) {
    $ids = array_values(array_filter(array_map('intval', array_keys($cart))));
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = db()->prepare("SELECT id, name, price, stock, image_url FROM products WHERE is_active = 1 AND id IN ($placeholders)");
        $query->execute($ids);
        $items = $query->fetchAll();
        foreach ($items as &$item) {
            $item['quantity'] = min((int) ($cart[$item['id']] ?? 1), (int) $item['stock']);
            $item['subtotal'] = (float) $item['price'] * $item['quantity'];
            $total += $item['subtotal'];
        }
        unset($item);
    }
}
$pageTitle = 'Tas belanja';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell"><div class="page-heading"><p class="eyebrow">PILIHANMU</p><h1>Tas belanja</h1><p>Periksa koleksimu sebelum melanjutkan ke pemesanan.</p></div>
    <?php if (!$items): ?><div class="panel"><p>Tasmu masih kosong.</p><a class="button button-dark" href="index.php#koleksi">Lihat koleksi</a></div>
    <?php else: ?><div class="cart-layout"><section class="panel"><?php foreach ($items as $item): ?><article class="cart-item"><img src="<?= e(asset_url($item['image_url'])) ?>" alt="<?= e($item['name']) ?>"><div><h3><?= e($item['name']) ?></h3><p><?= rupiah($item['price']) ?> &middot; kuota preorder <?= (int) $item['stock'] ?></p></div><div class="cart-qty"><form method="post" class="cart-qty"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>"><input type="number" name="quantity" min="1" max="<?= (int) $item['stock'] ?>" value="<?= (int) $item['quantity'] ?>" aria-label="Jumlah <?= e($item['name']) ?>"><button class="button button-outline button-small" type="submit">Perbarui</button></form><strong><?= rupiah($item['subtotal']) ?></strong><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>"><button class="button button-outline button-small" type="submit" aria-label="Hapus <?= e($item['name']) ?>">Hapus</button></form></div></article><?php endforeach; ?></section><aside class="summary-panel"><h2>Ringkasan</h2><div class="summary-row"><span><?= count($items) ?> jenis kue</span><span><?= rupiah($total) ?></span></div><div class="summary-row summary-total"><span>DP 50% sebelum dibuat</span><span><?= rupiah($total * 0.5) ?></span></div><div class="summary-row"><span>Sisa pelunasan</span><span><?= rupiah($total * 0.5) ?></span></div><a class="button button-dark" href="checkout.php">Atur preorder <span aria-hidden="true">&#8594;</span></a><p class="payment-note">Ongkir area Medan dikonfirmasi terpisah. Produksi dimulai setelah DP 50% diverifikasi admin.</p></aside></div><?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>