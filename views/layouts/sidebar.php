<?php
// views/layouts/sidebar.php
$newLeads = 0;
try {
    $db = Database::getInstance();
    $newLeads = $db->fetchOne(
        "SELECT COUNT(*) as cnt FROM leads WHERE status='new'"
    )['cnt'] ?? 0;
} catch(Exception $e) {}

if (!class_exists('Permissions')) {
    require_once __DIR__ . '/../../core/Permissions.php';
}

$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

function isActive($dir, $file = '') {
    global $currentDir, $currentPage;
    if ($file) return ($currentDir === $dir && $currentPage === $file) ? 'active' : '';
    return ($currentDir === $dir) ? 'active' : '';
}
?>
<link rel="stylesheet" href="/car-showroom/public/css/fa/all.min.css">
<link rel="stylesheet" href="/car-showroom/public/css/layout.css?v=<?= time() ?>">

<aside class="sidebar" id="sidebar">

    <!-- Logo only — no toggle here -->
<div class="sidebar-logo">
    <div class="flex items-center gap-3">
        <div style="width:36px;height:36px;background:#2563eb;border-radius:10px;
                    display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="fas fa-car-side" style="color:#fff;font-size:0.9rem"></i>
        </div>
        <div class="logo-text">
            <div style="color:#f1f5f9;font-weight:700;font-size:0.875rem;line-height:1.2">AutoManager</div>
            <div style="color:#475569;font-size:0.7rem">Pro Edition</div>
        </div>
    </div>
</div>

    <!-- Nav -->
    <nav id="sidebarNav">

    <div class="nav-section-title">Main</div>
    <a href="/car-showroom/dashboard/index.php"
       class="nav-item <?= isActive('dashboard') ?>">
        <span class="icon"><i class="fas fa-gauge-high"></i></span>
        <span class="nav-label">Dashboard</span>
        <span class="tooltip">Dashboard</span>
    </a>

    <?php if (Permissions::has('inventory.view')): ?>
    <div class="nav-section-title">Inventory</div>
    <a href="/car-showroom/modules/inventory/index.php"
       class="nav-item <?= isActive('inventory','index.php') ?>">
        <span class="icon"><i class="fas fa-car"></i></span>
        <span class="nav-label">All Cars</span>
        <span class="tooltip">All Cars</span>
    </a>
    <?php if (Permissions::has('inventory.add')): ?>
    <a href="/car-showroom/modules/inventory/add.php"
       class="nav-item <?= isActive('inventory','add.php') ?>">
        <span class="icon"><i class="fas fa-plus-circle"></i></span>
        <span class="nav-label">Add Car</span>
        <span class="tooltip">Add Car</span>
    </a>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (Permissions::has('leads.view') || Permissions::has('sales.view')): ?>
    <div class="nav-section-title">Sales & CRM</div>
    <?php if (Permissions::has('leads.view')): ?>
    <a href="/car-showroom/modules/leads/index.php"
       class="nav-item <?= isActive('leads') ?>">
        <span class="icon"><i class="fas fa-filter"></i></span>
        <span class="nav-label">Leads
            <?php if ($newLeads > 0): ?>
            <span class="nav-badge"><?= $newLeads ?></span>
            <?php endif; ?>
        </span>
        <span class="tooltip">Leads
            <?php if ($newLeads > 0): ?>
            <span class="tooltip-badge"><?= $newLeads ?></span>
            <?php endif; ?>
        </span>
    </a>
    <?php endif; ?>
    <?php if (Permissions::has('sales.view')): ?>
    <a href="/car-showroom/modules/sales/index.php"
       class="nav-item <?= isActive('sales','index.php') ?>">
        <span class="icon"><i class="fas fa-handshake"></i></span>
        <span class="nav-label">Sales</span>
        <span class="tooltip">Sales</span>
    </a>
    <?php endif; ?>
    <?php if (Permissions::has('sales.create')): ?>
    <a href="/car-showroom/modules/sales/create.php"
       class="nav-item <?= isActive('sales','create.php') ?>">
        <span class="icon"><i class="fas fa-file-invoice-dollar"></i></span>
        <span class="nav-label">New Sale</span>
        <span class="tooltip">New Sale</span>
    </a>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (Permissions::has('accounts.view') || Permissions::has('expenses.view')): ?>
    <div class="nav-section-title">Finance</div>
    <?php if (Permissions::has('accounts.view')): ?>
    <a href="/car-showroom/modules/accounts/index.php"
       class="nav-item <?= isActive('accounts') ?>">
        <span class="icon"><i class="fas fa-wallet"></i></span>
        <span class="nav-label">Accounts</span>
        <span class="tooltip">Accounts</span>
    </a>
    <?php endif; ?>
    <?php if (Permissions::has('expenses.view')): ?>
    <a href="/car-showroom/modules/expenses/index.php"
       class="nav-item <?= isActive('expenses') ?>">
        <span class="icon"><i class="fas fa-receipt"></i></span>
        <span class="nav-label">Expenses</span>
        <span class="tooltip">Expenses</span>
    </a>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (Permissions::has('reports.view')): ?>
    <div class="nav-section-title">Reports</div>
    <a href="/car-showroom/modules/reports/index.php"
       class="nav-item <?= isActive('reports') ?>">
        <span class="icon"><i class="fas fa-chart-line"></i></span>
        <span class="nav-label">Reports</span>
        <span class="tooltip">Reports</span>
    </a>
    <?php endif; ?>

    <?php if (Permissions::has('users.manage') || Permissions::has('settings.manage')): ?>
    <div class="nav-section-title">Admin</div>
    <?php if (Permissions::has('users.manage')): ?>
    <a href="/car-showroom/modules/users/index.php"
       class="nav-item <?= isActive('users') ?>">
        <span class="icon"><i class="fas fa-users"></i></span>
        <span class="nav-label">Users</span>
        <span class="tooltip">Users</span>
    </a>
    <?php endif; ?>
    <?php if (Permissions::has('settings.manage')): ?>
    <a href="/car-showroom/modules/settings/index.php"
       class="nav-item <?= isActive('settings','index.php') ?>">
        <span class="icon"><i class="fas fa-gear"></i></span>
        <span class="nav-label">Settings</span>
        <span class="tooltip">Settings</span>
    </a>
    <a href="/car-showroom/modules/settings/permissions.php"
       class="nav-item <?= isActive('settings','permissions.php') ?>">
        <span class="icon"><i class="fas fa-shield-halved"></i></span>
        <span class="nav-label">Permissions</span>
        <span class="tooltip">Permissions</span>
    </a>
    <?php endif; ?>
    <?php if (Permissions::has('inventory.view')): ?>
    <a href="/car-showroom/modules/inventory/custom-fields.php"
       class="nav-item <?= isActive('inventory','custom-fields.php') ?>">
        <span class="icon"><i class="fas fa-sliders"></i></span>
        <span class="nav-label">Custom Fields</span>
        <span class="tooltip">Custom Fields</span>
    </a>
    <?php endif; ?>
    <?php endif; ?>

