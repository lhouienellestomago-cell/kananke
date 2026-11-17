<?php
date_default_timezone_set("Asia/Manila");
session_start();

if (!isset($_SESSION["loggedIn"]) || $_SESSION["loggedIn"] !== true) {
    header("Location: login_page.php");
    exit;
}

require_once "db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$user_id = (int)($_SESSION["user_id"] ?? 0);

if ($user_id <= 0) {
    header("Location: login_page.php");
    exit;
}

if (empty($_SESSION["support_csrf"])) {
    $_SESSION["support_csrf"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["support_csrf"];
$success = "";
$error = "";

// =====================================
// HANDLE FORM SUBMISSIONS
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submitted_token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrf_token, $submitted_token)) {
        $error = "Invalid security token. Please refresh the page and try again.";
    } else {

        $action = $_POST["action"] ?? "";

        // =====================================
        // CREATE NEW SUPPORT CONVERSATION
        // =====================================

        if ($action === "new_message") {

            $message = trim($_POST["message"] ?? "");
            $order_id_input = trim($_POST["order_id"] ?? "");

            if ($message === "") {
                $error = "Please enter your message.";
            } elseif (strlen($message) < 3 || strlen($message) > 15000) {
                $error = "Your message must contain between 3 and 15000 characters.";
            } else {

                $order_id = null;

                // Optional order selection
                if ($order_id_input !== "") {

                    if (!ctype_digit($order_id_input)) {
                        $error = "Invalid order selected.";
                    } else {

                        $selected_order_id = (int)$order_id_input;

                        $check_order = $conn->prepare(
                            "SELECT id
                             FROM orders
                             WHERE id = ? AND user_id = ?
                             LIMIT 1"
                        );

                        $check_order->bind_param(
                            "ii",
                            $selected_order_id,
                            $user_id
                        );

                        $check_order->execute();
                        $order_result = $check_order->get_result();

                        if ($order_result->num_rows === 0) {
                            $error = "The selected order does not belong to your account.";
                        } else {
                            $order_id = $selected_order_id;
                        }

                        $check_order->close();
                    }
                }

                if ($error === "") {

                    $insert = $conn->prepare(
                        "INSERT INTO support_messages
                         (user_id, order_id, message, status, created_at)
                         VALUES (?, ?, ?, 'Open', NOW())"
                    );

                    $insert->bind_param(
                        "iis",
                        $user_id,
                        $order_id,
                        $message
                    );

                    $insert->execute();
                    $insert->close();

                    header("Location: contact_support.php?sent=1");
                    exit;
                }
            }
        }

        // =====================================
        // CUSTOMER REPLY
        // =====================================

        elseif ($action === "send_reply") {

            $support_id = (int)($_POST["support_id"] ?? 0);
            $reply_message = trim($_POST["reply_message"] ?? "");

            if ($support_id <= 0) {
                $error = "Invalid conversation.";
            } elseif ($reply_message === "") {
                $error = "Please enter your reply.";
            } elseif (strlen($reply_message) < 1 || strlen($reply_message) > 15000) {
                $error = "Your reply is too long.";
            } else {

                // Make sure conversation belongs to customer
                // and is not resolved.

                $check = $conn->prepare(
                    "SELECT id, status
                     FROM support_messages
                     WHERE id = ? AND user_id = ?
                     LIMIT 1"
                );

                $check->bind_param("ii", $support_id, $user_id);
                $check->execute();

                $conversation = $check->get_result()->fetch_assoc();
                $check->close();

                if (!$conversation) {
                    $error = "Conversation not found.";
                } elseif ($conversation["status"] === "Resolved") {
                    $error = "This conversation has been resolved. You cannot send another reply.";
                } else {

                    $conn->begin_transaction();

                    try {

                        $insert_reply = $conn->prepare(
                            "INSERT INTO support_replies
                             (support_id, sender_role, reply_message, created_at)
                             VALUES (?, 'customer', ?, NOW())"
                        );

                        $insert_reply->bind_param(
                            "is",
                            $support_id,
                            $reply_message
                        );

                        $insert_reply->execute();
                        $insert_reply->close();

                        $update_status = $conn->prepare(
                            "UPDATE support_messages
                             SET status = 'Open'
                             WHERE id = ?
                               AND user_id = ?
                               AND status <> 'Resolved'"
                        );

                        $update_status->bind_param(
                            "ii",
                            $support_id,
                            $user_id
                        );

                        $update_status->execute();
                        $update_status->close();

                        $conn->commit();

                        header("Location: contact_support.php?replied=1");
                        exit;

                    } catch (Throwable $e) {
                        $conn->rollback();
                        throw $e;
                    }
                }
            }
        }
    }
}

