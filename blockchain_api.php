<?php
/**
 * Blockchain API Helper
 * This file provides functions to interact with the Hyperledger Fabric backend API
 */

/**
 * Blockchain API Configuration
 * 
 * IMPORTANT: Set these values to match your backend .env file
 * 
 * 1. Run: cd backend && node generate-keys.js
 * 2. Copy the API_KEY from output
 * 3. Set $BLOCKCHAIN_API_KEY below to match backend .env API_KEY
 * 4. Update $BLOCKCHAIN_API_URL if backend runs on different port
 */

// Backend API URL (default: http://localhost:3000/api/v1)
$BLOCKCHAIN_API_URL = getenv('BLOCKCHAIN_API_URL') ?: 'http://localhost:3000/api/v1';

// API Key (MUST match API_KEY in backend/.env)
// Current API Key (generated and configured - matches backend/.env)
$BLOCKCHAIN_API_KEY = getenv('BLOCKCHAIN_API_KEY') ?: 'eyJrZXkiOiIwMWFkYzhhZGVjMjIzNjlmMTRkYzFiZjMzMTE0OWQyNmU4NTkwYjI4Y2VlYWEzMzIzZjFmMjEyMGIzOGE3NmE2IiwidGltZXN0YW1wIjoxNzY1MDIyNTYxMjgyfQ==';

/**
 * Make HTTP request to blockchain API
 * 
 * @param string $endpoint API endpoint
 * @param string $method HTTP method (GET, POST, etc.)
 * @param array|null $data Request data for POST requests
 * @return array Response data
 */
function blockchainApiRequest($endpoint, $method = 'GET', $data = null) {
    global $BLOCKCHAIN_API_URL, $BLOCKCHAIN_API_KEY;
    
    $url = rtrim($BLOCKCHAIN_API_URL, '/') . '/' . ltrim($endpoint, '/');
    
    $ch = curl_init($url);
    
    $headers = [
        'Content-Type: application/json',
        'api-key: ' . $BLOCKCHAIN_API_KEY
    ];
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($method === 'POST' && $data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    // Set timeout
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        error_log("Blockchain API Error: " . $error);
        return [
            'success' => false,
            'error' => $error,
            'http_code' => 0
        ];
    }
    
    $decoded = json_decode($response, true);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'data' => $decoded,
        'http_code' => $httpCode,
        'raw_response' => $response
    ];
}

/**
 * Create a blockchain log entry via Hyperledger Fabric
 * 
 * @param string $user_id User ID performing the action
 * @param string $action Action being performed
 * @param string $target_user Target user or entity
 * @param mixed $data Data to be stored (will be JSON encoded)
 * @param string|null $timestamp Optional timestamp (defaults to current time)
 * @param string|null $previous_hash Optional previous hash (will be fetched if not provided)
 * @return array Result with success status, hash, and transaction ID
 */
function createBlockchainLog($user_id, $action, $target_user, $data, $timestamp = null, $previous_hash = null) {
    // Use current timestamp if not provided
    if ($timestamp === null) {
        $timestamp = date('Y-m-d H:i:s');
    }
    
    // Get previous hash if not provided
    if ($previous_hash === null) {
        $hashResult = getLatestBlockchainHash();
        if ($hashResult['success']) {
            $previous_hash = $hashResult['hash'] ?? '';
        } else {
            $previous_hash = '';
        }
    }
    
    // Prepare request data
    $requestData = [
        'userId' => (string)$user_id,
        'action' => $action,
        'targetUser' => $target_user,
        'data' => $data,
        'timestamp' => $timestamp,
        'previousHash' => $previous_hash
    ];
    
    // Make API request
    $response = blockchainApiRequest('blockchain/log', 'POST', $requestData);
    
    if ($response['success'] && isset($response['data']['data'])) {
        return [
            'success' => true,
            'hash' => $response['data']['data']['hash'] ?? '',
            'previousHash' => $response['data']['data']['previousHash'] ?? $previous_hash ?? '',
            'currentHash' => $response['data']['data']['currentHash'] ?? $response['data']['data']['hash'] ?? '',
            'txId' => $response['data']['data']['txId'] ?? '',
            'timestamp' => $response['data']['data']['timestamp'] ?? $timestamp
        ];
    } else {
        error_log("Failed to create blockchain log: " . json_encode($response));
        return [
            'success' => false,
            'error' => $response['data']['message'] ?? 'Unknown error',
            'http_code' => $response['http_code']
        ];
    }
}

/**
 * Get the latest blockchain hash
 * 
 * @return array Result with success status and hash
 */
function getLatestBlockchainHash() {
    $response = blockchainApiRequest('blockchain/latest-hash', 'GET');
    
    if ($response['success'] && isset($response['data']['data']['hash'])) {
        return [
            'success' => true,
            'hash' => $response['data']['data']['hash']
        ];
    } else {
        return [
            'success' => false,
            'hash' => '',
            'error' => $response['data']['message'] ?? 'Unknown error'
        ];
    }
}

/**
 * Get a specific blockchain log by ID
 * 
 * @param string $logId Log ID
 * @return array Log data or error
 */
