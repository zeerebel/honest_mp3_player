<?php
/**
 * HPC Global Audio — persistent audio engine.
 *
 * The audio lives as a JavaScript object on `window` (not in the DOM) so it
 * survives Salient's AJAX page transitions and plays continuously as visitors
 * move between pages. Autoplays on every page; remembers position + volume
 * across full page reloads via sessionStorage.
 *
 * Note on refresh: browsers block autoplay SOUND on a fresh page load until
 * the visitor interacts. This engine restores the saved position and starts
 * muted; the first mouse-move / scroll / tap / click makes it audible again.
 */

add_action( 'wp_footer', function () {
	?>
	<script>
	(function () {
	  'use strict';

	  // window persists across Salient AJAX navigation, so if the engine was
	  // already created on an earlier page, don't create it again.
	  if (window.hpcAudio) return;

	  var SRC = 'https://honestpharmco.com/wp-content/uploads/2026/06/8D-Music-Bad-Habits-Chillout-8d-audio-for-Relaxing.mp3';
	  var STORAGE_POS = 'hpc-pos', STORAGE_VOL = 'hpc-volume', STORAGE_PLAYING = 'hpc-playing';

	  function read(k)    { try { return sessionStorage.getItem(k); } catch (e) { return null; } }
	  function write(k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} }

	  // --- Create the single persistent audio object -----------------------
	  var a = new Audio();
	  a.preload = 'auto';
	  a.loop    = true;
	  a.src     = SRC;

	  // Restore last volume (default 80%).
	  var sv = parseFloat(read(STORAGE_VOL));
	  a.volume = (!isNaN(sv) && sv >= 0 && sv <= 1) ? sv : 0.8;

	  window.hpcAudio     = a;
	  window.hpcAutoMuted = false; // true only when muted to satisfy autoplay policy
	  window.hpcStarted   = false; // true once we've achieved AUDIBLE playback

	  // Restore last position once the track's length is known.
	  var pos = parseFloat(read(STORAGE_POS));
	  function seekSaved() {
	    if (!isNaN(pos) && pos > 0 && a.duration && pos < a.duration - 1) {
	      a.currentTime = pos;
	    }
	  }
	  if (a.readyState >= 1) seekSaved();
	  else a.addEventListener('loadedmetadata', seekSaved, { once: true });

	  // Keep saving position so a refresh resumes where we left off.
	  a.addEventListener('timeupdate', function () {
	    write(STORAGE_POS, String(a.currentTime || 0));
	    write(STORAGE_PLAYING, a.paused ? '0' : '1');
	  });
	  window.addEventListener('pagehide', function () {
	    write(STORAGE_POS, String(a.currentTime || 0));
	    write(STORAGE_PLAYING, a.paused ? '0' : '1');
	  });

	  // --- Autoplay --------------------------------------------------------
	  // Try unmuted first (works for repeat visitors with autoplay permission).
	  // If blocked, play muted so the track is at least running; the first
	  // gesture (below) makes it audible and resumes it if it was fully blocked.
	  function start() {
	    var p = a.play();
	    if (p && typeof p.then === 'function') {
	      p.then(function () { window.hpcStarted = true; })
	       .catch(function () {
	         if (a.volume > 0) { a.muted = true; window.hpcAutoMuted = true; }
	         a.play().catch(function () {});
	       });
	    } else {
	      window.hpcStarted = true;
	    }
	  }

	  // First real interaction: unmute the muted stream, and if autoplay was
	  // blocked entirely (still paused), start it now that we have a gesture.
	  // Once it's genuinely playing audibly we stop, so a later manual pause
	  // by the visitor is respected and not auto-resumed.
	  function onGesture() {
	    if (window.hpcAutoMuted && a.muted) { a.muted = false; window.hpcAutoMuted = false; }
	    if (a.paused && !window.hpcStarted) { a.play().catch(function () {}); }
	    if (!a.paused && !a.muted) { window.hpcStarted = true; }
	  }
	  ['pointerdown','touchstart','keydown','wheel','scroll','click','mousemove'].forEach(function (evt) {
	    window.addEventListener(evt, onGesture, { capture: true, passive: true });
	  });

	  if (a.readyState >= 2) start();
	  else a.addEventListener('canplay', start, { once: true });
	})();
	</script>
	<?php
}, 99 );
