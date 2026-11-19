<?php
session_start();

// Check login
if (
    !isset($_SESSION["loggedIn"]) ||
    $_SESSION["loggedIn"] !== true
) {
    header("Location: login_page.php");
    exit;
}

require_once "db.php";

// Get user ID
$user_id = (int)($_SESSION["user_id"] ?? 0);

if ($user_id <= 0) {
    die("User session not found. Please log in again.");
}

// CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Escape helper
function order_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Handle order cancellation
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["cancel_order"])) {

    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $_SESSION["order_message"] = "Invalid request. Please try again.";
        $_SESSION["message_type"] = "error";

        header("Location: order_history.php");
        exit;
    }

    $order_id = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT);

    if (!$order_id || $order_id < 1) {
        $_SESSION["order_message"] = "Invalid order ID.";
        $_SESSION["message_type"] = "error";

        header("Location: order_history.php");
        exit;
    }

    $cancel_sql = "
        UPDATE orders
        SET status = 'Cancelled'
        WHERE id = ?
        AND user_id = ?
        AND LOWER(TRIM(status)) = 'pending'
    ";

    $cancel_stmt = $conn->prepare($cancel_sql);

    if (!$cancel_stmt) {
        die("Unable to process cancellation.");
    }

    $cancel_stmt->bind_param("ii", $order_id, $user_id);
    $cancel_stmt->execute();

    if ($cancel_stmt->affected_rows > 0) {
        $_SESSION["order_message"] = "Your order has been successfully cancelled.";
        $_SESSION["message_type"] = "success";
    } else {
        $_SESSION["order_message"] = "Unable to cancel this order. Only Pending orders can be cancelled.";
        $_SESSION["message_type"] = "error";
    }

    $cancel_stmt->close();

    header("Location: order_history.php");
    exit;
}

// Get notification
$message = $_SESSION["order_message"] ?? "";
$message_type = $_SESSION["message_type"] ?? "";

unset($_SESSION["order_message"], $_SESSION["message_type"]);

// Get orders
$sql = "
    SELECT *
    FROM orders
    WHERE user_id = ?
    ORDER BY order_date DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Unable to load your orders.");
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$total_orders = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Order History - Kananke Food Hauz</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

:root {
    --orange: #f5a900;
    --orange-dark: #e69500;
    --yellow-light: #fff8df;
    --dark: #292929;
    --gray: #777;
    --border: #eeeeee;
    --white: #ffffff;
}

body {
    min-height: 100vh;
    background: #fffaf0;
    color: var(--dark);
}

/* SIDEBAR */

.sidebar {
    width: 245px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    background: white;
    padding: 25px 17px;
    border-right: 1px solid #f0eadb;
    display: flex;
    flex-direction: column;
    z-index: 100;
}

.logo {
    font-size: 22px;
    font-weight: 900;
    color: var(--orange-dark);
    margin: 5px 8px 35px;
}

.logo span {
    display: block;
    color: #555;
    font-size: 11px;
    font-weight: 500;
    margin-top: 5px;
}

.nav-title {
    font-size: 11px;
    color: #aaa;
    font-weight: bold;
    letter-spacing: 1px;
    padding: 0 12px;
    margin-bottom: 12px;
}

