<?php
/**
 * Remote Notice Client — SDK Test Dashboard
 *
 * Paste into Code Snippets → Run everywhere.
 * Adds: Tools → RNC Test Dashboard
 *
 * Shows SDK status, cron info, last fetch time,
 * and buttons to enable/disable/fetch/reset.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_management_page(
		'RNC Test Dashboard',
		'RNC Test Dashboard',
		'manage_options',
		'rnc-test-dashboard',
		'rnc_test_dashboard_render'
	);
} );

// Handle form actions.
add_action( 'admin_init', function () {
	if ( ! isset( $_POST['rnc_action'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! check_admin_referer( 'rnc_test_nonce', '_rnc_nonce' ) ) {
		return;
	}

	$action  = sanitize_key( $_POST['rnc_action'] );
	$product = sanitize_key( $_POST['rnc_product'] ?? '' );

	if ( empty( $product ) || ! class_exists( 'Remote_Notice_Client' ) ) {
		set_transient( 'rnc_test_msg', '❌ Product slug is required and SDK must be active.', 30 );
		wp_safe_redirect( admin_url( 'tools.php?page=rnc-test-dashboard' ) );
		exit;
	}

	$msg = '';

	switch ( $action ) {
		case 'fetch':
			$result = Remote_Notice_Client::trigger_fetch( $product );
			$msg    = $result
				? '✅ Fetch triggered for "' . $product . '"'
				: '⚠️ No active instance for "' . $product . '". Is it initialized?';
			break;

		case 'enable':
			Remote_Notice_Client::enable( $product );
			$msg = '✅ Client enabled for "' . $product . '". Reload any admin page to re-initialize.';
			break;

		case 'disable':
			Remote_Notice_Client::disable( $product );
			$msg = '⛔ Client disabled for "' . $product . '". Cron unscheduled, cache cleared.';
			break;

		case 'clear':
			Remote_Notice_Client::clear_all( $product );
			$msg = '🗑️ All cached notices cleared for "' . $product . '".';
			break;

		case 'clear_dismissals':
			delete_option( 'rnc_' . $product . '_dismissed_ids' );
			$msg = '🔄 Dismissed campaign list cleared for "' . $product . '". Previously dismissed notices will reappear.';
			break;
	}

	if ( $msg ) {
		set_transient( 'rnc_test_msg', $msg, 30 );
	}
	wp_safe_redirect( admin_url( 'tools.php?page=rnc-test-dashboard' ) );
	exit;
} );

/**
 * Render the test dashboard
 */
