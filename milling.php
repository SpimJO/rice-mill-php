<?php
include "db.php";
include "blockchain_api.php"; // Hyperledger Fabric API helper
session_start();
date_default_timezone_set('Asia/Manila'); 

// require login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_user_id_int = intval($current_user_id); // for binding as int
$current_user_name = $_SESSION['name'] ?? '';
$current_role = $_SESSION['role'] ?? 'Operator';

// === Fetch total Palay quantity from palay_milling_process ===
$palay_total = 0;
$palay_res = $conn->query("SELECT SUM(palay_quantity) AS total_palay FROM palay_milling_process");
if ($palay_res) {
    $palay_row = $palay_res->fetch_assoc();
    $palay_total = floatval($palay_row['total_palay'] ?? 0);
}

// === Load Operators for dropdown ===
$operators = [];
$op_res = $conn->query("SELECT user_id, name FROM users WHERE role = 'Operator' ORDER BY name ASC");
if ($op_res) {
    while ($op = $op_res->fetch_assoc()) {
        $operators[] = $op;
    }
}

// === Load Rice Types for dropdown ===
$rice_types = [];
$rt_res = $conn->query("SELECT type_name FROM rice_types ORDER BY type_name ASC");
if ($rt_res) {
    while ($r = $rt_res->fetch_assoc()) {
        $rice_types[] = $r; // each row has 'type_name'
    }
}

// === Handle Add Milling ===
if (isset($_POST['add'])) {
    $rice_name = trim($_POST['rice_type']);
    $date = $_POST['date'] ?? date('Y-m-d');
    $operator_user_id = ($current_role === 'Admin') ? $_POST['operator_user_id'] : $current_user_id;
    $quantity = floatval($_POST['quantity']);
    $milled_output = floatval($_POST['milled_output']);
    
    // Check total palay available
    if ($quantity > $palay_total) {
        $_SESSION['error_message'] = "Cannot add Milling: Quantity exceeds total available Palay ({$palay_total} kg).";
        header("Location: milling.php");
        exit();
    }

    if ($milled_output <= $quantity && $rice_name !== "") {
        // Insert milling record
        $sql = "INSERT INTO milling (rice_name, date, quantity, milled_output, operator_user_id, status) 
                VALUES (?, ?, ?, ?, ?, 'Pending')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdds", $rice_name, $date, $quantity, $milled_output, $operator_user_id);
        $stmt->execute();
        $stmt->close();

        // Deduct Palay immediately (FIFO)
        $remaining_to_deduct = $quantity;
        $palay_res = $conn->query("SELECT id, palay_quantity FROM palay_milling_process WHERE palay_quantity > 0 ORDER BY id ASC");
        while ($row = $palay_res->fetch_assoc()) {
            if ($remaining_to_deduct <= 0) break;
            $deduct_qty = min($row['palay_quantity'], $remaining_to_deduct);
            $update = $conn->prepare("UPDATE palay_milling_process SET palay_quantity = palay_quantity - ? WHERE id = ?");
            $update->bind_param("di", $deduct_qty, $row['id']);
            $update->execute();
            $update->close();
            $remaining_to_deduct -= $deduct_qty;
        }
    }

    createBlockchainLog($current_user_id_int, 'Add Milling', $operator_user_id, 
      json_encode(['rice_type'=>$rice_name, 'quantity'=>$quantity, 'output'=>$milled_output]));
    header("Location: milling.php");
    exit();
}

