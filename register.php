<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
$activeUser = current_user();
if ($activeUser) {
    header('Location: ' . app_url($activeUser['role'] === 'admin' ? 'admin/index.php' : 'index.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string) ($_POST['password'] ?? '');
    if ($name === '' || !$email || strlen($password) < 10) {
        $error = 'Lengkapi nama dan email. Kata sandi minimal 10 karakter.';
    } else {
        try {
            $query = db()->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'customer')");
            $query->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) db()->lastInsertId();
            set_flash('success', 'Akun berhasil dibuat. Selamat datang di Sanggar Tresno.');
            header('Location: ' . app_url('index.php'));
            exit;
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = 'Email ini sudah digunakan. Silakan masuk atau gunakan email lain.';
            } else {
                throw $exception;
            }
        }
    }
}

$pageTitle = 'Daftar pelanggan';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell">
    <div class="auth-layout">
        <div class="auth-art">
            <p class="eyebrow">SANGGAR TRESNO</p>
            <h2>Pesan kue segar untuk hari istimewa.</h2>
            <p>Buat akun untuk memilih tanggal preorder dan memantau pembayaran DP.</p>
        </div>
        <section class="auth-form">
            <p class="eyebrow">DAFTAR PELANGGAN</p>
            <h1>Buat akun</h1>
            <p>Pesanan dan percakapanmu tersimpan rapi di satu tempat.</p>
            <?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?>
            <form class="form-stack" method="post">
                <?= csrf_field() ?>
                <label>Nama lengkap<input name="name" required maxlength="120" autocomplete="name"
                        value="<?= e($_POST['name'] ?? '') ?>"></label>
                <label>Email<input type="email" name="email" required maxlength="190" autocomplete="email"
                        value="<?= e($_POST['email'] ?? '') ?>"></label>
                <label>Kata sandi<input type="password" name="password" required minlength="10"
                        autocomplete="new-password"><small>Gunakan minimal 10 karakter.</small></label>
                <button class="button button-dark" type="submit">Buat akun</button>
            </form>
            <p class="auth-switch">Sudah punya akun? <a href="login.php">Masuk</a></p>
        </section>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>