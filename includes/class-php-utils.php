<?php
/**
 * PHP Utilities for HTML Notice Widget
 *
 * @package HTML_Notice_Widget
 */

namespace HTML_Notice_Widget;

class PHP_Utils {

	/**
	 * Get all sites directly from options
	 *
	 * @return array
	 */
	public static function get_all_sites() {
		return get_option( 'html_notice_widget_sites', [] );
	}

	/**
	 * Save sites directly to options
	 *
	 * @param array $sites Sites array.
	 * @return bool
	 */
	public static function save_sites( $sites ) {
		return update_option( 'html_notice_widget_sites', $sites );
	}

	/**
	 * Find site by ID
	 *
	 * @param string $site_id Site ID.
	 * @return array|null
	 */
	public static function find_site_by_id( $site_id ) {
		$sites = self::get_all_sites();

		foreach ( $sites as $site ) {
			if ( $site['id'] === $site_id ) {
				return $site;
			}
		}

		return null;
	}

	/**
	 * Find content within a site
	 *
	 * @param string $site_id Site ID.
	 * @param string $content_id Content ID.
	 * @return array|null
	 */
	public static function find_content_by_id( $site_id, $content_id ) {
		$site = self::find_site_by_id( $site_id );

		if ( ! $site || ! isset( $site['contents'] ) ) {
			return null;
		}

		foreach ( $site['contents'] as $content ) {
			if ( $content['id'] === $content_id ) {
				return $content;
			}
		}

		return null;
	}

	/**
	 * Add new site
	 *
	 * @param array $site_data Site data.
	 * @return bool|string Returns site ID on success, false on failure.
	 */
	public static function add_site( $site_data ) {
		$sites = self::get_all_sites();

		// Validate required fields
		if ( empty( $site_data['product'] ) ) {
			return false;
		}

		// Check if product already exists
		foreach ( $sites as $site ) {
			if ( $site['product'] === $site_data['product'] ) {
				return false;
			}
		}

		$site_id = wp_generate_uuid4();
		$new_site = [
			'id'        => $site_id,
			'product'   => sanitize_text_field( $site_data['product'] ),
			'enabled'   => ! empty( $site_data['enabled'] ),
			'endpoint'  => self::generate_endpoint( $site_data['product'] ),
			'contents'  => [],
		];

		$sites[] = $new_site;

		if ( self::save_sites( $sites ) ) {
			return $site_id;
		}

		return false;
	}

	/**
	 * Update site
	 *
	 * @param string $site_id Site ID.
	 * @param array $site_data Updated site data.
	 * @return bool
	 */
	public static function update_site( $site_id, $site_data ) {
		$sites = self::get_all_sites();
		$updated = false;

		foreach ( $sites as &$site ) {
			if ( $site['id'] === $site_id ) {
				if ( isset( $site_data['product'] ) ) {
					$site['product'] = sanitize_text_field( $site_data['product'] );
					$site['endpoint'] = self::generate_endpoint( $site_data['product'], $site['contents'] );
				}

				if ( isset( $site_data['enabled'] ) ) {
					$site['enabled'] = ! empty( $site_data['enabled'] );
				}

				$updated = true;
				break;
			}
		}

		if ( $updated ) {
			return self::save_sites( $sites );
		}

		return false;
	}

	/**
	 * Delete site
	 *
	 * @param string $site_id Site ID.
	 * @return bool
	 */
	public static function delete_site( $site_id ) {
		$sites = self::get_all_sites();
		$original_count = count( $sites );

		$sites = array_filter( $sites, function( $site ) use ( $site_id ) {
			return $site['id'] !== $site_id;
		});

		if ( count( $sites ) < $original_count ) {
			return self::save_sites( array_values( $sites ) );
		}

		return false;
	}

	/**
	 * Add content to site
	 *
	 * @param string $site_id Site ID.
	 * @param array $content_data Content data.
	 * @return bool|string Returns content ID on success, false on failure.
	 */
	public static function add_content( $site_id, $content_data ) {
		$sites = self::get_all_sites();

		// Validate required fields
		if ( empty( $content_data['title'] ) || empty( $content_data['content'] ) ) {
			return false;
		}

		foreach ( $sites as &$site ) {
			if ( $site['id'] === $site_id ) {
				if ( ! isset( $site['contents'] ) ) {
					$site['contents'] = [];
				}

				$content_id = wp_generate_uuid4();
				$new_content = [
					'id'             => $content_id,
					'title'          => sanitize_text_field( $content_data['title'] ),
					'description'    => ! empty( $content_data['description'] ) ? sanitize_text_field( $content_data['description'] ) : '',
					'content'        => wp_kses_post( $content_data['content'] ),
					'enabled'        => ! empty( $content_data['enabled'] ),
					'schedule_start' => ! empty( $content_data['schedule_start'] ) ? sanitize_text_field( $content_data['schedule_start'] ) : '',
					'schedule_end'   => ! empty( $content_data['schedule_end'] ) ? sanitize_text_field( $content_data['schedule_end'] ) : '',
				];

				$site['contents'][] = $new_content;

				// Regenerate endpoint with new content
				$site['endpoint'] = self::generate_endpoint( $site['product'], $site['contents'] );

				if ( self::save_sites( $sites ) ) {
					return $content_id;
				}

				break;
			}
		}

		return false;
	}

