<?php
/**
 * todo/task_detail.php
 * Full detail view for a single task
 */
require_once __DIR__ . '/../includes/config.php';
require_login();

$eid    = current_employee_id();
$is_mgr = is_manager();
$tid    = (int)($_GET['id'] ?? 0);

if (!$tid) { redirect('/todo/tasks.php'); }

// Fetch task — employees can only see their own; managers see all
$st = db()->prepare("
    SELECT t.*, c.name AS client_name, p.name AS project_name,
           ab.name AS assigned_by_name, at2.name AS assigned_to_name
    FROM tasks t
    LEFT JOIN clients c ON c.id = t.client_id
    LEFT JOIN projects p ON p.id = t.project_id
    LEFT JOIN employees ab ON ab.id = t.assigned_by
    LEFT JOIN employees at2 ON at2.id = t.assigned_to
    WHERE t.id = ? AND (t.assigned_to = ? OR ? = 1)
");
$st->execute([$tid, $eid, (int)$is_mgr]);
$task = $st->fetch();

if (!$task) { redirect('/todo/tasks.php'); }

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $valid = ['not_started','in_progress','waiting_client','under_review','completed','cancelled'];
        $ns = $_POST['status'] ?? '';
        if (in_array($ns, $valid, true)) {
            db()->prepare("UPDATE tasks SET status=? WHERE id=? AND (assigned_to=? OR ?=1)")
               ->execute([$ns, $tid, $eid, (int)$is_mgr]);
            flash('success', 'Status updated.');
        }
        redirect("task_detail.php?id=$tid");
    }
    if ($_POST['action'] === 'update_notes' && $is_mgr) {
        $notes = trim($_POST['notes'] ?? '');
        db()->prepare("UPDATE tasks SET notes=? WHERE id=?")->execute([$notes, $tid]);
        flash('success', 'Notes updated.');
        redirect("task_detail.php?id=$tid");
    }
}