</nav>

    <!-- Collapse toggle button — bottom of nav, above footer -->
    <div class="sidebar-toggle-wrap">
        <button class="sidebar-toggle-btn" id="sidebarToggle" title="Toggle Sidebar">
            <span class="toggle-arrow">
                <!-- Left chevron SVG -->
                <svg id="toggleSvg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </span>
            <span class="toggle-label">Collapse</span>
        </button>
    </div>

    <!-- Footer -->
    <div class="sidebar-footer">
        <div class="footer-inner">
            <div class="footer-avatar-wrap">
                <div class="footer-avatar">
                    <span style="color:#fff;font-size:0.875rem;font-weight:700">
                        <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
                    </span>
                </div>
                <div class="footer-tooltip">
                    <span style="color:#e2e8f0;font-weight:700;font-size:0.85rem">
                        <?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>
                    </span>
                    <span style="color:#64748b;font-size:0.72rem">
                        <?= htmlspecialchars($_SESSION['user_role'] ?? '') ?>
                    </span>
                    <a href="/car-showroom/core/logout.php"
                       style="pointer-events:auto;color:#f87171;font-size:0.75rem;
                              text-decoration:none;margin-top:4px;display:flex;align-items:center;gap:5px">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        Logout
                    </a>
                </div>
            </div>
            <div class="footer-info">
                <div style="color:#e2e8f0;font-size:0.85rem;font-weight:600;
                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    <?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>
                </div>
                <div style="color:#475569;font-size:0.72rem">
                    <?= htmlspecialchars($_SESSION['user_role'] ?? '') ?>
                </div>
            </div>
            <a href="/car-showroom/core/logout.php"
                style="pointer-events:auto;color:#f87171;font-size:0.75rem;
                        text-decoration:none;margin-top:4px;display:flex;align-items:center;gap:5px">
             <i class="fas fa-right-from-bracket" style="font-size:0.7rem"></i>
                Logout
            </a>        
        </div>
    </div>

</aside>

<script>
(function () {
    const STORAGE_KEY = 'sidebar_collapsed';
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const toggleSvg = document.getElementById('toggleSvg');
    const toggleLabel = sidebar.querySelector('.toggle-label');

    function getMain() { return document.querySelector('.main'); }

    function applyState(collapsed, animate) {
        if (!animate) sidebar.style.transition = 'none';
        sidebar.classList.toggle('collapsed', collapsed);
        const main = getMain();
        if (main) main.classList.toggle('sidebar-collapsed', collapsed);
        // Flip arrow
        toggleSvg.style.transform = collapsed ? 'rotate(180deg)' : 'rotate(0deg)';
        if (toggleLabel) toggleLabel.style.opacity = collapsed ? '0' : '1';
        if (!animate) {
            requestAnimationFrame(() => { sidebar.style.transition = ''; });
        }
    }

    // Apply saved state (no animation on load)
    const saved = localStorage.getItem(STORAGE_KEY) === 'true';
    applyState(saved, false);

    toggleBtn.addEventListener('click', function () {
        const isNow = sidebar.classList.toggle('collapsed');
        const main  = getMain();
        if (main) main.classList.toggle('sidebar-collapsed', isNow);
        toggleSvg.style.transform = isNow ? 'rotate(180deg)' : 'rotate(0deg)';
        if (toggleLabel) toggleLabel.style.opacity = isNow ? '0' : '1';
        localStorage.setItem(STORAGE_KEY, isNow);
    });

    // Tooltip positioning
    document.querySelectorAll('.nav-item').forEach(function(item) {
        const tooltip = item.querySelector('.tooltip');
        if (!tooltip) return;
        item.addEventListener('mouseenter', function() {
            if (!sidebar.classList.contains('collapsed')) return;
            const rect = item.getBoundingClientRect();
            tooltip.style.top       = (rect.top + rect.height / 2) + 'px';
            tooltip.style.left      = (rect.right + 10) + 'px';
            tooltip.style.transform = 'translateY(-50%)';
            tooltip.style.opacity   = '1';
        });
        item.addEventListener('mouseleave', function() {
            tooltip.style.opacity = '0';
        });
    });

    // Mobile close on outside click
    document.addEventListener('click', function (e) {
        if (window.innerWidth <= 768 &&
            !sidebar.contains(e.target) &&
            sidebar.classList.contains('mobile-open')) {
            sidebar.classList.remove('mobile-open');
        }
    });
})();
</script>