<?php
include "db.php";
session_start();

// Read raw input (for JSON)
$raw = file_get_contents("php://input");

// Detect if JSON or form-data
$data = json_decode($raw, true);

if ($data) {
    // JSON from JS fetch ({ action: "CART", items: [...] })
    $action = $data['action'] ?? '';
    $reason = $data['reason'] ?? '';
    $items  = $data['items'] ?? [];
} else {
    // Fallback for form POST
    $action = $_POST['action'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $items  = [[
        'itemName' => $_POST['item_name'] ?? null,
        'size'     => $_POST['size'] ?? null,
        'qty'      => $_POST['qty'] ?? null
    ]];
}

$user_id   = $_SESSION['user_id'] ?? 0;
$user_name = $_SESSION['name'] ?? 'Unknown';

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'Missing action']);
    exit;
}

// --- Prepare SQL ---
$stmt = $conn->prepare("
    INSERT INTO void_logs (user_id, user_name, action_type, item_name, size, qty, void_reason)
    VALUES (?,?,?,?,?,?,?)
");

// Loop through all items (works for CART and ITEM)
foreach ($items as $it) {
    $itemName = $it['itemName'] ?? null;
    $size     = $it['size'] ?? null;
    $qty      = $it['qty'] ?? null;

    $stmt->bind_param(
        "issssis",
        $user_id,
        $user_name,
        $action,
        $itemName,
        $size,
        $qty,
        $reason
    );
    $stmt->execute();
}

$stmt->close();
echo json_encode(['success' => true]);
