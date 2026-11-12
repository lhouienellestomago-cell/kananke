<?php

session_start();

// Remove all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Return to login page
header("Location: login_page.php");
exit;

?>