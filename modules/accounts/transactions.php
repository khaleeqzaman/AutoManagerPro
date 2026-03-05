<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('accounts.view');
Auth::check();
if (!Auth::hasRole(['Admin', 'Manager', 'Accountant'])) {
    setFlash('error', 'Access denied.');
    redirect('modules/accounts/index.php');
}

$db        = Database::getInstance();
$accountId = (int)($_GET['account_id'] ?? 0);

// All accounts for switcher
$allAccounts = $db->fetchAll("SELECT id, account_name, account_type, current_balance FROM accounts WHERE is_active=1 ORDER BY created_at ASC");

$account = $accountId
    ? $db->fetchOne("SELECT * FROM accounts WHERE id = ?", [$accountId], 'i')
    : ($allAccounts[0] ?? null);

if (!$account) {
    setFlash('error', 'No accounts found.');
    redirect('modules/accounts/index.php');
}
$accountId = $account['id'];

// Filters
$where  = ["t.account_id = ?"];
$params = [$accountId];
$types  = 'i';

if (!empty($_GET['type'])) {
    $where[]  = "t.transaction_type = ?";
    $params[] = clean($_GET['type']);
    $types   .= 's';
}
if (!empty($_GET['month'])) {
    $where[]  = "DATE_FORMAT(t.transaction_date,'%Y-%m') = ?";
    $params[] = clean($_GET['month']);
    $types   .= 's';
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// Totals for this account in filter
$totals = $db->fetchOne(
    "SELECT
        COALESCE(SUM(CASE WHEN transaction_type='credit' THEN amount END),0) as total_in,
        COALESCE(SUM(CASE WHEN transaction_type='debit'  THEN amount END),0) as total_out
     FROM transactions t $whereSQL",
    $params, $types
);

// Pagination
$perPage    = 20;
$page       = max(1,(int)($_GET['page'] ?? 1));
$offset     = ($page-1)*$perPage;
$totalRows  = $db->fetchOne("SELECT COUNT(*) as cnt FROM transactions t $whereSQL", $params, $types)['cnt'] ?? 0;
$totalPages = ceil($totalRows/$perPage);

$transactions = $db->fetchAll(
    "SELECT t.*, u.full_name as by_name
     FROM transactions t
     LEFT JOIN users u ON t.added_by = u.id
     $whereSQL
     ORDER BY t.transaction_date DESC, t.id DESC
     LIMIT $perPage OFFSET $offset",
    $params, $types
);

$pageTitle = $account['account_name'];
$pageSub   = 'Transaction Ledger';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($account['account_name']) ?> Ledger</title>
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
            padding:13px 16px; font-size:0.85rem; color:#94a3b8;
            border-bottom:1px solid rgba(148,163,184,0.05); vertical-align:middle;
        }
        .data-table tr:last-child td { border-bottom:none; }

        .acc-switcher {
            display:flex; gap:8px; overflow-x:auto; padding-bottom:4px; margin-bottom:20px;
        }
        .acc-pill {
            flex-shrink:0; padding:8px 16px; border-radius:20px;
            font-size:0.8rem; font-weight:700; text-decoration:none;
            border:1.5px solid rgba(148,163,184,0.1);
            background:rgba(13,20,40,0.8); color:#64748b; transition:all 0.2s;
        }
        .acc-pill.active { border-color:#3b82f6; color:#60a5fa; background:rgba(37,99,235,0.1); }
        .acc-pill:hover  { color:#e2e8f0; }
    </style>
</head>
<body>
<?php require_once '../../views/layouts/sidebar.php'; ?>
<div class="main">
<?php require_once '../../views/layouts/topbar.php'; ?>
<div class="content-area">

    <!-- Account switcher -->
    <div class="acc-switcher">
        <?php foreach ($allAccounts as $acc): ?>
        <a href="?account_id=<?= $acc['id'] ?>"
           class="acc-pill <?= $acc['id']==$accountId ? 'active' : '' ?>">
            <i class="fas <?= $acc['account_type']==='cash' ? 'fa-money-bill' : ($acc['account_type']==='bank' ? 'fa-building-columns' : 'fa-mobile-screen') ?> mr-1"></i>
            <?= htmlspecialchars($acc['account_name']) ?>
            <span class="ml-2 opacity-60"><?= formatPrice($acc['current_balance']) ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Balance + stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        <div class="p-5 rounded-2xl" style="background:#0d1526;border:1px solid rgba(148,163,184,0.08)">
            <div class="text-slate-500 text-xs font-700 uppercase tracking-wide mb-1">Current Balance</div>
            <div class="text-white text-2xl font-800"><?= formatPrice($account['current_balance']) ?></div>
            <div class="text-slate-600 text-xs mt-1">Opening: <?= formatPrice($account['opening_balance']) ?></div>
        </div>
        <div class="p-5 rounded-2xl" style="background:#0d1526;border:1px solid rgba(22,163,74,0.15)">
            <div class="text-slate-500 text-xs font-700 uppercase tracking-wide mb-1">Total In (filtered)</div>
            <div class="text-green-400 text-2xl font-800"><?= formatPrice($totals['total_in']) ?></div>
        </div>
        <div class="p-5 rounded-2xl" style="background:#0d1526;border:1px solid rgba(220,38,38,0.15)">
            <div class="text-slate-500 text-xs font-700 uppercase tracking-wide mb-1">Total Out (filtered)</div>
            <div class="text-red-400 text-2xl font-800"><?= formatPrice($totals['total_out']) ?></div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="flex flex-wrap gap-3 mb-5 p-4 rounded-xl"
          style="background:#0d1526;border:1px solid rgba(148,163,184,0.08)">
        <input type="hidden" name="account_id" value="<?= $accountId ?>">
        <select name="type" class="px-4 py-2 rounded-xl text-sm text-slate-300"
                style="background:rgba(30,41,59,0.8);border:1.5px solid rgba(148,163,184,0.12)">
            <option value="">All Types</option>
            <option value="credit" <?= (($_GET['type']??'')==='credit')?'selected':'' ?>>Credit (In)</option>
            <option value="debit"  <?= (($_GET['type']??'')==='debit') ?'selected':'' ?>>Debit (Out)</option>
        </select>
        <input type="month" name="month"
               value="<?= htmlspecialchars($_GET['month'] ?? '') ?>"
               class="px-4 py-2 rounded-xl text-sm text-slate-300"
               style="background:rgba(30,41,59,0.8);border:1.5px solid rgba(148,163,184,0.12)">
        <button type="submit"
                class="px-4 py-2 rounded-xl text-xs font-700 bg-blue-600 hover:bg-blue-700 text-white transition-all">
            <i class="fas fa-search mr-1"></i> Filter
        </button>
        <a href="?account_id=<?= $accountId ?>"
           class="px-4 py-2 rounded-xl text-xs font-700 text-slate-400 hover:text-white transition-all"
           style="background:rgba(30,41,59,0.5);border:1px solid rgba(148,163,184,0.1)">
            Clear
        </a>
        <a href="/car-showroom/modules/accounts/add_transaction.php?account_id=<?= $accountId ?>"
           class="ml-auto px-4 py-2 rounded-xl text-xs font-700 bg-blue-600 hover:bg-blue-700 text-white transition-all flex items-center gap-2">
            <i class="fas fa-plus"></i> Add Entry
        </a>
    </form>

    <!-- Ledger table -->
    <div class="section-card">
        <div class="section-header">
            <span class="section-title">
                <i class="fas fa-list text-blue-400 mr-2"></i>
                Ledger — <?= htmlspecialchars($account['account_name']) ?>
            </span>
            <span class="text-xs text-slate-500"><?= $totalRows ?> entries</span>
        </div>
        <?php if (empty($transactions)): ?>
        <div class="text-center py-12 text-slate-600">
            <i class="fas fa-list text-3xl mb-3 block opacity-20"></i>
            <p class="text-sm">No transactions found</p>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Balance After</th>
                    <th>Added By</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($transactions as $tx): ?>
            <tr>
                <td class="text-xs text-slate-500"><?= date('d M Y', strtotime($tx['transaction_date'])) ?></td>
                <td>
                    <span class="text-slate-300 text-sm">
                        <?= htmlspecialchars($tx['description'] ?? '—') ?>
                    </span>
                </td>
                <td>
                    <span style="font-size:0.7rem;font-weight:700;padding:2px 8px;border-radius:6px;background:rgba(30,41,59,0.8);border:1px solid rgba(148,163,184,0.1);color:#64748b">
                        <?= ucfirst(str_replace('_',' ',$tx['category'])) ?>
                    </span>
                </td>
                <td>
                    <span class="font-700 text-xs <?= $tx['transaction_type']==='credit' ? 'text-green-400' : 'text-red-400' ?>">
                        <i class="fas <?= $tx['transaction_type']==='credit' ? 'fa-arrow-down' : 'fa-arrow-up' ?> mr-1"></i>
                        <?= ucfirst($tx['transaction_type']) ?>
                    </span>
                </td>
                <td class="font-700 <?= $tx['transaction_type']==='credit' ? 'text-green-400' : 'text-red-400' ?>">
                    <?= $tx['transaction_type']==='credit' ? '+' : '−' ?><?= formatPrice($tx['amount']) ?>
                </td>
                <td class="text-slate-300 font-600"><?= formatPrice($tx['balance_after']) ?></td>
                <td class="text-xs text-slate-600"><?= htmlspecialchars($tx['by_name'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-center gap-2 p-4">
            <?php for ($i=1; $i<=$totalPages; $i++):
                $pUrl = '?' . http_build_query(array_merge($_GET,['page'=>$i]));
            ?>
            <a href="<?= $pUrl ?>"
               class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-600 transition-all
               <?= $i===$page ? 'bg-blue-600 text-white' : 'text-slate-400' ?>"
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