<?php
// No direct call
if ( !defined( 'YOURLS_UNINSTALL_PLUGIN' ) ) die();

// Drop the linkstat table
$table = YOURLS_DB_PREFIX . 'linkstat';
$sql   = "DROP TABLE IF EXISTS `$table`";
yourls_get_db()->fetchAffected( $sql, array() );
