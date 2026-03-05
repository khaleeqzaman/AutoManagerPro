<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('leads.add');
Auth::check();

$db     = Database::getInstance();
$errors = [];

// Available cars for dropdown
$availableCars = $db->fetchAll(
    "SELECT id, year, make, model, variant FROM cars
     WHERE status = 'available' ORDER BY make, model"
);

// Salespersons
$salespersons = $db->fetchAll(
    "SELECT u.id, u.full_name FROM users u
     JOIN roles r ON u.role_id = r.id
     WHERE r.name IN ('Salesperson','Manager','Admin') AND u.status='active'
     ORDER BY u.full_name"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request.';
    } else {
        $name        = clean($_POST['name'] ?? '');
        $phone       = clean($_POST['phone'] ?? '');
        $email       = clean($_POST['email'] ?? '');
        $source      = clean($_POST['source'] ?? 'walk_in');
        $status      = clean($_POST['status'] ?? 'new');
        $message     = clean($_POST['message'] ?? '');
        $notes       = clean($_POST['notes'] ?? '');
        $car_id      = (int)($_POST['car_id'] ?? 0) ?: null;
        $assigned_to = (int)($_POST['assigned_to'] ?? 0) ?: null;
        $follow_up   = clean($_POST['follow_up_date'] ?? '') ?: null;

        if (empty($name))  $errors[] = 'Name is required.';
        if (empty($phone)) $errors[] = 'Phone number is required.';

        if (empty($errors)) {
            $leadId = $db->insert(
                "INSERT INTO leads (car_id, name, phone, email, source, status, message, notes, assigned_to, follow_up_date)
                 VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$car_id, $name, $phone, $email, $source, $status, $message, $notes, $assigned_to, $follow_up],
                'isssssssss'
            );

            if ($leadId) {
                setFlash('success', 'Lead added successfully!');
                redirect('modules/leads/index.php');
            } else {
                $errors[] = 'Failed to save lead.';
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Add New Lead';
$pageSub   = 'Record a new inquiry or walk-in';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Lead — AutoManager Pro</title>
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
        .btn-primary {
            background:linear-gradient(135deg,#2563eb,#1d4ed8);
            color:#fff; font-weight:700; font-size:0.9rem;
            padding:12px 28px; border-radius:12px; border:none;
            cursor:pointer; transition:all 0.25s;
            box-shadow:0 4px 20px rgba(37,99,235,0.3);
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
<div class="content-area" style="max-width:800px;">

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

    <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <!-- Contact Info -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(37,99,235,0.15)">
                <i class="fas fa-user text-blue-400"></i>
            </div>
            <span class="form-section-title">Contact Information</span>
        </div>
        <div class="form-section-body">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="field-label">Full Name <span class="req">*</span></label>
                    <input type="text" name="name" class="form-input"
                           placeholder="Customer name"
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                </div>
                <div>
                    <label class="field-label">Phone <span class="req">*</span></label>
                    <input type="text" name="phone" class="form-input"
                           placeholder="03XX-XXXXXXX"
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                </div>
                <div>
                    <label class="field-label">Email</label>
                    <input type="email" name="email" class="form-input"
                           placeholder="Optional"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div>
                    <label class="field-label">Source</label>
                    <select name="source" class="form-input">
                        <?php foreach (['website'=>'Website','walk_in'=>'Walk In','phone'=>'Phone Call','whatsapp'=>'WhatsApp','referral'=>'Referral'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= (($_POST['source']??'walk_in')===$v)?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Lead Details -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(139,92,246,0.15)">
                <i class="fas fa-car text-purple-400"></i>
            </div>
            <span class="form-section-title">Lead Details</span>
        </div>
        <div class="form-section-body">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="field-label">Interested In</label>
                    <select name="car_id" class="form-input">
                        <option value="">— Select Vehicle (Optional) —</option>
                        <?php foreach ($availableCars as $ac): ?>
                        <option value="<?= $ac['id'] ?>"
                            <?= (($_POST['car_id']??'')==$ac['id'])?'selected':'' ?>>
                            <?= htmlspecialchars($ac['year'].' '.$ac['make'].' '.$ac['model'].' '.($ac['variant']??'')) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="field-label">Status</label>
                    <select name="status" class="form-input">
                        <?php foreach (['new'=>'New','contacted'=>'Contacted','interested'=>'Interested','negotiating'=>'Negotiating','closed_won'=>'Closed Won','closed_lost'=>'Closed Lost'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= (($_POST['status']??'new')===$v)?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="field-label">Assign To</label>
                    <select name="assigned_to" class="form-input">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($salespersons as $sp): ?>
                        <option value="<?= $sp['id'] ?>"
                            <?= (($_POST['assigned_to']??'')==$sp['id'])?'selected':'' ?>>
                            <?= htmlspecialchars($sp['full_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="field-label">Follow Up Date</label>
                    <input type="date" name="follow_up_date" class="form-input"
                           value="<?= htmlspecialchars($_POST['follow_up_date'] ?? '') ?>"
                           min="<?= date('Y-m-d') ?>">
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label">Message / Inquiry</label>
                    <textarea name="message" class="form-input" rows="3"
                              placeholder="What did the customer say?"
                              style="resize:vertical"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label">Internal Notes</label>
                    <textarea name="notes" class="form-input" rows="2"
                              placeholder="Internal notes (not visible to customer)"
                              style="resize:vertical"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit -->
    <div class="flex items-center justify-between gap-4 py-4 sticky bottom-0 px-4 -mx-4"
         style="background:rgba(10,15,30,0.95);backdrop-filter:blur(12px);border-top:1px solid rgba(148,163,184,0.08)">
        <a href="/car-showroom/modules/leads/index.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Cancel
        </a>
        <button type="submit" class="btn-primary">
            <i class="fas fa-plus mr-2"></i> Add Lead
        </button>
    </div>

    </form>
</div>
</div>
</body>
</html>