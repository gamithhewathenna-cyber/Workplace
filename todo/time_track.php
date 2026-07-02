<?php
/**
 * todo/time_track.php
 * Employee's monthly time log
 */
require_once __DIR__ . '/../includes/config.php';
require_login();

$eid     = current_employee_id();
$is_mgr  = is_manager();
$month_start = date('Y-m-01');
$today       = date('Y-m-d');

// ── My monthly login time (Mon–Fri only) ────────────────────
$myLogins = db()->prepare("
    SELECT login_date, first_login, status, minutes_late
    FROM emp_login_log
    WHERE employee_id = ?
      AND login_date >= ?
      AND DAYOFWEEK(login_date) BETWEEN 2 AND 6
    ORDER BY login_date DESC
");
$myLogins->execute([$eid, $month_start]);
$myLoginRows = $myLogins->fetchAll();

$working_days_elapsed = working_days($month_start, $today);
$my_present_days      = count($myLoginRows);
$my_on_time_days      = count(array_filter($myLoginRows, fn($r) => $r['status'] === 'on_time'));
$my_late_days         = count(array_filter($myLoginRows, fn($r) => $r['status'] === 'late'));
$my_attendance_pct    = $working_days_elapsed > 0 ? round($my_present_days / $working_days_elapsed * 100) : 0;

// ── All employees' monthly attendance (managers only) ───────
$teamAttendance = [];
if ($is_mgr) {
    $teamAttendance = db()->prepare("
        SELECT e.id, e.name, e.position,
               COUNT(el.id) AS present_days,
               SUM(el.status = 'on_time') AS on_time_days,
               SUM(el.status = 'late') AS late_days
        FROM employees e
        LEFT JOIN emp_login_log el ON el.employee_id = e.id
               AND el.login_date >= ?
               AND DAYOFWEEK(el.login_date) BETWEEN 2 AND 6
        WHERE e.status = 'active'
        GROUP BY e.id, e.name, e.position
        ORDER BY e.name ASC
    ");
    $teamAttendance->execute([$month_start]);
    $teamAttendance = $teamAttendance->fetchAll();
}

// Time log history (this month)
$logs = db()->prepare("
    SELECT tt.*, t.title AS task_title, c.name AS client_name,
           ROUND(tt.total_seconds/3600, 2) AS hours,
           ROUND(tt.break_seconds/3600, 2) AS break_hours
    FROM time_tracking tt
    JOIN tasks t ON t.id=tt.task_id
    LEFT JOIN clients c ON c.id=t.client_id
    WHERE tt.employee_id=? AND tt.status='finished' AND DATE(tt.started_at) >= DATE_FORMAT(NOW(),'%Y-%m-01')
    ORDER BY tt.started_at DESC
    LIMIT 50
");
$logs->execute([$eid]);
$timeLogs = $logs->fetchAll();

$total_hours = array_sum(array_column($timeLogs, 'hours'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Monthly Time – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="portal-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main">
    <div class="page-header">
      <h1 class="page-title"><i class="fa fa-clock"></i> Monthly Time</h1>
      <span class="badge badge-info"><?= round($total_hours, 1) ?> hrs logged this month</span>
    </div>

    <!-- My Monthly Login Time -->
    <section class="section-card">
      <div class="section-header">
        <h2><i class="fa fa-calendar-check"></i> My Login Time — <?= date('F Y') ?></h2>
        <span class="badge <?= $my_attendance_pct >= 90 ? 'badge-success' : 'badge-warning' ?>"><?= $my_present_days ?>/<?= (int)$working_days_elapsed ?> working days (<?= $my_attendance_pct ?>%)</span>
      </div>
      <div class="cards-row" style="margin-bottom:1.25rem">
        <div class="card">
          <div class="card-icon"><i class="fa fa-check"></i></div>
          <div class="card-body">
            <div class="card-label">Days Present</div>
            <div class="card-value"><?= $my_present_days ?></div>
          </div>
        </div>
        <div class="card">
          <div class="card-icon" style="background:rgba(39,174,96,.12);color:var(--clr-success)"><i class="fa fa-clock"></i></div>
          <div class="card-body">
            <div class="card-label">On Time</div>
            <div class="card-value"><?= $my_on_time_days ?></div>
          </div>
        </div>
        <div class="card">
          <div class="card-icon" style="background:rgba(243,156,18,.12);color:var(--clr-warning)"><i class="fa fa-exclamation-triangle"></i></div>
          <div class="card-body">
            <div class="card-label">Late</div>
            <div class="card-value"><?= $my_late_days ?></div>
          </div>
        </div>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Date</th><th>Day</th><th>Login Time</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($myLoginRows as $r): ?>
            <tr>
              <td><?= date('d M Y', strtotime($r['login_date'])) ?></td>
              <td><?= date('D', strtotime($r['login_date'])) ?></td>
              <td><?= date('h:i A', strtotime($r['first_login'])) ?></td>
              <td>
                <?php if ($r['status'] === 'on_time'): ?>
                  <span class="badge badge-success"><i class="fa fa-check"></i> On Time</span>
                <?php else: ?>
                  <span class="badge badge-warning"><i class="fa fa-exclamation-triangle"></i> Late by <?= $r['minutes_late'] ?> min</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$myLoginRows): ?>
              <tr><td colspan="4" class="text-center text-muted">No login records this month.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <?php if ($is_mgr): ?>
    <!-- All Employees' Monthly Attendance -->
    <section class="section-card">
      <div class="section-header">
        <h2><i class="fa fa-users"></i> All Employees — Monthly Attendance</h2>
        <span class="badge badge-info"><?= (int)$working_days_elapsed ?> working days so far this month</span>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Employee</th><th>Present Days</th><th>On Time</th><th>Late</th><th>Attendance</th></tr></thead>
          <tbody>
            <?php foreach ($teamAttendance as $ta):
                $ta_pct = $working_days_elapsed > 0 ? round($ta['present_days'] / $working_days_elapsed * 100) : 0;
            ?>
            <tr>
              <td><?= h($ta['name']) ?></td>
              <td><?= (int)$ta['present_days'] ?></td>
              <td><span style="color:#4ade80"><?= (int)$ta['on_time_days'] ?></span></td>
              <td><span style="color:#eab308"><?= (int)$ta['late_days'] ?></span></td>
              <td>
                <span style="white-space:nowrap"><?= $ta_pct ?>%</span>
                <div class="progress-bar-wrap" style="width:90px;display:inline-block;vertical-align:middle;margin-left:.5rem">
                  <div class="progress-bar <?= $ta_pct >= 90 ? 'bar-green' : ($ta_pct < 70 ? 'bar-red' : '') ?>" style="width:<?= $ta_pct ?>%"></div>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$teamAttendance): ?>
              <tr><td colspan="5" class="text-center text-muted">No active employees.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

    <!-- Time Log -->
    <section class="section-card">
      <div class="section-header">
        <h2><i class="fa fa-list-alt"></i> This Month's Time Log</h2>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Task</th><th>Client</th><th>Date</th><th>Hours</th><th>Break</th><th>Working</th></tr></thead>
          <tbody>
            <?php foreach ($timeLogs as $log): ?>
            <tr>
              <td><?= h($log['task_title']) ?></td>
              <td><?= h($log['client_name'] ?? '—') ?></td>
              <td><?= date('d M Y H:i', strtotime($log['started_at'])) ?></td>
              <td><?= $log['hours'] ?>h</td>
              <td><?= $log['break_hours'] ?>h</td>
              <td><?= round($log['hours'] - $log['break_hours'], 2) ?>h</td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$timeLogs): ?>
              <tr><td colspan="6" class="text-center text-muted">No time logged this month.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</div>

<script src="/assets/js/portal.js"></script>
</body>
</html>