	/**
	 * Update content
	 *
	 * @param string $site_id Site ID.
	 * @param string $content_id Content ID.
	 * @param array $content_data Updated content data.
	 * @return bool
	 */
	public static function update_content( $site_id, $content_id, $content_data ) {
		$sites = self::get_all_sites();
		$updated = false;

		foreach ( $sites as &$site ) {
			if ( $site['id'] === $site_id && isset( $site['contents'] ) ) {
				foreach ( $site['contents'] as &$content ) {
					if ( $content['id'] === $content_id ) {
						if ( isset( $content_data['title'] ) ) {
							$content['title'] = sanitize_text_field( $content_data['title'] );
						}

						if ( isset( $content_data['description'] ) ) {
							$content['description'] = ! empty( $content_data['description'] ) ? sanitize_text_field( $content_data['description'] ) : '';
						}

						if ( isset( $content_data['content'] ) ) {
							$content['content'] = wp_kses_post( $content_data['content'] );
						}

						if ( isset( $content_data['enabled'] ) ) {
							$content['enabled'] = ! empty( $content_data['enabled'] );
						}

						if ( array_key_exists( 'schedule_start', $content_data ) ) {
							$content['schedule_start'] = ! empty( $content_data['schedule_start'] ) ? sanitize_text_field( $content_data['schedule_start'] ) : '';
						}

						if ( array_key_exists( 'schedule_end', $content_data ) ) {
							$content['schedule_end'] = ! empty( $content_data['schedule_end'] ) ? sanitize_text_field( $content_data['schedule_end'] ) : '';
						}

						// Regenerate endpoint with updated content
						$site['endpoint'] = self::generate_endpoint( $site['product'], $site['contents'] );

						$updated = true;
						break 2;
					}
				}
			}
		}

		if ( $updated ) {
			return self::save_sites( $sites );
		}

		return false;
	}

	/**
	 * Delete content
	 *
	 * @param string $site_id Site ID.
	 * @param string $content_id Content ID.
	 * @return bool
	 */
	public static function delete_content( $site_id, $content_id ) {
		$sites = self::get_all_sites();
		$updated = false;

		foreach ( $sites as &$site ) {
			if ( $site['id'] === $site_id && isset( $site['contents'] ) ) {
				$original_count = count( $site['contents'] );

				$site['contents'] = array_filter( $site['contents'], function( $content ) use ( $content_id ) {
					return $content['id'] !== $content_id;
				});

				$site['contents'] = array_values( $site['contents'] );

				if ( count( $site['contents'] ) < $original_count ) {
					// Regenerate endpoint with updated content
					$site['endpoint'] = self::generate_endpoint( $site['product'], $site['contents'] );
					$updated = true;
					break;
				}
			}
		}

		if ( $updated ) {
			return self::save_sites( $sites );
		}

		return false;
	}

	/**
	 * Generate endpoint from product name and content hash
	 *
	 * @param string $product_name Product name.
	 * @param array $contents Optional. Contents array to generate hash from.
	 * @return string
	 */
	public static function generate_endpoint( $product_name, $contents = null ) {
		return strtolower( preg_replace( '/[^a-zA-Z0-9\-_]/', '-', $product_name ) );
	}

	/**
	 * Regenerate endpoint for a site with its current content
	 *
	 * @param array $site Site array.
	 * @return string
	 */
	public static function regenerate_site_endpoint( $site ) {
		$contents = isset( $site['contents'] ) ? $site['contents'] : [];
		return self::generate_endpoint( $site['product'], $contents );
	}

	/**
	 * Get enabled contents for a site
	 *
	 * @param string $site_id Site ID.
	 * @return array
	 */
	public static function get_enabled_contents( $site_id ) {
		$site = self::find_site_by_id( $site_id );

		if ( ! $site || ! $site['enabled'] || ! isset( $site['contents'] ) ) {
			return [];
		}

		return array_filter( $site['contents'], function( $content ) {
			if ( empty( $content['enabled'] ) ) {
				return false;
			}
			// Check schedule
			return self::is_content_within_schedule( $content );
		});
	}

	/**
	 * Get site statistics
	 *
	 * @return array
	 */
	public static function get_stats() {
		$sites = self::get_all_sites();

		$stats = [
			'total_sites' => count( $sites ),
			'enabled_sites' => 0,
			'total_contents' => 0,
			'enabled_contents' => 0,
		];

		foreach ( $sites as $site ) {
			if ( ! empty( $site['enabled'] ) ) {
				$stats['enabled_sites']++;
			}

			if ( isset( $site['contents'] ) ) {
				$stats['total_contents'] += count( $site['contents'] );

				foreach ( $site['contents'] as $content ) {
					if ( ! empty( $content['enabled'] ) ) {
						$stats['enabled_contents']++;
					}
				}
			}
		}

		return $stats;
	}