// Time tracking sessions for this task
$timeLogs = db()->prepare("
    SELECT tt.*, e.name AS emp_name,
           ROUND(tt.total_seconds/3600, 2) AS hours
    FROM time_tracking tt
    JOIN employees e ON e.id = tt.employee_id
    WHERE tt.task_id = ? AND tt.status = 'finished'
    ORDER BY tt.started_at DESC
    LIMIT 30
");
$timeLogs->execute([$tid]);
$logs = $timeLogs->fetchAll();
$total_hours = array_sum(array_column($logs, 'hours'));

// Attachments
$attachments = db()->prepare("
    SELECT ta.*, e.name AS uploader_name
    FROM task_attachments ta
    JOIN employees e ON e.id = ta.uploaded_by
    WHERE ta.task_id = ?
    ORDER BY ta.uploaded_at DESC
");
$attachments->execute([$tid]);
$files = $attachments->fetchAll();

// Active timer for this task (if any)
$activeTimer = db()->prepare("SELECT * FROM time_tracking WHERE task_id=? AND employee_id=? AND status IN ('running','paused') LIMIT 1");
$activeTimer->execute([$tid, $eid]);
$timer = $activeTimer->fetch();

$today = date('Y-m-d');
$success = get_flash('success');
$error   = get_flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($task['title']) ?> – Task Detail</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="portal-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main">

    <div class="page-header">
      <div>
        <a href="tasks.php" style="font-size:.85rem; color:var(--clr-muted); text-decoration:none;"><i class="fa fa-arrow-left"></i> Back to Tasks</a>
        <h1 class="page-title" style="margin-top:.25rem"><?= h($task['title']) ?></h1>
      </div>
      <div class="header-actions">
        <a href="time_track.php?task=<?= $tid ?>" class="btn btn-outline"><i class="fa fa-stopwatch"></i> Track Time</a>
      </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <div class="two-col">
      <!-- Task Info -->
      <section class="section-card">
        <div class="section-header"><h2><i class="fa fa-info-circle"></i> Task Details</h2></div>

        <table class="data-table">
          <tbody>
            <tr><td style="width:40%;font-weight:600;color:var(--clr-muted)">Client</td><td><?= h($task['client_name'] ?? '—') ?></td></tr>
            <tr><td style="font-weight:600;color:var(--clr-muted)">Project</td><td><?= $task['project_name'] ? '<a href="project_detail.php?id=' . $task['project_id'] . '">' . h($task['project_name']) . '</a>' : '—' ?></td></tr>
            <tr><td style="font-weight:600;color:var(--clr-muted)">Assigned By</td><td><?= h($task['assigned_by_name'] ?? '—') ?></td></tr>
            <tr><td style="font-weight:600;color:var(--clr-muted)">Assigned To</td><td><?= h($task['assigned_to_name'] ?? '—') ?></td></tr>
            <tr><td style="font-weight:600;color:var(--clr-muted)">Priority</td><td><span class="badge priority-<?= $task['priority'] ?>"><?= ucfirst($task['priority']) ?></span></td></tr>
            <tr>
              <td style="font-weight:600;color:var(--clr-muted)">Due Date</td>
              <td class="<?= $task['due_date'] && $task['due_date'] < $today && !in_array($task['status'],['completed','cancelled']) ? 'text-danger' : '' ?>">
                <?= $task['due_date'] ? date('d M Y', strtotime($task['due_date'])) : '—' ?>
                <?php if ($task['due_date'] && $task['due_date'] < $today && !in_array($task['status'],['completed','cancelled'])): ?>
                  <span class="badge badge-danger">Overdue</span>
                <?php endif; ?>
              </td>
            </tr>
            <tr><td style="font-weight:600;color:var(--clr-muted)">Estimated Hours</td><td><?= $task['estimated_hours'] ? $task['estimated_hours'] . 'h' : '—' ?></td></tr>
            <tr><td style="font-weight:600;color:var(--clr-muted)">Time Logged</td><td><?= round($total_hours, 2) ?>h</td></tr>
            <tr><td style="font-weight:600;color:var(--clr-muted)">Created</td><td><?= date('d M Y H:i', strtotime($task['created_at'])) ?></td></tr>
          </tbody>
        </table>

        <!-- Status Update -->
        <div style="margin-top:1rem">
          <form method="post" style="display:flex; gap:.5rem; align-items:center; flex-wrap:wrap">
            <input type="hidden" name="action" value="update_status">
            <label style="font-size:.85rem; font-weight:500">Update Status:</label>
            <select name="status" class="input" style="width:auto">
              <?php foreach (['not_started'=>'Not Started','in_progress'=>'In Progress','waiting_client'=>'Waiting Client','under_review'=>'Under Review','completed'=>'Completed','cancelled'=>'Cancelled'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $task['status']===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-primary btn-sm"><i class="fa fa-save"></i> Update</button>
          </form>
        </div>
      </section>

      <!-- Description & Notes -->
      <section class="section-card">
        <div class="section-header"><h2><i class="fa fa-align-left"></i> Description</h2></div>
        <?php if ($task['description']): ?>
          <p style="white-space:pre-wrap; font-size:.9rem; line-height:1.6"><?= h($task['description']) ?></p>
        <?php else: ?>
          <p class="empty-state">No description provided.</p>
        <?php endif; ?>

        <div style="margin-top:1.25rem">
          <div class="section-header"><h2><i class="fa fa-sticky-note"></i> Notes</h2></div>
          <?php if ($is_mgr): ?>
            <form method="post">
              <input type="hidden" name="action" value="update_notes">
              <textarea name="notes" class="input" rows="4" placeholder="Internal notes…"><?= h($task['notes'] ?? '') ?></textarea>
              <button class="btn btn-outline btn-sm" style="margin-top:.5rem"><i class="fa fa-save"></i> Save Notes</button>
            </form>
          <?php elseif ($task['notes']): ?>
            <p style="white-space:pre-wrap; font-size:.9rem"><?= h($task['notes']) ?></p>
          <?php else: ?>
            <p class="empty-state" style="padding:.75rem">No notes.</p>
          <?php endif; ?>
        </div>
      </section>
    </div>

    <!-- Time Log -->
    <section class="section-card">
      <div class="section-header">
        <h2><i class="fa fa-clock"></i> Time Log</h2>
        <span class="badge badge-info"><?= round($total_hours, 2) ?>h total</span>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Employee</th><th>Date</th><th>Duration</th><th>Break</th><th>Net Hours</th></tr></thead>
          <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
              <td><?= h($log['emp_name']) ?></td>
              <td><?= date('d M Y H:i', strtotime($log['started_at'])) ?></td>
              <td><?= round($log['hours'], 2) ?>h</td>
              <td><?= round($log['break_seconds']/3600, 2) ?>h</td>
              <td><?= round($log['hours'] - $log['break_seconds']/3600, 2) ?>h</td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?>
              <tr><td colspan="5" class="text-center text-muted">No time logged yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Attachments -->
    <?php if ($files): ?>
    <section class="section-card">
      <div class="section-header"><h2><i class="fa fa-paperclip"></i> Attachments</h2></div>
      <div style="display:flex; flex-wrap:wrap; gap:.75rem">
        <?php foreach ($files as $f): ?>
        <a href="/uploads/<?= h($f['filepath']) ?>" target="_blank" class="btn btn-outline" style="font-size:.85rem">
          <i class="fa fa-file"></i> <?= h($f['filename']) ?>
          <small class="text-muted">(<?= h($f['uploader_name']) ?>, <?= date('d M', strtotime($f['uploaded_at'])) ?>)</small>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

  </main>
</div>
<script src="/assets/js/portal.js"></script>
</body>
</html>
