<?php
date_default_timezone_set('Asia/Manila'); 
// reports.php - Full file (standalone)
// include DB and start session
include "db.php";
include "blockchain_api.php"; // Hyperledger Fabric API helper
session_start();

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

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// --- Handle CSV export ---
if (isset($_GET['export']) && $_GET['export'] == 'csv' && isset($_SESSION['report_data']) && !empty($_SESSION['report_type'])) {
    $filename = "report_" . date("Ymd_His") . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $output = fopen('php://output', 'w');

    // BOM for Excel
    fputs($output, "\xEF\xBB\xBF");

    if (!empty($_SESSION['report_columns'])) {
        fputcsv($output, $_SESSION['report_columns']);
    }

    foreach ($_SESSION['report_data'] as $row) {
        if (is_array($row)) {
            if (!empty($_SESSION['report_columns_map'])) {
                $values = [];
                foreach ($_SESSION['report_columns_map'] as $header => $key) {
                    if (strpos($key, '.') !== false) {
                        $parts = explode('.', $key);
                        $v = $row;
                        foreach ($parts as $p) {
                            if (is_array($v) && array_key_exists($p, $v)) $v = $v[$p]; 
                            else { $v = null; break; }
                        }
                    } else {
                        $v = $row[$key] ?? '';
                    }
                    $values[] = $v;
                }
                fputcsv($output, $values);
            } elseif (!empty($_SESSION['report_columns'])) {
                $values = [];
                foreach ($_SESSION['report_columns'] as $h) {
                    $found = null;
                    foreach ($row as $rk => $rv) {
                        $search = strtolower(preg_replace('/[^a-z0-9]/','',$h));
                        $candidate = strtolower(preg_replace('/[^a-z0-9]/','',$rk));
                        if ($search === $candidate || strpos($candidate, $search) !== false || strpos($search, $candidate) !== false) {
                            $found = $rv;
                            break;
                        }
                    }
                    $values[] = $found ?? '';
                }
                fputcsv($output, $values);
            } else {
                fputcsv($output, array_values($row));
            }
        } else {
            fputcsv($output, [$row]);
        }
    }
    
    // ✅ Blockchain log — only once per export
    $export_user_id = intval($_SESSION['user_id'] ?? 0);
    $export_report_type = $_SESSION['report_type'] ?? 'unknown';
    $export_details = json_encode([
        'report_type' => $export_report_type,
        'exported_by' => $_SESSION['name'] ?? '',
        'export_time' => date('Y-m-d H:i:s'),
        'rows' => is_array($_SESSION['report_data']) ? count($_SESSION['report_data']) : 0
    ]);
    addBlockchainLogWithFallback($conn, $export_user_id, 'Export Report CSV', $current_user_name, json_decode($export_details, true));
    
    fclose($output);
    exit;
}    

// --- Initialize variables ---
$report_type = $_POST['report_type'] ?? '';
$frequency = $_POST['frequency'] ?? 'date_range'; // daily, weekly, monthly, yearly, date_range

// inputs for date selection
$from_date = $_POST['from_date'] ?? '';
$to_date = $_POST['to_date'] ?? '';
$selected_year = $_POST['year'] ?? date('Y');
$week_picker = $_POST['week'] ?? '';   // format: YYYY-Www
$month_picker = $_POST['month'] ?? ''; // format: YYYY-MM

// transaction/pv
$transaction_id = trim($_POST['transaction_id'] ?? '');

// compute $from / $to depending on frequency
if ($frequency === 'yearly') {
    $from = "$selected_year-01-01";
    $to = "$selected_year-12-31";
} elseif ($frequency === 'weekly' && !empty($week_picker)) {
    // week_picker like "2025-W45" or "2025-W05" or browsers that send "YYYY-Www"
    if (strpos($week_picker, '-W') !== false) {
        list($wy, $ww) = explode('-W', $week_picker);
    } else {
        $wy = substr($week_picker, 0, 4);
        $ww = substr($week_picker, strpos($week_picker,'-')+2);
    }
    $wy = intval($wy);
    $ww = intval($ww);
    $dto = new DateTime();
    // setISODate sets to Monday of requested ISO week (ISO weeks start Monday)
    $dto->setISODate($wy, $ww);
    $from = $dto->format('Y-m-d');
    $dto->modify('+6 days');
    $to = $dto->format('Y-m-d');
} elseif ($frequency === 'monthly' && !empty($month_picker)) {
    $dto = DateTime::createFromFormat('Y-m', $month_picker);
    if ($dto) {
        $from = $dto->format('Y-m-01');
        $dto->modify('last day of this month');
        $to = $dto->format('Y-m-d');
    } else {
        $from = $from_date;
        $to = $to_date;
    }
} else {
    // date_range or frequency unspecified
    $from = $from_date;
    $to = $to_date;
}

