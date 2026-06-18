<?php
/**
 * HPC Global Audio — persistent audio engine.
 *
 * Salient's AJAX page transitions destroy and rebuild the page DOM on every
 * navigation (confirmed: a <audio> element in the footer does NOT survive).
 * So instead of relying on a DOM element persisting, we hold the audio as a
 * JavaScript object on `window` (window.hpcAudio = new Audio()). That object
 * lives in the JS layer, not the DOM, so Salient's DOM swap cannot touch it,
 * and a playing Audio() keeps playing across navigations with zero gap.
 *
 * This snippet runs on every page (wp_footer) and owns:
 *   - creating the single persistent audio object (guarded so it's made once)
 *   - autoplay (unmuted first; muted fallback; unmute on first interaction)
 *   - position + volume persistence via sessionStorage (covers full reloads)
 *
 * The visible player UI is a separate shortcode that binds to window.hpcAudio.
 */

add_action( 'wp_footer', function () {
	?>
	<script>
	(function () {
	  'use strict';

	  // window persists across Salient AJAX navigation, so if the engine was
	  // already created on an earlier page, reuse it — never recreate.
	  if (window.hpcAudio) return;

	  var SRC = 'https://honestpharmco.com/wp-content/uploads/2026/06/8D-Music-Bad-Habits-Chillout-8d-audio-for-Relaxing.mp3';
	  var STORAGE_POS = 'hpc-pos', STORAGE_VOL = 'hpc-volume', STORAGE_PLAYING = 'hpc-playing';

	  function read(k)  { try { return sessionStorage.getItem(k); } catch (e) { return null; } }
	  function write(k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} }

	  var a = new Audio();
	  a.preload = 'auto';
	  a.src = SRC;

	  // Restore volume (default 80%).
	  var sv = parseFloat(read(STORAGE_VOL));
	  a.volume = (!isNaN(sv) && sv >= 0 && sv <= 1) ? sv : 0.8;

	  window.hpcAudio = a;
	  window.hpcAutoMuted = false; // true when muted ONLY to satisfy autoplay policy

	  // Restore saved position once metadata is available (covers full reloads;
	  // on a true AJAX nav the object never resets, so this just no-ops).
	  var pos = parseFloat(read(STORAGE_POS));
	  function seekSaved() {
	    if (!isNaN(pos) && pos > 0 && a.duration && pos < a.duration - 1) {
	      a.currentTime = pos;
	    }
	  }
	  if (a.readyState >= 1) seekSaved();
	  else a.addEventListener('loadedmetadata', seekSaved, { once: true });

	  // Continuously persist position + playing flag.
	  a.addEventListener('timeupdate', function () {
	    write(STORAGE_POS, String(a.currentTime || 0));
	    write(STORAGE_PLAYING, a.paused ? '0' : '1');
	  });
	  window.addEventListener('pagehide', function () {
	    write(STORAGE_POS, String(a.currentTime || 0));
	    write(STORAGE_PLAYING, a.paused ? '0' : '1');
	  });

	  // Autoplay: try unmuted (works for repeat visitors / high MEI); on block,
	  // retry muted (always permitted); first interaction unmutes.
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
	    if (a.paused) a.play().catch(function () {});
	  }
	  // Unmuting an already-playing element needs no activation gesture, so
	  // mousemove/scroll/wheel work here alongside pointerdown/touch/keydown.
	  ['pointerdown','touchstart','keydown','wheel','scroll','click','mousemove'].forEach(function (evt) {
	    window.addEventListener(evt, unmute, { capture: true, passive: true });
	  });

	  if (a.readyState >= 2) start();
	  else a.addEventListener('canplay', start, { once: true });
	})();
	</script>
	<?php
}, 99 );
