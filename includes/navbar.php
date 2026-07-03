<?php
// includes/navbar.php
$eid = current_employee_id();
$notifs = [];
if ($eid) {
    $n = db()->prepare("SELECT * FROM notifications WHERE user_id=? AND is_read=0 ORDER BY created_at DESC LIMIT 10");
    $n->execute([$eid]);
    $notifs = $n->fetchAll();
}
$emp_name = $_SESSION['emp_name'] ?? '';
if (!$emp_name && $eid) {
    $e = db()->prepare("SELECT name FROM employees WHERE id=?");
    $e->execute([$eid]);
    $emp_name = $e->fetchColumn() ?: 'User';
    $_SESSION['emp_name'] = $emp_name;
}
?>
<nav class="portal-navbar">
  <!-- Mobile menu toggle -->
  <button id="menu-toggle" class="btn btn-ghost btn-sm" aria-label="Menu" style="display:none">
    <i class="fa fa-bars"></i>
  </button>

  <!-- Search -->
  <div class="nav-search">
    <i class="fa fa-search"></i>
    <input type="text" placeholder="Search…" id="nav-search-input">
  </div>

  <div class="nav-spacer"></div>

  <!-- Notifications -->
  <div style="position:relative">
    <button class="nav-notif-btn" aria-label="Notifications">
      <i class="fa fa-bell"></i>
      <?php if ($notifs): ?><span class="nav-notif-dot"></span><?php endif; ?>
    </button>
    <div id="notif-panel" style="display:none" class="notif-panel">
      <div style="padding:.75rem 1.25rem; border-bottom:1px solid var(--clr-border); font-weight:600; font-size:.85rem; color:var(--clr-text)">
        Notifications <span class="badge badge-warning" style="margin-left:.35rem"><?= count($notifs) ?: '' ?></span>
      </div>
      <div style="max-height:340px; overflow-y:auto">
        <?php foreach ($notifs as $nt): ?>
        <a href="<?= htmlspecialchars($nt['link'] ?? '#', ENT_QUOTES, 'UTF-8') ?>"
           style="display:block; padding:.8rem 1.25rem; border-bottom:1px solid var(--clr-border); text-decoration:none; color:var(--clr-text); font-size:.82rem; transition:.15s"
           onmouseover="this.style.background='var(--clr-bg)'" onmouseout="this.style.background=''">
          <div style="font-weight:600; margin-bottom:.15rem"><?= htmlspecialchars($nt['title'], ENT_QUOTES, 'UTF-8') ?></div>
          <div style="color:var(--clr-muted); font-size:.76rem"><?= htmlspecialchars($nt['message'], ENT_QUOTES, 'UTF-8') ?></div>
          <div style="color:var(--clr-muted); font-size:.7rem; margin-top:.2rem"><?= date('d M · H:i', strtotime($nt['created_at'])) ?></div>
        </a>
        <?php endforeach; ?>
        <?php if (!$notifs): ?>
          <div style="padding:2rem; text-align:center; color:var(--clr-muted); font-size:.83rem">
            <i class="fa fa-bell-slash" style="font-size:1.5rem; margin-bottom:.5rem; display:block; opacity:.4"></i>
            No new notifications
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Avatar -->
  <div class="nav-avatar" title="<?= htmlspecialchars($emp_name, ENT_QUOTES, 'UTF-8') ?>">
    <?= strtoupper(substr($emp_name, 0, 1)) ?>
  </div>
</nav>

<style>
#notif-panel.open { display: block !important; }
@media (max-width: 768px) {
  #menu-toggle { display: grid !important; }
}
</style>

<!-- ── Team Chat Widget ────────────────────────────────────── -->
<button id="chat-bubble" class="chat-bubble" aria-label="Team Chat">
  <i class="fa fa-comments"></i>
  <span id="chat-badge" class="chat-badge" style="display:none">0</span>
</button>

<div id="chat-panel" class="chat-panel" style="display:none">
  <div class="chat-panel-header">
    <span id="chat-panel-title">Team Chat</span>
    <button id="chat-close" class="chat-close-btn" aria-label="Close"><i class="fa fa-xmark"></i></button>
  </div>

  <div id="chat-contacts-list" class="chat-contacts-list"></div>
</div>

<!-- Multiple floating chat windows get inserted here (up to a max at once) -->
<div id="chat-windows-container" class="chat-windows-container"></div>

<script>window.CHAT_MY_ID = <?= (int)$eid ?>;</script>
<script src="/assets/js/chat.js?v=<?= @filemtime(__DIR__ . '/../assets/js/chat.js') ?: time() ?>"></script>
