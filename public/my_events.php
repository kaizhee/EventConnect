<?php
include __DIR__ . '/../includes/page_nav.php';
require_once __DIR__ . '/../config/config.php';
Auth::requireLogin();
$user = Auth::user();
$pdo = Database::pdo();
require_once __DIR__ . '/../includes/profile_check.php';

// Ensure role is club_admin
$chk = $pdo->prepare("SELECT 1 FROM roles r 
    JOIN user_roles ur ON ur.role_id=r.id 
    WHERE ur.user_id=? AND r.slug='club_admin'");
$chk->execute([$user->id]);
if (!$chk->fetchColumn()) redirect('dashboard.php');

// Fetch upcoming events (today or later)
$upcomingStmt = $pdo->prepare("
  SELECT e.*, 
         (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id=e.id) AS registrations
  FROM events e
  WHERE e.created_by=? AND e.event_date >= CURDATE()
  ORDER BY e.event_date ASC
");
$upcomingStmt->execute([$user->id]);
$upcoming = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch past events (before today)
$pastStmt = $pdo->prepare("
  SELECT e.*, 
         (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id=e.id) AS registrations,
         (SELECT COUNT(*) FROM feedbacks f WHERE f.event_id=e.id) AS feedback_count
  FROM events e
  WHERE e.created_by=? AND e.event_date < CURDATE()
  ORDER BY e.event_date DESC
");
$pastStmt->execute([$user->id]);
$past = $pastStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>My Events | EventConnect</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<div class="container py-4">
  <h1 class="h4 mb-3">My Events</h1>

  <!-- Upcoming Events -->
  <h2 class="h5 mt-4">Upcoming Events</h2>
  <?php if (!$upcoming): ?>
    <div class="alert alert-info">No upcoming events.</div>
  <?php else: ?>
    <table class="table table-striped">
      <thead>
        <tr>
          <th>Name</th><th>Date</th><th>Status</th><th>Registrations</th><th>Feedback</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($upcoming as $ev): ?>
        <tr>
          <td><?= e($ev['name']) ?></td>
          <td><?= e($ev['event_date']) ?></td>
          <td>
            <span class="badge bg-<?= $ev['status']==='approved'?'success':($ev['status']==='rejected'?'danger':'warning') ?>">
              <?= e(ucwords(str_replace('_',' ',$ev['status']))) ?>
            </span>
          </td>
          <td><?= (int)$ev['registrations'] ?></td>
          <td>—</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <!-- Past Events -->
  <h2 class="h5 mt-4">Past Events</h2>
  <?php if (!$past): ?>
    <div class="alert alert-info">No past events.</div>
  <?php else: ?>
    <table class="table table-striped">
      <thead>
        <tr>
          <th>Name</th><th>Date</th><th>Status</th><th>Registrations</th><th>Feedback</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($past as $ev): ?>
        <tr>
          <td><?= e($ev['name']) ?></td>
          <td><?= e($ev['event_date']) ?></td>
          <td>
            <span class="badge bg-<?= $ev['status']==='approved'?'success':($ev['status']==='rejected'?'danger':'secondary') ?>">
              <?= e(ucwords(str_replace('_',' ',$ev['status']))) ?>
            </span>
          </td>
          <td><?= (int)$ev['registrations'] ?></td>
          <td>
            <button class="btn btn-sm btn-outline-info" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#fb<?= (int)$ev['id'] ?>">
              View (<?= (int)$ev['feedback_count'] ?>)
            </button>
          </td>
        </tr>
        <tr class="collapse" id="fb<?= (int)$ev['id'] ?>">
          <td colspan="5">
            <?php
              $fbStmt = $pdo->prepare("
                SELECT f.rating, f.comment, f.survey_json, f.created_at, u.name 
                FROM feedbacks f
                JOIN users u ON u.id=f.user_id
                WHERE f.event_id=?
                ORDER BY f.created_at DESC
              ");
              $fbStmt->execute([$ev['id']]);
              $feedbacks = $fbStmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php if ($feedbacks): ?>
              <ul class="list-group">
                <?php foreach ($feedbacks as $fb): ?>
                  <li class="list-group-item">
                    <strong><?= e($fb['name']) ?></strong> 
                    <small class="text-muted">(<?= e($fb['created_at']) ?>)</small><br>
                    Rating: <?= str_repeat("⭐", (int)$fb['rating']) ?><br>
                    <?= nl2br(e($fb['comment'])) ?>

                    <?php if (!empty($fb['survey_json'])): ?>
                      <?php $survey = json_decode($fb['survey_json'], true); ?>
                      <?php if ($survey): ?>
                        <div class="mt-2">
                          <strong>Survey Responses:</strong>
                          <ul class="mb-0">
                            <?php foreach ($survey as $key => $val): ?>
                              <li>
                                <?= e(ucwords(str_replace('_',' ', $key))) ?>: 
                                <?php 
                                  if (is_array($val)) {
                                      echo e(implode(', ', $val));
                                  } else {
                                      echo e($val);
                                  }
                                ?>
                              </li>
                            <?php endforeach; ?>
                          </ul>
                        </div>
                      <?php endif; ?>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="alert alert-secondary mb-0">No feedback yet.</div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>