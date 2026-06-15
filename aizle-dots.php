<?php
/**
 * Plugin Name:       Aizle Dots
 * Description:       An interactive particle background that makes any WordPress site feel alive: coloured dots that drift gently and part around the cursor. No code, no libraries.
 * Version:           1.5.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Aizle
 * Author URI:        https://aizle.co
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aizle-dots
 * Domain Path:       /languages
 *
 * @package AizleDots
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIZLEDOTS_VERSION', '1.5.0' );
define( 'AIZLEDOTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'AIZLEDOTS_URL', plugin_dir_url( __FILE__ ) );
define( 'AIZLEDOTS_FILE', __FILE__ );
define( 'AIZLEDOTS_BASENAME', plugin_basename( __FILE__ ) );

require_once AIZLEDOTS_PATH . 'includes/defaults.php';
require_once AIZLEDOTS_PATH . 'includes/class-gd-frontend.php';

if ( is_admin() ) {
	require_once AIZLEDOTS_PATH . 'includes/class-gd-settings.php';
}

// Translations are loaded automatically by WordPress (4.6+) for the plugin's
// text domain, so no manual load_plugin_textdomain() call is needed.

add_action( 'init', 'aizledots_bootstrap' );
function aizledots_bootstrap() {
	if ( is_admin() && class_exists( 'AizleDots_Settings' ) ) {
		new AizleDots_Settings();
	}
	if ( class_exists( 'AizleDots_Frontend' ) ) {
		new AizleDots_Frontend();
	}
}

register_activation_hook( __FILE__, 'aizledots_on_activate' );
function aizledots_on_activate() {
	if ( false === get_option( 'aizledots_settings', false ) ) {
		add_option( 'aizledots_settings', aizledots_default_settings() );
	}
}
