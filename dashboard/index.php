<?php
require_once '../config/database.php';
require_once '../core/Database.php';
require_once '../core/Auth.php';
require_once '../core/functions.php';

Auth::check();

$db   = Database::getInstance();
$name = $_SESSION['user_name'];

// --- Stats ---
$totalCars     = $db->fetchOne("SELECT COUNT(*) as cnt FROM cars")['cnt'] ?? 0;
$availableCars = $db->fetchOne("SELECT COUNT(*) as cnt FROM cars WHERE status='available'")['cnt'] ?? 0;
$reservedCars  = $db->fetchOne("SELECT COUNT(*) as cnt FROM cars WHERE status='reserved'")['cnt'] ?? 0;
$soldCars      = $db->fetchOne("SELECT COUNT(*) as cnt FROM cars WHERE status='sold'")['cnt'] ?? 0;
$newLeads      = $db->fetchOne("SELECT COUNT(*) as cnt FROM leads WHERE status='new'")['cnt'] ?? 0;
$monthSales    = $db->fetchOne("SELECT COALESCE(SUM(final_price),0) as total FROM sales WHERE MONTH(sale_date)=MONTH(NOW()) AND YEAR(sale_date)=YEAR(NOW())")['total'] ?? 0;
$monthProfit   = $db->fetchOne("SELECT COALESCE(SUM(net_profit),0) as total FROM sales WHERE MONTH(sale_date)=MONTH(NOW()) AND YEAR(sale_date)=YEAR(NOW())")['total'] ?? 0;
$totalExpenses = $db->fetchOne("SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE MONTH(expense_date)=MONTH(NOW()) AND YEAR(expense_date)=YEAR(NOW())")['total'] ?? 0;

// Recent sales
$recentSales = $db->fetchAll(
    "SELECT s.*, c.make, c.model, c.year, cu.full_name as customer_name
     FROM sales s
     JOIN cars c ON s.car_id = c.id
     JOIN customers cu ON s.customer_id = cu.id
     ORDER BY s.created_at DESC LIMIT 5"
);

// Recent leads
$recentLeads = $db->fetchAll(
    "SELECT l.*, c.make, c.model FROM leads l
     LEFT JOIN cars c ON l.car_id = c.id
     ORDER BY l.created_at DESC LIMIT 5"
);

