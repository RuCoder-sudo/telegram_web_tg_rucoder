<?php
/**
 * Admin functionality.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/admin
 */

/**
 * Admin functionality class.
 *
 * This class handles admin functionality for the plugin.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/admin
 */
class Woo_Moysklad_Integration_Admin {

    /**
     * Plugin instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Woo_Moysklad_Integration    $plugin    Plugin instance.
     */
    private $plugin;
    
    /**
     * Constructor.
     *
     * @since    1.0.0
     * @param    Woo_Moysklad_Integration    $plugin    Plugin instance.
     */
    public function __construct($plugin) {
        $this->plugin = $plugin;
        
        // Add AJAX handlers
        add_action('wp_ajax_woo_moysklad_sync_products', array($this, 'ajax_sync_products'));
        add_action('wp_ajax_woo_moysklad_sync_inventory', array($this, 'ajax_sync_inventory'));
        add_action('wp_ajax_woo_moysklad_sync_categories', array($this, 'ajax_sync_categories'));
        add_action('wp_ajax_woo_moysklad_sync_orders', array($this, 'ajax_sync_orders'));
        add_action('wp_ajax_woo_moysklad_sync_order_batch', array($this, 'ajax_sync_order_batch'));
        add_action('wp_ajax_woo_moysklad_get_sync_status', array($this, 'ajax_get_sync_status'));
        add_action('wp_ajax_woo_moysklad_register_webhooks', array($this, 'ajax_register_webhooks'));
        add_action('wp_ajax_woo_moysklad_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_woo_moysklad_get_logs', array($this, 'ajax_get_logs'));
        add_action('wp_ajax_woo_moysklad_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_register_bonus_attributes', array($this, 'ajax_register_bonus_attributes'));
        add_action('wp_ajax_woo_moysklad_stop_sync', array($this, 'ajax_stop_sync'));
    }
    
