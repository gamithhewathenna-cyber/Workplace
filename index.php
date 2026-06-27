<?php
require_once __DIR__ . '/includes/config.php';

if (current_employee_id()) {
    redirect('/todo/index.php');
} else {
    redirect('/login.php');
}
