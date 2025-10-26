<?php
include __DIR__ . '/../includes/page_nav.php';
require_once __DIR__ . '/../config/config.php';
Auth::requireLogin();
$user = Auth::user();
$pdo = Database::pdo();

require_once __DIR__ . '/../src/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../src/PHPMailer/SMTP.php';
require_once __DIR__ . '/../src/PHPMailer/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// detect role
$roleStmt = $pdo->prepare("
  SELECT r.slug FROM roles r 
  JOIN user_roles ur ON ur.role_id=r.id 
  WHERE ur.user_id = ?
");
$roleStmt->execute([$user->id]);
$roleSlugs = $roleStmt->fetchAll(PDO::FETCH_COLUMN);

$isCouncil = in_array('student_council', $roleSlugs, true);
$isAffair  = in_array('student_affair',  $roleSlugs, true);
if (!$isCouncil && !$isAffair) redirect('dashboard.php');

$success = $error = '';

// handle decision
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eventId = (int)($_POST['event_id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    $comment = trim($_POST['comment'] ?? '');

    if ($isCouncil) {
        if ($decision === 'approve') {
            $stmt = $pdo->prepare("UPDATE events SET status='pending_affair', council_comment=? WHERE id=? AND status='pending_council'");
            $stmt->execute([$comment, $eventId]);
            $success = 'Moved to Student Affair for final approval.';
        } elseif ($decision === 'reject') {
            $stmt = $pdo->prepare("UPDATE events SET status='rejected', council_comment=? WHERE id=? AND (status='pending_council' OR status='pending_affair')");
            $stmt->execute([$comment, $eventId]);
            $success = 'Event rejected with comment.';
        }
    }
    if ($isAffair) {
        if ($decision === 'approve') {
            $stmt = $pdo->prepare("UPDATE events SET status='approved', affair_comment=? WHERE id=? AND status='pending_affair'");
            $stmt->execute([$comment, $eventId]);
            $success = 'Event approved.';

            // Fetch event details
            $evStmt = $pdo->prepare("SELECT * FROM events WHERE id=?");
            $evStmt->execute([$eventId]);
            $ev = $evStmt->fetch(PDO::FETCH_ASSOC);

            // Get all registered students
            $stuStmt = $pdo->prepare("
                SELECT u.id, u.name, u.email
                FROM event_participants ep
                JOIN users u ON u.id = ep.user_id
                WHERE ep.event_id=?
            ");
            $stuStmt->execute([$eventId]);
            $participants = $stuStmt->fetchAll(PDO::FETCH_ASSOC);

            // Insert in-app notifications
            foreach ($participants as $stu) {
                $msg = "Event '{$ev['name']}' has been updated and approved. Please check the new details.";
                $ins = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $ins->execute([$stu['id'], $msg]);
            }


            foreach ($participants as $stu) {
                try {
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'tangkaizhe8330@gmail.com';
                    $mail->Password   = 'rowutuweausoqqsp'; // App Password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('tangkaizhe8330@gmail.com', 'EventConnect');
                    $mail->addAddress($stu['email'], $stu['name']);

                    $mail->isHTML(true);
                    $mail->Subject = "Updated Event Details: {$ev['name']}";
                    $mail->Body    = "
                        <h3>Event Updated & Approved</h3>
                        <p><strong>Event:</strong> {$ev['name']}</p>
                        <p><strong>Organizer:</strong> {$ev['organizer']}</p>
                        <p><strong>Date:</strong> " . date('F d, Y', strtotime($ev['event_date'])) . "</p>
                        <p><strong>Time:</strong> " . substr($ev['start_at'],0,5) . " - " . substr($ev['end_at'],0,5) . "</p>
                        <p><strong>Venue:</strong> {$ev['venue']}</p>
                        <hr>
                        <p>The event details have been updated after approval. Please review before attending.</p>
                    ";

                    $mail->send();
                } catch (Exception $e) {
                    error_log("Mailer Error to {$stu['email']}: " . $mail->ErrorInfo);
                }
    }
        } elseif ($decision === 'reject') {
            $stmt = $pdo->prepare("UPDATE events SET status='rejected', affair_comment=? WHERE id=? AND status IN ('pending_affair','approved')");
            $stmt->execute([$comment, $eventId]);
            $success = 'Event rejected with comment.';
        }
        if ($decision === 'approve_delete') {
            $stmt = $pdo->prepare("DELETE FROM events WHERE id=? AND delete_requested=1");
            $stmt->execute([$eventId]);
            $success = 'Event deleted as requested.';
        }
    }
}

// fetch items
if ($isCouncil) {
    $council = $pdo->query("
      SELECT e.id, e.name, u.name AS creator, e.event_date, e.venue, e.description, e.poster_path
      FROM events e
      JOIN users u ON u.id = e.created_by
      WHERE e.status='pending_council'
      ORDER BY e.created_at ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} else { $council = []; }

if ($isAffair) {
    $affair = $pdo->query("
      SELECT e.id, e.name, u.name AS creator, e.event_date, e.venue, e.description, e.delete_requested, e.council_comment, e.poster_path
      FROM events e
      JOIN users u ON u.id = e.created_by
      WHERE e.status='pending_affair' OR e.delete_requested=1
      ORDER BY e.created_at ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} else { $affair = []; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Approvals | EventConnect</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../public/assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
<div class="container py-4 fade-in">
  <h1 class="h4 mb-3"><i class="bi bi-check2-square me-2"></i>Approvals</h1>

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

  <?php if ($isCouncil): ?>
    <h2 class="h5 mt-4"><i class="bi bi-people-fill me-2"></i>Student Council — First-level</h2>
    <?php if (!$council): ?><div class="alert alert-info">No pending items.</div><?php endif; ?>
    <?php foreach ($council as $ev): ?>
      <div class="card mb-3">
        <div class="card-body">
          <div class="row g-3 align-items-start">
            
            <!-- Poster on the left -->
            <?php if (!empty($ev['poster_path'])): ?>
              <div class="col-md-4 text-center">
                <img src="../public/uploads/posters/<?= e($ev['poster_path']) ?>"
                    alt="Event Poster"
                    class="img-fluid rounded shadow-sm"
                    style="max-height:250px; object-fit:cover;">
              </div>
            <?php endif; ?>

            <!-- Event details on the right -->
            <div class="col-md-8">
              <h5 class="card-title"><?= e($ev['name']) ?></h5>
              <p class="mb-1"><strong>Creator:</strong> <?= e($ev['creator']) ?></p>
              <p class="mb-1"><strong>Date:</strong> <?= e($ev['event_date']) ?> | 
                <strong>Venue:</strong> <?= e($ev['venue']) ?></p>
              <p><?= nl2br(e($ev['description'])) ?></p>

              <!-- Action form -->
              <form method="post" class="d-flex flex-wrap gap-2">
                <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
                <input type="text" class="form-control" name="comment" placeholder="Optional comment">
                <button name="decision" value="approve" class="btn btn-success">
                  <i class="bi bi-check-lg me-1"></i>Approve → Affair
                </button>
                <button name="decision" value="reject" class="btn btn-danger">
                  <i class="bi bi-x-lg me-1"></i>Reject
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($isAffair): ?>
    <h2 class="h5 mt-4"><i class="bi bi-building me-2"></i>Student Affair — Final approval</h2>
    <?php if (!$affair): ?><div class="alert alert-info">No pending items.</div><?php endif; ?>
    <?php foreach ($affair as $ev): ?>
      <div class="card mb-3">
        <div class="card-body">
          <div class="row g-3 align-items-start">
            
            <!-- Poster on the left -->
            <?php if (!empty($ev['poster_path'])): ?>
              <div class="col-md-4 text-center">
                <img src="../public/uploads/posters/<?= e($ev['poster_path']) ?>"
                    alt="Event Poster"
                    class="img-fluid rounded shadow-sm"
                    style="max-height:250px; object-fit:cover;">
              </div>
            <?php endif; ?>

            <!-- Event details on the right -->
            <div class="col-md-8">
              <h5 class="card-title"><?= e($ev['name']) ?></h5>
              <p class="mb-1"><strong>Creator:</strong> <?= e($ev['creator']) ?></p>
              <p class="mb-1"><strong>Date:</strong> <?= e($ev['event_date']) ?> | 
                <strong>Venue:</strong> <?= e($ev['venue']) ?></p>
              <p><?= nl2br(e($ev['description'])) ?></p>

              <!-- Council comment -->
              <?php if (!empty($ev['council_comment'])): ?>
                <div class="mb-2 p-2 border rounded bg-light">
                  <strong>Student Council Comment:</strong><br>
                  <?= nl2br(e($ev['council_comment'])) ?>
                </div>
              <?php endif; ?>

              <!-- Action form -->
              <form method="post" class="d-flex flex-wrap gap-2">
                <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
                <input type="text" class="form-control" name="comment" placeholder="Optional comment">
                <button name="decision" value="approve" class="btn btn-success">
                  <i class="bi bi-check-lg me-1"></i>Approve
                </button>
                <button name="decision" value="reject" class="btn btn-danger">
                  <i class="bi bi-x-lg me-1"></i>Reject
                </button>
                <?php if (!empty($ev['delete_requested'])): ?>
                  <button name="decision" value="approve_delete" class="btn btn-outline-danger">
                    <i class="bi bi-trash me-1"></i>Approve Deletion
                  </button>
                <?php endif; ?>
              </form>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>