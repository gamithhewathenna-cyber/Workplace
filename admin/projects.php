<?php
/**
 * admin/projects.php
 * Manager: create, edit, and manage projects + assign employees
 */
require_once __DIR__ . '/../includes/config.php';
require_login();
if (!is_manager()) { redirect('/todo/projects.php'); }

$eid = current_employee_id();

// ── Handle POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name  = trim($_POST['name'] ?? '');
        if (!$name) { flash('error', 'Project name is required.'); redirect('projects.php'); }

        $st = db()->prepare("INSERT INTO projects (name,client_id,description,priority,status,deadline,created_by)
                             VALUES (?,?,?,?,?,?,?)");
        $st->execute([
            $name,
            $_POST['client_id'] ?: null,
            trim($_POST['description'] ?? ''),
            $_POST['priority'] ?? 'medium',
            $_POST['status'] ?? 'active',
            $_POST['deadline'] ?: null,
            $eid,
        ]);
        $pid = (int)db()->lastInsertId();

        // Assign employees
        if (!empty($_POST['employees']) && is_array($_POST['employees'])) {
            $ins = db()->prepare("INSERT IGNORE INTO project_employees (project_id, employee_id) VALUES (?,?)");
            foreach ($_POST['employees'] as $emp_id) {
                $ins->execute([$pid, (int)$emp_id]);
            }
        }

        flash('success', 'Project created successfully.');
        redirect("projects.php");
    }

    if ($action === 'update') {
        $pid = (int)$_POST['project_id'];
        if (!$pid) { redirect('projects.php'); }

        db()->prepare("UPDATE projects SET name=?,client_id=?,description=?,priority=?,status=?,deadline=?,completion_percent=? WHERE id=?")
           ->execute([
               trim($_POST['name'] ?? ''),
               $_POST['client_id'] ?: null,
               trim($_POST['description'] ?? ''),
               $_POST['priority'] ?? 'medium',
               $_POST['status'] ?? 'active',
               $_POST['deadline'] ?: null,
               max(0, min(100, (int)$_POST['completion_percent'])),
               $pid,
           ]);

        // Re-sync team
        db()->prepare("DELETE FROM project_employees WHERE project_id=?")->execute([$pid]);
        if (!empty($_POST['employees']) && is_array($_POST['employees'])) {
            $ins = db()->prepare("INSERT IGNORE INTO project_employees (project_id, employee_id) VALUES (?,?)");
            foreach ($_POST['employees'] as $emp_id) {
                $ins->execute([$pid, (int)$emp_id]);
            }
        }

        flash('success', 'Project updated.');
        redirect("projects.php");
    }

    if ($action === 'delete') {
        $pid = (int)$_POST['project_id'];
        if ($pid) {
            db()->prepare("DELETE FROM projects WHERE id=?")->execute([$pid]);
            flash('success', 'Project deleted.');
        }
        redirect("projects.php");
    }
}

// ── Filters ────────────────────────────────────────────────
$status_filter = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');
$today         = date('Y-m-d');

$where  = ['1=1'];
$params = [];
if ($status_filter) { $where[] = 'p.status = ?'; $params[] = $status_filter; }
if ($search)        { $where[] = 'p.name LIKE ?'; $params[] = "%$search%"; }

