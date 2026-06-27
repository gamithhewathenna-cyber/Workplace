<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

if (!is_manager()) redirect('/todo/index.php');

$msg  = '';
$type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name    = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact_person'] ?? '');
        $email   = strtolower(trim($_POST['email'] ?? ''));
        $phone   = trim($_POST['phone'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $notes   = trim($_POST['notes'] ?? '');

        if (!$name) {
            $msg = 'Client name is required.'; $type = 'error';
        } else {
            db()->prepare("INSERT INTO clients (name, contact_person, email, phone, website, notes, is_active) VALUES (?,?,?,?,?,?,1)")
               ->execute([$name, $contact, $email, $phone, $website, $notes]);
            $msg = 'Client added successfully.'; $type = 'ok';
        }
    }

    if ($action === 'edit') {
        $id      = (int)($_POST['id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact_person'] ?? '');
        $email   = strtolower(trim($_POST['email'] ?? ''));
        $phone   = trim($_POST['phone'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $notes   = trim($_POST['notes'] ?? '');

        if (!$name || !$id) {
            $msg = 'Client name is required.'; $type = 'error';
        } else {
            db()->prepare("UPDATE clients SET name=?, contact_person=?, email=?, phone=?, website=?, notes=? WHERE id=?")
               ->execute([$name, $contact, $email, $phone, $website, $notes, $id]);
            $msg = 'Client updated.'; $type = 'ok';
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) db()->prepare("UPDATE clients SET is_active = 1 - is_active WHERE id=?")->execute([$id]);
        redirect('clients.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $cnt = db()->prepare("SELECT COUNT(*) FROM tasks WHERE client_id=?");
            $cnt->execute([$id]);
            if ((int)$cnt->fetchColumn() > 0) {
                $msg = 'Cannot delete: this client has tasks linked to them.'; $type = 'error';
            } else {
                db()->prepare("DELETE FROM clients WHERE id=?")->execute([$id]);
                $msg = 'Client deleted.'; $type = 'ok';
            }
        }
    }
}

$search  = trim($_GET['q'] ?? '');
$qwhere  = '1=1';
$qparams = [];
if ($search) {
    $qwhere  .= ' AND (name LIKE ? OR contact_person LIKE ? OR email LIKE ?)';
    $qparams  = ["%$search%", "%$search%", "%$search%"];
}

$clients = db()->prepare("SELECT * FROM clients WHERE $qwhere ORDER BY is_active DESC, name ASC");
$clients->execute($qparams);
$clients = $clients->fetchAll();

$total  = count($clients);
$active = count(array_filter($clients, fn($c) => $c['is_active']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Clients – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="portal-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main">

    <div class="page-header">
      <h1 class="page-title"><i class="fa fa-building"></i> Clients</h1>
      <button class="btn btn-primary" onclick="openModal('add-client-modal')">
        <i class="fa fa-plus"></i> Add Client
      </button>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-<?= $type === 'ok' ? 'success' : 'danger' ?>"><?= h($msg) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-value"><?= $total ?></div>
        <div class="stat-label">Total Clients</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $active ?></div>
        <div class="stat-label">Active</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $total - $active ?></div>
        <div class="stat-label">Inactive</div>
      </div>
    </div>

    <!-- Search -->
    <form method="get" class="filter-bar">
      <input type="text" name="q" placeholder="Search clients…" value="<?= h($search) ?>" class="input">
      <button class="btn btn-outline"><i class="fa fa-search"></i> Search</button>
      <?php if ($search): ?>
        <a href="clients.php" class="btn btn-ghost">Clear</a>
      <?php endif; ?>
    </form>

    <!-- Table -->
    <section class="section-card">
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Client</th>
              <th>Contact Person</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($clients as $c): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:.65rem">
                  <div style="width:34px;height:34px;border-radius:8px;background:rgba(125,69,154,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.8rem;font-weight:700;color:#c084fc">
                    <?= strtoupper(substr($c['name'], 0, 1)) ?>
                  </div>
                  <div>
                    <div style="font-weight:600;color:#f0f0f0"><?= h($c['name']) ?></div>
                    <?php if (!empty($c['website'])): ?>
                      <div style="font-size:.73rem;color:rgba(240,240,240,.38)"><?= h($c['website']) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td><?= h($c['contact_person'] ?? '—') ?></td>
              <td>
                <?php if (!empty($c['email'])): ?>
                  <a href="mailto:<?= h($c['email']) ?>" style="color:#c084fc;text-decoration:none"><?= h($c['email']) ?></a>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td><?= h($c['phone'] ?? '—') ?></td>
              <td>
                <span class="badge <?= $c['is_active'] ? 'badge-success' : 'badge-muted' ?>">
                  <?= $c['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td>
                <button class="btn btn-xs btn-outline" onclick='openEditModal(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Edit">
                  <i class="fa fa-edit"></i>
                </button>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button type="submit" class="btn btn-xs btn-outline" title="<?= $c['is_active'] ? 'Deactivate' : 'Activate' ?>">
                    <i class="fa <?= $c['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i>
                  </button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete <?= h(addslashes($c['name'])) ?>? This cannot be undone.')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button type="submit" class="btn btn-xs btn-danger" title="Delete">
                    <i class="fa fa-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$clients): ?>
              <tr>
                <td colspan="6" class="text-center text-muted" style="padding:2rem">
                  No clients found.
                  <a href="#" onclick="openModal('add-client-modal');return false" style="color:#c084fc;margin-left:.3rem">Add your first client.</a>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

  </main>
</div>

<!-- Add Client Modal -->
<div id="add-client-modal" class="modal-overlay" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="fa fa-plus"></i> Add Client</h3>
      <button onclick="closeModal('add-client-modal')" class="modal-close">&times;</button>
    </div>
    <form method="post" class="modal-form">
      <input type="hidden" name="action" value="add">
      <div class="form-grid">
        <div class="form-group span-2">
          <label>Client Name *</label>
          <input type="text" name="name" class="input" required placeholder="e.g. Acme Corporation">
        </div>
        <div class="form-group">
          <label>Contact Person</label>
          <input type="text" name="contact_person" class="input" placeholder="Primary contact name">
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" class="input" placeholder="contact@client.com">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" class="input" placeholder="+94 77 000 0000">
        </div>
        <div class="form-group">
          <label>Website</label>
          <input type="text" name="website" class="input" placeholder="www.client.com">
        </div>
        <div class="form-group span-2">
          <label>Notes</label>
          <textarea name="notes" class="input" rows="3" placeholder="Any notes about this client…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Add Client</button>
        <button type="button" onclick="closeModal('add-client-modal')" class="btn btn-ghost">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Client Modal -->
<div id="edit-client-modal" class="modal-overlay" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="fa fa-edit"></i> Edit Client</h3>
      <button onclick="closeModal('edit-client-modal')" class="modal-close">&times;</button>
    </div>
    <form method="post" class="modal-form">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit-id">
      <div class="form-grid">
        <div class="form-group span-2">
          <label>Client Name *</label>
          <input type="text" name="name" id="edit-name" class="input" required>
        </div>
        <div class="form-group">
          <label>Contact Person</label>
          <input type="text" name="contact_person" id="edit-contact" class="input">
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" id="edit-email" class="input">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" id="edit-phone" class="input">
        </div>
        <div class="form-group">
          <label>Website</label>
          <input type="text" name="website" id="edit-website" class="input">
        </div>
        <div class="form-group span-2">
          <label>Notes</label>
          <textarea name="notes" id="edit-notes" class="input" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Changes</button>
        <button type="button" onclick="closeModal('edit-client-modal')" class="btn btn-ghost">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="/assets/js/portal.js"></script>
<script>
function openEditModal(c) {
  document.getElementById('edit-id').value      = c.id      || '';
  document.getElementById('edit-name').value    = c.name    || '';
  document.getElementById('edit-contact').value = c.contact_person || '';
  document.getElementById('edit-email').value   = c.email   || '';
  document.getElementById('edit-phone').value   = c.phone   || '';
  document.getElementById('edit-website').value = c.website || '';
  document.getElementById('edit-notes').value   = c.notes   || '';
  openModal('edit-client-modal');
}
</script>
</body>
</html>
