<?php
/**
 * Options Helper Class
 *
 * @package HTML_Notice_Widget
 */

namespace HTML_Notice_Widget;

class Options {
	/**
	 * Get all sites
	 *
	 * @return array
	 */
	public static function get_sites() {
		return get_option( 'html_notice_widget_sites', [] );
	}

	/**
	 * Get a specific site by ID
	 *
	 * @param string $site_id Site ID.
	 * @return array|null
	 */
	public static function get_site( $site_id ) {
		$sites = self::get_sites();

		foreach ( $sites as $site ) {
			if ( $site['id'] === $site_id ) {
				return $site;
			}
		}

		return null;
	}

	/**
	 * Get a site by endpoint
	 *
	 * @param string $endpoint Endpoint slug.
	 * @return array|null
	 */
	public static function get_site_by_endpoint( $endpoint ) {
		$sites = self::get_sites();

		foreach ( $sites as $site ) {
			if ( $site['endpoint'] === $endpoint ) {
				return $site;
			}
		}

		return null;
	}

	/**
	 * Get enabled sites
	 *
	 * @return array
	 */
	public static function get_enabled_sites() {
		$sites = self::get_sites();

		return array_filter( $sites, function ( $site ) {
			return $site['enabled'];
		} );
	}

	/**
	 * Save sites
	 *
	 * @param array $sites Sites data.
	 * @return bool
	 */
	public static function save_sites( $sites ) {
		return update_option( 'html_notice_widget_sites', $sites );
	}
}

