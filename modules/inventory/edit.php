<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('inventory.edit');

Auth::check();
if (!Auth::hasRole(['Admin', 'Manager'])) {
    setFlash('error', 'You do not have permission to edit cars.');
    redirect('modules/inventory/index.php');
}

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

// Existing images
$existingImages = $db->fetchAll(
    "SELECT * FROM car_images WHERE car_id = ? ORDER BY is_primary DESC, sort_order ASC",
    [$id], 'i'
);

// Existing costs
$existingCosts = $db->fetchAll(
    "SELECT * FROM car_costs WHERE car_id = ? ORDER BY id ASC",
    [$id], 'i'
);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request. Please try again.';
    } else {

        // Required fields
        $chassis_no     = clean($_POST['chassis_no'] ?? '');
        $make           = clean($_POST['make'] ?? '');
        $model          = clean($_POST['model'] ?? '');
        $year           = (int)($_POST['year'] ?? 0);
        $purchase_price = (float)($_POST['purchase_price'] ?? 0);
        $sale_price     = (float)($_POST['sale_price'] ?? 0);

        if (empty($chassis_no))   $errors[] = 'Chassis number is required.';
        if (empty($make))         $errors[] = 'Make is required.';
        if (empty($model))        $errors[] = 'Model is required.';
        if ($year < 1970)         $errors[] = 'Valid year is required.';
        if ($purchase_price <= 0) $errors[] = 'Purchase price is required.';
        if ($sale_price <= 0)     $errors[] = 'Sale price is required.';

        // Duplicate chassis — exclude current car
        if (!empty($chassis_no)) {
            $exists = $db->fetchOne(
                "SELECT id FROM cars WHERE chassis_no = ? AND id != ?",
                [$chassis_no, $id], 'si'
            );
            if ($exists) $errors[] = 'Another car with this chassis number already exists.';
        }

        if (empty($errors)) {

            $engine_no         = clean($_POST['engine_no'] ?? '');
            $variant           = clean($_POST['variant'] ?? '');
            $registration_year = (int)($_POST['registration_year'] ?? 0) ?: null;
            $city_registered   = clean($_POST['city_registered'] ?? '');
            $color             = clean($_POST['color'] ?? '');
            $assembly          = clean($_POST['assembly'] ?? 'local');
            $body_type         = clean($_POST['body_type'] ?? '');
            $mileage           = (int)($_POST['mileage'] ?? 0);
            $fuel_type         = clean($_POST['fuel_type'] ?? 'petrol');
            $transmission      = clean($_POST['transmission'] ?? 'manual');
            $engine_capacity   = (int)($_POST['engine_capacity'] ?? 0) ?: null;
            $ownership         = clean($_POST['ownership'] ?? '1st');
            $condition_rating  = clean($_POST['condition_rating'] ?? 'good');
            $min_price         = (float)($_POST['min_price'] ?? 0);
            $is_negotiable     = isset($_POST['is_negotiable']) ? 1 : 0;
            $supplier_name     = clean($_POST['supplier_name'] ?? '');
            $buying_source     = clean($_POST['buying_source'] ?? 'individual');
            $status            = clean($_POST['status'] ?? 'available');
            $notes             = clean($_POST['notes'] ?? '');

            // Documentation
            $has_original_book = isset($_POST['has_original_book']) ? 1 : 0;
            $has_original_file = isset($_POST['has_original_file']) ? 1 : 0;
            $has_smart_card    = isset($_POST['has_smart_card'])    ? 1 : 0;
            $token_paid        = isset($_POST['token_paid'])        ? 1 : 0;
            $tracker_installed = isset($_POST['tracker_installed']) ? 1 : 0;

            // Features
            $abs             = isset($_POST['abs'])             ? 1 : 0;
            $airbags         = isset($_POST['airbags'])         ? 1 : 0;
            $sunroof         = isset($_POST['sunroof'])         ? 1 : 0;
            $alloy_rims      = isset($_POST['alloy_rims'])      ? 1 : 0;
            $navigation      = isset($_POST['navigation'])      ? 1 : 0;
            $climate_control = isset($_POST['climate_control']) ? 1 : 0;
            $keyless_entry   = isset($_POST['keyless_entry'])   ? 1 : 0;
            $push_start      = isset($_POST['push_start'])      ? 1 : 0;
            $cruise_control  = isset($_POST['cruise_control'])  ? 1 : 0;
            $parking_sensors = isset($_POST['parking_sensors']) ? 1 : 0;
            $reverse_camera  = isset($_POST['reverse_camera'])  ? 1 : 0;

            $meta_title       = clean($_POST['meta_title'] ?? '');
            $meta_description = clean($_POST['meta_description'] ?? '');

            // Price change log
            if ((float)$car['sale_price'] !== $sale_price) {
                logPriceChange($id, $car['sale_price'], $sale_price, Auth::id(), 'Manual update');
            }

            // Regenerate slug if make/model/year/color changed
            $newSlug = generateSlug($make, $model, $year, $color);
            if ($newSlug !== $car['slug']) {
                $slugBase  = $newSlug;
                $slugCount = 1;
                while ($db->fetchOne(
                    "SELECT id FROM cars WHERE slug = ? AND id != ?",
                    [$newSlug, $id], 'si'
                )) {
                    $newSlug = $slugBase . '-' . $slugCount++;
                }
            } else {
                $newSlug = $car['slug'];
            }

            // Build update data
            $updateData = [
                $chassis_no, $engine_no, $make, $model, $variant, $year, $registration_year,
                $city_registered, $color, $assembly, $body_type, $mileage, $fuel_type,
                $transmission, $engine_capacity, $ownership, $condition_rating,
                $purchase_price, $sale_price, $min_price, $is_negotiable,
                $supplier_name, $buying_source, $status, $notes,
                $has_original_book, $has_original_file, $has_smart_card, $token_paid, $tracker_installed,
                $abs, $airbags, $sunroof, $alloy_rims, $navigation, $climate_control,
                $keyless_entry, $push_start, $cruise_control, $parking_sensors, $reverse_camera,
                $newSlug, $meta_title, $meta_description,
                $id
            ];

            $types = '';
            foreach ($updateData as $val) {
                if (is_int($val))        $types .= 'i';
                elseif (is_float($val))  $types .= 'd';
                else                     $types .= 's';
            }

            $db->execute(
                "UPDATE cars SET
                    chassis_no=?, engine_no=?, make=?, model=?, variant=?, year=?, registration_year=?,
                    city_registered=?, color=?, assembly=?, body_type=?, mileage=?, fuel_type=?,
                    transmission=?, engine_capacity=?, ownership=?, condition_rating=?,
                    purchase_price=?, sale_price=?, min_price=?, is_negotiable=?,
                    supplier_name=?, buying_source=?, status=?, notes=?,
                    has_original_book=?, has_original_file=?, has_smart_card=?, token_paid=?, tracker_installed=?,
                    abs=?, airbags=?, sunroof=?, alloy_rims=?, navigation=?, climate_control=?,
                    keyless_entry=?, push_start=?, cruise_control=?, parking_sensors=?, reverse_camera=?,
                    slug=?, meta_title=?, meta_description=?
                WHERE id=?",
                $updateData,
                $types
            );

            // ── Delete removed images ──
            if (!empty($_POST['delete_images'])) {
                foreach ($_POST['delete_images'] as $imgId) {
                    $imgId = (int)$imgId;
                    $img   = $db->fetchOne(
                        "SELECT image_path FROM car_images WHERE id = ? AND car_id = ?",
                        [$imgId, $id], 'ii'
                    );
                    if ($img) {
                        $path = UPLOAD_PATH . $img['image_path'];
                        if (file_exists($path)) unlink($path);
                        $db->execute("DELETE FROM car_images WHERE id = ?", [$imgId], 'i');
                    }
                }
            }

            // ── Set primary image ──
            if (!empty($_POST['primary_image_id'])) {
                $primaryId = (int)$_POST['primary_image_id'];
                $db->execute("UPDATE car_images SET is_primary = 0 WHERE car_id = ?", [$id], 'i');
                $db->execute(
                    "UPDATE car_images SET is_primary = 1 WHERE id = ? AND car_id = ?",
                    [$primaryId, $id], 'ii'
                );
            }

            // ── New image uploads ──
            if (!empty($_FILES['new_images']['name'][0])) {
                $uploadDir  = UPLOAD_PATH . 'cars/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $sortStart  = count($existingImages);

                foreach ($_FILES['new_images']['tmp_name'] as $i => $tmpName) {
                    if ($_FILES['new_images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $file = [
                        'name'     => $_FILES['new_images']['name'][$i],
                        'type'     => $_FILES['new_images']['type'][$i],
                        'tmp_name' => $tmpName,
                        'size'     => $_FILES['new_images']['size'][$i],
                    ];
                    $upload = uploadImage($file, 'cars');
                    if ($upload['success']) {
                        $db->insert(
                            "INSERT INTO car_images (car_id, image_path, is_primary, sort_order) VALUES (?,?,0,?)",
                            [$id, $upload['filename'], $sortStart + $i], 'isi'
                        );
                    }
                }
            }

            // ── Update costs ──
            // Delete removed costs
            if (!empty($_POST['delete_costs'])) {
                foreach ($_POST['delete_costs'] as $costId) {
                    $db->execute(
                        "DELETE FROM car_costs WHERE id = ? AND car_id = ?",
                        [(int)$costId, $id], 'ii'
                    );
                }
            }

            // Add new costs
            if (!empty($_POST['cost_type'])) {
                foreach ($_POST['cost_type'] as $ci => $cType) {
                    $cType   = clean($cType);
                    $cAmount = (float)($_POST['cost_amount'][$ci] ?? 0);
                    $cDesc   = clean($_POST['cost_desc'][$ci] ?? '');
                    if ($cType && $cAmount > 0) {
                        $db->insert(
                            "INSERT INTO car_costs (car_id, cost_type, amount, description, cost_date, added_by)
                             VALUES (?,?,?,?,NOW(),?)",
                            [$id, $cType, $cAmount, $cDesc, Auth::id()],
                            'isdsi'
                        );
                    }
                }
            }

            // Timeline log
            logTimeline($id, 'updated',
                "Car updated: $year $make $model",
                Auth::id()
            );

            setFlash('success', "$year $make $model updated successfully!");
            redirect('modules/inventory/index.php');
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Use POST values on error, otherwise DB values
$d = !empty($errors) ? array_merge($car, $_POST) : $car;

$pageTitle = 'Edit Car';
$pageSub   = $car['year'] . ' ' . $car['make'] . ' ' . $car['model'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Car — AutoManager Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/car-showroom/public/css/fa/all.min.css">
    <style>
        .form-section {
            background: #0d1526;
            border: 1px solid rgba(148,163,184,0.08);
            border-radius: 16px; overflow: hidden; margin-bottom: 20px;
        }
        .form-section-header {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(148,163,184,0.07);
            display: flex; align-items: center; gap: 10px;
        }
        .form-section-header .icon-box {
            width:32px; height:32px; border-radius:8px;
            display:flex; align-items:center; justify-content:center; font-size:0.8rem;
        }
        .form-section-title { font-size:0.875rem; font-weight:700; color:#e2e8f0; }
        .form-section-body  { padding: 20px; }

        label.field-label {
            display:block; font-size:0.75rem; font-weight:600;
            color:#64748b; text-transform:uppercase;
            letter-spacing:0.06em; margin-bottom:6px;
        }
        .field-label span.req { color:#f87171; }

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
        select.form-input option  { background:#1e293b; color:#f1f5f9; }

        .check-item {
            display:flex; align-items:center; gap:8px;
            padding:8px 12px; border-radius:8px;
            border:1.5px solid rgba(148,163,184,0.1);
            background:rgba(30,41,59,0.5);
            cursor:pointer; transition:all 0.2s; user-select:none;
        }
        .check-item:hover { border-color:rgba(59,130,246,0.4); background:rgba(37,99,235,0.08); }
        .check-item input[type="checkbox"] { accent-color:#3b82f6; width:15px; height:15px; cursor:pointer; }
        .check-item input:checked + span   { color:#93c5fd; }
        .check-item span { font-size:0.8rem; color:#64748b; font-weight:500; }

        .cost-row {
            display:grid; grid-template-columns:1fr 1fr 2fr auto;
            gap:10px; align-items:end; margin-bottom:10px;
        }

        .btn-primary {
            background:linear-gradient(135deg,#2563eb,#1d4ed8);
            color:#fff; font-weight:700; font-size:0.9rem;
            padding:12px 28px; border-radius:12px; border:none;
            cursor:pointer; transition:all 0.25s;
            box-shadow:0 4px 20px rgba(37,99,235,0.3);
        }
        .btn-primary:hover { transform:translateY(-1px); box-shadow:0 8px 28px rgba(37,99,235,0.4); }

        .btn-secondary {
            background:rgba(30,41,59,0.8);
            border:1.5px solid rgba(148,163,184,0.12);
            color:#94a3b8; font-weight:600; font-size:0.9rem;
            padding:12px 24px; border-radius:12px;
            cursor:pointer; transition:all 0.2s;
            text-decoration:none; display:inline-flex; align-items:center; gap:8px;
        }
        .btn-secondary:hover { border-color:rgba(148,163,184,0.25); color:#e2e8f0; }

        .error-alert {
            background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.25);
            color:#fca5a5; border-radius:12px; padding:14px 16px; margin-bottom:20px;
        }
        .error-alert ul { margin-top:6px; padding-left:18px; }
        .error-alert li { font-size:0.85rem; margin-bottom:2px; }

        /* Image manager */
        .img-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:10px; }
        .img-card {
            position:relative; border-radius:10px; overflow:hidden;
            aspect-ratio:4/3; background:#1e293b;
            border:2px solid rgba(148,163,184,0.1);
            transition:border-color 0.2s;
        }
        .img-card.is-primary { border-color:#3b82f6; }
        .img-card img { width:100%; height:100%; object-fit:cover; }
        .img-card .img-actions {
            position:absolute; inset:0;
            background:rgba(0,0,0,0.6);
            display:flex; flex-direction:column;
            align-items:center; justify-content:center; gap:6px;
            opacity:0; transition:opacity 0.2s;
        }
        .img-card:hover .img-actions { opacity:1; }
        .img-card .primary-badge {
            position:absolute; top:5px; left:5px;
            background:#2563eb; color:#fff;
            font-size:0.6rem; font-weight:700;
            padding:2px 6px; border-radius:4px;
        }
        .img-btn {
            font-size:0.7rem; font-weight:600; padding:4px 10px;
            border-radius:6px; border:none; cursor:pointer; transition:all 0.2s;
        }

        .img-upload-zone {
            border:2px dashed rgba(148,163,184,0.15); border-radius:12px;
            padding:24px; text-align:center; transition:all 0.2s; cursor:pointer;
        }
        .img-upload-zone:hover { border-color:#3b82f6; background:rgba(37,99,235,0.05); }
    </style>
</head>
<body>
<?php require_once '../../views/layouts/sidebar.php'; ?>
<div class="main">
<?php require_once '../../views/layouts/topbar.php'; ?>
<div class="content-area" style="max-width:960px;">

    <?php if (!empty($errors)): ?>
    <div class="error-alert">
        <div class="flex items-center gap-2 font-bold text-sm">
            <i class="fas fa-circle-exclamation"></i> Please fix the following errors:
        </div>
        <ul>
            <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="editCarForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <!-- ═══ SECTION 1 — BASIC INFO ═══ -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(37,99,235,0.15)">
                <i class="fas fa-car text-blue-400"></i>
            </div>
            <span class="form-section-title">Basic Information</span>
        </div>
        <div class="form-section-body">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                <div>
                    <label class="field-label">Make <span class="req">*</span></label>
                    <select name="make" class="form-input" required>
                        <option value="">Select Make</option>
                        <?php
                        $makes = ['Toyota','Honda','Suzuki','Kia','Hyundai','Nissan','Mitsubishi',
                                  'Daihatsu','BMW','Mercedes','Audi','Ford','Changan','MG','Haval',
                                  'FAW','DFSK','Prince','United','Regal'];
                        foreach ($makes as $m):
                        ?>
                        <option value="<?= $m ?>" <?= $d['make']===$m?'selected':'' ?>><?= $m ?></option>
                        <?php endforeach; ?>
                        <option value="other" <?= $d['make']==='other'?'selected':'' ?>>Other</option>
                    </select>
                </div>

                <div>
                    <label class="field-label">Model <span class="req">*</span></label>
                    <input type="text" name="model" class="form-input"
                           value="<?= htmlspecialchars($d['model']) ?>" required>
                </div>

                <div>
                    <label class="field-label">Variant</label>
                    <input type="text" name="variant" class="form-input"
                           value="<?= htmlspecialchars($d['variant'] ?? '') ?>">
                </div>

                <div>
                    <label class="field-label">Year <span class="req">*</span></label>
                    <select name="year" class="form-input" required>
                        <?php for ($y = date('Y'); $y >= 1970; $y--): ?>
                        <option value="<?= $y ?>" <?= $d['year']==$y?'selected':'' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div>
                    <label class="field-label">Registration Year</label>
                    <select name="registration_year" class="form-input">
                        <option value="">Select Year</option>
                        <?php for ($y = date('Y'); $y >= 1970; $y--): ?>
                        <option value="<?= $y ?>" <?= ($d['registration_year']==$y)?'selected':'' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div>
                    <label class="field-label">City Registered</label>
                    <select name="city_registered" class="form-input">
                        <option value="">Select City</option>
                        <?php foreach (['Karachi','Lahore','Islamabad','Rawalpindi','Peshawar','Quetta','Multan','Faisalabad','Hyderabad','Sialkot','Other'] as $c): ?>
                        <option value="<?= $c ?>" <?= ($d['city_registered']===$c)?'selected':'' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="field-label">Color</label>
                    <input type="text" name="color" class="form-input"
                           value="<?= htmlspecialchars($d['color'] ?? '') ?>">
                </div>

                <div>
                    <label class="field-label">Body Type</label>
                    <select name="body_type" class="form-input">
                        <option value="">Select Type</option>
                        <?php foreach (['sedan','suv','hatchback','pickup','van','crossover','other'] as $bt): ?>
                        <option value="<?= $bt ?>" <?= ($d['body_type']===$bt)?'selected':'' ?>><?= ucfirst($bt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="field-label">Assembly</label>
                    <select name="assembly" class="form-input">
                        <option value="local"    <?= $d['assembly']==='local'   ?'selected':'' ?>>Local</option>
                        <option value="imported" <?= $d['assembly']==='imported'?'selected':'' ?>>Imported</option>
                    </select>
                </div>

            </div>
        </div>
    </div>

    <!-- ═══ SECTION 2 — ENGINE & SPECS ═══ -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(16,185,129,0.15)">
                <i class="fas fa-cogs text-emerald-400"></i>
            </div>
            <span class="form-section-title">Engine & Specifications</span>
        </div>
        <div class="form-section-body">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                <div>
                    <label class="field-label">Fuel Type</label>
                    <select name="fuel_type" class="form-input">
                        <?php foreach (['petrol'=>'Petrol','diesel'=>'Diesel','hybrid'=>'Hybrid','electric'=>'Electric','cng'=>'CNG'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= $d['fuel_type']===$v?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="field-label">Transmission</label>
                    <select name="transmission" class="form-input">
                        <option value="manual"    <?= $d['transmission']==='manual'   ?'selected':'' ?>>Manual</option>
                        <option value="automatic" <?= $d['transmission']==='automatic'?'selected':'' ?>>Automatic</option>
                    </select>
                </div>

                <div>
                    <label class="field-label">Engine Capacity (CC)</label>
                    <input type="number" name="engine_capacity" class="form-input"
                           value="<?= htmlspecialchars($d['engine_capacity'] ?? '') ?>">
                </div>

                <div>
                    <label class="field-label">Mileage (KM)</label>
                    <input type="number" name="mileage" class="form-input"
                           value="<?= htmlspecialchars($d['mileage'] ?? 0) ?>">
                </div>

                <div>
                    <label class="field-label">Ownership</label>
                    <select name="ownership" class="form-input">
                        <?php foreach (['1st'=>'1st Owner','2nd'=>'2nd Owner','3rd'=>'3rd Owner','4th+'=>'4th+ Owner'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= $d['ownership']===$v?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="field-label">Condition</label>
                    <select name="condition_rating" class="form-input">
                        <?php foreach (['excellent'=>'Excellent','good'=>'Good','average'=>'Average','below_average'=>'Below Average'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= $d['condition_rating']===$v?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>
        </div>
    </div>

    <!-- ═══ SECTION 3 — IDENTITY & SOURCE ═══ -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(139,92,246,0.15)">
                <i class="fas fa-fingerprint text-purple-400"></i>
            </div>
            <span class="form-section-title">Identity & Source</span>
        </div>
        <div class="form-section-body">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                <div>
                    <label class="field-label">Chassis Number <span class="req">*</span></label>
                    <input type="text" name="chassis_no" class="form-input"
                           value="<?= htmlspecialchars($d['chassis_no']) ?>"
                           style="text-transform:uppercase" required>
                </div>

                <div>
                    <label class="field-label">Engine Number</label>
                    <input type="text" name="engine_no" class="form-input"
                           value="<?= htmlspecialchars($d['engine_no'] ?? '') ?>">
                </div>

                <div>
                    <label class="field-label">Buying Source</label>
                    <select name="buying_source" class="form-input">
                        <?php foreach (['individual'=>'Individual Seller','auction'=>'Auction','trade_in'=>'Trade-In','dealer'=>'Dealer'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= $d['buying_source']===$v?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="field-label">Supplier Name</label>
                    <input type="text" name="supplier_name" class="form-input"
                           value="<?= htmlspecialchars($d['supplier_name'] ?? '') ?>">
                </div>

                <div>
                    <label class="field-label">Stock Status</label>
                    <select name="status" class="form-input">
                        <option value="available" <?= $d['status']==='available'?'selected':'' ?>>Available</option>
                        <option value="reserved"  <?= $d['status']==='reserved' ?'selected':'' ?>>Reserved</option>
                        <option value="sold"      <?= $d['status']==='sold'     ?'selected':'' ?>>Sold</option>
                    </select>
                </div>

            </div>
        </div>
    </div>

    <!-- ═══ SECTION 4 — PRICING ═══ -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(234,179,8,0.15)">
                <i class="fas fa-tag text-yellow-400"></i>
            </div>
            <span class="form-section-title">Pricing</span>
        </div>
        <div class="form-section-body">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="field-label">Purchase Price (PKR) <span class="req">*</span></label>
                    <input type="number" name="purchase_price" id="purchasePrice" class="form-input"
                           value="<?= htmlspecialchars($d['purchase_price']) ?>"
                           oninput="calcProfit()" required>
                </div>
                <div>
                    <label class="field-label">Sale Price (PKR) <span class="req">*</span></label>
                    <input type="number" name="sale_price" id="salePrice" class="form-input"
                           value="<?= htmlspecialchars($d['sale_price']) ?>"
                           oninput="calcProfit()" required>
                </div>
                <div>
                    <label class="field-label">Minimum Acceptable Price</label>
                    <input type="number" name="min_price" class="form-input"
                           value="<?= htmlspecialchars($d['min_price'] ?? '') ?>">
                </div>
            </div>

            <div id="profitPreview" class="p-4 rounded-xl flex items-center gap-4"
                 style="background:rgba(22,163,74,0.08);border:1px solid rgba(22,163,74,0.2)">
                <i class="fas fa-arrow-trend-up text-green-400 text-xl"></i>
                <div>
                    <div class="text-xs text-slate-500 font-semibold uppercase tracking-wide">Estimated Profit</div>
                    <div class="font-extrabold text-lg" id="profitAmount">PKR 0</div>
                </div>
                <div class="ml-auto">
                    <label class="check-item">
                        <input type="checkbox" name="is_negotiable"
                               <?= $d['is_negotiable'] ? 'checked' : '' ?>>
                        <span>Price is Negotiable</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ SECTION 5 — DOCUMENTATION ═══ -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(14,165,233,0.15)">
                <i class="fas fa-file-alt text-sky-400"></i>
            </div>
            <span class="form-section-title">Documentation</span>
        </div>
        <div class="form-section-body">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                <?php
                $docs = [
                    'has_original_book' => 'Original Book',
                    'has_original_file' => 'Original File',
                    'has_smart_card'    => 'Smart Card',
                    'token_paid'        => 'Token Paid',
                    'tracker_installed' => 'Tracker',
                ];
                foreach ($docs as $dname => $dlabel):
                ?>
                <label class="check-item">
                    <input type="checkbox" name="<?= $dname ?>"
                           <?= $d[$dname] ? 'checked' : '' ?>>
                    <span><?= $dlabel ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ═══ SECTION 6 — FEATURES ═══ -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(245,158,11,0.15)">
                <i class="fas fa-star text-amber-400"></i>
            </div>
            <span class="form-section-title">Features & Extras</span>
        </div>
        <div class="form-section-body">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                <?php
                $features = [
                    'abs'=>'ABS Brakes','airbags'=>'Airbags','sunroof'=>'Sunroof',
                    'alloy_rims'=>'Alloy Rims','navigation'=>'Navigation',
                    'climate_control'=>'Climate Control','keyless_entry'=>'Keyless Entry',
                    'push_start'=>'Push Start','cruise_control'=>'Cruise Control',
                    'parking_sensors'=>'Parking Sensors','reverse_camera'=>'Reverse Camera',
                ];
                foreach ($features as $fname => $flabel):
                ?>
                <label class="check-item">
                    <input type="checkbox" name="<?= $fname ?>"
                           <?= $d[$fname] ? 'checked' : '' ?>>
                    <span><?= $flabel ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ═══ SECTION 7 — EXISTING COSTS ═══ -->
    <?php if (!empty($existingCosts)): ?>
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(239,68,68,0.15)">
                <i class="fas fa-receipt text-red-400"></i>
            </div>
            <span class="form-section-title">Existing Costs</span>
        </div>
        <div class="form-section-body">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left;font-size:0.7rem;color:#475569;padding:8px 0;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;border-bottom:1px solid rgba(148,163,184,0.08)">Type</th>
                        <th style="text-align:left;font-size:0.7rem;color:#475569;padding:8px 0;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;border-bottom:1px solid rgba(148,163,184,0.08)">Amount</th>
                        <th style="text-align:left;font-size:0.7rem;color:#475569;padding:8px 0;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;border-bottom:1px solid rgba(148,163,184,0.08)">Description</th>
                        <th style="border-bottom:1px solid rgba(148,163,184,0.08)"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($existingCosts as $cost): ?>
                <tr id="cost-row-<?= $cost['id'] ?>">
                    <td style="padding:10px 0;font-size:0.85rem;color:#94a3b8;border-bottom:1px solid rgba(148,163,184,0.05)">
                        <?= ucfirst(str_replace('_',' ',$cost['cost_type'])) ?>
                    </td>
                    <td style="padding:10px 0;font-size:0.85rem;color:#4ade80;font-weight:600;border-bottom:1px solid rgba(148,163,184,0.05)">
                        <?= formatPrice($cost['amount']) ?>
                    </td>
                    <td style="padding:10px 0;font-size:0.85rem;color:#64748b;border-bottom:1px solid rgba(148,163,184,0.05)">
                        <?= htmlspecialchars($cost['description'] ?? '—') ?>
                    </td>
                    <td style="padding:10px 0;border-bottom:1px solid rgba(148,163,184,0.05);text-align:right">
                        <label style="display:flex;align-items:center;gap:6px;justify-content:flex-end;cursor:pointer">
                            <input type="checkbox" name="delete_costs[]"
                                   value="<?= $cost['id'] ?>"
                                   style="accent-color:#ef4444"
                                   onchange="this.closest('tr').style.opacity = this.checked ? '0.4' : '1'">
                            <span style="font-size:0.75rem;color:#f87171;font-weight:600">Remove</span>
                        </label>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══ SECTION 8 — ADD NEW COSTS ═══ -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(239,68,68,0.15)">
                <i class="fas fa-plus-circle text-red-400"></i>
            </div>
            <span class="form-section-title">Add New Costs</span>
        </div>
        <div class="form-section-body">
            <div id="costsContainer">
                <div class="cost-row">
                    <div>
                        <label class="field-label">Cost Type</label>
                        <select name="cost_type[]" class="form-input">
                            <option value="">Select Type</option>
                            <?php foreach (['repair'=>'Repair','paint'=>'Paint','transport'=>'Transport','inspection'=>'Inspection','token_fee'=>'Token Fee','transfer_fee'=>'Transfer Fee','other'=>'Other'] as $v=>$l): ?>
                            <option value="<?= $v ?>"><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Amount (PKR)</label>
                        <input type="number" name="cost_amount[]" class="form-input"
                               placeholder="0" min="0" oninput="calcProfit()">
                    </div>
                    <div>
                        <label class="field-label">Description</label>
                        <input type="text" name="cost_desc[]" class="form-input" placeholder="Optional note">
                    </div>
                    <div style="padding-bottom:2px">
                        <button type="button" onclick="removeCostRow(this)"
                                class="w-9 h-10 rounded-lg flex items-center justify-center text-red-400 hover:bg-red-500/10 transition-all"
                                style="border:1.5px solid rgba(239,68,68,0.2)">
                            <i class="fas fa-xmark text-sm"></i>
                        </button>
                    </div>
                </div>
            </div>
            <button type="button" onclick="addCostRow()"
                    class="mt-2 flex items-center gap-2 text-xs font-semibold text-blue-400 hover:text-blue-300 transition-colors">
                <i class="fas fa-plus-circle"></i> Add Another Cost
            </button>
        </div>
    </div>

    <!-- ═══ SECTION 9 — IMAGE MANAGER ═══ -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(99,102,241,0.15)">
                <i class="fas fa-images text-indigo-400"></i>
            </div>
            <span class="form-section-title">Image Manager</span>
            <span class="ml-2 text-xs text-slate-600">(Hover to set primary or delete)</span>
        </div>
        <div class="form-section-body">

            <!-- Existing images -->
            <?php if (!empty($existingImages)): ?>
            <p class="text-xs text-slate-500 font-semibold uppercase tracking-widest mb-3">Current Images</p>
            <div class="img-grid mb-5">
                <?php foreach ($existingImages as $img): ?>
                <div class="img-card <?= $img['is_primary'] ? 'is-primary' : '' ?>"
                     id="imgcard-<?= $img['id'] ?>">
                    <img src="<?= UPLOAD_URL . htmlspecialchars($img['image_path']) ?>" alt="car image">
                    <?php if ($img['is_primary']): ?>
                    <span class="primary-badge">Primary</span>
                    <?php endif; ?>
                    <div class="img-actions">
                        <button type="button"
                                onclick="setPrimary(<?= $img['id'] ?>)"
                                class="img-btn"
                                style="background:rgba(37,99,235,0.9);color:#fff">
                            <i class="fas fa-star mr-1"></i> Set Primary
                        </button>
                        <label class="img-btn flex items-center gap-1 cursor-pointer"
                               style="background:rgba(220,38,38,0.9);color:#fff">
                            <input type="checkbox" name="delete_images[]"
                                   value="<?= $img['id'] ?>" class="hidden"
                                   onchange="markDelete(this, <?= $img['id'] ?>)">
                            <i class="fas fa-trash"></i> Delete
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="primary_image_id" id="primaryImageId"
                   value="<?= $existingImages[0]['id'] ?? '' ?>">
            <?php endif; ?>

            <!-- Upload new -->
            <p class="text-xs text-slate-500 font-semibold uppercase tracking-widest mb-3">Upload New Images</p>
            <div class="img-upload-zone" onclick="document.getElementById('newImageInput').click()">
                <i class="fas fa-cloud-upload-alt text-slate-600 text-2xl mb-2 block"></i>
                <p class="text-slate-400 font-semibold text-sm">Click to add more images</p>
                <p class="text-slate-600 text-xs mt-1">JPG, PNG, WebP — max 5MB each</p>
            </div>
            <input type="file" name="new_images[]" id="newImageInput"
                   multiple accept="image/jpeg,image/png,image/webp"
                   class="hidden" onchange="previewNewImages(this)">
            <div class="img-grid mt-3" id="newPreviewGrid"></div>
        </div>
    </div>

    <!-- ═══ SECTION 10 — NOTES & SEO ═══ -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(20,184,166,0.15)">
                <i class="fas fa-note-sticky text-teal-400"></i>
            </div>
            <span class="form-section-title">Notes & SEO</span>
        </div>
        <div class="form-section-body">
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="field-label">Internal Notes</label>
                    <textarea name="notes" class="form-input" rows="3"
                              style="resize:vertical"><?= htmlspecialchars($d['notes'] ?? '') ?></textarea>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="field-label">Meta Title</label>
                        <input type="text" name="meta_title" class="form-input"
                               value="<?= htmlspecialchars($d['meta_title'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="field-label">Meta Description</label>
                        <input type="text" name="meta_description" class="form-input"
                               value="<?= htmlspecialchars($d['meta_description'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ SUBMIT BAR ═══ -->
    <div class="flex items-center justify-between gap-4 py-4 sticky bottom-0 px-4 -mx-4 rounded-xl"
         style="background:rgba(10,15,30,0.95);backdrop-filter:blur(12px);border-top:1px solid rgba(148,163,184,0.08)">
        <a href="/car-showroom/modules/inventory/index.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Cancel
        </a>
        <button type="submit" class="btn-primary">
            <i class="fas fa-save mr-2"></i> Save Changes
        </button>
    </div>

    </form>
</div>
</div>

<script>
// Profit calculator
function calcProfit() {
    const purchase = parseFloat(document.getElementById('purchasePrice').value) || 0;
    const sale     = parseFloat(document.getElementById('salePrice').value)     || 0;
    let extra      = 0;
    document.querySelectorAll('input[name="cost_amount[]"]').forEach(el => {
        extra += parseFloat(el.value) || 0;
    });
    const profit  = sale - purchase - extra;
    const el      = document.getElementById('profitAmount');
    const preview = document.getElementById('profitPreview');
    el.textContent       = 'PKR ' + profit.toLocaleString();
    el.className         = 'font-extrabold text-lg ' + (profit >= 0 ? 'text-green-400' : 'text-red-400');
    preview.style.borderColor = profit >= 0 ? 'rgba(22,163,74,0.2)' : 'rgba(239,68,68,0.2)';
    preview.style.background  = profit >= 0 ? 'rgba(22,163,74,0.08)' : 'rgba(239,68,68,0.08)';
}
calcProfit();

// Set primary image
function setPrimary(imgId) {
    document.getElementById('primaryImageId').value = imgId;
    document.querySelectorAll('.img-card').forEach(card => {
        card.classList.remove('is-primary');
        const badge = card.querySelector('.primary-badge');
        if (badge) badge.remove();
    });
    const card = document.getElementById('imgcard-' + imgId);
    if (card) {
        card.classList.add('is-primary');
        const b = document.createElement('span');
        b.className   = 'primary-badge'; b.textContent = 'Primary';
        card.appendChild(b);
    }
}

// Mark image for deletion
function markDelete(checkbox, imgId) {
    const card = document.getElementById('imgcard-' + imgId);
    if (card) card.style.opacity = checkbox.checked ? '0.3' : '1';
}

// Cost rows
function addCostRow() {
    const container = document.getElementById('costsContainer');
    const row = document.createElement('div');
    row.className = 'cost-row';
    row.innerHTML = `
        <div>
            <label class="field-label">Cost Type</label>
            <select name="cost_type[]" class="form-input">
                <option value="">Select Type</option>
                <option value="repair">Repair</option>
                <option value="paint">Paint</option>
                <option value="transport">Transport</option>
                <option value="inspection">Inspection</option>
                <option value="token_fee">Token Fee</option>
                <option value="transfer_fee">Transfer Fee</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div>
            <label class="field-label">Amount (PKR)</label>
            <input type="number" name="cost_amount[]" class="form-input"
                   placeholder="0" min="0" oninput="calcProfit()">
        </div>
        <div>
            <label class="field-label">Description</label>
            <input type="text" name="cost_desc[]" class="form-input" placeholder="Optional note">
        </div>
        <div style="padding-bottom:2px">
            <button type="button" onclick="removeCostRow(this)"
                    class="w-9 h-10 rounded-lg flex items-center justify-center text-red-400 hover:bg-red-500/10 transition-all"
                    style="border:1.5px solid rgba(239,68,68,0.2)">
                <i class="fas fa-xmark text-sm"></i>
            </button>
        </div>`;
    container.appendChild(row);
}

function removeCostRow(btn) {
    const rows = document.querySelectorAll('.cost-row');
    if (rows.length > 1) btn.closest('.cost-row').remove();
}

// New image preview
let newFiles = [];
function previewNewImages(input) {
    const grid = document.getElementById('newPreviewGrid');
    Array.from(input.files).forEach(file => {
        if (newFiles.length >= 10) return;
        newFiles.push(file);
        const reader = new FileReader();
        reader.onload = e => {
            const div = document.createElement('div');
            div.className = 'img-card';
            div.style.border = '2px solid rgba(37,99,235,0.3)';
            div.innerHTML = `<img src="${e.target.result}" alt="new">
                <span class="primary-badge" style="background:#1d4ed8">New</span>`;
            grid.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
    const dt = new DataTransfer();
    newFiles.forEach(f => dt.items.add(f));
    document.getElementById('newImageInput').files = dt.files;
}

// Chassis uppercase
document.querySelector('input[name="chassis_no"]').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});
</script>
</body>
</html>