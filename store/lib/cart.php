<?php

use App\Database;
use App\Helpers;

if (!function_exists('store_cart_get')) {
    /**
     * @return array{items:array<int,array<string,mixed>>,total:float}
     */
    function store_cart_get(): array
    {
        if (!isset($_SESSION['storefront_cart']) || !is_array($_SESSION['storefront_cart'])) {
            $_SESSION['storefront_cart'] = array('items' => array(), 'total' => 0.0);
        }

        $cart = $_SESSION['storefront_cart'];
        if (!isset($cart['items']) || !is_array($cart['items'])) {
            $cart['items'] = array();
        }
        if (!isset($cart['total'])) {
            $cart['total'] = 0.0;
        }

        return $cart;
    }
}

if (!function_exists('store_cart_save')) {
    /**
     * @param array{items:array<int,array<string,mixed>>,total:float} $cart
     */
    function store_cart_save(array $cart): void
    {
        $_SESSION['storefront_cart'] = $cart;
    }
}

if (!function_exists('store_cart_count')) {
    function store_cart_count(): int
    {
        $cart = store_cart_get();
        $count = 0;
        foreach ($cart['items'] as $item) {
            $count += isset($item['quantity']) ? (int) $item['quantity'] : 0;
        }

        return max(0, $count);
    }
}

if (!function_exists('store_cart_total')) {
    function store_cart_total(): float
    {
        $cart = store_cart_get();

        return isset($cart['total']) ? (float) $cart['total'] : 0.0;
    }
}

if (!function_exists('store_cart_add')) {
    /**
     * @return array{status:string,message:string}
     */
    function store_cart_add(int $productId, int $quantity = 1): array
    {
        $quantity = max(1, $quantity);

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare("SELECT p.id, p.name, p.price, p.sku, p.image, p.status, p.automatic_delivery,
                    c.name AS category_name, c.slug AS category_slug
                FROM products p
                INNER JOIN categories c ON c.id = p.category_id
                WHERE p.id = :id LIMIT 1");
            $stmt->execute(array('id' => $productId));
            $product = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $exception) {
            $product = null;
        }

        if (!$product || (isset($product['status']) && $product['status'] !== 'active')) {
            return array('status' => 'error', 'message' => 'Ürün bulunamadı veya aktif değil.');
        }

        $cart = store_cart_get();
        $key = (int) $product['id'];
        if (!isset($cart['items'][$key])) {
            $cart['items'][$key] = array(
                'product_id' => $key,
                'name' => (string) $product['name'],
                'price' => isset($product['price']) ? (float) $product['price'] : 0.0,
                'currency' => null,
                'quantity' => 0,
                'sku' => isset($product['sku']) ? (string) $product['sku'] : '',
                'image' => isset($product['image']) ? (string) $product['image'] : '',
                'category_name' => isset($product['category_name']) ? (string) $product['category_name'] : '',
                'automatic_delivery' => !empty($product['automatic_delivery']),
                'url' => function_exists('url_product') ? url_product($product) : '/product/' . $key,
            );
        }

        $cart['items'][$key]['quantity'] += $quantity;
        $cart = store_cart_recalculate($cart);
        store_cart_save($cart);

        store_cart_flash('success', 'Ürün sepete eklendi.');

        return array('status' => 'success', 'message' => 'Ürün sepete eklendi.');
    }
}

if (!function_exists('store_cart_update')) {
    /**
     * @return array{status:string,message:string}
     */
    function store_cart_update(int $productId, int $quantity): array
    {
        $cart = store_cart_get();
        $key = $productId;
        if (!isset($cart['items'][$key])) {
            return array('status' => 'error', 'message' => 'Ürün sepetinizde bulunamadı.');
        }

        $quantity = max(1, $quantity);
        $cart['items'][$key]['quantity'] = $quantity;
        $cart = store_cart_recalculate($cart);
        store_cart_save($cart);

        store_cart_flash('success', 'Sepet güncellendi.');

        return array('status' => 'success', 'message' => 'Sepet güncellendi.');
    }
}

if (!function_exists('store_cart_remove')) {
    /**
     * @return array{status:string,message:string}
     */
    function store_cart_remove(int $productId): array
    {
        $cart = store_cart_get();
        if (isset($cart['items'][$productId])) {
            unset($cart['items'][$productId]);
            $cart = store_cart_recalculate($cart);
            store_cart_save($cart);
            store_cart_flash('success', 'Ürün sepetten kaldırıldı.');

            return array('status' => 'success', 'message' => 'Ürün sepetten kaldırıldı.');
        }

        return array('status' => 'error', 'message' => 'Ürün sepetinizde bulunamadı.');
    }
}

if (!function_exists('store_cart_clear')) {
    function store_cart_clear(): void
    {
        store_cart_save(array('items' => array(), 'total' => 0.0));
    }
}

if (!function_exists('store_cart_recalculate')) {
    /**
     * @param array{items:array<int,array<string,mixed>>,total:float} $cart
     * @return array{items:array<int,array<string,mixed>>,total:float}
     */
    function store_cart_recalculate(array $cart): array
    {
        $total = 0.0;
        foreach ($cart['items'] as &$item) {
            $price = isset($item['price']) ? (float) $item['price'] : 0.0;
            $quantity = isset($item['quantity']) ? max(1, (int) $item['quantity']) : 1;
            $item['quantity'] = $quantity;
            $item['line_total'] = $price * $quantity;
            $total += $item['line_total'];
        }
        unset($item);
        $cart['total'] = $total;

        return $cart;
    }
}

if (!function_exists('store_cart_flash')) {
    function store_cart_flash(string $type, string $message): void
    {
        $_SESSION['storefront_cart_flash'] = array('type' => $type, 'message' => $message);
    }
}

if (!function_exists('store_cart_flash_get')) {
    /**
     * @return array{type:string,message:string}|null
     */
    function store_cart_flash_get(): ?array
    {
        if (!isset($_SESSION['storefront_cart_flash']) || !is_array($_SESSION['storefront_cart_flash'])) {
            return null;
        }
        $flash = $_SESSION['storefront_cart_flash'];
        unset($_SESSION['storefront_cart_flash']);

        if (!isset($flash['type'], $flash['message'])) {
            return null;
        }

        return array('type' => (string) $flash['type'], 'message' => (string) $flash['message']);
    }
}

if (!function_exists('store_request_wants_json')) {
    function store_request_wants_json(): bool
    {
        if (isset($_SERVER['HTTP_ACCEPT']) && stripos((string) $_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            return true;
        }

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        return false;
    }
}
