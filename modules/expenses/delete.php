<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('expenses.delete');
Auth::check();
if (!Auth::hasRole(['Admin', 'Manager'])) {
    setFlash('error', 'Permission denied.');
    redirect('modules/expenses/index.php');
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    setFlash('error', 'Invalid expense ID.');
    redirect('modules/expenses/index.php');
}

$db      = Database::getInstance();
$expense = $db->fetchOne("SELECT * FROM expenses WHERE id = ?", [$id], 'i');

if (!$expense) {
    setFlash('error', 'Expense not found.');
    redirect('modules/expenses/index.php');
}

// Delete receipt image if exists
if ($expense['receipt_image']) {
    $path = UPLOAD_PATH . $expense['receipt_image'];
    if (file_exists($path)) unlink($path);
}

$db->execute("DELETE FROM expenses WHERE id = ?", [$id], 'i');
setFlash('success', 'Expense deleted successfully.');
redirect('modules/expenses/index.php');