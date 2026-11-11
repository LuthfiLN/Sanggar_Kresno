            </div>
            </div>
            </div>

            <nav class="admin-mobile-nav" aria-label="Navigasi Cepat Admin">
                <a href="<?= e(app_url('admin/index.php')) ?>"
                    class="<?= ($adminActive ?? '') === 'overview' ? 'is-active' : '' ?>">
                    <small>Ringkasan</small>
                </a>
                <a href="<?= e(app_url('admin/products.php')) ?>"
                    class="<?= ($adminActive ?? '') === 'products' ? 'is-active' : '' ?>">
                    <small>Produk</small>
                </a>
                <a href="<?= e(app_url('admin/kelola_orders.php')) ?>"
                    class="<?= ($adminActive ?? '') === 'orders' ? 'is-active' : '' ?>">
                    <small>Pesanan</small>
                </a>
                <a href="<?= e(app_url('chat.php')) ?>"
                    class="<?= ($adminActive ?? '') === 'chat' ? 'is-active' : '' ?>">
                    <small>Chat</small>
                </a>
                <a href="<?= e(app_url('admin/reports.php')) ?>"
                    class="<?= ($adminActive ?? '') === 'reports' ? 'is-active' : '' ?>">
                    <small>Laporan</small>
                </a>
                <a href="<?= e(app_url('index.php')) ?>"
                    class="<?= ($adminActive ?? '') === 'store' ? 'is-active' : '' ?>">
                    <small>Lihat Toko</small>
                </a>
                <form method="post" action="<?= e(app_url('logout.php')) ?>" style="margin:0;">
                    <?= csrf_field() ?>
                    <button type="submit" class="admin-mobile-nav-item" style="display:flex;flex-direction:column;align-items:center;gap:4px;padding:4px 0;background:none;border:none;cursor:pointer;color:inherit;font:inherit;">
                        <small style="color:inherit;">Keluar</small>
                    </button>
                </form>
            </nav>
            </body>

            </html>