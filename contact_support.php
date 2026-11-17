<?php
session_start();

// =====================================
// CUSTOMER LOGIN CHECK
// =====================================

if (
    !isset($_SESSION["loggedIn"]) ||
    $_SESSION["loggedIn"] !== true
) {
    header("Location: login_page.php");
    exit;
}

// =====================================
// DATABASE CONNECTION
// =====================================

require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    die("User session not found. Please log in again.");
}

$user_id = (int) $_SESSION["user_id"];

// =====================================
// CSRF TOKEN
// =====================================

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// =====================================
// HANDLE CUSTOMER MESSAGE
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)
    ) {
        $_SESSION["support_message"] = "Invalid request. Please try again.";
        $_SESSION["support_message_type"] = "error";

        header("Location: contact_support.php");
        exit;
    }

    $customer_message = trim($_POST["message"] ?? "");
    $order_id_input = trim($_POST["order_id"] ?? "");

    if ($customer_message === "") {

        $_SESSION["support_message"] = "Please enter your message.";
        $_SESSION["support_message_type"] = "error";

    } elseif (strlen($customer_message) > 5000) {

        $_SESSION["support_message"] = "Your message is too long. Maximum is 5000 characters.";
        $_SESSION["support_message_type"] = "error";

    } else {

        // =================================
        // VALIDATE OPTIONAL ORDER
        // =================================

        $order_id = null;
        $valid_order = true;

        // General Concern and Other do not require an order ID.
        if (
            $order_id_input !== "" &&
            strtolower($order_id_input) !== "other"
        ) {

            $order_id = filter_var(
                $order_id_input,
                FILTER_VALIDATE_INT
            );

            if (!$order_id || $order_id < 1) {

                $valid_order = false;

            } else {

                // Make sure the selected order belongs to this customer.
                $check_sql = "SELECT id
                              FROM orders
                              WHERE id = ?
                              AND user_id = ?";

                $check_stmt = $conn->prepare($check_sql);

                $check_stmt->bind_param(
                    "ii",
                    $order_id,
                    $user_id
                );

                $check_stmt->execute();

                $check_result = $check_stmt->get_result();

                if ($check_result->num_rows === 0) {
                    $valid_order = false;
                }

                $check_stmt->close();
            }
        }

        if (!$valid_order) {

            $_SESSION["support_message"] = "Invalid order selected.";
            $_SESSION["support_message_type"] = "error";

        } else {

            // =================================
            // SAVE SUPPORT MESSAGE
            // =================================

            if ($order_id === null) {

                $insert_sql = "INSERT INTO support_messages
                               (user_id, order_id, message, status)
                               VALUES (?, NULL, ?, 'Open')";

                $insert_stmt = $conn->prepare($insert_sql);

                $insert_stmt->bind_param(
                    "is",
                    $user_id,
                    $customer_message
                );

            } else {

                $insert_sql = "INSERT INTO support_messages
                               (user_id, order_id, message, status)
                               VALUES (?, ?, ?, 'Open')";

                $insert_stmt = $conn->prepare($insert_sql);

                $insert_stmt->bind_param(
                    "iis",
                    $user_id,
                    $order_id,
                    $customer_message
                );
            }

            $insert_stmt->execute();
            $insert_stmt->close();

            $_SESSION["support_message"] = "Your message has been sent successfully.";
            $_SESSION["support_message_type"] = "success";
        }
    }

    header("Location: contact_support.php");
    exit;
}

// =====================================
// GET NOTIFICATION
// =====================================

$notification = $_SESSION["support_message"] ?? "";
$notification_type = $_SESSION["support_message_type"] ?? "";

unset(
    $_SESSION["support_message"],
    $_SESSION["support_message_type"]
);

// =====================================
// GET CUSTOMER ORDERS
// =====================================

$order_sql = "SELECT id, product_name, order_date
              FROM orders
              WHERE user_id = ?
              ORDER BY order_date DESC";

$order_stmt = $conn->prepare($order_sql);

$order_stmt->bind_param("i", $user_id);

$order_stmt->execute();

$orders = $order_stmt->get_result();

// =====================================
// GET CUSTOMER SUPPORT MESSAGES
// =====================================

$message_sql = "SELECT *
                FROM support_messages
                WHERE user_id = ?
                ORDER BY created_at DESC";

$message_stmt = $conn->prepare($message_sql);

$message_stmt->bind_param("i", $user_id);

$message_stmt->execute();

