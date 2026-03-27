<?php
/**
 * Centralized asset enqueue handler
 *
 * Registers and enqueues all admin CSS/JS assets on the
 * appropriate plugin pages. Keeps enqueue logic out of
 * individual admin classes.
 *
 * @package HTML_Notice_Widget
 */

namespace HTML_Notice_Widget;

defined( 'ABSPATH' ) || exit;

class Enqueue_Assets {

	/**
	 * Hook suffix for the main settings page.
	 *
	 * @var string
	 */
	const HOOK_SETTINGS = 'toplevel_page_html-notice-widget';

	/**
	 * Hook suffix for the analytics sub-page.
	 *
	 * @var string
	 */
	const HOOK_ANALYTICS = 'html-notice-widget_page_html-notice-widget-analytics';

	/**
	 * Constructor — register the enqueue hook
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Enqueue assets based on the current admin page
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue( $hook ) {
		if ( self::HOOK_SETTINGS === $hook ) {
			$this->enqueue_settings_assets();
			return;
		}

		if ( self::HOOK_ANALYTICS === $hook ) {
			$this->enqueue_analytics_assets();
			return;
		}
	}

	/**
	 * Enqueue assets for the main settings page
	 */
	private function enqueue_settings_assets() {
		wp_enqueue_style(
			'html-notice-widget-admin',
			HTML_NOTICE_WIDGET_URL . 'assets/css/php-admin.css',
			[],
			HTML_NOTICE_WIDGET_VERSION
		);

		wp_enqueue_script(
			'html-notice-widget-admin',
			HTML_NOTICE_WIDGET_URL . 'assets/js/php-admin.js',
			[ 'jquery' ],
			HTML_NOTICE_WIDGET_VERSION,
			true
		);
	}

	/**
	 * Enqueue assets for the analytics page
	 */
	private function enqueue_analytics_assets() {
		wp_enqueue_style(
			'html-notice-widget-admin',
			HTML_NOTICE_WIDGET_URL . 'assets/css/php-admin.css',
			[],
			HTML_NOTICE_WIDGET_VERSION
		);

		wp_enqueue_script(
			'html-notice-widget-analytics',
			HTML_NOTICE_WIDGET_URL . 'assets/js/analytics-admin.js',
			[ 'jquery' ],
			HTML_NOTICE_WIDGET_VERSION,
			true
		);

		// Build product list for JS.
		$sites    = PHP_Utils::get_all_sites();
		$products = [];

		foreach ( $sites as $site ) {
			$products[] = [
				'product'   => $site['product'],
				'endpoint'  => $site['endpoint'],
				'campaigns' => count( $site['contents'] ?? [] ),
			];
		}

		wp_localize_script( 'html-notice-widget-analytics', 'hnwAnalytics', [
			'restUrl'     => esc_url_raw( rest_url( 'html-notice-widget/v1' ) ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'products'    => $products,
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'rollupNonce' => wp_create_nonce( 'hnw_rollup_nonce' ),
		] );
	}
}
