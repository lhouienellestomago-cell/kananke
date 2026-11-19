<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/cart_helper.php";

if (($_SESSION["loggedIn"] ?? false) !== true) {
    header("Location: login_page.php");
    exit;
}

$type = $pageType ?? "tray";

$pages = [
    "tray" => [
        "title" => "Food by Tray",
        "subtitle" => "Delicious food trays made for sharing",
        "json" => "food_tray_products.json",
        "prefix" => "tray",
        "active" => "tray",
        "products" => [
            "chicken" => [
                "name" => "Fried Chicken Tray",
                "description" => "A delicious tray of crispy fried chicken.",
                "price" => 800,
                "image" => "chickentray.png"
            ],
            "bbq" => [
                "name" => "Chicken Cordon Bleu",
                "description" => "A delicious chicken favorite, perfect for sharing.",
                "price" => 800,
                "image" => "chickencordon.png"
            ]
        ]
    ],
    "bundle" => [
        "title" => "Food Bundles",
        "subtitle" => "Great meals and bundles for every occasion",
        "json" => "bundle_products.json",
        "prefix" => "bundle",
        "active" => "bundle",
        "products" => [
            "barkada-bundle" => [
                "name" => "Barkada Bundle",
                "description" => "A delicious bundle to enjoy with friends.",
                "price" => 5000,
                "image" => "images/Barkada_bundle.jpg"
            ],
            "family-bundle" => [
                "name" => "Family Bundle",
                "description" => "A satisfying meal for the whole family.",
                "price" => 1500,
                "image" => "images/bundle.jpg"
            ]
        ]
    ],
    "additional" => [
        "title" => "Additionals",
        "subtitle" => "Complete your meal with something extra",
        "json" => "additional_products.json",
        "prefix" => "additional",
        "active" => "additional",
        "products" => [
            "fried-chicken" => [
                "name" => "Softdrinks",
                "description" => "Refreshing drinks to enjoy with your meal.",
                "price" => 80,
                "image" => "images/softdrinks.jpg"
            ],
            "dessert" => [
                "name" => "Macarons",
                "description" => "Sweet treats to finish your meal.",
                "price" => 350,
                "image" => "images/Macarons.jpg"
            ]
        ]
    ]
];

if (!isset($pages[$type])) {
    http_response_code(404);
    exit("Page not found.");
}

$config = $pages[$type];
$isAdmin = (($_SESSION["role"] ?? "") === "admin");

if (empty($_SESSION["catalog_csrf"])) {
    $_SESSION["catalog_csrf"] = bin2hex(random_bytes(32));
}

$adminMessage = "";
$adminMessageType = "success";

