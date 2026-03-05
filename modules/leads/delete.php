<?php
require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';
require_once '../../core/functions.php';
require_once '../../core/Permissions.php';

Auth::check();
Permissions::require('leads.delete');

Auth::check();
if (!Auth::hasRole(['Admin', 'Manager'])) {
    setFlash('error', 'Permission denied.');
    redirect('modules/leads/index.php');
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    setFlash('error', 'Invalid lead ID.');
    redirect('modules/leads/index.php');
}

$db   = Database::getInstance();
$lead = $db->fetchOne("SELECT * FROM leads WHERE id = ?", [$id], 'i');

if (!$lead) {
    setFlash('error', 'Lead not found.');
    redirect('modules/leads/index.php');
}

$db->execute("DELETE FROM leads WHERE id = ?", [$id], 'i');
setFlash('success', 'Lead deleted successfully.');
redirect('modules/leads/index.php');