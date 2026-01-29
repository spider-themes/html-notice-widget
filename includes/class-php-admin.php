<?php
/**
 * PHP Admin Interface for HTML Notice Widget
 *
 * @package HTML_Notice_Widget
 */

namespace HTML_Notice_Widget;

class PHP_Admin {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'handle_form_submissions' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'wp_ajax_hnw_download_sdk', [ $this, 'ajax_download_sdk' ] );
	}

	/**
	 * Handle all form submissions
	 */
	public function handle_form_submissions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle site operations
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
	 * Handle add site
	 */
	private function handle_add_site() {
		$product = sanitize_text_field( $_POST['product'] ?? '' );
		$enabled = isset( $_POST['enabled'] ) ? 1 : 0;

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
			// Redirect to prevent form resubmission
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
		$enabled = isset( $_POST['enabled'] ) ? 1 : 0;

		if ( empty( $site_id ) || empty( $product ) ) {
			$this->add_admin_notice( 'Site ID and product name are required.', 'error' );
			return;
		}

		if ( PHP_Utils::update_site( $site_id, [
			'product' => $product,
			'enabled' => $enabled,
		])) {
			$this->add_admin_notice( 'Product updated successfully!', 'success' );
			// Redirect to prevent form resubmission
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
		$site_id = sanitize_text_field( $_POST['site_id'] ?? '' );
		$title = sanitize_text_field( $_POST['content_title'] ?? '' );
		$description = sanitize_text_field( $_POST['content_description'] ?? '' );
		$content = wp_kses_post( $_POST['content_html'] ?? '' );
		$enabled = isset( $_POST['content_enabled'] ) ? 1 : 0;

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
		]);

		if ( $content_id ) {
			$this->add_admin_notice( 'Content added successfully!', 'success' );
			// Redirect to prevent form resubmission
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
		$site_id = sanitize_text_field( $_POST['site_id'] ?? '' );
		$content_id = sanitize_text_field( $_POST['content_id'] ?? '' );
		$title = sanitize_text_field( $_POST['content_title'] ?? '' );
		$description = sanitize_text_field( $_POST['content_description'] ?? '' );
		$content = wp_kses_post( $_POST['content_html'] ?? '' );
		$enabled = isset( $_POST['content_enabled'] ) ? 1 : 0;

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
		])) {
			$this->add_admin_notice( 'Content updated successfully!', 'success' );
			// Redirect to prevent form resubmission
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
		$site_id = sanitize_text_field( $_POST['site_id'] ?? '' );
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

	/**
	 * Render the admin page
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$sites = PHP_Utils::get_all_sites();

		?>
		<div class="wrap">
			<h1>HTML Notice Widget</h1>

			<?php
			// Display stats
			$stats = PHP_Utils::get_stats();
			?>
			<div class="html-notice-widget-stats" style="background: #f0f0f1; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
				<strong>Statistics:</strong>
				<?php echo $stats['total_sites']; ?> Product (<?php echo $stats['enabled_sites']; ?> enabled) |
				<?php echo $stats['total_contents']; ?> Offers (<?php echo $stats['enabled_contents']; ?> enabled)
			</div>

			<?php $this->render_admin_notices(); ?>

			<?php $this->render_sites_list( $sites ); ?>
		</div>
		<?php
	}

	/**
	 * Render sites list
	 */
	private function render_sites_list( $sites ) {
		?>
		<div class="html-notice-widget-header">
			<button type="button" class="button button-primary" id="add-site-modal-trigger">Add New Product</button>
		</div>

		<?php if ( empty( $sites ) ): ?>
			<div class="html-notice-widget-empty-state">
				<p>No Product created yet.</p>
				<p><button type="button" class="button button-primary" id="add-site-modal-trigger-empty">Create your first product</button></p>
			</div>
		<?php else: ?>
			<div class="html-notice-widget-sites-grid">
				<?php foreach ( $sites as $site ): ?>
					<div class="html-notice-widget-site-card">
						<div class="site-header">
							<h3><?php echo esc_html( $site['product'] ); ?></h3>
							<span class="status <?php echo $site['enabled'] ? 'enabled' : 'disabled'; ?>">
								<?php echo $site['enabled'] ? '✓ Enabled' : '✗ Disabled'; ?>
							</span>
						</div>

						<div class="site-info">
							<p><strong>Endpoint:</strong> <code><?php echo esc_html( $site['endpoint'] ); ?></code></p>
							<p><strong>API URL:</strong></p>
							<div class="api-url">
								<?php echo esc_url( home_url( '/wp-json/html-notice-widget/v1/content/' . $site['endpoint'] ) ); ?>
							</div>
						</div>

						<div class="contents-section">
							<div class="contents-header">
								<h4>Campaigns (<?php echo count( $site['contents'] ?? [] ); ?>)</h4>
								<button type="button" class="button button-small add-content-trigger"
										data-site-id="<?php echo esc_attr( $site['id'] ); ?>"
										data-site-name="<?php echo esc_attr( $site['product'] ); ?>">Add Content</button>
							</div>

							<?php if ( ! empty( $site['contents'] ) ): ?>
								<div class="contents-list">
									<?php foreach ( $site['contents'] as $content ): ?>
										<div class="content-item">
											<div class="content-info">
												<strong><?php echo esc_html( $content['title'] ); ?></strong>
												<span class="content-status <?php echo $content['enabled'] ? 'enabled' : 'disabled'; ?>">
													<?php echo $content['enabled'] ? '✓ Offer Enabled' : '✗ Offer Disabled'; ?>
												</span>
											</div>

											<?php if ( ! empty( $content['description'] ) ): ?>
												<div class="content-description">
													<em>(<?php echo esc_html( $content['description'] ); ?>)</em>
												</div>
											<?php endif; ?>

											<?php
											$schedule_status = PHP_Utils::get_schedule_status( $content );
											if ( ! empty( $schedule_status ) ) :
											?>
												<div class="content-schedule">
													<span class="schedule-badge <?php echo strpos( $schedule_status, 'Expired' ) !== false ? 'expired' : 'active'; ?>">
														📅 <?php echo esc_html( $schedule_status ); ?>
													</span>
												</div>
											<?php endif; ?>

											<div class="content-actions">
												<button type="button" class="button button-small edit-content-trigger"
														data-site-id="<?php echo esc_attr( $site['id'] ); ?>"
														data-content-id="<?php echo esc_attr( $content['id'] ); ?>"
														data-content-title="<?php echo esc_attr( $content['title'] ); ?>"
														data-content-description="<?php echo esc_attr( $content['description'] ?? '' ); ?>"
														data-content-html="<?php echo esc_attr( $content['content'] ); ?>"
														data-content-enabled="<?php echo $content['enabled'] ? '1' : '0'; ?>"
														data-schedule-start="<?php echo esc_attr( $content['schedule_start'] ?? '' ); ?>"
														data-schedule-end="<?php echo esc_attr( $content['schedule_end'] ?? '' ); ?>">Edit</button>
												<form method="post" style="display: inline;margin-bottom:0;" onsubmit="return confirm('Are you sure you want to delete this content?');">
													<?php wp_nonce_field( 'html_notice_widget_action' ); ?>
													<input type="hidden" name="action" value="delete_content">
													<input type="hidden" name="site_id" value="<?php echo esc_attr( $site['id'] ); ?>">
													<input type="hidden" name="content_id" value="<?php echo esc_attr( $content['id'] ); ?>">
													<button type="submit" class="button button-small button-danger">Delete</button>
												</form>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							<?php else: ?>
								<p class="no-contents">No campaign added yet.</p>
							<?php endif; ?>
						</div>

						<div class="site-actions">
							<button type="button" class="button docs-trigger"
									data-product="<?php echo esc_attr( $site['product'] ); ?>"
									data-endpoint="<?php echo esc_attr( $site['endpoint'] ); ?>"
									data-api-url="<?php echo esc_url( home_url( '/wp-json/html-notice-widget/v1/content/' . $site['endpoint'] ) ); ?>">📖 How To Integrate</button>
							<button type="button" class="button edit-site-trigger"
									data-site-id="<?php echo esc_attr( $site['id'] ); ?>"
									data-site-name="<?php echo esc_attr( $site['product'] ); ?>"
									data-site-enabled="<?php echo $site['enabled'] ? '1' : '0'; ?>">Edit Product</button>
							<form method="post" style="display: inline;margin-bottom: 0;" onsubmit="return confirm('Are you sure you want to delete this product and all its campaign?');">
								<?php wp_nonce_field( 'html_notice_widget_action' ); ?>
								<input type="hidden" name="action" value="delete_site">
								<input type="hidden" name="site_id" value="<?php echo esc_attr( $site['id'] ); ?>">
								<button type="submit" class="button button-danger" style="margin-bottom: 0">Delete Product</button>
							</form>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<!-- Add Site Modal -->
		<div id="add-site-modal" class="html-notice-widget-modal" style="display: none;">
			<div class="modal-backdrop"></div>
			<div class="modal-content">
				<div class="modal-header">
					<h2>Add New Product</h2>
					<button type="button" class="modal-close">&times;</button>
				</div>
				<form method="post" class="html-notice-widget-form" id="add-site-form">
					<?php wp_nonce_field( 'html_notice_widget_action' ); ?>
					<input type="hidden" name="action" value="add_site">

					<table class="form-table">
						<tr>
							<th scope="row"><label for="product">Product Name <span class="required">*</span></label></th>
							<td>
								<input type="text" id="product" name="product" class="regular-text" required>
								<p class="description">This will be used to generate the API endpoint (e.g., "my-product" becomes "/content/my-product")</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Status</th>
							<td>
								<label>
									<input type="checkbox" name="enabled" value="1">
									Enable this product
								</label>
							</td>
						</tr>
					</table>

					<div class="modal-footer">
						<input type="submit" class="button button-primary" value="Create Product">
						<button type="button" class="button modal-cancel">Cancel</button>
					</div>
				</form>
			</div>
		</div>

		<!-- Edit Site Modal -->
		<div id="edit-site-modal" class="html-notice-widget-modal" style="display: none;">
			<div class="modal-backdrop"></div>
			<div class="modal-content">
				<div class="modal-header">
					<h2>Edit Product</h2>
					<button type="button" class="modal-close">&times;</button>
				</div>
				<form method="post" class="html-notice-widget-form" id="edit-site-form">
					<?php wp_nonce_field( 'html_notice_widget_action' ); ?>
					<input type="hidden" name="action" value="edit_site">
					<input type="hidden" name="site_id" id="edit-site-id">

					<table class="form-table">
						<tr>
							<th scope="row"><label for="edit-product">Product Name <span class="required">*</span></label></th>
							<td>
								<input type="text" id="edit-product" name="product" class="regular-text" required>
							</td>
						</tr>
						<tr>
							<th scope="row">Status</th>
							<td>
								<label>
									<input type="checkbox" id="edit-enabled" name="enabled" value="1">
									Enable this product
								</label>
							</td>
						</tr>
					</table>

					<div class="modal-footer">
						<input type="submit" class="button button-primary" value="Update Product">
						<button type="button" class="button modal-cancel">Cancel</button>
					</div>
				</form>
			</div>
		</div>

		<!-- Add Content Modal -->
		<div id="add-content-modal" class="html-notice-widget-modal" style="display: none;">
			<div class="modal-backdrop"></div>
			<div class="modal-content">
				<div class="modal-header">
					<h2>Add Campaign to "<span id="add-content-site-name"></span>"</h2>
					<button type="button" class="modal-close">&times;</button>
				</div>
				<form method="post" class="html-notice-widget-form" id="add-content-form">
					<?php wp_nonce_field( 'html_notice_widget_action' ); ?>
					<input type="hidden" name="action" value="add_content">
					<input type="hidden" name="site_id" id="add-content-site-id">

					<table class="form-table">
						<tr>
							<th scope="row"><label for="content_title">Campaign Title <span class="required">*</span></label></th>
							<td>
								<input type="text" id="content_title" name="content_title" class="regular-text" required>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="content_description">Description</label></th>
							<td>
								<input type="text" id="content_description" name="content_description" class="regular-text" placeholder="Brief description or notes about this campaign">
								<p class="description">Optional description to help identify this campaign.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="content_html">Campaign Content <span class="required">*</span></label></th>
							<td>
								<textarea id="content_html" name="content_html" rows="10" cols="50" class="large-text code" required></textarea>
								<p class="description">Enter your HTML content here.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label>Schedule</label></th>
							<td>
								<div class="schedule-fields">
									<div class="schedule-field">
										<label for="schedule_start">Start Date:</label>
										<input type="datetime-local" id="schedule_start" name="schedule_start" class="regular-text">
									</div>
									<div class="schedule-field">
										<label for="schedule_end">End Date:</label>
										<input type="datetime-local" id="schedule_end" name="schedule_end" class="regular-text">
									</div>
								</div>
								<p class="description">Optional. Set a date range for this campaign. Leave empty to show indefinitely. Campaign will be auto-disabled after the end date.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Status</th>
							<td>
								<label>
									<input type="checkbox" name="content_enabled" value="1" checked>
									Enable this offer
								</label>
							</td>
						</tr>
					</table>

					<div class="modal-footer">
						<input type="submit" class="button button-primary" value="Add Content">
						<button type="button" class="button modal-cancel">Cancel</button>
					</div>
				</form>
			</div>
		</div>

		<!-- Edit Content Modal -->
		<div id="edit-content-modal" class="html-notice-widget-modal" style="display: none;">
			<div class="modal-backdrop"></div>
			<div class="modal-content">
				<div class="modal-header">
					<h2>Edit Content: "<span id="edit-content-title-display"></span>"</h2>
					<button type="button" class="modal-close">&times;</button>
				</div>
				<form method="post" class="html-notice-widget-form" id="edit-content-form">
					<?php wp_nonce_field( 'html_notice_widget_action' ); ?>
					<input type="hidden" name="action" value="edit_content">
					<input type="hidden" name="site_id" id="edit-content-site-id">
					<input type="hidden" name="content_id" id="edit-content-id">

					<table class="form-table">
						<tr>
							<th scope="row"><label for="edit_content_title">Content Title <span class="required">*</span></label></th>
							<td>
								<input type="text" id="edit_content_title" name="content_title" class="regular-text" required>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="edit_content_description">Content Description</label></th>
							<td>
								<input type="text" id="edit_content_description" name="content_description" class="regular-text" placeholder="Brief description or notes about this content">
								<p class="description">Optional description to help identify this content.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="edit_content_html">HTML Content <span class="required">*</span></label></th>
							<td>
								<textarea id="edit_content_html" name="content_html" rows="10" cols="50" class="large-text code" required></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row"><label>Schedule</label></th>
							<td>
								<div class="schedule-fields">
									<div class="schedule-field">
										<label for="edit_schedule_start">Start Date:</label>
										<input type="datetime-local" id="edit_schedule_start" name="schedule_start" class="regular-text">
									</div>
									<div class="schedule-field">
										<label for="edit_schedule_end">End Date:</label>
										<input type="datetime-local" id="edit_schedule_end" name="schedule_end" class="regular-text">
									</div>
								</div>
								<p class="description">Optional. Set a date range for this campaign. Leave empty to show indefinitely. Campaign will be auto-disabled after the end date.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Status</th>
							<td>
								<label>
									<input type="checkbox" id="edit_content_enabled" name="content_enabled" value="1">
									Enable this offer
								</label>
							</td>
						</tr>
					</table>

					<div class="modal-footer">
						<input type="submit" class="button button-primary" value="Update Content">
						<button type="button" class="button modal-cancel">Cancel</button>
					</div>
				</form>
			</div>
		</div>

		<!-- Documentation Modal -->
		<div id="docs-modal" class="html-notice-widget-modal" style="display: none;">
			<div class="modal-backdrop"></div>
			<div class="modal-content modal-content-large">
				<div class="modal-header">
					<h2>📖 How To Integrate: "<span id="docs-product-name"></span>"</h2>
					<button type="button" class="modal-close">&times;</button>
				</div>
				<div class="docs-modal-body">
					<div class="docs-step">
						<h3>Step 1: Download SDK</h3>
						<p>Download the Remote Notice Client SDK file and add it to your plugin:</p>
						<button type="button" class="button button-primary" id="download-sdk-btn">⬇️ Download class-remote-notice-client.php</button>
						<p class="description" style="margin-top: 10px;">The file will be downloaded to your computer.</p>
					</div>

					<div class="docs-step">
						<h3>Step 2: Add to Your Plugin</h3>
						<p>Place the SDK file in your plugin directory:</p>
						<pre class="docs-code-block">your-plugin/
└── includes/
    └── remote-notices/
        └── class-remote-notice-client.php</pre>
					</div>

					<div class="docs-step">
						<h3>Step 3: Initialize in Your Plugin</h3>
						<p>Add this code to your main plugin file:</p>
						<div class="docs-code-wrapper">
							<pre class="docs-code-block" id="docs-init-code"><?php echo esc_html("<?php
// Remote Notice Integration
require_once PLUGIN_PATH . 'includes/remote-notices/class-remote-notice-client.php';

add_action( 'admin_init', function() {
    if ( class_exists( 'Remote_Notice_Client' ) ) {
        Remote_Notice_Client::init( 'PRODUCT_SLUG', [
            'api_url' => 'API_URL',
        ]);
    }
});"); ?></pre>
							<button type="button" class="button copy-code-btn" data-target="docs-init-code">📋 Copy Code</button>
						</div>
					</div>

					<div class="docs-step">
						<h3>Pro/Free Version Toggle</h3>
						<p>To disable notices when Pro version is active:</p>
						<div class="docs-code-wrapper">
							<pre class="docs-code-block" id="docs-pro-code"><?php echo esc_html("<?php
// Disable notices when Pro is active
add_action( 'admin_init', function() {
    if ( defined( 'YOUR_PLUGIN_PRO_ACTIVE' ) && YOUR_PLUGIN_PRO_ACTIVE ) {
        Remote_Notice_Client::disable( 'PRODUCT_SLUG' );
        return;
    }
    
    Remote_Notice_Client::init( 'PRODUCT_SLUG', [
        'api_url' => 'API_URL',
    ]);
});"); ?></pre>
							<button type="button" class="button copy-code-btn" data-target="docs-pro-code">📋 Copy Code</button>
						</div>
					</div>

					<div class="docs-step">
						<h3>Configuration Options</h3>
						<table class="docs-options-table">
							<thead>
								<tr>
									<th>Option</th>
									<th>Default</th>
									<th>Description</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td><code>api_url</code></td>
									<td><em>Required</em></td>
									<td>The API endpoint URL</td>
								</tr>
								<tr>
									<td><code>schedule</code></td>
									<td><code>'daily'</code></td>
									<td>Cron schedule: 'hourly', 'daily', 'twicedaily'</td>
								</tr>
								<tr>
									<td><code>capability</code></td>
									<td><code>'manage_options'</code></td>
									<td>Required user capability to see notices</td>
								</tr>
								<tr>
									<td><code>dismiss_duration</code></td>
									<td><code>WEEK_IN_SECONDS</code></td>
									<td>Temporary dismiss duration in seconds</td>
								</tr>
							</tbody>
						</table>
					</div>

					<div class="docs-step">
						<h3>Available Methods</h3>
						<table class="docs-options-table">
							<thead>
								<tr>
									<th>Method</th>
									<th>Description</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td><code>Remote_Notice_Client::init( $product, $config )</code></td>
									<td>Initialize notice client for a product</td>
								</tr>
								<tr>
									<td><code>Remote_Notice_Client::disable( $product )</code></td>
									<td>Disable notices and unschedule cron</td>
								</tr>
								<tr>
									<td><code>Remote_Notice_Client::enable( $product )</code></td>
									<td>Re-enable a disabled product</td>
								</tr>
								<tr>
									<td><code>Remote_Notice_Client::trigger_fetch( $product )</code></td>
									<td>Manually trigger content fetch</td>
								</tr>
								<tr>
									<td><code>Remote_Notice_Client::clear_all( $product )</code></td>
									<td>Clear all stored notices</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="button modal-cancel">Close</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Add admin notice
	 */
	private function add_admin_notice( $message, $type = 'info' ) {
		add_settings_error( 'html_notice_widget_messages', 'html_notice_widget_message', $message, $type );
	}

	/**
	 * AJAX handler to download SDK file
	 */
	public function ajax_download_sdk() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$sdk_file = HTML_NOTICE_WIDGET_PATH . 'sdk/class-remote-notice-client.php';

		if ( ! file_exists( $sdk_file ) ) {
			wp_die( 'SDK file not found' );
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

	/**
	 * Render admin notices
	 */
	private function render_admin_notices() {
		settings_errors( 'html_notice_widget_messages' );
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_html-notice-widget' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'html-notice-widget-php-admin',
			HTML_NOTICE_WIDGET_URL . 'assets/css/php-admin.css',
			[],
			HTML_NOTICE_WIDGET_VERSION
		);

		wp_enqueue_script( 'jquery' );

		// Add inline JavaScript for modal functionality
		$inline_js = "
		jQuery(document).ready(function($) {
			// Modal elements
			const modals = {
				addSite: $('#add-site-modal')[0],
				editSite: $('#edit-site-modal')[0],
				addContent: $('#add-content-modal')[0],
				editContent: $('#edit-content-modal')[0],
				docs: $('#docs-modal')[0]
			};

			// Open modal functions
			function openModal(modal) {
				if (modal) {
					$(modal).show();
					setTimeout(() => {
						$(modal).addClass('modal-show');
					}, 10);
					$('body').css('overflow', 'hidden');
				}
			}

			function closeModal(modal) {
				if (modal) {
					$(modal).removeClass('modal-show');
					setTimeout(() => {
						$(modal).hide();
						$('body').css('overflow', '');
						// Reset forms
						$(modal).find('form')[0]?.reset();
					}, 300);
				}
			}

			// Event delegation for dynamic buttons
			// Add Site Modal
			$(document).on('click', '#add-site-modal-trigger, #add-site-modal-trigger-empty', function(e) {
				e.preventDefault();
				openModal(modals.addSite);
			});

			// Edit Site Modal
			$(document).on('click', '.edit-site-trigger', function(e) {
				e.preventDefault();
				const siteId = $(this).data('site-id');
				const siteName = $(this).data('site-name');
				const siteEnabled = $(this).data('site-enabled');

				$('#edit-site-id').val(siteId);
				$('#edit-product').val(siteName);
				$('#edit-enabled').prop('checked', siteEnabled == 1);

				openModal(modals.editSite);
			});

			// Add Content Modal
			$(document).on('click', '.add-content-trigger', function(e) {
				e.preventDefault();
				const siteId = $(this).data('site-id');
				const siteName = $(this).data('site-name');

				$('#add-content-site-id').val(siteId);
				$('#add-content-site-name').text(siteName);

				openModal(modals.addContent);
			});

			// Edit Content Modal
			$(document).on('click', '.edit-content-trigger', function(e) {
				e.preventDefault();
				const siteId = $(this).data('site-id');
				const contentId = $(this).data('content-id');
				const title = $(this).data('content-title');
				const description = $(this).data('content-description');
				const html = $(this).data('content-html');
				const enabled = $(this).data('content-enabled');
				const scheduleStart = $(this).data('schedule-start');
				const scheduleEnd = $(this).data('schedule-end');

				$('#edit-content-site-id').val(siteId);
				$('#edit-content-id').val(contentId);
				$('#edit_content_title').val(title);
				$('#edit_content_description').val(description);
				$('#edit_content_html').val(html);
				$('#edit_content_enabled').prop('checked', enabled == 1);
				$('#edit-content-title-display').text(title);
				
				// Handle schedule fields - convert from stored format to datetime-local format
				if (scheduleStart) {
					$('#edit_schedule_start').val(scheduleStart.replace(' ', 'T').substring(0, 16));
				} else {
					$('#edit_schedule_start').val('');
				}
				if (scheduleEnd) {
					$('#edit_schedule_end').val(scheduleEnd.replace(' ', 'T').substring(0, 16));
				} else {
					$('#edit_schedule_end').val('');
				}

				openModal(modals.editContent);
			});

			// Documentation Modal
			$(document).on('click', '.docs-trigger', function(e) {
				e.preventDefault();
				const product = $(this).data('product');
				const apiUrl = $(this).data('api-url');
				
				$('#docs-product-name').text(product);
				
				// Update the basic code example with actual values
				const codeTemplate = `<?php
// Remote Notice Integration
require_once PLUGIN_PATH . 'includes/remote-notices/class-remote-notice-client.php';

add_action( 'admin_init', function() {
    if ( class_exists( 'Remote_Notice_Client' ) ) {
        Remote_Notice_Client::init( '` + product + `', [
            'api_url' => '` + apiUrl + `',
        ]);
    }
});`;
				$('#docs-init-code').text(codeTemplate);
				
				// Update the Pro/Free code example with actual values
				const proCodeTemplate = `<?php
// Disable notices when Pro is active
add_action( 'admin_init', function() {
    if ( defined( 'YOUR_PLUGIN_PRO_ACTIVE' ) && YOUR_PLUGIN_PRO_ACTIVE ) {
        Remote_Notice_Client::disable( '` + product + `' );
        return;
    }
    
    Remote_Notice_Client::init( '` + product + `', [
        'api_url' => '` + apiUrl + `',
    ]);
});`;
				$('#docs-pro-code').text(proCodeTemplate);
				
				openModal(modals.docs);
			});
			
			// SDK Download button
			$(document).on('click', '#download-sdk-btn', function(e) {
				e.preventDefault();
				window.location.href = ajaxurl + '?action=hnw_download_sdk';
			});
			
			// Copy code button
			$(document).on('click', '.copy-code-btn', function(e) {
				e.preventDefault();
				const targetId = $(this).data('target');
				const codeText = $('#' + targetId).text();
				
				navigator.clipboard.writeText(codeText).then(() => {
					const originalText = $(this).text();
					$(this).text('✅ Copied!');
					setTimeout(() => {
						$(this).text(originalText);
					}, 2000);
				}).catch(err => {
					console.error('Failed to copy:', err);
				});
			});

			// Close modal events
			$(document).on('click', '.modal-close, .modal-cancel', function(e) {
				e.preventDefault();
				const modal = $(this).closest('.html-notice-widget-modal')[0];
				closeModal(modal);
			});

			// Backdrop click
			$(document).on('click', '.modal-backdrop', function() {
				const modal = $(this).closest('.html-notice-widget-modal')[0];
				closeModal(modal);
			});

			// Escape key
			$(document).keydown(function(e) {
				if (e.key === 'Escape' || e.keyCode === 27) {
					$('.html-notice-widget-modal.modal-show').each(function() {
						closeModal(this);
					});
				}
			});

			// Add loading state to form submissions
			$(document).on('submit', '.html-notice-widget-modal form', function() {
				const submitBtn = $(this).find('input[type=\"submit\"], button[type=\"submit\"]');
				const originalText = submitBtn.val() || submitBtn.text();
				submitBtn.prop('disabled', true);
				if (submitBtn.is('input')) {
					submitBtn.val('Processing...');
				} else {
					submitBtn.text('Processing...');
				}
			});
		});
		";

		wp_add_inline_script('jquery', $inline_js);
	}
}