// === Handle Edit Milling ===
if (isset($_POST['edit'])) {
    $id = intval($_POST['id']);
    $rice_type_name = trim($_POST['rice_type'] ?? '');
    $date = $_POST['date'] ?? null;
    $quantity = floatval($_POST['quantity'] ?? 0);
    $milled_output = floatval($_POST['milled_output'] ?? 0);
    $operator_user_id_posted = isset($_POST['operator_user_id']) ? intval($_POST['operator_user_id']) : null;

    // Get old record
    $old_qty = 0; $old_milled = 0; $status = '';
    $stmt = $conn->prepare("SELECT quantity, milled_output, status FROM milling WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($old_qty, $old_milled, $status);
    $stmt->fetch();
    $stmt->close();

    $diff_qty = $quantity - $old_qty;
    $diff_milled = $milled_output - $old_milled;

    if ($rice_type_name !== '' && $milled_output <= $quantity) {
        // Update milling record
        if ($current_role === 'Operator') {
            $stmt = $conn->prepare("UPDATE milling SET rice_name=?, date=?, quantity=?, milled_output=? WHERE id=? AND operator_user_id=? AND status='Pending'");
            $stmt->bind_param("ssddii", $rice_type_name, $date, $quantity, $milled_output, $id, $current_user_id_int);
            $stmt->execute();
            $stmt->close();
        } else {
            if ($operator_user_id_posted !== null) {
                $stmt = $conn->prepare("UPDATE milling SET rice_name=?, date=?, quantity=?, milled_output=?, operator_user_id=? WHERE id=?");
                $stmt->bind_param("ssddii", $rice_type_name, $date, $quantity, $milled_output, $operator_user_id_posted, $id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("UPDATE milling SET rice_name=?, date=?, quantity=?, milled_output=? WHERE id=?");
                $stmt->bind_param("ssddi", $rice_type_name, $date, $quantity, $milled_output, $id);
                $stmt->execute();
                $stmt->close();
            }
        }

        // Adjust Palay for Pending
        if ($status === 'Pending' && abs($diff_qty) > 0.0001) {
            if ($diff_qty > 0) {
                $remaining_to_deduct = $diff_qty;
                $palay_res = $conn->query("SELECT id, palay_quantity FROM palay_milling_process WHERE palay_quantity > 0 ORDER BY id ASC");
                while ($row = $palay_res->fetch_assoc()) {
                    if ($remaining_to_deduct <= 0) break;
                    $deduct_qty = min($row['palay_quantity'], $remaining_to_deduct);
                    $update = $conn->prepare("UPDATE palay_milling_process SET palay_quantity = palay_quantity - ? WHERE id = ?");
                    $update->bind_param("di", $deduct_qty, $row['id']);
                    $update->execute();
                    $update->close();
                    $remaining_to_deduct -= $deduct_qty;
                }
            } else {
                $to_restore = abs($diff_qty);
                $palay_res = $conn->query("SELECT id FROM palay_milling_process ORDER BY id DESC");
                while ($row = $palay_res->fetch_assoc()) {
                    if ($to_restore <= 0) break;
                    $update = $conn->prepare("UPDATE palay_milling_process SET palay_quantity = palay_quantity + ? WHERE id = ?");
                    $update->bind_param("di", $to_restore, $row['id']);
                    $update->execute();
                    $update->close();
                    $to_restore = 0;
                }
            }
        }

        // Adjust rice stock if Approved
        if ($status === 'Approved' && abs($diff_milled) > 0.0001) {
            $updateStock = $conn->prepare("UPDATE rice_types SET total_quantity_kg = total_quantity_kg + ? WHERE type_name=?");
            $updateStock->bind_param("ds", $diff_milled, $rice_type_name);
            $updateStock->execute();
            $updateStock->close();
        }
    }

    createBlockchainLog($current_user_id_int, 'Edit Milling', $operator_user_id_posted ?? $current_user_id, 
    json_encode(['milling_id'=>$id, 'new_quantity'=>$quantity, 'new_output'=>$milled_output]));
header("Location: milling.php");
exit();
}

// === Handle Delete Milling ===
if (isset($_POST['delete'])) {
    $id = intval($_POST['id']);

    // Get record details
    $stmt = $conn->prepare("SELECT rice_name, quantity, milled_output, status, operator_user_id FROM milling WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($rice_name, $quantity, $milled_output, $status, $operator_id);
    $has_data = $stmt->fetch();
    $stmt->close();

    if ($has_data) {
        if ($status === 'Pending') {
            // Restore Palay
            $remaining_to_restore = $quantity;
            $palay_res = $conn->query("SELECT id, palay_quantity FROM palay_milling_process ORDER BY id ASC");
            while ($row = $palay_res->fetch_assoc()) {
                if ($remaining_to_restore <= 0) break;
                $update = $conn->prepare("UPDATE palay_milling_process SET palay_quantity = palay_quantity + ? WHERE id = ?");
                $update->bind_param("di", $remaining_to_restore, $row['id']);
                $update->execute();
                $update->close();
                $remaining_to_restore = 0;
            }
        }

        if ($status === 'Approved') {
            // Deduct milled stock
            $updateStock = $conn->prepare("UPDATE rice_types SET total_quantity_kg = total_quantity_kg - ? WHERE type_name=?");
            $updateStock->bind_param("ds", $milled_output, $rice_name);
            $updateStock->execute();
            $updateStock->close();
        }
    }

    // Delete record
    if ($current_role === 'Admin') {
        $stmt = $conn->prepare("DELETE FROM milling WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("DELETE FROM milling WHERE id=? AND operator_user_id=? AND status='Pending'");
        $stmt->bind_param("ii", $id, $current_user_id_int);
        $stmt->execute();
        $stmt->close();
    }

    createBlockchainLog($current_user_id_int, 'Delete Milling', $operator_id ?? '', 
    json_encode(['milling_id'=>$id, 'rice_type'=>$rice_name]));
header("Location: milling.php");
exit();
}

// === Handle Rice Types Add/Delete ===
if (isset($_POST['add_rice_type'])) {
  $type_name = trim($_POST['type_name']);
  if ($type_name !== "") {
      // Insert new rice type
      $stmt = $conn->prepare("INSERT IGNORE INTO rice_types (type_name) VALUES (?)");
      if (!$stmt) {
          die("Prepare failed (INSERT rice_types): " . $conn->error);
      }
      $stmt->bind_param("s", $type_name);
      $stmt->execute();
      $stmt->close();

      // ✅ Create blockchain log for adding a new rice type
      createBlockchainLog(
          intval($_SESSION['user_id']),
          'Add Rice Type',
          $_SESSION['name'] ?? '',
          json_encode(['rice_type' => $type_name])
      );
  }

  header("Location: milling.php");
  exit();
}

if (isset($_POST['delete_rice_type'])) {
  $type_name = trim($_POST['type_name'] ?? '');

  $check = $conn->prepare("SELECT COUNT(*) FROM milling WHERE rice_name = ?");
  if (!$check) {
      die("Prepare failed (check rice used): " . $conn->error);
  }
  $check->bind_param("s", $type_name);
  $check->execute();
  $check->bind_result($count);
  $check->fetch();
  $check->close();

  if ($count > 0) {
      $_SESSION['error_message'] = "Cannot delete this rice type because it is used in Milling Records.";
  } else {
      $stmt = $conn->prepare("DELETE FROM rice_types WHERE type_name=?");
      if (!$stmt) {
          die("Prepare failed (DELETE rice_type): " . $conn->error);
      }
      $stmt->bind_param("s", $type_name);
      $stmt->execute();
      $stmt->close();

      // ✅ Create blockchain log for deleting a rice type
      createBlockchainLog(
          intval($_SESSION['user_id']),
          'Delete Rice Type',
          $_SESSION['name'] ?? '',
          json_encode(['rice_type' => $type_name])
      );
  }

  header("Location: milling.php");
  exit();

}

// === Admin Actions ===
if ($current_role === 'Admin') {

    // --- Approve ---
if (isset($_POST['approve'])) {
    $id = intval($_POST['id']);

    // Update status to Approved
    $stmt = $conn->prepare("UPDATE milling SET status='Approved' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // Fetch milled_output and rice_name
    $stmt = $conn->prepare("SELECT rice_name, milled_output FROM milling WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($rice_name, $milled_output);
    $stmt->fetch();
    $stmt->close();

    // Update rice_types total_quantity_kg
    $updateStock = $conn->prepare("UPDATE rice_types SET total_quantity_kg = total_quantity_kg + ? WHERE type_name=?");
    $updateStock->bind_param("ds", $milled_output, $rice_name);
    $updateStock->execute();
    $updateStock->close();

    createBlockchainLog($current_user_id_int, 'Approve Milling', $current_user_name, 
      json_encode(['milling_id'=>$id, 'rice_name'=>$rice_name, 'milled_output'=>$milled_output]));
    header("Location: milling.php");
    exit();
}

    // --- Reject ---
    if (isset($_POST['reject_confirm'])) {
        $id = intval($_POST['id']);
        $remarks = trim($_POST['remarks'] ?? '');

        // Update status to Rejected and save remarks
        $stmt = $conn->prepare("UPDATE milling SET status='Rejected', remarks=? WHERE id=?");
        if (!$stmt) die("Prepare failed (reject update): " . $conn->error);
        $stmt->bind_param("si", $remarks, $id);
        $stmt->execute();
        $stmt->close();

        createBlockchainLog($current_user_id_int, 'Reject Milling', $current_user_name, 
        json_encode(['milling_id'=>$id, 'remarks'=>$remarks]));
    header("Location: milling.php");
    exit();
    }

    // --- Save/Edit Remarks ---
    if (isset($_POST['save_remarks'])) {
        $id = intval($_POST['id']);
        $remarks = trim($_POST['remarks'] ?? '');

        // Update remarks only, keep current status
        $stmt = $conn->prepare("UPDATE milling SET remarks=? WHERE id=?");
        if (!$stmt) die("Prepare failed (save remarks): " . $conn->error);
        $stmt->bind_param("si", $remarks, $id);
        $stmt->execute();
        $stmt->close();

        header("Location: milling.php");
        exit();
    }
}

// === Fetch Milling Records ===
// Admin sees all; Operator sees only their own
$query = "SELECT m.*, u.name AS operator_name
          FROM milling m
          LEFT JOIN users u ON m.operator_user_id = u.user_id ";
if ($current_role === 'Operator') {
    $query .= " WHERE m.operator_user_id = '" . $conn->real_escape_string($current_user_id) . "' ";
}
$query .= " ORDER BY m.id DESC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Milling Management</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ========== keep your original styles exactly ========== */
body { margin:0; font-family:Arial, sans-serif; display:flex; background:#f8f9fa; height:100vh; overflow:hidden; }
.sidebar { width:230px; background:#e9e6d9; height:100vh; display:flex; flex-direction:column; justify-content:space-between; position:fixed; top:0; left:0; overflow-y:auto; }
.sidebar .profile { text-align:center; margin:20px 0; font-weight:bold; }
.sidebar .profile i { font-size:2rem; display:block; margin-bottom:5px; }
.sidebar .menu { list-style:none; padding:0; margin:0; flex:1; }
.sidebar .menu li { padding:12px 20px; cursor:pointer; display:flex; align-items:center; gap:10px; color:#333; }
.sidebar .menu li:hover, .sidebar .menu li.active { background:#333; color:#fff; }
.sidebar .menu a { text-decoration:none; color:inherit; display:flex; align-items:center; gap:10px; width:100%; }
.sidebar .logout { display:block; padding:15px 20px; border-top:1px solid #ccc; cursor:pointer; font-weight:bold; color:#333; text-decoration:none; }
.sidebar .logout:hover { background:#333; color:#fff; }
.main-content { flex:1; padding:20px; background:#fff; margin-left:230px; height:100vh; overflow-y:auto; }
.header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; }
.header h2 { margin:0; }
.user-info { display:flex; align-items:center; gap:10px; }
.form-section { margin-bottom:30px; padding:20px; background:#f3f3f3; border-radius:10px; }
.form-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap:15px; }
.form-grid label { font-weight:bold; display:block; margin-bottom:5px; }
.form-grid input, .form-grid select { width:100%; padding:6px; border:1px solid #ccc; border-radius:5px; }
.form-actions { grid-column:1/-1; display:flex; gap:15px; justify-content:left; margin-top:15px; }
table { width:100%; border-collapse:collapse; font-size:14px; }
table th, table td { border:1px solid #ccc; padding:10px; text-align:center; vertical-align:middle; }
table th { background:#f3f3f3; }
table tr:hover { background:#f9f9f9; }
.btn { padding:6px 16px; background:#6a7a48; color:#fff; border:none; border-radius:5px; cursor:pointer; font-size:14px; }
.btn:hover { background:#4f5a32; }
.btn-approve { background:#27ae60; }
.btn-reject { background:#c0392b; }
.btn-edit { background:#f39c12; color:#000; }
.btn-delete { background:#a93226; }
.modal { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index:1000; }
.modal-content { background:#fff; padding:20px; border-radius:10px; width:420px; max-width:90%; }
.modal-content h3 { margin-top:0; }
.modal-content .form-grid { grid-template-columns:1fr; }
.search-container { display:flex; gap:10px; align-items:center; margin-bottom:15px; flex-wrap:wrap; }
.search-box { padding:6px; border:1px solid #ccc; border-radius:5px; }
/* status colors */
.status-Pending { color:#f39c12; font-weight:bold; }
.status-Approved { color:#27ae60; font-weight:bold; }
.status-Rejected { color:#c0392b; font-weight:bold; }
</style>
</head>
<body>
<?php

if ($current_role == 'Admin') {
?>
<aside class="sidebar">
  <div>
    <div class="profile">
      <i class="fa-solid fa-user-circle"></i>
      <span><?= htmlspecialchars($current_role) ?></span>
    </div>
    <ul class="menu">
      <li><a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
    <li><a href="pos.php"><i class="fa-solid fa-dollar"></i> POS</a></li>
      <li><a href="usermanagement.php"><i class="fa-solid fa-users"></i> User Management</a></li>
      <li><a href="buyingpalay.php"><i class="fa-solid fa-wheat-awn"></i> Buying Palay</a></li>
      <li class="active"><a href="milling.php"><i class="fa-solid fa-industry"></i> Milling Management</a></li>
      <li><a href="products.php"><i class="fa-solid fa-box"></i> Products & Inventory</a></li>
      <li><a href="reports.php"><i class="fa-solid fa-file-lines"></i> Reports</a></li>
      <li><a href="blockchain.php"><i class="fa-solid fa-link"></i> Blockchain Log</a></li>
    </ul>
  </div>
  <div class="logout" onclick="window.location.href='logout.php'">
    <i class="fa-solid fa-right-from-bracket"></i> Logout
  </div>
</aside>
<?php
}else{
?>
<aside class="sidebar">
  <div>
    <div class="profile">
      <i class="fa-solid fa-user-circle"></i>
      <span><?= htmlspecialchars($current_role) ?></span>
    </div>
    <ul class="menu">
      <li class="active"><a href="milling.php"><i class="fa-solid fa-industry"></i> Milling Management</a></li>

  </div>
  <div class="logout" onclick="window.location.href='logout.php'">
    <i class="fa-solid fa-right-from-bracket"></i> Logout
  </div>
</aside>
<?php } ?>
<main class="main-content">
<header class="header">

  <h2>MILLING MANAGEMENT</h2>

  <div class="user-info"><span><?= htmlspecialchars($current_user_name) ?></span></div>

</header>

<!-- Add Milling Form -->
<section class="form-section">
<div style="margin-bottom:20px; font-weight:bold; font-size:16px; color:#34495e;">
  Total Palay Quantity: <?= number_format($palay_total, 2) ?> kg
</div>
  <h3 style="color:#6a7a48; margin-bottom:20px;">Add Milling Record</h3>
  <form method="POST" onsubmit="return validateMillingForm(this);">

    <div class="form-grid">

      <div>
        <label>Rice Type</label>
        <select name="rice_type" required>
          <option value="">Select Rice Type</option>
          <?php foreach ($rice_types as $rt): ?>
            <option value="<?= htmlspecialchars($rt['type_name']) ?>"><?= htmlspecialchars($rt['type_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Date</label>
        <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
      </div>
      <div>
        <label>Quantity (kg)</label>
        <input type="number" name="quantity" id="add-quantity" step="0.01" required>
      </div>
      <div>
        <label>Milled Output (kg)</label>
        <input type="number" name="milled_output" id="add-output" step="0.01" required>
      </div>

      <!-- Operator select visible to Admin; for Operator we will use session id -->
      <div>
        <label>Operator</label>
        <select name="operator_user_id" id="add-operator" <?= $current_role === 'Operator' ? 'disabled' : '' ?> required>
          <option value="">Select Operator</option>
          <?php foreach ($operators as $op): ?>
            <option value="<?= $op['user_id'] ?>"><?= htmlspecialchars($op['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($current_role === 'Operator'): // keep a hidden input so form posts something for any JS that expects it ?>
          <input type="hidden" name="operator_user_id" value="<?= htmlspecialchars($current_user_id) ?>">
        <?php endif; ?>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" name="add" class="btn">Save</button>
      <button type="reset" class="btn" style="background:#888;">Reset</button>
      <button type="button" id="manageRiceBtn" class="btn" style="background:#3498db;">Manage Rice Types</button>
    </div>
  </form>
</section>

<!-- Search and Filter -->
<div class="search-container">
  <input type="text" id="searchInput" placeholder="Search..." class="search-box">

  <!-- Filter Rice Type -->
  <select id="filterRiceType" class="search-box" style="max-width:200px;">
    <option value="">All Rice Types</option>
    <?php foreach ($rice_types as $rt): ?>
      <option value="<?= htmlspecialchars($rt['type_name']) ?>"><?= htmlspecialchars($rt['type_name']) ?></option>
    <?php endforeach; ?>
  </select>

  <!-- Filter Operator -->
  <select id="filterOperator" class="search-box" style="max-width:200px;">
    <option value="">All Operators</option>
    <?php foreach ($operators as $op): ?>
      <option value="<?= htmlspecialchars($op['name']) ?>"><?= htmlspecialchars($op['name']) ?></option>
    <?php endforeach; ?>
  </select>
</div>

<!-- Manage Rice Types Modal -->
<div class="modal" id="riceModal">
  <div class="modal-content">
    <h3>Manage Rice Types</h3>
    <form method="POST">
      <div class="form-grid">
        <div>
          <label>New Rice Type</label>
          <input type="text" name="type_name" required>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" name="add_rice_type" class="btn">Add</button>
        <button type="button" class="btn" style="background:#888;" id="closeRice">Close</button>
      </div>
    </form>

    <h4>Existing Types</h4>
    <ul style="list-style:none; padding:0;">
      <?php foreach ($rice_types as $rt): ?>
        <li style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; padding:6px 10px; background:#f7f7f7; border-radius:5px;">
          <span><?= htmlspecialchars($rt['type_name']) ?></span>
          <form method="POST" style="margin:0;">
            <input type="hidden" name="type_name" value="<?= htmlspecialchars($rt['type_name']) ?>">
            <button type="submit" name="delete_rice_type" class="btn" style="background:#c0392b; padding:4px 10px; font-size:12px;">Delete</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>

<!-- Milling Records Table -->
<h3>Milling Records</h3>
<table id="millingTable">
<thead>
<tr>
  <th>ID</th>
  <th>Rice Type</th>
  <th>Date</th>
  <th>Quantity</th>
  <th>Milled Output</th>
  <th>Operator</th>
  <th>Status</th>
  <th>Remarks</th>
  <th>Actions</th>
</tr>
</thead>
<tbody>
<?php while ($row = $result->fetch_assoc()): 
    // for checks
    $is_mine = ($row['operator_user_id'] == $current_user_id); // loose compare OK
    $status = $row['status'] ?? 'Pending';
?>
<tr>
  <td><?= $row['id'] ?></td>
  <td><?= htmlspecialchars($row['rice_name'] ?? 'N/A') ?></td>
  <td><?= $row['date'] ?></td>
  <td><?= number_format($row['quantity'], 2) ?></td>
  <td><?= number_format($row['milled_output'], 2) ?></td>
  <td><?= htmlspecialchars($row['operator_name'] ?? 'N/A') ?></td>
  <td class="status-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></td>
  <td><?= htmlspecialchars($row['remarks'] ?? '') ?></td>
  <td>
    <?php if ($current_role === 'Admin'): ?>
      <!-- Admin buttons: Approve / Reject (with remarks modal) / Edit (open modal) / Delete -->
      <?php if ($status === 'Pending'): ?>
    <form method="POST" style="display:inline-block; margin-right:6px;">
      <input type="hidden" name="id" value="<?= $row['id'] ?>">
      <button type="submit" name="approve" class="btn btn-approve">Approve</button>
    </form>
    <button class="btn btn-reject" onclick="openRejectModal(<?= $row['id'] ?>)">Reject</button>
    <?php elseif ($status === 'Approved'): ?>
    <button class="btn btn-approve" disabled>Approved</button>
    <button class="btn" onclick="openRemarksModal(<?= $row['id'] ?>, <?= json_encode($row['remarks'] ?? '') ?>)">Edit Remarks</button>
    <?php else: ?>
    <button class="btn btn-reject" disabled>Rejected</button>
    <button class="btn" onclick="openRemarksModal(<?= $row['id'] ?>, <?= json_encode($row['remarks'] ?? '') ?>)">Edit Remarks</button>
    <?php endif; ?>

      <!-- Edit button (admin can edit any) -->
      <button class="btn btn-edit edit-btn" 
        data-id="<?= $row['id'] ?>"
        data-rice="<?= htmlspecialchars($row['rice_name']) ?>"
        data-date="<?= $row['date'] ?>"
        data-quantity="<?= $row['quantity'] ?>"
        data-output="<?= $row['milled_output'] ?>"
        data-operator="<?= htmlspecialchars($row['operator_user_id']) ?>">
        Edit
      </button>

      <!-- Delete (admin) -->
  

    <?php elseif ($current_role === 'Operator'): ?>
      <!-- Operator: can edit/delete only if own record and status is Pending -->
      <?php if ($is_mine && $status === 'Pending'): ?>
        <button class="btn btn-edit edit-btn"
          data-id="<?= $row['id'] ?>"
          data-rice="<?= htmlspecialchars($row['rice_name']) ?>"
          data-date="<?= $row['date'] ?>"
          data-quantity="<?= $row['quantity'] ?>"
          data-output="<?= $row['milled_output'] ?>"
          data-operator="<?= htmlspecialchars($row['operator_user_id']) ?>">
          Edit
        </button>

        <form method="POST" style="display:inline-block; margin-left:6px;">
          <input type="hidden" name="id" value="<?= $row['id'] ?>">
          <button type="submit" name="delete" class="btn btn-delete" onclick="return confirm('Delete this record?')">Delete</button>
        </form>
      <?php else: ?>
        <span style="color:#777;">-</span>
      <?php endif; ?>
    <?php endif; ?>
  </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</main>

<!-- Edit Milling Modal -->
<div class="modal" id="editModal">
  <div class="modal-content">
    <h3>Edit Milling</h3>
    <form method="POST" onsubmit="return validateMillingForm(this);">
      <input type="hidden" name="id" id="edit-id">
      <div class="form-grid">
        <div>
          <label>Rice Type</label>
          <select name="rice_type" id="edit-rice" required>
            <option value="">Select Rice Type</option>
            <?php foreach ($rice_types as $rt): ?>
              <option value="<?= htmlspecialchars($rt['type_name']) ?>"><?= htmlspecialchars($rt['type_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Date</label>
          <input type="date" name="date" id="edit-date" required>
        </div>
        <div>
          <label>Quantity (kg)</label>
          <input type="number" name="quantity" id="editModal-quantity" step="0.01" required>
        </div>
        <div>
          <label>Milled Output (kg)</label>
          <input type="number" name="milled_output" id="editModal-output" step="0.01" required>
        </div>
        <div>
          <label>Operator</label>
          <select name="operator_user_id" id="editModal-operator" <?= $current_role === 'Operator' ? 'disabled' : '' ?> required>
            <option value="">Select Operator</option>
            <?php foreach ($operators as $op): ?>
              <option value="<?= $op['user_id'] ?>"><?= htmlspecialchars($op['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($current_role === 'Operator'): // keep hidden so admin edit handles operator field but operator doesn't change it ?>
            <input type="hidden" name="operator_user_id" id="editModal-operator-hidden" value="<?= htmlspecialchars($current_user_id) ?>">
          <?php endif; ?>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" name="edit" class="btn">Update</button>
        <button type="button" class="btn" style="background:#888;" id="closeEdit">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Error Message Modal -->
<div class="modal" id="errorModal">
  <div class="modal-content" style="text-align:center;">
    <h3 style="color:#c0392b;">Action Not Allowed</h3>
    <p id="errorMessage" style="margin:15px 0; font-size:15px;"></p>
    <button class="btn" id="closeError" style="background:#c0392b;">OK</button>
  </div>
</div>

<!-- Reject Remarks Modal (for Admin reject with remark) -->
<div class="modal" id="rejectModal">
  <div class="modal-content">
    <h3>Reject Record</h3>
    <form method="POST">
      <input type="hidden" name="id" id="reject-id">
      <div class="form-grid">
        <div>
          <label>Remarks / Reason</label>
          <textarea name="remarks" id="reject-remarks" rows="4" style="width:100%; padding:6px; border:1px solid #ccc; border-radius:5px;" required></textarea>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" name="reject_confirm" class="btn btn-reject">Reject</button>
        <button type="button" class="btn" style="background:#888;" id="closeReject">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Remarks Modal (Admin can edit remarks) -->
<div class="modal" id="remarksModal">
  <div class="modal-content">
    <h3>Edit Remarks</h3>
    <form method="POST">
      <input type="hidden" name="id" id="remarks-id">
      <div class="form-grid">
        <div>
          <label>Remarks</label>
          <textarea name="remarks" id="remarks-text" rows="4" style="width:100%; padding:6px; border:1px solid #ccc; border-radius:5px;"></textarea>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" name="save_remarks" class="btn" style="background:#6a7a48;">Save</button>
        <button type="button" class="btn" style="background:#888;" id="closeRemarks">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
// === Edit Modal ===
const editModal = document.getElementById("editModal");
const closeEdit = document.getElementById("closeEdit");
document.querySelectorAll(".edit-btn").forEach(btn => {
  btn.addEventListener("click", () => {
    document.getElementById("edit-id").value = btn.dataset.id;
    document.getElementById("edit-rice").value = btn.dataset.rice;
    document.getElementById("edit-date").value = btn.dataset.date;
    document.getElementById("editModal-quantity").value = Number(btn.dataset.quantity).toFixed(2);
    document.getElementById("editModal-output").value = Number(btn.dataset.output).toFixed(2);

    const opSelect = document.getElementById("editModal-operator");
    const opHidden = document.getElementById("editModal-operator-hidden");
    if (opSelect) {
      const opVal = btn.dataset.operator || '';
      if (opVal) opSelect.value = opVal;
    }
    if (opHidden) opHidden.value = btn.dataset.operator;

    document.getElementById("editModal").style.display = "flex";
  });
});

closeEdit.onclick = () => editModal.style.display = "none";

// === Manage Rice Modal ===
const riceModal = document.getElementById("riceModal");
document.getElementById("manageRiceBtn").onclick = () => riceModal.style.display = "flex";
document.getElementById("closeRice").onclick = () => riceModal.style.display = "none";

// === Reject modal ===
const rejectModal = document.getElementById("rejectModal");
const closeReject = document.getElementById("closeReject");
function openRejectModal(id) {
  document.getElementById('reject-id').value = id;
  document.getElementById('reject-remarks').value = '';
  rejectModal.style.display = 'flex';
}
closeReject.onclick = () => rejectModal.style.display = "none";

// === Edit remarks modal ===
const remarksModal = document.getElementById("remarksModal");
const closeRemarks = document.getElementById("closeRemarks");
function openRemarksModal(id, text) {
  document.getElementById('remarks-id').value = id;
  document.getElementById('remarks-text').value = text || '';
  remarksModal.style.display = 'flex';
}
closeRemarks.onclick = () => remarksModal.style.display = "none";

// === Close modals by clicking outside ===
window.onclick = e => {
  if (e.target === editModal) editModal.style.display = "none";
  if (e.target === riceModal) riceModal.style.display = "none";
  if (e.target === rejectModal) rejectModal.style.display = "none";
  if (e.target === remarksModal) remarksModal.style.display = "none";
}

// === Search + Filter ===
document.getElementById("searchInput").addEventListener("keyup", filterTable);
document.getElementById("filterRiceType").addEventListener("change", filterTable);
document.getElementById("filterOperator").addEventListener("change", filterTable);

function filterTable() {
  const searchValue = document.getElementById("searchInput").value.toLowerCase();
  const filterRice = document.getElementById("filterRiceType").value.toLowerCase();
  const filterOperator = document.getElementById("filterOperator").value.toLowerCase();

  document.querySelectorAll("#millingTable tbody tr").forEach(row => {
    const text = row.innerText.toLowerCase();
    const rice = row.cells[1].innerText.toLowerCase();
    const operator = row.cells[5].innerText.toLowerCase();

    const matchesSearch = text.includes(searchValue);
    const matchesRice = filterRice === "" || rice === filterRice;
    const matchesOperator = filterOperator === "" || operator === filterOperator;

    row.style.display = (matchesSearch && matchesRice && matchesOperator) ? "" : "none";
  });
}

// === Validation ===
function validateMillingForm(form) {
  const qty = parseFloat(form.querySelector("[name='quantity']").value) || 0;
  const output = parseFloat(form.querySelector("[name='milled_output']").value) || 0;
  const totalPalay = <?= $palay_total ?>; // PHP value injected

  if (output > qty) {
    alert("Milled Output cannot be greater than Quantity!");
    return false;
  }
  if (qty > totalPalay) {
    alert("Quantity cannot exceed total available Palay (" + totalPalay.toFixed(2) + " kg)!");
    return false;
  }
  return true;
}

// === Error Modal Handling ===
const errorModal = document.getElementById("errorModal");
const closeError = document.getElementById("closeError");

if (closeError) {
  closeError.onclick = () => errorModal.style.display = "none";
  window.onclick = e => { if (e.target === errorModal) errorModal.style.display = "none"; };
}

<?php if (isset($_SESSION['error_message'])): ?>
  document.getElementById("errorMessage").textContent = "<?= addslashes($_SESSION['error_message']) ?>";
  errorModal.style.display = "flex";
  <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>
</script>
</body>
</html>