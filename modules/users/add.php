<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('users.manage');

Auth::check();
if (!Auth::hasRole(['Admin'])) {
    setFlash('error', 'Access denied.');
    redirect('dashboard/index.php');
}

$db     = Database::getInstance();
$roles  = $db->fetchAll("SELECT * FROM roles ORDER BY name");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request.';
    } else {
        $fullName      = clean($_POST['full_name'] ?? '');
        $email         = clean($_POST['email'] ?? '');
        $phone         = clean($_POST['phone'] ?? '');
        $password      = $_POST['password'] ?? '';
        $confirmPass   = $_POST['confirm_password'] ?? '';
        $roleId        = (int)($_POST['role_id'] ?? 0);
        $status        = clean($_POST['status'] ?? 'active');
        $commType      = clean($_POST['commission_type'] ?? 'percentage');
        $commValue     = (float)($_POST['commission_value'] ?? 0);

        if (empty($fullName))   $errors[] = 'Full name is required.';
        if (empty($email))      $errors[] = 'Email is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
        if (empty($password))   $errors[] = 'Password is required.';
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($password !== $confirmPass) $errors[] = 'Passwords do not match.';
        if (!$roleId)           $errors[] = 'Please select a role.';

        // Check email unique
        if (!empty($email)) {
            $exists = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$email], 's');
            if ($exists) $errors[] = 'This email is already registered.';
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $uid  = $db->insert(
                "INSERT INTO users (full_name, email, phone, password, role_id, status, commission_type, commission_value)
                 VALUES (?,?,?,?,?,?,?,?)",
                [$fullName, $email, $phone, $hash, $roleId, $status, $commType, $commValue],
                'ssssissd'
            );

            if ($uid) {
                setFlash('success', "User '$fullName' created successfully!");
                redirect('modules/users/index.php');
            } else {
                $errors[] = 'Failed to create user.';
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Add User';
$pageSub   = 'Create a new system user';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User — AutoManager Pro</title>
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
        .req { color:#f87171; }
        .form-input {
            width:100%; background:rgba(30,41,59,0.8);
            border:1.5px solid rgba(148,163,184,0.12);
            color:#f1f5f9; font-size:0.875rem; padding:10px 14px; border-radius:10px;
            transition:border-color 0.2s; font-family:'Plus Jakarta Sans',sans-serif;
        }
        .form-input:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,0.15); }
        .form-input::placeholder { color:#334155; }
        select.form-input option { background:#1e293b; color:#f1f5f9; }
        .pass-wrap { position:relative; }
        .pass-wrap .form-input { padding-right:40px; }
        .pass-eye {
            position:absolute; right:12px; top:50%; transform:translateY(-50%);
            color:#475569; cursor:pointer; font-size:0.85rem;
        }
        .pass-eye:hover { color:#94a3b8; }
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
        .error-alert ul { margin-top:6px; padding-left:18px; }
        .error-alert li { font-size:0.85rem; }
        .role-info {
            margin-top:10px; padding:12px; border-radius:10px;
            background:rgba(37,99,235,0.06); border:1px solid rgba(37,99,235,0.15);
            font-size:0.8rem; color:#64748b;
        }
    </style>
</head>
<body>
<?php require_once '../../views/layouts/sidebar.php'; ?>
<div class="main">
<?php require_once '../../views/layouts/topbar.php'; ?>
<div class="content-area" style="max-width:720px;">

    <?php if (!empty($errors)): ?>
    <div class="error-alert">
        <div class="flex items-center gap-2 font-700 text-sm">
            <i class="fas fa-circle-exclamation"></i> Please fix:
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

    <!-- Basic Info -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(37,99,235,0.15)">
                <i class="fas fa-user text-blue-400"></i>
            </div>
            <span class="form-section-title">Basic Information</span>
        </div>
        <div class="form-section-body">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="field-label">Full Name <span class="req">*</span></label>
                    <input type="text" name="full_name" class="form-input"
                           placeholder="User's full name"
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                </div>
                <div>
                    <label class="field-label">Email <span class="req">*</span></label>
                    <input type="email" name="email" class="form-input"
                           placeholder="user@showroom.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div>
                    <label class="field-label">Phone</label>
                    <input type="text" name="phone" class="form-input"
                           placeholder="03XX-XXXXXXX"
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                <div>
                    <label class="field-label">Password <span class="req">*</span></label>
                    <div class="pass-wrap">
                        <input type="password" name="password" id="pass1"
                               class="form-input" placeholder="Min 6 characters" required>
                        <i class="fas fa-eye pass-eye" onclick="togglePass('pass1', this)"></i>
                    </div>
                </div>
                <div>
                    <label class="field-label">Confirm Password <span class="req">*</span></label>
                    <div class="pass-wrap">
                        <input type="password" name="confirm_password" id="pass2"
                               class="form-input" placeholder="Repeat password" required>
                        <i class="fas fa-eye pass-eye" onclick="togglePass('pass2', this)"></i>
                    </div>
                </div>
                <div>
                    <label class="field-label">Status</label>
                    <select name="status" class="form-input">
                        <option value="active"   <?= (($_POST['status']??'active')==='active')  ?'selected':'' ?>>Active</option>
                        <option value="inactive" <?= (($_POST['status']??'')==='inactive')?'selected':'' ?>>Inactive</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Role -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(124,58,237,0.15)">
                <i class="fas fa-shield-halved text-purple-400"></i>
            </div>
            <span class="form-section-title">Role & Permissions</span>
        </div>
        <div class="form-section-body">
            <label class="field-label">Assign Role <span class="req">*</span></label>
            <select name="role_id" class="form-input" onchange="showRoleInfo(this)" required>
                <option value="">— Select Role —</option>
                <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>"
                        data-name="<?= htmlspecialchars($r['name']) ?>"
                        <?= (($_POST['role_id']??'')==$r['id'])?'selected':'' ?>>
                    <?= htmlspecialchars($r['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <div id="roleInfo" class="role-info hidden"></div>
        </div>
    </div>

    <!-- Commission -->
    <div class="form-section" id="commissionSection" style="display:none">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(245,158,11,0.15)">
                <i class="fas fa-percent text-amber-400"></i>
            </div>
            <span class="form-section-title">Commission Settings</span>
        </div>
        <div class="form-section-body">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="field-label">Commission Type</label>
                    <select name="commission_type" class="form-input">
                        <option value="percentage" <?= (($_POST['commission_type']??'percentage')==='percentage')?'selected':'' ?>>Percentage (%)</option>
                        <option value="fixed"      <?= (($_POST['commission_type']??'')==='fixed')?'selected':'' ?>>Fixed Amount (PKR)</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Commission Value</label>
                    <input type="number" name="commission_value" class="form-input"
                           placeholder="e.g. 2.5 for 2.5% or 5000 for fixed"
                           min="0" step="0.5"
                           value="<?= htmlspecialchars($_POST['commission_value'] ?? '0') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-between gap-4 py-4 sticky bottom-0 px-4 -mx-4"
         style="background:rgba(10,15,30,0.95);backdrop-filter:blur(12px);border-top:1px solid rgba(148,163,184,0.08)">
        <a href="/car-showroom/modules/users/index.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Cancel
        </a>
        <button type="submit" class="btn-primary">
            <i class="fas fa-plus mr-2"></i> Create User
        </button>
    </div>
    </form>
</div>
</div>

<script>
const roleDescriptions = {
    'Admin':       'Full access to everything including users, settings and all financial data.',
    'Manager':     'Can manage inventory, leads, sales and view reports. Cannot manage users.',
    'Salesperson': 'Can view inventory, manage leads and create sales. No financial reports.',
    'Accountant':  'Can view and manage accounts, expenses and financial reports only.',
};
const commRoles = ['Salesperson', 'Manager'];

function showRoleInfo(sel) {
    const name = sel.options[sel.selectedIndex]?.dataset?.name || '';
    const info = document.getElementById('roleInfo');
    if (name && roleDescriptions[name]) {
        info.textContent = '🔒 ' + roleDescriptions[name];
        info.classList.remove('hidden');
    } else {
        info.classList.add('hidden');
    }
    document.getElementById('commissionSection').style.display =
        commRoles.includes(name) ? '' : 'none';
}

function togglePass(id, icon) {
    const input = document.getElementById(id);
    const show  = input.type === 'password';
    input.type  = show ? 'text' : 'password';
    icon.className = (show ? 'fas fa-eye-slash' : 'fas fa-eye') + ' pass-eye';
}

// Init on page load (error state)
document.querySelector('select[name="role_id"]').dispatchEvent(new Event('change'));
</script>
</body>
</html>