<?php

declare(strict_types=1);

function find_active_product(int $id): ?array
{
    $query = db()->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1');
    $query->execute([$id]);
    $product = $query->fetch();
    return $product ?: null;
}

function find_active_product_stock(int $id): ?array
{
    $query = db()->prepare('SELECT id, stock FROM products WHERE id = ? AND is_active = 1');
    $query->execute([$id]);
    $product = $query->fetch();
    return $product ?: null;
}

function search_active_products(string $search, string $category): array
{
    $conditions = ['is_active = 1'];
    $parameters = [];

    if ($search !== '') {
        $conditions[] = '(name LIKE ? OR description LIKE ? OR category LIKE ?)';
        $term = '%' . $search . '%';
        array_push($parameters, $term, $term, $term);
    }

    if ($category === 'makanan') {
        $conditions[] = "(category LIKE '%kue%' OR category LIKE '%makanan%')";
    } elseif ($category === 'minuman') {
        $conditions[] = "category LIKE '%minuman%'";
    }

    $query = db()->prepare('SELECT * FROM products WHERE ' . implode(' AND ', $conditions) . ' ORDER BY created_at DESC');
    $query->execute($parameters);
    return $query->fetchAll();
}

function find_cart_products(array $ids): array
{
    if (!$ids) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $query = db()->prepare("SELECT id, name, price, stock, image_url, category FROM products WHERE is_active = 1 AND id IN ($placeholders)");
    $query->execute($ids);
    return $query->fetchAll();
}
