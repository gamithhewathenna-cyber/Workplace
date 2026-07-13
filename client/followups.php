<?php
/**
 * client/followups.php
 * Public, no-login page for clients (and anyone CC'd on a reminder email)
 * to view and check off their own pending follow-up items. Access is
 * controlled solely by the unguessable `t` token in the URL — nothing
 * else about the portal is visible or reachable from here.
 */
require_once __DIR__ . '/../includes/config.php';

$token = trim($_GET['t'] ?? $_POST['t'] ?? '');

$client = null;
if ($token) {
    $st = db()->prepare("SELECT id, name FROM clients WHERE public_token = ? AND is_active = 1");
    $st->execute([$token]);
    $client = $st->fetch();
}

if ($client && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete') {
    $id = (int)($_POST['id'] ?? 0);
    db()->prepare("UPDATE client_followups SET is_completed=1, completed_at=NOW(), completed_by=NULL WHERE id=? AND client_id=? AND is_completed=0")
       ->execute([$id, $client['id']]);
    header('Location: followups.php?t=' . urlencode($token));
    exit;
}

$items = [];
$done = 0; $total = 0;
if ($client) {
    $st = db()->prepare("SELECT id, title, is_completed FROM client_followups WHERE client_id=? ORDER BY is_completed ASC, created_at ASC");
    $st->execute([$client['id']]);
    $items = $st->fetchAll();
    $total = count($items);
    $done  = count(array_filter($items, fn($i) => $i['is_completed']));
}
$pct = $total ? round($done / $total * 100) : 0;

$company = get_setting('company_name', 'Creative Elements');
$logo    = get_setting('company_logo');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($company) ?> – Your Pending Items</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Poppins', 'Segoe UI', system-ui, sans-serif;
  background: #050505;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 1rem;
}
.wrap { width: 100%; max-width: 520px; }
.card {
  background: #111111;
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 18px;
  padding: 2rem;
  box-shadow: 0 24px 64px rgba(0,0,0,.55);
}
.brand { display: flex; align-items: center; gap: .6rem; margin-bottom: 1.75rem; }
.brand img { height: 34px; object-fit: contain; }
.brand .icon {
  width: 40px; height: 40px; border-radius: 10px;
  background: rgba(125,69,154,.2);
  display: flex; align-items: center; justify-content: center;
}
.brand .icon i { color: #c084fc; font-size: 1.1rem; }
.brand span { font-weight: 700; color: #f0f0f0; font-size: 1.02rem; }

h1 { font-size: 1.15rem; color: #f0f0f0; margin-bottom: .3rem; }
.sub { color: rgba(240,240,240,.45); font-size: .82rem; margin-bottom: 1.5rem; }

.progress-wrap { background: rgba(255,255,255,.06); border-radius: 6px; height: 8px; overflow: hidden; margin-bottom: .5rem; }
.progress-bar { height: 100%; background: linear-gradient(90deg,#7d459a,#c084fc); border-radius: 6px; transition: width .3s; }
.progress-label { font-size: .76rem; color: rgba(240,240,240,.4); margin-bottom: 1.5rem; }

.item {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: .85rem .9rem;
  background: rgba(255,255,255,.03);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 10px;
  margin-bottom: .6rem;
}
.item.done { opacity: .55; }
.item .title { flex: 1; font-size: .88rem; color: #e8e8e8; }
.item.done .title { text-decoration: line-through; }
.item i.status { font-size: 1.05rem; }
.item.done i.status { color: #4ade80; }
.item:not(.done) i.status { color: rgba(240,240,240,.25); }
.item form { margin: 0; }
.item button {
  background: #7d459a;
  border: none;
  color: #fff;
  font-size: .74rem;
  font-weight: 600;
  padding: .4rem .8rem;
  border-radius: 7px;
  cursor: pointer;
  font-family: inherit;
  white-space: nowrap;
}
.item button:hover { background: #6a3a84; }

.empty { text-align: center; padding: 2rem 0; color: rgba(240,240,240,.35); font-size: .85rem; }
.empty i { font-size: 1.8rem; display: block; margin-bottom: .6rem; opacity: .5; }

.footer-note { text-align: center; margin-top: 1.5rem; font-size: .74rem; color: rgba(240,240,240,.3); }

.error-box { text-align: center; padding: 1.5rem 0; }
.error-box i { font-size: 2rem; color: #e74c3c; margin-bottom: .75rem; display: block; }
.error-box p { color: rgba(240,240,240,.5); font-size: .88rem; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="brand">
      <?php if ($logo): ?>
        <img src="/uploads/logo/<?= h($logo) ?>" alt="<?= h($company) ?>">
      <?php else: ?>
        <div class="icon"><i class="fa fa-briefcase"></i></div>
      <?php endif; ?>
      <span><?= h($company) ?></span>
    </div>

    <?php if (!$client): ?>
      <div class="error-box">
        <i class="fa fa-link-slash"></i>
        <p>This link is invalid or has expired.<br>Please contact us for an updated link.</p>
      </div>
    <?php else: ?>
      <h1><?= h($client['name']) ?></h1>
      <p class="sub">Here's what's currently pending — tick items off as they're done.</p>

      <?php if ($total): ?>
        <div class="progress-wrap"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
        <div class="progress-label"><?= $done ?> of <?= $total ?> completed</div>
      <?php endif; ?>

      <?php if (!$items): ?>
        <div class="empty"><i class="fa fa-circle-check"></i>No items yet.</div>
      <?php else: foreach ($items as $it): ?>
        <div class="item <?= $it['is_completed'] ? 'done' : '' ?>">
          <i class="fa <?= $it['is_completed'] ? 'fa-circle-check' : 'fa-circle' ?> status"></i>
          <span class="title"><?= h($it['title']) ?></span>
          <?php if (!$it['is_completed']): ?>
            <form method="post">
              <input type="hidden" name="action" value="complete">
              <input type="hidden" name="id" value="<?= $it['id'] ?>">
              <input type="hidden" name="t" value="<?= h($token) ?>">
              <button type="submit"><i class="fa fa-check"></i> Mark Done</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; endif; ?>

      <div class="footer-note">This is a private link shared with you — no login required.</div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
