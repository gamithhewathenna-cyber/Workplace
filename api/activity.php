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

try {
    $st = db()->prepare("SELECT id, last_heartbeat_at FROM emp_login_log WHERE employee_id=? AND login_date=?");
    $st->execute([$eid, $today]);
    $row = $st->fetch();
} catch (PDOException $e) {
    // sql/activity_tracking.sql migration not applied yet
    json_response(['ok' => false, 'error' => 'Activity tracking not configured'], 200);
}
if (!$row) {
    json_response(['ok' => false, 'error' => 'No login record for today'], 404);
}

$awaySeconds   = (int)get_setting('away_after_minutes', '15') * 60;
$logoutSeconds = (int)get_setting('auto_logout_after_minutes', '45') * 60;

// Everything below is written using PHP's own clock (not MySQL's NOW()) so
// it stays consistent with how enforce_activity_timeout() reads it back via
// PHP's strtotime() — mixing the two risks a DB-server timezone mismatch
// (e.g. UTC vs Asia/Colombo) making a just-written timestamp look hours old.
$nowStr = date('Y-m-d H:i:s');

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
                   SET presence_status='logged_out', last_activity_at=?, last_heartbeat_at=?,
                       logout_at=?, logout_reason='Inactive / Auto Logout'
                   WHERE id=?")
       ->execute([$lastActivityAt, $nowStr, $nowStr, $row['id']]);

    $_SESSION = [];
    session_destroy();
    json_response(['ok' => true, 'logged_out' => true]);
}

if ($idleSeconds >= $awaySeconds) {
    db()->prepare("UPDATE emp_login_log
                   SET presence_status='away', away_seconds=away_seconds+?, last_activity_at=?, last_heartbeat_at=?
                   WHERE id=?")
       ->execute([$elapsed, $lastActivityAt, $nowStr, $row['id']]);
} else {
    db()->prepare("UPDATE emp_login_log
                   SET presence_status='active', active_seconds=active_seconds+?, last_activity_at=?, last_heartbeat_at=?
                   WHERE id=?")
       ->execute([$elapsed, $lastActivityAt, $nowStr, $row['id']]);
}

json_response(['ok' => true, 'logged_out' => false]);
