<?php
require_once __DIR__ . '/../config/config.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = Auth::login($_POST);
    if (!$errors) {
        redirect('dashboard.php');
    } else {
        set_flash($errors, ['email' => $_POST['email'] ?? '']);
        redirect('login.php');
    }
}
$errors = flash_errors();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body{background:#f7f7fb}</style>
</head>
<body>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <div class="text-center mb-4">
            <img src="assets/images/logo.png" alt="EventConnect Logo" class="img-fluid" style="max-height: 150px;">
          </div>
          <h1 class="h4 mb-3">Welcome back</h1>

          <?php if ($errors): ?>
            <div class="alert alert-danger">
              <ul class="mb-0">
                <?php foreach ($errors as $msg): ?>
                  <li><?= e($msg) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form class="needs-validation" novalidate method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" required value="<?= old('email') ?>">
              <div class="invalid-feedback">Enter a valid email.</div>
            </div>

            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-control" required>
              <div class="invalid-feedback">Password is required.</div>
            </div>

            <div class="d-grid">
              <button class="btn btn-primary" type="submit">Login</button>
            </div>
          </form>

          <p class="mt-3">
            <a href="forgot_password.php">Forgot your password?</a>
          </p>
          
          <hr class="my-4">
          <p class="mb-0">New Users? <a href="register.php">Register</a></p>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
(() => {
  'use strict';
  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(form => {
    form.addEventListener('submit', event => {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });
})();
</script>
</body>
</html>