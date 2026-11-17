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
body {
    font-family: Arial, sans-serif;
    background: #fff7ed;
    margin: 0;
    padding: 30px;
}

h1 {
    color: #f97316;
}

.admin-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    margin-top: 20px;
    box-shadow: 0 4px 15px #0001;
}

.admin-btn {
    display: inline-block;
    background: #f97316;
    color: white;
    padding: 12px 18px;
    margin: 8px 8px 8px 0;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
}

.admin-btn:hover {
    background: #ea580c;
}

.support-btn {
    background: #15803d;
}

.support-btn:hover {
    background: #166534;
}
</style>
</head>

<body>

<h1>Admin Dashboard</h1>

<p>
    Welcome, <?= htmlspecialchars($_SESSION["user_email"] ?? "") ?>!
</p>

<div class="admin-card">
    <h2>Product Management</h2>

    <a href="tray.php" class="admin-btn">Manage Products</a>
    <a href="bundle.php" class="admin-btn">Manage Bundles</a>
    <a href="additionals.php" class="admin-btn">Manage Additional Items</a>
</div>

<div class="admin-card">
    <h2>Customer Support</h2>
    <p>View customer concerns and respond to their messages.</p>

    <a href="admin_support.php" class="admin-btn support-btn">
        Open Customer Support
    </a>
</div>

<p style="margin-top:25px;">
    <a href="index.php">View Customer Website</a>
</p>

</body>
</html>