.nav-links {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.nav-links a {
    text-decoration: none;
    color: #555;
    padding: 13px 14px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    font-weight: 600;
    transition: .2s;
}

.nav-links a:hover {
    background: #fff5d6;
    color: #d68c00;
}

.nav-links a.active {
    background: #fff0bf;
    color: #b97800;
}

.nav-icon {
    width: 23px;
    text-align: center;
    font-size: 18px;
}

.sidebar-bottom {
    margin-top: auto;
    border-top: 1px solid var(--border);
    padding-top: 15px;
}

.sidebar-bottom a {
    display: block;
    text-decoration: none;
    color: #777;
    padding: 12px;
    font-size: 14px;
    font-weight: 600;
}

/* MAIN CONTENT */

.main {
    margin-left: 245px;
    padding: 28px 35px;
    min-height: 100vh;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 30px;
}

.topbar h2 {
    font-size: 23px;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.cart-link {
    text-decoration: none;
    background: white;
    color: #333;
    border: 1px solid #eee;
    padding: 11px 15px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: bold;
}

.cart-link:hover {
    background: #fff4d2;
}

.page-heading {
    margin-bottom: 25px;
}

.page-heading h1 {
    font-size: 30px;
    margin-bottom: 8px;
}

.page-heading p {
    color: var(--gray);
    font-size: 14px;
}

/* SUMMARY */

.summary-card {
    background: linear-gradient(120deg, #ffc72c, #ffdf75);
    padding: 23px 25px;
    border-radius: 18px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    box-shadow: 0 5px 15px #e9ae2820;
}

.summary-card h3 {
    font-size: 16px;
    margin-bottom: 8px;
}

.summary-card p {
    font-size: 13px;
    color: #654800;
}

.summary-number {
    font-size: 36px;
    font-weight: 900;
    color: #704900;
}

/* NOTIFICATIONS */

.message {
    padding: 14px 16px;
    border-radius: 11px;
    margin-bottom: 20px;
    font-size: 14px;
    font-weight: bold;
}

.message.success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
}

.message.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

/* ORDER SECTION */

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 17px;
    gap: 10px;
}

.section-header h2 {
    font-size: 19px;
}

.order-count {
    font-size: 12px;
    color: #777;
    background: white;
    padding: 7px 12px;
    border-radius: 20px;
    border: 1px solid #eee;
}

/* ORDER CARD */

.orders-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.order-card {
    background: white;
    border: 1px solid #f0eadb;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 14px #00000008;
}

.order-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f0f0f0;
}

.order-id {
    font-size: 14px;
    font-weight: 800;
}

.order-date {
    font-size: 12px;
    color: #888;
    margin-top: 5px;
}

.status {
    display: inline-block;
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
    text-transform: capitalize;
}

.pending {
    background: #fff1c2;
    color: #966500;
}

.approved {
    background: #dcfce7;
    color: #166534;
}

.preparing {
    background: #dbeafe;
    color: #1d4ed8;
}

.completed {
    background: #bbf7d0;
    color: #166534;
}

.cancelled {
    background: #fee2e2;
    color: #991b1b;
}

.unknown {
    background: #eeeeee;
    color: #555;
}

.order-details {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 15px;
    padding: 20px 0;
}

.detail-label {
    font-size: 11px;
    color: #999;
    margin-bottom: 7px;
    text-transform: uppercase;
    font-weight: bold;
}

