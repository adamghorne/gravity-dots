<?php
/**
 * Default settings for Aizle Dots.
 *
 * Single source of truth for PHP-side defaults. These MUST stay in sync with
 * the JS engine's internal defaults in assets/js/aizle-dots.js and with the
 * config contract in ASSETS-AND-DEFAULTS.md §2.
 *
 * @package AizleDots
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The default settings array.
 *
 * Keys map to the admin controls (see class-gd-settings.php) and, where noted,
 * to the JS config object printed as window.AizleDots (see class-gd-frontend.php).
 *
 * @return array
 */
function aizledots_default_settings() {
	return array(
		// --- Display (PHP-side gates; not in the JS contract) ---
		'enabled'                => true,        // master on/off.
		'scope'                  => 'sitewide',  // sitewide | front_page | posts | pages.
		'exclude_ids'            => array(),     // int[] of post/page IDs to suppress on.
		'layer'                  => 'over',      // over | behind → canvas class gd-over / gd-behind.

		// --- Look ---
		'palette'                => array( '#0F4FFF', '#F70000', '#7300F0', '#FF6E00', '#00C947', '#FFC400' ),
		'use_theme_colors'       => false,       // bool → pull the palette from the active theme instead.
		'opacity'                => 0.5,         // float 0.05–1.0 → field global alpha.
		'density_area'           => 7000,        // int 2500–20000 → screen px² per particle (LOWER = MORE dots).
		'cursor_radius'          => 320,         // int 0–800 (px) → how wide the cursor push reaches.
		'push'                   => 50,          // int 0–200 (px) → how far dots shove from the cursor.
		'cursor_mode'            => 'push',      // push | pull | swirl → how particles react to the cursor.
		'drift'                  => 4.0,         // float 0–200 (px) → ambient wander; 0 = static field.
		'size_min'               => 1.4,         // float 0.5–6 (px).
		'size_max'               => 4.6,         // float 0.5–10 (px).
		'shape_dot'              => true,        // bool.
		'shape_square'           => true,        // bool.
		'shape_triangle'         => true,        // bool.
		'shape_line'             => true,        // bool → short streaks; orientation reacts to the cursor.
		'link_lines'             => false,       // bool → draw lines between nearby dots (constellation).
		'link_distance'          => 120,         // int 20–300 (px) → max gap that still draws a link.

		// --- Motion & interaction ---
		'rotate_with_cursor'     => true,        // bool → shapes rotate to face the cursor push.
		'sleep_mode'             => false,       // bool → field rests faint/hidden until the cursor stirs it.
		'sleep_opacity'          => 0.0,         // float 0–1 → resting opacity when idle (0 = fully hidden).
		'wake_ms'                => 2000,        // int 100–5000 → how long woken particles stay lit (sleep mode).
		'scroll_strength'        => 40,          // int 0–200 → velocity kick imparted when the page scrolls.
		'avoid_content'          => true,        // bool → particles deflect around on-page text & media.
		'avoid_strength'         => 60,          // int 0–200 → how forcefully they avoid content edges.

		// --- Behaviour ---
		'disable_on_mobile'      => false,       // bool.
		'respect_reduced_motion' => true,        // bool.
	);
}

/**
 * Non-exposed engine constants (kept out of the settings page in v1 to keep it
 * simple). Folded into the JS config object by the frontend class.
 *
 * @return array
 */
function aizledots_engine_constants() {
	return array(
		'min_particles'    => 60,
		'max_particles'    => 5000,  // hard ceiling; the engine auto-throttles below this on slower devices.
		'mobile_particles' => 600,   // cap on small screens (perf), independent of the desktop ceiling.
		'mobile_breakpoint' => 640,

		// Content avoidance: which elements particles deflect around, how big a
		// buffer to keep, and a hard cap on how many boxes we test per frame
		// (perf guard). Rects are read from the DOM only on load/resize.
		'avoid_selectors'  => 'h1, h2, h3, h4, h5, h6, p, li, blockquote, img, figure, button',
		'content_padding'  => 14,
		'max_rects'        => 60,
	);
}

/**
 * Read the saved settings, with every missing key falling back to its default.
 *
 * @return array
 */
function aizledots_get_settings() {
	return wp_parse_args( get_option( 'aizledots_settings', array() ), aizledots_default_settings() );
}

/**
 * Read a colour palette from the active theme (theme.json / global settings).
 *
 * Pulls the theme + user-custom palettes (not WordPress's generic defaults),
 * keeps only valid hex values, de-duplicates, and caps the count.
 *
 * @return array Array of hex colour strings (may be empty on classic themes).
 */
