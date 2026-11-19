<?php
date_default_timezone_set("Asia/Manila");
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (($_SESSION["loggedIn"] ?? false) !== true) {
    header("Location: login_page.php");
    exit;
}

require_once __DIR__ . "/db.php";

$user_id = (int)($_SESSION["user_id"] ?? 0);
if ($user_id <= 0) {
    die("User session not found. Please log in again.");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function support_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function customerSupportRedirect($message, $type = "success") {
    $_SESSION["support_message"] = $message;
    $_SESSION["support_message_type"] = $type;
    header("Location: contact_support.php");
    exit;
}

// Handle customer messages and replies.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) || !hash_equals($_SESSION["csrf_token"], $token)) {
        customerSupportRedirect("Invalid request. Please try again.", "error");
    }

    $action = (string)($_POST["action"] ?? "");
    $message = trim((string)($_POST["message"] ?? ""));

    if ($message === "" || mb_strlen($message) > 5000) {
        customerSupportRedirect("Please enter a message of up to 5000 characters.", "error");
    }

    if ($action === "reply") {
        $support_id = filter_var($_POST["support_id"] ?? "", FILTER_VALIDATE_INT);

        if (!$support_id || $support_id < 1) {
            customerSupportRedirect("Invalid conversation.", "error");
        }

        $check = $conn->prepare(
            "SELECT status FROM support_messages WHERE id = ? AND user_id = ?"
        );
        $check->bind_param("ii", $support_id, $user_id);
        $check->execute();
        $conversation = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$conversation) {
            customerSupportRedirect("Conversation not found.", "error");
        }

        if (strtolower(trim((string)$conversation["status"])) === "resolved") {
            customerSupportRedirect(
                "This conversation has been resolved. Replies are disabled.",
                "error"
            );
        }

        $insert = $conn->prepare(
            "INSERT INTO support_replies (support_id, sender_role, reply_message)
             VALUES (?, 'customer', ?)"
        );
        $insert->bind_param("is", $support_id, $message);
        $insert->execute();
        $insert->close();

        $update = $conn->prepare(
            "UPDATE support_messages SET status = 'Open' WHERE id = ? AND user_id = ?"
        );
        $update->bind_param("ii", $support_id, $user_id);
        $update->execute();
        $update->close();

        customerSupportRedirect("Your reply has been sent.");
    }

    if ($action === "new") {
        $order_input = trim((string)($_POST["order_id"] ?? ""));
        $order_id = null;

        if ($order_input !== "" && strtolower($order_input) !== "other") {
            $order_id = filter_var($order_input, FILTER_VALIDATE_INT);

            if (!$order_id || $order_id < 1) {
                customerSupportRedirect("Invalid order selected.", "error");
            }

            $check = $conn->prepare(
                "SELECT id FROM orders WHERE id = ? AND user_id = ?"
            );
            $check->bind_param("ii", $order_id, $user_id);
            $check->execute();
            $valid = $check->get_result()->num_rows > 0;
            $check->close();

            if (!$valid) {
                customerSupportRedirect("The selected order is invalid.", "error");
            }
        }

        if ($order_id === null) {
            $insert = $conn->prepare(
                "INSERT INTO support_messages (user_id, order_id, message, status)
                 VALUES (?, NULL, ?, 'Open')"
            );
            $insert->bind_param("is", $user_id, $message);
        } else {
            $insert = $conn->prepare(
                "INSERT INTO support_messages (user_id, order_id, message, status)
                 VALUES (?, ?, ?, 'Open')"
            );
            $insert->bind_param("iis", $user_id, $order_id, $message);
        }

        $insert->execute();
        $insert->close();

        customerSupportRedirect("Your support message has been sent.");
    }

    customerSupportRedirect("Invalid action.", "error");
}

// One-time notification.
$notice = (string)($_SESSION["support_message"] ?? "");
$notice_type = (string)($_SESSION["support_message_type"] ?? "");
unset($_SESSION["support_message"], $_SESSION["support_message_type"]);

