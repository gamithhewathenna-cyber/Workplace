<?php
/**
 * todo/project_detail.php
 * Full detail view for a single project
 */
require_once __DIR__ . '/../includes/config.php';
require_login();

$eid    = current_employee_id();
$is_mgr = is_manager();
$pid    = (int)($_GET['id'] ?? 0);

if (!$pid) { redirect('/todo/projects.php'); }

// Employees can only view projects they're assigned to; managers see all
if ($is_mgr) {
    $st = db()->prepare("SELECT p.*, c.name AS client_name FROM projects p LEFT JOIN clients c ON c.id=p.client_id WHERE p.id=?");
    $st->execute([$pid]);
} else {
    $st = db()->prepare("SELECT p.*, c.name AS client_name FROM projects p JOIN project_employees pe ON pe.project_id=p.id LEFT JOIN clients c ON c.id=p.client_id WHERE p.id=? AND pe.employee_id=?");
    $st->execute([$pid, $eid]);
}
$project = $st->fetch();
if (!$project) { redirect('/todo/projects.php'); }

// Handle POST: update progress (managers only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_mgr) {
    $action = $_POST['action'] ?? '';
    if ($action === 'update') {
        $pct    = max(0, min(100, (int)$_POST['completion_percent']));
        $status = $_POST['status'] ?? $project['status'];
        $valid  = ['active','on_hold','completed','cancelled'];
        if (!in_array($status, $valid, true)) $status = $project['status'];
        db()->prepare("UPDATE projects SET completion_percent=?, status=? WHERE id=?")
           ->execute([$pct, $status, $pid]);
        flash('success', 'Project updated.');
        redirect("project_detail.php?id=$pid");
    }
}

