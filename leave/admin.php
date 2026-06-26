<?php
/**
 * leave/admin.php
 * Manager: view, approve, reject leave requests + team calendar
 */
require_once __DIR__ . '/../includes/config.php';
require_login();
if (!is_manager()) { redirect('/leave/index.php'); }

$eid  = current_employee_id();
$year = (int)($_GET['year'] ?? date('Y'));

// ── Handle POST: Approve / Reject ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $req_id = (int)$_POST['request_id'];
    $notes  = trim($_POST['notes'] ?? '');

    if (in_array($action, ['approve','reject'], true) && $req_id) {
        $status = $action === 'approve' ? 'approved' : 'rejected';

        $req = db()->prepare("SELECT * FROM leave_requests WHERE id=?");
        $req->execute([$req_id]);
        $leave = $req->fetch();

        if ($leave && $leave['status'] === 'pending') {
            db()->prepare("UPDATE leave_requests SET status=?,approved_by=?,approval_notes=?,decision_date=NOW() WHERE id=?")
               ->execute([$status, $eid, $notes, $req_id]);

            if ($status === 'approved') {
                // Deduct from balance using the year of the leave, not the filter year
                $leave_year = (int)date('Y', strtotime($leave['start_date']));
                db()->prepare("UPDATE leave_balances SET used = used + ? WHERE employee_id=? AND leave_type_id=? AND year=?")
                   ->execute([$leave['total_days'], $leave['employee_id'], $leave['leave_type_id'], $leave_year]);
            }

            // Notify employee
            add_notification(
                $leave['employee_id'],
                'leave_' . $status,
                'Leave ' . ucfirst($status),
                "Your leave request has been $status." . ($notes ? " Note: $notes" : ''),
                '/leave/index.php'
            );

            flash('success', "Leave request #{$req_id} has been {$status}.");
        }
    }
    redirect('admin.php');
}

