<?php
include "db.php";
session_start();

// === Require login ===
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$current_user_id = intval($_SESSION['user_id']);
$current_user_name = $_SESSION['name'] ?? '';
$current_role = $_SESSION['role'] ?? '';

// Only Admin
if ($current_role == 'operator') {
    $_SESSION['success_message'] = "Successfully Logged In!";
    header('Location: dashboard.php');
    exit();
}elseif ($current_role == 'admin') {
  $_SESSION['success_message'] = "Successfully Logged In!";
  header('Location: milling.php');
  exit();}

/* ========== Milling & Rice queries ========== */
$palay_total_result = $conn->query("SELECT COALESCE(SUM(palay_quantity),0) AS total_palay FROM palay_milling_process");
$total_palay_quantity = floatval($palay_total_result->fetch_assoc()['total_palay']);

$rice_total_result = $conn->query("SELECT COALESCE(SUM(total_quantity_kg),0) AS total_rice FROM rice_types");
$total_rice_produced = floatval($rice_total_result->fetch_assoc()['total_rice']);

$milling_res = $conn->query("SELECT id, rice_type, palay_quantity, added_by, added_date FROM palay_milling_process ORDER BY added_date DESC, id DESC");

$palay_by_type_res = $conn->query("
    SELECT rice_type, COALESCE(SUM(palay_quantity),0) AS total_palay
    FROM palay_milling_process
    GROUP BY rice_type ORDER BY total_palay DESC
");

$rice_by_type_res = $conn->query("
    SELECT type_name, COALESCE(total_quantity_kg,0) AS total_rice
    FROM rice_types ORDER BY total_rice DESC
");

$palay_labels = []; $palay_values = [];
while ($r = $palay_by_type_res->fetch_assoc()) {
    $palay_labels[] = $r['rice_type'];
    $palay_values[] = floatval($r['total_palay']);
}
$rice_labels = []; $rice_values = [];
while ($r = $rice_by_type_res->fetch_assoc()) {
    $rice_labels[] = $r['type_name'];
    $rice_values[] = floatval($r['total_rice']);
}

/* ========== Buying palay queries (palay_purchases) ========== */
$buy_total_res = $conn->query("SELECT COALESCE(SUM(quantity),0) AS total_purchased FROM palay_purchases");
$total_palay_purchased = floatval($buy_total_res->fetch_assoc()['total_purchased']);

$avg_price_res = $conn->query("SELECT COALESCE(AVG(price),0) AS avg_price FROM palay_purchases");
$avg_price_per_kg = floatval($avg_price_res->fetch_assoc()['avg_price']);

$weekly_res = $conn->query("
    SELECT CONCAT(YEAR(purchase_date), '-W', LPAD(WEEK(purchase_date,1),2,'0')) AS period,
           AVG(price) AS avg_price
    FROM palay_purchases
    GROUP BY YEAR(purchase_date), WEEK(purchase_date,1)
    ORDER BY YEAR(purchase_date), WEEK(purchase_date,1)
");

$monthly_res = $conn->query("
    SELECT DATE_FORMAT(purchase_date, '%Y-%m') AS period,
           AVG(price) AS avg_price
    FROM palay_purchases
    GROUP BY DATE_FORMAT(purchase_date, '%Y-%m')
    ORDER BY DATE_FORMAT(purchase_date, '%Y-%m')
");

$yearly_res = $conn->query("
    SELECT YEAR(purchase_date) AS period,
           AVG(price) AS avg_price
    FROM palay_purchases
    GROUP BY YEAR(purchase_date)
    ORDER BY YEAR(purchase_date)
");

$weekly_labels = []; $weekly_values = [];
while ($r = $weekly_res->fetch_assoc()) {
    $weekly_labels[] = $r['period'];
    $weekly_values[] = floatval($r['avg_price']);
}
$monthly_labels = []; $monthly_values = [];
while ($r = $monthly_res->fetch_assoc()) {
    $monthly_labels[] = $r['period'];
    $monthly_values[] = floatval($r['avg_price']);
}
$yearly_labels = []; $yearly_values = [];
while ($r = $yearly_res->fetch_assoc()) {
    $yearly_labels[] = $r['period'];
    $yearly_values[] = floatval($r['avg_price']);
}

/* ========== Inventory queries ========== */
$inv_tot_5_res = $conn->query("SELECT COALESCE(SUM(total_5kg),0) AS s5 FROM inventory");
$total_5kg_bags = floatval($inv_tot_5_res->fetch_assoc()['s5']);

$inv_tot_25_res = $conn->query("SELECT COALESCE(SUM(total_25kg),0) AS s25 FROM inventory");
$total_25kg_bags = floatval($inv_tot_25_res->fetch_assoc()['s25']);

$inv_tot_50_res = $conn->query("SELECT COALESCE(SUM(total_50kg),0) AS s50 FROM inventory");
$total_50kg_bags = floatval($inv_tot_50_res->fetch_assoc()['s50']);

$inv_tot_kg_res = $conn->query("SELECT COALESCE(SUM(total_kg),0) AS tkg FROM inventory");
$total_inventory_kg = floatval($inv_tot_kg_res->fetch_assoc()['tkg']);

$low_threshold = 10;

$low_count_res = $conn->query("
    SELECT
      SUM(CASE WHEN COALESCE(total_5kg,0) < $low_threshold THEN 1 ELSE 0 END) AS low_5,
      SUM(CASE WHEN COALESCE(total_25kg,0) < $low_threshold THEN 1 ELSE 0 END) AS low_25,
      SUM(CASE WHEN COALESCE(total_50kg,0) < $low_threshold THEN 1 ELSE 0 END) AS low_50
    FROM inventory
");
$low_counts = $low_count_res->fetch_assoc();
$low_5_count = intval($low_counts['low_5']);
$low_25_count = intval($low_counts['low_25']);
$low_50_count = intval($low_counts['low_50']);

$low_list_res = $conn->query("
    SELECT rice_type, total_5kg, total_25kg, total_50kg
    FROM inventory
    WHERE COALESCE(total_5kg,0) < $low_threshold
       OR COALESCE(total_25kg,0) < $low_threshold
       OR COALESCE(total_50kg,0) < $low_threshold
    ORDER BY rice_type
");
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Dashboard — Milling • Buying • Inventory</title>

<!-- Google font -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root{
  --bg:#f6f8f4;
  --panel:#ffffff;
  --muted:#6b6b6b;
  --accent:#6a7a48;
  --accent-2:#88C273;
  --danger:#ef5350;
  --radius:12px;
  --glass: rgba(255,255,255,0.6);
}

/* Reset & base */
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




/* Cards */
.summary-cards{ display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px }
.card{ background:var(--panel); padding:16px;border-radius:var(--radius); box-shadow:0 6px 18px rgba(28,33,22,0.05); flex:1; min-width:180px;display:flex;justify-content:space-between;align-items:flex-start;gap:10px;transition:transform .18s ease }
.card:hover{ transform: translateY(-4px) }
.card .meta{ display:flex;flex-direction:column }
.card h4{ margin:0;font-size:13px;color:var(--accent); font-weight:600 }
.card p{ margin:8px 0 0;font-size:20px;font-weight:700;color:#111 }
.card .small{ margin-top:6px;font-size:12px;color:var(--muted) }
.card .icon{ font-size:22px;color:var(--accent-2);padding:8px;border-radius:10px;background:linear-gradient(180deg, rgba(28,33,22,0.05), rgba(28,33,22,0.05)) }

/* Charts grid */
.charts{ display:grid; grid-template-columns: 1fr 1fr; gap:18px; margin-bottom:18px }
@media (max-width:1100px){ .charts{ grid-template-columns:1fr } }

/* Canvas wrapper preventing overflow */
.canvas-wrapper{ width:100%; height:320px; border-radius:10px; overflow:hidden; background:linear-gradient(180deg,#ffffff, #fbfdf8) ; padding:12px }
@media (max-width:900px){ .canvas-wrapper{ height:260px } }
@media (max-width:600px){ .canvas-wrapper{ height:220px } }

/* table / sections */
.table-section{ background:var(--panel); padding:14px; border-radius:var(--radius); box-shadow:0 6px 18px rgba(28,33,22,0.04); margin-bottom:16px }
.table-section h3{ margin:0 0 8px 0;font-size:16px;color:#243018 }
.small-muted{ font-size:13px;color:var(--muted) }

/* table styling */
.table-scroll{ overflow:auto;border-radius:8px }
table{ width:100%; border-collapse:collapse; font-size:13px; min-width:700px }
table th, table td{ border-bottom:1px solid #eef0e8; padding:10px 12px; text-align:center }
table th{ background:transparent; font-weight:600; color:var(--muted) }
table tr:hover td{ background: #fbfdf8 }

/* filter controls */
.filter-controls{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:10px }
.filter-controls button{ background:transparent;border:1px solid rgba(106,122,72,0.14); color:var(--accent); padding:8px 12px;border-radius:8px; cursor:pointer; font-weight:600 }
.filter-controls button.active{ background:var(--accent); color:#fff; border-color:var(--accent) }

/* low stock badge */
.badge{ background:var(--danger); color:#fff; padding:6px 10px; border-radius:999px; font-size:12px; }

</style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
  <div>
    <div class="profile">
      <i class="fa-solid fa-user-circle"></i>
      <span><?= htmlspecialchars($current_role) ?></span>
    </div>
    <ul class="menu">
      <li class="active"><a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
      <li><a href="pos.php"><i class="fa-solid fa-dollar"></i> POS</a></li>
      <li><a href="usermanagement.php"><i class="fa-solid fa-users"></i> User Management</a></li>
      <li><a href="buyingpalay.php"><i class="fa-solid fa-wheat-awn"></i> Buying Palay</a></li>
      <li><a href="milling.php"><i class="fa-solid fa-industry"></i> Milling Management</a></li>
      <li><a href="products.php"><i class="fa-solid fa-box"></i> Products & Inventory</a></li>
      <li><a href="reports.php"><i class="fa-solid fa-file-lines"></i> Reports</a></li>
      <li><a href="index.php"><i class="fa-solid fa-link"></i> Blockchain Log</a></li>
    </ul>
  </div>
  <div class="logout" onclick="window.location.href='logout.php'">
    <i class="fa-solid fa-right-from-bracket"></i> Logout
  </div>
</aside>

<!-- overlay for mobile -->
<div id="overlay" class="overlay" style="display:none"></div>

<!-- Main -->
<main class="main-content" id="main">
  <header class="header">
  
  <h2>DASHBOARD</h2>

   <div class="user-info"><span><?= htmlspecialchars($current_user_name) ?></span></div>
  

  </header>
    <div class="">
      <div class="hambtn" id="hambtn"></i></button>
    </div>
  <!-- Top summary cards -->
  <div class="summary-cards">
    <div class="card">
      <div class="meta">
        <h4>Total Palay Quantity (kg)</h4>
        <p><?= number_format($total_palay_quantity, 2) ?></p>
        <div class="small">From palay_milling_process</div>
      </div>
      <div class="icon"><i class="fa-solid fa-wheat-awn"></i></div>
    </div>

    <div class="card">
      <div class="meta">
        <h4>Total Rice Produced (kg)</h4>
        <p><?= number_format($total_rice_produced, 2) ?></p>
        <div class="small">From rice_types</div>
      </div>
      <div class="icon"><i class="fa-solid fa-box"></i></div>
    </div>


  </div>

   <!-- charts -->

    <div class="card" style="flex-direction:column; padding:12px">
      <h4 style="margin:6px 0 12px 0;color:var(--accent)">Total Rice Produced by Type</h4>
      <div class="canvas-wrapper"><canvas id="riceChart"></canvas></div>
    </div>
  </div>
   <!-- total palay purchased & average price/kg -->
   <div class="summary-cards">
    
    <div class="card">
      <div class="meta">
        <h4>Total Palay Purchased (kg)</h4>
        <p><?= number_format($total_palay_purchased, 2) ?></p>
      </div>
      <div class="icon"><i class="fa-solid fa-cart-shopping"></i></div>
    </div>

    <div class="card">
      <div class="meta">
        <h4>Average Price / kg</h4>
        <p><?= number_format($avg_price_per_kg, 2) ?> ₱</p>
        <div class="small">Average from purchases</div>
      </div>
      <div class="icon"><i class="fa-solid fa-peso-sign"></i></div>
    </div>
  </div>

    <!-- buying palay chart with filters -->
  <section class="table-section">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
      <div>
        <h3>Price per kg (Buying Palay)</h3>
        <div class="small-muted">Average price per selected period</div>
      </div>

      <div class="filter-controls" role="tablist" aria-label="Price filters">
        <button class="filter-btn active" data-filter="weekly">Weekly</button>
        <button class="filter-btn" data-filter="monthly">Monthly</button>
        <button class="filter-btn" data-filter="yearly">Yearly</button>
      </div>
    </div>

    <div style="margin-top:12px" class="card" >
      <div class="canvas-wrapper" style="height:340px"><canvas id="priceChart"></canvas></div>
    </div>
  </section>

  <!-- Inventory cards -->
  <div class="summary-cards" style="margin-bottom:18px">
    <div class="card">
      <div class="meta">
        <h4>Total 5kg Bags</h4>
        <p><?= number_format($total_5kg_bags) ?></p>
      </div>
      <div class="icon"><i class="fa-solid fa-box-open"></i></div>
    </div>

    <div class="card">
      <div class="meta">
        <h4>Total 25kg Bags</h4>
        <p><?= number_format($total_25kg_bags) ?></p>
      </div>
      <div class="icon"><i class="fa-solid fa-boxes-stacked"></i></div>
    </div>

    <div class="card">
      <div class="meta">
        <h4>Total 50kg Bags</h4>
        <p><?= number_format($total_50kg_bags) ?></p>
      </div>
      <div class="icon"><i class="fa-solid fa-dolly"></i></div>
    </div>

    <div class="card">
      <div class="meta">
        <h4>Total Inventory (kg)</h4>
        <p><?= number_format($total_inventory_kg, 2) ?></p>
      </div>
      <div class="icon"><i class="fa-solid fa-weight-scale"></i></div>
    </div>
  </div>

  <!-- low stock summary -->
  <div class="summary-cards" style="margin-bottom:18px">
    <div class="card">
      <div class="meta">
        <h4>Low 5kg Types</h4>
        <p><?= $low_5_count ?></p>
        <div class="small">&lt; <?= $low_threshold ?> bags</div>
      </div>
      <div class="badge">5kg</div>
    </div>

    <div class="card">
      <div class="meta">
        <h4>Low 25kg Types</h4>
        <p><?= $low_25_count ?></p>
        <div class="small"> &lt; <?= $low_threshold ?> bags</div>
      </div>
      <div class="badge">25kg</div>
    </div>

    <div class="card">
      <div class="meta">
        <h4>Low 50kg Types</h4>
        <p><?= $low_50_count ?></p>
        <div class="small"> &lt; <?= $low_threshold ?> bags</div>
      </div>
      <div class="badge">50kg</div>
    </div>

   
  </div>

 



  <!-- low stock table -->
  <section class="table-section">
    <h3>Low Stock Items</h3>
    <div class="small-muted">Showing inventory rows where any pack size is &lt; <?= $low_threshold ?> bags</div>
    <div class="table-scroll" style="margin-top:10px">
      <table>
        <thead>
          <tr><th>Rice Type</th><th>5kg Bags</th><th>25kg Bags</th><th>50kg Bags</th></tr>
        </thead>
        <tbody>
        <?php
        if ($low_list_res && $low_list_res->num_rows > 0) {
            while ($r = $low_list_res->fetch_assoc()) {
                echo "<tr>
                        <td>".htmlspecialchars($r['rice_type'])."</td>
                        <td>".intval($r['total_5kg'])."</td>
                        <td>".intval($r['total_25kg'])."</td>
                        <td>".intval($r['total_50kg'])."</td>
                      </tr>";
            }
        } else {
            echo "<tr><td colspan='4'>No low-stock items (all pack sizes ≥ {$low_threshold})</td></tr>";
        }
        ?>
        </tbody>
      </table>
    </div>
  </section>

 

</main>

<script>

  
// Sidebar toggle for mobile
const sidebar = document.getElementById('sidebar');
const main = document.getElementById('main');
const hambtn = document.getElementById('hambtn');
const overlay = document.getElementById('overlay');

function openSidebar(){
  sidebar.classList.remove('collapsed');
  overlay.classList.add('show');
  overlay.style.display = 'block';
}
function closeSidebar(){
  sidebar.classList.add('collapsed');
  overlay.classList.remove('show');
  setTimeout(()=>overlay.style.display='none',180);
}

hambtn.addEventListener('click', ()=>{
  if (sidebar.classList.contains('collapsed')) openSidebar();
  else closeSidebar();
});
overlay.addEventListener('click', closeSidebar);

// Chart data passed from PHP
const palayLabels = <?= json_encode($palay_labels, JSON_HEX_TAG); ?>;
const palayValues = <?= json_encode($palay_values, JSON_NUMERIC_CHECK); ?>;
const riceLabels = <?= json_encode($rice_labels, JSON_HEX_TAG); ?>;
const riceValues = <?= json_encode($rice_values, JSON_NUMERIC_CHECK); ?>;

const weeklyLabels = <?= json_encode($weekly_labels, JSON_HEX_TAG); ?>;
const weeklyValues = <?= json_encode($weekly_values, JSON_NUMERIC_CHECK); ?>;
const monthlyLabels = <?= json_encode($monthly_labels, JSON_HEX_TAG); ?>;
const monthlyValues = <?= json_encode($monthly_values, JSON_NUMERIC_CHECK); ?>;
const yearlyLabels = <?= json_encode($yearly_labels, JSON_HEX_TAG); ?>;
const yearlyValues = <?= json_encode($yearly_values, JSON_NUMERIC_CHECK); ?>;

// colors
const barPalette = ["#88C273","#A2D6A7","#C9E4C5","#6AA56A","#9FBB5D","#BFD99F","#D5EDB2","#7B8F5F"];

/* Build Bar Chart helper */
function buildBarChart(ctx, labels, values, labelTitle){
  return new Chart(ctx, {
    type: 'bar',
    data: { labels: labels, datasets: [{ label: labelTitle, data: values, backgroundColor: barPalette, borderRadius: 8, maxBarThickness:48 }] },
    options: {
      responsive:true, maintainAspectRatio:false,
      scales: {
        x:{ ticks:{ autoSkip:true, maxRotation:0 }, grid:{ display:false } },
        y:{ beginAtZero:true, ticks:{ callback: v => v.toLocaleString() + " kg" } }
      },
      plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label: ctx => `${ctx.parsed.y.toLocaleString()} kg` } } }
    }
  });
}

/* Palay / Rice charts */
buildBarChart(document.getElementById('palayChart'), palayLabels, palayValues, 'Palay (kg)');
buildBarChart(document.getElementById('riceChart'), riceLabels, riceValues, 'Rice (kg)');

/* Price per kg line chart (with filters) */
const priceCtx = document.getElementById('priceChart').getContext('2d');

function createPriceChart(labels, values){
  return new Chart(priceCtx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Price (₱ / kg)',
        data: values,
        tension: 0.25,
        borderColor: '#88C273',
        backgroundColor: 'rgba(136,194,115,0.12)',
        pointRadius: 3,
        pointHoverRadius: 6,
        borderWidth: 2,
        fill: true
      }]
    },
    options: {
      responsive:true, maintainAspectRatio:false,
      scales:{
        x:{ ticks:{ autoSkip:true, maxRotation:0 }, grid:{ display:false } },
        y:{ beginAtZero:false, ticks:{ callback: v => '₱' + Number(v).toLocaleString(undefined,{minimumFractionDigits:0, maximumFractionDigits:2}) } }
      },
      plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label: ctx => `₱ ${Number(ctx.parsed.y).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2})} / kg` } } }
    }
  });
}

let defaultLabels = weeklyLabels.length ? weeklyLabels : (monthlyLabels.length ? monthlyLabels : yearlyLabels);
let defaultValues = weeklyValues.length ? weeklyValues : (monthlyValues.length ? monthlyValues : yearlyValues);
let priceChart = createPriceChart(defaultLabels, defaultValues);

// Filter interaction
const filterButtons = document.querySelectorAll('.filter-btn');
filterButtons.forEach(btn=>{
  btn.addEventListener('click', ()=>{
    filterButtons.forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    const f = btn.dataset.filter;
    let labels=[], values=[];
    if (f==='weekly'){ labels = weeklyLabels; values = weeklyValues; }
    else if (f==='monthly'){ labels = monthlyLabels; values = monthlyValues; }
    else { labels = yearlyLabels; values = yearlyValues; }

    if (!labels || labels.length===0){ labels=['No data']; values=[0]; }
    priceChart.data.labels = labels;
    priceChart.data.datasets[0].data = values;
    priceChart.update();
  });
});


</script>

</body>
</html>
