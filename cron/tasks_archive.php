<?php
/**
 * cron/tasks_archive.php
 * Archives completed tasks (hides them from the active task list/board,
 * without deleting anything) every Monday at/after 4PM.
 *
 * Recommended: cPanel → Cron Jobs → run e.g. every hour on Mondays:
 *   php /home/YOURUSER/public_html/cron/tasks_archive.php
 *
 * This is a belt-and-suspenders companion to the automatic archiving that
 * already runs opportunistically whenever anyone is logged into the portal
 * (see tasks_maybe_weekly_archive() in includes/config.php) — you do NOT
 * have to set up this cron job for it to work, but it guarantees the
 * Monday 4PM archive happens even if nobody logs in that day.
 */
require_once __DIR__ . '/../includes/config.php';

tasks_maybe_weekly_archive();

echo "Task archive check complete.\n";
