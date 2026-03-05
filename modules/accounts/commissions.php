<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('accounts.manage');

Auth::check();
if (!Auth::hasRole(['Admin', 'Manager'])) {
    setFlash('error', 'Access denied.');
    redirect('modules/accounts/index.php');
}

$db = Database::getInstance();

// Pay commission action
if (isset($_GET['pay'])) {
    $saleId = (int)$_GET['pay'];
    $sale   = $db->fetchOne(
        "SELECT s.*, u.full_name FROM sales s
         LEFT JOIN users u ON s.salesperson_id = u.id
         WHERE s.id = ? AND s.commission_paid = 0",
        [$saleId], 'i'
    );

    if ($sale && $sale['commission_amount'] > 0) {
        $db->execute(
            "UPDATE sales SET commission_paid = 1, commission_paid_date = NOW() WHERE id = ?",
            [$saleId], 'i'
        );

        // Debit from main cash account automatically
        $cashAcc = $db->fetchOne(
            "SELECT id, current_balance FROM accounts WHERE account_type='cash' AND is_active=1 ORDER BY id ASC LIMIT 1"
        );
        if ($cashAcc) {
            $newBal = $cashAcc['current_balance'] - $sale['commission_amount'];
            $db->insert(
                "INSERT INTO transactions (account_id, transaction_type, category, amount, balance_after, reference_type, reference_id, description, transaction_date, added_by)
                VALUES (?,?,?,?,?,?,?,?,NOW(),?)",
                [$cashAcc['id'], 'debit', 'commission_payment', $sale['commission_amount'],
                $newBal, 'commission', $saleId,
                'Commission paid to: ' . $sale['full_name'] . ' | Invoice: ' . $sale['invoice_no'] . ' | Amount: ' . formatPrice($sale['commission_amount']),
                Auth::id()],
                'issddsssi'
            );
            $db->execute(
                "UPDATE accounts SET current_balance = ? WHERE id = ?",
                [$newBal, $cashAcc['id']], 'di'
            );
        }
        setFlash('success', 'Commission marked as paid!');
    } else {
        setFlash('error', 'Invalid commission payment request.');
    }
    redirect('modules/accounts/commissions.php');
}

// Filters
$filter     = clean($_GET['filter'] ?? 'unpaid');
$whereSQL   = $filter === 'paid' ? "WHERE s.commission_paid = 1" : "WHERE s.commission_paid = 0";
$whereSQL  .= " AND s.commission_amount > 0 AND s.salesperson_id IS NOT NULL";

$commissions = $db->fetchAll(
    "SELECT s.id, s.invoice_no, s.sale_date, s.final_price,
            s.commission_type, s.commission_value, s.commission_amount,
            s.commission_paid, s.commission_paid_date,
            u.full_name as salesperson_name,
            c.make, c.model, c.year,
            cu.full_name as customer_name
     FROM sales s
     JOIN users u ON s.salesperson_id = u.id
     JOIN cars c ON s.car_id = c.id
     JOIN customers cu ON s.customer_id = cu.id
     $whereSQL
     ORDER BY s.sale_date DESC"
);

$totalPending = $db->fetchOne(
    "SELECT COALESCE(SUM(commission_amount),0) as t FROM sales
     WHERE commission_paid=0 AND commission_amount>0"
)['t'];

$totalPaid = $db->fetchOne(
    "SELECT COALESCE(SUM(commission_amount),0) as t FROM sales
     WHERE commission_paid=1"
)['t'];

