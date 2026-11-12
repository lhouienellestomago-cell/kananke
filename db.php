<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = getenv("DB_HOST");
$user = getenv("DB_USER");
$password = getenv("DB_PASSWORD");
$database = getenv("DB_NAME");
$port = (int) getenv("DB_PORT");

if (
    empty($host) ||
    empty($user) ||
    empty($database) ||
    empty($port) ||
    $password === false
) {
    error_log("Database configuration is incomplete.");
    http_response_code(500);
    exit("Database configuration error.");
}

try {
    $conn = new mysqli(
        $host,
        $user,
        $password,
        $database,
        $port
    );

    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    exit("Database connection failed.");
}

// IMPORTANT: Do not add a closing PHP tag.
        