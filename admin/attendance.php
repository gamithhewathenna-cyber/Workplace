<?php
/**
 * admin/attendance.php
 * Manager: view daily attendance log for all employees
 */
require_once __DIR__ . '/../includes/config.php';
require_login();
if (!is_manager()) { redirect('/todo/index.php'); }

$today      = date('Y-m-d');
$view_date  = $_GET['date'] ?? $today;
$emp_filter = (int)($_GET['employee_id'] ?? 0);
$month      = (int)($_GET['month'] ?? date('m'));
$year       = (int)($_GET['year']  ?? date('Y'));
$view       = $_GET['view'] ?? 'daily';

$month_start = "$year-" . sprintf('%02d', $month) . "-01";
$month_end   = date('Y-m-t', strtotime($month_start));

// All active employees
$employees = db()->query("SELECT id, name, position FROM employees WHERE status='active' ORDER BY name")->fetchAll();

if ($view === 'daily') {
    // Daily view: attendance for a specific date
    $where  = ['el.login_date = ?'];
    $params = [$view_date];
    if ($emp_filter) { $where[] = 'e.id = ?'; $params[] = $emp_filter; }

    $data = db()->prepare("
        SELECT e.id, e.name, e.position,
               el.first_login, el.status, el.minutes_late
        FROM employees e
        LEFT JOIN emp_login_log el ON el.employee_id = e.id AND el.login_date = ?
        WHERE e.status = 'active'" . ($emp_filter ? " AND e.id = $emp_filter" : '') . "
        ORDER BY e.name
    ");
    $data->execute([$view_date]);
    $dailyRows = $data->fetchAll();

    $present = count(array_filter($dailyRows, fn($r) => $r['first_login']));
    $absent  = count($dailyRows) - $present;
    $on_time = count(array_filter($dailyRows, fn($r) => $r['status'] === 'on_time'));
    $late    = count(array_filter($dailyRows, fn($r) => $r['status'] === 'late'));

} else {
    // Monthly summary view
    $where  = "e.status = 'active'" . ($emp_filter ? " AND e.id = $emp_filter" : '');
    $summary = db()->prepare("
        SELECT e.name, e.position,
               COUNT(el.id) AS total_logins,
               SUM(el.status='on_time') AS on_time,
               SUM(el.status='late') AS late,
               ROUND(AVG(CASE WHEN el.status='late' THEN el.minutes_late END), 1) AS avg_late_min
        FROM employees e
        LEFT JOIN emp_login_log el ON el.employee_id = e.id AND el.login_date BETWEEN ? AND ?
        WHERE $where
        GROUP BY e.id
        ORDER BY e.name
    ");
    $summary->execute([$month_start, $month_end]);
    $monthRows = $summary->fetchAll();
}

$success = get_flash('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Attendance – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="portal-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main">
    <div class="page-header">
      <h1 class="page-title"><i class="fa fa-user-clock"></i> Attendance</h1>
      <a href="/admin/reports.php?report=attendance" class="btn btn-outline"><i class="fa fa-chart-bar"></i> Full Report</a>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

    <!-- View Toggle -->
    <div style="display:flex; gap:.5rem; margin-bottom:1.25rem">
      <a href="?view=daily&date=<?= $today ?>" class="btn <?= $view==='daily'?'btn-primary':'btn-outline' ?>"><i class="fa fa-calendar-day"></i> Daily</a>
      <a href="?view=monthly&month=<?= date('m') ?>&year=<?= date('Y') ?>" class="btn <?= $view==='monthly'?'btn-primary':'btn-outline' ?>"><i class="fa fa-calendar-alt"></i> Monthly</a>
    </div>

    <?php if ($view === 'daily'): ?>
    <!-- Daily Filters -->
    <form method="get" class="filter-bar">
      <input type="hidden" name="view" value="daily">
      <input type="date" name="date" class="input" value="<?= h($view_date) ?>" max="<?= $today ?>">
      <select name="employee_id" class="input">
        <option value="0">All Employees</option>
        <?php foreach ($employees as $e): ?>
          <option value="<?= $e['id'] ?>" <?= $emp_filter===$e['id']?'selected':'' ?>><?= h($e['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-outline"><i class="fa fa-search"></i> View</button>
    </form>

    <!-- Daily Summary Cards -->
    <div class="cards-row" style="margin-bottom:1.25rem">
      <div class="card">
        <div class="card-icon" style="background:color-mix(in srgb,var(--clr-success) 15%,transparent);color:var(--clr-success)"><i class="fa fa-check-circle"></i></div>
        <div class="card-body"><div class="card-label">Present</div><div class="card-value"><?= $present ?></div></div>
      </div>
      <div class="card">
        <div class="card-icon" style="background:color-mix(in srgb,var(--clr-danger) 15%,transparent);color:var(--clr-danger)"><i class="fa fa-times-circle"></i></div>
        <div class="card-body"><div class="card-label">Absent</div><div class="card-value"><?= $absent ?></div></div>
      </div>
      <div class="card">
        <div class="card-icon" style="background:color-mix(in srgb,var(--clr-primary) 15%,transparent);color:var(--clr-primary)"><i class="fa fa-clock"></i></div>
        <div class="card-body"><div class="card-label">On Time</div><div class="card-value"><?= $on_time ?></div></div>
      </div>
      <div class="card">
        <div class="card-icon" style="background:color-mix(in srgb,var(--clr-warning) 15%,transparent);color:var(--clr-warning)"><i class="fa fa-exclamation-triangle"></i></div>
        <div class="card-body"><div class="card-label">Late</div><div class="card-value"><?= $late ?></div></div>
      </div>
    </div>

    <!-- Daily Table -->
    <section class="section-card">
      <div class="section-header">
        <h2><i class="fa fa-list"></i> <?= date('D, d F Y', strtotime($view_date)) ?></h2>
        <span class="badge badge-info"><?= count($dailyRows) ?> employees</span>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Employee</th><th>Position</th><th>Login Time</th><th>Status</th><th>Late (min)</th></tr></thead>
          <tbody>
            <?php foreach ($dailyRows as $r): ?>
            <tr>
              <td><strong><?= h($r['name']) ?></strong></td>
              <td><?= h($r['position'] ?? '—') ?></td>
              <td><?= $r['first_login'] ? date('h:i A', strtotime($r['first_login'])) : '<span class="text-muted">—</span>' ?></td>
              <td>
                <?php if (!$r['first_login']): ?>
                  <span class="badge badge-danger">Absent</span>
                <?php elseif ($r['status'] === 'on_time'): ?>
                  <span class="badge badge-success"><i class="fa fa-check"></i> On Time</span>
                <?php else: ?>
                  <span class="badge badge-warning"><i class="fa fa-exclamation-triangle"></i> Late</span>
                <?php endif; ?>
              </td>
              <td><?= ($r['minutes_late'] ?? 0) > 0 ? $r['minutes_late'] : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <?php else: ?>
    <!-- Monthly Filters -->
    <form method="get" class="filter-bar">
      <input type="hidden" name="view" value="monthly">
      <select name="month" class="input" onchange="this.form.submit()">
        <?php for ($m=1; $m<=12; $m++): ?>
          <option value="<?= $m ?>" <?= $month===$m?'selected':'' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
        <?php endfor; ?>
      </select>
      <select name="year" class="input" onchange="this.form.submit()">
        <?php for ($y=date('Y')-1; $y<=date('Y'); $y++): ?>
          <option value="<?= $y ?>" <?= $year===$y?'selected':'' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
      <select name="employee_id" class="input">
        <option value="0">All Employees</option>
        <?php foreach ($employees as $e): ?>
          <option value="<?= $e['id'] ?>" <?= $emp_filter===$e['id']?'selected':'' ?>><?= h($e['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-outline"><i class="fa fa-search"></i> Filter</button>
    </form>

    <!-- Monthly Summary Table -->
    <section class="section-card">
      <div class="section-header">
        <h2><i class="fa fa-calendar-alt"></i> <?= date('F Y', strtotime($month_start)) ?> Summary</h2>
        <a href="/admin/reports.php?report=attendance&month=<?= $month ?>&year=<?= $year ?>&export=csv" class="btn btn-outline btn-sm"><i class="fa fa-file-csv"></i> CSV</a>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Employee</th><th>Position</th><th>Total Days</th><th>On Time</th><th>Late</th><th>On-Time %</th><th>Avg Late (min)</th></tr></thead>
          <tbody>
            <?php foreach ($monthRows as $r): ?>
            <?php $pct = $r['total_logins'] > 0 ? round(($r['on_time']/$r['total_logins'])*100) : 0; ?>
            <tr>
              <td><strong><?= h($r['name']) ?></strong></td>
              <td><?= h($r['position'] ?? '—') ?></td>
              <td><?= (int)$r['total_logins'] ?></td>
              <td><?= (int)$r['on_time'] ?></td>
              <td><?= (int)$r['late'] ?></td>
              <td>
                <div style="display:flex; align-items:center; gap:.5rem">
                  <div class="progress-bar-wrap" style="flex:1; margin:0">
                    <div class="progress-bar <?= $pct>=80?'bar-green':'bar-red' ?>" style="width:<?= $pct ?>%"></div>
                  </div>
                  <span style="font-size:.8rem"><?= $pct ?>%</span>
                </div>
              </td>
              <td><?= $r['avg_late_min'] ?? '—' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$monthRows): ?>
              <tr><td colspan="7" class="text-center text-muted">No data.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

  </main>
</div>
<script src="/assets/js/portal.js"></script>
</body>
</html>
