<?php
/**
 * PHP Admin Interface for HTML Notice Widget
 *
 * @package HTML_Notice_Widget
 */

namespace HTML_Notice_Widget;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHP_Admin {
	/**
	 * Constructor — register hooks
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'handle_form_submissions' ] );
		add_action( 'wp_ajax_hnw_download_sdk', [ $this, 'ajax_download_sdk' ] );
	}

	/* =========================================================================
	   Form Handlers
	   ========================================================================= */

	/**
	 * Handle all form submissions
	 */
	public function handle_form_submissions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['action'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'html_notice_widget_action' ) ) {
			switch ( $_POST['action'] ) {
				case 'add_site':
					$this->handle_add_site();
					break;
				case 'edit_site':
					$this->handle_edit_site();
					break;
				case 'delete_site':
					$this->handle_delete_site();
					break;
				case 'add_content':
					$this->handle_add_content();
					break;
				case 'edit_content':
					$this->handle_edit_content();
					break;
				case 'delete_content':
					$this->handle_delete_content();
					break;
			}
		}
	}

	/**
	 * Prepare campaign HTML for storage.
	 *
	 * Campaign content is authored by trusted admins (manage_options).
	 * We only strip WP magic quotes — no tag filtering, so inline CSS,
	 * style blocks, and full HTML are preserved exactly as entered.
	 *
	 * @param string $raw_html Raw HTML from form input.
	 * @return string Unslashed HTML ready for storage.
	 */
	private function sanitize_campaign_html( $raw_html ) {
		return wp_unslash( $raw_html );
	}

	/**
	 * Handle add site
	 */
	private function handle_add_site() {
		$product = sanitize_text_field( $_POST['product'] ?? '' );
		$enabled = 1; // Always enabled per requirements

		if ( empty( $product ) ) {
			$this->add_admin_notice( 'Product name is required.', 'error' );
			return;
		}

		$site_id = PHP_Utils::add_site([
			'product' => $product,
			'enabled' => $enabled,
		]);

		if ( $site_id ) {
			$this->add_admin_notice( 'Product created successfully!', 'success' );
			wp_redirect( remove_query_arg( ['action', 'site_id', 'content_id'] ) );
			exit;
		} else {
			$this->add_admin_notice( 'Failed to create Product. Product may already exist.', 'error' );
		}
	}

	/**
	 * Handle edit site
	 */
	private function handle_edit_site() {
		$site_id = sanitize_text_field( $_POST['site_id'] ?? '' );
		$product = sanitize_text_field( $_POST['product'] ?? '' );
		$enabled = 1; // Always enabled per requirements

		if ( empty( $site_id ) || empty( $product ) ) {
			$this->add_admin_notice( 'Site ID and product name are required.', 'error' );
			return;
		}

		if ( PHP_Utils::update_site( $site_id, [
			'product' => $product,
			'enabled' => $enabled,
		])) {
			$this->add_admin_notice( 'Product updated successfully!', 'success' );
			wp_redirect( remove_query_arg( ['action', 'site_id', 'content_id'] ) );
			exit;
		} else {
			$this->add_admin_notice( 'Failed to update Product.', 'error' );
		}
	}

	/**
	 * Handle delete site
	 */
	private function handle_delete_site() {
		$site_id = sanitize_text_field( $_POST['site_id'] ?? '' );

		if ( empty( $site_id ) ) {
			$this->add_admin_notice( 'Site ID is required.', 'error' );
			return;
		}

		if ( PHP_Utils::delete_site( $site_id ) ) {
			$this->add_admin_notice( 'Product deleted successfully!', 'success' );
		} else {
			$this->add_admin_notice( 'Failed to delete product.', 'error' );
		}
	}

	/**
	 * Handle add content
	 */
	private function handle_add_content() {
		$site_id     = sanitize_text_field( $_POST['site_id'] ?? '' );
		$title       = sanitize_text_field( $_POST['content_title'] ?? '' );
		$description = sanitize_text_field( $_POST['content_description'] ?? '' );
		$content     = $this->sanitize_campaign_html( $_POST['content_html'] ?? '' );
		$enabled     = isset( $_POST['content_enabled'] ) ? 1 : 0;

		if ( empty( $site_id ) || empty( $title ) || empty( $content ) ) {
			$this->add_admin_notice( 'Product ID, title, and content are required.', 'error' );
			return;
		}

		$content_id = PHP_Utils::add_content( $site_id, [
			'title'          => $title,
			'description'    => $description,
			'content'        => $content,
			'enabled'        => $enabled,
			'schedule_start' => isset( $_POST['schedule_start'] ) ? sanitize_text_field( $_POST['schedule_start'] ) : '',
			'schedule_end'   => isset( $_POST['schedule_end'] ) ? sanitize_text_field( $_POST['schedule_end'] ) : '',
			'targeting'      => [
				'pro_users'      => sanitize_text_field( $_POST['targeting_pro_users'] ?? 'all' ),
				'plugin_version' => [
					'operator' => sanitize_text_field( $_POST['targeting_version_op'] ?? '' ),
					'version'  => sanitize_text_field( $_POST['targeting_version'] ?? '' ),
				],
				'user_roles'     => isset( $_POST['targeting_roles'] ) && is_array( $_POST['targeting_roles'] )
					? array_map( 'sanitize_key', $_POST['targeting_roles'] )
					: [],
			],
		]);

		if ( $content_id ) {
			$this->add_admin_notice( 'Content added successfully!', 'success' );
			wp_redirect( remove_query_arg( ['action', 'site_id', 'content_id'] ) );
			exit;
		} else {
			$this->add_admin_notice( 'Failed to add content.', 'error' );
		}
	}

	/**
	 * Handle edit content
	 */
	private function handle_edit_content() {
		$site_id     = sanitize_text_field( $_POST['site_id'] ?? '' );
		$content_id  = sanitize_text_field( $_POST['content_id'] ?? '' );
		$title       = sanitize_text_field( $_POST['content_title'] ?? '' );
		$description = sanitize_text_field( $_POST['content_description'] ?? '' );
		$content     = $this->sanitize_campaign_html( $_POST['content_html'] ?? '' );
		$enabled     = isset( $_POST['content_enabled'] ) ? 1 : 0;

		if ( empty( $site_id ) || empty( $content_id ) || empty( $title ) || empty( $content ) ) {
			$this->add_admin_notice( 'All fields are required.', 'error' );
			return;
		}

		if ( PHP_Utils::update_content( $site_id, $content_id, [
			'title'          => $title,
			'description'    => $description,
			'content'        => $content,
			'enabled'        => $enabled,
			'schedule_start' => isset( $_POST['schedule_start'] ) ? sanitize_text_field( $_POST['schedule_start'] ) : '',
			'schedule_end'   => isset( $_POST['schedule_end'] ) ? sanitize_text_field( $_POST['schedule_end'] ) : '',
			'targeting'      => [
				'pro_users'      => sanitize_text_field( $_POST['targeting_pro_users'] ?? 'all' ),
				'plugin_version' => [
					'operator' => sanitize_text_field( $_POST['targeting_version_op'] ?? '' ),
					'version'  => sanitize_text_field( $_POST['targeting_version'] ?? '' ),
				],
				'user_roles'     => isset( $_POST['targeting_roles'] ) && is_array( $_POST['targeting_roles'] )
					? array_map( 'sanitize_key', $_POST['targeting_roles'] )
					: [],
			],
		])) {
			$this->add_admin_notice( 'Content updated successfully!', 'success' );
			wp_redirect( remove_query_arg( ['action', 'site_id', 'content_id'] ) );
			exit;
		} else {
			$this->add_admin_notice( 'Failed to update content.', 'error' );
		}
	}

	/**
	 * Handle delete content
	 */
	private function handle_delete_content() {
		$site_id    = sanitize_text_field( $_POST['site_id'] ?? '' );
		$content_id = sanitize_text_field( $_POST['content_id'] ?? '' );

		if ( empty( $site_id ) || empty( $content_id ) ) {
			$this->add_admin_notice( 'Site ID and Content ID are required.', 'error' );
			return;
		}

		if ( PHP_Utils::delete_content( $site_id, $content_id ) ) {
			$this->add_admin_notice( 'Content deleted successfully!', 'success' );
		} else {
			$this->add_admin_notice( 'Failed to delete content.', 'error' );
		}
	}

	/* =========================================================================
	   Render — Main Page
	   ========================================================================= */

	/**
	 * Render the admin page
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'html-notice-widget' ) );
		}

		$sites = PHP_Utils::get_all_sites();
		$stats = PHP_Utils::get_stats();
		?>
		<div class="hnw-wrap">

			<?php $this->render_admin_notices(); ?>

			<!-- Hero Header -->
			<div class="hnw-hero">
				<div class="hnw-hero__info">
					<span class="hnw-hero__icon"><?php echo self::svg_icon( 'megaphone', 38 ); ?></span>
					<div>
						<h1 class="hnw-hero__title">HTML Notice Widget</h1>
						<p class="hnw-hero__subtitle">Centrally manage and distribute HTML notices across your WordPress products.</p>
					</div>
				</div>
				<div class="hnw-hero__actions">
					<button type="button" class="hnw-btn hnw-btn--hero" id="hnw-add-product-btn"><?php echo self::svg_icon( 'plus', 14 ); ?> Add Product</button>
				</div>
			</div>

			<!-- Stats Ribbon -->
			<div class="hnw-stats">
				<div class="hnw-stat-card">
					<div class="hnw-stat-card__icon" style="background:var(--hnw-brand-bg);color:var(--hnw-brand);"><?php echo self::svg_icon( 'package', 20 ); ?></div>
					<div>
						<div class="hnw-stat-card__value"><?php echo absint( $stats['total_sites'] ); ?></div>
						<div class="hnw-stat-card__label">Products</div>
					</div>
				</div>
				<div class="hnw-stat-card">
					<div class="hnw-stat-card__icon" style="background:var(--hnw-success-bg);color:var(--hnw-success);"><?php echo self::svg_icon( 'campaign', 20 ); ?></div>
					<div>
						<div class="hnw-stat-card__value"><?php echo absint( $stats['total_contents'] ); ?></div>
						<div class="hnw-stat-card__label">Campaigns</div>
					</div>
				</div>
				<div class="hnw-stat-card">
					<div class="hnw-stat-card__icon" style="background:var(--hnw-success-bg);color:var(--hnw-success);"><?php echo self::svg_icon( 'check', 20 ); ?></div>
					<div>
						<div class="hnw-stat-card__value"><?php echo absint( $stats['enabled_contents'] ); ?></div>
						<div class="hnw-stat-card__label">Active</div>
					</div>
				</div>
				<div class="hnw-stat-card">
					<div class="hnw-stat-card__icon" style="background:var(--hnw-warning-bg);color:#b7791f;"><?php echo self::svg_icon( 'pause', 20 ); ?></div>
					<div>
						<div class="hnw-stat-card__value"><?php echo absint( $stats['total_contents'] - $stats['enabled_contents'] ); ?></div>
						<div class="hnw-stat-card__label">Inactive</div>
					</div>
				</div>
			</div>

			<?php if ( empty( $sites ) ): ?>
				<!-- Empty State -->
				<div class="hnw-empty">
					<div class="hnw-empty__icon"><?php echo self::svg_icon( 'inbox', 48 ); ?></div>
					<h2 class="hnw-empty__title">No products yet</h2>
					<p class="hnw-empty__text">Create your first product to start distributing HTML notices to your plugins.</p>
					<button type="button" class="hnw-btn hnw-btn--primary" id="hnw-add-product-empty"><?php echo self::svg_icon( 'plus', 14 ); ?> Create First Product</button>
				</div>
			<?php else: ?>
				<!-- Toolbar -->
				<div class="hnw-toolbar">
					<h2 class="hnw-toolbar__title">Your Products</h2>
				</div>

				<!-- Cards Grid -->
				<div class="hnw-grid">
					<?php foreach ( $sites as $site ): ?>
						<?php $this->render_site_card( $site ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php // ── All Modals ── ?>
			<?php $this->render_modal_add_product(); ?>
			<?php $this->render_modal_edit_product(); ?>
			<?php $this->render_modal_add_campaign(); ?>
			<?php $this->render_modal_edit_campaign(); ?>
			<?php $this->render_modal_user_doc(); ?>

		</div><!-- .hnw-wrap -->
		<?php
	}

	/* =========================================================================
	   Render — Site Card
	   ========================================================================= */

	/**
	 * Render a single site/product card
	 *
	 * @param array $site Site data.
	 */
	private function render_site_card( $site ) {
		$api_url       = home_url( '/wp-json/html-notice-widget/v1/content/' . $site['endpoint'] );
		$content_count = count( $site['contents'] ?? [] );
		?>
		<div class="hnw-card">
			<!-- Card Header -->
			<div class="hnw-card__header">
				<h3 class="hnw-card__title">
					<?php echo esc_html( $site['product'] ); ?>
					<span class="hnw-badge hnw-badge--enabled"><?php echo self::svg_icon( 'circle-dot', 10 ); ?> Active</span>
				</h3>
			</div>

			<!-- Card Body -->
			<div class="hnw-card__body">
				<div class="hnw-endpoint" data-hnw-tooltip="This slug is used in the REST API URL" data-tooltip-pos="right">
					/ <?php echo esc_html( $site['endpoint'] ); ?>
				</div>
			</div>

			<!-- Campaigns Section -->
			<div class="hnw-campaigns">
				<div class="hnw-campaigns__header">
					<h4 class="hnw-campaigns__title">Campaigns (<?php echo absint( $content_count ); ?>)</h4>
					<button type="button" class="hnw-btn hnw-btn--ghost hnw-btn--sm hnw-add-content"
						data-site-id="<?php echo esc_attr( $site['id'] ); ?>"
						data-site-name="<?php echo esc_attr( $site['product'] ); ?>"
						data-hnw-tooltip="Create a new campaign for this product" data-tooltip-pos="left"><?php echo self::svg_icon( 'plus', 12 ); ?> Add</button>
				</div>

				<?php if ( ! empty( $site['contents'] ) ): ?>
					<?php 
					// Newest first
					$contents = array_reverse( $site['contents'], true ); 
					?>
					<div class="hnw-campaigns__list">
						<?php $i = 0; foreach ( $contents as $content ): ?>
							<div class="hnw-campaign-wrap <?php echo $i >= 3 ? 'hnw-campaign-hidden' : ''; ?>">
								<?php $this->render_campaign_item( $site, $content ); ?>
							</div>
						<?php $i++; endforeach; ?>
						
						<?php if ( count( $contents ) > 4 ): ?>
							<div class="hnw-campaigns__fade"></div>
							<div class="hnw-campaigns__more">
								<button type="button" class="hnw-btn hnw-btn--secondary hnw-btn--sm hnw-campaigns-more-btn">
									See More <?php echo self::svg_icon( 'chevron-down', 14 ); ?>
								</button>
							</div>
						<?php endif; ?>
					</div>
				<?php else: ?>
					<p class="hnw-campaigns__empty">No campaigns added yet.</p>
				<?php endif; ?>
			</div>

			<!-- Card Footer -->
			<div class="hnw-card__footer">
				<button type="button" class="hnw-btn hnw-btn--secondary hnw-btn--sm hnw-user-doc-trigger"
					data-product="<?php echo esc_attr( $site['product'] ); ?>"
					data-endpoint="<?php echo esc_attr( $site['endpoint'] ); ?>"
					data-api-url="<?php echo esc_url( $api_url ); ?>"
					data-hnw-tooltip="View SDK integration instructions" data-tooltip-pos="top"><?php echo self::svg_icon( 'book', 14 ); ?> How To Integrate</button>

				<button type="button" class="hnw-btn hnw-btn--secondary hnw-btn--sm hnw-edit-site"
					data-site-id="<?php echo esc_attr( $site['id'] ); ?>"
					data-site-name="<?php echo esc_attr( $site['product'] ); ?>"><?php echo self::svg_icon( 'edit', 14 ); ?> Edit</button>

				<form method="post" class="hnw-inline-form" onsubmit="return confirm('Delete this product and all its campaigns?');">
					<?php wp_nonce_field( 'html_notice_widget_action' ); ?>
					<input type="hidden" name="action" value="delete_site">
					<input type="hidden" name="site_id" value="<?php echo esc_attr( $site['id'] ); ?>">
					<button type="submit" class="hnw-btn hnw-btn--danger hnw-btn--sm"><?php echo self::svg_icon( 'trash', 14 ); ?> Delete</button>
				</form>
			</div>
		</div>
		<?php
	}

	/* =========================================================================
	   Render — Campaign Item
	   ========================================================================= */

	/**
	 * Render a single campaign item inside a site card
	 *
	 * @param array $site    Site data.
	 * @param array $content Content data.
	 */
	private function render_campaign_item( $site, $content ) {
		$schedule_status = PHP_Utils::get_schedule_status( $content );
		$is_expired      = strpos( $schedule_status, 'Expired' ) !== false;
		?>
		<div class="hnw-campaign-item">
			<div class="hnw-campaign-item__info">
				<p class="hnw-campaign-item__name">
					<?php echo esc_html( $content['title'] ); ?>
					<?php if ( $content['enabled'] ): ?>
						<span class="hnw-badge hnw-badge--enabled"><?php echo self::svg_icon( 'circle-dot', 10 ); ?> Enabled</span>
					<?php else: ?>
						<span class="hnw-badge hnw-badge--disabled"><?php echo self::svg_icon( 'circle-o', 10 ); ?> Disabled</span>
					<?php endif; ?>
				</p>

				<?php if ( ! empty( $content['description'] ) ): ?>
					<p class="hnw-campaign-item__desc"><?php echo esc_html( $content['description'] ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $schedule_status ) ): ?>
					<div class="hnw-campaign-item__schedule">
						<span class="hnw-badge <?php echo $is_expired ? 'hnw-badge--expired' : 'hnw-badge--scheduled'; ?>">
							<?php echo self::svg_icon( 'calendar', 12 ); ?> <?php echo esc_html( $schedule_status ); ?>
						</span>
					</div>
				<?php endif; ?>

				<?php
				// Targeting badges
				$targeting = $content['targeting'] ?? [];
				$has_targeting = false;
				if ( ! empty( $targeting ) ) :
					$pro_label = '';
					if ( ( $targeting['pro_users'] ?? 'all' ) === 'free_only' ) {
						$pro_label = 'Free Only';
					} elseif ( ( $targeting['pro_users'] ?? 'all' ) === 'pro_only' ) {
						$pro_label = 'Pro Only';
					}

					$version_label = '';
					if ( ! empty( $targeting['plugin_version']['operator'] ) && ! empty( $targeting['plugin_version']['version'] ) ) {
						$op_labels = [ 'lt' => '<', 'lte' => '≤', 'eq' => '=', 'gte' => '≥', 'gt' => '>' ];
						$op_sym = $op_labels[ $targeting['plugin_version']['operator'] ] ?? '';
						$version_label = 'v' . $op_sym . $targeting['plugin_version']['version'];
					}

					$roles_label = '';
					if ( ! empty( $targeting['user_roles'] ) ) {
						$roles_label = implode( ', ', array_map( 'ucfirst', $targeting['user_roles'] ) );
					}

					if ( $pro_label || $version_label || $roles_label ) :
						$has_targeting = true;
					?>
					<div class="hnw-campaign-item__targeting">
						<?php if ( $pro_label ): ?>
							<span class="hnw-badge hnw-badge--targeting"><?php echo self::svg_icon( 'package', 10 ); ?> <?php echo esc_html( $pro_label ); ?></span>
						<?php endif; ?>
						<?php if ( $version_label ): ?>
							<span class="hnw-badge hnw-badge--targeting"><?php echo self::svg_icon( 'info', 10 ); ?> <?php echo esc_html( $version_label ); ?></span>
						<?php endif; ?>
						<?php if ( $roles_label ): ?>
							<span class="hnw-badge hnw-badge--targeting"><?php echo self::svg_icon( 'edit', 10 ); ?> <?php echo esc_html( $roles_label ); ?></span>
						<?php endif; ?>
					</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<div class="hnw-campaign-item__actions">
				<button type="button" class="hnw-btn hnw-btn--ghost hnw-btn--sm hnw-edit-content"
					data-site-id="<?php echo esc_attr( $site['id'] ); ?>"
					data-content-id="<?php echo esc_attr( $content['id'] ); ?>"
					data-content-title="<?php echo esc_attr( $content['title'] ); ?>"
					data-content-description="<?php echo esc_attr( $content['description'] ?? '' ); ?>"
					data-content-html="<?php echo esc_attr( $content['content'] ); ?>"
					data-content-enabled="<?php echo $content['enabled'] ? '1' : '0'; ?>"
					data-schedule-start="<?php echo esc_attr( $content['schedule_start'] ?? '' ); ?>"
					data-schedule-end="<?php echo esc_attr( $content['schedule_end'] ?? '' ); ?>"
				data-targeting-pro="<?php echo esc_attr( $content['targeting']['pro_users'] ?? 'all' ); ?>"
				data-targeting-version-op="<?php echo esc_attr( $content['targeting']['plugin_version']['operator'] ?? '' ); ?>"
				data-targeting-version="<?php echo esc_attr( $content['targeting']['plugin_version']['version'] ?? '' ); ?>"
				data-targeting-roles="<?php echo esc_attr( implode( ',', $content['targeting']['user_roles'] ?? [] ) ); ?>"><?php echo self::svg_icon( 'edit', 14 ); ?></button>

				<form method="post" class="hnw-inline-form" onsubmit="return confirm('Delete this campaign?');">
					<?php wp_nonce_field( 'html_notice_widget_action' ); ?>
					<input type="hidden" name="action" value="delete_content">
					<input type="hidden" name="site_id" value="<?php echo esc_attr( $site['id'] ); ?>">
					<input type="hidden" name="content_id" value="<?php echo esc_attr( $content['id'] ); ?>">
					<button type="submit" class="hnw-btn hnw-btn--ghost hnw-btn--sm" style="color:var(--hnw-error);"><?php echo self::svg_icon( 'trash', 14 ); ?></button>
				</form>
			</div>
		</div>
		<?php
	}

	/* =========================================================================
	   Render — Modals (Centralized Structure)
	   ========================================================================= */

	/**
	 * Render Add Product modal
	 */
	private function render_modal_add_product() {
		?>
		<div id="hnw-modal-add-product" class="hnw-modal" style="display:none;">
			<div class="hnw-modal__backdrop"></div>
			<div class="hnw-modal__panel">
				<div class="hnw-modal__header">
					<h2 class="hnw-modal__title">Add New Product</h2>
					<button type="button" class="hnw-modal__close">&times;</button>
				</div>
				<form method="post">
					<?php wp_nonce_field( 'html_notice_widget_action' ); ?>
					<input type="hidden" name="action" value="add_site">
					<div class="hnw-modal__body">
						<div class="hnw-field">
							<label class="hnw-field__label" for="hnw-add-product-name">
								Product Name <span class="hnw-field__required">*</span>
							</label>
							<input type="text" id="hnw-add-product-name" name="product" class="hnw-input" required
								data-hnw-tooltip="This generates the API endpoint slug (e.g. 'My Plugin' → /content/my-plugin)" data-tooltip-pos="right">
							<p class="hnw-field__hint">The slug will be auto-generated from this name for the REST endpoint.</p>
						</div>
					</div>
					<div class="hnw-modal__footer">
						<button type="button" class="hnw-btn hnw-btn--secondary hnw-modal-cancel">Cancel</button>
						<button type="submit" class="hnw-btn hnw-btn--primary">Create Product</button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Edit Product modal
	 */
	private function render_modal_edit_product() {
		?>
		<div id="hnw-modal-edit-product" class="hnw-modal" style="display:none;">
			<div class="hnw-modal__backdrop"></div>
			<div class="hnw-modal__panel">
				<div class="hnw-modal__header">
					<h2 class="hnw-modal__title">Edit Product</h2>
					<button type="button" class="hnw-modal__close">&times;</button>
				</div>
				<form method="post">
					<?php wp_nonce_field( 'html_notice_widget_action' ); ?>
					<input type="hidden" name="action" value="edit_site">
					<input type="hidden" name="site_id" id="hnw-edit-site-id">
					<div class="hnw-modal__body">
						<div class="hnw-field">
							<label class="hnw-field__label" for="hnw-edit-product">
								Product Name <span class="hnw-field__required">*</span>
							</label>
							<input type="text" id="hnw-edit-product" name="product" class="hnw-input" required>
						</div>
					</div>
					<div class="hnw-modal__footer">
						<button type="button" class="hnw-btn hnw-btn--secondary hnw-modal-cancel">Cancel</button>
						<button type="submit" class="hnw-btn hnw-btn--primary">Update Product</button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Add Campaign modal
	 */
	private function render_modal_add_campaign() {
		?>
		<div id="hnw-modal-add-campaign" class="hnw-modal" style="display:none;">
			<div class="hnw-modal__backdrop"></div>
			<div class="hnw-modal__panel">
				<div class="hnw-modal__header">
					<h2 class="hnw-modal__title">Add Campaign to "<span id="hnw-add-content-site-name"></span>"</h2>
					<button type="button" class="hnw-modal__close">&times;</button>
				</div>
				<form method="post">
					<?php wp_nonce_field( 'html_notice_widget_action' ); ?>
					<input type="hidden" name="action" value="add_content">
					<input type="hidden" name="site_id" id="hnw-add-content-site-id">
					<div class="hnw-modal__body">
						<div class="hnw-field">
							<label class="hnw-field__label" for="hnw-add-ct-title">
								Campaign Title <span class="hnw-field__required">*</span>
							</label>
							<input type="text" id="hnw-add-ct-title" name="content_title" class="hnw-input" required>
						</div>
						<div class="hnw-field">
							<label class="hnw-field__label" for="hnw-add-ct-desc">Description</label>
							<input type="text" id="hnw-add-ct-desc" name="content_description" class="hnw-input"
								placeholder="Brief notes about this campaign">
							<p class="hnw-field__hint">Optional internal note for your reference.</p>
						</div>
						<div class="hnw-field">
							<label class="hnw-field__label" for="hnw-add-ct-html">
								HTML Content <span class="hnw-field__required">*</span>
							</label>
							<textarea id="hnw-add-ct-html" name="content_html" class="hnw-textarea" required></textarea>
							<p class="hnw-field__hint">The raw HTML that will be rendered as an admin notice on remote sites.</p>
						</div>
						<div class="hnw-field">
							<label class="hnw-field__label">Schedule</label>
							<div class="hnw-schedule-row">
								<div class="hnw-schedule-field">
									<label class="hnw-field__label" for="hnw-add-sched-start" style="font-weight:400;font-size:12px;">
										Start Date
									</label>
									<input type="datetime-local" id="hnw-add-sched-start" name="schedule_start" class="hnw-datetime"
										data-hnw-tooltip="Leave empty to start immediately" data-tooltip-pos="top">
								</div>
								<div class="hnw-schedule-field">
									<label class="hnw-field__label" for="hnw-add-sched-end" style="font-weight:400;font-size:12px;">
										End Date
									</label>
									<input type="datetime-local" id="hnw-add-sched-end" name="schedule_end" class="hnw-datetime"
										data-hnw-tooltip="Campaign will auto-disable after this date" data-tooltip-pos="top">
								</div>
							</div>
							<p class="hnw-field__hint">Optional. Leave both empty to show this campaign indefinitely.</p>
						</div>
						<div class="hnw-field">
							<label class="hnw-toggle">
								<input type="checkbox" name="content_enabled" value="1" checked>
								<span class="hnw-toggle__slider"></span>
								Enable this campaign
							</label>
						</div>

						<!-- Targeting Section -->
						<div class="hnw-targeting-section">
							<button type="button" class="hnw-targeting-toggle">
								<?php echo self::svg_icon( 'package', 14 ); ?> Targeting Rules
								<span class="hnw-targeting-toggle__arrow"><?php echo self::svg_icon( 'download', 12 ); ?></span>
							</button>
							<div class="hnw-targeting-body" style="display:none;">
								<p class="hnw-field__hint" style="margin-bottom:12px;">Control who sees this campaign on the remote site.</p>

								<div class="hnw-field">
									<label class="hnw-field__label" for="hnw-add-targeting-pro">Audience</label>
									<select id="hnw-add-targeting-pro" name="targeting_pro_users" class="hnw-input">
										<option value="all">All Users</option>
										<option value="free_only">Free Users Only</option>
										<option value="pro_only">Pro Users Only</option>
									</select>
									<p class="hnw-field__hint">Show this notice only to Free or Pro plugin users.</p>
								</div>

								<div class="hnw-field">
									<label class="hnw-field__label">Plugin Version</label>
									<div class="hnw-version-row">
										<select name="targeting_version_op" class="hnw-input hnw-input--sm">
											<option value="">No filter</option>
											<option value="lt">&lt; Less than</option>
											<option value="lte">&le; Less or equal</option>
											<option value="eq">= Equal</option>
											<option value="gte">&ge; Greater or equal</option>
											<option value="gt">&gt; Greater than</option>
										</select>
										<input type="text" name="targeting_version" class="hnw-input hnw-input--sm" placeholder="e.g. 2.5.0">
									</div>
									<p class="hnw-field__hint">Show only when the remote plugin version matches this rule.</p>
								</div>

								<div class="hnw-field">
									<label class="hnw-field__label">User Roles</label>
									<div class="hnw-role-grid">
										<?php foreach ( [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ] as $role ): ?>
											<label class="hnw-role-check">
												<input type="checkbox" name="targeting_roles[]" value="<?php echo esc_attr( $role ); ?>">
												<?php echo esc_html( ucfirst( $role ) ); ?>
											</label>
										<?php endforeach; ?>
									</div>
									<p class="hnw-field__hint">Leave all unchecked to show to every role.</p>
								</div>
							</div>
						</div>
					</div>
					<div class="hnw-modal__footer">
						<button type="button" class="hnw-btn hnw-btn--secondary hnw-modal-cancel">Cancel</button>
						<button type="submit" class="hnw-btn hnw-btn--primary">Add Campaign</button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Edit Campaign modal
	 */
	private function render_modal_edit_campaign() {
		?>
		<div id="hnw-modal-edit-campaign" class="hnw-modal" style="display:none;">
			<div class="hnw-modal__backdrop"></div>
			<div class="hnw-modal__panel">
				<div class="hnw-modal__header">
					<h2 class="hnw-modal__title">Edit: "<span id="hnw-edit-content-title-display"></span>"</h2>
					<button type="button" class="hnw-modal__close">&times;</button>
				</div>
				<form method="post">
					<?php wp_nonce_field( 'html_notice_widget_action' ); ?>
					<input type="hidden" name="action" value="edit_content">
					<input type="hidden" name="site_id" id="hnw-edit-content-site-id">
					<input type="hidden" name="content_id" id="hnw-edit-content-id">
					<div class="hnw-modal__body">
						<div class="hnw-field">
							<label class="hnw-field__label" for="hnw-edit-content-title">
								Campaign Title <span class="hnw-field__required">*</span>
							</label>
							<input type="text" id="hnw-edit-content-title" name="content_title" class="hnw-input" required>
						</div>
						<div class="hnw-field">
							<label class="hnw-field__label" for="hnw-edit-content-desc">Description</label>
							<input type="text" id="hnw-edit-content-desc" name="content_description" class="hnw-input"
								placeholder="Brief notes about this campaign">
						</div>
						<div class="hnw-field">
							<label class="hnw-field__label" for="hnw-edit-content-html">
								HTML Content <span class="hnw-field__required">*</span>
							</label>
							<textarea id="hnw-edit-content-html" name="content_html" class="hnw-textarea" required></textarea>
						</div>
						<div class="hnw-field">
							<label class="hnw-field__label">Schedule</label>
							<div class="hnw-schedule-row">
								<div class="hnw-schedule-field">
									<label class="hnw-field__label" for="hnw-edit-schedule-start" style="font-weight:400;font-size:12px;">
										Start Date
									</label>
									<input type="datetime-local" id="hnw-edit-schedule-start" name="schedule_start" class="hnw-datetime">
								</div>
								<div class="hnw-schedule-field">
									<label class="hnw-field__label" for="hnw-edit-schedule-end" style="font-weight:400;font-size:12px;">
										End Date
									</label>
									<input type="datetime-local" id="hnw-edit-schedule-end" name="schedule_end" class="hnw-datetime">
								</div>
							</div>
							<p class="hnw-field__hint">Optional. Leave both empty to show indefinitely.</p>
						</div>
						<div class="hnw-field">
							<label class="hnw-toggle">
								<input type="checkbox" id="hnw-edit-content-enabled" name="content_enabled" value="1">
								<span class="hnw-toggle__slider"></span>
								Enable this campaign
							</label>
						</div>

						<!-- Targeting Section -->
						<div class="hnw-targeting-section">
							<button type="button" class="hnw-targeting-toggle">
								<?php echo self::svg_icon( 'package', 14 ); ?> Targeting Rules
								<span class="hnw-targeting-toggle__arrow"><?php echo self::svg_icon( 'download', 12 ); ?></span>
							</button>
							<div class="hnw-targeting-body" style="display:none;">
								<p class="hnw-field__hint" style="margin-bottom:12px;">Control who sees this campaign on the remote site.</p>

								<div class="hnw-field">
									<label class="hnw-field__label" for="hnw-edit-targeting-pro">Audience</label>
									<select id="hnw-edit-targeting-pro" name="targeting_pro_users" class="hnw-input">
										<option value="all">All Users</option>
										<option value="free_only">Free Users Only</option>
										<option value="pro_only">Pro Users Only</option>
									</select>
								</div>

								<div class="hnw-field">
									<label class="hnw-field__label">Plugin Version</label>
									<div class="hnw-version-row">
										<select id="hnw-edit-targeting-version-op" name="targeting_version_op" class="hnw-input hnw-input--sm">
											<option value="">No filter</option>
											<option value="lt">&lt; Less than</option>
											<option value="lte">&le; Less or equal</option>
											<option value="eq">= Equal</option>
											<option value="gte">&ge; Greater or equal</option>
											<option value="gt">&gt; Greater than</option>
										</select>
										<input type="text" id="hnw-edit-targeting-version" name="targeting_version" class="hnw-input hnw-input--sm" placeholder="e.g. 2.5.0">
									</div>
								</div>

								<div class="hnw-field">
									<label class="hnw-field__label">User Roles</label>
									<div class="hnw-role-grid" id="hnw-edit-targeting-roles">
										<?php foreach ( [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ] as $role ): ?>
											<label class="hnw-role-check">
												<input type="checkbox" name="targeting_roles[]" value="<?php echo esc_attr( $role ); ?>">
												<?php echo esc_html( ucfirst( $role ) ); ?>
											</label>
										<?php endforeach; ?>
									</div>
									<p class="hnw-field__hint">Leave all unchecked to show to every role.</p>
								</div>
							</div>
						</div>
					</div>
					<div class="hnw-modal__footer">
						<button type="button" class="hnw-btn hnw-btn--secondary hnw-modal-cancel">Cancel</button>
						<button type="submit" class="hnw-btn hnw-btn--primary">Update Campaign</button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Documentation modal
	 */
	private function render_modal_user_doc() {
		?>
		<div id="hnw-modal-user-doc" class="hnw-modal" style="display:none;">
			<div class="hnw-modal__backdrop"></div>
			<div class="hnw-modal__panel hnw-modal__panel--lg">
				<div class="hnw-modal__header">
					<h2 class="hnw-modal__title"><?php echo self::svg_icon( 'book', 18 ); ?> Integrate: "<span id="hnw-user-doc-product-name"></span>"</h2>
					<button type="button" class="hnw-modal__close">&times;</button>
				</div>
				<div class="hnw-modal__body">
					<!-- Step 1 -->
					<div class="hnw-user-doc-step">
						<h3>Step 1: Download SDK</h3>
						<p>Download the Remote Notice Client SDK and add it to your plugin:</p>
						<button type="button" class="hnw-btn hnw-btn--primary" id="hnw-download-sdk"><?php echo self::svg_icon( 'download', 14 ); ?> Download class-remote-notice-client.php</button>
					</div>

					<!-- Step 2 -->
					<div class="hnw-user-doc-step">
						<h3>Step 2: Place in Your Plugin</h3>
						<div class="hnw-code-block">
							<pre>your-plugin/
└── includes/
    └── remote-notices/
        └── class-remote-notice-client.php</pre>
						</div>
					</div>

					<!-- Step 3 -->
					<div class="hnw-user-doc-step">
						<h3>Step 3: Initialize</h3>
						<p>Add this code to your main plugin file. The <code>plugin_version</code> and <code>is_pro</code> params enable server-side targeting rules:</p>
						<div class="hnw-code-block">
							<pre id="hnw-user-doc-init-code"></pre>
							<button type="button" class="hnw-btn hnw-btn--secondary hnw-btn--sm hnw-code-copy" data-target="hnw-user-doc-init-code"><?php echo self::svg_icon( 'clipboard', 12 ); ?> Copy</button>
						</div>
					</div>

					<!-- Step 4 -->
					<div class="hnw-user-doc-step">
						<h3>Pro/Free Toggle</h3>
						<p>Use <code>disable()</code> to fully stop the client when your Pro version is active, or pass <code>is_pro</code> to let server-side targeting handle it:</p>
						<div class="hnw-code-block">
							<pre id="hnw-user-doc-pro-code"></pre>
							<button type="button" class="hnw-btn hnw-btn--secondary hnw-btn--sm hnw-code-copy" data-target="hnw-user-doc-pro-code"><?php echo self::svg_icon( 'clipboard', 12 ); ?> Copy</button>
						</div>
					</div>

					<!-- Config Table -->
					<div class="hnw-user-doc-step">
						<h3>Configuration Options</h3>
						<table class="hnw-options-table">
							<thead><tr><th>Option</th><th>Default</th><th>Description</th></tr></thead>
							<tbody>
								<tr><td><code>api_url</code></td><td><em>Required</em></td><td>The REST API endpoint URL</td></tr>
								<tr><td><code>plugin_version</code></td><td><code>''</code></td><td>Your plugin's current version (enables version-based targeting)</td></tr>
								<tr><td><code>is_pro</code></td><td><code>false</code></td><td>Whether the Pro edition is active (enables audience targeting)</td></tr>
								<tr><td><code>schedule</code></td><td><code>'daily'</code></td><td>Cron schedule: hourly, daily, twicedaily</td></tr>
								<tr><td><code>capability</code></td><td><code>'manage_options'</code></td><td>Required capability to view notices</td></tr>
								<tr><td><code>dismiss_duration</code></td><td><code>WEEK_IN_SECONDS</code></td><td>Temporary dismiss duration</td></tr>
							</tbody>
						</table>
					</div>

					<!-- Methods Table -->
					<div class="hnw-user-doc-step">
						<h3>Available Methods</h3>
						<table class="hnw-options-table">
							<thead><tr><th>Method</th><th>Description</th></tr></thead>
							<tbody>
								<tr><td><code>Remote_Notice_Client::init( $product, $config )</code></td><td>Initialize the notice client (returns instance or <code>false</code> if disabled)</td></tr>
								<tr><td><code>Remote_Notice_Client::disable( $product )</code></td><td>Disable client, unschedule cron, and clear stored data</td></tr>
								<tr><td><code>Remote_Notice_Client::enable( $product )</code></td><td>Re-enable a previously disabled product</td></tr>
								<tr><td><code>Remote_Notice_Client::is_disabled( $product )</code></td><td>Check if the client is currently disabled</td></tr>
								<tr><td><code>Remote_Notice_Client::trigger_fetch( $product )</code></td><td>Manually trigger a campaign fetch</td></tr>
								<tr><td><code>Remote_Notice_Client::clear_all( $product )</code></td><td>Clear all stored notices for a product</td></tr>
							</tbody>
						</table>
					</div>
				</div>
				<div class="hnw-modal__footer">
					<button type="button" class="hnw-btn hnw-btn--secondary hnw-modal-cancel">Close</button>
				</div>
			</div>
		</div>
		<?php
	}

	/* =========================================================================
	   Utilities
	   ========================================================================= */

	/**
	 * Return an inline SVG icon by name
	 *
	 * @param string $name Icon name.
	 * @param int    $size Size in px (default 16).
	 * @return string SVG markup.
	 */
	public static function svg_icon( $name, $size = 16 ) {
		$icons = [
			'megaphone'  => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
			'package'    => '<path d="M16.5 9.4 7.55 4.24"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
			'campaign'   => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
			'check'      => '<polyline points="20 6 9 17 4 12"/>',
			'pause'      => '<rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>',
			'inbox'      => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
			'plus'       => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
			'clipboard'  => '<rect x="9" y="2" width="6" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>',
			'book'       => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
			'edit'       => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
			'trash'      => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
			'calendar'   => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
			'download'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
			'circle-dot'  => '<circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="10" fill="none"/>',
			'circle-o'   => '<circle cx="12" cy="12" r="10" fill="none"/>',
			'x-circle'   => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
			'alert'      => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
			'info'       => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
			'bar-chart'  => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
			'eye'        => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
			'mouse-pointer' => '<path d="M3 3l7.07 16.97 2.51-7.39 7.39-2.51L3 3z"/><path d="M13 13l6 6"/>',
			'refresh'    => '<polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>',
		];

		$path = $icons[ $name ] ?? '';
		if ( empty( $path ) ) {
			return '';
		}

		return '<svg xmlns="http://www.w3.org/2000/svg" width="' . absint( $size ) . '" height="' . absint( $size ) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hnw-icon" aria-hidden="true">' . $path . '</svg>';
	}

	/**
	 * Add admin notice to transient
	 *
	 * @param string $message Notice message.
	 * @param string $type    Notice type (success, error, warning, info).
	 */
	private function add_admin_notice( $message, $type = 'info' ) {
		add_settings_error( 'html_notice_widget_messages', 'html_notice_widget_message', $message, $type );
	}

	/**
	 * Render admin notices with branded notice types
	 */
	private function render_admin_notices() {
		$notices = get_settings_errors( 'html_notice_widget_messages' );

		if ( empty( $notices ) ) {
			return;
		}

		$icon_map = [
			'success' => 'check',
			'error'   => 'x-circle',
			'warning' => 'alert',
			'info'    => 'info',
			'updated' => 'check',
		];

		foreach ( $notices as $notice ) {
			$t    = $notice['type'] ?? 'info';
			$cls  = ( 'updated' === $t ) ? 'success' : $t;
			$icon = $icon_map[ $t ] ?? 'info';
			?>
			<div class="hnw-notice hnw-notice--<?php echo esc_attr( $cls ); ?>">
				<span class="hnw-notice__icon"><?php echo self::svg_icon( $icon, 18 ); ?></span>
				<span class="hnw-notice__text"><?php echo esc_html( $notice['message'] ); ?></span>
			</div>
			<?php
		}
	}

	/**
	 * AJAX handler to download SDK file
	 */
	public function ajax_download_sdk() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'html-notice-widget' ) );
		}

		$sdk_file = HTML_NOTICE_WIDGET_PATH . 'sdk/class-remote-notice-client.php';

		if ( ! file_exists( $sdk_file ) ) {
			wp_die( esc_html__( 'SDK file not found', 'html-notice-widget' ) );
		}

		$content = file_get_contents( $sdk_file );

		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="class-remote-notice-client.php"' );
		header( 'Content-Length: ' . strlen( $content ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );

		echo $content;
		exit;
	}
}
