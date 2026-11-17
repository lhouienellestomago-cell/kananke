<?php
date_default_timezone_set("Asia/Manila");
session_start();

if (
    !isset($_SESSION["loggedIn"]) ||
    $_SESSION["loggedIn"] !== true ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: login.php");
    exit;
}

require_once "db.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function adminSupportRedirect($message, $type = "success") {
    $_SESSION["support_message"] = $message;
    $_SESSION["support_message_type"] = $type;
    header("Location: admin_support.php");
    exit;
}

// =====================================
// HANDLE ADMIN REPLY OR RESOLVE
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) || !hash_equals($_SESSION["csrf_token"], $token)) {
        adminSupportRedirect("Invalid request. Please try again.", "error");
    }

    $action = $_POST["action"] ?? "";
    $support_id = filter_var($_POST["support_id"] ?? "", FILTER_VALIDATE_INT);

    if (!$support_id || $support_id < 1) {
        adminSupportRedirect("Invalid conversation.", "error");
    }

    // Confirm conversation exists.
    $check = $conn->prepare("SELECT id FROM support_messages WHERE id = ?");
    $check->bind_param("i", $support_id);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if (!$exists) {
        adminSupportRedirect("Conversation not found.", "error");
    }

    if ($action === "reply") {
        $reply = trim($_POST["message"] ?? "");

        if ($reply === "" || strlen($reply) > 5000) {
            adminSupportRedirect("Please enter a reply of up to 5000 characters.", "error");
        }

        // Save every admin reply as a new conversation entry.
        $stmt = $conn->prepare(
            "INSERT INTO support_replies (support_id, sender_role, reply_message)
             VALUES (?, 'admin', ?)"
        );
        $stmt->bind_param("is", $support_id, $reply);
        $stmt->execute();
        $stmt->close();

        // Mark conversation as replied.
        $stmt = $conn->prepare(
            "UPDATE support_messages SET status = 'Replied' WHERE id = ?"
        );
        $stmt->bind_param("i", $support_id);
        $stmt->execute();
        $stmt->close();

        adminSupportRedirect("Your reply has been sent.");

    } elseif ($action === "resolve") {
        $stmt = $conn->prepare(
            "UPDATE support_messages SET status = 'Resolved' WHERE id = ?"
        );
        $stmt->bind_param("i", $support_id);
        $stmt->execute();
        $stmt->close();

        adminSupportRedirect("Conversation marked as resolved.");

    } else {
        adminSupportRedirect("Invalid action.", "error");
    }
}

// =====================================
// NOTIFICATIONS
// =====================================

$notice = $_SESSION["support_message"] ?? "";
$notice_type = $_SESSION["support_message_type"] ?? "";
unset($_SESSION["support_message"], $_SESSION["support_message_type"]);

// =====================================
// GET ALL CONVERSATIONS
// =====================================

$stmt = $conn->prepare(
    "SELECT s.*, u.email
     FROM support_messages s
     LEFT JOIN users u ON s.user_id = u.id
     ORDER BY s.created_at DESC"
);
$stmt->execute();
$conversations = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customer Support - Kananke Food Hauz</title>

