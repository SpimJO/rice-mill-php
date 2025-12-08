<?php
/**
 * Test Blockchain Logging
 * This script tests if blockchain logging is working when creating a user
 */

include "db.php";
include "blockchain_api.php";

echo "<h2>Blockchain Logging Test</h2>";

// Test data
$test_user_id = "test_user_" . time();
$test_action = "ADD_USER";
$test_target = "test_target";
$test_data = ["name" => "Test User", "role" => "Admin"];

echo "<h3>Testing Blockchain Log Creation</h3>";
echo "<p><strong>User ID:</strong> $test_user_id</p>";
echo "<p><strong>Action:</strong> $test_action</p>";
echo "<p><strong>Target:</strong> $test_target</p>";
echo "<p><strong>Data:</strong> " . json_encode($test_data) . "</p>";

// Check current log count
$before = $conn->query("SELECT COUNT(*) as count FROM blockchain_log")->fetch_assoc()['count'];
echo "<p><strong>Logs before:</strong> $before</p>";

// Try to create blockchain log
echo "<h3>Creating Blockchain Log...</h3>";

$result = addBlockchainLogWithFallback($conn, $test_user_id, $test_action, $test_target, $test_data);

if ($result) {
    echo "<p style='color:green;'>✅ Blockchain log created successfully!</p>";
} else {
    echo "<p style='color:red;'>❌ Failed to create blockchain log!</p>";
}

// Check new log count
$after = $conn->query("SELECT COUNT(*) as count FROM blockchain_log")->fetch_assoc()['count'];
echo "<p><strong>Logs after:</strong> $after</p>";

if ($after > $before) {
    echo "<p style='color:green;'>✅ New log entry was added to database!</p>";
    
    // Get the latest log
    $latest = $conn->query("SELECT * FROM blockchain_log ORDER BY id DESC LIMIT 1")->fetch_assoc();
    echo "<h3>Latest Log Entry:</h3>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>ID</td><td>{$latest['id']}</td></tr>";
    echo "<tr><td>User ID</td><td>{$latest['user_id']}</td></tr>";
    echo "<tr><td>Action</td><td>{$latest['action']}</td></tr>";
    echo "<tr><td>Target User</td><td>{$latest['target_user']}</td></tr>";
    echo "<tr><td>Data</td><td><pre>" . htmlspecialchars($latest['data']) . "</pre></td></tr>";
    echo "<tr><td>Timestamp</td><td>{$latest['timestamp']}</td></tr>";
    echo "<tr><td>Previous Hash</td><td style='font-family:monospace;font-size:10px;'>" . substr($latest['previous_hash'], 0, 20) . "...</td></tr>";
    echo "<tr><td>Current Hash</td><td style='font-family:monospace;font-size:10px;'>" . substr($latest['current_hash'], 0, 20) . "...</td></tr>";
    echo "</table>";
} else {
    echo "<p style='color:red;'>❌ No new log entry was added!</p>";
    echo "<p>Check PHP error logs for details.</p>";
}

// Check for errors
echo "<h3>Database Connection Status</h3>";
if ($conn->connect_error) {
    echo "<p style='color:red;'>❌ Database connection error: " . $conn->connect_error . "</p>";
} else {
    echo "<p style='color:green;'>✅ Database connected successfully</p>";
}

// Check blockchain_log table structure
echo "<h3>Table Structure Check</h3>";
$columns = $conn->query("SHOW COLUMNS FROM blockchain_log");
if ($columns) {
    echo "<p style='color:green;'>✅ blockchain_log table exists</p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Column</th><th>Type</th></tr>";
    while ($col = $columns->fetch_assoc()) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'>❌ blockchain_log table does not exist or error: " . $conn->error . "</p>";
}

$conn->close();
?>

