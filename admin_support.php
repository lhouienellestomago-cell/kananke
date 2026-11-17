
<?php

session_start();

// Admin authentication.
if (
    !isset($_SESSION["loggedIn"]) ||
    $_SESSION["loggedIn"] !== true ||
    strtolower(trim($_SESSION["role"] ?? "")) !== "admin"
) {
    header("Location: login_page.php");
    exit;
}

require_once "db.php";

if (empty($_SESSION["admin_support_csrf"])) {
    $_SESSION["admin_support_csrf"] = bin2hex(random_bytes(32));
}

$notice = $_SESSION["admin_support_notice"] ?? "";
$notice_type = $_SESSION["admin_support_type"] ?? "";

unset(
    $_SESSION["admin_support_notice"],
    $_SESSION["admin_support_type"]
);

// Handle admin reply or resolve action.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["admin_support_csrf"], $token)) {

        $_SESSION["admin_support_notice"] = "Invalid request.";
        $_SESSION["admin_support_type"] = "error";

        header("Location: admin_support.php");
        exit;
    }

    $message_id = filter_input(
        INPUT_POST,
        "message_id",
        FILTER_VALIDATE_INT
    );

    if (!$message_id || $message_id < 1) {

        $_SESSION["admin_support_notice"] = "Invalid message ID.";
        $_SESSION["admin_support_type"] = "error";

        header("Location: admin_support.php");
        exit;
    }

    // Send reply.
    if (isset($_POST["send_reply"])) {

        $reply = trim($_POST["admin_reply"] ?? "");

        if (strlen($reply) < 1 || strlen($reply) > 5000) {

            $_SESSION["admin_support_notice"] =
                "Reply must contain between 1 and 5000 characters.";

            $_SESSION["admin_support_type"] = "error";

        } else {

            $stmt = $conn->prepare(
                "UPDATE support_messages
                 SET admin_reply = ?,
                     status = 'Replied',
                     replied_at = CURRENT_TIMESTAMP
                 WHERE id = ?
                 AND status <> 'Resolved'"
            );

            $stmt->bind_param("si", $reply, $message_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $_SESSION["admin_support_notice"] =
                    "Reply sent successfully.";
                $_SESSION["admin_support_type"] = "success";
            } else {
                $_SESSION["admin_support_notice"] =
                    "Message not found or already resolved.";
                $_SESSION["admin_support_type"] = "error";
            }

            $stmt->close();
        }
    }

    // Resolve message.
    elseif (isset($_POST["resolve_message"])) {

        $stmt = $conn->prepare(
            "UPDATE support_messages
             SET status = 'Resolved'
             WHERE id = ?"
        );

        $stmt->bind_param("i", $message_id);
        $stmt->execute();

        $_SESSION["admin_support_notice"] =
            "Support message marked as resolved.";

        $_SESSION["admin_support_type"] = "success";

        $stmt->close();
    }

    header("Location: admin_support.php");
    exit;
}

// Count open messages.
$count_result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM support_messages
     WHERE status = 'Open'"
);

$open_count = (int) $count_result->fetch_assoc()["total"];

// Get all support messages and customer emails.
$sql = "SELECT
            sm.id,
            sm.user_id,
            sm.order_id,
            sm.message,
            sm.admin_reply,
            sm.status,
            sm.created_at,
            sm.replied_at,
            u.email
        FROM support_messages sm
        LEFT JOIN users u ON sm.user_id = u.id
        ORDER BY
            CASE
                WHEN sm.status = 'Open' THEN 0
                WHEN sm.status = 'Replied' THEN 1
                ELSE 2
            END,
            sm.created_at DESC";

$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Customer Support - Admin</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
}

body {
    background: #f3f4f6;
    min-height: 100vh;
    color: #172018;
}

