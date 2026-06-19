<?php
/**
 * HPC Global Audio — persistent audio engine.
 *
 * The audio lives as a JavaScript object on `window` (not in the DOM) so it
 * survives Salient's AJAX page transitions and plays continuously as visitors
 * move between pages. Autoplays on every page; remembers position + volume
 * across full page reloads via sessionStorage.
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

	  if (a.readyState >= 2) start();
	  else a.addEventListener('canplay', start, { once: true });
	})();
	</script>
	<?php
}, 99 );
