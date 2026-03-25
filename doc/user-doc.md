# HTML Notice Widget - User Documentation

Welcome to the HTML Notice Widget! This plugin allows you to centrally manage and distribute admin notices (HTML content) to multiple WordPress sites or products from a single unified dashboard.

## Overview

As a plugin or theme developer, or a manager of multiple sites, you often need to show promotional banners, update notices, or special offers across multiple WordPress installations. The HTML Notice Widget solves this by acting as a **central hub**:

1. You create **Products/Sites** in this central hub.
2. For each product, you can add multiple **Notices** (HTML contents).
3. The individual products/sites use our built-in SDK to automatically fetch and display these notices.
4. You can enable, disable, and schedule notices centrally, and changes reflect remotely without touching the client sites.

---

## Installation & Setup

1. **Install the Plugin:** Upload the `html-notice-widget` folder to your `/wp-content/plugins/` directory on your central management site.
2. **Activate:** Go to the "Plugins" menu in WordPress and activate "HTML Notice Widget".
3. **Navigate:** You will see a new menu item called **HTML Notice Widget** in your WordPress admin sidebar.

---

## Managing Products (Sites)

A "Product" represents an endpoint that your remote sites or plugins will check. For example, if you sell a plugin called "Super SEO Form", you would create a product for it.

### Adding a New Product
1. Go to **HTML Notice Widget**.
2. Click the **+ Add New Product** button.
3. Enter the **Product Name** (e.g., "Super SEO Form"). Note that this generates a slug (endpoint) like `super-seo-form`.
4. Toggle **Enable this site** to turn it ON.
5. Click **Save Site**.

---

## Adding Notices (Contents)

Once you have a product, you can add multiple notices specifically targeted to that product.

1. Find your created product card.
2. Click **+ Add Content**.
3. Fill in the details:
   - **Content Title:** For your internal reference (e.g., "Black Friday Sale 2024").
   - **HTML Content:** The actual HTML that will be displayed on the remote sites. You can use standard formatting, colors, and links.
   - **Enable this offer:** Toggle this ON so it becomes active.
   - **Scheduling (Optional):** You can set start and end dates/times for the notice to be automatically published and removed.

You can add multiple notices to a single product. When the remote plugin fetches from the endpoint, it will receive all enabled and scheduled notices and display them sequentially to the user.

---

## Remote Integration (For Your Plugins/Themes)

If you are a developer bundling this into your own plugin for your users, you need to use the bundled SDK to fetch the notices.

1. Locate the `sdk/class-remote-notice-client.php` file included with this plugin.
2. Copy this file into your own plugin/theme (e.g., into an `includes/` folder).
3. In your main plugin file, initialize the client to start fetching notices from your central hub:

```php
// Include the client class
require_once dirname( __FILE__ ) . '/includes/class-remote-notice-client.php';

// Initialize it
add_action( 'plugins_loaded', function() {
    Remote_Notice_Client::init( 
        'super-seo-form', // The endpoint/product slug you created in the hub
        array(
            'api_url'          => 'https://your-central-site.com/wp-json/html-notice-widget/v1/content/super-seo-form',
            'schedule'         => 'daily', // How often to fetch (hourly, twicedaily, daily)
            'dismiss_duration' => WEEK_IN_SECONDS, // How long before a dismissed notice comes back
        )
    );
});
```

That's it! Your plugin will now automatically poll your central server daily and display any active notices in the remote WordPress admin dashboard. If a user dismisses the notice, it stays away until a new notice is added or the `dismiss_duration` expires.
