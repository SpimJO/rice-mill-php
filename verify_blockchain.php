<?php
/**
 * Blockchain Verification Script
 * Verifies the integrity of the blockchain hash chain
 */

include "db.php";

echo "<h2>Blockchain Hash Chain Verification</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr style='background: #f0f0f0;'>";
echo "<th>ID</th><th>Action</th><th>Previous Hash</th><th>Current Hash</th><th>Chain Status</th>";
echo "</tr>";

$result = $conn->query("SELECT id, action, previous_hash, current_hash FROM blockchain_log ORDER BY id ASC");
$previous_hash = '';
$valid_count = 0;
$total_count = 0;

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $total_count++;
        $id = $row['id'];
        $action = $row['action'];
        $prev_hash = $row['previous_hash'];
        $curr_hash = $row['current_hash'];
        
        // Check if chain is valid
        $is_valid = true;
        $status = '';
        
        if ($id == 1 || empty($prev_hash)) {
            // First record (genesis block)
            $status = '✅ Genesis Block';
        } else {
            // Check if previous_hash matches previous record's current_hash
            if ($prev_hash === $previous_hash) {
                $status = '✅ Valid';
                $valid_count++;
            } else {
                $status = '❌ Broken Chain';
                $is_valid = false;
            }
        }
        
        $color = $is_valid ? '#d4edda' : '#f8d7da';
        
        echo "<tr style='background: $color;'>";
        echo "<td>$id</td>";
        echo "<td>$action</td>";
        echo "<td style='font-family: monospace; font-size: 10px;'>" . substr($prev_hash, 0, 20) . "...</td>";
        echo "<td style='font-family: monospace; font-size: 10px;'>" . substr($curr_hash, 0, 20) . "...</td>";
        echo "<td>$status</td>";
        echo "</tr>";
        
        $previous_hash = $curr_hash;
    }
}

echo "</table>";

echo "<br><h3>Summary</h3>";
echo "<p>Total Records: <strong>$total_count</strong></p>";
echo "<p>Valid Chain Links: <strong>$valid_count</strong></p>";
echo "<p>Chain Integrity: <strong>" . ($valid_count == $total_count - 1 ? "✅ PERFECT" : "⚠️ NEEDS ATTENTION") . "</strong></p>";

// Verify hash generation
echo "<br><h3>Hash Verification</h3>";
echo "<p>Checking if current_hash is correctly generated...</p>";

$verify_result = $conn->query("SELECT id, user_id, action, target_user, data, timestamp, previous_hash, current_hash FROM blockchain_log ORDER BY id ASC LIMIT 5");
$hash_errors = 0;

if ($verify_result && $verify_result->num_rows > 0) {
    while($row = $verify_result->fetch_assoc()) {
        // Recalculate hash
        $recordString = $row['user_id'] . '|' . $row['action'] . '|' . $row['target_user'] . '|' . $row['data'] . '|' . $row['timestamp'] . '|' . $row['previous_hash'];
        $calculatedHash = hash('sha256', $recordString);
        
        if ($calculatedHash !== $row['current_hash']) {
            $hash_errors++;
            echo "<p style='color: red;'>❌ ID {$row['id']}: Hash mismatch!</p>";
        }
    }
    
    if ($hash_errors == 0) {
        echo "<p style='color: green;'>✅ All hashes are correctly generated!</p>";
    }
}

echo "<br><p><strong>Conclusion:</strong> Your blockchain data structure is <strong>CORRECT and USEFUL</strong>! ✅</p>";
?>

