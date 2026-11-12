<?php
session_start();

$pageTitle = "Food by Tray";
$pageHeading = "Our Food Trays";

$isAdmin = (
    isset($_SESSION["loggedIn"]) &&
    $_SESSION["loggedIn"] === true &&
    ($_SESSION["role"] ?? "") === "admin"
);

$catalogFile = __DIR__ . "/food_tray_products.json";
$uploadDir = __DIR__ . "/uploads/food_tray/";
$uploadUrl = "uploads/food_tray/";

// Create upload directory if needed
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

// Original products
$originalProducts = [
    "chicken" => [
        "name" => "Fried Chicken Tray",
        "description" => "A delicious tray of fried chicken.",
        "price" => 800,
        "image" => "chickentray.png"
    ],
    "bbq" => [
        "name" => "Chicken Cordon Bleu",
        "description" => "Crispy chicken cordon bleu, perfect for sharing.",
        "price" => 800,
        "image" => "chickencordon.png"
    ]
];

// Load added products
$addedProducts = [];

if (is_file($catalogFile)) {
    $decoded = json_decode(file_get_contents($catalogFile), true);

    if (is_array($decoded)) {
        $addedProducts = $decoded;
    }
}

$error = "";

// Only administrators can add or edit products
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["save_product"])
) {
    if (!$isAdmin) {
        http_response_code(403);
        exit("Access denied. Only administrators can manage products.");
    }

    $action = $_POST["form_action"] ?? "add";
    $productId = trim($_POST["product_id"] ?? "");
    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price = filter_var(
        $_POST["price"] ?? "",
        FILTER_VALIDATE_FLOAT
    );

    if (!in_array($action, ["add", "edit"], true)) {
        $error = "Invalid product action.";
    } elseif (
        $name === "" ||
        $description === "" ||
        $price === false ||
        $price <= 0
    ) {
        $error = "Please enter a product name, description, and valid price.";
    } elseif (
        $action === "edit" &&
        !isset($addedProducts[$productId])
    ) {
        $error = "Product not found. Only added products can be edited.";
    } else {
        $oldProduct = (
            $action === "edit"
            ? $addedProducts[$productId]
            : null
        );

        $imagePath = $oldProduct["image"] ?? "";

        $hasUpload = isset($_FILES["image"]) &&
            $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE;

        if ($action === "add" && !$hasUpload) {
            $error = "Please select a product image.";
        } elseif ($hasUpload) {
            if ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {
                $error = "The image upload failed. Please try again.";
            } elseif (
                $_FILES["image"]["size"] > 5 * 1024 * 1024
            ) {
                $error = "The image must be 5 MB or smaller.";
            } else {
                $tmp = $_FILES["image"]["tmp_name"];

                $ext = strtolower(
                    pathinfo(
                        $_FILES["image"]["name"],
                        PATHINFO_EXTENSION
                    )
                );

                $allowed = ["jpg", "jpeg", "png", "webp"];
                $imageInfo = @getimagesize($tmp);

                if (
                    $imageInfo === false ||
                    !in_array($ext, $allowed, true)
                ) {
                    $error = "Please use a valid JPG, JPEG, PNG, or WEBP image.";
                } else {
                    $imageName = bin2hex(random_bytes(16)) . "." . $ext;

                    if (
                        move_uploaded_file(
                            $tmp,
                            $uploadDir . $imageName
                        )
                    ) {
                        $imagePath = $uploadUrl . $imageName;
                    } else {
                        $error = "The image could not be saved. Check folder permissions.";
                    }
                }
            }
        }

        if ($error === "") {
            if ($action === "add") {
                $productId = "tray-" . bin2hex(random_bytes(6));
            }

            $previous = $addedProducts[$productId] ?? null;

            $addedProducts[$productId] = [
                "name" => $name,
                "description" => $description,
                "price" => (float) $price,
                "image" => $imagePath
            ];

            $saved = file_put_contents(
                $catalogFile,
                json_encode(
                    $addedProducts,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                ),
                LOCK_EX
            );

            if ($saved !== false) {
                header(
                    "Location: " .
                    basename($_SERVER["PHP_SELF"]) .
                    "?saved=1"
                );
                exit;
            }

            if ($previous !== null) {
                $addedProducts[$productId] = $previous;
            } else {
                unset($addedProducts[$productId]);
            }

            $error = "Could not save the product. Check folder permissions.";
        }
    }
}