// Team members
$team = db()->prepare("
    SELECT e.id, e.name, e.position, pe.assigned_at
    FROM project_employees pe
    JOIN employees e ON e.id = pe.employee_id
    WHERE pe.project_id = ?
    ORDER BY e.name
");
$team->execute([$pid]);
$members = $team->fetchAll();

// Tasks in this project
$tasksSt = db()->prepare("
    SELECT t.*, e.name AS assignee_name
    FROM tasks t
    LEFT JOIN employees e ON e.id = t.assigned_to
    WHERE t.project_id = ?
    ORDER BY FIELD(t.priority,'critical','high','medium','low'), t.due_date ASC
");
$tasksSt->execute([$pid]);
$tasks = $tasksSt->fetchAll();

$total_tasks     = count($tasks);
$completed_tasks = count(array_filter($tasks, fn($t) => $t['status'] === 'completed'));
$today = date('Y-m-d');

$success = get_flash('success');
$error   = get_flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($project['name']) ?> – Project Detail</title>
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
        <a href="projects.php" style="font-size:.85rem; color:var(--clr-muted); text-decoration:none;"><i class="fa fa-arrow-left"></i> Back to Projects</a>
        <h1 class="page-title" style="margin-top:.25rem">
          <?= h($project['name']) ?>
          <span class="badge priority-<?= $project['priority'] ?>"><?= ucfirst($project['priority']) ?></span>
        </h1>
      </div>
      <?php if ($is_mgr): ?>
      <button class="btn btn-primary" onclick="openModal('update-project-modal')"><i class="fa fa-edit"></i> Update</button>
      <?php endif; ?>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="cards-row">
      <div class="card">
        <div class="card-icon"><i class="fa fa-circle-notch"></i></div>
        <div class="card-body">
          <div class="card-label">Status</div>
          <div class="card-value" style="font-size:1.1rem"><?= ucwords(str_replace('_',' ',$project['status'])) ?></div>
        </div>
      </div>
      <div class="card">
        <div class="card-icon"><i class="fa fa-tasks"></i></div>
        <div class="card-body">
          <div class="card-label">Tasks</div>
          <div class="card-value"><?= $completed_tasks ?> / <?= $total_tasks ?></div>
          <small>completed</small>
        </div>
      </div>
      <div class="card">
        <div class="card-icon"><i class="fa fa-chart-pie"></i></div>
        <div class="card-body">
          <div class="card-label">Progress</div>
          <div class="card-value"><?= $project['completion_percent'] ?>%</div>
          <div class="progress-bar-wrap"><div class="progress-bar" style="width:<?= $project['completion_percent'] ?>%"></div></div>
        </div>
      </div>
      <div class="card">
        <div class="card-icon"><i class="fa fa-users"></i></div>
        <div class="card-body">
          <div class="card-label">Team Size</div>
          <div class="card-value"><?= count($members) ?></div>
          <small>members</small>
        </div>
      </div>
    </div>

    <div class="two-col">
      <!-- Project Info -->
      <section class="section-card">
        <div class="section-header"><h2><i class="fa fa-info-circle"></i> Project Info</h2></div>
        <table class="data-table">
          <tbody>
            <tr><td style="width:40%;font-weight:600;color:var(--clr-muted)">Client</td><td><?= h($project['client_name'] ?? '—') ?></td></tr>
            <tr><td style="font-weight:600;color:var(--clr-muted)">Priority</td><td><span class="badge priority-<?= $project['priority'] ?>"><?= ucfirst($project['priority']) ?></span></td></tr>
            <tr>
              <td style="font-weight:600;color:var(--clr-muted)">Deadline</td>
              <td class="<?= $project['deadline'] && $project['deadline'] < $today && $project['status'] === 'active' ? 'text-danger' : '' ?>">
                <?= $project['deadline'] ? date('d M Y', strtotime($project['deadline'])) : '—' ?>
              </td>
            </tr>
            <tr><td style="font-weight:600;color:var(--clr-muted)">Created</td><td><?= date('d M Y', strtotime($project['created_at'])) ?></td></tr>
          </tbody>
        </table>
        <?php if ($project['description']): ?>
          <div style="margin-top:1rem; font-size:.9rem; line-height:1.6; color:var(--clr-text)">
            <div style="font-weight:600; font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; color:var(--clr-muted); margin-bottom:.4rem">Description</div>
            <p style="white-space:pre-wrap"><?= h($project['description']) ?></p>
          </div>
        <?php endif; ?>
      </section>

      <!-- Team Members -->
      <section class="section-card">
        <div class="section-header"><h2><i class="fa fa-users"></i> Team Members</h2></div>
        <?php if ($members): ?>
        <ul style="list-style:none">
          <?php foreach ($members as $m): ?>
          <li style="display:flex; align-items:center; gap:.75rem; padding:.5rem 0; border-bottom:1px solid var(--clr-border)">
            <div style="width:36px;height:36px;border-radius:50%;background:color-mix(in srgb,var(--clr-primary) 15%,transparent);color:var(--clr-primary);display:grid;place-items:center;font-weight:700;font-size:.9rem;flex-shrink:0">
              <?= strtoupper(substr($m['name'], 0, 1)) ?>
            </div>
            <div>
              <div style="font-weight:500"><?= h($m['name']) ?></div>
              <div style="font-size:.78rem; color:var(--clr-muted)"><?= h($m['position'] ?? '') ?></div>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
          <p class="empty-state">No team members assigned.</p>
        <?php endif; ?>
      </section>
    </div>

    <!-- Tasks -->
    <section class="section-card">
      <div class="section-header">
        <h2><i class="fa fa-clipboard-list"></i> Project Tasks</h2>
        <span class="badge"><?= $total_tasks ?> total</span>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Task</th><th>Assigned To</th><th>Priority</th><th>Due Date</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($tasks as $t): ?>
            <tr class="<?= $t['due_date'] && $t['due_date'] < $today && !in_array($t['status'],['completed','cancelled']) ? 'overdue-row' : '' ?>">
              <td><?= h($t['title']) ?></td>
              <td><?= h($t['assignee_name'] ?? '—') ?></td>
              <td><span class="badge priority-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span></td>
              <td><?= $t['due_date'] ? date('d M Y', strtotime($t['due_date'])) : '—' ?></td>
              <td><span class="badge badge-<?= $t['status']==='completed'?'success':($t['status']==='cancelled'?'secondary':'warning') ?>"><?= ucwords(str_replace('_',' ',$t['status'])) ?></span></td>
              <td><a href="task_detail.php?id=<?= $t['id'] ?>" class="btn btn-xs"><i class="fa fa-eye"></i></a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$tasks): ?>
              <tr><td colspan="6" class="text-center text-muted">No tasks for this project.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

  </main>
</div>

<?php if ($is_mgr): ?>
<div id="update-project-modal" class="modal-overlay" style="display:none">
  <div class="modal modal-sm">
    <div class="modal-header">
      <h3><i class="fa fa-edit"></i> Update Project</h3>
      <button onclick="closeModal('update-project-modal')" class="modal-close">&times;</button>
    </div>
    <form method="post" class="modal-form">
      <input type="hidden" name="action" value="update">
      <div class="form-group" style="margin-bottom:.875rem">
        <label>Status</label>
        <select name="status" class="input">
          <?php foreach (['active'=>'Active','on_hold'=>'On Hold','completed'=>'Completed','cancelled'=>'Cancelled'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= $project['status']===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:1rem">
        <label>Completion % (<?= $project['completion_percent'] ?>%)</label>
        <input type="range" name="completion_percent" min="0" max="100" value="<?= $project['completion_percent'] ?>" class="input" oninput="document.getElementById('pct-val').textContent=this.value+'%'">
        <span id="pct-val"><?= $project['completion_percent'] ?>%</span>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Save</button>
        <button type="button" onclick="closeModal('update-project-modal')" class="btn btn-ghost">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script src="/assets/js/portal.js"></script>
</body>
</html>
