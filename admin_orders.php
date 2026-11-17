
<?php
session_start();

// ADMIN ACCESS ONLY
if (
    !isset($_SESSION["loggedIn"]) ||
    $_SESSION["loggedIn"] !== true ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: login.php");
    exit;
}

require_once "db.php";

$conn->query("SET time_zone = '+00:00'");

// CSRF TOKEN
if (empty($_SESSION["admin_orders_csrf"])) {
    $_SESSION["admin_orders_csrf"] = bin2hex(random_bytes(32));
}

// FLASH MESSAGE
function orderRedirect($message, $type = "success") {
    $_SESSION["admin_orders_message"] = $message;
    $_SESSION["admin_orders_type"] = $type;
    header("Location: admin_orders.php");
    exit;
}

// HANDLE STATUS UPDATE
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["admin_orders_csrf"], $token)
    ) {
        orderRedirect("Invalid request. Please try again.", "error");
    }

    $order_id = filter_var(
        $_POST["order_id"] ?? "",
        FILTER_VALIDATE_INT
    );

    $new_status = trim($_POST["status"] ?? "");

    $allowed_statuses = [
        "Approved",
        "Preparing",
        "Completed",
        "Cancelled"
    ];

    if (!$order_id || $order_id < 1) {
        orderRedirect("Invalid order ID.", "error");
    }

    if (!in_array($new_status, $allowed_statuses, true)) {
        orderRedirect("Invalid order status.", "error");
    }

    // APPROVE ONLY PENDING ORDERS.
    if ($new_status === "Approved") {

        $stmt = $conn->prepare(
            "UPDATE orders
             SET status = 'Approved'
             WHERE id = ?
               AND LOWER(TRIM(status)) = 'pending'"
        );

        $stmt->bind_param("i", $order_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $stmt->close();
            orderRedirect("Order #{$order_id} has been approved.");
        }

        $stmt->close();

        orderRedirect(
            "This order could not be approved. It may no longer be Pending.",
            "error"
        );
    }

    // OTHER ADMIN STATUS UPDATES
    $stmt = $conn->prepare(
        "UPDATE orders
         SET status = ?
         WHERE id = ?"
    );

    $stmt->bind_param("si", $new_status, $order_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $stmt->close();
        orderRedirect("Order #{$order_id} status updated to {$new_status}.");
    }

    $stmt->close();

    // Check whether the order exists even if status was unchanged.
    $check = $conn->prepare(
        "SELECT id FROM orders WHERE id = ?"
    );

    $check->bind_param("i", $order_id);
    $check->execute();

    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if ($exists) {
        orderRedirect("The order already has that status.", "success");
    }

    orderRedirect("Order not found.", "error");
}

// GET FLASH MESSAGE
$message = $_SESSION["admin_orders_message"] ?? "";
$message_type = $_SESSION["admin_orders_type"] ?? "";

unset(
    $_SESSION["admin_orders_message"],
    $_SESSION["admin_orders_type"]
);

// GET ALL ORDERS
$sql = "
    SELECT
        o.id,
        o.user_id,
        o.product_name,
        o.price,
        o.quantity,
        o.total,
        o.order_date,
        o.status,
        u.full_name,
        u.email
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    ORDER BY
        CASE
            WHEN LOWER(TRIM(o.status)) = 'pending' THEN 0
            ELSE 1
        END,
        o.order_date DESC
";

$result = $conn->query($sql);

// FORMAT DATABASE UTC TIME TO PHILIPPINE TIME
function formatOrderDate($timestamp) {
    if (empty($timestamp)) {
        return "N/A";
    }

    try {
        $date = new DateTimeImmutable(
            $timestamp,
            new DateTimeZone("UTC")
        );

        return $date
            ->setTimezone(new DateTimeZone("Asia/Manila"))
            ->format("M d, Y h:i A");

    } catch (Exception $e) {
        return $timestamp;
    }
}

function orderStatusClass($status) {
    return match (strtolower(trim((string)$status))) {
        "pending" => "pending",
        "approved", "accepted" => "approved",
        "preparing" => "preparing",
        "completed" => "completed",
        "cancelled" => "cancelled",
        default => "unknown"
    };
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Order Management - Kananke Food Hauz</title>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
}

