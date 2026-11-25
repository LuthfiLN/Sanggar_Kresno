<?php
require_once dirname(__DIR__, 2) . '/config.php';

$pageTitle = $pageTitle ?? 'Aneka kue rumahan';
$currentNav = $currentNav ?? '';
$viewer = current_user();
$cartCount = array_sum(array_map('intval', $_SESSION['cart'] ?? []));
$flashMessage = take_flash();
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <meta name="theme-color" content="#f7f5ef">
    <meta name="description"
        content="Sistem Penjualan Pre-Order & Manajemen Toko Sanggar Kresno Medan. Aneka kue rumahan dibuat segar setelah DP dikonfirmasi.">
    <title><?= e($pageTitle) ?> | Sanggar Kresno</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('assets/style.css')) ?>">
</head>

<body>
    <header class="site-header">
        <div class="header-left">
            <a class="brand" href="<?= e(app_url('index.php')) ?>">
                <img class="brand-logo" src="<?= e(asset_url('assets/Logo.png')) ?>" alt="Logo Sanggar Kresno">
                <span class="brand-text">
                    Sanggar <strong>Kresno</strong>
                    <small>Aneka Kue Rumahan &bull; PRE-ORDER</small>
                </span>
            </a>
        </div>

        <nav class="main-nav" aria-label="Navigasi utama">
            <?php if ($viewer && $viewer['role'] === 'admin'): ?>
            <a href="<?= e(app_url('index.php')) ?>" class="<?= $currentNav === 'store' ? 'is-active' : '' ?>">Lihat
                Toko</a>
            <a href="<?= e(app_url('admin/index.php')) ?>" class="admin-badge-link">&#9881; Dashboard Admin</a>
            <?php else: ?>
            <a href="<?= e(app_url('index.php#koleksi')) ?>"
                class="<?= $currentNav === 'menu' ? 'is-active' : '' ?>">Catalog</a>
            <a href="<?= e(app_url('index.php#cara-pesan')) ?>"
                class="<?= $currentNav === 'how' ? 'is-active' : '' ?>">Cara pesan</a>
            <a href="<?= e(app_url('orders.php')) ?>" class="<?= $currentNav === 'orders' ? 'is-active' : '' ?>">Pesanan
                saya</a>
            <a href="<?= e(app_url('chat.php')) ?>" class="<?= $currentNav === 'chat' ? 'is-active' : '' ?>">Chat
                Admin</a>
            <?php endif; ?>
        </nav>

        <div class="header-right">
            <div class="location-badge">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                <span>Medan, Sumatera Utara</span>
            </div>

            <a class="cart-link" href="<?= e(app_url('cart.php')) ?>" aria-label="Keranjang, <?= $cartCount ?> barang">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
                <span>Cart(<?= $cartCount ?>)</span>
            </a>

            <?php if ($viewer): ?>
            <div class="user-profile-menu">
                <span class="user-name"
                    title="<?= e($viewer['email']) ?>"><?= e(explode(' ', $viewer['name'])[0]) ?></span>
                <form method="post" action="<?= e(app_url('logout.php')) ?>" class="nav-logout inline-logout">
                    <?= csrf_field() ?>
                    <button type="submit" class="logout-btn" title="Keluar">Keluar</button>
                </form>
            </div>
            <?php else: ?>
            <a class="button button-dark button-small" href="<?= e(app_url('login.php')) ?>">Masuk</a>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($flashMessage): ?>
    <div class="flash-wrap">
        <div class="notice notice-<?= e($flashMessage['type']) ?>"><?= e($flashMessage['message']) ?></div>
    </div>
    <?php endif; ?>