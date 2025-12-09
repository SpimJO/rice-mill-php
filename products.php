<?php 
include "db.php"; 
include "blockchain_api.php"; // Hyperledger Fabric API helper
session_start();
date_default_timezone_set('Asia/Manila'); 

function add_blockchain_log($user_id, $action, $target_user, $data) {
    // Use Hyperledger Fabric API with database fallback
    $dataArray = is_string($data) ? json_decode($data, true) : $data;
    if ($dataArray === null && is_string($data)) {
        $dataArray = $data; // Use as-is if not JSON
    }
    addBlockchainLogWithFallback($GLOBALS['conn'], $user_id, $action, $target_user, $dataArray);
}

// === Require login === 
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$current_user_id = intval($_SESSION['user_id']);
$current_user_name = $_SESSION['name'] ?? '';
$current_role = $_SESSION['role'] ?? 'Operator';

// === Only allow admin access === 
if ($current_role !== 'Admin') {
    $_SESSION['error_message'] = "Access denied. Admins only!";
    header('Location: dashboard.php');
    exit();
}

// === AJAX: Dashboard totals per rice type === 
if (isset($_GET['action']) && $_GET['action'] === 'get_totals') {
    $rice_type = $_GET['rice_type'] ?? '';
    if ($rice_type !== '') {
        $stmt = $conn->prepare("SELECT total_25kg, total_50kg, total_5kg FROM inventory WHERE rice_type=?");
        $stmt->bind_param("s", $rice_type);
        $stmt->execute();
        $stmt->bind_result($t25, $t50, $t5);
        $stmt->fetch();
        $stmt->close();

        echo json_encode([
            'total_25kg' => intval($t25),
            'total_50kg' => intval($t50),
            'total_5kg'  => intval($t5)
        ]);
    } else {
        echo json_encode(['total_25kg'=>0, 'total_50kg'=>0, 'total_5kg'=>0]);
    }
    exit();
}
// === Handle inventory update via AJAX (increment/decrement) ===
if (isset($_POST['update_inventory'])) {
    $rice_type = trim($_POST['rice_type'] ?? '');
    $delta_25 = intval($_POST['total_25kg']); // can be negative
    $delta_50 = intval($_POST['total_50kg']); // can be negative
    $delta_5  = intval($_POST['total_5kg']);  // can be negative
    $price_25 = floatval($_POST['price_25kg']);
    $price_50 = floatval($_POST['price_50kg']);
    $price_5  = floatval($_POST['price_5kg']);
    $reason   = trim($_POST['reason'] ?? 'Manual Adjustment');

    $conn->begin_transaction();
    try {
        // 1️⃣ Fetch current totals
        $stmt = $conn->prepare("SELECT total_25kg, total_50kg, total_5kg FROM inventory WHERE rice_type=?");
        $stmt->bind_param("s", $rice_type);
        $stmt->execute();
        $stmt->bind_result($current_25, $current_50, $current_5);
        $stmt->fetch();
        $stmt->close();

// User entered final totals — not additions
        $new_25 = max(0, intval($_POST['total_25kg']));
        $new_50 = max(0, intval($_POST['total_50kg']));
        $new_5  = max(0, intval($_POST['total_5kg']));
        $total_kg = $new_25*25 + $new_50*50 + $new_5*5;

        // 3️⃣ Update inventory
        $stmtUpdate = $conn->prepare("
            UPDATE inventory
            SET total_25kg=?, total_50kg=?, total_5kg=?, total_kg=?,
                price_25kg=?, price_50kg=?, price_5kg=?, updated_at=NOW()
            WHERE rice_type=?
        ");
        $stmtUpdate->bind_param("iiidddds", $new_25, $new_50, $new_5, $total_kg,
                                $price_25, $price_50, $price_5, $rice_type);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        // 4️⃣ Log adjustment
        $stmtLog = $conn->prepare("
            INSERT INTO inventory_adjustments_log
            (rice_type, adjusted_25kg, adjusted_50kg, adjusted_5kg,
             price_25kg, price_50kg, price_5kg, reason, operator_user_id, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,NOW())
        ");
        $stmtLog->bind_param("siiidddsi", $rice_type, $delta_25, $delta_50, $delta_5,
                             $price_25, $price_50, $price_5, $reason, $current_user_id);
        $stmtLog->execute();
        $stmtLog->close();

        $conn->commit();
        // 🧾 Blockchain log for inventory update
$log_data = json_encode([
    'rice_type' => $rice_type,
    'new_25kg' => $new_25,
    'new_50kg' => $new_50,
    'new_5kg'  => $new_5,
    'price_25kg' => $price_25,
    'price_50kg' => $price_50,
    'price_5kg'  => $price_5,
    'reason' => $reason
]);
add_blockchain_log($current_user_id, 'Inventory Update', $current_user_name, $log_data);

        echo json_encode([
            'success' => true,
            'total_25kg' => $new_25,
            'total_50kg' => $new_50,
            'total_5kg'  => $new_5,
            'price_25kg' => $price_25,
            'price_50kg' => $price_50,
            'price_5kg'  => $price_5
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// === Load rice types ===
$rice_types = [];
$res = $conn->query("SELECT type_name, total_quantity_kg FROM rice_types ORDER BY type_name ASC");
while ($r = $res->fetch_assoc()) $rice_types[] = $r;

// === Handle Add Product ===
// === Handle Add Product ===
if (isset($_POST['add_product'])) {
    $rice_type = trim($_POST['rice_type']);
    $s25 = intval($_POST['sack_25kg']);
    $s50 = intval($_POST['sack_50kg']);
    $s5  = intval($_POST['sack_5kg']);
    $price_25 = floatval($_POST['price_25kg'] ?? 0);
    $price_50 = floatval($_POST['price_50kg'] ?? 0);
    $price_5  = floatval($_POST['price_5kg'] ?? 0);

    $conn->begin_transaction();
    try {
        // 1️⃣ Insert into products table
        $total_kg = ($s25 * 25) + ($s50 * 50) + ($s5 * 5);
        $stmt = $conn->prepare("
            INSERT INTO products (rice_type, sack_25kg, sack_50kg, sack_5kg, total_kg, created_at, operator_user_id)
            VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->bind_param("siiidi", $rice_type, $s25, $s50, $s5, $total_kg, $current_user_id);
        $stmt->execute();
        $stmt->close();

        // 2️⃣ Check if inventory row exists
        $stmtCheck = $conn->prepare("SELECT total_25kg, total_50kg, total_5kg FROM inventory WHERE rice_type=?");
        $stmtCheck->bind_param("s", $rice_type);
        $stmtCheck->execute();
        $stmtCheck->bind_result($current_25, $current_50, $current_5);

        if ($stmtCheck->fetch()) {
            // ✅ Inventory exists
            $stmtCheck->close();

            // 3️⃣ Update totals
            $new_25 = $current_25 + $s25;
            $new_50 = $current_50 + $s50;
            $new_5  = $current_5 + $s5;
            $total_kg_updated = ($new_25 * 25) + ($new_50 * 50) + ($new_5 * 5);

            $stmtUpdate = $conn->prepare("
                UPDATE inventory
                SET total_25kg=?, total_50kg=?, total_5kg=?, total_kg=?,
                    price_25kg=?, price_50kg=?, price_5kg=?, updated_at=NOW()
                WHERE rice_type=?
            ");
            $stmtUpdate->bind_param("iiidddds", 
                $new_25, $new_50, $new_5, $total_kg_updated, 
                $price_25, $price_50, $price_5, $rice_type
            );
            $stmtUpdate->execute();
            $stmtUpdate->close();
        } else {
            // ✅ No inventory row yet → insert new
            $stmtCheck->close();
            $stmtInsert = $conn->prepare("
                INSERT INTO inventory 
                (rice_type, total_25kg, total_50kg, total_5kg, total_kg, 
                 price_25kg, price_50kg, price_5kg, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtInsert->bind_param("siiidddd",
                $rice_type, $s25, $s50, $s5, $total_kg,
                $price_25, $price_50, $price_5
            );
            $stmtInsert->execute();
            $stmtInsert->close();
        }

        // 4️⃣ Deduct from rice_types
        $total_used_kg = ($s25 * 25) + ($s50 * 50) + ($s5 * 5);
        $stmtDeduct = $conn->prepare("
            UPDATE rice_types 
            SET total_quantity_kg = GREATEST(total_quantity_kg - ?, 0)
            WHERE type_name = ?
        ");
        $stmtDeduct->bind_param("ds", $total_used_kg, $rice_type);
        $stmtDeduct->execute();
        $stmtDeduct->close();

        // 5️⃣ Commit
        $conn->commit();

        // 🧾 Blockchain log
        $log_data = json_encode([
            'rice_type' => $rice_type,
            'sack_25kg' => $s25,
            'sack_50kg' => $s50,
            'sack_5kg'  => $s5,
            'price_25kg' => $price_25,
            'price_50kg' => $price_50,
            'price_5kg'  => $price_5,
            'deducted_from_rice_types' => $total_used_kg
        ]);
        add_blockchain_log($current_user_id, 'Product Added', $current_user_name, $log_data);

        $_SESSION['success_message'] = "✅ Product added successfully! Inventory updated and total available rice deducted.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "❌ Error adding product: " . $e->getMessage();
    }

    header("Location: products.php");
    exit();
}

// === Fetch inventory dynamically ===
$inventory_result = $conn->query("
    SELECT i.rice_type, i.total_25kg, i.price_25kg, i.total_50kg, i.price_50kg, i.total_5kg, i.price_5kg, i.total_kg, i.updated_at 
    FROM inventory i 
    ORDER BY i.rice_type ASC
");

// === Fetch products list ===
$prod_result = $conn->query("SELECT * FROM products ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Products & Inventory</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* === CSS Styles === */
body {margin:0; font-family:Arial,sans-serif; display:flex; background:#f8f9fa; min-height:100vh;}
.sidebar {width:230px; background:#e9e6d9; display:flex; flex-direction:column; justify-content:space-between; position:fixed; top:0; left:0; bottom:0; overflow-y:auto;}
.sidebar .profile {text-align:center; margin:20px 0; font-weight:bold;}
.sidebar .profile i {font-size:2rem; display:block; margin-bottom:5px;}
.sidebar .menu {list-style:none; padding:0; margin:0; flex:1;}
.sidebar .menu li {padding:12px 20px; cursor:pointer; display:flex; align-items:center; gap:10px; color:#333;}
.sidebar .menu li:hover, .sidebar .menu li.active {background:#333; color:#fff;}
.sidebar .menu a {text-decoration:none; color:inherit; display:flex; align-items:center; gap:10px; width:100%;}
.sidebar .logout {display:block; padding:15px 20px; border-top:1px solid #ccc; cursor:pointer; font-weight:bold; color:#333; text-decoration:none;}
.sidebar .logout:hover {background:#333; color:#fff;}
.main-content {flex:1; padding:20px; margin-left:230px; min-height:100vh;}
.header {display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap;}
.header h2 {margin:0;}
.user-info {display:flex; align-items:center; gap:10px;}
.section-container {background:#f3f3f3; padding:20px; border-radius:10px; margin-bottom:30px;}
.form-grid {display:grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap:15px;}
.form-grid label {font-weight:bold; margin-bottom:5px; display:block;}
.form-grid input, .form-grid select {width:100%; padding:6px; border:1px solid #ccc; border-radius:5px;}
.form-actions {margin-top:15px; display:flex; gap:15px;}
.progress-container {margin-top:10px; background:#e0e0e0; border-radius:6px; height:24px; width:100%; overflow:hidden;}
.progress-fill {height:100%; width:0%; text-align:center; color:#fff; font-weight:bold; line-height:24px; transition:width 0.3s ease, background 0.3s ease;}
table {width:100%; border-collapse:collapse; margin-bottom:30px; font-size:14px;}
table th, table td {border:1px solid #ccc; padding:10px; text-align:center;}
table th {background:#ddd;}
table tr:hover {background:#f9f9f9;}
.btn {padding:6px 16px; background:#6a7a48; color:#fff; border:none; border-radius:5px; cursor:pointer; font-size:14px;}
.btn:hover {background:#4f5a32;}
.btn:disabled {background:#aaa; cursor:not-allowed;}
.dashboard-controls {margin-bottom:15px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;}
.dashboard-controls label {font-weight:bold;}
.dashboard-controls select {padding:6px 10px; border:1px solid #ccc; border-radius:5px; min-width:180px;}
.dashboard-cards {margin-top:10px; display:flex; gap:20px; flex-wrap:wrap;}
.card {flex:1; min-width:150px; background:#fff; border-radius:12px; display:flex; align-items:center; padding:15px; box-shadow:0 4px 10px rgba(0,0,0,0.08);}
.card-icon {font-size:2rem; color:#fff; width:60px; height:60px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-right:15px;}
.card-25kg .card-icon {background:#28a745;}
.card-50kg .card-icon {background:#fd7e14;}
.card-5kg .card-icon {background:#dc3545;}
.card-info h4 {margin:0; font-size:1rem; color:#555;}
.card-info p {margin:5px 0 0; font-size:1.2rem; font-weight:600; color:#222;}
@media(max-width:768px) {.dashboard-cards {flex-direction:column;} .form-grid {grid-template-columns:1fr;}}
/* Modal */
#editModal {display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center;}
#editModal .modal-content {background:#fff; padding:20px; border-radius:10px; width:400px; position:relative;}
#editModal .modal-content label {display:block; margin:8px 0;}
#editModal .modal-content input, #editModal .modal-content textarea {width:100%; padding:6px; border:1px solid #ccc; border-radius:5px;}
</style>
</head>
<body>
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
<li><a href="milling.php"><i class="fa-solid fa-industry"></i> Milling Management</a></li>
<li class="active"><a href="products.php"><i class="fa-solid fa-box"></i> Products & Inventory</a></li>
<li><a href="reports.php"><i class="fa-solid fa-file-lines"></i> Reports</a></li>
<li><a href="blockchain.php"><i class="fa-solid fa-link"></i> Blockchain Log</a></li>
</ul>
</div>
<div class="logout" onclick="window.location.href='logout.php'">
<i class="fa-solid fa-right-from-bracket"></i> Logout
</div>
</aside>

<main class="main-content">
<header class="header">
<h2>PRODUCTS & INVENTORY</h2>
<div class="user-info"><span><?= htmlspecialchars($current_user_name) ?></span></div>
</header>

<section class="section-container">
<h3>Inventory Dashboard</h3>
<div class="dashboard-controls">
<label for="dashboard_rice_type">Select Rice Type:</label>
<select id="dashboard_rice_type">
<option value="">-- Select Rice Type --</option>
<?php foreach ($rice_types as $rt): ?>
<option value="<?= htmlspecialchars($rt['type_name']) ?>" data-stock="<?= $rt['total_quantity_kg'] ?>">
<?= htmlspecialchars($rt['type_name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="dashboard-cards">
<div class="card card-25kg">
<div class="card-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
<div class="card-info">
<h4>25kg Sacks</h4>
<p id="dash_25kg">0</p>
</div>
</div>
<div class="card card-50kg">
<div class="card-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
<div class="card-info">
<h4>50kg Sacks</h4>
<p id="dash_50kg">0</p>
</div>
</div>
<div class="card card-5kg">
<div class="card-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
<div class="card-info">
<h4>5kg Sacks</h4>
<p id="dash_5kg">0</p>
</div>
</div>
</div>
</section>

<section class="section-container">
<h3>Add Product</h3>
<form method="POST" id="addProductForm">
<div class="form-grid">
<div>
<label>Rice Type</label>
<select name="rice_type" id="rice_type" required>
<option value="">Select Rice Type</option>
<?php foreach ($rice_types as $rt): ?>
<option value="<?= htmlspecialchars($rt['type_name']) ?>" data-stock="<?= $rt['total_quantity_kg'] ?>">
<?= htmlspecialchars($rt['type_name']) ?> (<?= number_format($rt['total_quantity_kg'],2) ?> kg)
</option>
<?php endforeach; ?>
</select>
</div>
<div><label>25kg Sacks</label><input type="number" name="sack_25kg" value="0" min="0"></div>
<div><label>50kg Sacks</label><input type="number" name="sack_50kg" value="0" min="0"></div>
<div><label>5kg Sacks</label><input type="number" name="sack_5kg" value="0" min="0"></div>
</div>
<div class="form-actions">
<button class="btn" type="submit" name="add_product" id="addProductBtn">Add Product</button>
</div>
<div class="progress-container">
<div id="progress_fill" class="progress-fill">0%</div>
</div>
<p>Remaining KG: <span id="remaining_kg">0</span></p>
</form>
</section>

<!-- Inventory Management Table -->
<section class="section-container">
<h3>Inventory Management</h3>
<table>
<thead>
<tr>
<th>Rice Type</th><th>Total 25kg</th><th>Price 25kg</th><th>Total 50kg</th><th>Price 50kg</th><th>Total 5kg</th><th>Price 5kg</th><th>Total KG</th><th>Last Updated</th><th>Action</th>
</tr>
</thead>
<tbody>
<?php while($row=$inventory_result->fetch_assoc()): ?>
<tr data-type="<?= htmlspecialchars($row['rice_type']) ?>">
<td><?= htmlspecialchars($row['rice_type']) ?></td>
<td><?= $row['total_25kg'] ?></td>
<td><?= $row['price_25kg'] ?></td>
<td><?= $row['total_50kg'] ?></td>
<td><?= $row['price_50kg'] ?></td>
<td><?= $row['total_5kg'] ?></td>
<td><?= $row['price_5kg'] ?></td>
<td><?= $row['total_kg'] ?></td>
<td><?= $row['updated_at'] ?></td>
<td><button class="btn edit-btn">Edit</button></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</section>

<!-- Products List Table -->
<section class="section-container">
<h3>Products List</h3>
<table>
<thead>
<tr>
<th>ID</th><th>Rice Type</th><th>25kg</th><th>50kg</th><th>5kg</th><th>Total KG</th><th>Operator</th><th>Date</th>
</tr>
</thead>
<tbody>
<?php while ($row = $prod_result->fetch_assoc()): ?>
<tr>
<td><?= $row['id'] ?></td>
<td><?= htmlspecialchars($row['rice_type']) ?></td>
<td><?= $row['sack_25kg'] ?></td>
<td><?= $row['sack_50kg'] ?></td>
<td><?= $row['sack_5kg'] ?></td>
<td><?= $row['total_kg'] ?></td>
<td><?= $row['operator_user_id'] ?></td>
<td><?= $row['created_at'] ?></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</section>

<!-- Modal for Editing Inventory -->
<div id="editModal">
<div class="modal-content">
<h3>Edit Inventory</h3>
<form id="modalForm">
<input type="hidden" name="rice_type" id="modal_rice_type">
<label>Total 25kg: <input type="number" name="total_25kg" id="modal_25kg"></label>
<label>Price 25kg: <input type="number" name="price_25kg" id="modal_price_25kg" step="0.01"></label>
<label>Total 50kg: <input type="number" name="total_50kg" id="modal_50kg"></label>
<label>Price 50kg: <input type="number" name="price_50kg" id="modal_price_50kg" step="0.01"></label>
<label>Total 5kg: <input type="number" name="total_5kg" id="modal_5kg"></label>
<label>Price 5kg: <input type="number" name="price_5kg" id="modal_price_5kg" step="0.01"></label>
<label>Reason for change: <textarea name="reason" id="modal_reason" required></textarea></label>
<br>
<button type="submit" class="btn">Save</button>
<button type="button" class="btn" id="closeModal">Cancel</button>
</form>
</div>
</div>

<script>
// Dashboard totals
document.getElementById('dashboard_rice_type').addEventListener('change', function(){
    const type = this.value;
    if(type===''){
        document.getElementById('dash_25kg').textContent='0';
        document.getElementById('dash_50kg').textContent='0';
        document.getElementById('dash_5kg').textContent='0';
        return;
    }
    fetch('products.php?action=get_totals&rice_type='+encodeURIComponent(type))
    .then(res=>res.json())
    .then(data=>{
        document.getElementById('dash_25kg').textContent=data.total_25kg;
        document.getElementById('dash_50kg').textContent=data.total_50kg;
        document.getElementById('dash_5kg').textContent=data.total_5kg;
    });
});

// Add Product Form: progress bar
const addForm = document.getElementById('addProductForm');
const progressFill = document.getElementById('progress_fill');
const remainingKg = document.getElementById('remaining_kg');
let maxStock = 0;

document.getElementById('rice_type').addEventListener('change', function() {
    const selected = this.selectedOptions[0];
    maxStock = parseFloat(selected.dataset.stock || 0);
    updateProgress();
});

function updateProgress() {
    const s25 = parseInt(addForm.sack_25kg.value||0);
    const s50 = parseInt(addForm.sack_50kg.value||0);
    const s5  = parseInt(addForm.sack_5kg.value||0);
    const enteredKg = s25*25 + s50*50 + s5*5;
    const percentage = maxStock > 0 ? Math.min((enteredKg / maxStock)*100, 100) : 0;
    progressFill.style.width = percentage + '%';
    progressFill.textContent = enteredKg + ' KG';
    remainingKg.textContent = Math.max(maxStock - enteredKg, 0);

    if(maxStock > 0 && enteredKg > maxStock){
        progressFill.style.background = '#dc3545';
        addForm.querySelector('#addProductBtn').disabled = true;
    } else {
        progressFill.style.background = '#28a745';
        addForm.querySelector('#addProductBtn').disabled = false;
    }
}

addForm.sack_25kg.addEventListener('input',updateProgress);
addForm.sack_50kg.addEventListener('input',updateProgress);
addForm.sack_5kg.addEventListener('input',updateProgress);
updateProgress();

// Modal
document.querySelectorAll('.edit-btn').forEach(btn=>{
    btn.addEventListener('click',()=>{
        const tr = btn.closest('tr');
        document.getElementById('modal_rice_type').value = tr.dataset.type;
        document.getElementById('modal_25kg').value = parseInt(tr.children[1].textContent.trim()) || 0;
        document.getElementById('modal_price_25kg').value = parseFloat(tr.children[2].textContent.trim()) || 0;
        document.getElementById('modal_50kg').value = parseInt(tr.children[3].textContent.trim()) || 0;
        document.getElementById('modal_price_50kg').value = parseFloat(tr.children[4].textContent.trim()) || 0;
        document.getElementById('modal_5kg').value = parseInt(tr.children[5].textContent.trim()) || 0;
        document.getElementById('modal_price_5kg').value = parseFloat(tr.children[6].textContent.trim()) || 0;
        document.getElementById('modal_reason').value='';
        document.getElementById('editModal').style.display='flex';
    });
});

document.getElementById('closeModal').addEventListener('click',()=>{document.getElementById('editModal').style.display='none';});

// Save modal via AJAX
document.getElementById('modalForm').addEventListener('submit', e => {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('update_inventory', 1);

    fetch('products.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const type = formData.get('rice_type');
            const row = document.querySelector(`tr[data-type="${type}"]`);
            if (row) {
                // Update row with new totals
                row.children[1].textContent = data.total_25kg;
                row.children[2].textContent = data.price_25kg.toFixed(2);
                row.children[3].textContent = data.total_50kg;
                row.children[4].textContent = data.price_50kg.toFixed(2);
                row.children[5].textContent = data.total_5kg;
                row.children[6].textContent = data.price_5kg.toFixed(2);
                row.children[7].textContent = 
                    data.total_25kg*25 + data.total_50kg*50 + data.total_5kg*5;
                row.children[8].textContent = new Date().toLocaleString();

                row.style.background = '#d4edda';
                setTimeout(() => { row.style.background = ''; }, 1000);

                // Update dashboard cards if selected rice type matches
                const selectedType = document.getElementById('dashboard_rice_type').value;
                if (selectedType === type) {
                    document.getElementById('dash_25kg').textContent = data.total_25kg;
                    document.getElementById('dash_50kg').textContent = data.total_50kg;
                    document.getElementById('dash_5kg').textContent = data.total_5kg;
                }
            }
            document.getElementById('editModal').style.display = 'none';
        } else {
            alert('Error: ' + (data.error || 'Could not update'));
        }
    });
});
</script>
</body>
</html>
