<?php
/**
 * Analytics Data Layer
 *
 * Two-table rollup architecture for campaign event tracking:
 * - Raw events table (write-heavy, pruned hourly)
 * - Summary table (read-heavy, permanent aggregates)
 *
 * @package HTML_Notice_Widget
 */

namespace HTML_Notice_Widget;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Analytics {

	/**
	 * Raw events table name (without prefix)
	 *
	 * @var string
	 */
	const RAW_TABLE = 'hnw_analytics_raw';

	/**
	 * Summary table name (without prefix)
	 *
	 * @var string
	 */
	const SUMMARY_TABLE = 'hnw_analytics_summary';

	/**
	 * Allowed event types
	 *
	 * @var array
	 */
	const EVENT_TYPES = [ 'impression', 'click', 'dismissal' ];

	/**
	 * Rate limit: max events per IP per minute
	 *
	 * @var int
	 */
	const RATE_LIMIT = 30;

	/**
	 * Rate limit window in seconds
	 *
	 * @var int
	 */
	const RATE_WINDOW = 60;

	/* =========================================================================
	   Table Creation
	   ========================================================================= */

	/**
	 * Create both analytics tables via dbDelta
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$raw_table       = $wpdb->prefix . self::RAW_TABLE;
		$summary_table   = $wpdb->prefix . self::SUMMARY_TABLE;

		$sql_raw = "CREATE TABLE {$raw_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_endpoint VARCHAR(191) NOT NULL,
			campaign_id VARCHAR(36) NOT NULL,
			event_type VARCHAR(20) NOT NULL,
			site_hash CHAR(32) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_created_at (created_at)
		) {$charset_collate};";

		$sql_summary = "CREATE TABLE {$summary_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_endpoint VARCHAR(191) NOT NULL,
			campaign_id VARCHAR(36) NOT NULL,
			event_date DATE NOT NULL,
			impressions INT UNSIGNED NOT NULL DEFAULT 0,
			clicks INT UNSIGNED NOT NULL DEFAULT 0,
			dismissals INT UNSIGNED NOT NULL DEFAULT 0,
			unique_sites INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY idx_campaign_date (product_endpoint, campaign_id, event_date),
			KEY idx_campaign (campaign_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_raw );
		dbDelta( $sql_summary );
	}

	/* =========================================================================
	   Event Recording (write path)
	   ========================================================================= */

