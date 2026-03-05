<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('settings.manage');

$db = Database::getInstance();

// ── Ensure tables exist ──
$db->execute("CREATE TABLE IF NOT EXISTS custom_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    field_name VARCHAR(100) NOT NULL,
    field_label VARCHAR(150) NOT NULL,
    field_type ENUM('text','number','dropdown','checkbox','textarea') DEFAULT 'text',
    field_options TEXT NULL COMMENT 'Comma-separated options for dropdown',
    placeholder VARCHAR(200) NULL,
    is_required TINYINT(1) DEFAULT 0,
    show_in_list TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$db->execute("CREATE TABLE IF NOT EXISTS car_custom_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    car_id INT NOT NULL,
    field_id INT NOT NULL,
    field_value TEXT,
    UNIQUE KEY unique_car_field (car_id, field_id),
    FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE,
    FOREIGN KEY (field_id) REFERENCES custom_fields(id) ON DELETE CASCADE
)");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors  = [];
$success = false;
$action  = $_GET['action'] ?? 'list';
$editId  = (int)($_GET['id'] ?? 0);

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request.';
    } else {
        $postAction = clean($_POST['action'] ?? '');

        // ── Save field (add or edit) ──
        if ($postAction === 'save_field') {
            $label      = clean($_POST['field_label'] ?? '');
            $type       = clean($_POST['field_type'] ?? 'text');
            $options    = clean($_POST['field_options'] ?? '');
            $placeholder= clean($_POST['placeholder'] ?? '');
            $required   = isset($_POST['is_required']) ? 1 : 0;
            $showInList = isset($_POST['show_in_list']) ? 1 : 0;
            $sortOrder  = (int)($_POST['sort_order'] ?? 0);
            $fieldId    = (int)($_POST['field_id'] ?? 0);

            if (empty($label)) {
                $errors[] = 'Field label is required.';
            } else {
                // Generate field_name from label
                $name = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $label));
                $name = trim($name, '_');

                if ($fieldId > 0) {
                    // Update existing
                    $db->execute(
                        "UPDATE custom_fields SET
                            field_label=?, field_type=?, field_options=?,
                            placeholder=?, is_required=?, show_in_list=?, sort_order=?
                         WHERE id=?",
                        [$label, $type, $options, $placeholder, $required, $showInList, $sortOrder, $fieldId],
                        'ssssiiii'
                    );
                    setFlash('success', 'Field updated successfully.');
                } else {
                    // Check name uniqueness
                    $existing = $db->fetchOne(
                        "SELECT id FROM custom_fields WHERE field_name=?",
                        [$name], 's'
                    );
                    if ($existing) $name .= '_' . time();

                    $db->execute(
                        "INSERT INTO custom_fields
                            (field_name, field_label, field_type, field_options, placeholder, is_required, show_in_list, sort_order)
                         VALUES (?,?,?,?,?,?,?,?)",
                        [$name, $label, $type, $options, $placeholder, $required, $showInList, $sortOrder],
                        'ssssssii'
                    );
                    setFlash('success', 'Custom field created successfully.');
                }
                header('Location: custom-fields.php');
                exit;
            }
        }

        // ── Toggle active ──
        if ($postAction === 'toggle') {
            $fid = (int)($_POST['field_id'] ?? 0);
            $db->execute(
                "UPDATE custom_fields SET is_active = 1 - is_active WHERE id=?",
                [$fid], 'i'
            );
            header('Location: custom-fields.php');
            exit;
        }

        // ── Delete field ──
        if ($postAction === 'delete') {
            $fid = (int)($_POST['field_id'] ?? 0);
            $db->execute("DELETE FROM custom_fields WHERE id=?", [$fid], 'i');
            setFlash('success', 'Field deleted.');
            header('Location: custom-fields.php');
            exit;
        }

        // ── Reorder ──
        if ($postAction === 'reorder') {
            $order = $_POST['order'] ?? [];
            foreach ($order as $pos => $fid) {
                $db->execute(
                    "UPDATE custom_fields SET sort_order=? WHERE id=?",
                    [(int)$pos, (int)$fid], 'ii'
                );
            }
            echo json_encode(['ok' => true]);
            exit;
        }
    }
}

// ── Load fields ──
$fields = $db->fetchAll(
    "SELECT * FROM custom_fields ORDER BY sort_order ASC, id ASC"
);

