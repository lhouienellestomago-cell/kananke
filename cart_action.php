<?php
require_once __DIR__ . '/cart_helper.php';

if (($_SESSION['loggedIn'] ?? false) !== true) {
    header('Location: login_page.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !cart_verify_csrf()) {
    http_response_code(400);
    exit('Invalid request.');
}

$action = (string)($_POST['action'] ?? '');
$productId = (string)($_POST['product_id'] ?? '');

if ($action === 'update' && isset($_SESSION['cart'][$productId])) {

    $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT);

    if ($quantity === false || $quantity < 1 || $quantity > 100) {
        header('Location: cart.php?error=quantity');
        exit;
    }

    $_SESSION['cart'][$productId]['quantity'] = $quantity;

} elseif ($action === 'remove') {

    unset($_SESSION['cart'][$productId]);

} elseif ($action === 'clear') {

    $_SESSION['cart'] = [];

} else {

    http_response_code(400);
    exit('Unknown cart action.');
}

header('Location: cart.php?updated=1');
exit;