function getBlockchainLog($logId) {
    $response = blockchainApiRequest('blockchain/log/' . urlencode($logId), 'GET');
    
    if ($response['success'] && isset($response['data']['data'])) {
        return [
            'success' => true,
            'log' => $response['data']['data']
        ];
    } else {
        return [
            'success' => false,
            'error' => $response['data']['message'] ?? 'Log not found'
        ];
    }
}

/**
 * Get all blockchain logs
 * 
 * @return array All logs or error
 */
function getAllBlockchainLogs() {
    $response = blockchainApiRequest('blockchain/logs', 'GET');
    
    if ($response['success'] && isset($response['data']['data']['logs'])) {
        return [
            'success' => true,
            'logs' => $response['data']['data']['logs'],
            'count' => $response['data']['data']['count'] ?? 0
        ];
    } else {
        return [
            'success' => false,
            'logs' => [],
            'error' => $response['data']['message'] ?? 'Unknown error'
        ];
    }
}

/**
 * Verify blockchain integrity
 * 
 * @return array Verification result
 */
function verifyBlockchain() {
    $response = blockchainApiRequest('blockchain/verify', 'GET');
    
    if ($response['success'] && isset($response['data']['data'])) {
        return [
            'success' => true,
            'valid' => $response['data']['data']['valid'] ?? false,
            'message' => $response['data']['data']['message'] ?? ''
        ];
    } else {
        return [
            'success' => false,
            'error' => $response['data']['message'] ?? 'Verification failed'
        ];
    }
}

/**
 * Check blockchain API health
 * 
 * @return array Health status
 */
function checkBlockchainHealth() {
    global $BLOCKCHAIN_API_URL;
    
    $url = rtrim($BLOCKCHAIN_API_URL, '/') . '/blockchain/health';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return [
            'success' => true,
            'status' => $data['data']['status'] ?? 'unknown',
            'message' => $data['data']['message'] ?? ''
        ];
    } else {
        return [
            'success' => false,
            'status' => 'disconnected',
            'message' => 'Blockchain API is not available'
        ];
    }
}

/**
 * Fallback function: Create blockchain log with database fallback
 * This function tries to use Hyperledger Fabric first, then falls back to database
 * 
 * @param mysqli $conn Database connection
 * @param string $user_id User ID
 * @param string $action Action
 * @param string $target_user Target user
 * @param mixed $data Data
 * @return bool Success status
 */
function addBlockchainLogWithFallback($conn, $user_id, $action, $target_user, $data) {
    // Try Hyperledger Fabric first
    $result = createBlockchainLog($user_id, $action, $target_user, $data);
    
    if ($result['success']) {
        // Also store in database for quick access (optional)
        $dataString = is_array($data) ? json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $data;
        $timestamp = $result['timestamp'] ?? date('Y-m-d H:i:s');
        $hash = $result['currentHash'] ?? $result['hash'];
        // Prefer the chaincode-provided previous hash; fall back to DB if missing
        $prevHash = $result['previousHash'] ?? '';
        if ($prevHash === '') {
            $res = $conn->query("SELECT current_hash FROM blockchain_log ORDER BY id DESC LIMIT 1");
            if ($res && $res->num_rows > 0) {
                $prevHash = $res->fetch_assoc()['current_hash'];
            }
        }
        
        $stmt = $conn->prepare("
            INSERT INTO blockchain_log (user_id, action, target_user, data, timestamp, previous_hash, current_hash)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            error_log("Database prepare error (Fabric success): " . $conn->error);
            return false;
        }
        
        // user_id is varchar(50) in database, so use 's' not 'i'
        // Convert to string variables (bind_param requires variables, not direct values)
        $user_id_str = (string)$user_id;
        $stmt->bind_param("sssssss", $user_id_str, $action, $target_user, $dataString, $timestamp, $prevHash, $hash);
        
        if (!$stmt->execute()) {
            error_log("Database insert error (Fabric success): " . $stmt->error);
            $stmt->close();
            return false;
        }
        
        $stmt->close();
        
        return true;
    } else {
        // Fallback to database-only logging
        error_log("Hyperledger Fabric unavailable, using database fallback");
        
        $prevHash = '';
        $res = $conn->query("SELECT current_hash FROM blockchain_log ORDER BY id DESC LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $prevHash = $res->fetch_assoc()['current_hash'];
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $dataString = is_array($data) ? json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $data;
        $recordString = $user_id . '|' . $action . '|' . $target_user . '|' . $dataString . '|' . $timestamp . '|' . $prevHash;
        $currentHash = hash('sha256', $recordString);
        
        $stmt = $conn->prepare("
            INSERT INTO blockchain_log (user_id, action, target_user, data, timestamp, previous_hash, current_hash)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            error_log("Database prepare error: " . $conn->error);
            return false;
        }
        
        // user_id is varchar(50) in database, so use 's' not 'i'
        // Convert to string variables (bind_param requires variables, not direct values)
        $user_id_str = (string)$user_id;
        $stmt->bind_param("sssssss", $user_id_str, $action, $target_user, $dataString, $timestamp, $prevHash, $currentHash);
        
        if (!$stmt->execute()) {
            error_log("Database insert error: " . $stmt->error);
            $stmt->close();
            return false;
        }
        
        $stmt->close();
        
        return true;
    }
}

?>

