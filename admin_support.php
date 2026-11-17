<?php
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

if (empty($_SESSION["admin_support_csrf"])) {
    $_SESSION["admin_support_csrf"] = bin2hex(random_bytes(32));
}

$notice = "";
$error = "";

/* HANDLE ADMIN ACTIONS */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["admin_support_csrf"], $token)
    ) {
        $error = "Invalid request. Please refresh the page.";
    } else {

        $messageId = filter_var(
            $_POST["message_id"] ?? null,
            FILTER_VALIDATE_INT
        );

        $action = $_POST["action"] ?? "";

        if (!$messageId || $messageId < 1) {

            $error = "Invalid message selected.";

        } elseif ($action === "reply") {

            $reply = trim($_POST["admin_reply"] ?? "");

            if ($reply === "") {

                $error = "Please enter a reply.";

            } elseif (mb_strlen($reply) > 5000) {

                $error = "Your reply is too long. Maximum is 5,000 characters.";

            } else {

                // Confirm the conversation exists and is not resolved.
                $check = $conn->prepare(
                    "SELECT id
                     FROM support_messages
                     WHERE id = ? AND status <> 'Resolved'
                     LIMIT 1"
                );

                $check->bind_param("i", $messageId);
                $check->execute();

                $exists = $check->get_result()->num_rows > 0;
                $check->close();

                if (!$exists) {

                    $error = "Message not found or already resolved.";

                } else {

                    $conn->begin_transaction();

                    try {

                        // Save the admin reply for customer notification.
                        $insert = $conn->prepare(
                            "INSERT INTO support_replies
                             (support_id, sender_role, reply_message)
                             VALUES (?, 'admin', ?)"
                        );

                        $insert->bind_param("is", $messageId, $reply);
                        $insert->execute();
                        $insert->close();

                        // Keep the previous admin_reply field updated too.
                        $update = $conn->prepare(
                            "UPDATE support_messages
                             SET admin_reply = ?,
                                 status = 'Replied',
                                 replied_at = UTC_TIMESTAMP()
                             WHERE id = ? AND status <> 'Resolved'"
                        );

                        $update->bind_param("si", $reply, $messageId);
                        $update->execute();
                        $update->close();

                        $conn->commit();

                        $notice = "Your reply has been sent successfully.";

                    } catch (Throwable $e) {

                        $conn->rollback();
                        $error = "Unable to send reply. Please try again.";
                    }
                }
            }

        } elseif ($action === "resolve") {

            $stmt = $conn->prepare(
                "UPDATE support_messages
                 SET status = 'Resolved'
                 WHERE id = ?"
            );

            $stmt->bind_param("i", $messageId);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $notice = "Message marked as resolved.";
            } else {
                $error = "Message not found or already resolved.";
            }

            $stmt->close();

        } else {

            $error = "Invalid action.";
        }
    }

    $_SESSION["admin_support_csrf"] = bin2hex(random_bytes(32));
}

/* GET SUPPORT MESSAGES AND LATEST REPLY */
$sql = "
    SELECT
        sm.id,
        sm.user_id,
        sm.order_id,
        sm.message,
        sm.admin_reply,
        sm.status,
        sm.created_at,
        sm.replied_at,
        u.email,

        (
            SELECT sr.sender_role
            FROM support_replies sr
            WHERE sr.support_id = sm.id
            ORDER BY sr.id DESC
            LIMIT 1
        ) AS last_sender_role

    FROM support_messages sm

    LEFT JOIN users u
        ON sm.user_id = u.id

    ORDER BY
        CASE
            WHEN sm.status = 'Open' THEN 0
            WHEN sm.status = 'Replied' THEN 1
            ELSE 2
        END,
        sm.created_at DESC
";

$messages = $conn->query($sql);
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
    font-family: Arial, sans-serif;
}

body {
    margin: 0;
    padding: 25px 15px;
    background: #fff7ed;
    color: #172018;
}

.container {
    max-width: 1000px;
    margin: auto;
}

header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

h1 {
    color: #f97316;
}

header a {
    color: #f97316;
    text-decoration: none;
    font-weight: bold;
}

