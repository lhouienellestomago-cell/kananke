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

// Product records are stored in Railway MySQL, not Render's temporary filesystem.
require_once __DIR__ . "/db.php";

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

$products = $config["products"];
$isAdmin = (($_SESSION["role"] ?? "") === "admin");
$adminMessage = "";
$adminMessageType = "success";

if (empty($_SESSION["catalog_csrf"])) {
    $_SESSION["catalog_csrf"] = bin2hex(random_bytes(32));
}

// Read legacy JSON products so products created before this upgrade remain visible.
$legacyJsonPath = __DIR__ . "/" . $config["json"];
if (is_file($legacyJsonPath)) {
    $legacyProducts = json_decode((string)file_get_contents($legacyJsonPath), true);
    if (is_array($legacyProducts)) {
        foreach ($legacyProducts as $legacyId => $legacyProduct) {
            if ($legacyId === "_deleted" || !is_array($legacyProduct) || !isset($legacyProduct["name"], $legacyProduct["price"])) continue;
            $legacyPrice = filter_var($legacyProduct["price"], FILTER_VALIDATE_FLOAT);
            if ($legacyPrice === false || $legacyPrice < 0) continue;
            $products[$legacyId] = [
                "name" => (string)$legacyProduct["name"],
                "description" => (string)($legacyProduct["description"] ?? ""),
                "price" => (float)$legacyPrice,
                "image" => (string)($legacyProduct["image"] ?? "")
            ];
        }
        foreach (($legacyProducts["_deleted"] ?? []) as $legacyDeletedId => $wasDeleted) {
            if ($wasDeleted) unset($products[$legacyDeletedId]);
        }
    }
}

// Load permanent product overrides and additions from Railway.
$loadProducts = $conn->prepare("SELECT id, product_key, name, description, price, image_data, image_path, is_deleted FROM catalog_products WHERE category = ?");
$loadProducts->bind_param("s", $config["prefix"]);
$loadProducts->execute();
$dbProducts = $loadProducts->get_result();
while ($row = $dbProducts->fetch_assoc()) {
    $productKey = (string)$row["product_key"];
    if ((int)$row["is_deleted"] === 1) {
        unset($products[$productKey]);
        continue;
    }
    $image = !empty($row["image_data"])
        ? "product_image.php?id=" . (int)$row["id"]
        : (string)($row["image_path"] ?? "");
    $products[$productKey] = [
        "name" => (string)$row["name"],
        "description" => (string)$row["description"],
        "price" => (float)$row["price"],
        "image" => $image
    ];
}
$loadProducts->close();

