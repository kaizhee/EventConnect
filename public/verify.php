<?php
require_once __DIR__ . '/../config/config.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');
    $userId = $_SESSION['pending_user_id'] ?? 0;

    if (!$userId) {
        redirect('login.php');
    }

    $stmt = Database::pdo()->prepare(
        'SELECT * FROM email_verifications WHERE user_id = ? AND verified = 0 LIMIT 1'
    );
    $stmt->execute([$userId]);
    $record = $stmt->fetch();

    if (!$record) {
        $errors[] = 'No verification request found.';
    } elseif (new DateTime() > new DateTime($record['expires_at'])) {
        $errors[] = 'OTP expired. Please register again.';
    } elseif ($otp !== $record['otp_code']) {
        $errors[] = 'Invalid OTP.';
    } else {
        // Mark verified
        $stmt = Database::pdo()->prepare(
            'UPDATE email_verifications SET verified = 1 WHERE id = ?'
        );
        $stmt->execute([$record['id']]);

        // Log the user in
        $_SESSION['user_id'] = $userId;
        unset($_SESSION['pending_user_id']);
        redirect('dashboard.php');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Email Verification</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h1 class="h4 mb-3">Verify Your Email</h1>
          <?php if ($errors): ?>
            <div class="alert alert-danger">
              <ul class="mb-0">
                <?php foreach ($errors as $msg): ?>
                  <li><?= e($msg) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <form method="post">
            <div class="mb-3">
              <label class="form-label">Enter OTP</label>
              <input type="text" name="otp" class="form-control" required maxlength="6">
            </div>
            <div class="d-grid">
              <button class="btn btn-primary" type="submit">Verify</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>