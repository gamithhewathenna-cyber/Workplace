<?php
/**
 * todo/client_followups.php
 * Client Follow-ups — a lightweight per-client to-do list.
 * No due dates, priority, or assignee — just quick action items anyone
 * on the team can add and check off (e.g. "Send proposal", "Follow up on invoice").
 * A single follow-up can be added to several selected clients at once.
 */
require_once __DIR__ . '/../includes/config.php';
require_login();

$eid     = current_employee_id();
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title      = trim($_POST['title'] ?? '');
        $clientIds  = array_map('intval', $_POST['client_ids'] ?? []);
        $clientIds  = array_values(array_unique(array_filter($clientIds, fn($id) => $id > 0)));

        if (!$title || !$clientIds) {
            flash('error', 'Enter a title and select at least one client.');
        } else {
            $ins = db()->prepare("INSERT INTO client_followups (client_id, title, created_by) VALUES (?,?,?)");
            foreach ($clientIds as $cid) {
                $ins->execute([$cid, $title, $eid]);
            }
            $n = count($clientIds);
            flash('success', "Follow-up added to $n client" . ($n === 1 ? '' : 's') . ".");
        }
        redirect('client_followups.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $row = db()->prepare("SELECT created_by FROM client_followups WHERE id=?");
        $row->execute([$id]);
        $createdBy = $row->fetchColumn();
        // Anyone can add/check items, but only the person who added it (or an
        // admin) can delete it — keeps accidental clean-up from wiping
        // someone else's reminder.
        if ($createdBy !== false && ((int)$createdBy === $eid || $isAdmin)) {
            db()->prepare("DELETE FROM client_followups WHERE id=?")->execute([$id]);
            flash('success', 'Follow-up removed.');
        } else {
            flash('error', 'Only the person who added this (or an admin) can remove it.');
        }
        redirect('client_followups.php');
    }
}

