<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('inventory.delete');
Auth::check();
if (!Auth::hasRole(['Admin', 'Manager'])) {
    setFlash('error', 'You do not have permission to delete cars.');
    redirect('modules/inventory/index.php');
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    setFlash('error', 'Invalid car ID.');
    redirect('modules/inventory/index.php');
}

$db  = Database::getInstance();
$car = $db->fetchOne("SELECT * FROM cars WHERE id = ?", [$id], 'i');

if (!$car) {
    setFlash('error', 'Car not found.');
    redirect('modules/inventory/index.php');
}

// Delete images from disk
$images = $db->fetchAll("SELECT image_path FROM car_images WHERE car_id = ?", [$id], 'i');
foreach ($images as $img) {
    $path = UPLOAD_PATH . $img['image_path'];
    if (file_exists($path)) unlink($path);
}

// Delete car (cascades to images, costs, timeline etc.)
$db->execute("DELETE FROM cars WHERE id = ?", [$id], 'i');

setFlash('success', 'Car deleted successfully.');
redirect('modules/inventory/index.php');