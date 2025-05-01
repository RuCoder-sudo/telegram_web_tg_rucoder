<?php
/**
 * Order Synchronization
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */

/**
 * Order Synchronization class.
 *
 * This class handles synchronization of orders from WooCommerce to MoySklad.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */
class Woo_Moysklad_Order_Sync {

    /**
     * API instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Woo_Moysklad_API    $api    API instance.
     */
    private $api;
    
    /**
     * Logger instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Woo_Moysklad_Logger    $logger    Logger instance.
     */
    private $logger;
    
    /**
     * Constructor.
     *
     * @since    1.0.0
     * @param    Woo_Moysklad_API       $api      API instance.
     * @param    Woo_Moysklad_Logger    $logger   Logger instance.
     */
    public function __construct($api, $logger) {
        $this->api = $api;
        $this->logger = $logger;
    }
    
    /**
     * Sync a new order to MoySklad.
     *
     * @since    1.0.0
     * @param    int    $order_id    The WooCommerce order ID.
     */
    public function sync_new_order($order_id) {
        $order_sync_enabled = get_option('woo_moysklad_order_sync_enabled', '1');
        
        if ($order_sync_enabled !== '1' || !$this->api->is_configured()) {
            return;
        }
        
        // Check if sync should be delayed
        $sync_delay = get_option('woo_moysklad_order_sync_delay', '0');
        
        if ($sync_delay === 'delayed') {
            // Schedule delayed sync
            $delay_minutes = (int)get_option('woo_moysklad_order_sync_delay_minutes', '60');
            wp_schedule_single_event(time() + ($delay_minutes * 60), 'woo_moysklad_delayed_order_sync', array($order_id));
            $this->logger->info("Scheduled delayed sync for order #$order_id in $delay_minutes minutes");
            return;
        }
        
        // Proceed with immediate sync
        $this->create_or_update_order($order_id);
    }
    
    /**
     * Handle order status change.
     *
     * @since    1.0.0
     * @param    int       $order_id         The WooCommerce order ID.
     * @param    string    $old_status       The old order status.
     * @param    string    $new_status       The new order status.
     */
    public function handle_order_status_change($order_id, $old_status, $new_status) {
        $order_sync_enabled = get_option('woo_moysklad_order_sync_enabled', '1');
        $order_status_sync_enabled = get_option('woo_moysklad_order_status_sync_enabled', '1');
        
        if ($order_sync_enabled !== '1' || $order_status_sync_enabled !== '1' || !$this->api->is_configured()) {
            return;
        }
        
        // Get MS order ID
        $ms_order_id = get_post_meta($order_id, '_ms_order_id', true);
        
        if (!$ms_order_id) {
            // Order not yet in MoySklad, create it
            $this->create_or_update_order($order_id);
            return;
        }
        
        // Update order status in MoySklad
        $this->update_order_status($order_id, $ms_order_id, $new_status);
    }
    
