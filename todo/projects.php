<?php
/**
 * todo/projects.php
 * Employee: view assigned projects
 */
require_once __DIR__ . '/../includes/config.php';
require_login();

$eid    = current_employee_id();
$is_mgr = is_manager();
$today  = date('Y-m-d');

$status_filter = $_GET['status'] ?? 'active';
$search        = trim($_GET['q'] ?? '');

$params = [];
$where  = [];

if ($is_mgr) {
    $join = '';
} else {
    $join    = 'JOIN project_employees pe ON pe.project_id = p.id AND pe.employee_id = ?';
    $params[] = $eid;
}

if ($status_filter && $status_filter !== 'all') {
    $where[]  = 'p.status = ?';
    $params[] = $status_filter;
}
if ($search) {
    $where[]  = 'p.name LIKE ?';
    $params[] = "%$search%";
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = db()->prepare("
    SELECT p.*, c.name AS client_name,
           COUNT(DISTINCT pe2.employee_id) AS team_size,
           SUM(t.status = 'completed') AS done_tasks,
           COUNT(t.id) AS total_tasks
    FROM projects p
    $join
    LEFT JOIN clients c ON c.id = p.client_id
    LEFT JOIN project_employees pe2 ON pe2.project_id = p.id
    LEFT JOIN tasks t ON t.project_id = p.id
    $where_sql
    GROUP BY p.id
    ORDER BY FIELD(p.priority,'critical','high','medium','low'), p.deadline ASC
");
$stmt->execute($params);
$projects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Projects – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="portal-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main">
    <div class="page-header">
      <h1 class="page-title"><i class="fa fa-folder-open"></i> <?= $is_mgr ? 'All Projects' : 'My Projects' ?></h1>
      <?php if ($is_mgr): ?>
        <a href="/admin/projects.php" class="btn btn-primary"><i class="fa fa-plus"></i> New Project</a>
      <?php endif; ?>
    </div>

    <!-- Filters -->
    <form method="get" class="filter-bar">
      <input type="text" name="q" placeholder="Search projects…" value="<?= h($search) ?>" class="input">
      <select name="status" class="input" onchange="this.form.submit()">
        <option value="all" <?= $status_filter==='all'?'selected':'' ?>>All Statuses</option>
        <option value="active"    <?= $status_filter==='active'?'selected':'' ?>>Active</option>
        <option value="on_hold"   <?= $status_filter==='on_hold'?'selected':'' ?>>On Hold</option>
        <option value="completed" <?= $status_filter==='completed'?'selected':'' ?>>Completed</option>
        <option value="cancelled" <?= $status_filter==='cancelled'?'selected':'' ?>>Cancelled</option>
      </select>
      <button class="btn btn-outline"><i class="fa fa-search"></i> Search</button>
      <a href="projects.php" class="btn btn-ghost">Clear</a>
    </form>

    <?php if ($projects): ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1.25rem">
      <?php foreach ($projects as $p): ?>
      <a href="project_detail.php?id=<?= $p['id'] ?>" class="project-card" style="display:block; text-decoration:none; color:inherit;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:.5rem">
          <div class="proj-name"><?= h($p['name']) ?></div>
          <span class="badge priority-<?= $p['priority'] ?>"><?= ucfirst($p['priority']) ?></span>
        </div>
        <div class="proj-meta">
          <span><i class="fa fa-building" style="margin-right:.3rem"></i><?= h($p['client_name'] ?? 'No Client') ?></span>
          <span class="badge badge-<?= $p['status']==='active'?'success':($p['status']==='on_hold'?'warning':'secondary') ?>">
            <?= ucwords(str_replace('_',' ',$p['status'])) ?>
          </span>
        </div>
        <div class="progress-bar-wrap"><div class="progress-bar" style="width:<?= $p['completion_percent'] ?>%"></div></div>
        <div class="proj-footer">
          <span><?= $p['completion_percent'] ?>% &bull; <?= (int)$p['done_tasks'] ?>/<?= (int)$p['total_tasks'] ?> tasks</span>
          <?php if ($p['deadline']): ?>
            <span class="<?= $p['deadline'] < $today && $p['status']==='active' ? 'text-danger' : '' ?>">
              <i class="fa fa-calendar-alt"></i> <?= date('d M Y', strtotime($p['deadline'])) ?>
            </span>
          <?php endif; ?>
        </div>
        <div style="margin-top:.4rem; font-size:.75rem; color:var(--clr-muted)">
          <i class="fa fa-users"></i> <?= (int)$p['team_size'] ?> member<?= $p['team_size'] != 1 ? 's' : '' ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <section class="section-card">
        <p class="empty-state"><i class="fa fa-folder-open"></i> No projects found.</p>
      </section>
    <?php endif; ?>
  </main>
</div>
<script src="/assets/js/portal.js"></script>
</body>
</html>
