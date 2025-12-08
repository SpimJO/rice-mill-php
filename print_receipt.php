<?php

date_default_timezone_set('Asia/Manila'); 

if (!isset($_POST['receiptText'])) {
    http_response_code(400);
    echo "Missing receipt text.";
    exit;
}

$receiptText = $_POST['receiptText'];

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

if ($ret === 0) {
    echo "Print job sent to '$printer'";
} else {
    echo "Printer error: Could not print. Please check printer connection.";
}