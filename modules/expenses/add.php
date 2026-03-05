<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('expenses.add');

Auth::check();

$db     = Database::getInstance();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request.';
    } else {
        $category   = clean($_POST['category'] ?? '');
        $customCat  = clean($_POST['custom_category'] ?? '');
        $finalCat   = ($category === 'Other' && !empty($customCat)) ? $customCat : $category;
        $amount     = (float)($_POST['amount'] ?? 0);
        $description= clean($_POST['description'] ?? '');
        $expDate    = clean($_POST['expense_date'] ?? date('Y-m-d'));
        $paidTo     = clean($_POST['paid_to'] ?? '');

        if (empty($finalCat)) $errors[] = 'Category is required.';
        if ($amount <= 0)     $errors[] = 'Amount must be greater than 0.';
        if (empty($expDate))  $errors[] = 'Date is required.';

        // Receipt upload
        $receiptPath = null;
        if (!empty($_FILES['receipt_image']['name'])) {
            $uploadDir = UPLOAD_PATH . 'receipts/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $upload = uploadImage($_FILES['receipt_image'], 'receipts');
            if ($upload['success']) {
                $receiptPath = $upload['filename'];
            } else {
                $errors[] = 'Receipt upload failed: ' . $upload['error'];
            }
        }

        if (empty($errors)) {
            $expId = $db->insert(
                "INSERT INTO expenses (category, amount, description, expense_date, paid_to, receipt_image, added_by)
                 VALUES (?,?,?,?,?,?,?)",
                [$finalCat, $amount, $description, $expDate, $paidTo, $receiptPath, Auth::id()]
            );

            if ($expId) {
                setFlash('success', 'Expense of ' . formatPrice($amount) . ' added successfully!');
                redirect('modules/expenses/index.php');
            } else {
                $errors[] = 'Failed to save expense.';
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Add Expense';
$pageSub   = 'Record a new showroom expense';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Expense — AutoManager Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/car-showroom/public/css/fa/all.min.css">
    <style>
        .form-section {
            background:#0d1526;
            border:1px solid rgba(148,163,184,0.08);
            border-radius:16px; overflow:hidden; margin-bottom:20px;
        }
        .form-section-header {
            padding:16px 20px;
            border-bottom:1px solid rgba(148,163,184,0.07);
            display:flex; align-items:center; gap:10px;
        }
        .icon-box {
            width:32px; height:32px; border-radius:8px;
            display:flex; align-items:center; justify-content:center; font-size:0.8rem;
        }
        .form-section-title { font-size:0.875rem; font-weight:700; color:#e2e8f0; }
        .form-section-body  { padding:20px; }
        label.field-label {
            display:block; font-size:0.75rem; font-weight:600;
            color:#64748b; text-transform:uppercase;
            letter-spacing:0.06em; margin-bottom:6px;
        }
        .req { color:#f87171; }
        .form-input {
            width:100%; background:rgba(30,41,59,0.8);
            border:1.5px solid rgba(148,163,184,0.12);
            color:#f1f5f9; font-size:0.875rem;
            padding:10px 14px; border-radius:10px;
            transition:border-color 0.2s,box-shadow 0.2s;
            font-family:'Plus Jakarta Sans',sans-serif;
        }
        .form-input:focus {
            outline:none; border-color:#3b82f6;
            box-shadow:0 0 0 3px rgba(59,130,246,0.15);
        }
        .form-input::placeholder { color:#334155; }
        select.form-input option { background:#1e293b; color:#f1f5f9; }

        /* Quick category buttons */
        .cat-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:8px; margin-bottom:16px; }
        .cat-btn {
            padding:10px 12px; border-radius:10px; text-align:center;
            border:1.5px solid rgba(148,163,184,0.1);
            background:rgba(30,41,59,0.5);
            cursor:pointer; transition:all 0.2s;
            font-size:0.78rem; font-weight:600; color:#64748b;
            user-select:none;
        }
        .cat-btn:hover { border-color:rgba(239,68,68,0.3); color:#f87171; background:rgba(220,38,38,0.06); }
        .cat-btn.selected { border-color:#dc2626; color:#f87171; background:rgba(220,38,38,0.12); }
        .cat-btn .cat-icon { display:block; font-size:1.1rem; margin-bottom:4px; }

        .upload-zone {
            border:2px dashed rgba(148,163,184,0.15); border-radius:12px;
            padding:24px; text-align:center; cursor:pointer; transition:all 0.2s;
        }
        .upload-zone:hover { border-color:#3b82f6; background:rgba(37,99,235,0.04); }

        .btn-primary {
            background:linear-gradient(135deg,#2563eb,#1d4ed8);
            color:#fff; font-weight:700; font-size:0.9rem;
            padding:12px 28px; border-radius:12px; border:none;
            cursor:pointer; transition:all 0.25s;
        }
        .btn-primary:hover { transform:translateY(-1px); }
        .btn-secondary {
            background:rgba(30,41,59,0.8);
            border:1.5px solid rgba(148,163,184,0.12);
            color:#94a3b8; font-weight:600; font-size:0.9rem;
            padding:12px 24px; border-radius:12px;
            text-decoration:none; display:inline-flex; align-items:center; gap:8px;
        }
        .error-alert {
            background:rgba(239,68,68,0.1);
            border:1px solid rgba(239,68,68,0.25);
            color:#fca5a5; border-radius:12px;
            padding:14px 16px; margin-bottom:20px;
        }
        .error-alert ul { margin-top:6px; padding-left:18px; }
        .error-alert li { font-size:0.85rem; }
    </style>
</head>
<body>
<?php require_once '../../views/layouts/sidebar.php'; ?>
<div class="main">
<?php require_once '../../views/layouts/topbar.php'; ?>
<div class="content-area" style="max-width:700px;">

    <?php if (!empty($errors)): ?>
    <div class="error-alert">
        <div class="flex items-center gap-2 font-700 text-sm">
            <i class="fas fa-circle-exclamation"></i> Please fix the following:
        </div>
        <ul>
            <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="expenseForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="category" id="categoryInput" value="<?= htmlspecialchars($_POST['category'] ?? '') ?>">

    <!-- Category -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(239,68,68,0.15)">
                <i class="fas fa-tags text-red-400"></i>
            </div>
            <span class="form-section-title">Expense Category <span style="color:#f87171">*</span></span>
        </div>
        <div class="form-section-body">
            <?php
            $quickCats = [
                ['icon'=>'fa-building',     'label'=>'Office Rent'],
                ['icon'=>'fa-users',        'label'=>'Staff Salary'],
                ['icon'=>'fa-bolt',         'label'=>'Electricity'],
                ['icon'=>'fa-bullhorn',     'label'=>'Advertising'],
                ['icon'=>'fa-wrench',       'label'=>'Repair'],
                ['icon'=>'fa-gear',         'label'=>'Maintenance'],
                ['icon'=>'fa-gas-pump',     'label'=>'Fuel'],
                ['icon'=>'fa-ellipsis',     'label'=>'Other'],
            ];
            $selectedCat = $_POST['category'] ?? '';
            ?>
            <div class="cat-grid">
                <?php foreach ($quickCats as $qc): ?>
                <div class="cat-btn <?= $selectedCat===$qc['label'] ? 'selected' : '' ?>"
                     onclick="selectCategory('<?= $qc['label'] ?>', this)">
                    <span class="cat-icon"><i class="fas <?= $qc['icon'] ?>"></i></span>
                    <?= $qc['label'] ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Custom category (shown when Other selected) -->
            <div id="customCatWrap" class="<?= $selectedCat==='Other' ? '' : 'hidden' ?>">
                <label class="field-label">Specify Category <span class="req">*</span></label>
                <input type="text" name="custom_category" class="form-input"
                       placeholder="e.g. Vehicle Insurance, Maintenance"
                       value="<?= htmlspecialchars($_POST['custom_category'] ?? '') ?>">
            </div>
        </div>
    </div>

    <!-- Details -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(37,99,235,0.15)">
                <i class="fas fa-file-lines text-blue-400"></i>
            </div>
            <span class="form-section-title">Expense Details</span>
        </div>
        <div class="form-section-body">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="field-label">Amount (PKR) <span class="req">*</span></label>
                    <input type="number" name="amount" class="form-input"
                            placeholder="0" min="1" step="any"
                           value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>" required>
                </div>
                <div>
                    <label class="field-label">Expense Date <span class="req">*</span></label>
                    <input type="date" name="expense_date" class="form-input"
                           value="<?= htmlspecialchars($_POST['expense_date'] ?? date('Y-m-d')) ?>"
                           max="<?= date('Y-m-d') ?>" required>
                </div>
                <div>
                    <label class="field-label">Paid To</label>
                    <input type="text" name="paid_to" class="form-input"
                           placeholder="Vendor / person name"
                           value="<?= htmlspecialchars($_POST['paid_to'] ?? '') ?>">
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label">Description</label>
                    <textarea name="description" class="form-input" rows="3"
                              placeholder="Describe this expense..."
                              style="resize:vertical"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Upload -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(99,102,241,0.15)">
                <i class="fas fa-image text-indigo-400"></i>
            </div>
            <span class="form-section-title">Receipt Image <span class="text-slate-500 text-xs font-400">(Optional)</span></span>
        </div>
        <div class="form-section-body">
            <div class="upload-zone" onclick="document.getElementById('receiptInput').click()">
                <i class="fas fa-cloud-upload-alt text-slate-600 text-2xl mb-2 block"></i>
                <p class="text-slate-400 font-600 text-sm">Click to upload receipt</p>
                <p class="text-slate-600 text-xs mt-1">JPG, PNG — max 5MB</p>
            </div>
            <input type="file" name="receipt_image" id="receiptInput"
                   accept="image/jpeg,image/png,image/webp" class="hidden"
                   onchange="previewReceipt(this)">
            <div id="receiptPreview" class="mt-3 hidden">
                <img id="receiptImg" src="" alt="receipt"
                     class="max-h-40 rounded-xl border"
                     style="border-color:rgba(148,163,184,0.15)">
            </div>
        </div>
    </div>

    <!-- Submit -->
    <div class="flex items-center justify-between gap-4 py-4 sticky bottom-0 px-4 -mx-4"
         style="background:rgba(10,15,30,0.95);backdrop-filter:blur(12px);border-top:1px solid rgba(148,163,184,0.08)">
        <a href="/car-showroom/modules/expenses/index.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Cancel
        </a>
        <button type="submit" class="btn-primary">
            <i class="fas fa-plus mr-2"></i> Add Expense
        </button>
    </div>

    </form>
</div>
</div>

<script>
function selectCategory(label, el) {
    document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('categoryInput').value = label;
    const wrap = document.getElementById('customCatWrap');
    wrap.classList.toggle('hidden', label !== 'Other');
}

function previewReceipt(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('receiptImg').src = e.target.result;
            document.getElementById('receiptPreview').classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

document.getElementById('expenseForm').addEventListener('submit', function(e) {
    const cat = document.getElementById('categoryInput').value;
    if (!cat) {
        e.preventDefault();
        alert('Please select an expense category.');
    }
});
</script>
</body>
</html>