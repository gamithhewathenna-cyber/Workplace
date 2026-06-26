<?php
/**
 * todo/time_track.php
 * Employee time tracker for tasks
 */
require_once __DIR__ . '/../includes/config.php';
require_login();

$eid    = current_employee_id();
$task_id = (int)($_GET['task'] ?? 0);

// Active session?
$active = db()->prepare("SELECT tt.*, t.title AS task_title FROM time_tracking tt JOIN tasks t ON t.id=tt.task_id WHERE tt.employee_id=? AND tt.status IN ('running','paused') LIMIT 1");
$active->execute([$eid]);
$session = $active->fetch();

// Task if pre-selected
$task = null;
if ($task_id) {
    $ts = db()->prepare("SELECT * FROM tasks WHERE id=? AND assigned_to=?");
    $ts->execute([$task_id, $eid]);
    $task = $ts->fetch();
}

// My tasks
$myTasks = db()->prepare("SELECT t.id, t.title, c.name AS client_name FROM tasks t LEFT JOIN clients c ON c.id=t.client_id WHERE t.assigned_to=? AND t.status NOT IN ('completed','cancelled') ORDER BY t.due_date");
$myTasks->execute([$eid]);
$tasks = $myTasks->fetchAll();

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
<title>Time Tracker – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="portal-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main">
    <div class="page-header">
      <h1 class="page-title"><i class="fa fa-stopwatch"></i> Time Tracker</h1>
      <span class="badge badge-info"><?= round($total_hours, 1) ?> hrs logged this month</span>
    </div>

    <!-- Timer Widget -->
    <section class="section-card" style="text-align:center">
      <div style="font-size:4rem; font-weight:700; font-variant-numeric:tabular-nums; letter-spacing:.05em; color:var(--clr-primary); margin:1rem 0" class="timer-display">
        <?php if ($session && $session['status']==='running'):
            $elapsed = time() - strtotime($session['started_at']) - ($session['break_seconds'] ?? 0);
            $h = floor($elapsed/3600); $m = floor(($elapsed%3600)/60); $s = $elapsed%60;
            echo sprintf('%02d:%02d:%02d', $h, $m, $s);
        else: ?>00:00:00<?php endif; ?>
      </div>

      <div style="font-size:.9rem; color:var(--clr-muted); margin-bottom:1rem" class="timer-status">
        <?= $session ? ($session['status']==='running' ? 'Running' : 'Paused') : 'Idle' ?>
        <?php if ($session): ?> — <strong><?= h($session['task_title']) ?></strong><?php endif; ?>
      </div>

      <?php if (!$session): ?>
      <!-- Start -->
      <div style="margin-bottom:1rem">
        <select id="task-select" class="input" style="max-width:360px; margin:0 auto .75rem; display:block">
          <option value="">Select a task to track…</option>
          <?php foreach ($tasks as $t): ?>
            <option value="<?= $t['id'] ?>" <?= $task_id===$t['id']?'selected':'' ?>>
              <?= h($t['title']) ?><?= $t['client_name'] ? ' (' . h($t['client_name']) . ')' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button onclick="startNew()" class="btn btn-primary" style="font-size:1rem; padding:.75rem 2rem">
          <i class="fa fa-play"></i> Start Timer
        </button>
      </div>
      <?php else: ?>
      <!-- Controls -->
      <div style="display:flex; gap:.75rem; justify-content:center; margin-bottom:1rem">
        <?php if ($session['status']==='running'): ?>
          <button onclick="pauseTimer(<?= $session['id'] ?>)" class="btn btn-warning" style="font-size:1rem; padding:.75rem 1.5rem"><i class="fa fa-pause"></i> Pause</button>
        <?php else: ?>
          <button onclick="resumeTimer(<?= $session['id'] ?>)" class="btn btn-success" style="font-size:1rem; padding:.75rem 1.5rem"><i class="fa fa-play"></i> Resume</button>
        <?php endif; ?>
        <button onclick="finishTimer(<?= $session['id'] ?>)" class="btn btn-danger" style="font-size:1rem; padding:.75rem 1.5rem"><i class="fa fa-stop"></i> Finish</button>
      </div>
      <?php endif; ?>
    </section>

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
<script>
<?php if ($session && $session['status']==='running'):
    $elapsed = time() - strtotime($session['started_at']) - ($session['break_seconds'] ?? 0);
?>
// Resume display
timerSeconds = <?= $elapsed ?>;
timerStart   = Date.now() - timerSeconds * 1000;
timerStatus  = 'running';
timerInterval = setInterval(updateTimerDisplay, 1000);
<?php elseif ($session && $session['status']==='paused'): ?>
timerSeconds = <?= time() - strtotime($session['started_at']) - ($session['break_seconds'] ?? 0) ?>;
updateTimerDisplay();
timerStatus = 'paused';
<?php endif; ?>

async function startNew() {
  const task_id = document.getElementById('task-select').value;
  if (!task_id) { alert('Please select a task first.'); return; }
  const res = await fetch('/api/time_track.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'start', task_id: parseInt(task_id)})
  });
  const data = await res.json();
  if (data.ok) location.reload();
}
</script>
</body>
</html>
