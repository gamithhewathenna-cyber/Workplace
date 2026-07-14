<?php
/**
 * config.php
 * Shared configuration — adjust DB constants to match your cPanel setup.
 * Include this file at the top of every module page.
 */

// ── Database ──────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'matsaqyg_Workplace');
define('DB_USER', 'matsaqyg_Workplaceadmin');
define('DB_PASS', 'W!YXPL1ti1o;ZQY,');
define('DB_CHAR', 'utf8mb4');

// ── Company Rules ─────────────────────────────────────────
define('LOGIN_HOUR',   8);   // 08:00
define('LOGIN_MINUTE', 0);

// ── File Upload Paths ─────────────────────────────────────
define('UPLOAD_DIR',        __DIR__ . '/../uploads/');
define('UPLOAD_CERT_DIR',   __DIR__ . '/../uploads/certs/');
define('UPLOAD_TASK_DIR',   __DIR__ . '/../uploads/tasks/');
define('UPLOAD_CHAT_DIR',   __DIR__ . '/../uploads/chat/');
define('UPLOAD_EXPENSE_DIR', __DIR__ . '/../uploads/expenses/');
define('UPLOAD_URL',        '/uploads/');

// ── Chat retention rules ───────────────────────────────────
define('CHAT_IMAGE_MAX_BYTES',   2 * 1024 * 1024); // 2MB
define('CHAT_IMAGE_RETAIN_DAYS', 3);
define('CHAT_HISTORY_RETAIN_DAYS', 60); // ~2 months

// ── Timezone — Sri Lanka Standard Time (UTC+5:30) ─────────
date_default_timezone_set('Asia/Colombo');

// ── Session ───────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── DB Connection (PDO) ───────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHAR;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ── Auth helpers ──────────────────────────────────────────
function current_employee_id(): int {
    // Adapt to your existing auth column name
    return (int)($_SESSION['employee_id'] ?? $_SESSION['user_id'] ?? 0);
}

function is_manager(): bool {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['manager','admin','hr'], true);
}

// Managers can always assign tasks; a regular employee can too if the
// admin has specifically granted them the can_assign_tasks permission.
function can_assign_tasks(): bool {
    return is_manager() || !empty($_SESSION['can_assign_tasks']);
}

function require_login(): void {
    if (!current_employee_id()) {
        header('Location: /login.php');
        exit;
    }
}

// ── Utility ───────────────────────────────────────────────
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function flash(string $key, string $msg): void {
    $_SESSION['flash'][$key] = $msg;
}

