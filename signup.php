<?php
session_start();
require_once "db.php";

if (isset($_SESSION["loggedIn"]) && $_SESSION["loggedIn"] === true) {
    header("Location: " . (($_SESSION["role"] ?? "user") === "admin"
        ? "admin_dashboard.php" : "index.php"));
    exit;
}

$error = "";
$fullName = "";
$email = "";

if (empty($_SESSION["signup_csrf"])) {
    $_SESSION["signup_csrf"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (!hash_equals($_SESSION["signup_csrf"], $_POST["csrf_token"] ?? "")) {
        $error = "Invalid form submission. Please refresh and try again.";
    } elseif ($fullName === "" || $email === "" || $password === "" || $confirmPassword === "") {
        $error = "Please fill in all fields.";
    } elseif (mb_strlen($fullName) > 150) {
        $error = "Full name must not exceed 150 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8 || strlen($password) > 72) {
        $error = "Password must be between 8 and 72 characters.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (
        !isset($_FILES["profile_picture"]) ||
        $_FILES["profile_picture"]["error"] !== UPLOAD_ERR_OK
    ) {
        $error = "Please select a profile picture.";
    } elseif ($_FILES["profile_picture"]["size"] > 2 * 1024 * 1024) {
        $error = "Profile picture must be 2 MB or smaller.";
    } else {
        $imageInfo = @getimagesize($_FILES["profile_picture"]["tmp_name"]);
        $allowedTypes = [
            IMAGETYPE_JPEG => "image/jpeg",
            IMAGETYPE_PNG => "image/png",
            IMAGETYPE_WEBP => "image/webp"
        ];

        if (!$imageInfo || !isset($allowedTypes[$imageInfo[2]])) {
            $error = "Only JPG, PNG, and WEBP images are allowed.";
        } else {
            $pictureType = $allowedTypes[$imageInfo[2]];
            $pictureData = file_get_contents($_FILES["profile_picture"]["tmp_name"]);

            if ($pictureData === false) {
                $error = "Could not read the uploaded picture.";
            } else {
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $exists = $stmt->get_result()->num_rows > 0;
                $stmt->close();

                if ($exists) {
                    $error = "This email address is already registered.";
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $role = "user";
                    $status = "Pending";

                    $stmt = $conn->prepare(
                        "INSERT INTO users
                        (email, password, role, status, full_name, profile_picture, profile_picture_type)
                        VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );

                    $nullBlob = null;
                    $stmt->bind_param(
                        "sssssbs",
                        $email,
                        $hashedPassword,
                        $role,
                        $status,
                        $fullName,
                        $nullBlob,
                        $pictureType
                    );
                    $stmt->send_long_data(5, $pictureData);

                    if ($stmt->execute()) {
                        $stmt->close();
                        unset($_SESSION["signup_csrf"]);
                        header("Location: login.php?registered=1");
                        exit;
                    }

                    $stmt->close();
                    $error = "Registration failed. Please try again.";
                }
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
*{box-sizing:border-box;margin:0;padding:0;font-family:Arial,sans-serif}
body{min-height:100vh;background:linear-gradient(135deg,#fff7ed,#dcfce7);display:flex;align-items:center;justify-content:center;padding:24px}
.signup-card{width:100%;max-width:570px;background:white;padding:40px 50px;border-radius:25px;box-shadow:0 15px 45px #0002}
.logo{text-align:center;font-size:38px;font-weight:800;color:#f97316;margin-bottom:10px}
.subtitle{text-align:center;color:#667085;margin-bottom:25px;font-size:18px}
h1{text-align:center;margin-bottom:22px;font-size:26px}
label{display:block;margin:16px 0 8px;font-weight:700}
input{width:100%;padding:14px;border:1px solid #d0d5dd;border-radius:12px;font-size:16px}
input:focus{outline:none;border-color:#f97316;box-shadow:0 0 0 3px #f9731620}
.hint{font-size:13px;color:#667085;margin-top:6px}
.btn{width:100%;padding:16px;border:0;border-radius:12px;background:#f97316;color:white;font-size:18px;font-weight:bold;margin-top:24px;cursor:pointer}
.btn:hover{background:#ea580c}
.message{padding:13px;border-radius:10px;margin-bottom:16px;text-align:center;line-height:1.5}
.error{background:#fee2e2;color:#b91c1c}
.info{text-align:center;color:#667085;margin-top:20px;line-height:1.5}
.login-link{text-align:center;margin-top:20px;color:#667085}
a{color:#f97316;font-weight:bold;text-decoration:none}
@media(max-width:600px){.signup-card{padding:30px 22px}.logo{font-size:30px}}
</style>
</head>
<body>
<div class="signup-card">
    <div class="logo">🍴 Chekananke</div>
    <p class="subtitle">Business Portal</p>
    <h1>Create Account</h1>

    <?php if ($error !== ""): ?>
        <div class="message error"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></div>
    <?php endif; ?>

    <form action="signup.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["signup_csrf"], ENT_QUOTES, "UTF-8") ?>">

        <label for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name"
               value="<?= htmlspecialchars($fullName, ENT_QUOTES, "UTF-8") ?>"
               maxlength="150" autocomplete="name" required>

        <label for="email">Email</label>
        <input type="email" id="email" name="email"
               value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>"
               maxlength="255" autocomplete="email" required>

        <label for="profile_picture">Profile Picture</label>
        <input type="file" id="profile_picture" name="profile_picture"
               accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
        <p class="hint">JPG, PNG, or WEBP. Maximum size: 2 MB.</p>

        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               minlength="8" maxlength="72" autocomplete="new-password" required>

        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password"
               minlength="8" maxlength="72" autocomplete="new-password" required>

        <button class="btn" type="submit">Create Account</button>
    </form>

    <p class="info">Your account and profile picture will be sent to the admin for approval before you can log in.</p>
    <p class="login-link">Already have an account? <a href="login.php">Log In</a></p>
</div>
</body>
</html>
