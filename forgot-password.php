<?php
require_once __DIR__ . '/includes/config.php';

if (current_employee_id()) redirect('/todo/index.php');

$msg   = '';
$type  = '';
$token = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg  = 'Please enter a valid email address.';
        $type = 'error';
    } else {
        $st = db()->prepare("SELECT id, name FROM employees WHERE LOWER(email)=? AND status='active' LIMIT 1");
        $st->execute([$email]);
        $emp = $st->fetch();

        if ($emp) {
            // Delete any old tokens for this email
            db()->prepare("DELETE FROM password_resets WHERE email=?")->execute([$email]);

            $token  = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            db()->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?,?,?)")
               ->execute([$email, $token, $expiry]);

            $reset_url = 'https://' . $_SERVER['HTTP_HOST'] . '/reset-password.php?token=' . $token;

            $subject   = 'Password Reset – ' . get_setting('company_name', 'Employee Portal');
            $html_body = '<!DOCTYPE html><html><body style="font-family:Poppins,sans-serif;background:#f5f5f5;padding:2rem">'
                . '<div style="max-width:500px;margin:auto;background:#fff;border-radius:12px;padding:2rem">'
                . '<h2 style="color:#7d459a">Password Reset</h2>'
                . '<p>Hi <strong>' . htmlspecialchars($emp['name'], ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
                . '<p>Click the button below to reset your password. This link expires in <strong>1 hour</strong>.</p>'
                . '<p style="margin:1.5rem 0"><a href="' . $reset_url . '" style="background:#7d459a;color:#fff;padding:.75rem 1.5rem;border-radius:8px;text-decoration:none;font-weight:600">Reset My Password</a></p>'
                . '<p style="font-size:.82rem;color:#999">If you did not request this, ignore this email.</p>'
                . '</div></body></html>';

            $sent = send_mail($email, $emp['name'], $subject, $html_body);

            $msg  = $sent
                ? 'A password reset link has been sent to your email. It expires in 1 hour.'
                : 'Email could not be sent automatically. Copy the reset link below and share it securely with the employee.';
            $type = 'ok';
        } else {
            // Don't reveal whether the email exists
            $msg  = 'If that email is registered, a reset link has been sent.';
            $type = 'ok';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Forgot Password – Employee Portal</title>
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

.alert { border-radius: 10px; padding: .75rem 1rem; font-size: .83rem; margin-bottom: 1.25rem; display: flex; align-items: flex-start; gap: .5rem; }
.alert.ok    { background: rgba(39,174,96,.12);  border: 1px solid rgba(39,174,96,.3);  color: #4ade80; }
.alert.error { background: rgba(231,76,60,.12);  border: 1px solid rgba(231,76,60,.3);  color: #ff6b6b; }
.alert i { margin-top: 2px; flex-shrink: 0; }

.fgroup { margin-bottom: 1.1rem; }
.fgroup label { display: block; font-size: .75rem; font-weight: 600; color: rgba(240,240,240,.45); margin-bottom: .4rem; text-transform: uppercase; letter-spacing: .04em; }
.fi { position: relative; }
.fi i.ic { position: absolute; left: .9rem; top: 50%; transform: translateY(-50%); color: rgba(240,240,240,.3); font-size: .85rem; pointer-events: none; }
.fi input {
  width: 100%;
  padding: .65rem .9rem .65rem 2.4rem;
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

.reset-link-box {
  margin-top: 1rem;
  background: #050505;
  border: 1px solid rgba(125,69,154,.3);
  border-radius: 10px;
  padding: .75rem;
}
.reset-link-box label { font-size: .75rem; color: rgba(240,240,240,.4); display: block; margin-bottom: .4rem; }
.reset-link-box code {
  display: block;
  font-size: .72rem;
  color: #c084fc;
  word-break: break-all;
  line-height: 1.5;
}
.copy-btn {
  margin-top: .5rem;
  padding: .35rem .75rem;
  background: rgba(125,69,154,.2);
  border: 1px solid rgba(125,69,154,.35);
  border-radius: 6px;
  color: #c084fc;
  font-size: .75rem;
  font-family: inherit;
  cursor: pointer;
  transition: background .2s;
}
.copy-btn:hover { background: rgba(125,69,154,.35); }

.back { text-align: center; margin-top: 1.25rem; }
.back a { color: rgba(240,240,240,.35); font-size: .8rem; text-decoration: none; transition: color .2s; }
.back a:hover { color: #7d459a; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-icon"><i class="fa fa-key"></i></div>
    <h1>Forgot Password</h1>
    <p>Enter your email to receive a reset link</p>
  </div>

  <?php if ($msg): ?>
    <div class="alert <?= $type ?>">
      <i class="fa <?= $type === 'ok' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
      <span><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <?php if ($token): ?>
    <div class="reset-link-box">
      <label>Reset Link (share securely with the employee):</label>
      <code id="rl">https://<?= htmlspecialchars($_SERVER['HTTP_HOST'], ENT_QUOTES, 'UTF-8') ?>/reset-password.php?token=<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?></code>
      <button class="copy-btn" onclick="copyLink()"><i class="fa fa-copy"></i> Copy Link</button>
    </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (!$token): ?>
  <form method="post">
    <div class="fgroup">
      <label for="email">Email Address</label>
      <div class="fi">
        <i class="ic fa fa-envelope"></i>
        <input type="email" id="email" name="email" required autofocus
               placeholder="you@company.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </div>
    </div>
    <button type="submit" class="btn-sub"><i class="fa fa-paper-plane"></i> Send Reset Link</button>
  </form>
  <?php endif; ?>

  <div class="back">
    <a href="/login.php"><i class="fa fa-arrow-left"></i> Back to Sign In</a>
  </div>
</div>

<script>
function copyLink() {
  var t = document.getElementById('rl').textContent;
  navigator.clipboard.writeText(t).then(function() {
    var btn = document.querySelector('.copy-btn');
    btn.innerHTML = '<i class="fa fa-check"></i> Copied!';
    setTimeout(function(){ btn.innerHTML = '<i class="fa fa-copy"></i> Copy Link'; }, 2000);
  });
}
</script>
</body>
</html>
