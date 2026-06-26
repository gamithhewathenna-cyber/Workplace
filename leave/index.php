<?php
/**
 * leave/index.php
 * Module 2 – Leave Management: Employee Dashboard + Apply Form
 */
require_once __DIR__ . '/../includes/config.php';
require_login();

$eid  = current_employee_id();
$year = (int)date('Y');

// ── Handle POST: Apply Leave ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
    $type_id    = (int)$_POST['leave_type_id'];
    $start      = $_POST['start_date'];
    $end        = $_POST['end_date'];
    $half_day   = isset($_POST['is_half_day']) ? 1 : 0;
    $half_type  = $_POST['half_day_type'] ?? null;
    $reason     = trim($_POST['reason'] ?? '');

    if (!$type_id || !$start || !$end || !$reason) {
        flash('error', 'Please fill in all required fields.');
        redirect('index.php');
    }

    // Validate dates
    if ($end < $start) {
        flash('error', 'End date cannot be before start date.');
        redirect('index.php');
    }

    // Calculate days
    $total_days = $half_day ? 0.5 : working_days($start, $end);

    // Get leave type
    $ltype = db()->prepare("SELECT * FROM leave_types WHERE id=?");
    $ltype->execute([$type_id]);
    $lt = $ltype->fetch();

    if (!$lt) { flash('error', 'Invalid leave type.'); redirect('index.php'); }

    // Special leave: only if annual balance is 0
    if ($lt['is_special']) {
        $annualBal = db()->prepare("SELECT balance FROM leave_balances WHERE employee_id=? AND leave_type_id=1 AND year=?");
        $annualBal->execute([$eid, $year]);
        $bal = (float)($annualBal->fetchColumn() ?? 0);
        if ($bal > 0) {
            flash('error', 'Special Leave is only available after Annual Leave balance reaches zero.');
            redirect('index.php');
        }
    }

    // Check balance
    if (!$lt['is_special']) {
        $balSt = db()->prepare("SELECT balance FROM leave_balances WHERE employee_id=? AND leave_type_id=? AND year=?");
        $balSt->execute([$eid, $type_id, $year]);
        $currentBal = (float)($balSt->fetchColumn() ?? 0);
        if ($total_days > $currentBal) {
            flash('error', "Insufficient leave balance. Available: $currentBal days.");
            redirect('index.php');
        }
    }

    // Handle medical cert upload
    $cert_name = null; $cert_path = null;
    if ($lt['requires_cert'] && $total_days > $lt['cert_threshold_days']) {
        if (empty($_FILES['cert']['name'])) {
            flash('error', 'Medical certificate is required for medical leave exceeding ' . $lt['cert_threshold_days'] . ' days.');
            redirect('index.php');
        }
    }
    if (!empty($_FILES['cert']['name'])) {
        $orig = basename($_FILES['cert']['name']);
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $safe = 'cert_' . $eid . '_' . time() . '.' . $ext;
        if (!is_dir(UPLOAD_CERT_DIR)) mkdir(UPLOAD_CERT_DIR, 0755, true);
        if (move_uploaded_file($_FILES['cert']['tmp_name'], UPLOAD_CERT_DIR . $safe)) {
            $cert_name = $orig;
            $cert_path = 'certs/' . $safe;
        }
    }

    // Insert request
    $ins = db()->prepare("INSERT INTO leave_requests (employee_id,leave_type_id,start_date,end_date,is_half_day,half_day_type,total_days,reason,cert_filename,cert_filepath)
                          VALUES (?,?,?,?,?,?,?,?,?,?)");
    $ins->execute([$eid, $type_id, $start, $end, $half_day, $half_type ?: null, $total_days, $reason, $cert_name, $cert_path]);

    // Notify all managers
    $managers = db()->query("SELECT id FROM employees WHERE role IN ('manager','admin','hr') AND status='active'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($managers as $mgr_id) {
        add_notification($mgr_id, 'leave_request', 'Leave Request', "An employee has submitted a leave request.", '/leave/admin.php');
    }

    flash('success', 'Leave request submitted successfully. Awaiting approval.');
    redirect('index.php');
}

