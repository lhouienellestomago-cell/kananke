
<?php

// Enable MySQLi error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Railway database credentials from Render Environment Variables
$host = getenv("DB_HOST");
$user = getenv("DB_USER");
$password = getenv("DB_PASSWORD");
$database = getenv("DB_NAME");
$port = (int) getenv("DB_PORT");

// Check if environment variables are configured
if (
    empty($host) ||
    empty($user) ||
    empty($database) ||
    empty($port) ||
    $password === false
) {
    error_log("Database environment variables are missing.");
    http_response_code(500);
    exit("Database configuration is incomplete.");
}

try {

    // Connect to Railway MySQL database
    $conn = new mysqli(
        $host,
        $user,
        $password,
        $database,
        $port
    );

    // Set character encoding
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {

    // Keep detailed errors in server logs, not public pages
    error_log("Database connection failed: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to connect to the database. Please try again later.");
}

?>
