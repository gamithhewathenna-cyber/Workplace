<?php
/**
 * config.php
 * Shared configuration — adjust DB constants to match your cPanel setup.
 * Include this file at the top of every module page.
 */

// ── Database ──────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_CHAR', 'utf8mb4');

// ── Company Rules ─────────────────────────────────────────
define('LOGIN_HOUR',   8);   // 08:00
define('LOGIN_MINUTE', 0);

// ── File Upload Paths ─────────────────────────────────────
define('UPLOAD_DIR',        __DIR__ . '/../uploads/');
define('UPLOAD_CERT_DIR',   __DIR__ . '/../uploads/certs/');
define('UPLOAD_TASK_DIR',   __DIR__ . '/../uploads/tasks/');
define('UPLOAD_URL',        '/uploads/');

// ── Timezone ──────────────────────────────────────────────
date_default_timezone_set('Asia/Kuala_Lumpur'); // adjust as needed

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
    $holidays = db()
        ->query("SELECT date FROM holidays WHERE date BETWEEN '$start' AND '$end'")
        ->fetchAll(PDO::FETCH_COLUMN);
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
    $templates = db()->query("SELECT id FROM checklist_templates WHERE is_active=1")->fetchAll(PDO::FETCH_COLUMN);
    $ins = db()->prepare("INSERT IGNORE INTO daily_checklist (template_id,employee_id,check_date) VALUES (?,?,?)");
    foreach ($templates as $tid) {
        $ins->execute([$tid, $eid, $date]);
    }
}

// Run login tracking on every page load if logged in
if (current_employee_id()) {
    record_login();
}
