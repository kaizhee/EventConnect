<?php
require_once __DIR__ . '/../config/config.php';
Auth::requireLogin();
$pdo  = Database::pdo();
$user = Auth::user();

include __DIR__ . '/../includes/page_nav.php';
require_once __DIR__ . '/../includes/profile_check.php';

// role check
$chk = $pdo->prepare("SELECT 1 FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=? AND r.slug='club_admin'");
$chk->execute([$user->id]);
if (!$chk->fetchColumn()) redirect('dashboard.php');

// Delete request (soft: mark delete_requested)
if (isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    $stmt = $pdo->prepare("UPDATE events SET delete_requested = 1 WHERE id = ? AND created_by = ?");
    $stmt->execute([$id, $user->id]);
}

// Edit (re-submit triggers approval reset to council)
if (isset($_POST['edit_id'])) {
    $id = (int)$_POST['edit_id'];
    $fields = ['name','organizer','description','venue','start_at','end_at','event_date'];
    $data = [];
    foreach ($fields as $f) { $data[$f] = trim($_POST[$f] ?? ''); }

    // Get current poster filename
    $posterPath = $pdo->prepare("SELECT poster_path FROM events WHERE id=? AND created_by=?");
    $posterPath->execute([$id, $user->id]);
    $posterPath = $posterPath->fetchColumn();

    // If a new poster is uploaded
    if (!empty($_FILES['poster']['name'])) {
        $uploadDir = __DIR__ . '/../public/uploads/posters/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext = strtolower(pathinfo($_FILES['poster']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if (in_array($ext, $allowed)) {
            $fname = 'poster_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $uploadDir . $fname;
            if (move_uploaded_file($_FILES['poster']['tmp_name'], $dest)) {
                $posterPath = $fname; // store only filename
            }
        }
    }

    $stmt = $pdo->prepare("
      UPDATE events
      SET name=?, organizer=?, description=?, venue=?, start_at=?, end_at=?, event_date=?, poster_path=?, status='pending_council', council_comment=NULL, affair_comment=NULL
      WHERE id=? AND created_by=?
    ");
    $stmt->execute([
        $data['name'],$data['organizer'],$data['description'],$data['venue'],
        $data['start_at'],$data['end_at'],$data['event_date'],$posterPath,$id,$user->id
    ]);
}

// List my events — now fetching all fields for prefill
$stmt = $pdo->prepare("
  SELECT id, name, organizer, description, venue, start_at, end_at, event_date,
         status, delete_requested, council_comment, affair_comment, poster_path
  FROM events
  WHERE created_by = ?
  ORDER BY created_at DESC
");
$stmt->execute([$user->id]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Manage Events | EventConnect</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
<div class="container py-4 fade-in">
  <h1 class="h4 mb-3"><i class="bi bi-pencil-square me-2"></i>Manage Events</h1>
  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
      <thead>
        <tr>
          <th>Name</th><th>Date</th><th>Status</th><th>Comments</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($events as $ev): ?>
        <tr>
          <td><?= e($ev['name']) ?></td>
          <td><?= e($ev['event_date']) ?></td>
          <td>
            <?php if ($ev['delete_requested']): ?>
              <span class="badge bg-secondary">Delete Requested</span>
            <?php else: ?>
              <span class="badge bg-<?= $ev['status']==='approved'?'success':($ev['status']==='rejected'?'danger':($ev['status']==='pending_affair'?'warning text-dark':'warning')) ?>">
                <?= e(ucwords(str_replace('_',' ',$ev['status']))) ?>
              </span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($ev['council_comment']): ?><div><strong>Council:</strong> <?= e($ev['council_comment']) ?></div><?php endif; ?>
            <?php if ($ev['affair_comment']): ?><div><strong>Affair:</strong> <?= e($ev['affair_comment']) ?></div><?php endif; ?>
          </td>
          <td>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#edit<?= (int)$ev['id'] ?>"><i class="bi bi-pencil me-1"></i>Edit</button>
            <form method="post" class="d-inline" onsubmit="return confirm('Request delete this event? This requires admin approval.');">
              <input type="hidden" name="delete_id" value="<?= (int)$ev['id'] ?>">
              <button class="btn btn-sm btn-outline-danger" <?= $ev['delete_requested']?'disabled':'' ?>><i class="bi bi-trash me-1"></i>Delete</button>
            </form>
          </td>
        </tr>
        <tr class="collapse" id="edit<?= (int)$ev['id'] ?>">
          <td colspan="5">
            <form method="post" enctype="multipart/form-data" class="border rounded p-3 bg-white">
              <input type="hidden" name="edit_id" value="<?= (int)$ev['id'] ?>">
              <div class="row g-2">
                <div class="col-md-4"><input class="form-control" name="name" value="<?= e($ev['name']) ?>" required></div>
                <div class="col-md-3"><input class="form-control" name="organizer" value="<?= e($ev['organizer']) ?>" required></div>
                <div class="col-md-3"><input class="form-control" name="venue" value="<?= e($ev['venue']) ?>" required></div>
                <div class="col-md-2"><input type="date" class="form-control" name="event_date" value="<?= e($ev['event_date']) ?>" required></div>
                <div class="col-md-2"><input type="time" class="form-control" name="start_at" value="<?= e(substr($ev['start_at'],0,5)) ?>" required></div>
                <div class="col-md-2"><input type="time" class="form-control" name="end_at" value="<?= e(substr($ev['end_at'],0,5)) ?>" required></div>
                <div class="col-12"><textarea class="form-control" name="description" placeholder="Description"><?= e($ev['description']) ?></textarea></div>
                <div class="col-12">
                  <?php if (!empty($ev['poster_path'])): ?>
                    <p class="mt-2">Current Poster:</p>
                    <img src="/eventConnect/public/uploads/posters/<?= e($ev['poster_path']) ?>" alt="Poster" style="max-width:150px;" class="rounded shadow-sm">
                  <?php endif; ?>
                  <label class="form-label mt-2">Replace Poster</label>
                  <input type="file" class="form-control" name="poster" accept=".jpg,.jpeg,.png,.webp">
                </div>
              </div>
              <div class="mt-2"><button class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i>Save (Re-submit)</button></div>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$events): ?>
        <tr><td colspan="5"><div class="alert alert-info mb-0"><i class="bi bi-info-circle-fill me-2"></i>No events yet.</div></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>