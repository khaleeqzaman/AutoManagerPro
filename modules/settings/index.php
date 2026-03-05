<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('settings.manage');

$db = Database::getInstance();

// ── Ensure settings table exists ──
$db->execute("CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// ── Default settings ──
$defaults = [
    // Showroom
    'showroom_name'        => 'AutoManager Pro',
    'showroom_tagline'     => 'Premium Car Showroom',
    'showroom_address'     => '',
    'showroom_city'        => 'Karachi',
    'showroom_phone'       => '',
    'showroom_email'       => '',
    'showroom_website'     => '',
    'showroom_ntn'         => '',
    // System
    'currency_symbol'      => 'PKR',
    'date_format'          => 'd M Y',
    'timezone'             => 'Asia/Karachi',
    'items_per_page'       => '25',
    // Tax
    'tax_rate'             => '0',
    'tax_number'           => '',
    'withholding_tax_rate' => '1',
    // Invoice
    'invoice_prefix'       => 'INV-',
    'invoice_start'        => '1001',
    'invoice_terms'        => 'Payment due within 30 days.',
    // Transfer fee
    'default_transfer_fee' => '0',
    'default_commission'   => '0',
];

// ── Handle POST ──
$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request.';
    } else {
        $tab = clean($_POST['active_tab'] ?? 'showroom');

        // Handle logo upload
        if (!empty($_FILES['showroom_logo']['name'])) {
            $logoFile = $_FILES['showroom_logo'];
            $allowed  = ['image/jpeg','image/png','image/webp','image/gif'];
            if (!in_array($logoFile['type'], $allowed)) {
                $errors[] = 'Logo must be JPG, PNG, WebP or GIF.';
            } elseif ($logoFile['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Logo must be under 2MB.';
            } else {
                $uploadDir = __DIR__ . '/../../public/uploads/settings/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext      = pathinfo($logoFile['name'], PATHINFO_EXTENSION);
                $filename = 'logo.' . $ext;
                if (move_uploaded_file($logoFile['tmp_name'], $uploadDir . $filename)) {
                    $db->execute(
                        "INSERT INTO system_settings (setting_key, setting_value)
                         VALUES ('showroom_logo', ?) ON DUPLICATE KEY UPDATE setting_value = ?",
                        [$filename, $filename], 'ss'
                    );
                }
            }
        }

        if (empty($errors)) {
            // Save all posted settings
            $allowed_keys = array_keys($defaults);
            foreach ($_POST as $key => $value) {
                if (!in_array($key, $allowed_keys)) continue;
                $value = clean($value);
                $db->execute(
                    "INSERT INTO system_settings (setting_key, setting_value)
                     VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?",
                    [$key, $value, $value], 'sss'
                );
            }
            setFlash('success', 'Settings saved successfully.');
            header('Location: index.php?tab=' . $tab);
            exit;
        }
    }
}