function catalog_upload_image_blob(string $field): ?array {
    if (!isset($_FILES[$field]) || ($_FILES[$field]["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if (($file["error"] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Image upload failed. Please try again.");
    }
    if (($file["size"] ?? 0) > 10 * 1024 * 1024) {
        throw new RuntimeException("Image must be 10MB or smaller.");
    }
    $info = @getimagesize($file["tmp_name"]);
    $allowed = ["image/jpeg", "image/png", "image/webp", "image/gif"];
    $mime = strtolower((string)($info["mime"] ?? ""));
    if (!$info || !in_array($mime, $allowed, true)) {
        throw new RuntimeException("Please upload a valid JPG, PNG, WEBP, or GIF image.");
    }
    $bytes = file_get_contents($file["tmp_name"]);
    if ($bytes === false) throw new RuntimeException("Could not read the uploaded image.");
    return ["data" => $bytes, "mime" => $mime];
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["catalog_action"])) {
    if (!$isAdmin) {
        http_response_code(403);
        exit("You are not allowed to manage products.");
    }
    if (!isset($_POST["catalog_csrf"]) || !hash_equals($_SESSION["catalog_csrf"], (string)$_POST["catalog_csrf"])) {
        http_response_code(400);
        exit("Invalid security token. Please refresh and try again.");
    }

    $action = (string)$_POST["catalog_action"];
    $id = trim((string)($_POST["product_id"] ?? ""));
    try {
        if ($action === "delete") {
            if ($id === "" || !isset($products[$id])) throw new RuntimeException("Product not found.");
            $old = $products[$id];
            $stmt = $conn->prepare("INSERT INTO catalog_products (product_key, category, name, description, price, image_path, is_deleted) VALUES (?, ?, ?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE is_deleted = 1");
            $stmt->bind_param("ssssds", $id, $config["prefix"], $old["name"], $old["description"], $old["price"], $old["image"]);
            $stmt->execute();
            $stmt->close();
            header("Location: " . basename($_SERVER["PHP_SELF"]) . "?deleted=1");
            exit;
        }

        if ($action !== "add" && $action !== "edit") throw new RuntimeException("Unknown product action.");
        $name = trim((string)($_POST["product_name"] ?? ""));
        $description = trim((string)($_POST["product_description"] ?? ""));
        $price = filter_var($_POST["product_price"] ?? null, FILTER_VALIDATE_FLOAT);
        if ($name === "" || mb_strlen($name) > 150) throw new RuntimeException("Enter a product name (maximum 150 characters).");
        if ($description === "" || mb_strlen($description) > 1000) throw new RuntimeException("Enter a description (maximum 1,000 characters).");
        if ($price === false || $price <= 0 || $price > 10000000) throw new RuntimeException("Enter a valid product price.");
        if ($action === "edit" && ($id === "" || !isset($products[$id]))) throw new RuntimeException("Product not found.");

        if ($action === "add") $id = $config["prefix"] . "-" . bin2hex(random_bytes(8));
        $uploaded = catalog_upload_image_blob("product_image");

        // Keep the existing image when an admin edits text/price only.
        $imageData = null;
        $imageMime = null;
        $imagePath = null;
        if ($uploaded !== null) {
            $imageData = $uploaded["data"];
            $imageMime = $uploaded["mime"];
        } elseif ($action === "edit") {
            $existing = $conn->prepare("SELECT image_data, image_mime, image_path FROM catalog_products WHERE category = ? AND product_key = ? LIMIT 1");
            $existing->bind_param("ss", $config["prefix"], $id);
            $existing->execute();
            $existingRow = $existing->get_result()->fetch_assoc();
            $existing->close();
            if ($existingRow && !empty($existingRow["image_data"])) {
                $imageData = $existingRow["image_data"];
                $imageMime = $existingRow["image_mime"];
            } else {
                $imagePath = $existingRow["image_path"] ?? ($products[$id]["image"] ?? null);
                if (is_string($imagePath) && str_starts_with($imagePath, "product_image.php?")) $imagePath = null;
            }
        }
        if ($action === "add" && $uploaded === null) throw new RuntimeException("Please choose a product image.");
        if ($action === "edit" && $uploaded === null && $imageData === null && !$imagePath) throw new RuntimeException("Please choose a product image.");

        $stmt = $conn->prepare("INSERT INTO catalog_products (product_key, category, name, description, price, image_data, image_mime, image_path, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0) ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), price = VALUES(price), image_data = IF(VALUES(image_data) IS NULL, image_data, VALUES(image_data)), image_mime = IF(VALUES(image_mime) IS NULL, image_mime, VALUES(image_mime)), image_path = IF(VALUES(image_data) IS NULL, VALUES(image_path), NULL), is_deleted = 0");
        $priceValue = round((float)$price, 2);
        $stmt->bind_param("ssssdsss", $id, $config["prefix"], $name, $description, $priceValue, $imageData, $imageMime, $imagePath);
        $stmt->execute();
        $stmt->close();

        header("Location: " . basename($_SERVER["PHP_SELF"]) . ($action === "add" ? "?added=1" : "?updated=1"));
        exit;
    } catch (Throwable $e) {
        error_log("Catalog management error: " . $e->getMessage());
        $adminMessage = $e->getMessage();
        $adminMessageType = "error";
    }
}

if (isset($_GET["added"])) $adminMessage = "Product added and saved permanently.";
if (isset($_GET["updated"])) $adminMessage = "Product updated successfully.";
if (isset($_GET["deleted"])) $adminMessage = "Product deleted successfully.";