// Admin-only product creation for the current category.
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_catalog_product"])) {
    if (!$isAdmin) {
        http_response_code(403);
        exit("You are not allowed to add products.");
    }

    $submittedToken = (string)($_POST["catalog_csrf"] ?? "");
    if (!hash_equals($_SESSION["catalog_csrf"], $submittedToken)) {
        $adminMessage = "Your session expired. Please refresh the page and try again.";
        $adminMessageType = "error";
    } else {
        $productName = trim((string)($_POST["product_name"] ?? ""));
        $description = trim((string)($_POST["product_description"] ?? ""));
        $price = filter_var(trim((string)($_POST["product_price"] ?? "")), FILTER_VALIDATE_FLOAT);

        if ($productName === "" || mb_strlen($productName) > 150) {
            $adminMessage = "Enter a product name (maximum 150 characters).";
            $adminMessageType = "error";
        } elseif ($description === "" || mb_strlen($description) > 1000) {
            $adminMessage = "Enter a description (maximum 1,000 characters).";
            $adminMessageType = "error";
        } elseif ($price === false || $price <= 0) {
            $adminMessage = "Enter a valid price greater than ₱0.";
            $adminMessageType = "error";
        } elseif (!isset($_FILES["product_image"]) || $_FILES["product_image"]["error"] !== UPLOAD_ERR_OK) {
            $adminMessage = "Please choose a product image to upload.";
            $adminMessageType = "error";
        } elseif ($_FILES["product_image"]["size"] > 10 * 1024 * 1024) {
            $adminMessage = "The image is too large. Maximum size is 10MB.";
            $adminMessageType = "error";
        } else {
            $tmpImage = $_FILES["product_image"]["tmp_name"];
            $imageInfo = @getimagesize($tmpImage);
            $mime = $imageInfo["mime"] ?? "";
            $allowedTypes = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp", "image/gif" => "gif"];

            if (!isset($allowedTypes[$mime])) {
                $adminMessage = "Only JPG, PNG, WEBP, and GIF images are allowed.";
                $adminMessageType = "error";
            } else {
                $uploadDirectory = __DIR__ . "/uploads/catalog/" . $type;
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                    $adminMessage = "Could not create the product image folder. Check hosting permissions.";
                    $adminMessageType = "error";
                } else {
                    $imageFileName = bin2hex(random_bytes(16)) . "." . $allowedTypes[$mime];
                    $imageDestination = $uploadDirectory . "/" . $imageFileName;
                    $relativeImagePath = "uploads/catalog/" . $type . "/" . $imageFileName;

                    if (!move_uploaded_file($tmpImage, $imageDestination)) {
                        $adminMessage = "The image could not be saved. Check folder permissions.";
                        $adminMessageType = "error";
                    } else {
                        $jsonPathForSave = __DIR__ . "/" . $config["json"];
                        $savedProducts = [];
                        if (is_file($jsonPathForSave)) {
                            $existingJson = json_decode((string)file_get_contents($jsonPathForSave), true);
                            if (is_array($existingJson)) $savedProducts = $existingJson;
                        }

                        $newProductId = $type . "-" . bin2hex(random_bytes(8));
                        $savedProducts[$newProductId] = [
                            "name" => $productName,
                            "description" => $description,
                            "price" => round((float)$price, 2),
                            "image" => $relativeImagePath
                        ];
                        $jsonOutput = json_encode($savedProducts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                        if ($jsonOutput === false || file_put_contents($jsonPathForSave, $jsonOutput, LOCK_EX) === false) {
                            @unlink($imageDestination);
                            $adminMessage = "Could not save the product list. Make sure the website folder is writable.";
                            $adminMessageType = "error";
                        } else {
                            $_SESSION["catalog_flash"] = ["type" => "success", "message" => "Product added successfully!"];
                            header("Location: " . basename($_SERVER["PHP_SELF"]));
                            exit;
                        }
                    }
                }
            }
        }
    }
}

if (isset($_SESSION["catalog_flash"]) && is_array($_SESSION["catalog_flash"])) {
    $adminMessage = (string)($_SESSION["catalog_flash"]["message"] ?? "");
    $adminMessageType = (string)($_SESSION["catalog_flash"]["type"] ?? "success");
    unset($_SESSION["catalog_flash"]);
}

$products = $config["products"];

// Load administrator-added products.
$jsonPath = __DIR__ . "/" . $config["json"];

if (is_file($jsonPath)) {
    $savedProducts = json_decode(
        file_get_contents($jsonPath),
        true
    );

    if (is_array($savedProducts)) {
        foreach ($savedProducts as $id => $product) {
            if (
                !is_array($product) ||
                !isset($product["name"], $product["price"])
            ) {
                continue;
            }

            $price = filter_var(
                $product["price"],
                FILTER_VALIDATE_FLOAT
            );

            if ($price === false || $price < 0) {
                continue;
            }

            $products[$id] = [
                "name" => (string)$product["name"],
                "description" => (string)($product["description"] ?? ""),
                "price" => (float)$price,
                "image" => (string)($product["image"] ?? "")
            ];
        }
    }
}

// Determine user information.
$customerName = $_SESSION["name"]
    ?? $_SESSION["full_name"]
    ?? $_SESSION["username"]
    ?? "Customer";

$customerInitial = strtoupper(
    substr(trim((string)$customerName), 0, 1)
);

if ($customerInitial === "") {
    $customerInitial = "C";
}

