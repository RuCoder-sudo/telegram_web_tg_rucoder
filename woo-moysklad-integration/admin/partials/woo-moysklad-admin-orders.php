<?php
/**
 * Orders admin template.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$order_sync_enabled = get_option('woo_moysklad_order_sync_enabled', '1');
$order_sync_delay = get_option('woo_moysklad_order_sync_delay', '0');
$order_sync_delay_minutes = get_option('woo_moysklad_order_sync_delay_minutes', '60');
$order_status_sync_enabled = get_option('woo_moysklad_order_status_sync_enabled', '1');
$order_status_sync_from_ms = get_option('woo_moysklad_order_status_sync_from_ms', '1');
$order_prefix = get_option('woo_moysklad_order_prefix', 'WC-');

$sync_enabled_text = $order_sync_enabled === '1' 
    ? __('Enabled', 'woo-moysklad-integration')
    : __('Disabled', 'woo-moysklad-integration');

$status_sync_enabled_text = $order_status_sync_enabled === '1' 
    ? __('Enabled', 'woo-moysklad-integration')
    : __('Disabled', 'woo-moysklad-integration');

$status_sync_from_ms_text = $order_status_sync_from_ms === '1' 
    ? __('Enabled', 'woo-moysklad-integration')
    : __('Disabled', 'woo-moysklad-integration');

// Get status mapping
$status_mapping = get_option('woo_moysklad_order_status_mapping', array());

// Get order stats
global $wpdb;
$synced_orders_count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = '_ms_order_id'");

// Get recently synced orders
$recent_orders = $wpdb->get_results("
    SELECT p.ID, p.post_date, pm.meta_value as ms_order_id 
    FROM $wpdb->posts p
    JOIN $wpdb->postmeta pm ON p.ID = pm.post_id
    WHERE p.post_type = 'shop_order'
    AND pm.meta_key = '_ms_order_id'
    ORDER BY p.post_date DESC
    LIMIT 10
");
?>

<div class="wrap woo-moysklad-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="subheader">
        <?php _e('Manage order synchronization between WooCommerce and MoySklad.', 'woo-moysklad-integration'); ?>
    </div>
    
    <div class="woo-moysklad-card">
        <h2><?php _e('Order Synchronization Status', 'woo-moysklad-integration'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th><?php _e('Order Sync', 'woo-moysklad-integration'); ?></th>
                <td><?php echo esc_html($sync_enabled_text); ?></td>
            </tr>
            <tr>
                <th><?php _e('Order Status Sync', 'woo-moysklad-integration'); ?></th>
                <td><?php echo esc_html($status_sync_enabled_text); ?></td>
            </tr>
            <tr>
                <th><?php _e('Status Sync from MoySklad', 'woo-moysklad-integration'); ?></th>
                <td><?php echo esc_html($status_sync_from_ms_text); ?></td>
            </tr>
            <tr>
                <th><?php _e('Order Prefix', 'woo-moysklad-integration'); ?></th>
                <td><?php echo esc_html($order_prefix); ?></td>
            </tr>
            <tr>
                <th><?php _e('Orders Synced', 'woo-moysklad-integration'); ?></th>
                <td><?php echo esc_html($synced_orders_count); ?></td>
            </tr>
            <tr>
                <th><?php _e('Sync Delay', 'woo-moysklad-integration'); ?></th>
                <td>
                    <?php 
                    if ($order_sync_delay === 'delayed') {
                        echo sprintf(
                            __('Orders are synced after %d minutes', 'woo-moysklad-integration'),
                            intval($order_sync_delay_minutes)
                        );
                    } else {
                        _e('Orders are synced immediately', 'woo-moysklad-integration');
                    }
                    ?>
                </td>
            </tr>
        </table>
        
        <div class="woo-moysklad-actions">
            <a href="<?php echo admin_url('admin.php?page=woo-moysklad-settings#orders-tab'); ?>" class="button button-primary">
                <?php _e('Order Settings', 'woo-moysklad-integration'); ?>
            </a>
            <button id="bulk-sync-orders" class="button">
                <?php _e('Синхронизировать все заказы', 'woo-moysklad-integration'); ?>
            </button>
            <button id="orders-only-sync" class="button" style="background-color: #0073aa; color: #fff; margin-left: 10px;">
                <?php _e('ТОЛЬКО ЗАКАЗЫ', 'woo-moysklad-integration'); ?>
            </button>
            <button id="stop-order-sync" class="button button-danger" style="display: none; background-color: #dc3232; color: #fff;">
                <?php _e('Остановить синхронизацию', 'woo-moysklad-integration'); ?>
            </button>
        </div>
        
        <div id="sync-status"></div>
    </div>
    
    <div class="woo-moysklad-card">
        <h2><?php _e('Синхронизация отдельного заказа', 'woo-moysklad-integration'); ?></h2>
        
        <p><?php _e('Введите ID заказа WooCommerce для синхронизации с МойСклад', 'woo-moysklad-integration'); ?></p>
        
        <div class="woo-moysklad-single-order-sync">
            <input type="number" id="single-order-id" placeholder="<?php _e('ID заказа', 'woo-moysklad-integration'); ?>" min="1" style="vertical-align: middle; width: 100px; margin-right: 10px;" />
            
            <button id="sync-single-order" class="button">
                <?php _e('Синхронизировать заказ', 'woo-moysklad-integration'); ?>
            </button>
            
            <button id="stop-order-sync" class="button button-danger" style="display: none; background-color: #dc3232; color: #fff;">
                <?php _e('Остановить синхронизацию', 'woo-moysklad-integration'); ?>
            </button>
        </div>
        
        <div id="order-sync-status" style="margin-top: 15px;"></div>
    </div>
    
    <div id="bulk-sync-progress" class="woo-moysklad-progress" style="display: none;">
        <p id="sync-status-message"><?php _e('Синхронизация заказов...', 'woo-moysklad-integration'); ?></p>
        <div class="progress-bar">
            <div class="progress-bar-fill" style="width: 0%;"></div>
        </div>
        <p id="sync-orders-count">0 / 0</p>
    </div>
    
    <div class="woo-moysklad-card">
        <h2><?php _e('Order Status Mapping', 'woo-moysklad-integration'); ?></h2>
        
        <?php if (empty($status_mapping)) : ?>
            <p><?php _e('No order status mapping configured. Please configure the status mapping in the settings.', 'woo-moysklad-integration'); ?></p>
        <?php else : ?>
            <table class="status-mapping-table">
                <thead>
                    <tr>
                        <th><?php _e('WooCommerce Status', 'woo-moysklad-integration'); ?></th>
                        <th><?php _e('MoySklad Status ID', 'woo-moysklad-integration'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status_mapping as $wc_status => $ms_status) : ?>
                        <?php if (!empty($ms_status)) : ?>
                            <tr>
                                <td><?php echo esc_html(wc_get_order_status_name($wc_status)); ?></td>
                                <td><?php echo esc_html($ms_status); ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <p class="description">
            <?php _e('This table shows how WooCommerce order statuses are mapped to MoySklad order statuses.', 'woo-moysklad-integration'); ?>
            <?php _e('You can configure this mapping in the Order Settings tab.', 'woo-moysklad-integration'); ?>
        </p>
    </div>
    
    <div class="woo-moysklad-card">
        <h2><?php _e('Recently Synced Orders', 'woo-moysklad-integration'); ?></h2>
        
        <?php if (empty($recent_orders)) : ?>
            <p><?php _e('No orders have been synced yet.', 'woo-moysklad-integration'); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Order ID', 'woo-moysklad-integration'); ?></th>
                        <th><?php _e('Date', 'woo-moysklad-integration'); ?></th>
                        <th><?php _e('MoySklad ID', 'woo-moysklad-integration'); ?></th>
                        <th><?php _e('Actions', 'woo-moysklad-integration'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_orders as $order) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo admin_url('post.php?post=' . $order->ID . '&action=edit'); ?>">
                                    <?php echo esc_html('#' . $order->ID); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($order->post_date))); ?></td>
                            <td><?php echo esc_html($order->ms_order_id); ?></td>
                            <td>
                                <a href="<?php echo admin_url('post.php?post=' . $order->ID . '&action=edit'); ?>" class="button button-small">
                                    <?php _e('View', 'woo-moysklad-integration'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="woo-moysklad-card">
        <h2><?php _e('Order Synchronization Guide', 'woo-moysklad-integration'); ?></h2>
        
        <p>
            <?php _e('This plugin synchronizes orders from WooCommerce to MoySklad. Here\'s how it works:', 'woo-moysklad-integration'); ?>
        </p>
        
        <ol>
            <li><?php _e('When a new order is created in WooCommerce, it is automatically sent to MoySklad.', 'woo-moysklad-integration'); ?></li>
            <li><?php _e('Order items are mapped to products in MoySklad using the product mapping created during product synchronization.', 'woo-moysklad-integration'); ?></li>
            <li><?php _e('Customer information is sent to MoySklad and linked to the order.', 'woo-moysklad-integration'); ?></li>
            <li><?php _e('When order statuses change in WooCommerce, they can be automatically updated in MoySklad based on your status mapping.', 'woo-moysklad-integration'); ?></li>
            <li><?php _e('Optionally, status changes in MoySklad can be synced back to WooCommerce if webhooks are properly configured.', 'woo-moysklad-integration'); ?></li>
        </ol>
        
        <p>
            <?php _e('To configure order synchronization settings, go to the Order Settings tab.', 'woo-moysklad-integration'); ?>
        </p>
    </div>
</div>
