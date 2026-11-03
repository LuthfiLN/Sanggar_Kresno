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

$pageTitle = 'Masuk';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell">
    <div class="auth-layout">
        <div class="auth-art">
            <p class="eyebrow">DAPUR TRESNO / MEDAN</p>
            <h2>Manisnya Lebaran dimulai dari rumah.</h2>
            <p>Masuk untuk mengikuti preorder atau mengelola toko.</p>
        </div>
        <section class="auth-form">
            <p class="eyebrow">AKUN SANGGAR TRESNO</p>
            <h1>Masuk</h1>
            <?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?>
            <form class="form-stack" method="post">
                <?= csrf_field() ?>
                <label>Email<input type="email" name="email" required autocomplete="username"></label>
                <label>Kata sandi<input type="password" name="password" required
                        autocomplete="current-password"></label>
                <button class="button button-dark" type="submit">Masuk</button>
            </form>
            <p class="auth-switch">Belum punya akun pelanggan? <a href="register.php">Daftar di sini</a></p>
        </section>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>