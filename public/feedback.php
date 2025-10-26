<?php
include __DIR__ . '/../includes/page_nav.php';
require_once __DIR__ . '/../config/config.php';
Auth::requireLogin();
$user = Auth::user();
$pdo  = Database::pdo();

// List events user joined (past or today, so we can check end time later)
$stmt = $pdo->prepare("
  SELECT e.id, e.name, e.event_date, e.end_at
  FROM events e
  JOIN event_participants p ON p.event_id = e.id
  WHERE p.user_id = ?
  ORDER BY e.event_date DESC
");
$stmt->execute([$user->id]);
$joined = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle feedback submit
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eventId = (int)($_POST['event_id'] ?? 0);
    $rating  = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    $survey  = $_POST['survey'] ?? [];

    // Verify user joined AND event has ended
    $chk = $pdo->prepare("
        SELECT CONCAT(e.event_date, ' ', e.end_at) AS event_end
        FROM events e
        JOIN event_participants p ON p.event_id = e.id
        WHERE p.user_id = ? AND e.id = ?
    ");
    $chk->execute([$user->id, $eventId]);
    $eventEnd = $chk->fetchColumn();

    if (!$eventEnd) {
        $error = 'You can only give feedback on events you joined.';
    } elseif (strtotime($eventEnd) > time()) {
        $error = 'Feedback is only allowed after the event has ended.';
    } elseif ($rating < 1 || $rating > 5) {
        $error = 'Please select a valid rating.';
    } else {
        // Check if feedback already exists
        $exists = $pdo->prepare("SELECT 1 FROM feedbacks WHERE user_id=? AND event_id=?");
        $exists->execute([$user->id, $eventId]);

        if ($exists->fetchColumn()) {
            $error = 'You have already submitted feedback for this event. Only one submission is allowed.';
        } else {
            $surveyJson = json_encode($survey, JSON_UNESCAPED_UNICODE);
            $ins = $pdo->prepare("
                INSERT INTO feedbacks (user_id, event_id, rating, comment, survey_json, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $ins->execute([$user->id, $eventId, $rating, $comment, $surveyJson]);
            $success = 'Thank you! Your feedback has been recorded.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Feedback | EventConnect</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../public/assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
<div class="container py-4 fade-in">
  <h1 class="h4 mb-3"><i class="bi bi-chat-left-text me-2"></i>Feedback</h1>

  <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="bi bi-check-circle-fill me-2"></i><?= e($success) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if ($joined): ?>
    <form method="post" class="card p-3">
      <div class="mb-3">
        <label class="form-label">Event</label>
        <select name="event_id" class="form-select" required>
          <?php foreach ($joined as $ev): ?>
            <option value="<?= (int)$ev['id'] ?>">
              <?= e($ev['name']) ?> (<?= e($ev['event_date']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label">Overall Rating</label>
        <select name="rating" class="form-select" required>
          <option value="">Select...</option>
          <option value="5">⭐⭐⭐⭐⭐ Excellent</option>
          <option value="4">⭐⭐⭐⭐ Good</option>
          <option value="3">⭐⭐⭐ Average</option>
          <option value="2">⭐⭐ Poor</option>
          <option value="1">⭐ Bad</option>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label">Comment</label>
        <textarea name="comment" class="form-control" rows="3" maxlength="1000" placeholder="Your feedback..."></textarea>
      </div>

      <!-- Expanded Survey -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Event Organization</label>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="survey[schedule]" value="On Time" id="sch1">
          <label class="form-check-label" for="sch1">Event started and ended on time</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="survey[schedule]" value="Delayed" id="sch2">
          <label class="form-check-label" for="sch2">Event schedule was delayed</label>
        </div>
        <div class="form-check mt-2">
          <input class="form-check-input" type="radio" name="survey[communication]" value="Clear" id="com1">
          <label class="form-check-label" for="com1">Pre‑event communication was clear</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="survey[communication]" value="Unclear" id="com2">
          <label class="form-check-label" for="com2">Pre‑event communication was unclear</label>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Content & Activities</label>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="survey[type][]" value="Speaker" id="en1">
          <label class="form-check-label" for="en1">Guest Speaker</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="survey[type][]" value="Workshop" id="en2">
          <label class="form-check-label" for="en2">Workshop / Activity</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="survey[type][]" value="Networking" id="en3">
          <label class="form-check-label" for="en3">Networking / Games</label>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Venue & Logistics</label>
        <select class="form-select" name="survey[venue_rating]">
          <option value="">Venue rating...</option>
          <option value="5">⭐⭐⭐⭐⭐ Excellent</option>
          <option value="4">⭐⭐⭐⭐ Good</option>
          <option value="3">⭐⭐⭐ Average</option>
          <option value="2">⭐⭐ Poor</option>
          <option value="1">⭐ Bad</option>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Open Feedback</label>
        <textarea class="form-control mb-2" name="survey[highlight]" rows="2" placeholder="What was the highlight?"></textarea>
        <textarea class="form-control" name="survey[improvement]" rows="2" placeholder="What can we improve?"></textarea>
      </div>

      <button class="btn btn-primary w-100"><i class="bi bi-send me-1"></i>Submit Feedback</button>
    </form>
  <?php else: ?>
<?php
echo '<div class="alert alert-info alert-dismissible fade show" role="alert">';
echo '<span class="bi bi-info-circle-fill me-2"></span> You haven\'t joined any events yet.';
echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
echo '</div>';
?>
<?php endif; ?>
</div> <!-- closes .container -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>