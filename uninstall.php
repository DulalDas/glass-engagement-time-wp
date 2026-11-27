<?php
/**
 * Remove plugin data on uninstall.
 *
 * @package GlassEngagementTime
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'get_settings' );
foreach ( array( 'get_views', 'get_seconds', 'get_scroll_sum', 'get_reads', 'get_d25', 'get_d50', 'get_d75', 'get_d100' ) as $k ) {
	delete_post_meta_by_key( $k );
}
