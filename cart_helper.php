<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (empty($_SESSION['cart_csrf'])) {
    $_SESSION['cart_csrf'] = bin2hex(random_bytes(32));
}

function cart_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function cart_read_json(string $filename): array {
    $path = __DIR__ . '/' . $filename;
    if (!is_file($path)) return [];
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function cart_catalog(): array {
    $catalog = [
        'tray:chicken' => [
            'name' => 'Fried Chicken Tray',
            'price' => 800,
            'image' => 'chickentray.png',
            'category' => 'Food by Tray'
        ],
        'tray:bbq' => [
            'name' => 'Chicken Cordon Bleu',
            'price' => 800,
            'image' => 'chickencordon.png',
            'category' => 'Food by Tray'
        ],
        'bundle:barkada-bundle' => [
            'name' => 'Barkada Bundle',
            'price' => 5000,
            'image' => 'images/barkada_bundle.jpg',
            'category' => 'Food Bundles'
        ],
        'bundle:family-bundle' => [
            'name' => 'Family Bundle',
            'price' => 1500,
            'image' => 'images/bundle.jpg',
            'category' => 'Food Bundles'
        ],
        'additional:fried-chicken' => [
            'name' => 'Softdrinks',
            'price' => 80,
            'image' => 'images/softdrinks.jpg',
            'category' => 'Additionals'
        ],
        'additional:dessert' => [
            'name' => 'Macarons',
            'price' => 350,
            'image' => 'images/Macarons.jpg',
            'category' => 'Additionals'
        ],
    ];

    $sources = [
        'tray' => [
            'file' => 'food_tray_products.json',
            'category' => 'Food by Tray'
        ],
        'bundle' => [
            'file' => 'bundle_products.json',
            'category' => 'Food Bundles'
        ],
        'additional' => [
            'file' => 'additional_products.json',
            'category' => 'Additionals'
        ],
    ];

    foreach ($sources as $prefix => $source) {
        $savedProducts = cart_read_json($source['file']);

        // A tombstone in the JSON file removes a built-in product too.
        if (isset($savedProducts['_deleted']) && is_array($savedProducts['_deleted'])) {
            foreach ($savedProducts['_deleted'] as $deletedId => $deleted) {
                if ($deleted) {
                    unset($catalog[$prefix . ':' . $deletedId]);
                }
            }
        }

        foreach ($savedProducts as $id => $product) {
            if ($id === '_deleted' || !is_array($product) ||
                !isset($product['name'], $product['price'])) {
                continue;
            }

            $price = filter_var(
                $product['price'],
                FILTER_VALIDATE_FLOAT
            );

            if ($price === false || $price <= 0) continue;

            // Saved catalog records override the built-in product details.
            $catalog[$prefix . ':' . $id] = [
                'name' => (string)$product['name'],
                'price' => (float)$price,
                'image' => (string)($product['image'] ?? ''),
                'category' => $source['category']
            ];
        }
    }

    // Merge the permanent Railway catalog. This keeps added/edited/deleted
    // products consistent when customers add items to their cart.
    global $conn;
    if (!isset($conn) || !($conn instanceof mysqli)) {
        require_once __DIR__ . '/db.php';
    }
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->query("CREATE TABLE IF NOT EXISTS catalog_products (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            product_key VARCHAR(100) NOT NULL,
            category VARCHAR(30) NOT NULL,
            name VARCHAR(150) NOT NULL,
            description VARCHAR(1000) NOT NULL,
            price DECIMAL(12,2) NOT NULL,
            image_data LONGBLOB NULL,
            image_mime VARCHAR(50) NULL,
            image_path VARCHAR(500) NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_catalog_product (category, product_key),
            INDEX idx_catalog_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $categoryNames = [
            'tray' => 'Food by Tray',
            'bundle' => 'Food Bundles',
            'additional' => 'Additionals'
        ];
        $result = $conn->query("SELECT id, product_key, category, name, price, image_data, image_path, is_deleted FROM catalog_products");
        while ($row = $result->fetch_assoc()) {
            $key = $row['category'] . ':' . $row['product_key'];
            if ((int)$row['is_deleted'] === 1) {
                unset($catalog[$key]);
                continue;
            }
            $image = !empty($row['image_data'])
                ? 'product_image.php?id=' . (int)$row['id']
                : (string)($row['image_path'] ?? '');
            $catalog[$key] = [
                'name' => (string)$row['name'],
                'price' => (float)$row['price'],
                'image' => $image,
                'category' => $categoryNames[$row['category']] ?? 'Products'
            ];
        }
        $result->free();
    }

    return $catalog;
}

function cart_item_count(): int {
    $count = 0;

    foreach ($_SESSION['cart'] as $item) {
        $count += max(0, (int)($item['quantity'] ?? 0));
    }

    return $count;
}

function cart_total(): float {
    $total = 0;

    foreach ($_SESSION['cart'] as $item) {
        $total += (float)$item['price'] * (int)$item['quantity'];
    }

    return $total;
}

function cart_verify_csrf(): bool {
    return isset($_POST['csrf_token'], $_SESSION['cart_csrf'])
        && hash_equals(
            $_SESSION['cart_csrf'],
            (string)$_POST['csrf_token']
        );
}
