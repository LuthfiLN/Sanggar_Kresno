<?php

declare(strict_types=1);

// Load env
require __DIR__ . '/../Config/app.php';
require __DIR__ . '/../Config/database.php';

load_env(app_base_path('.env'));

// Session
if (session_status() !== PHP_SESSION_ACTIVE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $isSecure,
    ]);
    @session_start();
}

// Core modules
require __DIR__ . '/../Database/Schema.php';
require __DIR__ . '/../Database/Connection.php';
require __DIR__ . '/../Support/helpers.php';
require __DIR__ . '/../Support/Auth.php';
require __DIR__ . '/../Support/Encryption.php';
require __DIR__ . '/../Repositories/ProductRepository.php';
require __DIR__ . '/../Repositories/UserRepository.php';
require __DIR__ . '/../Repositories/OrderRepository.php';
