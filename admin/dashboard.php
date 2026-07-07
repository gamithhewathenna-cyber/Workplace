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

$today  = date('Y-m-d');
$now_ts = time();
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
  display: block;
  background: #111;
  border: 1px solid rgba(125,69,154,.35);
  border-radius: 14px;
  padding: 1.15rem 1.25rem;
  position: relative;
  overflow: hidden;
  text-decoration: none;
  color: inherit;
  transition: var(--transition);
}
.live-card:hover { border-color: #7d459a; box-shadow: var(--shadow-lg); }
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
.task-row-pri { width: 8px; height: 8px; border-radius: 50%; display:inline-block; }
.pri-critical { background: #ef4444; }
.pri-high     { background: #f97316; }
.pri-medium   { background: #eab308; }
.pri-low      { background: #6b7280; }
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
      <a href="/todo/task_detail.php?id=<?= $ls['task_id'] ?>" class="live-card">
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
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </main>
</div>

<script>
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
