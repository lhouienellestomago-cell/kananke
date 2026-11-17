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

// Maximum image size: 10 MB
$maxFileSize = 10 * 1024 * 1024;

// CSRF token
if (empty($_SESSION["signup_csrf"])) {
    $_SESSION["signup_csrf"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Detect requests rejected by PHP post_max_size
    if (
        empty($_POST) &&
        empty($_FILES) &&
        !empty($_SERVER["CONTENT_LENGTH"])
    ) {
        $error = "Your upload is too large for the server. Please use a smaller picture or increase the server upload limit.";
    } else {

        $fullName = trim($_POST["full_name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";
        $csrfToken = $_POST["csrf_token"] ?? "";

        if (!hash_equals($_SESSION["signup_csrf"], $csrfToken)) {
            $error = "Invalid request. Please refresh the page and try again.";
        } elseif ($fullName === "" || $email === "" || $password === "" || $confirmPassword === "") {
            $error = "Please fill in all required fields.";
        } elseif (strlen($fullName) > 150) {
            $error = "Full name must not exceed 150 characters.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif ($password !== $confirmPassword) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 8) {
            $error = "Password must contain at least 8 characters.";
        } elseif (!isset($_FILES["profile_picture"])) {
            $error = "Please select a profile picture.";
        } else {

            $picture = $_FILES["profile_picture"];

            if ($picture["error"] !== UPLOAD_ERR_OK) {

                switch ($picture["error"]) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error = "Your picture exceeds the server's upload limit. Please use a smaller picture.";
                        break;

                    case UPLOAD_ERR_PARTIAL:
                        $error = "Your picture was only partially uploaded. Please try again.";
                        break;

                    case UPLOAD_ERR_NO_FILE:
                        $error = "Please select a profile picture.";
                        break;

                    default:
                        $error = "There was an error uploading your picture. Please try again.";
                        break;
                }

            } elseif ($picture["size"] > $maxFileSize) {

                $error = "Your picture must be 10 MB or smaller.";

            } elseif ($picture["size"] <= 0 || !is_uploaded_file($picture["tmp_name"])) {

                $error = "Invalid picture upload.";

            } else {

                // Validate actual image content
                $imageInfo = getimagesize($picture["tmp_name"]);

                if ($imageInfo === false) {
                    $error = "The selected file is not a valid image.";
                } else {

                    $allowedTypes = [
                        IMAGETYPE_JPEG => "image/jpeg",
                        IMAGETYPE_PNG => "image/png",
                        IMAGETYPE_WEBP => "image/webp"
                    ];

                    $imageType = $imageInfo[2];

                    if (!isset($allowedTypes[$imageType])) {
                        $error = "Only JPG, JPEG, PNG, and WEBP images are allowed.";
                    } else {

                        $pictureData = file_get_contents($picture["tmp_name"]);
                        $pictureType = $allowedTypes[$imageType];

                        // Check if email already exists
                        $check = $conn->prepare(
                            "SELECT id FROM users WHERE email = ? LIMIT 1"
                        );

                        $check->bind_param("s", $email);
                        $check->execute();
                        $result = $check->get_result();

                        if ($result->num_rows > 0) {
                            $error = "This email address is already registered.";
                        }

                        $check->close();

                        if ($error === "") {

                            $hashedPassword = password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );

                            $role = "user";
                            $status = "Pending";
                            $nullBlob = null;

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

                            // Send profile picture as a BLOB
                            $stmt->send_long_data(5, $pictureData);

                            if ($stmt->execute()) {

                                unset($_SESSION["signup_csrf"]);

                                header("Location: login.php?registered=1");
                                exit;

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
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Create Account | Chekananke</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 25px 15px;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f3f5f9;
    font-family: Arial, sans-serif;
    color: #222;
}

.container {
    width: 100%;
    max-width: 440px;
    background: #fff;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.08);
}

h2 {
    text-align: center;
    margin: 0 0 8px;
}

.subtitle {
    text-align: center;
    color: #777;
    margin: 0 0 25px;
}

label {
    display: block;
    margin: 15px 0 7px;
    font-weight: bold;
    font-size: 14px;
}

input {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 15px;
}

input:focus {
    outline: none;
    border-color: #f58220;
}

.file-input {
    padding: 9px;
    background: #fafafa;
}

.preview {
    display: none;
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 50%;
    margin: 15px auto;
    border: 3px solid #f58220;
}

.hint {
    font-size: 12px;
    color: #777;
    margin-top: 7px;
    line-height: 1.5;
}

button {
    width: 100%;
    margin-top: 22px;
    padding: 13px;
    border: none;
    border-radius: 8px;
    background: #f58220;
    color: white;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: #f58220;
}

.error {
    background: #ffe5e5;
    color: #b00020;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 15px;
    font-size: 14px;
}

.login-link {
    text-align: center;
    margin-top: 20px;
    font-size: 14px;
}

.login-link a {
    color: #f58220;
    text-decoration: none;
    font-weight: bold;
}

@media (max-width: 480px) {
    .container {
        padding: 22px 18px;
    }
}
</style>
</head>

<body>

<div class="container">

    <h2>Create Account</h2>
    <p class="subtitle">Chekananke Business Portal</p>

    <?php if ($error !== ""): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

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
            maxlength="150"
            value="<?= htmlspecialchars($fullName, ENT_QUOTES, "UTF-8") ?>"
            placeholder="Enter your full name"
            required
        >

        <label for="email">Email Address</label>
        <input
            type="email"
            id="email"
            name="email"
            value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>"
            placeholder="Enter your email"
            required
        >

        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            minlength="8"
            placeholder="At least 8 characters"
            required
        >

        <label for="confirm_password">Confirm Password</label>
        <input
            type="password"
            id="confirm_password"
            name="confirm_password"
            minlength="8"
            placeholder="Confirm your password"
            required
        >

        <label for="profile_picture">Profile Picture</label>
        <input
            type="file"
            id="profile_picture"
            name="profile_picture"
            class="file-input"
            accept="image/jpeg,image/png,image/webp"
            required
        >

        <p class="hint">
            Accepted formats: JPG, JPEG, PNG, WEBP.
            Maximum picture size: 10 MB.
            You can choose a picture from your phone gallery.
        </p>

        <img
            id="imagePreview"
            class="preview"
            alt="Profile picture preview"
        >

        <button type="submit">Create Account</button>

    </form>

    <div class="login-link">
        Already have an account?
        <a href="login.php">Login here</a>
    </div>

</div>

<script>
const pictureInput = document.getElementById("profile_picture");
const preview = document.getElementById("imagePreview");

const maxSize = 10 * 1024 * 1024;

pictureInput.addEventListener("change", function () {

    const file = this.files[0];

    preview.style.display = "none";
    preview.removeAttribute("src");

    if (!file) {
        return;
    }

    if (file.size > maxSize) {
        alert("Your picture must be 10 MB or smaller.");
        this.value = "";
        return;
    }

    const allowedTypes = [
        "image/jpeg",
        "image/png",
        "image/webp"
    ];

    if (!allowedTypes.includes(file.type)) {
        alert("Please select a JPG, PNG, or WEBP image.");
        this.value = "";
        return;
    }

    const reader = new FileReader();

    reader.onload = function (event) {
        preview.src = event.target.result;
        preview.style.display = "block";
    };

    reader.readAsDataURL(file);
});
</script>

</body>
</html>
