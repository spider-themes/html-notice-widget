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
	 * Analytics Admin instance
	 *
	 * @var Analytics_Admin
	 */
	private $analytics_admin = null;

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
		$this->analytics_admin = new Analytics_Admin();

		// Centralized asset enqueue handler.
		new Enqueue_Assets();
	}

	/**
	 * Setup plugin hooks
	 */
	private function setup_hooks() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Suppress third-party admin notices on our pages.
		add_action( 'admin_notices', [ $this, 'suppress_admin_notices' ], 1 );
		add_action( 'all_admin_notices', [ $this, 'suppress_admin_notices' ], 1 );
		
		// Schedule cron for processing expired content.
		add_action( 'html_notice_widget_process_expired', [ $this, 'process_expired_content' ] );

		// Analytics rollup cron.
		add_action( 'hnw_analytics_rollup', [ $this, 'run_analytics_rollup' ] );
		
		// Note: PHP Admin handles its own asset enqueuing
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'HTML Notice Widget', 'html-notice-widget' ),
			__( 'HTML Notice Widget', 'html-notice-widget' ),
			'manage_options',
			'html-notice-widget',
			[ $this, 'render_settings_page' ],
			'dashicons-megaphone',
			99
		);

		add_submenu_page(
			'html-notice-widget',
			'Analytics — HTML Notice Widget',
			'Analytics',
			'manage_options',
			'html-notice-widget-analytics',
			[ $this, 'render_analytics_page' ]
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
	 * Render analytics page
	 */
	public function render_analytics_page() {
		if ( $this->analytics_admin ) {
			$this->analytics_admin->render_analytics_page();
		} else {
			echo '<div class="wrap"><h1>Analytics</h1><p>Analytics interface not initialized.</p></div>';
		}
	}

	/**
	 * Suppress third-party admin notices on plugin pages
	 */
	public function suppress_admin_notices() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		// Match our plugin pages by screen ID.
		$our_pages = [
			'toplevel_page_html-notice-widget',
			'html-notice-widget_page_html-notice-widget-analytics',
		];

		if ( in_array( $screen->id, $our_pages, true ) ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
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

		// Create analytics tables.
		Analytics::create_tables();
		
		// Schedule cron job for processing expired content (runs hourly)
		if ( ! wp_next_scheduled( 'html_notice_widget_process_expired' ) ) {
			wp_schedule_event( time(), 'hourly', 'html_notice_widget_process_expired' );
		}

		// Schedule analytics rollup cron (runs hourly).
		if ( ! wp_next_scheduled( 'hnw_analytics_rollup' ) ) {
			wp_schedule_event( time(), 'hourly', 'hnw_analytics_rollup' );
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
		// Unschedule cron jobs.
		$timestamp = wp_next_scheduled( 'html_notice_widget_process_expired' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'html_notice_widget_process_expired' );
		}

		$rollup_ts = wp_next_scheduled( 'hnw_analytics_rollup' );
		if ( $rollup_ts ) {
			wp_unschedule_event( $rollup_ts, 'hnw_analytics_rollup' );
		}
		
		flush_rewrite_rules();
	}

	/**
	 * Run analytics rollup cron job
	 */
	public function run_analytics_rollup() {
		$result = Analytics::rollup_and_prune();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $result['pruned_count'] > 0 ) {
			error_log( '[HTML Notice Widget] Analytics rollup: ' . $result['rollup_count'] . ' rows rolled up, ' . $result['pruned_count'] . ' raw rows pruned' );
		}
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

