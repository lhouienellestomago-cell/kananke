<?php
session_start();

// =====================================
// ADMIN LOGIN CHECK
// =====================================

if (
    !isset($_SESSION["loggedIn"]) ||
    $_SESSION["loggedIn"] !== true ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: login.php");
    exit;
}

// =====================================
// DATABASE CONNECTION
// =====================================

require_once "db.php";

// =====================================
// CSRF TOKEN
// =====================================

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// =====================================
// NOTIFICATIONS
// =====================================

$message = $_SESSION["support_message"] ?? "";
$message_type = $_SESSION["support_message_type"] ?? "";

unset(
    $_SESSION["support_message"],
    $_SESSION["support_message_type"]
);

// =====================================
// HANDLE ADMIN ACTIONS
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)
    ) {
        $_SESSION["support_message"] = "Invalid request. Please try again.";
        $_SESSION["support_message_type"] = "error";

        header("Location: admin_support.php");
        exit;
    }

    $action = $_POST["action"] ?? "";

    $support_id = filter_input(
        INPUT_POST,
        "support_id",
        FILTER_VALIDATE_INT
    );

    if (!$support_id || $support_id < 1) {
        $_SESSION["support_message"] = "Invalid support message ID.";
        $_SESSION["support_message_type"] = "error";

        header("Location: admin_support.php");
        exit;
    }

    // =================================
    // REPLY TO CUSTOMER
    // =================================

    if ($action === "reply") {

        $reply = trim($_POST["admin_reply"] ?? "");

        if ($reply === "") {

            $_SESSION["support_message"] = "Please enter a reply.";
            $_SESSION["support_message_type"] = "error";

        } else {

            $sql = "UPDATE support_messages
                    SET admin_reply = ?,
                        status = 'Replied',
                        replied_at = NOW()
                    WHERE id = ?
                    AND status <> 'Resolved'";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "si",
                $reply,
                $support_id
            );

            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $_SESSION["support_message"] = "Your reply was successfully sent.";
                $_SESSION["support_message_type"] = "success";
            } else {
                $_SESSION["support_message"] =
                    "Message not found or it has already been resolved.";
                $_SESSION["support_message_type"] = "error";
            }

            $stmt->close();
        }

    // =================================
    // MARK MESSAGE AS RESOLVED
    // =================================

    } elseif ($action === "resolve") {

        $sql = "UPDATE support_messages
                SET status = 'Resolved'
                WHERE id = ?
                AND status <> 'Resolved'";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "i",
            $support_id
        );

        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $_SESSION["support_message"] = "Support message marked as resolved.";
            $_SESSION["support_message_type"] = "success";
        } else {
            $_SESSION["support_message"] =
                "Message not found or already resolved.";
            $_SESSION["support_message_type"] = "error";
        }

        $stmt->close();

    } else {

        $_SESSION["support_message"] = "Invalid action.";
        $_SESSION["support_message_type"] = "error";
    }

    header("Location: admin_support.php");
    exit;
}

// =====================================
// GET ALL CUSTOMER SUPPORT MESSAGES
// =====================================

