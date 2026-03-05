<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('reports.view');
Auth::check();
if (!Auth::hasRole(['Admin', 'Manager'])) {
    setFlash('error', 'Access denied.');
    redirect('dashboard/index.php');
}

$db = Database::getInstance();

// Date filters
$fromDate   = clean($_GET['from']   ?? date('Y-m-01'));
$toDate     = clean($_GET['to']     ?? date('Y-m-d'));
$reportType = clean($_GET['report'] ?? 'overview');

// ── OVERVIEW ──
$overview = $db->fetchOne(
    "SELECT
        COUNT(DISTINCT s.id)              as total_sales,
        COALESCE(SUM(s.final_price),0)    as total_revenue,
        COALESCE(SUM(s.net_profit),0)     as total_profit,
        COALESCE(SUM(s.commission_amount),0) as total_commission
     FROM sales s
     WHERE s.sale_date BETWEEN ? AND ?",
    [$fromDate, $toDate], 'ss'
);

$totalExpenses = $db->fetchOne(
    "SELECT COALESCE(SUM(amount),0) as total FROM expenses
     WHERE expense_date BETWEEN ? AND ?",
    [$fromDate, $toDate], 'ss'
)['total'] ?? 0;

$netPL = ($overview['total_profit'] ?? 0) - $totalExpenses;

$inventoryStats = $db->fetchOne(
    "SELECT
        COUNT(*)                           as total,
        SUM(status='available')            as available,
        SUM(status='reserved')             as reserved,
        SUM(status='sold')                 as sold,
        COALESCE(SUM(CASE WHEN status='available' THEN sale_price END),0) as stock_value,
        COALESCE(SUM(CASE WHEN status='available' THEN purchase_price END),0) as stock_cost
     FROM cars"
);

// ── SALES BY MONTH ──
$salesByMonth = $db->fetchAll(
    "SELECT
        DATE_FORMAT(sale_date,'%b %Y')  as month_label,
        DATE_FORMAT(sale_date,'%Y-%m')  as month_key,
        COUNT(*)                         as count,
        SUM(final_price)                 as revenue,
        SUM(net_profit)                  as profit
     FROM sales
     WHERE sale_date BETWEEN ? AND ?
     GROUP BY month_key, month_label
     ORDER BY month_key ASC",
    [$fromDate, $toDate], 'ss'
);

// ── TOP MAKES ──
$topMakes = $db->fetchAll(
    "SELECT c.make, COUNT(*) as count, SUM(s.net_profit) as profit
     FROM sales s JOIN cars c ON s.car_id = c.id
     WHERE s.sale_date BETWEEN ? AND ?
     GROUP BY c.make ORDER BY count DESC LIMIT 6",
    [$fromDate, $toDate], 'ss'
);

// ── SALES DETAIL ──
$salesDetail = $db->fetchAll(
    "SELECT s.invoice_no, s.sale_date, s.final_price, s.net_profit,
            s.payment_type, s.commission_amount, s.discount,
            c.make, c.model, c.year, c.variant,
            cu.full_name as customer_name, cu.phone as customer_phone,
            u.full_name as salesperson_name
     FROM sales s
     JOIN cars c       ON s.car_id       = c.id
     JOIN customers cu ON s.customer_id  = cu.id
     LEFT JOIN users u ON s.salesperson_id = u.id
     WHERE s.sale_date BETWEEN ? AND ?
     ORDER BY s.sale_date DESC",
    [$fromDate, $toDate], 'ss'
);

// ── EXPENSES BY CATEGORY ──
$expByCategory = $db->fetchAll(
    "SELECT category, COUNT(*) as count, SUM(amount) as total
     FROM expenses
     WHERE expense_date BETWEEN ? AND ?
     GROUP BY category ORDER BY total DESC",
    [$fromDate, $toDate], 'ss'
);

// ── EXPENSES DETAIL ──
$expDetail = $db->fetchAll(
    "SELECT e.*, u.full_name as by_name
     FROM expenses e LEFT JOIN users u ON e.added_by = u.id
     WHERE e.expense_date BETWEEN ? AND ?
     ORDER BY e.expense_date DESC",
    [$fromDate, $toDate], 'ss'
);

