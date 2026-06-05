<?php
/*
 * sample-index.php — Public links page entry point for YOURLS
 *
 * INSTALLATION INSTRUCTIONS
 * -------------------------
 * 1. BACK UP your existing index.php before making any changes.
 * 2. Copy this file to your YOURLS root directory and rename it to index.php
 *    (or replace the contents of your existing index.php with the single line below).
 * 3. Make sure both linkslist.php and linkslist.inc.php are in your user/pages/ directory.
 * 4. Activate the Link Status Manager plugin in your YOURLS admin.
 * 5. Visit your YOURLS site root — the public links page should appear.
 *
 * CALL CHAIN
 * ----------
 * index.php  →  user/pages/linkslist.php  →  user/pages/linkslist.inc.php
 *
 * linkslist.php    — bootstraps YOURLS and hands off to the inc file
 * linkslist.inc.php — outputs the full public HTML page
 */

include 'user/pages/linkslist.php';