// =====================================
// SUCCESS MESSAGES
// =====================================

if (isset($_GET["sent"])) {
    $success = "Your support message has been sent successfully.";
}

if (isset($_GET["replied"])) {
    $success = "Your reply has been sent successfully.";
}

// =====================================
// MARK ADMIN REPLIES AS READ
// Runs when customer opens this page.
// =====================================

$read_stmt = $conn->prepare(
    "UPDATE support_replies r
     INNER JOIN support_messages s
        ON s.id = r.support_id
     SET r.read_at = NOW()
     WHERE s.user_id = ?
       AND r.sender_role = 'admin'
       AND r.read_at IS NULL"
);

$read_stmt->bind_param("i", $user_id);
$read_stmt->execute();
$read_stmt->close();

// =====================================
// GET CUSTOMER ORDERS
// =====================================

$orders = [];

$order_stmt = $conn->prepare(
    "SELECT id, product_name, total, order_date, status
     FROM orders
     WHERE user_id = ?
     ORDER BY id DESC"
);

$order_stmt->bind_param("i", $user_id);
$order_stmt->execute();

$order_result = $order_stmt->get_result();

while ($row = $order_result->fetch_assoc()) {
    $orders[] = $row;
}

$order_stmt->close();

// =====================================
// GET CUSTOMER SUPPORT CONVERSATIONS
// =====================================

$conversations = [];

$support_stmt = $conn->prepare(
    "SELECT
        id,
        order_id,
        message,
        status,
        created_at
     FROM support_messages
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC"
);

$support_stmt->bind_param("i", $user_id);
$support_stmt->execute();

$support_result = $support_stmt->get_result();

while ($row = $support_result->fetch_assoc()) {
    $conversations[] = $row;
}

$support_stmt->close();

// =====================================
// GET REPLIES FOR EACH CONVERSATION
// =====================================

$reply_stmt = $conn->prepare(
    "SELECT
        id,
        sender_role,
        reply_message,
        created_at
     FROM support_replies
     WHERE support_id = ?
     ORDER BY created_at ASC, id ASC"
);

foreach ($conversations as &$conversation) {

    $conversation["replies"] = [];

    $support_id = (int)$conversation["id"];

    $reply_stmt->bind_param("i", $support_id);
    $reply_stmt->execute();

    $reply_result = $reply_stmt->get_result();

    while ($reply = $reply_result->fetch_assoc()) {
        $conversation["replies"][] = $reply;
    }
}

unset($conversation);

$reply_stmt->close();
$conn->close();

// =====================================
// HELPER FUNCTIONS
// =====================================

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function formatSupportDate($date) {
    if (!$date) {
        return "";
    }

    try {
        $datetime = new DateTime($date, new DateTimeZone("UTC"));
        $datetime->setTimezone(new DateTimeZone("Asia/Manila"));

        return $datetime->format("M d, Y - h:i A");
    } catch (Exception $e) {
        return $date;
    }
}

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
    background: #f3f4f6;
    color: #1f2937;
}

header {
    background: #fff;
    padding: 18px 6%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 10px #0001;
}

.logo {
    font-size: 23px;
    font-weight: bold;
    color: #f97316;
}

.back-link {
    text-decoration: none;
    color: #fff;
    background: #374151;
    padding: 10px 16px;
    border-radius: 8px;
    font-weight: bold;
}

.back-link:hover {
    background: #111827;
}

.container {
    max-width: 1000px;
    margin: 35px auto;
    padding: 0 18px;
}

