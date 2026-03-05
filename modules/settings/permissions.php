<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('settings.manage');

Auth::check();
Permissions::require('settings.manage');

$db = Database::getInstance();

// Handle toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['csrf_token'])) {
    if ($_POST['csrf_token'] === $_SESSION['csrf_token']) {
        $roleId = (int)($_POST['role_id'] ?? 0);
        $allPerms = array_keys(Permissions::all());

        foreach ($allPerms as $perm) {
            $granted = isset($_POST['perm'][$perm]) ? 1 : 0;
            $db->execute(
                "INSERT INTO role_permissions (role_id, permission, granted)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE granted = ?",
                [$roleId, $perm, $granted, $granted], 'isii'
            );
        }
        setFlash('success', 'Permissions updated successfully!');
        header('Location: permissions.php?role_id=' . $roleId);
        exit;
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$roles      = $db->fetchAll("SELECT * FROM roles ORDER BY name");
$activeRole = (int)($_GET['role_id'] ?? ($roles[0]['id'] ?? 0));

// Get current permissions for active role
$currentPerms = [];
$rows = $db->fetchAll(
    "SELECT permission, granted FROM role_permissions WHERE role_id = ?",
    [$activeRole], 'i'
);
foreach ($rows as $row) {
    $currentPerms[$row['permission']] = (bool)$row['granted'];
}

$allPerms = Permissions::all();

// Group permissions by module
$grouped = [];
foreach ($allPerms as $key => $label) {
    $module = explode('.', $key)[0];
    $grouped[$module][] = ['key' => $key, 'label' => $label];
}

$moduleIcons = [
    'inventory' => ['icon'=>'fa-car',           'color'=>'#60a5fa'],
    'leads'     => ['icon'=>'fa-filter',         'color'=>'#a78bfa'],
    'sales'     => ['icon'=>'fa-handshake',      'color'=>'#4ade80'],
    'accounts'  => ['icon'=>'fa-wallet',         'color'=>'#fbbf24'],
    'expenses'  => ['icon'=>'fa-receipt',        'color'=>'#f87171'],
    'reports'   => ['icon'=>'fa-chart-pie',      'color'=>'#2dd4bf'],
    'users'     => ['icon'=>'fa-users',          'color'=>'#f97316'],
    'settings'  => ['icon'=>'fa-gear',           'color'=>'#94a3b8'],
];

$flash     = getFlash();
$pageTitle = 'Role Permissions';
$pageSub   = 'Control what each role can access';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permissions — AutoManager Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/car-showroom/public/css/fa/all.min.css">
    <style>
        .role-tab {
            padding:10px 20px; border-radius:10px; font-size:0.85rem; font-weight:700;
            cursor:pointer; text-decoration:none; transition:all 0.2s;
            border:1.5px solid rgba(148,163,184,0.1);
            background:rgba(13,20,40,0.8); color:#64748b;
        }
        .role-tab.active {
            background:rgba(37,99,235,0.15); color:#60a5fa;
            border-color:rgba(37,99,235,0.3);
        }
        .role-tab:hover:not(.active) { color:#e2e8f0; }

        .perm-card {
            background:#0d1526;
            border:1px solid rgba(148,163,184,0.08);
            border-radius:14px; overflow:hidden; margin-bottom:16px;
        }
        .perm-card-header {
            padding:14px 20px;
            border-bottom:1px solid rgba(148,163,184,0.07);
            display:flex; align-items:center; justify-content:space-between;
        }
        .perm-card-body { padding:16px 20px; }

        .perm-row {
            display:flex; align-items:center; justify-content:space-between;
            padding:10px 0;
            border-bottom:1px solid rgba(148,163,184,0.05);
        }
        .perm-row:last-child { border-bottom:none; }
        .perm-label { font-size:0.85rem; color:#94a3b8; }

        /* Toggle switch */
        .toggle { position:relative; display:inline-block; width:44px; height:24px; }
        .toggle input { opacity:0; width:0; height:0; }
        .toggle-slider {
            position:absolute; cursor:pointer; inset:0;
            background:rgba(30,41,59,0.8); border-radius:24px;
            border:1.5px solid rgba(148,163,184,0.15);
            transition:all 0.25s;
        }
        .toggle-slider:before {
            content:''; position:absolute;
            height:16px; width:16px; border-radius:50%;
            left:3px; bottom:3px;
            background:#475569; transition:all 0.25s;
        }
        .toggle input:checked + .toggle-slider {
            background:rgba(37,99,235,0.3);
            border-color:rgba(37,99,235,0.5);
        }
        .toggle input:checked + .toggle-slider:before {
            transform:translateX(20px);
            background:#60a5fa;
            box-shadow:0 0 8px rgba(96,165,250,0.5);
        }

        .btn-save {
            background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff;
            font-weight:700; font-size:0.9rem; padding:12px 32px;
            border-radius:12px; border:none; cursor:pointer; transition:all 0.25s;
            box-shadow:0 4px 20px rgba(37,99,235,0.3);
        }
        .btn-save:hover { transform:translateY(-1px); }

        .module-icon {
            width:30px; height:30px; border-radius:8px;
            display:flex; align-items:center; justify-content:center; font-size:0.75rem;
        }

        .warning-banner {
            background:rgba(245,158,11,0.08);
            border:1px solid rgba(245,158,11,0.2);
            border-radius:12px; padding:12px 16px;
            color:#fbbf24; font-size:0.82rem;
            display:flex; align-items:center; gap:10px; margin-bottom:20px;
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

    <!-- Warning -->
    <div class="warning-banner">
        <i class="fas fa-triangle-exclamation text-lg flex-shrink-0"></i>
        <span>
            Permission changes take effect on the user's <strong>next login</strong>.
            Admin role always has full access regardless of toggles.
        </span>
    </div>

    <!-- Role Tabs -->
    <div class="flex flex-wrap gap-2 mb-6">
        <?php foreach ($roles as $role): ?>
        <a href="?role_id=<?= $role['id'] ?>"
           class="role-tab <?= $role['id']==$activeRole ? 'active' : '' ?>">
            <?= htmlspecialchars($role['name']) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Active role name -->
    <?php
    $activeRoleName = '';
    foreach ($roles as $r) {
        if ($r['id'] == $activeRole) { $activeRoleName = $r['name']; break; }
    }
    $isAdmin = $activeRoleName === 'Admin';
    ?>

    <?php if ($isAdmin): ?>
    <div class="perm-card">
        <div class="perm-card-body text-center py-8">
            <i class="fas fa-shield-halved text-red-400 text-3xl mb-3 block"></i>
            <p class="text-slate-300 font-700">Admin has all permissions by default</p>
            <p class="text-slate-500 text-sm mt-1">Admin role cannot be restricted.</p>
        </div>
    </div>
    <?php else: ?>

    <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="role_id"    value="<?= $activeRole ?>">

    <!-- Quick actions -->
    <div class="flex gap-3 mb-5">
        <button type="button" onclick="toggleAll(true)"
                class="px-4 py-2 rounded-xl text-xs font-700 text-green-400 transition-all"
                style="background:rgba(22,163,74,0.1);border:1px solid rgba(22,163,74,0.2)">
            <i class="fas fa-check-double mr-1"></i> Grant All
        </button>
        <button type="button" onclick="toggleAll(false)"
                class="px-4 py-2 rounded-xl text-xs font-700 text-red-400 transition-all"
                style="background:rgba(220,38,38,0.1);border:1px solid rgba(220,38,38,0.2)">
            <i class="fas fa-xmark mr-1"></i> Revoke All
        </button>
        <button type="button" onclick="resetDefaults()"
                class="px-4 py-2 rounded-xl text-xs font-700 text-amber-400 transition-all"
                style="background:rgba(217,119,6,0.1);border:1px solid rgba(217,119,6,0.2)">
            <i class="fas fa-rotate-left mr-1"></i> Reset to Default
        </button>
    </div>

    <!-- Permission groups -->
    <?php foreach ($grouped as $module => $perms):
        $mi = $moduleIcons[$module] ?? ['icon'=>'fa-circle','color'=>'#64748b'];
    ?>
    <div class="perm-card">
        <div class="perm-card-header">
            <div class="flex items-center gap-3">
                <div class="module-icon" style="background:rgba(255,255,255,0.05)">
                    <i class="fas <?= $mi['icon'] ?>" style="color:<?= $mi['color'] ?>"></i>
                </div>
                <span style="font-size:0.875rem;font-weight:700;color:#e2e8f0;text-transform:capitalize">
                    <?= ucfirst($module) ?>
                </span>
            </div>
            <!-- Module-level toggle all -->
            <button type="button"
                    onclick="toggleModule('<?= $module ?>')"
                    style="font-size:0.72rem;font-weight:700;color:#64748b;
                           padding:4px 10px;border-radius:6px;
                           background:rgba(30,41,59,0.5);
                           border:1px solid rgba(148,163,184,0.1)">
                Toggle All
            </button>
        </div>
        <div class="perm-card-body">
            <?php foreach ($perms as $perm): ?>
            <div class="perm-row">
                <div>
                    <div class="perm-label"><?= htmlspecialchars($perm['label']) ?></div>
                    <div style="font-size:0.7rem;color:#334155;font-family:monospace"><?= $perm['key'] ?></div>
                </div>
                <label class="toggle">
                    <input type="checkbox"
                           name="perm[<?= $perm['key'] ?>]"
                           class="perm-checkbox"
                           data-module="<?= $module ?>"
                           data-key="<?= $perm['key'] ?>"
                           <?= !empty($currentPerms[$perm['key']]) ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Save -->
    <div class="flex justify-end pt-2 pb-6">
        <button type="submit" class="btn-save">
            <i class="fas fa-save mr-2"></i>
            Save Permissions for <?= htmlspecialchars($activeRoleName) ?>
        </button>
    </div>
    </form>
    <?php endif; ?>

</div>
</div>

<script>
// Default permissions per role for reset
const defaults = <?= json_encode(Permissions::defaults()) ?>;
const activeRole = '<?= htmlspecialchars($activeRoleName) ?>';

function toggleAll(state) {
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = state);
}

function toggleModule(module) {
    const boxes = document.querySelectorAll(`.perm-checkbox[data-module="${module}"]`);
    const anyUnchecked = Array.from(boxes).some(b => !b.checked);
    boxes.forEach(b => b.checked = anyUnchecked);
}

function resetDefaults() {
    if (!confirm('Reset to default permissions for ' + activeRole + '?')) return;
    const roleDefs = defaults[activeRole] || [];
    document.querySelectorAll('.perm-checkbox').forEach(cb => {
        cb.checked = roleDefs.includes(cb.dataset.key);
    });
}
</script>
</body>
</html>