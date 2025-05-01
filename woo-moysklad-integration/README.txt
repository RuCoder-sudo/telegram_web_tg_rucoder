=== WooCommerce MoySklad Integration ===
Contributors: yourname
Tags: woocommerce, moysklad, integration, inventory, products, orders, synchronization, мойсклад
Requires at least: 5.0
Tested up to: 6.3
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Comprehensive integration between WooCommerce and MoySklad.ru (МойСклад) for product, inventory, and order synchronization.

== Description ==

WooCommerce MoySklad Integration provides a robust connection between your WooCommerce store and MoySklad (МойСклад) inventory management system. 

**Key Features:**

**Product Synchronization**
* Synchronize products from MoySklad to WooCommerce
* Transfer product names, descriptions, prices, and SKUs
* Import product images
* Support for variable products and modifications
* Support for product sets and bundles
* Synchronize product categories with full nested structure
* Map custom fields to product attributes

**Inventory Management**
* Synchronize stock levels from selected warehouses
* Automatic stock updates on schedule
* Real-time stock updates via webhooks
* Reserve management

**Order Management**
* Send WooCommerce orders to MoySklad automatically
* Synchronize order status changes in both directions
* Map WooCommerce order statuses to MoySklad statuses
* Customer data synchronization

**Customer Management**
* Create and update customers in MoySklad
* Assign customers to groups
* Support for customer-specific pricing

**Additional Features**
* Two synchronization modes: standard and accelerated
* Detailed activity logging
* Webhooks for real-time updates
* Extensive customization options
* Multiple scheduling options
* Comprehensive admin interface

== Installation ==

1. Upload the `woo-moysklad-integration` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to MoySklad menu in WordPress admin
4. Configure your API credentials in the Settings tab
5. Configure synchronization options as needed
6. Start synchronization

== Frequently Asked Questions ==

= Does this plugin require a MoySklad account? =

Yes, you need an active MoySklad (МойСклад) account and API access credentials.

= How often are products and inventory synchronized? =

You can configure the synchronization schedule in the plugin settings. Available options include hourly, twice daily, and daily updates. You can also enable real-time updates using webhooks.

= Can I synchronize existing products? =

Yes, the plugin will attempt to match existing products based on SKU or product name. You can also initiate a full synchronization from the admin interface.

= Does this plugin support variable products? =

Yes, product modifications in MoySklad will be synchronized as variable products in WooCommerce.

= Will my WooCommerce orders be sent to MoySklad? =

Yes, by default all new orders in WooCommerce will be automatically sent to MoySklad. You can configure the delay or disable this feature in the settings.

= Does the plugin support real-time updates? =

Yes, with webhooks enabled, changes in MoySklad (like inventory levels or order status changes) will be reflected in your WooCommerce store almost immediately.

= What languages does the plugin support? =

The plugin supports English and Russian languages.

== Screenshots ==

1. Main dashboard
2. Product synchronization settings
3. Inventory synchronization settings
4. Order synchronization settings
5. Webhooks configuration
6. Logs view

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release

== Configuration Guide ==

### Step 1: API Configuration

1. Log in to your MoySklad account
2. Go to your profile settings and create an API access token
3. In WordPress admin, navigate to MoySklad > Settings > API Settings
4. Enter your API credentials and test the connection

### Step 2: Product Synchronization Settings

1. Go to MoySklad > Settings > Products
2. Enable product synchronization
3. Configure which product data to synchronize (descriptions, images, categories, etc.)
4. Choose the synchronization mode and schedule

### Step 3: Inventory Settings

1. Go to MoySklad > Settings > Inventory
2. Select which warehouse to use for stock levels
3. Configure the synchronization schedule for inventory updates

### Step 4: Order Settings

1. Go to MoySklad > Settings > Orders
2. Enable order synchronization
3. Configure organization and warehouse settings
4. Set up status mapping between WooCommerce and MoySklad order statuses

### Step 5: Webhook Configuration (Optional)

For real-time updates:

1. Go to MoySklad > Settings > Webhooks
2. Enable webhooks
3. Click "Register Webhooks in MoySklad" to automatically set up the required webhooks

### Step 6: Starting Synchronization

1. Go to MoySklad > Products
2. Click "Sync Products Now" to start the initial synchronization
3. Monitor the progress in the Logs section

== MoySklad API Configuration ==

To use this plugin, you need to configure API access in your MoySklad account:

1. Log in to MoySklad (moysklad.ru)
2. Go to Settings > API Access
3. Create a new API account or use your main account credentials
4. Make sure the account has the necessary permissions:
   * Read/write access to Products
   * Read/write access to Orders
   * Read access to Warehouses
   * Read/write access to Customers
   * Read/write access to Organizations

For webhooks to work properly, your account also needs permission to manage webhooks.

== Troubleshooting ==

**Synchronization not working:**
* Check your API credentials
* Verify your server's PHP settings - the plugin requires PHP 7.2 or higher
* Look for error messages in the Logs section

**Products not appearing in WooCommerce:**
* Check that the products are active and visible in MoySklad
* Verify that product synchronization is enabled
* Check the logs for any specific errors related to product synchronization

**Stock levels not updating:**
* Verify that the correct warehouse is selected in settings
* Check that inventory synchronization is enabled
* Try a manual inventory sync

**Orders not appearing in MoySklad:**
* Verify that order synchronization is enabled
* Check the organization and warehouse settings
* Check the logs for any errors related to order creation

**Webhooks not working:**
* Ensure your site is accessible from the internet
* Verify that webhooks are enabled in the plugin settings
* Check that webhooks are properly registered in MoySklad
* Look for webhook-related errors in the logs
