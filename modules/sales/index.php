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

// Filters
$where  = [];
$params = [];
$types  = '';

if (!empty($_GET['search'])) {
    $s        = '%' . clean($_GET['search']) . '%';
    $where[]  = "(cu.full_name LIKE ? OR c.make LIKE ? OR c.model LIKE ? OR s.invoice_no LIKE ?)";
    $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
    $types   .= 'ssss';
}
if (!empty($_GET['payment_type'])) {
    $where[]  = "s.payment_type = ?";
    $params[] = clean($_GET['payment_type']);
    $types   .= 's';
}
if (!empty($_GET['month'])) {
    $where[]  = "DATE_FORMAT(s.sale_date, '%Y-%m') = ?";
    $params[] = clean($_GET['month']);
    $types   .= 's';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Totals
$totals = $db->fetchOne(
    "SELECT
        COUNT(*) as total_sales,
        COALESCE(SUM(s.final_price),0) as total_revenue,
        COALESCE(SUM(s.net_profit),0) as total_profit,
        COALESCE(SUM(s.commission_amount),0) as total_commission
     FROM sales s
     JOIN customers cu ON s.customer_id = cu.id
     JOIN cars c ON s.car_id = c.id
     $whereSQL",
    $params, $types
);

// Pagination
$perPage    = 15;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;
$totalRows  = $totals['total_sales'] ?? 0;
$totalPages = ceil($totalRows / $perPage);

$sales = $db->fetchAll(
    "SELECT s.*,
        c.make, c.model, c.year, c.variant,
        cu.full_name as customer_name, cu.phone as customer_phone,
        u.full_name as salesperson_name
     FROM sales s
     JOIN cars c ON s.car_id = c.id
     JOIN customers cu ON s.customer_id = cu.id
     LEFT JOIN users u ON s.salesperson_id = u.id
     $whereSQL
     ORDER BY s.sale_date DESC, s.id DESC
     LIMIT $perPage OFFSET $offset",
    $params, $types
);

$flash     = getFlash();
$pageTitle = 'Sales';
$pageSub   = number_format($totalRows) . ' total sales';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales — AutoManager Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/car-showroom/public/css/fa/all.min.css">
    <style>
        .stat-card {
            background:#0d1526;
            border:1px solid rgba(148,163,184,0.08);
            border-radius:14px; padding:18px;
            position:relative; overflow:hidden;
        }
        .stat-card::before {
            content:''; position:absolute;
            top:0; left:0; right:0; height:2px;
        }
        .stat-card.blue::before   { background:linear-gradient(90deg,#2563eb,#60a5fa); }
        .stat-card.green::before  { background:linear-gradient(90deg,#16a34a,#4ade80); }
        .stat-card.purple::before { background:linear-gradient(90deg,#7c3aed,#a78bfa); }
        .stat-card.amber::before  { background:linear-gradient(90deg,#d97706,#fbbf24); }
        .stat-icon {
            width:38px; height:38px; border-radius:10px;
            display:flex; align-items:center; justify-content:center;
            font-size:1rem; margin-bottom:12px;
        }
        .stat-icon.blue   { background:rgba(37,99,235,0.15);  color:#60a5fa; }
        .stat-icon.green  { background:rgba(22,163,74,0.15);   color:#4ade80; }
        .stat-icon.purple { background:rgba(124,58,237,0.15);  color:#a78bfa; }
        .stat-icon.amber  { background:rgba(217,119,6,0.15);   color:#fbbf24; }
        .stat-value { font-size:1.4rem; font-weight:800; color:#f1f5f9; line-height:1; }
        .stat-label { font-size:0.72rem; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; margin-top:4px; }

        .section-card {
            background:#0d1526;
            border:1px solid rgba(148,163,184,0.08);
            border-radius:16px; overflow:hidden;
        }
        .section-header {
            padding:16px 20px;
            border-bottom:1px solid rgba(148,163,184,0.07);
            display:flex; align-items:center; justify-content:space-between;
        }
        .section-title { font-size:0.9rem; font-weight:700; color:#e2e8f0; }

        .data-table { width:100%; border-collapse:collapse; }
        .data-table th {
            font-size:0.7rem; font-weight:700;
            letter-spacing:0.08em; text-transform:uppercase;
            color:#475569; padding:11px 16px;
            border-bottom:1px solid rgba(148,163,184,0.08);
            text-align:left; white-space:nowrap;
        }
        .data-table td {
            padding:13px 16px;
            font-size:0.85rem; color:#94a3b8;
            border-bottom:1px solid rgba(148,163,184,0.05);
            vertical-align:middle;
        }
        .data-table tr:last-child td { border-bottom:none; }
        .data-table tr:hover td { background:rgba(148,163,184,0.02); }

        .badge {
            font-size:0.68rem; font-weight:700;
            padding:3px 10px; border-radius:20px;
            letter-spacing:0.04em; text-transform:uppercase;
        }
        .badge-cash          { background:rgba(22,163,74,0.15);  color:#4ade80; }
        .badge-bank_transfer { background:rgba(37,99,235,0.15);  color:#60a5fa; }
        .badge-cheque        { background:rgba(217,119,6,0.15);  color:#fbbf24; }
        .badge-installment   { background:rgba(124,58,237,0.15); color:#a78bfa; }

        .action-btn {
            display:inline-flex; align-items:center; gap:5px;
            padding:5px 11px; border-radius:7px;
            font-size:0.75rem; font-weight:600;
            text-decoration:none; transition:all 0.15s;
            border:1px solid transparent;
        }
        .btn-view { background:rgba(37,99,235,0.1); color:#60a5fa; border-color:rgba(37,99,235,0.2); }
        .btn-view:hover { background:rgba(37,99,235,0.2); }
    </style>
</head>
<body>
<?php require_once '../../views/layouts/sidebar.php'; ?>
<div class="main">
<?php require_once '../../views/layouts/topbar.php'; ?>
<div class="content-area">

    <?php if ($flash): ?>
    <div class="mb-5 px-4 py-3 rounded-xl text-sm flex items-center gap-2
        <?= $flash['type']==='success' ? 'bg-green-500/10 border border-green-500/20 text-green-400' : 'bg-red-500/10 border border-red-500/20 text-red-400' ?>">
        <i class="fas <?= $flash['type']==='success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="fas fa-handshake"></i></div>
            <div class="stat-value"><?= number_format($totals['total_sales']) ?></div>
            <div class="stat-label">Total Sales</div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon green"><i class="fas fa-coins"></i></div>
            <div class="stat-value"><?= number_format($totals['total_revenue']/1000000, 1) ?>M</div>
            <div class="stat-label">Total Revenue</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon purple"><i class="fas fa-arrow-trend-up"></i></div>
            <div class="stat-value"><?= number_format($totals['total_profit']/1000, 0) ?>K</div>
            <div class="stat-label">Total Profit</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-icon amber"><i class="fas fa-percent"></i></div>
            <div class="stat-value"><?= number_format($totals['total_commission']/1000, 0) ?>K</div>
            <div class="stat-label">Commission Paid</div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="flex flex-wrap gap-3 mb-5 p-4 rounded-xl"
          style="background:#0d1526;border:1px solid rgba(148,163,184,0.08)">

        <input type="text" name="search" placeholder="Search customer, car, invoice..."
               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
               class="flex-1 min-w-[180px] px-4 py-2 rounded-xl text-sm text-slate-200 placeholder-slate-600"
               style="background:rgba(30,41,59,0.8);border:1.5px solid rgba(148,163,184,0.12)">

        <select name="payment_type" class="px-4 py-2 rounded-xl text-sm text-slate-300"
                style="background:rgba(30,41,59,0.8);border:1.5px solid rgba(148,163,184,0.12)">
            <option value="">All Payment Types</option>
            <?php foreach (['cash'=>'Cash','bank_transfer'=>'Bank Transfer','cheque'=>'Cheque','installment'=>'Installment'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= (($_GET['payment_type']??'')===$v)?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>

        <input type="month" name="month"
               value="<?= htmlspecialchars($_GET['month'] ?? '') ?>"
               class="px-4 py-2 rounded-xl text-sm text-slate-300"
               style="background:rgba(30,41,59,0.8);border:1.5px solid rgba(148,163,184,0.12)">

        <button type="submit"
                class="px-4 py-2 rounded-xl text-xs font-700 bg-blue-600 hover:bg-blue-700 text-white transition-all">
            <i class="fas fa-search mr-1"></i> Filter
        </button>
        <a href="/car-showroom/modules/sales/index.php"
           class="px-4 py-2 rounded-xl text-xs font-700 text-slate-400 hover:text-white transition-all"
           style="background:rgba(30,41,59,0.5);border:1px solid rgba(148,163,184,0.1)">
            <i class="fas fa-xmark mr-1"></i> Clear
        </a>

        <a href="/car-showroom/modules/sales/create.php"
           class="ml-auto px-4 py-2 rounded-xl text-xs font-700 bg-blue-600 hover:bg-blue-700 text-white transition-all flex items-center gap-2">
            <i class="fas fa-plus"></i> New Sale
        </a>
    </form>

    <!-- Table -->
    <div class="section-card">
        <div class="section-header">
            <span class="section-title">
                <i class="fas fa-handshake text-blue-400 mr-2"></i>
                Sales Records
            </span>
        </div>

        <?php if (empty($sales)): ?>
        <div class="text-center py-16 text-slate-600">
            <i class="fas fa-handshake text-4xl mb-3 block opacity-20"></i>
            <p class="font-600 text-slate-500">No sales recorded yet</p>
            <a href="/car-showroom/modules/sales/create.php"
               class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-600 rounded-xl transition-all">
                <i class="fas fa-plus"></i> Record First Sale
            </a>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Vehicle</th>
                    <th>Customer</th>
                    <th>Sale Price</th>
                    <th>Profit</th>
                    <th>Commission</th>
                    <th>Payment</th>
                    <th>Salesperson</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sales as $s): ?>
            <tr>
                <td>
                    <span class="text-blue-400 font-700 text-xs font-mono">
                        <?= htmlspecialchars($s['invoice_no']) ?>
                    </span>
                </td>
                <td>
                    <div class="text-slate-200 font-600">
                        <?= htmlspecialchars($s['year'].' '.$s['make'].' '.$s['model']) ?>
                    </div>
                    <div class="text-slate-600 text-xs"><?= htmlspecialchars($s['variant'] ?? '') ?></div>
                </td>
                <td>
                    <div class="text-slate-300"><?= htmlspecialchars($s['customer_name']) ?></div>
                    <div class="text-slate-600 text-xs"><?= htmlspecialchars($s['customer_phone']) ?></div>
                </td>
                <td class="text-blue-400 font-700"><?= formatPrice($s['final_price']) ?></td>
                <td>
                    <span class="font-700 <?= $s['net_profit'] >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                        <?= formatPrice($s['net_profit']) ?>
                    </span>
                </td>
                <td class="text-amber-400"><?= formatPrice($s['commission_amount']) ?></td>
                <td>
                    <span class="badge badge-<?= $s['payment_type'] ?>">
                        <?= ucfirst(str_replace('_',' ',$s['payment_type'])) ?>
                    </span>
                </td>
                <td class="text-slate-400"><?= htmlspecialchars($s['salesperson_name'] ?? '—') ?></td>
                <td class="text-slate-600 text-xs"><?= date('d M Y', strtotime($s['sale_date'])) ?></td>
                <td>
                    <a href="/car-showroom/modules/sales/view.php?id=<?= $s['id'] ?>"
                       class="action-btn btn-view">
                        <i class="fas fa-eye"></i> View
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-center gap-2 p-4">
            <?php for ($i = 1; $i <= $totalPages; $i++):
                $pUrl = '?' . http_build_query(array_merge($_GET, ['page'=>$i]));
            ?>
            <a href="<?= $pUrl ?>"
               class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-600 transition-all
               <?= $i===$page ? 'bg-blue-600 text-white' : 'text-slate-400 hover:text-white' ?>"
               style="<?= $i!==$page ? 'background:rgba(30,41,59,0.5);border:1px solid rgba(148,163,184,0.1)' : '' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

</div>
</div>
</body>
</html>