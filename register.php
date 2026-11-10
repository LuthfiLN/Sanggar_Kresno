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

    if ($name === '' || !$email || strlen($password) < 6) {
        $error = 'Lengkapi nama, email yang valid, dan kata sandi minimal 6 karakter.';
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
                $error = 'Email ini sudah digunakan. Silakan gunakan email lain atau langsung masuk.';
            } else {
                throw $exception;
            }
        }
    }
}

$pageTitle = 'Daftar Akun';
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="theme-color" content="#e5e5e5">
    <title>Daftar Akun | Sanggar Tresno</title>
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

            <h1 class="auth-card-title">Daftar Akun Baru</h1>

            <?php if ($error): ?>
                <div class="notice notice-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form class="auth-clean-form" method="post" action="<?= e(app_url('register.php')) ?>">
                <?= csrf_field() ?>
                
                <div class="form-group-clean">
                    <label for="reg-name">Nama lengkap / Username</label>
                    <input id="reg-name" type="text" name="name" placeholder="Masukkan nama lengkap" required autocomplete="name" value="<?= e($_POST['name'] ?? '') ?>">
                </div>

                <div class="form-group-clean">
                    <label for="reg-email">Email</label>
                    <input id="reg-email" type="email" name="email" placeholder="nama@email.com" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group-clean">
                    <label for="reg-password">Password</label>
                    <input id="reg-password" type="password" name="password" placeholder="Minimal 6 karakter" required minlength="6" autocomplete="new-password">
                </div>

                <button class="button button-solid-black button-full" type="submit">Daftar Sekarang</button>
                <a class="button button-outline-dark button-full" href="<?= e(app_url('login.php')) ?>">Sudah punya akun? Masuk</a>
            </form>

            <div class="auth-card-footer">
                <a href="<?= e(app_url('index.php')) ?>" class="text-link-subtle">&larr; Kembali ke beranda toko</a>
            </div>
        </div>
    </div>
</body>
</html>
