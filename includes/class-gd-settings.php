<?php
/**
 * Admin: the whole settings surface via the WordPress Settings API.
 *
 * Registers one option (aizledots_settings), builds the page under
 * Settings → Aizle Dots, sanitises every field on the way in, and escapes
 * every value on the way out.
 *
 * @package AizleDots
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page + registration + sanitisation.
 */
class AizleDots_Settings {

	const OPTION_GROUP = 'aizledots';
	const OPTION_NAME  = 'aizledots_settings';
	const PAGE_SLUG    = 'aizle-dots';

	/**
	 * The settings page hook suffix (used to scope admin assets).
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
		add_action( 'admin_post_aizledots_reset', array( $this, 'handle_reset' ) );
	}

	/**
	 * Handle the "Reset to defaults" action (posted to admin-post.php).
	 */
	public function handle_reset() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'aizle-dots' ) );
		}
		check_admin_referer( 'aizledots_reset' );
		update_option( self::OPTION_NAME, aizledots_default_settings() );
		set_transient( 'aizledots_reset_' . get_current_user_id(), 1, 30 );
		wp_safe_redirect( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Add the options page under Settings.
	 */
	public function add_menu() {
		$this->hook_suffix = add_options_page(
			__( 'Aizle Dots', 'aizle-dots' ),
			__( 'Aizle Dots', 'aizle-dots' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the setting, its sections, and its fields.
	 */
	public function register() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => aizledots_default_settings(),
			)
		);

		// --- Section: Where it appears (enable lives in the "Start" block) ---
		add_settings_section( 'aizledots_display', __( 'Where it appears', 'aizle-dots' ), array( $this, 'section_display' ), self::PAGE_SLUG );
		$this->add_field( 'aizledots_display', 'scope', __( 'Where it shows', 'aizle-dots' ), 'field_scope' );
		$this->add_field( 'aizledots_display', 'exclude_ids', __( 'Exclude post/page IDs', 'aizle-dots' ), 'field_exclude_ids' );
		$this->add_field( 'aizledots_display', 'layer', __( 'Layer', 'aizle-dots' ), 'field_layer' );

		// --- Section: Look ---
		add_settings_section( 'aizledots_look', __( 'Look', 'aizle-dots' ), array( $this, 'section_look' ), self::PAGE_SLUG );
		$this->add_field( 'aizledots_look', 'palette', __( 'Colour palette', 'aizle-dots' ), 'field_palette' );
		$this->add_field( 'aizledots_look', 'use_theme_colors', __( 'Use theme colours', 'aizle-dots' ), 'field_use_theme_colors' );
		$this->add_field( 'aizledots_look', 'opacity', __( 'Opacity', 'aizle-dots' ), 'field_opacity' );
		$this->add_field( 'aizledots_look', 'density_area', __( 'Density', 'aizle-dots' ), 'field_density' );
		$this->add_field( 'aizledots_look', 'size', __( 'Particle size', 'aizle-dots' ), 'field_size' );
		$this->add_field( 'aizledots_look', 'shapes', __( 'Shapes', 'aizle-dots' ), 'field_shapes' );
		$this->add_field( 'aizledots_look', 'link_lines', __( 'Connect nearby dots', 'aizle-dots' ), 'field_link_lines' );
		$this->add_field( 'aizledots_look', 'link_distance', __( 'Connection distance', 'aizle-dots' ), 'field_link_distance' );

		// --- Section: Motion & cursor ---
		add_settings_section( 'aizledots_motion', __( 'Motion &amp; cursor', 'aizle-dots' ), array( $this, 'section_motion' ), self::PAGE_SLUG );
		$this->add_field( 'aizledots_motion', 'drift', __( 'Drift', 'aizle-dots' ), 'field_drift' );
		$this->add_field( 'aizledots_motion', 'cursor_radius', __( 'Cursor reach', 'aizle-dots' ), 'field_cursor_radius' );
		$this->add_field( 'aizledots_motion', 'push', __( 'Push strength', 'aizle-dots' ), 'field_push' );
		$this->add_field( 'aizledots_motion', 'cursor_mode', __( 'Cursor effect', 'aizle-dots' ), 'field_cursor_mode' );
		$this->add_field( 'aizledots_motion', 'rotate_with_cursor', __( 'Rotate with cursor', 'aizle-dots' ), 'field_rotate_with_cursor' );
		$this->add_field( 'aizledots_motion', 'sleep_mode', __( 'Sleep until disturbed', 'aizle-dots' ), 'field_sleep_mode' );
		$this->add_field( 'aizledots_motion', 'sleep_opacity', __( 'Resting opacity', 'aizle-dots' ), 'field_sleep_opacity' );
		$this->add_field( 'aizledots_motion', 'wake_ms', __( 'Stay-awake time', 'aizle-dots' ), 'field_wake_ms' );
		$this->add_field( 'aizledots_motion', 'scroll_strength', __( 'Scroll reaction', 'aizle-dots' ), 'field_scroll_strength' );

		// --- Section: Content interaction ---
		add_settings_section( 'aizledots_content', __( 'Content interaction', 'aizle-dots' ), array( $this, 'section_content' ), self::PAGE_SLUG );
		$this->add_field( 'aizledots_content', 'avoid_content', __( 'Avoid page content', 'aizle-dots' ), 'field_avoid_content' );
		$this->add_field( 'aizledots_content', 'avoid_strength', __( 'Avoidance strength', 'aizle-dots' ), 'field_avoid_strength' );

		// --- Section: Behaviour ---
		add_settings_section( 'aizledots_behaviour', __( 'Behaviour', 'aizle-dots' ), array( $this, 'section_behaviour' ), self::PAGE_SLUG );
		$this->add_field( 'aizledots_behaviour', 'respect_reduced_motion', __( 'Respect reduced motion', 'aizle-dots' ), 'field_respect_reduced_motion' );
		$this->add_field( 'aizledots_behaviour', 'disable_on_mobile', __( 'Disable on mobile', 'aizle-dots' ), 'field_disable_on_mobile' );
	}

	/**
	 * Helper to register a field whose callback is a method on this class.
	 *
	 * @param string $section  Section id.
	 * @param string $id       Field id (used for the label `for` and the callback method).
	 * @param string $label    Field label.
	 * @param string $callback Method name on this class.
	 */
	private function add_field( $section, $id, $label, $callback ) {
		add_settings_field(
			$id,
			$label,
			array( $this, $callback ),
			self::PAGE_SLUG,
			$section,
			array( 'label_for' => 'gd-' . $id )
		);
	}

	/* ---------------------------------------------------------------------
	 * Section intros
	 * ------------------------------------------------------------------- */

	public function section_display() {
		echo '<p>' . esc_html__( 'Choose which pages show the field, and whether it sits over or behind your content.', 'aizle-dots' ) . '</p>';
	}

	public function section_look() {
		echo '<p>' . esc_html__( 'Colour, opacity, density, size, and shapes. Sensible defaults are already set.', 'aizle-dots' ) . '</p>';
	}

	public function section_motion() {
		echo '<p>' . esc_html__( 'How much the field drifts on its own, how it reacts to the cursor, and how it responds to scrolling.', 'aizle-dots' ) . '</p>';
	}

	public function section_content() {
		echo '<p>' . esc_html__( 'Let particles deflect around your page content. This is the heaviest effect, so it is automatically switched off on small screens.', 'aizle-dots' ) . '</p>';
	}

	public function section_behaviour() {
		echo '<p>' . esc_html__( 'Accessibility and performance options.', 'aizle-dots' ) . '</p>';
	}

	/* ---------------------------------------------------------------------
	 * Field renderers
	 * ------------------------------------------------------------------- */

	/**
	 * Current settings, resolved with defaults. Cached per request.
	 *
	 * @return array
	 */
	private function values() {
		return aizledots_get_settings();
	}

	/**
	 * Build the name attribute for a field within the option array.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private function name( $key ) {
		return self::OPTION_NAME . '[' . $key . ']';
	}

	public function field_enabled() {
		$v = $this->values();
		printf(
			'<label><input type="checkbox" id="gd-enabled" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( $this->name( 'enabled' ) ),
			checked( ! empty( $v['enabled'] ), true, false ),
			esc_html__( 'Show the particle field on the front end.', 'aizle-dots' )
		);
	}

	public function field_scope() {
		$v       = $this->values();
		$options = array(
			'sitewide'   => __( 'Sitewide (everywhere)', 'aizle-dots' ),
			'front_page' => __( 'Front page only', 'aizle-dots' ),
			'posts'      => __( 'Single posts only', 'aizle-dots' ),
			'pages'      => __( 'Pages only', 'aizle-dots' ),
		);
		echo '<select id="gd-scope" name="' . esc_attr( $this->name( 'scope' ) ) . '">';
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $v['scope'], $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	public function field_exclude_ids() {
		$v   = $this->values();
		$ids = implode( ', ', array_map( 'absint', (array) $v['exclude_ids'] ) );
		printf(
			'<input type="text" id="gd-exclude_ids" class="regular-text" name="%1$s" value="%2$s" placeholder="%3$s" />',
			esc_attr( $this->name( 'exclude_ids' ) ),
			esc_attr( $ids ),
			esc_attr__( 'e.g. 12, 84, 230', 'aizle-dots' )
		);
		echo '<p class="description">' . esc_html__( 'Comma-separated post or page IDs to hide the field on.', 'aizle-dots' ) . '</p>';
	}

	public function field_layer() {
		$v       = $this->values();
		$options = array(
			'over'   => __( 'Over content (subtle overlay)', 'aizle-dots' ),
			'behind' => __( 'Behind content (safest for text)', 'aizle-dots' ),
		);
		echo '<fieldset>';
		foreach ( $options as $value => $label ) {
			printf(
				'<label style="display:block;margin-bottom:4px"><input type="radio" name="%1$s" value="%2$s" %3$s /> %4$s</label>',
				esc_attr( $this->name( 'layer' ) ),
				esc_attr( $value ),
				checked( $v['layer'], $value, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'If the dots overlap a sticky header or menu, switch to "Behind content".', 'aizle-dots' ) . '</p>';
	}

	public function field_palette() {
		$v       = $this->values();
		$palette = array_values( (array) $v['palette'] );
		if ( empty( $palette ) ) {
			$defaults = aizledots_default_settings();
			$palette  = $defaults['palette'];
		}
		echo '<div id="gd-palette" class="gd-palette">';
		foreach ( $palette as $hex ) {
			$this->palette_row( $hex );
		}
		echo '</div>';
		printf(
			'<p><button type="button" class="button" id="gd-palette-add">%s</button></p>',
			esc_html__( '+ Add colour', 'aizle-dots' )
		);
		echo '<p class="description">' . esc_html__( 'The field cycles through these colours, one per particle. Add or remove as many as you like.', 'aizle-dots' ) . '</p>';

		// A template row for admin.js to clone when "Add colour" is clicked.
		echo '<script type="text/html" id="gd-palette-row-template">';
		$this->palette_row( '#0F4FFF' );
		echo '</script>';
	}

	/**
	 * Render a single palette row (colour input + remove button).
	 *
	 * @param string $hex Hex colour.
	 */
	private function palette_row( $hex ) {
		printf(
			'<div class="gd-palette-row"><input type="text" class="gd-color-field" name="%1$s[]" value="%2$s" /> <button type="button" class="button-link gd-palette-remove" aria-label="%3$s">%4$s</button></div>',
			esc_attr( $this->name( 'palette' ) ),
			esc_attr( $hex ),
			esc_attr__( 'Remove this colour', 'aizle-dots' ),
			esc_html__( 'Remove', 'aizle-dots' )
		);
	}

	/**
	 * Render a range slider with a live numeric readout.
	 *
	 * @param string $key    Setting key.
	 * @param array  $args   min, max, step, value, suffix, readout (data attr).
	 */
	private function range( $key, $args ) {
		$defaults = array(
			'min'     => 0,
			'max'     => 100,
			'step'    => 1,
			'value'   => 0,
			'suffix'  => '',
			'readout' => 'value',
		);
		$args = array_merge( $defaults, $args );
		printf(
			'<input type="range" id="gd-%1$s" class="gd-range" name="%2$s" min="%3$s" max="%4$s" step="%5$s" value="%6$s" data-readout="%7$s" data-suffix="%8$s" /> <output class="gd-range-output" for="gd-%1$s"></output>',
			esc_attr( $key ),
			esc_attr( $this->name( $key ) ),
			esc_attr( $args['min'] ),
			esc_attr( $args['max'] ),
			esc_attr( $args['step'] ),
			esc_attr( $args['value'] ),
			esc_attr( $args['readout'] ),
			esc_attr( $args['suffix'] )
		);
	}

	public function field_use_theme_colors() {
		$v     = $this->values();
		$theme = aizledots_get_theme_palette();
		printf(
			'<label><input type="checkbox" id="gd-use_theme_colors" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( $this->name( 'use_theme_colors' ) ),
			checked( ! empty( $v['use_theme_colors'] ), true, false ),
			esc_html__( 'Pull colours from your active theme instead of the swatches above.', 'aizle-dots' )
		);
		if ( empty( $theme ) ) {
			echo '<p class="description">' . esc_html__( 'Your active theme does not expose a colour palette, so the swatches above will be used.', 'aizle-dots' ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html( sprintf( /* translators: %d: number of colours. */ _n( 'Found %d theme colour.', 'Found %d theme colours.', count( $theme ), 'aizle-dots' ), count( $theme ) ) ) . '</p>';
		}
	}

	public function field_opacity() {
		$v = $this->values();
		$this->range( 'opacity', array( 'min' => 0.05, 'max' => 1, 'step' => 0.05, 'value' => $v['opacity'], 'readout' => 'value' ) );
		echo '<p class="description">' . esc_html__( 'How visible the whole field is. Lower keeps text crisp in "over" mode.', 'aizle-dots' ) . '</p>';
	}

	public function field_density() {
		$v = $this->values();
		// Stored value is density_area (screen px² per particle): LOWER = MORE dots.
		// Slider therefore reads left = More, right = Fewer (see label below).
		$this->range( 'density_area', array( 'min' => 400, 'max' => 20000, 'step' => 100, 'value' => $v['density_area'], 'readout' => 'dots' ) );
		echo '<p class="description">' . esc_html__( 'Left = more dots (up to several thousand), right = fewer. The readout estimates the count on a typical desktop screen; the field auto-throttles on slower devices.', 'aizle-dots' ) . '</p>';
	}

	public function field_size() {
		$v = $this->values();
		echo '<p style="margin:0 0 6px"><label>' . esc_html__( 'Smallest', 'aizle-dots' ) . ' ';
		$this->range( 'size_min', array( 'min' => 0.5, 'max' => 6, 'step' => 0.1, 'value' => $v['size_min'], 'suffix' => 'px' ) );
		echo '</label></p>';
		echo '<p style="margin:0"><label>' . esc_html__( 'Largest', 'aizle-dots' ) . ' ';
		$this->range( 'size_max', array( 'min' => 0.5, 'max' => 10, 'step' => 0.1, 'value' => $v['size_max'], 'suffix' => 'px' ) );
		echo '</label></p>';
	}

	public function field_shapes() {
		$v      = $this->values();
		$shapes = array(
			'shape_dot'      => __( 'Dots', 'aizle-dots' ),
			'shape_square'   => __( 'Squares', 'aizle-dots' ),
			'shape_triangle' => __( 'Triangles', 'aizle-dots' ),
			'shape_line'     => __( 'Lines', 'aizle-dots' ),
		);
		echo '<fieldset>';
		foreach ( $shapes as $key => $label ) {
			printf(
				'<label style="margin-right:14px"><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
				esc_attr( $this->name( $key ) ),
				checked( ! empty( $v[ $key ] ), true, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'Pick any combination. If none are ticked, dots are used.', 'aizle-dots' ) . '</p>';
	}

	public function field_link_lines() {
		$v = $this->values();
		printf(
			'<label><input type="checkbox" id="gd-link_lines" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( $this->name( 'link_lines' ) ),
			checked( ! empty( $v['link_lines'] ), true, false ),
			esc_html__( 'Draw faint lines between dots that are close together (a "constellation" web).', 'aizle-dots' )
		);
		echo '<p class="description">' . esc_html__( 'At very high dot counts the field automatically thins itself to stay smooth.', 'aizle-dots' ) . '</p>';
	}

	public function field_link_distance() {
		$v = $this->values();
		$this->range( 'link_distance', array( 'min' => 20, 'max' => 300, 'step' => 5, 'value' => $v['link_distance'], 'suffix' => 'px' ) );
		echo '<p class="description">' . esc_html__( 'How close two dots must be before a line connects them.', 'aizle-dots' ) . '</p>';
	}

	public function field_drift() {
		$v = $this->values();
		$this->range( 'drift', array( 'min' => 0, 'max' => 200, 'step' => 2, 'value' => $v['drift'], 'suffix' => 'px' ) );
		echo '<p class="description">' . esc_html__( 'How far particles wander on their own. Set to 0 for a perfectly still field; high values make a restless swarm.', 'aizle-dots' ) . '</p>';
	}

	public function field_cursor_radius() {
		$v = $this->values();
		$this->range( 'cursor_radius', array( 'min' => 0, 'max' => 800, 'step' => 10, 'value' => $v['cursor_radius'], 'suffix' => 'px' ) );
		echo '<p class="description">' . esc_html__( 'How wide the area is that reacts to the cursor.', 'aizle-dots' ) . '</p>';
	}

	public function field_push() {
		$v = $this->values();
		$this->range( 'push', array( 'min' => 0, 'max' => 200, 'step' => 5, 'value' => $v['push'], 'suffix' => 'px' ) );
		echo '<p class="description">' . esc_html__( 'How far particles shove away from the cursor.', 'aizle-dots' ) . '</p>';
	}

	public function field_cursor_mode() {
		$v       = $this->values();
		$options = array(
			'push'  => __( 'Push away', 'aizle-dots' ),
			'pull'  => __( 'Pull in', 'aizle-dots' ),
			'swirl' => __( 'Swirl around', 'aizle-dots' ),
		);
		echo '<select id="gd-cursor_mode" name="' . esc_attr( $this->name( 'cursor_mode' ) ) . '">';
		foreach ( $options as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $v['cursor_mode'], $value, false ), esc_html( $label ) );
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'How particles react to the cursor: push away, get pulled in, or orbit around it.', 'aizle-dots' ) . '</p>';
	}

	public function field_rotate_with_cursor() {
		$v = $this->values();
		printf(
			'<label><input type="checkbox" id="gd-rotate_with_cursor" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( $this->name( 'rotate_with_cursor' ) ),
			checked( ! empty( $v['rotate_with_cursor'] ), true, false ),
			esc_html__( 'Squares, triangles, and lines rotate to face the cursor as it pushes them, then settle back. (Dots are round, so rotation does not show on them.)', 'aizle-dots' )
		);
	}

	public function field_sleep_mode() {
		$v = $this->values();
		printf(
			'<label><input type="checkbox" id="gd-sleep_mode" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( $this->name( 'sleep_mode' ) ),
			checked( ! empty( $v['sleep_mode'] ), true, false ),
			esc_html__( 'The field rests faint (or hidden) and only wakes where the moving cursor disturbs it, then fades back to sleep.', 'aizle-dots' )
		);
	}

	public function field_sleep_opacity() {
		$v = $this->values();
		$this->range( 'sleep_opacity', array( 'min' => 0, 'max' => 1, 'step' => 0.05, 'value' => $v['sleep_opacity'], 'readout' => 'value' ) );
		echo '<p class="description">' . esc_html__( 'How visible particles are at rest (only applies when "Sleep until disturbed" is on). 0 = fully hidden until the cursor wakes them.', 'aizle-dots' ) . '</p>';
	}

	public function field_wake_ms() {
		$v = $this->values();
		$this->range( 'wake_ms', array( 'min' => 100, 'max' => 5000, 'step' => 100, 'value' => $v['wake_ms'], 'suffix' => 'ms' ) );
		echo '<p class="description">' . esc_html__( 'How long particles stay lit after the cursor wakes them, before fading back to sleep (only applies when "Sleep until disturbed" is on).', 'aizle-dots' ) . '</p>';
	}

	public function field_scroll_strength() {
		$v = $this->values();
		$this->range( 'scroll_strength', array( 'min' => 0, 'max' => 200, 'step' => 5, 'value' => $v['scroll_strength'], 'readout' => 'value' ) );
		echo '<p class="description">' . esc_html__( 'How strongly the field is nudged when the visitor scrolls. 0 = ignore scrolling.', 'aizle-dots' ) . '</p>';
	}

	public function field_avoid_content() {
		$v = $this->values();
		printf(
			'<label><input type="checkbox" id="gd-avoid_content" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( $this->name( 'avoid_content' ) ),
			checked( ! empty( $v['avoid_content'] ), true, false ),
			esc_html__( 'Particles deflect around your text and images instead of drifting over them.', 'aizle-dots' )
		);
		echo '<p class="description">' . esc_html__( 'Automatically disabled on small screens for performance.', 'aizle-dots' ) . '</p>';
	}

	public function field_avoid_strength() {
		$v = $this->values();
		$this->range( 'avoid_strength', array( 'min' => 0, 'max' => 200, 'step' => 5, 'value' => $v['avoid_strength'], 'readout' => 'value' ) );
		echo '<p class="description">' . esc_html__( 'How firmly particles are pushed out of content. Higher keeps them further clear of text and images.', 'aizle-dots' ) . '</p>';
	}

	public function field_respect_reduced_motion() {
		$v = $this->values();
		printf(
			'<label><input type="checkbox" id="gd-respect_reduced_motion" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( $this->name( 'respect_reduced_motion' ) ),
			checked( ! empty( $v['respect_reduced_motion'] ), true, false ),
			esc_html__( 'When a visitor has "reduce motion" enabled, show a calm static field with no animation.', 'aizle-dots' )
		);
	}

	public function field_disable_on_mobile() {
		$v = $this->values();
		printf(
			'<label><input type="checkbox" id="gd-disable_on_mobile" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( $this->name( 'disable_on_mobile' ) ),
			checked( ! empty( $v['disable_on_mobile'] ), true, false ),
			esc_html__( 'Hide the field on small screens (640px and below).', 'aizle-dots' )
		);
	}

	/* ---------------------------------------------------------------------
	 * Sanitisation — validate every field on the way IN. Never trust POST.
	 * ------------------------------------------------------------------- */

	/**
	 * Sanitise the whole settings array. Returns a clean array shaped exactly
	 * like the defaults.
	 *
	 * @param mixed $input Raw POSTed value.
	 * @return array
	 */
	public function sanitize( $input ) {
		$defaults = aizledots_default_settings();
		$input    = is_array( $input ) ? $input : array();
		$out      = array();

		// Booleans (unchecked checkboxes don't POST → absence means false).
		$out['enabled']                = ! empty( $input['enabled'] );
		$out['shape_dot']              = ! empty( $input['shape_dot'] );
		$out['shape_square']           = ! empty( $input['shape_square'] );
		$out['shape_triangle']         = ! empty( $input['shape_triangle'] );
		$out['shape_line']             = ! empty( $input['shape_line'] );
		$out['link_lines']             = ! empty( $input['link_lines'] );
		$out['use_theme_colors']       = ! empty( $input['use_theme_colors'] );
		$out['rotate_with_cursor']     = ! empty( $input['rotate_with_cursor'] );
		$out['sleep_mode']             = ! empty( $input['sleep_mode'] );
		$out['avoid_content']          = ! empty( $input['avoid_content'] );
		$out['disable_on_mobile']      = ! empty( $input['disable_on_mobile'] );
		$out['respect_reduced_motion'] = ! empty( $input['respect_reduced_motion'] );

		// Enums (whitelist; fall back to default if unrecognised).
		$scopes        = array( 'sitewide', 'front_page', 'posts', 'pages' );
		$out['scope']  = ( isset( $input['scope'] ) && in_array( $input['scope'], $scopes, true ) ) ? $input['scope'] : $defaults['scope'];
		$layers        = array( 'over', 'behind' );
		$out['layer']  = ( isset( $input['layer'] ) && in_array( $input['layer'], $layers, true ) ) ? $input['layer'] : $defaults['layer'];
		$modes              = array( 'push', 'pull', 'swirl' );
		$out['cursor_mode'] = ( isset( $input['cursor_mode'] ) && in_array( $input['cursor_mode'], $modes, true ) ) ? $input['cursor_mode'] : $defaults['cursor_mode'];

		// Exclude IDs (text field → split, absint each, drop zeros, unique).
		$out['exclude_ids'] = array();
		if ( ! empty( $input['exclude_ids'] ) ) {
			$raw = is_array( $input['exclude_ids'] ) ? $input['exclude_ids'] : preg_split( '/[\s,]+/', (string) $input['exclude_ids'] );
			foreach ( (array) $raw as $maybe_id ) {
				$maybe_id = trim( (string) $maybe_id );
				// Accept only genuine positive integers. Drops negatives, words, and
				// markup outright rather than letting absint() coerce e.g. "-4" to 4.
				if ( '' === $maybe_id || ! ctype_digit( $maybe_id ) ) {
					continue;
				}
				$id = absint( $maybe_id );
				if ( $id > 0 ) {
					$out['exclude_ids'][] = $id;
				}
			}
			$out['exclude_ids'] = array_values( array_unique( $out['exclude_ids'] ) );
		}

		// Palette (array → sanitize_hex_color each; drop invalid; fall back if empty).
		$out['palette'] = array();
		if ( ! empty( $input['palette'] ) && is_array( $input['palette'] ) ) {
			foreach ( $input['palette'] as $maybe_hex ) {
				$hex = sanitize_hex_color( trim( (string) $maybe_hex ) );
				if ( $hex ) {
					$out['palette'][] = $hex;
				}
			}
		}
		if ( empty( $out['palette'] ) ) {
			$out['palette'] = $defaults['palette'];
		}
		$out['palette'] = array_values( $out['palette'] );

		// Integers (absint + clamp to range).
		$out['density_area']    = $this->clamp_int( isset( $input['density_area'] ) ? $input['density_area'] : $defaults['density_area'], 400, 20000 );
		$out['cursor_radius']   = $this->clamp_int( isset( $input['cursor_radius'] ) ? $input['cursor_radius'] : $defaults['cursor_radius'], 0, 800 );
		$out['link_distance']   = $this->clamp_int( isset( $input['link_distance'] ) ? $input['link_distance'] : $defaults['link_distance'], 20, 300 );
		$out['push']            = $this->clamp_int( isset( $input['push'] ) ? $input['push'] : $defaults['push'], 0, 200 );
		$out['scroll_strength'] = $this->clamp_int( isset( $input['scroll_strength'] ) ? $input['scroll_strength'] : $defaults['scroll_strength'], 0, 200 );
		$out['wake_ms']         = $this->clamp_int( isset( $input['wake_ms'] ) ? $input['wake_ms'] : $defaults['wake_ms'], 100, 5000 );
		$out['avoid_strength']  = $this->clamp_int( isset( $input['avoid_strength'] ) ? $input['avoid_strength'] : $defaults['avoid_strength'], 0, 200 );

		// Floats (floatval + clamp to range).
		$out['opacity']       = $this->clamp_float( isset( $input['opacity'] ) ? $input['opacity'] : $defaults['opacity'], 0.05, 1.0 );
		$out['sleep_opacity'] = $this->clamp_float( isset( $input['sleep_opacity'] ) ? $input['sleep_opacity'] : $defaults['sleep_opacity'], 0.0, 1.0 );
		$out['drift']    = $this->clamp_float( isset( $input['drift'] ) ? $input['drift'] : $defaults['drift'], 0.0, 200.0 );
		$out['size_min'] = $this->clamp_float( isset( $input['size_min'] ) ? $input['size_min'] : $defaults['size_min'], 0.5, 6.0 );
		$out['size_max'] = $this->clamp_float( isset( $input['size_max'] ) ? $input['size_max'] : $defaults['size_max'], 0.5, 10.0 );

		// Keep the size range sane: largest must be at least the smallest.
		if ( $out['size_max'] < $out['size_min'] ) {
			$out['size_max'] = $out['size_min'];
		}

		return $out;
	}

	/**
	 * Clamp a value to an integer within [min, max].
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min   Minimum.
	 * @param int   $max   Maximum.
	 * @return int
	 */
	private function clamp_int( $value, $min, $max ) {
		$n = absint( $value );
		return (int) max( $min, min( $max, $n ) );
	}

	/**
	 * Clamp a value to a float within [min, max].
	 *
	 * @param mixed $value Raw value.
	 * @param float $min   Minimum.
	 * @param float $max   Maximum.
	 * @return float
	 */
	private function clamp_float( $value, $min, $max ) {
		$n = (float) $value;
		return (float) max( $min, min( $max, $n ) );
	}

	/* ---------------------------------------------------------------------
	 * Admin assets + page render
	 * ------------------------------------------------------------------- */

	/**
	 * Enqueue admin assets — only on this settings page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin( $hook ) {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'aizle-dots-admin', AIZLEDOTS_URL . 'assets/admin/admin.css', array(), AIZLEDOTS_VERSION );

		wp_enqueue_script(
			'aizle-dots-admin',
			AIZLEDOTS_URL . 'assets/admin/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			AIZLEDOTS_VERSION,
			true
		);

		wp_localize_script(
			'aizle-dots-admin',
			'AizleDotsAdmin',
			array(
				'defaultColor' => '#0F4FFF',
				'themePalette' => aizledots_get_theme_palette(),
				'presets'      => aizledots_presets(),
				'i18n'         => array(
					'remove' => __( 'Remove', 'aizle-dots' ),
				),
			)
		);
	}

	/**
	 * Render the fields of one registered section inside a form table.
	 *
	 * @param string $section Section id.
	 */
	private function render_section_fields( $section ) {
		echo '<table class="form-table" role="presentation">';
		do_settings_fields( self::PAGE_SLUG, $section );
		echo '</table>';
	}

	/**
	 * Render one settings section as a bounding-box card with a heading.
	 *
	 * @param string $title   Section heading.
	 * @param string $section Section id.
	 */
	private function render_card_section( $title, $section ) {
		echo '<div class="gd-card gd-section-card">';
		echo '<h2>' . esc_html( $title ) . '</h2>';
		$this->render_section_fields( $section );
		echo '</div>';
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap gd-settings-wrap">
			<h1><?php echo esc_html__( 'Aizle Dots', 'aizle-dots' ); ?></h1>

			<?php
			$reset_key = 'aizledots_reset_' . get_current_user_id();
			if ( get_transient( $reset_key ) ) {
				delete_transient( $reset_key );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings reset to defaults.', 'aizle-dots' ) . '</p></div>';
			}
			?>

			<div class="gd-preview-pane">
				<canvas id="gd-preview" width="600" height="300" aria-hidden="true"></canvas>
				<p class="description"><?php echo esc_html__( 'Live preview of the current (unsaved) settings — move your cursor over it.', 'aizle-dots' ); ?></p>
			</div>

			<form action="options.php" method="post" class="gd-form">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<div class="gd-card gd-start">
					<h2><?php echo esc_html__( 'Start here', 'aizle-dots' ); ?></h2>
					<p class="gd-enable"><?php $this->field_enabled(); ?></p>
					<p class="gd-presets-label"><strong><?php echo esc_html__( 'Quick looks', 'aizle-dots' ); ?></strong> — <?php echo esc_html__( 'a one-click starting point (your colours are kept). Tweak anything below, then Save.', 'aizle-dots' ); ?></p>
					<div class="gd-presets">
						<?php
						foreach ( aizledots_presets() as $key => $preset ) {
							printf(
								'<button type="button" class="button gd-preset" data-preset="%1$s">%2$s</button>',
								esc_attr( $key ),
								esc_html( $preset['label'] )
							);
						}
						?>
					</div>
				</div>

				<?php
				$this->render_card_section( __( 'Look', 'aizle-dots' ), 'aizledots_look' );
				$this->render_card_section( __( 'Movement & cursor', 'aizle-dots' ), 'aizledots_motion' );
				$this->render_card_section( __( 'Content interaction', 'aizle-dots' ), 'aizledots_content' );
				$this->render_card_section( __( 'Where it appears', 'aizle-dots' ), 'aizledots_display' );
				$this->render_card_section( __( 'Performance & accessibility', 'aizle-dots' ), 'aizledots_behaviour' );
				submit_button();
				?>
			</form>

			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="gd-reset-form" onsubmit="return confirm( '<?php echo esc_js( __( 'Reset all Aizle Dots settings to their defaults?', 'aizle-dots' ) ); ?>' );">
					<input type="hidden" name="action" value="aizledots_reset" />
					<?php wp_nonce_field( 'aizledots_reset' ); ?>
					<?php submit_button( __( 'Reset to defaults', 'aizle-dots' ), 'secondary', 'aizledots_reset_submit', false ); ?>
				</form>

				<p class="gd-credit">
				<?php
				printf(
					/* translators: %s: link to Aizle. */
					wp_kses( __( 'Made by <a href="%s" target="_blank" rel="noopener">Aizle</a> &rarr;', 'aizle-dots' ), array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ),
					esc_url( 'https://aizle.co' )
				);
				?>
			</p>
		</div>
		<?php
	}
}
