<?php
$adminPageTitle = $pageTitle ?? 'Ruang admin';
$adminActive = $adminActive ?? '';
$adminViewer = require_admin();
$adminNavigation = [
    'overview' => ['Ringkasan', 'admin/index.php'],
    'products' => ['Produk', 'admin/products.php'],
    'orders' => ['Pesanan', 'admin/kelola_orders.php'],
    'reports' => ['Laporan', 'admin/reports.php'],
    'chat' => ['Negosiasi', 'chat.php'],
    'admins' => ['Akun admin', 'admin/users.php'],
];
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f5ef">
    <title><?= e($adminPageTitle) ?> | Admin Sanggar Tresno</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= e(app_url('assets/style.css')) ?>">
</head>

<body class="admin-body">
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <a class="brand admin-brand" href="<?= e(app_url('admin/index.php')) ?>"><span
                    class="brand-mark">ST</span><span>Sanggar <strong>Tresno</strong><small>RUANG
                        ADMIN</small></span></a>
            <p class="admin-nav-label">MANAJEMEN TOKO</p>
            <nav class="admin-nav" aria-label="Navigasi admin">
                <?php foreach ($adminNavigation as $key => [$label, $path]): ?>
                <a class="<?= $adminActive === $key ? 'is-active' : '' ?>"
                    href="<?= e(app_url($path)) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="admin-sidebar-bottom">
                <a class="admin-store-link" href="<?= e(app_url('index.php')) ?>">Lihat toko <span
                        aria-hidden="true">&#8599;</span></a>
                <div class="admin-account"><span
                        class="admin-avatar"><?= e(strtoupper(substr($adminViewer['name'], 0, 1))) ?></span><span><strong><?= e($adminViewer['name']) ?></strong><small>Administrator</small></span>
                </div>
            </div>
        </aside>
        <div class="admin-workspace">
            <header class="admin-topbar">
                <span>Ruang admin <span aria-hidden="true">/</span> <?= e($adminPageTitle) ?></span>
                <form method="post" action="<?= e(app_url('logout.php')) ?>"><?= csrf_field() ?><button type="submit"
                        title="Keluar dari akun admin">Keluar</button></form>
            </header>
            <?php if ($flashMessage = take_flash()): ?>
            <div class="flash-wrap">
                <div class="notice notice-<?= e($flashMessage['type']) ?>"><?= e($flashMessage['message']) ?></div>
            </div>
            <?php endif; ?>
            <div class="admin-content">