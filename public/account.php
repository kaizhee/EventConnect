<?php
include __DIR__ . '/../includes/page_nav.php';
require_once __DIR__ . '/../config/config.php';
Auth::requireLogin();
$pdo = Database::pdo();

// Fetch current user
$user = Auth::user();

$success = $error = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['profile'])) {
        $name = trim($_POST['name'] ?? '');
        $student_id = trim($_POST['student_id'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $course = trim($_POST['course'] ?? '');
        $club_name = trim($_POST['club_name'] ?? '');

        if ($name === '') {
            $error = 'Name is required.';
        } else {
            if (Auth::hasRole('club_admin')) {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, student_id = ?, contact = ?, course = ?, club_name = ? WHERE id = ?");
                $stmt->execute([$name, $student_id, $contact, $course, $club_name, $user->id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, student_id = ?, contact = ?, course = ? WHERE id = ?");
                $stmt->execute([$name, $student_id, $contact, $course, $user->id]);
            }

            // Force reload from DB so new values appear immediately
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user->id]);
            $_SESSION['user'] = $stmt->fetch(PDO::FETCH_OBJ);

            header("Location: account.php?success=Profile+updated.");
            exit;
        }
    }

    if (isset($_POST['password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user->password_hash)) {
            $error = 'Current password is incorrect.';
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
            $error = 'New password must be 8+ chars and include letters and numbers.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $user->id]);

            // Force reload from DB
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user->id]);
            $_SESSION['user'] = $stmt->fetch(PDO::FETCH_OBJ);

            header("Location: account.php?success=Password+changed.");
            exit;
        }
    }
}

// Handle GET success message
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Reload user to ensure fresh data
$user = Auth::user(true);

// Check if profile is incomplete
$required = ['name','student_id','contact','course'];
$incomplete = false;
foreach ($required as $field) {
    if (empty($user->$field)) {
        $incomplete = true;
        break;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Account | EventConnect</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
<div class="container py-4 fade-in">
  <h1 class="h4 mb-3"><i class="bi bi-person-gear me-2"></i>Manage Account</h1>

  <?php if ($incomplete): ?>
    <div class="alert alert-warning d-flex align-items-center mb-3">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      <div>Please complete your profile details before you can start using the system.</div>
    </div>
  <?php endif; ?>

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

  <div class="row g-4">
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header bg-primary text-white fw-semibold">
          <i class="bi bi-person-lines-fill me-2"></i>Profile
        </div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="profile" value="1">
            <div class="mb-3">
              <label class="form-label">Name</label>
              <input class="form-control" name="name" value="<?= e($user->name) ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Student ID</label>
              <input class="form-control" name="student_id" value="<?= e($user->student_id ?? '') ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Contact</label>
              <input class="form-control" name="contact" value="<?= e($user->contact ?? '') ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Course</label>
              <input class="form-control" name="course" value="<?= e($user->course ?? '') ?>" required>
            </div>
            <button class="btn btn-primary w-100"><i class="bi bi-save me-1"></i>Save Profile</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header bg-warning text-dark fw-semibold">
          <i class="bi bi-shield-lock-fill me-2"></i>Change Password
        </div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="password" value="1">
            <div class="mb-3">
              <label class="form-label">Current Password</label>
              <input type="password" class="form-control" name="current_password" required>
            </div>
            <div class="mb-3">
              <label class="form-label">New Password</label>
              <input type="password" class="form-control" name="new_password" required minlength="8" pattern="^(?=.*[A-Za-z])(?=.*\d).{8,}$">
              <div class="form-text">8+ chars, include letters and numbers.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Confirm Password</label>
              <input type="password" class="form-control" name="confirm_password" required>
            </div>
            <button class="btn btn-warning w-100 text-white"><i class="bi bi-arrow-repeat me-1"></i>Change Password</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>