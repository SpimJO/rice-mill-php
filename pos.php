<?php
date_default_timezone_set('Asia/Manila'); 
// pos.php - Full POS with inventory update and receipt display

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include "db.php";
include "blockchain_api.php";
session_start();

// Require login for POS access
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// --- Prevent back button showing previous checkout ---
if (isset($_SESSION['receipt_data']) && !isset($_GET['print'])) {
    unset($_SESSION['receipt_data']); // Clear previous receipt
    header("Location: pos.php");
    exit;
}

// === Current user info ===
$current_user_id = $_SESSION['user_id'] ?? 1;
$current_user_name = $_SESSION['name'] ?? 'Operator';
$current_role = $_SESSION['role'] ?? 'Operator';

// Simple blockchain logger helper
function logBlockchain($conn, $userId, $action, $target, $data) {
    addBlockchainLogWithFallback($conn, $userId, $action, $target, $data);
}


// Load receipt after redirect
$receiptData = null;
if (isset($_GET['print']) && isset($_SESSION['receipt_data'])) {
    $receiptData = $_SESSION['receipt_data'];
    unset($_SESSION['receipt_data']);

    // Log reprint request when returning to print view
    if (!empty($receiptData['transaction_id'])) {
        logBlockchain($conn, $current_user_id, 'POS_RECEIPT_PRINT', $receiptData['transaction_id'], [
            'transaction_id' => $receiptData['transaction_id'],
            'operator' => $current_user_name,
            'source' => 'POS_AUTO_PRINT'
        ]);
    }
}

// === Load inventory ===
$rice_types = [];
$res = $conn->query("SELECT * FROM inventory ORDER BY rice_type ASC");
while ($r = $res->fetch_assoc()) {
    $rice_types[] = $r;
}