$allClients = db()->query("SELECT id, name FROM clients WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

$items = db()->query("
    SELECT cf.id, cf.client_id, cf.title, cf.is_completed, cf.created_by, e.name AS creator_name
    FROM client_followups cf
    JOIN employees e ON e.id = cf.created_by
    ORDER BY cf.is_completed ASC, cf.created_at ASC
")->fetchAll();

$itemsByClient  = [];
$countsByClient = [];
foreach ($items as $it) {
    $cid = (int)$it['client_id'];
    $itemsByClient[$cid][] = $it;
    $countsByClient[$cid]['total'] = ($countsByClient[$cid]['total'] ?? 0) + 1;
    $countsByClient[$cid]['done']  = ($countsByClient[$cid]['done']  ?? 0) + ($it['is_completed'] ? 1 : 0);
}

// Only clients that actually have follow-ups get a card — no clutter from
// every single active client, per how this page is meant to be used.
$search = trim($_GET['q'] ?? '');
$clientsWithItems = array_filter($allClients, function ($c) use ($itemsByClient) {
    return !empty($itemsByClient[$c['id']]);
});
if ($search) {
    $needle = mb_strtolower($search);
    $clientsWithItems = array_filter($clientsWithItems, function ($c) use ($needle) {
        return str_contains(mb_strtolower($c['name']), $needle);
    });
}

$success = get_flash('success');
$error   = get_flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Client Follow-ups – Employee Portal</title>
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
        <h1 class="page-title"><i class="fa fa-user-clock"></i> Client Follow-ups</h1>
        <p class="page-sub">Quick action items per client — anyone can add or check one off.</p>
      </div>
      <button class="btn btn-primary" onclick="openModal('add-followup-modal')"><i class="fa fa-plus"></i> Add Follow-up</button>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <form method="get" class="filter-bar">
      <input type="text" name="q" class="input" placeholder="Search clients…" value="<?= h($search) ?>">
      <button class="btn btn-outline"><i class="fa fa-search"></i> Filter</button>
      <?php if ($search): ?><a href="client_followups.php" class="btn btn-ghost">Clear</a><?php endif; ?>
    </form>

    <div class="checklist-cards-grid">
      <?php foreach ($clientsWithItems as $c):
          $cItems = $itemsByClient[$c['id']] ?? [];
          $done   = $countsByClient[$c['id']]['done']  ?? 0;
          $total  = $countsByClient[$c['id']]['total'] ?? 0;
          $pct    = $total ? round($done / $total * 100) : 0;
      ?>
      <div class="section-card checklist-emp-card">
        <div class="section-header">
          <div style="display:flex;align-items:center;gap:.65rem">
            <div class="emp-avatar-sm"><i class="fa fa-building"></i></div>
            <div>
              <h2 style="font-size:.92rem;margin:0"><?= h($c['name']) ?></h2>
            </div>
          </div>
          <span class="badge <?= $pct === 100 ? 'badge-success' : 'badge-info' ?>"><?= $done ?>/<?= $total ?></span>
        </div>
        <div class="progress-bar-wrap" style="margin-bottom:.65rem">
          <div class="progress-bar <?= $pct === 100 ? 'bar-green' : '' ?>" style="width:<?= $pct ?>%"></div>
        </div>
        <div style="max-height:220px;overflow-y:auto">
          <?php foreach ($cItems as $it): ?>
            <div class="chk-item-row" data-id="<?= $it['id'] ?>">
              <button type="button" class="chk-toggle" onclick="toggleFollowup(<?= $it['id'] ?>)" style="background:none;border:none;cursor:pointer;color:inherit;padding:0">
                <i class="fa <?= $it['is_completed'] ? 'fa-check-circle' : 'fa-circle' ?>" style="color:<?= $it['is_completed'] ? '#4ade80' : 'var(--clr-muted)' ?>"></i>
              </button>
              <span class="chk-title" style="<?= $it['is_completed'] ? 'text-decoration:line-through;color:var(--clr-muted)' : '' ?>" title="Added by <?= h($it['creator_name']) ?>"><?= h($it['title']) ?></span>
              <?php if ((int)$it['created_by'] === $eid || $isAdmin): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Remove this follow-up?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $it['id'] ?>">
                  <button class="btn btn-xs btn-outline" style="padding:.15rem .4rem"><i class="fa fa-trash" style="font-size:.7rem"></i></button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (!$clientsWithItems): ?>
        <p class="empty-state">No follow-ups yet — click "Add Follow-up" to create one.</p>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- Add Follow-up Modal -->
<div class="modal-overlay" id="add-followup-modal" style="display:none" onclick="if(event.target===this)closeModal('add-followup-modal')">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="fa fa-user-clock" style="color:var(--clr-primary)"></i> Add Follow-up</h3>
      <button class="modal-close" onclick="closeModal('add-followup-modal')">×</button>
    </div>
    <form method="post" class="modal-form">
      <input type="hidden" name="action" value="add">
      <div class="form-group" style="margin-bottom:1rem">
        <label style="font-size:.75rem;font-weight:600;color:var(--clr-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:.4rem">Follow-up <span style="color:var(--clr-danger)">*</span></label>
        <input type="text" name="title" class="input" required placeholder="e.g. Send year-end summary">
      </div>
      <div class="form-group" style="margin-bottom:1rem">
        <label style="font-size:.75rem;font-weight:600;color:var(--clr-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:.4rem">
          Clients <span style="color:var(--clr-danger)">*</span>
        </label>
        <div style="background:var(--clr-bg);border:1.5px solid var(--clr-border);border-radius:8px;padding:.5rem;max-height:220px;overflow-y:auto">
          <label style="display:flex;align-items:center;gap:.5rem;padding:.3rem .5rem;cursor:pointer;font-size:.82rem;border-bottom:1px solid var(--clr-border);margin-bottom:.35rem;font-weight:600">
            <input type="checkbox" onchange="toggleAllClients(this)"> Select All Clients
          </label>
          <?php foreach ($allClients as $c): ?>
          <label class="followup-client-chk" style="display:flex;align-items:center;gap:.5rem;padding:.25rem .5rem;cursor:pointer;font-size:.82rem;border-radius:6px">
            <input type="checkbox" name="client_ids[]" value="<?= $c['id'] ?>">
            <span><?= h($c['name']) ?></span>
          </label>
          <?php endforeach; ?>
          <?php if (!$allClients): ?><p class="empty-state" style="font-size:.8rem">No active clients.</p><?php endif; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('add-followup-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> Add Follow-up</button>
      </div>
    </form>
  </div>
</div>

<script src="/assets/js/portal.js"></script>
<script>
async function toggleFollowup(id) {
  const res = await fetch('/api/client_followup_toggle.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({id})
  });
  const data = await res.json();
  if (data.ok) location.reload();
}

function toggleAllClients(cb) {
  document.querySelectorAll('.followup-client-chk input[type=checkbox]').forEach(function (c) { c.checked = cb.checked; });
}

<?php if ($error && strpos($error, 'client') !== false): ?>
document.addEventListener('DOMContentLoaded', () => openModal('add-followup-modal'));
<?php endif; ?>
</script>
</body>
</html>
