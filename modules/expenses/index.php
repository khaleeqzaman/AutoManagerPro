<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('expenses.view');

Auth::check();

$db = Database::getInstance();

// Filters
$where  = [];
$params = [];
$types  = '';

if (!empty($_GET['category'])) {
    $where[]  = "e.category = ?";
    $params[] = clean($_GET['category']);
    $types   .= 's';
}
if (!empty($_GET['month'])) {
    $where[]  = "DATE_FORMAT(e.expense_date, '%Y-%m') = ?";
    $params[] = clean($_GET['month']);
    $types   .= 's';
}
if (!empty($_GET['search'])) {
    $s        = '%' . clean($_GET['search']) . '%';
    $where[]  = "(e.category LIKE ? OR e.description LIKE ? OR e.paid_to LIKE ?)";
    $params[] = $s; $params[] = $s; $params[] = $s;
    $types   .= 'sss';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Summary stats
$summary = $db->fetchOne(
    "SELECT
        COUNT(*) as total_count,
        COALESCE(SUM(amount),0) as total_amount,
        COALESCE(SUM(CASE WHEN DATE_FORMAT(expense_date,'%Y-%m')=DATE_FORMAT(NOW(),'%Y-%m') THEN amount END),0) as this_month,
        COALESCE(SUM(CASE WHEN DATE_FORMAT(expense_date,'%Y-%m')=DATE_FORMAT(DATE_SUB(NOW(),INTERVAL 1 MONTH),'%Y-%m') THEN amount END),0) as last_month
     FROM expenses"
);

// Category totals for chart
$categoryTotals = $db->fetchAll(
    "SELECT category, SUM(amount) as total
     FROM expenses
     WHERE DATE_FORMAT(expense_date,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')
     GROUP BY category ORDER BY total DESC"
);

// Pagination
$perPage    = 15;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;
$totalRows  = $db->fetchOne(
    "SELECT COUNT(*) as cnt FROM expenses e $whereSQL",
    $params, $types
)['cnt'] ?? 0;
$totalPages = ceil($totalRows / $perPage);

$expenses = $db->fetchAll(
    "SELECT e.*, u.full_name as added_by_name
     FROM expenses e
     LEFT JOIN users u ON e.added_by = u.id
     $whereSQL
     ORDER BY e.expense_date DESC, e.id DESC
     LIMIT $perPage OFFSET $offset",
    $params, $types
);

// All categories for filter
$categories = $db->fetchAll("SELECT DISTINCT category FROM expenses ORDER BY category");

$flash     = getFlash();
$pageTitle = 'Expenses';
$pageSub   = 'Track all showroom expenses';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses — AutoManager Pro</title>
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
        .stat-card.red::before    { background:linear-gradient(90deg,#dc2626,#f87171); }
        .stat-card.amber::before  { background:linear-gradient(90deg,#d97706,#fbbf24); }
        .stat-card.blue::before   { background:linear-gradient(90deg,#2563eb,#60a5fa); }
        .stat-card.green::before  { background:linear-gradient(90deg,#16a34a,#4ade80); }
        .stat-icon {
            width:38px; height:38px; border-radius:10px;
            display:flex; align-items:center; justify-content:center;
            font-size:1rem; margin-bottom:12px;
        }
        .stat-icon.red   { background:rgba(220,38,38,0.15);  color:#f87171; }
        .stat-icon.amber { background:rgba(217,119,6,0.15);  color:#fbbf24; }
        .stat-icon.blue  { background:rgba(37,99,235,0.15);  color:#60a5fa; }
        .stat-icon.green { background:rgba(22,163,74,0.15);  color:#4ade80; }
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
            padding:12px 16px;
            font-size:0.85rem; color:#94a3b8;
            border-bottom:1px solid rgba(148,163,184,0.05);
            vertical-align:middle;
        }
        .data-table tr:last-child td { border-bottom:none; }
        .data-table tr:hover td { background:rgba(148,163,184,0.02); }

        .cat-badge {
            font-size:0.7rem; font-weight:700;
            padding:3px 10px; border-radius:20px;
            background:rgba(30,41,59,0.8);
            border:1px solid rgba(148,163,184,0.1);
            color:#94a3b8; white-space:nowrap;
        }

        .action-btn {
            display:inline-flex; align-items:center; gap:5px;
            padding:5px 10px; border-radius:7px;
            font-size:0.75rem; font-weight:600;
            text-decoration:none; transition:all 0.15s;
            border:1px solid transparent; cursor:pointer;
            background:none;
        }
        .btn-del { background:rgba(220,38,38,0.1); color:#f87171; border-color:rgba(220,38,38,0.2); }
        .btn-del:hover { background:rgba(220,38,38,0.2); }

        .cat-bar {
            display:flex; align-items:center; gap:10px;
            padding:8px 0;
            border-bottom:1px solid rgba(148,163,184,0.05);
        }
        .cat-bar:last-child { border-bottom:none; }
        .cat-bar-label { font-size:0.78rem; color:#94a3b8; min-width:120px; }
        .cat-bar-track {
            flex:1; height:6px; border-radius:10px;
            background:rgba(30,41,59,0.8);
            overflow:hidden;
        }
        .cat-bar-fill { height:100%; border-radius:10px; background:linear-gradient(90deg,#dc2626,#f87171); }
        .cat-bar-amount { font-size:0.78rem; color:#f87171; font-weight:700; min-width:90px; text-align:right; }
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
        <div class="stat-card red">
            <div class="stat-icon red"><i class="fas fa-receipt"></i></div>
            <div class="stat-value"><?= number_format($summary['this_month']/1000, 0) ?>K</div>
            <div class="stat-label">This Month</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-icon amber"><i class="fas fa-clock-rotate-left"></i></div>
            <div class="stat-value"><?= number_format($summary['last_month']/1000, 0) ?>K</div>
            <div class="stat-label">Last Month</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="fas fa-list"></i></div>
            <div class="stat-value"><?= number_format($summary['total_count']) ?></div>
            <div class="stat-label">Total Records</div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon green"><i class="fas fa-coins"></i></div>
            <div class="stat-value"><?= number_format($summary['total_amount']/1000000, 1) ?>M</div>
            <div class="stat-label">All Time Total</div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">

        <!-- Category breakdown -->
        <div class="section-card xl:col-span-1">
            <div class="section-header">
                <span class="section-title">
                    <i class="fas fa-chart-pie text-red-400 mr-2"></i>
                    This Month by Category
                </span>
            </div>
            <div style="padding:16px 20px">
                <?php if (empty($categoryTotals)): ?>
                <div class="text-center py-6 text-slate-600 text-sm">No expenses this month</div>
                <?php else:
                    $maxCat = max(array_column($categoryTotals, 'total'));
                    foreach ($categoryTotals as $ct):
                        $pct = $maxCat > 0 ? ($ct['total'] / $maxCat * 100) : 0;
                ?>
                <div class="cat-bar">
                    <span class="cat-bar-label"><?= htmlspecialchars($ct['category']) ?></span>
                    <div class="cat-bar-track">
                        <div class="cat-bar-fill" style="width:<?= $pct ?>%"></div>
                    </div>
                    <span class="cat-bar-amount"><?= formatPrice($ct['total']) ?></span>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Filters + Add button -->
        <div class="xl:col-span-2 flex flex-col gap-4">
            <form method="GET" class="p-4 rounded-xl flex flex-wrap gap-3"
                  style="background:#0d1526;border:1px solid rgba(148,163,184,0.08)">

                <input type="text" name="search"
                       placeholder="Search description, category, paid to..."
                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                       class="flex-1 min-w-[160px] px-4 py-2 rounded-xl text-sm text-slate-200 placeholder-slate-600"
                       style="background:rgba(30,41,59,0.8);border:1.5px solid rgba(148,163,184,0.12)">

                <select name="category" class="px-4 py-2 rounded-xl text-sm text-slate-300"
                        style="background:rgba(30,41,59,0.8);border:1.5px solid rgba(148,163,184,0.12)">
                    <option value="">All Categories</option>
                    <?php
                    $defaultCats = ['Office Rent','Staff Salary','Electricity','Advertising',
                                    'Repair','Maintenance','Fuel','Other'];
                    $allCats = array_unique(array_merge(
                        $defaultCats,
                        array_column($categories, 'category')
                    ));
                    sort($allCats);
                    foreach ($allCats as $cat):
                    ?>
                    <option value="<?= htmlspecialchars($cat) ?>"
                        <?= (($_GET['category']??'')===$cat)?'selected':'' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
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
                <a href="/car-showroom/modules/expenses/index.php"
                   class="px-4 py-2 rounded-xl text-xs font-700 text-slate-400 hover:text-white transition-all"
                   style="background:rgba(30,41,59,0.5);border:1px solid rgba(148,163,184,0.1)">
                    <i class="fas fa-xmark mr-1"></i> Clear
                </a>
            </form>

            <div class="flex justify-end">
                <a href="/car-showroom/modules/expenses/add.php"
                   class="px-5 py-2.5 rounded-xl text-sm font-700 bg-blue-600 hover:bg-blue-700 text-white transition-all flex items-center gap-2">
                    <i class="fas fa-plus"></i> Add Expense
                </a>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="section-card">
        <div class="section-header">
            <span class="section-title">
                <i class="fas fa-receipt text-red-400 mr-2"></i>
                Expense Records
                <span class="text-slate-500 font-400 text-sm ml-2">(<?= $totalRows ?>)</span>
            </span>
            <?php if (!empty($_GET['month']) || !empty($_GET['category'])): ?>
            <span class="text-xs text-slate-500">
                Filtered total:
                <span class="text-red-400 font-700">
                    <?php
                    $filteredTotal = $db->fetchOne(
                        "SELECT COALESCE(SUM(amount),0) as t FROM expenses e $whereSQL",
                        $params, $types
                    )['t'] ?? 0;
                    echo formatPrice($filteredTotal);
                    ?>
                </span>
            </span>
            <?php endif; ?>
        </div>

        <?php if (empty($expenses)): ?>
        <div class="text-center py-16 text-slate-600">
            <i class="fas fa-receipt text-4xl mb-3 block opacity-20"></i>
            <p class="font-600 text-slate-500">No expenses found</p>
            <a href="/car-showroom/modules/expenses/add.php"
               class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-600 rounded-xl transition-all">
                <i class="fas fa-plus"></i> Add First Expense
            </a>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Paid To</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Added By</th>
                    <th>Receipt</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($expenses as $i => $exp): ?>
            <tr>
                <td class="text-slate-600 text-xs"><?= $offset + $i + 1 ?></td>
                <td><span class="cat-badge"><?= htmlspecialchars($exp['category']) ?></span></td>
                <td>
                    <span class="text-slate-300">
                        <?= htmlspecialchars(substr($exp['description'] ?? '—', 0, 50)) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($exp['paid_to'] ?? '—') ?></td>
                <td class="text-red-400 font-700"><?= formatPrice($exp['amount']) ?></td>
                <td class="text-slate-500 text-xs"><?= date('d M Y', strtotime($exp['expense_date'])) ?></td>
                <td class="text-slate-500 text-xs"><?= htmlspecialchars($exp['added_by_name'] ?? '—') ?></td>
                <td>
                    <?php if ($exp['receipt_image']): ?>
                    <a href="<?= UPLOAD_URL . htmlspecialchars($exp['receipt_image']) ?>"
                       target="_blank"
                       class="text-blue-400 hover:text-blue-300 text-xs">
                        <i class="fas fa-image mr-1"></i>View
                    </a>
                    <?php else: ?>
                    <span class="text-slate-700 text-xs">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (Auth::hasRole(['Admin','Manager'])): ?>
                    <button onclick="confirmDelete(<?= $exp['id'] ?>, '<?= htmlspecialchars($exp['category'], ENT_QUOTES) ?>')"
                            class="action-btn btn-del">
                        <i class="fas fa-trash"></i>
                    </button>
                    <?php endif; ?>
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

<!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden items-center justify-center"
     style="background:rgba(0,0,0,0.7);backdrop-filter:blur(4px)">
    <div class="rounded-2xl p-6 w-full max-w-sm mx-4"
         style="background:#0d1526;border:1px solid rgba(148,163,184,0.1)">
        <div class="w-12 h-12 bg-red-500/15 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-trash text-red-400 text-xl"></i>
        </div>
        <h3 class="text-white text-center font-700 text-base mb-2">Delete Expense?</h3>
        <p class="text-slate-400 text-center text-sm mb-6" id="deleteExpName"></p>
        <div class="flex gap-3">
            <button onclick="closeDelete()"
                    class="flex-1 py-2.5 rounded-xl text-sm font-600 text-slate-400"
                    style="background:rgba(30,41,59,0.8);border:1px solid rgba(148,163,184,0.1)">
                Cancel
            </button>
            <a id="deleteConfirmBtn" href="#"
               class="flex-1 py-2.5 rounded-xl text-sm font-700 text-white text-center bg-red-600 hover:bg-red-700 transition-all">
                Delete
            </a>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    document.getElementById('deleteExpName').textContent = name;
    document.getElementById('deleteConfirmBtn').href =
        '/car-showroom/modules/expenses/delete.php?id=' + id;
    const m = document.getElementById('deleteModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function closeDelete() {
    const m = document.getElementById('deleteModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}
</script>
</body>
</html>