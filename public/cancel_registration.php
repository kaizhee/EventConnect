<?php
require_once __DIR__ . '/../config/config.php';
Auth::requireLogin();
$user = Auth::user();

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$eventId = (int)$_GET['id'];

// Delete the registration
$stmt = Database::pdo()->prepare("DELETE FROM event_participants WHERE user_id = ? AND event_id = ?");
$stmt->execute([$user->id, $eventId]);

// Redirect back
header("Location: dashboard.php?cancelled=1");
exit;