.detail-value {
    font-size: 14px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.product-name {
    color: #333;
}

.price {
    color: #d68c00;
    font-size: 17px;
    font-weight: 900;
}

/* ACTIONS */

.order-actions {
    border-top: 1px solid #f0f0f0;
    padding-top: 14px;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
}

.cancel-btn {
    background: #ef4444;
    color: white;
    border: none;
    padding: 10px 16px;
    border-radius: 9px;
    font-size: 12px;
    font-weight: bold;
    cursor: pointer;
}

.cancel-btn:hover {
    background: #dc2626;
}

.cancelled-text {
    color: #dc2626;
    font-size: 12px;
    font-weight: bold;
}

.no-cancel {
    color: #aaa;
    font-size: 12px;
}

/* EMPTY STATE */

.empty {
    background: white;
    border: 1px dashed #e5d8b6;
    border-radius: 17px;
    padding: 55px 20px;
    text-align: center;
}

.empty-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.empty h2 {
    font-size: 20px;
    margin-bottom: 8px;
}

.empty p {
    font-size: 13px;
    color: #888;
    margin-bottom: 20px;
}

.shop-btn {
    display: inline-block;
    background: var(--orange);
    color: #222;
    padding: 12px 20px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 800;
}

/* TABLET */

@media(max-width: 1000px) {
    .sidebar {
        width: 205px;
    }

    .main {
        margin-left: 205px;
        padding: 25px 20px;
    }

    .order-details {
        grid-template-columns: 2fr 1fr;
    }
}

/* MOBILE */

@media(max-width: 650px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
        padding: 13px 14px;
        border-right: none;
        border-bottom: 1px solid #eee;
    }

    .logo {
        margin: 3px 5px 13px;
        font-size: 19px;
    }

    .logo span {
        display: none;
    }

    .nav-title,
    .sidebar-bottom {
        display: none;
    }

    .nav-links {
        flex-direction: row;
        overflow-x: auto;
        gap: 7px;
        padding-bottom: 3px;
    }

    .nav-links a {
        flex: 0 0 auto;
        padding: 10px 12px;
        font-size: 12px;
        gap: 6px;
    }

    .nav-icon {
        font-size: 15px;
        width: auto;
    }

    .main {
        margin-left: 0;
        padding: 19px 13px 35px;
    }

    .topbar {
        margin-bottom: 23px;
    }

    .topbar h2 {
        font-size: 18px;
    }

    .cart-link {
        padding: 9px 11px;
        font-size: 12px;
    }

    .page-heading h1 {
        font-size: 25px;
    }

    .summary-card {
        padding: 18px;
    }

    .summary-number {
        font-size: 30px;
    }

    .order-card {
        padding: 15px;
    }

    .order-top {
        align-items: flex-start;
    }

    .order-details {
        grid-template-columns: 1fr 1fr;
        gap: 17px 12px;
    }

    .detail-value {
        font-size: 13px;
    }

    .price {
        font-size: 15px;
    }

    .order-actions {
        justify-content: flex-start;
    }

    .cancel-btn {
        width: 100%;
    }

    .section-header h2 {
        font-size: 17px;
    }
}

@media(max-width: 360px) {
    .nav-links a {
        padding: 9px;
        font-size: 11px;
    }

    .order-top {
        flex-direction: column;
    }
}
</style>
</head>

<body>

<!-- SIDEBAR -->
<aside class="sidebar">

    <div class="logo">
        🍽 Kananke Food Hauz
        <span>Food ordering system</span>
    </div>

    <div class="nav-title">MAIN MENU</div>

    <nav class="nav-links">

        <a href="index.php">
            <span class="nav-icon">🏠</span>
            Dashboard
        </a>

        <a href="tray.php">
            <span class="nav-icon">🍱</span>
            Food Tray
        </a>

        <a href="bundle.php">
            <span class="nav-icon">🎁</span>
            Bundles
        </a>

        <a href="additionals.php">
            <span class="nav-icon">🍟</span>
            Additionals
        </a>

        <a href="order_history.php" class="active">
            <span class="nav-icon">📋</span>
            Order History
        </a>

        <a href="contact_support.php">
            <span class="nav-icon">💬</span>
            Contact Support
        </a>

    </nav>

    <div class="sidebar-bottom">
        <a href="logout.php">🚪 Logout</a>
    </div>

</aside>

