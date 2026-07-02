<?php
/**
 * admin/checklist.php
 * Manager: manage recurring daily checklist templates, assign them to
 * specific employees (or everyone), and monitor daily completion.
 */
require_once __DIR__ . '/../includes/config.php';
require_login();
if (!is_manager()) redirect('/todo/index.php');

$eid = current_employee_id();

function checklist_scope_from_post(): string {
    return ($_POST['scope'] ?? 'all') === 'specific' ? 'specific' : 'all';
}

function checklist_employee_ids_from_post(): array {
    $ids = array_map('intval', $_POST['employee_ids'] ?? []);
    return array_values(array_unique(array_filter($ids, fn($id) => $id > 0)));
}

function checklist_save_assignees(int $templateId, string $scope, array $employeeIds): void {
    db()->prepare("DELETE FROM checklist_template_assignees WHERE template_id=?")->execute([$templateId]);
    if ($scope === 'specific' && $employeeIds) {
        $ins = db()->prepare("INSERT IGNORE INTO checklist_template_assignees (template_id,employee_id) VALUES (?,?)");
        foreach ($employeeIds as $empId) {
            $ins->execute([$templateId, $empId]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $scope = checklist_scope_from_post();
        $empIds = checklist_employee_ids_from_post();
        if ($title) {
            $max = db()->query("SELECT MAX(sort_order) FROM checklist_templates")->fetchColumn();
            db()->prepare("INSERT INTO checklist_templates (title,sort_order,created_by,scope) VALUES (?,?,?,?)")
               ->execute([$title, (int)$max + 1, $eid, $scope]);
            checklist_save_assignees((int)db()->lastInsertId(), $scope, $empIds);
            flash('success', 'Checklist item added.');
        }
    }
    elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        db()->prepare("UPDATE checklist_templates SET is_active = NOT is_active WHERE id=?")->execute([$id]);
    }
    elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        db()->prepare("DELETE FROM checklist_templates WHERE id=?")->execute([$id]);
        flash('success', 'Item deleted.');
    }
    elseif ($action === 'edit') {
        $id    = (int)$_POST['id'];
        $title = trim($_POST['title'] ?? '');
        $scope = checklist_scope_from_post();
        $empIds = checklist_employee_ids_from_post();
        if ($title) {
            db()->prepare("UPDATE checklist_templates SET title=?, scope=? WHERE id=?")->execute([$title, $scope, $id]);
            checklist_save_assignees($id, $scope, $empIds);
            flash('success', 'Item updated.');
        }
    }
    redirect('checklist.php');
}

$items = db()->query("
    SELECT ct.*,
           GROUP_CONCAT(e.id ORDER BY e.name SEPARATOR ',')   AS assignee_ids,
           GROUP_CONCAT(e.name ORDER BY e.name SEPARATOR ', ') AS assignee_names
    FROM checklist_templates ct
    LEFT JOIN checklist_template_assignees cta ON cta.template_id = ct.id
    LEFT JOIN employees e ON e.id = cta.employee_id
    GROUP BY ct.id
    ORDER BY ct.sort_order, ct.id
")->fetchAll();

$employees = db()->query("SELECT id, name FROM employees WHERE status='active' ORDER BY name")->fetchAll();

// ── Completion monitoring ───────────────────────────────────
$monitor_date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $monitor_date)) $monitor_date = date('Y-m-d');

