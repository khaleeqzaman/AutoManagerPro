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
    redirect('dashboard/index.php');
}

$db = Database::getInstance();

$accounts = $db->fetchAll(
    "SELECT a.*,
        (SELECT COUNT(*) FROM transactions t WHERE t.account_id = a.id) as tx_count
     FROM accounts a WHERE a.is_active = 1 ORDER BY a.created_at ASC"
);

// Overall totals
$totalBalance = array_sum(array_column($accounts, 'current_balance'));

// Pending commissions
$pendingComm = $db->fetchOne(
    "SELECT COALESCE(SUM(commission_amount),0) as total, COUNT(*) as count
     FROM sales WHERE commission_paid = 0 AND commission_amount > 0"
);

// Pending customer payments (remaining amounts)
$pendingPayments = $db->fetchOne(
    "SELECT COALESCE(SUM(remaining_amount),0) as total, COUNT(*) as count
     FROM sales WHERE remaining_amount > 0"
);

// Recent transactions across all accounts
$recentTx = $db->fetchAll(
    "SELECT t.*, a.account_name, u.full_name as by_name
     FROM transactions t
     JOIN accounts a ON t.account_id = a.id
     LEFT JOIN users u ON t.added_by = u.id
     ORDER BY t.created_at DESC LIMIT 10"
);

