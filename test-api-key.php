<?php
/**
 * Test API Key Configuration
 * This script tests if the API key in PHP matches the backend
 */

include "blockchain_api.php";

echo "<h2>API Key Configuration Test</h2>";

// Get the API key from PHP config
global $BLOCKCHAIN_API_KEY;
$phpApiKey = $BLOCKCHAIN_API_KEY;

echo "<p><strong>PHP API Key:</strong> " . substr($phpApiKey, 0, 50) . "...</p>";

// Decode to see the structure
try {
    $decoded = base64_decode($phpApiKey);
    $parsed = json_decode($decoded, true);
    
    if ($parsed && isset($parsed['key'])) {
        echo "<p style='color:green;'>✅ API Key format is correct</p>";
        echo "<p><strong>Key:</strong> " . substr($parsed['key'], 0, 20) . "...</p>";
        echo "<p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s', $parsed['timestamp'] / 1000) . "</p>";
    } else {
        echo "<p style='color:orange;'>⚠️ API Key format might be incorrect</p>";
        echo "<p><strong>Decoded:</strong> " . htmlspecialchars($decoded) . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error decoding API key: " . $e->getMessage() . "</p>";
}

// Test API connection
echo "<h3>Testing API Connection</h3>";

$url = "http://localhost:3000/api/v1/blockchain/health";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "<p style='color:green;'>✅ Backend server is running</p>";
} else {
    echo "<p style='color:red;'>❌ Backend server not responding (HTTP $httpCode)</p>";
}

// Test with API key
echo "<h3>Testing with API Key</h3>";

$url = "http://localhost:3000/api/v1/blockchain/latest-hash";
$ch = curl_init($url);
$headers = [
    'Content-Type: application/json',
    'api-key: ' . $phpApiKey
];
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($httpCode === 200) {
    echo "<p style='color:green;'>✅ API key is valid!</p>";
    echo "<p><strong>Response:</strong> " . htmlspecialchars(substr($response, 0, 200)) . "</p>";
} elseif ($httpCode === 401) {
    echo "<p style='color:red;'>❌ API key authentication failed (401 Unauthorized)</p>";
    echo "<p><strong>Response:</strong> " . htmlspecialchars($response) . "</p>";
    echo "<p><strong>Solution:</strong> Make sure the API key in blockchain_api.php matches the API_KEY in backend/.env</p>";
} else {
    echo "<p style='color:orange;'>⚠️ Unexpected response (HTTP $httpCode)</p>";
    echo "<p><strong>Response:</strong> " . htmlspecialchars($response) . "</p>";
}

?>

