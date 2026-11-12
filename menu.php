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

<title>Kananke Food Hauz - Menu</title>

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


/* HEADER */

header {

    background: white;

    padding: 16px 6%;

    display: flex;

    justify-content: space-between;

    align-items: center;

    box-shadow: 0 2px 15px #0001;
}

.brand {

    font-size: 24px;

    font-weight: 800;

    color: #f97316;
}

.back {

    background: #111827;

    color: white;

    padding: 10px 16px;

    border-radius: 9px;

    text-decoration: none;

    font-weight: 600;
}

.back:hover {

    background: #374151;
}


/* MAIN */

.container {

    max-width: 1100px;

    margin: auto;

    padding: 45px 20px;
}

.title {

    text-align: center;

    font-size: 38px;

    margin-bottom: 10px;
}

.subtitle {

    text-align: center;

    color: #667085;

    margin-bottom: 35px;
}


/* MENU */

.menu {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 25px;
}


/* FOOD CARD */

.food-card {

    background: white;

    border-radius: 18px;

    overflow: hidden;

    box-shadow: 0 8px 25px #0001;

    transition: 0.2s;
}

.food-card:hover {

    transform: translateY(-5px);

    box-shadow: 0 12px 30px #0002;
}


/* IMAGE */

.food-card img {

    width: 100%;

    height: 210px;

    object-fit: cover;
}


/* CONTENT */

.food-content {

    padding: 20px;
}

.food-content h2 {

    margin-bottom: 8px;
}

.food-content p {

    color: #667085;

    line-height: 1.5;

    margin-bottom: 15px;
}


/* PRICE */

.price {

    color: #f97316;

    font-size: 21px;

    font-weight: 800;

    margin-bottom: 15px;
}


/* ORDER BUTTON */

.order-btn {

    display: block;

    width: 100%;

    text-align: center;

    background: #f97316;

    color: white;

    padding: 12px;

    border-radius: 9px;

    text-decoration: none;

    font-weight: 700;
}

.order-btn:hover {

    background: #ea580c;
}


/* MOBILE */

@media(max-width: 700px) {

    .menu {

        grid-template-columns: 1fr;

    }

    .title {

        font-size: 30px;

    }

}

</style>

</head>


<body>


<!-- HEADER -->

<header>

    <div class="brand">
        🍽 Chekananke
    </div>

    <a
        href="index.php"
        class="back"
    >
        Back to Home
    </a>

</header>



<!-- MAIN -->

<div class="container">

    <h1 class="title">
        Our Menu
    </h1>

    <p class="subtitle">
        Choose your favorite food and place your order.
    </p>


    <section class="menu">


        <!-- FOOD 1 -->

        <div class="food-card">

            <img
                src="images/chicken.jpg"
                alt="Chicken Tray"
            >

            <div class="food-content">

                <h2>
                    Chicken Tray
                </h2>

                <p>
                    Delicious and crispy chicken
                    perfect for sharing with family
                    and friends.
                </p>

                <div class="price">
                    ₱800
                </div>

                <a
                    href="#"
                    class="order-btn"
                >
                    Order Now
                </a>

            </div>

        </div>



        <!-- FOOD 2 -->

        <div class="food-card">

            <img
                src="images/bbq.jpg"
                alt="Pork BBQ"
            >

            <div class="food-content">

                <h2>
                    Pork BBQ
                </h2>

                <p>
                    Tasty grilled pork BBQ with
                    our special house flavor.
                </p>

                <div class="price">
                    ₱800
                </div>

                <a
                    href="#"
                    class="order-btn"
                >
                    Order Now
                </a>

            </div>

        </div>



        <!-- FOOD 3 -->

        <div class="food-card">

            <img
                src="images/pancit.jpg"
                alt="Pancit"
            >

            <div class="food-content">

                <h2>
                    Special Pancit
                </h2>

                <p>
                    Delicious Filipino-style pancit
                    perfect for sharing.
                </p>

                <div class="price">
                    ₱750
                </div>

                <a
                    href="#"
                    class="order-btn"
                >
                    Order Now
                </a>

            </div>

        </div>



        <!-- FOOD 4 -->

        <div class="food-card">

            <img
                src="images/bundle.jpg"
                alt="Family Bundle"
            >

            <div class="food-content">

                <h2>
                    Family Bundle
                </h2>

                <p>
                    A combination of delicious
                    meals for the whole family.
                </p>

                <div class="price">
                    ₱1500
                </div>

                <a
                    href="#"
                    class="order-btn"
                >
                    Order Now
                </a>

            </div>

        </div>



        <!-- FOOD 5 -->

        <div class="food-card">

            <img
                src="images/fried-chicken.jpg"
                alt="Fried Chicken"
            >

            <div class="food-content">

                <h2>
                    Fried Chicken
                </h2>

                <p>
                    Crispy golden fried chicken
                    with our special seasoning.
                </p>

                <div class="price">
                    ₱800
                </div>

                <a
                    href="#"
                    class="order-btn"
                >
                    Order Now
                </a>

            </div>

        </div>



        <!-- FOOD 6 -->

        <div class="food-card">

            <img
                src="images/dessert.jpg"
                alt="Dessert"
            >

            <div class="food-content">

                <h2>
                    Special Dessert
                </h2>

                <p>
                    Sweet and refreshing dessert
                    perfect after your meal.
                </p>

                <div class="price">
                    ₱350
                </div>

                <a
                    href="#"
                    class="order-btn"
                >
                    Order Now
                </a>

            </div>

        </div>


    </section>

</div>


</body>

</html>