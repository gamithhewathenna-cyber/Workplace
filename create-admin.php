<?php
/**
 * create-admin.php — ONE-TIME USE. Delete this file after running!
 */
require_once __DIR__ . '/includes/config.php';

$email    = 'gamithhewathenna@gmail.com';
$name     = 'Gamith Hewathenna';
$password = 'Admin@2025';
$role     = 'admin';
$position = 'Administrator';

$hash = password_hash($password, PASSWORD_DEFAULT);

// Insert or update if already exists
$check = db()->prepare("SELECT id FROM employees WHERE LOWER(email)=LOWER(?) LIMIT 1");
$check->execute([$email]);
$existing = $check->fetchColumn();

if ($existing) {
    db()->prepare("UPDATE employees SET name=?, password=?, role=?, position=?, status='active' WHERE id=?")
       ->execute([$name, $hash, $role, $position, $existing]);
    $action = 'Updated existing account';
} else {
    db()->prepare("INSERT INTO employees (name, email, password, role, position, status) VALUES (?,?,?,?,?,'active')")
       ->execute([$name, $email, $hash, $role, $position]);
    $action = 'Created new account';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Created</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', sans-serif; background: #272727; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
  .box { background: #333; border: 1px solid rgba(255,255,255,.08); border-radius: 16px; padding: 2rem; max-width: 440px; width: 100%; margin: 1rem; box-shadow: 0 20px 60px rgba(0,0,0,.5); }
  .icon { text-align: center; margin-bottom: 1.25rem; font-size: 2.5rem; }
  h2 { color: #f0f0f0; font-size: 1.2rem; margin-bottom: .4rem; text-align: center; }
  .sub { color: rgba(240,240,240,.4); font-size: .82rem; text-align: center; margin-bottom: 1.5rem; }
  .row { display: flex; justify-content: space-between; align-items: center; padding: .6rem .875rem; border-radius: 8px; margin-bottom: .5rem; background: #272727; }
  .row .lbl { font-size: .73rem; color: rgba(240,240,240,.4); text-transform: uppercase; letter-spacing: .05em; }
  .row .val { font-size: .88rem; color: #f0f0f0; font-weight: 600; }
  .warn { background: rgba(231,76,60,.12); border: 1px solid rgba(231,76,60,.3); border-radius: 10px; padding: .75rem 1rem; color: #ff6b6b; font-size: .82rem; margin-top: 1.25rem; text-align: center; }
  .btn { display: block; margin: 1.25rem auto 0; padding: .65rem 1.5rem; background: #7d459a; border: none; border-radius: 50px; color: #fff; font-size: .88rem; font-weight: 600; cursor: pointer; text-decoration: none; text-align: center; box-shadow: 0 4px 16px rgba(125,69,154,.4); }
  .btn:hover { background: #6a3a84; }
  .action { color: #4ade80; font-size: .78rem; text-align: center; margin-bottom: .75rem; }
</style>
</head>
<body>
<div class="box">
  <div class="icon">✅</div>
  <h2>Admin Account Ready</h2>
  <p class="sub"><?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?> successfully.</p>

  <div class="row"><span class="lbl">Name</span><span class="val"><?= htmlspecialchars($name) ?></span></div>
  <div class="row"><span class="lbl">Email</span><span class="val"><?= htmlspecialchars($email) ?></span></div>
  <div class="row"><span class="lbl">Password</span><span class="val"><?= htmlspecialchars($password) ?></span></div>
  <div class="row"><span class="lbl">Role</span><span class="val">Admin</span></div>

  <div class="warn">⚠️ <strong>Delete this file immediately after logging in!</strong><br>It exposes your password in plain text.</div>

  <a href="/login.php" class="btn">Go to Login →</a>
</div>
</body>
</html>
