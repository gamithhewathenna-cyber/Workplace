<?php
require_once __DIR__ . '/includes/config.php';

if (current_employee_id()) {
    redirect('/todo/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        try {
            $st = db()->prepare("SELECT * FROM employees WHERE LOWER(email)=LOWER(?) AND status='active' LIMIT 1");
            $st->execute([$email]);
            $emp = $st->fetch();

            if ($emp && password_verify($password, $emp['password'])) {
                // Regenerate session ID to prevent fixation
                session_regenerate_id(true);

                $_SESSION['employee_id'] = $emp['id'];
                $_SESSION['role']        = $emp['role'];
                $_SESSION['emp_name']    = $emp['name'];

                $next = filter_var($_GET['next'] ?? '/todo/index.php', FILTER_SANITIZE_URL);
                if (!$next || strpos($next, '/') !== 0) {
                    $next = '/todo/index.php';
                }
                header('Location: ' . $next);
                exit;
            } else {
                $error = 'Incorrect email or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign In – Employee Portal</title>
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
.login-wrap {
  width: 100%;
  max-width: 400px;
  padding: 1rem;
}
.login-box {
  background: #111111;
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 18px;
  padding: 2.5rem 2rem 2rem;
  box-shadow: 0 24px 64px rgba(0,0,0,.55);
}
.login-logo {
  text-align: center;
  margin-bottom: 2rem;
}
.login-logo .logo-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 60px;
  height: 60px;
  background: rgba(125,69,154,.2);
  border-radius: 16px;
  margin-bottom: .875rem;
}
.login-logo .logo-icon i { font-size: 1.6rem; color: #7d459a; }
.login-logo h1 { font-size: 1.25rem; font-weight: 700; color: #f0f0f0; }
.login-logo p  { color: rgba(240,240,240,.4); font-size: .82rem; margin-top: .2rem; }

.alert-err {
  background: rgba(231,76,60,.15);
  border: 1px solid rgba(231,76,60,.3);
  color: #ff6b6b;
  border-radius: 10px;
  padding: .7rem .9rem;
  font-size: .82rem;
  margin-bottom: 1.25rem;
  display: flex;
  align-items: center;
  gap: .5rem;
}

.fgroup { margin-bottom: 1.1rem; }
.fgroup label {
  display: block;
  font-size: .75rem;
  font-weight: 600;
  color: rgba(240,240,240,.45);
  margin-bottom: .4rem;
  letter-spacing: .04em;
  text-transform: uppercase;
}
.fi { position: relative; }
.fi i {
  position: absolute;
  left: .9rem;
  top: 50%;
  transform: translateY(-50%);
  color: rgba(240,240,240,.3);
  font-size: .85rem;
  pointer-events: none;
}
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
.fi input:focus {
  outline: none;
  border-color: #7d459a;
  box-shadow: 0 0 0 3px rgba(125,69,154,.2);
}

.btn-login {
  width: 100%;
  margin-top: 1.5rem;
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
  box-shadow: 0 6px 20px rgba(125,69,154,.45);
  transition: background .2s, box-shadow .2s;
}
.btn-login:hover {
  background: #6a3a84;
  box-shadow: 0 8px 24px rgba(125,69,154,.55);
}

.login-footer {
  text-align: center;
  margin-top: 1.25rem;
}
.login-footer a {
  color: rgba(240,240,240,.4);
  font-size: .8rem;
  text-decoration: none;
  transition: color .2s;
}
.login-footer a:hover { color: #7d459a; }
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <div class="login-logo">
      <div class="logo-icon"><i class="fa fa-briefcase"></i></div>
      <h1>Employee Portal</h1>
      <p>Sign in to your workspace</p>
    </div>

    <?php if ($error): ?>
      <div class="alert-err"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="fgroup">
        <label for="email">Email Address</label>
        <div class="fi">
          <i class="fa fa-envelope"></i>
          <input type="email" id="email" name="email" required autofocus
                 placeholder="you@company.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
      </div>

      <div class="fgroup">
        <label for="password">Password</label>
        <div class="fi">
          <i class="fa fa-lock"></i>
          <input type="password" id="password" name="password" required placeholder="••••••••">
        </div>
      </div>

      <button type="submit" class="btn-login">
        <i class="fa fa-sign-in-alt"></i> Sign In
      </button>
    </form>

    <div class="login-footer">
      <a href="/forgot-password.php"><i class="fa fa-key"></i> Forgot your password?</a>
    </div>
  </div>
</div>
</body>
</html>
