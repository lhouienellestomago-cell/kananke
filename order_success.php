<?php

session_start();

if (!isset($_SESSION["loggedIn"]) || $_SESSION["loggedIn"] !== true) {
    header("Location: login_page.php");
    exit;
}

$order_id = $_GET["id"] ?? "";

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Order Successful</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
}

body {

    min-height: 100vh;

    display: flex;

    justify-content: center;

    align-items: center;

    background:
        linear-gradient(
            135deg,
            #fff7ed,
            #dcfce7
        );
}

.success-card {

    background: white;

    width: 90%;

    max-width: 500px;

    padding: 40px;

    text-align: center;

    border-radius: 18px;

    box-shadow: 0 8px 25px #0001;
}

.icon {

    font-size: 60px;

    margin-bottom: 15px;
}

h1 {

    margin-bottom: 15px;

    color: #172018;
}

p {

    color: #667085;

    line-height: 1.6;

    margin-bottom: 20px;
}

.order-number {

    background: #fff7ed;

    color: #f97316;

    padding: 12px;

    border-radius: 8px;

    font-weight: 800;

    margin-bottom: 25px;
}

.button {

    display: inline-block;

    background: #f97316;

    color: white;

    padding: 12px 20px;

    border-radius: 9px;

    text-decoration: none;

    font-weight: 700;
}

.button:hover {

    background: #ea580c;
}

</style>

</head>

<body>

<div class="success-card">

    <div class="icon">
        ✅
    </div>

    <h1>
        Order Placed!
    </h1>

    <p>
        Thankyou for ordering i just call🕽 u later if ur order is on the way🛺!
    </p>

    <div class="order-number">

        Order #<?php echo htmlspecialchars($order_id); ?>

    </div>

    <a
        href="index.php"
        class="button"
    >
        Back to Home
    </a>

</div>

</body>

</html>