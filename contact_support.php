
<?php
session_start();

if (
    !isset($_SESSION["loggedIn"]) ||
    $_SESSION["loggedIn"] !== true
) {
    header("Location: login_page.php");
    exit;
}

require_once "db.php";

$user_id = (int) $_SESSION["user_id"];

if (empty($_SESSION["support_csrf"])) {
    $_SESSION["support_csrf"] = bin2hex(random_bytes(32));
}

$message = "";
$message_type = "";

if (isset($_SESSION["support_notice"])) {
    $message = $_SESSION["support_notice"];
    $message_type = $_SESSION["support_notice_type"];

    unset(
        $_SESSION["support_notice"],
        $_SESSION["support_notice_type"]
    );
}

// Send customer message.
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["send_message"])) {

    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["support_csrf"], $token)) {
        $_SESSION["support_notice"] = "Invalid request. Please try again.";
        $_SESSION["support_notice_type"] = "error";
        header("Location: contact_support.php");
        exit;
    }

    $order_id = !empty($_POST["order_id"])
        ? filter_var($_POST["order_id"], FILTER_VALIDATE_INT)
        : null;

    $customer_message = trim($_POST["message"] ?? "");

    if (strlen($customer_message) < 3 || strlen($customer_message) > 5000) {

        $_SESSION["support_notice"] =
            "Your message must contain between 3 and 5000 characters.";

        $_SESSION["support_notice_type"] = "error";

    } else {

        // Verify that the selected order belongs to the customer.
        if ($order_id !== null && $order_id !== false) {

            $check = $conn->prepare(
                "SELECT id FROM orders WHERE id = ? AND user_id = ?"
            );

            $check->bind_param("ii", $order_id, $user_id);
            $check->execute();

            $order_result = $check->get_result();

            if ($order_result->num_rows === 0) {
                $_SESSION["support_notice"] = "Invalid order selected.";
                $_SESSION["support_notice_type"] = "error";
                $check->close();

                header("Location: contact_support.php");
                exit;
            }

            $check->close();

        } else {
            $order_id = null;
        }

        $insert = $conn->prepare(
            "INSERT INTO support_messages
             (user_id, order_id, message, status)
             VALUES (?, ?, ?, 'Open')"
        );

        $insert->bind_param(
            "iis",
            $user_id,
            $order_id,
            $customer_message
        );

        $insert->execute();
        $insert->close();

        $_SESSION["support_notice"] =
            "Your message has been sent successfully!";

        $_SESSION["support_notice_type"] = "success";
    }

    header("Location: contact_support.php");
    exit;
}

// Get customer's orders for the dropdown.
$order_stmt = $conn->prepare(
    "SELECT id, product_name, status
     FROM orders
     WHERE user_id = ?
     ORDER BY order_date DESC"
);

$order_stmt->bind_param("i", $user_id);
$order_stmt->execute();

$orders = $order_stmt->get_result();

// Get customer's support conversations.
$msg_stmt = $conn->prepare(
    "SELECT *
     FROM support_messages
     WHERE user_id = ?
     ORDER BY created_at DESC"
);

$msg_stmt->bind_param("i", $user_id);
$msg_stmt->execute();

$messages = $msg_stmt->get_result();

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
    background: linear-gradient(135deg, #fff7ed, #dcfce7);
    min-height: 100vh;
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
    color: #f97316;
    font-size: 23px;
    font-weight: 800;
}

.back {
    background: #111827;
    color: white;
    text-decoration: none;
    padding: 10px 16px;
    border-radius: 8px;
}

.container {
    max-width: 850px;
    margin: auto;
    padding: 40px 20px;
}

h1 {
    text-align: center;
    margin-bottom: 10px;
}

.subtitle {
    color: #667085;
    text-align: center;
    margin-bottom: 25px;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 16px;
    box-shadow: 0 8px 25px #0001;
    margin-bottom: 25px;
}

label {
    display: block;
    font-weight: bold;
    margin: 16px 0 8px;
}

select, textarea {
    width: 100%;
    padding: 13px;
    border: 1px solid #ddd;
    border-radius: 9px;
    font-size: 15px;
}

