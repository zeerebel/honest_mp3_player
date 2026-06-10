<?php
/**
 * HPC Global Audio
 * Outputs a single hidden <audio id="hpc-global"> element in wp_footer on every page.
 * The audio element lives in the footer so it is not destroyed when navigating
 * within page content. Player UI shortcodes reference this element by id.
 */

add_action( 'wp_footer', function () {
	?>
	<audio id="hpc-global" preload="auto" style="display:none">
		<source src="https://honestpharmco.com/wp-content/uploads/2026/06/8D-Music-Bad-Habits-Chillout-8d-audio-for-Relaxing.mp3" type="audio/mpeg">
	</audio>
	<?php
}, 99 );