$pageTitle = 'Dashboard';
$pageSub   = date('l, d F Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — AutoManager Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/car-showroom/public/css/fa/all.min.css">
</head>
<body>

<?php require_once '../views/layouts/sidebar.php'; ?>

<div class="main">

<?php require_once '../views/layouts/topbar.php'; ?>

<div class="content-area">

    <!-- Flash -->
    <?php $flash = getFlash(); if ($flash): ?>
    <div class="mb-5 px-4 py-3 rounded-xl text-sm flex items-center gap-2
        <?= $flash['type']==='success'
            ? 'bg-green-500/10 border border-green-500/20 text-green-400'
            : 'bg-red-500/10 border border-red-500/20 text-red-400' ?>">
        <i class="fas <?= $flash['type']==='success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- ===== STAT CARDS ===== -->
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">

        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="fas fa-car"></i></div>
            <div class="stat-value"><?= $totalCars ?></div>
            <div class="stat-label">Total Cars</div>
        </div>

        <div class="stat-card green">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value"><?= $availableCars ?></div>
            <div class="stat-label">Available</div>
        </div>

        <div class="stat-card amber">
            <div class="stat-icon amber"><i class="fas fa-clock"></i></div>
            <div class="stat-value"><?= $reservedCars ?></div>
            <div class="stat-label">Reserved</div>
        </div>

        <div class="stat-card red">
            <div class="stat-icon red"><i class="fas fa-tag"></i></div>
            <div class="stat-value"><?= $soldCars ?></div>
            <div class="stat-label">Sold</div>
        </div>

        <div class="stat-card purple">
            <div class="stat-icon purple"><i class="fas fa-filter"></i></div>
            <div class="stat-value"><?= $newLeads ?></div>
            <div class="stat-label">New Leads</div>
        </div>

        <div class="stat-card teal">
            <div class="stat-icon teal"><i class="fas fa-coins"></i></div>
            <div class="stat-value"><?= number_format($monthProfit / 1000, 0) ?>K</div>
            <div class="stat-label">Month Profit</div>
        </div>

    </div>

    <!-- ===== FINANCIAL SUMMARY ===== -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

        <div class="section-card p-5">
            <div class="text-slate-500 text-xs font-700 uppercase tracking-widest mb-3">
                <i class="fas fa-chart-line text-blue-500 mr-1"></i> Monthly Sales
            </div>
            <div class="text-2xl font-800 text-white"><?= formatPrice($monthSales) ?></div>
            <div class="text-slate-600 text-xs mt-1"><?= date('F Y') ?></div>
        </div>

        <div class="section-card p-5">
            <div class="text-slate-500 text-xs font-700 uppercase tracking-widest mb-3">
                <i class="fas fa-arrow-trend-up text-green-500 mr-1"></i> Monthly Profit
            </div>
            <div class="text-2xl font-800 text-white"><?= formatPrice($monthProfit) ?></div>
            <div class="text-slate-600 text-xs mt-1"><?= date('F Y') ?></div>
        </div>

        <div class="section-card p-5">
            <div class="text-slate-500 text-xs font-700 uppercase tracking-widest mb-3">
                <i class="fas fa-receipt text-red-500 mr-1"></i> Monthly Expenses
            </div>
            <div class="text-2xl font-800 text-white"><?= formatPrice($totalExpenses) ?></div>
            <div class="text-slate-600 text-xs mt-1"><?= date('F Y') ?></div>
        </div>

    </div>

    <!-- ===== TABLES ROW ===== -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

        <!-- Recent Sales -->
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">
                    <i class="fas fa-handshake text-blue-500 mr-2"></i>Recent Sales
                </span>
                <a href="/car-showroom/modules/sales/index.php" class="btn-sm btn-ghost">View All</a>
            </div>
            <?php if (empty($recentSales)): ?>
            <div class="text-center py-10 text-slate-600">
                <i class="fas fa-inbox text-2xl mb-2 block"></i>
                No sales recorded yet
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentSales as $s): ?>
                <tr>
                    <td>
                        <span class="car-name">
                            <?= htmlspecialchars($s['year'].' '.$s['make'].' '.$s['model']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($s['customer_name']) ?></td>
                    <td class="text-green-400 font-600"><?= formatPrice($s['final_price']) ?></td>
                    <td><?= date('d M', strtotime($s['sale_date'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Recent Leads -->
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">
                    <i class="fas fa-filter text-purple-500 mr-2"></i>Recent Leads
                </span>
                <a href="/car-showroom/modules/leads/index.php" class="btn-sm btn-ghost">View All</a>
            </div>
            <?php if (empty($recentLeads)): ?>
            <div class="text-center py-10 text-slate-600">
                <i class="fas fa-inbox text-2xl mb-2 block"></i>
                No leads yet
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Interest</th>
                        <th>Source</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentLeads as $l): ?>
                <tr>
                    <td><span class="car-name"><?= htmlspecialchars($l['name']) ?></span></td>
                    <td><?= $l['make'] ? htmlspecialchars($l['make'].' '.$l['model']) : '—' ?></td>
                    <td class="capitalize"><?= htmlspecialchars($l['source']) ?></td>
                    <td>
                        <span class="badge badge-<?= $l['status'] ?>">
                            <?= ucfirst($l['status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div>
</div><!-- /content-area -->
</div><!-- /main -->

<style>
.stat-card {
    background: #0d1526;
    border: 1px solid rgba(148,163,184,0.08);
    border-radius: 16px; padding: 22px;
    transition: transform 0.2s, box-shadow 0.2s;
    position: relative; overflow: hidden;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(0,0,0,0.3); }
.stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; }
.stat-card.blue::before   { background: linear-gradient(90deg,#2563eb,#60a5fa); }
.stat-card.green::before  { background: linear-gradient(90deg,#16a34a,#4ade80); }
.stat-card.amber::before  { background: linear-gradient(90deg,#d97706,#fbbf24); }
.stat-card.red::before    { background: linear-gradient(90deg,#dc2626,#f87171); }
.stat-card.purple::before { background: linear-gradient(90deg,#7c3aed,#a78bfa); }
.stat-card.teal::before   { background: linear-gradient(90deg,#0d9488,#2dd4bf); }

.stat-icon {
    width:44px; height:44px; border-radius:12px;
    display:flex; align-items:center; justify-content:center;
    font-size:1.1rem; margin-bottom:14px;
}
.stat-icon.blue   { background:rgba(37,99,235,0.15);  color:#60a5fa; }
.stat-icon.green  { background:rgba(22,163,74,0.15);   color:#4ade80; }
.stat-icon.amber  { background:rgba(217,119,6,0.15);   color:#fbbf24; }
.stat-icon.red    { background:rgba(220,38,38,0.15);   color:#f87171; }
.stat-icon.purple { background:rgba(124,58,237,0.15);  color:#a78bfa; }
.stat-icon.teal   { background:rgba(13,148,136,0.15);  color:#2dd4bf; }

.stat-value { font-size:1.75rem; font-weight:800; color:#f1f5f9; line-height:1; margin-bottom:4px; }
.stat-label { font-size:0.78rem; color:#64748b; font-weight:500; text-transform:uppercase; letter-spacing:0.06em; }

.section-card {
    background:#0d1526;
    border:1px solid rgba(148,163,184,0.08);
    border-radius:16px; overflow:hidden;
}
.section-header {
    padding:18px 20px;
    border-bottom:1px solid rgba(148,163,184,0.07);
    display:flex; align-items:center; justify-content:space-between;
}
.section-title { font-size:0.9rem; font-weight:700; color:#e2e8f0; }

.btn-sm  { font-size:0.75rem; font-weight:600; padding:6px 14px; border-radius:8px; text-decoration:none; transition:all 0.2s; }
.btn-ghost { color:#60a5fa; background:rgba(37,99,235,0.1); border:1px solid rgba(37,99,235,0.2); }
.btn-ghost:hover { background:rgba(37,99,235,0.2); }

.data-table { width:100%; border-collapse:collapse; }
.data-table th {
    font-size:0.7rem; font-weight:700; letter-spacing:0.08em;
    text-transform:uppercase; color:#475569;
    padding:10px 16px; border-bottom:1px solid rgba(148,163,184,0.08); text-align:left;
}
.data-table td {
    padding:12px 16px; border-bottom:1px solid rgba(148,163,184,0.05);
    font-size:0.85rem; color:#94a3b8; vertical-align:middle;
}
.data-table tr:last-child td { border-bottom:none; }
.data-table tr:hover td { background:rgba(148,163,184,0.03); }
.data-table .car-name { color:#e2e8f0; font-weight:600; }

.badge { font-size:0.68rem; font-weight:700; padding:3px 10px; border-radius:20px; letter-spacing:0.04em; text-transform:uppercase; }
.badge-new        { background:rgba(37,99,235,0.15);  color:#60a5fa; }
.badge-contacted  { background:rgba(124,58,237,0.15); color:#a78bfa; }
.badge-interested { background:rgba(13,148,136,0.15); color:#2dd4bf; }
.badge-negotiating{ background:rgba(217,119,6,0.15);  color:#fbbf24; }
.badge-closed_won { background:rgba(22,163,74,0.15);  color:#4ade80; }
.badge-closed_lost{ background:rgba(220,38,38,0.15);  color:#f87171; }
</style>

</body>
</html>