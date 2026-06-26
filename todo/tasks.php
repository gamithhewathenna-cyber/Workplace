<?php
/**
 * todo/tasks.php
 * Full task list + create/edit form
 */
require_once __DIR__ . '/../includes/config.php';
require_login();

$eid       = current_employee_id();
$is_mgr    = is_manager();
$today     = date('Y-m-d');

// ── Handle POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_task' && $is_mgr) {
        $fields = [
            'title'          => trim($_POST['title'] ?? ''),
            'client_id'      => $_POST['client_id'] ?: null,
            'project_id'     => $_POST['project_id'] ?: null,
            'description'    => trim($_POST['description'] ?? ''),
            'priority'       => $_POST['priority'] ?? 'medium',
            'due_date'       => $_POST['due_date'] ?: null,
            'assigned_by'    => $eid,
            'assigned_to'    => (int)$_POST['assigned_to'],
            'estimated_hours'=> $_POST['estimated_hours'] ?: null,
            'notes'          => trim($_POST['notes'] ?? ''),
        ];
        if (!$fields['title'] || !$fields['assigned_to']) {
            flash('error', 'Title and assigned employee are required.');
        } else {
            $st = db()->prepare("INSERT INTO tasks (title,client_id,project_id,description,priority,due_date,assigned_by,assigned_to,estimated_hours,notes)
                                 VALUES (:title,:client_id,:project_id,:description,:priority,:due_date,:assigned_by,:assigned_to,:estimated_hours,:notes)");
            $st->execute($fields);
            $tid = db()->lastInsertId();

            // Notify assigned employee
            add_notification(
                $fields['assigned_to'], 'new_task',
                'New Task Assigned',
                "You have been assigned: " . $fields['title'],
                "/todo/task_detail.php?id=$tid"
            );

            // Email assigned employee
            $ae = db()->prepare("SELECT name, email FROM employees WHERE id=? LIMIT 1");
            $ae->execute([$fields['assigned_to']]);
            $ae = $ae->fetch();
            if ($ae && $ae['email']) {
                $due_str  = $fields['due_date'] ? date('d M Y', strtotime($fields['due_date'])) : 'Not set';
                $task_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                          . '://' . $_SERVER['HTTP_HOST'] . "/todo/task_detail.php?id=$tid";
                $rows = '<tr><td style="padding:.4rem .75rem;color:#888;width:35%;border-bottom:1px solid #1e1e1e">Task</td>'
                      . '<td style="padding:.4rem .75rem;font-weight:600;border-bottom:1px solid #1e1e1e">' . htmlspecialchars($fields['title'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
                      . '<tr><td style="padding:.4rem .75rem;color:#888;border-bottom:1px solid #1e1e1e">Priority</td>'
                      . '<td style="padding:.4rem .75rem;border-bottom:1px solid #1e1e1e;text-transform:capitalize">' . htmlspecialchars($fields['priority'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
                      . '<tr><td style="padding:.4rem .75rem;color:#888">Due Date</td>'
                      . '<td style="padding:.4rem .75rem">' . $due_str . '</td></tr>';
                if ($fields['description']) {
                    $rows .= '<tr><td colspan="2" style="padding:.75rem .75rem 0;color:#888;font-size:.85rem">'
                           . htmlspecialchars($fields['description'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
                }
                $body = '<p>Hi <strong>' . htmlspecialchars($ae['name'], ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
                      . '<p>A new task has been assigned to you:</p>'
                      . '<table style="width:100%;border-collapse:collapse;background:#0d0d0d;border-radius:8px;overflow:hidden;margin:.75rem 0">' . $rows . '</table>';
                send_mail($ae['email'], $ae['name'], 'New Task Assigned: ' . $fields['title'],
                    mail_template('You have a new task', $body, 'View Task', $task_url));
            }

            // Handle file attachment
            if (!empty($_FILES['attachment']['name'])) {
                $orig = basename($_FILES['attachment']['name']);
                $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                $safe = uniqid() . '.' . $ext;
                if (!is_dir(UPLOAD_TASK_DIR)) mkdir(UPLOAD_TASK_DIR, 0755, true);
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], UPLOAD_TASK_DIR . $safe)) {
                    db()->prepare("INSERT INTO task_attachments (task_id,filename,filepath,uploaded_by) VALUES (?,?,?,?)")
                        ->execute([$tid, $orig, 'tasks/' . $safe, $eid]);
                }
            }

            flash('success', 'Task created successfully.');
        }
        redirect('tasks.php');
    }

    if ($action === 'update_status') {
        $tid    = (int)$_POST['task_id'];
        $status = $_POST['status'];
        $valid  = ['not_started','in_progress','waiting_client','under_review','completed','cancelled'];
        if (in_array($status, $valid, true)) {
            db()->prepare("UPDATE tasks SET status=? WHERE id=? AND (assigned_to=? OR ? = 1)")
               ->execute([$status, $tid, $eid, (int)$is_mgr]);
            flash('success', 'Task status updated.');
        }
        redirect('tasks.php');
    }
}

// ── Filters ────────────────────────────────────────────────
$status_filter   = $_GET['status']   ?? '';
$priority_filter = $_GET['priority'] ?? '';
$search          = trim($_GET['q'] ?? '');
$page            = max(1, (int)($_GET['page'] ?? 1));
$per_page        = 20;
$offset          = ($page - 1) * $per_page;

$where  = $is_mgr ? '1=1' : "t.assigned_to = $eid";
$params = [];

if ($status_filter)   { $where .= ' AND t.status=?';           $params[] = $status_filter; }
if ($priority_filter) { $where .= ' AND t.priority=?';         $params[] = $priority_filter; }
if ($search)          { $where .= ' AND t.title LIKE ?';       $params[] = "%$search%"; }

$total = db()->prepare("SELECT COUNT(*) FROM tasks t WHERE $where");
$total->execute($params);
$total_rows = (int)$total->fetchColumn();
$total_pages = max(1, ceil($total_rows / $per_page));

$stmt = db()->prepare("
    SELECT t.*, c.name AS client_name,
           e.name AS assignee_name, e.position AS assignee_role
    FROM tasks t
    LEFT JOIN clients c ON c.id = t.client_id
    LEFT JOIN employees e ON e.id = t.assigned_to
    WHERE $where
    ORDER BY FIELD(t.priority,'critical','high','medium','low'), t.due_date ASC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// For create form
$employees = $is_mgr ? db()->query("SELECT id,name FROM employees WHERE status='active' ORDER BY name")->fetchAll() : [];
$clients   = db()->query("SELECT id,name FROM clients WHERE is_active=1 ORDER BY name")->fetchAll();
$projects  = db()->query("SELECT id,name FROM projects WHERE status='active' ORDER BY name")->fetchAll();

$success = get_flash('success');
$error   = get_flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tasks – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="portal-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main">
    <div class="page-header">
      <h1 class="page-title"><i class="fa fa-clipboard-list"></i> Tasks</h1>
      <?php if ($is_mgr): ?>
        <button class="btn btn-primary" onclick="openModal('create-task-modal')"><i class="fa fa-plus"></i> Assign Task</button>
      <?php endif; ?>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <!-- Filters -->
    <form method="get" class="filter-bar">
      <input type="text" name="q" placeholder="Search tasks…" value="<?= h($search) ?>" class="input">
      <select name="status" class="input">
        <option value="">All Statuses</option>
        <option value="not_started"     <?= $status_filter === 'not_started'     ? 'selected' : '' ?>>Not Started</option>
        <option value="in_progress"     <?= $status_filter === 'in_progress'     ? 'selected' : '' ?>>In Progress</option>
        <option value="waiting_client"  <?= $status_filter === 'waiting_client'  ? 'selected' : '' ?>>Waiting for Client</option>
        <option value="under_review"    <?= $status_filter === 'under_review'    ? 'selected' : '' ?>>Under Review</option>
        <option value="completed"       <?= $status_filter === 'completed'       ? 'selected' : '' ?>>Completed</option>
        <option value="cancelled"       <?= $status_filter === 'cancelled'       ? 'selected' : '' ?>>Cancelled</option>
      </select>
      <select name="priority" class="input">
        <option value="">All Priorities</option>
        <option value="critical" <?= $priority_filter === 'critical' ? 'selected' : '' ?>>Critical</option>
        <option value="high"     <?= $priority_filter === 'high'     ? 'selected' : '' ?>>High</option>
        <option value="medium"   <?= $priority_filter === 'medium'   ? 'selected' : '' ?>>Medium</option>
        <option value="low"      <?= $priority_filter === 'low'      ? 'selected' : '' ?>>Low</option>
      </select>
      <button class="btn btn-outline"><i class="fa fa-search"></i> Filter</button>
      <a href="tasks.php" class="btn btn-ghost">Clear</a>
    </form>

    <!-- Task Table -->
    <section class="section-card">
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Title</th><th>Client</th>
              <?php if ($is_mgr): ?><th>Assigned To</th><?php endif; ?>
              <th>Priority</th><th>Due Date</th><th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tasks as $t): ?>
            <tr class="<?= $t['due_date'] && $t['due_date'] < $today && !in_array($t['status'],['completed','cancelled']) ? 'overdue-row' : '' ?>">
              <td><a href="task_detail.php?id=<?= $t['id'] ?>"><?= h($t['title']) ?></a></td>
              <td><?= h($t['client_name'] ?? '—') ?></td>
              <?php if ($is_mgr): ?><td><?= h($t['assignee_name'] ?? '—') ?></td><?php endif; ?>
              <td><span class="badge priority-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span></td>
              <td class="<?= $t['due_date'] && $t['due_date'] < $today ? 'text-danger' : '' ?>">
                <?= $t['due_date'] ? date('d M Y', strtotime($t['due_date'])) : '—' ?>
              </td>
              <td>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                  <select name="status" onchange="this.form.submit()" class="status-select status-<?= str_replace('_','-',$t['status']) ?>">
                    <?php foreach (['not_started'=>'Not Started','in_progress'=>'In Progress','waiting_client'=>'Waiting Client','under_review'=>'Under Review','completed'=>'Completed','cancelled'=>'Cancelled'] as $v=>$l): ?>
                      <option value="<?= $v ?>" <?= $t['status']===$v?'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
              <td>
                <a href="task_detail.php?id=<?= $t['id'] ?>" class="btn btn-xs"><i class="fa fa-eye"></i></a>
                <a href="time_track.php?task=<?= $t['id'] ?>" class="btn btn-xs btn-outline" title="Track Time"><i class="fa fa-stopwatch"></i></a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$tasks): ?>
              <tr><td colspan="<?= $is_mgr ? 7 : 6 ?>" class="text-center text-muted">No tasks found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
          <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&priority=<?= urlencode($priority_filter) ?>"
             class="page-btn <?= $page === $i ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </section>
  </main>
</div>

<!-- Create Task Modal -->
<?php if ($is_mgr): ?>
<div id="create-task-modal" class="modal-overlay" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="fa fa-plus"></i> Assign New Task</h3>
      <button onclick="closeModal('create-task-modal')" class="modal-close">&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data" class="modal-form">
      <input type="hidden" name="action" value="create_task">
      <div class="form-grid">
        <div class="form-group span-2">
          <label>Task Title *</label>
          <input type="text" name="title" class="input" required placeholder="Enter task title">
        </div>
        <div class="form-group">
          <label>Assign To *</label>
          <select name="assigned_to" class="input" required>
            <option value="">Select Employee</option>
            <?php foreach ($employees as $e): ?>
              <option value="<?= $e['id'] ?>"><?= h($e['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Client</label>
          <select name="client_id" class="input">
            <option value="">Select Client</option>
            <?php foreach ($clients as $c): ?>
              <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Project</label>
          <select name="project_id" class="input">
            <option value="">No Project</option>
            <?php foreach ($projects as $p): ?>
              <option value="<?= $p['id'] ?>"><?= h($p['name']) ?></option>
            <?php endforeach; ?>
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
          <label>Due Date</label>
          <input type="date" name="due_date" class="input" min="<?= $today ?>">
        </div>
        <div class="form-group">
          <label>Estimated Hours</label>
          <input type="number" name="estimated_hours" class="input" min="0.5" step="0.5" placeholder="e.g. 4">
        </div>
        <div class="form-group span-2">
          <label>Description</label>
          <textarea name="description" class="input" rows="3" placeholder="Detailed description…"></textarea>
        </div>
        <div class="form-group span-2">
          <label>Notes</label>
          <textarea name="notes" class="input" rows="2" placeholder="Additional notes…"></textarea>
        </div>
        <div class="form-group span-2">
          <label>Attachment</label>
          <input type="file" name="attachment" class="input">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Assign Task</button>
        <button type="button" onclick="closeModal('create-task-modal')" class="btn btn-ghost">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script src="/assets/js/portal.js"></script>
</body>
</html>
