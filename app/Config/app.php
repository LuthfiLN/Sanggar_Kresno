<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

if (!function_exists('load_env')) {
    function load_env(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            if ($value !== '' && !array_key_exists($key, $_ENV) && !array_key_exists($key, $_SERVER)) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

if (!function_exists('is_vercel')) {
    function is_vercel(): bool
    {
        return !empty(getenv('VERCEL')) || !empty($_SERVER['VERCEL']) || !empty(getenv('NOW_REGION'));
    }
}

if (!function_exists('app_base_path')) {
    function app_base_path(string $path = ''): string
    {
        $base = dirname(__DIR__, 2);
        return $path !== '' ? $base . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR) : $base;
    }
}