    /**
     * Register the admin menu pages.
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu() {
        // Main menu
        add_menu_page(
            __('WooCommerce MoySklad Integration', 'woo-moysklad-integration'),
            __('MoySklad', 'woo-moysklad-integration'),
            'manage_options',
            'woo-moysklad',
            array($this, 'display_admin_page'),
            'dashicons-cart',
            58
        );
        
        // Products submenu
        add_submenu_page(
            'woo-moysklad',
            __('Products', 'woo-moysklad-integration'),
            __('Products', 'woo-moysklad-integration'),
            'manage_options',
            'woo-moysklad',
            array($this, 'display_admin_page')
        );
        
        // Orders submenu
        add_submenu_page(
            'woo-moysklad',
            __('Orders', 'woo-moysklad-integration'),
            __('Orders', 'woo-moysklad-integration'),
            'manage_options',
            'woo-moysklad-orders',
            array($this, 'display_orders_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'woo-moysklad',
            __('Settings', 'woo-moysklad-integration'),
            __('Settings', 'woo-moysklad-integration'),
            'manage_options',
            'woo-moysklad-settings',
            array($this, 'display_settings_page')
        );
        
        // Logs submenu
        add_submenu_page(
            'woo-moysklad',
            __('Logs', 'woo-moysklad-integration'),
            __('Logs', 'woo-moysklad-integration'),
            'manage_options',
            'woo-moysklad-logs',
            array($this, 'display_logs_page')
        );
    }
    
    /**
     * Register plugin settings.
     *
     * @since    1.0.0
     */
    public function register_settings() {
        // API Settings
        register_setting('woo_moysklad_api_settings', 'woo_moysklad_api_token');
        register_setting('woo_moysklad_api_settings', 'woo_moysklad_api_login');
        register_setting('woo_moysklad_api_settings', 'woo_moysklad_api_password');
        
        // Product Sync Settings
        register_setting('woo_moysklad_product_settings', 'woo_moysklad_sync_enabled');
        register_setting('woo_moysklad_product_settings', 'woo_moysklad_product_sync_interval');
        register_setting('woo_moysklad_product_settings', 'woo_moysklad_sync_product_name');
        register_setting('woo_moysklad_product_settings', 'woo_moysklad_sync_product_description');
        register_setting('woo_moysklad_product_settings', 'woo_moysklad_sync_product_images');
        register_setting('woo_moysklad_product_settings', 'woo_moysklad_sync_all_product_images');
        register_setting('woo_moysklad_product_settings', 'woo_moysklad_sync_product_groups');
        register_setting('woo_moysklad_product_settings', 'woo_moysklad_sync_product_modifications');
        register_setting('woo_moysklad_product_settings', 'woo_moysklad_sync_product_bundles');
        register_setting('woo_moysklad_product_settings', 'woo_moysklad_sync_product_custom_fields');
        register_setting('woo_moysklad_product_settings', 'woo_moysklad_sync_mode');
        register_setting('woo_moysklad_product_settings', 'woo_moysklad_price_type');
        
        // Inventory Sync Settings
        register_setting('woo_moysklad_inventory_settings', 'woo_moysklad_inventory_sync_interval');
        register_setting('woo_moysklad_inventory_settings', 'woo_moysklad_inventory_warehouse_id');
        
        // Order Sync Settings
        register_setting('woo_moysklad_order_settings', 'woo_moysklad_order_sync_enabled');
        register_setting('woo_moysklad_order_settings', 'woo_moysklad_order_sync_delay');
        register_setting('woo_moysklad_order_settings', 'woo_moysklad_order_sync_delay_minutes');
        register_setting('woo_moysklad_order_settings', 'woo_moysklad_order_organization_id');
        register_setting('woo_moysklad_order_settings', 'woo_moysklad_order_warehouse_id');
        register_setting('woo_moysklad_order_settings', 'woo_moysklad_order_customer_group_id');
        register_setting('woo_moysklad_order_settings', 'woo_moysklad_order_prefix');
        register_setting('woo_moysklad_order_settings', 'woo_moysklad_order_status_sync_enabled');
        register_setting('woo_moysklad_order_settings', 'woo_moysklad_order_status_sync_from_ms');
        register_setting('woo_moysklad_order_settings', 'woo_moysklad_order_status_mapping');
        
        // Customer Settings
        register_setting('woo_moysklad_customer_settings', 'woo_moysklad_customer_sync_enabled');
        register_setting('woo_moysklad_customer_settings', 'woo_moysklad_customer_group_id');
        register_setting('woo_moysklad_customer_settings', 'woo_moysklad_customer_price_type_sync');
        
        // Webhook Settings
        register_setting('woo_moysklad_webhook_settings', 'woo_moysklad_webhook_enabled');
        register_setting('woo_moysklad_webhook_settings', 'woo_moysklad_webhook_secret');
        
        // Log Settings
        register_setting('woo_moysklad_log_settings', 'woo_moysklad_log_level');
        register_setting('woo_moysklad_log_settings', 'woo_moysklad_log_to_file');
        register_setting('woo_moysklad_log_settings', 'woo_moysklad_log_to_db');
        
        // Bonus Integration Settings
        register_setting('woo_moysklad_bonus_settings', 'woo_moysklad_bonus_integration_enabled');
        register_setting('woo_moysklad_bonus_settings', 'woo_moysklad_bonus_used_attribute_id');
        register_setting('woo_moysklad_bonus_settings', 'woo_moysklad_bonus_earned_attribute_id');
        register_setting('woo_moysklad_bonus_settings', 'woo_moysklad_bonus_balance_attribute_id');
    }
    
    /**
     * Enqueue admin styles.
     *
     * @since    1.0.0
     * @param    string    $hook_suffix    The current admin page.
     */
    public function enqueue_styles($hook_suffix) {
        if (strpos($hook_suffix, 'woo-moysklad') === false) {
            return;
        }
        
        wp_enqueue_style(
            'woo-moysklad-admin',
            plugin_dir_url(__FILE__) . 'css/woo-moysklad-admin.css',
            array(),
            WOO_MOYSKLAD_VERSION,
            'all'
        );
    }
    
