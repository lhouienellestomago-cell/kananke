<?php
date_default_timezone_set("Asia/Manila");
session_start();

if (!isset($_SESSION["loggedIn"]) || $_SESSION["loggedIn"] !== true) {
    header("Location: login_page.php");
    exit;
}

require_once "db.php";

$user_id = (int)($_SESSION["user_id"] ?? 0);

if ($user_id <= 0) {
    die("User session not found. Please log in again.");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function supportRedirect($message, $type = "success") {
    $_SESSION["support_message"] = $message;
    $_SESSION["support_message_type"] = $type;
    header("Location: contact_support.php");
    exit;
}

// =====================================
// SEND NEW MESSAGE OR CUSTOMER REPLY
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) || !hash_equals($_SESSION["csrf_token"], $token)) {
        supportRedirect("Invalid request. Please try again.", "error");
    }

    $action = $_POST["action"] ?? "";
    $text = trim($_POST["message"] ?? "");

    if ($text === "" || strlen($text) > 5000) {
        supportRedirect("Please enter a message of up to 5000 characters.", "error");
    }

    // CUSTOMER REPLY TO EXISTING CONVERSATION
    if ($action === "reply") {
        $support_id = filter_var($_POST["support_id"] ?? "", FILTER_VALIDATE_INT);

        if (!$support_id || $support_id < 1) {
            supportRedirect("Invalid conversation.", "error");
        }

        // Verify this conversation belongs to the logged-in customer.
        $check = $conn->prepare(
            "SELECT id FROM support_messages WHERE id = ? AND user_id = ?"
        );
        $check->bind_param("ii", $support_id, $user_id);
        $check->execute();
        $owned = $check->get_result()->num_rows > 0;
        $check->close();

        if (!$owned) {
            supportRedirect("Conversation not found.", "error");
        }

        $stmt = $conn->prepare(
            "INSERT INTO support_replies (support_id, sender_role, reply_message)
             VALUES (?, 'customer', ?)"
        );
        $stmt->bind_param("is", $support_id, $text);
        $stmt->execute();
        $stmt->close();

        // A new customer reply reopens the conversation.
        $stmt = $conn->prepare(
            "UPDATE support_messages SET status = 'Open' WHERE id = ? AND user_id = ?"
        );
        $stmt->bind_param("ii", $support_id, $user_id);
        $stmt->execute();
        $stmt->close();

        supportRedirect("Your reply has been sent.");
    }

    // CREATE A NEW SUPPORT CONVERSATION
    if ($action === "new") {
        $order_input = trim($_POST["order_id"] ?? "");
        $order_id = null;

        if ($order_input !== "" && strtolower($order_input) !== "other") {
            $order_id = filter_var($order_input, FILTER_VALIDATE_INT);

            if (!$order_id || $order_id < 1) {
                supportRedirect("Invalid order selected.", "error");
            }

            $check = $conn->prepare(
                "SELECT id FROM orders WHERE id = ? AND user_id = ?"
            );
            $check->bind_param("ii", $order_id, $user_id);
            $check->execute();
            $valid = $check->get_result()->num_rows > 0;
            $check->close();

            if (!$valid) {
                supportRedirect("The selected order is invalid.", "error");
            }
        }

        if ($order_id === null) {
            $stmt = $conn->prepare(
                "INSERT INTO support_messages (user_id, order_id, message, status)
                 VALUES (?, NULL, ?, 'Open')"
            );
            $stmt->bind_param("is", $user_id, $text);
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO support_messages (user_id, order_id, message, status)
                 VALUES (?, ?, ?, 'Open')"
            );
            $stmt->bind_param("iis", $user_id, $order_id, $text);
        }

        $stmt->execute();
        $stmt->close();

        supportRedirect("Your support message has been sent.");
    }

    supportRedirect("Invalid action.", "error");
}

// =====================================
// NOTIFICATION
// =====================================

$notice = $_SESSION["support_message"] ?? "";
$notice_type = $_SESSION["support_message_type"] ?? "";
unset($_SESSION["support_message"], $_SESSION["support_message_type"]);

// =====================================
// CUSTOMER ORDERS
// =====================================

$order_stmt = $conn->prepare(
    "SELECT id, product_name FROM orders WHERE user_id = ? ORDER BY order_date DESC"
);
$order_stmt->bind_param("i", $user_id);
$order_stmt->execute();
$orders = $order_stmt->get_result();

// =====================================
// CUSTOMER CONVERSATIONS
// =====================================