// ── Leave Balances ─────────────────────────────────────────
$balances = db()->prepare("
    SELECT lb.*, lt.name AS type_name, lt.annual_quota, lt.is_special
    FROM leave_balances lb
    JOIN leave_types lt ON lt.id = lb.leave_type_id
    WHERE lb.employee_id=? AND lb.year=?
    ORDER BY lb.leave_type_id
");
$balances->execute([$eid, $year]);
$myBalances = $balances->fetchAll();

// Ensure balances exist (auto-init for current year)
$existingTypes = array_column($myBalances, 'leave_type_id');
$allTypes = db()->query("SELECT * FROM leave_types WHERE is_active=1")->fetchAll();
foreach ($allTypes as $lt) {
    if (!in_array($lt['id'], $existingTypes)) {
        $entitled = $lt['annual_quota'];
        db()->prepare("INSERT IGNORE INTO leave_balances (employee_id,leave_type_id,year,entitled) VALUES (?,?,?,?)")
            ->execute([$eid, $lt['id'], $year, $entitled]);
    }
}
// Reload
$balances->execute([$eid, $year]);
$myBalances = $balances->fetchAll();

// Special leave available?
$annualBal = 0;
foreach ($myBalances as $b) {
    if ($b['leave_type_id'] == 1) { $annualBal = $b['balance']; break; }
}

// ── Leave History ──────────────────────────────────────────
$history = db()->prepare("
    SELECT lr.*, lt.name AS type_name, e.name AS approved_by_name
    FROM leave_requests lr
    JOIN leave_types lt ON lt.id = lr.leave_type_id
    LEFT JOIN employees e ON e.id = lr.approved_by
    WHERE lr.employee_id=?
    ORDER BY lr.request_date DESC
    LIMIT 20
");
$history->execute([$eid]);
$leaveHistory = $history->fetchAll();

// ── Pending Requests ───────────────────────────────────────
$pending = db()->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id=? AND status='pending'");
$pending->execute([$eid]);
$pendingCount = (int)$pending->fetchColumn();

$success = get_flash('success');
$error   = get_flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Leave Management – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="portal-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main">
    <div class="page-header">
      <h1 class="page-title"><i class="fa fa-calendar-check"></i> Leave Management</h1>
      <button class="btn btn-primary" onclick="openModal('apply-leave-modal')">
        <i class="fa fa-paper-plane"></i> Apply Leave
      </button>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <?php if ($pendingCount): ?>
    <div class="alert alert-info"><i class="fa fa-hourglass-half"></i> You have <?= $pendingCount ?> pending leave request(s) awaiting approval.</div>
    <?php endif; ?>

    <!-- Leave Balances -->
    <div class="cards-row">
      <?php foreach ($myBalances as $b): ?>
      <div class="card card-leave">
        <div class="card-icon">
          <?php if ($b['leave_type_id'] == 1): ?><i class="fa fa-umbrella-beach"></i>
          <?php elseif ($b['leave_type_id'] == 2): ?><i class="fa fa-notes-medical"></i>
          <?php else: ?><i class="fa fa-star"></i>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <div class="card-label"><?= h($b['type_name']) ?></div>
          <?php if ($b['is_special']): ?>
            <?php if ($annualBal <= 0): ?>
              <span class="badge badge-success">Available</span>
            <?php else: ?>
              <span class="badge badge-warning">Available after Annual Leave = 0</span>
            <?php endif; ?>
          <?php else: ?>
            <div class="card-value"><?= $b['balance'] ?> / <?= $b['annual_quota'] ?> days</div>
            <div class="progress-bar-wrap">
              <div class="progress-bar" style="width:<?= $b['annual_quota'] > 0 ? round(($b['balance'] / $b['annual_quota']) * 100) : 0 ?>%"></div>
            </div>
            <small><?= $b['used'] ?> used<?= $b['carried_fwd'] > 0 ? ' · ' . $b['carried_fwd'] . ' carried fwd' : '' ?></small>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Leave History Table -->
    <section class="section-card">
      <div class="section-header">
        <h2><i class="fa fa-history"></i> Leave History</h2>
        <a href="calendar.php" class="btn btn-sm"><i class="fa fa-calendar-alt"></i> Team Calendar</a>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Leave Type</th><th>Dates</th><th>Days</th><th>Status</th><th>Approved By</th><th>Applied On</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($leaveHistory as $lr): ?>
            <tr>
              <td><?= h($lr['type_name']) ?></td>
              <td>
                <?= date('d M Y', strtotime($lr['start_date'])) ?>
                <?php if ($lr['start_date'] !== $lr['end_date']): ?>
                  → <?= date('d M Y', strtotime($lr['end_date'])) ?>
                <?php endif; ?>
                <?php if ($lr['is_half_day']): ?><span class="badge badge-info">½ Day</span><?php endif; ?>
              </td>
              <td><?= $lr['total_days'] ?></td>
              <td>
                <span class="badge badge-<?= $lr['status'] === 'approved' ? 'success' : ($lr['status'] === 'pending' ? 'warning' : ($lr['status'] === 'rejected' ? 'danger' : 'secondary')) ?>">
                  <?= ucfirst($lr['status']) ?>
                </span>
              </td>
              <td><?= h($lr['approved_by_name'] ?? '—') ?></td>
              <td><?= date('d M Y', strtotime($lr['request_date'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$leaveHistory): ?>
              <tr><td colspan="6" class="text-center text-muted">No leave records found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</div>

<!-- Apply Leave Modal -->
<div id="apply-leave-modal" class="modal-overlay" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="fa fa-paper-plane"></i> Apply for Leave</h3>
      <button onclick="closeModal('apply-leave-modal')" class="modal-close">&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data" class="modal-form">
      <input type="hidden" name="action" value="apply">
      <div class="form-grid">
        <div class="form-group span-2">
          <label>Leave Type *</label>
          <select name="leave_type_id" id="leave_type_id" class="input" required onchange="handleLeaveType(this)">
            <option value="">Select Leave Type</option>
            <?php foreach ($allTypes as $lt): ?>
              <?php
              $disabled = $lt['is_special'] && $annualBal > 0 ? 'disabled' : '';
              $label = $lt['name'];
              if ($lt['is_special'] && $annualBal > 0) $label .= ' (use Annual Leave first)';
              ?>
              <option value="<?= $lt['id'] ?>" <?= $disabled ?> data-special="<?= $lt['is_special'] ?>" data-cert="<?= $lt['requires_cert'] ?>" data-cert-days="<?= $lt['cert_threshold_days'] ?>">
                <?= h($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Start Date *</label>
          <input type="date" name="start_date" id="start_date" class="input" required min="<?= date('Y-m-d') ?>" onchange="calcDays()">
        </div>
        <div class="form-group">
          <label>End Date *</label>
          <input type="date" name="end_date" id="end_date" class="input" required min="<?= date('Y-m-d') ?>" onchange="calcDays()">
        </div>
        <div class="form-group span-2">
          <label class="checkbox-label">
            <input type="checkbox" name="is_half_day" id="is_half_day" onchange="toggleHalfDay(this)">
            Half Day
          </label>
        </div>
        <div class="form-group span-2" id="half_day_wrap" style="display:none">
          <label>Half Day Type</label>
          <select name="half_day_type" class="input">
            <option value="morning">Morning</option>
            <option value="afternoon">Afternoon</option>
          </select>
        </div>
        <div class="form-group span-2">
          <label>Days Calculated</label>
          <div class="days-calc" id="days-calc">— Select dates to calculate —</div>
        </div>
        <div class="form-group span-2">
          <label>Reason *</label>
          <textarea name="reason" class="input" rows="3" required placeholder="Reason for leave…"></textarea>
        </div>
        <div class="form-group span-2" id="cert-wrap" style="display:none">
          <label>Medical Certificate <small id="cert-note"></small></label>
          <input type="file" name="cert" class="input" accept=".jpg,.jpeg,.png,.pdf">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Submit Request</button>
        <button type="button" onclick="closeModal('apply-leave-modal')" class="btn btn-ghost">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="/assets/js/portal.js"></script>
<script>
function handleLeaveType(sel) {
  const opt = sel.options[sel.selectedIndex];
  const certWrap = document.getElementById('cert-wrap');
  if (opt.dataset.cert == '1') {
    certWrap.style.display = '';
    document.getElementById('cert-note').textContent = '(Required if > ' + opt.dataset.certDays + ' days)';
  } else {
    certWrap.style.display = 'none';
  }
}

function toggleHalfDay(cb) {
  document.getElementById('half_day_wrap').style.display = cb.checked ? '' : 'none';
  calcDays();
}

async function calcDays() {
  const s = document.getElementById('start_date').value;
  const e = document.getElementById('end_date').value;
  const half = document.getElementById('is_half_day').checked;
  if (!s || !e) return;
  if (half) { document.getElementById('days-calc').textContent = '0.5 day'; return; }
  const res = await fetch('/api/calc_days.php?start=' + s + '&end=' + e);
  const data = await res.json();
  document.getElementById('days-calc').textContent = data.days + ' working day(s)';
}
</script>
</body>
</html>
