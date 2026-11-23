<?php

declare(strict_types=1);

if (!function_exists('init_sqlite_schema')) {
    function init_sqlite_schema(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                phone TEXT NOT NULL DEFAULT '',
                password TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'customer',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT NOT NULL,
                category TEXT NOT NULL DEFAULT 'Makanan',
                price NUMERIC NOT NULL,
                stock INTEGER NOT NULL DEFAULT 20,
                image_url TEXT NOT NULL DEFAULT '',
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                recipient_name TEXT NOT NULL,
                phone TEXT NOT NULL,
                address TEXT NOT NULL,
                delivery_date TEXT NULL,
                notes TEXT NOT NULL DEFAULT '',
                total NUMERIC NOT NULL,
                dp_amount NUMERIC NOT NULL DEFAULT 0,
                shipping_fee NUMERIC NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'pending',
                payment_status TEXT NOT NULL DEFAULT 'unpaid',
                payment_method TEXT NOT NULL DEFAULT 'QRIS',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                product_id INTEGER NULL,
                product_name TEXT NOT NULL,
                price NUMERIC NOT NULL,
                quantity INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NULL,
                customer_id INTEGER NOT NULL,
                sender_role TEXT NOT NULL,
                body TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $pdo->exec("
            CREATE VIEW IF NOT EXISTS admin AS
            SELECT id AS id_admin, name AS nama_admin, email AS username, password FROM users WHERE role = 'admin';

            CREATE VIEW IF NOT EXISTS pelanggan AS
            SELECT id AS id_pelanggan, name AS nama_pelanggan, phone AS no_hp, password FROM users WHERE role = 'customer';

            CREATE VIEW IF NOT EXISTS produk AS
            SELECT id AS id_produk, name AS nama_produk, price AS harga, description AS deskripsi FROM products;

            CREATE VIEW IF NOT EXISTS transaksi AS
            SELECT id AS id_transaksi, user_id AS id_pelanggan, NULL AS id_admin, created_at AS tanggal_pesanan, shipping_fee AS ongkir, total AS total_harga, dp_amount AS jumlah_dp, payment_status AS status_dp, status AS status_pesanan, payment_method AS metode_pembayaran FROM orders;

            CREATE VIEW IF NOT EXISTS detail_transaksi AS
            SELECT id AS id_detail, order_id AS id_transaksi, product_id AS id_produk, quantity AS jumlah_pesanan, (price * quantity) AS subtotal FROM order_items;

            CREATE VIEW IF NOT EXISTS chat AS
            SELECT id AS id_chat, customer_id AS id_pelanggan, NULL AS id_admin, sender_role AS pengirim, body AS isi_pesan, created_at AS waktu_kirim FROM messages;
        ");
    }
}
