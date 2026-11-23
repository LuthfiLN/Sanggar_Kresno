<?php

declare(strict_types=1);

function message_key(): string
{
    $key = getenv('APP_KEY') ?: '';
    if ($key === '') {
        throw new RuntimeException('APP_KEY belum dikonfigurasi.');
    }
    return hash('sha256', $key, true);
}

function encrypt_message(string $plaintext): string
{
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', message_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Pesan gagal dienkripsi.');
    }
    return 'v1.' . base64_encode($iv . $tag . $ciphertext);
}

function decrypt_message(string $stored): string
{
    if (!str_starts_with($stored, 'v1.')) {
        return $stored;
    }
    $raw = base64_decode(substr($stored, 3), true);
    if ($raw === false || strlen($raw) < 28) {
        return '[Pesan tidak dapat dibaca]';
    }
    $plaintext = openssl_decrypt(
        substr($raw, 28),
        'aes-256-gcm',
        message_key(),
        OPENSSL_RAW_DATA,
        substr($raw, 0, 12),
        substr($raw, 12, 16)
    );
    return $plaintext === false ? '[Pesan tidak dapat dibaca]' : $plaintext;
}
