<?php
/**
 * HPC Global Audio — persistent audio engine (page-gated).
 *
 * The audio lives as a JavaScript object on `window` (not in the DOM) so it
 * survives Salient's AJAX page transitions and plays continuously as visitors
 * move between pages. This version only plays the music on the pages YOU list
 * below — it pauses on every other page and resumes when you return to a
 * listed page.
 *
 * TO CHANGE WHICH PAGES PLAY MUSIC: edit the ALLOWED_PATHS list a few lines
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
	     EDIT ME — PAGES THAT PLAY MUSIC
	     ---------------------------------------------------------------------
	     List the URL path of every page where the music should play. The
	     "path" is the part of the address AFTER your domain.

	       Full URL:  https://honestpharmco.com/about-us/
	       Path:      /about-us/

	     Rules:
	       - Always start with a slash:  '/about-us/'
	       - The home page is just:      '/'
	       - Trailing slashes don't matter ('/about-us' = '/about-us/').
	       - Put each page in quotes, separated by commas.
	       - Keep this list matching the pages where you pasted the player,
	         so music and controls always appear together.

	     Example with several pages:
	       var ALLOWED_PATHS = [
	         '/',
	         '/about-us/',
	         '/products/',
	         '/contact-us/'
	       ];
	     ===================================================================== */
	  var ALLOWED_PATHS = [
	    '/',
	    '/front_page_june-18b_2026-2/'
	  ];
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

	  window.hpcAudio     = a;
	  window.hpcAutoMuted = false; // true only when muted to satisfy autoplay policy
	  window.hpcGatePaused = false; // true when WE paused it because the page isn't listed

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

	  // --- Is the current page in the allowed list? ------------------------
	  function pathAllowed() {
	    var here = (location.pathname || '/').replace(/\/+$/, '') || '/';
	    for (var i = 0; i < ALLOWED_PATHS.length; i++) {
	      var listed = (ALLOWED_PATHS[i] || '').replace(/\/+$/, '') || '/';
	      if (listed === here) return true;
	    }
	    return false;
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
	  // On a listed page: resume if we had paused it for being off-list.
	  // On an unlisted page: pause it (and remember we did, so we can resume).
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

	  // First page load: play only if this page is listed.
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
