/* ============================================================
   Aizle Dots → admin settings page script
   ------------------------------------------------------------
   - Initialises wp-color-picker on each palette swatch.
   - Add / remove palette rows.
   - Live numeric readouts for the range sliders.
   - A contained live preview that approximates the current
     (unsaved) settings. This is a small, admin-only renderer;
     the real engine ships in assets/js/aizle-dots.js.

   The preview shows the LOOK (palette, opacity, density, size,
   shapes, cursor-facing rotation, cursor effect, sleep, and
   constellation links). Scroll reaction and content avoidance are
   page-level effects, so they only show on the live site.

   @package AizleDots
   ============================================================ */

( function ( $ ) {
	'use strict';

	var cfg = window.AizleDotsAdmin || {};

	/* ---------- Colour pickers ---------- */
	function initColorPicker( $field ) {
		$field.wpColorPicker( {
			change: function () {
				// Defer so the input value is updated before we re-read it.
				window.setTimeout( schedulePreviewRebuild, 30 );
			},
			clear: function () {
				window.setTimeout( schedulePreviewRebuild, 30 );
			}
		} );
	}

	function initAllColorPickers() {
		$( '.gd-palette .gd-color-field' ).each( function () {
			initColorPicker( $( this ) );
		} );
	}

	/* ---------- Palette add / remove ---------- */
	$( document ).on( 'click', '#gd-palette-add', function ( e ) {
		e.preventDefault();
		var tpl = $( '#gd-palette-row-template' ).html();
		var $row = $( tpl );
		// Give the cloned colour a fresh default so it's visibly new.
		$row.find( '.gd-color-field' ).val( cfg.defaultColor || '#0F4FFF' );
		$( '#gd-palette' ).append( $row );
		initColorPicker( $row.find( '.gd-color-field' ) );
		schedulePreviewRebuild();
	} );

	$( document ).on( 'click', '.gd-palette-remove', function ( e ) {
		e.preventDefault();
		var $rows = $( '#gd-palette .gd-palette-row' );
		// Keep at least one colour row so the palette is never empty.
		if ( $rows.length <= 1 ) {
			$( this ).closest( '.gd-palette-row' ).find( '.gd-color-field' ).val( '' ).trigger( 'change' );
			return;
		}
		$( this ).closest( '.gd-palette-row' ).remove();
		schedulePreviewRebuild();
	} );

	/* ---------- Range readouts ---------- */
	function updateReadout( el ) {
		var $el = $( el );
		var value = parseFloat( $el.val() );
		var mode = $el.data( 'readout' );
		var suffix = $el.data( 'suffix' ) || '';
		var $out = $( 'output[for="' + el.id + '"]' );
		var text;

		if ( mode === 'dots' ) {
			// Estimate the particle count on a reference 1920×1080 desktop.
			var dots = Math.round( ( 1920 * 1080 ) / value );
			dots = Math.max( 60, Math.min( 5000, dots ) );
			text = '~' + dots + ' dots';
		} else {
			text = value + ( suffix ? ' ' + suffix : '' );
		}
		$out.text( text );
	}

	function initRanges() {
		$( '.gd-range' ).each( function () {
			updateReadout( this );
		} );
	}

	$( document ).on( 'input change', '.gd-range', function () {
		updateReadout( this );
		schedulePreviewRebuild();
	} );

	// Any other control change should refresh the preview too.
	$( document ).on( 'change', '.gd-form input, .gd-form select', function () {
		schedulePreviewRebuild();
	} );

	/* ---------- Presets (one-click looks) ---------- */
	// Apply a preset bundle to the form controls. The user still clicks Save.
	function applyPreset( settings ) {
		Object.keys( settings ).forEach( function ( key ) {
			var v = settings[ key ];
			var $range = $( 'input.gd-range#gd-' + key );
			var $select = $( 'select#gd-' + key );
			var $check = $( 'input[type=checkbox][name$="[' + key + ']"]' );
			if ( $range.length ) { $range.val( v ); updateReadout( $range[ 0 ] ); }
			else if ( $select.length ) { $select.val( v ); }
			else if ( $check.length ) { $check.prop( 'checked', !!v ); }
		} );
		schedulePreviewRebuild();
	}

	$( document ).on( 'click', '.gd-preset', function ( e ) {
		e.preventDefault();
		var key = $( this ).data( 'preset' );
		var presets = cfg.presets || {};
		if ( presets[ key ] && presets[ key ].settings ) {
			applyPreset( presets[ key ].settings );
			$( '.gd-preset' ).removeClass( 'is-active' );
			$( this ).addClass( 'is-active' );
		}
	} );

	/* ============================================================
	   Live preview — a compact, contained approximation.
	   ============================================================ */
	var preview = ( function () {
		var canvas = document.getElementById( 'gd-preview' );
		if ( !canvas ) { return { rebuild: function () {} }; }

		var ctx = canvas.getContext( '2d' );
		var W = canvas.width, H = canvas.height;
		var particles = [];
		var mouse = { x: -9999, y: -9999, active: false, tMove: -9999 };
		var rafId = null;
		var pLast = 0;
		var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

		function readForm() {
			var palette = [];
			$( '#gd-palette .gd-color-field' ).each( function () {
				var v = ( $( this ).val() || '' ).trim();
				if ( /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test( v ) ) { palette.push( v ); }
			} );
			if ( !palette.length ) { palette = [ '#0F4FFF' ]; }
			// "Use theme colours" overrides the swatches in the preview too.
			if ( $( 'input[name$="[use_theme_colors]"]' ).is( ':checked' ) && cfg.themePalette && cfg.themePalette.length ) {
				palette = cfg.themePalette.slice();
			}

			var shapes = [];
			if ( $( 'input[name$="[shape_dot]"]' ).is( ':checked' ) ) { shapes.push( 'dot' ); }
			if ( $( 'input[name$="[shape_square]"]' ).is( ':checked' ) ) { shapes.push( 'sq' ); }
			if ( $( 'input[name$="[shape_triangle]"]' ).is( ':checked' ) ) { shapes.push( 'tri' ); }
			if ( $( 'input[name$="[shape_line]"]' ).is( ':checked' ) ) { shapes.push( 'line' ); }
			if ( !shapes.length ) { shapes.push( 'dot' ); }

			return {
				palette: palette,
				shapes: shapes,
				opacity: val( 'opacity', 0.5 ),
				densityArea: val( 'density_area', 7000 ),
				cursorRadius: val( 'cursor_radius', 320 ),
				push: val( 'push', 50 ),
				drift: val( 'drift', 4 ),
				sizeMin: val( 'size_min', 1.4 ),
				sizeMax: val( 'size_max', 4.6 ),
				rotate: $( 'input[name$="[rotate_with_cursor]"]' ).is( ':checked' ),
				sleep: $( 'input[name$="[sleep_mode]"]' ).is( ':checked' ),
				sleepOpacity: val( 'sleep_opacity', 0 ),
				wakeMs: val( 'wake_ms', 2000 ),
				cursorMode: $( '#gd-cursor_mode' ).val() || 'push',
				linkLines: $( 'input[name$="[link_lines]"]' ).is( ':checked' ),
				linkDistance: val( 'link_distance', 120 )
			};
		}

		function val( key, d ) {
			var v = parseFloat( $( '#gd-' + key ).val() );
			return isFinite( v ) ? v : d;
		}

		var C = readForm();

		function build() {
			C = readForm();
			// Scale the count down to the small preview, keep it lively but light.
			var n = Math.max( 12, Math.min( 90, Math.round( ( W * H ) / ( C.densityArea / 9 ) ) ) );
			particles = [];
			for ( var i = 0; i < n; i++ ) {
				var base = Math.random() * 6.2832;
				particles.push( {
					hx: Math.random() * W,
					hy: Math.random() * H,
					x: Math.random() * W,
					y: Math.random() * H,
					size: C.sizeMin + Math.random() * Math.max( 0, C.sizeMax - C.sizeMin ),
					shape: C.shapes[ ( Math.random() * C.shapes.length ) | 0 ],
					color: C.palette[ i % C.palette.length ],
					amp: C.drift * ( 0.6 + Math.random() * 0.8 ),
					seed: Math.random() * 6.2832,
					baseAngle: base,
					angle: base,
					wake: 0
				} );
			}
			if ( reduce ) { renderStatic(); }
		}

		function shape( p, x, y, s, a, angle ) {
			ctx.globalAlpha = a;
			ctx.fillStyle = p.color;
			if ( p.shape === 'dot' ) {
				ctx.beginPath();
				ctx.arc( x, y, s, 0, 6.2832 );
				ctx.fill();
				ctx.globalAlpha = 1;
				return;
			}
			ctx.save();
			ctx.translate( x, y );
			ctx.rotate( angle || 0 );
			if ( p.shape === 'sq' ) {
				ctx.fillRect( -s, -s, s * 2, s * 2 );
			} else if ( p.shape === 'tri' ) {
				ctx.beginPath();
				ctx.moveTo( 0, -s * 1.3 );
				ctx.lineTo( -s * 1.1, s );
				ctx.lineTo( s * 1.1, s );
				ctx.closePath();
				ctx.fill();
			} else if ( p.shape === 'line' ) {
				var len = s * 3.2, th = Math.max( 1, s * 0.55 );
				ctx.fillRect( -len / 2, -th / 2, len, th );
			}
			ctx.restore();
			ctx.globalAlpha = 1;
		}

		// Shortest-path angle interpolation.
		function lerpAngle( a, b, t ) {
			var d = b - a;
			while ( d > Math.PI ) { d -= 6.2832; }
			while ( d < -Math.PI ) { d += 6.2832; }
			return a + d * t;
		}

		function drawLinks() {
			var cs = C.linkDistance * 0.5; // scaled for the small preview box
			if ( cs < 6 ) { return; }
			var maxD2 = cs * cs;
			ctx.lineWidth = 1;
			for ( var i = 0; i < particles.length; i++ ) {
				var p = particles[ i ];
				for ( var j = i + 1; j < particles.length; j++ ) {
					var q = particles[ j ];
					var dx = p.rx - q.rx, dy = p.ry - q.ry, d2 = dx * dx + dy * dy;
					if ( d2 >= maxD2 ) { continue; }
					var a = ( 1 - Math.sqrt( d2 ) / cs ) * Math.min( p.ra, q.ra ) * 0.7;
					if ( a <= 0.01 ) { continue; }
					ctx.globalAlpha = a; ctx.strokeStyle = p.color;
					ctx.beginPath(); ctx.moveTo( p.rx, p.ry ); ctx.lineTo( q.rx, q.ry ); ctx.stroke();
				}
			}
			ctx.globalAlpha = 1;
		}

		function step( now ) {
			var t = ( now || 0 ) * 0.001;
			ctx.clearRect( 0, 0, W, H );
			var R = C.cursorRadius * 0.5, R2 = R * R; // scaled reach for the small box
			var moving = ( now - mouse.tMove ) < 120;
			var dt = pLast ? ( now - pLast ) : 16; if ( dt <= 0 || dt > 100 ) { dt = 16; } pLast = now;
			var wakeMul = Math.pow( 0.01, dt / Math.max( 60, C.wakeMs ) );
			for ( var i = 0; i < particles.length; i++ ) {
				var p = particles[ i ];
				var hx = p.hx + Math.sin( t * 0.55 + p.seed ) * p.amp;
				var hy = p.hy + Math.cos( t * 0.48 + p.seed * 1.7 ) * p.amp;
				p.x += ( hx - p.x ) * 0.06;
				p.y += ( hy - p.y ) * 0.06;
				var dx2 = p.x, dy2 = p.y, scale = 1, alpha = C.opacity, infl = 0, pushAngle = p.baseAngle;
				if ( mouse.active ) {
					var dx = p.x - mouse.x, dy = p.y - mouse.y;
					var d2 = dx * dx + dy * dy;
					if ( d2 < R2 ) {
						var d = Math.sqrt( d2 ) || 0.001;
						var f = ( R - d ) / R;
						var ux = dx / d, uy = dy / d, ox, oy;
						if ( C.cursorMode === 'pull' ) { ox = -ux; oy = -uy; }
						else if ( C.cursorMode === 'swirl' ) { ox = -uy; oy = ux; }
						else { ox = ux; oy = uy; }
						dx2 += ox * f * ( C.push * 0.5 );
						dy2 += oy * f * ( C.push * 0.5 );
						scale = 1 + f * 1.5;
						alpha = Math.min( 1, C.opacity + f * 0.35 );
						infl = f;
						pushAngle = Math.atan2( oy, ox );
					}
				}
				if ( C.sleep ) {
					if ( mouse.active && moving && infl > p.wake ) { p.wake = infl; }
					p.wake *= wakeMul;
					alpha = C.sleepOpacity + ( alpha - C.sleepOpacity ) * p.wake;
				}
				if ( C.rotate && p.shape !== 'dot' ) {
					p.angle = ( infl > 0 )
						? lerpAngle( p.angle, pushAngle, 0.15 + 0.5 * infl )
						: lerpAngle( p.angle, p.baseAngle, 0.04 );
				}
				p.rx = dx2; p.ry = dy2; p.ra = alpha;
				shape( p, dx2, dy2, p.size * scale, alpha, p.angle );
			}
			if ( C.linkLines ) { drawLinks(); }
			rafId = window.requestAnimationFrame( step );
		}

		function renderStatic() {
			ctx.clearRect( 0, 0, W, H );
			for ( var i = 0; i < particles.length; i++ ) {
				var p = particles[ i ];
				p.rx = p.hx; p.ry = p.hy; p.ra = C.opacity;
				shape( p, p.hx, p.hy, p.size, C.opacity, p.baseAngle );
			}
			if ( C.linkLines ) { drawLinks(); }
		}

		canvas.addEventListener( 'mousemove', function ( e ) {
			var r = canvas.getBoundingClientRect();
			mouse.x = ( e.clientX - r.left ) * ( W / r.width );
			mouse.y = ( e.clientY - r.top ) * ( H / r.height );
			mouse.active = true;
			mouse.tMove = ( window.performance && performance.now ) ? performance.now() : ( +new Date() );
		} );
		canvas.addEventListener( 'mouseleave', function () { mouse.active = false; } );

		build();
		if ( reduce ) { renderStatic(); } else { rafId = window.requestAnimationFrame( step ); }

		return { rebuild: build };
	} )();

	// Debounce preview rebuilds so dragging a slider stays smooth.
	var rebuildTimer = null;
	function schedulePreviewRebuild() {
		window.clearTimeout( rebuildTimer );
		rebuildTimer = window.setTimeout( function () {
			preview.rebuild();
		}, 120 );
	}

	/* ---------- Boot ---------- */
	$( function () {
		initAllColorPickers();
		initRanges();
	} );

} )( jQuery );
