<?php
/**
 * todo/report.php
 * Daily Work Report submission + history
 */
require_once __DIR__ . '/../includes/config.php';
require_login();

$eid   = current_employee_id();
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $work      = trim($_POST['work_completed'] ?? '');
    $problems  = trim($_POST['problems_faced'] ?? '');
    $tomorrow  = trim($_POST['tomorrow_plan'] ?? '');

    if (!$work) { flash('error', 'Work completed is required.'); redirect('report.php'); }

    $st = db()->prepare("INSERT INTO daily_reports (employee_id,report_date,work_completed,problems_faced,tomorrow_plan,submitted_at)
                         VALUES (?,?,?,?,?,NOW())
                         ON DUPLICATE KEY UPDATE work_completed=VALUES(work_completed), problems_faced=VALUES(problems_faced), tomorrow_plan=VALUES(tomorrow_plan), submitted_at=NOW()");
    $st->execute([$eid, $today, $work, $problems, $tomorrow]);

    // Notify manager
    if (is_manager()) {
        $managers = db()->query("SELECT id FROM employees WHERE role IN ('manager','admin','hr') AND status='active' AND id != $eid")->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $managers = db()->query("SELECT id FROM employees WHERE role IN ('manager','admin','hr') AND status='active'")->fetchAll(PDO::FETCH_COLUMN);
    }
    foreach ($managers as $mid) {
        add_notification($mid, 'daily_report', 'Daily Report Submitted', "An employee submitted their daily report.", '/admin/reports.php');
    }

    flash('success', 'Daily report submitted!');
    redirect('report.php');
}

// Today's report
$todayRpt = db()->prepare("SELECT * FROM daily_reports WHERE employee_id=? AND report_date=?");
$todayRpt->execute([$eid, $today]);
$existing = $todayRpt->fetch();

// History
$history = db()->prepare("SELECT * FROM daily_reports WHERE employee_id=? ORDER BY report_date DESC LIMIT 30");
$history->execute([$eid]);
$reports = $history->fetchAll();

$success = get_flash('success');
$error   = get_flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Daily Report – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="portal-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main">
    <div class="page-header">
      <h1 class="page-title"><i class="fa fa-file-alt"></i> Daily Work Report</h1>
      <span class="badge badge-info"><?= date('l, d F Y') ?></span>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <div class="two-col">
      <!-- Submit Form -->
      <section class="section-card">
        <div class="section-header">
          <h2><i class="fa fa-edit"></i> <?= $existing ? 'Update' : 'Submit' ?> Today's Report</h2>
          <?php if ($existing): ?><span class="badge badge-success">Submitted <?= date('H:i', strtotime($existing['submitted_at'])) ?></span><?php endif; ?>
        </div>
        <form method="post">
          <div class="form-group" style="margin-bottom:.875rem">
            <label>✅ Work Completed Today *</label>
            <textarea name="work_completed" class="input" rows="5" required placeholder="Describe all tasks you completed today…"><?= h($existing['work_completed'] ?? '') ?></textarea>
          </div>
          <div class="form-group" style="margin-bottom:.875rem">
            <label>⚠️ Problems / Blockers Faced</label>
            <textarea name="problems_faced" class="input" rows="3" placeholder="Any issues or blockers encountered…"><?= h($existing['problems_faced'] ?? '') ?></textarea>
          </div>
          <div class="form-group" style="margin-bottom:1rem">
            <label>📋 Tomorrow's Plan</label>
            <textarea name="tomorrow_plan" class="input" rows="3" placeholder="What do you plan to work on tomorrow…"><?= h($existing['tomorrow_plan'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> <?= $existing ? 'Update Report' : 'Submit Report' ?></button>
        </form>
      </section>

      <!-- Report History -->
      <section class="section-card">
        <div class="section-header"><h2><i class="fa fa-history"></i> Previous Reports</h2></div>
        <div style="max-height:500px; overflow-y:auto">
          <?php foreach ($reports as $r): ?>
          <div style="border:1px solid var(--clr-border); border-radius:8px; padding:.875rem; margin-bottom:.75rem; cursor:pointer" onclick="this.querySelector('.report-body').classList.toggle('hidden')">
            <div style="display:flex; justify-content:space-between; align-items:center">
              <strong><?= date('D, d M Y', strtotime($r['report_date'])) ?></strong>
              <span style="font-size:.75rem; color:var(--clr-muted)"><?= date('H:i', strtotime($r['submitted_at'])) ?></span>
            </div>
            <div class="report-body hidden" style="margin-top:.75rem">
              <div style="font-size:.8rem; color:var(--clr-muted); margin-bottom:.2rem">Work Completed</div>
              <p style="font-size:.875rem; white-space:pre-wrap"><?= h($r['work_completed']) ?></p>
              <?php if ($r['problems_faced']): ?>
                <div style="font-size:.8rem; color:var(--clr-muted); margin:.75rem 0 .2rem">Problems</div>
                <p style="font-size:.875rem; white-space:pre-wrap"><?= h($r['problems_faced']) ?></p>
              <?php endif; ?>
              <?php if ($r['tomorrow_plan']): ?>
                <div style="font-size:.8rem; color:var(--clr-muted); margin:.75rem 0 .2rem">Tomorrow's Plan</div>
                <p style="font-size:.875rem; white-space:pre-wrap"><?= h($r['tomorrow_plan']) ?></p>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (!$reports): ?><p class="empty-state">No reports yet.</p><?php endif; ?>
        </div>
      </section>
    </div>
  </main>
</div>
<script src="/assets/js/portal.js"></script>
<style>.hidden { display:none !important; }</style>
</body>
</html>
