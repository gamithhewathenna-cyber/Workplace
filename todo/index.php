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
generate_daily_checklist($eid, $today);
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

// ── Team's daily checklist, one card per employee (managers only) ──
$is_mgr = is_manager();
$team_checklist_by_emp = [];
$team_checklist_counts = [];
$team_task_by_emp      = [];
$team_task_counts      = [];
$team_employees        = [];
if ($is_mgr) {
    $team_employees = db()->query("
        SELECT id, name, position, role
        FROM employees
        WHERE status = 'active'
        ORDER BY name ASC
    ")->fetchAll();

    // Make sure today's checklist exists for every team member, not just
    // whoever has already logged in and viewed their own dashboard today.
    foreach ($team_employees as $te) {
        generate_daily_checklist((int)$te['id'], $today);
    }

    $team_checklist_rows = db()->prepare("
        SELECT dc.employee_id, ct.title, dc.is_completed
        FROM daily_checklist dc
        JOIN checklist_templates ct ON ct.id = dc.template_id
        WHERE dc.check_date = ?
        ORDER BY ct.sort_order
    ");
    $team_checklist_rows->execute([$today]);
    foreach ($team_checklist_rows->fetchAll() as $r) {
        $rEid = (int)$r['employee_id'];
        $team_checklist_by_emp[$rEid][] = ['title' => $r['title'], 'is_completed' => (bool)$r['is_completed']];
        $team_checklist_counts[$rEid]['total'] = ($team_checklist_counts[$rEid]['total'] ?? 0) + 1;
        $team_checklist_counts[$rEid]['done']  = ($team_checklist_counts[$rEid]['done']  ?? 0) + ($r['is_completed'] ? 1 : 0);
    }

    $team_task_rows = db()->query("
        SELECT t.id, t.title, t.status, t.priority, t.due_date, t.assigned_to,
               c.name AS client_name
        FROM tasks t
        LEFT JOIN clients c ON c.id = t.client_id
        WHERE t.status != 'cancelled'
        ORDER BY FIELD(t.priority,'critical','high','medium','low'), t.due_date ASC
    ")->fetchAll();
    foreach ($team_task_rows as $t) {
        $rEid = (int)$t['assigned_to'];
        $team_task_by_emp[$rEid][] = $t;
        $team_task_counts[$rEid]['total'] = ($team_task_counts[$rEid]['total'] ?? 0) + 1;
        $team_task_counts[$rEid]['done']  = ($team_task_counts[$rEid]['done']  ?? 0) + ($t['status'] === 'completed' ? 1 : 0);
    }
}

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
        <h1 class="page-title">Dashboard</h1>
        <p class="page-sub" id="live-clock"><?= date('l, d F Y') ?></p>
      </div>
      <div class="header-actions">
        <?php if (can_assign_tasks()): ?>
          <a href="tasks.php" class="btn btn-primary"><i class="fa fa-plus"></i> Assign Task</a>
        <?php endif; ?>
        <a href="report.php" class="btn btn-outline">
          <i class="fa fa-file-alt"></i> <?= $reportSubmitted ? 'Update Report' : 'Daily Report' ?>
        </a>
      </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= h($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= h($error) ?></div><?php endif; ?>

    <!-- Welcome Banner -->
    <?php
    $hour = (int)date('H');
    $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
    $emp_display = $_SESSION['emp_name'] ?? 'there';
    ?>
    <div class="welcome-banner">
      <div class="welcome-text">
        <h2><?= $greeting ?>, <?= h($emp_display) ?>!</h2>
        <p id="live-clock-banner"><?= date('l, d F Y') ?> &nbsp;·&nbsp;
          <?php if ($loginInfo): ?>
            Logged in at <?= date('h:i A', strtotime($loginInfo['first_login'])) ?>
            <?= $loginInfo['status'] === 'late' ? '&nbsp;<span style="background:rgba(255,255,255,.25);border-radius:50px;padding:.1rem .6rem;font-size:.75rem">Late by ' . $loginInfo['minutes_late'] . ' min</span>' : '' ?>
          <?php else: ?>
            Welcome back!
          <?php endif; ?>
        </p>
      </div>
      <div class="welcome-avatar"><?= strtoupper(substr($emp_display, 0, 1)) ?></div>
    </div>

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

    <?php if (($_SESSION['role'] ?? '') !== 'admin'): ?>
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
    <?php endif; ?>

    <?php if (($_SESSION['role'] ?? '') !== 'admin'): ?>
    <!-- Assigned Tasks -->
    <section class="section-card">
      <div class="section-header">
        <h2><i class="fa fa-clipboard-list"></i> Assigned Tasks</h2>
        <a href="tasks.php" class="btn btn-sm">View All</a>
      </div>
      <div class="task-cards-grid">
        <?php foreach ($openTasks as $t): ?>
        <a href="task_detail.php?id=<?= $t['id'] ?>" class="task-card <?= $t['due_date'] && $t['due_date'] < $today ? 'overdue' : '' ?>">
          <div class="task-card-title"><?= h($t['title']) ?></div>
          <div class="task-card-meta">
            <span><?= h($t['client_name'] ?? 'No Client') ?></span>
            <span class="badge priority-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span>
          </div>
          <div class="task-card-meta">
            <span class="badge status-<?= str_replace('_','-',$t['status']) ?>">
              <?= ucwords(str_replace('_',' ',$t['status'])) ?>
            </span>
            <span class="<?= $t['due_date'] && $t['due_date'] < $today ? 'text-danger' : '' ?>">
              <?php if ($t['due_date']): ?>
                <i class="fa fa-calendar-alt"></i> <?= date('d M Y', strtotime($t['due_date'])) ?>
              <?php else: ?>
                —
              <?php endif; ?>
            </span>
          </div>
        </a>
        <?php endforeach; ?>
        <?php if (!$openTasks): ?>
          <p class="empty-state">No open tasks 🎉</p>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($is_mgr): ?>
    <!-- Team's Daily Checklist -->
    <div class="section-label"><i class="fa fa-list-check" style="margin-right:.4rem"></i>Team's Daily Checklist — <?= date('d M Y', strtotime($today)) ?></div>
    <div class="checklist-cards-grid">
      <?php foreach ($team_employees as $te):
          $te_check = $team_checklist_by_emp[$te['id']] ?? [];
          $te_done  = $team_checklist_counts[$te['id']]['done']  ?? 0;
          $te_total = $team_checklist_counts[$te['id']]['total'] ?? 0;
          $te_pct   = $te_total ? round($te_done / $te_total * 100) : 0;
      ?>
      <div class="section-card checklist-emp-card">
        <div class="section-header">
          <div style="display:flex;align-items:center;gap:.65rem">
            <div class="emp-avatar-sm"><?= strtoupper(substr($te['name'], 0, 1)) ?></div>
            <div>
              <h2 style="font-size:.92rem;margin:0"><?= h($te['name']) ?></h2>
              <div style="font-size:.72rem;color:var(--clr-muted)"><?= h($te['position'] ?? $te['role']) ?></div>
            </div>
          </div>
          <span class="badge <?= !$te_total ? 'badge-secondary' : ($te_pct === 100 ? 'badge-success' : 'badge-info') ?>"><?= $te_done ?>/<?= $te_total ?></span>
        </div>
        <?php if ($te_total): ?>
        <div class="progress-bar-wrap" style="margin-bottom:.85rem">
          <div class="progress-bar <?= $te_pct === 100 ? 'bar-green' : '' ?>" style="width:<?= $te_pct ?>%"></div>
        </div>
        <?php endif; ?>
        <div>
          <?php if (!$te_check): ?>
            <p class="empty-state" style="padding:.5rem 0">No checklist assigned today.</p>
          <?php else: foreach ($te_check as $it): ?>
            <div class="chk-item-row">
              <?php if ($it['is_completed']): ?>
                <i class="fa fa-circle-check" style="color:#4ade80"></i>
              <?php else: ?>
                <i class="fa fa-clock" style="color:#eab308"></i>
              <?php endif; ?>
              <span class="chk-title" style="<?= $it['is_completed'] ? 'text-decoration:line-through;color:var(--clr-muted)' : '' ?>"><?= h($it['title']) ?></span>
              <span class="badge <?= $it['is_completed'] ? 'badge-success' : 'badge-warning' ?>" style="font-size:.65rem"><?= $it['is_completed'] ? 'Completed' : 'Pending' ?></span>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (!$team_employees): ?>
        <p class="empty-state">No active employees.</p>
      <?php endif; ?>
    </div>

    <!-- Team's Assigned Tasks -->
    <div class="section-label"><i class="fa fa-clipboard-list" style="margin-right:.4rem"></i>Team's Assigned Tasks</div>
    <div class="checklist-cards-grid">
      <?php
      $task_badge_map = [
          'not_started'    => 'badge-secondary',
          'in_progress'    => 'badge-info',
          'waiting_client' => 'badge-warning',
          'under_review'   => 'badge-warning',
          'completed'      => 'badge-success',
      ];
      foreach ($team_employees as $te):
          $te_tasks = $team_task_by_emp[$te['id']] ?? [];
          $te_tdone  = $team_task_counts[$te['id']]['done']  ?? 0;
          $te_ttotal = $team_task_counts[$te['id']]['total'] ?? 0;
          $te_tpct   = $te_ttotal ? round($te_tdone / $te_ttotal * 100) : 0;
      ?>
      <div class="section-card checklist-emp-card">
        <div class="section-header">
          <div style="display:flex;align-items:center;gap:.65rem">
            <div class="emp-avatar-sm"><?= strtoupper(substr($te['name'], 0, 1)) ?></div>
            <div>
              <h2 style="font-size:.92rem;margin:0"><?= h($te['name']) ?></h2>
              <div style="font-size:.72rem;color:var(--clr-muted)"><?= h($te['position'] ?? $te['role']) ?></div>
            </div>
          </div>
          <span class="badge <?= !$te_ttotal ? 'badge-secondary' : ($te_tpct === 100 ? 'badge-success' : 'badge-info') ?>"><?= $te_tdone ?>/<?= $te_ttotal ?></span>
        </div>
        <?php if ($te_ttotal): ?>
        <div class="progress-bar-wrap" style="margin-bottom:.85rem">
          <div class="progress-bar <?= $te_tpct === 100 ? 'bar-green' : '' ?>" style="width:<?= $te_tpct ?>%"></div>
        </div>
        <?php endif; ?>
        <div>
          <?php if (!$te_tasks): ?>
            <p class="empty-state" style="padding:.5rem 0">No tasks assigned.</p>
          <?php else: foreach ($te_tasks as $t): ?>
            <a href="/todo/task_detail.php?id=<?= $t['id'] ?>" class="chk-item-row chk-item-link">
              <span class="chk-title" style="<?= $t['status'] === 'completed' ? 'text-decoration:line-through;color:var(--clr-muted)' : '' ?>"><?= h($t['title']) ?></span>
              <span class="badge <?= $task_badge_map[$t['status']] ?? 'badge-secondary' ?>" style="font-size:.65rem"><?= ucwords(str_replace('_',' ',$t['status'])) ?></span>
            </a>
          <?php endforeach; endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (!$team_employees): ?>
        <p class="empty-state">No active employees.</p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

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

<script src="/assets/js/portal.js"></script>
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
