<?php
// Sanitize input
function clean($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Generate car slug: toyota-corolla-2019-white
function generateSlug($make, $model, $year, $color) {
    $slug = strtolower("$make-$model-$year-$color");
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

// Generate invoice number: INV-2025-0001
function generateInvoiceNo($lastId) {
    return 'INV-' . date('Y') . '-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);
}

// Format price in PKR
function formatPrice($amount) {
    return 'PKR ' . number_format($amount, 0);
}

// Calculate net profit
function calculateProfit($salePrice, $purchasePrice, $extraCosts = 0) {
    return $salePrice - $purchasePrice - $extraCosts;
}

// Log car timeline event
function logTimeline($carId, $eventType, $description, $doneBy = null) {
    $db = Database::getInstance();
    $db->insert(
        "INSERT INTO car_timeline (car_id, event_type, description, done_by) VALUES (?, ?, ?, ?)",
        [$carId, $eventType, $description, $doneBy],
        'issi'
    );
}

// Log price change
function logPriceChange($carId, $oldPrice, $newPrice, $changedBy, $reason = '') {
    $db = Database::getInstance();
    $db->insert(
        "INSERT INTO car_price_history (car_id, old_price, new_price, changed_by, reason) VALUES (?, ?, ?, ?, ?)",
        [$carId, $oldPrice, $newPrice, $changedBy, $reason],
        'iddis'
    );
}

// Upload image with validation
function uploadImage($file, $folder = 'cars') {
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowed)) {
        return ['success' => false, 'error' => 'Only JPG, PNG, WebP allowed'];
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Max file size is 5MB'];
    }

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $path     = UPLOAD_PATH . $folder . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $path)) {
        return ['success' => true, 'filename' => $folder . '/' . $filename];
    }
    return ['success' => false, 'error' => 'Upload failed'];
}

// Redirect
function redirect($url) {
    header("Location: " . BASE_URL . "/" . $url);
    exit;
}

// Flash messages
function setFlash($type, $message) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
// Get a single setting value
function getSetting($key, $default = '') {
    $db  = Database::getInstance();
    $row = $db->fetchOne(
        "SELECT setting_value FROM settings WHERE setting_key = ?",
        [$key], 's'
    );
    return $row ? $row['setting_value'] : $default;
}