// Combine original and added products
$allProducts = $originalProducts + $addedProducts;
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= htmlspecialchars($pageTitle) ?> | Kananke Food Hauz</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #fffaf3;
    color: #333;
}

nav {
    background: #f97316;
    padding: 20px;
    text-align: center;
}

nav a {
    color: #fff;
    text-decoration: none;
    font-weight: bold;
    margin: 0 12px;
}

.container {
    width: 90%;
    max-width: 1450px;
    margin: 35px auto;
}

h1, h2 {
    text-align: center;
}

.container > h1 {
    margin-bottom: 30px;
}

.product-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 25px;
}

.product-card {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 3px 12px #00000015;
    min-width: 0;
}

.product-card img {
    display: block;
    width: 100%;
    height: 220px;
    object-fit: cover;
    background: #eee;
}

.product-info {
    padding: 18px;
}

.product-info h3 {
    margin-top: 0;
}

.description {
    color: #666;
}

.price {
    color: #f97316;
    font-weight: bold;
    font-size: 20px;
}

.button {
    display: inline-block;
    padding: 10px 14px;
    background: #f97316;
    color: white;
    text-decoration: none;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
    font-size: 14px;
}

.button:hover,
.plus:hover {
    background: #ea580c;
}

.edit-button {
    margin-left: 6px;
}

.add-card {
    min-height: 360px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    border: 2px dashed #f97316;
    border-radius: 12px;
    cursor: pointer;
}

.add-card:hover {
    background: #fff7ed;
}

.plus {
    width: 75px;
    height: 75px;
    border: none;
    border-radius: 50%;
    background: #f97316;
    color: white;
    font-size: 48px;
    line-height: 1;
    cursor: pointer;
}

.modal {
    display: none;
    position: fixed;
    z-index: 10;
    inset: 0;
    background: #0009;
    padding: 20px;
    overflow-y: auto;
}

.modal-content {
    max-width: 500px;
    margin: 35px auto;
    padding: 25px;
    background: white;
    border-radius: 12px;
}

.modal-content h2,
.modal-content label {
    color: #f97316;
}

.modal-content label {
    display: block;
    margin: 14px 0 6px;
    font-weight: bold;
}

.modal-content input,
.modal-content textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font: inherit;
}

.modal-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 20px;
}

.close-button {
    background: #777;
}

.notice {
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 6px;
    text-align: center;
}

.success {
    background: #e6f7e9;
    color: #17652a;
}

.error {
    background: #fde8e8;
    color: #9b1c1c;
}

footer {
    margin-top: 40px;
    padding: 20px;
    background: #f97316;
    color: white;
    text-align: center;
}

@media (max-width: 900px) {
    .product-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .product-grid {
        grid-template-columns: 1fr;
    }

    .add-card {
        min-height: 180px;
    }

    nav a {
        display: inline-block;
        margin: 6px;
    }
}
</style>
</head>

<body>

<nav>
    <a href="index.php">Home</a>
    <a href="tray.php">Food by Tray</a>
    <a href="bundle.php">Food Bundle</a>
    <a href="additionals.php">Additionals</a>
    <a href="order_history.php">My Orders</a>

    <?php if ($isAdmin): ?>
        <a href="admin_dashboard.php">Admin Dashboard</a>
    <?php endif; ?>
</nav>

