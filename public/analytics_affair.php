<?php
include __DIR__ . '/../includes/page_nav.php';
require_once __DIR__ . '/../config/config.php';
Auth::requireLogin();
$user = Auth::user();
$pdo = Database::pdo();

// Ensure Student Affairs role
$chk = $pdo->prepare("SELECT 1 FROM roles r 
    JOIN user_roles ur ON ur.role_id=r.id 
    WHERE ur.user_id=? AND r.slug='student_affair'");
$chk->execute([$user->id]);
if (!$chk->fetchColumn()) redirect('dashboard.php');

// KPIs
$totalStudents = (int)$pdo->query("
  SELECT COUNT(DISTINCT u.id)
  FROM users u
  JOIN user_roles ur ON ur.user_id=u.id
  JOIN roles r ON r.id=ur.role_id
  WHERE r.slug='student'
")->fetchColumn();

$upcomingEvents = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()")->fetchColumn();
$pendingApprovals = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE status IN ('pending_council','pending_affair')")->fetchColumn();

// Month navigation
$month = (int)($_GET['month'] ?? date('m'));
$year  = (int)($_GET['year'] ?? date('Y'));
$prevMonth = $month - 1; $prevYear = $year;
$nextMonth = $month + 1; $nextYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

// Future month check
$currentMonth = (int)date('m');
$currentYear  = (int)date('Y');
$futureMonth = ($year > $currentYear) || ($year == $currentYear && $month > $currentMonth);

// Data arrays
$clubLabels = $clubValues = [];
$courseLabels = $courseValues = [];
$clubStatLabels = $clubStatValues = [];
$eventLabels = []; $datasets = [];

if (!$futureMonth) {
  // Event Attendance by Club (monthly)
  $clubStmt = $pdo->prepare("
    SELECT COALESCE(c.name,'Unknown') AS club, COUNT(ep.user_id) AS participants
    FROM events e
    LEFT JOIN clubs c ON e.club_id = c.id
    LEFT JOIN event_participants ep ON ep.event_id = e.id
    WHERE MONTH(e.event_date) = ? AND YEAR(e.event_date) = ?
    GROUP BY club
    ORDER BY participants DESC
  ");
  $clubStmt->execute([$month, $year]);
  $clubData = $clubStmt->fetchAll(PDO::FETCH_ASSOC);
  $clubLabels = array_column($clubData, 'club');
  $clubValues = array_map('intval', array_column($clubData, 'participants'));

  // Course Participation (monthly)
  $coursesStmt = $pdo->prepare("
    SELECT COALESCE(NULLIF(u.course,''),'Unknown') AS course, COUNT(*) AS c
    FROM event_participants ep
    JOIN users u ON u.id=ep.user_id
    JOIN events e ON e.id = ep.event_id
    WHERE MONTH(e.event_date) = ? AND YEAR(e.event_date) = ?
    GROUP BY course
    ORDER BY c DESC
  ");
  $coursesStmt->execute([$month, $year]);
  $courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
  $courseLabels = array_column($courses, 'course');
  $courseValues = array_map('intval', array_column($courses, 'c'));

  // ✅ Members by Club (distinct participants across all events of the club)
  $membersByClub = $pdo->query("
    SELECT c.name AS club_name, COUNT(DISTINCT ep.user_id) AS members
    FROM clubs c
    LEFT JOIN events e ON e.club_id = c.id
    LEFT JOIN event_participants ep ON ep.event_id = e.id
    GROUP BY c.id, c.name
    ORDER BY members DESC
  ")->fetchAll(PDO::FETCH_ASSOC);
  $clubStatLabels = array_column($membersByClub, 'club_name');
  $clubStatValues = array_map('intval', array_column($membersByClub, 'members'));

  // Participants by Event with Course Breakdown
  $eventCourseStmt = $pdo->prepare("
    SELECT e.id, e.name, e.event_date, u.course, COUNT(*) AS participants
    FROM event_participants ep
    JOIN events e ON e.id = ep.event_id
    JOIN users u ON u.id = ep.user_id
    WHERE MONTH(e.event_date) = ? AND YEAR(e.event_date) = ?
    GROUP BY e.id, e.name, e.event_date, u.course
    ORDER BY e.event_date DESC, u.course
  ");
  $eventCourseStmt->execute([$month, $year]);
  $eventCourseData = $eventCourseStmt->fetchAll(PDO::FETCH_ASSOC);

  $courseGroups = [];
  foreach ($eventCourseData as $row) {
      $eventKey = $row['name'].' ('.$row['event_date'].')';
      if (!in_array($eventKey, $eventLabels)) $eventLabels[] = $eventKey;
      $courseGroups[$row['course']][$eventKey] = (int)$row['participants'];
  }
  $colors = ['rgba(13,110,253,0.7)','rgba(25,135,84,0.7)','rgba(253,126,20,0.7)','rgba(220,53,69,0.7)','rgba(111,66,193,0.7)','rgba(32,201,151,0.7)','rgba(255,193,7,0.7)','rgba(108,117,125,0.7)'];
  $ci=0;
  foreach ($courseGroups as $course=>$values) {
      $data = [];
      foreach ($eventLabels as $ev) {
          $data[] = $values[$ev] ?? 0;
      }
      $datasets[] = [
          'label'=>$course,
          'data'=>$data,
          'backgroundColor'=>$colors[$ci % count($colors)],
          'borderRadius'=>8,
          'borderWidth'=>1,
          'borderColor'=>'#fff'
      ];
      $ci++;
  }
}

// Approvals
$approvals = $pdo->query("
  SELECT id, name, organizer, event_date, status, updated_at
  FROM events
  ORDER BY updated_at DESC
  LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Analytics | Student Affairs</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<div class="container py-4">
  <h1 class="h4 mb-3"><i class="bi bi-graph-up-arrow me-2"></i>Analytics</h1>

  <!-- KPI Cards -->
  <div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card p-4 text-center"><h6>Total Students</h6><div class="display-6"><?= $totalStudents ?></div></div></div>
    <div class="col-md-4"><div class="card p-4 text-center"><h6>Upcoming Events</h6><div class="display-6"><?= $upcomingEvents ?></div></div></div>
    <div class="col-md-4"><div class="card p-4 text-center"><h6>Pending Approvals</h6><div class="display-6"><?= $pendingApprovals ?></div></div></div>
  </div>

  <!-- Month navigation -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <a class="btn btn-outline-secondary" href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>">« Prev</a>
    <h5 class="mb-0"><?= date('F Y', strtotime("$year-$month-01")) ?></h5>
    <a class="btn btn-outline-secondary" href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>">Next »</a>
  </div>

  <?php if ($futureMonth): ?>
    <div class="alert alert-info">
      No statistics available yet for <?= date('F Y', strtotime("$year-$month-01")) ?>.
    </div>
  <?php else: ?>
    <!-- Charts in 2x2 grid -->
    <div class="row g-4 mb-4">
      <div class="col-md-6">
        <div class="card p-3 h-100">
          <h5>Event Attendance by Club (Monthly)</h5>
          <canvas id="clubChart"></canvas>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card p-3 h-100">
                    <h5>Course Participation (Monthly)</h5>
          <canvas id="courseChart"></canvas>
        </div>
      </div>
    </div>

    <div class="row g-4 mb-4">
      <div class="col-md-6">
        <div class="card p-3 h-100">
          <h5>Monthly Participants by Club</h5>
          <canvas id="clubMembersChart"></canvas>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card p-3 h-100">
          <h5>Participants by Event (with Course Breakdown)</h5>
          <canvas id="eventParticipantsChart"></canvas>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Approvals -->
  <div class="card p-3">
    <h5>Events and Approval Status</h5>
    <table class="table table-striped">
      <thead>
        <tr><th>Name</th><th>Organizer</th><th>Date</th><th>Status</th><th>Updated</th></tr>
      </thead>
      <tbody>
        <?php foreach ($approvals as $ev): ?>
          <tr>
            <td><?= e($ev['name']) ?></td>
            <td><?= e($ev['organizer']) ?></td>
            <td><?= e($ev['event_date']) ?></td>
            <td>
              <span class="badge bg-<?= $ev['status']==='approved'
                  ? 'success'
                  : ($ev['status']==='rejected'
                      ? 'danger'
                      : ($ev['status']==='pending_affair'
                          ? 'info'
                          : 'warning')) ?>">
                <?= e(ucwords(str_replace('_',' ',$ev['status']))) ?>
              </span>
            </td>
            <td><small class="text-muted"><?= e($ev['updated_at']) ?></small></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$approvals): ?>
          <tr><td colspan="5" class="text-muted">No events found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div> <!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  // Shared chart options
  const baseOptions = {
    responsive: true,
    animation: { duration: 1000, easing: 'easeOutQuart' },
    plugins: {
      legend: { position: 'bottom', labels: { boxWidth: 18, color: '#444' } },
      tooltip: {
        backgroundColor: '#fff',
        titleColor: '#000',
        bodyColor: '#000',
        borderColor: '#ddd',
        borderWidth: 1,
        callbacks: {
          label: function(ctx) {
            if (ctx.chart.config.type === 'bar' && ctx.chart.options.scales?.x?.stacked) {
              let total = ctx.chart.data.datasets.reduce((sum, ds) => sum + ds.data[ctx.dataIndex], 0);
              let value = ctx.raw;
              let pct = total ? ((value/total)*100).toFixed(1) : 0;
              return `${ctx.dataset.label}: ${value} (${pct}%)`;
            }
            return `${ctx.dataset.label}: ${ctx.raw}`;
          }
        }
      }
    },
    scales: {
      y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
      x: { grid: { display: false } }
    }
  };

  // Event Attendance by Club
  new Chart(document.getElementById('clubChart'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($clubLabels) ?>,
      datasets: [{
        label: 'Participants',
        data: <?= json_encode($clubValues) ?>,
        backgroundColor: 'rgba(13,110,253,0.7)',
        borderColor: '#fff',
        borderWidth: 1,
        borderRadius: 8
      }]
    },
    options: baseOptions
  });

  // Course Participation
  new Chart(document.getElementById('courseChart'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($courseLabels) ?>,
      datasets: [{
        label: 'Participants',
        data: <?= json_encode($courseValues) ?>,
        backgroundColor: 'rgba(25,135,84,0.7)',
        borderColor: '#fff',
        borderWidth: 1,
        borderRadius: 8
      }]
    },
    options: baseOptions
  });

  // Members by Club (using corrected distinct participant counts)
  new Chart(document.getElementById('clubMembersChart'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($clubStatLabels) ?>,
      datasets: [{
        label: 'Members',
        data: <?= json_encode($clubStatValues) ?>,
        backgroundColor: 'rgba(253,126,20,0.7)',
        borderColor: '#fff',
        borderWidth: 1,
        borderRadius: 8
      }]
    },
    options: {
      ...baseOptions,
      scales: {
        x: { title: { display: true, text: 'Club' }, grid: { display: false } },
        y: { beginAtZero: true, title: { display: true, text: 'Members' }, grid: { color: 'rgba(0,0,0,0.05)' } }
      }
    }
  });

  // Participants by Event with Course Breakdown (stacked bar)
  new Chart(document.getElementById('eventParticipantsChart'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($eventLabels) ?>,
      datasets: <?= json_encode($datasets) ?>
    },
    options: {
      ...baseOptions,
      scales: {
        x: {
          stacked: true,
          ticks: { autoSkip: false, maxRotation: 45, minRotation: 0 },
          title: { display: true, text: 'Event' },
          grid: { display: false }
        },
        y: {
          stacked: true,
          beginAtZero: true,
          title: { display: true, text: 'Participants' },
          grid: { color: 'rgba(0,0,0,0.05)' }
        }
      }
    }
  });
</script>
</body>
</html>