	/**
	 * Record a single analytics event
	 *
	 * Applies rate limiting per IP and validates inputs before insert.
	 *
	 * @param string $endpoint   Product endpoint slug.
	 * @param string $campaign_id Campaign UUID.
	 * @param string $event_type  Event type (impression, click, dismissal).
	 * @param string $site_url    Origin site URL (hashed before storage).
	 * @return bool True on success, false on rate limit or validation failure.
	 */
	public static function record_event( $endpoint, $campaign_id, $event_type, $site_url ) {
		// Validate event type.
		if ( ! in_array( $event_type, self::EVENT_TYPES, true ) ) {
			return false;
		}

		// Sanitize inputs.
		$endpoint    = sanitize_key( $endpoint );
		$campaign_id = sanitize_text_field( $campaign_id );
		$site_hash   = md5( esc_url_raw( $site_url ) );

		if ( empty( $endpoint ) || empty( $campaign_id ) ) {
			return false;
		}

		// Rate limiting per IP.
		if ( ! self::check_rate_limit() ) {
			return false;
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . self::RAW_TABLE,
			[
				'product_endpoint' => $endpoint,
				'campaign_id'      => $campaign_id,
				'event_type'       => $event_type,
				'site_hash'        => $site_hash,
				'created_at'       => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%s', '%s', '%s' ]
		);

		return false !== $result;
	}

	/**
	 * Check rate limit for current request IP
	 *
	 * Uses transients with IP hash as key. Max RATE_LIMIT events per RATE_WINDOW seconds.
	 *
	 * @return bool True if under limit, false if exceeded.
	 */
	private static function check_rate_limit() {
		$ip_raw  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$ip_hash = md5( $ip_raw );
		$key     = 'hnw_rate_' . $ip_hash;

		$count = get_transient( $key );

		if ( false === $count ) {
			set_transient( $key, 1, self::RATE_WINDOW );
			return true;
		}

		if ( (int) $count >= self::RATE_LIMIT ) {
			return false;
		}

		set_transient( $key, (int) $count + 1, self::RATE_WINDOW );
		return true;
	}

	/* =========================================================================
	   Rollup & Pruning (cron)
	   ========================================================================= */

	/**
	 * Aggregate raw events into summary table and prune old raw rows
	 *
	 * Intended to be called hourly via WP-Cron.
	 * Groups raw events by (endpoint, campaign, date, event_type) and
	 * upserts into the summary table, then deletes raw rows older than 48h.
	 *
	 * @return array Result with rollup_count and pruned_count.
	 */
	public static function rollup_and_prune() {
		global $wpdb;

		$raw_table     = $wpdb->prefix . self::RAW_TABLE;
		$summary_table = $wpdb->prefix . self::SUMMARY_TABLE;
		$cutoff        = gmdate( 'Y-m-d H:i:s', time() - ( 48 * HOUR_IN_SECONDS ) );

		// Step 1: Aggregate raw → summary using INSERT … ON DUPLICATE KEY UPDATE.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rollup_sql = "
			INSERT INTO {$summary_table}
				(product_endpoint, campaign_id, event_date, impressions, clicks, dismissals, unique_sites)
			SELECT
				product_endpoint,
				campaign_id,
				DATE(created_at) AS event_date,
				SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END),
				SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END),
				SUM(CASE WHEN event_type = 'dismissal' THEN 1 ELSE 0 END),
				COUNT(DISTINCT site_hash)
			FROM {$raw_table}
			GROUP BY product_endpoint, campaign_id, DATE(created_at)
			ON DUPLICATE KEY UPDATE
				impressions  = VALUES(impressions),
				clicks       = VALUES(clicks),
				dismissals   = VALUES(dismissals),
				unique_sites = VALUES(unique_sites)
		";

