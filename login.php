<?php
session_start();
require_once "db.php";

if (isset($_SESSION["loggedIn"]) && $_SESSION["loggedIn"] === true) {
    if (($_SESSION["role"] ?? "") === "admin") {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

$error = "";
$email = "";

if (isset($_GET["registered"]) && $_GET["registered"] == "1") {
    $success = "Your account has been created successfully. Please wait for admin approval.";
} else {
    $success = "";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Please enter your email and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare(
            "SELECT id, email, password, role, status
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (!password_verify($password, $user["password"])) {
                $error = "Incorrect email or password.";
            } else {
                $role = $user["role"] ?? "user";
                $status = $user["status"] ?? "";

                // Check customer approval status
                if ($role !== "admin" && $status !== "Approved") {
                    if ($status === "Pending") {
                        $error = "Your account is waiting for admin approval. Please try again later.";
                    } elseif ($status === "Declined") {
                        $error = "Your registration has been declined. Please contact the administrator.";
                    } else {
                        $error = "Your account is not approved. Please contact the administrator.";
                    }
                } else {
                    session_regenerate_id(true);

                    $_SESSION["user_id"] = (int)$user["id"];
                    $_SESSION["user_email"] = $user["email"];
                    $_SESSION["role"] = $role;
                    $_SESSION["loggedIn"] = true;

                    $stmt->close();

                    if ($role === "admin") {
                        header("Location: admin_dashboard.php");
                    } else {
                        header("Location: index.php");
                    }
                    exit;
                }
            }
        } else {
            $error = "Incorrect email or password.";
        }

        $stmt->close();
    }
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
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.login-card {
    width: 100%;
    max-width: 480px;
    background: white;
    padding: 40px;
    border-radius: 25px;
    box-shadow: 0 15px 45px #0002;
}

.logo {
    text-align: center;
    font-size: 38px;
    font-weight: 800;
    color: #f97316;
    margin-bottom: 10px;
}

.subtitle {
    text-align: center;
    color: #667085;
    font-size: 18px;
    margin-bottom: 30px;
}

h1 {
    text-align: center;
    margin-bottom: 25px;
    color: #172018;
}

label {
    display: block;
    margin: 18px 0 8px;
    font-weight: bold;
}

input {
    width: 100%;
    padding: 15px;
    border: 1px solid #d0d5dd;
    border-radius: 12px;
    font-size: 16px;
}

input:focus {
    outline: none;
    border-color: #f97316;
    box-shadow: 0 0 0 3px #f9731620;
}

.btn {
    width: 100%;
    padding: 16px;
    border: none;
    border-radius: 12px;
    background: #f97316;
    color: white;
    font-size: 18px;
    font-weight: bold;
    margin-top: 25px;
    cursor: pointer;
}

.btn:hover {
    background: #ea580c;
}

.message {
    padding: 13px;
    border-radius: 10px;
    margin-bottom: 18px;
    text-align: center;
    line-height: 1.5;
    font-size: 14px;
}

.error {
    background: #fee2e2;
    color: #b91c1c;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.signup-link {
    text-align: center;
    margin-top: 25px;
    color: #667085;
}

a {
    color: #f97316;
    font-weight: bold;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

@media (max-width: 500px) {
    .login-card {
        padding: 30px 22px;
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

    <h1>Login</h1>

    <?php if ($success !== ""): ?>
        <div class="message success">
            <?= htmlspecialchars($success, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="message error">
            <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">

        <label for="email">Email</label>
        <input
            type="email"
            id="email"
            name="email"
            placeholder="you@example.com"
            value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>"
            autocomplete="email"
            required
        >

        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            placeholder="Enter your password"
            autocomplete="current-password"
            required
        >

        <button type="submit" class="btn">Login</button>

    </form>

    <p class="signup-link">
        Don't have an account?
        <a href="signup.php">Create Account</a>
    </p>

</div>

</body>
</html>