// Customer's orders for the optional order selector.
$order_stmt = $conn->prepare(
    "SELECT id, product_name FROM orders WHERE user_id = ? ORDER BY order_date DESC"
);
$order_stmt->bind_param("i", $user_id);
$order_stmt->execute();
$orders = $order_stmt->get_result();
$orderOptions = [];
while ($order = $orders->fetch_assoc()) {
    $orderOptions[] = $order;
}
$order_stmt->close();

// Customer's own support conversations only.
$stmt = $conn->prepare(
    "SELECT * FROM support_messages WHERE user_id = ? ORDER BY created_at DESC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$conversations = $stmt->get_result();
$conversationRows = [];
while ($conversation = $conversations->fetch_assoc()) {
    $conversationRows[] = $conversation;
}
$stmt->close();

$customerName = (string)(
    $_SESSION["name"] ??
    $_SESSION["full_name"] ??
    $_SESSION["username"] ??
    "Customer"
);
$customerInitial = strtoupper(substr(trim($customerName), 0, 1));
if ($customerInitial === "") {
    $customerInitial = "C";
}
$isAdmin = (($_SESSION["role"] ?? "") === "admin");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contact Support | Kananke Food Hauz</title>
<style>
*{box-sizing:border-box}
:root{--gold:#f5b400;--orange:#f28b28;--dark:#292929;--muted:#888;--background:#f6f7f9;--white:#fff;--border:#eee}
body{margin:0;font-family:Arial,Helvetica,sans-serif;color:var(--dark);background:var(--background)}
a{text-decoration:none;color:inherit}
button,input,select,textarea{font:inherit}
.app-layout{display:grid;grid-template-columns:220px minmax(0,1fr) 285px;min-height:100vh}
.sidebar{background:#fff;padding:25px 16px;border-right:1px solid var(--border);display:flex;flex-direction:column;min-height:100vh}
.logo{font-size:23px;font-weight:900;color:var(--orange);padding:4px 12px 30px}
.logo small{display:block;font-size:10px;letter-spacing:2px;color:#999;margin-top:5px}
.nav-heading{font-size:10px;font-weight:bold;color:#aaa;letter-spacing:1.5px;padding:12px}
.nav-link{display:flex;align-items:center;gap:12px;padding:13px 12px;border-radius:10px;color:#777;font-size:13px;font-weight:600;margin:3px 0;transition:.2s}
.nav-link:hover{background:#fff8e5;color:#bd8500}
.nav-link.active{color:#9a6a00;background:#fff2c8}
.nav-icon{width:22px;text-align:center;font-size:17px}
.sidebar-bottom{margin-top:auto;border-top:1px solid var(--border);padding-top:16px}
.main-content{min-width:0;padding:27px 28px}
.topbar{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:25px}
.welcome-text{color:var(--muted);font-size:12px;margin-bottom:5px}
.topbar h1{margin:0;font-size:25px}
.profile-circle{width:42px;height:42px;flex:0 0 42px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#ffedbd;color:#9a6a00;font-weight:bold}
.banner{padding:23px 25px;border-radius:15px;background:linear-gradient(110deg,#ffda74,#ffedbd);display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:22px;overflow:hidden}
.banner h2{margin:0 0 8px;font-size:20px}
.banner p{margin:0;color:#755a22;font-size:12px;line-height:1.6}
.banner-icon{font-size:48px}
.notice{padding:13px 15px;border-radius:10px;font-size:12px;font-weight:700;margin-bottom:17px}
.notice.success{background:#eaf8ed;color:#216b35}
.notice.error{background:#fff0ed;color:#a52c1d}
.card{background:#fff;border:1px solid #f0f0f0;border-radius:14px;padding:21px;margin-bottom:18px;box-shadow:0 3px 12px rgba(0,0,0,.035)}
.card-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}
.card-heading h2{font-size:17px;margin:0}
.card-heading p{font-size:11px;color:var(--muted);margin:5px 0 0}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-group{min-width:0}
.form-group.full{grid-column:1/-1}
label{display:block;font-size:12px;font-weight:700;margin-bottom:7px}
select,textarea{width:100%;border:1px solid #e2e2e2;border-radius:9px;padding:12px;background:#fff;color:#333;outline:none;font-size:13px}
select:focus,textarea:focus{border-color:var(--orange);box-shadow:0 0 0 3px #f28b2818}
textarea{min-height:115px;resize:vertical;line-height:1.5}
.form-footer{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:13px}
.helper-text{font-size:10px;color:var(--muted)}
.primary-btn,.reply-btn{border:0;border-radius:9px;padding:11px 16px;background:var(--gold);color:#493500;font-size:12px;font-weight:800;cursor:pointer}
.primary-btn:hover,.reply-btn:hover{background:#e8a900}
.conversation{border:1px solid #eee;border-radius:12px;padding:16px;margin-bottom:14px}
.conversation:last-child{margin-bottom:0}
.conversation-top{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px}
.conversation-top h3{font-size:14px;margin:0}
.status{display:inline-flex;align-items:center;padding:6px 10px;border-radius:20px;font-size:10px;font-weight:800}
.status.open{background:#fff3cf;color:#936400}
.status.replied{background:#e5efff;color:#2456a6}
.status.resolved{background:#e5f7e9;color:#216b35}
.meta{font-size:10px;color:var(--muted);margin-bottom:10px}
.chat-message{padding:12px 13px;border-radius:10px;margin:10px 0;line-height:1.55;white-space:pre-wrap;overflow-wrap:anywhere;font-size:12px}
.chat-message.original,.chat-message.customer{background:#fff8ed;border-left:4px solid var(--orange)}
.chat-message.admin{background:#edf8ef;border-left:4px solid #4a9a5b}
.sender{font-weight:800;margin-bottom:5px;font-size:11px}
.message-date{font-size:10px;color:#888;margin-top:7px}
.reply-form{border-top:1px solid #eee;margin-top:14px;padding-top:14px}
.reply-form textarea{min-height:75px}
.reply-form-bottom{display:flex;justify-content:flex-end;margin-top:8px}
.resolved-notice{margin-top:13px;padding:11px;background:#f4f5f6;color:#60656b;border-radius:9px;font-size:11px;font-weight:700;text-align:center}
.empty-state{text-align:center;padding:32px 15px;color:var(--muted);font-size:12px}
.empty-state .empty-icon{font-size:35px;margin-bottom:10px}
.right-panel{background:#fff;border-left:1px solid var(--border);padding:25px 17px;min-width:0}
.account-card{display:flex;align-items:center;gap:10px;padding-bottom:20px;border-bottom:1px solid var(--border)}
.account-card strong{display:block;font-size:12px}
.account-card small{display:block;font-size:10px;color:var(--muted);margin-top:4px}
.side-card{margin-top:22px}
.side-card h3{font-size:14px;margin:0 0 12px}
.info-item{display:flex;align-items:flex-start;gap:10px;padding:12px 0;border-bottom:1px solid #f1f1f1}
.info-icon{width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:9px;background:#fff4d7;font-size:15px;flex:0 0 32px}
.info-item strong{display:block;font-size:11px;margin-bottom:4px}
.info-item small{display:block;color:var(--muted);font-size:10px;line-height:1.5}
.side-note{padding:14px;border-radius:11px;background:#fff8e8;color:#76571c;font-size:11px;line-height:1.6;margin-top:18px}
.mobile-cart{display:none}
@media(max-width:1050px){.app-layout{grid-template-columns:190px minmax(0,1fr)}.right-panel{grid-column:2;border-left:0;border-top:1px solid var(--border);min-height:auto}.main-content{padding:22px}}
@media(max-width:650px){
.app-layout{display:flex;flex-direction:column}
.sidebar{min-height:auto;padding:12px 12px 9px;position:sticky;top:0;z-index:50;border-right:0;border-bottom:1px solid var(--border)}
.logo{padding:2px 5px 10px;font-size:19px}.logo small{display:none}
.nav-heading,.sidebar-bottom{display:none}
.sidebar nav{display:flex;overflow-x:auto;gap:5px;scrollbar-width:none}
.sidebar nav::-webkit-scrollbar{display:none}
.nav-link{flex:0 0 auto;padding:9px 11px;margin:0;font-size:11px;white-space:nowrap}
.nav-icon{font-size:14px;width:auto}
.main-content{padding:18px 14px 25px}
.topbar{margin-bottom:18px}.topbar h1{font-size:21px}
.banner{padding:18px;margin-bottom:17px}.banner h2{font-size:17px}.banner p{font-size:11px}.banner-icon{font-size:38px}
.card{padding:16px}.form-grid{grid-template-columns:1fr}.form-group.full{grid-column:auto}
.form-footer{align-items:stretch;flex-direction:column}.primary-btn{width:100%}
.conversation{padding:13px}
.right-panel{display:block;grid-column:auto;padding:19px 14px 28px;border-top:1px solid var(--border)}
}
</style>
</head>
<body>
<div class="app-layout">
    <aside class="sidebar">
        <a href="index.php" class="logo">KANANKE<small>FOOD HAUZ</small></a>
        <div class="nav-heading">MAIN MENU</div>
        <nav>
            <a class="nav-link" href="index.php"><span class="nav-icon">⌂</span>Dashboard</a>
            <a class="nav-link" href="tray.php"><span class="nav-icon">🍗</span>Food by Tray</a>
            <a class="nav-link" href="bundle.php"><span class="nav-icon">🍱</span>Food Bundles</a>
            <a class="nav-link" href="additionals.php"><span class="nav-icon">🥤</span>Additionals</a>
            <a class="nav-link" href="order_history.php"><span class="nav-icon">📋</span>My Orders</a>
            <a class="nav-link active" href="contact_support.php"><span class="nav-icon">💬</span>Messages</a>
            <?php if ($isAdmin): ?>
                <a class="nav-link" href="admin_dashboard.php"><span class="nav-icon">⚙</span>Admin Panel</a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-bottom">
            <a class="nav-link" href="profile.php"><span class="nav-icon">👤</span>My Profile</a>
            <a class="nav-link" href="logout.php"><span class="nav-icon">↪</span>Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <div>
                <div class="welcome-text">Welcome back, <?= support_h($customerName) ?>!</div>
                <h1>Contact Support</h1>
            </div>
            <div class="profile-circle"><?= support_h($customerInitial) ?></div>
        </div>

        <section class="banner">
            <div>
                <h2>We're here to help!</h2>
                <p>Send us your questions or concerns and our support team will reply here.<br>Keep your conversation in one place.</p>
            </div>
            <div class="banner-icon">💬</div>
        </section>

        <?php if ($notice !== ""): ?>
            <div class="notice <?= $notice_type === "error" ? "error" : "success" ?>">
                <?= support_h($notice) ?>
            </div>
        <?php endif; ?>

        <section class="card">
            <div class="card-heading">
                <div>
                    <h2>Start a New Conversation</h2>
                    <p>Tell us how we can assist you.</p>
                </div>
                <span>✉️</span>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= support_h($_SESSION["csrf_token"]) ?>">
                <input type="hidden" name="action" value="new">

                <div class="form-grid">
                    <div class="form-group full">
                        <label for="order_id">Related Order (Optional)</label>
                        <select name="order_id" id="order_id">
                            <option value="">General Concern / No Order</option>
                            <option value="other">Other</option>
                            <?php foreach ($orderOptions as $order): ?>
                                <option value="<?= (int)$order["id"] ?>">
                                    Order #<?= (int)$order["id"] ?> - <?= support_h($order["product_name"] ?? "Order") ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label for="message">Your Message</label>
                        <textarea name="message" id="message" maxlength="5000" placeholder="Write your question or concern..." required></textarea>
                    </div>
                </div>

                <div class="form-footer">
                    <span class="helper-text">Maximum 5,000 characters.</span>
                    <button type="submit" class="primary-btn">Send Message&nbsp; →</button>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="card-heading">
                <div>
                    <h2>My Support Messages</h2>
                    <p>View your conversations and replies from our support team.</p>
                </div>
                <span class="status open"><?= count($conversationRows) ?> conversations</span>
            </div>

            <?php if (count($conversationRows) > 0): ?>
                <?php foreach ($conversationRows as $conversation): ?>
                    <?php
                    $support_id = (int)$conversation["id"];
                    $status = strtolower(trim((string)($conversation["status"] ?? "open")));
                    $status_class = in_array($status, ["open", "replied", "resolved"], true) ? $status : "open";

                    $reply_stmt = $conn->prepare(
                        "SELECT id, sender_role, reply_message, created_at
                         FROM support_replies
                         WHERE support_id = ?
                         ORDER BY created_at ASC, id ASC"
                    );
                    $reply_stmt->bind_param("i", $support_id);
                    $reply_stmt->execute();
                    $replies = $reply_stmt->get_result();
                    $replyRows = [];
                    while ($reply = $replies->fetch_assoc()) {
                        $replyRows[] = $reply;
                    }
                    $reply_stmt->close();
                    ?>
                    <article class="conversation">
                        <div class="conversation-top">
                            <h3>Conversation #<?= $support_id ?></h3>
                            <span class="status <?= support_h($status_class) ?>"><?= support_h(ucfirst($status)) ?></span>
                        </div>

                        <div class="meta">
                            <?php if (!empty($conversation["order_id"])): ?>
                                Related Order: #<?= (int)$conversation["order_id"] ?> &nbsp;·&nbsp;
                            <?php else: ?>
                                General Concern &nbsp;·&nbsp;
                            <?php endif; ?>
                            Sent <?= support_h($conversation["created_at"] ?? "") ?>
                        </div>

                        <div class="chat-message original">
                            <div class="sender">You</div>
                            <?= nl2br(support_h($conversation["message"] ?? "")) ?>
                            <div class="message-date"><?= support_h($conversation["created_at"] ?? "") ?></div>
                        </div>

                        <?php foreach ($replyRows as $reply): ?>
                            <?php $is_admin_reply = (($reply["sender_role"] ?? "") === "admin"); ?>
                            <div class="chat-message <?= $is_admin_reply ? "admin" : "customer" ?>">
                                <div class="sender"><?= $is_admin_reply ? "Kananke Customer Support" : "You" ?></div>
                                <?= nl2br(support_h($reply["reply_message"] ?? "")) ?>
                                <div class="message-date"><?= support_h($reply["created_at"] ?? "") ?></div>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($status !== "resolved"): ?>
                            <form method="POST" class="reply-form">
                                <input type="hidden" name="csrf_token" value="<?= support_h($_SESSION["csrf_token"]) ?>">
                                <input type="hidden" name="action" value="reply">
                                <input type="hidden" name="support_id" value="<?= $support_id ?>">
                                <label for="reply_<?= $support_id ?>">Reply to this conversation</label>
                                <textarea id="reply_<?= $support_id ?>" name="message" maxlength="5000" placeholder="Write your reply..." required></textarea>
                                <div class="reply-form-bottom">
                                    <button type="submit" class="reply-btn">Send Reply</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="resolved-notice">🔒 This conversation has been resolved. Replies are disabled.</div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">💬</div>
                    <strong>No support conversations yet.</strong>
                    <p>Send a message above if you need help.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <aside class="right-panel">
        <div class="account-card">
            <div class="profile-circle"><?= support_h($customerInitial) ?></div>
            <div>
                <strong><?= support_h($customerName) ?></strong>
                <small>Customer Account</small>
            </div>
        </div>

        <section class="side-card">
            <h3>Support Information</h3>
            <div class="info-item">
                <div class="info-icon">💬</div>
                <div><strong>Message Support</strong><small>Send your concern using the form on this page.</small></div>
            </div>
            <div class="info-item">
                <div class="info-icon">📋</div>
                <div><strong>Related Order</strong><small>Select an order if your concern is about a specific purchase.</small></div>
            </div>
            <div class="info-item">
                <div class="info-icon">🔔</div>
                <div><strong>Check Replies</strong><small>Return to this page to read the support team's response.</small></div>
            </div>
        </section>

        <div class="side-note">
            <strong>Need help?</strong><br>
            Keep your messages clear and include the related order when possible so our team can assist you faster.
        </div>
    </aside>
</div>
</body>
</html>
