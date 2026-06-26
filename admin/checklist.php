<?php
/**
 * admin/checklist.php
 * Manager: manage recurring daily checklist templates
 */
require_once __DIR__ . '/../includes/config.php';
require_login();
if (!is_manager()) redirect('/todo/index.php');

$eid = current_employee_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        if ($title) {
            $max = db()->query("SELECT MAX(sort_order) FROM checklist_templates")->fetchColumn();
            db()->prepare("INSERT INTO checklist_templates (title,sort_order,created_by) VALUES (?,?,?)")
               ->execute([$title, (int)$max + 1, $eid]);
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
        if ($title) {
            db()->prepare("UPDATE checklist_templates SET title=? WHERE id=?")->execute([$title, $id]);
            flash('success', 'Item updated.');
        }
    }
    redirect('checklist.php');
}

$items = db()->query("SELECT * FROM checklist_templates ORDER BY sort_order, id")->fetchAll();
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
      <h1 class="page-title"><i class="fa fa-list-check"></i> Recurring Checklist Setup</h1>
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
          <button class="btn btn-primary"><i class="fa fa-plus"></i> Add to Checklist</button>
        </form>
      </section>

      <!-- Items List -->
      <section class="section-card">
        <div class="section-header"><h2><i class="fa fa-th-list"></i> Current Items (<?= count($items) ?>)</h2></div>
        <div class="checklist">
          <?php foreach ($items as $item): ?>
          <div class="checklist-item" style="justify-content:space-between; border:1px solid var(--clr-border); border-radius:8px; margin-bottom:.5rem; padding:.75rem">
            <span style="font-weight:500; flex:1; <?= !$item['is_active'] ? 'text-decoration:line-through;color:var(--clr-muted)' : '' ?>">
              <?= h($item['title']) ?>
            </span>
            <div style="display:flex; gap:.35rem">
              <button onclick="editItem(<?= $item['id'] ?>, '<?= addslashes(h($item['title'])) ?>')" class="btn btn-xs btn-outline"><i class="fa fa-edit"></i></button>
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
        </div>
      </section>
    </div>
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
      <div class="modal-footer">
        <button class="btn btn-primary">Save</button>
        <button type="button" onclick="closeModal('edit-modal')" class="btn btn-ghost">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="/assets/js/portal.js"></script>
<script>
function editItem(id, title) {
  document.getElementById('edit-id').value = id;
  document.getElementById('edit-title').value = title;
  openModal('edit-modal');
}
</script>
</body>
</html>
