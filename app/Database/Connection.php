<?php

declare(strict_types=1);

if (!function_exists('db')) {
    function db(): PDO
    {
        static $connection;
        if ($connection instanceof PDO) {
            return $connection;
        }

        $dbUrl = getenv('DATABASE_URL');
        if ($dbUrl) {
            $parsed = parse_url($dbUrl);
            if ($parsed && ($parsed['scheme'] ?? '') === 'mysql') {
                $host = $parsed['host'] ?? '127.0.0.1';
                $port = $parsed['port'] ?? 3306;
                $user = $parsed['user'] ?? 'root';
                $pass = $parsed['pass'] ?? '';
                $name = ltrim($parsed['path'] ?? 'sanggar_tresno', '/');
                try {
                    $options = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ];
                    if (getenv('DB_SSL') === 'true' || getenv('MYSQL_ATTR_SSL_CA')) {
                        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                    }
                    $connection = new PDO(
                        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
                        $user,
                        $pass,
                        $options
                    );
                    return $connection;
                } catch (Throwable) {
                }
            }
        }

        $host = getenv('DB_HOST') ?: DB_HOST;
        $name = getenv('DB_NAME') ?: DB_NAME;
        $user = getenv('DB_USER') ?: DB_USER;
        $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : DB_PASS;
        $port = getenv('DB_PORT') ?: DB_PORT;

        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            if (getenv('DB_SSL') === 'true' || getenv('MYSQL_ATTR_SSL_CA')) {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }

            $connection = new PDO(
                "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                $options
            );
            return $connection;
        } catch (Throwable $e) {
            $sqlitePath = is_vercel()
                ? (sys_get_temp_dir() . '/sanggar_tresno.sqlite')
                : (app_base_path('sanggar_tresno.sqlite'));

            $appEnv = strtolower((string) (getenv('APP_ENV') ?: ''));
            $allowSqliteFallback = $appEnv !== 'production' && getenv('DB_REQUIRE') !== 'true';
            if (is_vercel() || !$allowSqliteFallback) {
                throw new RuntimeException('Database connection failed and SQLite fallback is disabled for this environment.', 0, $e);
            }

            $connection = new PDO('sqlite:' . $sqlitePath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            init_sqlite_schema($connection);
            return $connection;
        }
    }
}
