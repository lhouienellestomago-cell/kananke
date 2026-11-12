<?php
session_start();
require_once "db.php";

// Redirect if already logged in
if (isset($_SESSION["loggedIn"]) && $_SESSION["loggedIn"] === true) {
    if (($_SESSION["role"] ?? "user") === "admin") {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

$error = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if ($email === "" || $password === "" || $confirmPassword === "") {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($email) > 255) {
        $error = "Email address is too long.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (strlen($password) > 72) {
        $error = "Password must not exceed 72 characters.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        // Check if email is already registered
        $stmt = $conn->prepare(
            "SELECT id FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $error = "This email address is already registered.";
        } else {
            // Securely hash the password
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // New registrations are always regular users
            $role = "user";
            $status = "Pending";

            $stmt = $conn->prepare(
                "INSERT INTO users (email, password, role, status)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "ssss",
                $email,
                $hashedPassword,
                $role,
                $status
            );

            if ($stmt->execute()) {
                $stmt->close();

                header("Location: login.php?registered=1");
                exit;
            } else {
                if ($stmt->errno === 1062) {
                    $error = "This email address is already registered.";
                } else {
                    $error = "Registration failed. Please try again.";
                }

                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Chekananke - Sign Up</title>

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
    padding: 25px 20px;
}

.signup-card {
    width: 100%;
    max-width: 570px;
    background: white;
    padding: 45px 60px;
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
    margin-bottom: 12px;
    text-align: center;
}

.logo-icon {
    font-size: 42px;
}

.subtitle {
    color: #667085;
    margin-bottom: 30px;
    text-align: center;
    font-size: 23px;
}

h1 {
    font-size: 27px;
    text-align: center;
    margin-bottom: 25px;
    color: #172018;
}

label {
    display: block;
    margin: 18px 0 10px;
    font-weight: 700;
    font-size: 19px;
}

input {
    width: 100%;
    padding: 16px 18px;
    border: 1px solid #d0d5dd;
    border-radius: 14px;
    outline: none;
    font-size: 18px;
    background: white;
}

input:focus {
    border-color: #f97316;
    box-shadow: 0 0 0 3px #f9731620;
}

.btn {
    width: 100%;
    padding: 18px;
    border: none;
    border-radius: 14px;
    background: #f97316;
    color: white;
    font-weight: 700;
    font-size: 20px;
    margin-top: 28px;
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
    font-size: 15px;
    line-height: 1.5;
}

.error {
    background: #fee2e2;
    color: #b91c1c;
}

.demo {
    font-size: 17px;
    color: #667085;
    margin-top: 24px;
    text-align: center;
    line-height: 1.5;
}

.login-link {
    text-align: center;
    margin-top: 24px;
    font-size: 17px;
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
    .signup-card {
        padding: 32px 24px;
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
        margin-bottom: 25px;
    }

    h1 {
        font-size: 24px;
    }

    label {
        font-size: 16px;
    }

    input {
        padding: 14px;
        font-size: 16px;
    }

    .btn {
        padding: 15px;
        font-size: 18px;
    }

    .demo,
    .login-link {
        font-size: 14px;
    }
}
</style>
</head>

<body>

<div class="signup-card">

    <div class="logo">
        <span class="logo-icon">🍴</span>
        <span>Chekananke</span>
    </div>

    <p class="subtitle">Business Portal</p>

    <h1>Create Account</h1>

    <?php if ($error !== ""): ?>
        <div class="message error">
            <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <form action="signup.php" method="POST">

        <label for="email">Email</label>
        <input
            type="email"
            id="email"
            name="email"
            placeholder="you@example.com"
            value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>"
            maxlength="255"
            autocomplete="email"
            required
        >

        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            placeholder="Create password (min. 8 characters)"
            minlength="8"
            maxlength="72"
            autocomplete="new-password"
            required
        >

        <label for="confirm_password">Confirm Password</label>
        <input
            type="password"
            id="confirm_password"
            name="confirm_password"
            placeholder="Enter password again"
            minlength="8"
            maxlength="72"
            autocomplete="new-password"
            required
        >

        <button class="btn" type="submit">Create Account</button>

    </form>

    <p class="demo">
        Your account will require admin approval before you can log in.
    </p>

    <p class="login-link">
        Already have an account?
        <a href="login.php">Log In</a>
    </p>

</div>

</body>
</html>
