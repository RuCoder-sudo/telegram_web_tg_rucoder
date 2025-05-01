<?php
/**
 * Webhook Handler
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */

/**
 * Webhook Handler class.
 *
 * This class handles incoming webhooks from MoySklad.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */
class Woo_Moysklad_Webhook_Handler {

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
     * Register webhook endpoints.
     *
     * @since    1.0.0
     */
    public function register_webhook_endpoints() {
        register_rest_route('woo-moysklad/v1', '/webhook', array(
            'methods'  => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => array($this, 'verify_webhook')
        ));
        
        register_rest_route('woo-moysklad/v1', '/register-webhooks', array(
            'methods'  => 'POST',
            'callback' => array($this, 'register_ms_webhooks'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Verify incoming webhook.
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    The request object.
     * @return   bool                           Whether the webhook is valid.
     */
    public function verify_webhook($request) {
        $webhook_enabled = get_option('woo_moysklad_webhook_enabled', '0');
        
        if ($webhook_enabled !== '1') {
            $this->logger->warning('Webhook received but webhooks are disabled');
            return false;
        }
        
        // Verify secret if set
        $webhook_secret = get_option('woo_moysklad_webhook_secret', '');
        
        if (!empty($webhook_secret)) {
            $headers = $request->get_headers();
            
            if (!isset($headers['x_webhook_secret']) || empty($headers['x_webhook_secret'][0])) {
                $this->logger->warning('Webhook missing secret header');
                return false;
            }
            
            if ($headers['x_webhook_secret'][0] !== $webhook_secret) {
                $this->logger->warning('Webhook secret mismatch');
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Handle incoming webhook.
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    The request object.
     * @return   WP_REST_Response                The response.
     */
    public function handle_webhook($request) {
        $this->logger->info('Received webhook from MoySklad');
        
        // Get request body
        $data = $request->get_json_params();
        
        if (empty($data)) {
            $this->logger->warning('Empty webhook payload');
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Empty payload'
            ), 400);
        }
        
        $this->logger->debug('Webhook payload', $data);
        
        // Determine webhook type and process accordingly
        if (isset($data['events']) && is_array($data['events'])) {
            $event_types = array();
            foreach ($data['events'] as $event) {
                if (isset($event['meta']) && isset($event['meta']['type'])) {
                    $event_types[$event['meta']['type']] = $event['action'];
                }
            }
            
            $this->logger->info('Webhook event types', $event_types);
            
            // Process stock updates
            if (isset($event_types['product']) || isset($event_types['variant'])) {
                $result = $this->process_stock_update($data);
                return new WP_REST_Response($result, 200);
            }
            
            // Process order status updates
            if (isset($event_types['customerorder'])) {
                $order_sync = new Woo_Moysklad_Order_Sync($this->api, $this->logger);
                $result = $order_sync->handle_incoming_status_change($data);
                return new WP_REST_Response($result, 200);
            }
        }
        
        $this->logger->warning('Unhandled webhook type', $data);
        
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Unhandled webhook type'
        ), 400);
    }
    
    /**
     * Process stock updates from webhook.
     *
     * @since    1.0.0
     * @param    array    $data    The webhook data.
     * @return   array             Result of operation.
     */
    private function process_stock_update($data) {
        try {
            if (!isset($data['events']) || !is_array($data['events'])) {
                throw new Exception('Invalid webhook data format');
            }
            
            $updated_product_ids = array();
            $updated_variant_ids = array();
            
            foreach ($data['events'] as $event) {
                if ($event['action'] !== 'UPDATE' && $event['action'] !== 'CREATE') {
                    continue;
                }
                
                if ($event['meta']['type'] === 'product') {
                    $updated_product_ids[] = $event['entityId'];
                } elseif ($event['meta']['type'] === 'variant') {
                    $updated_variant_ids[] = $event['entityId'];
                }
            }
            
            $stats = array(
                'products_updated' => 0,
                'variants_updated' => 0,
                'failed' => 0
            );
            
            // Process updated products
            if (!empty($updated_product_ids)) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'woo_moysklad_product_mapping';
                
                foreach ($updated_product_ids as $ms_product_id) {
                    // Get WooCommerce product ID
                    $wc_product_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT woo_product_id FROM $table_name WHERE ms_product_id = %s",
                        $ms_product_id
                    ));
                    
                    if (!$wc_product_id) {
                        continue;
                    }
                    
                    // Get product data
                    $ms_product = $this->api->get_product($ms_product_id);
                    
                    if (is_wp_error($ms_product)) {
                        $this->logger->error('Failed to get product: ' . $ms_product->get_error_message());
                        $stats['failed']++;
                        continue;
                    }
                    
                    // Update product
                    $product_sync = new Woo_Moysklad_Product_Sync($this->api, $this->logger);
                    $result = $product_sync->process_product($ms_product);
                    
                    if ($result === 'updated') {
                        $stats['products_updated']++;
                    } else {
                        $stats['failed']++;
                    }
                    
                    // Update stock
                    $warehouse_id = get_option('woo_moysklad_inventory_warehouse_id', '');
                    $stock_data = $this->api->get_product_stock($ms_product_id, $warehouse_id);
                    
                    if (!is_wp_error($stock_data) && isset($stock_data['rows']) && !empty($stock_data['rows'])) {
                        $inventory_sync = new Woo_Moysklad_Inventory_Sync($this->api, $this->logger);
                        
                        $product = wc_get_product($wc_product_id);
                        
                        if ($product && !$product->is_type('variable')) {
                            $stock_item = $stock_data['rows'][0];
                            $inventory_sync->update_product_stock($wc_product_id, array(
                                'stock' => isset($stock_item['stock']) ? (int)$stock_item['stock'] : 0,
                                'reserve' => isset($stock_item['reserve']) ? (int)$stock_item['reserve'] : 0
                            ));
                        }
                    }
                }
            }
            
            // Process updated variants
            if (!empty($updated_variant_ids)) {
                foreach ($updated_variant_ids as $ms_variant_id) {
                    // Get variant data
                    $endpoint = "/entity/variant/$ms_variant_id";
                    $ms_variant = $this->api->request($endpoint);
                    
                    if (is_wp_error($ms_variant)) {
                        $this->logger->error('Failed to get variant: ' . $ms_variant->get_error_message());
                        $stats['failed']++;
                        continue;
                    }
                    
                    // Get product ID from variant
                    if (!isset($ms_variant['product']) || !isset($ms_variant['product']['meta']) || !isset($ms_variant['product']['meta']['href'])) {
                        $this->logger->error('Invalid variant data: no product reference');
                        $stats['failed']++;
                        continue;
                    }
                    
                    // Extract product ID from href
                    $ms_product_id = '';
                    if (preg_match('/product\/([^\/]+)/', $ms_variant['product']['meta']['href'], $matches)) {
                        $ms_product_id = $matches[1];
                    }
                    
                    if (empty($ms_product_id)) {
                        $this->logger->error('Failed to extract product ID from variant');
                        $stats['failed']++;
                        continue;
                    }
                    
                    // Get WooCommerce product ID
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'woo_moysklad_product_mapping';
                    
                    $wc_product_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT woo_product_id FROM $table_name WHERE ms_product_id = %s",
                        $ms_product_id
                    ));
                    
                    if (!$wc_product_id) {
                        continue;
                    }
                    
                    // Get variant's WooCommerce variation ID
                    $variation_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_ms_variant_id' AND meta_value = %s",
                        $ms_variant_id
                    ));
                    
