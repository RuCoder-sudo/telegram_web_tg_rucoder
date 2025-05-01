<?php
/**
 * Fired during plugin deactivation.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */
class Woo_Moysklad_Integration_Deactivator {

    /**
     * Deactivate the plugin.
     *
     * Clear scheduled events and perform other deactivation tasks.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('woo_moysklad_product_sync_cron');
        wp_clear_scheduled_hook('woo_moysklad_inventory_sync_cron');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
