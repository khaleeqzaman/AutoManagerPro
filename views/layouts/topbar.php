<?php
// views/layouts/topbar.php
$pageTitle = $pageTitle ?? 'Page';
$pageSub   = $pageSub   ?? '';
?>
<div class="topbar" id="topbar">
    <div class="flex items-center gap-3">
        <div>
            <h1 style="color:#f1f5f9;font-weight:700;font-size:1rem;line-height:1.2;margin:0">
                <?= htmlspecialchars($pageTitle) ?>
            </h1>
            <?php if ($pageSub): ?>
            <p style="color:#475569;font-size:0.72rem;margin:2px 0 0">
                <?= htmlspecialchars($pageSub) ?>
            </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <div class="hidden sm:block" style="text-align:right">
            <div style="color:#cbd5e1;font-size:0.85rem;font-weight:600">
                <?= date('l, d M Y') ?>
            </div>
            <div style="color:#334155;font-size:0.7rem">
                <?= htmlspecialchars($_SESSION['user_role'] ?? '') ?>
            </div>
        </div>
        <a href="/car-showroom/core/logout.php" class="topbar-logout">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            <span class="hidden sm:inline">Logout</span>
        </a>
    </div>
</div>

<script>
(function () {
    const STORAGE_KEY = 'sidebar_collapsed';
    const main    = document.querySelector('.main');
    const sidebar = document.getElementById('sidebar');
    if (localStorage.getItem(STORAGE_KEY) === 'true') {
        if (sidebar) sidebar.classList.add('collapsed');
        if (main)    main.classList.add('sidebar-collapsed');
    }
})();
</script>