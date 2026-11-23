<?php

declare(strict_types=1);

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        static $cachedUser = null;
        static $cachedId = null;

        if (empty($_SESSION['user_id'])) {
            $cachedUser = null;
            $cachedId = null;
            return null;
        }

        $id = (int) $_SESSION['user_id'];
        if ($cachedUser !== null && $cachedId === $id) {
            return $cachedUser;
        }

        $query = db()->prepare('SELECT * FROM users WHERE id = ?');
        $query->execute([$id]);
        $user = $query->fetch();
        if (!$user) {
            unset($_SESSION['user_id']);
            $cachedUser = null;
            $cachedId = null;
            return null;
        }

        $normalized = [
            'id'    => (int) ($user['id'] ?? 0),
            'name'  => (string) ($user['name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'phone' => (string) ($user['phone'] ?? ''),
            'role'  => (string) ($user['role'] ?? 'customer'),
        ];

        $cachedId = $id;
        $cachedUser = $normalized;
        return $normalized;
    }
}

if (!function_exists('require_login')) {
    function require_login(): array
    {
        $user = current_user();
        if (!$user) {
            header('Location: ' . app_url('login.php'));
            exit;
        }
        return $user;
    }
}

if (!function_exists('require_admin')) {
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
}
