<?php
// api/activity.php — presence heartbeat from the browser.
// Client reports how many seconds since the user last interacted with the
// page; this classifies Active/Away and accumulates today's totals.
require_once __DIR__ . '/../includes/config.php';
require_login();

$data        = json_decode(file_get_contents('php://input'), true);
$idleSeconds = max(0, (int)($data['idle_seconds'] ?? 0));
$eid         = current_employee_id();
$today       = date('Y-m-d');

$st = db()->prepare("SELECT id, last_heartbeat_at FROM emp_login_log WHERE employee_id=? AND login_date=?");
$st->execute([$eid, $today]);
$row = $st->fetch();
if (!$row) {
    json_response(['ok' => false, 'error' => 'No login record for today'], 404);
}

$awaySeconds   = (int)get_setting('away_after_minutes', '15') * 60;
$logoutSeconds = (int)get_setting('auto_logout_after_minutes', '45') * 60;

// Reconstruct the real moment of last activity from the client's self-report,
// rather than just "when did we last hear from this browser".
$lastActivityAt = date('Y-m-d H:i:s', time() - $idleSeconds);

// Time elapsed since the previous heartbeat, capped so a suspended
// laptop/backgrounded tab can't credit a huge jump to one status.
$elapsed = $row['last_heartbeat_at']
    ? max(0, min(300, time() - strtotime($row['last_heartbeat_at'])))
    : 0;

if ($idleSeconds >= $logoutSeconds) {
    db()->prepare("UPDATE emp_login_log
                   SET presence_status='logged_out', last_activity_at=?, last_heartbeat_at=NOW(),
                       logout_at=NOW(), logout_reason='Inactive / Auto Logout'
                   WHERE id=?")
       ->execute([$lastActivityAt, $row['id']]);

    $_SESSION = [];
    session_destroy();
    json_response(['ok' => true, 'logged_out' => true]);
}

if ($idleSeconds >= $awaySeconds) {
    db()->prepare("UPDATE emp_login_log
                   SET presence_status='away', away_seconds=away_seconds+?, last_activity_at=?, last_heartbeat_at=NOW()
                   WHERE id=?")
       ->execute([$elapsed, $lastActivityAt, $row['id']]);
} else {
    db()->prepare("UPDATE emp_login_log
                   SET presence_status='active', active_seconds=active_seconds+?, last_activity_at=?, last_heartbeat_at=NOW()
                   WHERE id=?")
       ->execute([$elapsed, $lastActivityAt, $row['id']]);
}

json_response(['ok' => true, 'logged_out' => false]);
