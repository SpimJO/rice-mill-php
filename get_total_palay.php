<?php
include "db.php";
$res = $conn->query("SELECT palay_quantity FROM palay_milling_process LIMIT 1");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    echo number_format(floatval($row['palay_quantity']), 2);
} else {
    echo "0.00";
}
?>
