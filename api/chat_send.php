<?php
// api/chat_send.php — send a direct message
require_once __DIR__ . '/../includes/config.php';
require_login();

$eid  = current_employee_id();
$data = json_decode(file_get_contents('php://input'), true);

$recipient_id = (int)($data['recipient_id'] ?? 0);
$message      = trim($data['message'] ?? '');

if (!$recipient_id || $recipient_id === $eid || $message === '') {
    json_response(['ok' => false, 'error' => 'Invalid message'], 400);
}
if (mb_strlen($message) > 2000) {
    $message = mb_substr($message, 0, 2000);
}

$chk = db()->prepare("SELECT id FROM employees WHERE id=? AND status='active' LIMIT 1");
$chk->execute([$recipient_id]);
if (!$chk->fetchColumn()) { json_response(['ok' => false, 'error' => 'Recipient not found'], 404); }

$ins = db()->prepare("INSERT INTO chat_messages (sender_id, recipient_id, message) VALUES (?,?,?)");
$ins->execute([$eid, $recipient_id, $message]);
$mid = db()->lastInsertId();

$row = db()->prepare("SELECT id, sender_id, recipient_id, message, created_at FROM chat_messages WHERE id=?");
$row->execute([$mid]);

json_response(['ok' => true, 'message' => $row->fetch()]);
