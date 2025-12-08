<?php
include 'db.php';

// --- ADD PURCHASE ---
if (isset($_POST['add'])) {
    $supplier = $_POST['supplier'];
    $quantity = $_POST['quantity'];
    $price = $_POST['price'];
    $amount = $_POST['total_amount'];
    $date = $_POST['purchase_date'];
    $status = $_POST['payment_status'];

    $stmt = $conn->prepare("INSERT INTO palay_purchases (supplier, quantity, price, total_amount, purchase_date, payment_status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sdddss", $supplier, $quantity, $price, $amount, $date, $status);
    $stmt->execute();
    header("Location: buyingPalay.php");
    exit;
}

// --- EDIT PURCHASE ---
if (isset($_POST['edit'])) {
    $id = $_POST['id'];
    $supplier = $_POST['supplier'];
    $quantity = $_POST['quantity'];
    $price = $_POST['price'];
    $amount = $_POST['total_amount'];
    $date = $_POST['purchase_date'];
    $status = $_POST['payment_status'];

    $stmt = $conn->prepare("UPDATE palay_purchases SET supplier=?, quantity=?, price=?, total_amount=?, purchase_date=?, payment_status=? WHERE id=?");
    $stmt->bind_param("sdddssi", $supplier, $quantity, $price, $amount, $date, $status, $id);
    $stmt->execute();
    header("Location: buyingPalay.php");
    exit;
}

// --- DELETE PURCHASE ---
if (isset($_POST['delete'])) {
    $id = $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM palay_purchases WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: buyingPalay.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Buying Palay</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* --- BODY --- */
body {
  margin: 0;
  font-family: Arial, sans-serif;
  display: flex;
  background: #f8f9fa;
  height: 100vh;
  overflow: hidden;
}

/* --- SIDEBAR --- */
.sidebar {
  width: 230px;
  background: #e9e6d9;
  height: 100vh;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  flex-shrink: 0;
  position: fixed;
  left: 0;
  top: 0;
  overflow-y: auto;
}
.sidebar .profile {
  text-align: center;
  margin: 20px 0;
  font-weight: bold;
}
.sidebar .profile i {
  font-size: 2rem;
  display: block;
  margin-bottom: 5px;
}
.sidebar .menu {
  list-style: none;
  padding: 0;
  margin: 0;
  flex: 1;
}
.sidebar .menu li {
  padding: 12px 20px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 10px;
  color: #333;
}
.sidebar .menu li:hover,
.sidebar .menu li.active {
  background: #333;
  color: #fff;
}

                .sidebar .menu a {
                    text-decoration: none;
                    color: inherit;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    width: 100%;
                }

            .sidebar .logout {
                display: block;
                padding: 15px 20px;
                border-top: 1px solid #ccc;
                cursor: pointer;
                font-weight: bold;
                color: #333;
                text-decoration: none;
            }

                .sidebar .logout:hover {
                    background: #333;
                    color: #fff;
                }
.sidebar .menu a {
  text-decoration: none;
  color: inherit;
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
}

/* --- MAIN CONTENT --- */
.main-content {
  flex: 1;
  padding: 20px;
  background: #fff;
  overflow-y: auto;
  margin-left: 230px;
  height: 100vh;
}
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.header h2 { margin: 0; }
.user-info { display: flex; align-items: center; gap: 10px; }
.user-info img { border-radius: 50%; width: 40px; height: 40px; }

/* --- TABLE --- */
table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}
table th, table td {
  border: 1px solid #ccc;
  padding: 10px;
  text-align: center;
}
table th { background: #f3f3f3; }
table tr:hover { background: #f9f9f9; }

/* --- BUTTON --- */
.btn {
  padding: 6px 16px;
  background: #6a7a48;
  color: #fff;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-size: 14px;
}
.btn:hover { background: #4f5a32; }

/* --- MODAL --- */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0,0,0,0.5);
  justify-content: center;
  align-items: center;
}
.modal-content {
  background: #fff;
  padding: 20px 25px;
  width: 500px;
  max-width: 95%;
  border-radius: 10px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.2);
  animation: fadeIn 0.3s ease-in-out;
}
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  border-bottom: 1px solid #ddd;
  padding-bottom: 10px;
}
.modal-header h3 { margin: 0; color: #6a7a48; }
.close-btn { font-size: 22px; cursor: pointer; font-weight: bold; }

@keyframes fadeIn {
  from {opacity: 0; transform: scale(0.9);}
  to {opacity: 1; transform: scale(1);}
}

.form-grid {
  display:grid; 
  grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); 
  gap:15px;
}
.form-grid label { font-weight: bold; display: block; margin-bottom: 5px; }
.form-grid input, .form-grid select { width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 5px; }