		$rollup_count = $wpdb->query( $rollup_sql );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Step 2: Prune raw events older than 48 hours.
		$pruned_count = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$raw_table} WHERE created_at < %s",
				$cutoff
			)
		);

		return [
			'rollup_count' => $rollup_count,
			'pruned_count' => $pruned_count,
		];
	}

	/* =========================================================================
	   Query Methods (read path — all from summary table)
	   ========================================================================= */

	/**
	 * Get aggregate stats for a single campaign
	 *
	 * @param string $endpoint    Product endpoint slug.
	 * @param string $campaign_id Campaign UUID.
	 * @return array Associative array with impressions, clicks, dismissals, ctr, unique_sites.
	 */
	public static function get_campaign_stats( $endpoint, $campaign_id ) {
		global $wpdb;

		$summary_table = $wpdb->prefix . self::SUMMARY_TABLE;
		$defaults      = [
			'impressions'  => 0,
			'clicks'       => 0,
			'dismissals'   => 0,
			'ctr'          => 0,
			'unique_sites' => 0,
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COALESCE(SUM(impressions), 0)  AS impressions,
					COALESCE(SUM(clicks), 0)       AS clicks,
					COALESCE(SUM(dismissals), 0)   AS dismissals,
					MAX(unique_sites)               AS unique_sites
				FROM {$summary_table}
				WHERE product_endpoint = %s AND campaign_id = %s",
				sanitize_key( $endpoint ),
				sanitize_text_field( $campaign_id )
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return $defaults;
		}

		$impressions = (int) $row['impressions'];
		$clicks      = (int) $row['clicks'];

		return [
			'impressions'  => $impressions,
			'clicks'       => $clicks,
			'dismissals'   => (int) $row['dismissals'],
			'ctr'          => $impressions > 0 ? round( ( $clicks / $impressions ) * 100, 1 ) : 0,
			'unique_sites' => (int) $row['unique_sites'],
		];
	}

	/**
	 * Get daily stats for a campaign (last N days)
	 *
	 * Queries the summary table grouped by event_date, ordered newest first.
	 * Used by the detail panel to show a daily trend breakdown.
	 *
	 * @param string $endpoint    Product endpoint slug.
	 * @param string $campaign_id Campaign UUID.
	 * @param int    $days        Number of days to return (default 14).
	 * @return array Array of daily stat rows.
	 */
	public static function get_campaign_daily_stats( $endpoint, $campaign_id, $days = 14 ) {
		global $wpdb;

		$summary_table = $wpdb->prefix . self::SUMMARY_TABLE;
		$days          = absint( $days );

		if ( $days < 1 ) {
			$days = 14;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					event_date,
					impressions,
					clicks,
					dismissals,
					unique_sites
				FROM {$summary_table}
				WHERE product_endpoint = %s AND campaign_id = %s
				ORDER BY event_date DESC
				LIMIT %d",
				sanitize_key( $endpoint ),
				sanitize_text_field( $campaign_id ),
				$days
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return [];
		}

		$result = [];
		foreach ( $rows as $row ) {
			$imp = (int) $row['impressions'];
			$clk = (int) $row['clicks'];

			$result[] = [
				'date'         => $row['event_date'],
				'impressions'  => $imp,
				'clicks'       => $clk,
				'dismissals'   => (int) $row['dismissals'],
				'ctr'          => $imp > 0 ? round( ( $clk / $imp ) * 100, 1 ) : 0,
				'unique_sites' => (int) $row['unique_sites'],
			];
		}

		return $result;
	}

	/**
	 * Get per-campaign summary for a specific product
	 *
	 * @param string $endpoint Product endpoint slug.
	 * @return array Array of campaign stats, keyed by campaign_id.
	 */
	public static function get_product_summary( $endpoint ) {
		global $wpdb;

		$summary_table = $wpdb->prefix . self::SUMMARY_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					campaign_id,
					COALESCE(SUM(impressions), 0)  AS impressions,
					COALESCE(SUM(clicks), 0)       AS clicks,
					COALESCE(SUM(dismissals), 0)   AS dismissals,
					MAX(unique_sites)               AS unique_sites
				FROM {$summary_table}
				WHERE product_endpoint = %s
				GROUP BY campaign_id
				ORDER BY impressions DESC",
				sanitize_key( $endpoint )
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return [];
		}

		$result = [];
		foreach ( $rows as $row ) {
			$impressions = (int) $row['impressions'];
			$clicks      = (int) $row['clicks'];

			$result[ $row['campaign_id'] ] = [
				'campaign_id'  => $row['campaign_id'],
				'impressions'  => $impressions,
				'clicks'       => $clicks,
				'dismissals'   => (int) $row['dismissals'],
				'ctr'          => $impressions > 0 ? round( ( $clicks / $impressions ) * 100, 1 ) : 0,
				'unique_sites' => (int) $row['unique_sites'],
			];
		}

		return $result;
	}

	/**
	 * Get global totals across all products
	 *
	 * @return array Associative array with total impressions, clicks, dismissals, avg_ctr, unique_sites.
	 */
	public static function get_global_totals() {
		global $wpdb;

		$summary_table = $wpdb->prefix . self::SUMMARY_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			"SELECT
				COALESCE(SUM(impressions), 0) AS impressions,
				COALESCE(SUM(clicks), 0)      AS clicks,
				COALESCE(SUM(dismissals), 0)  AS dismissals
			FROM {$summary_table}",
			ARRAY_A
		);

		if ( ! $row ) {
			return [
				'impressions' => 0,
				'clicks'      => 0,
				'dismissals'  => 0,
				'avg_ctr'     => 0,
			];
		}

		$impressions = (int) $row['impressions'];
		$clicks      = (int) $row['clicks'];

		return [
			'impressions' => $impressions,
			'clicks'      => $clicks,
			'dismissals'  => (int) $row['dismissals'],
			'avg_ctr'     => $impressions > 0 ? round( ( $clicks / $impressions ) * 100, 1 ) : 0,
		];
	}

	/**
	 * Drop analytics tables (for uninstall)
	 */
	public static function drop_tables() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}" . self::RAW_TABLE );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}" . self::SUMMARY_TABLE );
	}
}
