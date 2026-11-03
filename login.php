<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
if (current_user()) {
    header('Location: ' . app_url('index.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string) ($_POST['password'] ?? '');
    $query = db()->prepare('SELECT id, password, role FROM users WHERE email = ?');
    $query->execute([$email ?: '']);
    $account = $query->fetch();
    if ($account && password_verify($password, $account['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $account['id'];
        header('Location: ' . app_url($account['role'] === 'admin' ? 'admin/index.php' : 'index.php'));
        exit;
    }
    $error = 'Email atau kata sandi belum cocok.';
}

$pageTitle = 'Masuk pelanggan';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell">
    <div class="auth-layout">
        <div class="auth-art">
            <p class="eyebrow">SANGGAR TRESNO</p>
            <h2>Manisnya Lebaran dimulai dari rumah.</h2>
            <p>Masuk untuk mengikuti preorder kue dan jadwal kirim.</p>
        </div>
        <section class="auth-form">
            <p class="eyebrow">SELAMAT DATANG KEMBALI</p>
            <h1>Masuk</h1>
            <p>Gunakan email yang terdaftar untuk melanjutkan.</p>
            <?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?>
            <form class="form-stack" method="post">
                <?= csrf_field() ?>
                <label>Email<input type="email" name="email" required autocomplete="email"></label>
                <label>Kata sandi<input type="password" name="password" required autocomplete="current-password"></label>
                <button class="button button-dark" type="submit">Masuk ke akun</button>
            </form>
            <p class="auth-switch">Belum punya akun? <a href="register.php">Daftar di sini</a></p>
        </section>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>