<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function load_env(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($value !== '' && !array_key_exists($key, $_ENV) && !array_key_exists($key, $_SERVER)) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

load_env(__DIR__ . '/.env');

const DB_HOST = '127.0.0.1';
const DB_NAME = 'sanggar_tresno';
const DB_USER = 'root';
const DB_PASS = '';

function app_url(string $path = ''): string
{
    $scriptDirectory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
    if (basename($scriptDirectory) === 'admin') {
        $scriptDirectory = dirname($scriptDirectory);
    }
    $base = rtrim($scriptDirectory, '/');
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function asset_url(string $path): string
{
    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }
    return app_url($path);
}

function db(): PDO
{
    static $connection;
    if (!$connection) {
        $host = getenv('DB_HOST') ?: DB_HOST;
        $name = getenv('DB_NAME') ?: DB_NAME;
        $user = getenv('DB_USER') ?: DB_USER;
        $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : DB_PASS;
        $connection = new PDO(
            'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4',
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
    return $connection;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit('Sesi formulir kedaluwarsa. Muat ulang halaman, lalu coba lagi.');
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $query = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
    $query->execute([$_SESSION['user_id']]);
    $user = $query->fetch();
    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: ' . app_url('login.php'));
        exit;
    }
    return $user;
}

function require_admin(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: ' . app_url('login.php?role=admin'));
        exit;
    }
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('Halaman ini hanya untuk admin.');
    }
    return $user;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function payment_status_label(string $status): string
{
    return [
        'unpaid' => 'DP 50% belum dibayar',
        'dp_paid' => 'DP 50% terverifikasi',
        'paid' => 'Lunas 100%',
    ][$status] ?? ucfirst($status);
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function rupiah(int|float|string $amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

function order_status_label(string $status): string
{
    return [
        'pending' => 'Menunggu DP',
        'processing' => 'Sedang dibuat',
        'shipped' => 'Dalam pengiriman',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ][$status] ?? ucfirst($status);
}

function order_code(int|array $order): string
{
    if (is_int($order)) {
        return sprintf('SR-%s-%03d', date('ymd'), $order);
    }
    $createdAt = !empty($order['created_at']) ? strtotime($order['created_at']) : time();
    $id = (int) ($order['id'] ?? 0);
    return sprintf('SR-%s-%03d', date('ymd', $createdAt), $id);
}

function min_preorder_datetime(): DateTimeImmutable
{
    return new DateTimeImmutable('today + 3 days');
}

function min_preorder_date(): string
{
    return min_preorder_datetime()->format('Y-m-d');
}

function greeting_time(): string
{
    $hour = (int) date('H');
    if ($hour >= 4 && $hour < 11) {
        return 'Selamat pagi';
    }
    if ($hour >= 11 && $hour < 15) {
        return 'Selamat siang';
    }
    if ($hour >= 15 && $hour < 18) {
        return 'Selamat sore';
    }
    return 'Selamat malam';
}

function indo_date(string $dateStr, bool $withDay = true): string
{
    if (!$dateStr) {
        return '-';
    }
    $timestamp = strtotime($dateStr);
    if (!$timestamp) {
        return $dateStr;
    }
    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $months = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des'
    ];
    $dayName = $days[(int) date('w', $timestamp)];
    $dayNum = date('d', $timestamp);
    $monthName = $months[(int) date('n', $timestamp)];
    $year = date('Y', $timestamp);

    return $withDay ? "{$dayName}, {$dayNum} {$monthName} {$year}" : "{$dayNum} {$monthName} {$year}";
}