<?php
/**
 * Main Plugin Class
 *
 * @package HTML_Notice_Widget
 */

namespace HTML_Notice_Widget;

class Plugin {
	/**
	 * Instance of the plugin
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * PHP Admin instance
	 *
	 * @var PHP_Admin
	 */
	private $php_admin = null;

	/**
	 * Get or create instance
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setup_hooks();
		$this->init_admin_interface();
	}

	/**
	 * Initialize admin interface
	 */
	private function init_admin_interface() {
		// Initialize PHP Admin interface
		$this->php_admin = new PHP_Admin();
	}

	/**
	 * Setup plugin hooks
	 */
	private function setup_hooks() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		
		// Schedule cron for processing expired content
		add_action( 'html_notice_widget_process_expired', [ $this, 'process_expired_content' ] );
		
		// Note: PHP Admin handles its own asset enqueuing
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_menu_page(
			'HTML Notice Widget',
			'HTML Notice Widget',
			'manage_options',
			'html-notice-widget',
			[ $this, 'render_settings_page' ],
			'dashicons-megaphone',
			99
		);
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( $this->php_admin ) {
			$this->php_admin->render_admin_page();
		} else {
			echo '<div class="wrap"><h1>HTML Notice Widget</h1><p>Admin interface not initialized.</p></div>';
		}
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		$api = new API();
		$api->register_routes();
	}


	/**
	 * Activation hook
	 */
	public function activate() {
		// Set default options if they don't exist
		if ( false === get_option( 'html_notice_widget_sites' ) ) {
			update_option( 'html_notice_widget_sites', [] );
		} else {
			// Migrate existing sites to new structure
			$this->migrate_sites_structure();
		}
		
		// Schedule cron job for processing expired content (runs hourly)
		if ( ! wp_next_scheduled( 'html_notice_widget_process_expired' ) ) {
			wp_schedule_event( time(), 'hourly', 'html_notice_widget_process_expired' );
		}
		
		flush_rewrite_rules();
	}

	/**
	 * Migrate sites from old structure (single content) to new structure (contents array)
	 */
	private function migrate_sites_structure() {
		$sites = get_option( 'html_notice_widget_sites', [] );
		$needs_migration = false;

		foreach ( $sites as &$site ) {
			// Check if site has old 'content' field instead of new 'contents' array
			if ( isset( $site['content'] ) && ! isset( $site['contents'] ) ) {
				$needs_migration = true;

				// Create contents array from old content field
				$site['contents'] = [];

				// If there was content, convert it to a content item
				if ( ! empty( $site['content'] ) ) {
					$site['contents'][] = [
						'id'      => wp_generate_uuid4(),
						'title'   => 'Migrated Content',
						'content' => $site['content'],
						'enabled' => $site['enabled'] ?? true,
					];
				}

				// Remove old content field
				unset( $site['content'] );
			}
		}

		// Update the option if migration was needed
		if ( $needs_migration ) {
			update_option( 'html_notice_widget_sites', $sites );
		}
	}

	/**
	 * Deactivation hook
	 */
	public function deactivate() {
		// Unschedule cron job
		$timestamp = wp_next_scheduled( 'html_notice_widget_process_expired' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'html_notice_widget_process_expired' );
		}
		
		flush_rewrite_rules();
	}

	/**
	 * Process expired content (called by cron)
	 */
	public function process_expired_content() {
		$result = PHP_Utils::process_expired_content();
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $result['disabled_count'] > 0 ) {
			error_log( '[HTML Notice Widget] Disabled ' . $result['disabled_count'] . ' expired content item(s)' );
		}
	}
}

