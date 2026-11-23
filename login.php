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
    $loginInput = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($loginInput === '' || $password === '') {
        $error = 'Masukkan username atau email serta kata sandi.';
    } else {
        $account = find_user_for_login($loginInput);

        if ($account && password_verify($password, $account['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $account['id'];
            header('Location: ' . app_url($account['role'] === 'admin' ? 'admin/index.php' : 'index.php'));
            exit;
        }
        $error = 'Username / email atau kata sandi salah.';
    }
}

$pageTitle = 'Masuk';
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="theme-color" content="#e5e5e5">
    <title>Masuk | Sanggar Tresno</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('assets/style.css')) ?>">
</head>

<body class="auth-body">
    <div class="auth-screen-container">
        <div class="auth-card-clean">
            <div class="auth-card-header">
                <a class="brand auth-brand" href="<?= e(app_url('index.php')) ?>">
                    <span class="brand-mark">sr.</span>
                    <span class="brand-text">
                        Sanggar <strong>Tresno</strong>
                        <small>Aneka Kue Rumahan &bull; PRE-ORDER</small>
                    </span>
                </a>
            </div>

            <h1 class="auth-card-title">Selamat datang</h1>

            <?php if ($error): ?>
                <div class="notice notice-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form class="auth-clean-form" method="post" action="<?= e(app_url('login.php')) ?>">
                <?= csrf_field() ?>
                
                <div class="form-group-clean">
                    <label for="username">Username</label>
                    <input id="username" type="text" name="username" placeholder="Masukkan username" required autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>">
                </div>

                <div class="form-group-clean">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" placeholder="Masukkan password" required autocomplete="current-password">
                </div>

                <button class="button button-solid-black button-full" type="submit">Masuk</button>
                <a class="button button-outline-dark button-full" href="<?= e(app_url('register.php')) ?>">Daftar</a>
            </form>

            <div class="auth-card-footer">
                <a href="<?= e(app_url('index.php')) ?>" class="text-link-subtle">&larr; Kembali ke beranda toko</a>
            </div>
        </div>
    </div>
</body>
</html>
