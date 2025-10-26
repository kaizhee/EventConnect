<?php
require_once __DIR__ . '/../config/config.php';
Auth::requireLogin();
$pdo = Database::pdo();
$user = Auth::user();

require_once __DIR__ . '/../includes/profile_check.php';
include __DIR__ . '/../includes/page_nav.php';

// Upcoming, approved events only
$stmt = Database::pdo()->prepare("
  SELECT id, name, organizer, venue, start_at, end_at, event_date, poster_path
  FROM events
  WHERE status = 'approved' AND event_date >= CURDATE()
  ORDER BY event_date ASC, start_at ASC
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's registrations to mark joined
$joinedStmt = Database::pdo()->prepare("SELECT event_id FROM event_participants WHERE user_id = ?");
$joinedStmt->execute([$user->id]);
$joined = array_flip($joinedStmt->fetchAll(PDO::FETCH_COLUMN));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Events | EventConnect</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<div class="container py-4">
  <h1 class="h4 mb-3">Upcoming Events</h1>
  <div class="row g-4">
    <?php foreach ($events as $ev): ?>
      <div class="col-md-4">
        <div class="card h-100">
          <?php if (!empty($ev['poster_path'])): ?>
              <img src="/eventConnect/public/uploads/posters/<?= e($ev['poster_path']) ?>" alt="Event Poster" class="img-fluid" style="max-width: 250px; height: auto;">
          <?php endif; ?>
          <div class="card-body">
            <h5 class="card-title"><?= e($ev['name']) ?></h5>
            <p class="card-text mb-1"><strong>Organizer:</strong> <?= e($ev['organizer']) ?></p>
            <p class="card-text mb-1"><strong>Date:</strong> <?= e($ev['event_date']) ?></p>
            <p class="card-text mb-1"><strong>Time:</strong> <?= e(substr($ev['start_at'],0,5)) ?> - <?= e(substr($ev['end_at'],0,5)) ?></p>
            <p class="card-text"><strong>Venue:</strong> <?= e($ev['venue']) ?></p>
          </div>
          <div class="card-footer bg-white">
            <a href="event.php?id=<?= (int)$ev['id'] ?>" class="btn btn-outline-primary btn-sm">View details</a>
            <?php if (isset($joined[$ev['id']])): ?>
              <span class="badge bg-success ms-2">Joined</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$events): ?>
      <div class="col-12"><div class="alert alert-info">No upcoming events yet.</div></div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>