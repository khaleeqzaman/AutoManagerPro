<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('inventory.view');

Auth::check();

$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    setFlash('error', 'Invalid car ID.');
    redirect('modules/inventory/index.php');
}

$car = $db->fetchOne("SELECT * FROM cars WHERE id = ?", [$id], 'i');
if (!$car) {
    setFlash('error', 'Car not found.');
    redirect('modules/inventory/index.php');
}

// Images
$images = $db->fetchAll(
    "SELECT * FROM car_images WHERE car_id = ? ORDER BY is_primary DESC, sort_order ASC",
    [$id], 'i'
);

// Costs
$costs = $db->fetchAll(
    "SELECT * FROM car_costs WHERE car_id = ? ORDER BY created_at ASC",
    [$id], 'i'
);
$totalCosts = array_sum(array_column($costs, 'amount'));

// Timeline
$timeline = $db->fetchAll(
    "SELECT t.*, u.full_name FROM car_timeline t
     LEFT JOIN users u ON t.done_by = u.id
     WHERE t.car_id = ? ORDER BY t.event_date DESC",
    [$id], 'i'
);

// Price history
$priceHistory = $db->fetchAll(
    "SELECT ph.*, u.full_name FROM car_price_history ph
     LEFT JOIN users u ON ph.changed_by = u.id
     WHERE ph.car_id = ? ORDER BY ph.changed_at DESC",
    [$id], 'i'
);

// Profit calculation
$netProfit = $car['sale_price'] - $car['purchase_price'] - $totalCosts;

