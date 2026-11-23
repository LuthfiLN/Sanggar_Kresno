<?php

declare(strict_types=1);

function list_orders_for_user(int $userId): array
{
    $query = db()->prepare('SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC');
    $query->execute([$userId]);
    return $query->fetchAll();
}

function find_order_for_viewer(int $orderId, int $userId, string $role): ?array
{
    $query = db()->prepare('SELECT * FROM orders WHERE id = ? AND (user_id = ? OR ? = "admin")');
    $query->execute([$orderId, $userId, $role]);
    $order = $query->fetch();
    return $order ?: null;
}

function list_order_items(int $orderId): array
{
    $query = db()->prepare('SELECT product_name, price, quantity FROM order_items WHERE order_id = ? ORDER BY id');
    $query->execute([$orderId]);
    return $query->fetchAll();
}
