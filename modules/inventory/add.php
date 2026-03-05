<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('inventory.add');

$db     = Database::getInstance();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request. Please try again.';
    } else {

        // ── Required fields ──
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

        // Duplicate chassis check
        if (!empty($chassis_no)) {
            $exists = $db->fetchOne("SELECT id FROM cars WHERE chassis_no = ?", [$chassis_no], 's');
            if ($exists) $errors[] = 'A car with this chassis number already exists.';
        }

        if (empty($errors)) {

            // ── All fields ──
            $engine_no         = clean($_POST['engine_no'] ?? '');
            $variant           = clean($_POST['variant'] ?? '');
            $registration_no   = clean($_POST['registration_no'] ?? '');
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

            // SEO slug
            $slug = generateSlug($make, $model, $year, $color);
            $slugBase = $slug; $slugCount = 1;
            while ($db->fetchOne("SELECT id FROM cars WHERE slug = ?", [$slug], 's')) {
                $slug = $slugBase . '-' . $slugCount++;
            }
            $meta_title       = clean($_POST['meta_title'] ?? "$year $make $model - AutoManager Pro");
            $meta_description = clean($_POST['meta_description'] ?? '');

            // ── Insert car ──
            $insertData = [
                $chassis_no, $engine_no, $make, $model, $variant, $year, $registration_year,
                $city_registered, $color, $assembly, $body_type, $mileage, $fuel_type,
                $transmission, $engine_capacity, $ownership, $condition_rating,
                $purchase_price, $sale_price, $min_price, $is_negotiable,
                $supplier_name, $buying_source, $status, $notes,
                $has_original_book, $has_original_file, $has_smart_card, $token_paid, $tracker_installed,
                $abs, $airbags, $sunroof, $alloy_rims, $navigation, $climate_control,
                $keyless_entry, $push_start, $cruise_control, $parking_sensors, $reverse_camera,
                $slug, $meta_title, $meta_description, Auth::id()
            ];

            $types = '';
            foreach ($insertData as $val) {
                if (is_int($val))        $types .= 'i';
                elseif (is_float($val))  $types .= 'd';
                else                     $types .= 's';
            }

            $carId = $db->insert(
                "INSERT INTO cars (
                    chassis_no, engine_no, make, model, variant, year, registration_year,
                    city_registered, color, assembly, body_type, mileage, fuel_type,
                    transmission, engine_capacity, ownership, condition_rating,
                    purchase_price, sale_price, min_price, is_negotiable,
                    supplier_name, buying_source, status, notes,
                    has_original_book, has_original_file, has_smart_card, token_paid, tracker_installed,
                    abs, airbags, sunroof, alloy_rims, navigation, climate_control,
                    keyless_entry, push_start, cruise_control, parking_sensors, reverse_camera,
                    slug, meta_title, meta_description, added_by
                ) VALUES (
                    ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
                )",
                $insertData, $types
            );

            if ($carId) {

                // Store registration_no separately if column exists (or in notes)
                if (!empty($registration_no)) {
                    $db->execute(
                        "UPDATE cars SET notes = CONCAT(IFNULL(notes,''), IF(notes IS NULL OR notes='', '', '\n'), ?) WHERE id = ?",
                        ["Registration No: $registration_no", $carId], 'si'
                    );
                }

                // ── Image uploads ──
                if (!empty($_FILES['images']['name'][0])) {
                    $uploadDir = UPLOAD_PATH . 'cars/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {
                        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                        $file = [
                            'name' => $_FILES['images']['name'][$i],
                            'type' => $_FILES['images']['type'][$i],
                            'tmp_name' => $tmpName,
                            'size' => $_FILES['images']['size'][$i],
                        ];
                        $upload = uploadImage($file, 'cars');
                        if ($upload['success']) {
                            $isPrimary = ($i === 0) ? 1 : 0;
                            $db->insert(
                                "INSERT INTO car_images (car_id, image_path, is_primary, sort_order) VALUES (?,?,?,?)",
                                [$carId, $upload['filename'], $isPrimary, $i], 'isii'
                            );
                        }
                    }
                }

                // ── Timeline ──
                logTimeline($carId, 'added',
                    "Car added: $year $make $model at " . formatPrice($sale_price),
                    Auth::id()
                );

                // ── Additional costs ──
                if (!empty($_POST['cost_type'])) {
                    foreach ($_POST['cost_type'] as $ci => $cType) {
                        $cType   = clean($cType);
                        $cAmount = (float)($_POST['cost_amount'][$ci] ?? 0);
                        $cDesc   = clean($_POST['cost_desc'][$ci] ?? '');
                        if ($cType && $cAmount > 0) {
                            $db->insert(
                                "INSERT INTO car_costs (car_id, cost_type, amount, description, cost_date, added_by)
                                 VALUES (?,?,?,?,NOW(),?)",
                                [$carId, $cType, $cAmount, $cDesc, Auth::id()], 'isdsi'
                            );
                        }
                    }
                }

                setFlash('success', "$year $make $model added successfully!");
                redirect('modules/inventory/index.php');

            } else {
                $errors[] = 'Failed to save car. Please try again.';
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Add New Car';
$pageSub   = 'Fill in the vehicle details below';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Car — AutoManager Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/car-showroom/public/css/fa/all.min.css">
    <style>
        .form-section {
            background: #0d1526;
            border: 1px solid rgba(148,163,184,0.08);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .form-section-header {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(148,163,184,0.07);
            display: flex; align-items: center; gap: 10px;
        }
        .form-section-header .icon-box {
            width: 32px; height: 32px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center; font-size: 0.8rem;
        }
        .form-section-title { font-size: 0.875rem; font-weight: 700; color: #e2e8f0; }
        .form-section-body { padding: 20px; }

        label.field-label {
            display: block; font-size: 0.75rem; font-weight: 600;
            color: #64748b; text-transform: uppercase;
            letter-spacing: 0.06em; margin-bottom: 6px;
        }
        .field-label span.req { color: #f87171; }

        .form-input {
            width: 100%;
            background: rgba(30,41,59,0.8);
            border: 1.5px solid rgba(148,163,184,0.12);
            color: #f1f5f9; font-size: 0.875rem;
            padding: 10px 14px; border-radius: 10px;
            transition: border-color 0.2s, box-shadow 0.2s;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .form-input:focus {
            outline: none; border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }
        .form-input::placeholder { color: #334155; }
        .form-input.error { border-color: #f87171; }
        select.form-input option { background: #1e293b; color: #f1f5f9; }

        .check-item {
            display: flex; align-items: center; gap: 8px;
            padding: 8px 12px; border-radius: 8px;
            border: 1.5px solid rgba(148,163,184,0.1);
            background: rgba(30,41,59,0.5);
            cursor: pointer; transition: all 0.2s; user-select: none;
        }
        .check-item:hover { border-color: rgba(59,130,246,0.4); background: rgba(37,99,235,0.08); }
        .check-item input[type="checkbox"] { accent-color: #3b82f6; width: 15px; height: 15px; cursor: pointer; }
        .check-item input:checked + span { color: #93c5fd; }
        .check-item span { font-size: 0.8rem; color: #64748b; font-weight: 500; }

        .img-upload-zone {
            border: 2px dashed rgba(148,163,184,0.15);
            border-radius: 12px; padding: 32px;
            text-align: center; transition: all 0.2s; cursor: pointer;
        }
        .img-upload-zone:hover, .img-upload-zone.drag-over {
            border-color: #3b82f6; background: rgba(37,99,235,0.05);
        }
        .img-preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 8px; margin-top: 12px;
        }
        .img-preview-item {
            position: relative; border-radius: 8px;
            overflow: hidden; aspect-ratio: 4/3; background: #1e293b;
        }
        .img-preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .img-preview-item .primary-badge {
            position: absolute; top: 4px; left: 4px;
            background: #2563eb; color: #fff;
            font-size: 0.6rem; font-weight: 700;
            padding: 2px 6px; border-radius: 4px;
        }
        .img-preview-item .remove-btn {
            position: absolute; top: 4px; right: 4px;
            background: rgba(220,38,38,0.8); color: #fff;
            border: none; border-radius: 4px;
            width: 20px; height: 20px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 0.65rem;
        }
        .cost-row {
            display: grid;
            grid-template-columns: 1fr 1fr 2fr auto;
            gap: 10px; align-items: end; margin-bottom: 10px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff; font-weight: 700; font-size: 0.9rem;
            padding: 12px 28px; border-radius: 12px;
            border: none; cursor: pointer; transition: all 0.25s;
            box-shadow: 0 4px 20px rgba(37,99,235,0.3);
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 28px rgba(37,99,235,0.4); }
        .btn-secondary {
            background: rgba(30,41,59,0.8);
            border: 1.5px solid rgba(148,163,184,0.12);
            color: #94a3b8; font-weight: 600; font-size: 0.9rem;
            padding: 12px 24px; border-radius: 12px;
            cursor: pointer; transition: all 0.2s;
            text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-secondary:hover { border-color: rgba(148,163,184,0.25); color: #e2e8f0; }

        /* Reset button */
        .btn-reset {
            background: rgba(245,158,11,0.08);
            border: 1.5px solid rgba(245,158,11,0.2);
            color: #fbbf24; font-weight: 600; font-size: 0.9rem;
            padding: 12px 24px; border-radius: 12px;
            cursor: pointer; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-reset:hover { background: rgba(245,158,11,0.15); border-color: rgba(245,158,11,0.35); }

        .error-alert {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.25);
            color: #fca5a5; border-radius: 12px;
            padding: 14px 16px; margin-bottom: 20px;
        }
        .error-alert ul { margin-top: 6px; padding-left: 18px; }
        .error-alert li { font-size: 0.85rem; margin-bottom: 2px; }

        /* ── Registration Plate ── */
        .plate-wrap {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
        .plate-preview {
            display: inline-flex;
            align-items: center;
            gap: 0;
            border-radius: 6px;
            overflow: hidden;
            border: 2px solid #1e293b;
            box-shadow: 0 2px 12px rgba(0,0,0,0.4);
            font-family: 'Plus Jakarta Sans', sans-serif;
            height: 44px;
        }
        /* Left year strip — Pakistan style green/black */
        .plate-year-strip {
            background: #166534;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            padding: 0 6px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            min-width: 22px;
            border-right: 2px solid rgba(0,0,0,0.3);
            text-transform: uppercase;
        }
        /* Main plate body */
        .plate-body {
            background: #f8f4e3;
            padding: 0 14px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 1;
        }
        .plate-number {
            font-size: 1.1rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-width: 120px;
            text-align: center;
        }
        .plate-number.empty {
            color: #94a3b8;
            font-size: 0.75rem;
            font-weight: 500;
            letter-spacing: 0.04em;
        }
        .plate-input-row {
            display: flex;
            gap: 8px;
            align-items: center;
            width: 100%;
        }
        .plate-input-row .form-input {
            flex: 1;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
        }
        .plate-input-row select.form-input {
            width: 110px;
            flex: none;
        }
    </style>
</head>
<body>
<?php require_once '../../views/layouts/sidebar.php'; ?>
<div class="main">
<?php require_once '../../views/layouts/topbar.php'; ?>
<div class="content-area" style="max-width:960px;">

    <?php if (!empty($errors)): ?>
    <div class="error-alert">
        <div class="flex items-center gap-2 font-700 text-sm">
            <i class="fas fa-circle-exclamation"></i> Please fix the following errors:
        </div>
        <ul>
            <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="addCarForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <!-- ═══════════════════════════════════════
         SECTION 1 — BASIC INFORMATION
    ═══════════════════════════════════════ -->
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
                    <select name="make" id="makeSelect" class="form-input" required>
                        <option value="">Select Make</option>
                        <?php
                        $makes = ['Toyota','Honda','Suzuki','Kia','Hyundai','Nissan','Mitsubishi',
                                  'Daihatsu','BMW','Mercedes','Audi','Ford','Changan','MG','Haval',
                                  'FAW','DFSK','Prince','United','Regal'];
                        $oldMake = $_POST['make'] ?? '';
                        foreach ($makes as $m):
                        ?>
                        <option value="<?= $m ?>" <?= $oldMake===$m?'selected':'' ?>><?= $m ?></option>
                        <?php endforeach; ?>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div>
                    <label class="field-label">Model <span class="req">*</span></label>
                    <input type="text" name="model" class="form-input"
                           placeholder="e.g. Corolla, Civic"
                           value="<?= htmlspecialchars($_POST['model'] ?? '') ?>" required>
                </div>

                <div>
                    <label class="field-label">Variant</label>
                    <input type="text" name="variant" class="form-input"
                           placeholder="e.g. GLI, XLI, VTi"
                           value="<?= htmlspecialchars($_POST['variant'] ?? '') ?>">
                </div>

                <div>
                    <label class="field-label">Year <span class="req">*</span></label>
                    <select name="year" class="form-input" required>
                        <option value="">Select Year</option>
                        <?php for ($y = date('Y'); $y >= 1970; $y--): ?>
                        <option value="<?= $y ?>" <?= (($_POST['year']??'')==$y)?'selected':'' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div>
                    <label class="field-label">City Registered</label>
                    <select name="city_registered" class="form-input">
                        <option value="">Select City</option>
                        <?php
                        $cities = ['Karachi','Lahore','Islamabad','Rawalpindi','Peshawar',
                                   'Quetta','Multan','Faisalabad','Hyderabad','Sialkot','Other'];
                        foreach ($cities as $c):
                        ?>
                        <option value="<?= $c ?>" <?= (($_POST['city_registered']??'')===$c)?'selected':'' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="field-label">Color</label>
                    <input type="text" name="color" class="form-input"
                           placeholder="e.g. White, Black, Silver"
                           value="<?= htmlspecialchars($_POST['color'] ?? '') ?>">
                </div>

                <div>
                    <label class="field-label">Body Type</label>
                    <select name="body_type" class="form-input">
                        <option value="">Select Type</option>
                        <?php foreach (['sedan','suv','hatchback','pickup','van','crossover','other'] as $bt): ?>
                        <option value="<?= $bt ?>" <?= (($_POST['body_type']??'')===$bt)?'selected':'' ?>><?= ucfirst($bt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="field-label">Assembly</label>
                    <select name="assembly" class="form-input">
                        <option value="local"    <?= (($_POST['assembly']??'local')==='local')   ?'selected':'' ?>>Local</option>
                        <option value="imported" <?= (($_POST['assembly']??'')==='imported')?'selected':'' ?>>Imported</option>
                    </select>
                </div>

            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════
         SECTION 2 — REGISTRATION PLATE
    ═══════════════════════════════════════ -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(22,163,74,0.15)">
                <i class="fas fa-id-card text-green-400"></i>
            </div>
            <span class="form-section-title">Registration Plate</span>
            <span class="ml-2 text-xs text-slate-600">Pakistani number plate format</span>
        </div>
        <div class="form-section-body">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">

                <!-- Live plate preview -->
                <div>
                    <label class="field-label">Plate Preview</label>
                    <div class="plate-wrap">
                        <div class="plate-preview" id="platePreview">
                            <div class="plate-year-strip" id="plateYearStrip">2024</div>
                            <div class="plate-body">
                                <div class="plate-number empty" id="plateNumber">ABC-123</div>
                            </div>
                        </div>
                        <div class="plate-input-row">
                            <input type="text"
                                   name="registration_no"
                                   id="registrationNoInput"
                                   class="form-input"
                                   placeholder="e.g. LEB-1234 or ABC-123"
                                   value="<?= htmlspecialchars($_POST['registration_no'] ?? '') ?>"
                                   maxlength="15"
                                   oninput="updatePlate()">
                            <select name="registration_year"
                                    id="registrationYearSelect"
                                    class="form-input"
                                    onchange="updatePlate()">
                                <option value="">Year</option>
                                <?php for ($y = date('Y'); $y >= 1970; $y--): ?>
                                <option value="<?= $y ?>" <?= (($_POST['registration_year']??'')==$y)?'selected':'' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <p class="text-slate-600 text-xs">Type the plate number and select year to preview</p>
                    </div>
                </div>

                <!-- City code helper -->
                <div>
                    <label class="field-label">Common City Codes</label>
                    <div class="grid grid-cols-2 gap-2">
                        <?php
                        $cityCodes = [
                            'LEB' => 'Lahore',   'KHI' => 'Karachi',
                            'ISB' => 'Islamabad','RWP' => 'Rawalpindi',
                            'MUL' => 'Multan',   'FSD' => 'Faisalabad',
                            'PES' => 'Peshawar', 'HYD' => 'Hyderabad',
                        ];
                        foreach ($cityCodes as $code => $city):
                        ?>
                        <button type="button"
                                onclick="insertCityCode('<?= $code ?>')"
                                class="text-left px-3 py-2 rounded-lg text-xs transition-all"
                                style="background:rgba(30,41,59,0.6);
                                       border:1px solid rgba(148,163,184,0.1);
                                       color:#64748b">
                            <span style="color:#60a5fa;font-weight:700"><?= $code ?></span>
                            <span class="ml-1"><?= $city ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════
         SECTION 3 — ENGINE & SPECS
    ═══════════════════════════════════════ -->
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
                        <option value="<?= $v ?>" <?= (($_POST['fuel_type']??'petrol')===$v)?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="field-label">Transmission</label>
                    <select name="transmission" class="form-input">
                        <option value="manual"    <?= (($_POST['transmission']??'manual')==='manual')   ?'selected':'' ?>>Manual</option>
                        <option value="automatic" <?= (($_POST['transmission']??'')==='automatic')?'selected':'' ?>>Automatic</option>
                    </select>
                </div>

                <div>
                    <label class="field-label">Engine Capacity (CC)</label>
                    <input type="number" name="engine_capacity" class="form-input"
                           placeholder="e.g. 1300, 1800, 2000"
                           value="<?= htmlspecialchars($_POST['engine_capacity'] ?? '') ?>">
                </div>

                <div>
                    <label class="field-label">Mileage (KM)</label>
                    <input type="number" name="mileage" class="form-input"
                           placeholder="e.g. 45000"
                           value="<?= htmlspecialchars($_POST['mileage'] ?? '0') ?>">
                </div>

                <div>
                    <label class="field-label">Ownership</label>
                    <select name="ownership" class="form-input">
                        <?php foreach (['1st'=>'1st Owner','2nd'=>'2nd Owner','3rd'=>'3rd Owner','4th+'=>'4th+ Owner'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= (($_POST['ownership']??'1st')===$v)?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="field-label">Condition</label>
                    <select name="condition_rating" class="form-input">
                        <?php foreach (['excellent'=>'Excellent','good'=>'Good','average'=>'Average','below_average'=>'Below Average'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= (($_POST['condition_rating']??'good')===$v)?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════
         SECTION 4 — IDENTITY & SOURCE
    ═══════════════════════════════════════ -->
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
                           placeholder="Unique chassis number"
                           value="<?= htmlspecialchars($_POST['chassis_no'] ?? '') ?>"
                           style="text-transform:uppercase" required>
                </div>

                <div>
                    <label class="field-label">Engine Number</label>
                    <input type="text" name="engine_no" class="form-input"
                           placeholder="Engine number"
                           value="<?= htmlspecialchars($_POST['engine_no'] ?? '') ?>">
                </div>

                <div>
                    <label class="field-label">Buying Source</label>
                    <select name="buying_source" class="form-input">
                        <?php foreach (['individual'=>'Individual Seller','auction'=>'Auction','trade_in'=>'Trade-In','dealer'=>'Dealer'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= (($_POST['buying_source']??'individual')===$v)?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="field-label">Supplier Name</label>
                    <input type="text" name="supplier_name" class="form-input"
                           placeholder="Seller / supplier name"
                           value="<?= htmlspecialchars($_POST['supplier_name'] ?? '') ?>">
                </div>

                <div>
                    <label class="field-label">Stock Status</label>
                    <select name="status" class="form-input">
                        <option value="available" <?= (($_POST['status']??'available')==='available')?'selected':'' ?>>Available</option>
                        <option value="reserved"  <?= (($_POST['status']??'')==='reserved') ?'selected':'' ?>>Reserved</option>
                        <option value="sold"      <?= (($_POST['status']??'')==='sold')     ?'selected':'' ?>>Sold</option>
                    </select>
                </div>

            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════
         SECTION 5 — PRICING
    ═══════════════════════════════════════ -->
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
                           placeholder="0" min="0" step="1000"
                           value="<?= htmlspecialchars($_POST['purchase_price'] ?? '') ?>"
                           oninput="calcProfit()" required>
                </div>

                <div>
                    <label class="field-label">Sale Price (PKR) <span class="req">*</span></label>
                    <input type="number" name="sale_price" id="salePrice" class="form-input"
                           placeholder="0" min="0" step="1000"
                           value="<?= htmlspecialchars($_POST['sale_price'] ?? '') ?>"
                           oninput="calcProfit()" required>
                </div>

                <div>
                    <label class="field-label">Minimum Acceptable Price</label>
                    <input type="number" name="min_price" class="form-input"
                           placeholder="Internal floor price"
                           value="<?= htmlspecialchars($_POST['min_price'] ?? '') ?>">
                </div>

            </div>

            <div id="profitPreview" class="hidden p-4 rounded-xl flex items-center gap-4"
                 style="background:rgba(22,163,74,0.08);border:1px solid rgba(22,163,74,0.2)">
                <i class="fas fa-arrow-trend-up text-green-400 text-xl"></i>
                <div>
                    <div class="text-xs text-slate-500 font-600 uppercase tracking-wide">Estimated Profit</div>
                    <div class="text-green-400 font-800 text-lg" id="profitAmount">PKR 0</div>
                </div>
                <div class="ml-auto">
                    <label class="check-item">
                        <input type="checkbox" name="is_negotiable" <?= isset($_POST['is_negotiable'])?'checked':'' ?>>
                        <span>Price is Negotiable</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════
         SECTION 6 — DOCUMENTATION
    ═══════════════════════════════════════ -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(14,165,233,0.15)">
                <i class="fas fa-file-alt text-sky-400"></i>
            </div>
            <span class="form-section-title">Documentation</span>
        </div>
        <div class="form-section-body">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                <label class="check-item">
                    <input type="checkbox" name="has_original_book" <?= isset($_POST['has_original_book'])?'checked':'' ?>>
                    <span>Original Book</span>
                </label>
                <label class="check-item">
                    <input type="checkbox" name="has_original_file" <?= isset($_POST['has_original_file'])?'checked':'' ?>>
                    <span>Original File</span>
                </label>
                <label class="check-item">
                    <input type="checkbox" name="has_smart_card" <?= isset($_POST['has_smart_card'])?'checked':'' ?>>
                    <span>Smart Card</span>
                </label>
                <label class="check-item">
                    <input type="checkbox" name="token_paid" <?= isset($_POST['token_paid'])?'checked':'' ?>>
                    <span>Token Paid</span>
                </label>
                <label class="check-item">
                    <input type="checkbox" name="tracker_installed" <?= isset($_POST['tracker_installed'])?'checked':'' ?>>
                    <span>Tracker</span>
                </label>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════
         SECTION 7 — FEATURES
    ═══════════════════════════════════════ -->
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
                    'abs'             => 'ABS Brakes',
                    'airbags'         => 'Airbags',
                    'sunroof'         => 'Sunroof',
                    'alloy_rims'      => 'Alloy Rims',
                    'navigation'      => 'Navigation',
                    'climate_control' => 'Climate Control',
                    'keyless_entry'   => 'Keyless Entry',
                    'push_start'      => 'Push Start',
                    'cruise_control'  => 'Cruise Control',
                    'parking_sensors' => 'Parking Sensors',
                    'reverse_camera'  => 'Reverse Camera',
                ];
                foreach ($features as $fname => $flabel):
                ?>
                <label class="check-item">
                    <input type="checkbox" name="<?= $fname ?>"
                           <?= isset($_POST[$fname]) ? 'checked' : '' ?>>
                    <span><?= $flabel ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════
         SECTION 8 — ADDITIONAL COSTS
    ═══════════════════════════════════════ -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(239,68,68,0.15)">
                <i class="fas fa-receipt text-red-400"></i>
            </div>
            <span class="form-section-title">Additional Costs</span>
            <span class="ml-2 text-xs text-slate-600">(repair, paint, transport etc.)</span>
        </div>
        <div class="form-section-body">
            <div id="costsContainer">
                <div class="cost-row">
                    <div>
                        <label class="field-label">Cost Type</label>
                        <select name="cost_type[]" class="form-input">
                            <option value="">Select Type</option>
                            <?php foreach (['repair'=>'Repair','paint'=>'Paint','transport'=>'Transport',
                                            'inspection'=>'Inspection','token_fee'=>'Token Fee',
                                            'transfer_fee'=>'Transfer Fee','other'=>'Other'] as $v=>$l): ?>
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
                    class="mt-2 flex items-center gap-2 text-xs font-600 text-blue-400 hover:text-blue-300 transition-colors">
                <i class="fas fa-plus-circle"></i> Add Another Cost
            </button>
        </div>
    </div>

    <!-- ═══════════════════════════════════════
         SECTION 9 — IMAGES
    ═══════════════════════════════════════ -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="icon-box" style="background:rgba(99,102,241,0.15)">
                <i class="fas fa-images text-indigo-400"></i>
            </div>
            <span class="form-section-title">Vehicle Images</span>
            <span class="ml-2 text-xs text-slate-600">(First image = primary. Max 10 images, 5MB each)</span>
        </div>
        <div class="form-section-body">
            <div class="img-upload-zone" id="uploadZone" onclick="document.getElementById('imageInput').click()">
                <i class="fas fa-cloud-upload-alt text-slate-600 text-3xl mb-3 block"></i>
                <p class="text-slate-400 font-600 text-sm">Click to upload or drag & drop</p>
                <p class="text-slate-600 text-xs mt-1">JPG, PNG, WebP — max 5MB each</p>
            </div>
            <input type="file" name="images[]" id="imageInput"
                   multiple accept="image/jpeg,image/png,image/webp"
                   class="hidden" onchange="previewImages(this)">
            <div class="img-preview-grid" id="previewGrid"></div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════
         SECTION 10 — NOTES & SEO
    ═══════════════════════════════════════ -->
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
                              placeholder="Any internal notes about this vehicle..."
                              style="resize:vertical"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="field-label">Meta Title</label>
                        <input type="text" name="meta_title" class="form-input"
                               placeholder="Auto-generated if left blank"
                               value="<?= htmlspecialchars($_POST['meta_title'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="field-label">Meta Description</label>
                        <input type="text" name="meta_description" class="form-input"
                               placeholder="Short SEO description"
                               value="<?= htmlspecialchars($_POST['meta_description'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════
         SUBMIT BAR
    ═══════════════════════════════════════ -->
    <div class="flex items-center justify-between gap-4 py-4 sticky bottom-0 px-4 -mx-4 rounded-xl"
         style="background:rgba(10,15,30,0.95);backdrop-filter:blur(12px);border-top:1px solid rgba(148,163,184,0.08)">
        <div class="flex items-center gap-3">
            <a href="/car-showroom/modules/inventory/index.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancel
            </a>
            <button type="button" onclick="resetForm()" class="btn-reset">
                <i class="fas fa-rotate-left"></i> Reset
            </button>
        </div>
        <div class="flex items-center gap-3">
            <button type="submit" name="save_action" value="save_add" class="btn-secondary">
                <i class="fas fa-plus"></i> Save & Add Another
            </button>
            <button type="submit" name="save_action" value="save_view" class="btn-primary">
                <i class="fas fa-check mr-2"></i> Save Car
            </button>
        </div>
    </div>

    </form>
</div>
</div>

<script>
// ── Registration Plate Live Preview ──
function updatePlate() {
    const input  = document.getElementById('registrationNoInput');
    const yearSel= document.getElementById('registrationYearSelect');
    const numEl  = document.getElementById('plateNumber');
    const yrEl   = document.getElementById('plateYearStrip');

    const val  = input.value.trim().toUpperCase();
    const year = yearSel.value || new Date().getFullYear();

    // Force uppercase as user types
    input.value = input.value.toUpperCase();

    yrEl.textContent = year;

    if (val) {
        numEl.textContent = val;
        numEl.classList.remove('empty');
    } else {
        numEl.textContent = 'ABC-123';
        numEl.classList.add('empty');
    }
}

function insertCityCode(code) {
    const input = document.getElementById('registrationNoInput');
    // If empty or doesn't start with a letter sequence, set the code prefix
    const current = input.value.trim();
    if (!current) {
        input.value = code + '-';
    } else {
        // Replace the city code part if there's a dash
        const parts = current.split('-');
        parts[0] = code;
        input.value = parts.join('-');
    }
    input.focus();
    updatePlate();
}

// Init plate on load
document.addEventListener('DOMContentLoaded', function() {
    updatePlate();
});

// ── Profit Calculator ──
function calcProfit() {
    const purchase = parseFloat(document.getElementById('purchasePrice').value) || 0;
    const sale     = parseFloat(document.getElementById('salePrice').value)     || 0;

    let extraCosts = 0;
    document.querySelectorAll('input[name="cost_amount[]"]').forEach(el => {
        extraCosts += parseFloat(el.value) || 0;
    });

    const profit  = sale - purchase - extraCosts;
    const preview = document.getElementById('profitPreview');
    const amount  = document.getElementById('profitAmount');

    if (sale > 0 || purchase > 0) {
        preview.classList.remove('hidden');
        amount.textContent = 'PKR ' + profit.toLocaleString();
        amount.className   = 'font-800 text-lg ' + (profit >= 0 ? 'text-green-400' : 'text-red-400');
        preview.style.borderColor = profit >= 0 ? 'rgba(22,163,74,0.2)' : 'rgba(239,68,68,0.2)';
        preview.style.background  = profit >= 0 ? 'rgba(22,163,74,0.08)' : 'rgba(239,68,68,0.08)';
    } else {
        preview.classList.add('hidden');
    }
}

// ── Reset Form ──
function resetForm() {
    if (!confirm('Reset all fields? This cannot be undone.')) return;

    const form = document.getElementById('addCarForm');

    // Reset all text/number/select/textarea inputs
    form.querySelectorAll('input:not([type="hidden"]):not([type="file"]):not([type="checkbox"])').forEach(el => {
        el.value = '';
    });
    form.querySelectorAll('select').forEach(el => {
        el.selectedIndex = 0;
    });
    form.querySelectorAll('textarea').forEach(el => {
        el.value = '';
    });
    form.querySelectorAll('input[type="checkbox"]').forEach(el => {
        el.checked = false;
    });

    // Reset cost rows — keep only 1 blank row
    const container = document.getElementById('costsContainer');
    const rows = container.querySelectorAll('.cost-row');
    rows.forEach((row, i) => {
        if (i === 0) {
            row.querySelectorAll('input').forEach(el => el.value = '');
            row.querySelectorAll('select').forEach(el => el.selectedIndex = 0);
        } else {
            row.remove();
        }
    });

    // Reset images
    selectedFiles = [];
    document.getElementById('previewGrid').innerHTML = '';
    document.getElementById('imageInput').value = '';

    // Reset profit preview
    document.getElementById('profitPreview').classList.add('hidden');

    // Reset plate preview
    document.getElementById('registrationNoInput').value = '';
    updatePlate();

    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── Cost Rows ──
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
    if (rows.length > 1) {
        btn.closest('.cost-row').remove();
        calcProfit();
    }
}

// ── Image Preview ──
let selectedFiles = [];

function previewImages(input) {
    const grid = document.getElementById('previewGrid');
    const newFiles = Array.from(input.files);
    newFiles.forEach((file, i) => {
        if (selectedFiles.length >= 10) return;
        selectedFiles.push(file);
        const reader = new FileReader();
        reader.onload = e => {
            const div = document.createElement('div');
            div.className = 'img-preview-item';
            div.innerHTML = `
                <img src="${e.target.result}" alt="preview">
                ${selectedFiles.length === 1 && i === 0 ? '<span class="primary-badge">Primary</span>' : ''}
                <button type="button" class="remove-btn" onclick="removeImage(this, ${selectedFiles.length - 1})">
                    <i class="fas fa-xmark"></i>
                </button>`;
            grid.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
    updateFileInput();
}

function removeImage(btn, index) {
    selectedFiles.splice(index, 1);
    btn.closest('.img-preview-item').remove();
    const items = document.querySelectorAll('.img-preview-item');
    items.forEach((item, i) => {
        const badge = item.querySelector('.primary-badge');
        if (badge) badge.remove();
        if (i === 0) {
            const b = document.createElement('span');
            b.className = 'primary-badge'; b.textContent = 'Primary';
            item.appendChild(b);
        }
    });
    updateFileInput();
}

function updateFileInput() {
    const dt = new DataTransfer();
    selectedFiles.forEach(f => dt.items.add(f));
    document.getElementById('imageInput').files = dt.files;
}

// ── Drag & Drop ──
const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', ()  => zone.classList.remove('drag-over'));
zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('drag-over');
    const input = document.getElementById('imageInput');
    const dt    = new DataTransfer();
    Array.from(e.dataTransfer.files).forEach(f => dt.items.add(f));
    input.files = dt.files;
    previewImages(input);
});

// ── Chassis uppercase ──
document.querySelector('input[name="chassis_no"]').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});
</script>
</body>
</html>