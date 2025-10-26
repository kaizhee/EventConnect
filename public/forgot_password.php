<?php
require_once __DIR__ . '/../config/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../src/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../src/PHPMailer/SMTP.php';
require_once __DIR__ . '/../src/PHPMailer/Exception.php';

$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email.';
    } else {
        $user = User::findByEmail($email);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = (new DateTime('+30 minutes'))->format('Y-m-d H:i:s');

            $stmt = Database::pdo()->prepare(
                'INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)'
            );
            $stmt->execute([$user->id, $token, $expires]);

            $resetLink = APP_URL . '/reset_password.php?token=' . urlencode($token);

            // Send reset link via Gmail SMTP using PHPMailer
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'tangkaizhe8330@gmail.com'; // Gmail
                $mail->Password   = 'rowutuweausoqqsp'; // 16-char app password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('tangkaizhe8330@gmail.com', 'EventConnect');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Password Reset - EventConnect';
                $mail->Body    = "<p>Click the link to reset your password:</p>
                                  <p><a href='{$resetLink}'>{$resetLink}</a></p>
                                  <p>This link expires in 30 minutes.</p>";

                $mail->send();
            } catch (Exception $e) {
                error_log("Mailer Error: {$mail->ErrorInfo}");
            }

            $success = 'If that email exists, a reset link has been sent.';
        } else {
            $success = 'If that email exists, a reset link has been sent.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Forgot Password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h1 class="h4 mb-3">Forgot Password</h1>
          <?php if ($success): ?>
            <div class="alert alert-info"><?= e($success) ?></div>
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
          <form method="post">
            <div class="mb-3">
              <label class="form-label">Registered Email</label>
              <input type="email" name="email" class="form-control" required>
            </div>
            <div class="d-grid">
              <button class="btn btn-primary" type="submit">Send Reset Link</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>