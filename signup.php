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
$fullName = "";
$email = "";

// CSRF token
if (empty($_SESSION["signup_csrf"])) {
    $_SESSION["signup_csrf"] = bin2hex(random_bytes(32));
}

// Detect if the entire POST request exceeded PHP's post_max_size
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    empty($_POST) &&
    empty($_FILES) &&
    !empty($_SERVER["CONTENT_LENGTH"])
) {
    $error = "The uploaded data is too large for the server. Please use a smaller profile picture.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $error === "") {

    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";
    $csrfToken = $_POST["csrf_token"] ?? "";

    // Validate CSRF token
    if (
        empty($_SESSION["signup_csrf"]) ||
        !hash_equals($_SESSION["signup_csrf"], $csrfToken)
    ) {
        $error = "Invalid form submission. Please refresh the page and try again.";
    }

    // Validate fields
    elseif (
        $fullName === "" ||
        $email === "" ||
        $password === "" ||
        $confirmPassword === ""
    ) {
        $error = "Please fill in all fields.";
    }

    elseif (mb_strlen($fullName) > 150) {
        $error = "Full name must not exceed 150 characters.";
    }

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    }

    elseif (strlen($email) > 255) {
        $error = "Email address is too long.";
    }

    elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    }

    elseif (strlen($password) > 72) {
        $error = "Password must not exceed 72 characters.";
    }

    elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    }

    // Validate profile picture upload
    elseif (!isset($_FILES["profile_picture"])) {
        $error = "Profile picture was not received. Please select your picture again.";
    }

    elseif ($_FILES["profile_picture"]["error"] !== UPLOAD_ERR_OK) {

        switch ($_FILES["profile_picture"]["error"]) {

            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error = "Your profile picture exceeds the server upload limit. Please use a smaller image.";
                break;

            case UPLOAD_ERR_PARTIAL:
                $error = "Your profile picture was only partially uploaded. Please try again.";
                break;

            case UPLOAD_ERR_NO_FILE:
                $error = "Please select a profile picture.";
                break;

            case UPLOAD_ERR_NO_TMP_DIR:
                $error = "The server upload folder is missing. Please contact the administrator.";
                break;

            case UPLOAD_ERR_CANT_WRITE:
                $error = "The server could not save your uploaded picture.";
                break;

            case UPLOAD_ERR_EXTENSION:
                $error = "A server extension stopped the picture upload.";
                break;

            default:
                $error = "Profile picture upload failed. Please try again.";
                break;
        }
    }

    elseif (
        !is_uploaded_file($_FILES["profile_picture"]["tmp_name"])
    ) {
        $error = "The uploaded picture could not be verified. Please try again.";
    }

    elseif ($_FILES["profile_picture"]["size"] > 2 * 1024 * 1024) {
        $error = "Profile picture must be 2 MB or smaller.";
    }

    else {

        // Check actual image type
        $imageInfo = @getimagesize(
            $_FILES["profile_picture"]["tmp_name"]
        );

        $allowedTypes = [
            IMAGETYPE_JPEG => "image/jpeg",
            IMAGETYPE_PNG => "image/png",
            IMAGETYPE_WEBP => "image/webp"
        ];

        if (
            !$imageInfo ||
            !isset($allowedTypes[$imageInfo[2]])
        ) {
            $error = "Invalid picture. Please upload a JPG, PNG, or WEBP image.";
        }

        else {

            $pictureType = $allowedTypes[$imageInfo[2]];

            // Read picture data
            $pictureData = file_get_contents(
                $_FILES["profile_picture"]["tmp_name"]
            );

            if ($pictureData === false || $pictureData === "") {
                $error = "Could not read your profile picture. Please try again.";
            }

            else {

                // Check whether email already exists
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

                    // Hash password
                    $hashedPassword = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    // New accounts require admin approval
                    $role = "user";
                    $status = "Pending";

                    // Insert customer and profile picture into database
                    $stmt = $conn->prepare(
                        "INSERT INTO users
                        (
                            email,
                            password,
                            role,
                            status,
                            full_name,
                            profile_picture,
                            profile_picture_type
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );

                    // BLOB parameter is supplied separately
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
    gap: 12px;
    font-size: 42px;
    font-weight: 800;
    color: #f97316;
    margin-bottom: 12px;
    text-align: center;
}

.logo-icon {
    font-size: 38px;
}

.subtitle {
    color: #667085;
    margin-bottom: 30px;
    text-align: center;
    font-size: 20px;
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
    font-size: 17px;
}

input {
    width: 100%;
    padding: 15px 16px;
    border: 1px solid #d0d5dd;
    border-radius: 12px;
    outline: none;
    font-size: 16px;
    background: white;
}

input:focus {
    border-color: #f97316;
    box-shadow: 0 0 0 3px #f9731620;
}

input[type="file"] {
    padding: 12px;
    cursor: pointer;
}

.hint {
    color: #667085;
    font-size: 13px;
    margin-top: 7px;
    line-height: 1.5;
}

.btn {
    width: 100%;
    padding: 17px;
    border: none;
    border-radius: 13px;
    background: #f97316;
    color: white;
    font-weight: 700;
    font-size: 19px;
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
    overflow-wrap: anywhere;
}

.error {
    background: #fee2e2;
    color: #b91c1c;
}

.demo {
    font-size: 15px;
    color: #667085;
    margin-top: 24px;
    text-align: center;
    line-height: 1.6;
}

.login-link {
    text-align: center;
    margin-top: 24px;
    font-size: 16px;
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
        padding: 32px 22px;
    }

    .logo {
        font-size: 30px;
    }

    .logo-icon {
        font-size: 29px;
    }

    .subtitle {
        font-size: 17px;
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
        font-size: 17px;
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

    <form
        action="signup.php"
        method="POST"
        enctype="multipart/form-data"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION["signup_csrf"], ENT_QUOTES, "UTF-8") ?>"
        >

        <label for="full_name">Full Name</label>

        <input
            type="text"
            id="full_name"
            name="full_name"
            placeholder="Enter your full name"
            value="<?= htmlspecialchars($fullName, ENT_QUOTES, "UTF-8") ?>"
            maxlength="150"
            autocomplete="name"
            required
        >

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

        <label for="profile_picture">Profile Picture</label>

        <input
            type="file"
            id="profile_picture"
            name="profile_picture"
            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
            required
        >

        <p class="hint">
            Accepted formats: JPG, JPEG, PNG, and WEBP.
            Maximum size: 2 MB.
        </p>

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

        <button class="btn" type="submit">
            Create Account
        </button>

    </form>

    <p class="demo">
        Your account and profile picture will be sent to the admin
        for approval before you can log in.
    </p>

    <p class="login-link">
        Already have an account?
        <a href="login.php">Log In</a>
    </p>

</div>

</body>
</html>
