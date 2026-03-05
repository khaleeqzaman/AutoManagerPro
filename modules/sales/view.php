<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('sales.view');

Auth::check();

$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    setFlash('error', 'Invalid sale ID.');
    redirect('modules/sales/index.php');
}

$sale = $db->fetchOne(
    "SELECT s.*,
        c.make, c.model, c.year, c.variant, c.chassis_no, c.color, c.engine_capacity,
        c.fuel_type, c.transmission, c.engine_no,
        cu.full_name as customer_name, cu.cnic, cu.phone as customer_phone,
        cu.email as customer_email, cu.city as customer_city,
        u.full_name as salesperson_name
     FROM sales s
     JOIN cars c ON s.car_id = c.id
     JOIN customers cu ON s.customer_id = cu.id
     LEFT JOIN users u ON s.salesperson_id = u.id
     WHERE s.id = ?",
    [$id], 'i'
);

if (!$sale) {
    setFlash('error', 'Sale not found.');
    redirect('modules/sales/index.php');
}

$showroomName = getSetting('showroom_name', 'AutoManager Pro');
$showroomPhone= getSetting('phone_no', '');
$showroomCity = getSetting('showroom_city', '');

$pageTitle = 'Sale Invoice';
$pageSub   = $sale['invoice_no'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= htmlspecialchars($sale['invoice_no']) ?> — AutoManager Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/car-showroom/public/css/fa/all.min.css">
    <style>
        .detail-card {
            background:#0d1526;
            border:1px solid rgba(148,163,184,0.08);
            border-radius:16px; overflow:hidden; margin-bottom:16px;
        }
        .detail-card-header {
            padding:14px 20px;
            border-bottom:1px solid rgba(148,163,184,0.07);
            display:flex; align-items:center; gap:10px;
        }
        .icon-box {
            width:30px; height:30px; border-radius:8px;
            display:flex; align-items:center; justify-content:center; font-size:0.75rem;
        }
        .card-title { font-size:0.85rem; font-weight:700; color:#e2e8f0; }
        .card-body  { padding:18px 20px; }
        .spec-row {
            display:flex; justify-content:space-between; align-items:center;
            padding:7px 0; border-bottom:1px solid rgba(148,163,184,0.05);
            font-size:0.85rem;
        }
        .spec-row:last-child { border-bottom:none; }
        .spec-label { color:#475569; }
        .spec-value { color:#e2e8f0; font-weight:600; text-align:right; }

        /* Print invoice */
        .invoice-print {
            display:none;
        }

        @media print {
            body * { visibility:hidden; }
            .invoice-print, .invoice-print * { visibility:visible; }
            .invoice-print {
                display:block !important;
                position:fixed; inset:0;
                background:#fff; color:#000;
                padding:40px; font-size:13px;
                font-family:'Plus Jakarta Sans',sans-serif;
            }
            .inv-header { display:flex; justify-content:space-between; margin-bottom:30px; }
            .inv-title  { font-size:24px; font-weight:800; color:#1e40af; }
            .inv-sub    { font-size:12px; color:#666; }
            .inv-table  { width:100%; border-collapse:collapse; margin:20px 0; }
            .inv-table th, .inv-table td {
                border:1px solid #e5e7eb; padding:8px 12px; text-align:left; font-size:12px;
            }
            .inv-table th { background:#eff6ff; font-weight:700; }
            .inv-totals { margin-left:auto; width:280px; margin-top:20px; }
            .inv-total-row { display:flex; justify-content:space-between; padding:5px 0; font-size:13px; }
            .inv-total-final { font-size:16px; font-weight:800; color:#1e40af; border-top:2px solid #1e40af; padding-top:8px; margin-top:4px; }
            .inv-footer { margin-top:40px; text-align:center; color:#666; font-size:11px; border-top:1px solid #e5e7eb; padding-top:16px; }
            .no-print { display:none !important; }
        }
    </style>
</head>
<body>
<?php require_once '../../views/layouts/sidebar.php'; ?>
<div class="main">
<?php require_once '../../views/layouts/topbar.php'; ?>
<div class="content-area">

    <!-- Action bar -->
    <div class="flex items-center justify-between mb-5 no-print">
        <a href="/car-showroom/modules/sales/index.php"
           class="flex items-center gap-2 text-slate-400 hover:text-white text-sm font-600 transition-colors">
            <i class="fas fa-arrow-left"></i> Back to Sales
        </a>
        <button onclick="window.print()"
                class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-700 bg-blue-600 hover:bg-blue-700 text-white transition-all">
            <i class="fas fa-print"></i> Print Invoice
        </button>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 no-print">

        <!-- Left -->
        <div class="xl:col-span-2">

            <!-- Invoice Header -->
            <div class="detail-card">
                <div class="card-body">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="text-blue-400 font-800 text-xl">
                                <?= htmlspecialchars($sale['invoice_no']) ?>
                            </div>
                            <div class="text-slate-400 text-sm mt-1">
                                Sale Date: <?= date('d F Y', strtotime($sale['sale_date'])) ?>
                            </div>
                        </div>
                        <span class="px-4 py-2 rounded-xl text-sm font-700 bg-green-500/15 text-green-400 border border-green-500/20">
                            <i class="fas fa-check-circle mr-1"></i> Sold
                        </span>
                    </div>
                </div>
            </div>

            <!-- Vehicle Details -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="icon-box" style="background:rgba(37,99,235,0.15)">
                        <i class="fas fa-car text-blue-400"></i>
                    </div>
                    <span class="card-title">Vehicle Details</span>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-2 gap-x-8">
                        <?php
                        $vSpecs = [
                            'Vehicle'      => $sale['year'].' '.$sale['make'].' '.$sale['model'],
                            'Variant'      => $sale['variant'] ?: '—',
                            'Color'        => $sale['color'] ?: '—',
                            'Engine'       => $sale['engine_capacity'] ? $sale['engine_capacity'].'cc' : '—',
                            'Fuel'         => ucfirst($sale['fuel_type']),
                            'Transmission' => ucfirst($sale['transmission']),
                            'Chassis No.'  => $sale['chassis_no'],
                            'Engine No.'   => $sale['engine_no'] ?: '—',
                        ];
                        foreach ($vSpecs as $l => $v):
                        ?>
                        <div class="spec-row">
                            <span class="spec-label"><?= $l ?></span>
                            <span class="spec-value"><?= htmlspecialchars($v) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Customer Details -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="icon-box" style="background:rgba(22,163,74,0.15)">
                        <i class="fas fa-user text-green-400"></i>
                    </div>
                    <span class="card-title">Customer Details</span>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-2 gap-x-8">
                        <?php
                        $cSpecs = [
                            'Name'  => $sale['customer_name'],
                            'CNIC'  => $sale['cnic'] ?: '—',
                            'Phone' => $sale['customer_phone'],
                            'Email' => $sale['customer_email'] ?: '—',
                            'City'  => $sale['customer_city'] ?: '—',
                        ];
                        foreach ($cSpecs as $l => $v):
                        ?>
                        <div class="spec-row">
                            <span class="spec-label"><?= $l ?></span>
                            <span class="spec-value"><?= htmlspecialchars($v) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right -->
        <div class="xl:col-span-1">

            <!-- Financial Summary -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="icon-box" style="background:rgba(234,179,8,0.15)">
                        <i class="fas fa-coins text-yellow-400"></i>
                    </div>
                    <span class="card-title">Financial Summary</span>
                </div>
                <div class="card-body">
                    <div class="spec-row">
                        <span class="spec-label">Sale Price</span>
                        <span class="spec-value"><?= formatPrice($sale['sale_price']) ?></span>
                    </div>
                    <?php if ($sale['discount'] > 0): ?>
                    <div class="spec-row">
                        <span class="spec-label">Discount</span>
                        <span class="spec-value text-red-400">— <?= formatPrice($sale['discount']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="spec-row">
                        <span class="spec-label font-700 text-slate-300">Final Price</span>
                        <span class="spec-value text-blue-400 text-base"><?= formatPrice($sale['final_price']) ?></span>
                    </div>
                    <?php if ($sale['token_amount'] > 0): ?>
                    <div class="spec-row">
                        <span class="spec-label">Token Paid</span>
                        <span class="spec-value text-green-400"><?= formatPrice($sale['token_amount']) ?></span>
                    </div>
                    <div class="spec-row">
                        <span class="spec-label">Remaining</span>
                        <span class="spec-value text-amber-400"><?= formatPrice($sale['remaining_amount']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="spec-row">
                        <span class="spec-label">Payment Type</span>
                        <span class="spec-value capitalize"><?= ucfirst(str_replace('_',' ',$sale['payment_type'])) ?></span>
                    </div>
                    <?php if ($sale['bank_name']): ?>
                    <div class="spec-row">
                        <span class="spec-label">Bank</span>
                        <span class="spec-value"><?= htmlspecialchars($sale['bank_name']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profit (Admin/Manager only) -->
            <?php if (Auth::hasRole(['Admin','Manager'])): ?>
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="icon-box" style="background:rgba(22,163,74,0.15)">
                        <i class="fas fa-arrow-trend-up text-green-400"></i>
                    </div>
                    <span class="card-title">Profit Breakdown</span>
                </div>
                <div class="card-body">
                    <div class="spec-row">
                        <span class="spec-label">Purchase Price</span>
                        <span class="spec-value text-slate-400"><?= formatPrice($sale['purchase_price']) ?></span>
                    </div>
                    <div class="spec-row">
                        <span class="spec-label">Extra Costs</span>
                        <span class="spec-value text-red-400">— <?= formatPrice($sale['total_extra_costs']) ?></span>
                    </div>
                    <div class="spec-row">
                        <span class="spec-label">Commission</span>
                        <span class="spec-value text-amber-400">
                            — <?= formatPrice($sale['commission_amount']) ?>
                            <?php if ($sale['commission_value'] > 0): ?>
                            <span class="text-xs text-slate-600 ml-1">
                                (<?= $sale['commission_type']==='percentage' ? $sale['commission_value'].'%' : 'Fixed' ?>)
                            </span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($sale['transfer_fee'] > 0): ?>
                    <div class="spec-row">
                        <span class="spec-label">Transfer Fee</span>
                        <span class="spec-value text-red-400">— <?= formatPrice($sale['transfer_fee']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($sale['withholding_tax'] > 0): ?>
                    <div class="spec-row">
                        <span class="spec-label">Withholding Tax</span>
                        <span class="spec-value text-red-400">— <?= formatPrice($sale['withholding_tax']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="mt-3 p-3 rounded-xl <?= $sale['net_profit'] >= 0 ? 'bg-green-500/10 border border-green-500/20' : 'bg-red-500/10 border border-red-500/20' ?>">
                        <div class="text-xs font-700 uppercase tracking-widest <?= $sale['net_profit'] >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                            Net Profit
                        </div>
                        <div class="text-xl font-800 <?= $sale['net_profit'] >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                            <?= formatPrice($sale['net_profit']) ?>
                        </div>
                    </div>
                    <?php if ($sale['salesperson_name']): ?>
                    <div class="mt-3 text-xs text-slate-500">
                        <i class="fas fa-user mr-1"></i>
                        <?= htmlspecialchars($sale['salesperson_name']) ?> — Commission: <?= formatPrice($sale['commission_amount']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($sale['notes']): ?>
            <div class="detail-card">
                <div class="card-body">
                    <div class="text-xs text-slate-500 font-700 uppercase tracking-widest mb-2">Notes</div>
                    <p class="text-slate-400 text-sm"><?= nl2br(htmlspecialchars($sale['notes'])) ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>
</div>

<!-- PRINT INVOICE -->
<div class="invoice-print">
    <div class="inv-header">
        <div>
            <div class="inv-title"><?= htmlspecialchars($showroomName) ?></div>
            <div class="inv-sub"><?= htmlspecialchars($showroomCity) ?> | <?= htmlspecialchars($showroomPhone) ?></div>
        </div>
        <div style="text-align:right">
            <div style="font-size:18px;font-weight:800;color:#1e40af"><?= htmlspecialchars($sale['invoice_no']) ?></div>
            <div style="color:#666;font-size:12px"><?= date('d F Y', strtotime($sale['sale_date'])) ?></div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
        <div>
            <div style="font-weight:700;margin-bottom:6px;color:#1e40af">CUSTOMER</div>
            <div><?= htmlspecialchars($sale['customer_name']) ?></div>
            <div><?= htmlspecialchars($sale['cnic'] ?: '') ?></div>
            <div><?= htmlspecialchars($sale['customer_phone']) ?></div>
            <div><?= htmlspecialchars($sale['customer_city'] ?: '') ?></div>
        </div>
        <div>
            <div style="font-weight:700;margin-bottom:6px;color:#1e40af">VEHICLE</div>
            <div><?= htmlspecialchars($sale['year'].' '.$sale['make'].' '.$sale['model'].' '.($sale['variant']??'')) ?></div>
            <div>Chassis: <?= htmlspecialchars($sale['chassis_no']) ?></div>
            <div>Color: <?= htmlspecialchars($sale['color'] ?: '—') ?></div>
            <div><?= ucfirst($sale['transmission']) ?> | <?= ucfirst($sale['fuel_type']) ?></div>
        </div>
    </div>

    <div class="inv-totals">
        <div class="inv-total-row">
            <span>Sale Price</span><span><?= formatPrice($sale['sale_price']) ?></span>
        </div>
        <?php if ($sale['discount'] > 0): ?>
        <div class="inv-total-row">
            <span>Discount</span><span>— <?= formatPrice($sale['discount']) ?></span>
        </div>
        <?php endif; ?>
        <div class="inv-total-row inv-total-final">
            <span>TOTAL AMOUNT</span><span><?= formatPrice($sale['final_price']) ?></span>
        </div>
        <?php if ($sale['token_amount'] > 0): ?>
        <div class="inv-total-row">
            <span>Token Paid</span><span><?= formatPrice($sale['token_amount']) ?></span>
        </div>
        <div class="inv-total-row" style="color:#d97706;font-weight:600">
            <span>Remaining</span><span><?= formatPrice($sale['remaining_amount']) ?></span>
        </div>
        <?php endif; ?>
        <div class="inv-total-row">
            <span>Payment Type</span>
            <span><?= ucfirst(str_replace('_',' ',$sale['payment_type'])) ?></span>
        </div>
    </div>

    <div class="inv-footer">
        <p>Thank you for your purchase! For any queries contact <?= htmlspecialchars($showroomPhone) ?></p>
        <p style="margin-top:4px"><?= htmlspecialchars($showroomName) ?> — <?= htmlspecialchars($showroomCity) ?></p>
    </div>
</div>

</body>
</html>