    /**
     * Enqueue admin scripts.
     *
     * @since    1.0.0
     * @param    string    $hook_suffix    The current admin page.
     */
    public function enqueue_scripts($hook_suffix) {
        if (strpos($hook_suffix, 'woo-moysklad') === false) {
            return;
        }
        
        wp_enqueue_script(
            'woo-moysklad-admin',
            plugin_dir_url(__FILE__) . 'js/woo-moysklad-admin.js',
            array('jquery'),
            WOO_MOYSKLAD_VERSION,
            false
        );
        
        // Localize script
        wp_localize_script('woo-moysklad-admin', 'wooMoySkladAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('woo_moysklad_admin_nonce'),
            'syncInProgress' => get_option('woo_moysklad_sync_in_progress', '0'),
            'messages' => array(
                'confirmClearLogs' => __('Вы уверены, что хотите очистить все логи?', 'woo-moysklad-integration'),
                'confirmSync' => __('Это запустит процесс синхронизации. Продолжить?', 'woo-moysklad-integration'),
                'syncInProgress' => __('Синхронизация в процессе...', 'woo-moysklad-integration'),
                'syncComplete' => __('Синхронизация успешно завершена!', 'woo-moysklad-integration'),
                'syncFailed' => __('Ошибка синхронизации. Проверьте логи для получения подробностей.', 'woo-moysklad-integration'),
                'syncStopped' => __('Синхронизация остановлена пользователем.', 'woo-moysklad-integration'),
                'connectionSuccess' => __('Соединение успешно!', 'woo-moysklad-integration'),
                'connectionFailed' => __('Ошибка соединения. Проверьте учетные данные.', 'woo-moysklad-integration'),
                'webhooksRegistered' => __('Вебхуки успешно зарегистрированы!', 'woo-moysklad-integration'),
                'webhooksFailed' => __('Не удалось зарегистрировать вебхуки. Проверьте логи для подробностей.', 'woo-moysklad-integration'),
            )
        ));
    }
    
    /**
     * Display the main admin page.
     *
     * @since    1.0.0
     */
    public function display_admin_page() {
        include_once plugin_dir_path(__FILE__) . 'partials/woo-moysklad-admin-products.php';
    }
    
    /**
     * Display the orders page.
     *
     * @since    1.0.0
     */
    public function display_orders_page() {
        include_once plugin_dir_path(__FILE__) . 'partials/woo-moysklad-admin-orders.php';
    }
    
    /**
     * Display the settings page.
     *
     * @since    1.0.0
     */
    public function display_settings_page() {
        include_once plugin_dir_path(__FILE__) . 'partials/woo-moysklad-admin-settings.php';
    }
    
    /**
     * Display the logs page.
     *
     * @since    1.0.0
     */
    public function display_logs_page() {
        include_once plugin_dir_path(__FILE__) . 'partials/woo-moysklad-admin-logs.php';
    }
    
    /**
     * Add plugin action links.
     *
     * @since    1.0.0
     * @param    array    $links    Plugin action links.
     * @return   array              Modified plugin action links.
     */
    public function plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=woo-moysklad-settings') . '">' . __('Settings', 'woo-moysklad-integration') . '</a>';
        array_unshift($links, $settings_link);
        
        return $links;
    }
    
    /**
     * AJAX handler for product synchronization.
     *
     * @since    1.0.0
     */
    public function ajax_sync_products() {
        // Check nonce
        check_ajax_referer('woo_moysklad_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Permission denied', 'woo-moysklad-integration')
            ));
        }
        
        // Get product sync instance
        $product_sync = $this->plugin->get_product_sync();
        
        // Run synchronization
        $result = $product_sync->sync_products();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX handler for inventory synchronization.
     *
     * @since    1.0.0
     */
    public function ajax_sync_inventory() {
        // Check nonce
        check_ajax_referer('woo_moysklad_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Permission denied', 'woo-moysklad-integration')
            ));
        }
        
        // Get inventory sync instance
        $inventory_sync = $this->plugin->get_inventory_sync();
        
        // Run synchronization
        $result = $inventory_sync->sync_inventory();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX handler for category synchronization.
     *
     * @since    1.0.0
     */
    public function ajax_sync_categories() {
        // Check nonce
        check_ajax_referer('woo_moysklad_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Permission denied', 'woo-moysklad-integration')
            ));
        }
        
        // Get category sync instance
        $category_sync = $this->plugin->get_category_sync();
        
        // Run synchronization
        $result = $category_sync->sync_categories();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX handler for getting synchronization status.
     *
     * @since    1.0.0
     */
    public function ajax_get_sync_status() {
        // Check nonce
        check_ajax_referer('woo_moysklad_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Permission denied', 'woo-moysklad-integration')
            ));
        }
        
        $sync_in_progress = get_option('woo_moysklad_sync_in_progress', '0');
        $last_sync_time = get_option('woo_moysklad_last_sync_time', '');
        $last_inventory_sync_time = get_option('woo_moysklad_last_inventory_sync_time', '');
        $sync_enabled = get_option('woo_moysklad_sync_enabled', '0');
        
        wp_send_json_success(array(
            'inProgress' => $sync_in_progress === '1',
            'lastSyncTime' => !empty($last_sync_time) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync_time)) : __('Never', 'woo-moysklad-integration'),
            'lastInventorySyncTime' => !empty($last_inventory_sync_time) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_inventory_sync_time)) : __('Never', 'woo-moysklad-integration'),
            'syncEnabled' => $sync_enabled === '1',
        ));
    }
    
    /**
     * AJAX handler for registering webhooks.
     *
     * @since    1.0.0
     */
    public function ajax_register_webhooks() {
        // Check nonce
        check_ajax_referer('woo_moysklad_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Permission denied', 'woo-moysklad-integration')
            ));
        }
        
        // Get webhook handler
        $webhook_handler = $this->plugin->get_webhook_handler();
        
        // Create a mock request
        $request = new WP_REST_Request('POST', '/woo-moysklad/v1/register-webhooks');
        
        // Process request
        $response = $webhook_handler->register_ms_webhooks($request);
        
        if ($response->get_status() === 200) {
            wp_send_json_success($response->get_data());
        } else {
            wp_send_json_error($response->get_data());
        }
    }
    
    /**
     * AJAX handler for testing API connection.
     *
     * @since    1.0.0
     */
    public function ajax_test_connection() {
        // Check nonce
        check_ajax_referer('woo_moysklad_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Permission denied', 'woo-moysklad-integration')
            ));
        }
        
        // Get API instance
        $api = $this->plugin->get_api();
        
        // Test connection by requesting organizations
        $response = $api->get_organizations();
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => $response->get_error_message()
            ));
        } else {
            wp_send_json_success(array(
                'message' => __('Connection successful!', 'woo-moysklad-integration'),
                'data' => $response
            ));
        }
    }
    
    /**
     * AJAX handler for getting logs.
     *
     * @since    1.0.0
     */
    public function ajax_get_logs() {
        // Check nonce
        check_ajax_referer('woo_moysklad_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Permission denied', 'woo-moysklad-integration')
            ));
        }
        
        // Get parameters
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 100;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $level = isset($_POST['level']) ? sanitize_text_field($_POST['level']) : '';
        
        // Get logger
        $logger = $this->plugin->get_logger();
        
        // Get logs
        $logs = $logger->get_logs($limit, $offset, $level);
        $total = $logger->get_log_count($level);
        
        wp_send_json_success(array(
            'logs' => $logs,
            'total' => $total
        ));
    }
    
    /**
     * AJAX handler for clearing logs.
     *
     * @since    1.0.0
     */
    public function ajax_clear_logs() {
        // Check nonce
        check_ajax_referer('woo_moysklad_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Permission denied', 'woo-moysklad-integration')
            ));
        }
        
        // Get logger
        $logger = $this->plugin->get_logger();
        
        // Clear logs
        $result = $logger->clear_logs();
        
        wp_send_json_success(array(
            'message' => __('Logs cleared successfully', 'woo-moysklad-integration'),
            'rows_deleted' => $result
        ));
    }
    
    /**
     * AJAX handler for registering bonus attributes in MoySklad.
     *
     * @since    1.0.0
     */
    public function ajax_register_bonus_attributes() {
        // Check nonce
        check_ajax_referer('woo_moysklad_register_bonus_attributes', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Доступ запрещен', 'woo-moysklad-integration')
            ));
        }
        
        // Get API instance
        $api = $this->plugin->get_api();
        
        // Get bonus integration instance
        $bonus_integration = $this->plugin->get_bonus_integration();
        
        if (!$bonus_integration) {
            wp_send_json_error(array(
                'message' => __('Модуль бонусной интеграции не инициализирован', 'woo-moysklad-integration')
            ));
            return;
        }
        
        // Register attributes
        $result = $bonus_integration->register_bonus_attributes_in_moysklad();
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        } else {
            // Update options with attribute IDs
            if (isset($result['used_bonus_id'])) {
                update_option('woo_moysklad_bonus_used_attribute_id', $result['used_bonus_id']);
            }
            
            if (isset($result['earned_bonus_id'])) {
                update_option('woo_moysklad_bonus_earned_attribute_id', $result['earned_bonus_id']);
            }
            
            if (isset($result['balance_bonus_id'])) {
                update_option('woo_moysklad_bonus_balance_attribute_id', $result['balance_bonus_id']);
            }
            
            wp_send_json_success(array(
                'message' => __('Атрибуты бонусов успешно созданы в МойСклад', 'woo-moysklad-integration'),
                'attributes' => $result
            ));
        }
    }
    
    /**
     * AJAX handler for stopping synchronization.
     *
     * @since    1.0.0
     */
    public function ajax_stop_sync() {
        // Check nonce
        check_ajax_referer('woo_moysklad_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Недостаточно прав', 'woo-moysklad-integration')
            ));
        }
        
        // Set sync flag to stopped
        update_option('woo_moysklad_sync_in_progress', '0');
        update_option('woo_moysklad_sync_stopped_by_user', '1');
        
        // Log the stop action
        $logger = $this->plugin->get_logger();
        $logger->info('Синхронизация остановлена пользователем');
        
        wp_send_json_success(array(
            'message' => __('Синхронизация остановлена', 'woo-moysklad-integration')
        ));
    }
    
    /**
     * AJAX handler for initiating order synchronization.
     *
     * @since    1.0.0
     */
    public function ajax_sync_orders() {
        // Check nonce
        check_ajax_referer('woo_moysklad_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Недостаточно прав', 'woo-moysklad-integration')
            ));
        }
        
        // Reset sync flags
        update_option('woo_moysklad_sync_in_progress', '1');
        update_option('woo_moysklad_sync_stopped_by_user', '0');
        
        // Get logger
        $logger = $this->plugin->get_logger();
        
        // Check if a specific order ID was provided
        if (isset($_POST['order_id']) && !empty($_POST['order_id'])) {
            $order_id = intval($_POST['order_id']);
            
            // Get order
            $order = wc_get_order($order_id);
            
            if (!$order) {
                wp_send_json_error(array(
                    'message' => sprintf(__('Заказ #%d не найден', 'woo-moysklad-integration'), $order_id)
                ));
            }
            
            $logger->info("Запуск синхронизации отдельного заказа #{$order_id}");
            
            try {
                // Get order sync service
                $order_sync = $this->plugin->get_order_sync();
                
                // Sync order
                $result = $order_sync->create_or_update_order($order_id);
                
                if ($result) {
                    $success_message = sprintf(__('Заказ #%d успешно синхронизирован с МойСклад', 'woo-moysklad-integration'), $order_id);
                    $logger->info($success_message);
                    
                    wp_send_json_success(array(
                        'message' => $success_message,
                        'order_id' => $order_id
                    ));
                } else {
                    $error_message = sprintf(__('Не удалось синхронизировать заказ #%d с МойСклад', 'woo-moysklad-integration'), $order_id);
                    $logger->error($error_message);
                    
                    wp_send_json_error(array(
                        'message' => $error_message,
                        'order_id' => $order_id
                    ));
                }
            } catch (Exception $e) {
                $logger->error("Ошибка при синхронизации заказа #{$order_id}: " . $e->getMessage());
                
                wp_send_json_error(array(
                    'message' => sprintf(__('Ошибка при синхронизации заказа #%d: %s', 'woo-moysklad-integration'), $order_id, $e->getMessage()),
                    'order_id' => $order_id
                ));
            }
        } else {
            // Mass order sync - get all unsynchronized orders
            $logger->info('Запуск массовой синхронизации заказов');
            
            global $wpdb;
            
            // Get orders that don't have _ms_order_id meta
            $query = "
                SELECT p.ID 
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_ms_order_id'
                WHERE p.post_type = 'shop_order'
                AND p.post_status != 'trash'
                AND pm.meta_value IS NULL
                ORDER BY p.post_date DESC
            ";
            
            $orders = $wpdb->get_results($query);
            $total_orders = count($orders);
            
            // Log the count
            $logger->info("Найдено {$total_orders} заказов для синхронизации");
            
            wp_send_json_success(array(
                'message' => sprintf(__('Найдено %d заказов для синхронизации', 'woo-moysklad-integration'), $total_orders),
                'total' => $total_orders,
                'processed' => 0,
                'order_ids' => array_map(function($order) { return $order->ID; }, $orders)
            ));
        }
    }
    
    /**
     * AJAX handler for synchronizing a batch of orders.
     *
     * @since    1.0.0
     */
    public function ajax_sync_order_batch() {
        // Check nonce
        check_ajax_referer('woo_moysklad_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Недостаточно прав', 'woo-moysklad-integration')
            ));
        }
        
        // Get order sync instance
        $order_sync = $this->plugin->get_order_sync();
        
        // Get batch parameters
        $orders = isset($_POST['orders']) ? $_POST['orders'] : array();
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 10;
        $total = isset($_POST['total']) ? intval($_POST['total']) : count($orders);
        
        // Check if sync was stopped
        if (get_option('woo_moysklad_sync_stopped_by_user', '0') === '1') {
            update_option('woo_moysklad_sync_in_progress', '0');
            
            wp_send_json_success(array(
                'message' => __('Синхронизация заказов остановлена пользователем', 'woo-moysklad-integration'),
                'complete' => true,
                'processed' => $start,
                'total' => $total
            ));
            
            return;
        }
        
        // Get batch
        $batch = array_slice($orders, $start, $batch_size);
        $end = $start + count($batch);
        $complete = $end >= $total;
        
        // Log batch processing
        $logger = $this->plugin->get_logger();
        $logger->info("Обработка пакета заказов: $start - $end из $total");
        
        // Process each order
        $processed_count = 0;
        $errors = array();
        
        foreach ($batch as $order_id) {
            // Check if sync was stopped during batch processing
            if (get_option('woo_moysklad_sync_stopped_by_user', '0') === '1') {
                break;
            }
            
            try {
                // Sync order
                $result = $order_sync->create_or_update_order($order_id);
                
                if ($result) {
                    $processed_count++;
                }
            } catch (Exception $e) {
                $errors[] = array(
                    'order_id' => $order_id,
                    'message' => $e->getMessage()
                );
                
                $logger->error("Не удалось синхронизировать заказ #{$order_id}: " . $e->getMessage());
            }
        }
        
        // If sync was completed or stopped
        if ($complete || get_option('woo_moysklad_sync_stopped_by_user', '0') === '1') {
            update_option('woo_moysklad_sync_in_progress', '0');
            
            if (get_option('woo_moysklad_sync_stopped_by_user', '0') === '1') {
                update_option('woo_moysklad_sync_stopped_by_user', '0');
                $message = __('Синхронизация заказов остановлена пользователем', 'woo-moysklad-integration');
            } else {
                $message = __('Синхронизация заказов завершена', 'woo-moysklad-integration');
                $logger->info('Массовая синхронизация заказов завершена');
            }
        } else {
            $message = sprintf(
                __('Обработано %d из %d заказов', 'woo-moysklad-integration'),
                $end,
                $total
            );
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'processed' => $end,
            'batch_processed' => $processed_count,
            'batch_errors' => count($errors),
            'total' => $total,
            'complete' => $complete || get_option('woo_moysklad_sync_stopped_by_user', '0') === '1',
            'errors' => $errors
        ));
    }
}
