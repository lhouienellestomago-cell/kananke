<?php

// =====================================
// PHILIPPINE TIMEZONE
// =====================================

date_default_timezone_set("Asia/Manila");

// =====================================
// DATABASE ERROR REPORTING
// =====================================

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// =====================================
// DATABASE ENVIRONMENT VARIABLES
// =====================================

$host = getenv("DB_HOST");
$user = getenv("DB_USER");
$password = getenv("DB_PASSWORD");
$database = getenv("DB_NAME");
$port = getenv("DB_PORT");

// Default MySQL port if DB_PORT is not configured.
if (!$port || !is_numeric($port)) {
    $port = 3306;
} else {
    $port = (int) $port;
}

// =====================================
// CONNECT TO DATABASE
// =====================================

try {

    $conn = new mysqli(
        $host,
        $user,
        $password,
        $database,
        $port
    );

    // Set character encoding.
    $conn->set_charset("utf8mb4");

    // Set MySQL session timezone to Philippines (UTC+8).
    $conn->query("SET time_zone = '+08:00'");

} catch (mysqli_sql_exception $e) {

    error_log("Database connection error: " . $e->getMessage());

    die("Database connection failed. Please try again later.");

}
