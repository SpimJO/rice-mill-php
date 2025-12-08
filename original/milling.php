<?php
include "db.php";

// ADD NEW MILLING ENTRY
if(isset($_POST['add'])){
    $palay_type = $_POST['palay_type'];
    $date = $_POST['date'];
    $quantity = $_POST['quantity'];
    $milled_output = $_POST['milled_output']; // take the value from form

    // Ensure milled output is not higher than quantity
    if($milled_output > $quantity){
        $milled_output = $quantity;
    }

    $stmt = $conn->prepare("INSERT INTO milling (palay_type, date, quantity, milled_output) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssii", $palay_type, $date, $quantity, $milled_output);
    $stmt->execute();
    $stmt->close();
    header("Location: milling.php");
    exit();
}

// EDIT MILLING ENTRY
if(isset($_POST['edit'])){
    $id = $_POST['id'];
    $palay_type = $_POST['palay_type'];
    $date = $_POST['date'];
    $quantity = $_POST['quantity'];
    $milled_output = $_POST['milled_output'];

    if($milled_output > $quantity){
        echo "<script>alert('Milled output cannot exceed the quantity.'); window.location.href='milling.php';</script>";
        exit();
    }

    $stmt = $conn->prepare("UPDATE milling SET palay_type=?, date=?, quantity=?, milled_output=? WHERE id=?");
    $stmt->bind_param("ssiii", $palay_type, $date, $quantity, $milled_output, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: milling.php");
    exit();
}

// DELETE MILLING ENTRY
if(isset($_POST['delete'])){
    $id = $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM milling WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: milling.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Milling Management</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    display: flex;
    background: #f8f9fa;
    height: 100vh;
    overflow: hidden;
}

/* Sidebar */
.sidebar {
    width: 230px;
    background: #e9e6d9;
    height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: fixed;
    top: 0;
    left: 0;
    overflow-y: auto;
}
.sidebar .profile { text-align: center; margin:20px 0; font-weight:bold;}
.sidebar .profile i { font-size:2rem; display:block; margin-bottom:5px; }
.sidebar .menu { list-style:none; padding:0; margin:0; flex:1; }
.sidebar .menu li { padding:12px 20px; cursor:pointer; display:flex; align-items:center; gap:10px; color:#333; }
.sidebar .menu li:hover, .sidebar .menu li.active { background:#333; color:#fff; }
.sidebar .menu a { text-decoration:none; color:inherit; display:flex; align-items:center; gap:10px; width:100%; }
.sidebar .logout { display:block; padding:15px 20px; border-top:1px solid #ccc; cursor:pointer; font-weight:bold; color:#333; text-decoration:none; }
.sidebar .logout:hover { background:#333; color:#fff; }

/* Main content */
.main-content { flex:1; padding:20px; background:#fff; margin-left:230px; height:100vh; overflow-y:auto; }
.header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; }
.header h2 { margin:0; }
.user-info { display:flex; align-items:center; gap:10px; }
.user-info img { border-radius:50%; width:40px; height:40px; }

/* Form & Table */
.form-section {
    margin-bottom:30px; 
    padding:20px; 
    background:#f3f3f3; 
    border-radius:10px;
}
.form-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap:15px; }
.form-grid label { font-weight:bold; display:block; margin-bottom:5px; }
.form-grid input, .form-grid select { width:100%; padding:6px; border:1px solid #ccc; border-radius:5px; }
.form-actions { grid-column:1/-1; display:flex; gap:15px; justify-content:left; margin-top:15px; }

table { width:100%; border-collapse:collapse; font-size:14px; }
table th, table td { border:1px solid #ccc; padding:10px; text-align:center; }
table th { background:#f3f3f3; }
table tr:hover { background:#f9f9f9; }

.btn { padding:6px 16px; background:#6a7a48; color:#fff; border:none; border-radius:5px; cursor:pointer; font-size:14px; }
.btn:hover { background:#4f5a32; }
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
      <li><a href="buyingpalay.php"><i class="fa-solid fa-wheat-awn"></i> Buying Palay</a></li>
      <li class="active"><a href="milling.php"><i class="fa-solid fa-industry"></i> Milling Management</a></li>
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
  <h2>MILLING MANAGEMENT</h2>
  <div class="user-info">
    <img src="https://via.placeholder.com/40" alt="User Avatar">
    <div>
      <span>Eljhon Henry</span>
      <small>ADMIN</small>
    </div>
  </div>
</header>

<!-- Add Milling Section -->
<section class="form-section">
  <h3 style="color:#6a7a48; margin-bottom:20px;">Add New Milling Entry</h3>
  <form method="POST" id="addMillingForm">
    <div class="form-grid">
      <div>
        <label>Palay Type</label>
        <select name="palay_type" id="add-type" required>
          <option value="">-- Select Palay Type --</option>
          <option value="Dinorado">Dinorado</option>
          <option value="Jasponica">Jasponica</option>
          <option value="Sinandomeng">Sinandomeng</option>
          <option value="Angelica">Angelica</option>
        </select>
      </div>
      <div>
        <label>Date</label>
        <input type="date" name="date" id="add-date" value="<?php echo date('Y-m-d'); ?>" required>
      </div>
      <div>
        <label>Quantity (kg)</label>
        <input type="number" name="quantity" id="add-quantity" placeholder="Enter Quantity" required>
      </div>
      <div>
  <label>Milled Output (kg)</label>
  <input type="number" name="milled_output" id="add-output" placeholder="Enter Milled Output" required>
</div>
    </div>
    <div class="form-actions">
      <button type="submit" name="add" class="btn">Save</button>
      <button type="reset" class="btn" style="background:#888;">Reset</button>
    </div>
  </form>
</section>

<!-- Milling Table -->
<h3>Recent Milling Records</h3>
<table>
<thead>
  <tr>
    <th>ID</th>
    <th>Date</th>
    <th>Palay Type</th>
    <th>Quantity</th>
    <th>Milled Output</th>
    <th>Actions</th>
  </tr>
</thead>
<tbody>
<?php
$result = $conn->query("SELECT * FROM milling ORDER BY id DESC");
while($row = $result->fetch_assoc()){
    echo "
    <tr>
        <td>{$row['id']}</td>
        <td>{$row['date']}</td>
        <td>{$row['palay_type']}</td>
        <td>{$row['quantity']} kg</td>
        <td>{$row['milled_output']} kg</td>
        <td>
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

<script>
const addQty = document.getElementById('add-quantity');
const addOutput = document.getElementById('add-output');

// Initialize milled output with quantity
addOutput.value = addQty.value;

// Auto-fill milled output when quantity changes, but allow manual edits
addQty.addEventListener('input', () => {
    if(addOutput.dataset.manual !== "true"){
        addOutput.value = addQty.value;
    }
    // Ensure milled output never exceeds quantity
    if(Number(addOutput.value) > Number(addQty.value)){
        addOutput.value = addQty.value;
        addOutput.dataset.manual = "false";
    }
});

// Detect manual change
addOutput.addEventListener('input', () => {
    // Prevent user from entering higher than quantity
    if(Number(addOutput.value) > Number(addQty.value)){
        addOutput.value = addQty.value;
    }
    
    if(addOutput.value !== addQty.value){
        addOutput.dataset.manual = "true";
    } else {
        addOutput.dataset.manual = "false";
    }
});
</script>



</body>
</html>
