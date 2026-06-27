<?php
require_once __DIR__ . '/../includes/config.php';
require_login();
if (!is_manager()) redirect('/todo/index.php');

// ── Summary stats ──────────────────────────────────────────
$stat_employees = (int)db()->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn();
$stat_open      = (int)db()->query("SELECT COUNT(*) FROM tasks WHERE status NOT IN ('completed','cancelled')")->fetchColumn();
$stat_progress  = (int)db()->query("SELECT COUNT(*) FROM tasks WHERE status='in_progress'")->fetchColumn();
$stat_live      = (int)db()->query("SELECT COUNT(DISTINCT employee_id) FROM time_tracking WHERE status='running'")->fetchColumn();

// ── People with running timers right now ───────────────────
$live_sessions = db()->query("
    SELECT tt.id AS session_id,
           UNIX_TIMESTAMP(tt.started_at) AS started_ts,
           COALESCE(tt.break_seconds, 0) AS break_seconds,
           e.id AS emp_id, e.name AS emp_name, e.position,
           t.id AS task_id, t.title AS task_title, t.priority,
           c.name AS client_name
    FROM time_tracking tt
    JOIN employees e ON e.id = tt.employee_id
    JOIN tasks t ON t.id = tt.task_id
    LEFT JOIN clients c ON c.id = t.client_id
    WHERE tt.status = 'running'
    ORDER BY tt.started_at ASC
")->fetchAll();

$live_emp_ids = array_column($live_sessions, 'emp_id');

// ── Employee workload ──────────────────────────────────────
$employees = db()->query("
    SELECT e.id, e.name, e.position, e.department, e.role,
        COUNT(DISTINCT CASE WHEN t.status NOT IN ('completed','cancelled') THEN t.id END) AS open_tasks,
        COUNT(DISTINCT CASE WHEN t.status = 'in_progress' THEN t.id END) AS in_progress_tasks,
        COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.id END) AS completed_tasks,
        COALESCE((
            SELECT ROUND(SUM(tt.total_seconds) / 3600, 1)
            FROM time_tracking tt
            WHERE tt.employee_id = e.id AND tt.status = 'finished'
              AND tt.started_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
        ), 0) AS hours_month,
        (SELECT MAX(tt2.started_at) FROM time_tracking tt2 WHERE tt2.employee_id = e.id) AS last_active
    FROM employees e
    LEFT JOIN tasks t ON t.assigned_to = e.id
    WHERE e.status = 'active'
    GROUP BY e.id, e.name, e.position, e.department, e.role
    ORDER BY in_progress_tasks DESC, open_tasks DESC, e.name ASC
")->fetchAll();

// ── All open tasks grouped by employee ────────────────────
$all_open = db()->query("
    SELECT t.id, t.title, t.status, t.priority, t.due_date,
           t.assigned_to, t.estimated_hours,
           c.name AS client_name
    FROM tasks t
    LEFT JOIN clients c ON c.id = t.client_id
    WHERE t.status NOT IN ('completed','cancelled')
    ORDER BY FIELD(t.priority,'critical','high','medium','low'), t.due_date ASC
")->fetchAll();

$tasks_by_emp = [];
foreach ($all_open as $t) {
    $tasks_by_emp[(int)$t['assigned_to']][] = $t;
}

$today    = date('Y-m-d');
$now_ts   = time();

function time_ago(?string $dt): string {
    if (!$dt) return '—';
    $diff = time() - strtotime($dt);
    if ($diff < 60)    return 'Just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

$status_labels = [
    'not_started'    => 'Not Started',
    'in_progress'    => 'In Progress',
    'waiting_client' => 'Waiting Client',
    'under_review'   => 'Under Review',
];
$status_badges = [
    'not_started'    => 'badge-muted',
    'in_progress'    => 'badge-primary',
    'waiting_client' => 'badge-warning',
    'under_review'   => 'badge-info',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Team Overview – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
<style>
/* ── Live now cards ── */
.live-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1rem;
  margin-bottom: 1.75rem;
}
.live-card {
  background: #111;
  border: 1px solid rgba(125,69,154,.35);
  border-radius: 14px;
  padding: 1.15rem 1.25rem;
  position: relative;
  overflow: hidden;
}
.live-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(ellipse at top left, rgba(125,69,154,.12), transparent 70%);
  pointer-events: none;
}
.live-pulse {
  display: inline-flex; align-items: center; gap: .4rem;
  font-size: .68rem; font-weight: 700; letter-spacing: .06em;
  text-transform: uppercase; color: #4ade80;
  margin-bottom: .6rem;
}
.live-dot {
  width: 7px; height: 7px;
  border-radius: 50%;
  background: #4ade80;
  animation: pulse-dot 1.5s ease-in-out infinite;
}
@keyframes pulse-dot {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: .4; transform: scale(.8); }
}
.live-card-emp {
  display: flex; align-items: center; gap: .65rem;
  margin-bottom: .75rem;
}
.live-avatar {
  width: 38px; height: 38px;
  border-radius: 10px;
  background: rgba(125,69,154,.25);
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: .85rem; color: #c084fc;
  flex-shrink: 0;
}
.live-emp-name  { font-weight: 600; color: #f0f0f0; font-size: .92rem; }
.live-emp-pos   { font-size: .73rem; color: rgba(240,240,240,.4); }
.live-task-title {
  font-size: .84rem; font-weight: 500; color: #e0e0e0;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
  overflow: hidden; margin-bottom: .5rem;
}
.live-meta {
  display: flex; align-items: center; justify-content: space-between;
  margin-top: .5rem;
}
.live-timer {
  font-size: 1.2rem; font-weight: 700;
  font-variant-numeric: tabular-nums;
  color: #7d459a; letter-spacing: .04em;
}
.live-client { font-size: .72rem; color: rgba(240,240,240,.38); }

/* ── Workload table extras ── */
.emp-avatar-sm {
  width: 34px; height: 34px;
  border-radius: 9px;
  background: rgba(125,69,154,.2);
  display: inline-flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: .78rem; color: #c084fc;
  flex-shrink: 0;
}
.live-badge {
  display: inline-flex; align-items: center; gap: .3rem;
  font-size: .65rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .05em; color: #4ade80;
  background: rgba(74,222,128,.1);
  border: 1px solid rgba(74,222,128,.25);
  border-radius: 20px; padding: .15rem .55rem;
}
.bar-wrap {
  width: 80px; height: 5px;
  background: rgba(255,255,255,.07);
  border-radius: 3px; overflow: hidden;
  display: inline-block; vertical-align: middle;
}
.bar-fill {
  height: 100%; border-radius: 3px;
  background: linear-gradient(90deg, #7d459a, #c084fc);
  transition: width .4s;
}

/* ── Section label ── */
.section-label {
  font-size: .7rem; font-weight: 700; letter-spacing: .08em;
  text-transform: uppercase; color: rgba(240,240,240,.35);
  margin: 1.5rem 0 .75rem;
}

/* ── Task modal table ── */
#task-modal .modal { max-width: 720px; }
.task-row-pri { width: 8px; height: 8px; border-radius: 50%; display:inline-block; }
.pri-critical { background: #ef4444; }
.pri-high     { background: #f97316; }
.pri-medium   { background: #eab308; }
.pri-low      { background: #6b7280; }
.overdue-text { color: #f87171; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="portal-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main">

    <div class="page-header">
      <h1 class="page-title"><i class="fa fa-chart-bar"></i> Team Overview</h1>
      <span style="font-size:.8rem;color:rgba(240,240,240,.35)">
        <i class="fa fa-clock"></i> <?= date('d M Y, H:i') ?>
      </span>
    </div>

    <!-- Summary Stats -->
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-value"><?= $stat_employees ?></div>
        <div class="stat-label"><i class="fa fa-users" style="margin-right:.3rem"></i>Active Employees</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $stat_open ?></div>
        <div class="stat-label"><i class="fa fa-clipboard-list" style="margin-right:.3rem"></i>Open Tasks</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:#7d459a"><?= $stat_progress ?></div>
        <div class="stat-label"><i class="fa fa-spinner" style="margin-right:.3rem"></i>In Progress</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:#4ade80"><?= $stat_live ?></div>
        <div class="stat-label"><i class="fa fa-circle" style="margin-right:.3rem;color:#4ade80"></i>Working Now</div>
      </div>
    </div>

    <!-- Live / Currently Working -->
    <?php if ($live_sessions): ?>
    <div class="section-label"><i class="fa fa-circle" style="color:#4ade80;margin-right:.4rem"></i>Currently Working</div>
    <div class="live-grid">
      <?php foreach ($live_sessions as $ls):
          $elapsed = $now_ts - (int)$ls['started_ts'] - (int)$ls['break_seconds'];
          $elapsed = max(0, $elapsed);
      ?>
      <div class="live-card">
        <div class="live-pulse">
          <span class="live-dot"></span> Live
        </div>
        <div class="live-card-emp">
          <div class="live-avatar"><?= strtoupper(substr($ls['emp_name'], 0, 1)) ?></div>
          <div>
            <div class="live-emp-name"><?= h($ls['emp_name']) ?></div>
            <div class="live-emp-pos"><?= h($ls['position'] ?? '') ?></div>
          </div>
        </div>
        <div class="live-task-title">
          <span class="task-row-pri pri-<?= $ls['priority'] ?>" style="margin-right:.4rem"></span>
          <?= h($ls['task_title']) ?>
        </div>
        <div class="live-meta">
          <div class="live-timer"
               data-started="<?= $ls['started_ts'] ?>"
               data-break="<?= $ls['break_seconds'] ?>">
            <?php
              $h = floor($elapsed / 3600);
              $m = floor(($elapsed % 3600) / 60);
              $s = $elapsed % 60;
              echo sprintf('%02d:%02d:%02d', $h, $m, $s);
            ?>
          </div>
          <div class="live-client">
            <?= $ls['client_name'] ? '<i class="fa fa-building" style="margin-right:.25rem"></i>' . h($ls['client_name']) : '' ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Employee Workload Table -->
    <div class="section-label"><i class="fa fa-users" style="margin-right:.4rem"></i>Employee Workload</div>
    <section class="section-card" style="padding:0">
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th style="padding-left:1.25rem">Employee</th>
              <th>Open Tasks</th>
              <th>In Progress</th>
              <th>Hours (This Month)</th>
              <th>Last Active</th>
              <th>Status</th>
              <th>Tasks</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$employees): ?>
              <tr><td colspan="7" class="text-center text-muted" style="padding:2rem">No active employees.</td></tr>
            <?php endif; ?>
            <?php
            $max_open = max(1, max(array_column($employees, 'open_tasks') ?: [1]));
            foreach ($employees as $emp):
                $is_live    = in_array($emp['id'], $live_emp_ids, true);
                $emp_tasks  = $tasks_by_emp[$emp['id']] ?? [];
                $open_pct   = min(100, round($emp['open_tasks'] / $max_open * 100));
            ?>
            <tr>
              <td style="padding-left:1.25rem">
                <div style="display:flex;align-items:center;gap:.65rem">
                  <div class="emp-avatar-sm"><?= strtoupper(substr($emp['name'], 0, 1)) ?></div>
                  <div>
                    <div style="font-weight:600;color:#f0f0f0;font-size:.88rem"><?= h($emp['name']) ?></div>
                    <div style="font-size:.72rem;color:rgba(240,240,240,.38)"><?= h($emp['position'] ?? $emp['role']) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:.6rem">
                  <strong style="color:<?= $emp['open_tasks'] > 5 ? '#f87171' : '#f0f0f0' ?>">
                    <?= $emp['open_tasks'] ?>
                  </strong>
                  <div class="bar-wrap">
                    <div class="bar-fill" style="width:<?= $open_pct ?>%"></div>
                  </div>
                </div>
              </td>
              <td>
                <?php if ($emp['in_progress_tasks'] > 0): ?>
                  <span style="color:#c084fc;font-weight:600"><?= $emp['in_progress_tasks'] ?></span>
                <?php else: ?>
                  <span style="color:rgba(240,240,240,.28)">0</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($emp['hours_month'] > 0): ?>
                  <span style="color:#f0f0f0;font-weight:500"><?= $emp['hours_month'] ?>h</span>
                <?php else: ?>
                  <span style="color:rgba(240,240,240,.28)">—</span>
                <?php endif; ?>
              </td>
              <td style="color:rgba(240,240,240,.45);font-size:.82rem">
                <?= $is_live ? '<span style="color:#4ade80;font-size:.78rem;font-weight:600">● Now</span>' : time_ago($emp['last_active']) ?>
              </td>
              <td>
                <?php if ($is_live): ?>
                  <span class="live-badge"><span class="live-dot"></span> Working</span>
                <?php elseif ($emp['in_progress_tasks'] > 0): ?>
                  <span class="badge badge-primary" style="font-size:.7rem">In Progress</span>
                <?php elseif ($emp['open_tasks'] > 0): ?>
                  <span class="badge badge-muted" style="font-size:.7rem">Tasks Pending</span>
                <?php else: ?>
                  <span style="color:rgba(240,240,240,.25);font-size:.78rem">No tasks</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($emp_tasks): ?>
                  <button class="btn btn-xs btn-outline"
                          onclick='showTasks(<?= $emp['id'] ?>, <?= json_encode($emp['name'], JSON_HEX_APOS) ?>)'>
                    <i class="fa fa-list"></i> <?= count($emp_tasks) ?>
                  </button>
                <?php else: ?>
                  <span style="color:rgba(240,240,240,.2);font-size:.8rem">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

  </main>
</div>

<!-- Task list modal -->
<div id="task-modal" class="modal-overlay" style="display:none">
  <div class="modal" style="max-width:700px">
    <div class="modal-header">
      <h3><i class="fa fa-clipboard-list"></i> <span id="task-modal-name"></span>'s Tasks</h3>
      <button onclick="closeModal('task-modal')" class="modal-close">&times;</button>
    </div>
    <div style="overflow-x:auto">
      <table class="data-table" id="task-modal-table">
        <thead>
          <tr>
            <th>Task</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Due Date</th>
            <th>Client</th>
          </tr>
        </thead>
        <tbody id="task-modal-body"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Embed task data for JS -->
<script>
var TASKS_BY_EMP = <?= json_encode($tasks_by_emp, JSON_HEX_TAG) ?>;
var TODAY = '<?= $today ?>';

var STATUS_LABELS = {
  not_started:    'Not Started',
  in_progress:    'In Progress',
  waiting_client: 'Waiting Client',
  under_review:   'Under Review'
};
var STATUS_CLASSES = {
  not_started:    'badge-muted',
  in_progress:    'badge-primary',
  waiting_client: 'badge-warning',
  under_review:   'badge-info'
};
var PRI_COLORS = { critical:'#ef4444', high:'#f97316', medium:'#eab308', low:'#6b7280' };

function showTasks(empId, empName) {
  var tasks = TASKS_BY_EMP[empId] || [];
  document.getElementById('task-modal-name').textContent = empName;

  var tbody = document.getElementById('task-modal-body');
  tbody.innerHTML = '';

  if (!tasks.length) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:1.5rem;color:rgba(240,240,240,.3)">No open tasks.</td></tr>';
  } else {
    tasks.forEach(function(t) {
      var due      = t.due_date || '';
      var overdue  = due && due < TODAY;
      var dueLabel = due ? new Date(due + 'T00:00:00').toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'}) : '—';
      var priColor = PRI_COLORS[t.priority] || '#6b7280';
      var statusLabel = STATUS_LABELS[t.status] || t.status;
      var statusClass = STATUS_CLASSES[t.status] || 'badge-muted';

      var row = '<tr>'
        + '<td><a href="/todo/task_detail.php?id=' + t.id + '" style="color:#f0f0f0;text-decoration:none;font-weight:500">'
        +   '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' + priColor + ';margin-right:.5rem;vertical-align:middle"></span>'
        +   escHtml(t.title)
        + '</a></td>'
        + '<td><span style="text-transform:capitalize;color:rgba(240,240,240,.6);font-size:.82rem">' + escHtml(t.priority) + '</span></td>'
        + '<td><span class="badge ' + statusClass + '" style="font-size:.7rem">' + statusLabel + '</span></td>'
        + '<td class="' + (overdue ? 'overdue-text' : '') + '" style="font-size:.82rem">' + dueLabel + (overdue ? ' ⚠' : '') + '</td>'
        + '<td style="font-size:.82rem;color:rgba(240,240,240,.45)">' + (t.client_name ? escHtml(t.client_name) : '—') + '</td>'
        + '</tr>';
      tbody.insertAdjacentHTML('beforeend', row);
    });
  }

  openModal('task-modal');
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Live timer tick ─────────────────────────────────────────
var serverNow = <?= $now_ts ?>;
var clientNow = Math.floor(Date.now() / 1000);
var drift     = serverNow - clientNow;

function fmtSecs(s) {
  s = Math.max(0, s);
  var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
  return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (sec < 10 ? '0' : '') + sec;
}

function tickTimers() {
  var now = Math.floor(Date.now() / 1000) + drift;
  document.querySelectorAll('.live-timer').forEach(function(el) {
    var started  = parseInt(el.dataset.started, 10);
    var brk      = parseInt(el.dataset.break, 10) || 0;
    var elapsed  = now - started - brk;
    el.textContent = fmtSecs(elapsed);
  });
}

setInterval(tickTimers, 1000);
tickTimers();
</script>

<script src="/assets/js/portal.js"></script>
</body>
</html>