$pageTitle = 'Commission Tracker';
$pageSub   = 'Salesperson commissions';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commissions — AutoManager Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/car-showroom/public/css/fa/all.min.css">
    <style>
        .section-card {
            background:#0d1526; border:1px solid rgba(148,163,184,0.08);
            border-radius:16px; overflow:hidden;
        }
        .section-header {
            padding:16px 20px; border-bottom:1px solid rgba(148,163,184,0.07);
            display:flex; align-items:center; justify-content:space-between;
        }
        .section-title { font-size:0.9rem; font-weight:700; color:#e2e8f0; }
        .data-table { width:100%; border-collapse:collapse; }
        .data-table th {
            font-size:0.7rem; font-weight:700; letter-spacing:0.08em;
            text-transform:uppercase; color:#475569; padding:11px 16px;
            border-bottom:1px solid rgba(148,163,184,0.08); text-align:left;
        }
        .data-table td {
            padding:12px 16px; font-size:0.85rem; color:#94a3b8;
            border-bottom:1px solid rgba(148,163,184,0.05); vertical-align:middle;
        }
        .data-table tr:last-child td { border-bottom:none; }
    </style>
</head>
<body>
<?php require_once '../../views/layouts/sidebar.php'; ?>
<div class="main">
<?php require_once '../../views/layouts/topbar.php'; ?>
<div class="content-area">

    <?php $flash = getFlash(); if ($flash): ?>
    <div class="mb-5 px-4 py-3 rounded-xl text-sm flex items-center gap-2
        <?= $flash['type']==='success' ? 'bg-green-500/10 border border-green-500/20 text-green-400' : 'bg-red-500/10 border border-red-500/20 text-red-400' ?>">
        <i class="fas <?= $flash['type']==='success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
        <div class="p-5 rounded-2xl" style="background:#0d1526;border:1px solid rgba(217,119,6,0.2)">
            <div class="text-xs text-slate-500 font-700 uppercase tracking-wide mb-1">Pending Commission</div>
            <div class="text-amber-400 text-2xl font-800"><?= formatPrice($totalPending) ?></div>
        </div>
        <div class="p-5 rounded-2xl" style="background:#0d1526;border:1px solid rgba(22,163,74,0.2)">
            <div class="text-xs text-slate-500 font-700 uppercase tracking-wide mb-1">Total Paid</div>
            <div class="text-green-400 text-2xl font-800"><?= formatPrice($totalPaid) ?></div>
        </div>
    </div>

    <!-- Filter tabs -->
    <div class="flex gap-2 mb-5">
        <a href="?filter=unpaid"
           class="px-4 py-2 rounded-xl text-sm font-700 transition-all
           <?= $filter==='unpaid' ? 'bg-amber-500/15 text-amber-400 border border-amber-500/25' : 'text-slate-400 hover:text-white' ?>"
           style="<?= $filter!=='unpaid' ? 'background:rgba(30,41,59,0.5);border:1px solid rgba(148,163,184,0.1)' : '' ?>">
            <i class="fas fa-clock mr-1"></i> Unpaid
        </a>
        <a href="?filter=paid"
           class="px-4 py-2 rounded-xl text-sm font-700 transition-all
           <?= $filter==='paid' ? 'bg-green-500/15 text-green-400 border border-green-500/25' : 'text-slate-400 hover:text-white' ?>"
           style="<?= $filter!=='paid' ? 'background:rgba(30,41,59,0.5);border:1px solid rgba(148,163,184,0.1)' : '' ?>">
            <i class="fas fa-check mr-1"></i> Paid
        </a>
        <a href="/car-showroom/modules/accounts/index.php"
           class="ml-auto px-4 py-2 rounded-xl text-sm font-700 text-slate-400 hover:text-white transition-all"
           style="background:rgba(30,41,59,0.5);border:1px solid rgba(148,163,184,0.1)">
            <i class="fas fa-arrow-left mr-1"></i> Back to Accounts
        </a>
    </div>

    <!-- Table -->
    <div class="section-card">
        <div class="section-header">
            <span class="section-title">
                <i class="fas fa-percent text-amber-400 mr-2"></i>
                <?= $filter==='paid' ? 'Paid' : 'Pending' ?> Commissions
                <span class="text-slate-500 font-400 text-sm ml-2">(<?= count($commissions) ?>)</span>
            </span>
        </div>
        <?php if (empty($commissions)): ?>
        <div class="text-center py-12 text-slate-600">
            <i class="fas fa-check-circle text-green-400 text-3xl mb-3 block"></i>
            <p class="text-sm"><?= $filter==='paid' ? 'No commissions paid yet' : 'All commissions are paid!' ?></p>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Salesperson</th>
                    <th>Vehicle</th>
                    <th>Sale Price</th>
                    <th>Commission</th>
                    <th>Sale Date</th>
                    <?= $filter==='paid' ? '<th>Paid On</th>' : '<th>Action</th>' ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($commissions as $cm): ?>
            <tr>
                <td class="font-mono text-xs text-blue-400 font-700"><?= htmlspecialchars($cm['invoice_no']) ?></td>
                <td>
                    <div class="text-slate-200 font-600"><?= htmlspecialchars($cm['salesperson_name']) ?></div>
                    <div class="text-slate-600 text-xs">
                        <?= $cm['commission_type']==='percentage'
                            ? $cm['commission_value'].'%'
                            : 'Fixed: '.formatPrice($cm['commission_value']) ?>
                    </div>
                </td>
                <td class="text-slate-300">
                    <?= htmlspecialchars($cm['year'].' '.$cm['make'].' '.$cm['model']) ?>
                </td>
                <td class="text-blue-400 font-700"><?= formatPrice($cm['final_price']) ?></td>
                <td class="text-amber-400 font-700"><?= formatPrice($cm['commission_amount']) ?></td>
                <td class="text-xs text-slate-500"><?= date('d M Y', strtotime($cm['sale_date'])) ?></td>
                <?php if ($filter === 'paid'): ?>
                <td class="text-xs text-green-400">
                    <i class="fas fa-check mr-1"></i>
                    <?= date('d M Y', strtotime($cm['commission_paid_date'])) ?>
                </td>
                <?php else: ?>
                <td>
                    <a href="?pay=<?= $cm['id'] ?>"
                       onclick="return confirm('Mark commission of <?= formatPrice($cm['commission_amount']) ?> as paid to <?= htmlspecialchars($cm['salesperson_name'], ENT_QUOTES) ?>?')"
                       class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-700 bg-green-500/15 text-green-400 border border-green-500/25 hover:bg-green-500/25 transition-all">
                        <i class="fas fa-check"></i> Mark Paid
                    </a>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>
</body>
</html>