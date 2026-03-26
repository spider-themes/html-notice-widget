<?php
/**
 * API Class
 *
 * Public REST endpoints for content delivery and analytics tracking.
 * Admin REST endpoints for analytics stats.
 *
 * @package HTML_Notice_Widget
 */

namespace HTML_Notice_Widget;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class API {
	/**
	 * Namespace
	 *
	 * @var string
	 */
	private $namespace = 'html-notice-widget/v1';

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		// Public endpoint for enabled site content.
		register_rest_route(
			$this->namespace,
			'/content/(?P<endpoint>[a-zA-Z0-9\-]+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_site_content' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'endpoint' => [
						'required'          => true,
						'type'              => 'string',
						'description'       => 'Product endpoint slug',
						'validate_callback' => function ( $param ) {
							return ! empty( $param ) && preg_match( '/^[a-zA-Z0-9\-]+$/', $param );
						},
					],
				],
			]
		);

		// Public endpoint for analytics tracking (SDK beacons).
		register_rest_route(
			$this->namespace,
			'/analytics/track',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'track_event' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'endpoint'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => function ( $param ) {
							return ! empty( $param );
						},
					],
					'campaign_id' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return ! empty( $param );
						},
					],
					'event_type'  => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => function ( $param ) {
							return in_array( $param, Analytics::EVENT_TYPES, true );
						},
					],
					'site_url'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
						'validate_callback' => function ( $param ) {
							return ! empty( $param );
						},
					],
				],
			]
		);

		// Admin endpoint: product summary stats.
		register_rest_route(
			$this->namespace,
			'/analytics/summary/(?P<endpoint>[a-zA-Z0-9\-]+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_analytics_summary' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => [
					'endpoint' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);

		// Admin endpoint: single campaign detail stats.
		register_rest_route(
			$this->namespace,
			'/analytics/campaign/(?P<endpoint>[a-zA-Z0-9\-]+)/(?P<campaign_id>[a-zA-Z0-9\-]+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_analytics_campaign' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => [
					'endpoint'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
					'campaign_id' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Get site content by endpoint — main public API endpoint
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_site_content( $request ) {
		// Prevent caching.
		nocache_headers();

		header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
		header( 'Pragma: no-cache' );
		header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
		$endpoint = sanitize_text_field( $request->get_param( 'endpoint' ) );
		$sites    = get_option( 'html_notice_widget_sites', [] );

		foreach ( $sites as $site ) {
			if ( $site['endpoint'] === $endpoint && $site['enabled'] ) {
				// Get enabled contents.
				$enabled_contents = [];
				if ( isset( $site['contents'] ) && is_array( $site['contents'] ) ) {
					foreach ( $site['contents'] as $content ) {
						if ( ! isset( $content['enabled'] ) || ! $content['enabled'] ) {
							continue;
						}

						if ( ! PHP_Utils::is_content_within_schedule( $content ) ) {
							continue;
						}

						$enabled_contents[] = [
							'id'        => $content['id'],
							'title'     => $content['title'],
							'content'   => $content['content'],
							'targeting' => $content['targeting'] ?? [],
						];
					}
				}

				return rest_ensure_response( [
					'success'  => true,
					'contents' => $enabled_contents,
					'site'     => [
						'id'      => $site['id'],
						'product' => $site['product'],
					],
				] );
			}
		}

		return new \WP_Error( 'not_found', 'Site not found or not enabled', [ 'status' => 404 ] );
	}

	/**
	 * Track an analytics event (public beacon endpoint)
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function track_event( $request ) {
		$endpoint    = $request->get_param( 'endpoint' );
		$campaign_id = $request->get_param( 'campaign_id' );
		$event_type  = $request->get_param( 'event_type' );
		$site_url    = $request->get_param( 'site_url' );

		$recorded = Analytics::record_event( $endpoint, $campaign_id, $event_type, $site_url );

		// Always return success to avoid leaking rate-limit info.
		return rest_ensure_response( [ 'success' => true ] );
	}

	/**
	 * Get analytics summary for a product (admin only)
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function get_analytics_summary( $request ) {
		$endpoint = $request->get_param( 'endpoint' );

		// Try object cache first.
		$cache_key = 'hnw_analytics_summary_' . $endpoint;
		$cached    = wp_cache_get( $cache_key, 'hnw_analytics' );

		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		$summary = Analytics::get_product_summary( $endpoint );

		// Enrich with campaign metadata.
		$sites    = get_option( 'html_notice_widget_sites', [] );
		$campaigns = [];

		foreach ( $sites as $site ) {
			if ( $site['endpoint'] !== $endpoint || ! isset( $site['contents'] ) ) {
				continue;
			}

			foreach ( $site['contents'] as $content ) {
				$stats = $summary[ $content['id'] ] ?? [
					'impressions'  => 0,
					'clicks'       => 0,
					'dismissals'   => 0,
					'ctr'          => 0,
					'unique_sites' => 0,
				];

				$campaigns[] = [
					'campaign_id' => $content['id'],
					'title'       => $content['title'],
					'enabled'     => ! empty( $content['enabled'] ),
					'stats'       => $stats,
				];
			}
			break;
		}

		$result = [
			'success'   => true,
			'endpoint'  => $endpoint,
			'campaigns' => $campaigns,
		];

		wp_cache_set( $cache_key, $result, 'hnw_analytics', 300 );

		return rest_ensure_response( $result );
	}

	/**
	 * Get detailed analytics for a single campaign (admin only)
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function get_analytics_campaign( $request ) {
		$endpoint    = $request->get_param( 'endpoint' );
		$campaign_id = $request->get_param( 'campaign_id' );

		// Try object cache first.
		$cache_key = 'hnw_analytics_campaign_' . $endpoint . '_' . $campaign_id;
		$cached    = wp_cache_get( $cache_key, 'hnw_analytics' );

		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		$stats = Analytics::get_campaign_stats( $endpoint, $campaign_id );
		$daily = Analytics::get_campaign_daily_stats( $endpoint, $campaign_id, 14 );

		$result = [
			'success'     => true,
			'endpoint'    => $endpoint,
			'campaign_id' => $campaign_id,
			'stats'       => $stats,
			'daily'       => $daily,
		];

		wp_cache_set( $cache_key, $result, 'hnw_analytics', 300 );

		return rest_ensure_response( $result );
	}
}