$pageTitle = $car['year'] . ' ' . $car['make'] . ' ' . $car['model'];
$pageSub   = $car['variant'] ?? 'Vehicle Detail';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — AutoManager Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/car-showroom/public/css/fa/all.min.css">
    <style>
        .detail-card {
            background: #0d1526;
            border: 1px solid rgba(148,163,184,0.08);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .detail-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(148,163,184,0.07);
            display: flex; align-items: center; gap: 10px;
        }
        .detail-card-header .icon-box {
            width: 32px; height: 32px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem;
        }
        .detail-card-title { font-size: 0.875rem; font-weight: 700; color: #e2e8f0; }
        .detail-card-body  { padding: 20px; }

        /* Gallery */
        .gallery-main {
            width: 100%; aspect-ratio: 16/9;
            background: #0a0f1e; border-radius: 12px;
            overflow: hidden; position: relative;
        }
        .gallery-main img {
            width: 100%; height: 100%; object-fit: cover;
            transition: opacity 0.3s ease;
        }
        .gallery-thumbs {
            display: flex; gap: 8px; margin-top: 10px;
            overflow-x: auto; padding-bottom: 4px;
        }
        .gallery-thumbs::-webkit-scrollbar { height: 3px; }
        .gallery-thumbs::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 10px; }
        .gallery-thumb {
            flex-shrink: 0; width: 72px; height: 54px;
            border-radius: 8px; overflow: hidden;
            border: 2px solid transparent;
            cursor: pointer; transition: border-color 0.2s;
        }
        .gallery-thumb.active { border-color: #3b82f6; }
        .gallery-thumb img { width: 100%; height: 100%; object-fit: cover; }

        /* Status badge */
        .status-badge {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 0.75rem; font-weight: 700;
            padding: 5px 12px; border-radius: 20px;
            text-transform: uppercase; letter-spacing: 0.06em;
        }
        .status-available { background: rgba(22,163,74,0.15);  color: #4ade80; border: 1px solid rgba(22,163,74,0.2); }
        .status-reserved  { background: rgba(217,119,6,0.15);  color: #fbbf24; border: 1px solid rgba(217,119,6,0.2); }
        .status-sold      { background: rgba(220,38,38,0.15);  color: #f87171; border: 1px solid rgba(220,38,38,0.2); }

        /* Spec grid */
        .spec-item {
            padding: 12px 0;
            border-bottom: 1px solid rgba(148,163,184,0.06);
            display: flex; align-items: center; justify-content: space-between;
        }
        .spec-item:last-child { border-bottom: none; }
        .spec-label { font-size: 0.78rem; color: #475569; font-weight: 500; }
        .spec-value { font-size: 0.85rem; color: #e2e8f0; font-weight: 600; text-align: right; }

        /* Feature chips */
        .feature-chip {
            display: inline-flex; align-items: center; gap-6px;
            padding: 6px 12px; border-radius: 8px;
            font-size: 0.75rem; font-weight: 600;
        }
        .chip-yes { background: rgba(22,163,74,0.12);  color: #4ade80; border: 1px solid rgba(22,163,74,0.2); }
        .chip-no  { background: rgba(30,41,59,0.5);    color: #334155; border: 1px solid rgba(148,163,184,0.06); }

        /* Profit box */
        .profit-box {
            border-radius: 14px; padding: 20px;
            display: flex; align-items: center; gap-16px;
        }
        .profit-positive { background: rgba(22,163,74,0.08);  border: 1px solid rgba(22,163,74,0.2); }
        .profit-negative { background: rgba(220,38,38,0.08);  border: 1px solid rgba(220,38,38,0.2); }

        /* Timeline */
        .timeline-item {
            display: flex; gap: 14px;
            padding-bottom: 20px; position: relative;
        }
        .timeline-item:not(:last-child)::before {
            content: '';
            position: absolute; left: 15px; top: 32px; bottom: 0;
            width: 1px; background: rgba(148,163,184,0.08);
        }
        .timeline-dot {
            width: 32px; height: 32px; border-radius: 50%;
            flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem;
        }
        .timeline-content { flex: 1; padding-top: 4px; }
        .timeline-event  { font-size: 0.85rem; color: #e2e8f0; font-weight: 600; }
        .timeline-meta   { font-size: 0.75rem; color: #475569; margin-top: 2px; }

        /* Action buttons */
        .action-btn {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 12px 16px; border-radius: 12px;
            font-size: 0.85rem; font-weight: 700;
            text-decoration: none; transition: all 0.2s; border: none; cursor: pointer;
            width: 100%;
        }
        .btn-edit    { background: rgba(37,99,235,0.12); color: #60a5fa; border: 1px solid rgba(37,99,235,0.25); }
        .btn-edit:hover { background: rgba(37,99,235,0.2); }
        .btn-delete  { background: rgba(220,38,38,0.1); color: #f87171; border: 1px solid rgba(220,38,38,0.2); }
        .btn-delete:hover { background: rgba(220,38,38,0.2); }
        .btn-whatsapp{ background: rgba(37,211,102,0.12); color: #25d366; border: 1px solid rgba(37,211,102,0.25); }
        .btn-whatsapp:hover { background: rgba(37,211,102,0.2); }
        .btn-sale    { background: linear-gradient(135deg,#2563eb,#1d4ed8); color: #fff; box-shadow: 0 4px 16px rgba(37,99,235,0.3); }
        .btn-sale:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(37,99,235,0.4); }
    </style>
</head>
<body>
<?php require_once '../../views/layouts/sidebar.php'; ?>
<div class="main">
<?php require_once '../../views/layouts/topbar.php'; ?>
<div class="content-area">

    <!-- Back + Actions bar -->
    <div class="flex items-center justify-between mb-5">
        <a href="/car-showroom/modules/inventory/index.php"
           class="flex items-center gap-2 text-slate-400 hover:text-white text-sm font-600 transition-colors">
            <i class="fas fa-arrow-left"></i> Back to Inventory
        </a>
        <div class="flex items-center gap-2">
            <span class="status-badge status-<?= $car['status'] ?>">
                <i class="fas fa-circle text-[8px]"></i>
                <?= ucfirst($car['status']) ?>
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        <!-- ═══════════ LEFT COLUMN ═══════════ -->
        <div class="xl:col-span-2">

            <!-- Gallery -->
            <div class="detail-card">
                <div class="detail-card-body">
                    <?php if (!empty($images)): ?>
                    <div class="gallery-main">
                        <img src="<?= UPLOAD_URL . htmlspecialchars($images[0]['image_path']) ?>"
                             id="mainImage" alt="car">
                    </div>
                    <?php if (count($images) > 1): ?>
                    <div class="gallery-thumbs">
                        <?php foreach ($images as $i => $img): ?>
                        <div class="gallery-thumb <?= $i===0?'active':'' ?>"
                             onclick="setMainImage('<?= UPLOAD_URL . htmlspecialchars($img['image_path']) ?>', this)">
                            <img src="<?= UPLOAD_URL . htmlspecialchars($img['image_path']) ?>" alt="">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="gallery-main flex items-center justify-center">
                        <div class="text-center text-slate-600">
                            <i class="fas fa-car text-5xl mb-3 block opacity-20"></i>
                            <p class="text-sm">No images uploaded</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Title + Price -->
            <div class="detail-card">
                <div class="detail-card-body">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h1 class="text-white text-xl font-800 leading-tight">
                                <?= htmlspecialchars($car['year'].' '.$car['make'].' '.$car['model']) ?>
                            </h1>
                            <p class="text-slate-400 text-sm mt-1">
                                <?= htmlspecialchars($car['variant'] ?? '') ?>
                                <?= $car['transmission'] ? ' · '.ucfirst($car['transmission']) : '' ?>
                                <?= $car['fuel_type'] ? ' · '.ucfirst($car['fuel_type']) : '' ?>
                                <?= $car['engine_capacity'] ? ' · '.$car['engine_capacity'].'cc' : '' ?>
                            </p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="text-blue-400 text-2xl font-800"><?= formatPrice($car['sale_price']) ?></div>
                            <?php if ($car['is_negotiable']): ?>
                            <div class="text-slate-500 text-xs mt-1">Negotiable</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick specs row -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5">
                        <?php
                        $quickSpecs = [
                            ['icon'=>'fa-gauge-high','label'=>'Mileage','value'=>number_format($car['mileage']).' km'],
                            ['icon'=>'fa-calendar',  'label'=>'Year',   'value'=>$car['year']],
                            ['icon'=>'fa-user',       'label'=>'Owner',  'value'=>$car['ownership'].' Owner'],
                            ['icon'=>'fa-map-marker', 'label'=>'City',   'value'=>$car['city_registered'] ?: '—'],
                        ];
                        foreach ($quickSpecs as $qs):
                        ?>
                        <div class="p-3 rounded-xl text-center"
                             style="background:rgba(30,41,59,0.5);border:1px solid rgba(148,163,184,0.07)">
                            <i class="fas <?= $qs['icon'] ?> text-blue-400 mb-1 block"></i>
                            <div class="text-white text-sm font-700"><?= htmlspecialchars($qs['value']) ?></div>
                            <div class="text-slate-600 text-xs"><?= $qs['label'] ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Specifications -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="icon-box" style="background:rgba(37,99,235,0.15)">
                        <i class="fas fa-list text-blue-400"></i>
                    </div>
                    <span class="detail-card-title">Full Specifications</span>
                </div>
                <div class="detail-card-body">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8">
                        <?php
                        $specs = [
                            'Make'              => $car['make'],
                            'Model'             => $car['model'],
                            'Variant'           => $car['variant'] ?: '—',
                            'Year'              => $car['year'],
                            'Reg. Year'         => $car['registration_year'] ?: '—',
                            'Color'             => $car['color'] ?: '—',
                            'Body Type'         => $car['body_type'] ? ucfirst($car['body_type']) : '—',
                            'Assembly'          => ucfirst($car['assembly']),
                            'Fuel Type'         => ucfirst($car['fuel_type']),
                            'Transmission'      => ucfirst($car['transmission']),
                            'Engine'            => $car['engine_capacity'] ? $car['engine_capacity'].'cc' : '—',
                            'Mileage'           => number_format($car['mileage']).' km',
                            'Ownership'         => $car['ownership'].' Owner',
                            'Condition'         => ucfirst(str_replace('_',' ',$car['condition_rating'])),
                            'City Registered'   => $car['city_registered'] ?: '—',
                            'Buying Source'     => ucfirst(str_replace('_',' ',$car['buying_source'])),
                        ];
                        foreach ($specs as $label => $value):
                        ?>
                        <div class="spec-item">
                            <span class="spec-label"><?= $label ?></span>
                            <span class="spec-value"><?= htmlspecialchars($value) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Features -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="icon-box" style="background:rgba(245,158,11,0.15)">
                        <i class="fas fa-star text-amber-400"></i>
                    </div>
                    <span class="detail-card-title">Features & Extras</span>
                </div>
                <div class="detail-card-body">
                    <div class="flex flex-wrap gap-2">
                        <?php
                        $features = [
                            'abs'=>'ABS','airbags'=>'Airbags','sunroof'=>'Sunroof',
                            'alloy_rims'=>'Alloy Rims','navigation'=>'Navigation',
                            'climate_control'=>'Climate Control','keyless_entry'=>'Keyless Entry',
                            'push_start'=>'Push Start','cruise_control'=>'Cruise Control',
                            'parking_sensors'=>'Parking Sensors','reverse_camera'=>'Reverse Camera',
                        ];
                        foreach ($features as $key => $label):
                            $has = (bool)$car[$key];
                        ?>
                        <span class="feature-chip <?= $has ? 'chip-yes' : 'chip-no' ?>">
                            <i class="fas <?= $has ? 'fa-check' : 'fa-xmark' ?> mr-1"></i>
                            <?= $label ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Documentation -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="icon-box" style="background:rgba(14,165,233,0.15)">
                        <i class="fas fa-file-alt text-sky-400"></i>
                    </div>
                    <span class="detail-card-title">Documentation</span>
                </div>
                <div class="detail-card-body">
                    <div class="flex flex-wrap gap-2">
                        <?php
                        $docs = [
                            'has_original_book' => 'Original Book',
                            'has_original_file' => 'Original File',
                            'has_smart_card'    => 'Smart Card',
                            'token_paid'        => 'Token Paid',
                            'tracker_installed' => 'Tracker Installed',
                        ];
                        foreach ($docs as $key => $label):
                            $has = (bool)$car[$key];
                        ?>
                        <span class="feature-chip <?= $has ? 'chip-yes' : 'chip-no' ?>">
                            <i class="fas <?= $has ? 'fa-check' : 'fa-xmark' ?> mr-1"></i>
                            <?= $label ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <?php if (!empty($timeline)): ?>
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="icon-box" style="background:rgba(99,102,241,0.15)">
                        <i class="fas fa-clock-rotate-left text-indigo-400"></i>
                    </div>
                    <span class="detail-card-title">Vehicle Timeline</span>
                </div>
                <div class="detail-card-body">
                    <?php
                    $timelineIcons = [
                        'added'         => ['icon'=>'fa-plus',        'bg'=>'rgba(37,99,235,0.2)',  'color'=>'#60a5fa'],
                        'updated'       => ['icon'=>'fa-pen',         'bg'=>'rgba(245,158,11,0.2)', 'color'=>'#fbbf24'],
                        'price_changed' => ['icon'=>'fa-tag',         'bg'=>'rgba(139,92,246,0.2)', 'color'=>'#a78bfa'],
                        'reserved'      => ['icon'=>'fa-clock',       'bg'=>'rgba(217,119,6,0.2)',  'color'=>'#f59e0b'],
                        'sold'          => ['icon'=>'fa-handshake',   'bg'=>'rgba(22,163,74,0.2)',  'color'=>'#4ade80'],
                        'cost_added'    => ['icon'=>'fa-receipt',     'bg'=>'rgba(239,68,68,0.2)',  'color'=>'#f87171'],
                    ];
                    foreach ($timeline as $event):
                        $ti = $timelineIcons[$event['event_type']] ?? ['icon'=>'fa-circle','bg'=>'rgba(148,163,184,0.15)','color'=>'#64748b'];
                    ?>
                    <div class="timeline-item">
                        <div class="timeline-dot" style="background:<?= $ti['bg'] ?>">
                            <i class="fas <?= $ti['icon'] ?>" style="color:<?= $ti['color'] ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-event"><?= htmlspecialchars($event['description']) ?></div>
                            <div class="timeline-meta">
                                <?= date('d M Y, h:i A', strtotime($event['event_date'])) ?>
                                <?= $event['full_name'] ? ' · by '.$event['full_name'] : '' ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Price History -->
            <?php if (!empty($priceHistory)): ?>
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="icon-box" style="background:rgba(139,92,246,0.15)">
                        <i class="fas fa-chart-line text-purple-400"></i>
                    </div>
                    <span class="detail-card-title">Price History</span>
                </div>
                <div class="detail-card-body">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr>
                                <th style="text-align:left;font-size:0.7rem;color:#475569;padding:8px 0;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;border-bottom:1px solid rgba(148,163,184,0.08)">Date</th>
                                <th style="text-align:left;font-size:0.7rem;color:#475569;padding:8px 0;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;border-bottom:1px solid rgba(148,163,184,0.08)">Old Price</th>
                                <th style="text-align:left;font-size:0.7rem;color:#475569;padding:8px 0;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;border-bottom:1px solid rgba(148,163,184,0.08)">New Price</th>
                                <th style="text-align:left;font-size:0.7rem;color:#475569;padding:8px 0;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;border-bottom:1px solid rgba(148,163,184,0.08)">Changed By</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($priceHistory as $ph): ?>
                        <tr>
                            <td style="padding:10px 0;font-size:0.82rem;color:#64748b;border-bottom:1px solid rgba(148,163,184,0.05)">
                                <?= date('d M Y', strtotime($ph['changed_at'])) ?>
                            </td>
                            <td style="padding:10px 0;font-size:0.82rem;color:#f87171;font-weight:600;border-bottom:1px solid rgba(148,163,184,0.05)">
                                <?= formatPrice($ph['old_price']) ?>
                            </td>
                            <td style="padding:10px 0;font-size:0.82rem;color:#4ade80;font-weight:600;border-bottom:1px solid rgba(148,163,184,0.05)">
                                <?= formatPrice($ph['new_price']) ?>
                            </td>
                            <td style="padding:10px 0;font-size:0.82rem;color:#94a3b8;border-bottom:1px solid rgba(148,163,184,0.05)">
                                <?= htmlspecialchars($ph['full_name'] ?? '—') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- ═══════════ RIGHT COLUMN ═══════════ -->
        <div class="xl:col-span-1">

            <!-- Action Buttons -->
            <div class="detail-card mb-5">
                <div class="detail-card-body">
                    <div class="flex flex-col gap-3">

                        <?php if ($car['status'] !== 'sold'): ?>
                        <a href="/car-showroom/modules/sales/create.php?car_id=<?= $car['id'] ?>"
                           class="action-btn btn-sale">
                            <i class="fas fa-handshake"></i> Create Sale
                        </a>
                        <?php endif; ?>

                        <a href="/car-showroom/modules/inventory/edit.php?id=<?= $car['id'] ?>"
                           class="action-btn btn-edit">
                            <i class="fas fa-pen"></i> Edit Car
                        </a>

                        <?php
                        $waPhone = getSetting('whatsapp_no', '923352151519');

                        $waText  = urlencode("Hi, I'm interested in the {$car['year']} {$car['make']} {$car['model']} priced at " . formatPrice($car['sale_price']));
                        ?>
                        <a href="https://wa.me/<?= $waPhone ?>?text=<?= $waText ?>"
                           target="_blank" class="action-btn btn-whatsapp">
                            <i class="fab fa-whatsapp"></i> WhatsApp Inquiry
                        </a>

                        <?php if (Auth::hasRole(['Admin', 'Manager'])): ?>
                        <button onclick="confirmDelete()"
                                class="action-btn btn-delete">
                            <i class="fas fa-trash"></i> Delete Car
                        </button>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <!-- Identity -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="icon-box" style="background:rgba(139,92,246,0.15)">
                        <i class="fas fa-fingerprint text-purple-400"></i>
                    </div>
                    <span class="detail-card-title">Identity</span>
                </div>
                <div class="detail-card-body">
                    <div class="spec-item">
                        <span class="spec-label">Chassis No.</span>
                        <span class="spec-value font-mono text-xs"><?= htmlspecialchars($car['chassis_no']) ?></span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-label">Engine No.</span>
                        <span class="spec-value font-mono text-xs"><?= htmlspecialchars($car['engine_no'] ?: '—') ?></span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-label">Supplier</span>
                        <span class="spec-value"><?= htmlspecialchars($car['supplier_name'] ?: '—') ?></span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-label">Added On</span>
                        <span class="spec-value"><?= date('d M Y', strtotime($car['created_at'])) ?></span>
                    </div>
                </div>
            </div>

            <!-- Profit Breakdown -->
            <?php if (Auth::hasRole(['Admin', 'Manager'])): ?>
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="icon-box" style="background:rgba(22,163,74,0.15)">
                        <i class="fas fa-coins text-green-400"></i>
                    </div>
                    <span class="detail-card-title">Profit Breakdown</span>
                </div>
                <div class="detail-card-body">
                    <div class="spec-item">
                        <span class="spec-label">Purchase Price</span>
                        <span class="spec-value text-slate-300"><?= formatPrice($car['purchase_price']) ?></span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-label">Sale Price</span>
                        <span class="spec-value text-blue-400"><?= formatPrice($car['sale_price']) ?></span>
                    </div>
                    <?php if (!empty($costs)): ?>
                    <?php foreach ($costs as $cost): ?>
                    <div class="spec-item">
                        <span class="spec-label"><?= ucfirst(str_replace('_',' ',$cost['cost_type'])) ?></span>
                        <span class="spec-value text-red-400">- <?= formatPrice($cost['amount']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div class="spec-item">
                        <span class="spec-label">Total Extra Costs</span>
                        <span class="spec-value text-red-400">- <?= formatPrice($totalCosts) ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="mt-4 profit-box <?= $netProfit >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                        <div class="flex items-center gap-3 w-full">
                            <i class="fas <?= $netProfit >= 0 ? 'fa-arrow-trend-up text-green-400' : 'fa-arrow-trend-down text-red-400' ?> text-xl"></i>
                            <div>
                                <div class="text-xs font-700 uppercase tracking-widest"
                                     style="color:<?= $netProfit >= 0 ? '#4ade80' : '#f87171' ?>">
                                    Net Profit
                                </div>
                                <div class="text-xl font-800"
                                     style="color:<?= $netProfit >= 0 ? '#4ade80' : '#f87171' ?>">
                                    <?= formatPrice($netProfit) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notes -->
            <?php if (!empty($car['notes'])): ?>
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="icon-box" style="background:rgba(20,184,166,0.15)">
                        <i class="fas fa-note-sticky text-teal-400"></i>
                    </div>
                    <span class="detail-card-title">Internal Notes</span>
                </div>
                <div class="detail-card-body">
                    <p class="text-slate-400 text-sm leading-relaxed">
                        <?= nl2br(htmlspecialchars($car['notes'])) ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>

        </div>
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
        <h3 class="text-white text-center font-700 text-base mb-2">Delete This Car?</h3>
        <p class="text-slate-400 text-center text-sm mb-6">
            <?= htmlspecialchars($car['year'].' '.$car['make'].' '.$car['model']) ?>
        </p>
        <div class="flex gap-3">
            <button onclick="closeDelete()"
                    class="flex-1 py-2.5 rounded-xl text-sm font-600 text-slate-400 transition-all"
                    style="background:rgba(30,41,59,0.8);border:1px solid rgba(148,163,184,0.1)">
                Cancel
            </button>
            <a href="/car-showroom/modules/inventory/delete.php?id=<?= $car['id'] ?>"
               class="flex-1 py-2.5 rounded-xl text-sm font-700 text-white text-center bg-red-600 hover:bg-red-700 transition-all">
                Delete
            </a>
        </div>
    </div>
</div>

<script>
// Gallery
function setMainImage(src, thumb) {
    document.getElementById('mainImage').src = src;
    document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
}

// Delete modal
function confirmDelete() {
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