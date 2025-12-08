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

$current_user_id = $_SESSION['user_id'] ?? '';
$current_user_name = $_SESSION['name'] ?? '';
$current_role = $_SESSION['role'] ?? 'Operator';

// === Only allow admin access ===
if ($current_role !== 'Admin') {
    $_SESSION['error_message'] = "Access denied. Admins only!";
    header('Location: dashboard.php');
    exit();
}

// === Blockchain Logger Function (Hyperledger Fabric) ===
function addBlockchainLog($conn, $user_id, $action, $target_user, $data = []) {
    // Use Hyperledger Fabric API with database fallback
    addBlockchainLogWithFallback($conn, $user_id, $action, $target_user, $data);
}

// === AUTO-GENERATE USER ID ===
function generateNextUserId($conn) {
    // Get the highest numeric user_id
    $result = $conn->query("SELECT user_id FROM users WHERE user_id REGEXP '^[0-9]+$' ORDER BY CAST(user_id AS UNSIGNED) DESC LIMIT 1");
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastId = (int)$row['user_id'];
        return (string)($lastId + 1);
    }
    
    // If no numeric user_id exists, start from 1
    return "1";
}

// === CRUD OPERATIONS HANDLED HERE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ADD USER
    if (isset($_POST['add'])) {
        // Auto-generate user_id
        $user_id = generateNextUserId($conn);
        $name = trim($_POST['name']);
        $role = trim($_POST['role']);
        $password = trim($_POST['password']);

        if (empty($name) || empty($role) || empty($password)) {
            $_SESSION['error_message'] = "All fields are required.";
            header("Location: usermanagement.php");
            exit();
        }

        // Double-check if generated user_id already exists (safety check)
        $check = $conn->prepare("SELECT user_id FROM users WHERE user_id=?");
        $check->bind_param("s", $user_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            // If exists, try next number
            $user_id = (string)((int)$user_id + 1);
        }
        $check->close();

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (user_id, name, role, password, date_added) VALUES (?, ?, ?, ?, CURDATE())");
        $stmt->bind_param("ssss", $user_id, $name, $role, $hashed);
        $stmt->execute();
        $stmt->close();

        // Add to blockchain log
        // For ADD_USER, the user_id in blockchain log should be the newly created user's ID
        // Target User should be the name of the user being created
        addBlockchainLog($conn, $user_id, 'ADD_USER', $name, [
            'created_by' => $current_user_id, // Track who created this user in the data field
            'name' => $name,
            'role' => $role
        ]);

        $_SESSION['success_message'] = "User added successfully.";
        header("Location: usermanagement.php");
        exit();
    }

    // EDIT USER
    if (isset($_POST['edit'])) {
        $user_id = trim($_POST['user_id']);
        $name = trim($_POST['name']);
        $role = trim($_POST['role']);
        $password = trim($_POST['password']);

        if (empty($user_id)) {
            $_SESSION['error_message'] = "Invalid user ID.";
            header("Location: usermanagement.php");
            exit();
        }

        if ($user_id === $current_user_id) {
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET name=?, password=? WHERE user_id=? LIMIT 1");
                $stmt->bind_param("sss", $name, $hashed, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name=? WHERE user_id=? LIMIT 1");
                $stmt->bind_param("ss", $name, $user_id);
            }
        } else {
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET name=?, role=?, password=? WHERE user_id=? LIMIT 1");
                $stmt->bind_param("ssss", $name, $role, $hashed, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name=?, role=? WHERE user_id=? LIMIT 1");
                $stmt->bind_param("sss", $name, $role, $user_id);
            }
        }

        $stmt->execute();
        $stmt->close();

        // Add to blockchain log
        // Target User should be the name of the user being edited
        addBlockchainLog($conn, $current_user_id, 'EDIT_USER', $name, [
            'user_id' => $user_id,
            'name' => $name,
            'role' => $role,
            'password_changed' => !empty($password)
        ]);

        $_SESSION['success_message'] = "User updated successfully.";
        header("Location: usermanagement.php");
        exit();
    }

    // DELETE USER
    if (isset($_POST['delete'])) {
        $user_id = trim($_POST['user_id'] ?? '');

        if (empty($user_id)) {
            $_SESSION['error_message'] = "Invalid delete request.";
            header("Location: usermanagement.php");
            exit();
        }

        if ($user_id === $current_user_id) {
            $_SESSION['error_message'] = "You cannot delete your own account.";
            header("Location: usermanagement.php");
            exit();
        }

        // Get user name before deleting (for blockchain log)
        $get_user = $conn->prepare("SELECT name, role FROM users WHERE user_id=? LIMIT 1");
        $get_user->bind_param("s", $user_id);
        $get_user->execute();
        $user_info = $get_user->get_result()->fetch_assoc();
        $deleted_user_name = $user_info['name'] ?? $user_id;
        $deleted_user_role = $user_info['role'] ?? 'Unknown';
        $get_user->close();

        $stmt = $conn->prepare("DELETE FROM users WHERE user_id=? LIMIT 1");
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $stmt->close();

        // Add to blockchain log
        // Target User should be the name of the user being deleted
        addBlockchainLog($conn, $current_user_id, 'DELETE_USER', $deleted_user_name, [
            'user_id' => $user_id,
            'name' => $deleted_user_name,
            'role' => $deleted_user_role
        ]);

        $_SESSION['success_message'] = "User deleted successfully.";
        header("Location: usermanagement.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body {margin:0;font-family:Arial,sans-serif;display:flex;background:#f8f9fa;height:100vh;overflow:hidden;}
.sidebar {width:230px;background:#e9e6d9;height:100vh;display:flex;flex-direction:column;justify-content:space-between;position:fixed;top:0;left:0;overflow-y:auto;}
.sidebar .profile{text-align:center;margin:20px 0;font-weight:bold;}
.sidebar .profile i{font-size:2rem;display:block;margin-bottom:5px;}
.sidebar .menu{list-style:none;padding:0;margin:0;flex:1;}
.sidebar .menu li{padding:12px 20px;cursor:pointer;display:flex;align-items:center;gap:10px;color:#333;}
.sidebar .menu li:hover,.sidebar .menu li.active{background:#333;color:#fff;}
.sidebar .menu a{text-decoration:none;color:inherit;display:flex;align-items:center;gap:10px;width:100%;}
.sidebar .logout{display:block;padding:15px 20px;border-top:1px solid #ccc;cursor:pointer;font-weight:bold;color:#333;text-decoration:none;}
.sidebar .logout:hover{background:#333;color:#fff;}
.main-content{flex:1;padding:20px;background:#fff;overflow-y:auto;margin-left:230px;height:100vh;}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;}
.header h2{margin:0;}
.user-info{display:flex;align-items:center;gap:10px;}
.user-info img{border-radius:50%;width:40px;height:40px;}
.form-section { margin-bottom:30px; padding:20px; background:#f3f3f3; border-radius:10px; }
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
.btn[disabled] { opacity:0.6; cursor:not-allowed; }
.modal { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index:1000; }
.modal-content { background:#fff; padding:20px; border-radius:10px; width:400px; max-width:90%; }
.modal-content h3 { margin-top:0; }
.modal-content .form-grid { grid-template-columns:1fr; }
.alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 15px; font-weight: bold; display: flex; align-items: center; gap: 10px; animation: fadeIn 0.25s ease-in-out;}
.alert.success {background: #d4edda;color: #155724;border-left: 5px solid #28a745;}
.alert.error {background: #f8d7da;color: #721c24;border-left: 5px solid #dc3545;}
@keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
</style>
</head>
<body>

<aside class="sidebar">
  <div>
    <div class="profile">
      <i class="fa-solid fa-user-circle"></i>
      <span><?php echo htmlspecialchars($current_role); ?></span>
    </div>
    <ul class="menu">
      <li><a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
                  <li><a href="pos.php"><i class="fa-solid fa-dollar"></i> POS</a></li>

      <li class="active"><a href="usermanagement.php"><i class="fa-solid fa-users"></i> User Management</a></li>
      <li><a href="buyingpalay.php"><i class="fa-solid fa-wheat-awn"></i> Buying Palay</a></li>
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

<!-- Alerts -->
<?php if (!empty($_SESSION['success_message'])): ?>
  <div class="alert success">
    <i class="fa-solid fa-check-circle"></i>
    <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
  </div>
<?php endif; ?>

<?php if (!empty($_SESSION['error_message'])): ?>
  <div class="alert error">
    <i class="fa-solid fa-circle-exclamation"></i>
    <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
  </div>
<?php endif; ?>

<header class="header">
  <h2>USER MANAGEMENT</h2>
  <div class="user-info">
    <span><?php echo htmlspecialchars($current_user_name); ?></span>
  </div>
</header>

<!-- Add User Form -->
<section class="form-section">
  <h3 style="color:#6a7a48; margin-bottom:20px;">Add New User</h3>
  <form method="POST" action="">
    <div class="form-grid">
      <div><label>User ID</label><input type="text" name="user_id" id="user_id_field" value="<?php echo htmlspecialchars(generateNextUserId($conn)); ?>" readonly style="background:#f0f0f0; cursor:not-allowed;" required></div>
      <div><label>Name</label><input type="text" name="name" placeholder="Enter Full Name" required></div>
      <div><label>Role</label>
        <select name="role" required>
          <option value="">-- Select Role --</option>
          <option value="Admin">Admin</option>
          <option value="Cashier">Cashier</option>
          <option value="Operator">Operator</option>
        </select>
      </div>
      <div><label>Password</label><input type="password" name="password" placeholder="Enter Password" required></div>
    </div>
    <div class="form-actions">
      <button type="submit" name="add" class="btn">Save</button>
      <button type="reset" class="btn" style="background:#888;">Reset</button>
    </div>
  </form>
</section>

<!-- User Table -->
<h3>System Users</h3>
<table>
<thead>
<tr>
  <th>User ID</th><th>Name</th><th>Role</th><th>Date Added</th><th>Actions</th>
</tr>
</thead>
<tbody>
<?php
$result = $conn->query("SELECT * FROM users ORDER BY id ASC");
while($row = $result->fetch_assoc()){
  $isCurrent = ($row['user_id'] === $current_user_id);
  $disable = $isCurrent ? "disabled" : "";
  echo "
  <tr>
    <td>{$row['user_id']}</td>
    <td>{$row['name']}</td>
    <td>{$row['role']}</td>
    <td>{$row['date_added']}</td>
    <td>
      <button type='button' class='btn edit-btn' data-id='{$row['id']}' data-userid='{$row['user_id']}' data-name='{$row['name']}' data-role='{$row['role']}' data-disable='".($isCurrent?'1':'')."'>Edit</button>
      <form method='POST' action='' style='display:inline-block' onsubmit='return confirmDelete(this);'>
        <input type='hidden' name='user_id' value='{$row['user_id']}'>
        <button type='submit' name='delete' class='btn' {$disable}>Delete</button>
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
    <h3>Edit User</h3>
    <form method="POST" action="">
      <input type="hidden" name="id" id="edit-id">
      <div class="form-grid">
        <div><label>User ID</label><input type="text" name="user_id" id="edit-userid" required></div>
        <div><label>Name</label><input type="text" name="name" id="edit-name" required></div>
        <div><label>Role</label>
          <select name="role" id="edit-role" required>
            <option value="Admin">Admin</option>
            <option value="Cashier">Cashier</option>
            <option value="Operator">Operator</option>
          </select>
        </div>
        <div><label>Password</label><input type="password" name="password" id="edit-password" placeholder="Leave blank to keep current"></div>
      </div>
      <div class="form-actions">
        <button type="submit" name="edit" class="btn">Update</button>
        <button type="button" class="btn" style="background:#888;" id="closeEdit">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
const modal = document.getElementById("editModal");
const closeBtn = document.getElementById("closeEdit");
document.querySelectorAll(".edit-btn").forEach(btn => {
  btn.onclick = function() {
    document.getElementById("edit-id").value = this.dataset.id;
    document.getElementById("edit-userid").value = this.dataset.userid;
    document.getElementById("edit-name").value = this.dataset.name;
    document.getElementById("edit-role").value = this.dataset.role;
    document.getElementById("edit-role").disabled = this.dataset.disable ? true : false;
    document.getElementById("edit-password").value = "";
    modal.style.display = "flex";
  };
});
closeBtn.onclick = () => modal.style.display = "none";
window.onclick = e => { if (e.target == modal) modal.style.display = "none"; };
function confirmDelete(form) {
  const uid = form.querySelector('input[name="user_id"]').value.trim();
  const current = <?php echo json_encode($current_user_id); ?>;
  if (uid === current) {
    alert("You cannot delete your own account!");
    return false;
  }
  return confirm("Are you sure you want to delete this user?");
}
</script>

</body>
</html>
