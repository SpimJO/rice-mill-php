<?php
include "db.php";
include "blockchain_api.php";
session_start();

date_default_timezone_set('Asia/Manila'); 

// Accept JSON or form-encoded
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (is_array($payload)) {
    $receiptText = $payload['receiptText'] ?? '';
    $transactionId = $payload['transactionId'] ?? '';
    $source = $payload['source'] ?? 'UNKNOWN_PRINT';
} else {
    $receiptText = $_POST['receiptText'] ?? '';
    $transactionId = $_POST['transactionId'] ?? '';
    $source = $_POST['source'] ?? 'UNKNOWN_PRINT';
}

if (trim($receiptText) === '') {
    http_response_code(400);
    echo "Missing receipt text.";
    exit;
}

// Save receipt to temp .txt file (Windows ANSI format)
$tmpFile = sys_get_temp_dir() . "\\receipt_" . time() . ".txt";
file_put_contents($tmpFile, $receiptText);

// Get default printer name
$printer = trim(shell_exec('wmic printer where "Default=True" get Name /value'));
$printer = trim(str_replace('Name=', '', $printer));

if (!$printer) {
    die("Printer error: No default printer found.");
}

// Print silently via Notepad (built into Windows)
$cmd = 'notepad.exe /p "' . $tmpFile . '"';
exec($cmd, $out, $ret);

// Wait a moment to ensure print job starts
sleep(2);
unlink($tmpFile);

// Log print/reprint event to blockchain
$logUserId = $_SESSION['user_id'] ?? 'unknown';
$logTarget = $transactionId ?: ($source ?: 'PRINT_JOB');
$logData = [
    'transaction_id' => $transactionId,
    'source' => $source,
    'printer' => $printer,
    'status' => ($ret === 0) ? 'sent' : 'failed'
];
addBlockchainLogWithFallback($conn, $logUserId, 'RECEIPT_PRINT', $logTarget, $logData);

if ($ret === 0) {
    echo "Print job sent to '$printer'";
} else {
    echo "Printer error: Could not print. Please check printer connection.";
}