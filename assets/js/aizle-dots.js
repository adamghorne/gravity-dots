/* ============================================================
   Aizle Dots → particle engine
   ------------------------------------------------------------
   Dependency-free vanilla JS + Canvas 2D. No framework, no build
   step, no external requests. Originates from bao2.aizle.co.

   CONFIG-DRIVEN: reads `window.AizleDots`, a JSON object the
   plugin prints (via wp_add_inline_script) immediately before this
   script. Every visual is a setting. The defaults below MUST match
   includes/defaults.php so the engine still runs if the config is
   ever missing.

   Behaviour: a single ambient + cursor-reactive field with —
     • four shapes (dot / square / triangle / line);
     • cursor-facing rotation (non-round shapes swing to align with
       the push vector, then settle back);
     • a velocity channel layered over the spring-to-home drift, so
       impulses (scroll kicks, content bumps) feel physical and decay;
     • a scroll "velocity kick" that shoves the field as the page moves;
     • content avoidance — particles deflect around on-page text & media,
       using element rects cached in document space (layout is read only
       on load/resize, never per frame) and re-projected to the viewport.

   The loop pauses on `visibilitychange` when the tab is hidden, and
   short-circuits entirely under prefers-reduced-motion (when the
   setting respects it) → one static frame, no physics, no listeners.

   Each config key is commented with the SETTING it maps to.

   @package AizleDots
   ============================================================ */

