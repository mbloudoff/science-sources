<?php

/*
 * Plugin Name: Science Sources
 */

require __DIR__ . '/lib/hooks.php';
require __DIR__ . '/lib/admin-hooks.php';
require __DIR__ . '/lib/source.php';

ScienceSources\hooks();

if ( is_admin() ) {
	ScienceSources\admin_hooks();
}

remove_action( 'wp_scheduled_delete', 'wp_scheduled_delete' );