.page-title {
    margin-bottom: 10px;
    font-size: 30px;
}

.page-description {
    color: #6b7280;
    margin-bottom: 25px;
    line-height: 1.5;
}

.panel {
    background: #fff;
    padding: 25px;
    border-radius: 14px;
    box-shadow: 0 4px 18px #0000000d;
    margin-bottom: 25px;
}

.panel h2 {
    margin-bottom: 18px;
    font-size: 21px;
}

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
    border-radius: 8px;
    font-size: 15px;
    margin-bottom: 18px;
    outline: none;
}

select:focus,
textarea:focus {
    border-color: #f97316;
}

textarea {
    min-height: 120px;
    resize: vertical;
}

.send-button {
    background: #f97316;
    color: #fff;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
}

.send-button:hover {
    background: #ea580c;
}

.reply-button {
    background: #2563eb;
}

.reply-button:hover {
    background: #1d4ed8;
}

.success {
    background: #dcfce7;
    color: #166534;
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.conversation {
    background: #fff;
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 22px;
    box-shadow: 0 4px 18px #0000000d;
}

.conversation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.conversation-header h3 {
    font-size: 18px;
}

.status {
    padding: 7px 12px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: bold;
}

.status-open {
    background: #fef3c7;
    color: #92400e;
}

.status-replied {
    background: #dbeafe;
    color: #1e40af;
}

.status-resolved {
    background: #dcfce7;
    color: #166534;
}

.original-message {
    background: #f9fafb;
    border-left: 4px solid #f97316;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 16px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.message-label {
    font-weight: bold;
    font-size: 13px;
    margin-bottom: 7px;
}

.message-date {
    color: #9ca3af;
    font-size: 12px;
    margin-top: 8px;
}

.chat-reply {
    padding: 15px;
    border-radius: 10px;
    margin: 12px 0;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.admin-reply {
    background: #eff6ff;
    border-left: 4px solid #2563eb;
    margin-right: 25px;
}

.customer-reply {
    background: #fff7ed;
    border-left: 4px solid #f97316;
    margin-left: 25px;
}

.reply-form {
    margin-top: 20px;
    border-top: 1px solid #e5e7eb;
    padding-top: 18px;
}

.reply-form textarea {
    min-height: 90px;
}

.resolved-notice {
    background: #f3f4f6;
    color: #6b7280;
    padding: 13px;
    border-radius: 8px;
    margin-top: 18px;
    font-size: 14px;
}

.no-conversations {
    background: #fff;
    padding: 35px;
    border-radius: 14px;
    text-align: center;
    color: #6b7280;
}

footer {
    text-align: center;
    padding: 25px;
    color: #9ca3af;
}

@media(max-width: 600px) {

    header {
        gap: 12px;
        flex-wrap: wrap;
    }

    .container {
        margin-top: 22px;
    }

    .panel,
    .conversation {
        padding: 17px;
    }

    .admin-reply {
        margin-right: 5px;
    }

    .customer-reply {
        margin-left: 5px;
    }

}

</style>

</head>

<body>

<header>

    <div class="logo">
        🍽 Kananke Food Hauz
    </div>

    <a href="index.php" class="back-link">
        Back to Home
    </a>

</header>

<div class="container">

    <h1 class="page-title">Contact Support</h1>

    <p class="page-description">
        Have a question or concern? Send us a message.
        Our admin team will respond to your concern here.
    </p>

    <?php if ($success !== ""): ?>
        <div class="success">
            <?= h($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="error">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <!-- =====================================
         NEW SUPPORT MESSAGE
    ====================================== -->

    <section class="panel">

        <h2>Send a New Message</h2>

        <form method="POST" action="contact_support.php">

            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">

            <input type="hidden" name="action" value="new_message">

            <label for="order_id">
                Select Order (Optional)
            </label>

            <select name="order_id" id="order_id">

                <option value="">
                    General Concern / Other
                </option>

                <?php foreach ($orders as $order): ?>

                    <option value="<?= (int)$order["id"] ?>">

                        Order #<?= (int)$order["id"] ?>
                        -
                        <?= h($order["product_name"] ?? "Order") ?>
                        -
                        ₱<?= number_format((float)($order["total"] ?? 0), 2) ?>

                    </option>

                <?php endforeach; ?>

            </select>

            <label for="message">
                Your Message
            </label>

            <textarea
                name="message"
                id="message"
                placeholder="Write your question or concern here..."
                minlength="3"
                maxlength="15000"
                required
            ></textarea>

            <button type="submit" class="send-button">
                Send Message
            </button>

        </form>

    </section>

    <!-- =====================================
         CUSTOMER CONVERSATIONS
    ====================================== -->

    <h2 style="margin-bottom:18px;">
        My Support Conversations
    </h2>

    <?php if (count($conversations) === 0): ?>

        <div class="no-conversations">
            <h3>No support conversations yet.</h3>
            <p style="margin-top:8px;">
                Send a message above if you need help.
            </p>
        </div>

    <?php else: ?>

        <?php foreach ($conversations as $conversation): ?>

            <?php
                $status = $conversation["status"] ?? "Open";

                $status_class = "status-open";

                if ($status === "Replied") {
                    $status_class = "status-replied";
                } elseif ($status === "Resolved") {
                    $status_class = "status-resolved";
                }
            ?>

            <section class="conversation">

                <div class="conversation-header">

                    <h3>
                        Support Conversation #<?= (int)$conversation["id"] ?>
                    </h3>

                    <span class="status <?= h($status_class) ?>">
                        <?= h($status) ?>
                    </span>

                </div>

                <?php if (!empty($conversation["order_id"])): ?>

                    <p style="color:#6b7280; font-size:13px; margin-bottom:12px;">
                        Related Order: #<?= (int)$conversation["order_id"] ?>
                    </p>

                <?php else: ?>

                    <p style="color:#6b7280; font-size:13px; margin-bottom:12px;">
                        General Concern
                    </p>

                <?php endif; ?>

                <!-- Original customer message -->

                <div class="original-message">

                    <div class="message-label">
                        Your Original Message
                    </div>

                    <?= nl2br(h($conversation["message"])) ?>

                    <div class="message-date">
                        <?= h(formatSupportDate($conversation["created_at"])) ?>
                    </div>

                </div>

                <!-- Chat replies -->

                <?php foreach ($conversation["replies"] as $reply): ?>

                    <?php if ($reply["sender_role"] === "admin"): ?>

                        <div class="chat-reply admin-reply">

                            <div class="message-label">
                                Admin Support
                            </div>

                            <?= nl2br(h($reply["reply_message"])) ?>

                            <div class="message-date">
                                <?= h(formatSupportDate($reply["created_at"])) ?>
                            </div>

                        </div>

                    <?php else: ?>

                        <div class="chat-reply customer-reply">

                            <div class="message-label">
                                You
                            </div>

                            <?= nl2br(h($reply["reply_message"])) ?>

                            <div class="message-date">
                                <?= h(formatSupportDate($reply["created_at"])) ?>
                            </div>

                        </div>

                    <?php endif; ?>

                <?php endforeach; ?>

                <!-- Reply form is hidden when resolved -->

                <?php if ($status !== "Resolved"): ?>

                    <form
                        method="POST"
                        action="contact_support.php"
                        class="reply-form"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= h($csrf_token) ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="send_reply"
                        >

                        <input
                            type="hidden"
                            name="support_id"
                            value="<?= (int)$conversation["id"] ?>"
                        >

                        <label>
                            Send a Reply
                        </label>

                        <textarea
                            name="reply_message"
                            placeholder="Type your reply here..."
                            maxlength="15000"
                            required
                        ></textarea>

                        <button
                            type="submit"
                            class="send-button reply-button"
                        >
                            Send Reply
                        </button>

                    </form>

                <?php else: ?>

                    <div class="resolved-notice">
                        This conversation has been resolved.
                        You can still view the messages, but sending new replies is disabled.
                        If you have another concern, please create a new support conversation.
                    </div>

                <?php endif; ?>

            </section>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

<footer>
    © <?= date("Y") ?> Kananke Food Hauz. All rights reserved.
</footer>

</body>
</html>
