<?php
// api/calc_days.php
require_once __DIR__ . '/../includes/config.php';
$start = $_GET['start'] ?? '';
$end   = $_GET['end']   ?? '';
if (!$start || !$end || $end < $start) { json_response(['days' => 0]); }
json_response(['days' => working_days($start, $end)]);
