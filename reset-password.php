<?php
require_once __DIR__ . '/includes/config.php';

if (current_employee_id()) redirect('/todo/index.php');

$token = trim($_GET['token'] ?? '');
$msg   = '';
$type  = '';
$done  = false;

// Validate token
$row = null;
if ($token) {
    $st = db()->prepare(
        "SELECT * FROM password_resets
          WHERE token=? AND used=0 AND expires_at > NOW()
          LIMIT 1"
    );
    $st->execute([$token]);
    $row = $st->fetch();
}

if (!$token || !$row) {
    $msg  = 'This reset link is invalid or has expired. Please request a new one.';
    $type = 'error';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass1 = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (strlen($pass1) < 8) {
        $msg  = 'Password must be at least 8 characters.';
        $type = 'error';
    } elseif ($pass1 !== $pass2) {
        $msg  = 'Passwords do not match.';
        $type = 'error';
    } else {
        $hash = password_hash($pass1, PASSWORD_DEFAULT);

        db()->prepare("UPDATE employees SET password=? WHERE LOWER(email)=LOWER(?)")
           ->execute([$hash, $row['email']]);

        db()->prepare("UPDATE password_resets SET used=1 WHERE token=?")
           ->execute([$token]);

        $done = true;
        $msg  = 'Your password has been reset. You can now sign in.';
        $type = 'ok';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reset Password – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Poppins', 'Segoe UI', system-ui, sans-serif;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  background: #050505;
}
.card {
  background: #111111;
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 18px;
  padding: 2.5rem 2rem;
  width: 100%;
  max-width: 420px;
  margin: 1rem;
  box-shadow: 0 24px 64px rgba(0,0,0,.55);
}
.logo { text-align: center; margin-bottom: 1.75rem; }
.logo-icon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 56px; height: 56px;
  background: rgba(125,69,154,.2); border-radius: 14px; margin-bottom: .75rem;
}
.logo-icon i { font-size: 1.4rem; color: #7d459a; }
.logo h1 { font-size: 1.2rem; font-weight: 700; color: #f0f0f0; }
.logo p  { color: rgba(240,240,240,.4); font-size: .82rem; margin-top: .2rem; }

.alert { border-radius: 10px; padding: .75rem 1rem; font-size: .83rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: .5rem; }
.alert.ok    { background: rgba(39,174,96,.12);  border: 1px solid rgba(39,174,96,.3);  color: #4ade80; }
.alert.error { background: rgba(231,76,60,.12);  border: 1px solid rgba(231,76,60,.3);  color: #ff6b6b; }

.fgroup { margin-bottom: 1.1rem; }
.fgroup label { display: block; font-size: .75rem; font-weight: 600; color: rgba(240,240,240,.45); margin-bottom: .4rem; text-transform: uppercase; letter-spacing: .04em; }
.hint { font-size: .72rem; color: rgba(240,240,240,.3); margin-top: .3rem; }
.fi { position: relative; }
.fi i.ic { position: absolute; left: .9rem; top: 50%; transform: translateY(-50%); color: rgba(240,240,240,.3); font-size: .85rem; pointer-events: none; }
.fi .eye {
  position: absolute; right: .9rem; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer; color: rgba(240,240,240,.3); font-size: .85rem; padding: 0;
}
.fi .eye:hover { color: rgba(240,240,240,.6); }
.fi input {
  width: 100%;
  padding: .65rem 2.4rem .65rem 2.4rem;
  background: #050505;
  border: 1.5px solid rgba(255,255,255,.1);
  border-radius: 10px;
  color: #f0f0f0;
  font-size: .88rem;
  font-family: inherit;
  transition: border-color .2s, box-shadow .2s;
}
.fi input::placeholder { color: rgba(240,240,240,.25); }
.fi input:focus { outline: none; border-color: #7d459a; box-shadow: 0 0 0 3px rgba(125,69,154,.2); }

.strength { height: 4px; background: rgba(255,255,255,.08); border-radius: 2px; margin-top: .4rem; overflow: hidden; }
.strength-bar { height: 100%; border-radius: 2px; width: 0; transition: width .3s, background .3s; }

.btn-sub {
  width: 100%;
  margin-top: 1.25rem;
  padding: .75rem;
  background: #7d459a;
  border: none;
  border-radius: 10px;
  color: #fff;
  font-size: .9rem;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .5rem;
  box-shadow: 0 6px 20px rgba(125,69,154,.4);
  transition: background .2s;
}
.btn-sub:hover { background: #6a3a84; }

.back { text-align: center; margin-top: 1.25rem; }
.back a { color: rgba(240,240,240,.35); font-size: .8rem; text-decoration: none; transition: color .2s; }
.back a:hover { color: #7d459a; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-icon"><i class="fa fa-shield-halved"></i></div>
    <h1>Reset Password</h1>
    <p>Create a new password for your account</p>
  </div>

  <?php if ($msg): ?>
    <div class="alert <?= $type ?>">
      <i class="fa <?= $type === 'ok' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
      <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <?php if ($row && !$done): ?>
  <form method="post">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

    <div class="fgroup">
      <label for="password">New Password</label>
      <div class="fi">
        <i class="ic fa fa-lock"></i>
        <input type="password" id="password" name="password" required
               placeholder="At least 8 characters" oninput="checkStrength(this.value)">
        <button type="button" class="eye" onclick="togglePw('password',this)"><i class="fa fa-eye"></i></button>
      </div>
      <div class="strength"><div class="strength-bar" id="sb"></div></div>
      <div class="hint">Min. 8 characters with letters and numbers.</div>
    </div>

    <div class="fgroup">
      <label for="password2">Confirm Password</label>
      <div class="fi">
        <i class="ic fa fa-lock"></i>
        <input type="password" id="password2" name="password2" required placeholder="Repeat password">
        <button type="button" class="eye" onclick="togglePw('password2',this)"><i class="fa fa-eye"></i></button>
      </div>
    </div>

    <button type="submit" class="btn-sub"><i class="fa fa-check"></i> Set New Password</button>
  </form>
  <?php endif; ?>

  <div class="back">
    <?php if ($done): ?>
      <a href="/login.php"><i class="fa fa-sign-in-alt"></i> Go to Sign In</a>
    <?php else: ?>
      <a href="/forgot-password.php"><i class="fa fa-arrow-left"></i> Request a new link</a>
    <?php endif; ?>
  </div>
</div>

<script>
function togglePw(id, btn) {
  var inp = document.getElementById(id);
  var ic  = btn.querySelector('i');
  if (inp.type === 'password') {
    inp.type = 'text';
    ic.className = 'fa fa-eye-slash';
  } else {
    inp.type = 'password';
    ic.className = 'fa fa-eye';
  }
}
function checkStrength(v) {
  var bar = document.getElementById('sb');
  var s = 0;
  if (v.length >= 8)  s++;
  if (/[A-Z]/.test(v))  s++;
  if (/[0-9]/.test(v))  s++;
  if (/[^A-Za-z0-9]/.test(v)) s++;
  var colours = ['#e74c3c','#e67e22','#f39c12','#27ae60'];
  var widths  = ['25%','50%','75%','100%'];
  bar.style.width      = s > 0 ? widths[s-1] : '0';
  bar.style.background = s > 0 ? colours[s-1] : 'transparent';
}
</script>
</body>
</html>