// ── Load edit target ──
$editField = null;
if ($action === 'edit' && $editId > 0) {
    $editField = $db->fetchOne(
        "SELECT * FROM custom_fields WHERE id=?", [$editId], 'i'
    );
}

$flash     = getFlash();
$pageTitle = 'Custom Fields';
$pageSub   = 'Add extra fields to your inventory form';

$fieldTypes = [
    'text'     => ['label' => 'Text',     'icon' => 'fa-font',          'color' => '#60a5fa'],
    'number'   => ['label' => 'Number',   'icon' => 'fa-hashtag',       'color' => '#4ade80'],
    'dropdown' => ['label' => 'Dropdown', 'icon' => 'fa-caret-down',    'color' => '#a78bfa'],
    'checkbox' => ['label' => 'Checkbox', 'icon' => 'fa-square-check',  'color' => '#fbbf24'],
    'textarea' => ['label' => 'Textarea', 'icon' => 'fa-align-left',    'color' => '#2dd4bf'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Fields — AutoManager Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/car-showroom/public/css/fa/all.min.css">
    <link rel="stylesheet" href="/car-showroom/public/css/layout.css?v=<?= time() ?>">
    <style>
        .cf-card {
            background: #0d1526;
            border: 1px solid rgba(148,163,184,0.08);
            border-radius: 16px; overflow: hidden;
        }
        .cf-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(148,163,184,0.07);
            display: flex; align-items: center; justify-content: space-between;
        }
        .cf-card-title { font-size: 0.9rem; font-weight: 700; color: #e2e8f0; }

        /* Form inputs */
        label.field-label {
            display: block; font-size: 0.72rem; font-weight: 700;
            color: #475569; text-transform: uppercase;
            letter-spacing: 0.07em; margin-bottom: 6px;
        }
        .form-input {
            width: 100%;
            background: rgba(30,41,59,0.8);
            border: 1.5px solid rgba(148,163,184,0.1);
            color: #f1f5f9; font-size: 0.875rem;
            padding: 10px 14px; border-radius: 10px;
            transition: border-color 0.2s, box-shadow 0.2s;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .form-input:focus {
            outline: none; border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.12);
        }
        .form-input::placeholder { color: #334155; }
        select.form-input option { background: #1e293b; }

        /* Type selector */
        .type-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
        .type-btn {
            padding: 10px 6px; border-radius: 10px;
            border: 1.5px solid rgba(148,163,184,0.1);
            background: rgba(30,41,59,0.5);
            cursor: pointer; text-align: center;
            transition: all 0.2s;
        }
        .type-btn:hover { border-color: rgba(148,163,184,0.25); }
        .type-btn.selected { border-color: #3b82f6; background: rgba(37,99,235,0.1); }
        .type-btn input { display: none; }
        .type-btn .type-icon { font-size: 1rem; margin-bottom: 5px; }
        .type-btn .type-label { font-size: 0.68rem; font-weight: 700; color: #64748b; }
        .type-btn.selected .type-label { color: #60a5fa; }

        /* Toggle switch */
        .toggle-wrap { display: flex; align-items: center; gap: 8px; cursor: pointer; }
        .toggle { position: relative; width: 40px; height: 22px; flex-shrink: 0; }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute; inset: 0; border-radius: 22px;
            background: rgba(30,41,59,0.8);
            border: 1.5px solid rgba(148,163,184,0.12);
            transition: all 0.2s; cursor: pointer;
        }
        .toggle-slider::before {
            content: ''; position: absolute;
            width: 14px; height: 14px; border-radius: 50%;
            left: 3px; top: 50%; transform: translateY(-50%);
            background: #475569; transition: all 0.2s;
        }
        .toggle input:checked + .toggle-slider {
            background: rgba(37,99,235,0.25);
            border-color: rgba(37,99,235,0.4);
        }
        .toggle input:checked + .toggle-slider::before {
            transform: translate(18px, -50%);
            background: #60a5fa;
        }
        .toggle-label { font-size: 0.82rem; color: #64748b; font-weight: 500; }

        /* Field list rows */
        .field-row {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 20px;
            border-bottom: 1px solid rgba(148,163,184,0.05);
            transition: background 0.15s;
        }
        .field-row:last-child { border-bottom: none; }
        .field-row:hover { background: rgba(148,163,184,0.02); }
        .field-row.inactive { opacity: 0.45; }

        .drag-handle {
            color: #334155; cursor: grab; padding: 4px;
            font-size: 0.85rem; flex-shrink: 0;
        }
        .drag-handle:active { cursor: grabbing; }

        .type-pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 10px; border-radius: 20px;
            font-size: 0.68rem; font-weight: 700;
            background: rgba(30,41,59,0.8);
            border: 1px solid rgba(148,163,184,0.1);
            white-space: nowrap;
        }

        .action-btn {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 11px; border-radius: 7px;
            font-size: 0.75rem; font-weight: 600;
            text-decoration: none; transition: all 0.15s;
            border: 1px solid transparent; cursor: pointer;
            background: none;
        }
        .btn-edit   { background: rgba(245,158,11,0.1);  color: #fbbf24; border-color: rgba(245,158,11,0.2); }
        .btn-edit:hover { background: rgba(245,158,11,0.2); }
        .btn-delete { background: rgba(220,38,38,0.1);   color: #f87171; border-color: rgba(220,38,38,0.2); }
        .btn-delete:hover { background: rgba(220,38,38,0.2); }
        .btn-toggle-on  { background: rgba(22,163,74,0.1);  color: #4ade80; border-color: rgba(22,163,74,0.2); }
        .btn-toggle-off { background: rgba(100,116,139,0.1); color: #64748b; border-color: rgba(100,116,139,0.2); }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff; font-weight: 700; font-size: 0.875rem;
            padding: 10px 24px; border-radius: 10px;
            border: none; cursor: pointer; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 7px;
            box-shadow: 0 4px 16px rgba(37,99,235,0.25);
        }
        .btn-primary:hover { transform: translateY(-1px); }

        .empty-state {
            text-align: center; padding: 60px 20px;
            color: #334155;
        }

        /* Inline form panel */
        .form-panel {
            background: rgba(37,99,235,0.04);
            border: 1px solid rgba(37,99,235,0.12);
            border-radius: 14px; padding: 24px;
            margin-bottom: 20px;
        }

        .required-star { color: #f87171; margin-left: 2px; }

        /* Preview badge */
        .preview-pill {
            display: inline-flex; align-items: center; gap: 4px;
            background: rgba(13,148,136,0.1);
            border: 1px solid rgba(13,148,136,0.2);
            color: #2dd4bf; font-size: 0.65rem; font-weight: 700;
            padding: 2px 7px; border-radius: 4px;
        }
    </style>
</head>
<body>
<?php require_once '../../views/layouts/sidebar.php'; ?>
<div class="main">
<?php require_once '../../views/layouts/topbar.php'; ?>
<div class="content-area" style="max-width:900px">

    <?php if ($flash): ?>
    <div class="mb-5 px-4 py-3 rounded-xl text-sm flex items-center gap-2
        <?= $flash['type']==='success'
            ? 'bg-green-500/10 border border-green-500/20 text-green-400'
            : 'bg-red-500/10 border border-red-500/20 text-red-400' ?>">
        <i class="fas fa-<?= $flash['type']==='success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
        <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="mb-5 px-4 py-3 rounded-xl text-sm bg-red-500/10 border border-red-500/20 text-red-400">
        <?php foreach ($errors as $e): ?>
        <div class="flex items-center gap-2"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Info Banner -->
    <div class="mb-5 px-4 py-3 rounded-xl flex items-start gap-3"
         style="background:rgba(37,99,235,0.06);border:1px solid rgba(37,99,235,0.12)">
        <i class="fas fa-circle-info text-blue-400 mt-0.5 flex-shrink-0"></i>
        <p style="font-size:0.82rem;color:#64748b;line-height:1.6">
            Custom fields appear in the <strong style="color:#94a3b8">Add Car</strong> and
            <strong style="color:#94a3b8">Edit Car</strong> forms under a
            <strong style="color:#94a3b8">Custom Details</strong> section.
            Drag rows to reorder. Toggle to show/hide without deleting.
        </p>
    </div>

    <!-- ═══════════ ADD / EDIT FORM ═══════════ -->
    <div class="form-panel" id="fieldForm" <?= ($action !== 'edit') ? 'style="display:none"' : '' ?>>
        <div class="flex items-center justify-between mb-5">
            <h3 style="color:#e2e8f0;font-weight:700;font-size:0.95rem">
                <i class="fas fa-<?= $editField ? 'pen' : 'plus-circle' ?> text-blue-400 mr-2"></i>
                <?= $editField ? 'Edit Field' : 'New Custom Field' ?>
            </h3>
            <button type="button" onclick="hideForm()"
                    style="color:#475569;font-size:0.8rem;background:none;border:none;cursor:pointer">
                <i class="fas fa-xmark"></i> Cancel
            </button>
        </div>

        <form method="POST" id="cfForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="save_field">
            <input type="hidden" name="field_id" value="<?= $editField['id'] ?? 0 ?>">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="field-label">Field Label <span class="required-star">*</span></label>
                    <input type="text" name="field_label" class="form-input"
                           placeholder="e.g. Auction Grade, Import Country"
                           value="<?= htmlspecialchars($editField['field_label'] ?? '') ?>"
                           required>
                    <p style="font-size:0.7rem;color:#334155;margin-top:4px">
                        This is what users see in the form
                    </p>
                </div>
                <div>
                    <label class="field-label">Placeholder Text</label>
                    <input type="text" name="placeholder" class="form-input"
                           placeholder="e.g. Enter grade A, B, C..."
                           value="<?= htmlspecialchars($editField['placeholder'] ?? '') ?>">
                </div>
            </div>

            <!-- Field Type -->
            <div class="mb-4">
                <label class="field-label">Field Type</label>
                <div class="type-grid" id="typeGrid">
                    <?php foreach ($fieldTypes as $val => $ft): ?>
                    <label class="type-btn <?= ($editField['field_type'] ?? 'text') === $val ? 'selected' : '' ?>"
                           onclick="selectType(this, '<?= $val ?>')">
                        <input type="radio" name="field_type" value="<?= $val ?>"
                               <?= ($editField['field_type'] ?? 'text') === $val ? 'checked' : '' ?>>
                        <div class="type-icon" style="color:<?= $ft['color'] ?>">
                            <i class="fas <?= $ft['icon'] ?>"></i>
                        </div>
                        <div class="type-label"><?= $ft['label'] ?></div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Dropdown options (shown only for dropdown type) -->
            <div class="mb-4" id="optionsRow"
                 style="<?= ($editField['field_type'] ?? '') !== 'dropdown' ? 'display:none' : '' ?>">
                <label class="field-label">Dropdown Options <span class="required-star">*</span></label>
                <input type="text" name="field_options" class="form-input"
                       placeholder="Option 1, Option 2, Option 3"
                       value="<?= htmlspecialchars($editField['field_options'] ?? '') ?>">
                <p style="font-size:0.7rem;color:#334155;margin-top:4px">
                    Comma-separated list of options
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
                <div>
                    <label class="field-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-input"
                           min="0" value="<?= $editField['sort_order'] ?? count($fields) ?>">
                </div>
                <div class="flex items-end pb-1">
                    <label class="toggle-wrap">
                        <label class="toggle">
                            <input type="checkbox" name="is_required"
                                   <?= !empty($editField['is_required']) ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <span class="toggle-label">Required field</span>
                    </label>
                </div>
                <div class="flex items-end pb-1">
                    <label class="toggle-wrap">
                        <label class="toggle">
                            <input type="checkbox" name="show_in_list"
                                   <?= !empty($editField['show_in_list']) ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <span class="toggle-label">Show in car list</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" onclick="hideForm()"
                        class="action-btn btn-delete" style="padding:9px 18px">
                    Cancel
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i>
                    <?= $editField ? 'Update Field' : 'Save Field' ?>
                </button>
            </div>
        </form>
    </div>

    <!-- ═══════════ FIELDS LIST ═══════════ -->
    <div class="cf-card">
        <div class="cf-card-header">
            <span class="cf-card-title">
                <i class="fas fa-sliders text-blue-400 mr-2"></i>
                Custom Fields
                <span style="font-size:0.75rem;color:#334155;font-weight:400;margin-left:6px">
                    <?= count($fields) ?> field<?= count($fields) !== 1 ? 's' : '' ?>
                </span>
            </span>
            <button onclick="showForm()" class="btn-primary" style="padding:8px 18px;font-size:0.8rem">
                <i class="fas fa-plus"></i> Add Field
            </button>
        </div>

        <?php if (empty($fields)): ?>
        <div class="empty-state">
            <i class="fas fa-sliders text-4xl mb-3 block opacity-20"></i>
            <p style="font-weight:600;color:#475569;font-size:0.9rem">No custom fields yet</p>
            <p style="font-size:0.8rem;color:#334155;margin-top:6px">
                Add fields like "Auction Grade", "Import Country", "Sunroof" etc.
            </p>
            <button onclick="showForm()" class="btn-primary" style="margin-top:20px">
                <i class="fas fa-plus"></i> Add Your First Field
            </button>
        </div>
        <?php else: ?>

        <div id="fieldList">
            <?php foreach ($fields as $f):
                $ft = $fieldTypes[$f['field_type']] ?? $fieldTypes['text'];
            ?>
            <div class="field-row <?= !$f['is_active'] ? 'inactive' : '' ?>"
                 data-id="<?= $f['id'] ?>">

                <!-- Drag handle -->
                <span class="drag-handle" title="Drag to reorder">
                    <i class="fas fa-grip-vertical"></i>
                </span>

                <!-- Type icon -->
                <div style="width:34px;height:34px;border-radius:9px;
                            background:rgba(255,255,255,0.04);
                            display:flex;align-items:center;justify-content:center;
                            flex-shrink:0">
                    <i class="fas <?= $ft['icon'] ?>" style="color:<?= $ft['color'] ?>;font-size:0.85rem"></i>
                </div>

                <!-- Label + meta -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span style="color:#e2e8f0;font-weight:700;font-size:0.875rem">
                            <?= htmlspecialchars($f['field_label']) ?>
                        </span>
                        <?php if ($f['is_required']): ?>
                        <span class="required-star" title="Required">★</span>
                        <?php endif; ?>
                        <?php if ($f['show_in_list']): ?>
                        <span class="preview-pill"><i class="fas fa-table-list"></i> In List</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:0.7rem;color:#334155;margin-top:2px;font-family:monospace">
                        <?= htmlspecialchars($f['field_name']) ?>
                        <?php if ($f['field_type'] === 'dropdown' && $f['field_options']): ?>
                        <span style="font-family:'Plus Jakarta Sans',sans-serif;color:#475569;margin-left:8px">
                            · <?= htmlspecialchars(substr($f['field_options'], 0, 40)) ?>
                            <?= strlen($f['field_options']) > 40 ? '...' : '' ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Type pill -->
                <span class="type-pill hidden sm:inline-flex" style="color:<?= $ft['color'] ?>">
                    <i class="fas <?= $ft['icon'] ?>" style="font-size:0.65rem"></i>
                    <?= $ft['label'] ?>
                </span>

                <!-- Actions -->
                <div class="flex items-center gap-2 flex-shrink-0">
                    <!-- Toggle active -->
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="field_id" value="<?= $f['id'] ?>">
                        <button type="submit"
                                class="action-btn <?= $f['is_active'] ? 'btn-toggle-on' : 'btn-toggle-off' ?>"
                                title="<?= $f['is_active'] ? 'Disable' : 'Enable' ?>">
                            <i class="fas fa-<?= $f['is_active'] ? 'eye' : 'eye-slash' ?>"></i>
                        </button>
                    </form>

                    <!-- Edit -->
                    <a href="?action=edit&id=<?= $f['id'] ?>"
                       class="action-btn btn-edit"
                       onclick="scrollToForm()">
                        <i class="fas fa-pen"></i>
                    </a>

                    <!-- Delete -->
                    <button onclick="confirmDelete(<?= $f['id'] ?>, '<?= htmlspecialchars($f['field_label'], ENT_QUOTES) ?>')"
                            class="action-btn btn-delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>

    <!-- Usage guide -->
    <div class="mt-5 p-4 rounded-xl" style="background:rgba(13,148,136,0.05);border:1px solid rgba(13,148,136,0.12)">
        <div style="font-size:0.8rem;font-weight:700;color:#2dd4bf;margin-bottom:8px">
            <i class="fas fa-code mr-2"></i> How to use in inventory forms
        </div>
        <p style="font-size:0.78rem;color:#475569;line-height:1.7">
            Add this PHP snippet inside your <code style="background:rgba(255,255,255,0.06);padding:1px 5px;border-radius:4px;color:#94a3b8">modules/inventory/add.php</code>
            and <code style="background:rgba(255,255,255,0.06);padding:1px 5px;border-radius:4px;color:#94a3b8">edit.php</code>
            to render active custom fields automatically:
        </p>
        <pre style="background:rgba(15,23,42,0.8);border:1px solid rgba(148,163,184,0.08);
                    border-radius:10px;padding:14px;margin-top:10px;
                    font-size:0.72rem;color:#94a3b8;overflow-x:auto;line-height:1.8"><?= htmlspecialchars(
'$customFields = $db->fetchAll(
    "SELECT * FROM custom_fields WHERE is_active=1 ORDER BY sort_order ASC"
);
// Render in form:
foreach ($customFields as $cf) {
    // Renders text, number, dropdown, checkbox, textarea
}
// On save — loop and insert into car_custom_values') ?></pre>
    </div>

</div><!-- /content-area -->
</div><!-- /main -->

<!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden items-center justify-center"
     style="background:rgba(0,0,0,0.7);backdrop-filter:blur(4px)">
    <div class="rounded-2xl p-6 w-full max-w-sm mx-4"
         style="background:#0d1526;border:1px solid rgba(148,163,184,0.1)">
        <div class="w-12 h-12 bg-red-500/10 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-trash text-red-400 text-xl"></i>
        </div>
        <h3 class="text-white text-center font-700 text-base mb-2">Delete Field?</h3>
        <p class="text-slate-400 text-center text-sm mb-1" id="deleteFieldName"></p>
        <p class="text-slate-600 text-center text-xs mb-6">
            All saved values for this field will also be deleted from existing cars.
        </p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="field_id" id="deleteFieldId" value="">
            <div class="flex gap-3">
                <button type="button" onclick="closeDelete()"
                        class="flex-1 py-2.5 rounded-xl text-sm font-600 text-slate-400"
                        style="background:rgba(30,41,59,0.8);border:1px solid rgba(148,163,184,0.1)">
                    Cancel
                </button>
                <button type="submit"
                        class="flex-1 py-2.5 rounded-xl text-sm font-700 text-white bg-red-600 hover:bg-red-700 transition-all">
                    Delete Field
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Form show/hide ──
function showForm() {
    const f = document.getElementById('fieldForm');
    f.style.display = 'block';
    f.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
function hideForm() {
    document.getElementById('fieldForm').style.display = 'none';
    window.history.replaceState({}, '', 'custom-fields.php');
}
function scrollToForm() {
    setTimeout(() => {
        const f = document.getElementById('fieldForm');
        if (f) f.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);
}

// ── Type selector ──
function selectType(el, type) {
    document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    el.querySelector('input').checked = true;
    // Show/hide options row
    document.getElementById('optionsRow').style.display =
        type === 'dropdown' ? 'block' : 'none';
}

// ── Delete modal ──
function confirmDelete(id, name) {
    document.getElementById('deleteFieldName').textContent = '"' + name + '"';
    document.getElementById('deleteFieldId').value = id;
    const m = document.getElementById('deleteModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function closeDelete() {
    const m = document.getElementById('deleteModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

// ── Drag-to-reorder ──
(function () {
    const list = document.getElementById('fieldList');
    if (!list) return;

    let dragging = null;

    list.querySelectorAll('.field-row').forEach(row => {
        row.setAttribute('draggable', true);

        row.addEventListener('dragstart', e => {
            dragging = row;
            row.style.opacity = '0.4';
        });
        row.addEventListener('dragend', e => {
            row.style.opacity = '1';
            dragging = null;
            saveOrder();
        });
        row.addEventListener('dragover', e => {
            e.preventDefault();
            if (dragging && dragging !== row) {
                const rows = [...list.querySelectorAll('.field-row')];
                const idx  = rows.indexOf(row);
                const dragIdx = rows.indexOf(dragging);
                if (dragIdx < idx) {
                    row.after(dragging);
                } else {
                    row.before(dragging);
                }
            }
        });
    });

    function saveOrder() {
        const ids = [...list.querySelectorAll('.field-row')].map(r => r.dataset.id);
        const fd  = new FormData();
        fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
        fd.append('action', 'reorder');
        ids.forEach((id, i) => fd.append('order[' + i + ']', id));
        fetch('custom-fields.php', { method: 'POST', body: fd });
    }
})();

<?php if ($action === 'edit'): ?>
// Auto-show form on edit
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('fieldForm').style.display = 'block';
});
<?php endif; ?>
</script>
</body>
</html>