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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Kananke Food Hauz</title>
<style>
*{box-sizing:border-box}
body{font-family:Arial,sans-serif;background:#fff7ed;margin:0;padding:30px;color:#222}
h1{color:#f97316}
.admin-card{background:white;padding:25px;border-radius:12px;margin-top:20px;box-shadow:0 4px 15px #0001}
.admin-btn{display:inline-block;background:#f97316;color:white;padding:12px 18px;margin:8px 8px 8px 0;border-radius:8px;text-decoration:none;font-weight:bold}
.admin-btn:hover{background:#ea580c}
.support-btn{background:#15803d}
.support-btn:hover{background:#166534}
.registration-btn{background:#2563eb}
.registration-btn:hover{background:#1d4ed8}
.profile-btn{background:#7c3aed}
.profile-btn:hover{background:#6d28d9}
.footer-link{display:inline-block;margin-top:25px;color:#ea580c;font-weight:bold;text-decoration:none}
@media(max-width:600px){body{padding:16px}.admin-card{padding:18px}.admin-btn{display:block;text-align:center;margin:10px 0}}
</style>
</head>
<body>

<h1>Admin Dashboard</h1>
<p>Welcome, <?= htmlspecialchars($_SESSION["user_email"] ?? "", ENT_QUOTES, "UTF-8") ?>!</p>

<div class="admin-card">
    <h2>Product Management</h2>
    <a href="tray.php" class="admin-btn">Manage Products</a>
    <a href="bundle.php" class="admin-btn">Manage Bundles</a>
    <a href="additionals.php" class="admin-btn">Manage Additional Items</a>
</div>

<div class="admin-card">
    <h2>Customer Accounts</h2>
    <p>Review new customer registrations and view customer profile pictures.</p>
    <a href="registration_requests.php" class="admin-btn registration-btn">
        Registration Requests
    </a>
    <a href="customer_profiles.php" class="admin-btn profile-btn">
        Customer Profiles
    </a>
</div>

<div class="admin-card">
    <h2>Customer Support</h2>
    <p>View customer concerns and respond to their messages.</p>
    <a href="admin_support.php" class="admin-btn support-btn">
        Open Customer Support
    </a>
</div>

<a class="footer-link" href="index.php">View Customer Website</a>

</body>
</html>
