<?php
require_once __DIR__ . '/../config/config.php';

$errors = [];
$success = '';
$token = $_GET['token'] ?? '';

if (!$token) {
    redirect('forgot_password.php');
}

$stmt = Database::pdo()->prepare(
    'SELECT * FROM password_resets WHERE token = ? LIMIT 1'
);
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset || new DateTime() > new DateTime($reset['expires_at'])) {
    $errors[] = 'Invalid or expired reset link.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Password must be 8+ chars and include letters and numbers.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = Database::pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $reset['user_id']]);

        $stmt = Database::pdo()->prepare('DELETE FROM password_resets WHERE id = ?');
        $stmt->execute([$reset['id']]);

        $success = 'Password has been reset. You can now <a href="login.php">log in</a>.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Reset Password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h1 class="h4 mb-3">Reset Password</h1>
          <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
          <?php endif; ?>
          <?php if ($errors): ?>
            <div class="alert alert-danger">
              <ul class="mb-0">
                <?php foreach ($errors as $msg): ?>
                  <li><?= e($msg) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <?php if (!$success): ?>
          <form method="post">
            <div class="mb-3">
              <label class="form-label">New Password</label>
              <input type="password" name="password" class="form-control" required minlength="8"
                     pattern="^(?=.*[A-Za-z])(?=.*\d).{8,}$">
              <div class="form-text">8+ chars, include letters and numbers.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Confirm Password</label>
              <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <div class="d-grid">
              <button class="btn btn-primary" type="submit">Reset Password</button>
            </div>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>