@media(max-width:600px){
  .modal-content { width: 90%; padding: 15px; }
}
</style>
</head>
<body>

<aside class="sidebar">
  <div>
    <div class="profile">
      <i class="fa-solid fa-user-circle"></i>
      <span>Administrator</span>
    </div>
    <ul class="menu">
      <li><a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
      <li><a href="usermanagement.php"><i class="fa-solid fa-users"></i> User Management</a></li>
      <li class="active"><a href="buyingpalay.php"><i class="fa-solid fa-wheat-awn"></i> Buying Palay</a></li>
      <li><a href="milling.php"><i class="fa-solid fa-industry"></i> Milling Management</a></li>
      <li><a href="products.php"><i class="fa-solid fa-box"></i> Products</a></li>
      <li><a href="sales.php"><i class="fa-solid fa-peso-sign"></i> Sales</a></li>
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
    <img src="https://via.placeholder.com/40" alt="User Avatar">
    <div>
      <span>Eljhon Henry</span>
      <small>ADMIN</small>
    </div>
  </div>
</header>

<!-- Add Purchase Section -->
<section style="margin-bottom: 30px; padding: 20px; background: #f3f3f3; border-radius: 10px;">
  <h3 style="text-align: left; color: #6a7a48; margin-bottom: 20px;">Add New Purchase</h3>
  <form method="POST" id="addPurchaseForm">
    <div class="form-grid">
      <div>
        <label for="supplier">Supplier</label>
        <input type="text" name="supplier" id="supplier" placeholder="Enter Supplier Name" required>
      </div>
      <div>
        <label for="quantity">Quantity (kg)</label>
        <input type="number" name="quantity" id="quantity" placeholder="Enter Quantity" required>
      </div>
      <div>
        <label for="price">Price/kg</label>
        <input type="number" step="0.01" name="price" id="price" placeholder="Enter Price per kg" required>
      </div>
      <div>
        <label for="total_amount">Total Amount</label>
        <input type="number" step="0.01" name="total_amount" id="total_amount" placeholder="Total Amount" readonly required>
      </div>
      <div>
        <label for="purchase_date">Purchase Date</label>
        <input type="date" name="purchase_date" id="purchase_date" value="<?php echo date('Y-m-d'); ?>" required>
      </div>
      <div>
        <label for="payment_status">Status</label>
        <select name="payment_status" id="payment_status" required>
          <option value="Pending">Pending</option>
          <option value="Paid">Paid</option>
        </select>
      </div>
    </div>
    <div style="grid-column: 1 / -1; display: flex; justify-content: left; gap: 15px; margin-top: 15px;">
      <button type="submit" name="add" class="btn">Save</button>
      <button type="reset" class="btn" style="background:#888;">Reset</button>
    </div>
  </form>
</section>

