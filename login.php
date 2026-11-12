<?php
session_start();
require_once "db.php";

// Redirect logged-in users
if (isset($_SESSION["loggedIn"]) && $_SESSION["loggedIn"] === true) {
    if (($_SESSION["role"] ?? "user") === "admin") {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Please enter your email and password.";
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
            } elseif ($user["status"] !== "Approved") {
                $error = "Your account is pending admin approval.";
            } else {
                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["user_email"] = $user["email"];
                $_SESSION["role"] = $user["role"];
                $_SESSION["loggedIn"] = true;

                if ($user["role"] === "admin") {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: index.php");
                }
                exit;
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
    color: #172018;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.login-card {
    width: 100%;
    max-width: 570px;
    background: white;
    padding: 55px 60px;
    border-radius: 28px;
    box-shadow: 0 15px 45px #0002;
}

.logo {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    font-size: 48px;
    font-weight: 800;
    color: #f97316;
    margin-bottom: 14px;
    text-align: center;
}

.logo-icon {
    font-size: 42px;
}

.subtitle {
    color: #667085;
    margin-bottom: 46px;
    text-align: center;
    font-size: 23px;
}

label {
    display: block;
    margin: 20px 0 12px;
    font-weight: 700;
    font-size: 20px;
}

input {
    width: 100%;
    padding: 18px 20px;
    border: 1px solid #d0d5dd;
    border-radius: 15px;
    outline: none;
    font-size: 20px;
    background: white;
}

input:focus {
    border-color: #f97316;
    box-shadow: 0 0 0 3px #f9731620;
}

.btn {
    width: 100%;
    padding: 19px;
    border: none;
    border-radius: 15px;
    background: #f97316;
    color: white;
    font-weight: 700;
    font-size: 21px;
    margin-top: 30px;
    cursor: pointer;
}

.btn:hover {
    background: #ea580c;
}

.message {
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 20px;
    text-align: center;
    font-size: 15px;
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
    font-size: 18px;
    color: #667085;
    margin-top: 28px;
    text-align: center;
    line-height: 1.5;
}

.signup-link {
    text-align: center;
    margin-top: 30px;
    font-size: 18px;
    color: #667085;
}

a {
    color: #f97316;
    font-weight: 700;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

@media (max-width: 600px) {
    .login-card {
        padding: 35px 25px;
    }

    .logo {
        font-size: 32px;
        gap: 10px;
    }

    .logo-icon {
        font-size: 30px;
    }

    .subtitle {
        font-size: 19px;
        margin-bottom: 30px;
    }

    label {
        font-size: 17px;
    }

    input {
        font-size: 17px;
        padding: 14px;
    }

    .btn {
        font-size: 18px;
        padding: 15px;
    }

    .demo,
    .signup-link {
        font-size: 15px;
    }
}
</style>
</head>

<body>

<div class="login-card">

    <div class="logo">
        <span class="logo-icon">🍴</span>
        <span>Chekananke</span>
    </div>

    <p class="subtitle">Business Portal</p>

    <?php if ($error !== ""): ?>
        <div class="message error">
            <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
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
            maxlength="255"
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
