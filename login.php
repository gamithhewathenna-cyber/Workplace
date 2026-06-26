<?php
/**
 * login.php — Employee Portal Login
 */
require_once __DIR__ . '/includes/config.php';

// Already logged in
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
        $st = db()->prepare("SELECT * FROM employees WHERE email=? AND status='active' LIMIT 1");
        $st->execute([$email]);
        $emp = $st->fetch();

        if ($emp && password_verify($password, $emp['password'])) {
            $_SESSION['employee_id'] = $emp['id'];
            $_SESSION['role']        = $emp['role'];
            $_SESSION['emp_name']    = $emp['name'];

            // Redirect to originally requested page or dashboard
            $next = $_GET['next'] ?? '/todo/index.php';
            redirect(filter_var($next, FILTER_SANITIZE_URL));
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
<style>
  body { display: grid; place-items: center; min-height: 100vh; background: #272727; }
  .login-box {
    background: #333333;
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 16px;
    padding: 2.5rem 2rem;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 20px 60px rgba(0,0,0,.5);
  }
  .login-logo { text-align: center; margin-bottom: 1.75rem; }
  .login-logo i { font-size: 2.5rem; color: #7d459a; }
  .login-logo h1 { font-size: 1.3rem; font-weight: 700; margin-top: .5rem; color: #f0f0f0; }
  .login-logo p { color: rgba(240,240,240,.45); font-size: .85rem; }
  .form-group { margin-bottom: 1rem; }
  .form-group label { display: block; font-size: .8rem; font-weight: 600; color: rgba(240,240,240,.5); margin-bottom: .35rem; }
  .input-icon { position: relative; }
  .input-icon i { position: absolute; left: .875rem; top: 50%; transform: translateY(-50%); color: rgba(240,240,240,.35); font-size: .85rem; }
  .input-icon .input { padding-left: 2.25rem; background: #272727; border: 1.5px solid rgba(255,255,255,.1); color: #f0f0f0; border-radius: 10px; }
  .input-icon .input:focus { border-color: #7d459a; box-shadow: 0 0 0 3px rgba(125,69,154,.2); outline: none; }
  .btn-block { width: 100%; justify-content: center; padding: .7rem; font-size: .95rem; margin-top: 1.25rem; border-radius: 10px; background: #7d459a; border-color: #7d459a; box-shadow: 0 4px 18px rgba(125,69,154,.45); }
  .btn-block:hover { background: #6a3a84; }
</style>
</head>
<body>
<div class="login-box">
  <div class="login-logo">
    <i class="fa fa-briefcase"></i>
    <h1>Employee Portal</h1>
    <p>Sign in to your workspace</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger" style="margin-bottom:1rem"><i class="fa fa-exclamation-circle"></i> <?= h($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <div class="form-group">
      <label for="email">Email Address</label>
      <div class="input-icon">
        <i class="fa fa-envelope"></i>
        <input type="email" id="email" name="email" class="input" required
               value="<?= h($_POST['email'] ?? '') ?>"
               placeholder="you@company.com" autofocus>
      </div>
    </div>
    <div class="form-group">
      <label for="password">Password</label>
      <div class="input-icon">
        <i class="fa fa-lock"></i>
        <input type="password" id="password" name="password" class="input" required placeholder="••••••••">
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-sign-in-alt"></i> Sign In</button>
  </form>
</div>
</body>
</html>
