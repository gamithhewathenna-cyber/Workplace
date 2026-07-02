<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

if ($_SESSION['role'] !== 'admin') {
    header('Location: /todo/index.php');
    exit;
}

$success = '';
$error   = '';
$section = '';

// ── Handle form submissions ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['_section'] ?? '';

    // Company Info
    if ($section === 'company') {
        set_setting('company_name',    trim($_POST['company_name']    ?? ''));
        set_setting('company_email',   trim($_POST['company_email']   ?? ''));
        set_setting('company_phone',   trim($_POST['company_phone']   ?? ''));
        set_setting('company_address', trim($_POST['company_address'] ?? ''));
        $success = 'Company information updated.';
    }

    // SMTP Settings
    if ($section === 'smtp') {
        set_setting('smtp_host',       trim($_POST['smtp_host']       ?? ''));
        set_setting('smtp_port',       trim($_POST['smtp_port']       ?? '587'));
        set_setting('smtp_username',   trim($_POST['smtp_username']   ?? ''));
        set_setting('smtp_encryption', trim($_POST['smtp_encryption'] ?? 'tls'));
        set_setting('smtp_from_email', trim($_POST['smtp_from_email'] ?? ''));
        set_setting('smtp_from_name',  trim($_POST['smtp_from_name']  ?? ''));
        // Only overwrite password if a new one was entered
        if (!empty($_POST['smtp_password'])) {
            set_setting('smtp_password', $_POST['smtp_password']);
        }
        $success = 'SMTP settings saved.';
    }

    // Send Test Email
    if ($section === 'smtp_test') {
        $to = trim($_POST['test_email'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address for the test.';
        } else {
            $ok = send_mail($to, $to, 'SMTP Test – Employee Portal',
                '<h2 style="font-family:Poppins,sans-serif">SMTP is working!</h2>'
                . '<p style="font-family:Poppins,sans-serif">This is a test email from your Employee Portal.</p>'
            );
            $success = $ok ? "Test email sent to <strong>$to</strong> successfully." : 'Failed: ' . get_mail_error();
            if (!$ok) $error = $success; $success = '';
        }
    }

    // Logo Upload
    if ($section === 'logo') {
        $dir = __DIR__ . '/../uploads/logo/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!empty($_FILES['logo']['tmp_name'])) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo   = finfo_open(FILEINFO_MIME_TYPE);
            $mime    = finfo_file($finfo, $_FILES['logo']['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed, true)) {
                $error = 'Only JPG, PNG, GIF or WebP images are allowed.';
            } elseif ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                $error = 'Logo must be under 2 MB.';
            } else {
                $ext      = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $filename = 'logo_' . time() . '.' . strtolower($ext);

                // Delete old logo
                $old = get_setting('company_logo');
                if ($old && file_exists(__DIR__ . '/../uploads/logo/' . $old)) {
                    @unlink(__DIR__ . '/../uploads/logo/' . $old);
                }

                if (move_uploaded_file($_FILES['logo']['tmp_name'], $dir . $filename)) {
                    set_setting('company_logo', $filename);
                    $success = 'Logo uploaded successfully.';
                } else {
                    $error = 'Upload failed. Check folder permissions.';
                }
            }
        } else {
            $error = 'No file selected.';
        }
    }

    // Admin Profile (name)
    if ($section === 'profile') {
        $new_name = trim($_POST['admin_name'] ?? '');
        if (strlen($new_name) < 2) {
            $error = 'Name must be at least 2 characters.';
        } else {
            $eid = current_employee_id();
            db()->prepare("UPDATE employees SET name=? WHERE id=?")->execute([$new_name, $eid]);
            $_SESSION['emp_name'] = $new_name;
            $success = 'Your name has been updated.';
        }
    }

    // Date & Time Override
    if ($section === 'datetime') {
        if (isset($_POST['reset_dt'])) {
            set_setting('datetime_offset_seconds', '0');
            $success = 'System time reset to real server time.';
        } else {
            $target = trim($_POST['custom_datetime'] ?? '');
            if ($target) {
                $ts = strtotime($target);
                if ($ts) {
                    $offset = $ts - time();
                    set_setting('datetime_offset_seconds', (string)$offset);
                    $success = 'System date & time updated.';
                } else {
                    $error = 'Invalid date/time format.';
                }
            } else {
                $error = 'Please enter a date and time.';
            }
        }
    }

    // Password Change
    if ($section === 'password') {
        $cur  = $_POST['current_password'] ?? '';
        $new1 = $_POST['new_password']     ?? '';
        $new2 = $_POST['confirm_password'] ?? '';

        $eid = current_employee_id();
        $st  = db()->prepare("SELECT password FROM employees WHERE id=? LIMIT 1");
        $st->execute([$eid]);
        $hash = $st->fetchColumn();

        if (!password_verify($cur, $hash)) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new1) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new1 !== $new2) {
            $error = 'New passwords do not match.';
        } else {
            $newHash = password_hash($new1, PASSWORD_DEFAULT);
            db()->prepare("UPDATE employees SET password=? WHERE id=?")->execute([$newHash, $eid]);
            $success = 'Password changed successfully.';
        }
    }
}