$support_messages = $message_stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Contact Support - Kananke Food Hauz</title>

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

        /* HEADER */

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

        /* MAIN */

        .container {
            max-width: 950px;
            margin: auto;
            padding: 40px 20px;
        }

        .page-title {
            text-align: center;
            font-size: 36px;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #667085;
            margin-bottom: 30px;
        }

        /* CARDS */

        .card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 6px 20px #0001;
            margin-bottom: 25px;
        }

        .card h2 {
            margin-bottom: 18px;
            color: #f97316;
        }

        /* FORM */

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }

        select,
        textarea {
            width: 100%;
            padding: 13px;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            margin-bottom: 18px;
            font-size: 15px;
        }

        textarea {
            min-height: 150px;
            resize: vertical;
        }

        select:focus,
        textarea:focus {
            outline: none;
            border-color: #f97316;
            box-shadow: 0 0 0 2px #f9731630;
        }

        .send-btn {
            background: #f97316;
            color: white;
            border: none;
            padding: 13px 22px;
            border-radius: 9px;
            font-weight: bold;
            cursor: pointer;
            font-size: 15px;
        }

        .send-btn:hover {
            background: #ea580c;
        }

        /* NOTIFICATIONS */

        .notification {
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
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

        /* MESSAGE HISTORY */

        .support-item {
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 18px;
        }

        .support-item:last-child {
            margin-bottom: 0;
        }

        .support-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .support-top h3 {
            color: #f97316;
        }

        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .open {
            background: #fef3c7;
            color: #92400e;
        }

        .replied {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .resolved {
            background: #dcfce7;
            color: #166534;
        }

        .customer-message {
            background: #fff7ed;
            padding: 15px;
            border-radius: 9px;
            line-height: 1.7;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            margin-bottom: 15px;
        }

        .admin-reply {
            background: #dcfce7;
            border-left: 4px solid #16a34a;
            padding: 15px;
            border-radius: 9px;
            line-height: 1.7;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            margin-top: 10px;
        }

        .reply-heading {
            color: #166534;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .date {
            color: #667085;
            font-size: 13px;
            margin-top: 10px;
        }

        .empty {
            text-align: center;
            color: #667085;
            padding: 25px;
        }

        /* MOBILE */

        @media(max-width: 600px) {

            header {
                padding: 15px 4%;
            }

            .brand {
                font-size: 20px;
            }

            .container {
                padding: 28px 12px;
            }

            .page-title {
                font-size: 29px;
            }

            .card {
                padding: 18px;
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

        <a href="index.php" class="back-btn">
            Back to Home
        </a>

    </header>

    <!-- MAIN -->

    <main class="container">

        <h1 class="page-title">
            Contact Support
        </h1>

        <p class="subtitle">
            Have a question or concern? Send us a message.
        </p>

        <!-- NOTIFICATION -->

        <?php if ($notification !== ""): ?>

            <div class="notification <?php echo htmlspecialchars($notification_type); ?>">
                <?php echo htmlspecialchars($notification); ?>
            </div>

        <?php endif; ?>

        <!-- SEND MESSAGE -->

        <section class="card">

            <h2>Send Us a Message</h2>

            <form method="POST" action="contact_support.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
                >

                <label for="order_id">
                    Select Order (Optional)
                </label>

                <select name="order_id" id="order_id">

                    <option value="">
                        General Concern / No Order
                    </option>

                    <option value="other">
                        Other
                    </option>

                    <?php while ($order = $orders->fetch_assoc()): ?>

                        <option value="<?php echo (int)$order["id"]; ?>">

                            Order #<?php echo (int)$order["id"]; ?>
                            -
                            <?php echo htmlspecialchars($order["product_name"]); ?>

                        </option>

                    <?php endwhile; ?>

                </select>

                <label for="message">
                    Your Message
                </label>

                <textarea
                    name="message"
                    id="message"
                    maxlength="5000"
                    placeholder="Write your question or concern here..."
                    required
                ></textarea>

                <button type="submit" class="send-btn">
                    Send Message
                </button>

            </form>

        </section>

        <!-- MESSAGE HISTORY -->

        <section class="card">

            <h2>My Support Messages</h2>

            <?php if ($support_messages->num_rows > 0): ?>

                <?php while ($row = $support_messages->fetch_assoc()): ?>

                    <?php

                    $status = strtolower(
                        trim((string)($row["status"] ?? "open"))
                    );

                    $status_class = match ($status) {
                        "open" => "open",
                        "replied" => "replied",
                        "resolved" => "resolved",
                        default => "open"
                    };

                    ?>

                    <div class="support-item">

                        <div class="support-top">

                            <h3>
                                Message #<?php echo (int)$row["id"]; ?>
                            </h3>

                            <span class="status <?php echo $status_class; ?>">
                                <?php echo htmlspecialchars($row["status"]); ?>
                            </span>

                        </div>

                        <?php if (!empty($row["order_id"])): ?>

                            <p class="date">
                                Related Order: #<?php echo (int)$row["order_id"]; ?>
                            </p>

                        <?php else: ?>

                            <p class="date">
                                General Concern / Other
                            </p>

                        <?php endif; ?>

                        <p class="reply-heading">
                            Your Message:
                        </p>

                        <div class="customer-message"><?php
                            echo htmlspecialchars($row["message"]);
                        ?></div>

                        <?php if (!empty($row["admin_reply"])): ?>

                            <p class="reply-heading">
                                Admin Reply:
                            </p>

                            <div class="admin-reply"><?php
                                echo htmlspecialchars($row["admin_reply"]);
                            ?></div>

                            <?php if (!empty($row["replied_at"])): ?>

                                <p class="date">
                                    Replied:
                                    <?php
                                    echo htmlspecialchars(
                                        date("M d, Y h:i A", strtotime($row["replied_at"]))
                                    );
                                    ?>
                                </p>

                            <?php endif; ?>

                        <?php else: ?>

                            <p class="date">
                                Waiting for admin response.
                            </p>

                        <?php endif; ?>

                        <p class="date">
                            Sent:
                            <?php
                            echo htmlspecialchars(
                                date("M d, Y h:i A", strtotime($row["created_at"]))
                            );
                            ?>
                        </p>

                    </div>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="empty">
                    You haven't sent any support messages yet.
                </div>

            <?php endif; ?>

        </section>

    </main>

</body>

</html>

<?php

$order_stmt->close();
$message_stmt->close();
$conn->close();

?>
