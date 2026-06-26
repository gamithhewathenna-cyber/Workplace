<?php
/**
 * leave/calendar.php
 * Team leave calendar – visible to all employees (approved only)
 * Managers also see pending requests
 */
require_once __DIR__ . '/../includes/config.php';
require_login();

$is_mgr = is_manager();
$year   = (int)($_GET['year']  ?? date('Y'));
$month  = (int)($_GET['month'] ?? date('m'));

$cal_start = "$year-" . sprintf('%02d', $month) . "-01";
$cal_end   = date('Y-m-t', strtotime($cal_start));

// Leave events: managers see approved + pending; employees see approved only
$status_filter = $is_mgr ? "lr.status IN ('approved','pending')" : "lr.status = 'approved'";

$events = db()->prepare("
    SELECT lr.*, e.name AS emp_name, lt.name AS type_name
    FROM leave_requests lr
    JOIN employees e ON e.id = lr.employee_id
    JOIN leave_types lt ON lt.id = lr.leave_type_id
    WHERE $status_filter
      AND lr.start_date <= ? AND lr.end_date >= ?
    ORDER BY lr.start_date
");
$events->execute([$cal_end, $cal_start]);
$calEvents = $events->fetchAll();

// Holidays
$holidays = db()->prepare("SELECT date, name FROM holidays WHERE date BETWEEN ? AND ?");
$holidays->execute([$cal_start, $cal_end]);
$holidayMap = $holidays->fetchAll(PDO::FETCH_KEY_PAIR);

// Build calendar map: date => [events]
$calMap = [];
foreach ($calEvents as $ev) {
    $d   = new DateTime($ev['start_date']);
    $end = new DateTime($ev['end_date']);
    while ($d <= $end) {
        $key = $d->format('Y-m-d');
        if ($key >= $cal_start && $key <= $cal_end) {
            $calMap[$key][] = $ev;
        }
        $d->modify('+1 day');
    }
}

// Who is on leave today
$today = date('Y-m-d');
$onLeaveToday = db()->prepare("
    SELECT e.name, lt.name AS type_name
    FROM leave_requests lr
    JOIN employees e ON e.id = lr.employee_id
    JOIN leave_types lt ON lt.id = lr.leave_type_id
    WHERE lr.status = 'approved' AND ? BETWEEN lr.start_date AND lr.end_date
");
$onLeaveToday->execute([$today]);
$todayLeave = $onLeaveToday->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Team Calendar – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="portal-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main">
    <div class="page-header">
      <h1 class="page-title"><i class="fa fa-calendar-alt"></i> Team Leave Calendar</h1>
      <div class="header-actions">
        <a href="index.php" class="btn btn-outline"><i class="fa fa-paper-plane"></i> Apply Leave</a>
        <?php if ($is_mgr): ?>
          <a href="admin.php" class="btn btn-ghost"><i class="fa fa-user-shield"></i> Leave Admin</a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($todayLeave): ?>
    <div class="alert alert-info">
      <i class="fa fa-info-circle"></i> <strong>On leave today:</strong>
      <?= implode(', ', array_map(fn($l) => h($l['name']) . ' (' . h($l['type_name']) . ')', $todayLeave)) ?>
    </div>
    <?php endif; ?>

    <!-- Calendar Navigation -->
    <section class="section-card">
      <div class="section-header">
        <h2><?= date('F Y', strtotime($cal_start)) ?></h2>
        <form method="get" class="inline-form">
          <?php
          $prev_month = date('Y-m', strtotime($cal_start . ' -1 month'));
          $next_month = date('Y-m', strtotime($cal_start . ' +1 month'));
          [$py,$pm] = explode('-', $prev_month);
          [$ny,$nm] = explode('-', $next_month);
          ?>
          <a href="?year=<?= $py ?>&month=<?= (int)$pm ?>" class="btn btn-outline btn-sm"><i class="fa fa-chevron-left"></i></a>
          <select name="month" class="input input-sm" onchange="this.form.submit()">
            <?php for ($m=1; $m<=12; $m++): ?>
              <option value="<?= $m ?>" <?= $month===$m?'selected':'' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
            <?php endfor; ?>
          </select>
          <select name="year" class="input input-sm" onchange="this.form.submit()">
            <?php for ($y=date('Y')-1; $y<=date('Y')+1; $y++): ?>
              <option value="<?= $y ?>" <?= $year===$y?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
          <a href="?year=<?= $ny ?>&month=<?= (int)$nm ?>" class="btn btn-outline btn-sm"><i class="fa fa-chevron-right"></i></a>
        </form>
      </div>

      <div class="cal-legend">
        <span class="cal-dot approved"></span> Approved
        <?php if ($is_mgr): ?><span class="cal-dot pending"></span> Pending<?php endif; ?>
        <span class="cal-dot holiday"></span> Holiday
      </div>

      <div class="calendar-grid">
        <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
          <div class="cal-head"><?= $d ?></div>
        <?php endforeach;

        $first_day  = new DateTime($cal_start);
        $dow        = (int)$first_day->format('N') - 1;
        $days_count = (int)date('t', strtotime($cal_start));

        for ($i = 0; $i < $dow; $i++) echo '<div class="cal-cell empty"></div>';

        for ($d = 1; $d <= $days_count; $d++):
            $date_str   = "$year-" . sprintf('%02d', $month) . "-" . sprintf('%02d', $d);
            $is_holiday = isset($holidayMap[$date_str]);
            $evs        = $calMap[$date_str] ?? [];
            $dow_num    = (int)(new DateTime($date_str))->format('N');
            $is_weekend = $dow_num >= 6;
        ?>
        <div class="cal-cell <?= $date_str === $today ? 'today' : '' ?> <?= $is_holiday ? 'holiday' : '' ?> <?= $is_weekend ? 'weekend' : '' ?>">
          <span class="cal-day"><?= $d ?></span>
          <?php if ($is_holiday): ?>
            <div class="cal-event holiday-event"><?= h($holidayMap[$date_str]) ?></div>
          <?php endif; ?>
          <?php foreach (array_slice($evs, 0, 2) as $ev): ?>
            <div class="cal-event <?= $ev['status'] ?>-event" title="<?= h($ev['emp_name']) ?> – <?= h($ev['type_name']) ?>">
              <?= h(explode(' ', $ev['emp_name'])[0]) ?>
            </div>
          <?php endforeach; ?>
          <?php if (count($evs) > 2): ?>
            <div class="cal-more">+<?= count($evs) - 2 ?> more</div>
          <?php endif; ?>
        </div>
        <?php endfor; ?>
      </div>
    </section>

    <!-- Upcoming leaves -->
    <section class="section-card">
      <div class="section-header"><h2><i class="fa fa-list"></i> <?= date('F Y', strtotime($cal_start)) ?> Leave Summary</h2></div>
      <?php if ($calEvents): ?>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Employee</th><th>Leave Type</th><th>Dates</th><th>Days</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($calEvents as $ev): ?>
            <tr>
              <td><?= h($ev['emp_name']) ?></td>
              <td><?= h($ev['type_name']) ?></td>
              <td>
                <?= date('d M', strtotime($ev['start_date'])) ?>
                <?php if ($ev['start_date'] !== $ev['end_date']): ?>
                  → <?= date('d M', strtotime($ev['end_date'])) ?>
                <?php endif; ?>
              </td>
              <td><?= $ev['total_days'] ?></td>
              <td>
                <span class="badge badge-<?= $ev['status']==='approved'?'success':'warning' ?>">
                  <?= ucfirst($ev['status']) ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <p class="empty-state">No leave requests for <?= date('F Y', strtotime($cal_start)) ?>.</p>
      <?php endif; ?>
    </section>
  </main>
</div>
<script src="/assets/js/portal.js"></script>
</body>
</html>