function aizledots_get_theme_palette() {
	$out = array();
	if ( ! function_exists( 'wp_get_global_settings' ) ) {
		return $out;
	}

	$palette = wp_get_global_settings( array( 'color', 'palette' ) );
	$groups  = array();
	if ( isset( $palette['theme'] ) ) {
		$groups[] = $palette['theme'];
	}
	if ( isset( $palette['custom'] ) ) {
		$groups[] = $palette['custom'];
	}
	if ( empty( $groups ) && is_array( $palette ) ) {
		$groups[] = $palette; // flat-list fallback for unusual return shapes.
	}

	foreach ( $groups as $group ) {
		if ( ! is_array( $group ) ) {
			continue;
		}
		foreach ( $group as $entry ) {
			if ( empty( $entry['color'] ) ) {
				continue;
			}
			$hex = sanitize_hex_color( $entry['color'] );
			if ( $hex && ! in_array( $hex, $out, true ) ) {
				$out[] = $hex;
			}
		}
	}

	return array_slice( $out, 0, 12 );
}

/**
 * One-click "looks". Each preset is a bundle of look/motion settings that the
 * admin JS applies to the form (the user still clicks Save). Presets purposely
 * do NOT touch colours (the palette stays the user's choice) or placement
 * (scope / layer / performance).
 *
 * @return array
 */
function aizledots_presets() {
	return array(
		'calm'          => array(
			'label'    => __( 'Calm', 'aizle-dots' ),
			'settings' => array(
				'opacity' => 0.35, 'density_area' => 13000, 'size_min' => 2.0, 'size_max' => 5.0,
				'shape_dot' => true, 'shape_square' => false, 'shape_triangle' => false, 'shape_line' => false,
				'link_lines' => false, 'link_distance' => 120,
				'drift' => 6, 'push' => 40, 'cursor_radius' => 360, 'cursor_mode' => 'push',
				'rotate_with_cursor' => false, 'sleep_mode' => false, 'sleep_opacity' => 0.0, 'scroll_strength' => 20,
			),
		),
		'confetti'      => array(
			'label'    => __( 'Confetti', 'aizle-dots' ),
			'settings' => array(
				'opacity' => 0.85, 'density_area' => 1600, 'size_min' => 1.5, 'size_max' => 4.0,
				'shape_dot' => true, 'shape_square' => true, 'shape_triangle' => true, 'shape_line' => true,
				'link_lines' => false, 'link_distance' => 120,
				'drift' => 22, 'push' => 70, 'cursor_radius' => 320, 'cursor_mode' => 'push',
				'rotate_with_cursor' => true, 'sleep_mode' => false, 'sleep_opacity' => 0.0, 'scroll_strength' => 80,
			),
		),
		'constellation' => array(
			'label'    => __( 'Constellation', 'aizle-dots' ),
			'settings' => array(
				'opacity' => 0.7, 'density_area' => 4500, 'size_min' => 1.4, 'size_max' => 3.0,
				'shape_dot' => true, 'shape_square' => false, 'shape_triangle' => false, 'shape_line' => false,
				'link_lines' => true, 'link_distance' => 140,
				'drift' => 4, 'push' => 45, 'cursor_radius' => 320, 'cursor_mode' => 'push',
				'rotate_with_cursor' => false, 'sleep_mode' => false, 'sleep_opacity' => 0.0, 'scroll_strength' => 30,
			),
		),
		'minimal'       => array(
			'label'    => __( 'Minimal', 'aizle-dots' ),
			'settings' => array(
				'opacity' => 0.25, 'density_area' => 16000, 'size_min' => 1.0, 'size_max' => 2.5,
				'shape_dot' => true, 'shape_square' => false, 'shape_triangle' => false, 'shape_line' => false,
				'link_lines' => false, 'link_distance' => 120,
				'drift' => 3, 'push' => 35, 'cursor_radius' => 300, 'cursor_mode' => 'push',
				'rotate_with_cursor' => false, 'sleep_mode' => false, 'sleep_opacity' => 0.0, 'scroll_strength' => 15,
			),
		),
		'aizle'         => array(
			'label'    => __( 'Aizle', 'aizle-dots' ),
			'settings' => array(
				'opacity' => 0.6, 'density_area' => 4000, 'size_min' => 2.0, 'size_max' => 4.5,
				'shape_dot' => true, 'shape_square' => false, 'shape_triangle' => true, 'shape_line' => true,
				'link_lines' => true, 'link_distance' => 120,
				'drift' => 5, 'push' => 55, 'cursor_radius' => 420, 'cursor_mode' => 'swirl',
				'rotate_with_cursor' => true, 'sleep_mode' => true, 'sleep_opacity' => 0.06, 'scroll_strength' => 50,
			),
		),
	);
}
