<?php
/**
 * Analytics Admin Page for HTML Notice Widget
 *
 * @package HTML_Notice_Widget
 */

namespace HTML_Notice_Widget;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Analytics_Admin {

	/**
	 * Constructor — register hooks
	 */
	public function __construct() {
		add_action( 'wp_ajax_hnw_trigger_rollup', [ $this, 'ajax_trigger_rollup' ] );
	}

	/**
	 * AJAX handler — manually trigger analytics rollup
	 */
	public function ajax_trigger_rollup() {
		if ( ! check_ajax_referer( 'hnw_rollup_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid token.', 'html-notice-widget' ) ] );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'html-notice-widget' ) ] );
		}

		$result = Analytics::rollup_and_prune();

		wp_send_json_success( [
			'message'      => __( 'Rollup complete.', 'html-notice-widget' ),
			'rollup_count' => $result['rollup_count'] ?? 0,
			'pruned_count' => $result['pruned_count'] ?? 0,
		] );
	}

	/**
	 * Render the analytics page
	 */
	public function render_analytics_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'html-notice-widget' ) );
		}

		$sites  = PHP_Utils::get_all_sites();
		$totals = Analytics::get_global_totals();
		$active = PHP_Utils::get_stats();
		?>
		<div class="hnw-wrap">

			<!-- Hero Header -->
			<div class="hnw-hero">
				<div class="hnw-hero__info">
					<span class="hnw-hero__icon"><?php echo PHP_Admin::svg_icon( 'bar-chart', 38 ); ?></span>
					<div>
						<h1 class="hnw-hero__title">Campaign Analytics</h1>
						<p class="hnw-hero__subtitle">Track impressions, clicks & dismissals across your products.</p>
					</div>
				</div>
				<div class="hnw-hero__actions">
					<button type="button" class="hnw-btn hnw-btn--hero" id="hnw-a-refresh-btn"
						data-hnw-tooltip="Aggregate raw analytics into daily summaries" data-tooltip-pos="bottom"><?php echo PHP_Admin::svg_icon( 'refresh', 14 ); ?> Refresh Data</button>
				</div>
			</div>

			<!-- Global Stats Ribbon -->
			<div class="hnw-stats">
				<div class="hnw-stat-card">
					<div class="hnw-stat-card__icon" style="background:var(--hnw-info-bg);color:var(--hnw-info);"><?php echo PHP_Admin::svg_icon( 'eye', 20 ); ?></div>
					<div>
						<div class="hnw-stat-card__value" id="hnw-a-total-impressions"><?php echo absint( $totals['impressions'] ); ?></div>
						<div class="hnw-stat-card__label">Impressions</div>
					</div>
				</div>
				<div class="hnw-stat-card">
					<div class="hnw-stat-card__icon" style="background:var(--hnw-success-bg);color:var(--hnw-success);"><?php echo PHP_Admin::svg_icon( 'mouse-pointer', 20 ); ?></div>
					<div>
						<div class="hnw-stat-card__value" id="hnw-a-total-clicks"><?php echo absint( $totals['clicks'] ); ?></div>
						<div class="hnw-stat-card__label">Clicks</div>
					</div>
				</div>
				<div class="hnw-stat-card">
					<div class="hnw-stat-card__icon" style="background:var(--hnw-brand-bg);color:var(--hnw-brand);"><?php echo PHP_Admin::svg_icon( 'info', 20 ); ?></div>
					<div>
						<div class="hnw-stat-card__value" id="hnw-a-avg-ctr"><?php echo esc_html( $totals['avg_ctr'] ); ?>%</div>
						<div class="hnw-stat-card__label">Avg CTR</div>
					</div>
				</div>
				<div class="hnw-stat-card">
					<div class="hnw-stat-card__icon" style="background:var(--hnw-success-bg);color:var(--hnw-success);"><?php echo PHP_Admin::svg_icon( 'check', 20 ); ?></div>
					<div>
						<div class="hnw-stat-card__value"><?php echo absint( $active['enabled_contents'] ); ?></div>
						<div class="hnw-stat-card__label">Active Campaigns</div>
					</div>
				</div>
			</div>

			<?php if ( empty( $sites ) ) : ?>
				<!-- Empty State -->
				<div class="hnw-empty">
					<div class="hnw-empty__icon"><?php echo PHP_Admin::svg_icon( 'bar-chart', 48 ); ?></div>
					<h2 class="hnw-empty__title">No products yet</h2>
					<p class="hnw-empty__text">Create products and campaigns first, then analytics data will appear here.</p>
				</div>
			<?php else : ?>
				<!-- Two-column layout: sidebar + content -->
				<div class="hnw-analytics-layout">
					<!-- Product Sidebar -->
					<div class="hnw-sidebar">
						<h3 class="hnw-sidebar__title">Products</h3>
						<ul class="hnw-sidebar__list">
							<?php foreach ( $sites as $index => $site ) :
								$count = count( $site['contents'] ?? [] );
							?>
								<li class="hnw-sidebar-item<?php echo 0 === $index ? ' hnw-sidebar-item--active' : ''; ?>"
									data-endpoint="<?php echo esc_attr( $site['endpoint'] ); ?>"
									data-product="<?php echo esc_attr( $site['product'] ); ?>">
									<span class="hnw-sidebar-item__name"><?php echo esc_html( $site['product'] ); ?></span>
									<span class="hnw-badge hnw-badge--enabled"><?php echo absint( $count ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>

					<!-- Main Content Panel -->
					<div class="hnw-analytics-content">
						<!-- Campaign list (loaded via AJAX) -->
						<div class="hnw-toolbar">
							<h2 class="hnw-toolbar__title" id="hnw-a-product-title"><?php echo esc_html( $sites[0]['product'] ?? '' ); ?> — Campaigns</h2>
						</div>

						<div id="hnw-a-campaign-list" class="hnw-analytics-campaigns">
							<div class="hnw-analytics-loading">
								<div class="hnw-skeleton hnw-skeleton--row"></div>
								<div class="hnw-skeleton hnw-skeleton--row"></div>
								<div class="hnw-skeleton hnw-skeleton--row"></div>
							</div>
						</div>

						<!-- Campaign Detail Panel (hidden by default) -->
						<div id="hnw-a-detail-panel" class="hnw-detail-panel" style="display:none;">
						<div class="hnw-detail-panel__header">
						<h3 class="hnw-detail-panel__title" id="hnw-a-detail-title"></h3>
						<button type="button" class="hnw-btn hnw-btn--ghost hnw-btn--sm" id="hnw-a-detail-close">&times; Close</button>
						</div>
						
						<!-- Daily Breakdown (populated via JS) -->
						<div class="hnw-daily-section">
						<h4 class="hnw-daily-section__title">Daily Breakdown <span class="hnw-daily-section__hint">(Last 14 days)</span></h4>
						<div id="hnw-a-daily-table">
						<p class="hnw-daily-empty">Click a campaign to see daily data.</p>
						</div>
						</div>
						</div>

					</div>
				</div>
			<?php endif; ?>

		</div><!-- .hnw-wrap -->
		<?php
	}
}
