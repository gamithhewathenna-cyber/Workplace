<?php
require_once __DIR__ . '/includes/config.php';

if (current_employee_id()) redirect('/todo/index.php');

$error     = '';
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $st = db()->prepare("SELECT id, name FROM employees WHERE LOWER(email)=? AND status='active' LIMIT 1");
        $st->execute([$email]);
        $emp = $st->fetch();

        if ($emp) {
            db()->prepare("DELETE FROM password_resets WHERE email=?")->execute([$email]);

            $token  = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            db()->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?,?,?)")
               ->execute([$email, $token, $expiry]);

            $reset_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                       . '://' . $_SERVER['HTTP_HOST'] . '/reset-password.php?token=' . $token;

            $subject = 'Password Reset – ' . get_setting('company_name', 'Employee Portal');
            $body    = '<p>Hi <strong>' . htmlspecialchars($emp['name'], ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
                     . '<p>We received a request to reset your password. Click the button below to set a new one. This link expires in <strong>1 hour</strong>.</p>'
                     . '<p style="font-size:.82rem;color:#888;margin-top:1.25rem">If you did not request this, you can safely ignore this email — your password will not change.</p>';

            send_mail($email, $emp['name'], $subject,
                mail_template('Password Reset Request', $body, 'Reset My Password', $reset_url));
        }

        // Always mark as submitted — never reveal whether the email exists
        $submitted = true;
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
  min-height: 100vh;
  background: #050505;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
}

.card {
  background: #111111;
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 20px;
  padding: 3rem 2.25rem 2.5rem;
  width: 100%;
  max-width: 440px;
  box-shadow: 0 32px 80px rgba(0,0,0,.6);
}

/* ── Header ── */
.card-head { text-align: center; margin-bottom: 2rem; }
.icon-wrap {
  display: inline-flex; align-items: center; justify-content: center;
  width: 68px; height: 68px;
  background: rgba(125,69,154,.18);
  border-radius: 18px;
  margin-bottom: 1rem;
}
.icon-wrap i { font-size: 1.65rem; color: #7d459a; }
.card-head h1 { font-size: 1.3rem; font-weight: 700; color: #f0f0f0; letter-spacing: -.01em; }
.card-head p  { color: rgba(240,240,240,.42); font-size: .84rem; margin-top: .35rem; line-height: 1.5; }

/* ── Error alert ── */
.alert-err {
  background: rgba(231,76,60,.13);
  border: 1px solid rgba(231,76,60,.3);
  color: #ff6b6b;
  border-radius: 10px;
  padding: .75rem 1rem;
  font-size: .83rem;
  margin-bottom: 1.5rem;
  display: flex; align-items: center; gap: .5rem;
}

/* ── Email field ── */
.email-label {
  display: block;
  font-size: .7rem;
  font-weight: 700;
  color: rgba(240,240,240,.38);
  text-transform: uppercase;
  letter-spacing: .08em;
  margin-bottom: .5rem;
}
.email-wrap { position: relative; }
.email-wrap i {
  position: absolute; left: 1.1rem; top: 50%;
  transform: translateY(-50%);
  color: rgba(240,240,240,.28); font-size: .95rem;
  pointer-events: none;
}
.email-wrap input {
  width: 100%;
  padding: .9rem 1rem .9rem 2.85rem;
  background: #050505;
  border: 1.5px solid rgba(255,255,255,.1);
  border-radius: 12px;
  color: #f0f0f0;
  font-size: 1rem;
  font-family: inherit;
  transition: border-color .2s, box-shadow .2s;
}
.email-wrap input::placeholder { color: rgba(240,240,240,.22); }
.email-wrap input:focus {
  outline: none;
  border-color: #7d459a;
  box-shadow: 0 0 0 3px rgba(125,69,154,.22);
}

.btn-send {
  width: 100%;
  margin-top: 1.25rem;
  padding: .85rem;
  background: #7d459a;
  border: none;
  border-radius: 12px;
  color: #fff;
  font-size: .95rem;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: .55rem;
  box-shadow: 0 8px 24px rgba(125,69,154,.4);
  transition: background .2s, box-shadow .2s;
}
.btn-send:hover {
  background: #6a3a84;
  box-shadow: 0 10px 28px rgba(125,69,154,.5);
}

/* ── Success state ── */
.success-state { text-align: center; }
.success-icon-wrap {
  display: inline-flex; align-items: center; justify-content: center;
  width: 72px; height: 72px;
  background: rgba(74,222,128,.12);
  border-radius: 50%;
  margin-bottom: 1.25rem;
}
.success-icon-wrap i { font-size: 1.8rem; color: #4ade80; }
.success-state h2 { font-size: 1.2rem; font-weight: 700; color: #f0f0f0; margin-bottom: .6rem; }
.success-state p  { color: rgba(240,240,240,.45); font-size: .86rem; line-height: 1.65; }

/* ── Back link ── */
.back-link { text-align: center; margin-top: 1.75rem; }
.back-link a {
  color: rgba(240,240,240,.32);
  font-size: .8rem;
  text-decoration: none;
  display: inline-flex; align-items: center; gap: .4rem;
  transition: color .2s;
}
.back-link a:hover { color: #7d459a; }
</style>
</head>
<body>
<div class="card">

  <?php if ($submitted): ?>
    <!-- Success state -->
    <div class="success-state">
      <div class="success-icon-wrap"><i class="fa fa-envelope-circle-check"></i></div>
      <h2>Check your inbox</h2>
      <p>
        If that email is registered, a password reset link has been sent.<br>
        The link expires in <strong style="color:#f0f0f0">1 hour</strong>.
        Check your spam folder if you don't see it.
      </p>
    </div>

  <?php else: ?>
    <!-- Form state -->
    <div class="card-head">
      <div class="icon-wrap"><i class="fa fa-lock"></i></div>
      <h1>Forgot your password?</h1>
      <p>Enter your work email and we'll send you a link to reset it.</p>
    </div>

    <?php if ($error): ?>
      <div class="alert-err"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <label class="email-label" for="email">Work Email Address</label>
      <div class="email-wrap">
        <i class="fa fa-envelope"></i>
        <input type="email" id="email" name="email" required autofocus
               placeholder="you@company.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <button type="submit" class="btn-send">
        <i class="fa fa-paper-plane"></i> Send Reset Link
      </button>
    </form>

  <?php endif; ?>

  <div class="back-link">
    <a href="/login.php"><i class="fa fa-arrow-left"></i> Back to Sign In</a>
  </div>
</div>
</body>
</html>
