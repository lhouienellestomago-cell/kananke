<?php
session_start();

// Only logged-in admin accounts can manage products.
$isAdmin = (
    isset($_SESSION["loggedIn"]) &&
    $_SESSION["loggedIn"] === true &&
    ($_SESSION["role"] ?? "") === "admin"
);

$file = __DIR__ . "/bundle_products.json";
$uploadDir = __DIR__ . "/uploads/bundle/";
$uploadUrl = "uploads/bundle/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

$products = [
    "Bundle" => [
        "name" => "Barkada Bundle",
        "description" => "perfect for sharing with barkada.",
        "price" => 5000,
        "image" => "images/barkada_bundle.jpg"
    ],
    "bundle" => [
        "name" => "Family Bundle",
        "description" => "A delicious bundle for the whole family.",
        "price" => 1500,
        "image" => "images/bundle.jpg"
    ]
];

$added = [];

if (file_exists($file)) {
    $saved = json_decode(file_get_contents($file), true);

    if (is_array($saved)) {
        $added = $saved;
    }
}

$error = "";

// Process adding products only for admin accounts.
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_product"])) {

    // Server-side protection: customers cannot add products.
    if (!$isAdmin) {
        http_response_code(403);
        exit("Access denied. Only administrators can add food bundles.");
    }

    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price = filter_var($_POST["price"] ?? "", FILTER_VALIDATE_FLOAT);

    if (
        $name === "" ||
        $description === "" ||
        $price === false ||
        $price <= 0
    ) {
        $error = "Enter the product name, description, and a valid price.";

    } elseif (
        !isset($_FILES["image"]) ||
        $_FILES["image"]["error"] !== UPLOAD_ERR_OK
    ) {
        $error = "Please choose a product image.";

    } elseif ($_FILES["image"]["size"] > 5 * 1024 * 1024) {
        $error = "The image must be 5 MB or smaller.";

    } else {
        $tmp = $_FILES["image"]["tmp_name"];
        $ext = strtolower(
            pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION)
        );

        $allowed = ["jpg", "jpeg", "png", "webp"];
        $imageInfo = getimagesize($tmp);

        if (
            $imageInfo === false ||
            !in_array($ext, $allowed, true)
        ) {
            $error = "Use a valid JPG, JPEG, PNG, or WEBP image.";

        } else {
            $imageName = uniqid("bundle_", true) . "." . $ext;

            if (move_uploaded_file($tmp, $uploadDir . $imageName)) {

                $id = "bundle-" . bin2hex(random_bytes(6));

                $added[$id] = [
                    "name" => $name,
                    "description" => $description,
                    "price" => (float) $price,
                    "image" => $uploadUrl . $imageName
                ];

                $savedSuccessfully = file_put_contents(
                    $file,
                    json_encode(
                        $added,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                    ),
                    LOCK_EX
                );

                if ($savedSuccessfully !== false) {
                    header("Location: bundle.php?added=1");
                    exit;
                }

                unset($added[$id]);
                @unlink($uploadDir . $imageName);

                $error = "Could not save the product. Check folder permissions.";

            } else {
                $error = "Could not upload the image.";
            }
        }
    }
}

// Combine original products with admin-added products.
$products = $products + $added;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Food Bundle | Kananke Food Hauz</title>

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
    color: white;
    text-decoration: none;
    font-weight: bold;
    margin: 0 14px;
}

.container {
    width: 90%;
    max-width: 1450px;
    margin: 35px auto;
}

h2 {
    text-align: center;
    margin-bottom: 30px;
}

.product-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 25px;
}

.product-card {
    background: white;
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
    padding: 11px 17px;
    background: #f97316;
    color: white;
    text-decoration: none;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
}

.button:hover,
.plus:hover {
    background: #ea580c;
}

.add-card {
    min-height: 360px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border: 2px dashed #f97316;
    border-radius: 12px;
    cursor: pointer;
}

.add-card:hover {
    background: #fff0e5;
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

.close-button:hover {
    background: #555;
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
    color: #b91c1c;
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
        margin: 6px 8px;
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
</nav>

<main class="container">

    <h2>Food Bundle</h2>

    <?php if (isset($_GET["added"]) && $isAdmin): ?>
        <div class="notice success">
            Product added successfully!
        </div>
    <?php endif; ?>

    <?php if ($error !== "" && $isAdmin): ?>
        <div class="notice error">
            <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <div class="product-grid">

        <?php foreach ($products as $id => $product): ?>

            <article class="product-card">

                <img
                    src="<?= htmlspecialchars($product["image"], ENT_QUOTES, "UTF-8") ?>"
                    alt="<?= htmlspecialchars($product["name"], ENT_QUOTES, "UTF-8") ?>"
                    onerror="this.style.display='none';"
                >

                <div class="product-info">

                    <h3>
                        <?= htmlspecialchars($product["name"], ENT_QUOTES, "UTF-8") ?>
                    </h3>

                    <p class="description">
                        <?= htmlspecialchars($product["description"] ?? "", ENT_QUOTES, "UTF-8") ?>
                    </p>

                    <p class="price">
                        ₱<?= number_format((float) $product["price"], 2) ?>
                    </p>

                    <a
                        class="button"
                        href="order.php?product=<?= urlencode((string) $id) ?>"
                    >
                        Order Now
                    </a>

                </div>

            </article>

        <?php endforeach; ?>

        <?php if ($isAdmin): ?>

            <!-- Only the admin can see the Add Product button. -->
            <button
                type="button"
                class="add-card"
                onclick="openModal()"
                aria-label="Add a food bundle"
            >
                <span class="plus">+</span>
            </button>

        <?php endif; ?>

    </div>

</main>

<?php if ($isAdmin): ?>

<!-- Only the admin receives the Add Product form. -->
<div class="modal" id="productModal">

    <div class="modal-content">

        <h2>Add New Food Bundle</h2>

        <form method="POST" enctype="multipart/form-data">

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

            <label for="image">Product Image</label>
            <input
                type="file"
                id="image"
                name="image"
                accept=".jpg,.jpeg,.png,.webp"
                required
            >

            <div class="modal-actions">

                <button
                    class="button"
                    type="submit"
                    name="add_product"
                    value="1"
                >
                    Add Product
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

<?php endif; ?>

<footer>
    <p>
        &copy; <?= date("Y") ?> Kananke Food Hauz. All rights reserved.
    </p>
</footer>

<?php if ($isAdmin): ?>
<script>
function openModal() {
    document.getElementById("productModal").style.display = "block";
}

function closeModal() {
    document.getElementById("productModal").style.display = "none";
}

window.addEventListener("click", function(event) {
    const modal = document.getElementById("productModal");

    if (event.target === modal) {
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

</body>
</html>
