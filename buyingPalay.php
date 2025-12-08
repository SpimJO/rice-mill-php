<?php
include "db.php";
include "blockchain_api.php"; // Include Hyperledger Fabric API helper
session_start();
date_default_timezone_set('Asia/Manila'); 

// === Require login ===
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$current_user_id   = intval($_SESSION['user_id']);
$current_user_name = $_SESSION['name'] ?? '';
$current_role      = $_SESSION['role'] ?? 'Operator';

// === Only allow admin access ===
if ($current_role !== 'Admin') {
    $_SESSION['error_message'] = "Access denied. Admins only!";
    header('Location: dashboard.php');
    exit();
}

/* ============================
   🔗 Blockchain Logging Function (Hyperledger Fabric)
   ============================ */
function addBlockchainLog($conn, $user_id, $action, $target, $data) {
    // Use Hyperledger Fabric API with database fallback
    $dataArray = is_string($data) ? json_decode($data, true) : $data;
    if ($dataArray === null && is_string($data)) {
        $dataArray = $data; // Use as-is if not JSON
    }
    addBlockchainLogWithFallback($conn, $user_id, $action, $target, $dataArray);
}


/* ============================
   🟢 ADD PURCHASE
   ============================ */
if (isset($_POST['add'])) {
    $supplier = trim($_POST['supplier']);
    $quantity = floatval($_POST['quantity']);
    $price    = floatval($_POST['price']);
    $amount   = floatval($_POST['total_amount']);
    $date     = date('Y-m-d');
    $status   = 'Pending';
    $user_id  = $_SESSION['user_id'];

    if ($quantity <= 0 || $price <= 0) {
        die("Invalid quantity or price.");
    }

    $stmt = $conn->prepare("
        INSERT INTO palay_purchases (supplier, user_id, quantity, price, total_amount, purchase_date, payment_status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssdddss", $supplier, $user_id, $quantity, $price, $amount, $date, $status);
    $stmt->execute();

    // 🔗 Blockchain log
    $logData = json_encode([
        "supplier" => $supplier,
        "quantity" => $quantity,
        "price" => $price,
        "total_amount" => $amount,
        "date" => $date
    ]);
    addBlockchainLog($conn, $user_id, "Add Purchase", $supplier, $logData);

    header("Location: buyingpalay.php");
    exit;
}

/* ============================
   🔴 VOID PURCHASE
   ============================ */
if (isset($_POST['void'])) {
    $id = intval($_POST['id']);

    $get = $conn->prepare("SELECT supplier, quantity, price FROM palay_purchases WHERE id=?");
    $get->bind_param("i", $id);
    $get->execute();
    $info = $get->get_result()->fetch_assoc();

    $stmt = $conn->prepare("
        UPDATE palay_purchases 
        SET payment_status='Void' 
        WHERE id=? AND payment_status='Pending'
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $logData = json_encode([
        "purchase_id" => $id,
        "supplier" => $info['supplier'] ?? '',
        "quantity" => $info['quantity'] ?? '',
        "price" => $info['price'] ?? '',
        "status" => "Voided"
    ]);
    addBlockchainLog($conn, $current_user_id, "Void Purchase", "Purchase ID: $id", $logData);

    header("Location: buyingpalay.php");
    exit;
}

/* ============================
   🟡 MARK AS PAID
   ============================ */
if (isset($_POST['paid'])) {
    $id = intval($_POST['id']);

    $stmt = $conn->prepare("
        SELECT supplier, quantity, price 
        FROM palay_purchases 
        WHERE id=? AND payment_status='Pending'
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $purchase = $res->fetch_assoc();
        $quantity = floatval($purchase['quantity']);

        $conn->begin_transaction();
        try {
            $upd = $conn->prepare("
                UPDATE palay_purchases 
                SET payment_status='Paid' 
                WHERE id=? AND payment_status='Pending'
            ");
            $upd->bind_param("i", $id);
            $upd->execute();

            $check = $conn->query("SELECT palay_quantity FROM palay_milling_process LIMIT 1");
            if ($check->num_rows > 0) {
                $row = $check->fetch_assoc();
                $new_quantity = $row['palay_quantity'] + $quantity;
                $conn->query("UPDATE palay_milling_process SET palay_quantity=$new_quantity");
            } else {
                $conn->query("
                    INSERT INTO palay_milling_process (palay_quantity, added_by, added_date)
                    VALUES ($quantity, {$_SESSION['user_id']}, NOW())
                ");
            }

            $conn->commit();

            $logData = json_encode([
                "purchase_id" => $id,
                "supplier" => $purchase['supplier'],
                "quantity" => $quantity,
                "price" => $purchase['price'],
                "status" => "Paid"
            ]);
            addBlockchainLog($conn, $current_user_id, "Mark as Paid", "Purchase ID: $id", $logData);

        } catch (Exception $e) {
            $conn->rollback();
            die("Error updating milling process: " . $e->getMessage());
        }
    }

    header("Location: buyingpalay.php");
    exit;
}

/* ============================
   📊 TOTAL PALAY QUANTITY
   ============================ */
$total_palay = 0;
$palay_res = $conn->query("SELECT palay_quantity FROM palay_milling_process LIMIT 1");
if ($palay_res && $palay_res->num_rows > 0) {
    $row = $palay_res->fetch_assoc();
    $total_palay = floatval($row['palay_quantity']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Buying Palay</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<style>
/* === Your original styling preserved === */
body { margin:0; font-family:Arial,sans-serif; display:flex; background:#f8f9fa; height:100vh; overflow:hidden; }
.sidebar { width:230px; background:#e9e6d9; height:100vh; display:flex; flex-direction:column; justify-content:space-between; flex-shrink:0; position:fixed; left:0; top:0; overflow-y:auto; }
.sidebar .profile { text-align:center; margin:20px 0; font-weight:bold; }
.sidebar .profile i { font-size:2rem; display:block; margin-bottom:5px; }
.sidebar .menu { list-style:none; padding:0; margin:0; flex:1; }
.sidebar .menu li { padding:12px 20px; cursor:pointer; display:flex; align-items:center; gap:10px; color:#333; }
.sidebar .menu li:hover, .sidebar .menu li.active { background:#333; color:#fff; }
.sidebar .menu a { text-decoration:none; color:inherit; display:flex; align-items:center; gap:10px; width:100%; }
.sidebar .logout { display:block; padding:15px 20px; border-top:1px solid #ccc; cursor:pointer; font-weight:bold; color:#333; text-decoration:none; }
.sidebar .logout:hover { background:#333; color:#fff; }
.main-content { flex:1; padding:20px; background:#fff; overflow-y:auto; margin-left:230px; height:100vh; }
.header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; }
.header h2 { margin:0; }
.user-info { display:flex; align-items:center; gap:10px; }
.form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:15px; }
.form-grid label { font-weight:bold; display:block; margin-bottom:5px; }
.form-grid input { width:100%; padding:6px; border:1px solid #ccc; border-radius:5px; }
.btn { padding:4px 10px; background:#6a7a48; color:#fff; border:none; border-radius:5px; cursor:pointer; font-size:14px; }
.btn:hover { background:#4f5a32; }
.table-wrapper { max-height:260px; overflow-y:auto; border:1px solid #ccc; border-radius:5px; }
table { width:100%; border-collapse:collapse; font-size:14px; }
table th, table td { border:1px solid #ccc; padding:10px; text-align:center; white-space:nowrap; }
table th { background:#f3f3f3; position:sticky; top:0; z-index:2; }
table tr:hover { background:#f9f9f9; }
.total-palay { font-size:16px; font-weight:bold; margin-bottom:10px; color:#6a7a48; }
</style>
</head>
<body>
<aside class="sidebar">
    <div>
        <div class="profile">
            <i class="fa-solid fa-user-circle"></i>
            <span><?= htmlspecialchars($current_role); ?></span>
        </div>
        <ul class="menu">
            <li><a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
                  <li><a href="pos.php"><i class="fa-solid fa-dollar"></i> POS</a></li>
            <li><a href="usermanagement.php"><i class="fa-solid fa-users"></i> User Management</a></li>
            <li class="active"><a href="buyingpalay.php"><i class="fa-solid fa-wheat-awn"></i> Buying Palay</a></li>
            <li><a href="milling.php"><i class="fa-solid fa-industry"></i> Milling Management</a></li>
            <li><a href="products.php"><i class="fa-solid fa-box"></i> Products & Inventory</a></li>
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
    <h2>BUYING PALAY</h2>
    <div class="user-info">
        <span><?= htmlspecialchars($current_user_name); ?></span>
    </div>
</header>

<section style="margin-bottom:30px;padding:20px;background:#f3f3f3;border-radius:10px;">
    <h3 style="color:#6a7a48;margin-bottom:20px;">Add New Purchase</h3>
    <form method="POST" id="addPurchaseForm">
        <div class="form-grid">
            <div>
                <label>Supplier</label>
                <input type="text" name="supplier" required>
            </div>
            <div>
                <label>Quantity (kg)</label>
                <input type="number" name="quantity" id="quantity" required>
            </div>
            <div>
                <label>Price/kg</label>
                <input type="number" step="0.01" name="price" id="price" required>
            </div>
            <div>
                <label>Total Amount</label>
                <input type="number" step="0.01" name="total_amount" id="total_amount" readonly required>
            </div>
        </div>
        <div style="margin-top:15px;">
            <button type="submit" name="add" class="btn">Save</button>
            <button type="reset" class="btn" style="background:#888;">Reset</button>
        </div>
    </form>
</section>

<div class="total-palay">
    Current Total Palay Quantity: <?= number_format($total_palay, 2); ?> kg
</div>

<h4>Purchase Records</h4>
<div style="margin-bottom:15px;">
    <input type="text" id="searchInput" placeholder="Search transactions..." style="width:30%;padding:8px;border:1px solid #ccc;border-radius:5px;">
</div>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Supplier</th>
                <th>Added By</th>
                <th>Role</th>
                <th>Quantity</th>
                <th>Price/kg</th>
                <th>Total</th>
                <th>Date</th>
                <th>Status</th>
                <th>PV Number</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $res = $conn->query("
                SELECT p.*, u.name AS user_name, u.role AS user_role
                FROM palay_purchases p
                JOIN users u ON p.user_id = u.user_id
                ORDER BY p.id ASC
            ");
            while ($row = $res->fetch_assoc()) {
                $date = date('Y-m-d', strtotime($row['purchase_date']));
                $pvNumber = $row['pv_number'] ?? ($row['pv_printed'] ? 'Yes' : 'No');

                echo '<tr>';
                echo '<td>' . $row['id'] . '</td>';
                echo '<td>' . htmlspecialchars($row['supplier']) . '</td>';
                echo '<td>' . htmlspecialchars($row['user_name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['user_role']) . '</td>';
                echo '<td>' . number_format($row['quantity'], 2) . '</td>';
                echo '<td>' . number_format($row['price'], 2) . '</td>';
                echo '<td>' . number_format($row['total_amount'], 2) . '</td>';
                echo '<td>' . $date . '</td>';
                echo '<td>' . $row['payment_status'] . '</td>';
                echo '<td>' . htmlspecialchars($pvNumber) . '</td>';
                echo '<td>';

                if ($row['payment_status'] === 'Pending') {
                    echo '<button type="button" class="btn" style="background:#2980b9;" onclick="openPrintPV(' . $row['id'] . ')">Print PV</button> ';

                    echo '<form method="POST" style="display:inline-block;">
                            <input type="hidden" name="id" value="' . $row['id'] . '">
                            <button type="submit" name="paid" class="btn" style="background:' . ($row['pv_printed'] ? '#27ae60' : '#aaa') . ';" ' . ($row['pv_printed'] ? '' : 'disabled') . '>Mark as Paid</button>
                          </form> ';

                    echo '<form method="POST" style="display:inline-block;">
                            <input type="hidden" name="id" value="' . $row['id'] . '">
                            <button type="submit" name="void" class="btn" style="background:#c0392b;">Void</button>
                          </form>';
                } elseif ($row['payment_status'] === 'Paid') {
                    echo '<span style="color:#27ae60;font-weight:bold;">Paid</span>';
                } else {
                    echo '<span style="color:#c0392b;font-weight:bold;">Voided</span>';
                }

                echo '</td>';
                echo '</tr>';
            }
            ?>
        </tbody>
    </table>
</div>
</main>

<script>
// Auto-calculate total
function calculateAddTotal() {
    const qty = parseFloat(document.getElementById('quantity').value) || 0;
    const price = parseFloat(document.getElementById('price').value) || 0;
    document.getElementById('total_amount').value = (qty * price).toFixed(2);
}
document.getElementById('quantity').addEventListener('input', calculateAddTotal);
document.getElementById('price').addEventListener('input', calculateAddTotal);

// Search filter
document.getElementById('searchInput').addEventListener('input', function() {
    const filter = this.value.toLowerCase();
    document.querySelectorAll("table tbody tr").forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? "" : "none";
    });
});

function openPrintPV(id) {
    fetch('print_pv_composer.php?id=' + id)
      .then(res => res.text())
      .then(pvText => {
          // Optional: send to printer
          return fetch('print_receipt.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: 'receiptText=' + encodeURIComponent(pvText)
          }).then(res => res.text()).then(() => pvText);
      })
      .then(pvText => {
          // Extract PV number from the first line containing "PV Number"
          const match = pvText.match(/PV Number\s*:\s*(PV-\d{8}-\d+)/);
          const pvNumber = match ? match[1] : 'Yes';

          // Update PV Number column in table
          const row = document.querySelector('input[name="id"][value="' + id + '"]').closest('tr');
          if (row) {
              row.cells[9].textContent = pvNumber; // 10th column = PV Number
          }

          // Enable Mark as Paid button
          const paidButton = row.querySelector('button[name="paid"]');
          if (paidButton) {
              paidButton.disabled = false;
              paidButton.style.background = '#27ae60';
          }
      })
      .catch(err => console.error("Print error:", err));
}
</script>
</body>
</html>
