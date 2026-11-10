<?php
$adminPageTitle = $pageTitle ?? 'Ruang admin';
$adminActive = $adminActive ?? '';
$adminViewer = require_admin();

$adminNavigation = [
    'overview' => ['Ringkasan', 'admin/index.php', 'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'],
    'products' => ['Produk', 'admin/products.php', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
    'orders' => ['Pesanan', 'admin/kelola_orders.php', 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2'],
    'chat' => ['Negosiasi', 'chat.php', 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z'],
    'reports' => ['Laporan', 'admin/reports.php', 'M18 20V10M12 20V4M6 20v-6'],
    'admins' => ['Akun admin', 'admin/users.php', 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2 M9 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
];
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="theme-color" content="#f7f5ef">
    <title><?= e($adminPageTitle) ?> | Admin Sanggar Tresno</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('assets/style.css')) ?>">
</head>

<body class="admin-body">
    <div class="admin-layout">
        <!-- Sidebar Admin Sesuai Gambar 3.27, 3.28, 3.29, 3.30 -->
        <aside class="admin-sidebar">
            <div class="admin-sidebar-top">
                <a class="brand admin-brand" href="<?= e(app_url('admin/index.php')) ?>">
                    <span class="brand-mark">sr.</span>
                    <span class="brand-text">
                        Sanggar <strong>Tresno</strong>
                        <small>RUANG ADMIN</small>
                    </span>
                </a>
            </div>

            <p class="admin-nav-label">MANAJEMEN TOKO</p>
            <nav class="admin-nav" aria-label="Navigasi admin">
                <?php foreach ($adminNavigation as $key => [$label, $path, $iconSvg]): ?>
                    <a class="admin-nav-link <?= $adminActive === $key ? 'is-active' : '' ?>" href="<?= e(app_url($path)) ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="<?= $iconSvg ?>"></path>
                        </svg>
                        <span><?= e($label) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="admin-sidebar-bottom">
                <a class="admin-store-link" href="<?= e(app_url('index.php')) ?>">
                    <span>Lihat toko</span>
                    <span aria-hidden="true">&#8599;</span>
                </a>
                <div class="admin-account-card">
                    <span class="admin-avatar"><?= e(strtoupper(substr($adminViewer['name'], 0, 1))) ?></span>
                    <div class="admin-account-info">
                        <strong><?= e($adminViewer['name']) ?></strong>
                        <small>Administrator</small>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Area Kerja Admin -->
        <div class="admin-workspace">
            <header class="admin-topbar">
                <div class="admin-topbar-breadcrumb">
                    <span>Ruang admin</span>
                    <span class="sep">/</span>
                    <strong class="page-name"><?= e($adminPageTitle) ?></strong>
                </div>

                <div class="admin-topbar-actions">
                    <form method="post" action="<?= e(app_url('logout.php')) ?>" class="admin-logout-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="button button-outline-dark button-small" title="Keluar dari akun admin">
                            Keluar
                        </button>
                    </form>
                </div>
            </header>

            <?php if ($flashMessage = take_flash()): ?>
                <div class="flash-wrap">
                    <div class="notice notice-<?= e($flashMessage['type']) ?>"><?= e($flashMessage['message']) ?></div>
                </div>
            <?php endif; ?>

            <div class="admin-content-inner">
