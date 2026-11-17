<?php
date_default_timezone_set("Asia/Manila");
session_start();

if (!isset($_SESSION["loggedIn"]) || $_SESSION["loggedIn"] !== true) {
    header("Location: login_page.php");
    exit;
}

require_once "db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$user_id = (int)($_SESSION["user_id"] ?? 0);
$unread_replies = 0;

// =====================================
// COUNT UNREAD ADMIN REPLIES
// =====================================

if ($user_id > 0) {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS unread_count
         FROM support_replies r
         INNER JOIN support_messages s
            ON s.id = r.support_id
         WHERE s.user_id = ?
           AND r.sender_role = 'admin'
           AND r.read_at IS NULL"
    );

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result()->fetch_assoc();
    $unread_replies = (int)($result["unread_count"] ?? 0);

    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Kananke Food Hauz</title>

<style>

/* =====================================
   GENERAL
===================================== */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: linear-gradient(135deg, #fff7ed, #dcfce7);
    color: #172018;
    min-height: 100vh;
    overflow-x: hidden;
}

a {
    text-decoration: none;
}

/* =====================================
   HEADER
===================================== */

header {
    width: 100%;
    background: white;
    padding: 15px 6%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.brand {
    font-size: 24px;
    font-weight: 800;
    color: #f97316;
    white-space: nowrap;
}

nav {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 18px;
}

nav a {
    color: #344054;
    font-size: 14px;
    font-weight: 600;
    transition: 0.2s;
}

nav a:hover {
    color: #f97316;
}

/* =====================================
   CONTACT SUPPORT CHAT ICON
===================================== */

nav a.support-icon {
    position: relative;
    width: 43px;
    height: 43px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f97316;
    color: white !important;
    border-radius: 50%;
    font-size: 23px;
    flex-shrink: 0;
    transition: 0.2s;
}

nav a.support-icon:hover {
    background: #ea580c;
    color: white !important;
    transform: scale(1.06);
}

/* Unread notification badge */

.notification-count {
    position: absolute;
    top: -6px;
    right: -7px;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    background: #dc2626;
    color: white;
    border: 2px solid white;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: bold;
    line-height: 1;
}

.logout {
    background: #111827;
    color: white !important;
    padding: 10px 14px;
    border-radius: 9px;
}

.logout:hover {
    background: #374151;
}

/* =====================================
   MAIN CONTENT
===================================== */

main {
    width: 100%;
    max-width: 1150px;
    margin: auto;
    padding: 45px 20px;
}

.user-info {
    text-align: center;
    margin-bottom: 20px;
    color: #475467;
    font-size: 14px;
    overflow-wrap: anywhere;
}

/* =====================================
   HERO
===================================== */

.hero {
    background: white;
    border-radius: 22px;
    padding: 45px;
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    align-items: center;
    gap: 30px;
    box-shadow: 0 10px 35px rgba(0,0,0,0.08);
}

.hero-content h1 {
    font-size: 46px;
    line-height: 1.15;
    margin-bottom: 18px;
}

.hero-content h1 span {
    color: #f97316;
}

.hero-content p {
    font-size: 17px;
    color: #667085;
    line-height: 1.7;
    margin-bottom: 20px;
}

.hero-box {
    width: 100%;
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hero-box img {
    display: block;
    width: 100%;
    max-width: 330px;
    max-height: 300px;
    object-fit: contain;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 12px 22px;
    border-radius: 9px;
    background: #f97316;
    color: white;
    font-size: 15px;
    font-weight: bold;
    transition: 0.2s;
}

.btn:hover {
    background: #ea580c;
}

/* =====================================
   SECTION TITLES
===================================== */

.section-title {
    text-align: center;
    margin: 55px 0 25px;
    font-size: 30px;
    color: #172018;
}

/* =====================================
   PRODUCTS
===================================== */

.products {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 22px;
    width: 100%;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 17px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.07);
    min-width: 0;
}

.card .emoji {
    font-size: 42px;
    margin-bottom: 12px;
}

.card h3 {
    font-size: 19px;
    margin-bottom: 8px;
    color: #172018;
}

.card p {
    font-size: 14px;
    color: #667085;
    line-height: 1.5;
    margin-bottom: 12px;
}

.price {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 10px 17px;
    border-radius: 8px;
    background: #f97316;
    color: white;
    font-size: 14px;
    font-weight: bold;
    transition: 0.2s;
}

.price:hover {
    background: #ea580c;
}

/* =====================================
   ABOUT
===================================== */

.about-card {
    text-align: center;
    line-height: 1.7;
    color: #667085;
}

/* =====================================
   FOOTER
===================================== */

footer {
    text-align: center;
    padding: 28px 15px;
    color: #667085;
    font-size: 13px;
}

/* =====================================
   TABLET
===================================== */

@media screen and (max-width: 900px) {

    header {
        padding: 15px 3%;
        flex-direction: column;
        gap: 13px;
    }

    nav {
        width: 100%;
        gap: 12px;
    }

    .hero {
        padding: 30px;
    }

    .hero-content h1 {
        font-size: 38px;
    }

    .products {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

/* =====================================
   MOBILE PHONES
===================================== */

@media screen and (max-width: 600px) {

    body {
        width: 100%;
        overflow-x: hidden;
    }

    header {
        position: relative;
        width: 100%;
        padding: 12px 10px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }

    .brand {
        width: 100%;
        text-align: center;
        font-size: 22px;
    }

    nav {
        width: 100%;
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        align-items: center;
        gap: 7px;
    }

    nav a {
        font-size: 12px;
        padding: 9px 8px;
        border-radius: 7px;
    }

    nav a.support-icon {
        width: 42px;
        height: 42px;
        padding: 0;
        font-size: 22px;
    }

    .notification-count {
        min-width: 19px;
        height: 19px;
        font-size: 10px;
    }

    main {
        width: 100%;
        padding: 22px 12px;
        margin: 0 auto;
    }

    .user-info {
        font-size: 13px;
        margin-bottom: 16px;
    }

    .hero {
        width: 100%;
        display: flex;
        flex-direction: column;
        padding: 25px 17px;
        gap: 20px;
        text-align: center;
        border-radius: 17px;
    }

    .hero-content {
        width: 100%;
    }

    .hero-content h1 {
        font-size: 31px;
        line-height: 1.2;
        margin-bottom: 13px;
    }

    .hero-content p {
        font-size: 14px;
        line-height: 1.6;
        margin-bottom: 16px;
    }

    .hero-box {
        width: 100%;
        min-height: 130px;
    }

    .hero-box img {
        width: 100%;
        max-width: 230px;
        max-height: 170px;
        object-fit: contain;
    }

    .btn {
        width: 100%;
        max-width: 220px;
        min-height: 44px;
        font-size: 14px;
    }

    .section-title {
        font-size: 24px;
        margin: 32px 0 18px;
    }

    .products {
        width: 100%;
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 13px;
    }

    .card {
        width: 100%;
        padding: 18px;
        border-radius: 14px;
    }

    .card .emoji {
        font-size: 35px;
        margin-bottom: 8px;
    }

    .card h3 {
        font-size: 17px;
        margin-bottom: 6px;
    }

    .card p {
        font-size: 13px;
        margin-bottom: 10px;
    }

    .price {
        min-height: 40px;
        padding: 10px 17px;
        font-size: 13px;
    }

    .about-card {
        padding: 20px 15px;
        font-size: 14px;
    }

    footer {
        padding: 22px 12px;
        font-size: 12px;
    }
}

/* =====================================
   VERY SMALL PHONES
===================================== */

@media screen and (max-width: 360px) {

    .brand {
        font-size: 20px;
    }

    nav {
        gap: 5px;
    }

    nav a {
        font-size: 11px;
        padding: 8px 7px;
    }

    nav a.support-icon {
        width: 40px;
        height: 40px;
        padding: 0;
        font-size: 21px;
    }

    .hero-content h1 {
        font-size: 27px;
    }

    .section-title {
        font-size: 22px;
    }

    .card {
        padding: 16px;
    }
}

</style>

</head>

<body>

<!-- =====================================
     HEADER / NAVIGATION
===================================== -->

<header>

    <div class="brand">
        🍽 Kananke Food Hauz
    </div>

    <nav>

        <a href="#home">Home</a>

        <a href="#products">Products</a>

        <a href="#about">About</a>

        <a href="order_history.php">
            Order History
        </a>

        <!-- CONTACT SUPPORT ICON -->

        <a
            href="contact_support.php"
            class="support-icon"
            title="Contact Support"
            aria-label="Contact Support"
        >
            💬

            <?php if ($unread_replies > 0): ?>

                <span class="notification-count">
                    <?= $unread_replies > 99 ? "99+" : $unread_replies ?>
                </span>

            <?php endif; ?>

        </a>

        <a href="logout.php" class="logout">
            Log Out
        </a>

    </nav>

</header>

<!-- =====================================
     MAIN PAGE
===================================== -->

<main id="home">

    <div class="user-info">

        <h3>
            Welcome,
            <?= htmlspecialchars($_SESSION["user_email"] ?? "Customer", ENT_QUOTES, "UTF-8") ?>
        </h3>

    </div>

    <!-- HERO SECTION -->

    <section class="hero">

        <div class="hero-content">

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

            <a href="#products" class="btn">
                View Products
            </a>

        </div>

        <div class="hero-box">

            <img
                src="kanankelogo.png"
                alt="Kananke Food Hauz Logo"
            >

        </div>

    </section>

    <!-- PRODUCTS SECTION -->

    <h2 class="section-title" id="products">
        Our Products
    </h2>

    <section class="products">

        <div class="card">

            <div class="emoji">🍗</div>

            <h3>Food by Tray</h3>

            <p>
                Single Order Food
            </p>

            <a href="tray.php" class="price">
                View Menu
            </a>

        </div>

        <div class="card">

            <div class="emoji">🍱</div>

            <h3>Food Bundle</h3>

            <p>
                Bundle food with combos
            </p>

            <a href="bundle.php" class="price">
                View Menu
            </a>

        </div>

        <div class="card">

            <div class="emoji">🛒</div>

            <h3>Additionals</h3>

            <p>
                Add some additional product
            </p>

            <a href="additionals.php" class="price">
                View Menu
            </a>

        </div>

    </section>

    <!-- ABOUT SECTION -->

    <h2 class="section-title" id="about">
        About Us
    </h2>

    <section class="card about-card">

        <p>
            NOT DONE YET!
        </p>

    </section>

</main>

<!-- FOOTER -->

<footer>

    © <?= date("Y") ?> Kananke Food Hauz. All rights reserved.

</footer>

</body>
</html>
