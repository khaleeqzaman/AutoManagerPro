<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('leads.view');

Auth::check();

$db = Database::getInstance();

// Filters
$where  = [];
$params = [];
$types  = '';

if (!empty($_GET['status'])) {
    $where[]  = "l.status = ?";
    $params[] = clean($_GET['status']);
    $types   .= 's';
}
if (!empty($_GET['source'])) {
    $where[]  = "l.source = ?";
    $params[] = clean($_GET['source']);
    $types   .= 's';
}
if (!empty($_GET['search'])) {
    $s        = '%' . clean($_GET['search']) . '%';
    $where[]  = "(l.name LIKE ? OR l.phone LIKE ? OR l.email LIKE ?)";
    $params[] = $s; $params[] = $s; $params[] = $s;
    $types   .= 'sss';
}
if (!empty($_GET['assigned_to'])) {
    $where[]  = "l.assigned_to = ?";
    $params[] = (int)$_GET['assigned_to'];
    $types   .= 'i';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Pagination
$perPage    = 15;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;
$totalRows  = $db->fetchOne("SELECT COUNT(*) as cnt FROM leads l $whereSQL", $params, $types)['cnt'] ?? 0;
$totalPages = ceil($totalRows / $perPage);

$leads = $db->fetchAll(
    "SELECT l.*,
        c.make, c.model, c.year,
        u.full_name as assigned_name
     FROM leads l
     LEFT JOIN cars c ON l.car_id = c.id
     LEFT JOIN users u ON l.assigned_to = u.id
     $whereSQL
     ORDER BY l.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params, $types
);

// Stats per status
$statusCounts = [];
$statRows = $db->fetchAll("SELECT status, COUNT(*) as cnt FROM leads GROUP BY status");
foreach ($statRows as $row) {
    $statusCounts[$row['status']] = $row['cnt'];
}

// Salespersons for filter
$salespersons = $db->fetchAll(
    "SELECT u.id, u.full_name FROM users u
     JOIN roles r ON u.role_id = r.id
     WHERE r.name IN ('Salesperson','Manager','Admin') AND u.status='active'
     ORDER BY u.full_name"
);

$flash     = getFlash();
$pageTitle = 'Leads & CRM';
$pageSub   = $totalRows . ' total leads';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads — AutoManager Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/car-showroom/public/css/fa/all.min.css">
    <style>
        .badge {
            font-size:0.68rem; font-weight:700;
            padding:3px 10px; border-radius:20px;
            letter-spacing:0.04em; text-transform:uppercase;
            display:inline-flex; align-items:center; gap:4px;
        }
        .badge-new         { background:rgba(37,99,235,0.15);  color:#60a5fa;  border:1px solid rgba(37,99,235,0.2); }
        .badge-contacted   { background:rgba(124,58,237,0.15); color:#a78bfa;  border:1px solid rgba(124,58,237,0.2); }
        .badge-interested  { background:rgba(13,148,136,0.15); color:#2dd4bf;  border:1px solid rgba(13,148,136,0.2); }
        .badge-negotiating { background:rgba(217,119,6,0.15);  color:#fbbf24;  border:1px solid rgba(217,119,6,0.2); }
        .badge-closed_won  { background:rgba(22,163,74,0.15);  color:#4ade80;  border:1px solid rgba(22,163,74,0.2); }
        .badge-closed_lost { background:rgba(220,38,38,0.15);  color:#f87171;  border:1px solid rgba(220,38,38,0.2); }

        .source-badge {
            font-size:0.68rem; font-weight:600;
            padding:2px 8px; border-radius:6px;
            background:rgba(30,41,59,0.8);
            border:1px solid rgba(148,163,184,0.1);
            color:#64748b; text-transform:capitalize;
        }

        .lead-row {
            border-bottom:1px solid rgba(148,163,184,0.05);
            transition:background 0.15s;
        }
        .lead-row:hover { background:rgba(148,163,184,0.03); }
        .lead-row:last-child { border-bottom:none; }

        .stat-pill {
            display:flex; align-items:center; gap:8px;
            padding:10px 16px; border-radius:12px;
            cursor:pointer; transition:all 0.2s;
            text-decoration:none;
            border:1.5px solid transparent;
        }
        .stat-pill:hover { transform:translateY(-1px); }
        .stat-pill.active { border-color:currentColor; }

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

        .data-table { width:100%; border-collapse:collapse; }
        .data-table th {
            font-size:0.7rem; font-weight:700;
            letter-spacing:0.08em; text-transform:uppercase;
            color:#475569; padding:11px 16px;
            border-bottom:1px solid rgba(148,163,184,0.08);
            text-align:left; white-space:nowrap;
        }
        .data-table td {
            padding:13px 16px;
            font-size:0.85rem; color:#94a3b8;
            vertical-align:middle;
        }

        .action-btn {
            display:inline-flex; align-items:center; gap:5px;
            padding:5px 11px; border-radius:7px;
            font-size:0.75rem; font-weight:600;
            text-decoration:none; transition:all 0.15s;
            border:1px solid transparent;
        }
        .btn-view  { background:rgba(37,99,235,0.1);  color:#60a5fa;  border-color:rgba(37,99,235,0.2); }
        .btn-edit  { background:rgba(245,158,11,0.1); color:#fbbf24;  border-color:rgba(245,158,11,0.2); }
        .btn-del   { background:rgba(220,38,38,0.1);  color:#f87171;  border-color:rgba(220,38,38,0.2); }
        .btn-view:hover { background:rgba(37,99,235,0.2); }
        .btn-edit:hover { background:rgba(245,158,11,0.2); }
        .btn-del:hover  { background:rgba(220,38,38,0.2); }

        .follow-up-due   { color:#fbbf24; font-weight:600; }
        .follow-up-over  { color:#f87171; font-weight:600; }
        .follow-up-ok    { color:#4ade80; }
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

    <!-- Pipeline Stats -->
    <div class="flex flex-wrap gap-2 mb-5">
        <?php
        $pipelines = [
            ''             => ['label'=>'All',         'color'=>'#60a5fa',  'icon'=>'fa-list'],
            'new'          => ['label'=>'New',         'color'=>'#60a5fa',  'icon'=>'fa-star'],
            'contacted'    => ['label'=>'Contacted',   'color'=>'#a78bfa',  'icon'=>'fa-phone'],
            'interested'   => ['label'=>'Interested',  'color'=>'#2dd4bf',  'icon'=>'fa-heart'],
            'negotiating'  => ['label'=>'Negotiating', 'color'=>'#fbbf24',  'icon'=>'fa-handshake'],
            'closed_won'   => ['label'=>'Won',         'color'=>'#4ade80',  'icon'=>'fa-trophy'],
            'closed_lost'  => ['label'=>'Lost',        'color'=>'#f87171',  'icon'=>'fa-xmark'],
        ];
        $activeStatus = $_GET['status'] ?? '';
        foreach ($pipelines as $val => $p):
            $count   = $val === '' ? $totalRows : ($statusCounts[$val] ?? 0);
            $isActive= ($activeStatus === $val);
            $qp      = array_merge($_GET, ['status'=>$val,'page'=>1]);
            if ($val === '') unset($qp['status']);
            $url = '?' . http_build_query($qp);
        ?>
        <a href="<?= $url ?>"
           class="stat-pill <?= $isActive ? 'active' : '' ?>"
           style="color:<?= $p['color'] ?>;
                  background:<?= $isActive ? 'rgba(255,255,255,0.06)' : 'rgba(13,20,40,0.8)' ?>;
                  border-color:<?= $isActive ? $p['color'] : 'rgba(148,163,184,0.08)' ?>">
            <i class="fas <?= $p['icon'] ?> text-xs"></i>
            <span class="text-xs font-700"><?= $p['label'] ?></span>
            <span class="text-xs font-800 ml-1" style="opacity:0.8"><?= $count ?></span>
        </a>
        <?php endforeach; ?>

        <a href="/car-showroom/modules/leads/add.php"
           class="ml-auto px-4 py-2 rounded-xl text-xs font-700 bg-blue-600 hover:bg-blue-700 text-white transition-all flex items-center gap-2">
            <i class="fas fa-plus"></i> Add Lead
        </a>
    </div>

    <!-- Filters -->
    <form method="GET" class="flex flex-wrap gap-3 mb-5 p-4 rounded-xl"
          style="background:#0d1526;border:1px solid rgba(148,163,184,0.08)">
        <?php if (!empty($_GET['status'])): ?>
        <input type="hidden" name="status" value="<?= htmlspecialchars($_GET['status']) ?>">
        <?php endif; ?>

        <input type="text" name="search" placeholder="Search name, phone, email..."
               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
               class="flex-1 min-w-[180px] px-4 py-2 rounded-xl text-sm text-slate-200 placeholder-slate-600"
               style="background:rgba(30,41,59,0.8);border:1.5px solid rgba(148,163,184,0.12)">

        <select name="source" class="px-4 py-2 rounded-xl text-sm text-slate-300"
                style="background:rgba(30,41,59,0.8);border:1.5px solid rgba(148,163,184,0.12)">
            <option value="">All Sources</option>
            <?php foreach (['website','walk_in','phone','whatsapp','referral'] as $src): ?>
            <option value="<?= $src ?>" <?= (($_GET['source']??'')===$src)?'selected':'' ?>>
                <?= ucfirst(str_replace('_',' ',$src)) ?>
            </option>
            <?php endforeach; ?>
        </select>

        <select name="assigned_to" class="px-4 py-2 rounded-xl text-sm text-slate-300"
                style="background:rgba(30,41,59,0.8);border:1.5px solid rgba(148,163,184,0.12)">
            <option value="">All Staff</option>
            <?php foreach ($salespersons as $sp): ?>
            <option value="<?= $sp['id'] ?>" <?= (($_GET['assigned_to']??'')==$sp['id'])?'selected':'' ?>>
                <?= htmlspecialchars($sp['full_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>

        <button type="submit"
                class="px-4 py-2 rounded-xl text-xs font-700 bg-blue-600 hover:bg-blue-700 text-white transition-all">
            <i class="fas fa-search mr-1"></i> Filter
        </button>
        <a href="/car-showroom/modules/leads/index.php"
           class="px-4 py-2 rounded-xl text-xs font-700 text-slate-400 hover:text-white transition-all"
           style="background:rgba(30,41,59,0.5);border:1px solid rgba(148,163,184,0.1)">
            <i class="fas fa-xmark mr-1"></i> Clear
        </a>
    </form>

    <!-- Table -->
    <div class="section-card">
        <div class="section-header">
            <span class="section-title">
                <i class="fas fa-filter text-purple-400 mr-2"></i>
                Leads
                <span class="text-slate-500 font-400 text-sm ml-2">(<?= $totalRows ?>)</span>
            </span>
        </div>

        <?php if (empty($leads)): ?>
        <div class="text-center py-16 text-slate-600">
            <i class="fas fa-filter text-4xl mb-3 block opacity-20"></i>
            <p class="font-600 text-slate-500">No leads found</p>
            <p class="text-sm mt-1">Try adjusting filters or add a new lead</p>
            <a href="/car-showroom/modules/leads/add.php"
               class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-600 rounded-xl transition-all">
                <i class="fas fa-plus"></i> Add First Lead
            </a>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Interested In</th>
                    <th>Source</th>
                    <th>Assigned To</th>
                    <th>Follow Up</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($leads as $i => $lead):
                $followUpClass = '';
                if ($lead['follow_up_date']) {
                    $fDate = strtotime($lead['follow_up_date']);
                    $today = strtotime(date('Y-m-d'));
                    if ($fDate < $today)       $followUpClass = 'follow-up-over';
                    elseif ($fDate === $today)  $followUpClass = 'follow-up-due';
                    else                        $followUpClass = 'follow-up-ok';
                }
            ?>
            <tr class="lead-row">
                <td class="text-slate-600 text-xs"><?= $offset + $i + 1 ?></td>
                <td>
                    <div class="text-slate-200 font-600 text-sm">
                        <?= htmlspecialchars($lead['name'] ?? '—') ?>
                    </div>
                    <?php if ($lead['message']): ?>
                    <div class="text-slate-600 text-xs mt-0.5 truncate" style="max-width:160px">
                        <?= htmlspecialchars(substr($lead['message'], 0, 50)) ?>...
                    </div>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="text-slate-300 text-sm"><?= htmlspecialchars($lead['phone'] ?? '—') ?></div>
                    <?php if ($lead['email']): ?>
                    <div class="text-slate-600 text-xs"><?= htmlspecialchars($lead['email']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($lead['make']): ?>
                    <div class="text-slate-300 text-sm font-600">
                        <?= htmlspecialchars($lead['year'].' '.$lead['make'].' '.$lead['model']) ?>
                    </div>
                    <?php else: ?>
                    <span class="text-slate-600">—</span>
                    <?php endif; ?>
                </td>
                <td><span class="source-badge"><?= ucfirst(str_replace('_',' ',$lead['source'])) ?></span></td>
                <td>
                    <span class="text-slate-400 text-sm">
                        <?= htmlspecialchars($lead['assigned_name'] ?? 'Unassigned') ?>
                    </span>
                </td>
                <td>
                    <?php if ($lead['follow_up_date']): ?>
                    <span class="text-sm <?= $followUpClass ?>">
                        <?php
                        if ($followUpClass === 'follow-up-over') echo '<i class="fas fa-exclamation-circle mr-1"></i>';
                        elseif ($followUpClass === 'follow-up-due') echo '<i class="fas fa-bell mr-1"></i>';
                        ?>
                        <?= date('d M', strtotime($lead['follow_up_date'])) ?>
                    </span>
                    <?php else: ?>
                    <span class="text-slate-600">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge badge-<?= $lead['status'] ?>">
                        <?= ucfirst(str_replace('_',' ',$lead['status'])) ?>
                    </span>
                </td>
                <td class="text-slate-600 text-xs">
                    <?= date('d M Y', strtotime($lead['created_at'])) ?>
                </td>
                <td>
                    <div class="flex items-center gap-1">
                        <a href="/car-showroom/modules/leads/edit.php?id=<?= $lead['id'] ?>"
                           class="action-btn btn-edit">
                            <i class="fas fa-pen"></i>
                        </a>
                        <?php if ($lead['status'] !== 'closed_lost'): ?>
                        <a href="/car-showroom/modules/sales/create.php?lead_id=<?= $lead['id'] ?>"
                           class="action-btn btn-view" title="Convert to Sale">
                            <i class="fas fa-handshake"></i>
                        </a>
                        <?php endif; ?>
                        <button onclick="confirmDelete(<?= $lead['id'] ?>, '<?= htmlspecialchars($lead['name'] ?? '', ENT_QUOTES) ?>')"
                                class="action-btn btn-del">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-center gap-2 p-4">
            <?php for ($i = 1; $i <= $totalPages; $i++):
                $pUrl = '?' . http_build_query(array_merge($_GET, ['page'=>$i]));
            ?>
            <a href="<?= $pUrl ?>"
               class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-600 transition-all
               <?= $i===$page ? 'bg-blue-600 text-white' : 'text-slate-400 hover:text-white' ?>"
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

<!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden items-center justify-center"
     style="background:rgba(0,0,0,0.7);backdrop-filter:blur(4px)">
    <div class="rounded-2xl p-6 w-full max-w-sm mx-4"
         style="background:#0d1526;border:1px solid rgba(148,163,184,0.1)">
        <div class="w-12 h-12 bg-red-500/15 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-trash text-red-400 text-xl"></i>
        </div>
        <h3 class="text-white text-center font-700 text-base mb-2">Delete Lead?</h3>
        <p class="text-slate-400 text-center text-sm mb-6" id="deleteLeadName"></p>
        <div class="flex gap-3">
            <button onclick="closeDelete()"
                    class="flex-1 py-2.5 rounded-xl text-sm font-600 text-slate-400"
                    style="background:rgba(30,41,59,0.8);border:1px solid rgba(148,163,184,0.1)">
                Cancel
            </button>
            <a id="deleteConfirmBtn" href="#"
               class="flex-1 py-2.5 rounded-xl text-sm font-700 text-white text-center bg-red-600 hover:bg-red-700 transition-all">
                Delete
            </a>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    document.getElementById('deleteLeadName').textContent = name;
    document.getElementById('deleteConfirmBtn').href =
        '/car-showroom/modules/leads/delete.php?id=' + id;
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