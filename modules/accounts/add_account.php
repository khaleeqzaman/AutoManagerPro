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

$db     = Database::getInstance();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request.';
    } else {
        $name           = clean($_POST['account_name'] ?? '');
        $type           = clean($_POST['account_type'] ?? 'cash');
        $bankName       = clean($_POST['bank_name'] ?? '');
        $accountNumber  = clean($_POST['account_number'] ?? '');
        $openingBalance = (float)($_POST['opening_balance'] ?? 0);
        $notes          = clean($_POST['notes'] ?? '');

        if (empty($name)) $errors[] = 'Account name is required.';

        if (empty($errors)) {
            $accId = $db->insert(
                "INSERT INTO accounts
                    (account_name, account_type, bank_name, account_number,
                     opening_balance, current_balance, notes)
                 VALUES (?,?,?,?,?,?,?)",
                [$name, $type, $bankName, $accountNumber,
                 $openingBalance, $openingBalance, $notes],
                'ssssdds'
            );

            if ($accId) {
                // Log opening balance as first transaction if > 0
                if ($openingBalance > 0) {
                    $db->insert(
                        "INSERT INTO transactions
                            (account_id, transaction_type, category, amount,
                             balance_after, reference_type, description,
                             transaction_date, added_by)
                         VALUES (?,?,?,?,?,?,?,NOW(),?)",
                        [$accId, 'credit', 'deposit', $openingBalance,
                         $openingBalance, 'manual', 'Opening balance', Auth::id()],
                        'issddssi'
                    );
                }
                setFlash('success', "Account '$name' created successfully!");
                redirect('modules/accounts/index.php');
            } else {
                $errors[] = 'Failed to create account.';
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Add Account';
$pageSub   = 'Create a new financial account';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Account — AutoManager Pro</title>
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

        /* Type cards */
        .type-card {
            flex:1; padding:16px; border-radius:12px; text-align:center;
            border:1.5px solid rgba(148,163,184,0.1);
            background:rgba(30,41,59,0.5); cursor:pointer;
            transition:all 0.2s; user-select:none;
        }
        .type-card input[type="radio"] { display:none; }
        .type-card .tc-icon { font-size:1.5rem; display:block; margin-bottom:8px; }
        .type-card .tc-label { font-size:0.8rem; font-weight:700; color:#64748b; }
        .type-card.selected-cash          { border-color:#16a34a; background:rgba(22,163,74,0.1); }
        .type-card.selected-cash .tc-label{ color:#4ade80; }
        .type-card.selected-bank          { border-color:#2563eb; background:rgba(37,99,235,0.1); }
        .type-card.selected-bank .tc-label{ color:#60a5fa; }
        .type-card.selected-mobile_wallet          { border-color:#7c3aed; background:rgba(124,58,237,0.1); }
        .type-card.selected-mobile_wallet .tc-label{ color:#a78bfa; }

        .btn-primary {
            background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff;
            font-weight:700; font-size:0.9rem; padding:12px 28px;
            border-radius:12px; border:none; cursor:pointer; transition:all 0.25s;
        }
        .btn-primary:hover { transform:translateY(-1px); }
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
        <div class="flex items-center gap-2 font-700 text-sm">
            <i class="fas fa-circle-exclamation"></i> Error:
        </div>
        <ul style="margin-top:6px;padding-left:18px">
            <?php foreach ($errors as $e): ?>
            <li style="font-size:0.85rem"><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="account_type" id="accountTypeInput" value="<?= htmlspecialchars($_POST['account_type'] ?? 'cash') ?>">

    <!-- Account Type -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(37,99,235,0.15)">
                <i class="fas fa-building-columns text-blue-400"></i>
            </div>
            <span class="form-section-title">Account Type</span>
        </div>
        <div class="form-section-body">
            <div class="flex gap-3">
                <?php
                $types = [
                    'cash'          => ['icon'=>'fa-money-bill-wave', 'label'=>'Cash'],
                    'bank'          => ['icon'=>'fa-building-columns', 'label'=>'Bank'],
                    'mobile_wallet' => ['icon'=>'fa-mobile-screen',   'label'=>'Mobile Wallet'],
                ];
                $selType = $_POST['account_type'] ?? 'cash';
                foreach ($types as $val => $t):
                ?>
                <label class="type-card <?= $selType===$val ? 'selected-'.$val : '' ?>"
                       id="tc-<?= $val ?>"
                       onclick="selectType('<?= $val ?>')">
                    <input type="radio" name="_account_type" value="<?= $val ?>"
                           <?= $selType===$val ? 'checked' : '' ?>>
                    <span class="tc-icon"><i class="fas <?= $t['icon'] ?>"></i></span>
                    <span class="tc-label"><?= $t['label'] ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Account Details -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(22,163,74,0.15)">
                <i class="fas fa-pen text-green-400"></i>
            </div>
            <span class="form-section-title">Account Details</span>
        </div>
        <div class="form-section-body">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="field-label">Account Name *</label>
                    <input type="text" name="account_name" class="form-input"
                           placeholder="e.g. Main Cash Box, HBL Current Account"
                           value="<?= htmlspecialchars($_POST['account_name'] ?? '') ?>" required>
                </div>
                <div id="bankNameWrap">
                    <label class="field-label">Bank Name</label>
                    <input type="text" name="bank_name" class="form-input"
                           placeholder="e.g. HBL, MCB, UBL"
                           value="<?= htmlspecialchars($_POST['bank_name'] ?? '') ?>">
                </div>
                <div id="accNumWrap">
                    <label class="field-label">Account Number</label>
                    <input type="text" name="account_number" class="form-input"
                           placeholder="Bank account number"
                           value="<?= htmlspecialchars($_POST['account_number'] ?? '') ?>">
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label">Opening Balance (PKR)</label>
                    <input type="number" name="opening_balance" class="form-input"
                           placeholder="0" min="0" step="any"
                           value="<?= htmlspecialchars($_POST['opening_balance'] ?? '0') ?>">
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label">Notes</label>
                    <textarea name="notes" class="form-input" rows="2"
                              placeholder="Optional notes about this account"
                              style="resize:vertical"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
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
            <i class="fas fa-plus mr-2"></i> Create Account
        </button>
    </div>
    </form>
</div>
</div>
<script>
function selectType(val) {
    document.querySelectorAll('.type-card').forEach(c => c.className = 'type-card');
    document.getElementById('tc-' + val).classList.add('selected-' + val);
    document.getElementById('accountTypeInput').value = val;
    const showBank = ['bank','mobile_wallet'].includes(val);
    document.getElementById('bankNameWrap').style.display = showBank ? '' : 'none';
    document.getElementById('accNumWrap').style.display   = showBank ? '' : 'none';
}
selectType(document.getElementById('accountTypeInput').value);
</script>
</body>
</html>