<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('leads.edit');

Auth::check();

$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    setFlash('error', 'Invalid lead ID.');
    redirect('modules/leads/index.php');
}

$lead = $db->fetchOne("SELECT * FROM leads WHERE id = ?", [$id], 'i');
if (!$lead) {
    setFlash('error', 'Lead not found.');
    redirect('modules/leads/index.php');
}

$availableCars = $db->fetchAll(
    "SELECT id, year, make, model, variant FROM cars
     WHERE status IN ('available','reserved') ORDER BY make, model"
);

$salespersons = $db->fetchAll(
    "SELECT u.id, u.full_name FROM users u
     JOIN roles r ON u.role_id = r.id
     WHERE r.name IN ('Salesperson','Manager','Admin') AND u.status='active'
     ORDER BY u.full_name"
);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request.';
    } else {
        $name        = clean($_POST['name'] ?? '');
        $phone       = clean($_POST['phone'] ?? '');
        $email       = clean($_POST['email'] ?? '');
        $source      = clean($_POST['source'] ?? '');
        $status      = clean($_POST['status'] ?? '');
        $message     = clean($_POST['message'] ?? '');
        $notes       = clean($_POST['notes'] ?? '');
        $car_id      = (int)($_POST['car_id'] ?? 0) ?: null;
        $assigned_to = (int)($_POST['assigned_to'] ?? 0) ?: null;
        $follow_up   = clean($_POST['follow_up_date'] ?? '') ?: null;

        if (empty($name))  $errors[] = 'Name is required.';
        if (empty($phone)) $errors[] = 'Phone is required.';

        if (empty($errors)) {
            $db->execute(
                "UPDATE leads SET
                    car_id=?, name=?, phone=?, email=?, source=?, status=?,
                    message=?, notes=?, assigned_to=?, follow_up_date=?
                 WHERE id=?",
                [$car_id, $name, $phone, $email, $source, $status,
                 $message, $notes, $assigned_to, $follow_up, $id],
                'ssssssssssi'
            );

            setFlash('success', 'Lead updated successfully!');
            redirect('modules/leads/index.php');
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$d = !empty($errors) ? array_merge($lead, $_POST) : $lead;

$pageTitle = 'Edit Lead';
$pageSub   = htmlspecialchars($lead['name'] ?? 'Unknown');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Lead — AutoManager Pro</title>
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

        /* Status selector cards */
        .status-cards { display:flex; flex-wrap:wrap; gap:8px; }
        .status-card {
            flex:1; min-width:100px;
            padding:10px 12px; border-radius:10px;
            border:1.5px solid rgba(148,163,184,0.1);
            background:rgba(30,41,59,0.5);
            cursor:pointer; transition:all 0.2s;
            text-align:center; user-select:none;
        }
        .status-card input[type="radio"] { display:none; }
        .status-card .sc-label { font-size:0.75rem; font-weight:700; display:block; margin-top:4px; }
        .status-card .sc-icon  { font-size:1rem; }
        .status-card.selected-new         { border-color:#3b82f6; background:rgba(37,99,235,0.12); color:#60a5fa; }
        .status-card.selected-contacted   { border-color:#8b5cf6; background:rgba(124,58,237,0.12); color:#a78bfa; }
        .status-card.selected-interested  { border-color:#0d9488; background:rgba(13,148,136,0.12); color:#2dd4bf; }
        .status-card.selected-negotiating { border-color:#d97706; background:rgba(217,119,6,0.12); color:#fbbf24; }
        .status-card.selected-closed_won  { border-color:#16a34a; background:rgba(22,163,74,0.12); color:#4ade80; }
        .status-card.selected-closed_lost { border-color:#dc2626; background:rgba(220,38,38,0.12); color:#f87171; }

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

    <!-- Status Pipeline -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(124,58,237,0.15)">
                <i class="fas fa-filter text-purple-400"></i>
            </div>
            <span class="form-section-title">Lead Status</span>
        </div>
        <div class="form-section-body">
            <div class="status-cards" id="statusCards">
                <?php
                $statuses = [
                    'new'         => ['icon'=>'fa-star',      'label'=>'New'],
                    'contacted'   => ['icon'=>'fa-phone',     'label'=>'Contacted'],
                    'interested'  => ['icon'=>'fa-heart',     'label'=>'Interested'],
                    'negotiating' => ['icon'=>'fa-handshake', 'label'=>'Negotiating'],
                    'closed_won'  => ['icon'=>'fa-trophy',    'label'=>'Won'],
                    'closed_lost' => ['icon'=>'fa-xmark',     'label'=>'Lost'],
                ];
                $currentStatus = $d['status'] ?? 'new';
                foreach ($statuses as $val => $s):
                ?>
                <label class="status-card <?= $currentStatus===$val ? 'selected-'.$val : '' ?>"
                       id="sc-<?= $val ?>">
                    <input type="radio" name="status" value="<?= $val ?>"
                           <?= $currentStatus===$val ? 'checked' : '' ?>
                           onchange="selectStatus('<?= $val ?>')">
                    <span class="sc-icon"><i class="fas <?= $s['icon'] ?>"></i></span>
                    <span class="sc-label"><?= $s['label'] ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

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
                           value="<?= htmlspecialchars($d['name'] ?? '') ?>" required>
                </div>
                <div>
                    <label class="field-label">Phone <span class="req">*</span></label>
                    <input type="text" name="phone" class="form-input"
                           value="<?= htmlspecialchars($d['phone'] ?? '') ?>" required>
                </div>
                <div>
                    <label class="field-label">Email</label>
                    <input type="email" name="email" class="form-input"
                           value="<?= htmlspecialchars($d['email'] ?? '') ?>">
                </div>
                <div>
                    <label class="field-label">Source</label>
                    <select name="source" class="form-input">
                        <?php foreach (['website'=>'Website','walk_in'=>'Walk In','phone'=>'Phone Call','whatsapp'=>'WhatsApp','referral'=>'Referral'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= ($d['source']===$v)?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Lead Details -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(16,185,129,0.15)">
                <i class="fas fa-car text-emerald-400"></i>
            </div>
            <span class="form-section-title">Lead Details</span>
        </div>
        <div class="form-section-body">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="field-label">Interested In</label>
                    <select name="car_id" class="form-input">
                        <option value="">— No specific vehicle —</option>
                        <?php foreach ($availableCars as $ac): ?>
                        <option value="<?= $ac['id'] ?>"
                            <?= ($d['car_id']==$ac['id'])?'selected':'' ?>>
                            <?= htmlspecialchars($ac['year'].' '.$ac['make'].' '.$ac['model'].' '.($ac['variant']??'')) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="field-label">Assign To</label>
                    <select name="assigned_to" class="form-input">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($salespersons as $sp): ?>
                        <option value="<?= $sp['id'] ?>"
                            <?= ($d['assigned_to']==$sp['id'])?'selected':'' ?>>
                            <?= htmlspecialchars($sp['full_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="field-label">Follow Up Date</label>
                    <input type="date" name="follow_up_date" class="form-input"
                           value="<?= htmlspecialchars($d['follow_up_date'] ?? '') ?>">
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label">Message / Inquiry</label>
                    <textarea name="message" class="form-input" rows="3"
                              style="resize:vertical"><?= htmlspecialchars($d['message'] ?? '') ?></textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label">Internal Notes</label>
                    <textarea name="notes" class="form-input" rows="2"
                              style="resize:vertical"><?= htmlspecialchars($d['notes'] ?? '') ?></textarea>
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
        <div class="flex gap-3">
            <?php if (($lead['status'] ?? '') !== 'closed_lost'): ?>
            <a href="/car-showroom/modules/sales/create.php?lead_id=<?= $lead['id'] ?>"
               class="btn-secondary" style="color:#4ade80;border-color:rgba(22,163,74,0.3)">
                <i class="fas fa-handshake"></i> Convert to Sale
            </a>
            <?php endif; ?>
            <button type="submit" class="btn-primary">
                <i class="fas fa-save mr-2"></i> Save Changes
            </button>
        </div>
    </div>

    </form>
</div>
</div>

<script>
function selectStatus(val) {
    const allCards = document.querySelectorAll('.status-card');
    allCards.forEach(card => {
        card.className = 'status-card';
    });
    const selected = document.getElementById('sc-' + val);
    if (selected) selected.classList.add('selected-' + val);
}
</script>
</body>
</html>