<?php
$viewer = current_user();
$cartCount = array_sum(array_map('intval', $_SESSION['cart'] ?? []));
$currentNav = $currentNav ?? '';
?>
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <a class="brand" href="<?= e(app_url('index.php')) ?>">
                <span class="brand-mark">sr.</span>
                <span>Sanggar <strong>Tresno</strong><small>Resep rumahan, dibuat segar.</small></span>
            </a>
        </div>
        <p class="footer-center-text">Dibuat sesuai pesanan &bull; Medan &bull; Bantuan 08.00–18.00 WIB</p>
        <div class="footer-links">
            <a href="<?= e(app_url('index.php#cara-pesan')) ?>">Syarat pre-order</a>
            <span>/</span>
            <a href="<?= e(app_url('chat.php')) ?>">Hubungi kami</a>
        </div>
    </div>
</footer>

<!-- Bottom Navigation for Mobile (Sesuai Mockup UI/UX Mobile Gambar 3.33, 3.34, 3.35, 3.36, 3.41) -->
<nav class="mobile-bottom-nav" aria-label="Navigasi Bawah Mobile">
    <a href="<?= e(app_url('index.php')) ?>" class="mobile-nav-item <?= $currentNav === 'menu' ? 'is-active' : '' ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        <span>Menu</span>
    </a>
    <a href="<?= e(app_url('cart.php')) ?>" class="mobile-nav-item <?= $currentNav === 'cart' ? 'is-active' : '' ?>">
        <span class="mobile-nav-icon-wrap">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <path d="M16 10a4 4 0 0 1-8 0"></path>
            </svg>
            <?php if ($cartCount > 0): ?>
                <span class="mobile-nav-counter"><?= $cartCount ?></span>
            <?php endif; ?>
        </span>
        <span>Keranjang</span>
    </a>
    <a href="<?= e(app_url('orders.php')) ?>" class="mobile-nav-item <?= $currentNav === 'orders' ? 'is-active' : '' ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
            <line x1="8" y1="21" x2="16" y2="21"></line>
            <line x1="12" y1="17" x2="12" y2="21"></line>
        </svg>
        <span>Pesanan</span>
    </a>
    <a href="<?= e(app_url('chat.php')) ?>" class="mobile-nav-item <?= $currentNav === 'chat' ? 'is-active' : '' ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        </svg>
        <span>Chat</span>
    </a>
    <a href="<?= $viewer ? e(app_url('orders.php')) : e(app_url('login.php')) ?>" class="mobile-nav-item <?= $currentNav === 'account' ? 'is-active' : '' ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
        </svg>
        <span><?= $viewer ? 'Akun' : 'Masuk' ?></span>
    </a>
</nav>

</body>
</html>
