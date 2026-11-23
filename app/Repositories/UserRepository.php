<?php

declare(strict_types=1);

function find_user_for_login(string $login): ?array
{
    $query = db()->prepare('SELECT id, password, role FROM users WHERE email = ? OR name = ? LIMIT 1');
    $query->execute([$login, $login]);
    $user = $query->fetch();
    return $user ?: null;
}

function create_customer(string $name, string $email, string $passwordHash): int
{
    $query = db()->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'customer')");
    $query->execute([$name, $email, $passwordHash]);
    return (int) db()->lastInsertId();
}
