<?php
// api/chat_upload.php — send a chat image attachment (max 2MB, auto-deletes after 3 days)
require_once __DIR__ . '/../includes/config.php';
require_login();

$eid          = current_employee_id();
$recipient_id = (int)($_POST['recipient_id'] ?? 0);

if (!$recipient_id || $recipient_id === $eid) {
    json_response(['ok' => false, 'error' => 'Invalid recipient'], 400);
}

$chk = db()->prepare("SELECT id FROM employees WHERE id=? AND status='active' LIMIT 1");
$chk->execute([$recipient_id]);
if (!$chk->fetchColumn()) { json_response(['ok' => false, 'error' => 'Recipient not found'], 404); }

if (empty($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
    json_response(['ok' => false, 'error' => 'No image provided'], 400);
}
$file = $_FILES['image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    json_response(['ok' => false, 'error' => 'Upload failed'], 400);
}
if ($file['size'] > CHAT_IMAGE_MAX_BYTES) {
    json_response(['ok' => false, 'error' => 'Image is larger than 2MB'], 400);
}

$info = @getimagesize($file['tmp_name']);
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
if (!$info || !isset($allowed[$info['mime']])) {
    json_response(['ok' => false, 'error' => 'File must be a JPG, PNG, GIF, or WEBP image'], 400);
}

if (!is_dir(UPLOAD_CHAT_DIR)) mkdir(UPLOAD_CHAT_DIR, 0755, true);

$ext  = $allowed[$info['mime']];
$name = uniqid('chat_', true) . '.' . $ext;
if (!move_uploaded_file($file['tmp_name'], UPLOAD_CHAT_DIR . $name)) {
    json_response(['ok' => false, 'error' => 'Could not save image'], 500);
}

$caption = trim($_POST['message'] ?? '');
if (mb_strlen($caption) > 2000) $caption = mb_substr($caption, 0, 2000);

$ins = db()->prepare("
    INSERT INTO chat_messages (sender_id, recipient_id, message, attachment_path, attachment_expires_at)
    VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL " . CHAT_IMAGE_RETAIN_DAYS . " DAY))
");
$ins->execute([$eid, $recipient_id, $caption, 'chat/' . $name]);
$mid = db()->lastInsertId();

$row = db()->prepare("SELECT id, sender_id, recipient_id, message, attachment_path, created_at FROM chat_messages WHERE id=?");
$row->execute([$mid]);

json_response(['ok' => true, 'message' => $row->fetch()]);
