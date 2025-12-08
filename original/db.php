<?php
$host = "localhost";
$user = "root"; // default XAMPP username
$pass = ""; // default XAMPP password is empty
$db = "rice_mill_db";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

?>
