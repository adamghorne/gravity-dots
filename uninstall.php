<?php
/**
 * Uninstall handler for Aizle Dots.
 *
 * Runs when the plugin is deleted from the Plugins screen. Removes the single
 * option the plugin stores — no leftovers.
 *
 * @package AizleDots
 */

// Only run from WordPress's uninstall routine.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'aizledots_settings' );

// Multisite: clean the option on every site in the network. Use the get_sites()
// API (not a direct DB query) so no caching/prefix concerns apply.
if ( is_multisite() && function_exists( 'get_sites' ) ) {
	$aizledots_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $aizledots_site_ids as $aizledots_site_id ) {
		switch_to_blog( (int) $aizledots_site_id );
		delete_option( 'aizledots_settings' );
		restore_current_blog();
	}
}
