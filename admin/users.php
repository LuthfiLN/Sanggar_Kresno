<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config.php';
require_admin();
$pdo = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '' || strlen($name) > 120 || !$email || strlen($password) < 6) {
        $error = 'Isi nama, email yang valid, dan kata sandi minimal 6 karakter.';
    } else {
        try {
            $insert = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
            $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            set_flash('success', 'Akun admin baru "' . $name . '" berhasil dibuat.');
            header('Location: users.php');
            exit;
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = 'Email tersebut sudah terdaftar.';
            } else {
                throw $exception;
            }
        }
    }
}

$admins = $pdo->query("SELECT id, name, email, created_at FROM users WHERE role = 'admin' ORDER BY created_at ASC")->fetchAll();
$customers = $pdo->query("SELECT id, name, email, created_at, (SELECT COUNT(*) FROM orders WHERE user_id = users.id) AS order_count FROM users WHERE role = 'customer' ORDER BY created_at DESC LIMIT 20")->fetchAll();

$pageTitle = 'Kelola Pengguna';
$adminActive = 'admins';
require dirname(__DIR__) . '/app/Views/Admin/header.php';
?>
<main class="admin-page-wrap">
    <div class="panel-header-row kelola-orders-header">
        <div>
            <p class="eyebrow">RUANG ADMIN / PENGGUNA</p>
            <h1 class="page-main-title">Akun admin &amp; pelanggan</h1>
            <p class="page-subtitle">Kelola akses pengelola toko dan pantau pelanggan terdaftar.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="notice notice-error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="products-management-grid">
        <div class="products-form-col">
            <section class="panel admin-panel-box">
                <h2 class="section-clean-title">Tambah admin baru</h2>
                <small class="panel-sub">Berikan hak akses administrator untuk mengelola pesanan.</small>

                <form class="form-stack admin-product-form" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group-clean">
                        <label for="admin-name">Nama lengkap</label>
                        <input id="admin-name" type="text" name="name" required maxlength="120"
                            placeholder="Misal: Luthfi Nurrahman">
                    </div>
                    <div class="form-group-clean">
                        <label for="admin-email">Email</label>
                        <input id="admin-email" type="email" name="email" required maxlength="190"
                            placeholder="admin@sanggarkresno.com">
                    </div>
                    <div class="form-group-clean">
                        <label for="admin-pass">Kata sandi awal</label>
                        <input id="admin-pass" type="password" name="password" required minlength="6"
                            placeholder="Minimal 6 karakter">
                    </div>
                    <button class="button button-solid-black button-full" type="submit">Buat akun admin</button>
                </form>
            </section>
        </div>

        <div class="products-table-col">
            <section class="panel admin-panel-box">
                <h2 class="section-clean-title">Daftar administrator</h2>
                <div class="table-wrap-clean">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Terdaftar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $a): ?>
                                <tr>
                                    <td data-label="Nama"><strong><?= e($a['name']) ?></strong></td>
                                    <td data-label="Email"><?= e($a['email']) ?></td>
                                    <td data-label="Terdaftar"><?= indo_date($a['created_at'], false) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel admin-panel-box" style="margin-top: 24px;">
                <h2 class="section-clean-title">Pelanggan terdaftar terbaru</h2>
                <div class="table-wrap-clean">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Pesanan</th>
                                <th>Terdaftar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$customers): ?>
                                <tr>
                                    <td colspan="4" class="empty-table-cell">Belum ada pelanggan terdaftar.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($customers as $c): ?>
                                <tr>
                                    <td data-label="Nama"><strong><?= e($c['name']) ?></strong></td>
                                    <td data-label="Email"><?= e($c['email']) ?></td>
                                    <td data-label="Pesanan"><?= (int) $c['order_count'] ?> pesanan</td>
                                    <td data-label="Terdaftar"><?= indo_date($c['created_at'], false) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</main>
<?php require dirname(__DIR__) . '/app/Views/Admin/footer.php'; ?>