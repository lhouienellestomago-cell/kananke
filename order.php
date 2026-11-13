<?php
session_start();

require_once __DIR__ . "/db.php";

// Require the customer to log in first.
if (($_SESSION["loggedIn"] ?? false) !== true) {
    header("Location: login_page.php");
    exit;
}

$user_id = $_SESSION["user_id"] ?? null;

if (!$user_id) {
    die("User session is missing. Please log in again.");
}

// Existing products.
$products = [
    "chicken" => [
        "name" => "Fried Chicken Tray",
        "price" => 800,
        "image" => "chickentray.png"
    ],
    "bbq" => [
        "name" => "Chicken Cordon Bleu",
        "price" => 800,
        "image" => "chickencordon.png"
    ],
    "Bundle" => [
        "name" => "Barkada Bundle",
        "price" => 5000,
        "image" => "images/barkada_bundle.jpg"
    ],
    "bundle" => [
        "name" => "Family Bundle",
        "price" => 1500,
        "image" => "images/bundle.jpg"
    ],
    "fried-chicken" => [
        "name" => "Softdrinks",
        "price" => 80,
        "image" => "images/softdrinks.jpg"
    ],
    "dessert" => [
        "name" => "Macarons",
        "price" => 350,
        "image" => "images/Macarons.jpg"
    ]
];

// Load products added through tray.php.
$productsFile = __DIR__ . "/food_tray_products.json";

if (file_exists($productsFile)) {
    $addedProducts = json_decode(
        file_get_contents($productsFile),
        true
    );

    if (is_array($addedProducts)) {
        foreach ($addedProducts as $id => $product) {
            if (
                isset($product["name"], $product["price"], $product["image"]) &&
                is_numeric($product["price"]) &&
                (float) $product["price"] > 0
            ) {
                $products[$id] = [
                    "name" => (string) $product["name"],
                    "price" => (float) $product["price"],
                    "image" => (string) $product["image"]
                ];
            }
        }
    }
}

$product_id = $_GET["product"] ?? $_POST["product"] ?? "";

if (!is_string($product_id) || !isset($products[$product_id])) {
    die("Product not found. Please return to the Food by Tray page.");
}

$product = $products[$product_id];

$error = "";

$customer_name = "";
$phone = "";
$address = "";
$quantity = 1;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $customer_name = trim($_POST["customer_name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $quantity = filter_var(
        $_POST["quantity"] ?? 1,
        FILTER_VALIDATE_INT
    );

    if (
        $customer_name === "" ||
        $phone === "" ||
        $address === ""
    ) {
        $error = "Please complete all customer information.";
    } elseif (
        $quantity === false ||
        $quantity < 1 ||
        $quantity > 100
    ) {
        $error = "Please enter a quantity between 1 and 100.";
    } else {
        $price = (float) $product["price"];
        $total = $price * $quantity;

        // Save the order in the existing orders table.
        $stmt = $conn->prepare(
            "INSERT INTO orders
            (user_id, product_name, price, quantity,
             customer_name, phone, address, total)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            $error = "Could not prepare the order. Please try again.";
        } else {
            $stmt->bind_param(
                "isdisssd",
                $user_id,
                $product["name"],
                $price,
                $quantity,
                $customer_name,
                $phone,
                $address,
                $total
            );

            if ($stmt->execute()) {
                $order_id = $conn->insert_id;
                $stmt->close();

                header(
                    "Location: order_success.php?id=" .
                    urlencode((string) $order_id)
                );
                exit;
            } else {
                $error = "Your order could not be saved. Please try again.";
                $stmt->close();
            }
        }
    }
}

