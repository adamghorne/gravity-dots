<?php
/**
 * Frontend: decide-to-load, enqueue assets, print the config object, inject the canvas.
 *
 * @package AizleDots
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles everything the visitor sees.
 */
class AizleDots_Frontend {

	/**
	 * Resolved settings for this request.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * The canvas layer class for this request (gd-over | gd-behind).
	 *
	 * @var string
	 */
	private $layer_class = 'gd-over';

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Decide whether the field shows on the current request.
	 *
	 * @param array $settings Resolved settings.
	 * @return bool
	 */
	private function should_display( $settings ) {
		// Master switch.
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		// Never paint on admin, feeds, or the login screen.
		if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
			return false;
		}

		// Exclude-by-ID (front page has no queried object, so guard for it).
		$current_id = (int) get_queried_object_id();
		if ( $current_id && ! empty( $settings['exclude_ids'] ) && in_array( $current_id, array_map( 'absint', (array) $settings['exclude_ids'] ), true ) ) {
			return false;
		}

		// Scope gate.
		$show = true;
		switch ( $settings['scope'] ) {
			case 'front_page':
				$show = is_front_page();
				break;
			case 'posts':
				$show = is_singular( 'post' );
				break;
			case 'pages':
				$show = is_page();
				break;
			case 'sitewide':
			default:
				$show = true;
				break;
		}

		/**
		 * Filter the decision to render the field on this request.
		 *
		 * Lets developers scope the field beyond the built-in options
		 * (e.g. by template, taxonomy, or custom post type).
		 *
		 * @param bool  $show     Whether to render.
		 * @param array $settings The resolved settings.
		 */
		return (bool) apply_filters( 'aizledots_should_display', $show, $settings );
	}

	/**
	 * Enqueue assets, print the config, and queue the canvas — only when in scope.
	 */
	public function enqueue() {
		$settings = aizledots_get_settings();

		if ( ! $this->should_display( $settings ) ) {
			return;
		}

		$this->settings    = $settings;
		$this->layer_class = ( 'behind' === $settings['layer'] ) ? 'gd-behind' : 'gd-over';

		wp_enqueue_style( 'aizle-dots', AIZLEDOTS_URL . 'assets/css/aizle-dots.css', array(), AIZLEDOTS_VERSION );
		wp_enqueue_script( 'aizle-dots', AIZLEDOTS_URL . 'assets/js/aizle-dots.js', array(), AIZLEDOTS_VERSION, true );

		$config = $this->build_config( $settings );
		wp_add_inline_script(
			'aizle-dots',
			'window.AizleDots = ' . wp_json_encode( $config ) . ';',
			'before'
		);

		add_action( 'wp_footer', array( $this, 'render_canvas' ) );
	}

	/**
	 * Assemble the JS config contract (window.AizleDots) from the saved settings.
	 *
	 * Casts each value to the exact type the engine expects so wp_json_encode
	 * emits clean JS (ints as ints, floats as floats, bools as bools). Keys and
	 * types here are authoritative — see ASSETS-AND-DEFAULTS.md §2.
	 *
	 * @param array $settings Resolved settings.
	 * @return array
	 */
	private function build_config( $settings ) {
		$constants = aizledots_engine_constants();

		// Palette: manual swatches, unless "use theme colours" is on and the
		// active theme exposes a palette we can read.
		$palette = array_values( (array) $settings['palette'] );
		if ( ! empty( $settings['use_theme_colors'] ) ) {
			$theme = aizledots_get_theme_palette();
			if ( ! empty( $theme ) ) {
				$palette = $theme;
			}
		}

		return array(
			'palette'              => $palette,
			'opacity'              => (float) $settings['opacity'],
			'densityArea'          => (int) $settings['density_area'],
			'minParticles'         => (int) $constants['min_particles'],
			'maxParticles'         => (int) $constants['max_particles'],
			'cursorRadius'         => (int) $settings['cursor_radius'],
			'push'                 => (int) $settings['push'],
			'cursorMode'           => (string) $settings['cursor_mode'],
			'drift'                => (float) $settings['drift'],
			'sizeMin'              => (float) $settings['size_min'],
			'sizeMax'              => (float) $settings['size_max'],
			'shapeDot'             => (bool) $settings['shape_dot'],
			'shapeSquare'          => (bool) $settings['shape_square'],
			'shapeTriangle'        => (bool) $settings['shape_triangle'],
			'shapeLine'            => (bool) $settings['shape_line'],
			'linkLines'            => (bool) $settings['link_lines'],
			'linkDistance'         => (int) $settings['link_distance'],
			'rotateWithCursor'     => (bool) $settings['rotate_with_cursor'],
			'sleepMode'            => (bool) $settings['sleep_mode'],
			'sleepOpacity'         => (float) $settings['sleep_opacity'],
			'wakeMs'               => (int) $settings['wake_ms'],
			'scrollStrength'       => (int) $settings['scroll_strength'],
			'avoidContent'         => (bool) $settings['avoid_content'],
			'avoidStrength'        => (int) $settings['avoid_strength'],
			'avoidSelectors'       => (string) $constants['avoid_selectors'],
			'contentPadding'       => (int) $constants['content_padding'],
			'maxRects'             => (int) $constants['max_rects'],
			'disableOnMobile'      => (bool) $settings['disable_on_mobile'],
			'mobileParticles'      => (int) $constants['mobile_particles'],
			'mobileBreakpoint'     => (int) $constants['mobile_breakpoint'],
			'respectReducedMotion' => (bool) $settings['respect_reduced_motion'],
		);
	}

	/**
	 * Print the canvas element in the footer.
	 */
	public function render_canvas() {
		echo '<canvas id="aizle-dots" class="' . esc_attr( $this->layer_class ) . '" aria-hidden="true"></canvas>';
	}
}
