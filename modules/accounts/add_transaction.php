<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('accounts.manage');
Auth::check();
if (!Auth::hasRole(['Admin', 'Manager', 'Accountant'])) {
    setFlash('error', 'Access denied.');
    redirect('modules/accounts/index.php');
}

$db        = Database::getInstance();
$accounts  = $db->fetchAll("SELECT id, account_name, current_balance FROM accounts WHERE is_active=1 ORDER BY created_at ASC");
$preAccId  = (int)($_GET['account_id'] ?? 0);
$preSaleId = (int)($_GET['sale_id'] ?? 0);

// Pre-fill from sale (customer remaining payment)
$preSale = null;
if ($preSaleId) {
    $preSale = $db->fetchOne(
        "SELECT s.*, cu.full_name as customer_name, c.make, c.model, c.year
         FROM sales s
         JOIN customers cu ON s.customer_id = cu.id
         JOIN cars c ON s.car_id = c.id
         WHERE s.id = ?",
        [$preSaleId], 'i'
    );
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request.';
    } else {
        $accountId  = (int)($_POST['account_id'] ?? 0);
        $txType     = clean($_POST['transaction_type'] ?? '');
        $category   = clean($_POST['category'] ?? 'other');
        $amount     = (float)($_POST['amount'] ?? 0);
        $description= clean($_POST['description'] ?? '');
        $txDate     = clean($_POST['transaction_date'] ?? date('Y-m-d'));
        $refType    = clean($_POST['reference_type'] ?? 'manual');
        $refId      = (int)($_POST['reference_id'] ?? 0) ?: null;

        if (!$accountId) $errors[] = 'Please select an account.';
        if (!$txType)    $errors[] = 'Transaction type is required.';
        if ($amount <= 0)$errors[] = 'Amount must be greater than 0.';

        if (empty($errors)) {
            // Get current balance
            $acc = $db->fetchOne("SELECT current_balance FROM accounts WHERE id = ?", [$accountId], 'i');
            $currentBal = $acc['current_balance'] ?? 0;

            $newBalance = $txType === 'credit'
                ? $currentBal + $amount
                : $currentBal - $amount;

            // Insert transaction
            $txId = $db->insert(
                "INSERT INTO transactions
                    (account_id, transaction_type, category, amount, balance_after,
                     reference_type, reference_id, description, transaction_date, added_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$accountId, $txType, $category, $amount, $newBalance,
                 $refType, $refId, $description, $txDate, Auth::id()],
                'issddsssi' . ($refId ? 'i' : 's')
            );

            if ($txId) {
                // Update account balance
                $db->execute(
                    "UPDATE accounts SET current_balance = ? WHERE id = ?",
                    [$newBalance, $accountId], 'di'
                );

                // If this is a customer remaining payment — reduce remaining_amount on sale
                if ($preSaleId && $refType === 'sale') {
                    $db->execute(
                        "UPDATE sales SET remaining_amount = GREATEST(remaining_amount - ?, 0) WHERE id = ?",
                        [$amount, $preSaleId], 'di'
                    );
                }

                setFlash('success', 'Transaction recorded successfully!');
                redirect('modules/accounts/transactions.php?account_id=' . $accountId);
            } else {
                $errors[] = 'Failed to save transaction.';
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Add Transaction';
$pageSub   = $preSale ? 'Customer payment — ' . $preSale['customer_name'] : 'Record a financial entry';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Transaction — AutoManager Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/car-showroom/public/css/fa/all.min.css">
    <style>
        .form-section {
            background:#0d1526; border:1px solid rgba(148,163,184,0.08);
            border-radius:16px; overflow:hidden; margin-bottom:20px;
        }
        .form-section-header {
            padding:16px 20px; border-bottom:1px solid rgba(148,163,184,0.07);
            display:flex; align-items:center; gap:10px;
        }
        .icon-box {
            width:32px; height:32px; border-radius:8px;
            display:flex; align-items:center; justify-content:center; font-size:0.8rem;
        }
        .form-section-title { font-size:0.875rem; font-weight:700; color:#e2e8f0; }
        .form-section-body  { padding:20px; }
        label.field-label {
            display:block; font-size:0.75rem; font-weight:600; color:#64748b;
            text-transform:uppercase; letter-spacing:0.06em; margin-bottom:6px;
        }
        .form-input {
            width:100%; background:rgba(30,41,59,0.8);
            border:1.5px solid rgba(148,163,184,0.12);
            color:#f1f5f9; font-size:0.875rem; padding:10px 14px; border-radius:10px;
            transition:border-color 0.2s; font-family:'Plus Jakarta Sans',sans-serif;
        }
        .form-input:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,0.15); }
        .form-input::placeholder { color:#334155; }
        select.form-input option { background:#1e293b; color:#f1f5f9; }

        .type-toggle { display:flex; gap:0; border-radius:12px; overflow:hidden; border:1.5px solid rgba(148,163,184,0.1); }
        .type-btn {
            flex:1; padding:12px; text-align:center; cursor:pointer;
            font-size:0.85rem; font-weight:700; transition:all 0.2s;
            background:rgba(30,41,59,0.5); color:#64748b;
        }
        .type-btn.active-credit { background:rgba(22,163,74,0.15); color:#4ade80; }
        .type-btn.active-debit  { background:rgba(220,38,38,0.15);  color:#f87171; }

        .btn-primary {
            background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff;
            font-weight:700; font-size:0.9rem; padding:12px 28px;
            border-radius:12px; border:none; cursor:pointer;
        }
        .btn-secondary {
            background:rgba(30,41,59,0.8); border:1.5px solid rgba(148,163,184,0.12);
            color:#94a3b8; font-weight:600; font-size:0.9rem; padding:12px 24px;
            border-radius:12px; text-decoration:none; display:inline-flex; align-items:center; gap:8px;
        }
        .error-alert {
            background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.25);
            color:#fca5a5; border-radius:12px; padding:14px 16px; margin-bottom:20px;
        }
    </style>
</head>
<body>
<?php require_once '../../views/layouts/sidebar.php'; ?>
<div class="main">
<?php require_once '../../views/layouts/topbar.php'; ?>
<div class="content-area" style="max-width:640px;">

    <?php if (!empty($errors)): ?>
    <div class="error-alert">
        <?php foreach ($errors as $e): ?>
        <div class="text-sm"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Pre-filled sale banner -->
    <?php if ($preSale): ?>
    <div class="mb-5 p-4 rounded-xl flex items-center gap-4"
         style="background:rgba(37,99,235,0.08);border:1px solid rgba(37,99,235,0.2)">
        <i class="fas fa-file-invoice text-blue-400 text-xl"></i>
        <div>
            <div class="text-blue-300 font-700 text-sm">
                Recording payment for <?= htmlspecialchars($preSale['customer_name']) ?>
            </div>
            <div class="text-slate-500 text-xs mt-0.5">
                <?= htmlspecialchars($preSale['year'].' '.$preSale['make'].' '.$preSale['model']) ?> ·
                Remaining: <span class="text-red-400 font-700"><?= formatPrice($preSale['remaining_amount']) ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="transaction_type" id="txTypeInput"
           value="<?= htmlspecialchars($_POST['transaction_type'] ?? 'credit') ?>">
    <?php if ($preSaleId): ?>
    <input type="hidden" name="reference_type" value="sale">
    <input type="hidden" name="reference_id"   value="<?= $preSaleId ?>">
    <?php else: ?>
    <input type="hidden" name="reference_type" value="manual">
    <?php endif; ?>

    <!-- Type toggle -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(37,99,235,0.15)">
                <i class="fas fa-right-left text-blue-400"></i>
            </div>
            <span class="form-section-title">Transaction Type</span>
        </div>
        <div class="form-section-body">
            <div class="type-toggle">
                <div class="type-btn <?= ($_POST['transaction_type']??'credit')==='credit' ? 'active-credit' : '' ?>"
                     id="btn-credit" onclick="setType('credit')">
                    <i class="fas fa-arrow-down mr-2"></i>Credit (Money In)
                </div>
                <div class="type-btn <?= ($_POST['transaction_type']??'')==='debit' ? 'active-debit' : '' ?>"
                     id="btn-debit" onclick="setType('debit')">
                    <i class="fas fa-arrow-up mr-2"></i>Debit (Money Out)
                </div>
            </div>
        </div>
    </div>

    <!-- Details -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(22,163,74,0.15)">
                <i class="fas fa-file-lines text-green-400"></i>
            </div>
            <span class="form-section-title">Transaction Details</span>
        </div>
        <div class="form-section-body">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                <div class="sm:col-span-2">
                    <label class="field-label">Account *</label>
                    <select name="account_id" class="form-input" required>
                        <option value="">— Select Account —</option>
                        <?php foreach ($accounts as $acc): ?>
                        <option value="<?= $acc['id'] ?>"
                                data-balance="<?= $acc['current_balance'] ?>"
                            <?= (($preAccId==$acc['id']) || (($_POST['account_id']??'')==$acc['id'])) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($acc['account_name']) ?>
                            (<?= formatPrice($acc['current_balance']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="field-label">Category</label>
                    <select name="category" class="form-input">
                        <?php
                        $cats = [
                            'sale_income'        => 'Sale Income',
                            'expense'            => 'Expense',
                            'commission_payment' => 'Commission Payment',
                            'deposit'            => 'Deposit',
                            'withdrawal'         => 'Withdrawal',
                            'transfer'           => 'Transfer',
                            'other'              => 'Other',
                        ];
                        $defCat = $preSale ? 'sale_income' : ($_POST['category'] ?? 'other');
                        foreach ($cats as $v=>$l):
                        ?>
                        <option value="<?= $v ?>" <?= $defCat===$v?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="field-label">Amount (PKR) *</label>
                    <input type="number" name="amount" class="form-input"
                           placeholder="0" min="1" step="any"
                           value="<?= htmlspecialchars($_POST['amount'] ?? ($preSale ? $preSale['remaining_amount'] : '')) ?>"
                           required>
                </div>

                <div>
                    <label class="field-label">Date *</label>
                    <input type="date" name="transaction_date" class="form-input"
                           value="<?= htmlspecialchars($_POST['transaction_date'] ?? date('Y-m-d')) ?>"
                           required>
                </div>

                <div class="sm:col-span-2">
                    <label class="field-label">Description</label>
                    <textarea name="description" class="form-input" rows="3"
                              placeholder="Describe this transaction..."
                              style="resize:vertical"><?= htmlspecialchars($_POST['description'] ?? ($preSale ? 'Customer payment — ' . $preSale['customer_name'] . ' — ' . $preSale['invoice_no'] : '')) ?></textarea>
                </div>

            </div>
        </div>
    </div>

    <div class="flex items-center justify-between gap-4 py-4 sticky bottom-0 px-4 -mx-4"
         style="background:rgba(10,15,30,0.95);backdrop-filter:blur(12px);border-top:1px solid rgba(148,163,184,0.08)">
        <a href="/car-showroom/modules/accounts/index.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Cancel
        </a>
        <button type="submit" class="btn-primary">
            <i class="fas fa-check mr-2"></i> Save Transaction
        </button>
    </div>
    </form>
</div>
</div>
<script>
function setType(val) {
    document.getElementById('txTypeInput').value = val;
    document.getElementById('btn-credit').className = 'type-btn' + (val==='credit' ? ' active-credit' : '');
    document.getElementById('btn-debit').className  = 'type-btn' + (val==='debit'  ? ' active-debit'  : '');
}
setType(document.getElementById('txTypeInput').value);
</script>
</body>
</html>