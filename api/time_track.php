<?php
// api/time_track.php
require_once __DIR__ . '/../includes/config.php';
require_login();

$data     = json_decode(file_get_contents('php://input'), true);
$action   = $data['action']   ?? '';
$track_id = (int)($data['track_id'] ?? 0);
$task_id  = (int)($data['task_id']  ?? 0);
$eid      = current_employee_id();

switch ($action) {
    case 'start':
        if (!$task_id) { json_response(['ok'=>false,'error'=>'task_id required'], 400); }
        // Close any running session
        db()->prepare("UPDATE time_tracking SET status='finished', finished_at=NOW() WHERE employee_id=? AND status='running'")
           ->execute([$eid]);
        $ins = db()->prepare("INSERT INTO time_tracking (task_id,employee_id,started_at,status) VALUES (?,?,NOW(),'running')");
        $ins->execute([$task_id, $eid]);
        json_response(['ok'=>true, 'track_id' => db()->lastInsertId()]);
        break;

    case 'pause':
        db()->prepare("UPDATE time_tracking SET paused_at=NOW(), status='paused' WHERE id=? AND employee_id=?")
           ->execute([$track_id, $eid]);
        json_response(['ok'=>true]);
        break;

    case 'resume':
        $row = db()->prepare("SELECT * FROM time_tracking WHERE id=? AND employee_id=?");
        $row->execute([$track_id, $eid]);
        $t = $row->fetch();
        if (!$t) { json_response(['ok'=>false], 404); }
        $break = $t['paused_at'] ? (time() - strtotime($t['paused_at'])) : 0;
        db()->prepare("UPDATE time_tracking SET resumed_at=NOW(), status='running', break_seconds=break_seconds+? WHERE id=?")
           ->execute([$break, $track_id]);
        json_response(['ok'=>true]);
        break;

    case 'finish':
        $row = db()->prepare("SELECT * FROM time_tracking WHERE id=? AND employee_id=?");
        $row->execute([$track_id, $eid]);
        $t = $row->fetch();
        if (!$t) { json_response(['ok'=>false], 404); }
        $total = time() - strtotime($t['started_at']);
        $break = $t['break_seconds'];
        db()->prepare("UPDATE time_tracking SET finished_at=NOW(), status='finished', total_seconds=?, break_seconds=? WHERE id=?")
           ->execute([$total, $break, $track_id]);
        json_response(['ok'=>true, 'total_seconds'=>$total, 'break_seconds'=>$break]);
        break;

    default:
        json_response(['ok'=>false,'error'=>'Unknown action'], 400);
}
