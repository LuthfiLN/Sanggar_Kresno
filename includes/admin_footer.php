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
            </nav>
            </body>

            </html>