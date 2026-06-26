<?php
/**
 * todo/performance.php
 * Employee: personal performance overview
 */
require_once __DIR__ . '/../includes/config.php';
require_login();

$eid   = current_employee_id();
$today = date('Y-m-d');
$year  = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('m'));

$month_start = "$year-" . sprintf('%02d', $month) . "-01";
$month_end   = date('Y-m-t', strtotime($month_start));

// Login history for selected month
$loginLog = db()->prepare("
    SELECT * FROM emp_login_log
    WHERE employee_id=? AND login_date BETWEEN ? AND ?
    ORDER BY login_date DESC
");
$loginLog->execute([$eid, $month_start, $month_end]);
$loginHistory = $loginLog->fetchAll();

$total_days   = count($loginHistory);
$on_time      = count(array_filter($loginHistory, fn($r) => $r['status'] === 'on_time'));
$late_days    = count(array_filter($loginHistory, fn($r) => $r['status'] === 'late'));
$att_pct      = $total_days > 0 ? round(($on_time / $total_days) * 100) : 0;
$avg_late     = $late_days > 0 ? round(array_sum(array_column(array_filter($loginHistory, fn($r) => $r['status']==='late'), 'minutes_late')) / $late_days) : 0;

// Task stats
$taskStats = db()->prepare("
    SELECT
      COUNT(*) AS total,
      SUM(status='completed') AS completed,
      SUM(status='cancelled') AS cancelled,
      SUM(status NOT IN ('completed','cancelled') AND due_date < ?) AS overdue
    FROM tasks
    WHERE assigned_to=? AND created_at BETWEEN ? AND ?
");
$taskStats->execute([$today, $eid, $month_start, $month_end . ' 23:59:59']);
$ts = $taskStats->fetch();

$task_pct = $ts['total'] > 0 ? round(($ts['completed'] / $ts['total']) * 100) : 0;

// Time tracked
$timeStats = db()->prepare("
    SELECT ROUND(SUM(total_seconds)/3600, 2) AS hours_total,
           COUNT(*) AS sessions
    FROM time_tracking
    WHERE employee_id=? AND status='finished' AND DATE(started_at) BETWEEN ? AND ?
");
$timeStats->execute([$eid, $month_start, $month_end]);
$tt = $timeStats->fetch();

// Top tasks by time
$topTasks = db()->prepare("
    SELECT t.title, ROUND(SUM(tt.total_seconds)/3600,2) AS hours
    FROM time_tracking tt
    JOIN tasks t ON t.id = tt.task_id
    WHERE tt.employee_id=? AND tt.status='finished' AND DATE(tt.started_at) BETWEEN ? AND ?
    GROUP BY t.id
    ORDER BY hours DESC
    LIMIT 5
");
$topTasks->execute([$eid, $month_start, $month_end]);
$topTaskList = $topTasks->fetchAll();

// Daily report streak
$reportCount = db()->prepare("
    SELECT COUNT(*) FROM daily_reports WHERE employee_id=? AND report_date BETWEEN ? AND ?
");
$reportCount->execute([$eid, $month_start, $month_end]);
$reports_submitted = (int)$reportCount->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Performance – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="portal-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main">
    <div class="page-header">
      <h1 class="page-title"><i class="fa fa-chart-line"></i> My Performance</h1>
      <form method="get" class="inline-form">
        <select name="month" class="input input-sm" onchange="this.form.submit()">
          <?php for ($m=1; $m<=12; $m++): ?>
            <option value="<?= $m ?>" <?= $month===$m?'selected':'' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
          <?php endfor; ?>
        </select>
        <select name="year" class="input input-sm" onchange="this.form.submit()">
          <?php for ($y=date('Y')-1; $y<=date('Y'); $y++): ?>
            <option value="<?= $y ?>" <?= $year===$y?'selected':'' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </form>
    </div>

    <!-- Summary Cards -->
    <div class="cards-row">
      <div class="card">
        <div class="card-icon <?= $att_pct >= 80 ? '' : '' ?>" style="background:color-mix(in srgb,var(--clr-<?= $att_pct>=80?'success':'warning' ?>) 15%,transparent);color:var(--clr-<?= $att_pct>=80?'success':'warning' ?>)"><i class="fa fa-clock"></i></div>
        <div class="card-body">
          <div class="card-label">On-Time Rate</div>
          <div class="card-value"><?= $att_pct ?>%</div>
          <small><?= $on_time ?> on-time / <?= $late_days ?> late</small>
        </div>
      </div>
      <div class="card">
        <div class="card-icon"><i class="fa fa-tasks"></i></div>
        <div class="card-body">
          <div class="card-label">Task Completion</div>
          <div class="card-value"><?= $task_pct ?>%</div>
          <small><?= (int)$ts['completed'] ?> / <?= (int)$ts['total'] ?> tasks</small>
        </div>
      </div>
      <div class="card">
        <div class="card-icon"><i class="fa fa-stopwatch"></i></div>
        <div class="card-body">
          <div class="card-label">Hours Tracked</div>
          <div class="card-value"><?= $tt['hours_total'] ?? 0 ?>h</div>
          <small><?= (int)($tt['sessions'] ?? 0) ?> sessions</small>
        </div>
      </div>
      <div class="card">
        <div class="card-icon"><i class="fa fa-file-alt"></i></div>
        <div class="card-body">
          <div class="card-label">Daily Reports</div>
          <div class="card-value"><?= $reports_submitted ?></div>
          <small>submitted this month</small>
        </div>
      </div>
    </div>

    <!-- Performance Rings -->
    <section class="section-card">
      <div class="section-header"><h2><i class="fa fa-chart-bar"></i> Performance Overview – <?= date('F Y', strtotime($month_start)) ?></h2></div>
      <div class="perf-grid">
        <div class="perf-item">
          <div class="perf-label">On-Time Login</div>
          <div class="perf-ring" data-pct="<?= $att_pct ?>">
            <svg viewBox="0 0 36 36"><circle class="ring-bg" cx="18" cy="18" r="15.9"/><circle class="ring-fill" cx="18" cy="18" r="15.9" stroke-dasharray="0 100"/></svg>
            <span><?= $att_pct ?>%</span>
          </div>
        </div>
        <div class="perf-item">
          <div class="perf-label">Task Completion</div>
          <div class="perf-ring" data-pct="<?= $task_pct ?>">
            <svg viewBox="0 0 36 36"><circle class="ring-bg" cx="18" cy="18" r="15.9"/><circle class="ring-fill" cx="18" cy="18" r="15.9" stroke-dasharray="0 100"/></svg>
            <span><?= $task_pct ?>%</span>
          </div>
        </div>
        <?php if ($ts['total'] > 0): ?>
        <div class="perf-item">
          <div class="perf-label">Overdue Tasks</div>
          <div class="perf-ring" data-pct="<?= round(($ts['overdue']/$ts['total'])*100) ?>">
            <svg viewBox="0 0 36 36"><circle class="ring-bg" cx="18" cy="18" r="15.9"/><circle class="ring-fill" cx="18" cy="18" r="15.9" stroke-dasharray="0 100" style="stroke:var(--clr-danger)"/></svg>
            <span style="color:var(--clr-danger)"><?= (int)$ts['overdue'] ?></span>
          </div>
          <small style="font-size:.7rem;color:var(--clr-muted)">overdue</small>
        </div>
        <?php endif; ?>
        <?php if ($avg_late > 0): ?>
        <div class="perf-item">
          <div class="perf-label">Avg Late</div>
          <div style="font-size:1.8rem; font-weight:700; color:var(--clr-warning)"><?= $avg_late ?><span style="font-size:1rem">min</span></div>
        </div>
        <?php endif; ?>
      </div>
    </section>

    <div class="two-col">
      <!-- Login History -->
      <section class="section-card">
        <div class="section-header"><h2><i class="fa fa-history"></i> Login History</h2></div>
        <div style="max-height:360px; overflow-y:auto">
          <table class="data-table">
            <thead><tr><th>Date</th><th>Login Time</th><th>Status</th><th>Late (min)</th></tr></thead>
            <tbody>
              <?php foreach ($loginHistory as $log): ?>
              <tr>
                <td><?= date('D d M', strtotime($log['login_date'])) ?></td>
                <td><?= date('h:i A', strtotime($log['first_login'])) ?></td>
                <td>
                  <span class="badge badge-<?= $log['status']==='on_time'?'success':'warning' ?>">
                    <?= $log['status']==='on_time' ? 'On Time' : 'Late' ?>
                  </span>
                </td>
                <td><?= $log['minutes_late'] > 0 ? $log['minutes_late'] : '—' ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$loginHistory): ?>
                <tr><td colspan="4" class="text-center text-muted">No login records.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Top Tasks by Hours -->
      <section class="section-card">
        <div class="section-header"><h2><i class="fa fa-stopwatch"></i> Time by Task</h2></div>
        <?php if ($topTaskList): ?>
          <?php $max_h = max(array_column($topTaskList, 'hours')) ?: 1; ?>
          <?php foreach ($topTaskList as $tt2): ?>
          <div style="margin-bottom:.75rem">
            <div style="display:flex; justify-content:space-between; font-size:.85rem; margin-bottom:.25rem">
              <span><?= h($tt2['title']) ?></span>
              <span style="font-weight:600"><?= $tt2['hours'] ?>h</span>
            </div>
            <div class="progress-bar-wrap">
              <div class="progress-bar" style="width:<?= round(($tt2['hours']/$max_h)*100) ?>%"></div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="empty-state">No time tracked this month.</p>
        <?php endif; ?>
      </section>
    </div>

  </main>
</div>
<script src="/assets/js/portal.js"></script>
</body>
</html>
