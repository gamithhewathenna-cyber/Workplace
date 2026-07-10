<?php
/**
 * admin/announcements.php
 * Manager: post company-wide announcements (broadcast to everyone)
 */
require_once __DIR__ . '/../includes/config.php';
require_login();
if (!is_manager()) redirect('/todo/index.php');

$eid = current_employee_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'post') {
        $title   = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if (!$title || !$message) {
            flash('error', 'Title and message are both required.');
        } else {
            db()->prepare("INSERT INTO company_announcements (title, message, created_by) VALUES (?,?,?)")
               ->execute([$title, $message, $eid]);
            $aid = (int)db()->lastInsertId();

            // Notify every active employee (except the poster) via the bell
            $others = db()->prepare("SELECT id FROM employees WHERE status='active' AND id != ?");
            $others->execute([$eid]);
            foreach ($others->fetchAll(PDO::FETCH_COLUMN) as $otherId) {
                add_notification(
                    (int)$otherId, 'announcement',
                    'Company Announcement: ' . $title,
                    mb_strimwidth($message, 0, 140, '…'),
                    '/todo/index.php'
                );
            }
            flash('success', 'Announcement posted — everyone will see it on their dashboard.');
        }
        redirect('announcements.php');
    }

    if ($action === 'toggle') {
        $id = (int)$_POST['id'];
        db()->prepare("UPDATE company_announcements SET is_active = NOT is_active WHERE id=?")->execute([$id]);
        redirect('announcements.php');
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        db()->prepare("DELETE FROM company_announcements WHERE id=?")->execute([$id]);
        flash('success', 'Announcement deleted.');
        redirect('announcements.php');
    }
}

$announcements = db()->query("
    SELECT ca.*, e.name AS author_name
    FROM company_announcements ca
    JOIN employees e ON e.id = ca.created_by
    ORDER BY ca.created_at DESC
")->fetchAll();

$success = get_flash('success');
$error   = get_flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Announcements – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="portal-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main">
    <div class="page-header">
      <h1 class="page-title"><i class="fa fa-bullhorn"></i> Company Announcements</h1>
    </div>
    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <div class="two-col">
      <!-- Post New -->
      <section class="section-card">
        <div class="section-header"><h2><i class="fa fa-plus"></i> Post Announcement</h2></div>
        <form method="post">
          <input type="hidden" name="action" value="post">
          <div class="form-group" style="margin-bottom:.875rem">
            <label>Title *</label>
            <input type="text" name="title" class="input" required placeholder="e.g. Office closed for New Year">
          </div>
          <div class="form-group" style="margin-bottom:.875rem">
            <label>Message *</label>
            <textarea name="message" class="input" rows="5" required placeholder="Write the announcement…" style="resize:vertical"></textarea>
          </div>
          <button class="btn btn-primary"><i class="fa fa-paper-plane"></i> Post to Everyone</button>
        </form>
      </section>

      <!-- Past Announcements -->
      <section class="section-card">
        <div class="section-header"><h2><i class="fa fa-clock-rotate-left"></i> Past Announcements (<?= count($announcements) ?>)</h2></div>
        <div class="checklist">
          <?php foreach ($announcements as $a): ?>
          <div class="checklist-item" style="justify-content:space-between; border:1px solid var(--clr-border); border-radius:8px; margin-bottom:.5rem; padding:.75rem">
            <div style="flex:1">
              <div style="font-weight:600; <?= !$a['is_active'] ? 'text-decoration:line-through;color:var(--clr-muted)' : '' ?>"><?= h($a['title']) ?></div>
              <div style="font-size:.8rem; color:var(--clr-muted); margin:.25rem 0; white-space:pre-wrap"><?= h($a['message']) ?></div>
              <div style="font-size:.72rem; color:var(--clr-muted)">
                <?= h($a['author_name']) ?> · <?= date('d M Y, h:i A', strtotime($a['created_at'])) ?>
              </div>
            </div>
            <div style="display:flex; gap:.35rem">
              <form method="post" style="display:inline">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                <button class="btn btn-xs <?= $a['is_active'] ? 'btn-warning' : 'btn-success' ?>" title="<?= $a['is_active'] ? 'Deactivate' : 'Reactivate' ?>">
                  <i class="fa <?= $a['is_active'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                </button>
              </form>
              <form method="post" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                <button class="btn btn-xs btn-danger" data-confirm="Delete this announcement?"><i class="fa fa-trash"></i></button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (!$announcements): ?><p class="empty-state">No announcements yet.</p><?php endif; ?>
        </div>
      </section>
    </div>
  </main>
</div>
<script src="/assets/js/portal.js"></script>
</body>
</html>
