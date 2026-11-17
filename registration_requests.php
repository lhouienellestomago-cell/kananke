<?php
session_start();
require_once "db.php";

if (
    !isset($_SESSION["loggedIn"]) ||
    $_SESSION["loggedIn"] !== true ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION["registration_csrf"])) {
    $_SESSION["registration_csrf"] = bin2hex(random_bytes(32));
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";
    $userId = filter_input(INPUT_POST, "user_id", FILTER_VALIDATE_INT);
    $action = $_POST["action"] ?? "";

    if (!hash_equals($_SESSION["registration_csrf"], $token)) {
        $message = "Invalid request. Please refresh the page.";
    } elseif (!$userId || !in_array($action, ["approve", "decline"], true)) {
        $message = "Invalid registration request.";
    } else {
        $newStatus = $action === "approve" ? "Approved" : "Declined";

        $stmt = $conn->prepare(
            "UPDATE users SET status = ?
             WHERE id = ? AND role = 'user' AND status = 'Pending'"
        );
        $stmt->bind_param("si", $newStatus, $userId);
        $stmt->execute();

        if ($stmt->affected_rows === 1) {
            $message = $action === "approve"
                ? "Customer account approved successfully."
                : "Customer registration declined.";
        } else {
            $message = "This request may already have been processed.";
        }
        $stmt->close();
    }
}

$result = $conn->query(
    "SELECT id, full_name, email, profile_picture, profile_picture_type
     FROM users
     WHERE role = 'user' AND status = 'Pending'
     ORDER BY id DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registration Requests</title>
<style>
*{box-sizing:border-box}
body{font-family:Arial,sans-serif;background:#fff7ed;margin:0;padding:25px;color:#222}
.container{max-width:1100px;margin:auto}
h1{color:#f97316}
.top-link{display:inline-block;margin:0 0 20px;color:#ea580c;font-weight:bold;text-decoration:none}
.notice{background:#dcfce7;color:#166534;padding:12px;border-radius:8px;margin:15px 0}
.customer{background:white;border-radius:14px;padding:20px;margin:15px 0;box-shadow:0 4px 15px #0001;display:flex;align-items:center;gap:20px;flex-wrap:wrap}
.avatar{width:95px;height:95px;border-radius:50%;object-fit:cover;background:#eee}
.details{flex:1;min-width:180px}
.details h3{margin:0 0 8px}
.details p{margin:5px 0;color:#555;overflow-wrap:anywhere}
.actions{display:flex;gap:10px;flex-wrap:wrap}
button{border:0;border-radius:8px;padding:12px 18px;color:white;font-weight:bold;cursor:pointer}
.accept{background:#15803d}.decline{background:#dc2626}
.empty{background:white;padding:25px;border-radius:12px}
@media(max-width:550px){body{padding:15px}.customer{align-items:flex-start}.actions{width:100%}.actions form{flex:1}.actions button{width:100%}}
</style>
</head>
<body>
<div class="container">
    <a class="top-link" href="admin_dashboard.php">← Back to Admin Dashboard</a>
    <h1>Customer Registration Requests</h1>
    <p>Review the customer's details and profile picture before approving their account.</p>

    <?php if ($message !== ""): ?>
        <div class="notice"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></div>
    <?php endif; ?>

    <?php if ($result->num_rows === 0): ?>
        <div class="empty">There are no pending registration requests.</div>
    <?php else: ?>
        <?php while ($customer = $result->fetch_assoc()): ?>
            <div class="customer">
                <?php if (!empty($customer["profile_picture"])): ?>
                    <img class="avatar"
                         src="data:<?= htmlspecialchars($customer["profile_picture_type"] ?: "image/jpeg", ENT_QUOTES, "UTF-8") ?>;base64,<?= base64_encode($customer["profile_picture"]) ?>"
                         alt="Customer profile picture">
                <?php else: ?>
                    <div class="avatar"></div>
                <?php endif; ?>

                <div class="details">
                    <h3><?= htmlspecialchars($customer["full_name"] ?? "Unnamed customer", ENT_QUOTES, "UTF-8") ?></h3>
                    <p><?= htmlspecialchars($customer["email"], ENT_QUOTES, "UTF-8") ?></p>
                    <p>Account status: Pending</p>
                </div>

                <div class="actions">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["registration_csrf"], ENT_QUOTES, "UTF-8") ?>">
                        <input type="hidden" name="user_id" value="<?= (int)$customer["id"] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button class="accept" type="submit">Accept</button>
                    </form>

                    <form method="POST" onsubmit="return confirm('Are you sure you want to decline this registration?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["registration_csrf"], ENT_QUOTES, "UTF-8") ?>">
                        <input type="hidden" name="user_id" value="<?= (int)$customer["id"] ?>">
                        <input type="hidden" name="action" value="decline">
                        <button class="decline" type="submit">Decline</button>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>
</body>
</html>