// ── Load current values ────────────────────────────────────
$cfg = [
    'company_name'    => get_setting('company_name',    'My Company'),
    'company_email'   => get_setting('company_email'),
    'company_phone'   => get_setting('company_phone'),
    'company_address' => get_setting('company_address'),
    'mail_from'       => get_setting('mail_from'),
    'company_logo'    => get_setting('company_logo'),
    'smtp_host'       => get_setting('smtp_host'),
    'smtp_port'       => get_setting('smtp_port', '587'),
    'smtp_username'   => get_setting('smtp_username'),
    'smtp_encryption' => get_setting('smtp_encryption', 'tls'),
    'smtp_from_email' => get_setting('smtp_from_email'),
    'smtp_from_name'  => get_setting('smtp_from_name'),
];

$admin = db()->prepare("SELECT name, email FROM employees WHERE id=? LIMIT 1");
$admin->execute([current_employee_id()]);
$me = $admin->fetch();

$logo_url = $cfg['company_logo']
    ? '/uploads/logo/' . h($cfg['company_logo'])
    : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Settings – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
<style>
.settings-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}
@media(max-width:860px){ .settings-grid{ grid-template-columns:1fr; } }

.s-card {
  background: var(--clr-surface);
  border: 1px solid var(--clr-border);
  border-radius: var(--radius);
  overflow: hidden;
  box-shadow: var(--shadow);
}
.s-card-head {
  display: flex;
  align-items: center;
  gap: .65rem;
  padding: 1rem 1.35rem;
  border-bottom: 1px solid var(--clr-border);
  background: rgba(125,69,154,.06);
}
.s-card-head i { color: var(--clr-primary); font-size: 1rem; }
.s-card-head h3 { font-size: .9rem; font-weight: 700; color: var(--clr-text); }
.s-card-body { padding: 1.35rem; }

.fg { display: flex; flex-direction: column; gap: .3rem; margin-bottom: .9rem; }
.fg:last-child { margin-bottom: 0; }
.fg label { font-size: .73rem; font-weight: 600; color: var(--clr-muted); text-transform: uppercase; letter-spacing: .05em; }
.fg input, .fg select, .fg textarea {
  padding: .55rem .8rem;
  background: var(--clr-bg);
  border: 1.5px solid var(--clr-border);
  border-radius: 8px;
  color: var(--clr-text);
  font-size: .85rem;
  font-family: inherit;
  width: 100%;
  transition: border-color .2s, box-shadow .2s;
}
.fg input:focus, .fg select:focus, .fg textarea:focus {
  outline: none;
  border-color: var(--clr-primary);
  box-shadow: 0 0 0 3px rgba(125,69,154,.15);
}
.fg textarea { resize: vertical; min-height: 72px; }
.fg select { cursor: pointer; }

.save-row { display: flex; justify-content: flex-end; padding-top: .85rem; margin-top: .85rem; border-top: 1px solid var(--clr-border); }

.logo-preview {
  width: 100px; height: 100px;
  border: 2px dashed var(--clr-border);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: .9rem;
  overflow: hidden;
  background: var(--clr-bg);
  cursor: pointer;
  transition: border-color .2s;
}
.logo-preview:hover { border-color: var(--clr-primary); }
.logo-preview img { width: 100%; height: 100%; object-fit: contain; }
.logo-preview .no-logo { color: var(--clr-muted); text-align: center; font-size: .75rem; }
.logo-preview .no-logo i { font-size: 2rem; display: block; margin-bottom: .3rem; opacity: .4; }
#logo-input { display: none; }

.pw-wrap { position: relative; }
.pw-wrap input { padding-right: 2.5rem; }
.pw-wrap .eye-btn { position: absolute; right: .7rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--clr-muted); }
.pw-wrap .eye-btn:hover { color: var(--clr-primary); }

.strength-bar-wrap { height: 4px; background: var(--clr-border); border-radius: 2px; margin-top: .4rem; overflow: hidden; }
.strength-bar { height: 100%; border-radius: 2px; width: 0; transition: width .3s, background .3s; }