// Refresh products from Railway after any request.
$products = $config["products"];
if (isset($legacyProducts) && is_array($legacyProducts)) {
    foreach ($legacyProducts as $legacyId => $legacyProduct) {
        if ($legacyId === "_deleted" || !is_array($legacyProduct) || !isset($legacyProduct["name"], $legacyProduct["price"])) continue;
        $legacyPrice = filter_var($legacyProduct["price"], FILTER_VALIDATE_FLOAT);
        if ($legacyPrice !== false && $legacyPrice >= 0) $products[$legacyId] = ["name" => (string)$legacyProduct["name"], "description" => (string)($legacyProduct["description"] ?? ""), "price" => (float)$legacyPrice, "image" => (string)($legacyProduct["image"] ?? "")];
    }
    foreach (($legacyProducts["_deleted"] ?? []) as $legacyDeletedId => $wasDeleted) if ($wasDeleted) unset($products[$legacyDeletedId]);
}
$refresh = $conn->prepare("SELECT id, product_key, name, description, price, image_data, image_path, is_deleted FROM catalog_products WHERE category = ?");
$refresh->bind_param("s", $config["prefix"]);
$refresh->execute();
$refreshResult = $refresh->get_result();
while ($row = $refreshResult->fetch_assoc()) {
    $key = (string)$row["product_key"];
    if ((int)$row["is_deleted"] === 1) { unset($products[$key]); continue; }
    $image = !empty($row["image_data"]) ? "product_image.php?id=" . (int)$row["id"] : (string)($row["image_path"] ?? "");
    $products[$key] = ["name" => (string)$row["name"], "description" => (string)$row["description"], "price" => (float)$row["price"], "image" => $image];
}
$refresh->close();

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

