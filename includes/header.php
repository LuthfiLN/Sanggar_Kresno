<?php
require_once dirname(__DIR__) . '/config.php';

$pageTitle = $pageTitle ?? 'Aneka kue rumahan';
$viewer = current_user();
$cartCount = array_sum(array_map('intval', $_SESSION['cart'] ?? []));
$flashMessage = take_flash();
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f5ef">
    <meta name="description" content="Preorder kue Lebaran rumahan di Medan. Dibuat setelah DP 50% terkonfirmasi.">
    <title><?= e($pageTitle) ?> | Sanggar Tresno</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="/Sanggar_Tresno/assets/style.css">
</head>

<body>
    <header class="site-header">
        <a class="brand" href="<?= e(app_url('index.php')) ?>"><span class="brand-mark">ST</span><span>Sanggar
                <strong>Tresno</strong><small>Aneka Kue Rumahan</small></span></a>
        <nav class="main-nav" aria-label="Navigasi utama">
            <?php if ($viewer && $viewer['role'] === 'admin'): ?>
            <a href="<?= e(app_url('index.php')) ?>">Lihat toko</a>
            <a href="<?= e(app_url('admin/index.php')) ?>">Dashboard admin</a>
            <form method="post" action="<?= e(app_url('logout.php')) ?>" class="nav-logout"><?= csrf_field() ?><button
                    type="submit">Keluar</button></form>
            <?php else: ?>
            <a href="<?= e(app_url('index.php#koleksi')) ?>">Aneka Kue</a>
            <?php if ($viewer): ?>
            <a href="<?= e(app_url('orders.php')) ?>">Pesanan saya</a>
            <a href="<?= e(app_url('chat.php')) ?>">Chat</a>
            <form method="post" action="<?= e(app_url('logout.php')) ?>" class="nav-logout"><?= csrf_field() ?><button
                    type="submit">Keluar</button></form>
            <?php else: ?>
            <a href="<?= e(app_url('login.php')) ?>">Masuk</a>
            <?php endif; ?>
            <a class="cart-link" href="<?= e(app_url('cart.php')) ?>"
                aria-label="Keranjang, <?= $cartCount ?> barang">Keranjang <span><?= $cartCount ?></span></a>
            <?php endif; ?>
        </nav>
    </header>
    <?php if ($flashMessage): ?>
    <div class="flash-wrap">
        <div class="notice notice-<?= e($flashMessage['type']) ?>"><?= e($flashMessage['message']) ?></div>
    </div>
    <?php endif; ?>