.alert-banner {
  padding: .8rem 1rem;
  border-radius: 10px;
  font-size: .83rem;
  display: flex;
  align-items: center;
  gap: .5rem;
  margin-bottom: 1.5rem;
}
.alert-banner.ok    { background: rgba(39,174,96,.12);  border: 1px solid rgba(39,174,96,.25);  color: var(--clr-success); }
.alert-banner.error { background: rgba(231,76,60,.12);  border: 1px solid rgba(231,76,60,.25);  color: var(--clr-danger); }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="portal-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <main class="portal-main">
    <div class="page-header">
      <div>
        <h1 class="page-title"><i class="fa fa-cog"></i> Settings</h1>
        <p class="page-sub">Manage company configuration and your account</p>
      </div>
    </div>

    <?php if ($success): ?>
    <div class="alert-banner ok"><i class="fa fa-check-circle"></i> <?= h($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert-banner error"><i class="fa fa-exclamation-circle"></i> <?= h($error) ?></div>
    <?php endif; ?>

    <div class="settings-grid">

      <!-- Company Information -->
      <div class="s-card">
        <div class="s-card-head"><i class="fa fa-building"></i><h3>Company Information</h3></div>
        <div class="s-card-body">
          <form method="post">
            <input type="hidden" name="_section" value="company">
            <div class="fg"><label>Company Name</label><input type="text" name="company_name" value="<?= h($cfg['company_name']) ?>" placeholder="e.g. Creative Elements" required></div>
            <div class="fg"><label>Company Email</label><input type="email" name="company_email" value="<?= h($cfg['company_email']) ?>" placeholder="info@company.com"></div>
            <div class="fg"><label>Phone Number</label><input type="text" name="company_phone" value="<?= h($cfg['company_phone']) ?>" placeholder="+60 12-345 6789"></div>
            <div class="fg"><label>Address</label><textarea name="company_address" placeholder="Street, City, State, Country"><?= h($cfg['company_address']) ?></textarea></div>
            <div class="save-row"><button class="btn btn-primary btn-sm" type="submit"><i class="fa fa-save"></i> Save</button></div>
          </form>
        </div>
      </div>

      <!-- SMTP Configuration (spans full width) -->
      <div class="s-card" style="grid-column:span 2">
        <div class="s-card-head"><i class="fa fa-paper-plane"></i><h3>SMTP Email Configuration</h3></div>
        <div class="s-card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;flex-wrap:wrap">

            <!-- SMTP Credentials -->
            <form method="post">
              <input type="hidden" name="_section" value="smtp">
              <div style="font-size:.78rem;font-weight:700;color:var(--clr-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.85rem">Server Settings</div>
              <div class="fg">
                <label>SMTP Host</label>
                <input type="text" name="smtp_host" class="fg-input" value="<?= h($cfg['smtp_host']) ?>" placeholder="smtp.gmail.com">
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                <div class="fg">
                  <label>Port</label>
                  <input type="number" name="smtp_port" value="<?= h($cfg['smtp_port']) ?>" placeholder="587">
                </div>
                <div class="fg">
                  <label>Encryption</label>
                  <select name="smtp_encryption">
                    <option value="tls"  <?= $cfg['smtp_encryption']==='tls'?'selected':'' ?>>TLS (STARTTLS) – 587</option>
                    <option value="ssl"  <?= $cfg['smtp_encryption']==='ssl'?'selected':'' ?>>SSL/SMTPS – 465</option>
                    <option value="none" <?= $cfg['smtp_encryption']==='none'?'selected':'' ?>>None – 25</option>
                  </select>
                </div>
              </div>
              <div class="fg">
                <label>SMTP Username</label>
                <input type="text" name="smtp_username" value="<?= h($cfg['smtp_username']) ?>" placeholder="your@gmail.com" autocomplete="off">
              </div>
              <div class="fg">
                <label>SMTP Password <small style="color:var(--clr-muted)">(leave blank to keep current)</small></label>
                <div class="pw-wrap">
                  <input type="password" name="smtp_password" id="smtp-pw" placeholder="••••••••" autocomplete="new-password">
                  <button type="button" class="eye-btn" onclick="togglePw('smtp-pw',this)"><i class="fa fa-eye"></i></button>
                </div>
              </div>
              <div class="fg">
                <label>From Email</label>
                <input type="email" name="smtp_from_email" value="<?= h($cfg['smtp_from_email']) ?>" placeholder="noreply@company.com">
              </div>
              <div class="fg">
                <label>From Name</label>
                <input type="text" name="smtp_from_name" value="<?= h($cfg['smtp_from_name']) ?>" placeholder="Creative Elements Portal">
              </div>
              <div class="save-row"><button class="btn btn-primary btn-sm" type="submit"><i class="fa fa-save"></i> Save SMTP Settings</button></div>
            </form>

            <!-- Test & Tips -->
            <div>
              <div style="font-size:.78rem;font-weight:700;color:var(--clr-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.85rem">Send Test Email</div>
              <form method="post" style="margin-bottom:1.5rem">
                <input type="hidden" name="_section" value="smtp_test">
                <div class="fg">
                  <label>Send Test To</label>
                  <input type="email" name="test_email" value="<?= h($_SESSION['emp_name'] ?? '') ?>" placeholder="you@gmail.com">
                </div>
                <div class="save-row"><button class="btn btn-outline btn-sm" type="submit"><i class="fa fa-paper-plane"></i> Send Test Email</button></div>
              </form>

              <div style="background:var(--clr-bg);border-radius:10px;padding:1rem;font-size:.78rem;color:var(--clr-muted);line-height:1.7">
                <div style="font-weight:700;color:var(--clr-text);margin-bottom:.5rem"><i class="fa fa-circle-info" style="color:var(--clr-primary)"></i> Quick Setup Guide</div>
                <strong style="color:var(--clr-text)">Gmail:</strong><br>
                Host: <code>smtp.gmail.com</code> · Port: <code>587</code> · TLS<br>
                Use an <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color:var(--clr-primary)">App Password</a> (not your regular Gmail password).<br><br>
                <strong style="color:var(--clr-text)">Outlook / Hotmail:</strong><br>
                Host: <code>smtp.office365.com</code> · Port: <code>587</code> · TLS<br><br>
                <strong style="color:var(--clr-text)">cPanel Mail:</strong><br>
                Host: <code>mail.yourdomain.com</code> · Port: <code>465</code> · SSL
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- Company Logo -->
      <div class="s-card">
        <div class="s-card-head"><i class="fa fa-image"></i><h3>Company Logo</h3></div>
        <div class="s-card-body">
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="_section" value="logo">
            <div class="logo-preview" onclick="document.getElementById('logo-input').click()" id="logo-box">
              <?php if ($logo_url): ?>
                <img src="<?= h($logo_url) ?>" alt="Logo" id="logo-img">
              <?php else: ?>
                <div class="no-logo"><i class="fa fa-image"></i>Click to upload</div>
              <?php endif; ?>
            </div>
            <input type="file" name="logo" id="logo-input" accept="image/*" onchange="previewLogo(this)">
            <p style="font-size:.76rem;color:var(--clr-muted);margin-bottom:.9rem">PNG, JPG or WebP · Max 2 MB. Displayed in emails and reports.</p>
            <div class="save-row"><button class="btn btn-primary btn-sm" type="submit"><i class="fa fa-upload"></i> Upload</button></div>
          </form>
        </div>
      </div>

      <!-- My Profile -->
      <div class="s-card">
        <div class="s-card-head"><i class="fa fa-user-circle"></i><h3>My Profile</h3></div>
        <div class="s-card-body">
          <form method="post">
            <input type="hidden" name="_section" value="profile">
            <div class="fg"><label>Display Name</label><input type="text" name="admin_name" value="<?= h($me['name'] ?? '') ?>" required></div>
            <div class="fg"><label>Email Address</label><input type="email" value="<?= h($me['email'] ?? '') ?>" disabled style="opacity:.5;cursor:not-allowed"></div>
            <p style="font-size:.76rem;color:var(--clr-muted);margin-top:.25rem">Email cannot be changed here. Contact your database admin if needed.</p>
            <div class="save-row"><button class="btn btn-primary btn-sm" type="submit"><i class="fa fa-save"></i> Save</button></div>
          </form>
        </div>
      </div>

      <!-- Change Password -->
      <div class="s-card">
        <div class="s-card-head"><i class="fa fa-lock"></i><h3>Change Password</h3></div>
        <div class="s-card-body">
          <form method="post">
            <input type="hidden" name="_section" value="password">
            <div class="fg">
              <label>Current Password</label>
              <div class="pw-wrap">
                <input type="password" name="current_password" id="pw0" placeholder="••••••••" required>
                <button type="button" class="eye-btn" onclick="togglePw('pw0',this)"><i class="fa fa-eye"></i></button>
              </div>
            </div>
            <div class="fg">
              <label>New Password</label>
              <div class="pw-wrap">
                <input type="password" name="new_password" id="pw1" placeholder="Min. 8 characters" required oninput="checkStr(this.value)">
                <button type="button" class="eye-btn" onclick="togglePw('pw1',this)"><i class="fa fa-eye"></i></button>
              </div>
              <div class="strength-bar-wrap"><div class="strength-bar" id="sb"></div></div>
            </div>
            <div class="fg">
              <label>Confirm New Password</label>
              <div class="pw-wrap">
                <input type="password" name="confirm_password" id="pw2" placeholder="Repeat new password" required>
                <button type="button" class="eye-btn" onclick="togglePw('pw2',this)"><i class="fa fa-eye"></i></button>
              </div>
            </div>
            <div class="save-row"><button class="btn btn-primary btn-sm" type="submit"><i class="fa fa-key"></i> Change Password</button></div>
          </form>
        </div>
      </div>

      <!-- Date & Time Override -->
      <?php
        $dt_offset = (int)get_setting('datetime_offset_seconds', '0');
        $app_ts    = time() + $dt_offset;
        $is_overridden = $dt_offset !== 0;
      ?>
      <div class="s-card" style="grid-column:span 2">
        <div class="s-card-head"><i class="fa fa-calendar-clock"></i><h3>Date &amp; Time Override</h3></div>
        <div class="s-card-body">
          <div style="display:flex;gap:1.5rem;flex-wrap:wrap;align-items:flex-start">
            <div style="flex:1;min-width:220px">
              <form method="post">
                <input type="hidden" name="_section" value="datetime">
                <div class="fg">
                  <label>Set System Date &amp; Time To</label>
                  <input type="datetime-local" name="custom_datetime"
                    value="<?= date('Y-m-d\TH:i', $app_ts) ?>">
                </div>
                <div class="save-row" style="gap:.5rem">
                  <?php if ($is_overridden): ?>
                  <button type="submit" name="reset_dt" value="1" class="btn btn-outline btn-sm">
                    <i class="fa fa-rotate-left"></i> Reset to Real Time
                  </button>
                  <?php endif; ?>
                  <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-check"></i> Apply</button>
                </div>
              </form>
            </div>
            <div style="background:var(--clr-bg);border-radius:10px;padding:1rem 1.25rem;min-width:200px">
              <div style="font-size:.72rem;color:var(--clr-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.5rem">System Displays As</div>
              <div style="font-size:1.4rem;font-weight:700;color:var(--clr-text)"><?= date('d M Y', $app_ts) ?></div>
              <div style="font-size:1rem;color:var(--clr-primary);font-weight:600;margin-top:.15rem"><?= date('h:i:s A', $app_ts) ?></div>
              <?php if ($is_overridden): ?>
              <div style="margin-top:.5rem;font-size:.72rem;background:rgba(243,156,18,.12);color:var(--clr-warning);border-radius:6px;padding:.25rem .6rem;display:inline-block">
                <i class="fa fa-triangle-exclamation"></i> Override active
              </div>
              <?php else: ?>
              <div style="margin-top:.5rem;font-size:.72rem;color:var(--clr-muted)"><i class="fa fa-check-circle"></i> Using real server time</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /settings-grid -->
  </main>
</div>

<script src="/assets/js/portal.js"></script>
<script>
function togglePw(id, btn) {
  var inp = document.getElementById(id);
  var ic  = btn.querySelector('i');
  inp.type = inp.type === 'password' ? 'text' : 'password';
  ic.className = inp.type === 'password' ? 'fa fa-eye' : 'fa fa-eye-slash';
}

function checkStr(v) {
  var bar = document.getElementById('sb');
  var s = 0;
  if (v.length >= 8)          s++;
  if (/[A-Z]/.test(v))        s++;
  if (/[0-9]/.test(v))        s++;
  if (/[^A-Za-z0-9]/.test(v)) s++;
  var c = ['#e74c3c','#e67e22','#f39c12','#27ae60'];
  var w = ['25%','50%','75%','100%'];
  bar.style.width      = s > 0 ? w[s-1] : '0';
  bar.style.background = s > 0 ? c[s-1] : '';
}

function previewLogo(input) {
  if (!input.files || !input.files[0]) return;
  var reader = new FileReader();
  reader.onload = function(e) {
    var box = document.getElementById('logo-box');
    box.innerHTML = '<img src="' + e.target.result + '" alt="Preview" style="width:100%;height:100%;object-fit:contain">';
  };
  reader.readAsDataURL(input.files[0]);
}

// Auto-scroll to the saved section after submit
<?php if ($success || $error): ?>
document.querySelector('.alert-banner').scrollIntoView({ behavior: 'smooth', block: 'center' });
<?php endif; ?>
</script>
</body>
</html>