/* ADMIN PRODUCT MANAGEMENT */
.catalog-heading-actions { display:flex; align-items:center; gap:12px; }
.catalog-add-button,.catalog-save-button { border:0; border-radius:9px; background:#f28b28; color:#fff; padding:11px 16px; font-weight:700; cursor:pointer; }
.catalog-add-button:hover,.catalog-save-button:hover { background:#df7615; }
.catalog-admin-actions { display:flex; gap:8px; margin-top:12px; }
.catalog-admin-actions form { margin:0; }
.catalog-edit-button,.catalog-delete-button { border:0; border-radius:7px; padding:8px 10px; font-size:11px; font-weight:700; cursor:pointer; }
.catalog-edit-button { background:#fff0d9; color:#a95a00; }
.catalog-delete-button { background:#fff0f0; color:#c0392b; }
.catalog-message { padding:11px 14px; margin:0 0 15px; border-radius:8px; background:#eaf8ee; color:#24733a; font-size:12px; }
.catalog-message.error { background:#fff0f0; color:#b42318; }
.catalog-modal-overlay { display:none; position:fixed; inset:0; z-index:999; background:rgba(0,0,0,.48); padding:20px; align-items:center; justify-content:center; overflow-y:auto; }
.catalog-modal-overlay.open { display:flex; }
.catalog-modal { width:min(100%,480px); max-height:calc(100vh - 40px); overflow-y:auto; background:#fff; border-radius:16px; padding:22px; box-shadow:0 18px 60px rgba(0,0,0,.2); }
.catalog-modal-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
.catalog-modal-head h2 { margin:0; font-size:19px; }
.catalog-close { border:0; background:transparent; font-size:28px; color:#777; cursor:pointer; }
.catalog-label { display:block; margin:13px 0 6px; font-size:12px; font-weight:700; color:#444; }
.catalog-input { display:block; width:100%; border:1px solid #ddd; border-radius:8px; padding:11px; font-size:13px; outline:none; background:#fff; }
.catalog-input:focus { border-color:#f28b28; box-shadow:0 0 0 3px rgba(242,139,40,.12); }
.catalog-textarea { min-height:90px; resize:vertical; }
.catalog-file { padding:8px; }
.catalog-help { color:#888; font-size:10px; line-height:1.5; margin-top:6px; }
.catalog-save-button { width:100%; margin-top:20px; padding:13px; }
@media(max-width:650px) {
 .catalog-heading-actions { gap:7px; }
 .catalog-add-button { padding:9px 10px; font-size:10px; }
 .catalog-admin-actions { flex-wrap:wrap; }
 .catalog-modal { padding:17px; }
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
            <div class="catalog-heading-actions">
                <span><?= count($products) ?> products</span>
                <?php if ($isAdmin): ?>
                    <button type="button" class="catalog-add-button" onclick="openProductModal('add')">+ Add Product</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($adminMessage !== ""): ?>
            <div class="catalog-message <?= $adminMessageType === "error" ? "error" : "" ?>"><?= cart_h($adminMessage) ?></div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <div class="catalog-modal-overlay" id="productModal" onclick="closeProductModalOutside(event)">
            <div class="catalog-modal" role="dialog" aria-modal="true" aria-labelledby="productModalTitle">
                <div class="catalog-modal-head">
                    <h2 id="productModalTitle">Add New Product</h2>
                    <button type="button" class="catalog-close" onclick="closeProductModal()" aria-label="Close">&times;</button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="productForm">
                    <input type="hidden" name="catalog_csrf" value="<?= cart_h($_SESSION["catalog_csrf"]) ?>">
                    <input type="hidden" name="catalog_action" id="catalogAction" value="add">
                    <input type="hidden" name="product_id" id="catalogProductId" value="">
                    <label class="catalog-label" for="catalogName">Product Name</label>
                    <input class="catalog-input" type="text" name="product_name" id="catalogName" maxlength="150" required>
                    <label class="catalog-label" for="catalogDescription">Description</label>
                    <textarea class="catalog-input catalog-textarea" name="product_description" id="catalogDescription" maxlength="1000" required></textarea>
                    <label class="catalog-label" for="catalogPrice">Price (₱)</label>
                    <input class="catalog-input" type="number" name="product_price" id="catalogPrice" min="0.01" max="10000000" step="0.01" required>
                    <label class="catalog-label" for="catalogImage">Product Image <span id="imageOptionalNote">(required for new products)</span></label>
                    <input class="catalog-input catalog-file" type="file" name="product_image" id="catalogImage" accept="image/jpeg,image/png,image/webp,image/gif">
                    <div class="catalog-help">JPG, PNG, WEBP, or GIF. Maximum file size: 10MB. When editing, leave empty to keep the current image.</div>
                    <button class="catalog-save-button" type="submit">Save Product</button>
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

                        <?php if ($isAdmin): ?>
                        <div class="catalog-admin-actions">
                            <button type="button" class="catalog-edit-button"
                                onclick='openProductModal("edit", <?= json_encode((string)$id) ?>, <?= json_encode((string)$product["name"], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>, <?= json_encode((string)$product["description"], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>, <?= json_encode((string)$product["price"]) ?>)'>Edit Product</button>
                            <form method="POST" onsubmit="return confirm('Delete <?= cart_h(addslashes($product["name"])) ?>? This cannot be undone.');">
                                <input type="hidden" name="catalog_csrf" value="<?= cart_h($_SESSION["catalog_csrf"]) ?>">
                                <input type="hidden" name="catalog_action" value="delete">
                                <input type="hidden" name="product_id" value="<?= cart_h($id) ?>">
                                <button type="submit" class="catalog-delete-button">Delete</button>
                            </form>
                        </div>
                        <?php endif; ?>

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
function openProductModal(mode, id = "", name = "", description = "", price = "") {
    const overlay = document.getElementById("productModal");
    document.getElementById("catalogAction").value = mode;
    document.getElementById("catalogProductId").value = id;
    document.getElementById("productModalTitle").textContent = mode === "edit" ? "Edit Product" : "Add New Product";
    document.getElementById("catalogName").value = name;
    document.getElementById("catalogDescription").value = description;
    document.getElementById("catalogPrice").value = price;
    document.getElementById("catalogImage").required = mode === "add";
    document.getElementById("imageOptionalNote").textContent = mode === "edit" ? "(optional — leave empty to keep current image)" : "(required)";
    overlay.classList.add("open");
    document.body.style.overflow = "hidden";
}
function closeProductModal() {
    document.getElementById("productModal").classList.remove("open");
    document.body.style.overflow = "";
}
function closeProductModalOutside(event) {
    if (event.target.id === "productModal") closeProductModal();
}
document.addEventListener("keydown", function(event) {
    if (event.key === "Escape") closeProductModal();
});
</script>
<?php endif; ?>

</body>
</html>
