<?php
include "db.php";
include "blockchain_api.php";
session_start();

if (!isset($_GET['id'])) {
    die("Missing purchase ID.");
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("
    SELECT p.*, u.name AS user_name, u.role AS user_role
    FROM palay_purchases p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("Purchase not found.");
}

$row = $res->fetch_assoc();

// Company info
$company_name = "DENNIS RICE MILL";
$company_address = "Brgy. David, San Jose, Tarlac";
$company_contact = "Contact: 09399059153";

// PV number (unique per purchase)
$pv_number = "PV-" . date('Ymd', strtotime($row['purchase_date'])) . "-" . str_pad($row['id'], 4, "0", STR_PAD_LEFT);

// Update DB: mark as printed and save PV number
$update = $conn->prepare("
    UPDATE palay_purchases 
    SET pv_printed = 1, pv_number = ? 
    WHERE id = ?
");
$update->bind_param("si", $pv_number, $id);
$update->execute();

// Log PV generation
$logUserId = $_SESSION['user_id'] ?? 'unknown';
addBlockchainLogWithFallback($conn, $logUserId, 'PV_PRINT', $pv_number, [
    'purchase_id' => $id,
    'pv_number' => $pv_number,
    'supplier' => $row['supplier'],
    'added_by' => $row['user_name'] ?? ''
]);

// Line length for Letter size (~80 chars)
$line_len = 80;
$line = str_repeat("_", $line_len);

// Purchase details
$supplier = strtoupper($row['supplier']);
$date = date('Y-m-d', strtotime($row['purchase_date']));
$qty = number_format($row['quantity'], 2);
$price = number_format($row['price'], 2);
$total = number_format($row['total_amount'], 2);
$added_by = strtoupper($row['user_name']);

// Column widths
$item_w = 12;
$qty_w = 15;
$price_w = 12;
$total_w = 12;

// Prepare PV text
$pv_text = "";

// Single Letter page with spacing between copies
for ($copy=1; $copy<=2; $copy++) {
    $pv_text .= $line . "\n";
    $pv_text .= str_pad("PURCHASE VOUCHER (" . ($copy==1?"SUPPLIER COPY":"RECEIVER COPY") . ")", $line_len, " ", STR_PAD_BOTH) . "\n";
    $pv_text .= $line . "\n";
    $pv_text .= str_pad($company_name, $line_len, " ", STR_PAD_BOTH) . "\n";
    $pv_text .= str_pad($company_address, $line_len, " ", STR_PAD_BOTH) . "\n";
    $pv_text .= str_pad($company_contact, $line_len, " ", STR_PAD_BOTH) . "\n";
    $pv_text .= $line . "\n";

    $pv_text .= str_pad("PV Number   : $pv_number", $line_len) . "\n";
    $pv_text .= str_pad("Purchase Date: $date", $line_len) . "\n";
    $pv_text .= str_pad("Supplier     : $supplier", $line_len) . "\n";
    $pv_text .= str_pad("Added By     : $added_by", $line_len) . "\n";
    $pv_text .= $line . "\n";

    // Table header
    $pv_text .= "|" . str_pad("ITEM", $item_w) 
                . "|" . str_pad("QUANTITY (kg)", $qty_w) 
                . "|" . str_pad("PRICE/kg", $price_w) 
                . "|" . str_pad("TOTAL", $total_w) 
                . "|\n";
    $pv_text .= $line . "\n";

    // Table content
    $pv_text .= "|" . str_pad("PALAY", $item_w) 
                . "|" . str_pad($qty, $qty_w) 
                . "|" . str_pad($price, $price_w) 
                . "|" . str_pad($total, $total_w) 
                . "|\n";
    $pv_text .= $line . "\n\n";

    // Signature lines
    $pv_text .= "SIGNATURES:\n\n";
    $pv_text .= "Supplier: " . str_pad("", 25, "_") . "      Receiver: " . str_pad("", 25, "_") . "\n";
    $pv_text .= str_repeat("\n", 6); // Extra space between copies
}

echo $pv_text;
?>