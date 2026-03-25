<?php
/**
 * Analytics Admin Page
 *
 * Renders the analytics submenu page and enqueues its assets.
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
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue analytics page assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'html-notice-widget_page_html-notice-widget-analytics' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'html-notice-widget-admin',
			HTML_NOTICE_WIDGET_URL . 'assets/css/php-admin.css',
			[],
			HTML_NOTICE_WIDGET_VERSION
		);

		wp_enqueue_script( 'jquery' );

		wp_enqueue_script(
			'html-notice-widget-analytics',
			HTML_NOTICE_WIDGET_URL . 'assets/js/analytics-admin.js',
			[ 'jquery' ],
			HTML_NOTICE_WIDGET_VERSION,
			true
		);

		// Pass data to JS.
		$sites    = PHP_Utils::get_all_sites();
		$products = [];

		foreach ( $sites as $site ) {
			$products[] = [
				'product'  => $site['product'],
				'endpoint' => $site['endpoint'],
				'campaigns' => count( $site['contents'] ?? [] ),
			];
		}

		wp_localize_script( 'html-notice-widget-analytics', 'hnwAnalytics', [
			'restUrl'  => esc_url_raw( rest_url( 'html-notice-widget/v1' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'products' => $products,
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
					<div class="hnw-stat-card__icon" style="background:var(--hnw-accent-light);color:var(--hnw-accent);"><?php echo PHP_Admin::svg_icon( 'check', 20 ); ?></div>
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

							<!-- 4 Metric Cards -->
							<div class="hnw-metric-grid">
								<div class="hnw-metric-card">
									<div class="hnw-metric-card__icon" style="background:var(--hnw-info-bg);color:var(--hnw-info);"><?php echo PHP_Admin::svg_icon( 'eye', 18 ); ?></div>
									<div class="hnw-metric-card__value" id="hnw-a-d-impressions">0</div>
									<div class="hnw-metric-card__label">Impressions</div>
								</div>
								<div class="hnw-metric-card">
									<div class="hnw-metric-card__icon" style="background:var(--hnw-success-bg);color:var(--hnw-success);"><?php echo PHP_Admin::svg_icon( 'mouse-pointer', 18 ); ?></div>
									<div class="hnw-metric-card__value" id="hnw-a-d-clicks">0</div>
									<div class="hnw-metric-card__label">Clicks</div>
								</div>
								<div class="hnw-metric-card">
									<div class="hnw-metric-card__icon" style="background:var(--hnw-error-bg);color:var(--hnw-error);"><?php echo PHP_Admin::svg_icon( 'x-circle', 18 ); ?></div>
									<div class="hnw-metric-card__value" id="hnw-a-d-dismissals">0</div>
									<div class="hnw-metric-card__label">Dismissals</div>
								</div>
								<div class="hnw-metric-card">
									<div class="hnw-metric-card__icon" style="background:var(--hnw-accent-light);color:var(--hnw-accent);"><?php echo PHP_Admin::svg_icon( 'package', 18 ); ?></div>
									<div class="hnw-metric-card__value" id="hnw-a-d-sites">0</div>
									<div class="hnw-metric-card__label">Unique Sites</div>
								</div>
							</div>

							<!-- CTR Bar -->
							<div class="hnw-ctr-section">
								<div class="hnw-ctr-section__header">
									<span class="hnw-ctr-section__label">Click-Through Rate</span>
									<span class="hnw-ctr-section__value" id="hnw-a-d-ctr">0%</span>
								</div>
								<div class="hnw-ctr-bar">
									<div class="hnw-ctr-bar__fill" id="hnw-a-d-ctr-bar" style="width:0%"></div>
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
