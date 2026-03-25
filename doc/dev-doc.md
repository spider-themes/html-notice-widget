# HTML Notice Widget - Developer Documentation

This document serves as the architectural blueprint and developer guide for the **HTML Notice Widget** plugin.

## 1. Plugin Architecture

The plugin is structured around a central management hub (this plugin) and a remote client integration (the SDK).

### 1.1 Folder Structure

```
html-notice-widget/
├── html-notice-widget.php         # Main plugin script, constant definitions
├── README.md                      # General plugin information
├── includes/                      # Core PHP classes
│   ├── class-plugin.php           # Plugin bootstrap, cron, activation hooks
│   ├── class-api.php              # REST API registration (content + analytics)
│   ├── class-options.php          # Option management
│   ├── class-php-admin.php        # WordPress admin interface and CRUD logic
│   ├── class-php-utils.php        # Utility/helper functions (e.g., scheduling logic)
│   ├── class-analytics.php        # Analytics data layer (two-table rollup)
│   └── class-analytics-admin.php  # Analytics admin page rendering
├── sdk/                           # Remote client
│   └── class-remote-notice-client.php # The class to include in your remote plugins
├── doc/                           # Documentation
│   ├── dev-doc.md                 # Developer documentation (this file)
│   └── user-doc.md                # User/integration guide
└── assets/                        # CSS/JS for the WP Admin UI
    ├── css/php-admin.css          # Admin styles (including analytics)
    ├── js/php-admin.js            # Main admin JS
    └── js/analytics-admin.js      # Analytics page JS
```

### 1.2 Data Storage

All data related to the HTML Notice Widget is stored in the WordPress `wp_options` table under a single key: `html_notice_widget_sites`. This array is serialized automatically by WordPress.

**Structure:**
```php
array(
    array(
        'id'       => 'b5fca... (UUID)',
        'product'  => 'My Product Name',
        'endpoint' => 'my-product-name',  // the slug used in the REST API
        'enabled'  => true,               // global site toggle
        'contents' => array(              // inside each site are multiple contents
            array(
                'id'         => 'c9eab... (UUID)',
                'title'      => 'Notice Title',
                'content'    => '<div>HTML string...</div>',
                'enabled'    => true,
                // optional scheduling/condition fields are added via class-php-utils.php
            )
        )
    )
)
```

## 2. Core Functional Flow

### 2.1 The Admin Dashboard (`class-php-admin.php`)
Provides the UI in the WP Admin via `add_menu_page`. It handles the CRUD operations for:
1. Adding, updating, and removing sites.
2. Managing the `contents` array for each specific site.

### 2.2 The REST API (`class-api.php`)
Registers the public endpoint under the namespace `html-notice-widget/v1`.

**Endpoint:** `GET /wp-json/html-notice-widget/v1/content/{endpoint}`

When a remote client polls this endpoint, the API:
1. Checks the `html_notice_widget_sites` option.
2. Finds the site matching the `{endpoint}` slug.
3. If the site is `enabled`, it loops over the `contents` array.
4. Checks each content's `enabled` toggle, and evaluates `PHP_Utils::is_content_within_schedule()`.
5. Returns a JSON success array of the active contents holding `id`, `title`, and `content`.

### 2.3 The Processing Cron (`class-plugin.php`)
Schedules an hourly event `html_notice_widget_process_expired`. It delegates to `PHP_Utils::process_expired_content()` to auto-disable notices whose schedule has lapsed.

## 3. Remote SDK Integration (`class-remote-notice-client.php`)

The SDK handles fetching, caching, rendering, and dismissing notices on target sites.

### 3.1 Initializing the Client

Remote plugins bundle `class-remote-notice-client.php` and initialize it on `plugins_loaded`:

```php
Remote_Notice_Client::init( 'product-slug', array(
    'api_url' => '...',
    'schedule' => 'daily',
    'capability' => 'manage_options',
    'dismiss_duration' => WEEK_IN_SECONDS
) );
```

### 3.2 Key SDK Mechanisms
- **Cron Generation:** The SDK instantiates its own `wp_schedule_event` on the target site (e.g., `rnc_fetch_content_product-slug`).
- **Data Persistence:** The target site stores fetched contents locally in its `wp_options` (e.g., `rnc_product-slug_contents`).
- **Dismissal Handling:** When an admin clicks the 'X' to dismiss a notice, an AJAX request to `wp_ajax_rnc_dismiss_content_product-slug` stores a local dismissal record (e.g., `rnc_product-slug_dismissed_{content-id}`).
- **Sanitization:** Fetched data is deeply sanitized before rendering using `wp_kses()` with an explicitly allowed HTML tag set defined within `Remote_Notice_Client`.

