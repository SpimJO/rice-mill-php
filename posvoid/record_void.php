<?php
include "db.php";
session_start();

$user_id = $_SESSION['user_id'] ?? 0;
$user_name = $_SESSION['name'] ?? 'Unknown';
$action = $_POST['action'] ?? '';
$item_name = $_POST['item_name'] ?? null;
$size = $_POST['size'] ?? null;
$qty = $_POST['qty'] ?? null;
$reason = $_POST['reason'] ?? '';

if(!$action){
    echo json_encode(['success'=>false, 'message'=>'Missing action type']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO void_logs (user_id, user_name, action_type, item_name, size, qty, void_reason)
                        VALUES (?,?,?,?,?,?,?)");
$stmt->bind_param("issssis", $user_id, $user_name, $action, $item_name, $size, $qty, $reason);
$stmt->execute();
$stmt->close();

echo json_encode(['success'=>true]);
