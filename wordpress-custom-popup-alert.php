<?php
/**
 * Plugin Name:       WordPress Custom Popup Alert
 * Description:       Displays contextual popup alerts based on WordPress and WooCommerce rules.
 * Version:           1.5.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Eduardo Henrique Teixeira
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       wordpress-custom-popup-alert
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'WCCPA_VERSION', '1.5.1' );
define( 'WCCPA_FILE', __FILE__ );
define( 'WCCPA_PATH', plugin_dir_path( __FILE__ ) );
define( 'WCCPA_URL', plugin_dir_url( __FILE__ ) );

require_once WCCPA_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'WCCPA\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WCCPA\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		WCCPA\Plugin::instance()->boot();
	}
);