## 4. Hooks and Filters

### 4.1 Filter: `html_notice_widget_sanitize_content`
Whenever an admin saves new notice content from the admin dashboard, you can hook into this filter to modify or restrict the allowed HTML elements stored in the database.

### 4.2 Action: `rnc_fetch_content_{product-slug}`
This is dynamically registered by the SDK client for executing the remote API fetch. You can technically force it using:
```php
do_action( "rnc_fetch_content_my-product-slug" );
```

## 5. UI Component Architecture (CSS/JS)

The admin panel interface uses native WordPress patterns blended with a custom vanilla CSS and jQuery-based component system.

### 5.1 CSS Components (`assets/css/php-admin.css`)

- **Modals:**
  - `.html-notice-widget-modal`: The main wrapper for all modals. Includes positioning and flex-centering.
  - `.modal-backdrop`: Dark semi-transparent overlay covering the screen.
  - `.modal-content`: The white container holding the modal body and headers.
  - `.modal-header` & `.modal-footer`: Structural padding and borders.
  - `.modal-close` & `.modal-cancel`: Close triggers and UI buttons.
- **Form Elements:**
  - Enclosed in `.html-notice-widget-form` and utilizing native WP `.form-table` styling.
  - `.regular-text` and `.large-text.code` for standard inputs and code textareas (focus states glow with WP blue `#0073aa`).
  - Standard `input[type="checkbox"]` inside `.form-table td label`.
  - `input[type="datetime-local"]` customized for schedule management with native datepickers.
- **Buttons (`.button` base class):**
  - `.button-primary`: Primary form submission actions (Create/Update).
  - `.button-danger`: Destructive actions (Delete overrides).
  - `.button-small`: Used for secondary inline actions (Edit/Delete).
  - `.docs-trigger` / `.copy-code-btn`: Specialized API integration button styles.
- **Layout & Structure Widgets:**
  - `.html-notice-widget-sites-grid`: The main CSS Grid driving the two-column site cards. Responsive flex on mobile.
  - `.html-notice-widget-site-card`: Individual product/site card structural styling (shadows, borders, padding).
  - Status badges for visual distinction such as `.status.enabled`, `.status.disabled`, and timetable bounds `.schedule-badge`.

### 5.2 Component-Based JavaScript (Inline via `class-php-admin.php`)

The plugin uses jQuery injected via `wp_add_inline_script` inside `class-php-admin.php` mapped to `admin_enqueue_scripts`. The architecture relies heavily on **Event Delegation** via `$(document).on(...)` allowing content nodes to be dynamically created/destroyed without needing listener re-bindings.

- **Modal Component Management:**
  - Contains centralized `openModal(modal)` and `closeModal(modal)` functions that append/remove `.modal-show` classes and toggle `body` overflow for scroll locking.
  - Modal closure relies on mapping backdrop clicks (`.modal-backdrop`), specific close buttons (`.modal-close`, `.modal-cancel`), and global `Escape` key (`keyCode 27`) event listeners.
- **Mapping Data to Input Fields (Edit Modals):**
  - Trigger buttons (e.g., `.edit-content-trigger`) store original widget states as `data-*` attributes (`data-site-id`, `data-content-title`, `data-content-html`).
  - Upon interaction (click), the JS component extracts this dataset and maps it strictly to the respective hidden HTML elements (`#edit-content-site-id`), standard DOM element inputs (`#edit_content_title`), code textareas (`#edit_content_html`), and manipulates properties for checkboxes (`#edit_content_enabled`).
  - Transforms database scheduling strings into normalized inputs compatible with `type="datetime-local"`.
- **Dynamic Code Block Injection:**
  - The documentation modal (`.docs-trigger`) handles live replacement via standard template literals. It overrides `#docs-product-name`, `#docs-init-code`, and `#docs-pro-code` text streams dynamically translating the backend PHP `$product` slugs and `$apiUrl` straight into "How to Integrate" visual snippets for users.
