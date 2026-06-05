<?php
/*
 * sample-index.php — Public links page entry point for YOURLS
 *
 * INSTALLATION INSTRUCTIONS
 * -------------------------
 * 1. BACK UP your existing index.php before making any changes.
 * 2. Copy this file to your YOURLS root directory and rename it to index.php
 *    (or merge the relevant lines into your existing index.php).
 * 3. Make sure linkslist.inc.php is in your user/pages/ directory.
 * 4. Activate the Link Status Manager plugin in your YOURLS admin.
 * 5. Visit your YOURLS site root — the public links page should appear.
 *
 * NOTE: The path in the include below assumes linkslist.inc.php is located at
 *       user/pages/linkslist.inc.php relative to your YOURLS root.
 *       Adjust if your installation uses a different directory structure.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/load-yourls.php';
include 'user/pages/linkslist.inc.php';
