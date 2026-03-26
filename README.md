# HTML Notice Widget Plugin

A powerful WordPress plugin for managing multiple HTML notice widgets with dynamic API endpoints and individual content switcher controls.

## Features

- **Dynamic Site Management**: Add, edit, and delete HTML notice widget sites from the admin panel
- **Multiple HTML Contents per Site**: Each site can contain multiple HTML contents with individual titles
- **Individual Switcher Controls**: Enable/disable individual HTML contents within each site with "Enable Offer" switches
- **Site-level Controls**: Overall enable/disable control for entire sites
- **API Endpoints**: Each product gets a unique API endpoint for retrieving enabled HTML contents
- **WordPress Options Integration**: All data is stored securely in WordPress options table
- **RESTful API**: Full REST API for programmatic access to both sites and individual contents
- **Clean Admin UI**: Beautiful and intuitive admin interface with content management

## Installation

1. Upload the `html-notice-widget` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to "HTML Notice Widget" in the admin menu to get started

## Usage

### Admin Panel

1. Go to **HTML Notice Widget** in the WordPress admin menu
2. Click **"+ Add New Product"** to create a new site
3. Fill in the following fields:
   - **Product Name**: A unique identifier (used for the API endpoint)
   - **Enable this site**: Toggle to enable/disable the entire site

4. Click **"Save Site"** to create the site
5. In the site card, click **"+ Add Content"** to add HTML contents
6. For each HTML content, fill in:
   - **Content Title**: A descriptive name for the content
   - **HTML Content**: The HTML content you want to serve
   - **Enable this offer**: Individual toggle to enable/disable this specific content

7. View the generated API URL for your site

### API Endpoints

#### Get Site Content by Endpoint (Public)
```
GET /wp-json/html-notice-widget/v1/content/{endpoint}
```

**Parameters:**
- `{endpoint}`: The product name (converted to lowercase with hyphens)

**Response (Success):**
```json
{
  "success": true,
  "contents": [
    {
      "id": "content-uuid",
      "title": "Special Offer",
      "content": "<div>HTML content here</div>"
    },
    {
      "id": "content-uuid-2", 
      "title": "Another Offer",
      "content": "<div>More HTML content</div>"
    }
  ],
  "site": {
    "id": "site-uuid",
    "product": "my-product"
  }
}
```

**Response (Error - Site not found or disabled):**
```json
{
  "code": "not_found",
  "message": "Site not found or not enabled",
  "data": {
    "status": 404
  }
}
```

**Dynamic Endpoint Generation:**
The endpoint is automatically generated from the product name:
- Product: "My Product" → Endpoint: "my-product"
- Product: "Special Offer 2024" → Endpoint: "special-offer-2024" 
- Product: "Test_Product" → Endpoint: "test-product"

### Admin Management
All site and content management is done through the **WordPress Admin Panel** at:
```
/wp-admin/admin.php?page=html-notice-widget
```

- ✅ **Add/Edit/Delete Sites**: Full CRUD operations via PHP forms
- ✅ **Add/Edit/Delete Contents**: Manage multiple HTML contents per site
- ✅ **Enable/Disable Controls**: Individual toggles for sites and contents
- ✅ **Real-time Statistics**: View total sites, contents, and enabled status

## Data Storage

All site data is stored in the WordPress options table under the option key: `html_notice_widget_sites`

The data structure:
```php
[
  [
    'id'       => 'unique-uuid',
    'product'  => 'product-name',
    'enabled'  => true,
    'endpoint' => 'product-name',
    'contents' => [
      [
        'id'      => 'content-uuid',
        'title'   => 'Content Title',
        'content' => '<html>content</html>',
        'enabled' => true
      ]
    ]
  ]
]
```

## Security

- **Admin-only endpoints**: Site and content management endpoints are restricted to users with `manage_options` capability
- **Public content endpoint**: The content endpoint is public (when site is enabled) allowing any client to fetch enabled HTML contents
- **Content sanitization**: HTML content is sanitized using WordPress `wp_kses_post()`
- **CSRF protection**: All requests include WordPress nonce verification

## Example Usage

### Frontend Implementation

```javascript
// Fetch all enabled HTML contents from a product endpoint
fetch('/wp-json/html-notice-widget/v1/content/my-product')
  .then(response => response.json())
  .then(data => {
    if (data.success && data.contents) {
      const container = document.getElementById('notice-container');
      
      // Display all enabled contents
      data.contents.forEach(content => {
        const contentDiv = document.createElement('div');
        contentDiv.className = 'html-notice-content';
        contentDiv.innerHTML = content.content;
        container.appendChild(contentDiv);
      });
    }
  });
```

### Using cURL

```bash
# Get all enabled contents for a product
curl https://example.com/wp-json/html-notice-widget/v1/content/my-product

# Example: Get contents for different product endpoints
curl https://example.com/wp-json/html-notice-widget/v1/content/special-offer
curl https://example.com/wp-json/html-notice-widget/v1/content/holiday-sale-2024
```

## Migration

The plugin automatically migrates existing sites from the old single-content structure to the new multi-content structure when activated. Any existing content will be preserved as "Migrated Content" entries.

## Hooks and Filters

### Filters

#### `html_notice_widget_sanitize_content`
Filter to customize HTML content sanitization:

```php
add_filter('html_notice_widget_sanitize_content', function($content) {
  // Custom sanitization logic
  return $content;
}, 10, 1);
```

## Troubleshooting

### Endpoint returns 404
- Make sure the site is **enabled** in the admin panel
- Check that at least one HTML content is **enabled** within the site
- Check that the endpoint slug matches the product name (lowercase, with hyphens)
- Verify permalinks are set to something other than "Plain"

### No contents returned
- Verify that individual HTML contents have their "Enable Offer" switches turned on
- Make sure the site itself is enabled

### Can't see changes
- Clear your browser cache
- Check WordPress debug log at `/wp-content/debug.log`

### REST API not working
- Make sure pretty permalinks are enabled
- Verify user has `manage_options` capability for admin endpoints

## Support

For issues, questions, or suggestions, please reach out through your support channel.

## License

GPL v2 or later

## Changelog

### Version 1.3.0 (25 March 2026)
- **New: Campaign Analytics System** – Track impressions, clicks, and dismissals across all remote sites using the integrated SDK beacons.
- **New: Analytics Dashboard** – Drill down into your products and campaigns to view aggregate statistics and click-through rates.
- **Improved: Remote SDK** – Notice rendering automatically triggers non-blocking telemetry beacons (`navigator.sendBeacon`) for accurate tracking.
- **Improved: Data Pruning** – Ephemeral raw tracking events are securely rolled up grouped by day and automatically pruned after 48 hours to minimize database size.

### Version 1.1.0
- **MAJOR UPDATE**: Restructured to support multiple HTML contents per site
- Added individual "Enable Offer" switches for each HTML content
- New API endpoints for content management within sites
- Updated admin interface with content listing and management
- Automatic migration from old single-content structure
- Enhanced frontend API to return array of enabled contents

### Version 1.0.0
- Initial release
- Dynamic site management
- API endpoints  
- Admin panel with UI
