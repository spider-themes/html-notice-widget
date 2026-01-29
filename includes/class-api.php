<?php

/**
 * API Class
 *
 * @package HTML_Notice_Widget
 */

namespace HTML_Notice_Widget;

class API
{
	/**
	 * Namespace
	 *
	 * @var string
	 */
	private $namespace = 'html-notice-widget/v1';

	/**
	 * Register REST API routes
	 */
	public function register_routes()
	{
		// Public endpoint for enabled site content - this is the main endpoint used by frontend
		register_rest_route(
			$this->namespace,
			'/content/(?P<endpoint>[a-zA-Z0-9\-]+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [$this, 'get_site_content'],
				'permission_callback' => '__return_true', // Public endpoint
				'args'                => [
					'endpoint' => [
						'required'    => true,
						'type'        => 'string',
						'description' => 'Product endpoint slug',
						'validate_callback' => function ($param, $request, $key) {
							return ! empty($param) && preg_match('/^[a-zA-Z0-9\-]+$/', $param);
						},
					],
				],
			]
		);
	}

	/**
	 * Get site content by endpoint - This is the main public API endpoint
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_site_content($request)
	{
		// Prevent caching
		nocache_headers();

		header('Cache-Control: no-cache, must-revalidate, max-age=0');
		header('Pragma: no-cache');
		header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
		$endpoint = sanitize_text_field($request->get_param('endpoint'));
		$sites    = get_option('html_notice_widget_sites', []);

		foreach ($sites as $site) {
			if ($site['endpoint'] === $endpoint && $site['enabled']) {
				// Get enabled contents
				$enabled_contents = [];
				if (isset($site['contents']) && is_array($site['contents'])) {
					foreach ($site['contents'] as $content) {
						// Check if content is enabled
						if (! isset($content['enabled']) || ! $content['enabled']) {
							continue;
						}

						// Check if content is within schedule
						if (! PHP_Utils::is_content_within_schedule($content)) {
							continue;
						}

						$enabled_contents[] = [
							'id' => $content['id'],
							'title' => $content['title'],
							'content' => $content['content'],
						];
					}
				}

				return rest_ensure_response([
					'success' => true,
					'contents' => $enabled_contents,
					'site'    => [
						'id'      => $site['id'],
						'product' => $site['product'],
					],
				]);
			}
		}

		return new \WP_Error('not_found', 'Site not found or not enabled', ['status' => 404]);
	}
}