- **Utility Actions:**
  - Custom listeners intercept standard `form` submissions to immediately disable `.button[type="submit"]` elements visually changing them to "Processing...", effectively preventing double-submits.
  - Includes OS clipboard bindings via `navigator.clipboard.writeText()` mapped to the generic `.copy-code-btn`.
  - Uses an AJAX proxy GET parameter wrapper for triggering the SDK extraction `window.location.href = ajaxurl + '?action=hnw_download_sdk'`.

---

## 6. Campaign Analytics System

The analytics system tracks campaign performance (impressions, clicks, dismissals) across all remote sites using the SDK. It uses a **two-table rollup architecture** for scale and performance.

### 6.1 Architecture Overview

```
Remote Site (SDK)                     Hub Server
┌──────────────┐                   ┌────────────────────────┐
│ Notice Render │──sendBeacon()──→ │ POST /analytics/track  │
│ Link Click    │──sendBeacon()──→ │   ↓ rate limit check   │
│ Dismiss       │──sendBeacon()──→ │   ↓ sanitize + hash    │
└──────────────┘                   │   ↓ INSERT into _raw   │
                                   ├────────────────────────┤
                                   │ Hourly WP-Cron         │
                                   │   ↓ rollup _raw→_summary│
                                   │   ↓ prune _raw >48h    │
                                   ├────────────────────────┤
                                   │ Admin Analytics Page   │
                                   │   ↓ GET /summary/…     │
                                   │   ↓ GET /campaign/…    │
                                   │   ↓ render sidebar +   │
                                   │     campaign table +   │
                                   │     detail panel       │
                                   └────────────────────────┘
```

### 6.2 Data Storage — Two-Table Schema

Located in `includes/class-analytics.php`.

**Table 1: `{prefix}hnw_analytics_raw`** (write-heavy, ephemeral)

| Column | Type | Description |
|--------|------|-------------|
| `id` | `BIGINT AUTO_INCREMENT` | PK |
| `product_endpoint` | `VARCHAR(191)` | Product slug |
| `campaign_id` | `VARCHAR(36)` | Campaign UUID |
| `event_type` | `VARCHAR(20)` | `impression`, `click`, or `dismissal` |
| `site_hash` | `CHAR(32)` | MD5 of origin site URL (privacy) |
| `created_at` | `DATETIME` | Indexed for pruning |

**Table 2: `{prefix}hnw_analytics_summary`** (read-heavy, permanent)

| Column | Type | Description |
|--------|------|-------------|
| `id` | `BIGINT AUTO_INCREMENT` | PK |
| `product_endpoint` | `VARCHAR(191)` | Product slug |
| `campaign_id` | `VARCHAR(36)` | Campaign UUID |
| `event_date` | `DATE` | Daily bucket |
| `impressions` | `INT UNSIGNED` | Daily count |
| `clicks` | `INT UNSIGNED` | Daily count |
| `dismissals` | `INT UNSIGNED` | Daily count |
| `unique_sites` | `INT UNSIGNED` | Distinct site hashes per day |

Unique index: `(product_endpoint, campaign_id, event_date)` — enables `INSERT … ON DUPLICATE KEY UPDATE` for idempotent rollups.

### 6.3 Key Methods (`Analytics` class)

| Method | Purpose |
|--------|---------|
| `create_tables()` | `dbDelta()` for both tables. Called on activation |
| `record_event()` | Validate → rate check → sanitize → `$wpdb->insert()` into `_raw` |
| `rollup_and_prune()` | Aggregate `_raw` → `_summary` via SQL, then `DELETE` raw rows >48h |
| `get_campaign_stats()` | Totals + CTR + unique sites for one campaign |
| `get_product_summary()` | Per-campaign aggregates for the campaign list view |
| `get_global_totals()` | Cross-product totals for the stat ribbon |
| `drop_tables()` | For uninstall cleanup |

### 6.4 Rate Limiting

`record_event()` checks a transient keyed by MD5 of `$_SERVER['REMOTE_ADDR']`. Max **30 events per 60 seconds** per IP. Exceeding returns `false` silently — the REST response always returns `{ success: true }` to avoid leaking rate-limit info.

### 6.5 Privacy

Raw `site_url` values are **never stored**. They are `md5()`-hashed server-side before insertion. The `unique_sites` metric counts `DISTINCT site_hash` values.

---

## 7. Analytics REST API

