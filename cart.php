<?php
require_once __DIR__ . '/cart_helper.php';

// Only logged-in customers can access the cart.
if (($_SESSION['loggedIn'] ?? false) !== true) {
    header('Location: login_page.php');
    exit;
}

// Connect to the existing database.
require_once __DIR__ . '/db.php';

$userId = (int)($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    die('User session not found. Please log in again.');
}

$error = '';

$customerName = trim(
    (string)($_SESSION['name'] ?? $_SESSION['full_name'] ?? '')
);

$phone = '';
$address = '';

// Handle checkout.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {

    if (!cart_verify_csrf()) {
        $error = 'Your session expired. Please refresh the page.';

    } elseif (empty($_SESSION['cart'])) {
        $error = 'Your cart is empty.';

    } else {

        $customerName = trim((string)($_POST['customer_name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));

        if (
            $customerName === '' ||
            $phone === '' ||
            $address === ''
        ) {
            $error = 'Please complete all customer information.';

        } elseif (
            strlen($customerName) > 150 ||
            strlen($phone) > 30 ||
            strlen($address) > 1000
        ) {
            $error = 'Some customer information is too long.';

        } else {

            $stmt = null;

            try {

                // Begin database transaction.
                $conn->begin_transaction();

                $stmt = $conn->prepare(
                    "INSERT INTO orders
                    (
                        user_id,
                        product_name,
                        price,
                        quantity,
                        customer_name,
                        phone,
                        address,
                        total
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );

                $lastOrderId = 0;

                foreach ($_SESSION['cart'] as $item) {

                    $name = trim((string)($item['name'] ?? ''));
                    $price = (float)($item['price'] ?? 0);
                    $quantity = (int)($item['quantity'] ?? 0);

                    if (
                        $name === '' ||
                        $price <= 0 ||
                        $quantity < 1 ||
                        $quantity > 100
                    ) {
                        throw new RuntimeException(
                            'Invalid cart item.'
                        );
                    }

                    $lineTotal = $price * $quantity;

                    $stmt->bind_param(
                        'isdisssd',
                        $userId,
                        $name,
                        $price,
                        $quantity,
                        $customerName,
                        $phone,
                        $address,
                        $lineTotal
                    );

                    $stmt->execute();

                    $lastOrderId = (int)$conn->insert_id;
                }

                // Save all items together in the database transaction.
                $conn->commit();

                $stmt->close();
                $stmt = null;

                // Empty the cart after successful checkout.
                $_SESSION['cart'] = [];

                // Redirect to the existing order success page.
                header(
                    'Location: order_success.php?id=' .
                    urlencode((string)$lastOrderId) .
                    '&cart=1'
                );
                exit;

            } catch (Throwable $e) {

                // Undo database changes if checkout fails.
                try {
                    $conn->rollback();
                } catch (Throwable $rollbackError) {
                    error_log(
                        'Cart rollback error: ' .
                        $rollbackError->getMessage()
                    );
                }

                if ($stmt instanceof mysqli_stmt) {
                    $stmt->close();
                }

                error_log(
                    'Cart checkout error: ' .
                    $e->getMessage()
                );

                $error = 'Your order could not be saved. Please try again.';
            }
        }
    }
}

// Get current cart information.
$cart = $_SESSION['cart'];
$grandTotal = cart_total();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>My Cart | Kananke Food Hauz</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #fffaf3;
    color: #292929;
}

/* HEADER */

header {
    background: white;
    padding: 16px 5%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 14px #0001;
    position: sticky;
    top: 0;
    z-index: 100;
}

header a {
    color: #292929;
    text-decoration: none;
    font-weight: bold;
}

.brand {
    font-size: 21px;
    color: #e99f00;
}

/* MAIN */

main {
    width: min(1050px, 94%);
    margin: 32px auto;
}

.heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.heading h1 {
    margin: 0;
}

.back {
    color: #a16f00;
    text-decoration: none;
    font-weight: bold;
}

/* NOTICES */

.notice,
.error {
    padding: 12px 15px;
    border-radius: 9px;
    margin: 12px 0;
    background: #fff0d5;
    color: #8a5b00;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

/* LAYOUT */

.layout {
    display: grid;
    grid-template-columns:
        minmax(0, 1.5fr)
        minmax(270px, .8fr);
    gap: 20px;
}

.panel {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 22px #0000000a;
}

/* CART ITEM */

.cart-item {
    display: grid;
    grid-template-columns: 86px minmax(0, 1fr) auto;
    gap: 14px;
    align-items: center;
    padding: 14px 0;
    border-bottom: 1px solid #eee;
}

.cart-item img {
    width: 86px;
    height: 75px;
    object-fit: cover;
    border-radius: 10px;
    background: #fff4d8;
}

.cart-item h3 {
    font-size: 15px;
    margin: 0 0 5px;
}

.muted {
    color: #888;
    font-size: 12px;
}

.line-price {
    font-weight: bold;
    color: #d89500;
    white-space: nowrap;
}

/* ACTION BUTTONS */

.actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.qty {
    width: 66px;
    padding: 7px;
    border: 1px solid #ddd;
    border-radius: 7px;
}

.small-btn {
    border: 0;
    border-radius: 7px;
    padding: 8px 10px;
    background: #f3f3f3;
    cursor: pointer;
}

.small-btn:hover {
    background: #e8e8e8;
}

.remove {
    color: #b42318;
    background: #fff0ef;
}

.remove:hover {
    background: #ffdddd;
}

/* SUMMARY */

.total-row {
    display: flex;
    justify-content: space-between;
    padding: 9px 0;
}

.grand {
    font-size: 20px;
    font-weight: bold;
    color: #d89500;
    border-top: 1px solid #eee;
    margin-top: 8px;
    padding-top: 15px;
}

/* CUSTOMER INFORMATION */

.field {
    margin: 12px 0;
}

.field label {
    display: block;
    font-weight: bold;
    font-size: 13px;
    margin-bottom: 6px;
}

.field input,
.field textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font: inherit;
}

.field input:focus,
.field textarea:focus {
    outline: none;
    border-color: #f5b400;
    box-shadow: 0 0 0 2px #f5b40022;
}

.field textarea {
    resize: vertical;
}

/* CHECKOUT */

.checkout {
    width: 100%;
    border: 0;
    border-radius: 9px;
    background: #f5b400;
    color: white;
    font-weight: bold;
    padding: 14px;
    cursor: pointer;
    margin-top: 10px;
    font-size: 15px;
}

.checkout:hover {
    background: #e5a300;
}

/* EMPTY CART */

.empty {
    text-align: center;
    padding: 45px 15px;
}

.empty .emoji {
    font-size: 55px;
}

.empty a {
    display: inline-block;
    margin-top: 12px;
    background: #f5b400;
    color: white;
    text-decoration: none;
    padding: 11px 18px;
    border-radius: 8px;
    font-weight: bold;
}

/* CLEAR CART */

.clear-form {
    text-align: right;
    margin-top: 15px;
}

.clear-form button {
    border: 0;
    background: none;
    color: #b42318;
    cursor: pointer;
    font-size: 13px;
}

.clear-form button:hover {
    text-decoration: underline;
}

/* MOBILE RESPONSIVE */

@media(max-width: 700px) {

    header {
        padding: 14px 4%;
    }

    .brand {
        font-size: 18px;
    }

    header a:last-child {
        font-size: 12px;
    }

    main {
        margin: 20px auto;
    }

    .layout {
        grid-template-columns: 1fr;
    }

    .panel {
        padding: 15px;
    }

    .cart-item {
        grid-template-columns: 66px minmax(0, 1fr);
        gap: 10px;
    }

    .cart-item img {
        width: 66px;
        height: 66px;
    }

    .line-price {
        grid-column: 2;
        margin-top: -5px;
    }

    .heading h1 {
        font-size: 24px;
    }

    .actions {
        gap: 6px;
    }

    .qty {
        width: 58px;
    }

    .checkout {
        padding: 15px;
    }
}

</style>

</head>

<body>

<header>

    <a class="brand" href="index.php">
        KANANKE.
    </a>

    <a href="index.php">
        ← Continue shopping
    </a>

</header>

<main>

    <div class="heading">

        <h1>
            My Shopping Cart 🛒
            (<?= cart_item_count() ?>)
        </h1>

        <a class="back" href="tray.php">
            Browse food menu
        </a>

    </div>

    <?php if (isset($_GET['added'])): ?>

        <div class="notice">
            Product added to your cart successfully.
        </div>

    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>

        <div class="notice">
            Your cart has been updated.
        </div>

    <?php endif; ?>

    <?php if ($error !== ''): ?>

        <div class="error">
            <?= cart_h($error) ?>
        </div>

    <?php endif; ?>


    <?php if (empty($cart)): ?>

        <div class="panel empty">

            <div class="emoji">🛒</div>

            <h2>Your cart is empty</h2>

            <p class="muted">
                Browse our food trays, bundles, and additionals.
            </p>

            <a href="tray.php">
                Start Shopping
            </a>

        </div>

    <?php else: ?>


    <div class="layout">

        <!-- CART ITEMS -->

        <section class="panel">

            <h2>Cart Items</h2>

            <?php foreach ($cart as $id => $item): ?>

                <article class="cart-item">

                    <img
                        src="<?= cart_h(
                            !empty($item['image'])
                                ? $item['image']
                                : 'kanankelogo.png'
                        ) ?>"
                        alt="<?= cart_h($item['name'] ?? 'Product') ?>"
                        onerror="this.src='kanankelogo.png'"
                    >

                    <div>

                        <h3>
                            <?= cart_h($item['name'] ?? 'Product') ?>
                        </h3>

                        <div class="muted">

                            <?= cart_h($item['category'] ?? 'Product') ?>

                            · ₱<?= number_format(
                                (float)$item['price'],
                                2
                            ) ?> each

                        </div>

                        <!-- UPDATE QUANTITY -->

                        <div class="actions">

                            <form
                                method="POST"
                                action="cart_action.php"
                                class="actions"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= cart_h(
                                        $_SESSION['cart_csrf']
                                    ) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="update"
                                >

                                <input
                                    type="hidden"
                                    name="product_id"
                                    value="<?= cart_h($id) ?>"
                                >

                                <input
                                    class="qty"
                                    type="number"
                                    name="quantity"
                                    min="1"
                                    max="100"
                                    value="<?= (int)$item['quantity'] ?>"
                                    aria-label="Quantity"
                                    required
                                >

                                <button
                                    class="small-btn"
                                    type="submit"
                                >
                                    Update
                                </button>

                            </form>

                            <!-- REMOVE ITEM -->

                            <form
                                method="POST"
                                action="cart_action.php"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= cart_h(
                                        $_SESSION['cart_csrf']
                                    ) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="remove"
                                >

                                <input
                                    type="hidden"
                                    name="product_id"
                                    value="<?= cart_h($id) ?>"
                                >

                                <button
                                    class="small-btn remove"
                                    type="submit"
                                >
                                    Remove
                                </button>

                            </form>

                        </div>

                    </div>

                    <div class="line-price">

                        ₱<?= number_format(
                            (float)$item['price'] *
                            (int)$item['quantity'],
                            2
                        ) ?>

                    </div>

                </article>

            <?php endforeach; ?>


            <!-- CLEAR CART -->

            <form
                class="clear-form"
                method="POST"
                action="cart_action.php"
                onsubmit="return confirm('Remove all items from your cart?');"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= cart_h($_SESSION['cart_csrf']) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="clear"
                >

                <button type="submit">
                    Clear cart
                </button>

            </form>

        </section>


        <!-- ORDER SUMMARY -->

        <aside class="panel">

            <h2>Order Summary</h2>

            <div class="total-row">

                <span>Items</span>

                <strong>
                    <?= cart_item_count() ?>
                </strong>

            </div>

            <div class="total-row grand">

                <span>Total</span>

                <span>
                    ₱<?= number_format($grandTotal, 2) ?>
                </span>

            </div>


            <h3>Customer Information</h3>

            <!-- CHECKOUT FORM -->

            <form method="POST" action="cart.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= cart_h($_SESSION['cart_csrf']) ?>"
                >

                <div class="field">

                    <label for="customer_name">
                        Full Name
                    </label>

                    <input
                        id="customer_name"
                        name="customer_name"
                        type="text"
                        maxlength="150"
                        value="<?= cart_h($customerName) ?>"
                        placeholder="Enter your full name"
                        required
                    >

                </div>


                <div class="field">

                    <label for="phone">
                        Phone Number
                    </label>

                    <input
                        id="phone"
                        name="phone"
                        type="tel"
                        maxlength="30"
                        value="<?= cart_h($phone) ?>"
                        placeholder="Enter your phone number"
                        required
                    >

                </div>


                <div class="field">

                    <label for="address">
                        Delivery Address
                    </label>

                    <textarea
                        id="address"
                        name="address"
                        rows="3"
                        maxlength="1000"
                        placeholder="Enter your complete delivery address"
                        required
                    ><?= cart_h($address) ?></textarea>

                </div>


                <button
                    class="checkout"
                    type="submit"
                    name="checkout"
                    value="1"
                >

                    Place Order · ₱<?= number_format(
                        $grandTotal,
                        2
                    ) ?>

                </button>

            </form>

            <p class="muted">
                Your items will be saved to your existing order history.
            </p>

        </aside>

    </div>

    <?php endif; ?>

</main>

</body>
</html>
