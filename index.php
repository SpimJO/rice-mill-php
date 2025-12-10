<?php

include "db.php";
session_start();


if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}


$current_user_id = $_SESSION['user_id'] ?? '';
$current_user_name = $_SESSION['name'] ?? '';
$current_role = $_SESSION['role'] ?? 'Operator';
?>
<!-- Updated layout: compressed table, no horizontal scroll -->
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Blockchain Log</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body {margin:0;font-family:Arial,sans-serif;display:flex;background:#f8f9fa;height:100vh;overflow:hidden;}
.sidebar {width:230px;background:#e9e6d9;height:100vh;display:flex;flex-direction:column;justify-content:space-between;position:fixed;top:0;left:0;overflow-y:auto;}
.sidebar .profile{text-align:center;margin:20px 0;font-weight:bold;}
.sidebar .profile i{font-size:2rem;display:block;margin-bottom:5px;}
.sidebar .menu{list-style:none;padding:0;margin:0;flex:1;}
.sidebar .menu li{padding:12px 20px;cursor:pointer;display:flex;align-items:center;gap:10px;color:#333;transition:background 0.2s;}
.sidebar .menu li:hover,.sidebar .menu li.active{background:#333;color:#fff;}
.sidebar .menu a{text-decoration:none;color:inherit;display:flex;align-items:center;gap:10px;width:100%;}
.sidebar .logout{display:block;padding:15px 20px;border-top:1px solid #ccc;cursor:pointer;font-weight:bold;color:#333;text-decoration:none;}
.sidebar .logout:hover{background:#333;color:#fff;}
.main-content{flex:1;padding:20px;background:#fff;overflow:hidden;margin-left:230px;height:100vh;display:flex;flex-direction:column;}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;}
.header h2{margin:0;color:#333;}
.user-info{display:flex;align-items:center;gap:10px;color:#444;font-weight:bold;}

/* NEW COMPRESSED TABLE STYLES */
.table-section {background:#f3f3f3;padding:10px;border-radius:10px;flex:1;display:flex;flex-direction:column;overflow:hidden;}
.table-container {flex:1;overflow-y:auto;border-radius:8px;border:1px solid #ccc;background:#fff;scrollbar-width:thin;scrollbar-color:#6a7a48 #f3f3f3;}
.table-container::-webkit-scrollbar {width:6px;}
.table-container::-webkit-scrollbar-thumb {background-color:#6a7a48;border-radius:10px;}
.table-container::-webkit-scrollbar-track {background:#f3f3f3;}

table {width:100%;border-collapse:collapse;font-size:12px;table-layout:auto;}

/* compress spacing */
table th, table td {border:1px solid #ddd;padding:6px;text-align:center;vertical-align:top;word-break:break-word;}
table th {background:#f3f3f3;position:sticky;top:0;z-index:2;}
table tr:hover {background:#fafafa;}

.hash {font-family:monospace;font-size:11px;word-break:break-word;}
.hash-short { font-family: monospace; font-size: 11px; cursor: pointer; }
.hash-short:hover { color: #4f5a32; }
.filter-input { margin-bottom: 10px; padding: 5px; width: 200px; }

pre.data-block {margin:0;max-height:40px;overflow:hidden;text-align:left;transition:max-height 0.3s ease;font-size:11px;}
pre.data-block.expanded {max-height:300px;overflow:auto;background:#f8f9fa;border-radius:5px;padding:6px;}
.toggle-btn {color:#6a7a48;font-size:11px;cursor:pointer;display:inline-block;margin-top:3px;text-decoration:underline;}
.toggle-btn:hover {color:#4f5a32;}


/* RESPONSIVE DESIGN */
@media (max-width: 1200px) {
  table {font-size:11px;}
  table th, table td {padding:5px;}
  .hash {font-size:10px;}
}

@media (max-width: 900px) {
  .main-content {margin-left:0;}
  .sidebar {position:relative;width:100%;height:auto;}
  .table-section {padding:5px;}
  table {font-size:10px;}
  table th, table td {padding:4px;}
}

@media (max-width: 600px) {
  table th:nth-child(1),
  table td:nth-child(1),
  table th:nth-child(4),
  table td:nth-child(4) {
    display:none; /* hide ID & Target User on small screens */
  }

  .toggle-btn {font-size:10px;}
  pre.data-block {max-height:35px;}
}

@media (max-width: 450px) {
  table th, table td {padding:3px;}
  .header h2 {font-size:16px;}
  .user-info {font-size:12px;}
}

</style>
</head>
<body>


<!-- SIDEBAR -->
<aside class="sidebar">
  <div>
    <div class="profile">
      <i class="fa-solid fa-user-circle"></i>
      <span><?php echo htmlspecialchars($current_role); ?></span>
    </div>
    <ul class="menu">
      <li><a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
      <li><a href="pos.php"><i class="fa-solid fa-dollar"></i> POS</a></li>
      <li><a href="usermanagement.php"><i class="fa-solid fa-users"></i> User Management</a></li>
      <li><a href="buyingpalay.php"><i class="fa-solid fa-wheat-awn"></i> Buying Palay</a></li>
      <li><a href="milling.php"><i class="fa-solid fa-industry"></i> Milling Management</a></li>
      <li><a href="products.php"><i class="fa-solid fa-box"></i> Products & Inventory</a></li>
      <li><a href="reports.php"><i class="fa-solid fa-file-lines"></i> Reports</a></li>
      <li class="active"><a href="index.php"><i class="fa-solid fa-link"></i> Blockchain Log</a></li>
    </ul>
  </div>
  <div class="logout" onclick="window.location.href='logout.php'">
    <i class="fa-solid fa-right-from-bracket"></i> Logout
  </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main-content">
  <header class="header">
    <h2><i class="fa-solid fa-link"></i> Blockchain Log</h2>
    <div class="user-info">
      <span><?php echo htmlspecialchars($current_user_name); ?></span>
    </div>
  </header>

  <section class="table-section">
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>User</th>
            <th>Action</th>
            <th>Target User</th>
            <th>Data</th>
            <th>Timestamp</th>
            <th>Previous Hash</th>
            <th>Current Hash</th>
          </tr>
        </thead>
        <tbody>
          <?php
$query = "SELECT bl.*, COALESCE(u.name, bl.user_id) AS user_name FROM blockchain_log bl LEFT JOIN users u ON LOWER(u.user_id) = LOWER(bl.user_id) ORDER BY bl.id DESC";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
while($row = $result->fetch_assoc()) {
$display_user = htmlspecialchars($row['user_name'] ?? $row['user_id']);
$action = htmlspecialchars($row['action']);
$target_user = htmlspecialchars($row['target_user']);
$data = htmlspecialchars($row['data']);
$timestamp = htmlspecialchars($row['timestamp']);
$prev_full_raw = $row['previous_hash'];
$curr_full_raw = $row['current_hash'];
$prev_full = htmlspecialchars($prev_full_raw);
$curr_full = htmlspecialchars($curr_full_raw);
$prev_short = htmlspecialchars(substr($prev_full_raw, 0, 6) . "…");
$curr_short = htmlspecialchars(substr($curr_full_raw, 0, 6) . "…");
echo "<tr>
<td>{$display_user}</td>
<td>{$action}</td>
<td>{$target_user}</td>
<td><pre class='data-block'>{$data}</pre></td>
<td>{$timestamp}</td>
<td class='hash-short' title='{$prev_full}'>{$prev_short}</td>
<td class='hash-short' title='{$curr_full}'>{$curr_short}</td>
</tr>";
}
} else {
echo "<tr><td colspan='7'>No blockchain records found.</td></tr>";
}
?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<script>
function toggleData(id) {
  const pre = document.getElementById('data' + id);
  const btn = pre.nextElementSibling;
  if (pre.classList.contains('expanded')) {
    pre.classList.remove('expanded');
    btn.textContent = 'View';
  } else {
    pre.classList.add('expanded');
    btn.textContent = 'Hide';
  }
}
</script>
</body>
</html>
