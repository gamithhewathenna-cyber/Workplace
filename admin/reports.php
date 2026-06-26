<?php
/**
 * admin/reports.php
 * Manager reports: Attendance, Productivity, Leave — with CSV/PDF export
 */
require_once __DIR__ . '/../includes/config.php';
require_login();
if (!is_manager()) redirect('/todo/index.php');

$report  = $_GET['report'] ?? 'attendance';
$month   = (int)($_GET['month'] ?? date('m'));
$year    = (int)($_GET['year']  ?? date('Y'));
$emp_id  = (int)($_GET['employee_id'] ?? 0);

$start = "$year-" . sprintf('%02d', $month) . "-01";
$end   = date('Y-m-t', strtotime($start));

// ── Export CSV ─────────────────────────────────────────────
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_' . $report . '_' . $start . '.csv"');
    $out = fopen('php://output', 'w');
}

$employees = db()->query("SELECT id, name FROM employees WHERE status='active' ORDER BY name")->fetchAll();

// ── Attendance Report ──────────────────────────────────────
if ($report === 'attendance') {
    $where = $emp_id ? "AND el.employee_id = $emp_id" : '';
    $data = db()->query("
        SELECT e.name, e.position,
               COUNT(el.id) as total_logins,
               SUM(el.status='on_time') as on_time,
               SUM(el.status='late') as late,
               ROUND(AVG(el.minutes_late),1) as avg_late_min
        FROM employees e
        LEFT JOIN emp_login_log el ON el.employee_id=e.id AND el.login_date BETWEEN '$start' AND '$end'
        WHERE e.status='active' $where
        GROUP BY e.id
        ORDER BY e.name
    ")->fetchAll();
    $cols = ['Employee','Position','Total Logins','On Time','Late','Avg Late (min)'];
    $rows = array_map(fn($r) => [$r['name'], $r['position'], $r['total_logins'], $r['on_time'], $r['late'], $r['avg_late_min']], $data);
}

// ── Productivity Report ────────────────────────────────────
elseif ($report === 'productivity') {
    $where = $emp_id ? "AND t.assigned_to = $emp_id" : '';
    $data = db()->query("
        SELECT e.name,
               COUNT(t.id) as total_tasks,
               SUM(t.status='completed') as completed,
               SUM(t.status NOT IN ('completed','cancelled') AND t.due_date < CURDATE()) as overdue,
               ROUND(SUM(tt.total_seconds)/3600, 1) as hours_tracked
        FROM employees e
        LEFT JOIN tasks t ON t.assigned_to=e.id AND t.created_at BETWEEN '$start' AND '$end' $where
        LEFT JOIN time_tracking tt ON tt.employee_id=e.id AND DATE(tt.started_at) BETWEEN '$start' AND '$end'
        WHERE e.status='active'
        GROUP BY e.id
        ORDER BY e.name
    ")->fetchAll();
    $cols = ['Employee','Total Tasks','Completed','Overdue','Hours Tracked'];
    $rows = array_map(fn($r) => [$r['name'], $r['total_tasks'], $r['completed'], $r['overdue'], $r['hours_tracked']], $data);
}

// ── Leave Report ───────────────────────────────────────────
elseif ($report === 'leave') {
    $where = $emp_id ? "AND lr.employee_id = $emp_id" : '';
    $data = db()->query("
        SELECT e.name, lt.name AS leave_type,
               COUNT(lr.id) as requests,
               SUM(lr.total_days) as total_days,
               SUM(lr.status='approved') as approved,
               SUM(lr.status='rejected') as rejected,
               SUM(lr.status='pending') as pending
        FROM employees e
        JOIN leave_requests lr ON lr.employee_id=e.id AND lr.start_date BETWEEN '$start' AND '$end' $where
        JOIN leave_types lt ON lt.id=lr.leave_type_id
        WHERE e.status='active'
        GROUP BY e.id, lt.id
        ORDER BY e.name, lt.name
    ")->fetchAll();
    $cols = ['Employee','Leave Type','Requests','Total Days','Approved','Rejected','Pending'];
    $rows = array_map(fn($r) => [$r['name'], $r['leave_type'], $r['requests'], $r['total_days'], $r['approved'], $r['rejected'], $r['pending']], $data);
}

// Output CSV if requested
if (isset($out)) {
    fputcsv($out, $cols);
    foreach ($rows as $row) fputcsv($out, $row);
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reports – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="portal-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main">
    <div class="page-header">
      <h1 class="page-title"><i class="fa fa-chart-bar"></i> Reports</h1>
      <div class="header-actions">
        <a href="?report=<?= h($report) ?>&month=<?= $month ?>&year=<?= $year ?>&employee_id=<?= $emp_id ?>&export=csv" class="btn btn-outline">
          <i class="fa fa-file-csv"></i> Export CSV
        </a>
        <button onclick="window.print()" class="btn btn-ghost"><i class="fa fa-print"></i> Print</button>
      </div>
    </div>

    <!-- Report Tabs -->
    <div style="display:flex; gap:.5rem; margin-bottom:1.5rem; flex-wrap:wrap">
      <a href="?report=attendance&month=<?= $month ?>&year=<?= $year ?>" class="btn <?= $report==='attendance' ? 'btn-primary' : 'btn-outline' ?>"><i class="fa fa-user-clock"></i> Attendance</a>
      <a href="?report=productivity&month=<?= $month ?>&year=<?= $year ?>" class="btn <?= $report==='productivity' ? 'btn-primary' : 'btn-outline' ?>"><i class="fa fa-chart-line"></i> Productivity</a>
      <a href="?report=leave&month=<?= $month ?>&year=<?= $year ?>" class="btn <?= $report==='leave' ? 'btn-primary' : 'btn-outline' ?>"><i class="fa fa-calendar-check"></i> Leave</a>
    </div>

    <!-- Filters -->
    <form method="get" class="filter-bar">
      <input type="hidden" name="report" value="<?= h($report) ?>">
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
          <option value="<?= $e['id'] ?>" <?= $emp_id===$e['id']?'selected':'' ?>><?= h($e['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-outline"><i class="fa fa-search"></i> Filter</button>
    </form>

    <section class="section-card">
      <div class="section-header">
        <h2><?= ucfirst($report) ?> Report — <?= date('F Y', strtotime($start)) ?></h2>
        <span class="badge badge-info"><?= count($rows ?? []) ?> records</span>
      </div>
      <div class="table-responsive">
        <table class="data-table" id="report-table">
          <thead><tr><?php foreach ($cols as $c): ?><th><?= h($c) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
            <?php foreach ($rows ?? [] as $row): ?>
            <tr><?php foreach ($row as $cell): ?><td><?= h((string)$cell) ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
              <tr><td colspan="<?= count($cols) ?>" class="text-center text-muted">No data for this period.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</div>
<script src="/assets/js/portal.js"></script>
<style>@media print { .portal-sidebar, .portal-navbar, .header-actions, .filter-bar { display:none !important; } .portal-main { margin:0 !important; } }</style>
</body>
</html>