// Flash messages
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// === Handle Checkout (form submit) ===
if (isset($_POST['checkout'])) {
    $discount_percent = floatval($_POST['discount'] ?? 0.0); // percentage input
    $cart_json = $_POST['cart_json'] ?? '';
    $items = json_decode($cart_json, true);

    if (!$items || !is_array($items)) {
        $_SESSION['error_message'] = "Invalid cart data.";
    } else {
        $subtotal = 0;
        foreach ($items as $it) {
            $subtotal += $it['unitPrice'] * $it['qty'];
        }

        // Convert percentage to peso
        $discount_value = round($subtotal * ($discount_percent / 100), 2);
        $subtotal_after_discount = max(0.0, $subtotal - $discount_value);
        $tax = 0; // no tax applied
        $total = round($subtotal_after_discount, 2);
        // Generate unique transaction ID
        $transaction_id = date('YmdHis') . '-' . rand(1000, 9999);

        // Insert each line into sales table and update inventory
        foreach ($items as $it) {
            $rice_type = $it['itemName'];
            $size = $it['size'];
            $qty = intval($it['qty']);
            $price = floatval($it['unitPrice']);
            if ($price <= 0) {
    $_SESSION['error_message'] = "Cannot sell $rice_type ($size) — no price is set.";
    header("Location: pos.php");
    exit;
}
            $line_total = $price * $qty;
            if ($qty <= 0) continue;

            // Determine inventory column
            $col_qty = $size === '25kg' ? 'total_25kg' : ($size === '50kg' ? 'total_50kg' : 'total_5kg');

            // Check stock
            $stmt = $conn->prepare("SELECT $col_qty FROM inventory WHERE rice_type = ?");
            $stmt->bind_param("s", $rice_type);
            $stmt->execute();
            $stmt->bind_result($stock);
            $found = $stmt->fetch();
            $stmt->close();

            if (!$found || $stock < $qty) {
                $_SESSION['error_message'] = "Insufficient stock for $rice_type ($size).";
                header("Location: pos.php");
                exit;
            }

            // Deduct inventory
            $stmt = $conn->prepare("UPDATE inventory SET $col_qty = $col_qty - ? WHERE rice_type = ?");
            $stmt->bind_param("is", $qty, $rice_type);
            $stmt->execute();
            $stmt->close();

            // Prepare sale entry
            $s25 = ($size === '25kg') ? $qty : 0;
            $s50 = ($size === '50kg') ? $qty : 0;
            $s5 = ($size === '5kg') ? $qty : 0;

            $stmt = $conn->prepare("
                INSERT INTO sales 
                (transaction_id, rice_type, sack_25kg, sack_50kg, sack_5kg, subtotal, discount, tax, total, operator_user_id, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,NOW())
            ");
            $stmt->bind_param("ssiiiddddi", $transaction_id, $rice_type, $s25, $s50, $s5, $line_total, $discount_value, $tax, $total, $current_user_id);
            $stmt->execute();
            $stmt->close();
        }

        $_SESSION['success_message'] = "Sale recorded successfully!";
        $_SESSION['receipt_data'] = [
            'items' => $items,
            'subtotal' => number_format($subtotal, 2),
            'discount' => number_format($discount_value, 2),
            'total' => number_format($total, 2),
            'operator' => $current_user_name,
            'date' => date("Y-m-d H:i:s"),
            'transaction_id' => $transaction_id
        ];

        // Log POS checkout to blockchain
        logBlockchain($conn, $current_user_id, 'POS_CHECKOUT', $transaction_id, [
            'transaction_id' => $transaction_id,
            'items' => $items,
            'subtotal' => $subtotal,
            'discount_percent' => $discount_percent,
            'discount_value' => $discount_value,
            'total' => $total,
            'operator' => $current_user_name
        ]);

        // Redirect to prevent form resubmission
        header("Location: pos.php?print=1");
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>POS</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* --- Styles kept same as original --- */
:root { --accent:#2e7d32; --muted:#8a8f93; --bg:#f4f6f8; --card:#fff; --card-border:#eef2ee; }
*{box-sizing:border-box} body{margin:0;font-family:Inter,Segoe UI,system-ui,Arial;background:var(--bg);color:#222}
.app{ max-width:1200px;margin:18px auto;padding:18px;display:grid;grid-template-columns:1fr 380px;gap:18px;align-items:start; }
.left{background:var(--card);border-radius:12px;padding:18px;border:1px solid var(--card-border);box-shadow:0 6px 18px rgba(19,19,19,0.03);display:flex;flex-direction:column;min-height:70vh;overflow:hidden;}
.topbar{display:flex;gap:12px;align-items:center;margin-bottom:12px}
.search{flex:1;display:flex;gap:8px;align-items:center;background:#f7faf8;padding:10px;border-radius:12px;border:1px solid #eef2ee}
.search input{border:0;background:transparent;outline:none;font-size:15px;width:100%}
.products{margin-top:8px;display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;overflow:auto;padding-bottom:12px}
.product-card{background:linear-gradient(180deg,#fff,#fbfffb);border:1px solid #eef5ee;border-radius:12px;padding:12px;cursor:pointer;transition:transform .12s,box-shadow .12s;display:flex;flex-direction:column;gap:8px;}
.product-card:hover{transform:translateY(-4px);box-shadow:0 10px 20px rgba(16,24,16,0.06)}
.product-title{font-weight:700;font-size:15px;color:#16391a}
.product-meta{font-size:13px;color:var(--muted)}
.price-badge{background:var(--accent);color:#fff;padding:6px 8px;border-radius:8px;font-weight:700;font-size:13px;display:inline-block}
.small-badge{background:#f0f8f0;color:var(--accent);padding:6px 8px;border-radius:8px;font-weight:600}
.right{display:flex;flex-direction:column;gap:12px}
.cart{background:var(--card);border-radius:12px;padding:14px;border:1px solid var(--card-border);box-shadow:0 6px 18px rgba(19,19,19,0.03);display:flex;flex-direction:column;min-height:52vh}
.cart-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.cart-header .operator {text-align:right}
.cart-table{width:100%;border-collapse:collapse;font-size:14px}
.cart-table th,.cart-table td{padding:8px;border-bottom:1px solid #f0f0f0;text-align:left}
.cart-table th{font-size:13px;color:var(--muted)}
.qty-input{width:70px;padding:6px;border-radius:8px;border:1px solid #e6e6e6}
.icon-btn{background:transparent;border:0;color:var(--muted);cursor:pointer}
.summary {background:var(--card);border-radius:12px;padding:16px;border:1px solid var(--card-border);box-shadow:0 6px 18px rgba(19,19,19,0.03)}
.summary-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;color:#333}
.summary-row.small{font-size:13px;color:var(--muted)}
.summary-total{font-size:20px;font-weight:800;color:var(--accent);margin-top:6px}
.checkout-btn{background:var(--accent);color:#fff;padding:12px;border-radius:10px;border:0;width:100%;font-weight:700;cursor:pointer}
.checkout-btn:disabled{background:#b7d3b0;cursor:not-allowed}
.controls {display:flex;gap:8px;align-items:center;margin-top:12px}
.controls .discount {flex:1}
.controls input[type="number"]{width:100%;padding:8px;border-radius:8px;border:1px solid #e6e6e6}
.controls .clear-cart {background:#f8f9fa;border:1px solid #e6e6e6;padding:8px;border-radius:8px;cursor:pointer}
.controls .clear-cart:hover {background:#ffecec}
#qtyModal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.35);justify-content:center;align-items:center;z-index:9999}
#qtyModal .modal-content{background:#fff;padding:18px;border-radius:12px;width:360px;box-shadow:0 12px 40px rgba(0,0,0,0.25)}
#qtyModal h3{margin:0 0 10px;color:var(--accent)}
.modal-row{display:flex;justify-content:space-between;align-items:center;margin:8px 0}
.modal-row input{width:110px;padding:8px;border-radius:8px;border:1px solid #e6e6e6}
.modal-stock{font-size:12px;color:var(--muted);margin-left:8px}
.modal-actions{display:flex;gap:10px;margin-top:14px}
.modal-actions button{flex:1;padding:10px;border-radius:8px;border:0;font-weight:700;cursor:pointer}
.modal-add{background:var(--accent);color:#fff}
.modal-cancel{background:#ddd;color:#333}
.empty{color:var(--muted);text-align:center;padding:18px}
.flash {max-width:1200px;margin:10px auto;padding:12px;border-radius:8px}
.flash.success {background:#e9f8ee;color:#1b5e20;border:1px solid #c7efcf}
.flash.error {background:#fdecea;color:#7a261f;border:1px solid #f5c6c6}
@media(max-width:980px){ .app{grid-template-columns:1fr; padding:12px} .left{height:auto} .right{position:relative} }
</style>
</head>
<body>

<?php if($success_message): ?>
    <div class="flash success"><?= htmlspecialchars($success_message) ?></div>
<?php endif; ?>
<?php if($error_message): ?>
    <div class="flash error"><?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<div class="app">
    <!-- LEFT: Product grid -->
    <div class="left">
        <div class="topbar">
            <div class="search">
                <i class="fa fa-search" style="color:var(--muted);margin-right:8px"></i>
                <input id="searchInput" placeholder="Search rice type..." />
            </div>
        </div>
        <div id="products" class="products">
            <?php foreach ($rice_types as $r): ?>
                <div class="product-card" tabindex="0"
                    data-name="<?= htmlspecialchars($r['rice_type'], ENT_QUOTES) ?>"
                    data-p25="<?= htmlspecialchars($r['price_25kg']) ?>"
                    data-p50="<?= htmlspecialchars($r['price_50kg']) ?>"
                    data-p5="<?= htmlspecialchars($r['price_5kg']) ?>"
                    data-s25="<?= htmlspecialchars($r['total_25kg']) ?>"
                    data-s50="<?= htmlspecialchars($r['total_50kg']) ?>"
                    data-s5="<?= htmlspecialchars($r['total_5kg']) ?>"
                    onclick="openQtyModal(this)">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <div>
                            <div class="product-title"><?= htmlspecialchars($r['rice_type']) ?></div>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;margin-top:10px">
                        <div class="small-badge">25kg: <?= $r['total_25kg'] ?></div>
                        <div class="small-badge">50kg: <?= $r['total_50kg'] ?></div>
                        <div class="small-badge">5kg: <?= $r['total_5kg'] ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- RIGHT: Cart & checkout -->
    <div class="right">
        <div class="cart">
            <div class="cart-header">
                <div>
                    <strong style="font-size:16px">Cart</strong><br>
                    <small style="color:var(--muted)">Multiple rice types supported</small>
                </div>
                <div class="operator">
                    <small style="color:var(--muted)"><?= htmlspecialchars($current_role) ?></small><br>
                    <strong><?= htmlspecialchars($current_user_name) ?></strong>

                           <?php
                        $$current_role = strtolower($_SESSION['role']);
                        ?>
                        <div style="margin-top:8px">
<?php if (strtolower($current_role) === 'cashier'): ?>
    <!-- Cashier: Logout only -->
    <form method="post" action="logout.php" style="display:inline-block; margin-right:8px;">
        <button type="submit" class="icon-btn"
            style="background:#f8f9fa;border:1px solid #e6e6e6;padding:6px 8px;
                   border-radius:6px;cursor:pointer">
            Logout
        </button>
    </form>

<?php else: ?>
    <!-- Admin: Back + Logout -->
    <form method="get" action="dashboard.php" style="display:inline-block; margin-right:8px;">
        <button type="submit" class="icon-btn"
            style="background:#e8f5e9;border:1px solid #c8e6c9;padding:6px 8px;
                   border-radius:6px;cursor:pointer">
            Back to Admin Dashboard
        </button>
    </form>

    <form method="post" action="logout.php" style="display:inline-block;">
        <button type="submit" class="icon-btn"
            style="background:#f8f9fa;border:1px solid #e6e6e6;padding:6px 8px;
                   border-radius:6px;cursor:pointer">
            Logout
        </button>
    </form>
<?php endif; ?>
</div>

                    </form>
                </div>
            </div>
            <div style="overflow:auto; flex:1;">
                <table class="cart-table" id="cartTable">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Unit</th>
                            <th style="width:110px">Qty</th>
                            <th style="text-align:right;width:110px">Total</th>
                            <th style="width:36px"></th>
                        </tr>
                    </thead>
                    <tbody id="cartBody">
                        <tr class="empty-row">
                            <td colspan="5" class="empty">Cart is empty — click a product card to start</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="controls">
                <div class="discount">
                    <div style="font-size:13px;color:var(--muted);margin-bottom:6px">Discount (%)</div>
                    <input id="discountInput" type="number" value="0" min="0" max="100" step="0.01">
                </div>
                <button class="clear-cart" title="Void entire cart" onclick="voidCart()">
                    <i class="fa fa-ban" style="color:#b02a37"></i> Void Cart
                </button>
            </div>
        </div>

        <div class="summary">
            <div class="summary-row small"><div>Subtotal</div><div id="subtotalDisplay">₱0.00</div></div>
            <div class="summary-row small"><div>Discount</div><div id="discountDisplay">₱0.00</div></div>
            <div class="summary-row"><div style="font-weight:700">Total</div><div id="totalDisplay" class="summary-total">₱0.00</div></div>
            <form method="POST" id="checkoutForm" onsubmit="return handleCheckoutSubmit(event)">
                <input type="hidden" name="rice_type" id="hidden_rice_type">
                <input type="hidden" name="qty_25kg" id="hidden_qty_25">
                <input type="hidden" name="qty_50kg" id="hidden_qty_50">
                <input type="hidden" name="qty_5kg" id="hidden_qty_5">
                <input type="hidden" name="discount" id="hidden_discount" value="0">
                <button type="submit" name="checkout" id="checkoutBtn" class="checkout-btn" disabled>Checkout</button>
            </form>
            <div class="footer-note" style="margin-top:8px;font-size:13px;color:var(--muted)">
                Tip: click a product card, enter quantities in the modal, then Checkout.
            </div>
        </div>
    </div>
</div>

<!-- Quantity Modal -->
<div id="qtyModal">
    <div class="modal-content" role="dialog" aria-modal="true">
        <h3 id="modalTitle">Add Quantity</h3>
    <div class="modal-row">
  <div>
    <strong>25kg</strong>
    <div class="modal-stock" id="stock25"></div>
    <div class="modal-price" id="price25" style="font-size:12px;color:#1b5e20"></div>
  </div>
  <input type="number" id="q25" min="0" value="0">
</div>

<div class="modal-row">
  <div>
    <strong>50kg</strong>
    <div class="modal-stock" id="stock50"></div>
    <div class="modal-price" id="price50" style="font-size:12px;color:#1b5e20"></div>
  </div>
  <input type="number" id="q50" min="0" value="0">
</div>

<div class="modal-row">
  <div>
    <strong>5kg</strong>
    <div class="modal-stock" id="stock5"></div>
    <div class="modal-price" id="price5" style="font-size:12px;color:#1b5e20"></div>
  </div>
  <input type="number" id="q5" min="0" value="0">
</div>

        <div class="modal-actions">
            <button class="modal-add" onclick="addFromModal()">Add to Cart</button>
            <button class="modal-cancel" onclick="closeQtyModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- Admin Password Modal -->
<div id="adminPwdModal" style="
  display:none; position:fixed; top:0; left:0; right:0; bottom:0;
  background:rgba(0,0,0,0.4); justify-content:center; align-items:center; 
  z-index:999999;">
    <div style="
        background:#fff; padding:20px; width:300px; border-radius:10px;
        box-shadow:0 6px 20px rgba(0,0,0,0.25); text-align:center;">
        
        <h3 style="margin-top:0; color:#2e7d32">Admin Password</h3>
        <p style="font-size:14px; color:#555">Enter admin password to continue</p>

        <input type="password" id="adminPwdInput"
            style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; margin-bottom:15px; font-size:16px">

        <div style="display:flex; gap:10px;">
            <button onclick="submitAdminPassword()"
                style="flex:1; background:#2e7d32; color:#fff; border:0; padding:10px; border-radius:6px; font-weight:bold;">
                Confirm
            </button>

            <button onclick="closeAdminPwdModal()"
                style="flex:1; background:#ccc; border:0; padding:10px; border-radius:6px;">
                Cancel
            </button>
        </div>

        <input type="hidden" id="adminPwdCallbackHolder">
    </div>
</div>


<script>
// === JS Cart Logic & Modal Handling ===

let cart = [];
let activeRiceType = null;
let selectedProduct = null;

const cartBody = document.getElementById('cartBody');
const subtotalDisplay = document.getElementById('subtotalDisplay');
const totalDisplay = document.getElementById('totalDisplay');
const discountDisplay = document.getElementById('discountDisplay');
const discountInput = document.getElementById('discountInput');
const checkoutBtn = document.getElementById('checkoutBtn');

// --- Open Quantity Modal ---
function openQtyModal(card) {
  selectedProduct = card;
  document.getElementById('modalTitle').textContent = card.dataset.name;

  // Reset quantities
  document.getElementById('q25').value = 0;
  document.getElementById('q50').value = 0;
  document.getElementById('q5').value = 0;

  // Show stock
  document.getElementById('stock25').textContent = "Available: " + (card.dataset.s25 || 0);
  document.getElementById('stock50').textContent = "Available: " + (card.dataset.s50 || 0);
  document.getElementById('stock5').textContent = "Available: " + (card.dataset.s5 || 0);

  // Show prices beside availability
  const p25 = parseFloat(card.dataset.p25) || 0;
  const p50 = parseFloat(card.dataset.p50) || 0;
  const p5 = parseFloat(card.dataset.p5) || 0;
  document.getElementById('price25').textContent = "Price: ₱" + (p25 > 0 ? p25.toFixed(2) : "N/A");
  document.getElementById('price50').textContent = "Price: ₱" + (p50 > 0 ? p50.toFixed(2) : "N/A");
  document.getElementById('price5').textContent = "Price: ₱" + (p5 > 0 ? p5.toFixed(2) : "N/A");

  // Show modal
  document.getElementById('qtyModal').style.display = 'flex';
  setTimeout(() => document.getElementById('q25').focus(), 120);
}

// --- Close Quantity Modal ---
function closeQtyModal() {
    document.getElementById('qtyModal').style.display = 'none';
}

// --- Add Items From Modal to Cart ---
function addFromModal() {
    if (!selectedProduct) { closeQtyModal(); return; }

    const name = selectedProduct.dataset.name;
    const p25 = parseFloat(selectedProduct.dataset.p25) || 0;
    const p50 = parseFloat(selectedProduct.dataset.p50) || 0;
    const p5 = parseFloat(selectedProduct.dataset.p5) || 0;
    const s25 = parseInt(selectedProduct.dataset.s25) || 0;
    const s50 = parseInt(selectedProduct.dataset.s50) || 0;
    const s5 = parseInt(selectedProduct.dataset.s5) || 0;

    let q25 = parseInt(document.getElementById('q25').value) || 0;
    let q50 = parseInt(document.getElementById('q50').value) || 0;
    let q5 = parseInt(document.getElementById('q5').value) || 0;

    if (q25 + q50 + q5 <= 0) {
        alert("Enter at least one quantity.");
        return;
    }

    // Check if cart quantities + new quantities exceed stock
    let inCart25 = 0, inCart50 = 0, inCart5 = 0;
    cart.forEach(it => {
        if (it.itemName === name) {
            if (it.size === '25kg') inCart25 += it.qty;
            if (it.size === '50kg') inCart50 += it.qty;
            if (it.size === '5kg') inCart5 += it.qty;
        }
    });

    if (inCart25 + q25 > s25 || inCart50 + q50 > s50 || inCart5 + q5 > s5) {
        alert("One or more quantities exceed available stock.");
        return;
    }

    if (p25 <= 0 && q25 > 0) {
    alert("Cannot add 25kg of " + name + " — price not set.");
    return;
}
if (p50 <= 0 && q50 > 0) {
    alert("Cannot add 50kg of " + name + " — price not set.");
    return;
}
if (p5 <= 0 && q5 > 0) {
    alert("Cannot add 5kg of " + name + " — price not set.");
    return;
}

if (q25 > 0) addOrUpdateCartItem('25kg', p25, q25, name);
if (q50 > 0) addOrUpdateCartItem('50kg', p50, q50, name);
if (q5 > 0) addOrUpdateCartItem('5kg', p5, q5, name);


    activeRiceType = name;
    renderCart();
    closeQtyModal();
}

// --- Add or Update Cart Item ---
function addOrUpdateCartItem(size, price, qty, name) {
    const idx = cart.findIndex(it => it.size === size && it.itemName === name);
    if (idx >= 0) {
        cart[idx].qty += qty;
    } else {
        cart.push({ size, unitPrice: price, qty, itemName: name });
    }
}

// --- Render Cart ---
function renderCart() {
    cartBody.innerHTML = '';
    if (cart.length === 0) {
        cartBody.innerHTML = '<tr class="empty-row"><td colspan="5" class="empty">Cart is empty — click a product card to start</td></tr>';
        checkoutBtn.disabled = true;
        activeRiceType = null;
        updateSummary();
        return;
    }

    cart.forEach((it, i) => {
        const total = it.unitPrice * it.qty;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${it.itemName}</td>
            <td>${it.size}</td>
            <td>${it.qty}</td>
            <td style="text-align:right">₱${total.toFixed(2)}</td>
            <td><button onclick="voidCartItem(${i})" class="icon-btn"><i class="fa fa-ban"></i></button></td>
        `;
        cartBody.appendChild(tr);
    });

    checkoutBtn.disabled = false;
    updateSummary();
}

// --- Update Summary ---
function updateSummary() {
    const subtotal = cart.reduce((sum, i) => sum + (i.unitPrice * i.qty), 0);
    const discountPercent = parseFloat(discountInput.value) || 0;
    const discountValue = subtotal * (discountPercent / 100);
    const total = subtotal - discountValue;

    subtotalDisplay.innerText = '₱' + subtotal.toFixed(2);
    discountDisplay.innerText = '₱' + discountValue.toFixed(2);
    totalDisplay.innerText = '₱' + total.toFixed(2);
}

discountInput.addEventListener('input', updateSummary);

// --- Clear Entire Cart ---
function clearCart() {
    if (confirm("Clear cart?")) {
        cart = [];
        renderCart();
    }
}

// --- Handle Checkout Form Submit ---
function handleCheckoutSubmit(e) {
    if (cart.length === 0) {
        alert("Cart is empty.");
        return false;
    }

    const cartJson = JSON.stringify(cart);
    const form = document.getElementById('checkoutForm');
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'cart_json';
    input.value = cartJson;
    form.appendChild(input);

    document.getElementById('hidden_discount').value = discountInput.value || 0;
    return true;
}

// --- Admin Password Verification for Voids ---
let adminPasswordCallback = null;

function promptAdminPassword(callback) {
    adminPasswordCallback = callback;
    document.getElementById("adminPwdModal").style.display = "flex";
    document.getElementById("adminPwdInput").value = "";
    setTimeout(() => {
        document.getElementById("adminPwdInput").focus();
    }, 100);
}

function closeAdminPwdModal() {
    document.getElementById("adminPwdModal").style.display = "none";
}

function submitAdminPassword() {
    const pwd = document.getElementById("adminPwdInput").value;

    fetch('verify_admin.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'password=' + encodeURIComponent(pwd)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeAdminPwdModal();
            if (adminPasswordCallback) adminPasswordCallback();
        } else {
            alert("Invalid admin password!");
        }
    });
}

// --- Void Single Item ---
function voidCartItem(idx) {
    const item = cart[idx];
    const reason = prompt("Enter reason for voiding this item:");
    if (!reason) return;

    promptAdminPassword(() => {
        fetch('record_void.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'ITEM',
                item_name: item.itemName,
                size: item.size,
                qty: item.qty,
                reason: reason
            })
        });
        cart.splice(idx, 1);
        renderCart();
    });
}

// --- Void Entire Cart ---
function voidCart() {
    const reason = prompt("Enter reason for voiding entire cart:");
    if (!reason) return;

    promptAdminPassword(() => {
        fetch('record_void.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'CART',
                reason: reason,
                items: cart
            })
        });

        cart = [];
        renderCart();
    });
}

<?php if($receiptData): ?>
// --- Professional Receipt Layout ---
const LINE_WIDTH = 42; // Adjust to printer

function centerLine(text) {
    const pad = Math.floor((LINE_WIDTH - text.length) / 2);
    return ' '.repeat(Math.max(pad,0)) + text;
}

function rightAlign(text, width = LINE_WIDTH) {
    return ' '.repeat(Math.max(width - text.length, 0)) + text;
}

let receiptLines = [];

// HEADER
receiptLines.push(centerLine("DENNIS RICE MILL"));
receiptLines.push(centerLine("Brgy. David, San Jose, Tarlac"));
receiptLines.push(centerLine("Contact: 09399059153"));
receiptLines.push('='.repeat(LINE_WIDTH));

// DATE, OPERATOR, TRANSACTION #
receiptLines.push(`Date: <?= $receiptData['date'] ?>`);
receiptLines.push(`Operator: <?= $receiptData['operator'] ?>`);
receiptLines.push(`Transaction #: <?= $receiptData['transaction_id'] ?>`);
receiptLines.push('-'.repeat(LINE_WIDTH));

// ITEMS
receiptLines.push("Item               Size  Qty   Total");
receiptLines.push('-'.repeat(LINE_WIDTH));
<?php foreach($receiptData['items'] as $it): ?>
const itemName = <?= json_encode(substr(htmlspecialchars($it['itemName']),0,15)) ?>;
const size = <?= json_encode(substr(htmlspecialchars($it['size']),0,5)) ?>;
const qty = <?= $it['qty'] ?>;
const total = '₱<?= number_format($it['unitPrice'] * $it['qty'],2) ?>';
receiptLines.push(
    itemName.padEnd(17) + 
    size.padEnd(6) + 
    String(qty).padEnd(5) + 
    rightAlign(total, 14)
);
<?php endforeach; ?>

receiptLines.push('-'.repeat(LINE_WIDTH));

// TOTALS
receiptLines.push(rightAlign(`Subtotal: ₱<?= $receiptData['subtotal'] ?>`));
receiptLines.push(rightAlign(`Discount: ₱<?= $receiptData['discount'] ?>`));
receiptLines.push(rightAlign(`TOTAL: ₱<?= $receiptData['total'] ?>`));
receiptLines.push('='.repeat(LINE_WIDTH));

// FOOTER
receiptLines.push(centerLine("Thank you for your purchase!"));
receiptLines.push(centerLine("Please come again."));
receiptLines.push(centerLine("Have a nice day!"));

const autoReceipt = receiptLines.join("\n");

// --- Send to Printer ---
(async () => {
  try {
        const res = await fetch('print_receipt.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                receiptText: autoReceipt,
                transactionId: <?= json_encode($receiptData['transaction_id']) ?>,
                source: 'POS_AUTO_PRINT'
            })
        });
        const text = await res.text();
        if (res.ok) {
            alert(text);
        } else {
            alert(text || 'Print error');
        }
  } catch (err) {
    alert('Connection error: ' + err);
  }
})();
<?php endif; ?>



</script>

</body>
</html>
