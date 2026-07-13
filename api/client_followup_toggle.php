<?php
// api/client_followup_toggle.php — toggle a client follow-up item complete/incomplete
require_once __DIR__ . '/../includes/config.php';
require_login();

$eid  = current_employee_id();
$data = json_decode(file_get_contents('php://input'), true);
$id   = (int)($data['id'] ?? 0);

$row = db()->prepare("SELECT * FROM client_followups WHERE id=?");
$row->execute([$id]);
$row = $row->fetch();

if (!$row) { json_response(['ok' => false, 'error' => 'Not found'], 404); }

if ($row['is_completed']) {
    db()->prepare("UPDATE client_followups SET is_completed=0, completed_at=NULL, completed_by=NULL WHERE id=?")
       ->execute([$id]);
} else {
    db()->prepare("UPDATE client_followups SET is_completed=1, completed_at=NOW(), completed_by=? WHERE id=?")
       ->execute([$eid, $id]);
}

json_response(['ok' => true]);
