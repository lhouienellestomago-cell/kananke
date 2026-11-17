<?php

session_start();

// Only logged-in users can access order history.
if (
    !isset($_SESSION["loggedIn"]) ||
    $_SESSION["loggedIn"] !== true
) {
    header("Location: login_page.php");
    exit;
}

// Connect to Railway database.
require_once "db.php";

// Get current user's ID.
if (!isset($_SESSION["user_id"])) {
    die("User session not found. Please log in again.");
}

$user_id = (int) $_SESSION["user_id"];

// Create CSRF token.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Handle order cancellation.
if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["cancel_order"])) {

    // Verify CSRF token.
    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $_SESSION["order_message"] = "Invalid request. Please try again.";
        $_SESSION["message_type"] = "error";

        header("Location: order_history.php");
        exit;
    }

    $order_id = filter_input(
        INPUT_POST,
        "order_id",
        FILTER_VALIDATE_INT
    );

    if (!$order_id || $order_id < 1) {
        $_SESSION["order_message"] = "Invalid order ID.";
        $_SESSION["message_type"] = "error";

        header("Location: order_history.php");
        exit;
    }

    // Cancel only if the order belongs to this user
    // and its current status is Pending.
    $cancel_sql = "UPDATE orders
                   SET status = 'Cancelled'
                   WHERE id = ?
                   AND user_id = ?
                   AND LOWER(status) = 'pending'";

    $cancel_stmt = $conn->prepare($cancel_sql);

    $cancel_stmt->bind_param(
        "ii",
        $order_id,
        $user_id
    );

    $cancel_stmt->execute();

    if ($cancel_stmt->affected_rows > 0) {

        $_SESSION["order_message"] =
            "Your order has been successfully cancelled.";

        $_SESSION["message_type"] = "success";

    } else {

        $_SESSION["order_message"] =
            "Unable to cancel this order. It may already have been accepted, cancelled, or does not belong to you.";

        $_SESSION["message_type"] = "error";
    }

    $cancel_stmt->close();

    header("Location: order_history.php");
    exit;
}

// Get notification message.
$message = $_SESSION["order_message"] ?? "";
$message_type = $_SESSION["message_type"] ?? "";

unset(
    $_SESSION["order_message"],
    $_SESSION["message_type"]
);