header {
    background: #111827;
    color: white;
    padding: 18px 6%;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.brand {
    font-size: 23px;
    font-weight: bold;
    color: #fb923c;
}

.back {
    background: white;
    color: #111827;
    padding: 10px 15px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
}

.container {
    max-width: 1100px;
    margin: auto;
    padding: 35px 20px;
}

h1 {
    margin-bottom: 10px;
}

.subtitle {
    color: #667085;
    margin-bottom: 25px;
}

.counter {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 3px 12px #0001;
}

.counter strong {
    color: #ea580c;
    font-size: 26px;
}

.card {
    background: white;
    padding: 22px;
    border-radius: 14px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px #0001;
}

.card h3 {
    margin: 12px 0;
}

.info {
    color: #667085;
    font-size: 13px;
    line-height: 1.8;
}

.customer-message {
    background: #f9fafb;
    padding: 15px;
    border-radius: 9px;
    margin-top: 15px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.status {
    display: inline-block;
    padding: 6px 12px;
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

.reply-box {
    margin-top: 18px;
}

textarea {
    width: 100%;
    min-height: 100px;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 9px;
    resize: vertical;
    font-size: 14px;
}

button {
    padding: 11px 16px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    margin-top: 10px;
}

.reply-btn {
    background: #f97316;
    color: white;
}

.resolve-btn {
    background: #16a34a;
    color: white;
    margin-left: 7px;
}

.admin-reply {
    background: #f0fdf4;
    padding: 15px;
    border-left: 4px solid #16a34a;
    border-radius: 8px;
    margin-top: 15px;
    line-height: 1.6;
}

.notice {
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
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

.empty {
    text-align: center;
    padding: 35px;
    background: white;
    border-radius: 12px;
    color: #667085;
}

@media(max-width: 600px) {
    header {
        padding: 15px 4%;
    }

    .brand {
        font-size: 18px;
    }

    .card {
        padding: 16px;
    }

    .resolve-btn {
        margin-left: 0;
    }
}

</style>

</head>

<body>

<header>

    <div class="brand">
        🍽 Kananke Food Hauz
    </div>

    <a href="admin_dashboard.php" class="back">
        Admin Dashboard
    </a>

</header>

<div class="container">

    <h1>Customer Support</h1>

    <p class="subtitle">
        View customer concerns and respond to their messages.
    </p>

    <div class="counter">

        <p>Open Support Messages</p>

        <strong>
            <?php echo $open_count; ?>
        </strong>

    </div>

    <?php if ($notice !== ""): ?>

        <div class="notice <?php echo htmlspecialchars($notice_type); ?>">
            <?php echo htmlspecialchars($notice); ?>
        </div>

    <?php endif; ?>

    <?php if ($result->num_rows > 0): ?>

        <?php while ($row = $result->fetch_assoc()): ?>

            <?php
            $status_class = strtolower($row["status"]);
            ?>

            <div class="card">

                <span class="status <?php echo htmlspecialchars($status_class); ?>">
                    <?php echo htmlspecialchars($row["status"]); ?>
                </span>

                <h3>
                    Support Message #<?php echo (int) $row["id"]; ?>
                </h3>

                <div class="info">

                    Customer:
                    <?php echo htmlspecialchars($row["email"] ?? "Unknown customer"); ?>

                    <br>

                    Customer ID:
                    <?php echo (int) $row["user_id"]; ?>

                    <br>

                    Related Order:
                    <?php echo $row["order_id"] ? "#" . (int) $row["order_id"] : "General Inquiry"; ?>

                    <br>

                    Date:
                    <?php echo htmlspecialchars($row["created_at"]); ?>

                </div>

                <div class="customer-message">

                    <strong>Customer Message:</strong>

                    <p>
                        <?php echo nl2br(htmlspecialchars($row["message"])); ?>
                    </p>

                </div>

                <?php if (!empty($row["admin_reply"])): ?>

                    <div class="admin-reply">

                        <strong>Your Reply:</strong>

                        <p>
                            <?php echo nl2br(htmlspecialchars($row["admin_reply"])); ?>
                        </p>

                    </div>

                <?php endif; ?>

                <?php if ($row["status"] !== "Resolved"): ?>

                    <form
                        method="POST"
                        action="admin_support.php"
                        class="reply-box"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION["admin_support_csrf"]); ?>"
                        >

                        <input
                            type="hidden"
                            name="message_id"
                            value="<?php echo (int) $row["id"]; ?>"
                        >

                        <textarea
                            name="admin_reply"
                            placeholder="Write your reply to the customer..."
                            maxlength="5000"
                            required
                        ></textarea>

                        <button
                            type="submit"
                            name="send_reply"
                            class="reply-btn"
                        >
                            Send Reply
                        </button>

                        <button
                            type="submit"
                            name="resolve_message"
                            class="resolve-btn"
                            onclick="return confirm('Mark this support message as resolved?');"
                        >
                            Mark as Resolved
                        </button>

                    </form>

                <?php else: ?>

                    <p style="color:#16a34a;font-weight:bold;margin-top:15px;">
                        This support message is resolved.
                    </p>

                <?php endif; ?>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="empty">
            <h3>No Support Messages Yet</h3>
            <p>Customer messages will appear here.</p>
        </div>

    <?php endif; ?>

</div>

</body>
</html>

<?php
$conn->close();
?>
