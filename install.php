<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$error = '';
$installed = false;

try {
    $pdo = db();
    $usersTable = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'");
    $usersTable->execute();
    if ((int) $usersTable->fetchColumn() > 0) {
        $installed = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() > 0;
    }

    if (!$installed) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin','customer') NOT NULL DEFAULT 'customer',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(180) NOT NULL,
        description TEXT NOT NULL,
        category VARCHAR(100) NOT NULL DEFAULT 'Koleksi',
        price DECIMAL(12,2) NOT NULL,
        stock INT UNSIGNED NOT NULL DEFAULT 0,
        image_url VARCHAR(1000) NOT NULL DEFAULT '',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        recipient_name VARCHAR(120) NOT NULL,
        phone VARCHAR(40) NOT NULL,
        address TEXT NOT NULL,
        delivery_date DATE NULL,
        notes VARCHAR(500) NOT NULL DEFAULT '',
        total DECIMAL(12,2) NOT NULL,
        dp_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        status ENUM('pending','processing','shipped','completed','cancelled') NOT NULL DEFAULT 'pending',
        payment_status ENUM('unpaid','dp_paid','paid') NOT NULL DEFAULT 'unpaid',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id INT UNSIGNED NOT NULL,
        product_id INT UNSIGNED NULL,
        product_name VARCHAR(180) NOT NULL,
        price DECIMAL(12,2) NOT NULL,
        quantity INT UNSIGNED NOT NULL,
        CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        customer_id INT UNSIGNED NOT NULL,
        sender_role ENUM('admin','customer') NOT NULL,
        body TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_messages_thread (customer_id, id),
        CONSTRAINT fk_messages_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    $orderColumns = $pdo->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('delivery_date', $orderColumns, true)) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN delivery_date DATE NULL AFTER address');
    }
    if (!in_array('dp_amount', $orderColumns, true)) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN dp_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total');
        $pdo->exec("UPDATE orders SET dp_amount = ROUND(total * 0.5, 2) WHERE dp_amount = 0");
    }
    $pdo->exec("ALTER TABLE orders MODIFY payment_status ENUM('unpaid','dp_paid','paid') NOT NULL DEFAULT 'unpaid'");

    $productCount = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    if ($productCount === 0) {
        $samples = [
            ['Nastar Nanas Premium', 'Nastar lembut berisi selai nanas rumahan, dibuat segar setelah DP terkonfirmasi.', 'Kue Kering', 145000, 20, 'assets/product-nastar.png'],
            ['Kastengel Keju Edam', 'Kastengel gurih dengan taburan keju melimpah untuk suguhan Lebaran.', 'Kue Kering', 160000, 20, 'assets/product-kastengel.png'],
            ['Putri Salju Mede', 'Kue putri salju renyah dengan kacang mede dan gula halus.', 'Kue Kering', 135000, 20, 'assets/product-putri-salju.png'],
            ['Lidah Kucing Keju', 'Lidah kucing tipis dan renyah, dipanggang dalam batch preorder.', 'Kue Kering', 125000, 20, 'assets/product-lidah-kucing.png'],
        ];
        $insert = $pdo->prepare('INSERT INTO products (name, description, category, price, stock, image_url) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($samples as $sample) {
            $insert->execute($sample);
        }
    }
    $sampleUpdates = [
        ['Nastar Nanas Premium', 'Nastar lembut berisi selai nanas rumahan, dibuat segar setelah DP terkonfirmasi.', 'Kue Kering', 145000, 20, 'assets/product-nastar.png', 'Kain Lurik Pusaka'],
        ['Kastengel Keju Edam', 'Kastengel gurih dengan taburan keju melimpah untuk suguhan Lebaran.', 'Kue Kering', 160000, 20, 'assets/product-kastengel.png', 'Selendang Tari Sekar'],
        ['Putri Salju Mede', 'Kue putri salju renyah dengan kacang mede dan gula halus.', 'Kue Kering', 135000, 20, 'assets/product-putri-salju.png', 'Tas Kain Tresno'],
        ['Lidah Kucing Keju', 'Lidah kucing tipis dan renyah, dipanggang dalam batch preorder.', 'Kue Kering', 125000, 20, 'assets/product-lidah-kucing.png', 'Kipas Panggung'],
    ];
    $updateSample = $pdo->prepare('UPDATE products SET name = ?, description = ?, category = ?, price = ?, stock = ?, image_url = ? WHERE name = ?');
    foreach ($sampleUpdates as $sample) {
        $updateSample->execute($sample);
    }

    $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($adminCount > 0) {
        $installed = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $password = (string) ($_POST['password'] ?? '');
        if ($name === '' || !$email || strlen($password) < 10) {
            throw new RuntimeException('Isi nama dan email yang valid. Kata sandi minimal 10 karakter.');
        }
        $statement = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
        $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        set_flash('success', 'Instalasi selesai. Silakan masuk dengan akun admin Anda.');
        header('Location: login.php');
        exit;
    }
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

$pageTitle = 'Instalasi';
require __DIR__ . '/includes/header.php';
?>
<main class="install-page">
    <section class="install-panel">
        <p class="eyebrow">Sanggar Tresno / SETUP</p>
        <h1><?= $installed ? 'Situs sudah siap.' : 'Mulai dari sini.' ?></h1>
        <?php if ($installed): ?>
            <p>Admin sudah terdaftar. Demi keamanan, halaman instalasi ini tidak dapat digunakan kembali.</p>
            <a class="button button-dark" href="login.php">Masuk ke admin</a>
        <?php else: ?>
            <p>Database sanggar_tresno akan digunakan. Tabel aplikasi preorder akan disiapkan di database yang sama tanpa membuat database baru.</p>
            <?php if ($error !== ''): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?>
            <form method="post" class="form-stack">
                <?= csrf_field() ?>
                <label>Nama admin<input name="name" required maxlength="120" autocomplete="name"></label>
                <label>Email<input type="email" name="email" required maxlength="190" autocomplete="email"></label>
                <label>Kata sandi<input type="password" name="password" required minlength="10" autocomplete="new-password"><small>Minimal 10 karakter.</small></label>
                <button class="button button-dark" type="submit">Buat admin & siapkan toko</button>
            </form>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>