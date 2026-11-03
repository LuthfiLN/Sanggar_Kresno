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
    if ($name === '' || strlen($name) > 120 || !$email || strlen($password) < 10) {
        $error = 'Isi nama, email yang valid, dan kata sandi minimal 10 karakter.';
    } else {
        try {
            $insert = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
            $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            set_flash('success', 'Akun admin baru berhasil dibuat.');
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
$pageTitle = 'Akun admin';
$adminActive = 'admins';
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<main class="page-shell">
    <div class="page-heading">
        <p class="eyebrow">PENGELOLA AKSES</p>
        <h1>Akun admin</h1>
        <p>Admin yang sudah masuk dapat membuat akun admin tambahan. Pendaftaran publik tetap hanya membuat akun
            pelanggan.</p>
    </div>
    <?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?>
    <div class="admin-columns">
        <section class="admin-section">
            <h2>Tambah admin</h2>
            <form class="panel form-stack" method="post">
                <?= csrf_field() ?>
                <label>Nama admin<input name="name" required maxlength="120" autocomplete="name"></label>
                <label>Email admin<input type="email" name="email" required maxlength="190"
                        autocomplete="email"></label>
                <label>Kata sandi sementara<input type="password" name="password" required minlength="10"
                        autocomplete="new-password"><small>Minimal 10 karakter. Sampaikan kata sandi awal secara
                        pribadi.</small></label>
                <button class="button button-dark" type="submit">Buat akun admin</button>
            </form>
        </section>
        <section class="admin-section">
            <h2>Admin terdaftar</h2>
            <div class="panel table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Terdaftar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                        <tr>
                            <td><?= e($admin['name']) ?></td>
                            <td><?= e($admin['email']) ?></td>
                            <td><?= e(date('d M Y', strtotime($admin['created_at']))) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>