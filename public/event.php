<?php
include __DIR__ . '/../includes/page_nav.php';
require_once __DIR__ . '/../config/config.php';
// Load PHPMailer classes (adjust path if needed)
require __DIR__ . '/../src/PHPMailer/PHPMailer.php';
require __DIR__ . '/../src/PHPMailer/SMTP.php';
require __DIR__ . '/../src/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

Auth::requireLogin();
$user = Auth::user();


$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('events.php');

$pdo = Database::pdo();
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND status = 'approved' LIMIT 1");
$stmt->execute([$id]);
$ev = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ev) redirect('events.php');

// Already joined?
$joinedStmt = $pdo->prepare("SELECT 1 FROM event_participants WHERE user_id = ? AND event_id = ?");
$joinedStmt->execute([$user->id, $id]);
$already = (bool)$joinedStmt->fetchColumn();

// Handle registration
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already) {
    try {
        // Insert participant
        $ins = $pdo->prepare("INSERT IGNORE INTO event_participants (user_id, event_id, joined_at) VALUES (?, ?, NOW())");
        $ins->execute([$user->id, $id]);
        $already = true;
        $success = 'You have registered for this event.';

        // Insert in-app notifications
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt->execute([$user->id, "You registered for '{$ev['name']}' on {$ev['event_date']} at {$ev['venue']}."]);

        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt->execute([$ev['created_by'], "{$user->name} registered for your event '{$ev['name']}'."]);

        // Try sending email, but don't break registration if it fails
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
            $mail->addAddress('tangkaizhe8330@gmail.com');

            $mail->isHTML(true);
            $mail->Subject = 'New Event Registration';
            $mail->Body = "
              <h3>New Event Registration</h3>
              <p><strong>Event:</strong> {$ev['name']}</p>
              <p><strong>Organizer:</strong> {$ev['organizer']}</p>
              <p><strong>Date:</strong> " . date('F d, Y', strtotime($ev['event_date'])) . "</p>
              <p><strong>Time:</strong> " . e(substr($ev['start_at'],0,5)) . " - " . e(substr($ev['end_at'],0,5)) . "</p>
              <p><strong>Venue:</strong> {$ev['venue']}</p>
              <hr>
              <p><strong>Registered Student:</strong> {$user->name}</p>
            ";

            $mail->send();
        } catch (Exception $e) {
            // Log or show mail error separately
            $error = 'Email notification failed: ' . $mail->ErrorInfo;
        }

    } catch (Throwable $e) {
        $error = 'Failed to register. Please try again.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= e($ev['name']) ?> | EventConnect</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<div class="container py-4">
  <a href="events.php" class="btn btn-link">&larr; Back</a>
  <div class="card">
    <div class="card-body">
      <h1 class="h4"><?= e($ev['name']) ?></h1>
      <p><strong>Organizer:</strong> <?= e($ev['organizer']) ?></p>
      <p><strong>Date:</strong> <?= e($ev['event_date']) ?></p>
      <p><strong>Time:</strong> <?= e(substr($ev['start_at'],0,5)) ?> - <?= e(substr($ev['end_at'],0,5)) ?></p>
      <p><strong>Venue:</strong> <?= e($ev['venue']) ?></p>
      <p><?= nl2br(e($ev['description'])) ?></p>

      <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

      <?php if (!$already): ?>
        <form method="post">
          <button class="btn btn-primary">Register</button>
        </form>
      <?php else: ?>
        <span class="badge bg-success">You have registered</span>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>