$report_data = [];
$report_columns = [];
$report_columns_map = [];
$sum_columns = [];
$summary_rows = [];

if (isset($_POST['generate'])) {
    // basic validation
    if ($frequency !== 'yearly' && (empty($from) || empty($to))) {
        $error = 'Please choose a valid date range, week, or month.';
    } else {
        $from_esc = $conn->real_escape_string($from);
        $to_esc = $conn->real_escape_string($to);
        $tx_esc = $conn->real_escape_string($transaction_id);

        switch ($report_type) {
            case 'sales':
                $query = "
                    SELECT 
                        MIN(s.id) AS id,
                        s.transaction_id AS transaction_id,
                        GROUP_CONCAT(DISTINCT s.rice_type SEPARATOR ', ') AS rice_types,
                        SUM(s.sack_25kg) AS sack_25kg,
                        SUM(s.sack_50kg) AS sack_50kg,
                        SUM(s.sack_5kg) AS sack_5kg,
                        SUM(s.subtotal) AS subtotal,
                        SUM(s.discount) AS discount,
                        SUM(s.total) AS total,
                        ANY_VALUE(u.name) AS operator_name,
                        DATE(MAX(s.created_at)) AS sale_date
                    FROM sales s
                    LEFT JOIN users u ON s.operator_user_id = u.user_id
                    WHERE DATE(s.created_at) BETWEEN '$from_esc' AND '$to_esc'
                    " . (!empty($transaction_id) ? " AND s.transaction_id = '$tx_esc' " : "") . "
                    GROUP BY s.transaction_id
                    ORDER BY MIN(s.created_at) ASC
                ";
                $report_columns = ['ID','Transaction ID','Rice Types','25kg Sacks','50kg Sacks','5kg Sacks','Subtotal','Discount','Total','Operator','Date'];
                $report_columns_map = [
                    'ID' => 'id',
                    'Transaction ID' => 'transaction_id',
                    'Rice Types' => 'rice_types',
                    '25kg Sacks' => 'sack_25kg',
                    '50kg Sacks' => 'sack_50kg',
                    '5kg Sacks' => 'sack_5kg',
                    'Subtotal' => 'subtotal',
                    'Discount' => 'discount',
                    'Total' => 'total',
                    'Operator' => 'operator_name',
                    'Date' => 'sale_date'
                ];
                $sum_columns = ['sack_25kg','sack_50kg','sack_5kg','subtotal','discount','total'];
                $summary_table = 'sales';
                $summary_date_field = 'created_at';
                $summary_value_field = 'total';
                break;

            case 'purchases':
                $query = "
                    SELECT 
                        p.id AS id,
                        COALESCE(p.pv_number, CONCAT('PV-', DATE_FORMAT(p.purchase_date,'%Y%m%d'), '-', p.id)) AS pv_number,
                        p.supplier AS supplier,
                        p.quantity AS quantity,
                        p.price AS price,
                        p.total_amount AS total_amount,
                        DATE(p.purchase_date) AS purchase_date,
                        p.pv_printed AS pv_printed,
                        u.name AS operator_name,
                        p.payment_status AS payment_status
                    FROM palay_purchases p
                    LEFT JOIN users u ON p.user_id = u.user_id
                    WHERE DATE(p.purchase_date) BETWEEN '$from_esc' AND '$to_esc'
                    " . (!empty($transaction_id) ? " AND (p.pv_number = '$tx_esc' OR CONCAT('PV-', DATE_FORMAT(p.purchase_date,'%Y%m%d'), '-', p.id) = '$tx_esc') " : "") . "
                    ORDER BY p.purchase_date ASC
                ";
                $report_columns = ['ID','PV Number','Supplier','Quantity (kg)','Price/kg','Total Amount','Purchase Date','PV Printed','Added By','Payment Status'];
                $report_columns_map = [
                    'ID' => 'id',
                    'PV Number' => 'pv_number',
                    'Supplier' => 'supplier',
                    'Quantity (kg)' => 'quantity',
                    'Price/kg' => 'price',
                    'Total Amount' => 'total_amount',
                    'Purchase Date' => 'purchase_date',
                    'PV Printed' => 'pv_printed',
                    'Added By' => 'operator_name',
                    'Payment Status' => 'payment_status'
                ];
                $sum_columns = ['quantity','total_amount'];
                $summary_table = 'palay_purchases';
                $summary_date_field = 'purchase_date';
                $summary_value_field = "IF(payment_status='Paid', total_amount, 0)"; // ignore voided in summary
                break;

            case 'milling':
                $query = "
                    SELECT 
                        m.id,
                        m.rice_name,
                        m.quantity,
                        m.milled_output,
                        u.name AS operator_name,
                        DATE(m.created_at) AS created_at
                    FROM milling m
                    LEFT JOIN users u ON m.operator_user_id = u.user_id
                    WHERE DATE(m.created_at) BETWEEN '$from_esc' AND '$to_esc'
                    ORDER BY m.created_at ASC
                ";
                $report_columns = ['ID','Rice Name','Quantity','Milled Output','Operator','Date'];
                $report_columns_map = [
                    'ID' => 'id',
                    'Rice Name' => 'rice_name',
                    'Quantity' => 'quantity',
                    'Milled Output' => 'milled_output',
                    'Operator' => 'operator_name',
                    'Date' => 'created_at'
                ];
                $sum_columns = ['quantity','milled_output'];
                $summary_table = 'milling';
                $summary_date_field = 'created_at';
                $summary_value_field = 'milled_output';
                break;

            case 'inventory':
                $query = "
                    SELECT 
                        rice_type,
                        total_25kg,
                        total_50kg,
                        total_5kg,
                        total_kg,
                        snapshot_date AS updated_at,
                        reference_type AS source
                    FROM inventory_history
                    WHERE snapshot_date BETWEEN '$from_esc' AND '$to_esc'
                    ORDER BY snapshot_date ASC
                ";
                $report_columns = ['Rice Type','Total 25kg','Total 50kg','Total 5kg','Total KG','Snapshot Date','Source'];
                $report_columns_map = [
                    'Rice Type' => 'rice_type',
                    'Total 25kg' => 'total_25kg',
                    'Total 50kg' => 'total_50kg',
                    'Total 5kg' => 'total_5kg',
                    'Total KG' => 'total_kg',
                    'Snapshot Date' => 'updated_at',
                    'Source' => 'source'
                ];
                $sum_columns = ['total_25kg','total_50kg','total_5kg','total_kg'];
                $summary_table = '';
                $summary_date_field = '';
                $summary_value_field = '';
                break;

            case 'adjustments':
                $query = "SELECT ial.id AS id, ial.rice_type AS rice_type, ial.adjusted_25kg AS adjusted_25kg, ial.adjusted_50kg AS adjusted_50kg, ial.adjusted_5kg AS adjusted_5kg, ial.reason AS reason, ial.operator_user_id AS operator_user_id, DATE(ial.created_at) AS created_at FROM inventory_adjustments_log ial WHERE DATE(ial.created_at) BETWEEN '$from_esc' AND '$to_esc' ORDER BY ial.created_at ASC";
                $report_columns = ['ID','Rice Type','25kg Adj','50kg Adj','5kg Adj','Reason','Operator','Date'];
                $report_columns_map = [
                    'ID' => 'id',
                    'Rice Type' => 'rice_type',
                    '25kg Adj' => 'adjusted_25kg',
                    '50kg Adj' => 'adjusted_50kg',
                    '5kg Adj' => 'adjusted_5kg',
                    'Reason' => 'reason',
                    'Operator' => 'operator_user_id',
                    'Date' => 'created_at'
                ];
                $sum_columns = ['adjusted_25kg','adjusted_50kg','adjusted_5kg'];
                $summary_table = 'inventory_adjustments_log';
                $summary_date_field = 'created_at';
                $summary_value_field = "(COALESCE(adjusted_25kg,0)*25 + COALESCE(adjusted_50kg,0)*50 + COALESCE(adjusted_5kg,0)*5)";
                break;

            case 'voids':
                $report_columns = ['ID','User','Category','Action Type','Item Name','Size','Quantity','Reason','Date'];
                $report_columns_map = [
                    'ID' => 'id',
                    'User' => 'user_name',
                    'Category' => 'void_category',
                    'Action Type' => 'action_type',
                    'Item Name' => 'item_name',
                    'Size' => 'size',
                    'Quantity' => 'qty',
                    'Reason' => 'void_reason',
                    'Date' => 'created_at'
                ];
                $sum_columns = ['qty'];
                $summary_table = '';
                $summary_date_field = '';
                $summary_value_field = '';
                break;

            case 'products':
                $query = "
                    SELECT 
                        id,
                        rice_type,
                        sack_25kg,
                        sack_50kg,
                        sack_5kg,
                        total_kg,
                        operator_user_id,
                        DATE(created_at) AS created_at,
                        DATE(updated_at) AS updated_at
                    FROM products
                    WHERE DATE(created_at) BETWEEN '$from_esc' AND '$to_esc'
                    ORDER BY created_at ASC
                ";

                $report_columns = [
                    'ID',
                    'Rice Type',
                    '25kg Sacks',
                    '50kg Sacks',
                    '5kg Sacks',
                    'Total KG',
                    'Operator ID',
                    'Created At',
                    'Updated At'
                ];

                $report_columns_map = [
                    'ID' => 'id',
                    'Rice Type' => 'rice_type',
                    '25kg Sacks' => 'sack_25kg',
                    '50kg Sacks' => 'sack_50kg',
                    '5kg Sacks' => 'sack_5kg',
                    'Total KG' => 'total_kg',
                    'Operator ID' => 'operator_user_id',
                    'Created At' => 'created_at',
                    'Updated At' => 'updated_at'
                ];

                $sum_columns = ['sack_25kg','sack_50kg','sack_5kg','total_kg'];
                $summary_table = 'products';
                $summary_date_field = 'created_at';
                $summary_value_field = 'total_kg';
                break;

            default:
                $query = '';
                $error = 'Please select a valid report type.';
                break;
        }

        if ($report_type === 'voids') {
            $q_voids = "
                SELECT 
                    id,
                    user_name,
                    action_type,
                    item_name,
                    size,
                    qty,
                    void_reason,
                    DATE(created_at) AS created_at
                FROM void_logs
                WHERE DATE(created_at) BETWEEN '$from_esc' AND '$to_esc'
                ORDER BY created_at ASC";

            $res_voids = $conn->query($q_voids);

            $report_data = [];
            $report_data['Voided Items'] = $res_voids ? $res_voids->fetch_all(MYSQLI_ASSOC) : [];

            $totals = [];
            foreach ($report_data as $cat => $rows) {
                $sum = 0;
                foreach ($rows as $r) {
                    if (isset($r['qty']) && is_numeric($r['qty'])) $sum += floatval($r['qty']);
                }
                $totals[$cat] = $sum;
            }

            $_SESSION['report_data'] = $report_data;
            $_SESSION['report_columns'] = $report_columns;
            $_SESSION['report_columns_map'] = $report_columns_map;
            $_SESSION['report_type'] = $report_type;
            $_SESSION['report_totals'] = $totals;
        } else {
            if (!empty($query)) {
                $result = $conn->query($query);
                if (!$result) {
                    $error = 'Database error: ' . h($conn->error);
                } else {
                    $report_data = $result->fetch_all(MYSQLI_ASSOC);

                    $totals = [];
                    foreach ($report_data as $r) {
                        if (($r['payment_status'] ?? '') === 'Paid') {
                            foreach ($sum_columns as $k) {
                                if (isset($r[$k]) && is_numeric($r[$k])) {
                                    $totals[$k] = ($totals[$k] ?? 0) + floatval($r[$k]);
                                }
                            }
                        }
                    }

                    if (!empty($summary_table) && !empty($summary_value_field)) {
                        $label_select = "DATE($summary_date_field) AS period_label";
                        $group_by = "DATE($summary_date_field)";
                        if ($frequency === 'daily') {
                            $label_select = "DATE($summary_date_field) AS period_label";
                            $group_by = "DATE($summary_date_field)";
                        } elseif ($frequency === 'weekly') {
                            $label_select = "CONCAT(YEAR($summary_date_field), '-W', WEEK($summary_date_field, 1)) AS period_label";
                            $group_by = "YEAR($summary_date_field), WEEK($summary_date_field, 1)";
                        } elseif ($frequency === 'monthly') {
                            $label_select = "DATE_FORMAT($summary_date_field, '%M %Y') AS period_label";
                            $group_by = "YEAR($summary_date_field), MONTH($summary_date_field)";
                        } elseif ($frequency === 'yearly') {
                            $label_select = "YEAR($summary_date_field) AS period_label";
                            $group_by = "YEAR($summary_date_field)";
                        } else {
                            $label_select = "'Custom Range' AS period_label";
                            $group_by = "1";
                        }

                        $where_tx = '';
                        if ($report_type === 'sales' && !empty($transaction_id)) {
                            $where_tx = " AND transaction_id = '$tx_esc' ";
                        } elseif ($report_type === 'purchases' && !empty($transaction_id)) {
                            $where_tx = " AND (pv_number = '$tx_esc' OR CONCAT('PV-', DATE_FORMAT(purchase_date,'%Y%m%d'), '-', id) = '$tx_esc') ";
                        }

                        $summary_query = "
                            SELECT
                                $label_select,
                                SUM($summary_value_field) AS total_sum
                            FROM $summary_table
                            WHERE DATE($summary_date_field) BETWEEN '$from_esc' AND '$to_esc'
                            $where_tx
                            GROUP BY $group_by
                            ORDER BY MIN($summary_date_field) ASC
                        ";
                        $sr = $conn->query($summary_query);
                        if ($sr) $summary_rows = $sr->fetch_all(MYSQLI_ASSOC);
                    }

                    $_SESSION['report_data'] = $report_data;
                    $_SESSION['report_columns'] = $report_columns;
                    $_SESSION['report_columns_map'] = $report_columns_map;
                    $_SESSION['report_type'] = $report_type;
                    $_SESSION['report_totals'] = $totals;
                }
            }
        }
    }
}

    // --- Blockchain log: Report Generated ---
    if (isset($_POST['generate']) && empty($error)) {
        addBlockchainLogWithFallback($conn, intval($current_user_id), 'Generate Report', $current_user_name, [
            'report_type' => $report_type,
            'from' => $from,
            'to' => $to,
            'frequency' => $frequency,
            'rows' => count($report_data)
        ]);
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports</title>
<!-- Font Awesome (kept as requested) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* === Layout (kept classic colors and style) === */
body {
    margin: 0;
    font-family: Arial, sans-serif;
    display: flex;
    background: #f8f9fa;
    height: 100vh;
    overflow: hidden;
}
.sidebar {
    width: 230px;
    background: #e9e6d9; /* same as before */
    height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: fixed;
    top: 0;
    left: 0;
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
.form-section {
    margin-bottom: 18px;
    padding: 16px;
    background: #f3f3f3;
    border-radius: 10px;
    border: 1px solid rgba(0,0,0,0.03);
    box-shadow: 0 1px 0 rgba(0,0,0,0.02) inset;
}

/* improved grid for aligned inputs */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
    align-items: start;
}

.form-grid > div {
    display: flex;
    flex-direction: column;
}

label {
    font-weight: 600;
    margin-bottom: 5px;
    color: #333;
    font-size: 13px;
}

input[type=date],
input[type=week],
input[type=month],
select,
input[type=text] {
    width: 100%;
    padding: 8px 10px;
    border-radius: 6px;
    border: 1px solid #bbb;
    font-size: 14px;
    box-sizing: border-box;
}

input[type=date]:hover,
input[type=week]:hover,
input[type=month]:hover,
select:hover,
input[type=text]:hover {
    border-color: #6a7a48;
    box-shadow: 0 0 3px rgba(106, 122, 72, 0.18);
}

input[type=date]:focus,
input[type=week]:focus,
input[type=month]:focus,
select:focus,
input[type=text]:focus {
    border-color: #6a7a48;
    outline: none;
    box-shadow: 0 0 3px rgba(106, 122, 72, 0.18);
}

/* button group */
.form-grid .action-row {
    grid-column: 1 / -1;
    display: flex;
    justify-content: flex-start; /* aligns left */
    align-items: flex-start;
    margin-top: 10px;
    gap: 10px;
    padding-left: 0; /* ensure no extra spacing */
}

.form-section form {
    display: block;
}

.form-section {
    margin-bottom: 20px;
}

.form-section .btn {
    margin-right: 8px;
}
.btn {
    padding: 8px 14px;
    background: #6a7a48; /* preserved color */
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    display: inline-flex;
    gap: 8px;
    align-items: center;
    text-decoration: none;
}

.btn.secondary {
    background: #ccc;
    color: #222;
}

.btn:hover { filter: brightness(0.95); transform: translateY(-1px); }

/* table */
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
    margin-top: 15px;
    table-layout: fixed;
}
table th, table td {
    border: 1px solid #ccc;
    padding: 8px;
    text-align: center;
    word-wrap: break-word;
}
table th { background: #f3f3f3; }
table tr:nth-child(even) { background: #f9f9f9; }

/* alerts */
.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: fadeIn 0.25s ease-in-out;
}
.alert.success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
.alert.error { background: #f8d7da; color: #721c24; border-left: 5px solid #dc3545; }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}

/* print */
@media print {
    @page { size: A4 portrait; margin: 15mm; }
    body { font-family: "Arial", sans-serif; color: #000; background: #fff; margin: 0; padding: 0; }
    .sidebar, form, .btn, .form-actions { display: none !important; }
    .main-content { margin-left: 0; padding: 0; }
    th, td { font-size: 12px; }
}
</style>
</head>
<body>

<aside class="sidebar">
  <div>
    <div class="profile">
      <i class="fa-solid fa-file-lines"></i>
      <span><?php echo h($current_role); ?></span>
    </div>
    <ul class="menu">
      <li><a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
      <li><a href="pos.php"><i class="fa-solid fa-dollar"></i> POS</a></li>
      <li><a href="usermanagement.php"><i class="fa-solid fa-users"></i> User Management</a></li>
      <li><a href="buyingpalay.php"><i class="fa-solid fa-wheat-awn"></i> Buying Palay</a></li>
      <li><a href="milling.php"><i class="fa-solid fa-industry"></i> Milling Management</a></li>
      <li><a href="products.php"><i class="fa-solid fa-box"></i> Products & Inventory</a></li>
      <li class="active"><a href="reports.php"><i class="fa-solid fa-file-lines"></i> Reports</a></li>
    <li><a href="index.php"><i class="fa-solid fa-link"></i> Blockchain Log</a></li>
    </ul>
  </div>
  <div class="logout" onclick="window.location.href='logout.php'">
    <i class="fa-solid fa-right-from-bracket"></i> Logout
  </div>
</aside>

<main class="main-content">

<header class="header">
  <h2>REPORTS</h2>
  <div class="user-info">
    <span><?php echo h($current_user_name); ?></span>
  </div>
</header>

<section class="form-section">
  <form method="POST" id="reportForm">
    <div class="form-grid">
      <div>
        <label>Report Type</label>
        <select name="report_type" id="report_type" required>
          <option value="">-- Select Report --</option>
          <option value="sales" <?php if($report_type=='sales') echo 'selected'; ?>>Sales</option>
          <option value="purchases" <?php if($report_type=='purchases') echo 'selected'; ?>>Purchases</option>
          <option value="milling" <?php if($report_type=='milling') echo 'selected'; ?>>Milling</option>
          <option value="inventory" <?php if($report_type=='inventory') echo 'selected'; ?>>Inventory</option>
          <option value="adjustments" <?php if($report_type=='adjustments') echo 'selected'; ?>>Inventory Adjustments</option>
          <option value="voids" <?php if($report_type=='voids') echo 'selected'; ?>>Void Logs</option>
          <option value="products" <?php if($report_type=='products') echo 'selected'; ?>>Products</option>

        </select>
        
      </div>

      <div>
        <label>Frequency</label>
        <select name="frequency" id="frequency" required>
          <option value="daily" <?=($frequency=='daily' ? 'selected' : '')?>>Daily</option>
          <option value="weekly" <?=($frequency=='weekly' ? 'selected' : '')?>>Weekly</option>
          <option value="monthly" <?=($frequency=='monthly' ? 'selected' : '')?>>Monthly</option>
          <option value="yearly" <?=($frequency=='yearly' ? 'selected' : '')?>>Yearly</option>
          <option value="date_range" <?=($frequency=='date_range' ? 'selected' : '')?>>Custom Date Range</option>
        </select>
      </div>

      <div id="date-range-fields">
        <label>From</label>
        <input type="date" name="from_date" id="from_date" value="<?php echo h($from_date); ?>" <?php echo ($frequency!=='yearly' ? 'required' : ''); ?>>
      </div>

      <div id="date-range-fields-2">
        <label>To</label>
        <input type="date" name="to_date" id="to_date" value="<?php echo h($to_date); ?>" <?php echo ($frequency!=='yearly' ? 'required' : ''); ?>>
      </div>

      <div id="week-field" style="display:none;">
        <label>Choose Week</label>
        <input type="week" name="week" id="week" value="<?php echo h($week_picker); ?>">
        <div class="small" style="font-size:12px;color:#666;margin-top:4px;">Week starts Monday</div>
      </div>

      <div id="month-field" style="display:none;">
        <label>Choose Month</label>
        <input type="month" name="month" id="month" value="<?php echo h($month_picker); ?>">
      </div>

      <div id="year-field" style="display:none;">
        <label>Year</label>
        <select name="year">
          <?php 
          $currentYear = date('Y');
          for($y = $currentYear-5; $y <= $currentYear+1; $y++){
              $selected = ($selected_year == $y) ? 'selected' : '';
              echo "<option value='$y' $selected>$y</option>";
          }
          ?>
        </select>
      </div>

      <div id="transaction-field" style="display:none;">
        <label id="transaction-label">Transaction ID (optional)</label>
        <input type="text" name="transaction_id" id="transaction_id" placeholder="">
      </div>
      <div class="action-row">
        <button type="submit" name="generate" class="btn"><i class="fa-solid fa-magnifying-glass"></i> Generate</button>
        <button type="button" class="btn secondary" onclick="resetForm()"><i class="fa-solid fa-rotate-left"></i> Reset</button>
    </div>
  </form>
</section>

<?php if (isset($error)): ?>
    <div class="alert error"><?php echo h($error); ?></div>
<?php endif; ?>

<?php if (!empty($report_data)): ?>
    <div class="report-section panel" style="padding:16px;background:#fff;border-radius:8px;">
        <div class="report-header" style="text-align:center;margin-bottom:12px;">
            <h1 style="margin:0;">DENNIS RICE MILL</h1>
            <div class="small">Brgy. David, San Jose, Tarlac — Contact: 09399059153</div>
            <h3 style="margin-top:8px;"><?php echo h(ucfirst($report_type)); ?> Report</h3>
            <?php if (!empty($transaction_id) && ($report_type === 'sales' || $report_type === 'purchases')): ?>
                <div><strong><?php echo ($report_type === 'purchases' ? 'PV Number' : 'Transaction ID'); ?>:</strong> <?php echo h($transaction_id); ?></div>
            <?php endif; ?>
            <div class="small">Frequency: <?php echo h(ucfirst($frequency)); ?> — Date Range: <?php echo h($from); ?> to <?php echo h($to); ?></div>
            <div class="small">Printed: <?php echo date('Y-m-d H:i:s'); ?></div>
        </div>

        <div style="display:flex;gap:8px;justify-content:flex-end;margin-bottom:10px;">
            <button onclick="printTable()" class="btn"><i class="fa-solid fa-print"></i> Print</button>
            <a class="btn" href="?export=csv" style="text-decoration:none;color:#fff;"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        </div>

        <?php if ($report_type === 'voids'): ?>
            <?php foreach ($report_data as $category => $rows): ?>
                <h3 style="margin-top:8px;color:#6a7a48;"><?php echo h($category); ?> (<?php echo count($rows); ?>)</h3>
                <?php if (!empty($rows)): ?>
                    <table>
                        <thead>
                            <tr>
                                <?php foreach ($report_columns as $col): echo "<th>".h($col)."</th>"; endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?php echo h($row['id'] ?? ''); ?></td>
                                    <td><?php echo h($row['user_name'] ?? ''); ?></td>
                                    <td><?php echo h($category); ?></td>
                                    <td><?php echo h($row['action_type'] ?? ''); ?></td>
                                    <td><?php echo h($row['item_name'] ?? ''); ?></td>
                                    <td><?php echo h($row['size'] ?? ''); ?></td>
                                    <td style="text-align:right;"><?php echo isset($row['qty']) ? h(number_format($row['qty'],2)) : ''; ?></td>
                                    <td><?php echo h($row['void_reason'] ?? ''); ?></td>
                                    <td><?php echo h($row['created_at'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php 
                                // show category total if exists in session totals
                                $tot = $_SESSION['report_totals'] ?? [];
                                if (isset($tot[$category])): ?>
                                <tr style="font-weight:bold;background:#d3d3d3;">
                                    <td colspan="6">Total</td>
                                    <td style="text-align:right;"><?php echo h(number_format($tot[$category],2)); ?></td>
                                    <td colspan="2"></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align:center;margin:10px 0;">No records found for <?php echo h($category); ?></div>
                <?php endif; ?>
            <?php endforeach; ?>

        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <?php foreach ($report_columns as $col): echo "<th>".h($col)."</th>"; endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data as $row): ?>
                        <tr>
                            <?php foreach ($report_columns_map as $col => $key):
                                $cell = $row[$key] ?? '';
                                if (is_numeric($cell) && in_array($key, $sum_columns)) {
                                    $cell_display = number_format((float)$cell, 2);
                                } else {
                                    $cell_display = $cell;
                                }
                            ?>
                                <td><?php echo h($cell_display); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!empty($_SESSION['report_totals']) && is_array($_SESSION['report_totals'])): $tot = $_SESSION['report_totals']; ?>
                        <tr style="font-weight:bold;background:#d3d3d3;">
                            <?php
                            foreach ($report_columns_map as $hdr => $key) {
                                if (isset($tot[$key])) echo "<td>".h(number_format($tot[$key],2))."</td>";
                                else echo "<td></td>";
                            }
                            ?>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($summary_rows) && $report_type !== 'voids'): ?>
            <h3 style="margin-top:20px;color:#6a7a48;">Summary Totals (<?php echo h(ucfirst($frequency)); ?>)</h3>
            <table>
                <thead><tr><th>Period</th><th>Total</th></tr></thead>
                <tbody>
                    <?php $grand=0; foreach ($summary_rows as $sr): $grand += floatval($sr['total_sum']); ?>
                        <tr><td><?php echo h($sr['period_label']); ?></td><td style="text-align:right;"><?php echo h(number_format($sr['total_sum'],2)); ?></td></tr>
                    <?php endforeach; ?>
                    <tr style="font-weight:bold;background:#d3d3d3;"><td>Grand Total</td><td style="text-align:right;"><?php echo h(number_format($grand,2)); ?></td></tr>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="report-footer" style="display:flex;gap:20px;margin-top:24px;">
            <div class="signature" style="flex:1;text-align:center;">
                <div class="signature-line" style="margin:40px auto 8px auto;border-top:1px solid #333;width:80%;"></div>
                <div>Prepared by:<br><?php echo h($current_user_name); ?></div>
            </div>
            <div class="signature" style="flex:1;text-align:center;">
                <div class="signature-line" style="margin:40px auto 8px auto;border-top:1px solid #333;width:80%;"></div>
                <div>Checked by:</div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
// UI behavior: show/hide appropriate date controls, transaction field
(function(){
    const rpt = document.getElementById('report_type');
    const freq = document.getElementById('frequency');

    const dateFrom = document.getElementById('from_date');
    const dateTo = document.getElementById('to_date');
    const dateRange1 = document.getElementById('date-range-fields');
    const dateRange2 = document.getElementById('date-range-fields-2');

    const weekField = document.getElementById('week-field');
    const monthField = document.getElementById('month-field');
    const yearField = document.getElementById('year-field');

    const txField = document.getElementById('transaction-field');
    const txInput = document.getElementById('transaction_id');
    const txLabel = document.getElementById('transaction-label');

    function updateControls() {
        const f = freq ? freq.value : 'date_range';
        // Show corresponding controls
        if (f === 'daily' || f === 'date_range') {
            dateRange1.style.display = 'block';
            dateRange2.style.display = 'block';
            dateFrom.required = true;
            dateTo.required = true;
            weekField.style.display = 'none';
            monthField.style.display = 'none';
            yearField.style.display = 'none';
        } else if (f === 'weekly') {
            dateRange1.style.display = 'none';
            dateRange2.style.display = 'none';
            dateFrom.required = false;
            dateTo.required = false;
            weekField.style.display = 'block';
            monthField.style.display = 'none';
            yearField.style.display = 'none';
        } else if (f === 'monthly') {
            dateRange1.style.display = 'none';
            dateRange2.style.display = 'none';
            dateFrom.required = false;
            dateTo.required = false;
            weekField.style.display = 'none';
            monthField.style.display = 'block';
            yearField.style.display = 'none';
        } else if (f === 'yearly') {
            dateRange1.style.display = 'none';
            dateRange2.style.display = 'none';
            dateFrom.required = false;
            dateTo.required = false;
            weekField.style.display = 'none';
            monthField.style.display = 'none';
            yearField.style.display = 'block';
        } else {
            dateRange1.style.display = 'block';
            dateRange2.style.display = 'block';
            weekField.style.display = 'none';
            monthField.style.display = 'none';
            yearField.style.display = 'none';
            dateFrom.required = true;
            dateTo.required = true;
        }

        // Show tx field only for sales and purchases
        const r = rpt ? rpt.value : '';
        if (r === 'sales') {
            txField.style.display = 'block';
            txLabel.textContent = 'Transaction ID (optional)';
            txInput.placeholder = 'Enter Transaction ID';
        } else if (r === 'purchases') {
            txField.style.display = 'block';
            txLabel.textContent = 'PV Number (optional)';
            txInput.placeholder = 'Enter PV Number (e.g. PV-20250101-12)';
        } else {
            txField.style.display = 'none';
            if (txInput) txInput.value = '';
        }
    }

    if (freq) freq.addEventListener('change', updateControls);
    if (rpt) rpt.addEventListener('change', updateControls);
    window.addEventListener('DOMContentLoaded', updateControls);
})();

function resetForm(){
    const form = document.getElementById('reportForm');
    form.reset();
    // trigger controls update
    const evt = new Event('change');
    document.getElementById('frequency').dispatchEvent(evt);
    document.getElementById('report_type').dispatchEvent(evt);
}

function printTable(){
    const reportSection = document.querySelector('.report-section');
    if(!reportSection) return alert('No report to print.');
    const originalBody = document.body.innerHTML;
    document.body.innerHTML = reportSection.outerHTML;
    window.print();
    document.body.innerHTML = originalBody;
    window.location.reload();
}
</script>

</main>
</body>
</html>