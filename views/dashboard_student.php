<?php
require_once __DIR__ . '/../config/config.php';
Auth::requireLogin();
$user = Auth::user();

$pdo = Database::pdo();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user->id]);
$unreadCount = (int)$stmt->fetchColumn();
?>
<div class="row g-4">
  <div class="col-md-4">
    <div class="card p-4 text-center">
      <i class="bi bi-calendar-event fs-1 text-primary mb-3"></i>
      <h5>Upcoming Events</h5>
      <p class="text-muted">See what's happening soon and join in.</p>
      <a href="events.php" class="btn btn-primary btn-sm">View Events</a>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-4 text-center">
      <i class="bi bi-bell fs-1 text-info mb-3"></i>
      <h5>Notifications</h5>
      <p class="text-muted">Stay updated on registrations.</p>
      <a href="notifications.php" class="btn btn-info btn-sm text-white">
        View Notifications
        <?php if ($unreadCount > 0): ?>
          <span class="badge bg-danger ms-1"><?= $unreadCount ?></span>
        <?php endif; ?>
      </a>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-4 text-center">
      <i class="bi bi-chat-dots fs-1 text-success mb-3"></i>
      <h5>Give Feedback</h5>
      <p class="text-muted">Only for events you've joined.</p>
      <a href="feedback.php" class="btn btn-success btn-sm">Feedback</a>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-4 text-center">
      <i class="bi bi-person-gear fs-1 text-warning mb-3"></i>
      <h5>Profile</h5>
      <p class="text-muted">Edit profile and change password.</p>
      <a href="account.php" class="btn btn-warning btn-sm text-white">Manage Profile</a>
    </div>
  </div>
</div>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
  <link rel="stylesheet" href="../public/assets/css/style.css">
</head>
<body>
  <?php
  $stmt = Database::pdo()->prepare("
      SELECT e.id, e.name, e.organizer, e.venue, e.event_date, e.start_at, e.end_at
      FROM events e
      JOIN event_participants ep ON ep.event_id = e.id
      WHERE ep.user_id = ?
      ORDER BY e.event_date ASC, e.start_at ASC
  ");
  $stmt->execute([$user->id]);
  $myEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
  ?>

  <div class="mt-5">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-check2-circle me-2 text-primary"></i> My Registered Events</h4>
    <div>
      <button class="btn btn-outline-secondary btn-sm me-1" onclick="setView('list')">
        <i class="bi bi-list"></i> List
      </button>
      <button class="btn btn-outline-secondary btn-sm" onclick="setView('card')">
        <i class="bi bi-grid-3x3-gap"></i> Card
      </button>
    </div>
  </div>

  <?php if ($myEvents): ?>
    <!-- Scrollable container -->
    <div id="myEvents" class="list-view scrollable-module">
      <?php foreach ($myEvents as $ev): ?>
        <div class="event-item">
          <strong><?= e($ev['name']) ?></strong><br>
          <small class="text-muted">
            <i class="bi bi-calendar-event me-1"></i>
            <?= date('M d, Y', strtotime($ev['event_date'])) ?>
            <?= e(substr($ev['start_at'],0,5)) ?> - <?= e(substr($ev['end_at'],0,5)) ?>
            @ <?= e($ev['venue']) ?>
          </small><br>
          <a href="event.php?id=<?= (int)$ev['id'] ?>" class="btn btn-outline-primary btn-sm mt-1">View</a>
          <a href="cancel_registration.php?id=<?= (int)$ev['id'] ?>"
             class="btn btn-outline-danger btn-sm mt-1"
             onclick="return confirm('Cancel your registration for this event?');">
             Cancel
          </a>
      </div>
      <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="alert alert-info">You haven’t registered for any events yet.</div>
    <?php endif; ?>
  </div>
  <script>
    function setView(view) {
      const container = document.getElementById('myEvents');
      if (!container) return;

      // Switch classes
      container.classList.remove('list-view', 'card-view');
      container.classList.add(view + '-view');

      // Save preference
      localStorage.setItem('myEventsView', view);
    }

    // On page load, restore preference
    document.addEventListener('DOMContentLoaded', () => {
      const savedView = localStorage.getItem('myEventsView');
      if (savedView) {
        setView(savedView);
      }
    });
  </script>
</body>
</html>
