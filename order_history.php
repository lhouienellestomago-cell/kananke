<?php

session_start();


// Only logged-in users can access order history
if (!isset($_SESSION["loggedIn"]) || $_SESSION["loggedIn"] !== true) {

    header("Location: login_page.php");

    exit;
}


include "db.php";


// Get current user's ID
if (!isset($_SESSION["user_id"])) {

    die("User session not found. Please log in again.");

}

$user_id = $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| GET ONLY THIS USER'S ORDERS
|--------------------------------------------------------------------------
*/

$sql = "SELECT *
        FROM orders
        WHERE user_id = ?
        ORDER BY order_date DESC";


$stmt = $conn->prepare($sql);


if (!$stmt) {

    die(
        "Database error: " .
        $conn->error
    );

}


$stmt->bind_param(
    "i",
    $user_id
);


$stmt->execute();


$result =
    $stmt->get_result();

?>


<!DOCTYPE html>

<html lang="en">


<head>


<meta charset="UTF-8">


<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>


<title>
    My Order History - Kananke Food Hauz
</title>


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

    max-width: 1200px;

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

    margin-bottom: 30px;

}


/* TABLE */

.table-container {

    background: white;

    border-radius: 18px;

    padding: 20px;

    box-shadow: 0 8px 25px #0001;

    overflow-x: auto;

}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 850px;

}


th {

    background: #f97316;

    color: white;

    padding: 14px;

    text-align: left;

}


td {

    padding: 14px;

    border-bottom: 1px solid #eee;

}


tr:hover {

    background: #fff7ed;

}


/* PRICE */

.price {

    color: #f97316;

    font-weight: 800;

}


/* EMPTY */

.empty {

    text-align: center;

    padding: 40px;

    color: #667085;

}


.empty h2 {

    margin-bottom: 10px;

    color: #172018;

}


/* MOBILE */

@media(max-width: 700px) {


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

        My Order History

    </h1>


    <p class="subtitle">

        View the orders you have placed.

    </p>



    <div class="table-container">


        <?php if ($result->num_rows > 0): ?>


            <table>


                <thead>


                    <tr>


                        <th>
                            Order ID
                        </th>


                        <th>
                            Product
                        </th>


                        <th>
                            Price
                        </th>


                        <th>
                            Quantity
                        </th>


                        <th>
                            Total
                        </th>


                        <th>
                            Date
                        </th>


                    </tr>


                </thead>


                <tbody>


                <?php while ($row = $result->fetch_assoc()): ?>


                    <tr>


                        <td>

                            #<?php
                            echo $row["id"];
                            ?>

                        </td>


                        <td>

                            <?php
                            echo htmlspecialchars(
                                $row["product_name"]
                            );
                            ?>

                        </td>


                        <td>

                            ₱<?php
                            echo number_format(
                                $row["price"],
                                2
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                            echo $row["quantity"];
                            ?>

                        </td>


                        <td class="price">

                            ₱<?php
                            echo number_format(
                                $row["total"],
                                2
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                           
$orderDate = new DateTime(
    $row["order_date"],
    new DateTimeZone("UTC")
);

$orderDate->setTimezone(
    new DateTimeZone("Asia/Manila")
);

echo htmlspecialchars(
    $orderDate->format("M d, Y h:i A")
);

                                )
                            );
                            ?>

                        </td>


                    </tr>


                <?php endwhile; ?>


                </tbody>


            </table>


        <?php else: ?>


            <div class="empty">


                <h2>
                    No Orders Yet
                </h2>


                <p>
                    Your orders will appear here
                    after you place an order.
                </p>


            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>
