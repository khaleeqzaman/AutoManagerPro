<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('users.manage');

$db = Database::getInstance();

$users = $db->fetchAll(
    "SELECT u.*, r.name as role_name
     FROM users u
     JOIN roles r ON u.role_id = r.id
     WHERE u.status != 'inactive'
     ORDER BY r.name ASC, u.full_name ASC"
);

$roles     = $db->fetchAll("SELECT * FROM roles ORDER BY name");
$flash     = getFlash();
$pageTitle = 'Users & Roles';
$pageSub   = count($users) . ' total users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users — AutoManager Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/car-showroom/public/css/fa/all.min.css">
    <style>
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
        .user-card {
            display:flex; align-items:center; gap:14px;
            padding:14px 20px;
            border-bottom:1px solid rgba(148,163,184,0.05);
            transition:background 0.15s;
        }
        .user-card:last-child { border-bottom:none; }
        .user-card:hover { background:rgba(148,163,184,0.02); }
        .avatar {
            width:42px; height:42px; border-radius:12px;
            display:flex; align-items:center; justify-content:center;
            font-size:1rem; font-weight:800; flex-shrink:0;
        }
        .role-badge {
            font-size:0.68rem; font-weight:700;
            padding:3px 10px; border-radius:20px;
            text-transform:uppercase; letter-spacing:0.04em;
        }
        .role-Admin       { background:rgba(220,38,38,0.15);  color:#f87171; border:1px solid rgba(220,38,38,0.2); }
        .role-Manager     { background:rgba(37,99,235,0.15);  color:#60a5fa; border:1px solid rgba(37,99,235,0.2); }
        .role-Salesperson { background:rgba(22,163,74,0.15);  color:#4ade80; border:1px solid rgba(22,163,74,0.2); }
        .role-Accountant  { background:rgba(124,58,237,0.15); color:#a78bfa; border:1px solid rgba(124,58,237,0.2); }
        .status-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
        .status-dot.active   { background:#4ade80; box-shadow:0 0 6px rgba(74,222,128,0.5); }
        .status-dot.inactive { background:#475569; }
        .action-btn {
            display:inline-flex; align-items:center; gap:5px;
            padding:5px 11px; border-radius:7px;
            font-size:0.75rem; font-weight:600;
            text-decoration:none; transition:all 0.15s;
            border:1px solid transparent;
        }
        .btn-edit { background:rgba(245,158,11,0.1); color:#fbbf24; border-color:rgba(245,158,11,0.2); }
        .btn-edit:hover { background:rgba(245,158,11,0.2); }
        .btn-del  { background:rgba(220,38,38,0.1);  color:#f87171; border-color:rgba(220,38,38,0.2); cursor:pointer; }
        .btn-del:hover { background:rgba(220,38,38,0.2); }
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

    <div class="flex items-center justify-between mb-5">
        <div></div>
        <div class="flex gap-2">
            <a href="/car-showroom/modules/settings/permissions.php"
               class="px-4 py-2.5 rounded-xl text-sm font-700 text-purple-400 transition-all flex items-center gap-2"
               style="background:rgba(124,58,237,0.1);border:1px solid rgba(124,58,237,0.2)">
                <i class="fas fa-shield-halved"></i> Manage Permissions
            </a>
            <a href="/car-showroom/modules/users/add.php"
               class="px-5 py-2.5 rounded-xl text-sm font-700 bg-blue-600 hover:bg-blue-700 text-white transition-all flex items-center gap-2">
                <i class="fas fa-plus"></i> Add User
            </a>
        </div>
    </div>

    <?php
    $grouped = [];
    foreach ($users as $u) {
        $grouped[$u['role_name']][] = $u;
    }
    $avatarColors = [
        'Admin'       => ['bg'=>'rgba(220,38,38,0.2)',  'color'=>'#f87171'],
        'Manager'     => ['bg'=>'rgba(37,99,235,0.2)',  'color'=>'#60a5fa'],
        'Salesperson' => ['bg'=>'rgba(22,163,74,0.2)',  'color'=>'#4ade80'],
        'Accountant'  => ['bg'=>'rgba(124,58,237,0.2)', 'color'=>'#a78bfa'],
    ];
    foreach ($grouped as $roleName => $roleUsers):
        $ac = $avatarColors[$roleName] ?? ['bg'=>'rgba(100,116,139,0.2)','color'=>'#94a3b8'];
    ?>
    <div class="section-card mb-5">
        <div class="section-header">
            <span class="section-title">
                <span class="role-badge role-<?= $roleName ?> mr-2"><?= $roleName ?></span>
                <span class="text-slate-500 font-400 text-sm">
                    <?= count($roleUsers) ?> user<?= count($roleUsers) !== 1 ? 's' : '' ?>
                </span>
            </span>
        </div>
        <?php foreach ($roleUsers as $u):
            $initials = strtoupper(implode('', array_map(
                fn($w) => $w[0],
                array_slice(explode(' ', $u['full_name']), 0, 2)
            )));
        ?>
        <div class="user-card">
            <div class="avatar" style="background:<?= $ac['bg'] ?>;color:<?= $ac['color'] ?>">
                <?= $initials ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <span class="text-slate-200 font-700 text-sm">
                        <?= htmlspecialchars($u['full_name']) ?>
                    </span>
                    <div class="status-dot <?= $u['status'] ?>"></div>
                    <?php if ($u['id'] === Auth::id()): ?>
                    <span style="font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:4px;
                                 background:rgba(37,99,235,0.15);color:#60a5fa;border:1px solid rgba(37,99,235,0.2)">
                        YOU
                    </span>
                    <?php endif; ?>
                </div>
                <div class="text-slate-500 text-xs mt-0.5"><?= htmlspecialchars($u['email']) ?></div>
            </div>
            <div class="hidden sm:block text-slate-500 text-xs text-center mx-4">
                <div class="text-slate-300 font-600"><?= htmlspecialchars($u['phone'] ?? '—') ?></div>
                <div>Phone</div>
            </div>
            <?php if (in_array($roleName, ['Salesperson','Manager'])): ?>
            <div class="hidden sm:block text-slate-500 text-xs text-center mx-4">
                <div class="text-amber-400 font-700">
                    <?= $u['commission_value'] > 0
                        ? ($u['commission_type']==='percentage'
                            ? $u['commission_value'].'%'
                            : formatPrice($u['commission_value']))
                        : '—' ?>
                </div>
                <div>Commission</div>
            </div>
            <?php endif; ?>
            <div class="hidden sm:block text-slate-500 text-xs text-center mx-4">
                <div class="text-slate-300 font-600">
                    <?= date('d M Y', strtotime($u['created_at'])) ?>
                </div>
                <div>Joined</div>
            </div>
            <div class="flex items-center gap-2">
                <a href="/car-showroom/modules/users/edit.php?id=<?= $u['id'] ?>"
                   class="action-btn btn-edit">
                    <i class="fas fa-pen"></i> Edit
                </a>
                <?php if ($u['id'] !== Auth::id()): ?>
                <button onclick="confirmDelete(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>')"
                        class="action-btn btn-del">
                    <i class="fas fa-trash"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <?php if (empty($grouped)): ?>
    <div class="text-center py-16 text-slate-600">
        <i class="fas fa-users text-4xl mb-3 block opacity-20"></i>
        <p class="font-600 text-slate-500">No users found</p>
    </div>
    <?php endif; ?>

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
        <h3 class="text-white text-center font-700 text-base mb-2">Deactivate User?</h3>
        <p class="text-slate-400 text-center text-sm mb-1" id="deleteUserName"></p>
        <p class="text-slate-600 text-center text-xs mb-6">
            Their sales and leads history will be preserved.
        </p>
        <div class="flex gap-3">
            <button onclick="closeDelete()"
                    class="flex-1 py-2.5 rounded-xl text-sm font-600 text-slate-400"
                    style="background:rgba(30,41,59,0.8);border:1px solid rgba(148,163,184,0.1)">
                Cancel
            </button>
            <a id="deleteConfirmBtn" href="#"
               class="flex-1 py-2.5 rounded-xl text-sm font-700 text-white text-center bg-red-600 hover:bg-red-700 transition-all">
                Deactivate
            </a>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    document.getElementById('deleteUserName').textContent = name;
    document.getElementById('deleteConfirmBtn').href =
        '/car-showroom/modules/users/delete.php?id=' + id;
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