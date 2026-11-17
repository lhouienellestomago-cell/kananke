<?php
session_start();

if (!isset($_SESSION["loggedIn"]) || $_SESSION["loggedIn"] !== true) {
    header("Location: login_page.php");
    exit;
}

require_once "db.php";

$user_id = (int)($_SESSION["user_id"] ?? 0);

if ($user_id <= 0) {
    die("User session not found. Please log in again.");
}

// Count unread admin replies.
$unreadStmt = $conn->prepare(
    "SELECT COUNT(*) AS unread_count
     FROM support_replies r
     INNER JOIN support_messages s ON s.id = r.support_id
     WHERE s.user_id = ?
       AND r.sender_role = 'admin'
       AND r.read_at IS NULL"
);

$unreadStmt->bind_param("i", $user_id);
$unreadStmt->execute();

$unreadCount = (int)$unreadStmt
    ->get_result()
    ->fetch_assoc()["unread_count"];

$unreadStmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Kananke Food Hauz</title>

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
}

button {
    cursor: pointer;
    border: 0;
}

header {
    background: #fff;
    padding: 16px 6%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    box-shadow: 0 2px 15px #0001;
    position: sticky;
    top: 0;
    z-index: 5;
}

.brand {
    font-size: 24px;
    font-weight: 800;
    color: #f97316;
    white-space: nowrap;
}

nav {
    display: flex;
    gap: 22px;
    align-items: center;
    flex-wrap: wrap;
}

nav a {
    color: #344054;
    text-decoration: none;
    font-weight: 600;
    position: relative;
}

nav a:hover {
    color: #f97316;
}

/* CIRCULAR CONTACT SUPPORT BUTTON */

.support-link {
    background: #f97316;
    color: white !important;
    width: 50px;
    height: 50px;
    padding: 0;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: relative;
    flex-shrink: 0;
    font-size: 19px;
    transition: 0.2s;
}

.support-link:hover {
    background: #ea580c;
    transform: scale(1.05);
}

/* NOTIFICATION BADGE */

.notification-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    border-radius: 50px;
    background: #dc2626;
    color: white;
    font-size: 11px;
    font-weight: bold;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
}

.logout {
    background: #111827;
    color: #fff;
    padding: 9px 16px;
    border-radius: 9px;
    text-decoration: none;
    font-weight: 600;
}

.logout:hover {
    background: #374151;
}

main {
    max-width: 1100px;
    margin: auto;
    padding: 50px 20px;
}

.hero {
    background: #fff;
    border-radius: 24px;
    padding: 55px 45px;
    display: grid;
    grid-template-columns: 1.3fr .7fr;
    gap: 30px;
    box-shadow: 0 10px 35px #0001;
}

.hero h1 {
    font-size: 46px;
    line-height: 1.1;
    margin-bottom: 18px;
}

.hero h1 span {
    color: #f97316;
}

.hero p {
    font-size: 18px;
    color: #667085;
    line-height: 1.6;
    margin-bottom: 20px;
}

.hero-box {
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 250px;
}

.hero-box img {
    display: block;
    width: 100%;
    height: auto;
    max-height: 300px;
    object-fit: contain;
}

.btn {
    display: inline-block;
    padding: 13px 24px;
    border-radius: 10px;
    background: #f97316;
    color: white;
    font-weight: 700;
    text-decoration: none;
    margin-top: 10px;
}

.btn:hover {
    background: #ea580c;
}

.section-title {
    text-align: center;
    margin: 55px 0 25px;
    font-size: 30px;
}

.products {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.card {
    background: #fff;
    padding: 25px;
    border-radius: 18px;
    box-shadow: 0 8px 25px #0001;
}

.card .emoji {
    font-size: 45px;
}

.card h3 {
    margin: 12px 0 7px;
}

.card p {
    color: #667085;
    line-height: 1.5;
}

.price {
    display: inline-block;
    color: white;
    background: #f97316;
    font-weight: 800;
    font-size: 16px;
    padding: 10px 16px;
    border-radius: 8px;
    margin-top: 5px;
    text-decoration: none;
}

.price:hover {
    background: #ea580c;
}

/* ABOUT US */

.about-card {
    text-align: center;
    line-height: 1.6;
    color: #667085;
    padding: 30px 20px;
}

/* FACEBOOK BUTTON */

.facebook-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-top: 22px;
}

.facebook-button {
    width: 58px;
    height: 58px;
    border-radius: 50%;
    background: #1877F2;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-decoration: none;
    box-shadow: 0 5px 15px #1877F244;
    transition: 0.25s;
}

