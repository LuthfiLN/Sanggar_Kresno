<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
	verify_csrf();
	$productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
	$query = db()->prepare('SELECT id, stock FROM products WHERE id = ? AND is_active = 1');
	$query->execute([$productId]);
	$product = $query->fetch();
	if (!$product || (int) $product['stock'] < 1) {
		set_flash('error', 'Produk sedang tidak tersedia.');
	} else {
		$_SESSION['cart'] ??= [];
		$inCart = (int) ($_SESSION['cart'][$productId] ?? 0);
		$_SESSION['cart'][$productId] = min($inCart + 1, (int) $product['stock']);
		set_flash('success', 'Produk ditambahkan ke tas belanja.');
	}
	header('Location: ' . app_url('index.php#koleksi'));
	exit;
}

$search = trim((string) ($_GET['q'] ?? ''));
if ($search !== '') {
	$productsQuery = db()->prepare('SELECT * FROM products WHERE is_active = 1 AND (name LIKE ? OR description LIKE ? OR category LIKE ?) ORDER BY created_at DESC');
	$term = '%' . $search . '%';
	$productsQuery->execute([$term, $term, $term]);
} else {
	$productsQuery = db()->query('SELECT * FROM products WHERE is_active = 1 ORDER BY created_at DESC');
}
$products = $productsQuery->fetchAll();
$pageTitle = 'Preorder kue Lebaran di Medan';
require __DIR__ . '/includes/header.php';
?>
<main>
	<section class="hero">
		<div class="hero-copy">
			<p class="eyebrow">Sanggar Tresno</p>
			<h1>Lebih nikmat,<br><span>lebih hangat</span><br>dengan kue rumah.</h1>
			<p>Nastar, kastengel, dan kue favorit lainnya dibuat segar sesuai preorder. Pembayaran DP 50% sebelum mulai dipanggang.</p>
			<div class="hero-actions"><a class="button button-dark" href="#koleksi">Pilih Kue <span aria-hidden="true">&#8594;</span></a><a class="text-link" href="chat.php">Tanya jadwal</a></div>
		</div>
		<div class="hero-image" role="img" aria-label="Aneka kue Lebaran rumahan"><span class="hero-stamp">Fresh from our oven</span></div>
	</section>
	<div class="ticker"><span>&bull; PREORDER</span> &bull; DIBUAT SETELAH DP 50%<span>&bull; HOMEMADE</span></div>
	<section class="catalog" id="koleksi">
		<div class="section-top">
			<div><p class="eyebrow">DIBUAT BERDASARKAN PESANAN</p><h2>Kue favorit keluarga</h2><p>Pesan lebih awal untuk tanggal kirim pilihanmu.</p></div>
			<form class="search-form" method="get" action="<?= e(app_url('index.php')) ?>"><input type="search" name="q" placeholder="Cari koleksi..." value="<?= e($search) ?>" aria-label="Cari produk"><button type="submit">Cari</button></form>
		</div>
		<div class="product-grid">
			<?php if (!$products): ?><div class="empty-state">Belum ada produk yang cocok. Coba kata pencarian lain.</div><?php endif; ?>
			<?php foreach ($products as $index => $product): ?>
				<article class="product-card" style="animation-delay: <?= (int) $index * 70 ?>ms">
					<div class="product-image"><img src="<?= e(asset_url($product['image_url'] ?: 'assets/product-nastar.png')) ?>" alt="<?= e($product['name']) ?>" loading="lazy"><span class="product-category"><?= e($product['category']) ?></span></div>
					<div class="product-info"><h3><?= e($product['name']) ?></h3><p><?= e($product['description']) ?></p><div class="product-bottom"><span class="product-price"><?= rupiah($product['price']) ?></span><form class="add-form" method="post"><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><?= csrf_field() ?><button type="submit" aria-label="Tambah <?= e($product['name']) ?> ke tas" <?= (int) $product['stock'] < 1 ? 'disabled' : '' ?>>+</button></form></div></div>
				</article>
			<?php endforeach; ?>
		</div>
	</section>
	<section class="story-band"><div class="story-image" role="img" aria-label="Kue kering buatan rumah"></div><div class="story-copy"><p class="eyebrow">PREORDER YANG LEBIH SEGAR</p><h2>Adonan disiapkan setelah DP masuk, bukan disimpan lama di rak.</h2><p>Bayar DP 50% untuk mengunci pesanan. Kami mulai memanggang setelah pembayaran diverifikasi, kemudian kirim setelah pelunasan dan ongkir dikonfirmasi.</p><a class="text-link" href="chat.php">Tanya admin Sanggar Tresno &#8594;</a></div></section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>