.notice, .error {
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.notice {
    background: #dcfce7;
    color: #166534;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

.message-card {
    background: white;
    padding: 22px;
    border-radius: 12px;
    margin-bottom: 18px;
    box-shadow: 0 4px 15px #0001;
}

.message-text, .reply-text {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    line-height: 1.6;
}

.meta {
    color: #667085;
    font-size: 13px;
    line-height: 1.7;
    margin: 10px 0;
}

.status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    background: #fef3c7;
    color: #92400e;
}

.status.Replied {
    background: #dbeafe;
    color: #1d4ed8;
}

.status.Resolved {
    background: #dcfce7;
    color: #166534;
}

/* RED DOT FOR MESSAGES WAITING FOR ADMIN REPLY */
.message-number {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.red-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    min-width: 10px;
    border-radius: 50%;
    background: #dc2626;
    box-shadow: 0 0 0 3px #fee2e2;
}

.waiting-label {
    color: #dc2626;
    font-size: 12px;
    font-weight: bold;
}

.reply-box {
    background: #f0fdf4;
    border-left: 4px solid #16a34a;
    padding: 14px;
    margin-top: 15px;
    border-radius: 6px;
}

textarea {
    width: 100%;
    min-height: 100px;
    padding: 12px;
    border: 1px solid #d0d5dd;
    border-radius: 8px;
    resize: vertical;
    font-size: 15px;
    margin-top: 10px;
}

button {
    padding: 11px 17px;
    border: 0;
    border-radius: 8px;
    background: #f97316;
    color: white;
    font-weight: bold;
    cursor: pointer;
    margin: 10px 7px 0 0;
}

button:hover {
    background: #ea580c;
}

.resolve-btn {
    background: #15803d;
}

.resolve-btn:hover {
    background: #166534;
}

.empty {
    background: white;
    padding: 25px;
    border-radius: 12px;
}

@media(max-width: 600px) {
    body {
        padding: 15px 10px;
    }

    .message-card {
        padding: 17px;
    }

    h1 {
        font-size: 24px;
    }
}
</style>
</head>

<body>

<div class="container">

<header>
    <h1>💬 Customer Support</h1>
    <a href="admin_dashboard.php">← Admin Dashboard</a>
</header>

<?php if ($notice): ?>
    <div class="notice">
        <?= htmlspecialchars($notice) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($messages->num_rows === 0): ?>

    <div class="empty">
        There are no customer support messages yet.
    </div>

<?php else: ?>

    <?php while ($row = $messages->fetch_assoc()): ?>

        <?php

        /*
         * Show the red dot when:
         * - The conversation is not resolved.
         * - The customer's latest reply is the latest conversation reply.
         *
         * For older conversations without support_replies records,
         * check whether the old admin_reply field is empty.
         */

        $lastSender = $row["last_sender_role"] ?? null;

        $needsReply = (
            $row["status"] !== "Resolved" &&
            (
                $lastSender === "customer" ||
                (
                    $lastSender === null &&
                    empty($row["admin_reply"])
                )
            )
        );

        ?>

        <div class="message-card">

            <span class="status <?= htmlspecialchars($row["status"]) ?>">
                <?= htmlspecialchars($row["status"]) ?>
            </span>

            <div class="meta">

                <strong class="message-number">

                    Message #<?= (int)$row["id"] ?>

                    <?php if ($needsReply): ?>
                        <span class="red-dot"
                              title="Customer is waiting for an admin reply">
                        </span>

                        <span class="waiting-label">
                            Needs Reply
                        </span>
                    <?php endif; ?>

                </strong>

                <br>

                Customer:
                <?= htmlspecialchars($row["email"] ?? "Unknown customer") ?>

                <br>

                Customer ID:
                <?= (int)$row["user_id"] ?>

                <br>

                <?php if (!empty($row["order_id"])): ?>
                    Related Order: #<?= (int)$row["order_id"] ?>
                    <br>
                <?php endif; ?>

                Sent:
                <?= htmlspecialchars($row["created_at"]) ?>

            </div>

            <h3>Customer Message</h3>

            <p class="message-text">
                <?= htmlspecialchars($row["message"]) ?>
            </p>

            <?php if (!empty($row["admin_reply"])): ?>

                <div class="reply-box">

                    <strong>Previous Admin Reply</strong>

                    <p class="reply-text">
                        <?= htmlspecialchars($row["admin_reply"]) ?>
                    </p>

                    <?php if (!empty($row["replied_at"])): ?>
                        <div class="meta">
                            Replied:
                            <?= htmlspecialchars($row["replied_at"]) ?>
                        </div>
                    <?php endif; ?>

                </div>

            <?php endif; ?>

            <?php if ($row["status"] !== "Resolved"): ?>

                <form method="POST">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= htmlspecialchars($_SESSION["admin_support_csrf"]) ?>">

                    <input type="hidden"
                           name="message_id"
                           value="<?= (int)$row["id"] ?>">

                    <input type="hidden"
                           name="action"
                           value="reply">

                    <label>
                        <strong>Reply to Customer</strong>
                    </label>

                    <textarea
                        name="admin_reply"
                        maxlength="5000"
                        placeholder="Write your reply here..."
                        required
                    ></textarea>

                    <button type="submit">
                        Send Reply
                    </button>

                </form>

                <form method="POST"
                      onsubmit="return confirm('Mark this message as resolved?');">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= htmlspecialchars($_SESSION["admin_support_csrf"]) ?>">

                    <input type="hidden"
                           name="message_id"
                           value="<?= (int)$row["id"] ?>">

                    <input type="hidden"
                           name="action"
                           value="resolve">

                    <button type="submit" class="resolve-btn">
                        Mark as Resolved
                    </button>

                </form>

            <?php endif; ?>

        </div>

    <?php endwhile; ?>

<?php endif; ?>

</div>

</body>
</html>
