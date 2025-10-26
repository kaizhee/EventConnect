<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Models/Club.php';

Auth::requireLogin();
$pdo  = Database::pdo();
$user = Auth::user();

require_once __DIR__ . '/../includes/profile_check.php';
include __DIR__ . '/../includes/page_nav.php';

function requireRole($slug) {
  $stmt = Database::pdo()->prepare("SELECT 1 FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=? AND r.slug=?");
  $stmt->execute([$GLOBALS['user']->id, $slug]);
  if (!$stmt->fetchColumn()) redirect('dashboard.php');
}
requireRole('club_admin');

$pdo = Database::pdo();
$success = $error = '';

// Get club info for this admin
$club = $user->club_id ? Club::find($user->club_id) : null;
if (!$club) {
    die("Your account is not assigned to a club. Please contact Student Affairs.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $venue = trim($_POST['venue'] ?? '');
    $start_at = $_POST['start_at'] ?? '';
    $end_at = $_POST['end_at'] ?? '';
    $event_date = $_POST['event_date'] ?? '';

    if ($name === '' || $venue === '' || !$event_date || !$start_at || !$end_at) {
        $error = 'All required fields must be filled.';
    } else {
        $posterPath = null;
        if (!empty($_FILES['poster']['name'])) {
            $uploadDir = __DIR__ . '/../public/uploads/posters/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext = strtolower(pathinfo($_FILES['poster']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp'];
            if (!in_array($ext, $allowed)) {
                $error = 'Poster must be JPG, PNG, or WEBP.';
            } else {
                $fname = 'poster_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $uploadDir . $fname;
                if (move_uploaded_file($_FILES['poster']['tmp_name'], $dest)) {
                    $posterPath = $fname;
                } else {
                    $error = 'Failed to upload poster.';
                }
            }
        }
        if (!$error) {
            $stmt = $pdo->prepare("
              INSERT INTO events (name, organizer, description, venue, start_at, end_at, event_date, poster_path, created_by, club_id, status, created_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_council', NOW())
            ");
            $stmt->execute([
                $name,
                $club['name'],   // organizer (legacy column)
                $description,
                $venue,
                $start_at,
                $end_at,
                $event_date,
                $posterPath,
                $user->id,
                $club['id']      // new club_id column
            ]);
            $success = 'Event submitted. Awaiting Student Council approval.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Create Event | EventConnect</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../public/assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
<div class="container py-4 fade-in">
  <h1 class="h4 mb-3"><i class="bi bi-plus-circle me-2"></i>Create Event</h1>

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

  <form method="post" enctype="multipart/form-data" class="card p-3">
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Event Name</label>
        <input class="form-control" name="name" placeholder="Enter event name" required>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Club</label>
        <input class="form-control" value="<?= e($club['name']) ?>" disabled>
        <div class="form-text">You can only create events for your assigned club.</div>
      </div>
      <div class="col-12 mb-3">
        <label class="form-label">Description</label>
        <textarea class="form-control" name="description" rows="3" placeholder="Describe your event..."></textarea>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Venue</label>
        <input class="form-control" name="venue" placeholder="Event venue" required>
      </div>
      <div class="col-md-3 mb-3">
        <label class="form-label">Start Time</label>
        <input type="time" class="form-control" name="start_at" required>
      </div>
      <div class="col-md-3 mb-3">
        <label class="form-label">End Time</label>
        <input type="time" class="form-control" name="end_at" required>
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label">Event Date</label>
        <input type="date" class="form-control" name="event_date" required>
      </div>
      <div class="col-md-8 mb-3">
        <label class="form-label">Poster</label>
        <input type="file" class="form-control" name="poster" accept=".jpg,.jpeg,.png,.webp">
      </div>
    </div>
    <button class="btn btn-primary w-100"><i class="bi bi-send me-1"></i>Submit for Approval</button>
  </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>