<?php
/**
 * setup.php — ONE-TIME USE. Delete this file after running!
 * Creates the initial admin and a sample employee account.
 */
require_once __DIR__ . '/includes/config.php';

$users = [
    [
        'name'     => 'Admin User',
        'email'    => 'admin@company.com',
        'password' => 'Admin@2025',
        'position' => 'Administrator',
        'role'     => 'admin',
    ],
    [
        'name'     => 'Sample Employee',
        'email'    => 'employee@company.com',
        'password' => 'Employee@2025',
        'position' => 'Staff',
        'role'     => 'employee',
    ],
];

$ins = db()->prepare("INSERT IGNORE INTO employees (name,email,password,position,role,status) VALUES (?,?,?,?,?,'active')");

echo '<style>body{font-family:sans-serif;max-width:600px;margin:2rem auto;padding:1rem}</style>';
echo '<h2>Setup — Creating Accounts</h2><ul>';

foreach ($users as $u) {
    $hash = password_hash($u['password'], PASSWORD_DEFAULT);
    $ins->execute([$u['name'], $u['email'], $hash, $u['position'], $u['role']]);
    echo '<li><strong>' . htmlspecialchars($u['name']) . '</strong> — ' . htmlspecialchars($u['email']) . ' / <code>' . htmlspecialchars($u['password']) . '</code></li>';
}

echo '</ul>';
echo '<p style="color:green;font-weight:bold">✅ Done! <a href="/login.php">Go to Login</a></p>';
echo '<p style="color:red">⚠️ <strong>Delete this file immediately after use!</strong></p>';