function rnc_test_dashboard_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$msg = get_transient( 'rnc_test_msg' );
	if ( $msg ) {
		delete_transient( 'rnc_test_msg' );
	}

	// Auto-detect product slugs from registered options.
	global $wpdb;
	$like    = $wpdb->esc_like( 'rnc_' ) . '%' . $wpdb->esc_like( '_contents' );
	$results = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 20",
			$like
		)
	);

	$products = [];
	foreach ( $results as $opt ) {
		// rnc_{product}_contents → extract product.
		$slug = preg_replace( '/^rnc_(.+)_contents$/', '$1', $opt );
		if ( $slug !== $opt ) {
			$products[] = $slug;
		}
	}

	?>
	<style>
		.rnc-wrap{max-width:800px;margin:20px auto;font-family:-apple-system,sans-serif}
		.rnc-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:14px}
		.rnc-card h3{margin:0 0 12px;font-size:15px;color:#1f2937}
		.rnc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:14px}
		.rnc-stat{background:#f8f9fb;border-radius:6px;padding:14px;text-align:center}
		.rnc-stat .val{font-size:18px;font-weight:700;color:#1f2937}
		.rnc-stat .lbl{font-size:11px;color:#9ca1ad;text-transform:uppercase;letter-spacing:.5px;margin-top:4px}
		.rnc-badge{display:inline-block;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600}
		.rnc-badge--on{background:#e6f9f3;color:#00875a}
		.rnc-badge--off{background:#fef0ed;color:#c0392b}
		.rnc-badge--warn{background:#fff8e1;color:#e67e22}
		.rnc-msg{padding:10px 14px;border-radius:6px;background:#eef6ff;border-left:4px solid #74b9ff;margin-bottom:14px;font-size:13px}
		.rnc-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
		.rnc-btn{display:inline-flex;align-items:center;gap:4px;padding:7px 14px;border:none;border-radius:5px;font-size:12px;font-weight:600;cursor:pointer;transition:.2s}
		.rnc-btn:hover{transform:translateY(-1px);box-shadow:0 2px 8px rgba(0,0,0,.12)}
		.rnc-btn--primary{background:#6c5ce7;color:#fff}
		.rnc-btn--success{background:#00b894;color:#fff}
		.rnc-btn--danger{background:#e17055;color:#fff}
		.rnc-btn--secondary{background:#f0f1f4;color:#374151;border:1px solid #d1d5db}
		.rnc-input{padding:6px 10px;border:1px solid #d1d5db;border-radius:5px;font-size:13px;min-width:180px}
		.rnc-input-label{font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px}
		.rnc-table{width:100%;border-collapse:collapse;font-size:12px;margin-top:10px}
		.rnc-table th,.rnc-table td{padding:6px 10px;text-align:left;border-bottom:1px solid #f0f1f4}
		.rnc-table th{background:#f8f9fb;font-weight:600;color:#6b7280;text-transform:uppercase;font-size:10px;letter-spacing:.5px}
		.rnc-table code{background:#f0f1f4;padding:1px 5px;border-radius:3px;font-size:11px}
	</style>

	<div class="rnc-wrap">
		<h1 style="font-size:22px;font-weight:700;margin-bottom:6px">🔧 RNC Test Dashboard</h1>
		<p style="color:#6b7280;margin-bottom:18px">Manage and debug the Remote Notice Client SDK on this site.</p>

		<?php if ( $msg ) : ?>
			<div class="rnc-msg"><?php echo wp_kses_post( $msg ); ?></div>
		<?php endif; ?>

		<?php if ( ! class_exists( 'Remote_Notice_Client' ) ) : ?>
			<div class="rnc-card">
				<p style="color:#e17055;font-weight:600">⚠️ Remote_Notice_Client class not found. The SDK is not loaded on this site.</p>
			</div>
			<?php return; ?>
		<?php endif; ?>

		<?php if ( empty( $products ) ) : ?>
			<div class="rnc-card">
				<p style="color:#9ca1ad;font-style:italic">No registered products found. The SDK hasn't fetched any data yet.</p>
				<form method="post" style="margin-top:12px">
					<?php wp_nonce_field( 'rnc_test_nonce', '_rnc_nonce' ); ?>
					<div class="rnc-row">
						<div>
							<span class="rnc-input-label">Product Slug</span>
							<input type="text" name="rnc_product" class="rnc-input" placeholder="your-product-slug">
						</div>
						<button type="submit" name="rnc_action" value="fetch" class="rnc-btn rnc-btn--primary" style="margin-top:16px">📥 Trigger First Fetch</button>
					</div>
				</form>
			</div>
			<?php return; ?>
		<?php endif; ?>

		<?php foreach ( $products as $product ) :
			$is_disabled    = Remote_Notice_Client::is_disabled( $product );
			$cron_hook      = 'rnc_fetch_content_' . $product;
			$next_scheduled = wp_next_scheduled( $cron_hook );
			$last_fetched   = get_option( 'rnc_' . $product . '_fetched_time', '' );
			$contents       = get_option( 'rnc_' . $product . '_contents', [] );
			$dismissed_ids  = get_option( 'rnc_' . $product . '_dismissed_ids', [] );
			$content_count  = is_array( $contents ) ? count( $contents ) : 0;
			$dismiss_count  = is_array( $dismissed_ids ) ? count( $dismissed_ids ) : 0;
		?>
		<div class="rnc-card">
			<h3>📡 Product: <code><?php echo esc_html( $product ); ?></code></h3>

			<div class="rnc-grid">
				<div class="rnc-stat">
					<div class="val">
						<span class="rnc-badge <?php echo $is_disabled ? 'rnc-badge--off' : 'rnc-badge--on'; ?>">
							<?php echo $is_disabled ? 'Disabled' : 'Active'; ?>
						</span>
					</div>
					<div class="lbl">SDK Status</div>
				</div>
				<div class="rnc-stat">
					<div class="val">
						<?php if ( $next_scheduled ) : ?>
							<span class="rnc-badge rnc-badge--on">Scheduled</span>
						<?php else : ?>
							<span class="rnc-badge rnc-badge--off">Not Scheduled</span>
						<?php endif; ?>
					</div>
					<div class="lbl">Cron Status</div>
				</div>
				<div class="rnc-stat">
					<div class="val"><?php echo $content_count; ?></div>
					<div class="lbl">Cached Notices</div>
				</div>
				<div class="rnc-stat">
					<div class="val"><?php echo $dismiss_count; ?> / 20</div>
					<div class="lbl">Dismissed IDs</div>
				</div>
			</div>

			<div class="rnc-grid" style="grid-template-columns:1fr 1fr">
				<div class="rnc-stat">
					<div class="val" style="font-size:13px"><?php echo $last_fetched ? esc_html( $last_fetched ) : '—'; ?></div>
					<div class="lbl">Last Fetched</div>
				</div>
				<div class="rnc-stat">
					<div class="val" style="font-size:13px">
						<?php
						if ( $next_scheduled ) {
							$diff = $next_scheduled - time();
							if ( $diff > 0 ) {
								echo esc_html( human_time_diff( time(), $next_scheduled ) ) . ' from now';
							} else {
								echo '<span class="rnc-badge rnc-badge--warn">Overdue</span>';
							}
						} else {
							echo '—';
						}
						?>
					</div>
					<div class="lbl">Next Cron Run</div>
				</div>
			</div>

			<form method="post">
				<?php wp_nonce_field( 'rnc_test_nonce', '_rnc_nonce' ); ?>
				<input type="hidden" name="rnc_product" value="<?php echo esc_attr( $product ); ?>">
				<div class="rnc-row">
					<button type="submit" name="rnc_action" value="fetch" class="rnc-btn rnc-btn--primary">📥 Fetch Now</button>
					<?php if ( $is_disabled ) : ?>
						<button type="submit" name="rnc_action" value="enable" class="rnc-btn rnc-btn--success">✅ Enable</button>
					<?php else : ?>
						<button type="submit" name="rnc_action" value="disable" class="rnc-btn rnc-btn--danger">⛔ Disable</button>
					<?php endif; ?>
					<button type="submit" name="rnc_action" value="clear" class="rnc-btn rnc-btn--secondary">🗑️ Clear Cache</button>
					<button type="submit" name="rnc_action" value="clear_dismissals" class="rnc-btn rnc-btn--secondary">🔄 Reset Dismissals</button>
				</div>
			</form>
		</div>

		<?php if ( ! empty( $contents ) ) : ?>
		<div class="rnc-card">
			<h3>📋 Cached Notices for <code><?php echo esc_html( $product ); ?></code></h3>
			<table class="rnc-table">
				<thead>
					<tr><th>#</th><th>ID</th><th>Title</th><th>Dismissed?</th><th>Targeting</th></tr>
				</thead>
				<tbody>
					<?php foreach ( $contents as $i => $c ) :
						$cid        = $c['id'] ?? '—';
						$title      = $c['title'] ?? '(no title)';
						$is_dismissed = in_array( $cid, (array) $dismissed_ids, true );
						$has_targeting = ! empty( $c['targeting'] );
					?>
					<tr>
						<td><?php echo intval( $i + 1 ); ?></td>
						<td><code><?php echo esc_html( substr( $cid, 0, 12 ) ); ?>…</code></td>
						<td><?php echo esc_html( $title ); ?></td>
						<td>
							<?php if ( $is_dismissed ) : ?>
								<span class="rnc-badge rnc-badge--off">Yes</span>
							<?php else : ?>
								<span class="rnc-badge rnc-badge--on">No</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $has_targeting ) : ?>
								<span class="rnc-badge rnc-badge--warn">Has Rules</span>
							<?php else : ?>
								<span style="color:#9ca1ad;font-size:11px">None</span>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>

		<?php endforeach; ?>
	</div>
	<?php
}