function get_flash(string $key): string {
    $msg = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function working_days(string $start, string $end): float {
    $st = db()->prepare("SELECT date FROM holidays WHERE date BETWEEN ? AND ?");
    $st->execute([$start, $end]);
    $holidays = $st->fetchAll(PDO::FETCH_COLUMN);
    $days  = 0;
    $cur   = new DateTime($start);
    $endDt = new DateTime($end);
    while ($cur <= $endDt) {
        $dow = (int)$cur->format('N');
        if ($dow < 6 && !in_array($cur->format('Y-m-d'), $holidays, true)) {
            $days++;
        }
        $cur->modify('+1 day');
    }
    return (float)$days;
}

function add_notification(int $userId, string $type, string $title, string $message, string $link = ''): void {
    $st = db()->prepare("INSERT INTO notifications (user_id,type,title,message,link) VALUES (?,?,?,?,?)");
    $st->execute([$userId, $type, $title, $message, $link]);
}

// ── Auto-record today's login ──────────────────────────────
function record_login(): void {
    $eid = current_employee_id();
    if (!$eid) return;
    $today = date('Y-m-d');
    $now   = date('Y-m-d H:i:s');

    $st = db()->prepare("SELECT id FROM emp_login_log WHERE employee_id=? AND login_date=?");
    $st->execute([$eid, $today]);
    if ($st->fetch()) return; // already recorded

    $company = new DateTime('today ' . LOGIN_HOUR . ':' . sprintf('%02d', LOGIN_MINUTE));
    $loginDt = new DateTime($now);
    $diff    = (int)(($loginDt->getTimestamp() - $company->getTimestamp()) / 60);

    $status      = $diff > 0 ? 'late' : 'on_time';
    $minutes_late = max(0, $diff);

    $ins = db()->prepare("INSERT INTO emp_login_log (employee_id,login_date,first_login,status,minutes_late)
                          VALUES (?,?,?,?,?)");
    $ins->execute([$eid, $today, $now, $status, $minutes_late]);

    if ($status === 'late') {
        add_notification($eid, 'late_login', 'Late Login', "You logged in $minutes_late minutes late today.", '/todo/index.php');
    }

    // Auto-create today's checklist for this employee
    generate_daily_checklist($eid, $today);
}

function generate_daily_checklist(int $eid, string $date): void {
    $tpl = db()->prepare("
        SELECT ct.id FROM checklist_templates ct
        WHERE ct.is_active = 1
          AND (
            ct.scope = 'all'
            OR EXISTS (
              SELECT 1 FROM checklist_template_assignees cta
              WHERE cta.template_id = ct.id AND cta.employee_id = ?
            )
          )
    ");
    $tpl->execute([$eid]);
    $templates = $tpl->fetchAll(PDO::FETCH_COLUMN);
    $ins = db()->prepare("INSERT IGNORE INTO daily_checklist (template_id,employee_id,check_date) VALUES (?,?,?)");
    foreach ($templates as $tid) {
        $ins->execute([$tid, $eid, $date]);
    }
}

// ── Mailer ────────────────────────────────────────────────
require_once __DIR__ . '/Mailer.php';

// ── App Time helpers ───────────────────────────────────────
// Uses real server time (Asia/Colombo) plus any admin override stored in DB.
// Override is 0 by default — meaning real time is always used.
function app_time(): int {
    $offset = (int)get_setting('datetime_offset_seconds', '0');
    return time() + $offset;
}

function app_now(string $format = 'Y-m-d H:i:s'): string {
    return date($format, app_time());
}

// ── Company Settings helpers ───────────────────────────────
function get_setting(string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        try {
            $st = db()->prepare("SELECT setting_value FROM company_settings WHERE setting_key=? LIMIT 1");
            $st->execute([$key]);
            $val = $st->fetchColumn();
            $cache[$key] = $val !== false ? $val : $default;
        } catch (PDOException $e) {
            return $default;
        }
    }
    return $cache[$key];
}

function set_setting(string $key, string $value): void {
    db()->prepare("INSERT INTO company_settings (setting_key, setting_value)
                   VALUES (?,?)
                   ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")
       ->execute([$key, $value]);
}

// ── Chat retention cleanup ──────────────────────────────────
// Deletes expired chat image files (CHAT_IMAGE_RETAIN_DAYS) and purges chat
// history older than CHAT_HISTORY_RETAIN_DAYS. Runs at most once every few
// hours, triggered opportunistically from chat API calls — no cron needed,
// though a real cron hitting cron/chat_cleanup.php is more reliable and
// recommended if the portal ever sits idle for long stretches.
function chat_maybe_cleanup(): void {
    $last = (int)get_setting('chat_last_cleanup', '0');
    if (time() - $last < 6 * 3600) return;
    set_setting('chat_last_cleanup', (string)time());
    chat_run_cleanup();
}

function chat_run_cleanup(): void {
    // Expire attachments past their retention window — delete the file,
    // keep the message row so history stays intact.
    $expired = db()->query("
        SELECT id, attachment_path FROM chat_messages
        WHERE attachment_path IS NOT NULL AND attachment_expires_at < NOW()
    ")->fetchAll();
    foreach ($expired as $row) {
        $full = UPLOAD_CHAT_DIR . basename($row['attachment_path']);
        if (is_file($full)) @unlink($full);
    }
    if ($expired) {
        db()->exec("UPDATE chat_messages SET attachment_path=NULL, attachment_expires_at=NULL
                     WHERE attachment_path IS NOT NULL AND attachment_expires_at < NOW()");
    }

    // Purge chat history past the retention window (and any attachment
    // files still attached to those old messages).
    $oldFiles = db()->query("
        SELECT attachment_path FROM chat_messages
        WHERE created_at < DATE_SUB(NOW(), INTERVAL " . CHAT_HISTORY_RETAIN_DAYS . " DAY)
          AND attachment_path IS NOT NULL
    ")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($oldFiles as $path) {
        $full = UPLOAD_CHAT_DIR . basename($path);
        if (is_file($full)) @unlink($full);
    }
    db()->exec("DELETE FROM chat_messages WHERE created_at < DATE_SUB(NOW(), INTERVAL " . CHAT_HISTORY_RETAIN_DAYS . " DAY)");
}

// ── Weekly task archiving ───────────────────────────────────
// Every Monday at/after 4PM, completed tasks are archived (hidden from the
// active task list/board) — never deleted, so time logs and attachments
// stay intact and the task can still be reported on or restored later.
// Runs at most once per Monday, triggered opportunistically on page load
// (see call below) — no cron required, though cron/tasks_archive.php is
// available if you want it to fire reliably even with nobody logged in.
function tasks_maybe_weekly_archive(): void {
    $now = new DateTime();
    if ((int)$now->format('N') !== 1) return;   // 1 = Monday
    if ((int)$now->format('H') < 16) return;    // before 4:00 PM

    $today = $now->format('Y-m-d');
    if (get_setting('tasks_last_archive_date', '') === $today) return;
    set_setting('tasks_last_archive_date', $today);

    db()->exec("UPDATE tasks SET archived_at = NOW() WHERE status = 'completed' AND archived_at IS NULL");
}

// ── Client public follow-up link ────────────────────────────
// A stable, unguessable token per client so the "Send Reminder" email
// can link straight to a no-login public page showing just that
// client's pending items — generated once, then reused forever.
function client_public_token(int $clientId): string {
    $st = db()->prepare("SELECT public_token FROM clients WHERE id=?");
    $st->execute([$clientId]);
    $token = $st->fetchColumn();

    if ($token) return $token;

    $token = bin2hex(random_bytes(24));
    db()->prepare("UPDATE clients SET public_token=? WHERE id=?")->execute([$token, $clientId]);
    return $token;
}

// ── Client Follow-up reminder email ─────────────────────────
// Emails a client every one of their pending (unchecked) follow-up items
// in one message, CC'd to the configured client-reminder addresses
// (Settings), this client's own CC persons, and any extra CC passed in
// (e.g. the employee who triggered it manually). Returns false if the
// client has no email on file or has nothing pending — nothing is sent.
function send_client_followup_reminder(int $clientId, array $extraCc = []): bool {
    $client = db()->prepare("SELECT id, name, email, contact_person, cc_emails FROM clients WHERE id=?");
    $client->execute([$clientId]);
    $client = $client->fetch();
    if (!$client || empty($client['email'])) return false;

    $pending = db()->prepare("SELECT title FROM client_followups WHERE client_id=? AND is_completed=0 ORDER BY created_at ASC");
    $pending->execute([$clientId]);
    $pending = $pending->fetchAll(PDO::FETCH_COLUMN);
    if (!$pending) return false;

    $list = '<ul style="margin:0;padding-left:1.2rem;line-height:1.9;color:#e8e8e8">';
    foreach ($pending as $title) {
        $list .= '<li>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    $list .= '</ul>';

    $greetName  = $client['contact_person'] ?: $client['name'];
    $token      = client_public_token($client['id']);
    $host       = isset($_SERVER['HTTP_HOST'])
        ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
        : rtrim(get_setting('site_url'), '/');
    $publicLink = $host . '/client/followups.php?t=' . $token;

    $html = mail_template(
        'Pending Action Items',
        '<p>Hi ' . htmlspecialchars($greetName, ENT_QUOTES, 'UTF-8') . ',</p>'
        . '<p>Just a quick reminder of the items currently pending for <strong>' . htmlspecialchars($client['name'], ENT_QUOTES, 'UTF-8') . '</strong>:</p>'
        . $list
        . '<p style="margin-top:1.5rem">You can view and check these off directly using the button below — no login needed.</p>'
        . '<p style="margin-top:1.5rem">Let us know if you have any questions.</p>',
        'View & Complete Your Items',
        $publicLink
    );

    $clientCcs = array_filter(array_map('trim', explode(',', $client['cc_emails'] ?? '')));
    $cc = array_values(array_unique(array_filter(array_merge([
        get_setting('client_cc_email_1', 'reach@creativelements.co'),
        get_setting('client_cc_email_2'),
    ], $clientCcs, $extraCc))));

    return send_mail($client['email'], $client['name'], 'Pending Action Items — ' . $client['name'], $html, true, $cc);
}

// ── Client Follow-up reminder schedule ──────────────────────
// Automatically emails every client that has pending follow-ups every
// Monday and Thursday at/after 8:30 AM (once per day) — on top of the
// manual "Send Reminder" button. Runs opportunistically on page load like
// the other scheduled jobs; cron/client_followups_remind.php is the
// belt-and-suspenders companion for reliability.
function client_followups_maybe_remind(): void {
    $now = new DateTime();
    $dow = (int)$now->format('N'); // 1=Mon .. 7=Sun
    if ($dow !== 1 && $dow !== 4) return; // Monday or Thursday only

    $minutesNow = (int)$now->format('H') * 60 + (int)$now->format('i');
    if ($minutesNow < (8 * 60 + 30)) return; // before 8:30 AM

    $today = $now->format('Y-m-d');
    if (get_setting('client_followups_last_reminder_date', '') === $today) return;
    set_setting('client_followups_last_reminder_date', $today);

    client_followups_run_reminders();
}

function client_followups_run_reminders(): int {
    $clientIds = db()->query("SELECT DISTINCT client_id FROM client_followups WHERE is_completed = 0")
        ->fetchAll(PDO::FETCH_COLUMN);

    $sent = 0;
    foreach ($clientIds as $cid) {
        if (send_client_followup_reminder((int)$cid)) $sent++;
    }
    return $sent;
}

// Run login tracking on every page load if logged in
if (current_employee_id()) {
    record_login();
    tasks_maybe_weekly_archive();
    client_followups_maybe_remind();
}