// ── Load all settings ──
$rows = $db->fetchAll("SELECT setting_key, setting_value FROM system_settings");
$settings = $defaults;
foreach ($rows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Active tab
$activeTab = clean($_GET['tab'] ?? 'showroom');
$validTabs = ['showroom','system','tax','invoice'];
if (!in_array($activeTab, $validTabs)) $activeTab = 'showroom';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$flash     = getFlash();
$pageTitle = 'Settings';
$pageSub   = 'Configure your showroom & system preferences';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — AutoManager Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/car-showroom/public/css/fa/all.min.css">
    <style>
        .settings-card {
            background: #0d1526;
            border: 1px solid rgba(148,163,184,0.08);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .settings-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(148,163,184,0.07);
            display: flex; align-items: center; gap: 10px;
        }
        .settings-card-header .icon-box {
            width: 32px; height: 32px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
        }
        .settings-card-title { font-size: 0.875rem; font-weight: 700; color: #e2e8f0; }
        .settings-card-body { padding: 20px; }

        label.field-label {
            display: block; font-size: 0.75rem; font-weight: 600;
            color: #64748b; text-transform: uppercase;
            letter-spacing: 0.06em; margin-bottom: 6px;
        }
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
        select.form-input option { background: #1e293b; color: #f1f5f9; }
        textarea.form-input { resize: vertical; }

        /* Tab navigation */
        .settings-tabs {
            display: flex; gap: 4px;
            background: rgba(13,20,40,0.6);
            border: 1px solid rgba(148,163,184,0.08);
            border-radius: 14px; padding: 5px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .settings-tab {
            padding: 9px 18px; border-radius: 10px;
            font-size: 0.82rem; font-weight: 600;
            color: #475569; cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            display: flex; align-items: center; gap: 7px;
        }
        .settings-tab:hover { color: #94a3b8; }
        .settings-tab.active {
            background: rgba(37,99,235,0.15);
            color: #60a5fa;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }

        .btn-save {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff; font-weight: 700; font-size: 0.9rem;
            padding: 12px 32px; border-radius: 12px;
            border: none; cursor: pointer; transition: all 0.25s;
            box-shadow: 0 4px 20px rgba(37,99,235,0.3);
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 8px 28px rgba(37,99,235,0.4); }

        .logo-preview {
            width: 100px; height: 100px; border-radius: 12px;
            background: rgba(30,41,59,0.8);
            border: 2px dashed rgba(148,163,184,0.15);
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; cursor: pointer;
            transition: border-color 0.2s;
        }
        .logo-preview:hover { border-color: #3b82f6; }
        .logo-preview img { width: 100%; height: 100%; object-fit: contain; }

        .info-row {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 16px; margin-bottom: 16px;
        }
        @media (max-width: 640px) { .info-row { grid-template-columns: 1fr; } }

        .input-prefix {
            display: flex; align-items: center;
            background: rgba(30,41,59,0.8);
            border: 1.5px solid rgba(148,163,184,0.12);
            border-radius: 10px; overflow: hidden;
        }
        .input-prefix span {
            padding: 10px 12px;
            background: rgba(37,99,235,0.08);
            border-right: 1.5px solid rgba(148,163,184,0.12);
            color: #475569; font-size: 0.8rem; font-weight: 600;
            white-space: nowrap;
        }
        .input-prefix input {
            flex: 1; background: transparent;
            border: none; color: #f1f5f9;
            font-size: 0.875rem; padding: 10px 14px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .input-prefix input:focus { outline: none; }

        .alert-success {
            background: rgba(22,163,74,0.1);
            border: 1px solid rgba(22,163,74,0.25);
            color: #4ade80; border-radius: 12px;
            padding: 12px 16px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px; font-size: 0.875rem;
        }
        .alert-error {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.25);
            color: #fca5a5; border-radius: 12px;
            padding: 14px 16px; margin-bottom: 20px;
        }

        .settings-hint {
            font-size: 0.72rem; color: #334155; margin-top: 5px;
        }
    </style>
</head>
<body>
<?php require_once '../../views/layouts/sidebar.php'; ?>
<div class="main">
<?php require_once '../../views/layouts/topbar.php'; ?>
<div class="content-area" style="max-width:860px">

    <?php if ($flash): ?>
    <div class="alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <i class="fas fa-<?= $flash['type'] === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
        <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert-error">
        <div class="flex items-center gap-2 font-700 text-sm mb-1">
            <i class="fas fa-circle-exclamation"></i> Please fix the following:
        </div>
        <ul style="padding-left:18px;margin-top:6px">
            <?php foreach ($errors as $e): ?>
            <li style="font-size:0.85rem;margin-bottom:2px"><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <div class="settings-tabs">
        <a href="?tab=showroom"
           class="settings-tab <?= $activeTab==='showroom'?'active':'' ?>">
            <i class="fas fa-store"></i> Showroom
        </a>
        <a href="?tab=system"
           class="settings-tab <?= $activeTab==='system'?'active':'' ?>">
            <i class="fas fa-sliders"></i> System
        </a>
        <a href="?tab=tax"
           class="settings-tab <?= $activeTab==='tax'?'active':'' ?>">
            <i class="fas fa-percent"></i> Tax & Fees
        </a>
        <a href="?tab=invoice"
           class="settings-tab <?= $activeTab==='invoice'?'active':'' ?>">
            <i class="fas fa-file-invoice"></i> Invoice
        </a>
    </div>

    <form method="POST" enctype="multipart/form-data" id="settingsForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="active_tab" value="<?= $activeTab ?>">

    <!-- ═══════════════════════════
         TAB: SHOWROOM
    ═══════════════════════════ -->
    <?php if ($activeTab === 'showroom'): ?>

        <!-- Logo & Name -->
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="icon-box" style="background:rgba(37,99,235,0.15)">
                    <i class="fas fa-store text-blue-400"></i>
                </div>
                <span class="settings-card-title">Showroom Identity</span>
            </div>
            <div class="settings-card-body">
                <div class="flex items-start gap-6 mb-5">
                    <!-- Logo upload -->
                    <div>
                        <label class="field-label">Logo</label>
                        <div class="logo-preview" onclick="document.getElementById('logoInput').click()">
                            <?php
                            $logoFile = $settings['showroom_logo'] ?? '';
                            $logoPath = '/car-showroom/public/uploads/settings/' . $logoFile;
                            if ($logoFile && file_exists(__DIR__ . '/../../public/uploads/settings/' . $logoFile)):
                            ?>
                            <img src="<?= $logoPath ?>" alt="Logo">
                            <?php else: ?>
                            <div class="text-center">
                                <i class="fas fa-image text-slate-600 text-2xl mb-1 block"></i>
                                <span style="font-size:0.65rem;color:#334155">Click to upload</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="showroom_logo" id="logoInput"
                               accept="image/*" class="hidden" onchange="previewLogo(this)">
                        <p class="settings-hint">PNG, JPG — max 2MB</p>
                    </div>

                    <!-- Name & Tagline -->
                    <div class="flex-1 grid grid-cols-1 gap-4">
                        <div>
                            <label class="field-label">Showroom Name</label>
                            <input type="text" name="showroom_name" class="form-input"
                                   placeholder="e.g. Premier Motors"
                                   value="<?= htmlspecialchars($settings['showroom_name']) ?>">
                        </div>
                        <div>
                            <label class="field-label">Tagline</label>
                            <input type="text" name="showroom_tagline" class="form-input"
                                   placeholder="e.g. Your trusted car partner"
                                   value="<?= htmlspecialchars($settings['showroom_tagline']) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Info -->
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="icon-box" style="background:rgba(16,185,129,0.15)">
                    <i class="fas fa-address-card text-emerald-400"></i>
                </div>
                <span class="settings-card-title">Contact Information</span>
            </div>
            <div class="settings-card-body">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="field-label">Phone Number</label>
                        <input type="text" name="showroom_phone" class="form-input"
                               placeholder="e.g. 021-34567890"
                               value="<?= htmlspecialchars($settings['showroom_phone']) ?>">
                    </div>
                    <div>
                        <label class="field-label">Email Address</label>
                        <input type="email" name="showroom_email" class="form-input"
                               placeholder="e.g. info@showroom.com"
                               value="<?= htmlspecialchars($settings['showroom_email']) ?>">
                    </div>
                    <div>
                        <label class="field-label">Website</label>
                        <input type="text" name="showroom_website" class="form-input"
                               placeholder="e.g. https://showroom.com"
                               value="<?= htmlspecialchars($settings['showroom_website']) ?>">
                    </div>
                    <div>
                        <label class="field-label">NTN Number</label>
                        <input type="text" name="showroom_ntn" class="form-input"
                               placeholder="National Tax Number"
                               value="<?= htmlspecialchars($settings['showroom_ntn']) ?>">
                    </div>
                    <div>
                        <label class="field-label">City</label>
                        <select name="showroom_city" class="form-input">
                            <?php foreach (['Karachi','Lahore','Islamabad','Rawalpindi','Peshawar',
                                            'Quetta','Multan','Faisalabad','Hyderabad','Sialkot','Other'] as $c): ?>
                            <option value="<?= $c ?>" <?= $settings['showroom_city']===$c?'selected':'' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Address</label>
                        <input type="text" name="showroom_address" class="form-input"
                               placeholder="Street address"
                               value="<?= htmlspecialchars($settings['showroom_address']) ?>">
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

    <!-- ═══════════════════════════
         TAB: SYSTEM
    ═══════════════════════════ -->
    <?php if ($activeTab === 'system'): ?>

        <div class="settings-card">
            <div class="settings-card-header">
                <div class="icon-box" style="background:rgba(139,92,246,0.15)">
                    <i class="fas fa-globe text-purple-400"></i>
                </div>
                <span class="settings-card-title">Regional & Display</span>
            </div>
            <div class="settings-card-body">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <div>
                        <label class="field-label">Currency Symbol</label>
                        <select name="currency_symbol" class="form-input">
                            <?php foreach (['PKR'=>'PKR — Pakistani Rupee','USD'=>'USD — US Dollar',
                                            'AED'=>'AED — UAE Dirham','GBP'=>'GBP — British Pound',
                                            'EUR'=>'EUR — Euro'] as $v=>$l): ?>
                            <option value="<?= $v ?>" <?= $settings['currency_symbol']===$v?'selected':'' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="field-label">Date Format</label>
                        <select name="date_format" class="form-input">
                            <?php
                            $formats = [
                                'd M Y'  => date('d M Y')   . ' (Day Month Year)',
                                'd/m/Y'  => date('d/m/Y')   . ' (DD/MM/YYYY)',
                                'm/d/Y'  => date('m/d/Y')   . ' (MM/DD/YYYY)',
                                'Y-m-d'  => date('Y-m-d')   . ' (YYYY-MM-DD)',
                                'd-m-Y'  => date('d-m-Y')   . ' (DD-MM-YYYY)',
                            ];
                            foreach ($formats as $v=>$l): ?>
                            <option value="<?= $v ?>" <?= $settings['date_format']===$v?'selected':'' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="field-label">Timezone</label>
                        <select name="timezone" class="form-input">
                            <?php
                            $zones = [
                                'Asia/Karachi'   => 'Pakistan (PKT, UTC+5)',
                                'Asia/Dubai'     => 'Dubai (GST, UTC+4)',
                                'Asia/Kolkata'   => 'India (IST, UTC+5:30)',
                                'Europe/London'  => 'London (GMT/BST)',
                                'America/New_York'=> 'New York (EST/EDT)',
                                'UTC'            => 'UTC',
                            ];
                            foreach ($zones as $v=>$l): ?>
                            <option value="<?= $v ?>" <?= $settings['timezone']===$v?'selected':'' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="field-label">Records Per Page</label>
                        <select name="items_per_page" class="form-input">
                            <?php foreach (['10','25','50','100'] as $n): ?>
                            <option value="<?= $n ?>" <?= $settings['items_per_page']===$n?'selected':'' ?>><?= $n ?> per page</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>
            </div>
        </div>

    <?php endif; ?>

    <!-- ═══════════════════════════
         TAB: TAX & FEES
    ═══════════════════════════ -->
    <?php if ($activeTab === 'tax'): ?>

        <div class="settings-card">
            <div class="settings-card-header">
                <div class="icon-box" style="background:rgba(234,179,8,0.15)">
                    <i class="fas fa-percent text-yellow-400"></i>
                </div>
                <span class="settings-card-title">Tax Configuration</span>
            </div>
            <div class="settings-card-body">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <div>
                        <label class="field-label">Sales Tax Rate (%)</label>
                        <div class="input-prefix">
                            <span><i class="fas fa-percent"></i></span>
                            <input type="number" name="tax_rate" min="0" max="100" step="0.1"
                                   placeholder="0"
                                   value="<?= htmlspecialchars($settings['tax_rate']) ?>">
                        </div>
                        <p class="settings-hint">Enter 0 to disable sales tax</p>
                    </div>

                    <div>
                        <label class="field-label">Withholding Tax Rate (%)</label>
                        <div class="input-prefix">
                            <span><i class="fas fa-percent"></i></span>
                            <input type="number" name="withholding_tax_rate" min="0" max="100" step="0.1"
                                   placeholder="1"
                                   value="<?= htmlspecialchars($settings['withholding_tax_rate']) ?>">
                        </div>
                        <p class="settings-hint">Applied on vehicle sales automatically</p>
                    </div>

                    <div>
                        <label class="field-label">Tax Registration Number</label>
                        <input type="text" name="tax_number" class="form-input"
                               placeholder="GST/Sales Tax number"
                               value="<?= htmlspecialchars($settings['tax_number']) ?>">
                    </div>

                </div>
            </div>
        </div>

        <div class="settings-card">
            <div class="settings-card-header">
                <div class="icon-box" style="background:rgba(239,68,68,0.15)">
                    <i class="fas fa-hand-holding-dollar text-red-400"></i>
                </div>
                <span class="settings-card-title">Default Fees</span>
            </div>
            <div class="settings-card-body">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <div>
                        <label class="field-label">Default Transfer Fee (<?= htmlspecialchars($settings['currency_symbol']) ?>)</label>
                        <div class="input-prefix">
                            <span><?= htmlspecialchars($settings['currency_symbol']) ?></span>
                            <input type="number" name="default_transfer_fee" min="0" step="100"
                                   placeholder="0"
                                   value="<?= htmlspecialchars($settings['default_transfer_fee']) ?>">
                        </div>
                        <p class="settings-hint">Pre-fills transfer fee on new sales</p>
                    </div>

                    <div>
                        <label class="field-label">Default Commission (<?= htmlspecialchars($settings['currency_symbol']) ?>)</label>
                        <div class="input-prefix">
                            <span><?= htmlspecialchars($settings['currency_symbol']) ?></span>
                            <input type="number" name="default_commission" min="0" step="100"
                                   placeholder="0"
                                   value="<?= htmlspecialchars($settings['default_commission']) ?>">
                        </div>
                        <p class="settings-hint">Default salesperson commission per sale</p>
                    </div>

                </div>
            </div>
        </div>

    <?php endif; ?>

    <!-- ═══════════════════════════
         TAB: INVOICE
    ═══════════════════════════ -->
    <?php if ($activeTab === 'invoice'): ?>

        <div class="settings-card">
            <div class="settings-card-header">
                <div class="icon-box" style="background:rgba(14,165,233,0.15)">
                    <i class="fas fa-file-invoice text-sky-400"></i>
                </div>
                <span class="settings-card-title">Invoice Configuration</span>
            </div>
            <div class="settings-card-body">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">

                    <div>
                        <label class="field-label">Invoice Number Prefix</label>
                        <div class="input-prefix">
                            <span>Prefix</span>
                            <input type="text" name="invoice_prefix" maxlength="10"
                                   placeholder="INV-"
                                   value="<?= htmlspecialchars($settings['invoice_prefix']) ?>">
                        </div>
                        <p class="settings-hint">e.g. INV-, SALE-, TXN-</p>
                    </div>

                    <div>
                        <label class="field-label">Starting Invoice Number</label>
                        <input type="number" name="invoice_start" class="form-input"
                               min="1" placeholder="1001"
                               value="<?= htmlspecialchars($settings['invoice_start']) ?>">
                        <p class="settings-hint">Next invoice will be <?= htmlspecialchars($settings['invoice_prefix']) ?><?= htmlspecialchars($settings['invoice_start']) ?></p>
                    </div>

                </div>

                <div>
                    <label class="field-label">Invoice Payment Terms</label>
                    <textarea name="invoice_terms" class="form-input" rows="3"
                              placeholder="Payment terms and conditions..."><?= htmlspecialchars($settings['invoice_terms']) ?></textarea>
                    <p class="settings-hint">Appears at the bottom of every invoice</p>
                </div>
            </div>
        </div>

        <!-- Preview -->
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="icon-box" style="background:rgba(99,102,241,0.15)">
                    <i class="fas fa-eye text-indigo-400"></i>
                </div>
                <span class="settings-card-title">Invoice Preview</span>
            </div>
            <div class="settings-card-body">
                <div style="background:#f8fafc;border-radius:12px;padding:24px;color:#0f172a;font-family:'Plus Jakarta Sans',sans-serif">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px">
                        <div>
                            <div style="font-size:1.2rem;font-weight:800;color:#0f172a"><?= htmlspecialchars($settings['showroom_name']) ?></div>
                            <div style="font-size:0.75rem;color:#64748b"><?= htmlspecialchars($settings['showroom_tagline']) ?></div>
                            <?php if ($settings['showroom_phone']): ?>
                            <div style="font-size:0.75rem;color:#64748b;margin-top:4px"><i class="fas fa-phone" style="font-size:0.6rem"></i> <?= htmlspecialchars($settings['showroom_phone']) ?></div>
                            <?php endif; ?>
                            <?php if ($settings['showroom_email']): ?>
                            <div style="font-size:0.75rem;color:#64748b"><i class="fas fa-envelope" style="font-size:0.6rem"></i> <?= htmlspecialchars($settings['showroom_email']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="text-align:right">
                            <div style="font-size:1.5rem;font-weight:800;color:#2563eb"><?= htmlspecialchars($settings['invoice_prefix']) ?><?= htmlspecialchars($settings['invoice_start']) ?></div>
                            <div style="font-size:0.75rem;color:#94a3b8"><?= date($settings['date_format']) ?></div>
                        </div>
                    </div>
                    <div style="border-top:2px solid #e2e8f0;padding-top:12px;font-size:0.72rem;color:#94a3b8">
                        <?= htmlspecialchars($settings['invoice_terms']) ?>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

    <!-- Save Button -->
    <div class="flex justify-between items-center py-4 sticky bottom-0 px-4 -mx-4 rounded-xl"
         style="background:rgba(10,15,30,0.95);backdrop-filter:blur(12px);border-top:1px solid rgba(148,163,184,0.08)">
        <a href="/car-showroom/dashboard/index.php"
           style="color:#475569;font-size:0.85rem;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:6px">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <button type="submit" class="btn-save">
            <i class="fas fa-save"></i> Save Settings
        </button>
    </div>

    </form>
</div>
</div>

<script>
function previewLogo(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const preview = input.closest('.settings-card-body').querySelector('.logo-preview');
        preview.innerHTML = `<img src="${e.target.result}" alt="Logo" style="width:100%;height:100%;object-fit:contain">`;
    };
    reader.readAsDataURL(input.files[0]);
}
</script>
</body>
</html>