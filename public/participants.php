<?php
include __DIR__ . '/../includes/page_nav.php';
require_once __DIR__ . '/../config/config.php';
Auth::requireLogin();
$user = Auth::user();
$pdo = Database::pdo();
require_once __DIR__ . '/../includes/profile_check.php';

// role check
$chk = $pdo->prepare("SELECT 1 FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=? AND r.slug='club_admin'");
$chk->execute([$user->id]);
if (!$chk->fetchColumn()) redirect('dashboard.php');

// Which event (owned by this club admin)?
$eventId = (int)($_GET['event_id'] ?? 0);

// Export CSV
if (isset($_GET['export']) && $eventId) {
    $own = $pdo->prepare("SELECT 1 FROM events WHERE id = ? AND created_by = ?");
    $own->execute([$eventId, $user->id]);
    if (!$own->fetchColumn()) redirect('participants.php');

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="participants_event_'.$eventId.'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name','Student ID','Email','Contact','Course','Joined At']);
    $q = $pdo->prepare("
      SELECT u.name, u.student_id, u.email, u.contact, u.course, p.joined_at
      FROM event_participants p
      JOIN users u ON u.id = p.user_id
      WHERE p.event_id = ?
      ORDER BY u.name
    ");
    $q->execute([$eventId]);
    while ($row = $q->fetch(PDO::FETCH_NUM)) fputcsv($out, $row);
    fclose($out);
    exit;
}

// List admin's events
$my = $pdo->prepare("SELECT id, name FROM events WHERE created_by = ? ORDER BY created_at DESC");
$my->execute([$user->id]);
$myEvents = $my->fetchAll(PDO::FETCH_ASSOC);

// Participants for selected event
$participants = [];
if ($eventId) {
    $own = $pdo->prepare("SELECT 1 FROM events WHERE id = ? AND created_by = ?");
    $own->execute([$eventId, $user->id]);
    if ($own->fetchColumn()) {
        $q = $pdo->prepare("
          SELECT u.name, u.student_id, u.email, u.contact, u.course, p.joined_at
          FROM event_participants p
          JOIN users u ON u.id = p.user_id
          WHERE p.event_id = ?
          ORDER BY u.name
        ");
        $q->execute([$eventId]);
        $participants = $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Participants | EventConnect</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../public/assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
<div class="container py-4 fade-in">
  <h1 class="h4 mb-3"><i class="bi bi-people me-2"></i>Participants</h1>

  <form class="row g-2 mb-3" method="get">
    <div class="col-md-6">
      <select name="event_id" class="form-select" required>
        <option value="">Select your event</option>
        <?php foreach ($myEvents as $ev): ?>
          <option value="<?= (int)$ev['id'] ?>" <?= $eventId===$ev['id']?'selected':'' ?>><?= e($ev['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6 d-flex align-items-start gap-2">
      <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Load</button>
      <?php if ($eventId): ?>
        <a class="btn btn-outline-success" href="participants.php?event_id=<?= (int)$eventId ?>&export=1">
          <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
        </a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($eventId && !$participants): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
      <i class="bi bi-info-circle-fill me-2"></i>No participants yet.
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if ($participants): ?>
    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle">
        <thead>
          <tr>
            <th>Name</th><th>Student ID</th><th>Email</th><th>Contact</th><th>Course</th><th>Joined</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($participants as $p): ?>
            <tr>
              <td><?= e($p['name']) ?></td>
              <td><?= e($p['student_id']) ?></td>
              <td><?= e($p['email']) ?></td>
              <td><?= e($p['contact']) ?></td>
              <td><?= e($p['course']) ?></td>
              <td><?= e($p['joined_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>