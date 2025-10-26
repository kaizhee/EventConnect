<?php
include __DIR__ . '/../includes/page_nav.php';
require_once __DIR__ . '/../config/config.php';
Auth::requireLogin();
$user = Auth::user();
$pdo = Database::pdo();

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC");
$stmt->execute([$user->id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user->id]);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Notifications | EventConnect</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
<div class="container py-4">
  <h1 class="h4 mb-3"><i class="bi bi-bell me-2"></i>Notifications</h1>
  <?php if (!$notifications): ?>
    <div class="alert alert-info">No notifications yet.</div>
  <?php endif; ?>
  <?php foreach ($notifications as $n): ?>
    <div class="alert <?= $n['is_read'] ? 'alert-secondary' : 'alert-primary' ?> mb-2">
      <?= e($n['message']) ?>
      <small class="text-muted d-block"><?= $n['created_at'] ?></small>
    </div>
  <?php endforeach; ?>
</div>
</body>
</html>