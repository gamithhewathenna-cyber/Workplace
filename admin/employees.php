<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

if (!is_manager()) {
    header('Location: /todo/index.php');
    exit;
}

$success = '';
$error   = '';
$temp_pw = '';      // shown once after reset
$new_emp_pw = '';   // shown once after add

// ── Handle Actions ─────────────────────────────────────────
$action = $_POST['action'] ?? '';

// Add Employee
if ($action === 'add') {
    $name  = trim($_POST['name']  ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pos   = trim($_POST['position']   ?? '');
    $dept  = trim($_POST['department'] ?? '');
    $phone = trim($_POST['phone']      ?? '');
    $role  = $_POST['role']   ?? 'employee';
    $jdate = $_POST['join_date'] ?? '';
    $pw    = $_POST['password'] ?? '';

    $valid_roles = ['employee','manager','hr','admin'];
    if (!$name || !$email) {
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (!in_array($role, $valid_roles, true)) {
        $error = 'Invalid role.';
    } else {
        $chk = db()->prepare("SELECT id FROM employees WHERE LOWER(email)=? LIMIT 1");
        $chk->execute([$email]);
        if ($chk->fetchColumn()) {
            $error = 'An employee with that email already exists.';
        } else {
            if (!$pw) {
                $pw = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#'), 0, 10);
            }
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            db()->prepare(
                "INSERT INTO employees (name,email,password,position,phone,department,join_date,role,status)
                 VALUES (?,?,?,?,?,?,?,?,'active')"
            )->execute([$name, $email, $hash, $pos, $phone, $dept, $jdate ?: null, $role]);
            $new_emp_pw = $pw;

            $login_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                       . '://' . $_SERVER['HTTP_HOST'] . '/login.php';
            $body = '<p>Hi <strong>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
                  . '<p>An account has been created for you on the Employee Portal. Use the details below to log in and activate your account.</p>'
                  . '<p style="margin:1rem 0">Login email: <strong>' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</strong></p>'
                  . '<p style="margin:0 0 .5rem">Temporary password:</p>'
                  . '<p style="font-size:1.4rem;font-weight:700;letter-spacing:.15em;color:#c084fc;background:#0d0d0d;padding:.75rem 1.25rem;border-radius:8px;display:inline-block">' . htmlspecialchars($pw, ENT_QUOTES, 'UTF-8') . '</p>'
                  . '<p style="margin-top:1rem;color:#888;font-size:.85rem">Please log in and change your password immediately from your profile settings.</p>';
            $mailSent = send_mail($email, $name, 'Your Employee Portal Account',
                mail_template('Welcome to the Team', $body, 'Log In Now', $login_url));

            $success = "Employee <strong>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</strong> added.";
            if ($mailSent) {
                $success .= ' A welcome email with login details was sent to their address.';
            } else {
                $success .= ' <span style="color:var(--clr-warning)">Could not send the welcome email (' . htmlspecialchars(get_mail_error() ?: 'SMTP not configured', ENT_QUOTES, 'UTF-8') . ') — share the password below manually.</span>';
            }
        }
    }
}

// Edit Employee
if ($action === 'edit') {
    $eid   = (int)($_POST['emp_id'] ?? 0);
    $name  = trim($_POST['name']       ?? '');
    $pos   = trim($_POST['position']   ?? '');
    $dept  = trim($_POST['department'] ?? '');
    $phone = trim($_POST['phone']      ?? '');
    $role  = $_POST['role'] ?? 'employee';
    $jdate = $_POST['join_date'] ?? '';
    $status = $_POST['status'] ?? 'active';

    $valid_roles   = ['employee','manager','hr','admin'];
    $valid_statuses = ['active','inactive'];

    if (!$eid || !$name) {
        $error = 'Name is required.';
    } elseif (!in_array($role, $valid_roles, true) || !in_array($status, $valid_statuses, true)) {
        $error = 'Invalid role or status.';
    } else {
        db()->prepare(
            "UPDATE employees SET name=?,position=?,phone=?,department=?,join_date=?,role=?,status=? WHERE id=?"
        )->execute([$name, $pos, $phone, $dept, $jdate ?: null, $role, $status, $eid]);
        $success = 'Employee updated.';
    }
}

// Toggle Status
if ($action === 'toggle_status') {
    $eid = (int)($_POST['emp_id'] ?? 0);
    if ($eid) {
        $cur = db()->prepare("SELECT status FROM employees WHERE id=? LIMIT 1");
        $cur->execute([$eid]);
        $cur_status = $cur->fetchColumn();
        $new_status = $cur_status === 'active' ? 'inactive' : 'active';
        db()->prepare("UPDATE employees SET status=? WHERE id=?")->execute([$new_status, $eid]);
        $success = 'Employee status updated.';
    }
}

// Reset Password
if ($action === 'reset_pw') {
    $eid = (int)($_POST['emp_id'] ?? 0);
    if ($eid) {
        $temp_pw = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#'), 0, 10);
        $hash = password_hash($temp_pw, PASSWORD_DEFAULT);
        db()->prepare("UPDATE employees SET password=? WHERE id=?")->execute([$hash, $eid]);

        // Email the employee their new temp password
        $pr = db()->prepare("SELECT name, email FROM employees WHERE id=? LIMIT 1");
        $pr->execute([$eid]);
        $pr = $pr->fetch();
        if ($pr && $pr['email']) {
            $login_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                       . '://' . $_SERVER['HTTP_HOST'] . '/login.php';
            $body = '<p>Hi <strong>' . htmlspecialchars($pr['name'], ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
                  . '<p>An administrator has reset your Employee Portal password.</p>'
                  . '<p style="margin:1rem 0">Your temporary password is:</p>'
                  . '<p style="font-size:1.4rem;font-weight:700;letter-spacing:.15em;color:#c084fc;background:#0d0d0d;padding:.75rem 1.25rem;border-radius:8px;display:inline-block">' . htmlspecialchars($temp_pw, ENT_QUOTES, 'UTF-8') . '</p>'
                  . '<p style="margin-top:1rem;color:#888;font-size:.85rem">Please log in and change your password immediately from your profile settings.</p>';
            send_mail($pr['email'], $pr['name'], 'Your Password Has Been Reset',
                mail_template('Password Reset', $body, 'Log In Now', $login_url));
            $success = 'Password reset and emailed to <strong>' . htmlspecialchars($pr['name'], ENT_QUOTES, 'UTF-8') . '</strong>. Temporary password also shown below.';
        } else {
            $success = 'Password reset. Share the temporary password below with the employee.';
        }
    }
}

// Send Email to Employee(s)
if ($action === 'send_email') {
    $recipients = $_POST['recipients'] ?? [];
    $subj = trim($_POST['email_subject'] ?? '');
    $body = trim($_POST['email_body']    ?? '');

    if (!$recipients || !$subj || !$body) {
        $error = 'Recipients, subject and message are all required.';
    } else {
        $sent = 0; $failed = 0;
        foreach ($recipients as $rid) {
            $r = db()->prepare("SELECT name,email FROM employees WHERE id=? AND status='active' LIMIT 1");
            $r->execute([(int)$rid]);
            $rec = $r->fetch();
            if (!$rec) continue;

            // Wrap in a simple HTML template
            $html = '<!DOCTYPE html><html><body style="font-family:Poppins,sans-serif;background:#f5f5f5;padding:2rem">'
                  . '<div style="max-width:600px;margin:auto;background:#fff;border-radius:12px;padding:2rem">'
                  . '<h2 style="color:#7d459a;margin-bottom:1rem">' . htmlspecialchars(get_setting('company_name','Employee Portal'), ENT_QUOTES, 'UTF-8') . '</h2>'
                  . '<p style="white-space:pre-wrap;line-height:1.7">' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</p>'
                  . '<hr style="margin:1.5rem 0;border:none;border-top:1px solid #eee">'
                  . '<p style="font-size:.8rem;color:#999">This message was sent via the Employee Portal.</p>'
                  . '</div></body></html>';

            if (send_mail($rec['email'], $rec['name'], $subj, $html)) {
                $sent++;
            } else {
                $failed++;
            }
        }
        $success = "Sent to <strong>$sent</strong> employee(s)" . ($failed ? ", <strong>$failed</strong> failed." : '.');
        if ($failed && !$sent) { $error = $success; $success = ''; }
    }
}

// Delete Employee
if ($action === 'delete') {
    $eid = (int)($_POST['emp_id'] ?? 0);
    $me  = current_employee_id();
    if ($eid && $eid !== $me) {
        db()->prepare("DELETE FROM employees WHERE id=?")->execute([$eid]);
        $success = 'Employee removed.';
    } else {
        $error = 'You cannot delete your own account.';
    }
}

// ── Filters & Load ─────────────────────────────────────────
$search = trim($_GET['q']      ?? '');
$role_f = trim($_GET['role']   ?? '');
$stat_f = trim($_GET['status'] ?? '');

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(name LIKE ? OR email LIKE ? OR position LIKE ? OR department LIKE ?)";
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($role_f)  { $where[] = 'role=?';   $params[] = $role_f; }
if ($stat_f)  { $where[] = 'status=?'; $params[] = $stat_f; }

$sql = "SELECT * FROM employees WHERE " . implode(' AND ', $where) . " ORDER BY name ASC";
$st  = db()->prepare($sql);
$st->execute($params);
$employees = $st->fetchAll();

// Counts
$counts = db()->query("SELECT
    COUNT(*) total,
    SUM(status='active') active,
    SUM(status='inactive') inactive,
    SUM(role='admin') admins,
    SUM(role='manager') managers
  FROM employees")->fetch();

// Editing?
$edit_emp = null;
if (isset($_GET['edit'])) {
    $es = db()->prepare("SELECT * FROM employees WHERE id=? LIMIT 1");
    $es->execute([(int)$_GET['edit']]);
    $edit_emp = $es->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Employees – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
<style>
.emp-avatar {
  width: 36px; height: 36px;
  border-radius: 50%;
  background: var(--clr-primary);
  color: #fff;
  display: grid;
  place-items: center;
  font-weight: 700;
  font-size: .88rem;
  flex-shrink: 0;
}
.emp-info { display: flex; align-items: center; gap: .75rem; }
.emp-name { font-weight: 600; font-size: .88rem; }
.emp-email { font-size: .75rem; color: var(--clr-muted); }
.role-badge {
  display: inline-block;
  padding: .18rem .6rem;
  border-radius: 50px;
  font-size: .68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
}
.role-admin    { background: rgba(125,69,154,.18); color: #c084fc; }
.role-manager  { background: rgba(41,128,185,.15); color: #60a5fa; }
.role-hr       { background: rgba(39,174,96,.15);  color: #4ade80; }
.role-employee { background: rgba(240,240,240,.08); color: var(--clr-muted); }

.temp-pw-box {
  background: var(--clr-bg);
  border: 1px solid var(--clr-border);
  border-radius: 10px;
  padding: 1rem 1.25rem;
  margin-bottom: 1.25rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}
.temp-pw-box code {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--clr-primary);
  letter-spacing: .1em;
  background: rgba(125,69,154,.12);
  padding: .3rem .75rem;
  border-radius: 6px;
}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="portal-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <main class="portal-main">
    <div class="page-header">
      <div>
        <h1 class="page-title"><i class="fa fa-users"></i> Employees</h1>
        <p class="page-sub">Manage team members and account access</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" onclick="openModal('email-modal')">
          <i class="fa fa-paper-plane"></i> Send Email
        </button>
        <button class="btn btn-primary" onclick="openModal('add-modal')">
          <i class="fa fa-user-plus"></i> Add Employee
        </button>
      </div>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($temp_pw): ?>
    <div class="temp-pw-box">
      <i class="fa fa-key" style="color:var(--clr-warning);font-size:1.3rem"></i>
      <div>
        <div style="font-size:.75rem;color:var(--clr-muted);margin-bottom:.25rem">Temporary Password — share securely then ask employee to change it:</div>
        <code id="temp-pw-val"><?= h($temp_pw) ?></code>
      </div>
      <button class="btn btn-outline btn-xs" onclick="copyTempPw()"><i class="fa fa-copy"></i> Copy</button>
    </div>
    <?php endif; ?>

    <?php if ($new_emp_pw): ?>
    <div class="temp-pw-box">
      <i class="fa fa-user-check" style="color:var(--clr-success);font-size:1.3rem"></i>
      <div>
        <div style="font-size:.75rem;color:var(--clr-muted);margin-bottom:.25rem">Employee created! Initial password — share securely:</div>
        <code id="temp-pw-val"><?= h($new_emp_pw) ?></code>
      </div>
      <button class="btn btn-outline btn-xs" onclick="copyTempPw()"><i class="fa fa-copy"></i> Copy</button>
    </div>
    <?php endif; ?>

    <!-- Stats Row -->
    <div class="cards-row" style="margin-bottom:1.5rem">
      <div class="card">
        <div class="card-icon"><i class="fa fa-users"></i></div>
        <div><div class="card-label">Total</div><div class="card-value"><?= $counts['total'] ?></div></div>
      </div>
      <div class="card">
        <div class="card-icon" style="background:rgba(39,174,96,.12);color:var(--clr-success)"><i class="fa fa-circle-check"></i></div>
        <div><div class="card-label">Active</div><div class="card-value"><?= (int)$counts['active'] ?></div></div>
      </div>
      <div class="card">
        <div class="card-icon" style="background:rgba(231,76,60,.12);color:var(--clr-danger)"><i class="fa fa-circle-xmark"></i></div>
        <div><div class="card-label">Inactive</div><div class="card-value"><?= (int)$counts['inactive'] ?></div></div>
      </div>
      <div class="card">
        <div class="card-icon" style="background:rgba(125,69,154,.12);color:var(--clr-primary)"><i class="fa fa-user-shield"></i></div>
        <div><div class="card-label">Admins / Managers</div><div class="card-value"><?= (int)$counts['admins'] + (int)$counts['managers'] ?></div></div>
      </div>
    </div>

    <!-- Filters -->
    <div class="section-card">
      <form method="get" class="filter-bar">
        <div style="position:relative;flex:1;min-width:200px">
          <i class="fa fa-search" style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:var(--clr-muted);font-size:.8rem"></i>
          <input type="text" name="q" class="input input-sm" value="<?= h($search) ?>"
                 placeholder="Search name, email, position…" style="padding-left:2.2rem;width:100%">
        </div>
        <select name="role" class="input input-sm">
          <option value="">All Roles</option>
          <option value="employee" <?= $role_f==='employee'?'selected':'' ?>>Employee</option>
          <option value="manager"  <?= $role_f==='manager'?'selected':'' ?>>Manager</option>
          <option value="hr"       <?= $role_f==='hr'?'selected':'' ?>>HR</option>
          <option value="admin"    <?= $role_f==='admin'?'selected':'' ?>>Admin</option>
        </select>
        <select name="status" class="input input-sm">
          <option value="">All Status</option>
          <option value="active"   <?= $stat_f==='active'?'selected':'' ?>>Active</option>
          <option value="inactive" <?= $stat_f==='inactive'?'selected':'' ?>>Inactive</option>
        </select>
        <button type="submit" class="btn btn-outline btn-sm"><i class="fa fa-filter"></i> Filter</button>
        <?php if ($search || $role_f || $stat_f): ?>
          <a href="/admin/employees.php" class="btn btn-ghost btn-sm"><i class="fa fa-xmark"></i> Clear</a>
        <?php endif; ?>
      </form>

      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Position / Dept</th>
              <th>Phone</th>
              <th>Role</th>
              <th>Joined</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$employees): ?>
            <tr><td colspan="7" class="empty-state"><i class="fa fa-users" style="font-size:2rem;opacity:.2;display:block;margin-bottom:.5rem"></i>No employees found</td></tr>
          <?php endif; ?>
          <?php foreach ($employees as $emp): ?>
          <tr>
            <td>
              <div class="emp-info">
                <div class="emp-avatar"><?= strtoupper(substr($emp['name'], 0, 1)) ?></div>
                <div>
                  <div class="emp-name"><?= h($emp['name']) ?></div>
                  <div class="emp-email"><?= h($emp['email']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <div style="font-size:.85rem"><?= h($emp['position'] ?? '—') ?></div>
              <?php if (!empty($emp['department'])): ?>
              <div style="font-size:.75rem;color:var(--clr-muted)"><?= h($emp['department']) ?></div>
              <?php endif; ?>
            </td>
            <td style="font-size:.83rem;color:var(--clr-muted)"><?= h($emp['phone'] ?? '—') ?></td>
            <td><span class="role-badge role-<?= h($emp['role']) ?>"><?= h($emp['role']) ?></span></td>
            <td style="font-size:.78rem;color:var(--clr-muted)">
              <?= !empty($emp['join_date']) ? date('d M Y', strtotime($emp['join_date'])) : '—' ?>
            </td>
            <td>
              <span class="badge <?= $emp['status']==='active' ? 'badge-success' : 'badge-secondary' ?>">
                <?= $emp['status'] ?>
              </span>
            </td>
            <td>
              <div class="action-btns">
                <button class="btn btn-outline btn-xs"
                  onclick="openEditModal(<?= htmlspecialchars(json_encode($emp), ENT_QUOTES, 'UTF-8') ?>)">
                  <i class="fa fa-pen"></i>
                </button>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action"  value="reset_pw">
                  <input type="hidden" name="emp_id"  value="<?= $emp['id'] ?>">
                  <button type="submit" class="btn btn-warning btn-xs"
                    onclick="return confirm('Reset password for <?= h($emp['name']) ?>?')"
                    title="Reset Password">
                    <i class="fa fa-key"></i>
                  </button>
                </form>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="emp_id" value="<?= $emp['id'] ?>">
                  <button type="submit"
                    class="btn btn-xs <?= $emp['status']==='active' ? 'btn-outline' : 'btn-success' ?>"
                    title="<?= $emp['status']==='active' ? 'Deactivate' : 'Activate' ?>">
                    <i class="fa <?= $emp['status']==='active' ? 'fa-ban' : 'fa-circle-check' ?>"></i>
                  </button>
                </form>
                <?php if ($emp['id'] !== current_employee_id()): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="emp_id" value="<?= $emp['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-xs"
                    onclick="return confirm('Permanently delete <?= h($emp['name']) ?>? This cannot be undone.')"
                    title="Delete">
                    <i class="fa fa-trash"></i>
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<!-- ── Send Email Modal ───────────────────────────────────── -->
<div class="modal-overlay" id="email-modal" style="display:none" onclick="if(event.target===this)closeModal('email-modal')">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="fa fa-paper-plane" style="color:var(--clr-primary)"></i> Send Email to Employees</h3>
      <button class="modal-close" onclick="closeModal('email-modal')">×</button>
    </div>
    <form method="post" class="modal-form">
      <input type="hidden" name="action" value="send_email">
      <div class="form-group" style="margin-bottom:1rem">
        <label style="font-size:.75rem;font-weight:600;color:var(--clr-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:.4rem">
          Recipients <span style="color:var(--clr-danger)">*</span>
        </label>
        <div style="background:var(--clr-bg);border:1.5px solid var(--clr-border);border-radius:8px;padding:.5rem;max-height:160px;overflow-y:auto">
          <label style="display:flex;align-items:center;gap:.5rem;padding:.3rem .5rem;cursor:pointer;font-size:.82rem;border-bottom:1px solid var(--clr-border);margin-bottom:.35rem;font-weight:600">
            <input type="checkbox" onchange="toggleAll(this)"> Select All Active Employees
          </label>
          <?php
          $active_emps = db()->query("SELECT id,name,email,role FROM employees WHERE status='active' ORDER BY name")->fetchAll();
          foreach ($active_emps as $ae):
          ?>
          <label class="emp-chk" style="display:flex;align-items:center;gap:.5rem;padding:.25rem .5rem;cursor:pointer;font-size:.82rem;border-radius:6px">
            <input type="checkbox" name="recipients[]" value="<?= $ae['id'] ?>">
            <span style="flex:1"><?= h($ae['name']) ?></span>
            <span style="font-size:.72rem;color:var(--clr-muted)"><?= h($ae['email']) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:1rem">
        <label style="font-size:.75rem;font-weight:600;color:var(--clr-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:.4rem">Subject <span style="color:var(--clr-danger)">*</span></label>
        <input type="text" name="email_subject" class="input" required placeholder="e.g. Company Announcement">
      </div>
      <div class="form-group" style="margin-bottom:1rem">
        <label style="font-size:.75rem;font-weight:600;color:var(--clr-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:.4rem">Message <span style="color:var(--clr-danger)">*</span></label>
        <textarea name="email_body" class="input" rows="6" required placeholder="Type your message here…" style="resize:vertical"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('email-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Send Email</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Add Employee Modal ──────────────────────────────────── -->
<div class="modal-overlay" id="add-modal" style="display:none" onclick="if(event.target===this)closeModal('add-modal')">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="fa fa-user-plus" style="color:var(--clr-primary)"></i> Add Employee</h3>
      <button class="modal-close" onclick="closeModal('add-modal')">×</button>
    </div>
    <form method="post" class="modal-form">
      <input type="hidden" name="action" value="add">
      <div class="form-grid">
        <div class="form-group">
          <label>Full Name <span style="color:var(--clr-danger)">*</span></label>
          <input type="text" name="name" class="input" required placeholder="e.g. John Smith">
        </div>
        <div class="form-group">
          <label>Email Address <span style="color:var(--clr-danger)">*</span></label>
          <input type="email" name="email" class="input" required placeholder="john@company.com">
        </div>
        <div class="form-group">
          <label>Position / Job Title</label>
          <input type="text" name="position" class="input" placeholder="e.g. Designer">
        </div>
        <div class="form-group">
          <label>Department</label>
          <input type="text" name="department" class="input" placeholder="e.g. Creative">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" class="input" placeholder="+60 12-345 6789">
        </div>
        <div class="form-group">
          <label>Join Date</label>
          <input type="date" name="join_date" class="input" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label>Role</label>
          <select name="role" class="input">
            <option value="employee">Employee</option>
            <option value="manager">Manager</option>
            <option value="hr">HR</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="form-group">
          <label>Initial Password <small style="color:var(--clr-muted)">(leave blank to auto-generate)</small></label>
          <input type="text" name="password" class="input" placeholder="Auto-generate if empty">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-user-plus"></i> Add Employee</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Edit Employee Modal ─────────────────────────────────── -->
<div class="modal-overlay" id="edit-modal" style="display:none" onclick="if(event.target===this)closeModal('edit-modal')">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="fa fa-pen" style="color:var(--clr-primary)"></i> Edit Employee</h3>
      <button class="modal-close" onclick="closeModal('edit-modal')">×</button>
    </div>
    <form method="post" class="modal-form">
      <input type="hidden" name="action"  value="edit">
      <input type="hidden" name="emp_id"  id="edit-id">
      <div class="form-grid">
        <div class="form-group">
          <label>Full Name <span style="color:var(--clr-danger)">*</span></label>
          <input type="text" name="name" id="edit-name" class="input" required>
        </div>
        <div class="form-group">
          <label>Email <small style="color:var(--clr-muted)">(read-only)</small></label>
          <input type="email" id="edit-email" class="input" disabled style="opacity:.5">
        </div>
        <div class="form-group">
          <label>Position / Job Title</label>
          <input type="text" name="position" id="edit-position" class="input">
        </div>
        <div class="form-group">
          <label>Department</label>
          <input type="text" name="department" id="edit-dept" class="input">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" id="edit-phone" class="input">
        </div>
        <div class="form-group">
          <label>Join Date</label>
          <input type="date" name="join_date" id="edit-jdate" class="input">
        </div>
        <div class="form-group">
          <label>Role</label>
          <select name="role" id="edit-role" class="input">
            <option value="employee">Employee</option>
            <option value="manager">Manager</option>
            <option value="hr">HR</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" id="edit-status" class="input">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('edit-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id) {
  document.getElementById(id).style.display = 'grid';
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id).style.display = 'none';
  document.body.style.overflow = '';
}

function openEditModal(emp) {
  document.getElementById('edit-id').value       = emp.id;
  document.getElementById('edit-name').value     = emp.name;
  document.getElementById('edit-email').value    = emp.email;
  document.getElementById('edit-position').value = emp.position  || '';
  document.getElementById('edit-dept').value     = emp.department|| '';
  document.getElementById('edit-phone').value    = emp.phone     || '';
  document.getElementById('edit-jdate').value    = emp.join_date || '';
  document.getElementById('edit-role').value     = emp.role;
  document.getElementById('edit-status').value   = emp.status;
  openModal('edit-modal');
}

function toggleAll(cb) {
  document.querySelectorAll('.emp-chk input[type=checkbox]').forEach(function(c){ c.checked = cb.checked; });
}

function copyTempPw() {
  var el = document.getElementById('temp-pw-val');
  if (!el) return;
  navigator.clipboard.writeText(el.textContent).then(function(){
    var btn = el.parentElement.nextElementSibling;
    btn.innerHTML = '<i class="fa fa-check"></i> Copied!';
    setTimeout(function(){ btn.innerHTML = '<i class="fa fa-copy"></i> Copy'; }, 2000);
  });
}

<?php if ($error && (strpos($error,'Name') !== false || strpos($error,'email') !== false || strpos($error,'already') !== false)): ?>
openModal('add-modal');
<?php endif; ?>
</script>
</body>
</html>
