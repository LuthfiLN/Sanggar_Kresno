<?php

declare(strict_types=1);

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        if (is_vercel()) {
            $base = '';
        } else {
            $scriptDirectory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
            if (basename($scriptDirectory) === 'admin' || basename($scriptDirectory) === 'api') {
                $scriptDirectory = dirname($scriptDirectory);
            }
            $base = rtrim($scriptDirectory, '/');
            if ($base === '/' || $base === '\\') {
                $base = '';
            }
        }
        return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('asset_url')) {
    function asset_url(string $path): string
    {
        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }
        return app_url($path);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(): void
    {
        $submitted = $_POST['csrf_token'] ?? '';
        if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
            http_response_code(419);
            exit('Sesi formulir kedaluwarsa. Muat ulang halaman, lalu coba lagi.');
        }
    }
}

if (!function_exists('set_flash')) {
    function set_flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('take_flash')) {
    function take_flash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }
}

if (!function_exists('payment_status_label')) {
    function payment_status_label(string $status): string
    {
        return [
            'unpaid' => 'DP 50% belum dibayar',
            'dp_paid' => 'DP 50% terverifikasi',
            'paid' => 'Lunas 100%',
        ][$status] ?? ucfirst($status);
    }
}

if (!function_exists('rupiah')) {
    function rupiah(int|float|string $amount): string
    {
        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }
}

if (!function_exists('order_status_label')) {
    function order_status_label(string $status): string
    {
        return [
            'pending' => 'Menunggu Pembayaran',
            'processing' => 'Diproduksi',
            'shipped' => 'Dikirim',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
        ][$status] ?? ucfirst($status);
    }
}

if (!function_exists('order_code')) {
    function order_code(int|array $order): string
    {
        if (is_int($order)) {
            return sprintf('SR-%s-%03d', date('ymd'), $order);
        }
        $createdAt = !empty($order['created_at']) ? strtotime($order['created_at']) : time();
        $id = (int) ($order['id'] ?? 0);
        return sprintf('SR-%s-%03d', date('ymd', $createdAt), $id);
    }
}

if (!function_exists('min_preorder_datetime')) {
    function min_preorder_datetime(): DateTimeImmutable
    {
        return new DateTimeImmutable('today + 3 days');
    }
}

if (!function_exists('min_preorder_date')) {
    function min_preorder_date(): string
    {
        return min_preorder_datetime()->format('Y-m-d');
    }
}

if (!function_exists('greeting_time')) {
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
}

if (!function_exists('indo_date')) {
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
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
        ];
        $dayName = $days[(int) date('w', $timestamp)];
        $dayNum = date('d', $timestamp);
        $monthName = $months[(int) date('n', $timestamp)];
        $year = date('Y', $timestamp);

        return $withDay ? "{$dayName}, {$dayNum} {$monthName} {$year}" : "{$dayNum} {$monthName} {$year}";
    }
}
