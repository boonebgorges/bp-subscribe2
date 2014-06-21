<?php

/*
Plugin Name: BP Subscribe2
Description: BuddyPress integration for Subscribe2
Version: 1.0
Author: Boone B Gorges
*/

/**
 * Load only when BuddyPress and Subscribe2 are present.
 */
function bps2_include() {
	// Bail if Subscribe2 is not present
	if ( ! class_exists( 's2class' ) ) {
		return;
	}

	require( dirname( __FILE__ ) . '/bp-subscribe2.php' );
}
add_action( 'bp_include', 'bps2_include' );
