<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('users.manage');

Auth::check();
if (!Auth::hasRole(['Admin'])) {
    setFlash('error', 'Access denied.');
    redirect('modules/users/index.php');
}

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    setFlash('error', 'Invalid user.');
    redirect('modules/users/index.php');
}

// Prevent self-delete
if ($id === Auth::id()) {
    setFlash('error', 'You cannot delete your own account.');
    redirect('modules/users/index.php');
}

$db   = Database::getInstance();
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$id], 'i');

if (!$user) {
    setFlash('error', 'User not found.');
    redirect('modules/users/index.php');
}

// Soft delete — set inactive instead of hard delete
// to preserve sales/leads history
$db->execute(
    "UPDATE users SET status='inactive', email=CONCAT(email,'_deleted_',NOW()) WHERE id=?",
    [$id], 'i'
);

setFlash('success', htmlspecialchars($user['full_name']) . ' has been deactivated.');
redirect('modules/users/index.php');