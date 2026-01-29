<?php
/**
 * Plugin Name: HTML Notice Widget
 * Plugin URI: https://example.com/html-notice-widget
 * Description: Dynamically manage multiple sites with multiple HTML contents per site, each with individual enable/disable switches
 * Version: 1.2.0
 * Author: Muaz
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: html-notice-widget
 * Domain Path: /languages
 *
 * @package HTML_Notice_Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Define plugin constants
define( 'HTML_NOTICE_WIDGET_VERSION', '1.2.0' );
define( 'HTML_NOTICE_WIDGET_PATH', plugin_dir_path( __FILE__ ) );
define( 'HTML_NOTICE_WIDGET_URL', plugin_dir_url( __FILE__ ) );
define( 'HTML_NOTICE_WIDGET_BASENAME', plugin_basename( __FILE__ ) );

// Include required files
require_once HTML_NOTICE_WIDGET_PATH . 'includes/class-plugin.php';
require_once HTML_NOTICE_WIDGET_PATH . 'includes/class-php-admin.php';
require_once HTML_NOTICE_WIDGET_PATH . 'includes/class-php-utils.php';
require_once HTML_NOTICE_WIDGET_PATH . 'includes/class-api.php';
require_once HTML_NOTICE_WIDGET_PATH . 'includes/class-options.php';

/**
 * Initialize the plugin
 */
function html_notice_widget_init() {
	HTML_Notice_Widget\Plugin::instance();
}

add_action( 'plugins_loaded', 'html_notice_widget_init' );

/**
 * Activation hook
 */
register_activation_hook( __FILE__, function() {
	HTML_Notice_Widget\Plugin::instance()->activate();
} );

/**
 * Deactivation hook
 */
register_deactivation_hook( __FILE__, function() {
	HTML_Notice_Widget\Plugin::instance()->deactivate();
} );

