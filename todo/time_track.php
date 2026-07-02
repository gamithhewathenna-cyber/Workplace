<?php
/**
 * todo/time_track.php
 * Employee's monthly time log
 */
require_once __DIR__ . '/../includes/config.php';
require_login();

$eid = current_employee_id();

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