$total = (float) $product["price"] * (int) $quantity;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Place Order | Kananke Food Hauz</title>

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

        header {
            background: #f97316;
            color: white;
            text-align: center;
            padding: 22px;
        }

        header h1 {
            margin: 0 0 8px;
        }

        .container {
            width: 90%;
            max-width: 700px;
            margin: 30px auto;
        }

        .order-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px #00000015;
        }

        .product-image {
            display: block;
            width: 100%;
            max-height: 280px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 20px;
            background: #eee;
        }

        .price {
            font-size: 22px;
            font-weight: bold;
            color: #f97316;
        }

        label {
            display: block;
            margin: 15px 0 6px;
            font-weight: bold;
        }

        input,
        textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font: inherit;
        }

        textarea {
            resize: vertical;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-controls button {
            width: 42px;
            height: 42px;
            border: none;
            border-radius: 6px;
            background: #f97316;
            color: white;
            font-size: 22px;
            cursor: pointer;
        }

        .quantity-controls input {
            width: 90px;
            text-align: center;
        }

        .total {
            margin: 20px 0;
            font-size: 20px;
            font-weight: bold;
        }

        .button {
            display: inline-block;
            background: #f97316;
            color: white;
            border: none;
            padding: 12px 18px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
        }

        .button:hover {
            background: #f97316;
        }

        .back-link {
            display: inline-block;
            margin-top: 18px;
            color: #f97316;
            text-decoration: none;
        }

        .error {
            padding: 12px;
            margin-bottom: 15px;
            background: #fde8e8;
            color: #f97316;
            border-radius: 6px;
        }

        footer {
            margin-top: 40px;
            background: #f97316;
            color: white;
            padding: 18px;
            text-align: center;
        }
    </style>
</head>
<body>

<header>
    <h1>Kananke Food Hauz</h1>
    <p>Place Your Order</p>
</header>

<main class="container">
    <section class="order-card">

        <?php if ($error !== ""): ?>
            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <img
            class="product-image"
            src="<?= htmlspecialchars($product["image"]) ?>"
            alt="<?= htmlspecialchars($product["name"]) ?>"
            onerror="this.style.display='none';"
        >

        <h2><?= htmlspecialchars($product["name"]) ?></h2>

        <p class="price">
            ₱<?= number_format((float) $product["price"], 2) ?>
        </p>

        <form method="POST" action="order.php">
            <input
                type="hidden"
                name="product"
                value="<?= htmlspecialchars($product_id) ?>"
            >

            <label for="quantity">Quantity</label>

            <div class="quantity-controls">
                <button type="button" onclick="changeQuantity(-1)">−</button>

                <input
                    type="number"
                    id="quantity"
                    name="quantity"
                    min="1"
                    max="100"
                    value="<?= (int) $quantity ?>"
                    required
                >

                <button type="button" onclick="changeQuantity(1)">+</button>
            </div>

            <label for="customer_name">Full Name</label>
            <input
                type="text"
                id="customer_name"
                name="customer_name"
                maxlength="150"
                value="<?= htmlspecialchars($customer_name) ?>"
                required
            >

            <label for="phone">Phone Number</label>
            <input
                type="tel"
                id="phone"
                name="phone"
                maxlength="30"
                value="<?= htmlspecialchars($phone) ?>"
                required
            >

            <label for="address">Delivery Address</label>
            <textarea
                id="address"
                name="address"
                rows="3"
                maxlength="1000"
                required
            ><?= htmlspecialchars($address) ?></textarea>

            <p class="total">
                Total: ₱<span id="totalAmount"><?= number_format($total, 2) ?></span>
            </p>

            <button class="button" type="submit">
                Place Order
            </button>
        </form>

        <a class="back-link" href="tray.php">
            &larr; Back to Food by Tray
        </a>
    </section>
</main>

<footer>
    <p>&copy; <?= date("Y") ?> Kananke Food Hauz. All rights reserved.</p>
</footer>

<script>
    const quantityInput = document.getElementById("quantity");
    const totalAmount = document.getElementById("totalAmount");
    const unitPrice = <?= json_encode((float) $product["price"]) ?>;

    function updateTotal() {
        let quantity = parseInt(quantityInput.value, 10);

        if (isNaN(quantity) || quantity < 1) {
            quantity = 1;
        }

        if (quantity > 100) {
            quantity = 100;
        }

        totalAmount.textContent = (unitPrice * quantity).toLocaleString(
            "en-PH",
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );
    }

    function changeQuantity(amount) {
        let quantity = parseInt(quantityInput.value, 10) || 1;
        quantity += amount;

        if (quantity < 1) quantity = 1;
        if (quantity > 100) quantity = 100;

        quantityInput.value = quantity;
        updateTotal();
    }

    quantityInput.addEventListener("input", updateTotal);
</script>

</body>
</html>
