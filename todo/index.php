<?php
/**
 * todo/index.php
 * Module 1 – Employee Daily Workspace Dashboard
 */
require_once __DIR__ . '/../includes/config.php';
require_login();

$eid   = current_employee_id();
$today = date('Y-m-d');
$now   = date('H:i:s');

// ── Login status ───────────────────────────────────────────
$loginLog = db()->prepare("SELECT * FROM emp_login_log WHERE employee_id=? AND login_date=?");
$loginLog->execute([$eid, $today]);
$loginInfo = $loginLog->fetch();

// ── Checklist ──────────────────────────────────────────────
$checklist = db()->prepare("
    SELECT dc.id, ct.title, dc.is_completed, dc.completed_at
    FROM daily_checklist dc
    JOIN checklist_templates ct ON ct.id = dc.template_id
    WHERE dc.employee_id=? AND dc.check_date=?
    ORDER BY ct.sort_order
");
$checklist->execute([$eid, $today]);
$tasks_check = $checklist->fetchAll();

$done_count  = count(array_filter($tasks_check, fn($t) => $t['is_completed']));
$total_check = count($tasks_check);

// ── Assigned tasks (open) ──────────────────────────────────
$myTasks = db()->prepare("
    SELECT t.*, c.name AS client_name
    FROM tasks t
    LEFT JOIN clients c ON c.id = t.client_id
    WHERE t.assigned_to=? AND t.status NOT IN ('completed','cancelled')
    ORDER BY FIELD(t.priority,'critical','high','medium','low'), t.due_date ASC
    LIMIT 10
");
$myTasks->execute([$eid]);
$openTasks = $myTasks->fetchAll();

// ── My projects ────────────────────────────────────────────
$myProjects = db()->prepare("
    SELECT p.*, c.name AS client_name
    FROM projects p
    JOIN project_employees pe ON pe.project_id = p.id
    LEFT JOIN clients c ON c.id = p.client_id
    WHERE pe.employee_id=? AND p.status='active'
    ORDER BY p.deadline ASC
    LIMIT 6
");
$myProjects->execute([$eid]);
$projects = $myProjects->fetchAll();

// ── Notifications ──────────────────────────────────────────
$notifs = db()->prepare("SELECT * FROM notifications WHERE user_id=? AND is_read=0 ORDER BY created_at DESC LIMIT 10");
$notifs->execute([$eid]);
$notifications = $notifs->fetchAll();

// ── Performance stats ──────────────────────────────────────
$month_start = date('Y-m-01');
$statsLogin  = db()->prepare("SELECT COUNT(*) as total, SUM(status='on_time') as on_time FROM emp_login_log WHERE employee_id=? AND login_date BETWEEN ? AND ?");
$statsLogin->execute([$eid, $month_start, $today]);
$loginStats  = $statsLogin->fetch();

$statsTasks  = db()->prepare("SELECT COUNT(*) as total, SUM(status='completed') as completed FROM tasks WHERE assigned_to=? AND created_at >= ?");
$statsTasks->execute([$eid, $month_start]);
$taskStats   = $statsTasks->fetch();

$att_pct   = $loginStats['total'] > 0 ? round(($loginStats['on_time'] / $loginStats['total']) * 100) : 0;
$task_pct  = $taskStats['total'] > 0 ? round(($taskStats['completed'] / $taskStats['total']) * 100) : 0;
$chk_pct   = $total_check > 0 ? round(($done_count / $total_check) * 100) : 0;

// ── Daily report submitted? ────────────────────────────────
$rpCheck = db()->prepare("SELECT id FROM daily_reports WHERE employee_id=? AND report_date=?");
$rpCheck->execute([$eid, $today]);
$reportSubmitted = (bool)$rpCheck->fetchColumn();

// ── Flash messages ─────────────────────────────────────────
$success = get_flash('success');
$error   = get_flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Workspace – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="portal-wrapper">
  <!-- Sidebar -->
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <main class="portal-main">
    <div class="page-header">
      <div>
        <h1 class="page-title"><i class="fa fa-th-large"></i> My Workspace</h1>
        <p class="page-sub" id="live-clock"><?= date('l, d F Y') ?></p>
      </div>
      <div class="header-actions">
        <a href="tasks.php" class="btn btn-primary"><i class="fa fa-plus"></i> New Task</a>
        <a href="report.php" class="btn btn-outline <?= $reportSubmitted ? 'btn-success' : '' ?>">
          <i class="fa fa-file-alt"></i> <?= $reportSubmitted ? 'Report Submitted' : 'Daily Report' ?>
        </a>
      </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <!-- Login Status Card -->
    <div class="cards-row">
      <div class="card card-login <?= $loginInfo['status'] ?? '' ?>">
        <div class="card-icon"><i class="fa fa-clock"></i></div>
        <div class="card-body">
          <div class="card-label">Login Status</div>
          <?php if ($loginInfo): ?>
            <div class="card-value"><?= date('h:i A', strtotime($loginInfo['first_login'])) ?></div>
            <?php if ($loginInfo['status'] === 'on_time'): ?>
              <span class="badge badge-success"><i class="fa fa-check"></i> On Time</span>
            <?php elseif ($loginInfo['status'] === 'late'): ?>
              <span class="badge badge-warning"><i class="fa fa-exclamation-triangle"></i> Late by <?= $loginInfo['minutes_late'] ?> min</span>
            <?php endif; ?>
          <?php else: ?>
            <span class="badge badge-danger"><i class="fa fa-times-circle"></i> Not Logged In</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="card card-stat">
        <div class="card-icon"><i class="fa fa-check-double"></i></div>
        <div class="card-body">
          <div class="card-label">Today's Checklist</div>
          <div class="card-value"><?= $done_count ?> / <?= $total_check ?></div>
          <div class="progress-bar-wrap"><div class="progress-bar" style="width:<?= $chk_pct ?>%"></div></div>
        </div>
      </div>

      <div class="card card-stat">
        <div class="card-icon"><i class="fa fa-tasks"></i></div>
        <div class="card-body">
          <div class="card-label">Open Tasks</div>
          <div class="card-value"><?= count($openTasks) ?></div>
          <small><?= $taskStats['completed'] ?> completed this month</small>
        </div>
      </div>

      <div class="card card-stat">
        <div class="card-icon"><i class="fa fa-trophy"></i></div>
        <div class="card-body">
          <div class="card-label">On-Time Rate</div>
          <div class="card-value"><?= $att_pct ?>%</div>
          <div class="progress-bar-wrap"><div class="progress-bar <?= $att_pct >= 80 ? 'bar-green' : 'bar-red' ?>" style="width:<?= $att_pct ?>%"></div></div>
        </div>
      </div>
    </div>

    <div class="two-col">
      <!-- Daily Checklist -->
      <section class="section-card">
        <div class="section-header">
          <h2><i class="fa fa-list-check"></i> Daily Checklist</h2>
          <span class="badge"><?= $done_count ?>/<?= $total_check ?></span>
        </div>
        <ul class="checklist">
          <?php foreach ($tasks_check as $item): ?>
          <li class="checklist-item <?= $item['is_completed'] ? 'done' : '' ?>" data-id="<?= $item['id'] ?>">
            <button class="chk-toggle" onclick="toggleCheck(<?= $item['id'] ?>)">
              <i class="fa <?= $item['is_completed'] ? 'fa-check-circle' : 'fa-circle' ?>"></i>
            </button>
            <span class="chk-title"><?= h($item['title']) ?></span>
            <?php if ($item['completed_at']): ?>
              <small class="chk-time"><?= date('h:i A', strtotime($item['completed_at'])) ?></small>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
          <?php if (!$tasks_check): ?>
            <li class="empty-state"><i class="fa fa-sun"></i> No checklist for today yet.</li>
          <?php endif; ?>
        </ul>
      </section>

      <!-- Active Projects -->
      <section class="section-card">
        <div class="section-header">
          <h2><i class="fa fa-folder-open"></i> My Projects</h2>
          <a href="projects.php" class="btn btn-sm">View All</a>
        </div>
        <div class="project-list">
          <?php foreach ($projects as $p): ?>
          <a href="project_detail.php?id=<?= $p['id'] ?>" class="project-card">
            <div class="proj-name"><?= h($p['name']) ?></div>
            <div class="proj-meta">
              <span><?= h($p['client_name'] ?? 'No Client') ?></span>
              <span class="badge priority-<?= $p['priority'] ?>"><?= ucfirst($p['priority']) ?></span>
            </div>
            <div class="progress-bar-wrap">
              <div class="progress-bar" style="width:<?= $p['completion_percent'] ?>%"></div>
            </div>
            <div class="proj-footer">
              <span><?= $p['completion_percent'] ?>% complete</span>
              <?php if ($p['deadline']): ?>
                <span class="due-date"><i class="fa fa-calendar-alt"></i> <?= date('d M', strtotime($p['deadline'])) ?></span>
              <?php endif; ?>
            </div>
          </a>
          <?php endforeach; ?>
          <?php if (!$projects): ?><p class="empty-state">No active projects assigned.</p><?php endif; ?>
        </div>
      </section>
    </div>

    <!-- Assigned Tasks -->
    <section class="section-card">
      <div class="section-header">
        <h2><i class="fa fa-clipboard-list"></i> Assigned Tasks</h2>
        <a href="tasks.php" class="btn btn-sm">View All</a>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Task</th><th>Client</th><th>Priority</th><th>Due Date</th><th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($openTasks as $t): ?>
            <tr class="<?= $t['due_date'] && $t['due_date'] < $today ? 'overdue-row' : '' ?>">
              <td><?= h($t['title']) ?></td>
              <td><?= h($t['client_name'] ?? '—') ?></td>
              <td><span class="badge priority-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span></td>
              <td><?= $t['due_date'] ? date('d M Y', strtotime($t['due_date'])) : '—' ?></td>
              <td>
                <span class="badge status-<?= str_replace('_','-',$t['status']) ?>">
                  <?= ucwords(str_replace('_',' ',$t['status'])) ?>
                </span>
              </td>
              <td>
                <a href="task_detail.php?id=<?= $t['id'] ?>" class="btn btn-xs"><i class="fa fa-eye"></i></a>
                <a href="time_track.php?task=<?= $t['id'] ?>" class="btn btn-xs btn-outline"><i class="fa fa-stopwatch"></i></a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$openTasks): ?>
              <tr><td colspan="6" class="text-center text-muted">No open tasks 🎉</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Performance Stats -->
    <section class="section-card">
      <div class="section-header"><h2><i class="fa fa-chart-bar"></i> Monthly Performance</h2></div>
      <div class="perf-grid">
        <div class="perf-item">
          <div class="perf-label">On-Time Login</div>
          <div class="perf-ring" data-pct="<?= $att_pct ?>">
            <svg viewBox="0 0 36 36"><circle class="ring-bg" cx="18" cy="18" r="15.9"/><circle class="ring-fill" cx="18" cy="18" r="15.9" stroke-dasharray="<?= $att_pct ?> 100"/></svg>
            <span><?= $att_pct ?>%</span>
          </div>
        </div>
        <div class="perf-item">
          <div class="perf-label">Task Completion</div>
          <div class="perf-ring" data-pct="<?= $task_pct ?>">
            <svg viewBox="0 0 36 36"><circle class="ring-bg" cx="18" cy="18" r="15.9"/><circle class="ring-fill" cx="18" cy="18" r="15.9" stroke-dasharray="<?= $task_pct ?> 100"/></svg>
            <span><?= $task_pct ?>%</span>
          </div>
        </div>
        <div class="perf-item">
          <div class="perf-label">Checklist Today</div>
          <div class="perf-ring" data-pct="<?= $chk_pct ?>">
            <svg viewBox="0 0 36 36"><circle class="ring-bg" cx="18" cy="18" r="15.9"/><circle class="ring-fill" cx="18" cy="18" r="15.9" stroke-dasharray="<?= $chk_pct ?> 100"/></svg>
            <span><?= $chk_pct ?>%</span>
          </div>
        </div>
      </div>
    </section>
  </main>
</div>

<script>
// Live clock
setInterval(() => {
  const el = document.getElementById('live-clock');
  if (el) {
    const now = new Date();
    el.textContent = now.toLocaleString('en-MY', {weekday:'long',day:'2-digit',month:'long',year:'numeric',hour:'2-digit',minute:'2-digit',second:'2-digit'});
  }
}, 1000);

// Toggle checklist item
async function toggleCheck(id) {
  const res = await fetch('/api/checklist_toggle.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({id})
  });
  const data = await res.json();
  if (data.ok) location.reload();
}
</script>
</body>
</html>