Registered in `includes/class-api.php`.

### 7.1 Public Tracking Endpoint

```
POST /wp-json/html-notice-widget/v1/analytics/track
```

| Param | Type | Validation |
|-------|------|------------|
| `endpoint` | string | `sanitize_key`, required |
| `campaign_id` | string | `sanitize_text_field`, required |
| `event_type` | string | Enum: `impression`, `click`, `dismissal` |
| `site_url` | string | `esc_url_raw`, required |

- `permission_callback`: `__return_true` (public — called by SDK from remote sites).
- Always returns `{ "success": true }` regardless of rate limiting.

### 7.2 Admin Summary Endpoint

```
GET /wp-json/html-notice-widget/v1/analytics/summary/{endpoint}
```

- `permission_callback`: `current_user_can('manage_options')`.
- Returns per-campaign stats for the given product, enriched with campaign metadata (title, enabled status).
- Cached via `wp_cache_set()` for 5 minutes.

### 7.3 Admin Campaign Detail Endpoint

```
GET /wp-json/html-notice-widget/v1/analytics/campaign/{endpoint}/{campaign_id}
```

- `permission_callback`: `current_user_can('manage_options')`.
- Returns: `impressions`, `clicks`, `dismissals`, `ctr`, `unique_sites`.
- Cached via `wp_cache_set()` for 5 minutes.

---

## 8. SDK Analytics Beacons

Located in `sdk/class-remote-notice-client.php`, inside the inline `<script>` in `display_notices()`.

### 8.1 Beacon Helper

```javascript
var trackUrl = apiUrl.replace(/\/content\/[^\/]+$/, '/analytics/track');

function sendBeacon(eventType) {
    if (typeof navigator.sendBeacon === 'function') {
        var payload = JSON.stringify({
            endpoint: endpoint,
            campaign_id: cid,
            event_type: eventType,
            site_url: window.location.origin
        });
        navigator.sendBeacon(trackUrl, new Blob([payload], {type: 'application/json'}));
    }
}
```

### 8.2 Event Triggers

| Event | Trigger | Method |
|-------|---------|--------|
| `impression` | Immediately on notice render | `sendBeacon('impression')` |
| `click` | Click on `<a>` inside `.rnc-notice-content` | Delegated click listener |
| `dismissal` | Click on `.notice-dismiss` button | Before the existing AJAX dismiss XHR |

`navigator.sendBeacon()` is fire-and-forget, non-blocking, and doesn't interfere with page navigation.

---

## 9. Analytics Admin Page

### 9.1 Files

| File | Purpose |
|------|---------|
| `includes/class-analytics-admin.php` | PHP rendering + asset enqueuing |
| `assets/js/analytics-admin.js` | jQuery AJAX interactions |
| `assets/css/php-admin.css` (appended) | Analytics-specific styles |

### 9.2 Page Structure

- **Hero Header** — Reuses `hnw-hero` component with `bar-chart` icon.
- **Global Stat Cards** — 4× `hnw-stat-card`: Total Impressions, Total Clicks, Avg CTR, Active Campaigns. Server-rendered.
- **Two-Column Layout** (`hnw-analytics-layout`):
  - **Sidebar** (`hnw-sidebar`) — List of products, clickable, active state highlight.
  - **Content Panel** (`hnw-analytics-content`):
    - Campaign table (`hnw-analytics-table`) — loaded via AJAX on product click.
    - Detail panel (`hnw-detail-panel`) — 4× metric cards + CTR progress bar, shown on campaign row click.
- **Empty State** — Reuses `hnw-empty` when no products/data exist.

### 9.3 New CSS Components

| Component | Class | Used For |
|-----------|-------|----------|
| Sidebar | `.hnw-sidebar`, `.hnw-sidebar-item` | Product list with active/hover states |
| Metric Card | `.hnw-metric-card` | Compact stat display (icon + value + label) |
| Campaign Row | `.hnw-campaign-row` | Clickable table rows with active state |
| CTR Bar | `.hnw-ctr-bar`, `.hnw-ctr-bar__fill` | Visual click-through rate indicator |
| Skeleton | `.hnw-skeleton`, `.hnw-skeleton--row` | Shimmer loading placeholder |

### 9.4 New SVG Icons

Added to `PHP_Admin::svg_icon()`:
- `bar-chart` — Page hero icon
- `eye` — Impressions metric
- `mouse-pointer` — Clicks metric

