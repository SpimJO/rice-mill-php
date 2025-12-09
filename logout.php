<?php
session_start();
include "db.php";
include "blockchain_api.php";

// Log logout action before clearing the session
if (isset($_SESSION['user_id'])) {
	$uid = $_SESSION['user_id'];
	$uname = $_SESSION['name'] ?? '';
	$role = $_SESSION['role'] ?? '';
	addBlockchainLogWithFallback($conn, $uid, 'LOGOUT_SUCCESS', $uname ?: (string)$uid, [
		'role' => $role,
		'name' => $uname,
		'status' => 'success'
	]);
}

session_unset();
session_destroy();
header("Location: login.php");
exit();
?>