$stmt = db()->prepare("
    SELECT p.*, c.name AS client_name,
           COUNT(DISTINCT pe.employee_id) AS team_size,
           SUM(t.status = 'completed') AS done_tasks,
           COUNT(t.id) AS total_tasks
    FROM projects p
    LEFT JOIN clients c ON c.id = p.client_id
    LEFT JOIN project_employees pe ON pe.project_id = p.id
    LEFT JOIN tasks t ON t.project_id = p.id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY p.id
    ORDER BY FIELD(p.priority,'critical','high','medium','low'), p.deadline ASC
");
$stmt->execute($params);
$projects = $stmt->fetchAll();

// For forms
$employees = db()->query("SELECT id,name,position FROM employees WHERE status='active' ORDER BY name")->fetchAll();
$clients   = db()->query("SELECT id,name FROM clients WHERE is_active=1 ORDER BY name")->fetchAll();

// For edit modal: load selected project
$edit_pid  = (int)($_GET['edit'] ?? 0);
$editProj  = null;
$editTeam  = [];
if ($edit_pid) {
    $ep = db()->prepare("SELECT * FROM projects WHERE id=?");
    $ep->execute([$edit_pid]);
    $editProj = $ep->fetch();
    if ($editProj) {
        $et = db()->prepare("SELECT employee_id FROM project_employees WHERE project_id=?");
        $et->execute([$edit_pid]);
        $editTeam = array_column($et->fetchAll(), 'employee_id');
    }
}

$success = get_flash('success');
$error   = get_flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage Projects – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="portal-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main">
    <div class="page-header">
      <h1 class="page-title"><i class="fa fa-project-diagram"></i> Manage Projects</h1>
      <button class="btn btn-primary" onclick="openModal('create-project-modal')"><i class="fa fa-plus"></i> New Project</button>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <!-- Filters -->
    <form method="get" class="filter-bar">
      <input type="text" name="q" placeholder="Search projects…" value="<?= h($search) ?>" class="input">
      <select name="status" class="input" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <option value="active"    <?= $status_filter==='active'?'selected':'' ?>>Active</option>
        <option value="on_hold"   <?= $status_filter==='on_hold'?'selected':'' ?>>On Hold</option>
        <option value="completed" <?= $status_filter==='completed'?'selected':'' ?>>Completed</option>
        <option value="cancelled" <?= $status_filter==='cancelled'?'selected':'' ?>>Cancelled</option>
      </select>
      <button class="btn btn-outline"><i class="fa fa-search"></i> Filter</button>
      <a href="projects.php" class="btn btn-ghost">Clear</a>
    </form>

    <!-- Projects Table -->
    <section class="section-card">
      <div class="section-header">
        <h2><i class="fa fa-list"></i> Projects</h2>
        <span class="badge badge-info"><?= count($projects) ?></span>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr><th>Project</th><th>Client</th><th>Priority</th><th>Status</th><th>Progress</th><th>Deadline</th><th>Team</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($projects as $p): ?>
            <tr class="<?= $p['deadline'] && $p['deadline'] < $today && $p['status']==='active' ? 'overdue-row' : '' ?>">
              <td>
                <a href="/todo/project_detail.php?id=<?= $p['id'] ?>" style="font-weight:500"><?= h($p['name']) ?></a>
                <?php if ($p['total_tasks']): ?>
                  <div style="font-size:.75rem; color:var(--clr-muted)"><?= (int)$p['done_tasks'] ?>/<?= (int)$p['total_tasks'] ?> tasks</div>
                <?php endif; ?>
              </td>
              <td><?= h($p['client_name'] ?? '—') ?></td>
              <td><span class="badge priority-<?= $p['priority'] ?>"><?= ucfirst($p['priority']) ?></span></td>
              <td>
                <span class="badge badge-<?= $p['status']==='active'?'success':($p['status']==='on_hold'?'warning':'secondary') ?>">
                  <?= ucwords(str_replace('_',' ',$p['status'])) ?>
                </span>
              </td>
              <td style="min-width:100px">
                <div class="progress-bar-wrap" style="margin:0"><div class="progress-bar" style="width:<?= $p['completion_percent'] ?>%"></div></div>
                <div style="font-size:.72rem; color:var(--clr-muted)"><?= $p['completion_percent'] ?>%</div>
              </td>
              <td class="<?= $p['deadline'] && $p['deadline'] < $today && $p['status']==='active' ? 'text-danger' : '' ?>">
                <?= $p['deadline'] ? date('d M Y', strtotime($p['deadline'])) : '—' ?>
              </td>
              <td><span class="badge badge-info"><i class="fa fa-users"></i> <?= (int)$p['team_size'] ?></span></td>
              <td>
                <a href="?edit=<?= $p['id'] ?>" class="btn btn-xs btn-outline"><i class="fa fa-edit"></i></a>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="project_id" value="<?= $p['id'] ?>">
                  <button class="btn btn-xs btn-danger" data-confirm="Delete project '<?= addslashes($p['name']) ?>'? All tasks will lose their project link."><i class="fa fa-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$projects): ?>
              <tr><td colspan="8" class="text-center text-muted">No projects found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</div>

<!-- Create Project Modal -->
<div id="create-project-modal" class="modal-overlay" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="fa fa-plus"></i> New Project</h3>
      <button onclick="closeModal('create-project-modal')" class="modal-close">&times;</button>
    </div>
    <form method="post" class="modal-form">
      <input type="hidden" name="action" value="create">
      <div class="form-grid">
        <div class="form-group span-2">
          <label>Project Name *</label>
          <input type="text" name="name" class="input" required placeholder="Project name">
        </div>
        <div class="form-group">
          <label>Client</label>
          <select name="client_id" class="input">
            <option value="">No Client</option>
            <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Priority</label>
          <select name="priority" class="input">
            <option value="low">Low</option>
            <option value="medium" selected>Medium</option>
            <option value="high">High</option>
            <option value="critical">Critical</option>
          </select>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" class="input">
            <option value="active" selected>Active</option>
            <option value="on_hold">On Hold</option>
          </select>
        </div>
        <div class="form-group">
          <label>Deadline</label>
          <input type="date" name="deadline" class="input" min="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group span-2">
          <label>Description</label>
          <textarea name="description" class="input" rows="3" placeholder="Project description…"></textarea>
        </div>
        <div class="form-group span-2">
          <label>Assign Team Members</label>
          <div style="max-height:180px; overflow-y:auto; border:1px solid var(--clr-border); border-radius:8px; padding:.5rem">
            <?php foreach ($employees as $e): ?>
            <label style="display:flex; align-items:center; gap:.5rem; padding:.3rem; cursor:pointer; border-radius:4px; font-size:.875rem">
              <input type="checkbox" name="employees[]" value="<?= $e['id'] ?>">
              <?= h($e['name']) ?> <small class="text-muted">(<?= h($e['position'] ?? '') ?>)</small>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary"><i class="fa fa-save"></i> Create Project</button>
        <button type="button" onclick="closeModal('create-project-modal')" class="btn btn-ghost">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Project Modal (auto-open if ?edit= set) -->
<?php if ($editProj): ?>
<div id="edit-project-modal" class="modal-overlay" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="fa fa-edit"></i> Edit Project</h3>
      <button onclick="closeModal('edit-project-modal'); location.href='projects.php'" class="modal-close">&times;</button>
    </div>
    <form method="post" class="modal-form">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="project_id" value="<?= $editProj['id'] ?>">
      <div class="form-grid">
        <div class="form-group span-2">
          <label>Project Name *</label>
          <input type="text" name="name" class="input" required value="<?= h($editProj['name']) ?>">
        </div>
        <div class="form-group">
          <label>Client</label>
          <select name="client_id" class="input">
            <option value="">No Client</option>
            <?php foreach ($clients as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $editProj['client_id']==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Priority</label>
          <select name="priority" class="input">
            <?php foreach (['low','medium','high','critical'] as $pr): ?>
              <option value="<?= $pr ?>" <?= $editProj['priority']===$pr?'selected':'' ?>><?= ucfirst($pr) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" class="input">
            <?php foreach (['active'=>'Active','on_hold'=>'On Hold','completed'=>'Completed','cancelled'=>'Cancelled'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= $editProj['status']===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Deadline</label>
          <input type="date" name="deadline" class="input" value="<?= h($editProj['deadline'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Completion %</label>
          <input type="number" name="completion_percent" class="input" min="0" max="100" value="<?= $editProj['completion_percent'] ?>">
        </div>
        <div class="form-group span-2">
          <label>Description</label>
          <textarea name="description" class="input" rows="3"><?= h($editProj['description'] ?? '') ?></textarea>
        </div>
        <div class="form-group span-2">
          <label>Team Members</label>
          <div style="max-height:180px; overflow-y:auto; border:1px solid var(--clr-border); border-radius:8px; padding:.5rem">
            <?php foreach ($employees as $e): ?>
            <label style="display:flex; align-items:center; gap:.5rem; padding:.3rem; cursor:pointer; border-radius:4px; font-size:.875rem">
              <input type="checkbox" name="employees[]" value="<?= $e['id'] ?>" <?= in_array($e['id'], $editTeam)?'checked':'' ?>>
              <?= h($e['name']) ?> <small class="text-muted">(<?= h($e['position'] ?? '') ?>)</small>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary"><i class="fa fa-save"></i> Save Changes</button>
        <button type="button" onclick="closeModal('edit-project-modal'); location.href='projects.php'" class="btn btn-ghost">Cancel</button>
      </div>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => openModal('edit-project-modal'));
</script>
<?php endif; ?>

<script src="/assets/js/portal.js"></script>
</body>
</html>
