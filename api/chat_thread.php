<?php
// api/chat_thread.php — fetch a conversation and mark incoming messages read
require_once __DIR__ . '/../includes/config.php';
require_login();

$eid  = current_employee_id();
$with = (int)($_GET['with'] ?? 0);

if (!$with) { json_response(['ok' => false, 'error' => 'Missing contact'], 400); }

$chk = db()->prepare("SELECT id, name FROM employees WHERE id=? AND status='active' LIMIT 1");
$chk->execute([$with]);
$contact = $chk->fetch();
if (!$contact) { json_response(['ok' => false, 'error' => 'Not found'], 404); }

db()->prepare("UPDATE chat_messages SET is_read=1 WHERE sender_id=? AND recipient_id=? AND is_read=0")
   ->execute([$with, $eid]);

$since = (int)($_GET['since_id'] ?? 0);
$sql = "
    SELECT id, sender_id, recipient_id, message, attachment_path, created_at
    FROM chat_messages
    WHERE ((sender_id=? AND recipient_id=?) OR (sender_id=? AND recipient_id=?))
";
$params = [$eid, $with, $with, $eid];
if ($since) {
    $sql .= " AND id > ?";
    $params[] = $since;
}
$sql .= " ORDER BY id ASC LIMIT 200";

$st = db()->prepare($sql);
$st->execute($params);

json_response(['ok' => true, 'contact' => $contact, 'messages' => $st->fetchAll()]);