// ── Pending requests ───────────────────────────────────────
$pending = db()->query("
    SELECT lr.*, lt.name AS type_name, e.name AS emp_name, e.position AS emp_position
    FROM leave_requests lr
    JOIN leave_types lt ON lt.id = lr.leave_type_id
    JOIN employees e ON e.id = lr.employee_id
    WHERE lr.status = 'pending'
    ORDER BY lr.request_date ASC
")->fetchAll();

// ── All requests (with filters) ────────────────────────────
$filter_emp    = (int)($_GET['employee_id'] ?? 0);
$filter_status = $_GET['status'] ?? '';
$filter_type   = (int)($_GET['type'] ?? 0);
$month         = (int)($_GET['month'] ?? date('m'));

$where  = ['YEAR(lr.start_date) = ?'];
$params = [$year];
if ($filter_emp)    { $where[] = 'lr.employee_id = ?'; $params[] = $filter_emp; }
if ($filter_status) { $where[] = 'lr.status = ?';      $params[] = $filter_status; }
if ($filter_type)   { $where[] = 'lr.leave_type_id = ?'; $params[] = $filter_type; }

$allReq = db()->prepare("
    SELECT lr.*, lt.name AS type_name, e.name AS emp_name, mgr.name AS approved_by_name
    FROM leave_requests lr
    JOIN leave_types lt ON lt.id = lr.leave_type_id
    JOIN employees e ON e.id = lr.employee_id
    LEFT JOIN employees mgr ON mgr.id = lr.approved_by
    WHERE " . implode(' AND ', $where) . "
    ORDER BY lr.request_date DESC
    LIMIT 50
");
$allReq->execute($params);
$allRequests = $allReq->fetchAll();

// ── Team calendar month data ────────────────────────────────
$cal_start = "$year-" . sprintf('%02d', $month) . "-01";
$cal_end   = date('Y-m-t', strtotime($cal_start));
$approved  = db()->prepare("
    SELECT lr.*, e.name AS emp_name, lt.name AS type_name
    FROM leave_requests lr
    JOIN employees e ON e.id = lr.employee_id
    JOIN leave_types lt ON lt.id = lr.leave_type_id
    WHERE lr.status IN ('approved','pending')
      AND lr.start_date <= ? AND lr.end_date >= ?
    ORDER BY lr.start_date
");
$approved->execute([$cal_end, $cal_start]);
$calEvents = $approved->fetchAll();

$holidays = db()->query("SELECT date, name FROM holidays WHERE date BETWEEN '$cal_start' AND '$cal_end'")->fetchAll(PDO::FETCH_KEY_PAIR);

// Build calendar map: date => [events]
$calMap = [];
foreach ($calEvents as $ev) {
    $d = new DateTime($ev['start_date']);
    $end = new DateTime($ev['end_date']);
    while ($d <= $end) {
        $key = $d->format('Y-m-d');
        if ($key >= $cal_start && $key <= $cal_end) {
            $calMap[$key][] = $ev;
        }
        $d->modify('+1 day');
    }
}

// Stats
$employees  = db()->query("SELECT id, name FROM employees WHERE status='active' ORDER BY name")->fetchAll();
$leaveTypes = db()->query("SELECT id, name FROM leave_types WHERE is_active=1")->fetchAll();

$success = get_flash('success');
$error   = get_flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Leave Admin – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="portal-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main">
    <div class="page-header">
      <h1 class="page-title"><i class="fa fa-user-shield"></i> Leave Administration</h1>
      <div class="header-actions">
        <a href="/admin/reports.php?report=leave&year=<?= $year ?>" class="btn btn-outline"><i class="fa fa-file-excel"></i> Export Report</a>
        <a href="holidays.php" class="btn btn-ghost"><i class="fa fa-calendar-plus"></i> Manage Holidays</a>
      </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <!-- Pending Approvals -->
    <?php if ($pending): ?>
    <section class="section-card urgent">
      <div class="section-header">
        <h2><i class="fa fa-hourglass-half"></i> Pending Approvals
          <span class="badge badge-warning"><?= count($pending) ?></span>
        </h2>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr><th>Employee</th><th>Leave Type</th><th>Dates</th><th>Days</th><th>Reason</th><th>Applied</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php foreach ($pending as $p): ?>
            <tr>
              <td>
                <strong><?= h($p['emp_name']) ?></strong>
                <br><small class="text-muted"><?= h($p['emp_position'] ?? '') ?></small>
              </td>
              <td><?= h($p['type_name']) ?></td>
              <td>
                <?= date('d M Y', strtotime($p['start_date'])) ?>
                <?php if ($p['start_date'] !== $p['end_date']): ?>
                  → <?= date('d M Y', strtotime($p['end_date'])) ?>
                <?php endif; ?>
                <?php if ($p['is_half_day']): ?><span class="badge badge-info">½</span><?php endif; ?>
              </td>
              <td><?= $p['total_days'] ?></td>
              <td><?= h(mb_strimwidth($p['reason'], 0, 60, '…')) ?></td>
              <td><?= date('d M', strtotime($p['request_date'])) ?></td>
              <td>
                <div class="action-btns">
                  <button class="btn btn-success btn-sm" onclick="approveLeave(<?= $p['id'] ?>)">
                    <i class="fa fa-check"></i> Approve
                  </button>
                  <button class="btn btn-danger btn-sm" onclick="rejectLeave(<?= $p['id'] ?>)">
                    <i class="fa fa-times"></i> Reject
                  </button>
                  <?php if ($p['cert_filepath']): ?>
                    <a href="/uploads/<?= h($p['cert_filepath']) ?>" target="_blank" class="btn btn-ghost btn-sm" title="View Certificate">
                      <i class="fa fa-file-medical"></i>
                    </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

    <!-- Team Leave Calendar -->
    <section class="section-card">
      <div class="section-header">
        <h2><i class="fa fa-calendar-alt"></i> Team Leave Calendar</h2>
        <form method="get" class="inline-form">
          <select name="month" class="input input-sm" onchange="this.form.submit()">
            <?php for ($m = 1; $m <= 12; $m++): ?>
              <option value="<?= $m ?>" <?= $month === $m ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
            <?php endfor; ?>
          </select>
          <select name="year" class="input input-sm" onchange="this.form.submit()">
            <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
              <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </form>
      </div>
      <div class="cal-legend">
        <span class="cal-dot approved"></span> Approved
        <span class="cal-dot pending"></span> Pending
        <span class="cal-dot holiday"></span> Holiday
      </div>
      <div class="calendar-grid">
        <?php
        $days_of_week = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
        foreach ($days_of_week as $d): ?>
          <div class="cal-head"><?= $d ?></div>
        <?php endforeach;

        $first_day  = new DateTime($cal_start);
        $dow        = (int)$first_day->format('N') - 1; // 0=Mon
        $days_count = (int)date('t', strtotime($cal_start));

        // Empty slots
        for ($i = 0; $i < $dow; $i++) echo '<div class="cal-cell empty"></div>';

        for ($d = 1; $d <= $days_count; $d++):
            $date_str = "$year-" . sprintf('%02d', $month) . "-" . sprintf('%02d', $d);
            $is_holiday = isset($holidays[$date_str]);
            $events = $calMap[$date_str] ?? [];
            $dow_num = (int)(new DateTime($date_str))->format('N');
            $is_weekend = $dow_num >= 6;
        ?>
        <div class="cal-cell <?= $date_str === date('Y-m-d') ? 'today' : '' ?> <?= $is_holiday ? 'holiday' : '' ?> <?= $is_weekend ? 'weekend' : '' ?>">
          <span class="cal-day"><?= $d ?></span>
          <?php if ($is_holiday): ?>
            <div class="cal-event holiday-event"><?= h($holidays[$date_str]) ?></div>
          <?php endif; ?>
          <?php foreach (array_slice($events, 0, 3) as $ev): ?>
            <div class="cal-event <?= $ev['status'] ?>-event" title="<?= h($ev['emp_name']) ?> – <?= h($ev['type_name']) ?>">
              <?= h(explode(' ', $ev['emp_name'])[0]) ?>
            </div>
          <?php endforeach; ?>
          <?php if (count($events) > 3): ?>
            <div class="cal-more">+<?= count($events) - 3 ?> more</div>
          <?php endif; ?>
        </div>
        <?php endfor; ?>
      </div>
    </section>

    <!-- All Requests with Filters -->
    <section class="section-card">
      <div class="section-header"><h2><i class="fa fa-list"></i> All Leave Requests – <?= $year ?></h2></div>
      <form method="get" class="filter-bar">
        <input type="hidden" name="year" value="<?= $year ?>">
        <select name="employee_id" class="input">
          <option value="">All Employees</option>
          <?php foreach ($employees as $e): ?>
            <option value="<?= $e['id'] ?>" <?= $filter_emp === $e['id'] ? 'selected' : '' ?>><?= h($e['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="type" class="input">
          <option value="">All Types</option>
          <?php foreach ($leaveTypes as $lt): ?>
            <option value="<?= $lt['id'] ?>" <?= $filter_type === $lt['id'] ? 'selected' : '' ?>><?= h($lt['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="status" class="input">
          <option value="">All Statuses</option>
          <option value="pending"  <?= $filter_status === 'pending'  ? 'selected' : '' ?>>Pending</option>
          <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved</option>
          <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
          <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>
        <button class="btn btn-outline"><i class="fa fa-search"></i> Filter</button>
        <a href="admin.php?year=<?= $year ?>" class="btn btn-ghost">Clear</a>
      </form>

      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr><th>Employee</th><th>Type</th><th>Dates</th><th>Days</th><th>Status</th><th>Approved By</th><th>Decision</th></tr>
          </thead>
          <tbody>
            <?php foreach ($allRequests as $r): ?>
            <tr>
              <td><?= h($r['emp_name']) ?></td>
              <td><?= h($r['type_name']) ?></td>
              <td><?= date('d M', strtotime($r['start_date'])) ?> – <?= date('d M Y', strtotime($r['end_date'])) ?></td>
              <td><?= $r['total_days'] ?></td>
              <td><span class="badge badge-<?= $r['status'] === 'approved' ? 'success' : ($r['status'] === 'pending' ? 'warning' : 'danger') ?>"><?= ucfirst($r['status']) ?></span></td>
              <td><?= h($r['approved_by_name'] ?? '—') ?></td>
              <td><?= $r['decision_date'] ? date('d M Y', strtotime($r['decision_date'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$allRequests): ?>
              <tr><td colspan="7" class="text-center text-muted">No requests found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</div>

<!-- Approve Modal -->
<div id="approve-modal" class="modal-overlay" style="display:none">
  <div class="modal modal-sm">
    <div class="modal-header"><h3><i class="fa fa-check-circle text-success"></i> Approve Leave</h3></div>
    <form method="post" class="modal-form">
      <input type="hidden" name="action" value="approve">
      <input type="hidden" name="request_id" id="approve-id">
      <div class="form-group"><label>Notes (optional)</label><textarea name="notes" class="input" rows="2"></textarea></div>
      <div class="modal-footer">
        <button class="btn btn-success">Approve</button>
        <button type="button" onclick="closeModal('approve-modal')" class="btn btn-ghost">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Reject Modal -->
<div id="reject-modal" class="modal-overlay" style="display:none">
  <div class="modal modal-sm">
    <div class="modal-header"><h3><i class="fa fa-times-circle text-danger"></i> Reject Leave</h3></div>
    <form method="post" class="modal-form">
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="request_id" id="reject-id">
      <div class="form-group"><label>Reason for rejection *</label><textarea name="notes" class="input" rows="2" required></textarea></div>
      <div class="modal-footer">
        <button class="btn btn-danger">Reject</button>
        <button type="button" onclick="closeModal('reject-modal')" class="btn btn-ghost">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="/assets/js/portal.js"></script>
<script>
function approveLeave(id) {
  document.getElementById('approve-id').value = id;
  openModal('approve-modal');
}
function rejectLeave(id) {
  document.getElementById('reject-id').value = id;
  openModal('reject-modal');
}
</script>
</body>
</html>
