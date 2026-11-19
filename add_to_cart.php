<?php
require_once __DIR__ . '/cart_helper.php';

if (($_SESSION['loggedIn'] ?? false) !== true) {
    header('Location: login_page.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !cart_verify_csrf()) {
    http_response_code(400);
    exit('Invalid request. Please refresh and try again.');
}

$productId = trim((string)($_POST['product_id'] ?? ''));
$quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT);

$catalog = cart_catalog();

if (!isset($catalog[$productId])) {
    http_response_code(404);
    exit('Product not found.');
}

if ($quantity === false || $quantity < 1 || $quantity > 100) {
    exit('Invalid quantity.');
}

$product = $catalog[$productId];

if (!isset($_SESSION['cart'][$productId])) {
    $_SESSION['cart'][$productId] = [
        'name' => $product['name'],
        'price' => (float)$product['price'],
        'image' => $product['image'],
        'category' => $product['category'],
        'quantity' => 0
    ];
}

$newQuantity = (int)$_SESSION['cart'][$productId]['quantity'] + $quantity;

$_SESSION['cart'][$productId]['quantity'] = min(100, $newQuantity);

$returnTo = (string)($_POST['return_to'] ?? 'cart.php');

$allowed = [
    'index.php',
    'tray.php',
    'bundle.php',
    'additionals.php',
    'cart.php'
];

if (!in_array($returnTo, $allowed, true)) {
    $returnTo = 'cart.php';
}

header('Location: ' . $returnTo . '?added=1');
exit;