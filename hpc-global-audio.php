<?php
/**
 * HPC Global Audio — persistent audio engine (page-gated).
 *
 * The audio lives as a JavaScript object on `window` (not in the DOM) so it
 * survives Salient's AJAX page transitions and plays continuously as visitors
 * move between pages. You control WHERE it plays with the MODE + PAGES
 * settings below — supporting an allow-list, a block-list, and * wildcards.
 *
 * TO CHANGE WHERE MUSIC PLAYS: edit the MODE and PAGES settings a few lines
 * down. That is the only thing you normally need to touch.
 */

add_action( 'wp_footer', function () {
	?>
	<script>
	(function () {
	  'use strict';

	  // window persists across Salient AJAX navigation, so if the engine was
	  // already created on an earlier page, don't create it again.
	  if (window.hpcAudio) return;

	  /* =====================================================================
	     EDIT ME — WHERE THE MUSIC PLAYS
	     ---------------------------------------------------------------------
	     Two settings: MODE and PAGES.

	     MODE:
	       'allow' = music plays ONLY on the pages you list (everywhere else
	                 is silent).
	       'block' = music plays EVERYWHERE EXCEPT the pages you list.

	     PAGES: the list of page paths. A "path" is the part of the address
	     after your domain:
	         https://honestpharmco.com/about-us/    ->    /about-us/

	     WILDCARD: put a * at the end of a path to match that page and
	     everything beneath it:
	         '/products/*'  matches /products/, /products/kief, /products/a/b
	         '*'            matches every page on the site

	     Rules:
	       - Start each path with a slash:  '/about-us/'
	       - The home page is just:         '/'
	       - Trailing slashes don't matter.
	       - One entry per line, in quotes, separated by commas.

	     EXAMPLES:

	       // Play ONLY on these pages:
	       var MODE  = 'allow';
	       var PAGES = ['/', '/about-us/', '/products/*'];

	       // Play EVERYWHERE EXCEPT checkout and cart:
	       var MODE  = 'block';
	       var PAGES = ['/checkout/*', '/cart/'];

	       // Play on the WHOLE site (no exceptions):
	       var MODE  = 'block';
	       var PAGES = [];
	     ===================================================================== */
	  var MODE  = 'block';
	  var PAGES = [];
	  /* ===================== END EDIT ME ================================== */

	  var SRC = 'https://honestpharmco.com/wp-content/uploads/2026/06/8D-Music-Bad-Habits-Chillout-8d-audio-for-Relaxing.mp3';
	  var STORAGE_POS = 'hpc-pos', STORAGE_VOL = 'hpc-volume', STORAGE_PLAYING = 'hpc-playing';

	  function read(k)    { try { return sessionStorage.getItem(k); } catch (e) { return null; } }
	  function write(k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} }

	  // --- Create the single persistent audio object -----------------------
	  var a = new Audio();
	  a.preload = 'auto';
	  a.loop    = true;   // background music loops forever
	  a.src     = SRC;

	  // Restore last volume (default 80%).
	  var sv = parseFloat(read(STORAGE_VOL));
	  a.volume = (!isNaN(sv) && sv >= 0 && sv <= 1) ? sv : 0.8;

	  window.hpcAudio      = a;
	  window.hpcAutoMuted  = false; // true only when muted to satisfy autoplay policy
	  window.hpcGatePaused = false; // true when WE paused it because the page is off-list

	  // Restore last position once the track's length is known.
	  var pos = parseFloat(read(STORAGE_POS));
	  function seekSaved() {
	    if (!isNaN(pos) && pos > 0 && a.duration && pos < a.duration - 1) a.currentTime = pos;
	  }
	  if (a.readyState >= 1) seekSaved();
	  else a.addEventListener('loadedmetadata', seekSaved, { once: true });

	  // Keep saving position + playing state for full-reload recovery.
	  a.addEventListener('timeupdate', function () {
	    write(STORAGE_POS, String(a.currentTime || 0));
	    write(STORAGE_PLAYING, a.paused ? '0' : '1');
	  });
	  window.addEventListener('pagehide', function () {
	    write(STORAGE_POS, String(a.currentTime || 0));
	    write(STORAGE_PLAYING, a.paused ? '0' : '1');
	  });

	  // --- Should the current page play music? -----------------------------
	  // Normalize a path by dropping any trailing slash ('/about/' -> '/about').
	  function norm(s) { s = (s || '').replace(/\/+$/, ''); return s === '' ? '/' : s; }
	  // Match one PAGES entry against a path. A trailing * means "this path
	  // and anything beneath it"; otherwise it's an exact match.
	  function matchPath(pattern, path) {
	    if (pattern === '*' || pattern === '/*') return true;
	    if (pattern.charAt(pattern.length - 1) === '*') {
	      var prefix = norm(pattern.slice(0, -1));
	      var p = norm(path);
	      return p === prefix || p.indexOf(prefix + '/') === 0;
	    }
	    return norm(pattern) === norm(path);
	  }
	  function pathAllowed() {
	    var listed = false;
	    for (var i = 0; i < PAGES.length; i++) {
	      if (matchPath(PAGES[i], location.pathname)) { listed = true; break; }
	    }
	    // 'block' mode inverts the list: play everywhere EXCEPT listed pages.
	    return (MODE === 'block') ? !listed : listed;
	  }

	  // --- Autoplay (unmuted first; muted fallback; unmute on interaction) --
	  function start() {
	    var p = a.play();
	    if (p && typeof p.then === 'function') {
	      p.catch(function () {
	        if (a.volume > 0) { a.muted = true; window.hpcAutoMuted = true; }
	        a.play().catch(function () {});
	      });
	    }
	  }
	  function unmute() {
	    if (window.hpcAutoMuted && a.muted) { a.muted = false; window.hpcAutoMuted = false; }
	  }
	  ['pointerdown','touchstart','keydown','wheel','scroll','click','mousemove'].forEach(function (evt) {
	    window.addEventListener(evt, unmute, { capture: true, passive: true });
	  });

	  // --- Apply the gate for the page we're currently on ------------------
	  // On a playing page: resume if we had paused it for being off-list.
	  // On a silent page: pause it (and remember we did, so we can resume).
	  function applyGate() {
	    if (pathAllowed()) {
	      if (window.hpcGatePaused) {
	        window.hpcGatePaused = false;
	        if (a.readyState >= 2) start();
	        else a.addEventListener('canplay', start, { once: true });
	      }
	    } else {
	      if (!a.paused) { a.pause(); window.hpcGatePaused = true; }
	    }
	  }

	  // First page load: play only if this page should play.
	  if (pathAllowed()) {
	    if (a.readyState >= 2) start();
	    else a.addEventListener('canplay', start, { once: true });
	  } else {
	    window.hpcGatePaused = true;
	  }

	  // Watch for navigation (AJAX or full) and re-apply the gate. This single
	  // watcher lives on window, so it keeps working no matter how the theme
	  // changes pages — no dependence on theme-specific events.
	  var lastPath = location.pathname;
	  setInterval(function () {
	    if (location.pathname !== lastPath) {
	      lastPath = location.pathname;
	      applyGate();
	    }
	  }, 250);
	})();
	</script>
	<?php
}, 99 );
