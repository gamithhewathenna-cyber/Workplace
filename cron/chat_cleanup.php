<?php
/**
 * cron/chat_cleanup.php
 * Deletes expired chat image attachments (3 days) and purges chat history
 * older than 2 months. Safe to run as often as you like — it's just DELETE
 * statements guarded by date comparisons.
 *
 * Recommended: cPanel → Cron Jobs → run daily:
 *   php /home/YOURUSER/public_html/cron/chat_cleanup.php
 *
 * This is a belt-and-suspenders companion to the automatic cleanup that
 * already runs opportunistically whenever the chat widget is used
 * (see chat_maybe_cleanup() in includes/config.php) — you do NOT have to
 * set up this cron job for cleanup to work, but it guarantees cleanup runs
 * even during long stretches with no one logged into the portal.
 */
require_once __DIR__ . '/../includes/config.php';

chat_run_cleanup();

echo "Chat cleanup complete.\n";
