<?php
/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */
class Woo_Moysklad_Integration {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Woo_Moysklad_Integration    $instance    Maintains and registers all hooks for the plugin.
     */
    protected static $instance = null;
    
    /**
     * The MoySklad API handler.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Woo_Moysklad_API    $api    Handles API calls to MoySklad.
     */
    protected $api;
    
    /**
     * The Product Synchronization handler.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Woo_Moysklad_Product_Sync    $product_sync    Handles product synchronization.
     */
    protected $product_sync;
    
    /**
     * The Inventory Synchronization handler.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Woo_Moysklad_Inventory_Sync    $inventory_sync    Handles inventory synchronization.
     */
    protected $inventory_sync;
    
    /**
     * The Order Synchronization handler.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Woo_Moysklad_Order_Sync    $order_sync    Handles order synchronization.
     */
    protected $order_sync;
    
    /**
     * The Category Synchronization handler.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Woo_Moysklad_Category_Sync    $category_sync    Handles category synchronization.
     */
    protected $category_sync;
    
    /**
     * The Customer Synchronization handler.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Woo_Moysklad_Customer_Sync    $customer_sync    Handles customer synchronization.
     */
    protected $customer_sync;
    
    /**
     * The Webhook handler.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Woo_Moysklad_Webhook_Handler    $webhook_handler    Handles webhooks from MoySklad.
     */
    protected $webhook_handler;
    
    /**
     * The Logger.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Woo_Moysklad_Logger    $logger    Logs plugin activities.
     */
    protected $logger;
    
    /**
     * The Admin handler.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Woo_Moysklad_Integration_Admin    $admin    Handles admin functionality.
     */
    protected $admin;
    
