<?php
/**
 * Database Configuration
 * This file handles both local (XAMPP) and cloud deployment
 */

// Check if running in Docker/Cloud environment
$isDocker = getenv('DB_HOST') !== false;

if ($isDocker) {
    // Docker/Cloud environment
    $host = getenv('DB_HOST') ?: 'mysql';
    $user = getenv('DB_USER') ?: 'rice_mill_user';
    $pass = getenv('DB_PASSWORD') ?: 'rice_mill_pass';
    $db   = getenv('DB_NAME') ?: 'rice_mill_db';
    $port = getenv('DB_PORT') ?: 3306;
} else {
    // Local XAMPP environment
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db   = "rice_mill_db";
    $port = 3306;
}

// Create connection
$conn = new mysqli($host, $user, $pass, $db, $port);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Database connection failed. Please try again later.");
}

// Set charset
$conn->set_charset("utf8mb4");

// Authorize connection for website access
if (!$conn->query("SET @website_authorized = 1;")) {
    error_log("Authorization variable failed: " . $conn->error);
}

// Backend API URL
$backendApiUrl = getenv('BACKEND_API_URL') ?: 'http://localhost:3000'; // Localhost (commented out)
// $backendApiUrl = getenv('BACKEND_API_URL') ?: 'https://plantar-dithyrambic-janette.ngrok-free.dev';

// Function to get API key from backend
function getApiKey() {
    global $backendApiUrl;
    return file_get_contents($backendApiUrl . '/api/v1/api-key');
}
?>