$sql = "SELECT
            s.id,
            s.user_id,
            s.order_id,
            s.message,
            s.admin_reply,
            s.status,
            s.created_at,
            s.replied_at,
            u.email
        FROM support_messages s
        LEFT JOIN users u ON s.user_id = u.id
        ORDER BY s.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customer Support - Kananke Food Hauz</title>

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
            padding: 18px 6%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
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
            padding: 11px 17px;
            border-radius: 9px;
            text-decoration: none;
            font-weight: bold;
        }

        .back-btn:hover {
            background: #374151;
        }

        /* MAIN */

        .container {
            max-width: 1100px;
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

        /* SUPPORT CARD */

        .support-card {
            background: white;
            padding: 25px;
            margin-bottom: 22px;
            border-radius: 16px;
            box-shadow: 0 6px 20px #0001;
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .card-top h2 {
            font-size: 20px;
            color: #f97316;
        }

        .details {
            color: #667085;
            font-size: 14px;
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .customer-message {
            background: #fff7ed;
            padding: 16px;
            border-radius: 10px;
            line-height: 1.7;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            margin-bottom: 18px;
        }

        /* STATUS */

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

        /* ADMIN REPLY */

        .reply-label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }

        textarea {
            width: 100%;
            min-height: 110px;
            padding: 13px;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            resize: vertical;
            font-size: 15px;
            margin-bottom: 12px;
        }

        textarea:focus {
            outline: none;
            border-color: #f97316;
            box-shadow: 0 0 0 2px #f9731630;
        }

        .admin-reply {
            background: #dcfce7;
            border-left: 4px solid #16a34a;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 18px;
            line-height: 1.7;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .reply-title {
            font-weight: bold;
            color: #166534;
            margin-bottom: 8px;
        }

        /* BUTTONS */

        .button-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .reply-btn {
            background: #f97316;
            color: white;
            border: none;
            padding: 11px 18px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
        }

        .reply-btn:hover {
            background: #ea580c;
        }

        .resolve-btn {
            background: #16a34a;
            color: white;
            border: none;
            padding: 11px 18px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
        }

        .resolve-btn:hover {
            background: #15803d;
        }

        .resolved-text {
            color: #166534;
            font-weight: bold;
            padding: 10px 0;
        }

        /* EMPTY */

        .empty {
            background: white;
            padding: 50px 20px;
            text-align: center;
            border-radius: 16px;
            box-shadow: 0 6px 20px #0001;
        }

        .empty h2 {
            margin-bottom: 10px;
        }

        .empty p {
            color: #667085;
        }

        /* MOBILE */

        @media (max-width: 600px) {

            header {
                padding: 16px 4%;
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

            .support-card {
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

        <a href="admin_dashboard.php" class="back-btn">
            Back to Dashboard
        </a>

    </header>

    <!-- MAIN -->

    <main class="container">

        <h1 class="page-title">
            Customer Support
        </h1>

        <p class="subtitle">
            View customer concerns and respond to their messages.
        </p>

        <!-- NOTIFICATION -->

        <?php if ($message !== ""): ?>

            <div class="notification <?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>

        <!-- SUPPORT MESSAGES -->

        <?php if ($result->num_rows > 0): ?>

            <?php while ($row = $result->fetch_assoc()): ?>

                <?php

                $status = strtolower(trim((string)($row["status"] ?? "open")));

                $status_class = match ($status) {
                    "open" => "open",
                    "replied" => "replied",
                    "resolved" => "resolved",
                    default => "open"
                };

                ?>

                <section class="support-card">

                    <div class="card-top">

                        <h2>
                            Support Message #<?php echo (int)$row["id"]; ?>
                        </h2>

                        <span class="status <?php echo $status_class; ?>">
                            <?php echo htmlspecialchars($row["status"]); ?>
                        </span>

                    </div>

                    <div class="details">

                        <strong>Customer:</strong>
                        <?php echo htmlspecialchars($row["email"] ?? "Unknown customer"); ?>

                        <br>

                        <strong>Customer ID:</strong>
                        <?php echo (int)$row["user_id"]; ?>

                        <br>

                        <strong>Order ID:</strong>
                        <?php
                        echo $row["order_id"] !== null
                            ? "#" . (int)$row["order_id"]
                            : "Not specified";
                        ?>

                        <br>

                        <strong>Date:</strong>
                        <?php
                        echo htmlspecialchars(
                            date("M d, Y h:i A", strtotime($row["created_at"]))
                        );
                        ?>

                    </div>

                    <h3 class="reply-title">
                        Customer Message
                    </h3>

                    <div class="customer-message"><?php
                        echo htmlspecialchars($row["message"]);
                    ?></div>

                    <?php if (!empty($row["admin_reply"])): ?>

                        <h3 class="reply-title">
                            Your Reply
                        </h3>

                        <div class="admin-reply"><?php
                            echo htmlspecialchars($row["admin_reply"]);
                        ?></div>

                        <?php if (!empty($row["replied_at"])): ?>

                            <div class="details">
                                <strong>Replied:</strong>
                                <?php
                                echo htmlspecialchars(
                                    date("M d, Y h:i A", strtotime($row["replied_at"]))
                                );
                                ?>
                            </div>

                        <?php endif; ?>

                    <?php endif; ?>

                    <?php if ($status !== "resolved"): ?>

                        <!-- REPLY FORM -->

                        <form method="POST" action="admin_support.php">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
                            >

                            <input
                                type="hidden"
                                name="support_id"
                                value="<?php echo (int)$row["id"]; ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="reply"
                            >

                            <label class="reply-label">
                                Write a Reply
                            </label>

                            <textarea
                                name="admin_reply"
                                placeholder="Type your reply to the customer..."
                                required
                            ></textarea>

                            <div class="button-row">

                                <button type="submit" class="reply-btn">
                                    Send Reply
                                </button>

                            </div>

                        </form>

                        <!-- RESOLVE FORM -->

                        <form
                            method="POST"
                            action="admin_support.php"
                            onsubmit="return confirm('Are you sure you want to mark this message as resolved?');"
                            style="margin-top: 12px;"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
                            >

                            <input
                                type="hidden"
                                name="support_id"
                                value="<?php echo (int)$row["id"]; ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="resolve"
                            >

                            <button type="submit" class="resolve-btn">
                                Mark as Resolved
                            </button>

                        </form>

                    <?php else: ?>

                        <div class="resolved-text">
                            This support message has been resolved.
                        </div>

                    <?php endif; ?>

                </section>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="empty">

                <h2>No Support Messages Yet</h2>

                <p>
                    Customer messages will appear here when someone contacts support.
                </p>

            </div>

        <?php endif; ?>

    </main>

</body>

</html>

<?php

$stmt->close();
$conn->close();

?>