    /**
     * Create or update an order in MoySklad.
     *
     * @since    1.0.0
     * @param    int       $order_id    The WooCommerce order ID.
     * @return   bool                   Whether the operation was successful.
     */
    public function create_or_update_order($order_id) {
        try {
            // Check if sync was stopped by user
            if (get_option('woo_moysklad_sync_stopped_by_user', '0') === '1') {
                $this->logger->info("Синхронизация заказа #$order_id прервана пользователем");
                return false;
            }
            
            $this->logger->info("Processing order #$order_id for MoySklad sync");
            
            $order = wc_get_order($order_id);
            
            if (!$order) {
                throw new Exception("Order #$order_id not found");
            }
            
            // Check if order already exists in MoySklad
            $ms_order_id = get_post_meta($order_id, '_ms_order_id', true);
            
            // Prepare order data
            $order_data = $this->prepare_order_data($order);
            
            if ($ms_order_id) {
                // Update existing order
                $response = $this->api->update_order($ms_order_id, $order_data);
                
                if (is_wp_error($response)) {
                    throw new Exception('Failed to update order in MoySklad: ' . $response->get_error_message());
                }
                
                $this->logger->info("Updated order #$order_id in MoySklad", array('ms_order_id' => $ms_order_id));
                return true;
            } else {
                // Create new order
                $response = $this->api->create_order($order_data);
                
                if (is_wp_error($response)) {
                    throw new Exception('Failed to create order in MoySklad: ' . $response->get_error_message());
                }
                
                // Store MoySklad order ID
                if (isset($response['id'])) {
                    update_post_meta($order_id, '_ms_order_id', $response['id']);
                    $this->logger->info("Created order #$order_id in MoySklad", array('ms_order_id' => $response['id']));
                    
                    // Add note to the order
                    $order->add_order_note(__('Order synchronized with MoySklad', 'woo-moysklad-integration'));
                    
                    return true;
                } else {
                    throw new Exception('Invalid response from MoySklad API');
                }
            }
        } catch (Exception $e) {
            $this->logger->error("Order sync error for #$order_id: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update order status in MoySklad.
     *
     * @since    1.0.0
     * @param    int       $order_id       The WooCommerce order ID.
     * @param    string    $ms_order_id    The MoySklad order ID.
     * @param    string    $status         The new order status.
     * @return   bool                      Whether the update was successful.
     */
    public function update_order_status($order_id, $ms_order_id, $status) {
        try {
            // Check if sync was stopped by user
            if (get_option('woo_moysklad_sync_stopped_by_user', '0') === '1') {
                $this->logger->info("Синхронизация статуса заказа #$order_id прервана пользователем");
                return false;
            }
            
            // Get status mapping
            $status_mapping = get_option('woo_moysklad_order_status_mapping', array());
            
            if (!is_array($status_mapping) || !isset($status_mapping[$status]) || empty($status_mapping[$status])) {
                $this->logger->debug("No status mapping for WooCommerce status: $status");
                return false;
            }
            
            $ms_status_id = $status_mapping[$status];
            
            // Get current order from MoySklad
            $ms_order = $this->api->get_order($ms_order_id);
            
            if (is_wp_error($ms_order)) {
                throw new Exception('Failed to get order from MoySklad: ' . $ms_order->get_error_message());
            }
            
            // Prepare update data (include only status change)
            $update_data = array(
                'state' => array(
                    'meta' => array(
                        'href' => $this->api->api_base . "/entity/customerorder/metadata/states/$ms_status_id",
                        'type' => 'state',
                        'mediaType' => 'application/json'
                    )
                )
            );
            
            // Update order
            $response = $this->api->update_order($ms_order_id, $update_data);
            
            if (is_wp_error($response)) {
                throw new Exception('Failed to update order status in MoySklad: ' . $response->get_error_message());
            }
            
            $this->logger->info("Updated order #$order_id status in MoySklad", array(
                'ms_order_id' => $ms_order_id,
                'status' => $status,
                'ms_status_id' => $ms_status_id
            ));
            
            // Add note to the order
            $order = wc_get_order($order_id);
            if ($order) {
                $order->add_order_note(sprintf(
                    __('Order status synchronized with MoySklad: %s', 'woo-moysklad-integration'),
                    $status
                ));
            }
            
            return true;
        } catch (Exception $e) {
            $this->logger->error("Order status update error for #$order_id: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Prepare order data for MoySklad.
     *
     * @since    1.0.0
     * @param    WC_Order    $order    The WooCommerce order.
     * @return   array                 The prepared order data.
     */
    private function prepare_order_data($order) {
        // Check if sync was stopped by user
        if (get_option('woo_moysklad_sync_stopped_by_user', '0') === '1') {
            $this->logger->info("Синхронизация данных заказа #{$order->get_id()} прервана пользователем");
            throw new Exception('Синхронизация остановлена пользователем');
        }
        
        $order_prefix = get_option('woo_moysklad_order_prefix', 'WC-');
        $organization_id = get_option('woo_moysklad_order_organization_id', '');
        $warehouse_id = get_option('woo_moysklad_order_warehouse_id', '');
        
        // Prepare customer data
        $customer_data = $this->prepare_customer_data($order);
        $customer = $this->api->find_or_create_customer($customer_data);
        
        if (is_wp_error($customer)) {
            $this->logger->error('Failed to find/create customer: ' . $customer->get_error_message());
            throw new Exception('Failed to find/create customer: ' . $customer->get_error_message());
        }
        
        // Prepare order items
        $positions = array();
        $items = $order->get_items();
        
        foreach ($items as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $target_id = $variation_id ? $variation_id : $product_id;
            
            // Get MoySklad product ID
            global $wpdb;
            $table_name = $wpdb->prefix . 'woo_moysklad_product_mapping';
            
            $ms_product_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ms_product_id FROM $table_name WHERE woo_product_id = %d",
                $target_id
            ));
            
            if (!$ms_product_id) {
                // Try parent product if variation not found
                if ($variation_id) {
                    $ms_product_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT ms_product_id FROM $table_name WHERE woo_product_id = %d",
                        $product_id
                    ));
                }
                
                if (!$ms_product_id) {
                    $this->logger->warning("Product not found in MoySklad: $target_id");
                    continue;
                }
            }
            
            // For variations, we need to get the variant ID
            if ($variation_id) {
                $ms_variant_id = get_post_meta($variation_id, '_ms_variant_id', true);
                
                if ($ms_variant_id) {
                    $assortment_url = "/entity/variant/$ms_variant_id";
                } else {
                    $assortment_url = "/entity/product/$ms_product_id";
                }
            } else {
                $assortment_url = "/entity/product/$ms_product_id";
            }
            
            $positions[] = array(
                'quantity' => $item->get_quantity(),
                'price' => wc_get_price_excluding_tax($item->get_product()) * 100, // MoySklad uses kopecks
                'discount' => 0,
                'vat' => 0,
                'assortment' => array(
                    'meta' => array(
                        'href' => $this->api->api_base . $assortment_url,
                        'type' => $variation_id && $ms_variant_id ? 'variant' : 'product',
                        'mediaType' => 'application/json'
                    )
                )
            );
        }
        
        // Prepare order data
        $order_data = array(
            'name' => $order_prefix . $order->get_order_number(),
            'externalCode' => $order->get_id(),
            'moment' => gmdate('Y-m-d H:i:s', strtotime($order->get_date_created())),
            'description' => sprintf(
                __('WooCommerce Order #%s from %s', 'woo-moysklad-integration'),
                $order->get_order_number(),
                $order->get_date_created()->date_i18n(get_option('date_format') . ' ' . get_option('time_format'))
            ),
            'agent' => array(
                'meta' => array(
                    'href' => $customer['meta']['href'],
                    'type' => 'counterparty',
                    'mediaType' => 'application/json'
                )
            ),
            'positions' => $positions
        );
        
        // Add organization if set
        if (!empty($organization_id)) {
            $order_data['organization'] = array(
                'meta' => array(
                    'href' => $this->api->api_base . "/entity/organization/$organization_id",
                    'type' => 'organization',
                    'mediaType' => 'application/json'
                )
            );
        }
        
        // Add warehouse if set
        if (!empty($warehouse_id)) {
            $order_data['store'] = array(
                'meta' => array(
                    'href' => $this->api->api_base . "/entity/store/$warehouse_id",
                    'type' => 'store',
                    'mediaType' => 'application/json'
                )
            );
        }
        
        // Add status if mapping exists
        $status_mapping = get_option('woo_moysklad_order_status_mapping', array());
        $wc_status = $order->get_status();
        
        if (is_array($status_mapping) && isset($status_mapping[$wc_status]) && !empty($status_mapping[$wc_status])) {
            $ms_status_id = $status_mapping[$wc_status];
            
            $order_data['state'] = array(
                'meta' => array(
                    'href' => $this->api->api_base . "/entity/customerorder/metadata/states/$ms_status_id",
                    'type' => 'state',
                    'mediaType' => 'application/json'
                )
            );
        }
        
        return $order_data;
    }
    
    /**
     * Prepare customer data for MoySklad.
     *
     * @since    1.0.0
     * @param    WC_Order    $order    The WooCommerce order.
     * @return   array                 The prepared customer data.
     */
    private function prepare_customer_data($order) {
        // Check if sync was stopped by user
        if (get_option('woo_moysklad_sync_stopped_by_user', '0') === '1') {
            $this->logger->info("Синхронизация данных клиента для заказа #{$order->get_id()} прервана пользователем");
            throw new Exception('Синхронизация остановлена пользователем');
        }
        
        $customer_group_id = get_option('woo_moysklad_order_customer_group_id', '');
        
        // Получаем контактные данные
        $email = $order->get_billing_email();
        $phone = $order->get_billing_phone();
        
        // Если нет email и телефона, используем дефолтный email
        if (empty($email) && empty($phone)) {
            $email = 'customer_' . $order->get_id() . '@example.com';
        }
        
        $customer_data = array(
            'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'externalCode' => $order->get_customer_id() ? $order->get_customer_id() : 'guest_' . $order->get_id(),
            'email' => $email,
            'phone' => $phone,
            'description' => sprintf(
                __('WooCommerce customer created from order #%s', 'woo-moysklad-integration'),
                $order->get_order_number()
            ),
            'actualAddress' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() . ', ' . 
                               $order->get_billing_city() . ', ' . $order->get_billing_state() . ', ' . 
                               $order->get_billing_postcode() . ', ' . $order->get_billing_country()
        );
        
        // Add customer group if set
        if (!empty($customer_group_id)) {
            $customer_data['group'] = array(
                'meta' => array(
                    'href' => $this->api->api_base . "/entity/counterparty/group/$customer_group_id",
                    'type' => 'group',
                    'mediaType' => 'application/json'
                )
            );
        }
        
        return $customer_data;
    }
    
    /**
     * Handle incoming orders from MoySklad.
     *
     * @since    1.0.0
     * @param    array     $ms_order    The MoySklad order data.
     * @return   array                  Result of operation.
     */
    public function handle_incoming_order($ms_order) {
        // This would handle webhooks for new orders from MoySklad
        // Implement if needed - for now, we're only syncing from WooCommerce to MoySklad
        return array(
            'success' => false,
            'message' => __('Importing orders from MoySklad is not implemented', 'woo-moysklad-integration')
        );
    }
    
    /**
     * Handle incoming status changes from MoySklad.
     *
     * @since    1.0.0
     * @param    array     $data    The webhook data.
     * @return   array              Result of operation.
     */
    public function handle_incoming_status_change($data) {
        try {
            $order_status_sync_enabled = get_option('woo_moysklad_order_status_sync_from_ms', '1');
            
            if ($order_status_sync_enabled !== '1') {
                return array(
                    'success' => false,
                    'message' => __('Status sync from MoySklad is disabled', 'woo-moysklad-integration')
                );
            }
            
            if (!isset($data['events']) || !is_array($data['events'])) {
                throw new Exception('Invalid webhook data format');
            }
            
            $updated = 0;
            
            foreach ($data['events'] as $event) {
                if ($event['meta']['type'] !== 'customerorder' || $event['action'] !== 'UPDATE') {
                    continue;
                }
                
                $ms_order_id = $event['entityId'];
                $ms_order = $this->api->get_order($ms_order_id);
                
                if (is_wp_error($ms_order)) {
                    throw new Exception('Failed to get order: ' . $ms_order->get_error_message());
                }
                
                // Find corresponding WC order
                global $wpdb;
                $wc_order_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_ms_order_id' AND meta_value = %s LIMIT 1",
                    $ms_order_id
                ));
                
                if (!$wc_order_id) {
                    // Try to find by external code
                    if (isset($ms_order['externalCode'])) {
                        $wc_order_id = $ms_order['externalCode'];
                    } else {
                        continue;
                    }
                }
                
                $order = wc_get_order($wc_order_id);
                
                if (!$order) {
                    continue;
                }
                
                // If state has changed, update WC order status
                if (isset($ms_order['state']) && isset($ms_order['state']['meta']) && isset($ms_order['state']['meta']['href'])) {
                    // Extract state ID from href
                    $state_id = '';
                    if (preg_match('/states\/([^\/]+)/', $ms_order['state']['meta']['href'], $matches)) {
                        $state_id = $matches[1];
                    }
                    
                    if (!$state_id) {
                        continue;
                    }
                    
                    // Get reverse status mapping
                    $status_mapping = get_option('woo_moysklad_order_status_mapping', array());
                    $reverse_mapping = array();
                    
                    foreach ($status_mapping as $wc_status => $ms_status) {
                        $reverse_mapping[$ms_status] = $wc_status;
                    }
                    
                    if (!isset($reverse_mapping[$state_id])) {
                        continue;
                    }
                    
                    $wc_status = $reverse_mapping[$state_id];
                    
                    // Update order status if different
                    if ('wc-' . $order->get_status() !== $wc_status) {
                        $order->update_status(
                            str_replace('wc-', '', $wc_status),
                            __('Status updated from MoySklad', 'woo-moysklad-integration'),
                            true
                        );
                        $updated++;
                        
                        $this->logger->info("Updated order #$wc_order_id status from MoySklad", array(
                            'ms_order_id' => $ms_order_id,
                            'ms_status_id' => $state_id,
                            'wc_status' => $wc_status
                        ));
                    }
                }
            }
            
            return array(
                'success' => true,
                'message' => sprintf(__('Updated %d order statuses', 'woo-moysklad-integration'), $updated),
                'updated' => $updated
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to process incoming status change: ' . $e->getMessage());
            
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
}
