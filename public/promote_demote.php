<?php
include __DIR__ . '/../includes/page_nav.php';
require_once __DIR__ . '/../config/config.php';
Auth::requireLogin();
$user = Auth::user();
$pdo = Database::pdo();

// Only council or affair can manage roles
$roleChk = $pdo->prepare("
  SELECT r.slug FROM roles r 
  JOIN user_roles ur ON ur.role_id=r.id 
  WHERE ur.user_id=?
");
$roleChk->execute([$user->id]);
$slugs = $roleChk->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('student_council',$slugs,true) && !in_array('student_affair',$slugs,true)) {
    redirect('dashboard.php');
}

// Fetch roles but exclude Student Affairs from being assignable
$roles = $pdo->query("SELECT id, name, slug FROM roles WHERE slug != 'student_affair' ORDER BY name")
             ->fetchAll(PDO::FETCH_ASSOC);

$success = $error = '';
$targetRoles = [];
$targetClubId = null;

// Handle club management (only Student Affairs)
if (in_array('student_affair',$slugs,true)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_club'])) {
        $clubName = trim($_POST['new_club']);
        if ($clubName !== '') {
            $stmt = $pdo->prepare("INSERT INTO clubs (name) VALUES (?)");
            try {
                $stmt->execute([$clubName]);
                $success = "Club '$clubName' created successfully.";
            } catch (PDOException $e) {
                $error = "Error: Club may already exist.";
            }
        }
    }
    if (isset($_GET['delete_club'])) {
        $clubId = (int)$_GET['delete_club'];
        $stmt = $pdo->prepare("DELETE FROM clubs WHERE id=?");
        $stmt->execute([$clubId]);
        $success = "Club deleted.";
    }
}

// Fetch clubs for dropdown
$clubs = $pdo->query("SELECT id, name FROM clubs ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['new_club'])) {
    $targetEmail = strtolower(trim($_POST['email'] ?? ''));
    $assign = $_POST['assign'] ?? []; // role ids
    $clubId = $_POST['club_id'] ?? null;

    $u = $pdo->prepare("SELECT id FROM users WHERE email=?");
    $u->execute([$targetEmail]);
    $target = $u->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        $error = 'No user with that email.';
    } else {
        // Block assigning Student Affairs role
        $assign = array_filter($assign, fn($rid) => (int)$rid !== Role::STUDENT_AFFAIR);

        // Prevent Student Affairs from changing their own role
        if (in_array('student_affair', $slugs, true) && $target['id'] == $user->id) {
            $error = 'Student Affairs cannot change their own role.';
        } else {
            $pdo->beginTransaction();
            try {
                // Reset roles
                $pdo->prepare("DELETE FROM user_roles WHERE user_id=?")->execute([$target['id']]);
                foreach ($assign as $rid) {
                    $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$target['id'], (int)$rid]);
                }

                // If Club Admin assigned, require club_id
                if (in_array(Role::CLUB_ADMIN, array_map('intval',$assign), true)) {
                    $chk = $pdo->prepare("SELECT 1 FROM clubs WHERE id=?");
                    $chk->execute([$clubId]);
                    if (!$clubId || !$chk->fetchColumn()) {
                        $error = 'Please select a valid club for Club Admin.';
                    } else {
                        $pdo->prepare("UPDATE users SET club_id=? WHERE id=?")->execute([(int)$clubId, $target['id']]);
                    }
                } else {
                    // Clear club_id if not a club admin
                    $pdo->prepare("UPDATE users SET club_id=NULL WHERE id=?")->execute([$target['id']]);
                }

                if (!$error) {
                    $pdo->commit();
                    $success = 'Roles updated.';
                } else {
                    $pdo->rollBack();
                }
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = 'Failed to update roles.';
            }
        }
    }
}

// Show current roles of searched user
if (!empty($_GET['email'])) {
    $email = strtolower(trim($_GET['email']));
    $u = $pdo->prepare("SELECT id, club_id FROM users WHERE email=?");
    $u->execute([$email]);
    $t = $u->fetch(PDO::FETCH_ASSOC);
    if ($t) {
        $targetClubId = $t['club_id'];
        $q = $pdo->prepare("
          SELECT r.id FROM roles r 
          JOIN user_roles ur ON ur.role_id=r.id 
          WHERE ur.user_id=?
        ");
        $q->execute([$t['id']]);
        $targetRoles = $q->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Promote/Demote | EventConnect</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../public/assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
<div class="container py-4 fade-in">
  <h1 class="h4 mb-3"><i class="bi bi-arrow-up-down me-2"></i>Promote / Demote</h1>

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

  <!-- Club Management (only for Student Affairs) -->
  <?php if (in_array('student_affair',$slugs,true)): ?>
  <div class="card p-3 mb-4">
    <h5>Manage Clubs</h5>
    <form method="post" class="d-flex mb-3">
      <input type="text" name="new_club" class="form-control me-2" placeholder="Enter new club name" required>
      <button type="submit" class="btn btn-primary">Create</button>
    </form>
    <table class="table table-bordered">
      <thead><tr><th>Club Name</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($clubs as $c): ?>
          <tr>
            <td><?= e($c['name']) ?></td>
            <td>
              <a href="?delete_club=<?= $c['id'] ?>" class="btn btn-sm btn-danger"
                 onclick="return confirm('Delete this club?')">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$clubs): ?>
          <tr><td colspan="2" class="text-muted">No clubs found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Search Form -->
  <form class="row g-2 mb-3" method="get">
    <div class="col-md-6">
      <input class="form-control" name="email" placeholder="Search user by email" value="<?= e($_GET['email'] ?? '') ?>" required>
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Search</button>
    </div>
  </form>

  <!-- Role Assignment Form -->
  <form method="post" class="card p-3">
    <div class="mb-3">
      <label class="form-label">User Email</label>
      <input class="form-control" name="email" value="<?= e($_GET['email'] ?? '') ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Assign Roles</label>
      <div class="row g-2">
        <?php foreach ($roles as $r): ?>
          <div class="col-md-3 col-sm-6">
            <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="assign[]" value="<?= (int)$r['id'] ?>"
                    id="r<?= (int)$r['id'] ?>" <?= in_array($r['id'], $targetRoles)?'checked':'' ?>>
              <label class="form-check-label" for="r<?= (int)$r['id'] ?>">
                <?= e($r['name']) ?> <small class="text-muted">(<?= e($r['slug']) ?>)</small>
              </label>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Club (for Club Admin)</label>
      <select name="club_id" class="form-select">
        <option value="">Select a club</option>
        <?php foreach ($clubs as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ($targetClubId == $c['id']) ? 'selected' : '' ?>>
            <?= e($c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">Required when assigning Club Admin role.</div>
    </div>

    <button class="btn btn-secondary w-100"><i class="bi bi-save me-1"></i>Update Roles</button>
  </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>