    /**
     * The Bonus Integration handler.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Woo_Moysklad_Bonus_Integration    $bonus_integration    Handles integration with Bonus for Woo plugin.
     */
    protected $bonus_integration;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin dependencies and the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_sync_hooks();
    }
    
    /**
     * Get an instance of this class.
     *
     * @since     1.0.0
     * @return    Woo_Moysklad_Integration    A single instance of this class.
     */
    public static function get_instance() {
        if (null == self::$instance) {
            self::$instance = new self;
        }
        
        return self::$instance;
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        // API and core functionality
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-woo-moysklad-api.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-woo-moysklad-logger.php';
        
        // Synchronization classes
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-woo-moysklad-product-sync.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-woo-moysklad-inventory-sync.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-woo-moysklad-order-sync.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-woo-moysklad-category-sync.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-woo-moysklad-customer-sync.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-woo-moysklad-webhook-handler.php';
        
        // Integrations with other plugins
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-woo-moysklad-bonus-integration.php';
        
        // Admin
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-woo-moysklad-integration-admin.php';
        
        // Initialize components
        $this->logger = new Woo_Moysklad_Logger();
        $this->api = new Woo_Moysklad_API($this->logger);
        $this->product_sync = new Woo_Moysklad_Product_Sync($this->api, $this->logger);
        $this->inventory_sync = new Woo_Moysklad_Inventory_Sync($this->api, $this->logger);
        $this->order_sync = new Woo_Moysklad_Order_Sync($this->api, $this->logger);
        $this->category_sync = new Woo_Moysklad_Category_Sync($this->api, $this->logger);
        $this->customer_sync = new Woo_Moysklad_Customer_Sync($this->api, $this->logger);
        $this->webhook_handler = new Woo_Moysklad_Webhook_Handler($this->api, $this->logger);
        $this->bonus_integration = new Woo_Moysklad_Bonus_Integration($this->api, $this->logger);
        $this->admin = new Woo_Moysklad_Integration_Admin($this);
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        // Admin UI hooks
        add_action('admin_menu', array($this->admin, 'add_plugin_admin_menu'));
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_scripts'));
        
        // Plugin settings
        add_action('admin_init', array($this->admin, 'register_settings'));
        
        // Plugin action links
        add_filter('plugin_action_links_' . WOO_MOYSKLAD_PLUGIN_BASENAME, array($this->admin, 'plugin_action_links'));
    }

    /**
     * Register all of the hooks related to synchronization functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_sync_hooks() {
        // Schedule cron jobs
        add_action('init', array($this, 'setup_cron_jobs'));
        
        // Cron job hooks
        add_action('woo_moysklad_product_sync_cron', array($this->product_sync, 'sync_products'));
        add_action('woo_moysklad_inventory_sync_cron', array($this->inventory_sync, 'sync_inventory'));
        
        // WooCommerce order hooks
        add_action('woocommerce_new_order', array($this->order_sync, 'sync_new_order'));
        add_action('woocommerce_order_status_changed', array($this->order_sync, 'handle_order_status_change'), 10, 3);
        
        // Webhooks
        add_action('rest_api_init', array($this->webhook_handler, 'register_webhook_endpoints'));
        
        // Bonus integration hooks
        add_action('woo_moysklad_register_bonus_attributes', array($this->bonus_integration, 'register_bonus_attributes'));
    }

    /**
     * Setup cron jobs for synchronization tasks.
     *
     * @since    1.0.0
     */
    public function setup_cron_jobs() {
        // Get sync settings
        $product_sync_interval = get_option('woo_moysklad_product_sync_interval', 'daily');
        $inventory_sync_interval = get_option('woo_moysklad_inventory_sync_interval', 'hourly');
        
        // Product sync
        if (!wp_next_scheduled('woo_moysklad_product_sync_cron')) {
            wp_schedule_event(time(), $product_sync_interval, 'woo_moysklad_product_sync_cron');
        }
        
        // Inventory sync
        if (!wp_next_scheduled('woo_moysklad_inventory_sync_cron')) {
            wp_schedule_event(time(), $inventory_sync_interval, 'woo_moysklad_inventory_sync_cron');
        }
    }

    /**
     * Run the plugin.
     *
     * @since    1.0.0
     */
    public function run() {
        // Load translations
        load_plugin_textdomain(
            'woo-moysklad-integration',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
    
    /**
     * Get the API handler.
     *
     * @since    1.0.0
     * @return   Woo_Moysklad_API    The API handler.
     */
    public function get_api() {
        return $this->api;
    }
    
    /**
     * Get the product sync handler.
     *
     * @since    1.0.0
     * @return   Woo_Moysklad_Product_Sync    The product sync handler.
     */
    public function get_product_sync() {
        return $this->product_sync;
    }
    
    /**
     * Get the inventory sync handler.
     *
     * @since    1.0.0
     * @return   Woo_Moysklad_Inventory_Sync    The inventory sync handler.
     */
    public function get_inventory_sync() {
        return $this->inventory_sync;
    }
    
    /**
     * Get the order sync handler.
     *
     * @since    1.0.0
     * @return   Woo_Moysklad_Order_Sync    The order sync handler.
     */
    public function get_order_sync() {
        return $this->order_sync;
    }
    
    /**
     * Get the category sync handler.
     *
     * @since    1.0.0
     * @return   Woo_Moysklad_Category_Sync    The category sync handler.
     */
    public function get_category_sync() {
        return $this->category_sync;
    }
    
    /**
     * Get the customer sync handler.
     *
     * @since    1.0.0
     * @return   Woo_Moysklad_Customer_Sync    The customer sync handler.
     */
    public function get_customer_sync() {
        return $this->customer_sync;
    }
    
    /**
     * Get the webhook handler.
     *
     * @since    1.0.0
     * @return   Woo_Moysklad_Webhook_Handler    The webhook handler.
     */
    public function get_webhook_handler() {
        return $this->webhook_handler;
    }
    
    /**
     * Get the logger.
     *
     * @since    1.0.0
     * @return   Woo_Moysklad_Logger    The logger.
     */
    public function get_logger() {
        return $this->logger;
    }
    
    /**
     * Get the bonus integration handler.
     *
     * @since    1.0.0
     * @return   Woo_Moysklad_Bonus_Integration    The bonus integration handler.
     */
    public function get_bonus_integration() {
        return $this->bonus_integration;
    }
}
