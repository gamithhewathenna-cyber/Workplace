<?php
// api/checklist_toggle.php
require_once __DIR__ . '/../includes/config.php';
require_login();

$data = json_decode(file_get_contents('php://input'), true);
$id   = (int)($data['id'] ?? 0);
$eid  = current_employee_id();

$item = db()->prepare("SELECT * FROM daily_checklist WHERE id=? AND employee_id=?");
$item->execute([$id, $eid]);
$row = $item->fetch();

if (!$row) { json_response(['ok'=>false,'error'=>'Not found'], 404); }

if ($row['is_completed']) {
    db()->prepare("UPDATE daily_checklist SET is_completed=0, completed_at=NULL, completed_by=NULL WHERE id=?")
       ->execute([$id]);
} else {
    db()->prepare("UPDATE daily_checklist SET is_completed=1, completed_at=NOW(), completed_by=? WHERE id=?")
       ->execute([$eid, $id]);
}
json_response(['ok'=>true]);