$mrows = db()->prepare("
    SELECT e.id AS emp_id, e.name AS emp_name, ct.title, dc.is_completed, dc.completed_at
    FROM employees e
    LEFT JOIN daily_checklist dc ON dc.employee_id = e.id AND dc.check_date = ?
    LEFT JOIN checklist_templates ct ON ct.id = dc.template_id
    WHERE e.status = 'active'
    ORDER BY e.name, ct.sort_order
");
$mrows->execute([$monitor_date]);
$mrows = $mrows->fetchAll();

$byEmp = [];
foreach ($mrows as $r) {
    if (!isset($byEmp[$r['emp_id']])) {
        $byEmp[$r['emp_id']] = ['name' => $r['emp_name'], 'items' => []];
    }
    if ($r['title'] !== null) {
        $byEmp[$r['emp_id']]['items'][] = $r;
    }
}

$success = get_flash('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Checklist Setup – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="portal-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main">
    <div class="page-header">
      <h1 class="page-title"><i class="fa fa-list-check"></i> Daily Checklist</h1>
    </div>
    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

    <div class="two-col">
      <!-- Add New -->
      <section class="section-card">
        <div class="section-header"><h2><i class="fa fa-plus"></i> Add Item</h2></div>
        <form method="post">
          <input type="hidden" name="action" value="add">
          <div class="form-group" style="margin-bottom:.875rem">
            <label>Task Title *</label>
            <input type="text" name="title" class="input" required placeholder="e.g. Check client websites">
          </div>
          <div class="form-group" style="margin-bottom:.875rem">
            <label>Assign To</label>
            <div style="display:flex;gap:1rem;margin:.4rem 0">
              <label style="display:flex;align-items:center;gap:.35rem;font-weight:400;cursor:pointer">
                <input type="radio" name="scope" value="all" checked onchange="toggleScopeBox('add')"> All Employees
              </label>
              <label style="display:flex;align-items:center;gap:.35rem;font-weight:400;cursor:pointer">
                <input type="radio" name="scope" value="specific" onchange="toggleScopeBox('add')"> Specific Employees
              </label>
            </div>
            <div id="add-emp-box" style="display:none;background:var(--clr-bg);border:1.5px solid var(--clr-border);border-radius:8px;padding:.5rem;max-height:160px;overflow-y:auto">
              <?php foreach ($employees as $emp): ?>
              <label style="display:flex;align-items:center;gap:.5rem;padding:.25rem .5rem;cursor:pointer;font-size:.82rem">
                <input type="checkbox" name="employee_ids[]" value="<?= $emp['id'] ?>"> <?= h($emp['name']) ?>
              </label>
              <?php endforeach; ?>
              <?php if (!$employees): ?><p class="empty-state" style="font-size:.8rem">No active employees.</p><?php endif; ?>
            </div>
          </div>
          <button class="btn btn-primary"><i class="fa fa-plus"></i> Add to Checklist</button>
        </form>
      </section>

      <!-- Items List -->
      <section class="section-card">
        <div class="section-header"><h2><i class="fa fa-th-list"></i> Current Items (<?= count($items) ?>)</h2></div>
        <div class="checklist">
          <?php foreach ($items as $item): ?>
          <div class="checklist-item" style="justify-content:space-between; border:1px solid var(--clr-border); border-radius:8px; margin-bottom:.5rem; padding:.75rem">
            <div style="flex:1">
              <span style="font-weight:500; <?= !$item['is_active'] ? 'text-decoration:line-through;color:var(--clr-muted)' : '' ?>">
                <?= h($item['title']) ?>
              </span>
              <div style="margin-top:.3rem">
                <?php if ($item['scope'] === 'all'): ?>
                  <span class="badge badge-secondary" style="font-size:.65rem"><i class="fa fa-users"></i> All Employees</span>
                <?php elseif ($item['assignee_names']): ?>
                  <span class="badge badge-info" style="font-size:.65rem"><i class="fa fa-user"></i> <?= h($item['assignee_names']) ?></span>
                <?php else: ?>
                  <span class="badge badge-warning" style="font-size:.65rem"><i class="fa fa-triangle-exclamation"></i> No one assigned</span>
                <?php endif; ?>
              </div>
            </div>
            <div style="display:flex; gap:.35rem">
              <button onclick='editItem(<?= json_encode([
                  "id" => (int)$item["id"],
                  "title" => $item["title"],
                  "scope" => $item["scope"],
                  "assignee_ids" => $item["assignee_ids"] ? array_map("intval", explode(",", $item["assignee_ids"])) : [],
              ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="btn btn-xs btn-outline"><i class="fa fa-edit"></i></button>
              <form method="post" style="display:inline">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                <button class="btn btn-xs <?= $item['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                  <i class="fa <?= $item['is_active'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                </button>
              </form>
              <form method="post" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                <button class="btn btn-xs btn-danger" data-confirm="Delete this checklist item?"><i class="fa fa-trash"></i></button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (!$items): ?><p class="empty-state">No checklist items yet.</p><?php endif; ?>
        </div>
      </section>
    </div>

    <!-- Completion Monitoring -->
    <section class="section-card">
      <div class="section-header">
        <h2><i class="fa fa-user-check"></i> Employee Completion Status</h2>
        <form method="get" style="display:flex;align-items:center;gap:.5rem">
          <input type="date" name="date" class="input input-sm" value="<?= h($monitor_date) ?>" onchange="this.form.submit()">
        </form>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Employee</th><th>Progress</th><th>Items</th></tr></thead>
          <tbody>
            <?php foreach ($byEmp as $emp): ?>
            <?php
              $items2 = $emp['items'];
              $total  = count($items2);
              $done   = count(array_filter($items2, fn($i) => $i['is_completed']));
              $pct    = $total ? round($done / $total * 100) : 0;
            ?>
            <tr>
              <td><?= h($emp['name']) ?></td>
              <td>
                <?php if ($total): ?>
                  <span style="white-space:nowrap"><?= $done ?>/<?= $total ?></span>
                  <div class="progress-bar-wrap" style="width:100px;display:inline-block;vertical-align:middle;margin-left:.5rem">
                    <div class="progress-bar <?= $pct === 100 ? 'bar-green' : '' ?>" style="width:<?= $pct ?>%"></div>
                  </div>
                <?php else: ?>
                  <span class="text-muted">No checklist</span>
                <?php endif; ?>
              </td>
              <td>
                <?php foreach ($items2 as $it): ?>
                  <span class="badge <?= $it['is_completed'] ? 'badge-success' : 'badge-secondary' ?>" style="margin:.15rem" title="<?= $it['completed_at'] ? h(date('h:i A', strtotime($it['completed_at']))) : '' ?>">
                    <i class="fa <?= $it['is_completed'] ? 'fa-check' : 'fa-circle' ?>"></i> <?= h($it['title']) ?>
                  </span>
                <?php endforeach; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$byEmp): ?>
              <tr><td colspan="3" class="text-center text-muted">No active employees.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</div>

<!-- Edit Modal -->
<div id="edit-modal" class="modal-overlay" style="display:none">
  <div class="modal modal-sm">
    <div class="modal-header"><h3>Edit Item</h3><button onclick="closeModal('edit-modal')" class="modal-close">&times;</button></div>
    <form method="post" class="modal-form">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit-id">
      <div class="form-group" style="margin-bottom:.875rem">
        <label>Title</label>
        <input type="text" name="title" id="edit-title" class="input" required>
      </div>
      <div class="form-group" style="margin-bottom:.875rem">
        <label>Assign To</label>
        <div style="display:flex;gap:1rem;margin:.4rem 0">
          <label style="display:flex;align-items:center;gap:.35rem;font-weight:400;cursor:pointer">
            <input type="radio" name="scope" value="all" onchange="toggleScopeBox('edit')"> All Employees
          </label>
          <label style="display:flex;align-items:center;gap:.35rem;font-weight:400;cursor:pointer">
            <input type="radio" name="scope" value="specific" onchange="toggleScopeBox('edit')"> Specific Employees
          </label>
        </div>
        <div id="edit-emp-box" style="display:none;background:var(--clr-bg);border:1.5px solid var(--clr-border);border-radius:8px;padding:.5rem;max-height:160px;overflow-y:auto">
          <?php foreach ($employees as $emp): ?>
          <label style="display:flex;align-items:center;gap:.5rem;padding:.25rem .5rem;cursor:pointer;font-size:.82rem">
            <input type="checkbox" name="employee_ids[]" value="<?= $emp['id'] ?>"> <?= h($emp['name']) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Save</button>
        <button type="button" onclick="closeModal('edit-modal')" class="btn btn-ghost">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="/assets/js/portal.js"></script>
<script>
function toggleScopeBox(prefix) {
  const box = document.getElementById(prefix + '-emp-box');
  const checked = box.parentElement.querySelector('input[name="scope"]:checked');
  box.style.display = (checked && checked.value === 'specific') ? 'block' : 'none';
}

function editItem(item) {
  document.getElementById('edit-id').value = item.id;
  document.getElementById('edit-title').value = item.title;
  document.querySelector('#edit-modal input[name="scope"][value="' + item.scope + '"]').checked = true;
  document.querySelectorAll('#edit-emp-box input[type=checkbox]').forEach(function (cb) {
    cb.checked = item.assignee_ids.includes(parseInt(cb.value, 10));
  });
  toggleScopeBox('edit');
  openModal('edit-modal');
}
</script>
</body>
</html>