<main class="container">

    <h1><?= htmlspecialchars($pageHeading) ?></h1>

    <?php if (isset($_GET["saved"])): ?>
        <div class="notice success">
            Product saved successfully!
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="notice error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="product-grid">

        <?php foreach ($allProducts as $id => $product): ?>

            <article class="product-card">

                <img
                    src="<?= htmlspecialchars($product["image"]) ?>"
                    alt="<?= htmlspecialchars($product["name"]) ?>"
                    onerror="this.style.display='none';"
                >

                <div class="product-info">

                    <h3>
                        <?= htmlspecialchars($product["name"]) ?>
                    </h3>

                    <p class="description">
                        <?= htmlspecialchars($product["description"] ?? "") ?>
                    </p>

                    <p class="price">
                        ₱<?= number_format((float)$product["price"], 2) ?>
                    </p>

                    <a
                        class="button"
                        href="order.php?product=<?= urlencode((string)$id) ?>"
                    >
                        Order Now
                    </a>

                    <?php if ($isAdmin && isset($addedProducts[$id])): ?>

                        <button
                            type="button"
                            class="button edit-button"
                            onclick='editProduct(
                                <?= json_encode((string)$id) ?>,
                                <?= json_encode(
                                    $product["name"],
                                    JSON_HEX_TAG |
                                    JSON_HEX_APOS |
                                    JSON_HEX_QUOT |
                                    JSON_HEX_AMP
                                ) ?>,
                                <?= json_encode(
                                    $product["description"] ?? "",
                                    JSON_HEX_TAG |
                                    JSON_HEX_APOS |
                                    JSON_HEX_QUOT |
                                    JSON_HEX_AMP
                                ) ?>,
                                <?= json_encode((float)$product["price"]) ?>
                            )'
                        >
                            Edit
                        </button>

                    <?php endif; ?>

                </div>
            </article>

        <?php endforeach; ?>

        <?php if ($isAdmin): ?>

            <button
                type="button"
                class="add-card"
                onclick="openAddModal()"
                aria-label="Add a product"
            >
                <span class="plus">+</span>
            </button>

        <?php endif; ?>

    </div>
</main>

<?php if ($isAdmin): ?>

<div class="modal" id="productModal">

    <div class="modal-content">

        <h2 id="modalTitle">Add New Product</h2>

        <form method="POST" enctype="multipart/form-data">

            <input type="hidden" name="product_id" id="product_id">
            <input type="hidden" name="form_action" id="form_action" value="add">

            <label for="name">Product Name</label>
            <input
                type="text"
                id="name"
                name="name"
                maxlength="150"
                required
            >

            <label for="description">Description</label>
            <textarea
                id="description"
                name="description"
                rows="3"
                maxlength="1000"
                required
            ></textarea>

            <label for="price">Price (₱)</label>
            <input
                type="number"
                id="price"
                name="price"
                min="0.01"
                step="0.01"
                required
            >

            <label for="image">
                Product Image
                <span id="imageHint">(required)</span>
            </label>

            <input
                type="file"
                id="image"
                name="image"
                accept=".jpg,.jpeg,.png,.webp"
            >

            <div class="modal-actions">

                <button
                    class="button"
                    type="submit"
                    name="save_product"
                    value="1"
                >
                    Save Product
                </button>

                <button
                    class="button close-button"
                    type="button"
                    onclick="closeModal()"
                >
                    Cancel
                </button>

            </div>

        </form>

    </div>
</div>

<script>
function openModal() {
    document.getElementById("productModal").style.display = "block";
}

function openAddModal() {
    document.getElementById("form_action").value = "add";
    document.getElementById("product_id").value = "";

    document.getElementById("modalTitle").textContent =
        "Add New Product";

    document.getElementById("name").value = "";
    document.getElementById("description").value = "";
    document.getElementById("price").value = "";
    document.getElementById("image").value = "";

    document.getElementById("image").required = true;

    document.getElementById("imageHint").textContent =
        "(required)";

    openModal();
}

function editProduct(id, name, description, price) {
    document.getElementById("form_action").value = "edit";
    document.getElementById("product_id").value = id;

    document.getElementById("modalTitle").textContent =
        "Edit Product";

    document.getElementById("name").value = name;
    document.getElementById("description").value = description;
    document.getElementById("price").value = price;
    document.getElementById("image").value = "";

    document.getElementById("image").required = false;

    document.getElementById("imageHint").textContent =
        "(optional; leave blank to keep current image)";

    openModal();
}

function closeModal() {
    document.getElementById("productModal").style.display = "none";
}

window.addEventListener("click", function(event) {
    if (event.target === document.getElementById("productModal")) {
        closeModal();
    }
});

window.addEventListener("keydown", function(event) {
    if (event.key === "Escape") {
        closeModal();
    }
});
</script>

<?php endif; ?>

<footer>
    <p>
        &copy; <?= date("Y") ?> Kananke Food Hauz. All rights reserved.
    </p>
</footer>

</body>
</html>