body {
    min-height: 100vh;
    background: linear-gradient(135deg, #fff7ed, #dcfce7);
    color: #172018;
}

header {
    background: white;
    padding: 16px 6%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    box-shadow: 0 2px 15px #0001;
}

.brand {
    font-size: 24px;
    font-weight: 800;
    color: #f97316;
}

.back-btn {
    background: #111827;
    color: white;
    padding: 11px 16px;
    border-radius: 9px;
    text-decoration: none;
    font-weight: bold;
}

.back-btn:hover {
    background: #374151;
}

.container {
    max-width: 1450px;
    margin: auto;
    padding: 40px 20px;
}

h1 {
    text-align: center;
    font-size: 36px;
    margin-bottom: 10px;
}

.subtitle {
    text-align: center;
    color: #667085;
    margin-bottom: 30px;
}

.message {
    padding: 14px;
    border-radius: 9px;
    margin-bottom: 20px;
    text-align: center;
    font-weight: bold;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

.table-container {
    background: white;
    padding: 20px;
    border-radius: 16px;
    box-shadow: 0 8px 25px #0001;
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 1200px;
    border-collapse: collapse;
}

th {
    background: #f97316;
    color: white;
    padding: 14px;
    text-align: left;
    white-space: nowrap;
}

td {
    padding: 14px;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

tr:hover td {
    background: #fffaf5;
}

.customer-name {
    font-weight: bold;
}

.customer-email {
    color: #667085;
    font-size: 12px;
    margin-top: 5px;
    overflow-wrap: anywhere;
}

.price {
    color: #f97316;
    font-weight: bold;
    white-space: nowrap;
}

.status {
    display: inline-block;
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    white-space: nowrap;
}

.pending {
    background: #fef3c7;
    color: #92400e;
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
    background: #e5e7eb;
    color: #374151;
}

.action-form {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 140px;
}

.action-form select {
    padding: 9px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    background: white;
}

.update-btn {
    padding: 9px 12px;
    background: #2563eb;
    color: white;
    border: 0;
    border-radius: 7px;
    font-weight: bold;
    cursor: pointer;
}

.update-btn:hover {
    background: #1d4ed8;
}

.approve-btn {
    padding: 9px 12px;
    background: #16a34a;
    color: white;
    border: 0;
    border-radius: 7px;
    font-weight: bold;
    cursor: pointer;
}

.approve-btn:hover {
    background: #15803d;
}

.empty {
    text-align: center;
    padding: 40px;
    color: #667085;
}

@media(max-width: 600px) {
    header {
        padding: 15px 4%;
    }

    .brand {
        font-size: 20px;
    }

    .back-btn {
        font-size: 13px;
    }

    .container {
        padding: 28px 12px;
    }

    h1 {
        font-size: 29px;
    }

    .table-container {
        padding: 12px;
    }
}
</style>
</head>

<body>

<header>
    <div class="brand">🍽 Chekananke</div>
    <a href="admin_dashboard.php" class="back-btn">
        Back to Dashboard
    </a>
</header>

<main class="container">

    <h1>Order Management</h1>

    <p class="subtitle">
        Review customer orders and manage their approval status.
    </p>

    <?php if ($message !== ""): ?>
        <div class="message <?= htmlspecialchars($message_type, ENT_QUOTES, "UTF-8") ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <div class="table-container">

        <?php if ($result->num_rows > 0): ?>

            <table>

                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Total</th>
                        <th>Order Date</th>
                        <th>Status</th>
                        <th>Manage Order</th>
                    </tr>
                </thead>

                <tbody>

                <?php while ($order = $result->fetch_assoc()): ?>

                    <?php
                    $orderId = (int)$order["id"];
                    $status = trim((string)($order["status"] ?? "Pending"));
                    $statusKey = strtolower($status);
                    ?>

                    <tr>

                        <td>
                            <strong>#<?= $orderId ?></strong>
                        </td>

                        <td>
                            <div class="customer-name">
                                <?= htmlspecialchars(
                                    $order["full_name"] ?: "Customer",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </div>

                            <div class="customer-email">
                                <?= htmlspecialchars(
                                    $order["email"] ?? "No email",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </div>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $order["product_name"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </td>

                        <td>
                            ₱<?= number_format((float)$order["price"], 2) ?>
                        </td>

                        <td>
                            <?= (int)$order["quantity"] ?>
                        </td>

                        <td class="price">
                            ₱<?= number_format((float)$order["total"], 2) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                formatOrderDate($order["order_date"]),
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </td>

                        <td>
                            <span class="status <?= orderStatusClass($status) ?>">
                                <?= htmlspecialchars($status, ENT_QUOTES, "UTF-8") ?>
                            </span>
                        </td>

                        <td>

                            <?php if ($statusKey === "pending"): ?>

                                <form method="POST" class="action-form">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars(
                                            $_SESSION["admin_orders_csrf"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="order_id"
                                        value="<?= $orderId ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="status"
                                        value="Approved"
                                    >

                                    <button
                                        type="submit"
                                        class="approve-btn"
                                        onclick="return confirm('Approve order #<?= $orderId ?>?')"
                                    >
                                        ✓ Approve Order
                                    </button>

                                </form>

                                <form method="POST" class="action-form">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars(
                                            $_SESSION["admin_orders_csrf"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="order_id"
                                        value="<?= $orderId ?>"
                                    >

                                    <select name="status" required>
                                        <option value="Cancelled">Cancelled</option>
                                    </select>

                                    <button
                                        type="submit"
                                        class="update-btn"
                                        onclick="return confirm('Cancel order #<?= $orderId ?>?')"
                                    >
                                        Update Status
                                    </button>

                                </form>

                            <?php elseif ($statusKey !== "cancelled" && $statusKey !== "completed"): ?>

                                <form method="POST" class="action-form">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars(
                                            $_SESSION["admin_orders_csrf"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="order_id"
                                        value="<?= $orderId ?>"
                                    >

                                    <select name="status" required>

                                        <?php if ($statusKey === "approved" || $statusKey === "accepted"): ?>
                                            <option value="Approved" selected>Approved</option>
                                        <?php endif; ?>

                                        <?php if ($statusKey === "preparing"): ?>
                                            <option value="Preparing" selected>Preparing</option>
                                        <?php endif; ?>

                                        <?php if ($statusKey === "approved" || $statusKey === "accepted"): ?>
                                            <option value="Preparing">Preparing</option>
                                        <?php endif; ?>

                                        <option value="Completed">Completed</option>
                                        <option value="Cancelled">Cancelled</option>

                                    </select>

                                    <button type="submit" class="update-btn">
                                        Update Status
                                    </button>

                                </form>

                            <?php else: ?>

                                <strong>
                                    <?= $statusKey === "completed" ? "Order Finished" : "Order Cancelled" ?>
                                </strong>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

        <?php else: ?>

            <div class="empty">
                <h2>No Orders Found</h2>
                <p>Customer orders will appear here when they place an order.</p>
            </div>

        <?php endif; ?>

    </div>

</main>

</body>
</html>

<?php
$conn->close();
?>