// ── COMMISSION REPORT ──
$commReport = $db->fetchAll(
    "SELECT u.full_name,
            COUNT(s.id)              as sales_count,
            SUM(s.final_price)       as total_value,
            SUM(s.commission_amount) as total_commission,
            SUM(CASE WHEN s.commission_paid=1 THEN s.commission_amount END) as paid,
            SUM(CASE WHEN s.commission_paid=0 THEN s.commission_amount END) as unpaid
     FROM sales s JOIN users u ON s.salesperson_id = u.id
     WHERE s.sale_date BETWEEN ? AND ?
     GROUP BY u.id, u.full_name
     ORDER BY total_commission DESC",
    [$fromDate, $toDate], 'ss'
);

// ── INVENTORY PROFIT ──
$invProfit = $db->fetchAll(
    "SELECT c.make, c.model, c.year, c.variant, c.chassis_no,
            c.purchase_price, c.sale_price, c.status, c.color,
            COALESCE(cc.extra,0) as extra_costs,
            (c.sale_price - c.purchase_price - COALESCE(cc.extra,0)) as est_profit
     FROM cars c
     LEFT JOIN (SELECT car_id, SUM(amount) as extra FROM car_costs GROUP BY car_id) cc
        ON c.id = cc.car_id
     ORDER BY c.status ASC, est_profit DESC"
);

