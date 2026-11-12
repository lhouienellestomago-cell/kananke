<?php

include "db.php";

$email = "admin@gmail.com";
$password = password_hash("12345", PASSWORD_DEFAULT);

$sql = "INSERT INTO users (email, password) VALUES (?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $email, $password);

if ($stmt->execute()) {
    echo "User created successfully!";
} else {
    echo "Error: " . $stmt->error;
}

?>