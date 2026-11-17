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

$result = $conn->query(
    "SELECT id, full_name, email, status, profile_picture, profile_picture_type
     FROM users
     WHERE role = 'user'
     ORDER BY id DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customer Profiles</title>
<style>
*{box-sizing:border-box}
body{font-family:Arial,sans-serif;background:#fff7ed;margin:0;padding:25px;color:#222}
.container{max-width:1100px;margin:auto}
h1{color:#f97316}
.back{display:inline-block;margin-bottom:20px;color:#ea580c;font-weight:bold;text-decoration:none}
.profile{background:white;padding:20px;border-radius:14px;margin:15px 0;display:flex;align-items:center;gap:20px;box-shadow:0 4px 15px #0001;flex-wrap:wrap}
.avatar{width:90px;height:90px;object-fit:cover;border-radius:50%;background:#eee}
.details{flex:1;min-width:180px}
.details h3{margin:0 0 8px}
.details p{margin:5px 0;color:#555;overflow-wrap:anywhere}
.status{font-weight:bold}
.Pending{color:#b45309}.Approved{color:#15803d}.Declined{color:#dc2626}
.empty{background:white;padding:25px;border-radius:12px}
@media(max-width:550px){body{padding:15px}.profile{align-items:flex-start}}
</style>
</head>
<body>
<div class="container">
    <a class="back" href="admin_dashboard.php">← Back to Admin Dashboard</a>
    <h1>Customer Profiles</h1>
    <p>View customer names, email addresses, profile pictures, and account status.</p>

    <?php if ($result->num_rows === 0): ?>
        <div class="empty">No customer accounts found.</div>
    <?php else: ?>
        <?php while ($customer = $result->fetch_assoc()): ?>
            <div class="profile">
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
                    <p class="status <?= htmlspecialchars($customer["status"] ?? "", ENT_QUOTES, "UTF-8") ?>">
                        Status: <?= htmlspecialchars($customer["status"] ?? "Unknown", ENT_QUOTES, "UTF-8") ?>
                    </p>
                </div>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>
</body>
</html>
