<?php
session_start();

if (isset($_SESSION["loggedIn"]) && $_SESSION["loggedIn"] === true) {
    if (($_SESSION["role"] ?? "user") === "admin") {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

$errorMessage = "";

if (isset($_GET["error"])) {
    switch ($_GET["error"]) {
        case "invalid":
            $errorMessage = "Incorrect email or password.";
            break;

        case "empty":
            $errorMessage = "Please enter your email and password.";
            break;

        case "pending":
            $errorMessage = "Your account is pending admin approval.";
            break;

        default:
            $errorMessage = "Unable to log in. Please try again.";
    }
}

if (isset($_GET["registered"])) {
    $errorMessage = "";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Chekananke - Login</title>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
}

body {
    min-height: 100vh;
    background: linear-gradient(135deg, #fff7ed, #dcfce7);
    color: #172018;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.login-card {
    width: 100%;
    max-width: 490px;
    background: white;
    padding: 48px;
    border-radius: 26px;
    box-shadow: 0 15px 45px #0002;
}

.logo {
    font-size: 36px;
    font-weight: 800;
    color: #f97316;
    margin-bottom: 12px;
    text-align: center;
}

.subtitle {
    color: #667085;
    margin-bottom: 36px;
    text-align: center;
    font-size: 18px;
}

label {
    display: block;
    margin: 18px 0 9px;
    font-weight: 700;
    font-size: 17px;
}

input {
    width: 100%;
    padding: 15px 16px;
    border: 1px solid #d0d5dd;
    border-radius: 13px;
    outline: none;
    font-size: 17px;
}

input:focus {
    border-color: #f97316;
    box-shadow: 0 0 0 3px #f9731620;
}

.btn {
    width: 100%;
    padding: 16px;
    border: none;
    border-radius: 13px;
    background: #f97316;
    color: white;
    font-weight: 700;
    font-size: 18px;
    margin-top: 24px;
    cursor: pointer;
}

.btn:hover {
    background: #ea580c;
}

.message {
    padding: 13px;
    border-radius: 9px;
    margin-bottom: 18px;
    text-align: center;
    font-size: 14px;
    line-height: 1.5;
}

.error {
    background: #fee2e2;
    color: #b91c1c;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.demo {
    font-size: 15px;
    color: #667085;
    margin-top: 20px;
    text-align: center;
    line-height: 1.6;
}

.signup-link {
    text-align: center;
    margin-top: 22px;
    color: #667085;
    font-size: 15px;
}

a {
    color: #ea580c;
    font-weight: 700;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

@media (max-width: 480px) {
    .login-card {
        padding: 30px 24px;
    }

    .logo {
        font-size: 30px;
    }
}
</style>
</head>

<body>
<div class="login-card">

    <div class="logo">🍴 Chekananke</div>

    <p class="subtitle">Business Portal</p>

    <?php if ($errorMessage !== ""): ?>
        <div class="message error">
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET["registered"])): ?>
        <div class="message success">
            Registration successful! Your account is awaiting admin approval.
        </div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <label for="email">Email</label>

        <input
            type="email"
            id="email"
            name="email"
            placeholder="you@example.com"
            autocomplete="email"
            required
        >

        <label for="password">Password</label>

        <input
            type="password"
            id="password"
            name="password"
            placeholder="Enter password"
            autocomplete="current-password"
            required
        >

        <button class="btn" type="submit">Log In</button>
    </form>

    <p class="demo">
        Use your registered database account to log in.
    </p>

    <p class="signup-link">
        Don't have an account?
        <a href="signup.php">Create an account</a>
    </p>

</div>
</body>
</html>