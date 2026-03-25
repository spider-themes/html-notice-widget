# HTML Notice Widget — Testing Guide

A ready-to-use Code Snippet that adds a **Test Dashboard** page under `Tools → HNW Test Dashboard` in wp-admin. Paste it into [Code Snippets](https://wordpress.org/plugins/code-snippets/) or any snippet plugin.

---

## Quick Setup

1. Install the **Code Snippets** plugin (or similar).
2. Create a new snippet → paste the **code below**.
3. Set it to **"Run everywhere"** and activate.
4. Go to **Tools → HNW Test Dashboard** in your admin.

---

## The Snippet

```php
<?php
/**
 * HTML Notice Widget — Test Dashboard
 *
 * Adds a tools page with buttons to:
 * - Initialize / disable / enable the SDK client
 * - Fetch notices from the API
 * - Send test analytics events (impression, click, dismissal)
 * - View raw analytics data
 * - Trigger the rollup cron manually
 *
 * Paste this into Code Snippets → Run everywhere.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_management_page(
		'HNW Test Dashboard',
		'HNW Test Dashboard',
		'manage_options',
		'hnw-test-dashboard',
		'hnw_test_dashboard_render'
	);
} );

/**
 * Handle form actions
 */
add_action( 'admin_init', function () {
	if ( ! isset( $_POST['hnw_test_action'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! check_admin_referer( 'hnw_test_nonce', 'hnw_test_nonce_field' ) ) {
		return;
	}

	$action   = sanitize_key( $_POST['hnw_test_action'] );
	$product  = sanitize_key( $_POST['hnw_test_product'] ?? 'test-product' );
	$hub_url  = esc_url_raw( $_POST['hnw_test_hub_url'] ?? '' );
	$endpoint = sanitize_key( $_POST['hnw_test_endpoint'] ?? '' );
	$message  = '';

	// Make sure SDK class is available.
	$sdk_file = WP_PLUGIN_DIR . '/html-notice-widget/sdk/class-remote-notice-client.php';
	if ( file_exists( $sdk_file ) ) {
		require_once $sdk_file;
	}

	switch ( $action ) {

		case 'init_client':
			if ( empty( $hub_url ) || empty( $endpoint ) ) {
				$message = '❌ Hub URL and Endpoint are required.';
				break;
			}
			$api_url = trailingslashit( $hub_url ) . 'wp-json/html-notice-widget/v1/content/' . $endpoint;
			$result  = \Remote_Notice_Client::init( $product, [
				'api_url'          => $api_url,
				'schedule'         => 'hourly',
				'capability'       => 'manage_options',
				'dismiss_duration' => WEEK_IN_SECONDS,
			] );
			$message = $result ? '✅ Client initialized for "' . $product . '"' : '⚠️ Client is disabled or already exists.';
			break;

		case 'fetch_notices':
			if ( class_exists( '\\Remote_Notice_Client' ) ) {
				$fetched = \Remote_Notice_Client::trigger_fetch( $product );
				$message = $fetched ? '✅ Fetch triggered for "' . $product . '"' : '⚠️ No active instance for "' . $product . '". Initialize first.';
			} else {
				$message = '❌ SDK class not found.';
			}
			break;

		case 'disable_client':
			if ( class_exists( '\\Remote_Notice_Client' ) ) {
				\Remote_Notice_Client::disable( $product );
				$message = '✅ Client disabled for "' . $product . '"';
			}
			break;

		case 'enable_client':
			if ( class_exists( '\\Remote_Notice_Client' ) ) {
				\Remote_Notice_Client::enable( $product );
				$message = '✅ Client enabled for "' . $product . '"';
			}
			break;

		case 'clear_data':
			if ( class_exists( '\\Remote_Notice_Client' ) ) {
				\Remote_Notice_Client::clear_all( $product );
				$message = '✅ All cached data cleared for "' . $product . '"';
			}
			break;

		case 'send_test_event':
			$event_type  = sanitize_key( $_POST['hnw_event_type'] ?? 'impression' );
			$campaign_id = sanitize_text_field( $_POST['hnw_campaign_id'] ?? 'test-campaign-001' );

			if ( empty( $hub_url ) || empty( $endpoint ) ) {
				$message = '❌ Hub URL and Endpoint are required.';
				break;
			}

			$track_url = trailingslashit( $hub_url ) . 'wp-json/html-notice-widget/v1/analytics/track';
			$response  = wp_remote_post( $track_url, [
				'body'    => wp_json_encode( [
					'endpoint'    => $endpoint,
					'campaign_id' => $campaign_id,
					'event_type'  => $event_type,
					'site_url'    => home_url(),
				] ),
				'headers' => [ 'Content-Type' => 'application/json' ],
				'timeout' => 10,
			] );

			if ( is_wp_error( $response ) ) {
				$message = '❌ Request failed: ' . $response->get_error_message();
			} else {
				$code = wp_remote_retrieve_response_code( $response );
				$body = wp_remote_retrieve_body( $response );
				$message = '✅ Event sent! HTTP ' . $code . ' — ' . $body;
			}
			break;

		case 'trigger_rollup':
			if ( class_exists( '\\HTML_Notice_Widget\\Analytics' ) ) {
				$result  = \HTML_Notice_Widget\Analytics::rollup_and_prune();
				$message = '✅ Rollup done: ' . intval( $result['rollup_count'] ) . ' rows rolled up, ' . intval( $result['pruned_count'] ) . ' raw rows pruned.';
			} else {
				$message = '❌ Analytics class not found. Is the plugin active?';
			}
			break;

		case 'view_raw_data':
			// Handled in render.
			break;
	}

	if ( $message ) {
		set_transient( 'hnw_test_message', $message, 30 );
	}

	wp_safe_redirect( admin_url( 'tools.php?page=hnw-test-dashboard' ) );
	exit;
} );

/**
 * Render the test dashboard page
 */
function hnw_test_dashboard_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$message      = get_transient( 'hnw_test_message' );
	$product      = 'test-product';
	$is_disabled  = false;
	$sdk_file     = WP_PLUGIN_DIR . '/html-notice-widget/sdk/class-remote-notice-client.php';

	if ( file_exists( $sdk_file ) ) {
		require_once $sdk_file;
		if ( class_exists( '\\Remote_Notice_Client' ) ) {
			$is_disabled = \Remote_Notice_Client::is_disabled( $product );
		}
	}

	// Get raw analytics data.
	global $wpdb;
	$raw_table     = $wpdb->prefix . 'hnw_analytics_raw';
	$summary_table = $wpdb->prefix . 'hnw_analytics_summary';
	$raw_rows      = $wpdb->get_results( "SELECT * FROM {$raw_table} ORDER BY id DESC LIMIT 20", ARRAY_A );
	$summary_rows  = $wpdb->get_results( "SELECT * FROM {$summary_table} ORDER BY id DESC LIMIT 20", ARRAY_A );

	if ( $message ) {
		delete_transient( 'hnw_test_message' );
	}
	?>
	<style>
		.hnw-test-wrap { max-width: 900px; margin: 20px auto; font-family: -apple-system, sans-serif; }
		.hnw-test-card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 16px; }
		.hnw-test-card h3 { margin-top: 0; font-size: 15px; color: #1f2937; }
		.hnw-test-row { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 10px; }
		.hnw-test-row label { font-size: 12px; font-weight: 600; color: #6b7280; display: block; margin-bottom: 4px; }
		.hnw-test-row input[type="text"], .hnw-test-row select { padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 5px; font-size: 13px; min-width: 200px; }
		.hnw-test-btn { display: inline-flex; align-items: center; gap: 5px; padding: 7px 16px; border: none; border-radius: 5px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
		.hnw-test-btn:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
		.hnw-test-btn--primary { background: #6c5ce7; color: #fff; }
		.hnw-test-btn--success { background: #00b894; color: #fff; }
		.hnw-test-btn--danger { background: #e17055; color: #fff; }
		.hnw-test-btn--secondary { background: #f0f1f4; color: #374151; border: 1px solid #d1d5db; }
		.hnw-test-msg { padding: 10px 14px; border-radius: 6px; background: #eef6ff; border-left: 4px solid #74b9ff; margin-bottom: 16px; font-size: 13px; }
		.hnw-test-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
		.hnw-test-badge--on { background: #e6f9f3; color: #00875a; }
		.hnw-test-badge--off { background: #fef0ed; color: #c0392b; }
		.hnw-test-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 10px; }
		.hnw-test-table th, .hnw-test-table td { padding: 6px 10px; text-align: left; border-bottom: 1px solid #f0f1f4; }
		.hnw-test-table th { background: #f8f9fb; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; }
		.hnw-test-table td { color: #374151; }
		.hnw-test-table code { background: #f0f1f4; padding: 1px 5px; border-radius: 3px; font-size: 11px; }
	</style>

	<div class="hnw-test-wrap">
		<h1 style="font-size:22px;font-weight:700;margin-bottom:6px;">🧪 HNW Test Dashboard</h1>
		<p style="color:#6b7280;margin-bottom:20px;">Test the HTML Notice Widget SDK and analytics system.</p>

		<?php if ( $message ) : ?>
			<div class="hnw-test-msg"><?php echo wp_kses_post( $message ); ?></div>
		<?php endif; ?>

		<!-- ── 1. SDK Controls ── -->
		<div class="hnw-test-card">
			<h3>📡 SDK Client Controls</h3>
			<p style="font-size:12px;color:#9ca1ad;margin-top:-4px;">
				Status: <span class="hnw-test-badge <?php echo $is_disabled ? 'hnw-test-badge--off' : 'hnw-test-badge--on'; ?>">
					<?php echo $is_disabled ? 'Disabled' : 'Enabled'; ?>
				</span>
			</p>
			<form method="post">
				<?php wp_nonce_field( 'hnw_test_nonce', 'hnw_test_nonce_field' ); ?>
				<input type="hidden" name="hnw_test_product" value="test-product">

				<div class="hnw-test-row">
					<div>
						<label>Hub Site URL</label>
						<input type="text" name="hnw_test_hub_url" placeholder="https://your-hub-site.com" value="">
					</div>
					<div>
						<label>Product Endpoint</label>
						<input type="text" name="hnw_test_endpoint" placeholder="my-product-slug" value="">
					</div>
				</div>

				<div class="hnw-test-row" style="margin-top:12px;">
					<button type="submit" name="hnw_test_action" value="init_client" class="hnw-test-btn hnw-test-btn--primary">🚀 Initialize Client</button>
					<button type="submit" name="hnw_test_action" value="fetch_notices" class="hnw-test-btn hnw-test-btn--success">📥 Fetch Notices</button>
					<button type="submit" name="hnw_test_action" value="disable_client" class="hnw-test-btn hnw-test-btn--danger">⛔ Disable</button>
					<button type="submit" name="hnw_test_action" value="enable_client" class="hnw-test-btn hnw-test-btn--success">✅ Enable</button>
					<button type="submit" name="hnw_test_action" value="clear_data" class="hnw-test-btn hnw-test-btn--secondary">🗑️ Clear Cache</button>
				</div>
			</form>
		</div>

		<!-- ── 2. Analytics Events ── -->
		<div class="hnw-test-card">
			<h3>📊 Send Test Analytics Events</h3>
			<form method="post">
				<?php wp_nonce_field( 'hnw_test_nonce', 'hnw_test_nonce_field' ); ?>
				<input type="hidden" name="hnw_test_action" value="send_test_event">

				<div class="hnw-test-row">
					<div>
						<label>Hub Site URL</label>
						<input type="text" name="hnw_test_hub_url" placeholder="https://your-hub-site.com" value="">
					</div>
					<div>
						<label>Product Endpoint</label>
						<input type="text" name="hnw_test_endpoint" placeholder="my-product-slug" value="">
					</div>
					<div>
						<label>Campaign ID</label>
						<input type="text" name="hnw_campaign_id" placeholder="campaign-uuid" value="test-campaign-001">
					</div>
					<div>
						<label>Event Type</label>
						<select name="hnw_event_type">
							<option value="impression">Impression</option>
							<option value="click">Click</option>
							<option value="dismissal">Dismissal</option>
						</select>
					</div>
				</div>

				<div class="hnw-test-row" style="margin-top:12px;">
					<button type="submit" class="hnw-test-btn hnw-test-btn--primary">📤 Send Event</button>
				</div>
			</form>
		</div>

		<!-- ── 3. Rollup Cron ── -->
		<div class="hnw-test-card">
			<h3>⏰ Analytics Cron</h3>
			<form method="post">
				<?php wp_nonce_field( 'hnw_test_nonce', 'hnw_test_nonce_field' ); ?>
				<input type="hidden" name="hnw_test_product" value="test-product">
				<button type="submit" name="hnw_test_action" value="trigger_rollup" class="hnw-test-btn hnw-test-btn--primary">🔄 Run Rollup Now</button>
				<span style="font-size:12px;color:#9ca1ad;margin-left:8px;">Aggregates raw → summary, prunes old data</span>
			</form>
		</div>

		<!-- ── 4. Raw Data Viewer ── -->
		<div class="hnw-test-card">
			<h3>🗄️ Raw Events (last 20)</h3>
			<?php if ( ! empty( $raw_rows ) ) : ?>
				<table class="hnw-test-table">
					<thead>
						<tr><th>ID</th><th>Endpoint</th><th>Campaign</th><th>Event</th><th>Site Hash</th><th>Time</th></tr>
					</thead>
					<tbody>
						<?php foreach ( $raw_rows as $r ) : ?>
							<tr>
								<td><?php echo absint( $r['id'] ); ?></td>
								<td><code><?php echo esc_html( $r['product_endpoint'] ); ?></code></td>
								<td><code><?php echo esc_html( substr( $r['campaign_id'], 0, 12 ) ); ?>…</code></td>
								<td><?php echo esc_html( $r['event_type'] ); ?></td>
								<td><code><?php echo esc_html( substr( $r['site_hash'], 0, 8 ) ); ?>…</code></td>
								<td><?php echo esc_html( $r['created_at'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p style="color:#9ca1ad;font-size:13px;font-style:italic;">No raw events yet. Send some test events above.</p>
			<?php endif; ?>
		</div>

		<div class="hnw-test-card">
			<h3>📈 Summary Data (last 20)</h3>
			<?php if ( ! empty( $summary_rows ) ) : ?>
				<table class="hnw-test-table">
					<thead>
						<tr><th>ID</th><th>Endpoint</th><th>Campaign</th><th>Date</th><th>Impr</th><th>Clicks</th><th>Dismiss</th><th>Sites</th></tr>
					</thead>
					<tbody>
						<?php foreach ( $summary_rows as $r ) : ?>
							<tr>
								<td><?php echo absint( $r['id'] ); ?></td>
								<td><code><?php echo esc_html( $r['product_endpoint'] ); ?></code></td>
								<td><code><?php echo esc_html( substr( $r['campaign_id'], 0, 12 ) ); ?>…</code></td>
								<td><?php echo esc_html( $r['event_date'] ); ?></td>
								<td><?php echo absint( $r['impressions'] ); ?></td>
								<td><?php echo absint( $r['clicks'] ); ?></td>
								<td><?php echo absint( $r['dismissals'] ); ?></td>
								<td><?php echo absint( $r['unique_sites'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p style="color:#9ca1ad;font-size:13px;font-style:italic;">No summary data yet. Run the rollup above after sending events.</p>
			<?php endif; ?>
		</div>

	</div>
	<?php
}
```

---

## How To Use

### Step 1 — Set up the Hub

On your **hub site** (where the plugin is installed):
1. Create a product (e.g., "Test Product" with endpoint `test-product`).
2. Add a campaign with some HTML content and enable it.
3. Note the hub site URL (e.g., `https://development.local`).

### Step 2 — Test the SDK

On the **test dashboard** page:
1. Enter the **Hub Site URL** and **Product Endpoint**.
2. Click **🚀 Initialize Client** — this sets up the SDK to fetch from your hub.
3. Click **📥 Fetch Notices** — pulls the latest campaigns from the hub API.
4. Reload any admin page — you should see the notice rendered.
5. Click **⛔ Disable** → reload → notice disappears. Click **✅ Enable** → it's back.

### Step 3 — Test Analytics

1. Enter the **Hub Site URL**, **Product Endpoint**, and a **Campaign ID** (copy from the hub's campaign list).
2. Select an event type and click **📤 Send Event**.
3. Check the **Raw Events** table at the bottom — your event should appear.
4. Click **🔄 Run Rollup Now** — raw data moves to the **Summary Data** table.
5. Go to **HTML Notice Widget → Analytics** on the hub — your data should show up.

### Step 4 — Verify SDK Beacons

1. After initializing the client and fetching notices, load any admin page.
2. Open **DevTools → Network** → filter by `analytics`.
3. You should see `sendBeacon` requests for `impression` events.
4. Click a link inside the notice → `click` beacon fires.
5. Dismiss the notice → `dismissal` beacon fires.
