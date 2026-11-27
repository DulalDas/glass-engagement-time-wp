<?php
/**
 * Plugin Name:       Glass Engagement Time
 * Plugin URI:        https://www.facebook.com/share/1CJkZsQtna/
 * Description:       See how long people really read each post. Measures active reading time and scroll depth without cookies, IP storage or external services. Created by Dulal Das.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Dulal Das
 * Author URI:        https://www.facebook.com/share/1CJkZsQtna/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       glass-engagement-time
 *
 * @package GlassEngagementTime
 */

defined( 'ABSPATH' ) || exit;

define( 'GET_VERSION', '1.0.0' );
define( 'GET_DIR', plugin_dir_path( __FILE__ ) );
define( 'GET_URL', plugin_dir_url( __FILE__ ) );

require_once GET_DIR . 'includes/class-get-store.php';
require_once GET_DIR . 'includes/class-get-front.php';
require_once GET_DIR . 'includes/class-get-admin.php';

add_action( 'plugins_loaded', function () {
	GET_Front::init();
	GET_Admin::init();
} );
