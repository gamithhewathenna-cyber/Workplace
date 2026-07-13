<?php
/**
 * cron/client_followups_remind.php
 * Emails every client with pending follow-ups every Monday and Thursday
 * at/after 8:30 AM.
 *
 * Recommended: cPanel → Cron Jobs → run e.g. every 30 min on Mon & Thu:
 *   php /home/YOURUSER/public_html/cron/client_followups_remind.php
 *
 * This is a belt-and-suspenders companion to the automatic reminder that
 * already runs opportunistically whenever anyone is logged into the portal
 * (see client_followups_maybe_remind() in includes/config.php) — you do
 * NOT have to set up this cron job for it to work, but it guarantees the
 * Monday/Thursday 8:30 AM reminder goes out even if nobody logs in that
 * day. Make sure Admin → Settings → Site URL is set, since there is no
 * HTTP request here to infer the domain from.
 */
require_once __DIR__ . '/../includes/config.php';

client_followups_maybe_remind();

echo "Client follow-up reminder check complete.\n";
