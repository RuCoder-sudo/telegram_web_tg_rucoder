<?php
/**
 * Fired during plugin activation.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */
class Woo_Moysklad_Integration_Activator {

    /**
     * Activate the plugin.
     *
     * Initialize the plugin by setting up default options, database tables,
     * and any other activation requirements.
     *
     * @since    1.0.0
     */
    public static function activate() {
        // Default settings
        self::setup_default_settings();
        
        // Create custom tables if needed
        self::create_tables();
        
        // Set a transient for the activation message
        set_transient('woo_moysklad_activation_notice', true, 5);
        
        // Schedule bonus attributes registration
        if (get_option('woo_moysklad_bonus_integration_enabled') === '1') {
            wp_schedule_single_event(time() + 30, 'woo_moysklad_register_bonus_attributes');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Setup default settings for the plugin.
     *
     * @since    1.0.0
     */
    private static function setup_default_settings() {
        // API settings
        if (!get_option('woo_moysklad_api_login')) {
            add_option('woo_moysklad_api_login', '');
        }
        
        if (!get_option('woo_moysklad_api_password')) {
            add_option('woo_moysklad_api_password', '');
        }
        
        // Product sync settings
        if (!get_option('woo_moysklad_sync_enabled')) {
            add_option('woo_moysklad_sync_enabled', '0');
        }
        
        if (!get_option('woo_moysklad_product_sync_interval')) {
            add_option('woo_moysklad_product_sync_interval', 'daily');
        }
        
        if (!get_option('woo_moysklad_sync_product_images')) {
            add_option('woo_moysklad_sync_product_images', '1');
        }
        
        if (!get_option('woo_moysklad_sync_product_description')) {
            add_option('woo_moysklad_sync_product_description', '1');
        }
        
        if (!get_option('woo_moysklad_sync_product_groups')) {
            add_option('woo_moysklad_sync_product_groups', '1');
        }
        
        if (!get_option('woo_moysklad_sync_product_modifications')) {
            add_option('woo_moysklad_sync_product_modifications', '1');
        }
        
        if (!get_option('woo_moysklad_sync_product_bundles')) {
            add_option('woo_moysklad_sync_product_bundles', '1');
        }
        
        if (!get_option('woo_moysklad_sync_product_custom_fields')) {
            add_option('woo_moysklad_sync_product_custom_fields', '1');
        }
        
        if (!get_option('woo_moysklad_sync_mode')) {
            add_option('woo_moysklad_sync_mode', 'standard'); // standard or accelerated
        }
        
        // Inventory sync settings
        if (!get_option('woo_moysklad_inventory_sync_interval')) {
            add_option('woo_moysklad_inventory_sync_interval', 'hourly');
        }
        
        if (!get_option('woo_moysklad_inventory_warehouse_id')) {
            add_option('woo_moysklad_inventory_warehouse_id', '');
        }
        
        // Order sync settings
        if (!get_option('woo_moysklad_order_sync_enabled')) {
            add_option('woo_moysklad_order_sync_enabled', '1');
        }
        
        if (!get_option('woo_moysklad_order_organization_id')) {
            add_option('woo_moysklad_order_organization_id', '');
        }
        
        if (!get_option('woo_moysklad_order_warehouse_id')) {
            add_option('woo_moysklad_order_warehouse_id', '');
        }
        
        if (!get_option('woo_moysklad_order_customer_group_id')) {
            add_option('woo_moysklad_order_customer_group_id', '');
        }
        
        if (!get_option('woo_moysklad_order_prefix')) {
            add_option('woo_moysklad_order_prefix', 'WC-');
        }
        
        // Order status mapping
        if (!get_option('woo_moysklad_order_status_mapping')) {
            $default_mapping = array(
                'pending' => '',
                'processing' => '',
                'on-hold' => '',
                'completed' => '',
                'cancelled' => '',
                'refunded' => '',
                'failed' => '',
            );
            add_option('woo_moysklad_order_status_mapping', $default_mapping);
        }
        
        // Webhook settings
        if (!get_option('woo_moysklad_webhook_enabled')) {
            add_option('woo_moysklad_webhook_enabled', '0');
        }
        
        if (!get_option('woo_moysklad_webhook_secret')) {
            add_option('woo_moysklad_webhook_secret', wp_generate_password(32, false));
        }
        
        // Bonus integration settings
        if (!get_option('woo_moysklad_bonus_integration_enabled')) {
            add_option('woo_moysklad_bonus_integration_enabled', '0');
        }
        
        if (!get_option('woo_moysklad_bonus_used_attribute_id')) {
            add_option('woo_moysklad_bonus_used_attribute_id', '6af5c95b-f91b-11eb-0a80-0656000e3f2c');
        }
        
        if (!get_option('woo_moysklad_bonus_earned_attribute_id')) {
            add_option('woo_moysklad_bonus_earned_attribute_id', '7bc8dfbb-f91b-11eb-0a80-0656000e3f2d');
        }
        
        if (!get_option('woo_moysklad_bonus_balance_attribute_id')) {
            add_option('woo_moysklad_bonus_balance_attribute_id', '8c24e9bb-f91b-11eb-0a80-0656000e3f2e');
        }
    }
    
    /**
     * Create custom tables required by the plugin.
     *
     * @since    1.0.0
     */
    private static function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'woo_moysklad_product_mapping';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            woo_product_id bigint(20) NOT NULL,
            ms_product_id varchar(255) NOT NULL,
            ms_product_meta text NULL,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY woo_product_id (woo_product_id),
            KEY ms_product_id (ms_product_id)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        // Create log table
        $log_table_name = $wpdb->prefix . 'woo_moysklad_logs';
        
        $log_sql = "CREATE TABLE $log_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            level varchar(10) NOT NULL,
            message text NOT NULL,
            context text NULL,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        dbDelta($log_sql);
    }
}