$flash     = getFlash();
$pageTitle = 'Accounts';
$pageSub   = 'Financial ledger & balances';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts — AutoManager Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/car-showroom/public/css/fa/all.min.css">
    <style>
        .account-card {
            background:#0d1526;
            border:1px solid rgba(148,163,184,0.08);
            border-radius:16px; padding:20px;
            transition:all 0.2s; position:relative; overflow:hidden;
        }
        .account-card::before {
            content:''; position:absolute;
            top:0; left:0; right:0; height:2px;
        }
        .account-card.cash::before         { background:linear-gradient(90deg,#16a34a,#4ade80); }
        .account-card.bank::before         { background:linear-gradient(90deg,#2563eb,#60a5fa); }
        .account-card.mobile_wallet::before{ background:linear-gradient(90deg,#7c3aed,#a78bfa); }
        .account-card:hover { border-color:rgba(148,163,184,0.15); transform:translateY(-2px); }

        .acc-icon {
            width:42px; height:42px; border-radius:12px;
            display:flex; align-items:center; justify-content:center;
            font-size:1.1rem; margin-bottom:14px;
        }
        .acc-icon.cash          { background:rgba(22,163,74,0.15);  color:#4ade80; }
        .acc-icon.bank          { background:rgba(37,99,235,0.15);  color:#60a5fa; }
        .acc-icon.mobile_wallet { background:rgba(124,58,237,0.15); color:#a78bfa; }

        .acc-balance { font-size:1.5rem; font-weight:800; color:#f1f5f9; }
        .acc-name    { font-size:0.85rem; font-weight:600; color:#94a3b8; margin-top:4px; }
        .acc-meta    { font-size:0.72rem; color:#475569; margin-top:2px; }

        .stat-card {
            background:#0d1526;
            border:1px solid rgba(148,163,184,0.08);
            border-radius:14px; padding:16px;
            display:flex; align-items:center; gap:14px;
        }
        .stat-icon {
            width:40px; height:40px; border-radius:10px;
            display:flex; align-items:center; justify-content:center; font-size:1rem;
            flex-shrink:0;
        }

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

        .tx-row {
            display:flex; align-items:center; gap:12px;
            padding:12px 20px;
            border-bottom:1px solid rgba(148,163,184,0.05);
            transition:background 0.15s;
        }
        .tx-row:last-child { border-bottom:none; }
        .tx-row:hover { background:rgba(148,163,184,0.02); }

        .tx-dot {
            width:32px; height:32px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            font-size:0.7rem; flex-shrink:0;
        }
        .tx-dot.credit { background:rgba(22,163,74,0.15);  color:#4ade80; }
        .tx-dot.debit  { background:rgba(220,38,38,0.15);  color:#f87171; }

        .add-btn {
            display:inline-flex; align-items:center; gap:6px;
            padding:8px 16px; border-radius:10px;
            font-size:0.8rem; font-weight:700;
            text-decoration:none; transition:all 0.2s;
        }
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

    <!-- Top action bar -->
    <div class="flex items-center justify-between mb-5">
        <div></div>
        <div class="flex gap-2">
            <a href="/car-showroom/modules/accounts/add_account.php"
               class="add-btn bg-blue-600 hover:bg-blue-700 text-white">
                <i class="fas fa-plus"></i> Add Account
            </a>
            <a href="/car-showroom/modules/accounts/add_transaction.php"
               class="add-btn text-slate-300 hover:text-white"
               style="background:rgba(30,41,59,0.8);border:1px solid rgba(148,163,184,0.12)">
                <i class="fas fa-right-left"></i> Add Transaction
            </a>
        </div>
    </div>

    <!-- Summary stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(37,99,235,0.15)">
                <i class="fas fa-wallet text-blue-400"></i>
            </div>
            <div>
                <div class="text-slate-500 text-xs font-700 uppercase tracking-wide">Total Balance</div>
                <div class="text-white text-xl font-800"><?= formatPrice($totalBalance) ?></div>
                <div class="text-slate-600 text-xs"><?= count($accounts) ?> active accounts</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(217,119,6,0.15)">
                <i class="fas fa-percent text-amber-400"></i>
            </div>
            <div>
                <div class="text-slate-500 text-xs font-700 uppercase tracking-wide">Pending Commission</div>
                <div class="text-amber-400 text-xl font-800"><?= formatPrice($pendingComm['total']) ?></div>
                <div class="text-slate-600 text-xs"><?= $pendingComm['count'] ?> unpaid sales</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(220,38,38,0.15)">
                <i class="fas fa-clock text-red-400"></i>
            </div>
            <div>
                <div class="text-slate-500 text-xs font-700 uppercase tracking-wide">Pending Payments</div>
                <div class="text-red-400 text-xl font-800"><?= formatPrice($pendingPayments['total']) ?></div>
                <div class="text-slate-600 text-xs"><?= $pendingPayments['count'] ?> customers owing</div>
            </div>
        </div>
    </div>

    <!-- Account Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <?php foreach ($accounts as $acc): ?>
        <div class="account-card <?= $acc['account_type'] ?>">
            <div class="acc-icon <?= $acc['account_type'] ?>">
                <i class="fas <?= $acc['account_type']==='cash' ? 'fa-money-bill' : ($acc['account_type']==='bank' ? 'fa-building-columns' : 'fa-mobile-screen') ?>"></i>
            </div>
            <div class="acc-balance"><?= formatPrice($acc['current_balance']) ?></div>
            <div class="acc-name"><?= htmlspecialchars($acc['account_name']) ?></div>
            <div class="acc-meta">
                <?= $acc['bank_name'] ? htmlspecialchars($acc['bank_name']) . ' · ' : '' ?>
                <?= $acc['account_number'] ? htmlspecialchars($acc['account_number']) . ' · ' : '' ?>
                Opening: <?= formatPrice($acc['opening_balance']) ?>
            </div>
            <div class="flex gap-2 mt-4">
                <a href="/car-showroom/modules/accounts/transactions.php?account_id=<?= $acc['id'] ?>"
                   class="flex-1 text-center py-2 rounded-xl text-xs font-700 transition-all"
                   style="background:rgba(37,99,235,0.12);color:#60a5fa;border:1px solid rgba(37,99,235,0.2)">
                    <i class="fas fa-list mr-1"></i> Ledger
                </a>
                <a href="/car-showroom/modules/accounts/add_transaction.php?account_id=<?= $acc['id'] ?>"
                   class="flex-1 text-center py-2 rounded-xl text-xs font-700 transition-all"
                   style="background:rgba(22,163,74,0.12);color:#4ade80;border:1px solid rgba(22,163,74,0.2)">
                    <i class="fas fa-plus mr-1"></i> Entry
                </a>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Add account card -->
        <a href="/car-showroom/modules/accounts/add_account.php"
           class="account-card flex flex-col items-center justify-center text-center cursor-pointer"
           style="border:2px dashed rgba(148,163,184,0.1);min-height:160px;">
            <i class="fas fa-plus text-slate-600 text-2xl mb-2"></i>
            <span class="text-slate-600 font-600 text-sm">Add New Account</span>
        </a>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

        <!-- Recent Transactions -->
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">
                    <i class="fas fa-right-left text-blue-400 mr-2"></i>
                    Recent Transactions
                </span>
                <a href="/car-showroom/modules/accounts/transactions.php"
                   class="text-xs text-blue-400 hover:text-blue-300 font-600">View All</a>
            </div>
            <?php if (empty($recentTx)): ?>
            <div class="text-center py-10 text-slate-600 text-sm">No transactions yet</div>
            <?php else: ?>
            <?php foreach ($recentTx as $tx): ?>
            <div class="tx-row">
                <div class="tx-dot <?= $tx['transaction_type'] ?>">
                    <i class="fas <?= $tx['transaction_type']==='credit' ? 'fa-arrow-down' : 'fa-arrow-up' ?>"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-slate-300 text-sm font-600 truncate">
                        <?= htmlspecialchars($tx['description'] ?? ucfirst(str_replace('_',' ',$tx['category']))) ?>
                    </div>
                    <div class="text-slate-600 text-xs">
                        <?= htmlspecialchars($tx['account_name']) ?> ·
                        <?= date('d M Y', strtotime($tx['transaction_date'])) ?>
                    </div>
                </div>
                <div class="text-right flex-shrink-0">
                    <div class="font-700 text-sm <?= $tx['transaction_type']==='credit' ? 'text-green-400' : 'text-red-400' ?>">
                        <?= $tx['transaction_type']==='credit' ? '+' : '−' ?><?= formatPrice($tx['amount']) ?>
                    </div>
                    <div class="text-slate-600 text-xs">
                        Bal: <?= formatPrice($tx['balance_after']) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pending Commissions -->
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">
                    <i class="fas fa-percent text-amber-400 mr-2"></i>
                    Pending Commissions
                </span>
                <a href="/car-showroom/modules/accounts/commissions.php"
                   class="text-xs text-blue-400 hover:text-blue-300 font-600">Manage All</a>
            </div>
            <?php
            $pendingSales = $db->fetchAll(
                "SELECT s.id, s.invoice_no, s.sale_date, s.commission_amount,
                        s.commission_type, s.commission_value, s.final_price,
                        u.full_name as salesperson_name,
                        c.make, c.model, c.year
                 FROM sales s
                 JOIN users u ON s.salesperson_id = u.id
                 JOIN cars c ON s.car_id = c.id
                 WHERE s.commission_paid = 0 AND s.commission_amount > 0
                 ORDER BY s.sale_date DESC LIMIT 8"
            );
            ?>
            <?php if (empty($pendingSales)): ?>
            <div class="text-center py-10 text-slate-600 text-sm">
                <i class="fas fa-check-circle text-green-400 text-2xl mb-2 block"></i>
                All commissions paid!
            </div>
            <?php else: ?>
            <?php foreach ($pendingSales as $ps): ?>
            <div class="tx-row">
                <div class="tx-dot" style="background:rgba(217,119,6,0.15)">
                    <i class="fas fa-percent text-amber-400"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-slate-300 text-sm font-600">
                        <?= htmlspecialchars($ps['salesperson_name']) ?>
                    </div>
                    <div class="text-slate-600 text-xs">
                        <?= htmlspecialchars($ps['year'].' '.$ps['make'].' '.$ps['model']) ?> ·
                        <?= htmlspecialchars($ps['invoice_no']) ?>
                    </div>
                </div>
                <div class="text-right flex-shrink-0 flex items-center gap-2">
                    <div>
                        <div class="text-amber-400 font-700 text-sm"><?= formatPrice($ps['commission_amount']) ?></div>
                        <div class="text-slate-600 text-xs"><?= date('d M Y', strtotime($ps['sale_date'])) ?></div>
                    </div>
                    <a href="/car-showroom/modules/accounts/commissions.php?pay=<?= $ps['id'] ?>"
                       class="px-2 py-1 rounded-lg text-xs font-700 bg-green-500/15 text-green-400 border border-green-500/20 hover:bg-green-500/25 transition-all whitespace-nowrap">
                       Pay
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <!-- Pending Customer Payments -->
    <div class="section-card mt-5">
        <div class="section-header">
            <span class="section-title">
                <i class="fas fa-clock text-red-400 mr-2"></i>
                Pending Customer Payments
            </span>
            <span class="text-xs text-red-400 font-700"><?= formatPrice($pendingPayments['total']) ?> outstanding</span>
        </div>
        <?php
        $pendingCustPayments = $db->fetchAll(
            "SELECT s.id, s.invoice_no, s.sale_date, s.final_price,
                    s.token_amount, s.remaining_amount, s.payment_type,
                    cu.full_name as customer_name, cu.phone as customer_phone,
                    c.make, c.model, c.year
             FROM sales s
             JOIN customers cu ON s.customer_id = cu.id
             JOIN cars c ON s.car_id = c.id
             WHERE s.remaining_amount > 0
             ORDER BY s.sale_date ASC"
        );
        ?>
        <?php if (empty($pendingCustPayments)): ?>
        <div class="text-center py-10 text-slate-600 text-sm">
            <i class="fas fa-check-circle text-green-400 text-2xl mb-2 block"></i>
            No pending payments!
        </div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr>
                    <?php foreach (['Invoice','Customer','Vehicle','Sale Price','Token Paid','Remaining','Date','Action'] as $h): ?>
                    <th style="font-size:0.7rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#475569;padding:11px 16px;border-bottom:1px solid rgba(148,163,184,0.08);text-align:left;white-space:nowrap">
                        <?= $h ?>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pendingCustPayments as $cp): ?>
            <tr style="border-bottom:1px solid rgba(148,163,184,0.05)">
                <td style="padding:12px 16px;font-size:0.8rem;color:#60a5fa;font-weight:700;font-family:monospace">
                    <?= htmlspecialchars($cp['invoice_no']) ?>
                </td>
                <td style="padding:12px 16px">
                    <div style="color:#e2e8f0;font-size:0.85rem;font-weight:600"><?= htmlspecialchars($cp['customer_name']) ?></div>
                    <div style="color:#475569;font-size:0.75rem"><?= htmlspecialchars($cp['customer_phone']) ?></div>
                </td>
                <td style="padding:12px 16px;color:#94a3b8;font-size:0.85rem">
                    <?= htmlspecialchars($cp['year'].' '.$cp['make'].' '.$cp['model']) ?>
                </td>
                <td style="padding:12px 16px;color:#60a5fa;font-weight:700;font-size:0.85rem">
                    <?= formatPrice($cp['final_price']) ?>
                </td>
                <td style="padding:12px 16px;color:#4ade80;font-weight:600;font-size:0.85rem">
                    <?= formatPrice($cp['token_amount']) ?>
                </td>
                <td style="padding:12px 16px;color:#f87171;font-weight:700;font-size:0.85rem">
                    <?= formatPrice($cp['remaining_amount']) ?>
                </td>
                <td style="padding:12px 16px;color:#475569;font-size:0.75rem">
                    <?= date('d M Y', strtotime($cp['sale_date'])) ?>
                </td>
                <td style="padding:12px 16px">
                    <a href="/car-showroom/modules/accounts/add_transaction.php?sale_id=<?= $cp['id'] ?>"
                       style="display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border-radius:8px;font-size:0.75rem;font-weight:700;background:rgba(37,99,235,0.1);color:#60a5fa;border:1px solid rgba(37,99,235,0.2);text-decoration:none">
                        <i class="fas fa-plus"></i> Record Payment
                    </a>
                </td>
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