<!-- MAIN -->
<main class="main">

    <div class="topbar">

        <h2>My Orders</h2>

        <div class="topbar-right">
            <a href="cart.php" class="cart-link">
                🛒 My Cart
            </a>
        </div>

    </div>

    <div class="page-heading">
        <h1>Order History</h1>
        <p>Track and manage your food orders.</p>
    </div>

    <!-- SUMMARY -->
    <div class="summary-card">

        <div>
            <h3>Your Order Activity</h3>
            <p>All your previous and current orders in one place.</p>
        </div>

        <div class="summary-number">
            <?php echo $total_orders; ?>
        </div>

    </div>

    <!-- NOTIFICATION -->
    <?php if ($message !== ""): ?>
        <div class="message <?php echo $message_type === "success" ? "success" : "error"; ?>">
            <?php echo order_h($message); ?>
        </div>
    <?php endif; ?>

    <div class="section-header">
        <h2>Recent Orders</h2>

        <span class="order-count">
            <?php echo $total_orders; ?>
            <?php echo $total_orders === 1 ? "Order" : "Orders"; ?>
        </span>
    </div>

    <?php if ($total_orders > 0): ?>

        <div class="orders-list">

            <?php while ($row = $result->fetch_assoc()): ?>

                <?php
                    $status = trim((string)($row["status"] ?? ""));
                    $status_key = strtolower($status);

                    $status_class = match ($status_key) {
                        "pending" => "pending",
                        "approved", "accepted" => "approved",
                        "preparing" => "preparing",
                        "completed" => "completed",
                        "cancelled" => "cancelled",
                        default => "unknown"
                    };

                    $display_date = "Date unavailable";

                    if (!empty($row["order_date"])) {
                        try {
                            $orderDate = new DateTime(
                                $row["order_date"],
                                new DateTimeZone("UTC")
                            );

                            $orderDate->setTimezone(
                                new DateTimeZone("Asia/Manila")
                            );

                            $display_date = $orderDate->format("M d, Y h:i A");
                        } catch (Exception $e) {
                            $display_date = $row["order_date"];
                        }
                    }
                ?>

                <div class="order-card">

                    <div class="order-top">

                        <div>
                            <div class="order-id">
                                Order #<?php echo (int)$row["id"]; ?>
                            </div>

                            <div class="order-date">
                                📅 <?php echo order_h($display_date); ?>
                            </div>
                        </div>

                        <span class="status <?php echo $status_class; ?>">
                            <?php echo order_h($status !== "" ? $status : "Unknown"); ?>
                        </span>

                    </div>

                    <div class="order-details">

                        <div>
                            <div class="detail-label">Product</div>
                            <div class="detail-value product-name">
                                <?php echo order_h($row["product_name"] ?? ""); ?>
                            </div>
                        </div>

                        <div>
                            <div class="detail-label">Price</div>
                            <div class="detail-value">
                                ₱<?php echo number_format((float)($row["price"] ?? 0), 2); ?>
                            </div>
                        </div>

                        <div>
                            <div class="detail-label">Quantity</div>
                            <div class="detail-value">
                                <?php echo (int)($row["quantity"] ?? 0); ?>
                            </div>
                        </div>

                        <div>
                            <div class="detail-label">Total Amount</div>
                            <div class="price">
                                ₱<?php echo number_format((float)($row["total"] ?? 0), 2); ?>
                            </div>
                        </div>

                    </div>

                    <div class="order-actions">

                        <?php if ($status_key === "pending"): ?>

                            <form
                                method="POST"
                                action="order_history.php"
                                onsubmit="return confirm('Are you sure you want to cancel this order?');"
                            >

                                <input
                                    type="hidden"
                                    name="order_id"
                                    value="<?php echo (int)$row["id"]; ?>"
                                >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?php echo order_h($_SESSION["csrf_token"]); ?>"
                                >

                                <button
                                    type="submit"
                                    name="cancel_order"
                                    class="cancel-btn"
                                >
                                    ✕ Cancel Order
                                </button>

                            </form>

                        <?php elseif ($status_key === "cancelled"): ?>

                            <span class="cancelled-text">
                                ✕ Order Cancelled
                            </span>

                        <?php else: ?>

                            <span class="no-cancel">
                                No available actions
                            </span>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <div class="empty">

            <div class="empty-icon">🛍️</div>

            <h2>No Orders Yet</h2>

            <p>Your orders will appear here after you place an order.</p>

            <a href="tray.php" class="shop-btn">
                Start Ordering
            </a>

        </div>

    <?php endif; ?>

</main>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