---

## 10. Cron Jobs

| Cron Hook | Schedule | Handler | Purpose |
|-----------|----------|---------|---------|
| `html_notice_widget_process_expired` | Hourly | `Plugin::process_expired_content()` | Auto-disable expired notices |
| `hnw_analytics_rollup` | Hourly | `Plugin::run_analytics_rollup()` → `Analytics::rollup_and_prune()` | Aggregate raw events → summary, prune raw >48h |

Both are scheduled on activation and unscheduled on deactivation.

---

## 11. Developer Testing Guide

### 11.1 Database Setup

1. Deactivate then reactivate the plugin.
2. Verify tables exist:
```sql
SHOW TABLES LIKE '%hnw_analytics%';
-- Expected: wp_hnw_analytics_raw, wp_hnw_analytics_summary
```

### 11.2 Tracking Endpoint

**Insert test events:**
```bash
# Impression
curl -X POST "https://your-site.test/wp-json/html-notice-widget/v1/analytics/track" \
  -H "Content-Type: application/json" \
  -d '{"endpoint":"my-product","campaign_id":"test-campaign-001","event_type":"impression","site_url":"https://remote-site.com"}'

# Click
curl -X POST "https://your-site.test/wp-json/html-notice-widget/v1/analytics/track" \
  -H "Content-Type: application/json" \
  -d '{"endpoint":"my-product","campaign_id":"test-campaign-001","event_type":"click","site_url":"https://remote-site.com"}'

# Dismissal
curl -X POST "https://your-site.test/wp-json/html-notice-widget/v1/analytics/track" \
  -H "Content-Type: application/json" \
  -d '{"endpoint":"my-product","campaign_id":"test-campaign-001","event_type":"dismissal","site_url":"https://remote-site.com"}'
```

**Verify raw data:**
```sql
SELECT * FROM wp_hnw_analytics_raw ORDER BY id DESC LIMIT 10;
```

**Test rate limiting (should silently drop after 30):**
```bash
for i in $(seq 1 35); do
  curl -s -X POST "https://your-site.test/wp-json/html-notice-widget/v1/analytics/track" \
    -H "Content-Type: application/json" \
    -d '{"endpoint":"my-product","campaign_id":"test-campaign-001","event_type":"impression","site_url":"https://test.com"}'
done
# Count: SELECT COUNT(*) FROM wp_hnw_analytics_raw; -- should be 30, not 35
```

### 11.3 Rollup Cron

**Trigger manually via WP-CLI:**
```bash
wp cron event run hnw_analytics_rollup
```

**Verify rollup:**
```sql
SELECT * FROM wp_hnw_analytics_summary ORDER BY id DESC LIMIT 10;
-- Should show aggregated rows per (endpoint, campaign_id, date)

SELECT COUNT(*) FROM wp_hnw_analytics_raw;
-- Should be 0 (or only rows < 48h old)
```

### 11.4 Admin Stats Endpoints

```bash
# Product summary (requires authentication)
curl "https://your-site.test/wp-json/html-notice-widget/v1/analytics/summary/my-product" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  --cookie "wordpress_logged_in_HASH=VALUE"

# Campaign detail
curl "https://your-site.test/wp-json/html-notice-widget/v1/analytics/campaign/my-product/test-campaign-001" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  --cookie "wordpress_logged_in_HASH=VALUE"
```

### 11.5 SDK Beacon Verification

1. Install the SDK in a test plugin with a valid `api_url`.
2. Create a product + campaign on the hub, ensure it's enabled.
3. Load an admin page on the test site where the notice displays.
4. Open DevTools → Network tab → filter by `analytics`.
5. **Impression:** Should fire immediately on page load.
6. **Click:** Click any `<a>` inside the notice content.
7. **Dismiss:** Click the ✕ dismiss button.

### 11.6 Admin Page Verification

1. Navigate to **HTML Notice Widget → Analytics** in wp-admin.
2. Verify: hero header, 4 stat cards, product sidebar, campaign table loads on product click.
3. Click a campaign row → detail panel slides in with 4 metric cards + CTR bar.
4. Test with no data → empty state should render.
5. Test responsive: resize to <768px → sidebar stacks above content, metric grid becomes 2-col.

---
*Updated for HTML Notice Widget analytics system v1.3.0.*
