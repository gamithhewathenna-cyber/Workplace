<?php
/**
 * cron/time_reports_send.php
 * Emails every active employee their previous month's time report (CC'd to
 * the admin) on the 1st of each month at/after 9 AM.
 *
 * Recommended: cPanel → Cron Jobs → run e.g. once daily around 9 AM:
 *   php /home/YOURUSER/public_html/cron/time_reports_send.php
 *
 * This is a belt-and-suspenders companion to the automatic send that
 * already runs opportunistically whenever anyone is logged into the portal
 * (see time_reports_maybe_send() in includes/config.php) — you do NOT have
 * to set up this cron job for it to work, but it guarantees the report goes
 * out on the 1st even if nobody logs in that day.
 */
require_once __DIR__ . '/../includes/config.php';

time_reports_maybe_send();

echo "Monthly time report check complete.\n";