$cart = $_SESSION["cart"] ?? [];
$cartCount = cart_item_count();
$cartTotal = cart_total();

function food_image_path($image): string {
    $image = trim((string)$image);

    if ($image === "") {
        return "kanankelogo.png";
    }

    return $image;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= cart_h($config["title"]) ?> | Kananke Food Hauz</title>

<style>
* {
    box-sizing: border-box;
}

:root {
    --gold: #f5b400;
    --orange: #f28b28;
    --dark: #292929;
    --muted: #888;
    --background: #f6f7f9;
    --white: #fff;
    --border: #eeeeee;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    color: var(--dark);
    background: var(--background);
}

a {
    text-decoration: none;
    color: inherit;
}

button,
input {
    font: inherit;
}

/* MAIN APP LAYOUT */

.app-layout {
    display: grid;
    grid-template-columns: 220px minmax(0, 1fr) 285px;
    min-height: 100vh;
}

/* LEFT SIDEBAR */

.sidebar {
    background: white;
    padding: 25px 16px;
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.logo {
    font-size: 23px;
    font-weight: 900;
    color: var(--orange);
    padding: 4px 12px 30px;
}

.logo small {
    display: block;
    font-size: 10px;
    letter-spacing: 2px;
    color: #999;
    margin-top: 5px;
}

.nav-heading {
    font-size: 10px;
    font-weight: bold;
    color: #aaa;
    letter-spacing: 1.5px;
    padding: 12px;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 12px;
    border-radius: 10px;
    color: #777;
    font-size: 13px;
    font-weight: 600;
    margin: 3px 0;
    transition: .2s;
}

.nav-link:hover {
    background: #fff8e5;
    color: #bd8500;
}

.nav-link.active {
    color: #9a6a00;
    background: #fff2c8;
}

.nav-icon {
    width: 22px;
    text-align: center;
    font-size: 17px;
}

.sidebar-bottom {
    margin-top: auto;
    border-top: 1px solid var(--border);
    padding-top: 16px;
}

/* CENTER CONTENT */

.main-content {
    min-width: 0;
    padding: 27px 28px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
}

.welcome-text {
    color: var(--muted);
    font-size: 12px;
    margin-bottom: 5px;
}

.topbar h1 {
    margin: 0;
    font-size: 25px;
}

.profile-circle {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ffedbd;
    color: #9a6a00;
    font-weight: bold;
}

/* BANNER */

.banner {
    padding: 25px;
    border-radius: 15px;
    background: linear-gradient(110deg, #ffda74, #ffedbd);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
    overflow: hidden;
}

.banner h2 {
    margin: 0 0 8px;
    font-size: 21px;
}

.banner p {
    margin: 0;
    color: #755a22;
    font-size: 12px;
    line-height: 1.6;
}

.banner-icon {
    font-size: 52px;
}

/* SECTION HEADING */

.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin: 22px 0 16px;
}

.section-heading h2 {
    font-size: 18px;
    margin: 0;
}

.section-heading span {
    color: var(--muted);
    font-size: 11px;
}

/* ADMIN ADD PRODUCT */
.admin-heading-actions { display:flex; align-items:center; gap:12px; }
.admin-add-button { border:0; border-radius:9px; background:var(--orange); color:#fff; padding:10px 14px; font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap; }
.admin-add-button:hover { background:#dc7418; }
.admin-message { margin:0 0 15px; padding:12px 14px; border-radius:9px; font-size:12px; }
.admin-message.success { background:#eaf8ed; color:#216b35; }
.admin-message.error { background:#fff0ed; color:#a52c1d; }
.product-modal-overlay { display:none; position:fixed; inset:0; z-index:200; background:rgba(0,0,0,.55); padding:18px; align-items:center; justify-content:center; }
.product-modal-overlay.open { display:flex; }
.product-modal { width:100%; max-width:470px; max-height:92vh; overflow-y:auto; background:#fff; border-radius:15px; padding:23px; box-shadow:0 15px 50px #0003; }
.product-modal-header { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:18px; }
.product-modal-header h2 { margin:0; font-size:20px; }
.product-modal-close { border:0; background:#f2f2f2; border-radius:50%; width:34px; height:34px; cursor:pointer; font-size:20px; }
.admin-form-group { margin:13px 0; }
.admin-form-group label { display:block; margin-bottom:7px; font-size:12px; font-weight:700; }
.admin-form-group input, .admin-form-group textarea { width:100%; border:1px solid #ddd; border-radius:8px; padding:11px; outline:none; font:inherit; font-size:13px; }
.admin-form-group input:focus, .admin-form-group textarea:focus { border-color:var(--orange); }
.admin-form-group textarea { resize:vertical; min-height:85px; }
.admin-form-note { color:var(--muted); font-size:10px; margin-top:5px; }
.admin-save-button { width:100%; border:0; border-radius:9px; padding:13px; margin-top:10px; background:var(--gold); color:#493500; font-weight:800; cursor:pointer; }
.admin-save-button:hover { background:#e8a900; }

/* PRODUCT GRID */

.product-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px;
}

.product-card {
    min-width: 0;
    background: white;
    border-radius: 13px;
    padding: 11px;
    box-shadow: 0 3px 12px rgba(0,0,0,.035);
    transition: transform .2s, box-shadow .2s;
}

.product-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,.08);
}

.product-image {
    width: 100%;
    height: 155px;
    object-fit: cover;
    border-radius: 9px;
    background: #fff5d9;
}

.product-info {
    padding: 11px 3px 3px;
}

.product-info h3 {
    font-size: 14px;
    margin: 0 0 6px;
}

.product-description {
    color: var(--muted);
    font-size: 11px;
    line-height: 1.5;
    min-height: 32px;
    margin: 0 0 10px;
}

.product-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.product-price {
    color: #d68c00;
    font-weight: 800;
    font-size: 15px;
    white-space: nowrap;
}

.add-button {
    border: none;
    border-radius: 7px;
    background: var(--gold);
    color: #493500;
    padding: 9px 10px;
    font-size: 11px;
    font-weight: bold;
    cursor: pointer;
}

.add-button:hover {
    background: #e8a900;
}

.add-button:disabled {
    opacity: .6;
    cursor: not-allowed;
}

/* RIGHT PANEL */

.right-panel {
    background: white;
    border-left: 1px solid var(--border);
    padding: 25px 17px;
    min-width: 0;
}

.account-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
}

.account-card strong {
    display: block;
    font-size: 12px;
}

.account-card small {
    display: block;
    font-size: 10px;
    color: var(--muted);
    margin-top: 4px;
}

.cart-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 22px 0 15px;
}

.cart-heading h2 {
    margin: 0;
    font-size: 17px;
}

.cart-badge {
    background: #fff1c5;
    color: #9a6a00;
    border-radius: 20px;
    padding: 5px 9px;
    font-size: 10px;
    font-weight: bold;
}

.mini-cart-item {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 11px 0;
    border-bottom: 1px solid #f1f1f1;
}

.mini-cart-item img {
    width: 45px;
    height: 45px;
    border-radius: 8px;
    object-fit: cover;
    background: #fff5d9;
}

.mini-cart-details {
    min-width: 0;
    flex: 1;
}

.mini-cart-details strong {
    display: block;
    font-size: 11px;
    overflow-wrap: anywhere;
}

.mini-cart-details small {
    display: block;
    font-size: 10px;
    color: var(--muted);
    margin-top: 4px;
}

.mini-cart-price {
    font-size: 11px;
    font-weight: bold;
    white-space: nowrap;
}

.empty-cart {
    padding: 25px 8px;
    text-align: center;
    color: var(--muted);
    font-size: 12px;
}

.empty-cart div {
    font-size: 35px;
    margin-bottom: 9px;
}

.summary {
    border-top: 1px solid var(--border);
    margin-top: 15px;
    padding-top: 15px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    margin: 10px 0;
}

.summary-total {
    font-size: 16px;
    font-weight: 800;
    color: #bd8500;
    margin-top: 14px;
}

.checkout-button {
    display: block;
    text-align: center;
    width: 100%;
    padding: 13px;
    background: var(--gold);
    color: #493500;
    border-radius: 8px;
    font-size: 12px;
    font-weight: bold;
    margin-top: 18px;
}

.checkout-button:hover {
    background: #e8a900;
}

.browse-note {
    margin-top: 15px;
    color: var(--muted);
    font-size: 10px;
    text-align: center;
    line-height: 1.5;
}

/* MOBILE CART ACCESS */

.mobile-cart {
    display: none;
}

/* TABLET */

@media (max-width: 1050px) {
    .app-layout {
        grid-template-columns: 190px minmax(0, 1fr);
    }

    .right-panel {
        grid-column: 2;
        border-left: none;
        border-top: 1px solid var(--border);
        min-height: auto;
    }

    .mini-cart-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0 15px;
    }

    .main-content {
        padding: 22px;
    }
}

/* MOBILE */

@media (max-width: 650px) {
    .admin-heading-actions { gap:7px; }
    .admin-add-button { padding:9px 10px; font-size:10px; }
    .section-heading h2 { font-size:16px; }
    .app-layout {
        display: flex;
        flex-direction: column;
    }

    .sidebar {
        min-height: auto;
        padding: 12px 12px 9px;
        position: sticky;
        top: 0;
        z-index: 50;
        border-right: none;
        border-bottom: 1px solid var(--border);
    }

    .logo {
        padding: 2px 5px 10px;
        font-size: 19px;
    }

    .logo small {
        display: none;
    }

    .nav-heading,
    .sidebar-bottom {
        display: none;
    }

    .sidebar nav {
        display: flex;
        overflow-x: auto;
        gap: 5px;
        scrollbar-width: none;
    }

    .sidebar nav::-webkit-scrollbar {
        display: none;
    }

    .nav-link {
        flex: 0 0 auto;
        padding: 9px 11px;
        margin: 0;
        font-size: 11px;
        white-space: nowrap;
    }

    .nav-icon {
        font-size: 14px;
        width: auto;
    }

    .main-content {
        padding: 18px 14px 25px;
    }

    .topbar {
        margin-bottom: 18px;
    }

    .topbar h1 {
        font-size: 21px;
    }

    .banner {
        padding: 19px;
        margin-bottom: 20px;
    }

    .banner h2 {
        font-size: 17px;
    }

    .banner p {
        font-size: 11px;
    }

    .banner-icon {
        font-size: 39px;
    }

    .product-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 11px;
    }

    .product-card {
        padding: 8px;
    }

    .product-image {
        height: 115px;
    }

    .product-info h3 {
        font-size: 12px;
    }

    .product-description {
        font-size: 10px;
        min-height: 42px;
    }

    .product-price {
        font-size: 12px;
    }

    .add-button {
        padding: 8px;
        font-size: 10px;
    }

    .product-bottom {
        align-items: flex-start;
        flex-direction: column;
    }

    .right-panel {
        display: block;
        grid-column: auto;
        padding: 20px 15px 85px;
        border-top: 1px solid var(--border);
    }

    .mini-cart-list {
        display: block;
    }

    .mobile-cart {
        display: flex;
        position: fixed;
        right: 16px;
        bottom: 17px;
        z-index: 100;
        background: var(--gold);
        color: #493500;
        border-radius: 50px;
        padding: 13px 17px;
        font-size: 13px;
        font-weight: bold;
        box-shadow: 0 5px 18px #0003;
    }
}

@media (max-width: 360px) {
    .product-grid {
        grid-template-columns: 1fr;
    }

    .product-image {
        height: 175px;
    }
}
</style>
</head>

<body>

<div class="app-layout">

    <!-- LEFT NAVIGATION -->

    <aside class="sidebar">

        <a href="index.php" class="logo">
            KANANKE
            <small>FOOD HAUZ</small>
        </a>

        <div class="nav-heading">MAIN MENU</div>

        <nav>

            <a class="nav-link" href="index.php">
                <span class="nav-icon">⌂</span>
                Dashboard
            </a>

            <a class="nav-link <?= $type === "tray" ? "active" : "" ?>"
               href="tray.php">
                <span class="nav-icon">🍗</span>
                Food by Tray
            </a>

            <a class="nav-link <?= $type === "bundle" ? "active" : "" ?>"
               href="bundle.php">
                <span class="nav-icon">🍱</span>
                Food Bundles
            </a>

            <a class="nav-link <?= $type === "additional" ? "active" : "" ?>"
               href="additionals.php">
                <span class="nav-icon">🥤</span>
                Additionals
            </a>

            <a class="nav-link" href="order_history.php">
                <span class="nav-icon">📋</span>
                My Orders
            </a>

            <a class="nav-link" href="contact_support.php">
                <span class="nav-icon">💬</span>
                Messages
            </a>

        </nav>

        <div class="sidebar-bottom">

            <a class="nav-link" href="profile.php">
                <span class="nav-icon">👤</span>
                My Profile
            </a>

            <a class="nav-link" href="logout.php">
                <span class="nav-icon">↪</span>
                Logout
            </a>

        </div>

    </aside>


    <!-- CENTER PRODUCT CONTENT -->

    <main class="main-content">

        <div class="topbar">

            <div>
                <div class="welcome-text">
                    Welcome back, <?= cart_h($customerName) ?>!
                </div>

                <h1><?= cart_h($config["title"]) ?></h1>
            </div>

            <div class="profile-circle">
                <?= cart_h($customerInitial) ?>
            </div>

        </div>


        <section class="banner">

            <div>
                <h2><?= cart_h($config["title"]) ?></h2>

                <p>
                    <?= cart_h($config["subtitle"]) ?>
                    <br>
                    Fresh favorites from Kananke Food Hauz.
                </p>
            </div>

            <div class="banner-icon">🍽️</div>

        </section>


        <div class="section-heading">
            <h2>Available Products</h2>
            <div class="admin-heading-actions">
                <span><?= count($products) ?> products</span>
                <?php if ($isAdmin): ?>
                    <button type="button" class="admin-add-button" onclick="openProductModal()">+ Add Product</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($adminMessage !== ""): ?>
            <div class="admin-message <?= cart_h($adminMessageType) ?>"><?= cart_h($adminMessage) ?></div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <div class="product-modal-overlay" id="productModal" onclick="closeProductModalFromOverlay(event)">
            <div class="product-modal" role="dialog" aria-modal="true" aria-labelledby="productModalTitle">
                <div class="product-modal-header">
                    <h2 id="productModalTitle">Add New Product</h2>
                    <button type="button" class="product-modal-close" onclick="closeProductModal()" aria-label="Close">&times;</button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="catalog_csrf" value="<?= cart_h($_SESSION["catalog_csrf"]) ?>">
                    <input type="hidden" name="add_catalog_product" value="1">
                    <div class="admin-form-group">
                        <label for="product_name">Product Name</label>
                        <input type="text" id="product_name" name="product_name" maxlength="150" required>
                    </div>
                    <div class="admin-form-group">
                        <label for="product_description">Description</label>
                        <textarea id="product_description" name="product_description" maxlength="1000" required></textarea>
                    </div>
                    <div class="admin-form-group">
                        <label for="product_price">Price (₱)</label>
                        <input type="number" id="product_price" name="product_price" min="0.01" step="0.01" required>
                    </div>
                    <div class="admin-form-group">
                        <label for="product_image">Product Image</label>
                        <input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/webp,image/gif" required>
                        <div class="admin-form-note">JPG, PNG, WEBP, or GIF. Maximum size: 10MB.</div>
                    </div>
                    <button type="submit" class="admin-save-button">Save Product</button>
                </form>
            </div>
        </div>
        <?php endif; ?>


        <section class="product-grid">

            <?php foreach ($products as $id => $product): ?>

                <article class="product-card">

                    <img
                        class="product-image"
                        src="<?= cart_h(food_image_path($product["image"] ?? "")) ?>"
                        alt="<?= cart_h($product["name"]) ?>"
                        onerror="this.src='kanankelogo.png'"
                    >

                    <div class="product-info">

                        <h3><?= cart_h($product["name"]) ?></h3>

                        <p class="product-description">
                            <?= cart_h($product["description"]) ?>
                        </p>

                        <div class="product-bottom">

                            <span class="product-price">
                                ₱<?= number_format((float)$product["price"], 2) ?>
                            </span>

                            <form method="POST" action="add_to_cart.php">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= cart_h($_SESSION["cart_csrf"]) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="product_id"
                                    value="<?= cart_h($config["prefix"] . ":" . $id) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="quantity"
                                    value="1"
                                >

                                <input
                                    type="hidden"
                                    name="return_to"
                                    value="<?= $type === "additional" ? "additionals.php" : ($type === "bundle" ? "bundle.php" : "tray.php") ?>"
                                >

                                <button class="add-button" type="submit">
                                    + Add to Cart
                                </button>

                            </form>

                        </div>

                    </div>

                </article>

            <?php endforeach; ?>

        </section>

    </main>


    <!-- RIGHT CART PANEL -->

    <aside class="right-panel">

        <div class="account-card">

            <div class="profile-circle">
                <?= cart_h($customerInitial) ?>
            </div>

            <div>
                <strong><?= cart_h($customerName) ?></strong>
                <small>Customer Account</small>
            </div>

        </div>


        <div class="cart-heading">

            <h2>My Cart 🛒</h2>

            <span class="cart-badge">
                <?= $cartCount ?> items
            </span>

        </div>


        <?php if (empty($cart)): ?>

            <div class="empty-cart">
                <div>🛒</div>
                Your cart is empty.
                <br>
                Add something delicious!
            </div>

        <?php else: ?>

            <div class="mini-cart-list">

                <?php foreach ($cart as $item): ?>

                    <div class="mini-cart-item">

                        <img
                            src="<?= cart_h(food_image_path($item["image"] ?? "")) ?>"
                            alt=""
                            onerror="this.src='kanankelogo.png'"
                        >

                        <div class="mini-cart-details">

                            <strong>
                                <?= cart_h($item["name"] ?? "Product") ?>
                            </strong>

                            <small>
                                Qty: <?= (int)($item["quantity"] ?? 0) ?>
                            </small>

                        </div>

                        <span class="mini-cart-price">
                            ₱<?= number_format(
                                (float)($item["price"] ?? 0) *
                                (int)($item["quantity"] ?? 0),
                                2
                            ) ?>
                        </span>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>


        <div class="summary">

            <div class="summary-row">
                <span>Total Items</span>
                <strong><?= $cartCount ?></strong>
            </div>

            <div class="summary-row summary-total">
                <span>Total</span>
                <span>₱<?= number_format($cartTotal, 2) ?></span>
            </div>

            <a href="cart.php" class="checkout-button">
                View Cart & Checkout
            </a>

            <div class="browse-note">
                You can update quantities or remove items on your cart page.
            </div>

        </div>

    </aside>

</div>


<!-- MOBILE CART BUTTON -->

<a href="cart.php" class="mobile-cart">
    🛒 My Cart (<?= $cartCount ?>)
</a>

<?php if ($isAdmin): ?>
<script>
function openProductModal() {
    const modal = document.getElementById("productModal");
    if (modal) {
        modal.classList.add("open");
        document.body.style.overflow = "hidden";
        const field = document.getElementById("product_name");
        if (field) field.focus();
    }
}
function closeProductModal() {
    const modal = document.getElementById("productModal");
    if (modal) {
        modal.classList.remove("open");
        document.body.style.overflow = "";
    }
}
function closeProductModalFromOverlay(event) {
    if (event.target && event.target.id === "productModal") closeProductModal();
}
document.addEventListener("keydown", function(event) {
    if (event.key === "Escape") closeProductModal();
});
</script>
<?php endif; ?>

</body>
</html>