<h3>Purchase Records</h3>
<table>
  <thead>
    <tr>
      <th>ID</th><th>Supplier</th><th>Quantity</th><th>Price/kg</th><th>Total</th><th>Date</th><th>Status</th><th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php
  $res = $conn->query("SELECT * FROM palay_purchases ORDER BY id ASC");
  while($row = $res->fetch_assoc()){
      $date = date('Y-m-d', strtotime($row['purchase_date']));
      echo "<tr>
      <td>{$row['id']}</td>
      <td>{$row['supplier']}</td>
      <td>{$row['quantity']}</td>
      <td>{$row['price']}</td>
      <td>{$row['total_amount']}</td>
      <td>$date</td>
      <td>{$row['payment_status']}</td>
      <td>
        <button class='btn editBtn' 
          data-id='{$row['id']}' 
          data-supplier='{$row['supplier']}' 
          data-quantity='{$row['quantity']}' 
          data-price='{$row['price']}' 
          data-amount='{$row['total_amount']}' 
          data-date='$date' 
          data-status='{$row['payment_status']}'>Edit</button>
        <form method='POST' style='display:inline-block;'>
          <input type='hidden' name='id' value='{$row['id']}'>
          <button type='submit' name='delete' class='btn'>Delete</button>
        </form>
      </td>
      </tr>";
  }
  ?>
  </tbody>
</table>
</main>


<!-- Edit Modal -->
<div class="modal" id="editModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Edit Purchase</h3>
      <span class="close-btn" id="closeEdit">&times;</span>
    </div>
    <form method="POST">
      <input type="hidden" name="id" id="edit-id">
      <div class="form-grid">
        <div>
          <label>Supplier</label>
          <input type="text" name="supplier" id="edit-supplier" required>
        </div>
        <div>
          <label>Quantity (kg)</label>
          <input type="number" name="quantity" id="edit-quantity" required>
        </div>
        <div>
          <label>Price/kg</label>
          <input type="number" step="0.01" name="price" id="edit-price" required>
        </div>
        <div>
          <label>Total Amount</label>
          <input type="number" step="0.01" name="total_amount" id="edit-amount" readonly required>
        </div>
        <div>
          <label>Purchase Date</label>
          <input type="date" name="purchase_date" id="edit-date" required>
        </div>
        <div>
          <label>Status</label>
          <select name="payment_status" id="edit-status">
            <option>Pending</option>
            <option>Paid</option>
          </select>
        </div>
      </div>
      <div style="text-align:right; margin-top:15px;">
        <button type="submit" name="edit" class="btn">Update</button>
      </div>
    </form>
  </div>
</div>

<script>
// --- EDIT MODAL ---
const editModal = document.getElementById("editModal");
const closeEdit = document.getElementById("closeEdit");

// Open Edit modal & prefill values
document.querySelectorAll(".editBtn").forEach(btn => {
  btn.addEventListener("click", function() {
    document.getElementById("edit-id").value = this.dataset.id;
    document.getElementById("edit-supplier").value = this.dataset.supplier;
    document.getElementById("edit-quantity").value = this.dataset.quantity;
    document.getElementById("edit-price").value = this.dataset.price;
    document.getElementById("edit-date").value = this.dataset.date;
    document.getElementById("edit-status").value = this.dataset.status;

    // calculate total for edit modal
    calculateEditTotal();

    editModal.style.display = "flex";
  });
});

// Close Edit modal
closeEdit.addEventListener("click", () => editModal.style.display = "none");

// Close modal when clicking outside
window.addEventListener("click", e => {
  if(e.target === editModal) editModal.style.display = "none";
});

// --- LIVE TOTAL CALCULATION FOR ADD FORM ---
function calculateAddTotal(){
  const qty = parseFloat(document.getElementById('quantity').value) || 0;
  const price = parseFloat(document.getElementById('price').value) || 0;
  document.getElementById('total_amount').value = (qty * price).toFixed(2);
}
document.getElementById('quantity').addEventListener('input', calculateAddTotal);
document.getElementById('price').addEventListener('input', calculateAddTotal);

// --- LIVE TOTAL CALCULATION FOR EDIT MODAL ---
function calculateEditTotal(){
  const qty = parseFloat(document.getElementById('edit-quantity').value) || 0;
  const price = parseFloat(document.getElementById('edit-price').value) || 0;
  document.getElementById('edit-amount').value = (qty * price).toFixed(2);
}
document.getElementById('edit-quantity').addEventListener('input', calculateEditTotal);
document.getElementById('edit-price').addEventListener('input', calculateEditTotal);
</script>


</body>
</html>
