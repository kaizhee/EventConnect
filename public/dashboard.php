<?php
require_once __DIR__ . '/../config/config.php';
Auth::requireLogin();
$user = Auth::user();

$pdo = Database::pdo();

// Count unread notifications for this user
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user->id]);
$unreadCount = (int)$stmt->fetchColumn();


// Fetch all role slugs for this user
function getRolesFor($userId) {
    $stmt = Database::pdo()->prepare("
        SELECT r.slug
        FROM roles r
        JOIN user_roles ur ON ur.role_id = r.id
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

$roles = getRolesFor($user->id);

function hasRole($roles, $slug) {
    return in_array($slug, $roles, true);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Dashboard | EventConnect</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    body { background-color: #f4f6f8; font-family: 'Segoe UI', sans-serif; }
    .sidebar { height: 100vh; background-color: #1f2937; color: white; padding-top: 2rem; position: fixed; width: 240px; }
    .sidebar a { color: #cbd5e1; text-decoration: none; display: block; padding: 0.75rem 1.5rem; border-radius: 0.5rem; margin: 0.25rem 1rem; transition: background 0.2s ease; }
    .sidebar a:hover, .sidebar a.active { background-color: #374151; color: white; }
    .main-content { margin-left: 240px; padding: 2rem; }
    .card { border: none; border-radius: 0.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: transform 0.2s ease; }
    .card:hover { transform: translateY(-3px); }
    .welcome { font-size: 1.5rem; font-weight: 600; }
    h2.section-title { margin-top: 2rem; margin-bottom: 1rem; font-size: 1.25rem; font-weight: 600; }
  </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
  <h4 class="text-center mb-4" style="color: white;">EventConnect</h4>
  <a href="dashboard.php" class="active"><i class="bi bi-house-door me-2"></i> Dashboard</a>

  <?php if (hasRole($roles, 'student')): ?>
    <div class="mt-3 px-3 text-uppercase small text-white">Student</div>
    <a href="events.php"><i class="bi bi-calendar-event me-2"></i> Events</a>
    <a href="notifications.php" class="nav-link">
      <i class="bi bi-bell me-2"></i> Notifications
      <?php if ($unreadCount > 0): ?>
        <span class="badge bg-danger"><?= $unreadCount ?></span>
      <?php endif; ?>
    </a>
    <a href="feedback.php"><i class="bi bi-chat-left-text me-2"></i> Feedback</a>
    <a href="account.php"><i class="bi bi-person-gear me-2"></i> Profile</a>
  <?php endif; ?>

  <?php if (hasRole($roles, 'club_admin')): ?>
    <div class="mt-3 px-3 text-uppercase small text-white">Club Admin</div>
    <a href="create_event.php"><i class="bi bi-plus-circle me-2"></i> Create Event</a>
    <a href="manage_events.php"><i class="bi bi-pencil-square me-2"></i> Manage Events</a>
    <a href="my_events.php"><i class="bi bi-calendar-event me-2"></i> My Events</a>
    <a href="participants.php"><i class="bi bi-people me-2"></i> Participants</a>
  <?php endif; ?>

  <?php if (hasRole($roles, 'student_council')): ?>
    <div class="mt-3 px-3 text-uppercase small text-white">Student Council</div>
    <a href="approval.php"><i class="bi bi-check2-square me-2"></i> Approvals (Council)</a>
  <?php endif; ?>

  <?php if (hasRole($roles, 'student_affair')): ?>
    <div class="mt-3 px-3 text-uppercase small text-white">Student Affairs</div>
    <a href="approval.php"><i class="bi bi-check2-square me-2"></i> Approvals (Affair)</a>
    <a href="promote_demote.php"><i class="bi bi-person-gear me-2"></i> Promote/Demote</a>
    <a href="analytics_affair.php"><i class="bi bi-bar-chart-line me-2"></i> Analytics</a>
  <?php endif; ?>

  <a href="logout.php" class="text-danger"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
</div>

<!-- Main Content -->
<div class="main-content">
  <div class="welcome mb-4">Welcome back, <?= e($user->name) ?></div>

  <?php if (hasRole($roles, 'student')): ?>
    <h2 class="section-title">Student Modules</h2>
    <?php include __DIR__ . '/../views/dashboard_student.php'; ?>
  <?php endif; ?>

  <?php if (hasRole($roles, 'club_admin')): ?>
    <h2 class="section-title">Club Admin Modules</h2>
    <?php include __DIR__ . '/../views/dashboard_club_admin.php'; ?>
  <?php endif; ?>

  <?php if (hasRole($roles, 'student_council')): ?>
    <h2 class="section-title">Student Council Modules</h2>
    <?php include __DIR__ . '/../views/dashboard_council.php'; ?>
  <?php endif; ?>

  <?php if (hasRole($roles, 'student_affair')): ?>
    <h2 class="section-title">Student Affairs Modules</h2>
    <?php include __DIR__ . '/../views/dashboard_affair.php'; ?>
  <?php endif; ?>
</div>

</body>
</html>