<?php
// api/announcement_dismiss.php — mark a company announcement banner as dismissed for the current employee
require_once __DIR__ . '/../includes/config.php';
require_login();

$eid  = current_employee_id();
$data = json_decode(file_get_contents('php://input'), true);
$aid  = (int)($data['id'] ?? 0);

if (!$aid) { json_response(['ok' => false, 'error' => 'Missing id'], 400); }

db()->prepare("INSERT IGNORE INTO announcement_dismissals (announcement_id, employee_id) VALUES (?,?)")
   ->execute([$aid, $eid]);

json_response(['ok' => true]);
