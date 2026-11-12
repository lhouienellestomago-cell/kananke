<?php
session_start();

if (!isset($_SESSION["loggedIn"]) || $_SESSION["loggedIn"] !== true) {
    header("Location: login_page.php");
    exit;
}
?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

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

    background:
        linear-gradient(
            135deg,
            #fff7ed,
            #dcfce7
        );

    color: #172018;
}


button {
    cursor: pointer;

    border: 0;
}


/* =========================
   HEADER
========================= */

header {

    background: #fff;

    padding: 16px 6%;

    display: flex;

    align-items: center;

    justify-content: space-between;

    box-shadow: 0 2px 15px #0001;

    position: sticky;

    top: 0;

    z-index: 5;
}


.brand {

    font-size: 24px;

    font-weight: 800;

    color: #f97316;
}


nav {

    display: flex;

    gap: 22px;

    align-items: center;
}


nav a {

    color: #344054;

    text-decoration: none;

    font-weight: 600;
}


nav a:hover {

    color: #f97316;
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


/* =========================
   MAIN
========================= */

main {

    max-width: 1100px;

    margin: auto;

    padding: 50px 20px;
}


/* =========================
   HERO
========================= */

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
    border-radius: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 250px;
    overflow: visible;
}

.hero-box img {
    display: block;
    width: 100%;
    height: auto;
    max-height: 300px;
    object-fit: contain;
    background: transparent;
    border: none;
    border-radius: 0;
    box-shadow: none;
}



/* =========================
   BUTTON
========================= */

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


/* =========================
   SECTION TITLE
========================= */

.section-title {

    text-align: center;

    margin: 55px 0 25px;

    font-size: 30px;
}


/* =========================
   PRODUCTS
========================= */

.products {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

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
}


.price:hover {

    background: #ea580c;

    cursor: pointer;
}


/* =========================
   ABOUT
========================= */

.about-card {

    text-align: center;

    line-height: 1.6;

    color: #667085;
}


/* =========================
   USER
========================= */

.user-info {

    text-align: center;

    margin-bottom: 20px;

    color: #667085;

    font-size: 14px;
}


/* =========================
   FOOTER
========================= */

footer {

    text-align: center;

    padding: 30px;

    color: #667085;
}


/* =========================
   MOBILE
========================= */

@media(max-width:700px) {

    nav a {
        display: none;
    }

    .hero {

        grid-template-columns: 1fr;

        padding: 30px;
    }

    .hero h1 {

        font-size: 36px;
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


<!-- =========================
     HEADER
========================= -->

<header>

    <div class="brand">
        🍽 Chekananke
    </div>


    <nav>

    <a href="#home">
        Home
    </a>

    <a href="#products">
        Products
    </a>

    <a href="#about">
        About
    </a>

    <a href="order_history.php">
        Order History
    </a>

    <a
        href="logout.php"
        class="logout"
    >
        Log Out
    </a>

</nav>

</header>


<!-- =========================
     MAIN WEBSITE
========================= -->

<main id="home">


    <!-- USER INFO -->

    <div class="user-info">

        <h3>Welcome, <?= htmlspecialchars($_SESSION["user_email"]) ?></h3>
        </strong>

    </div>


    <!-- HERO -->

    <section class="hero">


        <div>

            <h1>

                Good Food.

                <br>

                <span>
                    Good Experience.
                </span>

            </h1>


            <p>

                Welcome to KANANKE'S FOOD HAUZ,
                a simple modern business website
                for showcasing delicious products
                and serving customers online.

            </p>


            <a
                href="#products"
                class="btn"
            >
                View Products
            </a>

        </div>


        <div class="hero-box">

            <img
                src="kanankelogo.png"
                alt="KANANKE'S FOOD HAUZ Logo"
            >

        </div>


    </section>



    <!-- PRODUCTS -->

    <h2
        class="section-title"
        id="products"
    >
        Our Products
    </h2>


    <section class="products">


        <!-- FOOD BY TRAY -->
<div class="card">
    <div class="emoji">🍗</div>
    <h3>Food by Tray</h3>
    <p>Single Order Food</p>
    <a href="tray.php" class="price">View Menu</a>
</div>


<!-- FOOD BUNDLE -->
<div class="card">
    <div class="emoji">🍱</div>
    <h3>Food Bundle</h3>
    <p>Bundle food with combos</p>
    <a href="bundle.php" class="price">View Menu</a>
</div>


<!-- Additionals -->
<div class="card">
    <div class="emoji">🛒</div>
    <h3>Additionals</h3>
    <p>Add some additional product</p>
    <a href="additionals.php" class="price">View Menu</a>
</div>


    </section>



    <!-- ABOUT -->

    <h2
        class="section-title"
        id="about"
    >
        About Us
    </h2>


    <section class="card about-card">

        <p>

            NOT DONE YET!

        </p>

    </section>


</main>


<!-- =========================
     FOOTER
========================= -->

<footer>

    © 2026 Kananke Food Hauz.
    All rights reserved.

</footer>


</body>

</html>