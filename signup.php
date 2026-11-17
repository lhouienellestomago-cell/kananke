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

// Create CSRF token
if (empty($_SESSION["signup_csrf"])) {
    $_SESSION["signup_csrf"] = bin2hex(random_bytes(32));
}

// Detect request exceeding PHP post_max_size
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    empty($_POST) &&
    empty($_FILES) &&
    !empty($_SERVER["CONTENT_LENGTH"])
) {
    $error = "The uploaded data is too large. Please use a smaller picture.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $error === "") {

    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";
    $csrfToken = $_POST["csrf_token"] ?? "";

    if (
        empty($_SESSION["signup_csrf"]) ||
        !hash_equals($_SESSION["signup_csrf"], $csrfToken)
    ) {
        $error = "Invalid form submission. Please refresh and try again.";
    }

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

    elseif (strlen($password) < 8 || strlen($password) > 72) {
        $error = "Password must be between 8 and 72 characters.";
    }

    elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    }

    // Check uploaded picture
    elseif (!isset($_FILES["profile_picture"])) {
        $error = "Please select or take a profile picture.";
    }

    elseif ($_FILES["profile_picture"]["error"] !== UPLOAD_ERR_OK) {

        switch ($_FILES["profile_picture"]["error"]) {

            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error = "Your picture is too large for the server. Please use a smaller picture.";
                break;

            case UPLOAD_ERR_PARTIAL:
                $error = "Your picture was only partially uploaded. Please try again.";
                break;

            case UPLOAD_ERR_NO_FILE:
                $error = "Please select or take a profile picture.";
                break;

            case UPLOAD_ERR_NO_TMP_DIR:
                $error = "The server upload folder is missing.";
                break;

            case UPLOAD_ERR_CANT_WRITE:
                $error = "The server could not save your picture.";
                break;

            case UPLOAD_ERR_EXTENSION:
                $error = "A server extension stopped the picture upload.";
                break;

            default:
                $error = "Picture upload failed. Please try again.";
                break;
        }
    }

    elseif (!is_uploaded_file($_FILES["profile_picture"]["tmp_name"])) {
        $error = "The uploaded picture could not be verified.";
    }

    elseif ($_FILES["profile_picture"]["size"] > 2 * 1024 * 1024) {
        $error = "Profile picture must be 2 MB or smaller.";
    }

    else {

        // Verify actual image type
        $imageInfo = @getimagesize($_FILES["profile_picture"]["tmp_name"]);

        $allowedTypes = [
            IMAGETYPE_JPEG => "image/jpeg",
            IMAGETYPE_PNG => "image/png",
            IMAGETYPE_WEBP => "image/webp"
        ];

        if (!$imageInfo || !isset($allowedTypes[$imageInfo[2]])) {

            $error = "Unsupported image format. Please use JPG, PNG, or WEBP. If using an iPhone, choose Most Compatible in Camera settings.";

        } else {

            $pictureType = $allowedTypes[$imageInfo[2]];

            $pictureData = file_get_contents(
                $_FILES["profile_picture"]["tmp_name"]
            );

            if ($pictureData === false || $pictureData === "") {

                $error = "Could not read your profile picture.";

            } else {

                // Check duplicate email
                $stmt = $conn->prepare(
                    "SELECT id FROM users WHERE email = ? LIMIT 1"
                );

                $stmt->bind_param("s", $email);
                $stmt->execute();

                $exists = $stmt->get_result()->num_rows > 0;
                $stmt->close();

                if ($exists) {

                    $error = "This email address is already registered.";

                } else {

                    $hashedPassword = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $role = "user";
                    $status = "Pending";

                    // Save customer and picture in MySQL
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
    padding: 25px 15px;
}

.signup-card {
    width: 100%;
    max-width: 570px;
    background: white;
    padding: 40px 50px;
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
    margin-bottom: 28px;
}

h1 {
    text-align: center;
    margin-bottom: 24px;
    font-size: 26px;
}

label {
    display: block;
    margin: 17px 0 8px;
    font-weight: bold;
}

input {
    width: 100%;
    padding: 14px;
    border: 1px solid #d0d5dd;
    border-radius: 12px;
    font-size: 16px;
    background: white;
}

input:focus {
    outline: none;
    border-color: #f97316;
    box-shadow: 0 0 0 3px #f9731620;
}

input[type="file"] {
    cursor: pointer;
    padding: 12px;
    font-size: 14px;
}

.hint {
    color: #667085;
    font-size: 13px;
    margin-top: 7px;
    line-height: 1.5;
}

.preview-container {
    display: none;
    text-align: center;
    margin-top: 18px;
}

.preview-container img {
    width: 135px;
    height: 135px;
    object-fit: cover;
    border-radius: 50%;
    border: 4px solid #f97316;
    background: #f3f4f6;
}

.preview-text {
    margin-top: 8px;
    color: #15803d;
    font-size: 14px;
    font-weight: bold;
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
    overflow-wrap: anywhere;
}

.error {
    background: #fee2e2;
    color: #b91c1c;
}

.demo {
    font-size: 14px;
    color: #667085;
    margin-top: 22px;
    text-align: center;
    line-height: 1.6;
}

.login-link {
    text-align: center;
    margin-top: 22px;
    color: #667085;
    font-size: 15px;
}

a {
    color: #f97316;
    font-weight: bold;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

@media (max-width: 600px) {

    body {
        padding: 15px;
    }

    .signup-card {
        padding: 30px 20px;
        border-radius: 20px;
    }

    .logo {
        font-size: 30px;
    }

    .subtitle {
        font-size: 16px;
    }

    h1 {
        font-size: 23px;
    }

    input {
        padding: 13px;
        font-size: 16px;
    }

    .btn {
        padding: 15px;
        font-size: 17px;
    }

    .preview-container img {
        width: 120px;
        height: 120px;
    }
}

</style>

</head>

<body>

<div class="signup-card">

    <div class="logo">🍴 Chekananke</div>

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

        <label for="profile_picture">Customer Profile Picture</label>

        <input
            type="file"
            id="profile_picture"
            name="profile_picture"
            accept="image/*"
            required
        >

        <p class="hint">
            Select a photo from your cellphone gallery, take a new
            picture using your camera, or upload from your computer.
            Supported formats: JPG, PNG, and WEBP. Maximum size: 2 MB.
        </p>

        <div class="preview-container" id="previewContainer">
            <img id="picturePreview" src="" alt="Profile picture preview">
            <p class="preview-text">Profile picture selected!</p>
        </div>

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

<script>

const pictureInput = document.getElementById("profile_picture");
const previewContainer = document.getElementById("previewContainer");
const picturePreview = document.getElementById("picturePreview");

let currentPreviewUrl = null;

pictureInput.addEventListener("change", function () {

    const file = this.files[0];

    if (currentPreviewUrl) {
        URL.revokeObjectURL(currentPreviewUrl);
        currentPreviewUrl = null;
    }

    previewContainer.style.display = "none";
    picturePreview.src = "";

    if (!file) {
        return;
    }

    if (!file.type.startsWith("image/")) {
        alert("Please select an image file.");
        this.value = "";
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        alert("Your picture must be 5 MB or smaller.");
        this.value = "";
        return;
    }

    currentPreviewUrl = URL.createObjectURL(file);
    picturePreview.src = currentPreviewUrl;
    previewContainer.style.display = "block";
});

</script>

</body>
</html>