.facebook-button:hover {
    background: #0866FF;
    transform: scale(1.1);
    box-shadow: 0 7px 20px #1877F266;
}

.facebook-button span {
    font-family: Arial, sans-serif;
    font-size: 43px;
    font-weight: bold;
    line-height: 1;
    color: white;
    margin-top: 10px;
}

.facebook-text {
    color: #1877F2;
    font-size: 14px;
    font-weight: bold;
    text-decoration: none;
}

.facebook-text:hover {
    text-decoration: underline;
}

.user-info {
    text-align: center;
    margin-bottom: 20px;
    color: #667085;
    font-size: 14px;
}

footer {
    text-align: center;
    padding: 30px;
    color: #667085;
}

/* TABLET */

@media(max-width:900px) {

    header {
        flex-wrap: wrap;
    }

    nav {
        gap: 12px;
    }

    .hero {
        grid-template-columns: 1fr;
    }

    .products {
        grid-template-columns: repeat(2, 1fr);
    }

}

/* MOBILE */

@media(max-width:600px) {

    header {
        padding: 15px 4%;
        align-items: flex-start;
    }

    .brand {
        font-size: 20px;
    }

    nav {
        width: 100%;
        gap: 10px;
    }

    nav a {
        font-size: 13px;
    }

    .support-link {
        width: 44px;
        height: 44px;
        padding: 0;
    }

    main {
        padding: 30px 12px;
    }

    .hero {
        padding: 30px 22px;
    }

    .hero h1 {
        font-size: 36px;
    }

    .hero p {
        font-size: 16px;
    }

    .hero-box {
        height: 180px;
        min-height: 180px;
    }

    .products {
        grid-template-columns: 1fr;
    }

}

</style>
</head>

<body>

<header>

    <div class="brand">🍽 Chekananke</div>

    <nav>

        <a href="#home">Home</a>
        <a href="#products">Products</a>
        <a href="#about">About</a>

        <a href="order_history.php">Order History</a>

        <a href="contact_support.php"
           class="support-link"
           title="Contact Support"
           aria-label="Contact Support">

            💬

            <?php if ($unreadCount > 0): ?>
                <span class="notification-badge">
                    <?php echo $unreadCount > 99 ? "99+" : $unreadCount; ?>
                </span>
            <?php endif; ?>

        </a>

        <a href="logout.php" class="logout">Log Out</a>

    </nav>

</header>

<main id="home">

    <div class="user-info">
        <h3>
            Welcome,
            <?= htmlspecialchars($_SESSION["user_email"] ?? "", ENT_QUOTES, "UTF-8") ?>
        </h3>
    </div>

    <section class="hero">

        <div>

            <h1>
                Good Food.
                <br>
                <span>Good Experience.</span>
            </h1>

            <p>
                Welcome to KANANKE'S FOOD HAUZ,
                a simple modern business website
                for showcasing delicious products
                and serving customers online.
            </p>

            <a href="#products" class="btn">View Products</a>

        </div>

        <div class="hero-box">

            <img src="kanankelogo.png"
                 alt="KANANKE'S FOOD HAUZ Logo">

        </div>

    </section>

    <h2 class="section-title" id="products">Our Products</h2>

    <section class="products">

        <div class="card">
            <div class="emoji">🍗</div>
            <h3>Food by Tray</h3>
            <p>Single Order Food</p>
            <a href="tray.php" class="price">View Menu</a>
        </div>

        <div class="card">
            <div class="emoji">🍱</div>
            <h3>Food Bundle</h3>
            <p>Bundle food with combos</p>
            <a href="bundle.php" class="price">View Menu</a>
        </div>

        <div class="card">
            <div class="emoji">🛒</div>
            <h3>Additionals</h3>
            <p>Add some additional product</p>
            <a href="additionals.php" class="price">View Menu</a>
        </div>

    </section>

    <h2 class="section-title" id="about">About Us</h2>

    <section class="card about-card">


        <!-- FACEBOOK PROFILE BUTTON -->

        <div class="facebook-container">

            <a
                href="https://www.facebook.com/cherry.estomago"
                target="_blank"
                rel="noopener noreferrer"
                class="facebook-button"
                aria-label="Visit our Facebook profile"
                title="Visit our Facebook Account"
            >
                <span>f</span>
            </a>

            <a
                href="https://www.facebook.com/cherry.estomago"
                target="_blank"
                rel="noopener noreferrer"
                class="facebook-text"
            >
                Visit Our Facebook Account
            </a>

        </div>

    </section>

</main>

<footer>
    © 2026 Kananke Food Hauz. All rights reserved.
</footer>

</body>
</html>

<?php
$conn->close();
?>
