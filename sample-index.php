<?php
/*
 * sample-index.php — Public links page entry point for YOURLS
 *
 * INSTALLATION INSTRUCTIONS
 * -------------------------
 * 1. BACK UP your existing index.php before making any changes.
 * 2. Copy this file to your YOURLS root directory and rename it to index.php
 *    (or replace the contents of your existing index.php with the single line below).
 * 3. Make sure linkslist.php is in your user/pages/ directory.
 * 4. Activate the Link Status Manager plugin in your YOURLS admin.
 * 5. Go to Plugins > Link Status Manager > Page Settings and configure your logo,
 *    page title, and heading.
 * 6. Visit your YOURLS site root — the public links page should appear.
 *
 * NOTE: Do NOT overwrite your config.php — it contains your site credentials.
 */

include 'user/pages/linkslist.php';