$pageTitle = 'Reports';
$pageSub   = date('d M Y', strtotime($fromDate)) . ' — ' . date('d M Y', strtotime($toDate));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports — AutoManager Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/car-showroom/public/css/fa/all.min.css">
    <style>
        * { font-family:'Plus Jakarta Sans',sans-serif; }

        .report-tab {
            padding:10px 18px; border-radius:10px; font-size:0.82rem; font-weight:700;
            cursor:pointer; text-decoration:none; transition:all 0.2s;
            border:1.5px solid transparent; display:inline-flex; align-items:center; gap:7px;
        }
        .report-tab.active {
            background:rgba(37,99,235,0.15); color:#60a5fa;
            border-color:rgba(37,99,235,0.3);
        }
        .report-tab:not(.active) {
            background:rgba(13,20,40,0.8); color:#64748b;
            border-color:rgba(148,163,184,0.08);
        }
        .report-tab:not(.active):hover { color:#e2e8f0; border-color:rgba(148,163,184,0.15); }

        .kpi-card {
            background:#0d1526; border:1px solid rgba(148,163,184,0.08);
            border-radius:14px; padding:18px; position:relative; overflow:hidden;
        }
        .kpi-card::before {
            content:''; position:absolute; top:0; left:0; right:0; height:2px;
        }
        .kpi-card.blue::before   { background:linear-gradient(90deg,#2563eb,#60a5fa); }
        .kpi-card.green::before  { background:linear-gradient(90deg,#16a34a,#4ade80); }
        .kpi-card.purple::before { background:linear-gradient(90deg,#7c3aed,#a78bfa); }
        .kpi-card.red::before    { background:linear-gradient(90deg,#dc2626,#f87171); }
        .kpi-card.amber::before  { background:linear-gradient(90deg,#d97706,#fbbf24); }
        .kpi-card.teal::before   { background:linear-gradient(90deg,#0d9488,#2dd4bf); }

        .kpi-icon {
            width:36px; height:36px; border-radius:10px;
            display:flex; align-items:center; justify-content:center;
            font-size:0.9rem; margin-bottom:12px;
        }
        .kpi-value { font-size:1.35rem; font-weight:800; color:#f1f5f9; line-height:1; }
        .kpi-label { font-size:0.7rem; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; margin-top:4px; }

        .section-card {
            background:#0d1526; border:1px solid rgba(148,163,184,0.08);
            border-radius:16px; overflow:hidden; margin-bottom:20px;
        }
        .section-header {
            padding:14px 20px; border-bottom:1px solid rgba(148,163,184,0.07);
            display:flex; align-items:center; justify-content:space-between;
        }
        .section-title { font-size:0.875rem; font-weight:700; color:#e2e8f0; }

        .data-table { width:100%; border-collapse:collapse; }
        .data-table th {
            font-size:0.68rem; font-weight:700; letter-spacing:0.08em;
            text-transform:uppercase; color:#475569; padding:10px 14px;
            border-bottom:1px solid rgba(148,163,184,0.08); text-align:left; white-space:nowrap;
        }
        .data-table td {
            padding:11px 14px; font-size:0.82rem; color:#94a3b8;
            border-bottom:1px solid rgba(148,163,184,0.04); vertical-align:middle;
        }
        .data-table tr:last-child td { border-bottom:none; }
        .data-table tr:hover td { background:rgba(148,163,184,0.02); }

        .bar-track {
            height:6px; border-radius:10px; background:rgba(30,41,59,0.8); overflow:hidden;
        }
        .bar-fill { height:100%; border-radius:10px; }

        .pl-row {
            display:flex; justify-content:space-between; align-items:center;
            padding:10px 0; border-bottom:1px solid rgba(148,163,184,0.05); font-size:0.875rem;
        }
        .pl-row:last-child { border-bottom:none; }
        .pl-label { color:#64748b; }
        .pl-value { font-weight:700; text-align:right; }

        /* Print styles */
        @media print {
            .sidebar, .topbar, .no-print { display:none !important; }
            .main { margin:0 !important; }
            .content-area { padding:20px !important; }
            body { background:#fff !important; color:#000 !important; }
            .section-card, .kpi-card {
                background:#fff !important; border:1px solid #e5e7eb !important;
                break-inside:avoid;
            }
            .kpi-value, .section-title, .data-table td { color:#000 !important; }
            .data-table th { color:#666 !important; }
        }
    </style>
</head>
<body>
<?php require_once '../../views/layouts/sidebar.php'; ?>
<div class="main">
<?php require_once '../../views/layouts/topbar.php'; ?>
<div class="content-area">

    <!-- Date filter + print -->
    <div class="flex flex-wrap items-center gap-3 mb-5 no-print">
        <form method="GET" class="flex flex-wrap gap-2 items-center">
            <input type="hidden" name="report" value="<?= htmlspecialchars($reportType) ?>">
            <div class="flex items-center gap-2 px-4 py-2 rounded-xl"
                 style="background:#0d1526;border:1px solid rgba(148,163,184,0.08)">
                <label class="text-xs text-slate-500 font-700">FROM</label>
                <input type="date" name="from" value="<?= $fromDate ?>"
                       class="bg-transparent text-slate-300 text-sm border-none outline-none">
            </div>
            <div class="flex items-center gap-2 px-4 py-2 rounded-xl"
                 style="background:#0d1526;border:1px solid rgba(148,163,184,0.08)">
                <label class="text-xs text-slate-500 font-700">TO</label>
                <input type="date" name="to" value="<?= $toDate ?>"
                       class="bg-transparent text-slate-300 text-sm border-none outline-none">
            </div>
            <button type="submit"
                    class="px-4 py-2 rounded-xl text-xs font-700 bg-blue-600 hover:bg-blue-700 text-white transition-all">
                <i class="fas fa-search mr-1"></i> Apply
            </button>
            <!-- Quick ranges -->
            <?php
            $ranges = [
                'This Month'    => [date('Y-m-01'), date('Y-m-d')],
                'Last Month'    => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last month'))],
                'This Year'     => [date('Y-01-01'), date('Y-m-d')],
                'Last 3 Months' => [date('Y-m-d', strtotime('-3 months')), date('Y-m-d')],
            ];
            foreach ($ranges as $label => $range):
            ?>
            <a href="?report=<?= $reportType ?>&from=<?= $range[0] ?>&to=<?= $range[1] ?>"
               class="px-3 py-2 rounded-xl text-xs font-600 transition-all
               <?= ($fromDate===$range[0] && $toDate===$range[1]) ? 'bg-blue-600 text-white' : 'text-slate-400 hover:text-white' ?>"
               style="<?= ($fromDate!==$range[0] || $toDate!==$range[1]) ? 'background:rgba(30,41,59,0.5);border:1px solid rgba(148,163,184,0.1)' : '' ?>">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </form>
        <button onclick="window.print()"
                class="ml-auto px-4 py-2 rounded-xl text-xs font-700 text-white bg-slate-700 hover:bg-slate-600 transition-all flex items-center gap-2">
            <i class="fas fa-print"></i> Print
        </button>
    </div>

    <!-- Report Tabs -->
    <div class="flex flex-wrap gap-2 mb-6 no-print">
        <?php
        $tabs = [
            'overview'  => ['icon'=>'fa-chart-pie',    'label'=>'Overview'],
            'sales'     => ['icon'=>'fa-handshake',    'label'=>'Sales Report'],
            'expenses'  => ['icon'=>'fa-receipt',      'label'=>'Expense Report'],
            'pl'        => ['icon'=>'fa-scale-balanced','label'=>'P&L Statement'],
            'inventory' => ['icon'=>'fa-car',          'label'=>'Inventory Report'],
            'commission'=> ['icon'=>'fa-percent',      'label'=>'Commission Report'],
        ];
        foreach ($tabs as $key => $tab):
        ?>
        <a href="?report=<?= $key ?>&from=<?= $fromDate ?>&to=<?= $toDate ?>"
           class="report-tab <?= $reportType===$key ? 'active' : '' ?>">
            <i class="fas <?= $tab['icon'] ?>"></i> <?= $tab['label'] ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php // ═══════════════════════════════════
    // OVERVIEW
    // ═══════════════════════════════════
    if ($reportType === 'overview'): ?>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <?php
        $kpis = [
            ['color'=>'blue',   'icon'=>'fa-handshake',     'label'=>'Total Sales',     'value'=> number_format($overview['total_sales'])],
            ['color'=>'green',  'icon'=>'fa-coins',         'label'=>'Revenue',         'value'=> formatPrice($overview['total_revenue'])],
            ['color'=>'purple', 'icon'=>'fa-arrow-trend-up','label'=>'Gross Profit',    'value'=> formatPrice($overview['total_profit'])],
            ['color'=>'red',    'icon'=>'fa-receipt',       'label'=>'Total Expenses',  'value'=> formatPrice($totalExpenses)],
            ['color'=>$netPL>=0?'teal':'red', 'icon'=>'fa-scale-balanced','label'=>'Net P&L','value'=> formatPrice($netPL)],
            ['color'=>'amber',  'icon'=>'fa-percent',       'label'=>'Commission Paid', 'value'=> formatPrice($overview['total_commission'])],
        ];
        foreach ($kpis as $kpi):
        ?>
        <div class="kpi-card <?= $kpi['color'] ?>">
            <div class="kpi-icon" style="background:rgba(255,255,255,0.05)">
                <i class="fas <?= $kpi['icon'] ?>"
                   style="color:<?= ['blue'=>'#60a5fa','green'=>'#4ade80','purple'=>'#a78bfa','red'=>'#f87171','amber'=>'#fbbf24','teal'=>'#2dd4bf'][$kpi['color']] ?>"></i>
            </div>
            <div class="kpi-value"><?= $kpi['value'] ?></div>
            <div class="kpi-label"><?= $kpi['label'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-5">

        <!-- Sales by month -->
        <div class="section-card">
            <div class="section-header">
                <span class="section-title"><i class="fas fa-chart-bar text-blue-400 mr-2"></i>Sales by Month</span>
            </div>
            <div style="padding:16px 20px">
                <?php if (empty($salesByMonth)): ?>
                <p class="text-slate-600 text-sm text-center py-6">No sales in this period</p>
                <?php else:
                    $maxRev = max(array_column($salesByMonth, 'revenue'));
                    foreach ($salesByMonth as $m):
                        $pct = $maxRev > 0 ? ($m['revenue']/$maxRev*100) : 0;
                ?>
                <div style="margin-bottom:12px">
                    <div class="flex justify-between mb-1">
                        <span style="font-size:0.78rem;color:#94a3b8;font-weight:600"><?= $m['month_label'] ?></span>
                        <span style="font-size:0.78rem;color:#60a5fa;font-weight:700">
                            <?= $m['count'] ?> sales · <?= formatPrice($m['revenue']) ?>
                        </span>
                    </div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width:<?= $pct ?>%;background:linear-gradient(90deg,#2563eb,#60a5fa)"></div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Top makes -->
        <div class="section-card">
            <div class="section-header">
                <span class="section-title"><i class="fas fa-trophy text-amber-400 mr-2"></i>Top Selling Makes</span>
            </div>
            <div style="padding:16px 20px">
                <?php if (empty($topMakes)): ?>
                <p class="text-slate-600 text-sm text-center py-6">No data</p>
                <?php else:
                    $maxMake = max(array_column($topMakes,'count'));
                    foreach ($topMakes as $i => $m):
                        $pct = $maxMake > 0 ? ($m['count']/$maxMake*100) : 0;
                        $colors = ['#f59e0b','#60a5fa','#4ade80','#a78bfa','#f87171','#2dd4bf'];
                        $color  = $colors[$i % count($colors)];
                ?>
                <div style="margin-bottom:12px">
                    <div class="flex justify-between mb-1">
                        <span style="font-size:0.78rem;color:#94a3b8;font-weight:600"><?= htmlspecialchars($m['make']) ?></span>
                        <span style="font-size:0.78rem;font-weight:700;color:<?= $color ?>">
                            <?= $m['count'] ?> sold · <?= formatPrice($m['profit']) ?> profit
                        </span>
                    </div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- Inventory snapshot -->
    <div class="section-card">
        <div class="section-header">
            <span class="section-title"><i class="fas fa-car text-purple-400 mr-2"></i>Inventory Snapshot</span>
        </div>
        <div style="padding:20px">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <?php
                $invKpis = [
                    ['label'=>'Total Cars',    'value'=>$inventoryStats['total'],      'color'=>'#60a5fa'],
                    ['label'=>'Available',     'value'=>$inventoryStats['available'],  'color'=>'#4ade80'],
                    ['label'=>'Reserved',      'value'=>$inventoryStats['reserved'],   'color'=>'#fbbf24'],
                    ['label'=>'Sold',          'value'=>$inventoryStats['sold'],       'color'=>'#f87171'],
                ];
                foreach ($invKpis as $ik):
                ?>
                <div class="text-center p-4 rounded-xl" style="background:rgba(30,41,59,0.5);border:1px solid rgba(148,163,184,0.07)">
                    <div style="font-size:1.8rem;font-weight:800;color:<?= $ik['color'] ?>"><?= $ik['value'] ?></div>
                    <div style="font-size:0.72rem;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:0.06em"><?= $ik['label'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div class="p-4 rounded-xl" style="background:rgba(37,99,235,0.08);border:1px solid rgba(37,99,235,0.15)">
                    <div class="text-xs text-slate-500 font-700 uppercase tracking-wide">Stock Value (Sale Price)</div>
                    <div class="text-blue-400 text-xl font-800 mt-1"><?= formatPrice($inventoryStats['stock_value']) ?></div>
                </div>
                <div class="p-4 rounded-xl" style="background:rgba(22,163,74,0.08);border:1px solid rgba(22,163,74,0.15)">
                    <div class="text-xs text-slate-500 font-700 uppercase tracking-wide">Stock Cost (Purchase Price)</div>
                    <div class="text-green-400 text-xl font-800 mt-1"><?= formatPrice($inventoryStats['stock_cost']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php // ═══════════════════════════════════
    // SALES REPORT
    // ═══════════════════════════════════
    elseif ($reportType === 'sales'): ?>

    <div class="section-card">
        <div class="section-header">
            <span class="section-title">
                <i class="fas fa-handshake text-blue-400 mr-2"></i>
                Sales Report
                <span class="text-slate-500 font-400 text-sm ml-2">(<?= count($salesDetail) ?> sales)</span>
            </span>
            <div class="flex gap-3 text-xs font-700">
                <span class="text-green-400">Revenue: <?= formatPrice($overview['total_revenue']) ?></span>
                <span class="text-purple-400">Profit: <?= formatPrice($overview['total_profit']) ?></span>
            </div>
        </div>
        <?php if (empty($salesDetail)): ?>
        <div class="text-center py-12 text-slate-600 text-sm">No sales in this period</div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Invoice</th><th>Date</th><th>Vehicle</th><th>Customer</th>
                    <th>Sale Price</th><th>Discount</th><th>Final</th>
                    <th>Profit</th><th>Commission</th><th>Payment</th><th>Salesperson</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($salesDetail as $s): ?>
            <tr>
                <td class="font-mono text-xs text-blue-400 font-700"><?= htmlspecialchars($s['invoice_no']) ?></td>
                <td class="text-xs text-slate-500"><?= date('d M Y', strtotime($s['sale_date'])) ?></td>
                <td>
                    <div class="text-slate-300 font-600"><?= htmlspecialchars($s['year'].' '.$s['make'].' '.$s['model']) ?></div>
                    <div class="text-slate-600 text-xs"><?= htmlspecialchars($s['variant'] ?? '') ?></div>
                </td>
                <td>
                    <div class="text-slate-300"><?= htmlspecialchars($s['customer_name']) ?></div>
                    <div class="text-slate-600 text-xs"><?= htmlspecialchars($s['customer_phone']) ?></div>
                </td>
                <td class="text-slate-300"><?= formatPrice($s['final_price'] + $s['discount']) ?></td>
                <td class="text-red-400"><?= $s['discount'] > 0 ? '−'.formatPrice($s['discount']) : '—' ?></td>
                <td class="text-blue-400 font-700"><?= formatPrice($s['final_price']) ?></td>
                <td class="font-700 <?= $s['net_profit']>=0?'text-green-400':'text-red-400' ?>"><?= formatPrice($s['net_profit']) ?></td>
                <td class="text-amber-400"><?= formatPrice($s['commission_amount']) ?></td>
                <td>
                    <span style="font-size:0.68rem;font-weight:700;padding:2px 8px;border-radius:6px;background:rgba(30,41,59,0.8);color:#64748b">
                        <?= ucfirst(str_replace('_',' ',$s['payment_type'])) ?>
                    </span>
                </td>
                <td class="text-slate-500 text-xs"><?= htmlspecialchars($s['salesperson_name'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <?php // ═══════════════════════════════════
    // EXPENSE REPORT
    // ═══════════════════════════════════
    elseif ($reportType === 'expenses'): ?>

    <!-- Category breakdown -->
    <div class="section-card mb-5">
        <div class="section-header">
            <span class="section-title"><i class="fas fa-chart-pie text-red-400 mr-2"></i>By Category</span>
            <span class="text-red-400 font-700 text-sm"><?= formatPrice($totalExpenses) ?> total</span>
        </div>
        <div style="padding:16px 20px">
            <?php if (empty($expByCategory)): ?>
            <p class="text-slate-600 text-sm text-center py-4">No expenses in this period</p>
            <?php else:
                $maxExp = max(array_column($expByCategory,'total'));
                foreach ($expByCategory as $ec):
                    $pct = $maxExp > 0 ? ($ec['total']/$maxExp*100) : 0;
            ?>
            <div style="margin-bottom:12px">
                <div class="flex justify-between mb-1">
                    <span style="font-size:0.78rem;color:#94a3b8;font-weight:600">
                        <?= htmlspecialchars($ec['category']) ?>
                        <span style="color:#475569;font-weight:400">(<?= $ec['count'] ?>)</span>
                    </span>
                    <span style="font-size:0.78rem;color:#f87171;font-weight:700"><?= formatPrice($ec['total']) ?></span>
                </div>
                <div class="bar-track">
                    <div class="bar-fill" style="width:<?= $pct ?>%;background:linear-gradient(90deg,#dc2626,#f87171)"></div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Expense detail table -->
    <div class="section-card">
        <div class="section-header">
            <span class="section-title"><i class="fas fa-receipt text-red-400 mr-2"></i>All Expenses</span>
        </div>
        <?php if (empty($expDetail)): ?>
        <div class="text-center py-12 text-slate-600 text-sm">No expenses in this period</div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr><th>Date</th><th>Category</th><th>Description</th><th>Paid To</th><th>Amount</th><th>Added By</th></tr>
            </thead>
            <tbody>
            <?php foreach ($expDetail as $e): ?>
            <tr>
                <td class="text-xs text-slate-500"><?= date('d M Y', strtotime($e['expense_date'])) ?></td>
                <td><span style="font-size:0.7rem;font-weight:700;padding:2px 8px;border-radius:6px;background:rgba(220,38,38,0.1);color:#f87171;border:1px solid rgba(220,38,38,0.2)"><?= htmlspecialchars($e['category']) ?></span></td>
                <td class="text-slate-300"><?= htmlspecialchars($e['description'] ?? '—') ?></td>
                <td class="text-slate-400"><?= htmlspecialchars($e['paid_to'] ?? '—') ?></td>
                <td class="text-red-400 font-700"><?= formatPrice($e['amount']) ?></td>
                <td class="text-xs text-slate-600"><?= htmlspecialchars($e['by_name'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <?php // ═══════════════════════════════════
    // P&L STATEMENT
    // ═══════════════════════════════════
    elseif ($reportType === 'pl'): ?>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
        <div class="section-card">
            <div class="section-header">
                <span class="section-title"><i class="fas fa-scale-balanced text-teal-400 mr-2"></i>
                    Profit & Loss — <?= date('d M Y', strtotime($fromDate)) ?> to <?= date('d M Y', strtotime($toDate)) ?>
                </span>
            </div>
            <div style="padding:20px">

                <!-- Income -->
                <div style="font-size:0.7rem;font-weight:700;color:#4ade80;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:8px">
                    INCOME
                </div>
                <div class="pl-row">
                    <span class="pl-label">Gross Sales Revenue</span>
                    <span class="pl-value text-green-400"><?= formatPrice($overview['total_revenue']) ?></span>
                </div>
                <div class="pl-row">
                    <span class="pl-label">Total Sales Count</span>
                    <span class="pl-value text-slate-300"><?= number_format($overview['total_sales']) ?> vehicles</span>
                </div>

                <div style="margin:16px 0 8px;font-size:0.7rem;font-weight:700;color:#f87171;text-transform:uppercase;letter-spacing:0.1em">
                    COST OF GOODS SOLD
                </div>
                <?php
                $totalPurchase = $db->fetchOne(
                    "SELECT COALESCE(SUM(s.purchase_price),0) as t FROM sales s WHERE s.sale_date BETWEEN ? AND ?",
                    [$fromDate,$toDate],'ss'
                )['t'] ?? 0;
                $totalExtraCosts = $db->fetchOne(
                    "SELECT COALESCE(SUM(s.total_extra_costs),0) as t FROM sales s WHERE s.sale_date BETWEEN ? AND ?",
                    [$fromDate,$toDate],'ss'
                )['t'] ?? 0;
                ?>
                <div class="pl-row">
                    <span class="pl-label">Purchase Cost of Sold Cars</span>
                    <span class="pl-value text-red-400">— <?= formatPrice($totalPurchase) ?></span>
                </div>
                <div class="pl-row">
                    <span class="pl-label">Refurbishment / Extra Costs</span>
                    <span class="pl-value text-red-400">— <?= formatPrice($totalExtraCosts) ?></span>
                </div>
                <div class="pl-row" style="border-top:2px solid rgba(148,163,184,0.1);padding-top:12px;margin-top:4px">
                    <span class="pl-label font-700 text-slate-300">Gross Profit</span>
                    <span class="pl-value text-purple-400 text-base"><?= formatPrice($overview['total_profit']) ?></span>
                </div>

                <div style="margin:16px 0 8px;font-size:0.7rem;font-weight:700;color:#f87171;text-transform:uppercase;letter-spacing:0.1em">
                    OPERATING EXPENSES
                </div>
                <?php foreach ($expByCategory as $ec): ?>
                <div class="pl-row">
                    <span class="pl-label"><?= htmlspecialchars($ec['category']) ?></span>
                    <span class="pl-value text-red-400">— <?= formatPrice($ec['total']) ?></span>
                </div>
                <?php endforeach; ?>
                <div class="pl-row">
                    <span class="pl-label">Staff Commission</span>
                    <span class="pl-value text-amber-400">— <?= formatPrice($overview['total_commission']) ?></span>
                </div>
                <div class="pl-row" style="border-top:2px solid rgba(148,163,184,0.1);padding-top:12px;margin-top:4px">
                    <span class="pl-label font-700 text-slate-300">Total Operating Expenses</span>
                    <span class="pl-value text-red-400 text-base">— <?= formatPrice($totalExpenses + $overview['total_commission']) ?></span>
                </div>

                <!-- Net P&L -->
                <div style="margin-top:20px;padding:18px;border-radius:14px;
                    background:<?= $netPL>=0?'rgba(22,163,74,0.08)':'rgba(220,38,38,0.08)' ?>;
                    border:1px solid <?= $netPL>=0?'rgba(22,163,74,0.2)':'rgba(220,38,38,0.2)' ?>">
                    <div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;
                        color:<?= $netPL>=0?'#4ade80':'#f87171' ?>">
                        NET PROFIT / LOSS
                    </div>
                    <div style="font-size:2rem;font-weight:800;color:<?= $netPL>=0?'#4ade80':'#f87171' ?>;margin-top:4px">
                        <?= formatPrice($netPL) ?>
                    </div>
                    <?php if ($overview['total_revenue'] > 0): ?>
                    <div style="font-size:0.75rem;color:#64748b;margin-top:4px">
                        Margin: <?= number_format(($netPL / $overview['total_revenue']) * 100, 1) ?>%
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Payment method breakdown -->
        <div>
            <div class="section-card mb-5">
                <div class="section-header">
                    <span class="section-title"><i class="fas fa-credit-card text-blue-400 mr-2"></i>Payment Methods</span>
                </div>
                <div style="padding:16px 20px">
                    <?php
                    $payMethods = $db->fetchAll(
                        "SELECT payment_type, COUNT(*) as count, SUM(final_price) as total
                         FROM sales WHERE sale_date BETWEEN ? AND ?
                         GROUP BY payment_type ORDER BY total DESC",
                        [$fromDate, $toDate], 'ss'
                    );
                    $maxPay = !empty($payMethods) ? max(array_column($payMethods,'total')) : 1;
                    foreach ($payMethods as $pm):
                        $pct = $maxPay > 0 ? ($pm['total']/$maxPay*100) : 0;
                    ?>
                    <div style="margin-bottom:10px">
                        <div class="flex justify-between mb-1">
                            <span style="font-size:0.78rem;color:#94a3b8;font-weight:600">
                                <?= ucfirst(str_replace('_',' ',$pm['payment_type'])) ?>
                                (<?= $pm['count'] ?>)
                            </span>
                            <span style="font-size:0.78rem;color:#60a5fa;font-weight:700"><?= formatPrice($pm['total']) ?></span>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width:<?= $pct ?>%;background:linear-gradient(90deg,#2563eb,#60a5fa)"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Pending amounts -->
            <div class="section-card">
                <div class="section-header">
                    <span class="section-title"><i class="fas fa-clock text-amber-400 mr-2"></i>Outstanding Amounts</span>
                </div>
                <div style="padding:16px 20px">
                    <?php
                    $outstanding = $db->fetchOne(
                        "SELECT COALESCE(SUM(remaining_amount),0) as remaining,
                                COALESCE(SUM(CASE WHEN commission_paid=0 THEN commission_amount END),0) as comm
                         FROM sales"
                    );
                    ?>
                    <div class="pl-row">
                        <span class="pl-label">Customer Pending Payments</span>
                        <span class="pl-value text-amber-400"><?= formatPrice($outstanding['remaining']) ?></span>
                    </div>
                    <div class="pl-row">
                        <span class="pl-label">Unpaid Commissions</span>
                        <span class="pl-value text-amber-400"><?= formatPrice($outstanding['comm']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php // ═══════════════════════════════════
    // INVENTORY REPORT
    // ═══════════════════════════════════
    elseif ($reportType === 'inventory'): ?>

    <div class="section-card">
        <div class="section-header">
            <span class="section-title"><i class="fas fa-car text-purple-400 mr-2"></i>All Inventory with Profit Analysis</span>
        </div>
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Vehicle</th><th>Chassis</th><th>Color</th><th>Status</th>
                    <th>Purchase</th><th>Extra Costs</th><th>Sale Price</th><th>Est. Profit</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($invProfit as $iv):
                $statusColors = [
                    'available' => ['bg'=>'rgba(22,163,74,0.12)','color'=>'#4ade80'],
                    'reserved'  => ['bg'=>'rgba(217,119,6,0.12)','color'=>'#fbbf24'],
                    'sold'      => ['bg'=>'rgba(220,38,38,0.12)','color'=>'#f87171'],
                ];
                $sc = $statusColors[$iv['status']] ?? $statusColors['available'];
            ?>
            <tr>
                <td>
                    <div class="text-slate-200 font-600"><?= htmlspecialchars($iv['year'].' '.$iv['make'].' '.$iv['model']) ?></div>
                    <div class="text-slate-600 text-xs"><?= htmlspecialchars($iv['variant'] ?? '') ?></div>
                </td>
                <td class="font-mono text-xs text-slate-500"><?= htmlspecialchars($iv['chassis_no']) ?></td>
                <td class="text-slate-400"><?= htmlspecialchars($iv['color'] ?? '—') ?></td>
                <td>
                    <span style="font-size:0.68rem;font-weight:700;padding:3px 8px;border-radius:20px;
                        background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;text-transform:uppercase">
                        <?= $iv['status'] ?>
                    </span>
                </td>
                <td class="text-slate-300"><?= formatPrice($iv['purchase_price']) ?></td>
                <td class="text-red-400"><?= $iv['extra_costs'] > 0 ? formatPrice($iv['extra_costs']) : '—' ?></td>
                <td class="text-blue-400 font-700"><?= formatPrice($iv['sale_price']) ?></td>
                <td class="font-700 <?= $iv['est_profit']>=0?'text-green-400':'text-red-400' ?>">
                    <?= formatPrice($iv['est_profit']) ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <?php // ═══════════════════════════════════
    // COMMISSION REPORT
    // ═══════════════════════════════════
    elseif ($reportType === 'commission'): ?>

    <div class="section-card">
        <div class="section-header">
            <span class="section-title"><i class="fas fa-percent text-amber-400 mr-2"></i>Commission Report by Salesperson</span>
        </div>
        <?php if (empty($commReport)): ?>
        <div class="text-center py-12 text-slate-600 text-sm">No commission data in this period</div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Salesperson</th><th>Sales Count</th><th>Total Sales Value</th>
                    <th>Total Commission</th><th>Paid</th><th>Unpaid</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($commReport as $cr): ?>
            <tr>
                <td class="text-slate-200 font-600"><?= htmlspecialchars($cr['full_name']) ?></td>
                <td class="text-slate-300"><?= $cr['sales_count'] ?> sales</td>
                <td class="text-blue-400 font-700"><?= formatPrice($cr['total_value']) ?></td>
                <td class="text-amber-400 font-700"><?= formatPrice($cr['total_commission']) ?></td>
                <td class="text-green-400"><?= formatPrice($cr['paid'] ?? 0) ?></td>
                <td class="text-red-400"><?= formatPrice($cr['unpaid'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>

</div>
</div>
</body>
</html>