// Get only this user's orders.
$sql = "SELECT *
        FROM orders
        WHERE user_id = ?
        ORDER BY order_date DESC";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Order History - Kananke Food Hauz</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(
                135deg,
                #fff7ed,
                #dcfce7
            );
            color: #172018;
        }

        header {
            background: white;
            padding: 16px 6%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 15px #0001;
        }

        .brand {
            font-size: 24px;
            font-weight: 800;
            color: #f97316;
        }

        .back {
            background: #111827;
            color: white;
            padding: 10px 16px;
            border-radius: 9px;
            text-decoration: none;
            font-weight: 600;
        }

        .back:hover {
            background: #374151;
        }

        .container {
            max-width: 1200px;
            margin: auto;
            padding: 45px 20px;
        }

        .title {
            text-align: center;
            font-size: 38px;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #667085;
            margin-bottom: 30px;
        }

        .table-container {
            background: white;
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 8px 25px #0001;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1050px;
        }

        th {
            background: #f97316;
            color: white;
            padding: 14px;
            text-align: left;
        }

        td {
            padding: 14px;
            border-bottom: 1px solid #eee;
        }

        tr:hover {
            background: #fff7ed;
        }

        .price {
            color: #f97316;
            font-weight: 800;
        }

        /* STATUS */

        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .pending {
            background: #fef3c7;
            color: #92400e;
        }

        .accepted {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .preparing {
            background: #ffedd5;
            color: #c2410c;
        }

        .completed {
            background: #dcfce7;
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

        /* CANCEL BUTTON */

        .cancel-btn {
            background: #dc2626;
            color: white;
            border: none;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
        }

        .cancel-btn:hover {
            background: #991b1b;
        }

        .cancelled-text {
            color: #991b1b;
            font-size: 13px;
            font-weight: bold;
        }

        .no-cancel {
            color: #9ca3af;
            font-size: 13px;
        }

        /* NOTIFICATION */

        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }

        .success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .empty {
            text-align: center;
            padding: 40px;
            color: #667085;
        }

        .empty h2 {
            margin-bottom: 10px;
            color: #172018;
        }

        @media(max-width: 700px) {

            .title {
                font-size: 30px;
            }

            header {
                padding: 16px 4%;
            }

            .brand {
                font-size: 20px;
            }

        }

    </style>

</head>

<body>

    <!-- HEADER -->

    <header>

        <div class="brand">
            🍽 Chekananke
        </div>

        <a href="index.php" class="back">
            Back to Home
        </a>

    </header>

    <!-- MAIN -->

    <div class="container">

        <h1 class="title">
            My Order History
        </h1>

        <p class="subtitle">
            View and manage the orders you have placed.
        </p>

        <div class="table-container">

            <!-- NOTIFICATION -->

            <?php if ($message !== ""): ?>

                <div class="message <?php echo htmlspecialchars($message_type); ?>">

                    <?php echo htmlspecialchars($message); ?>

                </div>

            <?php endif; ?>

            <?php if ($result->num_rows > 0): ?>

                <table>

                    <thead>

                        <tr>
                            <th>Order ID</th>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Total</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php while ($row = $result->fetch_assoc()): ?>

                            <?php

                            $status = trim(
                                (string) ($row["status"] ?? "")
                            );

                            $status_key = strtolower($status);

                            $status_class = match ($status_key) {
                                "pending" => "pending",
                                "accepted" => "accepted",
                                "preparing" => "preparing",
                                "completed" => "completed",
                                "cancelled" => "cancelled",
                                default => "unknown"
                            };

                            ?>

                            <tr>

                                <td>
                                    #<?php echo htmlspecialchars((string) $row["id"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row["product_name"]); ?>
                                </td>

                                <td>
                                    ₱<?php echo number_format((float) $row["price"], 2); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars((string) $row["quantity"]); ?>
                                </td>

                                <td class="price">
                                    ₱<?php echo number_format((float) $row["total"], 2); ?>
                                </td>

                                <td>

                                    <?php

                                    $orderDate = new DateTime(
                                        $row["order_date"],
                                        new DateTimeZone("UTC")
                                    );

                                    $orderDate->setTimezone(
                                        new DateTimeZone("Asia/Manila")
                                    );

                                    echo htmlspecialchars(
                                        $orderDate->format("M d, Y h:i A")
                                    );

                                    ?>

                                </td>

                                <!-- STATUS -->

                                <td>

                                    <span class="status <?php echo $status_class; ?>">

                                        <?php
                                        echo htmlspecialchars(
                                            $status !== "" ? $status : "Unknown"
                                        );
                                        ?>

                                    </span>

                                </td>

                                <!-- ACTION -->

                                <td>

                                    <?php if ($status_key === "pending"): ?>

                                        <form
                                            method="POST"
                                            action="order_history.php"
                                            onsubmit="return confirm('Are you sure you want to cancel this order?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="order_id"
                                                value="<?php echo (int) $row["id"]; ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="cancel_order"
                                                class="cancel-btn"
                                            >
                                                Cancel Order
                                            </button>

                                        </form>

                                    <?php elseif ($status_key === "cancelled"): ?>

                                        <span class="cancelled-text">
                                            Order Cancelled
                                        </span>

                                    <?php else: ?>

                                        <span class="no-cancel">
                                            Not Available
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <div class="empty">

                    <h2>No Orders Yet</h2>

                    <p>
                        Your orders will appear here
                        after you place an order.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </div>

</body>

</html>

<?php

$stmt->close();
$conn->close();

?>
