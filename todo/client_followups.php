<?php
/**
 * todo/client_followups.php
 * Client Follow-ups — a lightweight per-client to-do list.
 * No due dates, priority, or assignee — just quick action items anyone
 * on the team can add and check off (e.g. "Send proposal", "Follow up on invoice").
 */
require_once __DIR__ . '/../includes/config.php';
require_login();

$eid     = current_employee_id();
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $clientId = (int)($_POST['client_id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        if ($clientId && $title) {
            db()->prepare("INSERT INTO client_followups (client_id, title, created_by) VALUES (?,?,?)")
               ->execute([$clientId, $title, $eid]);
            flash('success', 'Follow-up added.');
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

$search = trim($_GET['q'] ?? '');
$where  = "c.is_active = 1";
$params = [];
if ($search) {
    $where .= " AND c.name LIKE ?";
    $params[] = "%$search%";
}

$clients = db()->prepare("SELECT c.id, c.name FROM clients c WHERE $where ORDER BY c.name ASC");
$clients->execute($params);
$clients = $clients->fetchAll();

$items = db()->query("
    SELECT cf.id, cf.client_id, cf.title, cf.is_completed, cf.created_by, e.name AS creator_name
    FROM client_followups cf
    JOIN employees e ON e.id = cf.created_by
    ORDER BY cf.is_completed ASC, cf.created_at ASC
")->fetchAll();

$itemsByClient = [];
$countsByClient = [];
foreach ($items as $it) {
    $cid = (int)$it['client_id'];
    $itemsByClient[$cid][] = $it;
    $countsByClient[$cid]['total'] = ($countsByClient[$cid]['total'] ?? 0) + 1;
    $countsByClient[$cid]['done']  = ($countsByClient[$cid]['done']  ?? 0) + ($it['is_completed'] ? 1 : 0);
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
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <form method="get" class="filter-bar">
      <input type="text" name="q" class="input" placeholder="Search clients…" value="<?= h($search) ?>">
      <button class="btn btn-outline"><i class="fa fa-search"></i> Filter</button>
      <?php if ($search): ?><a href="client_followups.php" class="btn btn-ghost">Clear</a><?php endif; ?>
    </form>

    <div class="checklist-cards-grid">
      <?php foreach ($clients as $c):
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
          <span class="badge <?= !$total ? 'badge-secondary' : ($pct === 100 ? 'badge-success' : 'badge-info') ?>"><?= $done ?>/<?= $total ?></span>
        </div>
        <?php if ($total): ?>
        <div class="progress-bar-wrap" style="margin-bottom:.65rem">
          <div class="progress-bar <?= $pct === 100 ? 'bar-green' : '' ?>" style="width:<?= $pct ?>%"></div>
        </div>
        <?php endif; ?>
        <div style="max-height:220px;overflow-y:auto;margin-bottom:.65rem">
          <?php if (!$cItems): ?>
            <p class="empty-state" style="padding:.5rem 0">No follow-ups yet.</p>
          <?php else: foreach ($cItems as $it): ?>
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
          <?php endforeach; endif; ?>
        </div>
        <form method="post" style="display:flex;gap:.4rem">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="client_id" value="<?= $c['id'] ?>">
          <input type="text" name="title" class="input" placeholder="Add a follow-up…" required style="font-size:.8rem;padding:.4rem .6rem">
          <button class="btn btn-xs btn-primary"><i class="fa fa-plus"></i></button>
        </form>
      </div>
      <?php endforeach; ?>
      <?php if (!$clients): ?>
        <p class="empty-state">No active clients found.</p>
      <?php endif; ?>
    </div>
  </main>
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
</script>
</body>
</html>
