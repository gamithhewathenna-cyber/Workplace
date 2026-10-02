<?php
/**
 * cron/time_reports_send.php
 * Emails every active employee their previous month's time report (CC'd to
 * the admin) once the calendar has moved into a new month.
 *
 * Recommended: cPanel → Cron Jobs → run e.g. once daily:
 *   php /home/YOURUSER/public_html/cron/time_reports_send.php
 *
 * This is a belt-and-suspenders companion to the automatic send that
 * already runs opportunistically whenever anyone is logged into the portal
 * (see time_reports_maybe_send() in includes/config.php) — you do NOT have
 * to set up this cron job for it to work, but it guarantees the report goes
 * out even on months nobody happens to log in right after the 1st, and it
 * will keep retrying on each run if a prior attempt failed to send.
 */
require_once __DIR__ . '/../includes/config.php';

time_reports_maybe_send();

echo "Monthly time report check complete.\n";