(function () {
  "use strict";

  var TAU = 6.2831853;

  /* ---------- 1 · Read config (with safe defaults) ---------- */
  // The plugin prints `window.AizleDots = { ...settings... }` before
  // this script. Defaults here MUST match includes/defaults.php so the
  // engine still runs if the config is ever missing.
  var IN = (window.AizleDots && typeof window.AizleDots === "object") ? window.AizleDots : {};

  function num(v, d) { return (typeof v === "number" && isFinite(v)) ? v : d; }
  function bool(v, d) { return (typeof v === "boolean") ? v : d; }
  function str(v, d) { return (typeof v === "string" && v) ? v : d; }
  function now() { return (window.performance && performance.now) ? performance.now() : Date.now(); }

  var C = {
    palette:        Array.isArray(IN.palette) && IN.palette.length ? IN.palette
                    : ["#0F4FFF", "#F70000", "#7300F0", "#FF6E00", "#00C947", "#FFC400"], // setting: palette (list of hex)
    opacity:        num(IN.opacity, 0.5),          // setting: opacity (0.05–1) → global alpha of the field
    densityArea:    num(IN.densityArea, 7000),     // setting: density → screen px² per particle (LOWER = MORE dots)
    minParticles:   num(IN.minParticles, 60),      // floor
    maxParticles:   num(IN.maxParticles, 5000),    // hard ceiling (the loop auto-throttles below this)
    mobileParticles: num(IN.mobileParticles, 600), // cap on small screens (perf)
    cursorRadius:   num(IN.cursorRadius, 320),     // setting: cursor_radius (px) → how wide the push reaches
    push:           num(IN.push, 50),              // setting: push (px) → how far dots shove away
    cursorMode:     str(IN.cursorMode, "push"),    // setting: cursor_mode → push | pull | swirl
    drift:          num(IN.drift, 4),              // setting: drift (px) → ambient wander amplitude (0 = static field)
    sizeMin:        num(IN.sizeMin, 1.4),          // setting: size_min (px)
    sizeMax:        num(IN.sizeMax, 4.6),          // setting: size_max (px)
    shapeDot:       bool(IN.shapeDot, true),       // setting: shape_dot
    shapeSquare:    bool(IN.shapeSquare, true),    // setting: shape_square
    shapeTriangle:  bool(IN.shapeTriangle, true),  // setting: shape_triangle
    shapeLine:      bool(IN.shapeLine, true),      // setting: shape_line → short streaks
    linkLines:      bool(IN.linkLines, false),     // setting: link_lines → draw lines between near dots
    linkDistance:   num(IN.linkDistance, 120),     // setting: link_distance (px)
    rotateWithCursor: bool(IN.rotateWithCursor, true), // setting: rotate_with_cursor
    sleepMode:      bool(IN.sleepMode, false),     // setting: sleep_mode → rest faint/hidden until stirred
    sleepOpacity:   num(IN.sleepOpacity, 0),       // setting: sleep_opacity (0–1) → resting opacity when idle
    wakeMs:         num(IN.wakeMs, 2000),          // setting: wake_ms → how long woken particles stay lit
    scrollStrength: num(IN.scrollStrength, 40),    // setting: scroll_strength (0–200) → kick on scroll
    avoidContent:   bool(IN.avoidContent, true),   // setting: avoid_content → deflect around DOM content
    avoidStrength:  num(IN.avoidStrength, 60),     // setting: avoid_strength (0–200)
    avoidSelectors: str(IN.avoidSelectors, "h1,h2,h3,h4,h5,h6,p,li,blockquote,img,figure,button"), // const
    contentPadding: num(IN.contentPadding, 14),    // const: buffer kept around content (px)
    maxRects:       num(IN.maxRects, 60),          // const: hard cap on tested boxes per frame
    disableOnMobile: bool(IN.disableOnMobile, false), // setting: disable_on_mobile
    mobileBreakpoint: num(IN.mobileBreakpoint, 640),  // px width below which "mobile" applies
    respectReducedMotion: bool(IN.respectReducedMotion, true) // setting: respect_reduced_motion
  };

  /* ---------- 2 · Early exits ---------- */
  var canvas = document.getElementById("aizle-dots");
  if (!canvas) return; // the plugin injects <canvas id="aizle-dots"> in wp_footer

  var reduce = C.respectReducedMotion &&
    window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function isMobile() { return (window.innerWidth || 0) <= C.mobileBreakpoint; }
  if (C.disableOnMobile && isMobile()) { return; } // bail entirely on phones if asked

  // Build the active shape list once.
  var SHAPES = [];
  if (C.shapeDot) SHAPES.push("dot");
  if (C.shapeSquare) SHAPES.push("sq");
  if (C.shapeTriangle) SHAPES.push("tri");
  if (C.shapeLine) SHAPES.push("line");
  if (!SHAPES.length) SHAPES.push("dot"); // never empty

  /* ---------- 3 · Canvas + particles ---------- */
  var ctx = canvas.getContext("2d");
  var W = 0, H = 0, DPR = Math.min(window.devicePixelRatio || 1, 2); // cap DPR at 2 for perf
  var particles = [];
  var N = 0;            // number of particles built for this viewport
  var activeN = 0;      // number actually rendered right now (adaptive throttle)
  var emaDt = 16;       // smoothed frame time (ms)
  var lastT = 0;        // previous frame timestamp
  var mouse = { x: -9999, y: -9999, active: false, tMove: -9999 };
  var rafId = null;
  var running = false;

  // Tuning constants for the physics layer.
  var SPRING = 0.06;     // pull toward the (drifting) home anchor
  var FRICTION = 0.86;   // decay applied to the impulse velocity each frame

  function resize() {
    // Fallback chain → some embed/preview contexts report 0 here.
    W = window.innerWidth || document.documentElement.clientWidth || 1280;
    H = window.innerHeight || document.documentElement.clientHeight || 800;
    canvas.width = Math.floor(W * DPR);
    canvas.height = Math.floor(H * DPR);
    canvas.style.width = W + "px";
    canvas.style.height = H + "px";
    ctx.setTransform(DPR, 0, 0, DPR, 0, 0);

    // particle count scales with viewport area, clamped. Small screens get a
    // lower hard cap regardless of the density setting.
    N = Math.max(C.minParticles, Math.min(C.maxParticles, Math.round((W * H) / C.densityArea)));
    if (isMobile()) { N = Math.min(N, C.mobileParticles); }
    if (particles.length !== N) build();
    // Start conservative for large fields, then ramp toward N if the device copes.
    activeN = Math.min(N, isMobile() ? 300 : 1200);
    buildRects();
    if (reduce) renderStatic();
  }

  function build() {
    particles = [];
    for (var i = 0; i < N; i++) {
      var base = Math.random() * TAU;
      particles.push({
        x: Math.random() * W,
        y: Math.random() * H,
        hx: Math.random() * W,   // home x (the scatter anchor)
        hy: Math.random() * H,   // home y
        vx: 0, vy: 0,            // impulse velocity (scroll + content bumps); decays
        size: C.sizeMin + Math.random() * Math.max(0, C.sizeMax - C.sizeMin),
        shape: SHAPES[(Math.random() * SHAPES.length) | 0],
        color: C.palette[i % C.palette.length],
        amp: C.drift * (0.6 + Math.random() * 0.8), // per-particle ambient amplitude
        seed: Math.random() * TAU,
        baseAngle: base,         // rest orientation
        angle: base,             // current orientation (eased toward cursor push)
        kf: 0.6 + Math.random() * 0.8, // scroll-kick responsiveness, per particle
        wake: 0                  // 0 = asleep (resting opacity), 1 = fully awake
      });
    }
  }

  /* ---------- 3b · Content rects (cached in document space) ---------- */
  // We read element boxes from the DOM only here (load / resize), store them
  // in document coordinates, then re-project to the viewport every frame by
  // subtracting the current scroll offset. No per-frame layout reads → no thrash.
  var rects = [];
  function buildRects() {
    rects = [];
    if (!C.avoidContent || isMobile()) return; // content avoidance is desktop-only (perf)
    var els;
    try { els = document.querySelectorAll(C.avoidSelectors); } catch (e) { return; }
    var sx = window.pageXOffset || 0, sy = window.pageYOffset || 0;

    // 1) Collect candidate boxes (document coords).
    var cand = [];
    for (var i = 0; i < els.length; i++) {
      var b = els[i].getBoundingClientRect();
      if (b.width < 8 || b.height < 8) continue;              // skip tiny/empty
      if (b.width > W * 0.96 && b.height > H * 0.9) continue;  // skip full-page wrappers
      cand.push({ l: b.left + sx, t: b.top + sy, r: b.right + sx, b: b.bottom + sy, a: b.width * b.height });
    }

    // 2) Drop containers. Any candidate that fully encloses another is a wrapper
    //    (e.g. a post-template <li> around a heading + paragraph), not a leaf to
    //    avoid. Keeping wrappers makes particles pile along their long edges.
    for (var j = 0; j < cand.length && rects.length < C.maxRects; j++) {
      var A = cand[j], wrapper = false;
      for (var k = 0; k < cand.length; k++) {
        if (k === j) continue;
        var B = cand[k];
        if (A.a > B.a && A.l <= B.l && A.t <= B.t && A.r >= B.r && A.b >= B.b) { wrapper = true; break; }
      }
      if (!wrapper) rects.push({ l: A.l, t: A.t, r: A.r, b: A.b });
    }
  }

  function applyContent(p) {
    if (!rects.length) return;
    var sx = window.pageXOffset || 0, sy = window.pageYOffset || 0;
    var pad = C.contentPadding;
    // Position ease-out strength. This is FIRST-ORDER (we move the particle
    // toward the edge, we do NOT add velocity) so it has no momentum and cannot
    // overshoot → it settles at the edge instead of buzzing against the
    // home-spring. Velocity is reserved for the scroll kick only.
    var k = 0.05 + ( C.avoidStrength / 200 ) * 0.30;
    for (var i = 0; i < rects.length; i++) {
      var R = rects[i];
      // project to viewport (padded)
      var l = R.l - sx - pad, t = R.t - sy - pad, ri = R.r - sx + pad, bo = R.b - sy + pad;
      if (bo < -160 || t > H + 160) continue;       // off-screen → skip the inside test
      if (p.x > l && p.x < ri && p.y > t && p.y < bo) {
        // inside the padded box → ease toward the nearest edge (just outside it)
        var dl = p.x - l, dr = ri - p.x, dt = p.y - t, db = bo - p.y;
        var m = Math.min(dl, dr, dt, db);
        if (m === dl)      p.x += ( ( l - 1 ) - p.x ) * k;
        else if (m === dr) p.x += ( ( ri + 1 ) - p.x ) * k;
        else if (m === dt) p.y += ( ( t - 1 ) - p.y ) * k;
        else               p.y += ( ( bo + 1 ) - p.y ) * k;
      }
    }
  }

  /* ---------- 3c · Drawing ---------- */
  function draw(p, x, y, scale, alpha, angle) {
    var s = p.size * scale;
    ctx.globalAlpha = alpha;
    ctx.fillStyle = p.color;

    if (p.shape === "dot") {
      // round → rotation is invisible, skip the transform for speed.
      ctx.beginPath();
      ctx.arc(x, y, s, 0, TAU);
      ctx.fill();
      ctx.globalAlpha = 1;
      return;
    }

    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(angle || 0);
    if (p.shape === "sq") {
      ctx.fillRect(-s, -s, s * 2, s * 2);
    } else if (p.shape === "tri") {
      ctx.beginPath();
      ctx.moveTo(0, -s * 1.3);
      ctx.lineTo(-s * 1.1, s);
      ctx.lineTo(s * 1.1, s);
      ctx.closePath();
      ctx.fill();
    } else if (p.shape === "line") {
      var len = s * 3.2, th = Math.max(1, s * 0.55);
      ctx.fillRect(-len / 2, -th / 2, len, th);
    }
    ctx.restore();
    ctx.globalAlpha = 1;
  }

  // Shortest-path angle interpolation.
  function lerpAngle(a, b, t) {
    var d = b - a;
    while (d > Math.PI) d -= TAU;
    while (d < -Math.PI) d += TAU;
    return a + d * t;
  }

  /* ---------- 3d · Constellation links ---------- */
  // Draw faint lines between nearby particles. A uniform spatial grid (cell =
  // link distance) keeps this near-linear instead of O(n²): each particle only
  // tests its own cell and the 8 neighbours. Line opacity fades with distance
  // and follows the dimmer endpoint's alpha, so links honour sleep mode too.
  function drawLinks() {
    var cs = C.linkDistance;
    if (cs < 8 || activeN < 2) return;
    var maxD2 = cs * cs;
    var grid = {}, i, p, gx, gy;
    for (i = 0; i < activeN; i++) {
      p = particles[i];
      gx = (p.rx / cs) | 0; gy = (p.ry / cs) | 0;
      var key = gx + "," + gy;
      (grid[key] || (grid[key] = [])).push(i);
    }
    ctx.lineWidth = 1;
    for (i = 0; i < activeN; i++) {
      p = particles[i];
      var pcx = (p.rx / cs) | 0, pcy = (p.ry / cs) | 0;
      for (var ox = -1; ox <= 1; ox++) {
        for (var oy = -1; oy <= 1; oy++) {
          var cell = grid[(pcx + ox) + "," + (pcy + oy)];
          if (!cell) continue;
          for (var k = 0; k < cell.length; k++) {
            var j = cell[k];
            if (j <= i) continue; // visit each pair once
            var q = particles[j];
            var ddx = p.rx - q.rx, ddy = p.ry - q.ry;
            var d2 = ddx * ddx + ddy * ddy;
            if (d2 >= maxD2) continue;
            var a = (1 - Math.sqrt(d2) / cs) * Math.min(p.rAlpha, q.rAlpha) * 0.7;
            if (a <= 0.01) continue;
            ctx.globalAlpha = a;
            ctx.strokeStyle = p.color;
            ctx.beginPath();
            ctx.moveTo(p.rx, p.ry);
            ctx.lineTo(q.rx, q.ry);
            ctx.stroke();
          }
        }
      }
    }
    ctx.globalAlpha = 1;
  }

  /* ---------- 4 · The loop ---------- */
  function step(now) {
    var t = (now || 0) * 0.001;
    ctx.clearRect(0, 0, W, H);

    // Frame time → drives both the adaptive throttle and the sleep-mode fade.
    // Guarded against big gaps (e.g. after the tab was hidden).
    var dt = lastT ? (now - lastT) : 16;
    if (dt <= 0 || dt > 100) dt = 16;
    lastT = now;
    emaDt = emaDt * 0.9 + Math.min(50, dt) * 0.1;
    if (emaDt > 22 && activeN > C.minParticles) {        // < ~45fps → back off
      activeN -= Math.ceil(activeN * 0.05);
      if (activeN < C.minParticles) activeN = C.minParticles;
    } else if (emaDt < 17 && activeN < N) {              // > ~59fps → add more
      activeN += 24;
      if (activeN > N) activeN = N;
    }
    // Sleep mode: woken particles fade to ~1% over wakeMs (time-based, fps-proof).
    var wakeMul = Math.pow(0.01, dt / Math.max(60, C.wakeMs));

    var R = C.cursorRadius, R2 = R * R;
    // Sleep mode wakes particles only while the cursor is actively MOVING.
    var moving = (now - mouse.tMove) < 120;
    for (var i = 0; i < activeN; i++) {
      var p = particles[i];

      // ambient drift → a gentle wander around the home anchor, always on
      // (set drift = 0 in settings for a perfectly static field).
      var hx = p.hx + Math.sin(t * 0.55 + p.seed) * p.amp;
      var hy = p.hy + Math.cos(t * 0.48 + p.seed * 1.7) * p.amp;

      // ease toward the (drifting) home
      p.x += (hx - p.x) * SPRING;
      p.y += (hy - p.y) * SPRING;

      // content avoidance eases the particle out of text/media (position-based,
      // so it settles at the edge instead of buzzing).
      if (C.avoidContent) applyContent(p);
      // scroll kicks live on the velocity channel; integrate + decay it.
      p.x += p.vx; p.y += p.vy;
      p.vx *= FRICTION; p.vy *= FRICTION;

      var dx2 = p.x, dy2 = p.y, scale = 1, alpha = C.opacity, infl = 0, pushAngle = p.baseAngle;

      // cursor repulsion → a wide, soft push (drawn as a transient offset)
      if (mouse.active) {
        var dx = p.x - mouse.x, dy = p.y - mouse.y;
        var d2 = dx * dx + dy * dy;
        if (d2 < R2) {
          var d = Math.sqrt(d2) || 0.001;
          var f = (R - d) / R;
          var ux = dx / d, uy = dy / d; // unit vector pointing AWAY from the cursor
          var ox, oy;
          if (C.cursorMode === "pull") { ox = -ux; oy = -uy; }        // toward the cursor
          else if (C.cursorMode === "swirl") { ox = -uy; oy = ux; }   // perpendicular → orbit
          else { ox = ux; oy = uy; }                                  // push (default)
          dx2 += ox * f * C.push;
          dy2 += oy * f * C.push;
          scale = 1 + f * 1.5;
          alpha = Math.min(1, C.opacity + f * 0.35); // brighten near the cursor
          infl = f;
          pushAngle = Math.atan2(oy, ox); // face the direction of travel
        }
      }

      // sleep mode → the moving cursor wakes the particles within its reach
      // (strongest at the centre), and everything fades back toward the resting
      // opacity when the cursor stops or moves on.
      if (C.sleepMode) {
        if (mouse.active && moving && infl > p.wake) { p.wake = infl; }
        p.wake *= wakeMul;
        alpha = C.sleepOpacity + (alpha - C.sleepOpacity) * p.wake;
      }

      // rotation → swing to face the push when influenced, settle back otherwise.
      if (C.rotateWithCursor && p.shape !== "dot") {
        if (infl > 0) {
          p.angle = lerpAngle(p.angle, pushAngle, 0.15 + 0.5 * infl);
        } else {
          p.angle = lerpAngle(p.angle, p.baseAngle, 0.04);
        }
      }

      // remember the rendered position + alpha for the constellation pass
      p.rx = dx2; p.ry = dy2; p.rAlpha = alpha;
      draw(p, dx2, dy2, scale, alpha, p.angle);
    }
    if (C.linkLines) drawLinks();
    rafId = requestAnimationFrame(step);
  }

  function renderStatic() {
    // reduced-motion → draw the field once, no rAF, no physics.
    ctx.clearRect(0, 0, W, H);
    for (var i = 0; i < particles.length; i++) {
      var p = particles[i];
      draw(p, p.hx, p.hy, 1, C.opacity, p.baseAngle);
    }
  }

  function start() { if (!running && !reduce) { running = true; rafId = requestAnimationFrame(step); } }
  function stop() { if (running) { running = false; if (rafId) cancelAnimationFrame(rafId); rafId = null; } }

  /* ---------- 5 · Events ---------- */
  if (!reduce) {
    window.addEventListener("pointermove", function (e) {
      mouse.x = e.clientX; mouse.y = e.clientY; mouse.active = true; mouse.tMove = now();
    }, { passive: true });
    window.addEventListener("pointerleave", function () { mouse.active = false; });
    window.addEventListener("touchmove", function (e) {
      if (e.touches[0]) { mouse.x = e.touches[0].clientX; mouse.y = e.touches[0].clientY; mouse.active = true; mouse.tMove = now(); }
    }, { passive: true });
    window.addEventListener("touchend", function () { mouse.active = false; });

    // Scroll → impart a velocity kick in the scroll direction; friction settles it.
    var lastScroll = window.pageYOffset || 0;
    window.addEventListener("scroll", function () {
      var y = window.pageYOffset || 0;
      var dv = y - lastScroll;
      lastScroll = y;
      if (!C.scrollStrength) return;
      if (dv > 100) dv = 100; else if (dv < -100) dv = -100; // clamp big jumps
      var imp = dv * (C.scrollStrength / 1200);
      for (var i = 0; i < particles.length; i++) {
        particles[i].vy += imp * particles[i].kf;
      }
    }, { passive: true });

    // Pause the loop when the tab is hidden → saves battery/CPU and
    // avoids the "blank in a backgrounded tab" artefact.
    document.addEventListener("visibilitychange", function () {
      if (document.hidden) { stop(); } else { start(); }
    });
  }

  window.addEventListener("resize", resize);
  window.addEventListener("load", function () {
    resize();          // re-measure once layout settles
    // images/webfonts can shift layout after load → refresh the cached rects.
    setTimeout(buildRects, 500);
    setTimeout(buildRects, 1500);
  });

  /* ---------- 6 · Boot ---------- */
  resize();
  if (reduce) { renderStatic(); } else { start(); }
})();
