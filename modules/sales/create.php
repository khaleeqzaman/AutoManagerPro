<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('sales.create');

Auth::check();
if (!Auth::hasRole(['Admin', 'Manager', 'Salesperson'])) {
    setFlash('error', 'Permission denied.');
    redirect('modules/sales/index.php');
}

$db = Database::getInstance();

// Pre-fill from car_id or lead_id
$preCarId  = (int)($_GET['car_id']  ?? 0);
$preLeadId = (int)($_GET['lead_id'] ?? 0);

$preCar  = $preCarId  ? $db->fetchOne("SELECT * FROM cars WHERE id = ? AND status != 'sold'", [$preCarId],  'i') : null;
$preLead = $preLeadId ? $db->fetchOne("SELECT * FROM leads WHERE id = ?",                     [$preLeadId], 'i') : null;

// Available cars
$availableCars = $db->fetchAll(
    "SELECT id, year, make, model, variant, sale_price, purchase_price
     FROM cars WHERE status != 'sold' ORDER BY make, model"
);

// Salespersons
$salespersons = $db->fetchAll(
    "SELECT u.id, u.full_name, u.commission_type, u.commission_value
     FROM users u JOIN roles r ON u.role_id = r.id
     WHERE r.name IN ('Salesperson','Manager','Admin') AND u.status='active'
     ORDER BY u.full_name"
);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request.';
    } else {

        // Customer
        $custName  = clean($_POST['customer_name'] ?? '');
        $custCnic  = clean($_POST['customer_cnic'] ?? '');
        $custPhone = clean($_POST['customer_phone'] ?? '');
        $custEmail = clean($_POST['customer_email'] ?? '');
        $custCity  = clean($_POST['customer_city'] ?? '');

        // Sale
        $carId        = (int)($_POST['car_id'] ?? 0);
        $salePrice    = (float)($_POST['sale_price'] ?? 0);
        $discount     = (float)($_POST['discount'] ?? 0);
        $finalPrice   = $salePrice - $discount;
        $paymentType  = clean($_POST['payment_type'] ?? 'cash');
        $bankName     = clean($_POST['bank_name'] ?? '');
        $chequeNo     = clean($_POST['cheque_no'] ?? '');
        $tokenAmount  = (float)($_POST['token_amount'] ?? 0);
        $tokenDate    = clean($_POST['token_date'] ?? '') ?: null;
        $saleDate     = clean($_POST['sale_date'] ?? date('Y-m-d'));
        $salespersonId= (int)($_POST['salesperson_id'] ?? 0) ?: null;
        $transferFee  = (float)($_POST['transfer_fee'] ?? 0);
        $withholdingTax=(float)($_POST['withholding_tax'] ?? 0);
        $notes        = clean($_POST['notes'] ?? '');

        // Commission
        $commType     = clean($_POST['commission_type'] ?? 'percentage');
        $commValue    = (float)($_POST['commission_value'] ?? 0);

        // Validation
        if (empty($custName))   $errors[] = 'Customer name is required.';
        if (empty($custPhone))  $errors[] = 'Customer phone is required.';
        if (!$carId)            $errors[] = 'Please select a vehicle.';
        if ($salePrice <= 0)    $errors[] = 'Sale price must be greater than 0.';
        if (empty($saleDate))   $errors[] = 'Sale date is required.';

        if (empty($errors)) {

            // Get car details for profit calc
            $car = $db->fetchOne("SELECT * FROM cars WHERE id = ?", [$carId], 'i');
            if (!$car) {
                $errors[] = 'Selected car not found.';
            } else {

                // Extra costs
                $extraCosts = $db->fetchOne(
                    "SELECT COALESCE(SUM(amount),0) as total FROM car_costs WHERE car_id = ?",
                    [$carId], 'i'
                )['total'] ?? 0;

                // Commission calc
                $commAmount = 0;
                if ($commValue > 0) {
                    $commAmount = $commType === 'percentage'
                        ? ($finalPrice * $commValue / 100)
                        : $commValue;
                }

                // Net profit
                $netProfit = $finalPrice
                           - $car['purchase_price']
                           - $extraCosts
                           - $commAmount
                           - $transferFee
                           - $withholdingTax;

                $remainingAmount = $finalPrice - $tokenAmount;

                // Create or find customer
                $customer = $db->fetchOne(
                    "SELECT id FROM customers WHERE phone = ?",
                    [$custPhone], 's'
                );

                if ($customer) {
                    $customerId = $customer['id'];
                } else {
                    $customerId = $db->insert(
                        "INSERT INTO customers (full_name, cnic, phone, email, city)
                         VALUES (?,?,?,?,?)",
                        [$custName, $custCnic, $custPhone, $custEmail, $custCity],
                        'sssss'
                    );
                }

                // Generate invoice number
                $lastSale  = $db->fetchOne("SELECT MAX(id) as max_id FROM sales");
                $invoiceNo = generateInvoiceNo($lastSale['max_id'] ?? 0);

                // Insert sale
                $saleId = $db->insert(
                    "INSERT INTO sales (
                        car_id, customer_id, salesperson_id,
                        sale_price, discount, final_price,
                        payment_type, bank_name, cheque_no,
                        token_amount, token_date, remaining_amount,
                        commission_type, commission_value, commission_amount,
                        purchase_price, total_extra_costs, net_profit,
                        transfer_fee, withholding_tax,
                        sale_date, invoice_no, notes
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    [
                        $carId, $customerId, $salespersonId,
                        $salePrice, $discount, $finalPrice,
                        $paymentType, $bankName, $chequeNo,
                        $tokenAmount, $tokenDate, $remainingAmount,
                        $commType, $commValue, $commAmount,
                        $car['purchase_price'], $extraCosts, $netProfit,
                        $transferFee, $withholdingTax,
                        $saleDate, $invoiceNo, $notes
                    ]
                );

                if ($saleId) {
                    // Mark car as sold
                    $db->execute(
                        "UPDATE cars SET status = 'sold' WHERE id = ?",
                        [$carId], 'i'
                    );

                    // Log timeline
                    logTimeline($carId, 'sold',
                        "Car sold to $custName for " . formatPrice($finalPrice) . " | Invoice: $invoiceNo",
                        Auth::id()
                    );

                    // Close lead if came from lead
                    if ($preLeadId) {
                        $db->execute(
                            "UPDATE leads SET status='closed_won', customer_id=? WHERE id=?",
                            [$customerId, $preLeadId], 'ii'
                        );
                    }

                    setFlash('success', "Sale recorded! Invoice: $invoiceNo");
                    redirect('modules/sales/view.php?id=' . $saleId);
                } else {
                    $errors[] = 'Failed to record sale.';
                }
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Create Sale';
$pageSub   = 'Record a new vehicle sale';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Sale — AutoManager Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/car-showroom/public/css/fa/all.min.css">
    <style>
        .form-section {
            background:#0d1526;
            border:1px solid rgba(148,163,184,0.08);
            border-radius:16px; overflow:hidden; margin-bottom:20px;
        }
        .form-section-header {
            padding:16px 20px;
            border-bottom:1px solid rgba(148,163,184,0.07);
            display:flex; align-items:center; gap:10px;
        }
        .icon-box {
            width:32px; height:32px; border-radius:8px;
            display:flex; align-items:center; justify-content:center; font-size:0.8rem;
        }
        .form-section-title { font-size:0.875rem; font-weight:700; color:#e2e8f0; }
        .form-section-body  { padding:20px; }
        label.field-label {
            display:block; font-size:0.75rem; font-weight:600;
            color:#64748b; text-transform:uppercase;
            letter-spacing:0.06em; margin-bottom:6px;
        }
        .req { color:#f87171; }
        .form-input {
            width:100%; background:rgba(30,41,59,0.8);
            border:1.5px solid rgba(148,163,184,0.12);
            color:#f1f5f9; font-size:0.875rem;
            padding:10px 14px; border-radius:10px;
            transition:border-color 0.2s,box-shadow 0.2s;
            font-family:'Plus Jakarta Sans',sans-serif;
        }
        .form-input:focus {
            outline:none; border-color:#3b82f6;
            box-shadow:0 0 0 3px rgba(59,130,246,0.15);
        }
        .form-input::placeholder { color:#334155; }
        select.form-input option { background:#1e293b; color:#f1f5f9; }
        .btn-primary {
            background:linear-gradient(135deg,#2563eb,#1d4ed8);
            color:#fff; font-weight:700; font-size:0.9rem;
            padding:12px 28px; border-radius:12px; border:none;
            cursor:pointer; transition:all 0.25s;
            box-shadow:0 4px 20px rgba(37,99,235,0.3);
        }
        .btn-primary:hover { transform:translateY(-1px); }
        .btn-secondary {
            background:rgba(30,41,59,0.8);
            border:1.5px solid rgba(148,163,184,0.12);
            color:#94a3b8; font-weight:600; font-size:0.9rem;
            padding:12px 24px; border-radius:12px;
            text-decoration:none; display:inline-flex; align-items:center; gap:8px;
        }
        .error-alert {
            background:rgba(239,68,68,0.1);
            border:1px solid rgba(239,68,68,0.25);
            color:#fca5a5; border-radius:12px;
            padding:14px 16px; margin-bottom:20px;
        }
        .error-alert ul { margin-top:6px; padding-left:18px; }
        .error-alert li { font-size:0.85rem; }
        .summary-box {
            background:rgba(30,41,59,0.6);
            border:1px solid rgba(148,163,184,0.1);
            border-radius:12px; padding:16px;
        }
        .summary-row {
            display:flex; justify-content:space-between;
            align-items:center; padding:6px 0;
            border-bottom:1px solid rgba(148,163,184,0.05);
            font-size:0.85rem;
        }
        .summary-row:last-child { border-bottom:none; }
        .summary-label { color:#64748b; }
        .summary-value { color:#e2e8f0; font-weight:600; }
    </style>
</head>
<body>
<?php require_once '../../views/layouts/sidebar.php'; ?>
<div class="main">
<?php require_once '../../views/layouts/topbar.php'; ?>
<div class="content-area" style="max-width:960px;">

    <?php if (!empty($errors)): ?>
    <div class="error-alert">
        <div class="flex items-center gap-2 font-700 text-sm">
            <i class="fas fa-circle-exclamation"></i> Please fix the following:
        </div>
        <ul>
            <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" id="saleForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        <!-- LEFT: Main form -->
        <div class="xl:col-span-2">

        <!-- Vehicle Selection -->
        <div class="form-section">
            <div class="form-section-header">
                <div class="icon-box" style="background:rgba(37,99,235,0.15)">
                    <i class="fas fa-car text-blue-400"></i>
                </div>
                <span class="form-section-title">Vehicle</span>
            </div>
            <div class="form-section-body">
                <div>
                    <label class="field-label">Select Vehicle <span class="req">*</span></label>
                    <select name="car_id" id="carSelect" class="form-input" required onchange="loadCarDetails(this)">
                        <option value="">— Select Vehicle —</option>
                        <?php foreach ($availableCars as $ac): ?>
                        <option value="<?= $ac['id'] ?>"
                                data-price="<?= $ac['sale_price'] ?>"
                                data-purchase="<?= $ac['purchase_price'] ?>"
                                <?= ($preCar && $preCar['id']==$ac['id']) ? 'selected' : '' ?>
                                <?= (($_POST['car_id']??'')==$ac['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ac['year'].' '.$ac['make'].' '.$ac['model'].' '.($ac['variant']??'')) ?>
                            — <?= formatPrice($ac['sale_price']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Car details preview -->
                <div id="carPreview" class="mt-4 hidden p-4 rounded-xl"
                     style="background:rgba(37,99,235,0.06);border:1px solid rgba(37,99,235,0.15)">
                    <div class="text-blue-400 text-sm font-700" id="carPreviewName"></div>
                    <div class="text-slate-500 text-xs mt-1" id="carPreviewPrice"></div>
                </div>
            </div>
        </div>

        <!-- Customer Info -->
        <div class="form-section">
            <div class="form-section-header">
                <div class="icon-box" style="background:rgba(22,163,74,0.15)">
                    <i class="fas fa-user text-green-400"></i>
                </div>
                <span class="form-section-title">Customer Information</span>
            </div>
            <div class="form-section-body">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="field-label">Full Name <span class="req">*</span></label>
                        <input type="text" name="customer_name" class="form-input"
                               placeholder="Customer full name"
                               value="<?= htmlspecialchars($_POST['customer_name'] ?? ($preLead['name'] ?? '')) ?>" required>
                    </div>
                    <div>
                        <label class="field-label">Phone <span class="req">*</span></label>
                        <input type="text" name="customer_phone" class="form-input"
                               placeholder="03XX-XXXXXXX"
                               value="<?= htmlspecialchars($_POST['customer_phone'] ?? ($preLead['phone'] ?? '')) ?>" required>
                    </div>
                    <div>
                        <label class="field-label">CNIC</label>
                        <input type="text" name="customer_cnic" class="form-input"
                               placeholder="XXXXX-XXXXXXX-X"
                               value="<?= htmlspecialchars($_POST['customer_cnic'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="field-label">Email</label>
                        <input type="email" name="customer_email" class="form-input"
                               placeholder="Optional"
                               value="<?= htmlspecialchars($_POST['customer_email'] ?? ($preLead['email'] ?? '')) ?>">
                    </div>
                    <div>
                        <label class="field-label">City</label>
                        <input type="text" name="customer_city" class="form-input"
                               placeholder="Customer city"
                               value="<?= htmlspecialchars($_POST['customer_city'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Pricing -->
        <div class="form-section">
            <div class="form-section-header">
                <div class="icon-box" style="background:rgba(234,179,8,0.15)">
                    <i class="fas fa-tag text-yellow-400"></i>
                </div>
                <span class="form-section-title">Pricing & Payment</span>
            </div>
            <div class="form-section-body">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="field-label">Sale Price (PKR) <span class="req">*</span></label>
                        <input type="number" name="sale_price" id="salePrice" class="form-input"
                               placeholder="0" min="0" step="1000"
                               value="<?= htmlspecialchars($_POST['sale_price'] ?? ($preCar['sale_price'] ?? '')) ?>"
                               oninput="calcSummary()" required>
                    </div>
                    <div>
                        <label class="field-label">Discount (PKR)</label>
                        <input type="number" name="discount" id="discount" class="form-input"
                               placeholder="0" min="0" step="1000"
                               value="<?= htmlspecialchars($_POST['discount'] ?? '0') ?>"
                               oninput="calcSummary()">
                    </div>
                    <div>
                        <label class="field-label">Payment Type</label>
                        <select name="payment_type" id="paymentType" class="form-input"
                                onchange="togglePaymentFields()">
                            <?php foreach (['cash'=>'Cash','bank_transfer'=>'Bank Transfer','cheque'=>'Cheque','installment'=>'Installment'] as $v=>$l): ?>
                            <option value="<?= $v ?>" <?= (($_POST['payment_type']??'cash')===$v)?'selected':'' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Sale Date <span class="req">*</span></label>
                        <input type="date" name="sale_date" class="form-input"
                               value="<?= htmlspecialchars($_POST['sale_date'] ?? date('Y-m-d')) ?>" required>
                    </div>

                    <!-- Bank fields -->
                    <div id="bankField" class="hidden">
                        <label class="field-label">Bank Name</label>
                        <input type="text" name="bank_name" class="form-input"
                               placeholder="e.g. HBL, MCB, UBL"
                               value="<?= htmlspecialchars($_POST['bank_name'] ?? '') ?>">
                    </div>
                    <div id="chequeField" class="hidden">
                        <label class="field-label">Cheque Number</label>
                        <input type="text" name="cheque_no" class="form-input"
                               placeholder="Cheque number"
                               value="<?= htmlspecialchars($_POST['cheque_no'] ?? '') ?>">
                    </div>

                    <!-- Token -->
                    <div>
                        <label class="field-label">Token / Advance Amount</label>
                        <input type="number" name="token_amount" id="tokenAmount" class="form-input"
                               placeholder="0" min="0"
                               value="<?= htmlspecialchars($_POST['token_amount'] ?? '0') ?>"
                               oninput="calcSummary()">
                    </div>
                    <div>
                        <label class="field-label">Token Date</label>
                        <input type="date" name="token_date" class="form-input"
                               value="<?= htmlspecialchars($_POST['token_date'] ?? '') ?>">
                    </div>

                    <!-- Transfer & Tax -->
                    <div>
                        <label class="field-label">Transfer Fee</label>
                        <input type="number" name="transfer_fee" id="transferFee" class="form-input"
                               placeholder="0" min="0"
                               value="<?= htmlspecialchars($_POST['transfer_fee'] ?? '0') ?>"
                               oninput="calcSummary()">
                    </div>
                    <div>
                        <label class="field-label">Withholding Tax</label>
                        <input type="number" name="withholding_tax" id="withholdingTax" class="form-input"
                               placeholder="0" min="0"
                               value="<?= htmlspecialchars($_POST['withholding_tax'] ?? '0') ?>"
                               oninput="calcSummary()">
                    </div>
                </div>
            </div>
        </div>

        <!-- Commission -->
        <div class="form-section">
            <div class="form-section-header">
                <div class="icon-box" style="background:rgba(245,158,11,0.15)">
                    <i class="fas fa-percent text-amber-400"></i>
                </div>
                <span class="form-section-title">Salesperson & Commission</span>
            </div>
            <div class="form-section-body">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="field-label">Salesperson</label>
                        <select name="salesperson_id" id="salespersonSelect" class="form-input"
                                onchange="loadCommission(this)">
                            <option value="">— None —</option>
                            <?php foreach ($salespersons as $sp): ?>
                            <option value="<?= $sp['id'] ?>"
                                    data-type="<?= $sp['commission_type'] ?>"
                                    data-value="<?= $sp['commission_value'] ?>"
                                    <?= (($_POST['salesperson_id']??'')==$sp['id'])?'selected':'' ?>>
                                <?= htmlspecialchars($sp['full_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Commission Type</label>
                        <select name="commission_type" id="commissionType" class="form-input"
                                onchange="calcSummary()">
                            <option value="percentage" <?= (($_POST['commission_type']??'percentage')==='percentage')?'selected':'' ?>>Percentage %</option>
                            <option value="fixed"      <?= (($_POST['commission_type']??'')==='fixed')?'selected':'' ?>>Fixed Amount</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Commission Value</label>
                        <input type="number" name="commission_value" id="commissionValue" class="form-input"
                               placeholder="0" min="0" step="0.5"
                               value="<?= htmlspecialchars($_POST['commission_value'] ?? '0') ?>"
                               oninput="calcSummary()">
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div class="form-section">
            <div class="form-section-header">
                <div class="icon-box" style="background:rgba(20,184,166,0.15)">
                    <i class="fas fa-note-sticky text-teal-400"></i>
                </div>
                <span class="form-section-title">Notes</span>
            </div>
            <div class="form-section-body">
                <textarea name="notes" class="form-input" rows="3"
                          placeholder="Any additional notes about this sale..."
                          style="resize:vertical"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
            </div>
        </div>

        </div>

        <!-- RIGHT: Summary -->
        <div class="xl:col-span-1">
            <div class="sticky top-20">
                <div class="form-section">
                    <div class="form-section-header">
                        <div class="icon-box" style="background:rgba(22,163,74,0.15)">
                            <i class="fas fa-calculator text-green-400"></i>
                        </div>
                        <span class="form-section-title">Sale Summary</span>
                    </div>
                    <div class="form-section-body">
                        <div class="summary-box">
                            <div class="summary-row">
                                <span class="summary-label">Sale Price</span>
                                <span class="summary-value" id="sumSalePrice">PKR 0</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Discount</span>
                                <span class="summary-value text-red-400" id="sumDiscount">— PKR 0</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label font-700 text-slate-300">Final Price</span>
                                <span class="summary-value text-blue-400 text-base" id="sumFinalPrice">PKR 0</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Token Received</span>
                                <span class="summary-value text-green-400" id="sumToken">PKR 0</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Remaining</span>
                                <span class="summary-value text-amber-400" id="sumRemaining">PKR 0</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Commission</span>
                                <span class="summary-value text-amber-400" id="sumCommission">PKR 0</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Transfer Fee</span>
                                <span class="summary-value text-red-400" id="sumTransfer">PKR 0</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Withholding Tax</span>
                                <span class="summary-value text-red-400" id="sumTax">PKR 0</span>
                            </div>
                        </div>

                        <!-- Profit box -->
                        <div id="profitBox" class="mt-4 p-4 rounded-xl"
                             style="background:rgba(22,163,74,0.08);border:1px solid rgba(22,163,74,0.2)">
                            <div class="text-xs text-slate-500 font-700 uppercase tracking-widest mb-1">
                                Estimated Net Profit
                            </div>
                            <div class="text-2xl font-800 text-green-400" id="sumProfit">PKR 0</div>
                            <div class="text-xs text-slate-600 mt-1" id="sumPurchaseInfo"></div>
                        </div>

                        <button type="submit" class="btn-primary w-full mt-5 justify-center flex items-center gap-2">
                            <i class="fas fa-handshake"></i> Record Sale
                        </button>
                        <a href="/car-showroom/modules/inventory/index.php"
                           class="btn-secondary w-full mt-3 justify-center">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>
    </form>
</div>
</div>

<script>
// Car data map
const carData = {};
<?php foreach ($availableCars as $ac): ?>
carData[<?= $ac['id'] ?>] = {
    price: <?= $ac['sale_price'] ?>,
    purchase: <?= $ac['purchase_price'] ?>
};
<?php endforeach; ?>

function loadCarDetails(sel) {
    const id      = parseInt(sel.value);
    const preview = document.getElementById('carPreview');
    if (id && carData[id]) {
        document.getElementById('salePrice').value = carData[id].price;
        document.getElementById('carPreviewName').textContent =
            sel.options[sel.selectedIndex].text.split('—')[0].trim();
        document.getElementById('carPreviewPrice').textContent =
            'Listed at PKR ' + carData[id].price.toLocaleString();
        preview.classList.remove('hidden');
        calcSummary();
    } else {
        preview.classList.add('hidden');
    }
}

function loadCommission(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (opt.value) {
        document.getElementById('commissionType').value  = opt.dataset.type  || 'percentage';
        document.getElementById('commissionValue').value = opt.dataset.value || 0;
        calcSummary();
    }
}

function togglePaymentFields() {
    const pt      = document.getElementById('paymentType').value;
    const bankF   = document.getElementById('bankField');
    const chequeF = document.getElementById('chequeField');
    bankF.classList.toggle('hidden',   !['bank_transfer','cheque'].includes(pt));
    chequeF.classList.toggle('hidden', pt !== 'cheque');
}

function fmt(n) {
    return 'PKR ' + Math.round(n).toLocaleString();
}

function calcSummary() {
    const sale       = parseFloat(document.getElementById('salePrice').value)     || 0;
    const discount   = parseFloat(document.getElementById('discount').value)       || 0;
    const token      = parseFloat(document.getElementById('tokenAmount').value)    || 0;
    const transfer   = parseFloat(document.getElementById('transferFee').value)    || 0;
    const tax        = parseFloat(document.getElementById('withholdingTax').value) || 0;
    const commVal    = parseFloat(document.getElementById('commissionValue').value)|| 0;
    const commType   = document.getElementById('commissionType').value;
    const finalPrice = sale - discount;
    const remaining  = finalPrice - token;
    const commission = commType === 'percentage' ? (finalPrice * commVal / 100) : commVal;

    // Get purchase price from selected car
    const carId    = parseInt(document.getElementById('carSelect').value) || 0;
    const purchase = carId && carData[carId] ? carData[carId].purchase : 0;
    const profit   = finalPrice - purchase - commission - transfer - tax;

    document.getElementById('sumSalePrice').textContent  = fmt(sale);
    document.getElementById('sumDiscount').textContent   = '— ' + fmt(discount);
    document.getElementById('sumFinalPrice').textContent = fmt(finalPrice);
    document.getElementById('sumToken').textContent      = fmt(token);
    document.getElementById('sumRemaining').textContent  = fmt(remaining);
    document.getElementById('sumCommission').textContent = fmt(commission);
    document.getElementById('sumTransfer').textContent   = fmt(transfer);
    document.getElementById('sumTax').textContent        = fmt(tax);

    const profitEl = document.getElementById('sumProfit');
    const profitBox= document.getElementById('profitBox');
    profitEl.textContent = fmt(profit);
    profitEl.className   = 'text-2xl font-800 ' + (profit >= 0 ? 'text-green-400' : 'text-red-400');
    profitBox.style.background   = profit >= 0 ? 'rgba(22,163,74,0.08)'  : 'rgba(220,38,38,0.08)';
    profitBox.style.borderColor  = profit >= 0 ? 'rgba(22,163,74,0.2)'   : 'rgba(220,38,38,0.2)';

    if (purchase > 0) {
        document.getElementById('sumPurchaseInfo').textContent =
            'Purchase: PKR ' + purchase.toLocaleString() + ' | Extra costs not included';
    }
}

// Init
togglePaymentFields();
calcSummary();

<?php if ($preCar): ?>
document.getElementById('carSelect').dispatchEvent(new Event('change'));
<?php endif; ?>
</script>
</body>
</html>