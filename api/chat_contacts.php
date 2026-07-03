<?php
// api/chat_contacts.php — list active employees with unread count + last message preview
require_once __DIR__ . '/../includes/config.php';
require_login();

$eid = current_employee_id();

$st = db()->prepare("
    SELECT e.id, e.name, e.position,
        (SELECT COUNT(*) FROM chat_messages cm
          WHERE cm.sender_id = e.id AND cm.recipient_id = ? AND cm.is_read = 0) AS unread_count,
        (SELECT cm2.message FROM chat_messages cm2
          WHERE (cm2.sender_id = e.id AND cm2.recipient_id = ?)
             OR (cm2.sender_id = ? AND cm2.recipient_id = e.id)
          ORDER BY cm2.id DESC LIMIT 1) AS last_message,
        (SELECT cm3.created_at FROM chat_messages cm3
          WHERE (cm3.sender_id = e.id AND cm3.recipient_id = ?)
             OR (cm3.sender_id = ? AND cm3.recipient_id = e.id)
          ORDER BY cm3.id DESC LIMIT 1) AS last_message_at
    FROM employees e
    WHERE e.status = 'active' AND e.id != ?
    ORDER BY last_message_at IS NULL, last_message_at DESC, e.name ASC
");
$st->execute([$eid, $eid, $eid, $eid, $eid, $eid]);

json_response(['ok' => true, 'contacts' => $st->fetchAll()]);