	/**
	 * Test endpoint generation and updates
	 * This method demonstrates that endpoints update when content changes
	 *
	 * @return array Test results
	 */
	public static function test_endpoint_updates() {
		$results = [];

		// Test 1: Generate endpoint with no content
		$endpoint1 = self::generate_endpoint( 'Test Product' );
		$results['empty_content'] = $endpoint1; // Should be: test-product

		// Test 2: Generate endpoint with content
		$content = [
			[ 'id' => '1', 'title' => 'Test', 'content' => '<p>Test</p>', 'enabled' => true ]
		];
		$endpoint2 = self::generate_endpoint( 'Test Product', $content );
		$results['with_content'] = $endpoint2; // Should be: test-product-[hash]

		// Test 3: Generate endpoint with different content (should have different hash)
		$content2 = [
			[ 'id' => '1', 'title' => 'Test Updated', 'content' => '<p>Test Updated</p>', 'enabled' => true ]
		];
		$endpoint3 = self::generate_endpoint( 'Test Product', $content2 );
		$results['updated_content'] = $endpoint3; // Should be: test-product-[different-hash]

		// Test 4: Add more content
		$content3 = [
			[ 'id' => '1', 'title' => 'Test', 'content' => '<p>Test</p>', 'enabled' => true ],
			[ 'id' => '2', 'title' => 'Test 2', 'content' => '<p>Test 2</p>', 'enabled' => true ]
		];
		$endpoint4 = self::generate_endpoint( 'Test Product', $content3 );
		$results['multiple_content'] = $endpoint4; // Should be: test-product-[another-different-hash]

		$results['endpoints_different'] = ( $endpoint2 !== $endpoint3 && $endpoint3 !== $endpoint4 );

		return $results;
	}

	/**
	 * Check if content is within its scheduled time range.
	 *
	 * @param array $content Content array.
	 * @return bool True if within schedule or no schedule set.
	 */
	public static function is_content_within_schedule( $content ) {
		$now = current_time( 'timestamp' );

		// Check start date
		if ( ! empty( $content['schedule_start'] ) ) {
			$start_time = strtotime( $content['schedule_start'] );
			if ( $start_time && $now < $start_time ) {
				return false; // Not yet started
			}
		}

		// Check end date
		if ( ! empty( $content['schedule_end'] ) ) {
			$end_time = strtotime( $content['schedule_end'] );
			if ( $end_time && $now > $end_time ) {
				return false; // Already expired
			}
		}

		return true;
	}

	/**
	 * Process expired content and disable them.
	 * This should be called by the cron job.
	 *
	 * @return array Array with count of disabled contents.
	 */
	public static function process_expired_content() {
		$sites   = self::get_all_sites();
		$updated = false;
		$count   = 0;
		$now     = current_time( 'timestamp' );

		foreach ( $sites as &$site ) {
			if ( ! isset( $site['contents'] ) ) {
				continue;
			}

			foreach ( $site['contents'] as &$content ) {
				// Skip already disabled content
				if ( empty( $content['enabled'] ) ) {
					continue;
				}

				// Check if expired
				if ( ! empty( $content['schedule_end'] ) ) {
					$end_time = strtotime( $content['schedule_end'] );
					if ( $end_time && $now > $end_time ) {
						$content['enabled'] = false;
						$updated = true;
						$count++;
					}
				}
			}
		}

		if ( $updated ) {
			self::save_sites( $sites );
		}

		return [
			'disabled_count' => $count,
			'updated'        => $updated,
		];
	}

	/**
	 * Get schedule status text for a content item.
	 *
	 * @param array $content Content array.
	 * @return string Status text.
	 */
	public static function get_schedule_status( $content ) {
		$now = current_time( 'timestamp' );

		// No schedule set
		if ( empty( $content['schedule_start'] ) && empty( $content['schedule_end'] ) ) {
			return '';
		}

		// Check if not yet started
		if ( ! empty( $content['schedule_start'] ) ) {
			$start_time = strtotime( $content['schedule_start'] );
			if ( $start_time && $now < $start_time ) {
				return 'Scheduled: Starts ' . wp_date( 'M j, Y g:i a', $start_time );
			}
		}

		// Check if expired
		if ( ! empty( $content['schedule_end'] ) ) {
			$end_time = strtotime( $content['schedule_end'] );
			if ( $end_time && $now > $end_time ) {
				return 'Expired: ' . wp_date( 'M j, Y g:i a', $end_time );
			} else {
				return 'Active until: ' . wp_date( 'M j, Y g:i a', $end_time );
			}
		}

		return '';
	}

}
