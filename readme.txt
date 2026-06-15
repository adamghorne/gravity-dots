=== Aizle Dots ===
Contributors: adamhorne
Tags: particles, background, animation, canvas, interactive
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Colourful particles that drift across your site and gently part around the cursor. An interactive background with no code and no libraries.

== Description ==

**Aizle Dots** paints a living field of coloured particles behind or over your site. They drift slowly on their own, and part in a wide, soft wave around your visitor's cursor. A premium, modern touch in two clicks.

It's built to be the *good* kind of effect:

* **Dependency-free.** One small vanilla-JavaScript canvas. No jQuery, no animation libraries, no bloat.
* **Private by design.** No external requests, no tracking, no calls home. Your site stays your site.
* **Respectful.** Honours the visitor's "reduce motion" setting, pauses when the browser tab is hidden (saving battery and CPU), and never blocks clicks, links, or forms.
* **Tunable.** A friendly settings page for colours, opacity, density, cursor behaviour, shapes, and where it shows.
* **Light.** Particle count scales to the screen; you can disable it on mobile or place it behind your content for maximum readability.

Switch it on and it looks great immediately with sensible defaults, then make it yours.

**What you can control**

* Your own colour palette (add or remove colours).
* Opacity, density (more ↔ fewer dots), and particle size.
* Cursor reach and push strength, plus how much the field drifts on its own (down to perfectly still).
* Shapes: dots, squares, triangles, in any combination.
* Layer: *over* your content (subtle overlay) or *behind* it (safest for text).
* Where it appears: sitewide, the front page only, or by post type, with an exclude-by-ID list.
* Accessibility & performance: respect reduced motion, and an optional "disable on mobile".

Made by [Aizle](https://aizle.co). Free, and genuinely free. The goodwill is the point.

== Installation ==

1. In your dashboard, go to **Plugins → Add New** and search for "Aizle Dots", or upload the plugin zip via **Plugins → Add New → Upload Plugin**.
2. Click **Activate**. You'll see the particle field straight away.
3. Go to **Settings → Aizle Dots** to choose your colours and tune the look.

== Frequently Asked Questions ==

= Will it slow my site down? =
It's deliberately lightweight: one small canvas, no libraries, no external requests. The particle count scales to the screen size, the animation pauses when the tab isn't visible, and you can disable it on mobile. For very content-heavy pages, set it to "behind content".

= The dots sit over my menu or header. How do I fix that? =
Switch the **Layer** setting to **Behind content**. Some themes use high "stacking" values for sticky headers; placing the field behind your content resolves any overlap while keeping the effect.

= Is it accessible? =
Yes. The canvas is hidden from assistive technology and never intercepts clicks or keyboard focus. If a visitor has "reduce motion" enabled in their system and you've kept that setting on, the field renders as a calm static texture with no animation.

= Can I use my brand colours? =
Absolutely. The palette is fully editable on the settings page. Add as many or as few colours as you like; the field cycles through them.

= Can I show it only on certain pages? =
Yes. Choose sitewide, front-page-only, or by post type, and add any page/post IDs you want to exclude.

= Does it send any data anywhere? =
No. There are no external requests, no analytics, and no tracking of any kind.

== Screenshots ==

1. Aizle Dots over a live site — coloured particles that drift gently and part around the cursor.
2. The settings page: a live preview, one-click presets, and all the controls.

== Changelog ==

= 1.5.0 =
* New "Stay-awake time" control (in milliseconds) for how long disturbed particles stay lit before fading back to sleep.
* Larger, sticky live preview on the settings page.
* Settings sections are now clear bounding-box cards instead of collapsible panels.
* Added a "Reset to defaults" button.

= 1.4.0 =
* Presets: one-click "looks" (Calm, Confetti, Constellation, Minimal, Aizle).
* Redesigned settings page: live preview on top, a "Start here" block, and collapsible advanced sections — much friendlier.
* Cursor effect modes: push away, pull in, or swirl around the cursor.
* Connection lines ("constellation"): faint lines between nearby dots, with a distance control.
* Match-my-theme colours: pull the palette from your active theme.

= 1.3.0 =
* Much higher dot ceiling — dense fields of up to several thousand particles.
* Adaptive performance: the field auto-throttles its particle count on slower devices and small screens so it never janks a real visitor.

= 1.2.0 =
* New "Sleep until disturbed" mode: the field rests faint (or fully hidden) and only the particles the moving cursor reaches wake up, then fade back to sleep.
* New "Resting opacity" control for how visible the field is when idle.
* Refined content avoidance: removed an edge-pile-up artifact and stopped particles buzzing when the cursor rests over text.

= 1.1.0 =
* New "Lines" shape (short streaks) alongside dots, squares, and triangles.
* Shapes now rotate to face the cursor push, then settle back.
* Scroll reaction: the field is nudged as the visitor scrolls the page.
* Content avoidance: particles deflect around your text and images (desktop only, for performance).
* Wider ranges — Drift and Push up to 200, Cursor reach up to 800.

= 1.0.0 =
* Initial release: ambient + cursor-reactive particle field, full settings page, reduced-motion support, tab-hidden pause, layer (over/behind) and scope controls.

== Upgrade Notice ==

= 1.5.0 =
Adds a stay-awake time control and a larger, card-based settings layout.

= 1.4.0 =
Adds presets, a redesigned settings page, cursor effect modes, constellation lines, and theme-colour matching.

= 1.3.0 =
Raises the maximum dot count dramatically, with adaptive performance throttling.

= 1.2.0 =
Adds "Sleep until disturbed" mode and refines content avoidance.

= 1.1.0 =
Adds the Lines shape, cursor-facing rotation, scroll reaction, and content avoidance.

= 1.0.0 =
First release of Aizle Dots.
