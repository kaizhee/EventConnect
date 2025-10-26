<?php
require_once __DIR__ . '/../config/config.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = Auth::register($_POST);
    if (!$errors) {
        redirect('dashboard.php');
    } else {
        set_flash($errors, [
            'name' => $_POST['name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'role_id' => $_POST['role_id'] ?? ''
        ]);
        redirect('register.php');
    }
}

$errors = flash_errors();
// Only allow Student and Student Affairs Department to register
$roles = array_filter(Role::all(), function($r) {
    return in_array((int)$r['id'], [Role::STUDENT, Role::STUDENT_AFFAIR]);
});
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Register</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body{background:#f7f7fb}</style>
</head>
<body>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <div class="text-center mb-4">
            <img src="assets/images/logo.png" alt="EventConnect Logo" class="img-fluid" style="max-height: 150px;">
          </div>
          <h1 class="h4 mb-3">Create an account</h1>

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
              <label class="form-label">Full name</label>
              <input type="text" name="name" class="form-control" required minlength="2" value="<?= old('name') ?>">
              <div class="invalid-feedback">Please enter your name.</div>
            </div>

            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" required value="<?= old('email') ?>">
              <div class="invalid-feedback">Enter a valid email.</div>
            </div>

            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-control" required minlength="8"
                     pattern="^(?=.*[A-Za-z])(?=.*\d).{8,}$">
              <div class="form-text">8+ chars, include letters and numbers.</div>
              <div class="invalid-feedback">Password must be 8+ chars with letters and numbers.</div>
            </div>

            <div class="mb-3">
              <label class="form-label">Confirm password</label>
              <input type="password" name="confirm_password" class="form-control" required>
              <div class="invalid-feedback">Passwords must match.</div>
            </div>

            <div class="mb-3">
              <label class="form-label">Role</label>
              <select name="role_id" class="form-select" required>
                <option value="" disabled selected hidden>Select a role</option>
                <?php foreach ($roles as $r): ?>
                  <option value="<?= (int)$r['id'] ?>" <?= old('role_id') == (int)$r['id'] ? 'selected' : '' ?>>
                    <?= e(ucwords(str_replace('_', ' ', $r['name']))) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Please select your role.</div>
            </div>
            
            <!-- Consent checkbox -->
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="consent" id="consent" required>
              <label class="form-check-label" for="consent">
                I have read and agree to the 
                <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>.
              </label>
              <div class="invalid-feedback">You must agree before registering.</div>
            </div>

            <div class="d-grid">
              <button class="btn btn-primary" type="submit">Register</button>
            </div>
          </form>

          <hr class="my-4">
          <p class="mb-0">Already have an account? <a href="login.php">Log in</a></p>
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
      const pwd = form.querySelector('input[name="password"]');
      const conf = form.querySelector('input[name="confirm_password"]');
      if (pwd.value !== conf.value) {
        conf.setCustomValidity('Mismatch');
      } else {
        conf.setCustomValidity('');
      }
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });
})();
</script>

<!-- Terms & Conditions Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="termsModalLabel">Terms and Conditions</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Welcome to EventConnect. By registering, you agree to the following terms:</p>
        <h6>1. Membership Eligibility</h6>
        <ul>
          <li>Only current students and approved staff of [Your Institution Name] may register.</li>
          <li>Student Affairs Department accounts are for official use only.</li>
        </ul>
        <h6>2. Accurate Information</h6>
        <ul>
          <li>You must provide truthful, complete and up‑to‑date information during registration.</li>
          <li>Misrepresentation may result in suspension or removal from the system.</li>
        </ul>
        <h6>3. Code of Conduct</h6>
        <ul>
          <li>Treat all members with respect in communications and activities.</li>
          <li>No harassment, discrimination or disruptive behavior.</li>
        </ul>
        <h6>4. Use of Personal Data</h6>
        <ul>
          <li>Your name, email and role will be stored for account management and club operations.</li>
          <li>Event participation data may be shared with club administrators and Student Affairs for official purposes only.</li>
        </ul>
        <h6>5. Event Participation</h6>
        <ul>
          <li>Follow event‑specific rules and safety guidelines.</li>
          <li>The club and institution are not liable for personal injury or loss of belongings during events, except as required by law.</li>
        </ul>
        <h6>6. System Usage</h6>
        <ul>
          <li>Do not attempt to hack, disrupt or misuse the system.</li>
          <li>Accounts may be suspended for security violations.</li>
        </ul>
        <h6>7. Agreement</h6>
        <p>By ticking the consent box and registering, you confirm that you have read, understood and agree to these Terms & Conditions.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS (needed for modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>