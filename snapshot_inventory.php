<?php
include "db.php";  // your database connection

$today = date('Y-m-d');

// Get all rice types from products
$rice_types = $mysqli->query("SELECT DISTINCT rice_type FROM products");

while ($row = $rice_types->fetch_assoc()) {
    $rice = $row['rice_type'];

    // Sum all inventory movements up to today
    $totals = $mysqli->query("
        SELECT 
            SUM(total_25kg) AS t25,
            SUM(total_50kg) AS t50,
            SUM(total_5kg) AS t5
        FROM inventory_history
        WHERE rice_type='$rice' AND snapshot_date <= '$today'
    ")->fetch_assoc();

    $t25 = $totals['t25'] ?? 0;
    $t50 = $totals['t50'] ?? 0;
    $t5  = $totals['t5'] ?? 0;
    $tkg = $t25*25 + $t50*50 + $t5*5;

    // Insert snapshot
    $mysqli->query("
        INSERT INTO inventory_history
        (rice_type, total_25kg, total_50kg, total_5kg, total_kg, snapshot_date, reference_type)
        VALUES
        ('$rice', $t25, $t50, $t5, $tkg, '$today', 'snapshot')
    ");
}

echo "Inventory snapshot for $today completed.";
?>