                    if (!$variation_id) {
                        // Variation not found, need to update the entire product
                        $ms_product = $this->api->get_product($ms_product_id);
                        
                        if (is_wp_error($ms_product)) {
                            $this->logger->error('Failed to get product: ' . $ms_product->get_error_message());
                            $stats['failed']++;
                            continue;
                        }
                        
                        $product_sync = new Woo_Moysklad_Product_Sync($this->api, $this->logger);
                        $result = $product_sync->process_product($ms_product);
                        
                        if ($result === 'updated') {
                            $stats['products_updated']++;
                        } else {
                            $stats['failed']++;
                        }
                    } else {
                        // Update variation
                        $variation = wc_get_product($variation_id);
                        
                        if (!$variation) {
                            $stats['failed']++;
                            continue;
                        }
                        
                        // Update price
                        if (isset($ms_variant['salePrices']) && isset($ms_variant['salePrices'][0]) && isset($ms_variant['salePrices'][0]['value'])) {
                            $price = $ms_variant['salePrices'][0]['value'] / 100;
                            $variation->set_regular_price($price);
                        }
                        
                        // Update SKU
                        if (isset($ms_variant['code'])) {
                            $variation->set_sku($ms_variant['code']);
                        }
                        
                        $variation->save();
                        $stats['variants_updated']++;
                        
                        // Update stock
                        $warehouse_id = get_option('woo_moysklad_inventory_warehouse_id', '');
                        $endpoint = "/report/stock/all?filter=variant.id=$ms_variant_id";
                        
                        if (!empty($warehouse_id)) {
                            $endpoint .= ";store.id=$warehouse_id";
                        }
                        
                        $stock_data = $this->api->request($endpoint);
                        
                        if (!is_wp_error($stock_data) && isset($stock_data['rows']) && !empty($stock_data['rows'])) {
                            $stock_item = $stock_data['rows'][0];
                            
                            $available_stock = isset($stock_item['stock']) ? (int)$stock_item['stock'] : 0;
                            $reserve = isset($stock_item['reserve']) ? (int)$stock_item['reserve'] : 0;
                            $available_stock = max(0, $available_stock - $reserve);
                            
                            $variation->set_stock_quantity($available_stock);
                            
                            if ($available_stock > 0) {
                                $variation->set_stock_status('instock');
                            } else {
                                $variation->set_stock_status('outofstock');
                            }
                            
                            $variation->save();
                        }
                    }
                }
            }
            
            $this->logger->info('Processed webhook stock updates', $stats);
            
            return array(
                'success' => true,
                'message' => sprintf(
                    __('Updated %d products, %d variants, %d failed', 'woo-moysklad-integration'),
                    $stats['products_updated'],
                    $stats['variants_updated'],
                    $stats['failed']
                ),
                'stats' => $stats
            );
        } catch (Exception $e) {
            $this->logger->error('Stock update error: ' . $e->getMessage());
            
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Register webhooks in MoySklad.
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    The request object.
     * @return   WP_REST_Response                The response.
     */
    public function register_ms_webhooks($request) {
        if (!current_user_can('manage_options')) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Permission denied', 'woo-moysklad-integration')
            ), 403);
        }
        
        if (!$this->api->is_configured()) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('API not configured', 'woo-moysklad-integration')
            ), 400);
        }
        
        $webhook_enabled = get_option('woo_moysklad_webhook_enabled', '0');
        
        if ($webhook_enabled !== '1') {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Webhooks are disabled in settings', 'woo-moysklad-integration')
            ), 400);
        }
        
        try {
            $home_url = trailingslashit(home_url());
            $webhook_url = $home_url . 'wp-json/woo-moysklad/v1/webhook';
            $webhook_secret = get_option('woo_moysklad_webhook_secret', '');
            
            // Get current webhooks
            $existing_webhooks = $this->api->get_webhooks();
            
            if (is_wp_error($existing_webhooks)) {
                throw new Exception($existing_webhooks->get_error_message());
            }
            
            // Check if our webhook already exists
            $webhook_exists = false;
            
            if (isset($existing_webhooks['rows'])) {
                foreach ($existing_webhooks['rows'] as $webhook) {
                    if (isset($webhook['url']) && $webhook['url'] === $webhook_url) {
                        $webhook_exists = true;
                        break;
                    }
                }
            }
            
            $results = array();
            
            // Register webhooks if they don't exist
            if (!$webhook_exists) {
                // Register product webhook for stock updates
                $product_webhook = $this->api->register_webhook('product', 'UPDATE', $webhook_url);
                
                if (!is_wp_error($product_webhook)) {
                    $results[] = array(
                        'type' => 'product',
                        'action' => 'UPDATE',
                        'success' => true
                    );
                } else {
                    $results[] = array(
                        'type' => 'product',
                        'action' => 'UPDATE',
                        'success' => false,
                        'message' => $product_webhook->get_error_message()
                    );
                }
                
                // Register variant webhook for stock updates
                $variant_webhook = $this->api->register_webhook('variant', 'UPDATE', $webhook_url);
                
                if (!is_wp_error($variant_webhook)) {
                    $results[] = array(
                        'type' => 'variant',
                        'action' => 'UPDATE',
                        'success' => true
                    );
                } else {
                    $results[] = array(
                        'type' => 'variant',
                        'action' => 'UPDATE',
                        'success' => false,
                        'message' => $variant_webhook->get_error_message()
                    );
                }
                
                // Register order webhook for status updates
                $order_webhook = $this->api->register_webhook('customerorder', 'UPDATE', $webhook_url);
                
                if (!is_wp_error($order_webhook)) {
                    $results[] = array(
                        'type' => 'customerorder',
                        'action' => 'UPDATE',
                        'success' => true
                    );
                } else {
                    $results[] = array(
                        'type' => 'customerorder',
                        'action' => 'UPDATE',
                        'success' => false,
                        'message' => $order_webhook->get_error_message()
                    );
                }
            } else {
                $results[] = array(
                    'message' => __('Webhooks already registered', 'woo-moysklad-integration'),
                    'success' => true
                );
            }
            
            $this->logger->info('Registered MoySklad webhooks', $results);
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => __('Webhooks registered', 'woo-moysklad-integration'),
                'results' => $results,
                'webhook_url' => $webhook_url
            ), 200);
        } catch (Exception $e) {
            $this->logger->error('Failed to register webhooks: ' . $e->getMessage());
            
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $e->getMessage()
            ), 500);
        }
    }
}
