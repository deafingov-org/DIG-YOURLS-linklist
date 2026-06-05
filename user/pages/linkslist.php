<?php
/*
 * linkslist.php
 * Bootstraps the YOURLS environment and loads the public links list page.
 * Called by index.php in the YOURLS root directory.
 *
 * Call chain:
 *   index.php  →  user/pages/linkslist.php  →  user/pages/linkslist.inc.php
 */

// No direct call outside YOURLS root context
if ( !defined( 'YOURLS_ABSPATH' ) ) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/load-yourls.php';
}

include __DIR__ . '/linkslist.inc.php';
