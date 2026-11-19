<?php
require_once __DIR__ . '/cart_helper.php';

// Check if the customer is logged in.
if (($_SESSION['loggedIn'] ?? false) !== true) {
    header('Location: login_page.php');
    exit;
}

// Only accept POST requests with a valid security token.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !cart_verify_csrf()) {
    http_response_code(400);
    exit('Invalid request. Please refresh and try again.');
}

// Get submitted product information.
$productId = trim((string)($_POST['product_id'] ?? ''));
$quantity = filter_var(
    $_POST['quantity'] ?? 1,
    FILTER_VALIDATE_INT
);

// Get the available products.
$catalog = cart_catalog();

// Check whether the product exists.
if (!isset($catalog[$productId])) {
    http_response_code(404);
    exit('Product not found.');
}

// Validate quantity.
if ($quantity === false || $quantity < 1 || $quantity > 100) {
    exit('Invalid quantity.');
}

// Get the actual product details from the catalog.
$product = $catalog[$productId];

// Add product to the cart.
if (!isset($_SESSION['cart'][$productId])) {
    $_SESSION['cart'][$productId] = [
        'name' => $product['name'],
        'price' => (float)$product['price'],
        'image' => $product['image'],
        'category' => $product['category'],
        'quantity' => 0
    ];
}

// Increase the quantity.
$newQuantity =
    (int)$_SESSION['cart'][$productId]['quantity'] + $quantity;

$_SESSION['cart'][$productId]['quantity'] =
    min(100, $newQuantity);

// Redirect back to the page where the customer added the item.
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
