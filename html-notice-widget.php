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

namespace HTML_Notice_Widget;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main plugin core class.
 */
final class Core {

	/**
	 * Plugin instance.
	 *
	 * @var Core
	 */
	private static $instance = null;

	/**
	 * Main instance getter.
	 *
	 * @return Core
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Define plugin constants.
	 */
	private function define_constants() {
		define( 'HTML_NOTICE_WIDGET_VERSION', '1.2.0' );
		define( 'HTML_NOTICE_WIDGET_PATH', plugin_dir_path( __FILE__ ) );
		define( 'HTML_NOTICE_WIDGET_URL', plugin_dir_url( __FILE__ ) );
		define( 'HTML_NOTICE_WIDGET_BASENAME', plugin_basename( __FILE__ ) );
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once HTML_NOTICE_WIDGET_PATH . 'includes/class-plugin.php';
		require_once HTML_NOTICE_WIDGET_PATH . 'admin/class-php-admin.php';
		require_once HTML_NOTICE_WIDGET_PATH . 'includes/class-php-utils.php';
		require_once HTML_NOTICE_WIDGET_PATH . 'includes/class-api.php';
		require_once HTML_NOTICE_WIDGET_PATH . 'includes/class-options.php';
		require_once HTML_NOTICE_WIDGET_PATH . 'includes/class-analytics.php';
		require_once HTML_NOTICE_WIDGET_PATH . 'admin/class-analytics-admin.php';
		require_once HTML_NOTICE_WIDGET_PATH . 'includes/class-enqueue-assets.php';
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
	}

	/**
	 * Fire up the main plugin logic.
	 */
	public function init_plugin() {
		Plugin::instance();
	}

	/**
	 * Activation callback.
	 */
	public function activate() {
		Plugin::instance()->activate();
	}

	/**
	 * Deactivation callback.
	 */
	public function deactivate() {
		Plugin::instance()->deactivate();
	}
}

// Boot the plugin.
Core::instance();