<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:Arial,sans-serif}
body{min-height:100vh;background:linear-gradient(135deg,#fff7ed,#dcfce7);color:#172018}
header{background:white;padding:18px 6%;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;box-shadow:0 2px 15px #0001}
.brand{font-size:24px;font-weight:800;color:#f97316}
.back-btn{background:#111827;color:white;padding:11px 16px;border-radius:9px;text-decoration:none;font-weight:bold}
.container{max-width:1100px;margin:auto;padding:40px 20px}
h1{text-align:center;font-size:36px;margin-bottom:10px}
.subtitle{text-align:center;color:#667085;margin-bottom:30px}
.card{background:white;padding:24px;border-radius:16px;box-shadow:0 6px 20px #0001;margin-bottom:22px}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:15px}
.top h2{font-size:21px;color:#f97316}
.details{font-size:13px;color:#667085;line-height:1.8;margin-bottom:12px}
.status{display:inline-block;padding:7px 12px;border-radius:20px;font-size:12px;font-weight:bold}
.open{background:#fef3c7;color:#92400e}
.replied{background:#dbeafe;color:#1d4ed8}
.resolved{background:#dcfce7;color:#166534}
.chat-message{padding:14px;border-radius:10px;margin:12px 0;line-height:1.6;white-space:pre-wrap;overflow-wrap:anywhere}
.original{background:#fff7ed}
.customer{background:#fff7ed;border-left:4px solid #f97316}
.admin{background:#dcfce7;border-left:4px solid #16a34a}
.sender{font-weight:bold;margin-bottom:5px}
.date{font-size:12px;color:#667085;margin-top:7px}
label{display:block;font-weight:bold;margin-bottom:8px}
textarea{width:100%;min-height:110px;padding:13px;border:1px solid #d1d5db;border-radius:9px;margin-bottom:12px;font-size:15px;resize:vertical}
textarea:focus{outline:none;border-color:#f97316}
button{border:0;border-radius:8px;padding:11px 17px;font-weight:bold;cursor:pointer}
.reply-btn{background:#f97316;color:white}
.resolve-btn{background:#16a34a;color:white}
.button-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}
.notification{padding:14px;border-radius:9px;text-align:center;font-weight:bold;margin-bottom:20px}
.success{background:#dcfce7;color:#166534}
.error{background:#fee2e2;color:#991b1b}
.empty{text-align:center;color:#667085;padding:35px;background:white;border-radius:15px}
@media(max-width:600px){header{padding:15px 4%}.brand{font-size:20px}.container{padding:28px 12px}h1{font-size:29px}.card{padding:17px}}
</style>
</head>

<body>
<header>
    <div class="brand">🍽 Chekananke</div>
    <a href="admin_dashboard.php" class="back-btn">Back to Dashboard</a>
</header>

<main class="container">
    <h1>Customer Support</h1>
    <p class="subtitle">View customer conversations and reply to their messages.</p>

    <?php if ($notice !== ""): ?>
        <div class="notification <?php echo htmlspecialchars($notice_type); ?>">
            <?php echo htmlspecialchars($notice); ?>
        </div>
    <?php endif; ?>

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

            <section class="card">
                <div class="top">
                    <h2>Conversation #<?php echo $support_id; ?></h2>
                    <span class="status <?php echo $status_class; ?>">
                        <?php echo htmlspecialchars($conversation["status"]); ?>
                    </span>
                </div>

                <div class="details">
                    <strong>Customer:</strong>
                    <?php echo htmlspecialchars($conversation["email"] ?? "Unknown customer"); ?>
                    <br>

                    <strong>Customer ID:</strong>
                    <?php echo (int)$conversation["user_id"]; ?>
                    <br>

                    <strong>Order:</strong>
                    <?php echo $conversation["order_id"] !== null ? "#" . (int)$conversation["order_id"] : "General Concern / Other"; ?>
                    <br>

                    <strong>Started:</strong>
                    <?php echo htmlspecialchars($conversation["created_at"]); ?>
                </div>

                <div class="chat-message original">
                    <div class="sender">Customer</div>
                    <?php echo nl2br(htmlspecialchars($conversation["message"])); ?>
                    <div class="date">
                        <?php echo htmlspecialchars($conversation["created_at"]); ?>
                    </div>
                </div>

                <?php while ($reply = $replies->fetch_assoc()): ?>
                    <?php $is_admin = $reply["sender_role"] === "admin"; ?>
                    <div class="chat-message <?php echo $is_admin ? "admin" : "customer"; ?>">
                        <div class="sender">
                            <?php echo $is_admin ? "You (Admin)" : "Customer"; ?>
                        </div>
                        <?php echo nl2br(htmlspecialchars($reply["reply_message"])); ?>
                        <div class="date">
                            <?php echo htmlspecialchars($reply["created_at"]); ?>
                        </div>
                    </div>
                <?php endwhile; ?>

                <?php $reply_stmt->close(); ?>

                <form method="POST" style="margin-top:18px;border-top:1px solid #eee;padding-top:16px;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="support_id" value="<?php echo $support_id; ?>">

                    <label>Reply to Customer</label>
                    <textarea name="message" maxlength="5000" placeholder="Write your reply..." required></textarea>

                    <div class="button-row">
                        <button type="submit" class="reply-btn">Send Reply</button>
                    </div>
                </form>

                <?php if ($status !== "resolved"): ?>
                    <form method="POST" onsubmit="return confirm('Mark this conversation as resolved?');" style="margin-top:12px;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
                        <input type="hidden" name="action" value="resolve">
                        <input type="hidden" name="support_id" value="<?php echo $support_id; ?>">
                        <button type="submit" class="resolve-btn">Mark as Resolved</button>
                    </form>
                <?php endif; ?>
            </section>

        <?php endwhile; ?>

    <?php else: ?>
        <div class="empty">There are no customer support messages yet.</div>
    <?php endif; ?>
</main>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
