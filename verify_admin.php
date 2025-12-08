<?php
include "db.php";
session_start();

$password = $_POST['password'] ?? '';
if(!$password){
    echo json_encode(['success'=>false]);
    exit;
}

// Fetch any admin
$stmt = $conn->prepare("SELECT password FROM users WHERE role='Admin' LIMIT 1");
$stmt->execute();
$stmt->bind_result($hash);
$found = $stmt->fetch();
$stmt->close();

if($found && password_verify($password, $hash)){
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false]);
}