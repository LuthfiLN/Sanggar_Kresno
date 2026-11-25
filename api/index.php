<?php

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$root = dirname(__DIR__);
$publicRoot = $root . '/public';
$assetRoot = is_dir($publicRoot . '/assets') ? $publicRoot : $root;

$path = trim((string) $uri, '/');

if ($path === '' || $path === 'index.php') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
    chdir($root);
    require $root . '/index.php';
    exit;
}

if (str_starts_with($path, 'assets/')) {
    $assetPath = $assetRoot . '/' . $path;
    if (!is_file($assetPath) && $assetRoot !== $root) {
        $assetPath = $root . '/' . $path;
    }
    if (is_file($assetPath)) {
        $ext = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
        $mimes = [
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico'  => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
        ];
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=86400');
        readfile($assetPath);
        exit;
    }
}

if ($path === 'admin' || $path === 'admin/' || $path === 'admin/index.php') {
    $_SERVER['SCRIPT_NAME'] = '/admin/index.php';
    $_SERVER['PHP_SELF'] = '/admin/index.php';
    chdir($root . '/admin');
    require $root . '/admin/index.php';
    exit;
}

$targetFile = $root . '/' . $path;
if (is_file($targetFile) && str_ends_with($targetFile, '.php')) {
    $_SERVER['SCRIPT_NAME'] = '/' . $path;
    $_SERVER['PHP_SELF'] = '/' . $path;
    chdir(dirname($targetFile));
    require $targetFile;
    exit;
}

if (is_file($targetFile . '.php')) {
    $_SERVER['SCRIPT_NAME'] = '/' . $path . '.php';
    $_SERVER['PHP_SELF'] = '/' . $path . '.php';
    chdir(dirname($targetFile));
    require $targetFile . '.php';
    exit;
}

if (is_dir($targetFile) && is_file($targetFile . '/index.php')) {
    $_SERVER['SCRIPT_NAME'] = '/' . $path . '/index.php';
    $_SERVER['PHP_SELF'] = '/' . $path . '/index.php';
    chdir($targetFile);
    require $targetFile . '/index.php';
    exit;
}

http_response_code(404);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>404 - Halaman Tidak Ditemukan | Sanggar Kresno</title>
    <style>
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        text-align: center;
        padding: 60px 20px;
        background: #fafafa;
        color: #222;
    }

    .box {
        max-width: 480px;
        margin: 0 auto;
        background: #fff;
        padding: 36px;
        border-radius: 12px;
        border: 1px solid #eaeaea;
    }

    h1 {
        margin-top: 0;
        font-size: 24px;
    }

    a {
        color: #111;
        text-decoration: underline;
        font-weight: 600;
    }
    </style>
</head>

<body>
    <div class="box">
        <h1>404 &bull; Halaman Tidak Ditemukan</h1>
        <p>Halaman yang Anda minta tidak tersedia di sistem Sanggar Kresno.</p>
        <p><a href="/">&larr; Kembali ke Katalog Pre-Order</a></p>
    </div>
</body>

</html>