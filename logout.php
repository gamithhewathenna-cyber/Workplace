<?php
require_once __DIR__ . '/includes/config.php';

// Clear session data
$_SESSION = [];

// Expire the session cookie
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 86400,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']
    );
}

session_destroy();
header('Location: /login.php');
exit;