$stmt = $conn->prepare(
    "SELECT * FROM support_messages WHERE user_id = ? ORDER BY created_at DESC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$conversations = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contact Support - Kananke Food Hauz</title>

<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:Arial,sans-serif}
body{min-height:100vh;background:linear-gradient(135deg,#fff7ed,#dcfce7);color:#172018}
header{background:white;padding:16px 6%;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;box-shadow:0 2px 15px #0001}
.brand{font-size:24px;font-weight:800;color:#f97316}
.back-btn{background:#111827;color:white;padding:11px 16px;border-radius:9px;text-decoration:none;font-weight:bold}
.container{max-width:950px;margin:auto;padding:40px 20px}
h1{text-align:center;font-size:36px;margin-bottom:10px}
.subtitle{text-align:center;color:#667085;margin-bottom:30px}
.card{background:white;padding:25px;border-radius:16px;box-shadow:0 6px 20px #0001;margin-bottom:25px}
.card h2{color:#f97316;margin-bottom:18px}
label{display:block;font-weight:bold;margin-bottom:8px}
select,textarea{width:100%;padding:13px;border:1px solid #d1d5db;border-radius:9px;margin-bottom:16px;font-size:15px}
textarea{min-height:110px;resize:vertical}
button{border:0;border-radius:8px;padding:12px 18px;font-weight:bold;cursor:pointer}
.send-btn{background:#f97316;color:white}
.reply-btn{background:#2563eb;color:white}
.notification{padding:14px;border-radius:9px;text-align:center;font-weight:bold;margin-bottom:20px}
.success{background:#dcfce7;color:#166534}
.error{background:#fee2e2;color:#991b1b}
.conversation{border:1px solid #eee;border-radius:12px;padding:18px;margin-bottom:18px}
.conversation h3{color:#f97316;margin-bottom:10px}
.top{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:12px;flex-wrap:wrap}
.status{padding:7px 12px;border-radius:20px;font-size:12px;font-weight:bold}
.open{background:#fef3c7;color:#92400e}
.replied{background:#dbeafe;color:#1d4ed8}
.resolved{background:#dcfce7;color:#166534}
.chat-message{padding:13px;border-radius:10px;margin:12px 0;line-height:1.6;white-space:pre-wrap;overflow-wrap:anywhere}
.original{background:#fff7ed}
.customer{background:#fff7ed;border-left:4px solid #f97316}
.admin{background:#dcfce7;border-left:4px solid #16a34a}
.sender{font-weight:bold;margin-bottom:5px}
.date{font-size:12px;color:#667085;margin-top:7px}
.reply-form{margin-top:18px;border-top:1px solid #eee;padding-top:16px}
.empty{text-align:center;color:#667085;padding:25px}
@media(max-width:600px){header{padding:15px 4%}.brand{font-size:20px}.container{padding:28px 12px}h1{font-size:29px}.card{padding:18px}}
</style>
</head>

<body>
<header>
    <div class="brand">🍽 Chekananke</div>
    <a href="index.php" class="back-btn">Back to Home</a>
</header>

<main class="container">
    <h1>Contact Support</h1>
    <p class="subtitle">Send us a message and chat with our support team.</p>

    <?php if ($notice !== ""): ?>
        <div class="notification <?php echo htmlspecialchars($notice_type); ?>">
            <?php echo htmlspecialchars($notice); ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <h2>Start a New Conversation</h2>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
            <input type="hidden" name="action" value="new">

            <label for="order_id">Select Order (Optional)</label>
            <select name="order_id" id="order_id">
                <option value="">General Concern / No Order</option>
                <option value="other">Other</option>

                <?php while ($order = $orders->fetch_assoc()): ?>
                    <option value="<?php echo (int)$order["id"]; ?>">
                        Order #<?php echo (int)$order["id"]; ?> -
                        <?php echo htmlspecialchars($order["product_name"]); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="message">Your Message</label>
            <textarea name="message" id="message" maxlength="5000" placeholder="Write your question or concern..." required></textarea>

            <button type="submit" class="send-btn">Send Message</button>
        </form>
    </section>

    <section class="card">
        <h2>My Support Messages</h2>

        <?php if ($conversations->num_rows > 0): ?>
            <?php while ($conversation = $conversations->fetch_assoc()): ?>
                <?php
                $support_id = (int)$conversation["id"];
                $status = strtolower(trim($conversation["status"] ?? "open"));
                $status_class = in_array($status, ["open","replied","resolved"], true) ? $status : "open";

                $reply_stmt = $conn->prepare(
                    "SELECT sender_role, reply_message, created_at
                     FROM support_replies
                     WHERE support_id = ?
                     ORDER BY created_at ASC, id ASC"
                );
                $reply_stmt->bind_param("i", $support_id);
                $reply_stmt->execute();
                $replies = $reply_stmt->get_result();
                ?>

                <div class="conversation">
                    <div class="top">
                        <h3>Message #<?php echo $support_id; ?></h3>
                        <span class="status <?php echo $status_class; ?>">
                            <?php echo htmlspecialchars($conversation["status"]); ?>
                        </span>
                    </div>

                    <?php if (!empty($conversation["order_id"])): ?>
                        <p class="date">Related Order: #<?php echo (int)$conversation["order_id"]; ?></p>
                    <?php else: ?>
                        <p class="date">General Concern / Other</p>
                    <?php endif; ?>

                    <div class="chat-message original">
                        <div class="sender">You</div>
                        <?php echo nl2br(htmlspecialchars($conversation["message"])); ?>
                        <div class="date">
                            <?php echo htmlspecialchars($conversation["created_at"]); ?>
                        </div>
                    </div>

                    <?php while ($reply = $replies->fetch_assoc()): ?>
                        <?php $is_admin = $reply["sender_role"] === "admin"; ?>
                        <div class="chat-message <?php echo $is_admin ? "admin" : "customer"; ?>">
                            <div class="sender">
                                <?php echo $is_admin ? "Customer Support" : "You"; ?>
                            </div>
                            <?php echo nl2br(htmlspecialchars($reply["reply_message"])); ?>
                            <div class="date">
                                <?php echo htmlspecialchars($reply["created_at"]); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>

                    <?php $reply_stmt->close(); ?>

                    <form method="POST" class="reply-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
                        <input type="hidden" name="action" value="reply">
                        <input type="hidden" name="support_id" value="<?php echo $support_id; ?>">

                        <label>Reply to this conversation</label>
                        <textarea name="message" maxlength="5000" placeholder="Write your reply..." required></textarea>
                        <button type="submit" class="reply-btn">Send Reply</button>
                    </form>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty">You haven't sent any support messages yet.</div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>

<?php
$order_stmt->close();
$stmt->close();
$conn->close();
?>
