<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();

$db = Database::getInstance();

// Filters
$where  = [];
$params = [];
$types  = '';

if (!empty($_GET['status'])) {
    $where[]  = "c.status = ?";
    $params[] = clean($_GET['status']);
    $types   .= 's';
}
if (!empty($_GET['make'])) {
    $where[]  = "c.make = ?";
    $params[] = clean($_GET['make']);
    $types   .= 's';
}
if (!empty($_GET['year'])) {
    $where[]  = "c.year = ?";
    $params[] = (int)$_GET['year'];
    $types   .= 'i';
}
if (!empty($_GET['search'])) {
    $s        = '%' . clean($_GET['search']) . '%';
    $where[]  = "(c.make LIKE ? OR c.model LIKE ? OR c.chassis_no LIKE ? OR c.variant LIKE ?)";
    $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
    $types   .= 'ssss';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Pagination
$perPage     = 12;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $perPage;
$totalRows   = $db->fetchOne("SELECT COUNT(*) as cnt FROM cars c $whereSQL", $params, $types)['cnt'] ?? 0;
$totalPages  = ceil($totalRows / $perPage);

// Fetch cars with primary image
$cars = $db->fetchAll(
    "SELECT c.*,
        (SELECT image_path FROM car_images WHERE car_id = c.id AND is_primary = 1 LIMIT 1) as primary_image,
        (SELECT SUM(amount) FROM car_costs WHERE car_id = c.id) as extra_costs
     FROM cars c
     $whereSQL
     ORDER BY c.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params, $types
);

// Makes for filter dropdown
$makes = $db->fetchAll("SELECT DISTINCT make FROM cars ORDER BY make");

// Stats
$stats = [
    'all'       => $db->fetchOne("SELECT COUNT(*) as cnt FROM cars")['cnt'] ?? 0,
    'available' => $db->fetchOne("SELECT COUNT(*) as cnt FROM cars WHERE status='available'")['cnt'] ?? 0,
    'reserved'  => $db->fetchOne("SELECT COUNT(*) as cnt FROM cars WHERE status='reserved'")['cnt'] ?? 0,
    'sold'      => $db->fetchOne("SELECT COUNT(*) as cnt FROM cars WHERE status='sold'")['cnt'] ?? 0,
];

$flash     = getFlash();
$pageTitle = 'Cars Inventory';
$pageSub   = $totalRows . ' vehicles in database';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory — AutoManager Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/car-showroom/public/css/fa/all.min.css">
</head>
<body>
<?php require_once '../../views/layouts/sidebar.php'; ?>

<div class="main">
<?php require_once '../../views/layouts/topbar.php'; ?>

<div class="content-area">

    <!-- Flash -->
    <?php if ($flash): ?>
    <div class="mb-5 px-4 py-3 rounded-xl text-sm flex items-center gap-2
        <?= $flash['type']==='success' ? 'bg-green-500/10 border border-green-500/20 text-green-400' : 'bg-red-500/10 border border-red-500/20 text-red-400' ?>">
        <i class="fas <?= $flash['type']==='success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- Status tabs -->
    <div class="flex flex-wrap gap-2 mb-5">
        <?php
        $tabs = [
            ''          => ['label'=>'All Cars',  'count'=>$stats['all'],       'color'=>'blue'],
            'available' => ['label'=>'Available', 'count'=>$stats['available'], 'color'=>'green'],
            'reserved'  => ['label'=>'Reserved',  'count'=>$stats['reserved'],  'color'=>'amber'],
            'sold'      => ['label'=>'Sold',       'count'=>$stats['sold'],      'color'=>'red'],
        ];
        $colors = [
            'blue'  => 'bg-blue-500/10 border-blue-500/30 text-blue-400',
            'green' => 'bg-green-500/10 border-green-500/30 text-green-400',
            'amber' => 'bg-amber-500/10 border-amber-500/30 text-amber-400',
            'red'   => 'bg-red-500/10 border-red-500/30 text-red-400',
        ];
        $activeStatus = $_GET['status'] ?? '';
        foreach ($tabs as $val => $tab):
            $isActive = ($activeStatus === $val);
            $params2  = array_merge($_GET, ['status'=>$val, 'page'=>1]);
            if ($val === '') unset($params2['status']);
            $url = '?' . http_build_query($params2);
        ?>
        <a href="<?= $url ?>"
           class="px-4 py-2 rounded-xl text-xs font-700 border transition-all flex items-center gap-2
           <?= $isActive ? $colors[$tab['color']] : 'bg-slate-800/50 border-slate-700/50 text-slate-400 hover:text-slate-200' ?>">
            <?= $tab['label'] ?>
            <span class="<?= $isActive ? '' : 'bg-slate-700 text-slate-400' ?> text-xs px-2 py-0.5 rounded-full font-bold">
                <?= $tab['count'] ?>
            </span>
        </a>
        <?php endforeach; ?>

        <!-- Add Car button -->
        <a href="/car-showroom/modules/inventory/add.php"
           class="ml-auto px-4 py-2 rounded-xl text-xs font-700 bg-blue-600 hover:bg-blue-700 text-white transition-all flex items-center gap-2">
            <i class="fas fa-plus"></i> Add New Car
        </a>
    </div>

    <!-- Filters -->
    <form method="GET" class="flex flex-wrap gap-3 mb-6 p-4 rounded-xl"
          style="background:#0d1526;border:1px solid rgba(148,163,184,0.08)">
        <input type="hidden" name="status" value="<?= htmlspecialchars($activeStatus) ?>">

        <input type="text" name="search" placeholder="Search make, model, chassis..."
               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
               class="flex-1 min-w-[200px] px-4 py-2 rounded-xl text-sm text-slate-200 placeholder-slate-600"
               style="background:rgba(30,41,59,0.8);border:1.5px solid rgba(148,163,184,0.12)">

        <select name="make" class="px-4 py-2 rounded-xl text-sm text-slate-300"
                style="background:rgba(30,41,59,0.8);border:1.5px solid rgba(148,163,184,0.12)">
            <option value="">All Makes</option>
            <?php foreach ($makes as $m): ?>
            <option value="<?= htmlspecialchars($m['make']) ?>"
                <?= (($_GET['make'] ?? '') === $m['make']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($m['make']) ?>
            </option>
            <?php endforeach; ?>
        </select>

        <select name="year" class="px-4 py-2 rounded-xl text-sm text-slate-300"
                style="background:rgba(30,41,59,0.8);border:1.5px solid rgba(148,163,184,0.12)">
            <option value="">All Years</option>
            <?php for ($y = date('Y'); $y >= 1990; $y--): ?>
            <option value="<?= $y ?>" <?= (($_GET['year'] ?? '') == $y) ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>

        <button type="submit"
                class="px-4 py-2 rounded-xl text-xs font-700 bg-blue-600 hover:bg-blue-700 text-white transition-all">
            <i class="fas fa-search mr-1"></i> Filter
        </button>
        <a href="/car-showroom/modules/inventory/index.php"
           class="px-4 py-2 rounded-xl text-xs font-700 text-slate-400 hover:text-white transition-all"
           style="background:rgba(30,41,59,0.5);border:1px solid rgba(148,163,184,0.1)">
            <i class="fas fa-xmark mr-1"></i> Clear
        </a>
    </form>

    <!-- Car Grid -->
    <?php if (empty($cars)): ?>
    <div class="text-center py-20 text-slate-600">
        <i class="fas fa-car text-5xl mb-4 block opacity-30"></i>
        <p class="text-lg font-600 text-slate-500">No cars found</p>
        <p class="text-sm mt-1">Try adjusting your filters or add a new car</p>
        <a href="/car-showroom/modules/inventory/add.php"
           class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-600 rounded-xl transition-all">
            <i class="fas fa-plus"></i> Add First Car
        </a>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-6">
        <?php foreach ($cars as $car):
            $extraCosts = $car['extra_costs'] ?? 0;
            $profit     = $car['sale_price'] - $car['purchase_price'] - $extraCosts;
            $imgSrc     = $car['primary_image']
                            ? UPLOAD_URL . $car['primary_image']
                            : '/car-showroom/public/assets/no-car.png';
        ?>
        <div class="rounded-2xl overflow-hidden transition-all duration-200 hover:-translate-y-1 hover:shadow-2xl"
             style="background:#0d1526;border:1px solid rgba(148,163,184,0.08)">

            <!-- Image -->
            <div class="relative h-44 overflow-hidden bg-slate-800">
                <img src="<?= htmlspecialchars($imgSrc) ?>"
                     alt="<?= htmlspecialchars($car['make'].' '.$car['model']) ?>"
                     class="w-full h-full object-cover"
                     onerror="this.src='/car-showroom/public/assets/no-car.png'">

                <!-- Status badge -->
                <span class="absolute top-3 left-3 text-xs font-700 px-2.5 py-1 rounded-lg uppercase tracking-wide
                    <?= $car['status']==='available' ? 'bg-green-500/90 text-white' :
                       ($car['status']==='reserved'  ? 'bg-amber-500/90 text-white' :
                                                        'bg-red-500/90 text-white') ?>">
                    <?= ucfirst($car['status']) ?>
                </span>

                <!-- Assembly badge -->
                <span class="absolute top-3 right-3 text-xs font-600 px-2 py-0.5 rounded-lg"
                      style="background:rgba(0,0,0,0.6);color:#94a3b8">
                    <?= ucfirst($car['assembly']) ?>
                </span>
            </div>

            <!-- Info -->
            <div class="p-4">
                <h3 class="text-white font-700 text-sm leading-tight mb-1">
                    <?= htmlspecialchars($car['year'].' '.$car['make'].' '.$car['model']) ?>
                </h3>
                <p class="text-slate-500 text-xs mb-3">
                    <?= htmlspecialchars($car['variant'] ?? '') ?>
                    <?= $car['transmission'] ? '· '.ucfirst($car['transmission']) : '' ?>
                    <?= $car['fuel_type'] ? '· '.ucfirst($car['fuel_type']) : '' ?>
                </p>

                <!-- Price row -->
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="text-blue-400 font-800 text-base"><?= formatPrice($car['sale_price']) ?></div>
                        <?php if ($car['is_negotiable']): ?>
                        <div class="text-slate-600 text-xs">Negotiable</div>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-slate-600">Profit</div>
                        <div class="text-xs font-700 <?= $profit >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                            <?= formatPrice($profit) ?>
                        </div>
                    </div>
                </div>

                <!-- Meta row -->
                <div class="flex items-center gap-3 text-xs text-slate-600 mb-4 border-t pt-3"
                     style="border-color:rgba(148,163,184,0.07)">
                    <span><i class="fas fa-gauge-high mr-1"></i><?= number_format($car['mileage']) ?> km</span>
                    <span><i class="fas fa-palette mr-1"></i><?= htmlspecialchars($car['color'] ?? '—') ?></span>
                    <span><i class="fas fa-user mr-1"></i><?= $car['ownership'] ?></span>
                </div>

                <!-- Actions -->
                <div class="flex gap-2">
    <a href="/car-showroom/modules/inventory/view.php?id=<?= $car['id'] ?>"
       class="flex-1 text-center py-2 rounded-xl text-xs font-600 transition-all
              bg-slate-500/10 hover:bg-slate-500/20 text-slate-400 border border-slate-500/20">
        <i class="fas fa-eye mr-1"></i> View
    </a>
    <a href="/car-showroom/modules/inventory/edit.php?id=<?= $car['id'] ?>"
                       class="flex-1 text-center py-2 rounded-xl text-xs font-600 transition-all
                              bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/20">
                        <i class="fas fa-pen mr-1"></i> Edit
                    </a>
                    <button onclick="confirmDelete(<?= $car['id'] ?>, '<?= htmlspecialchars($car['year'].' '.$car['make'].' '.$car['model'], ENT_QUOTES) ?>')"
                            class="px-3 py-2 rounded-xl text-xs font-600 transition-all
                                   bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-center gap-2 mt-4">
        <?php for ($i = 1; $i <= $totalPages; $i++):
            $pParams = array_merge($_GET, ['page' => $i]);
            $pUrl    = '?' . http_build_query($pParams);
        ?>
        <a href="<?= $pUrl ?>"
           class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-600 transition-all
           <?= $i === $page ? 'bg-blue-600 text-white' : 'text-slate-400 hover:text-white' ?>"
           style="<?= $i !== $page ? 'background:rgba(30,41,59,0.5);border:1px solid rgba(148,163,184,0.1)' : '' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
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
        <h3 class="text-white text-center font-700 text-base mb-2">Delete Car?</h3>
        <p class="text-slate-400 text-center text-sm mb-6" id="deleteCarName"></p>
        <div class="flex gap-3">
            <button onclick="closeDelete()"
                    class="flex-1 py-2.5 rounded-xl text-sm font-600 text-slate-400 transition-all"
                    style="background:rgba(30,41,59,0.8);border:1px solid rgba(148,163,184,0.1)">
                Cancel
            </button>
            <a id="deleteConfirmBtn" href="#"
               class="flex-1 py-2.5 rounded-xl text-sm font-600 text-white text-center transition-all bg-red-600 hover:bg-red-700">
                Delete
            </a>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    document.getElementById('deleteCarName').textContent = name;
    document.getElementById('deleteConfirmBtn').href = '/car-showroom/modules/inventory/delete.php?id=' + id;
    const modal = document.getElementById('deleteModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}
function closeDelete() {
    const modal = document.getElementById('deleteModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
</script>
</body>
</html>