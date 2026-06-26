<?php
// includes/navbar.php
$eid = current_employee_id();
$notifs = [];
if ($eid) {
    $n = db()->prepare("SELECT * FROM notifications WHERE user_id=? AND is_read=0 ORDER BY created_at DESC LIMIT 10");
    $n->execute([$eid]);
    $notifs = $n->fetchAll();
}
$emp_name = '';
if ($eid) {
    $e = db()->prepare("SELECT name FROM employees WHERE id=?");
    $e->execute([$eid]);
    $emp_name = $e->fetchColumn() ?? 'User';
}
?>
<nav class="portal-navbar">
  <button id="menu-toggle" class="btn btn-ghost btn-sm" aria-label="Menu">
    <i class="fa fa-bars"></i>
  </button>
  <span class="nav-brand"><i class="fa fa-briefcase"></i> Employee Portal</span>

  <!-- Notifications -->
  <div style="position:relative">
    <button class="nav-notif-btn" aria-label="Notifications">
      <i class="fa fa-bell"></i>
      <?php if ($notifs): ?><span class="nav-notif-dot"></span><?php endif; ?>
    </button>
    <div id="notif-panel" class="notif-panel" style="display:none; position:absolute; right:0; top:44px; width:320px; background:var(--clr-surface); border:1px solid var(--clr-border); border-radius:var(--radius); box-shadow:var(--shadow-lg); z-index:500;">
      <div style="padding:.75rem 1rem; border-bottom:1px solid var(--clr-border); font-weight:600; font-size:.875rem;">Notifications</div>
      <div style="max-height:360px; overflow-y:auto">
        <?php foreach ($notifs as $nt): ?>
        <a href="<?= h($nt['link'] ?? '#') ?>" style="display:block; padding:.75rem 1rem; border-bottom:1px solid var(--clr-border); text-decoration:none; color:var(--clr-text); font-size:.85rem;">
          <div style="font-weight:500"><?= h($nt['title']) ?></div>
          <div style="color:var(--clr-muted); font-size:.78rem"><?= h($nt['message']) ?></div>
          <div style="color:var(--clr-muted); font-size:.72rem; margin-top:.2rem"><?= date('d M H:i', strtotime($nt['created_at'])) ?></div>
        </a>
        <?php endforeach; ?>
        <?php if (!$notifs): ?>
          <div style="padding:1.5rem; text-align:center; color:var(--clr-muted); font-size:.85rem;">No new notifications</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Avatar -->
  <div class="nav-avatar" title="<?= h($emp_name) ?>"><?= strtoupper(substr($emp_name, 0, 1)) ?></div>
</nav>

<style>
#notif-panel.open { display: block !important; }
</style>