textarea {
    min-height: 130px;
    resize: vertical;
}

.send-btn {
    width: 100%;
    padding: 14px;
    margin-top: 18px;
    border: none;
    border-radius: 9px;
    background: #f97316;
    color: white;
    font-weight: bold;
    font-size: 16px;
    cursor: pointer;
}

.send-btn:hover {
    background: #ea580c;
}

.notice {
    padding: 13px;
    border-radius: 8px;
    margin-bottom: 18px;
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

.message-card {
    border: 1px solid #eee;
    border-radius: 12px;
    padding: 17px;
    margin-top: 15px;
}

.message-card p {
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.reply {
    background: #f0fdf4;
    border-left: 4px solid #16a34a;
    padding: 14px;
    margin-top: 15px;
    border-radius: 6px;
}

.status {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    background: #fef3c7;
    color: #92400e;
    margin: 10px 0;
}

.status.replied, .status.resolved {
    background: #dcfce7;
    color: #166534;
}

small {
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
        padding: 18px;
    }
}
</style>

</head>

<body>

<header>
    <div class="brand">🍽 Chekananke</div>
    <a href="index.php" class="back">Back to Home</a>
</header>

<div class="container">

    <h1>Contact Support</h1>

    <p class="subtitle">
        Have a problem with your order? Send us a message.
        Our team will assist you.
    </p>

    <div class="card">

        <h2>Send a Message</h2>

        <?php if ($message !== ""): ?>
            <div class="notice <?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="contact_support.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION["support_csrf"]); ?>"
            >

            <label for="order_id">Related Order (Optional)</label>

            <select name="order_id" id="order_id">
                <option value="">General Inquiry</option>

                <?php while ($order = $orders->fetch_assoc()): ?>
                    <option value="<?php echo (int) $order["id"]; ?>">
                        Order #<?php echo (int) $order["id"]; ?>
                        - <?php echo htmlspecialchars($order["product_name"]); ?>
                        (<?php echo htmlspecialchars($order["status"]); ?>)
                    </option>
                <?php endwhile; ?>

            </select>

            <label for="message">Your Message</label>

            <textarea
                name="message"
                id="message"
                placeholder="Describe your concern or question..."
                minlength="3"
                maxlength="5000"
                required
            ></textarea>

            <button
                type="submit"
                name="send_message"
                class="send-btn"
            >
                Send Message
            </button>

        </form>

    </div>

    <div class="card">

        <h2>My Support Messages</h2>

        <?php if ($messages->num_rows > 0): ?>

            <?php while ($msg = $messages->fetch_assoc()): ?>

                <div class="message-card">

                    <small>
                        Message #<?php echo (int) $msg["id"]; ?>
                        <?php if (!empty($msg["order_id"])): ?>
                            | Order #<?php echo (int) $msg["order_id"]; ?>
                        <?php endif; ?>
                        | <?php echo htmlspecialchars($msg["created_at"]); ?>
                    </small>

                    <br>

                    <span class="status <?php echo strtolower($msg["status"]) === "open" ? "" : htmlspecialchars(strtolower($msg["status"])); ?>">
                        <?php echo htmlspecialchars($msg["status"]); ?>
                    </span>

                    <p>
                        <?php echo nl2br(htmlspecialchars($msg["message"])); ?>
                    </p>

                    <?php if (!empty($msg["admin_reply"])): ?>

                        <div class="reply">

                            <strong>Owner/Admin Reply</strong>

                            <p>
                                <?php echo nl2br(htmlspecialchars($msg["admin_reply"])); ?>
                            </p>

                            <?php if (!empty($msg["replied_at"])): ?>
                                <small>
                                    <?php echo htmlspecialchars($msg["replied_at"]); ?>
                                </small>
                            <?php endif; ?>

                        </div>

                    <?php else: ?>

                        <p>
                            <small>Waiting for the owner/admin to reply.</small>
                        </p>

                    <?php endif; ?>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <p style="margin-top:15px;color:#667085;">
                You haven't sent any support messages yet.
            </p>

        <?php endif; ?>

    </div>

</div>

</body>
</html>

<?php
$order_stmt->close();
$msg_stmt->close();
$conn->close();
?>
