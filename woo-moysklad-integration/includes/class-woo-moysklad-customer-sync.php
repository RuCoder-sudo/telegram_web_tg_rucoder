<?php
/**
 * Customer Synchronization
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */

/**
 * Customer Synchronization class.
 *
 * This class handles synchronization of customers between WooCommerce and MoySklad.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */
class Woo_Moysklad_Customer_Sync {

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
        
        // Register hooks for customer creation/update
        add_action('user_register', array($this, 'sync_new_customer'));
        add_action('profile_update', array($this, 'sync_updated_customer'));
    }
    
    /**
     * Sync a new customer to MoySklad.
     *
     * @since    1.0.0
     * @param    int    $user_id    The WordPress user ID.
     */
    public function sync_new_customer($user_id) {
        $customer_sync_enabled = get_option('woo_moysklad_customer_sync_enabled', '1');
        
        if ($customer_sync_enabled !== '1' || !$this->api->is_configured()) {
            return;
        }
        
        $this->sync_customer($user_id);
    }
    
    /**
     * Sync an updated customer to MoySklad.
     *
     * @since    1.0.0
     * @param    int    $user_id    The WordPress user ID.
     */
    public function sync_updated_customer($user_id) {
        $customer_sync_enabled = get_option('woo_moysklad_customer_sync_enabled', '1');
        
        if ($customer_sync_enabled !== '1' || !$this->api->is_configured()) {
            return;
        }
        
        $this->sync_customer($user_id);
    }
    
    /**
     * Sync a customer to MoySklad.
     *
     * @since    1.0.0
     * @param    int       $user_id    The WordPress user ID.
     * @return   array|bool            The result of the operation or false on failure.
     */
    public function sync_customer($user_id) {
        try {
            $user = get_userdata($user_id);
            
            if (!$user) {
                throw new Exception("User not found: $user_id");
            }
            
            // Check if this is a customer role
            if (!in_array('customer', $user->roles)) {
                return false;
            }
            
            $this->logger->info("Syncing customer: $user_id");
            
            // Get customer data
            $customer_data = $this->prepare_customer_data($user_id);
            
            // Check if customer already exists in MoySklad
            $ms_customer_id = get_user_meta($user_id, '_ms_customer_id', true);
            
            if ($ms_customer_id) {
                // Update existing customer
                $endpoint = "/entity/counterparty/$ms_customer_id";
                $response = $this->api->request($endpoint, 'PUT', $customer_data);
            } else {
                // Find or create customer
                $response = $this->api->find_or_create_customer($customer_data);
            }
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            // Store MoySklad customer ID
            if (isset($response['id'])) {
                update_user_meta($user_id, '_ms_customer_id', $response['id']);
                $this->logger->info("Customer synchronized: $user_id -> {$response['id']}");
                
                return array(
                    'success' => true,
                    'ms_customer_id' => $response['id'],
                );
            } else {
                throw new Exception('Invalid response from MoySklad API');
            }
        } catch (Exception $e) {
            $this->logger->error("Customer sync error for #$user_id: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Prepare customer data for MoySklad.
     *
     * @since    1.0.0
     * @param    int       $user_id    The WordPress user ID.
     * @return   array                 The prepared customer data.
     */
    private function prepare_customer_data($user_id) {
        $customer_group_id = get_option('woo_moysklad_customer_group_id', '');
        
        $user = get_userdata($user_id);
        
        // Get customer address data
        $billing_first_name = get_user_meta($user_id, 'billing_first_name', true);
        $billing_last_name = get_user_meta($user_id, 'billing_last_name', true);
        $billing_address_1 = get_user_meta($user_id, 'billing_address_1', true);
        $billing_address_2 = get_user_meta($user_id, 'billing_address_2', true);
        $billing_city = get_user_meta($user_id, 'billing_city', true);
        $billing_state = get_user_meta($user_id, 'billing_state', true);
        $billing_postcode = get_user_meta($user_id, 'billing_postcode', true);
        $billing_country = get_user_meta($user_id, 'billing_country', true);
        $billing_phone = get_user_meta($user_id, 'billing_phone', true);
        
        // Prepare name
        $name = trim($billing_first_name . ' ' . $billing_last_name);
        if (empty($name)) {
            $name = $user->display_name;
        }
        
        // Prepare address
        $address_parts = array_filter(array(
            $billing_address_1,
            $billing_address_2,
            $billing_city,
            $billing_state,
            $billing_postcode,
            $billing_country
        ));
        $address = implode(', ', $address_parts);
        
        $customer_data = array(
            'name' => $name,
            'externalCode' => 'wc_' . $user_id,
            'email' => $user->user_email,
            'phone' => $billing_phone,
            'description' => sprintf(
                __('WooCommerce customer ID: %s', 'woo-moysklad-integration'),
                $user_id
            )
        );
        
        if (!empty($address)) {
            $customer_data['actualAddress'] = $address;
        }
        
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
     * Sync price types for customer groups.
     *
     * @since    1.0.0
     * @return   array    Sync results.
     */
    public function sync_customer_price_types() {
        if (!$this->api->is_configured()) {
            $this->logger->error('Customer price type sync failed: API not configured');
            return array(
                'success' => false,
                'message' => __('API not configured', 'woo-moysklad-integration'),
            );
        }
        
        $price_type_sync_enabled = get_option('woo_moysklad_customer_price_type_sync', '0');
        
        if ($price_type_sync_enabled !== '1') {
            return array(
                'success' => false,
                'message' => __('Customer price type synchronization is disabled', 'woo-moysklad-integration'),
            );
        }
        
        $this->logger->info('Starting customer price type synchronization');
        
        try {
            // Get price types from MoySklad
            $response = $this->api->get_price_types();
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            if (!isset($response['rows']) || empty($response['rows'])) {
                return array(
                    'success' => true,
                    'message' => __('No price types found in MoySklad', 'woo-moysklad-integration'),
                );
            }
            
            $price_types = $response['rows'];
            
            // Get customer groups from MoySklad
            $groups_response = $this->api->get_customer_groups();
            
            if (is_wp_error($groups_response)) {
                throw new Exception($groups_response->get_error_message());
            }
            
            if (!isset($groups_response['rows']) || empty($groups_response['rows'])) {
                return array(
                    'success' => true,
                    'message' => __('No customer groups found in MoySklad', 'woo-moysklad-integration'),
                );
            }
            
            $customer_groups = $groups_response['rows'];
            
            // Store price types and customer groups for later use in settings
            update_option('woo_moysklad_price_types', $price_types);
            update_option('woo_moysklad_customer_groups', $customer_groups);
            
            $this->logger->info('Customer price type synchronization completed', array(
                'price_types' => count($price_types),
                'customer_groups' => count($customer_groups)
            ));
            
            return array(
                'success' => true,
                'message' => sprintf(
                    __('Synchronized %d price types and %d customer groups', 'woo-moysklad-integration'),
                    count($price_types),
                    count($customer_groups)
                ),
                'price_types' => $price_types,
                'customer_groups' => $customer_groups,
            );
        } catch (Exception $e) {
            $this->logger->error('Customer price type sync error: ' . $e->getMessage());
            
            return array(
                'success' => false,
                'message' => $e->getMessage(),
            );
        }
    }
    
    /**
     * Get customer-specific price for a product.
     *
     * @since    1.0.0
     * @param    float    $price        The original price.
     * @param    object   $product      The product object.
     * @param    int      $customer_id  The customer ID.
     * @return   float                  The adjusted price.
     */
    public function get_customer_price($price, $product, $customer_id) {
        $price_type_sync_enabled = get_option('woo_moysklad_customer_price_type_sync', '0');
        
        if ($price_type_sync_enabled !== '1' || !$this->api->is_configured()) {
            return $price;
        }
        
        // Get customer's group
        $ms_customer_id = get_user_meta($customer_id, '_ms_customer_id', true);
        
        if (!$ms_customer_id) {
            return $price;
        }
        
        // Get product's MoySklad ID
        $product_id = $product->get_id();
        global $wpdb;
        $table_name = $wpdb->prefix . 'woo_moysklad_product_mapping';
        
        $ms_product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ms_product_id FROM $table_name WHERE woo_product_id = %d",
            $product_id
        ));
        
        if (!$ms_product_id) {
            return $price;
        }
        
        // Get customer's price type
        $customer_price_type_id = get_user_meta($customer_id, '_ms_price_type_id', true);
        
        if (!$customer_price_type_id) {
            return $price;
        }
        
        // Get custom price from MoySklad product data
        $product_meta = $wpdb->get_var($wpdb->prepare(
            "SELECT ms_product_meta FROM $table_name WHERE woo_product_id = %d",
            $product_id
        ));
        
        if (!$product_meta) {
            return $price;
        }
        
        $product_data = json_decode($product_meta, true);
        
        if (!$product_data || !isset($product_data['salePrices']) || !is_array($product_data['salePrices'])) {
            return $price;
        }
        
        // Find matching price type
        foreach ($product_data['salePrices'] as $sale_price) {
            if (isset($sale_price['priceType']) && isset($sale_price['priceType']['id']) && 
                $sale_price['priceType']['id'] === $customer_price_type_id && isset($sale_price['value'])) {
                return $sale_price['value'] / 100; // MoySklad prices are in kopecks
            }
        }
        
        return $price;
    }
}
