<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "rice_mill_db";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// 🔒 Authorize this connection for website access
if (!$conn->query("SET @website_authorized = 1;")) {
    die("Authorization variable failed: " . $conn->error);
}
?>
