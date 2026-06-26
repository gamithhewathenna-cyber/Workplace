<?php
// api/mark_read.php
require_once __DIR__ . '/../includes/config.php';
require_login();
db()->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")
   ->execute([current_employee_id()]);
json_response(['ok'=>true]);
