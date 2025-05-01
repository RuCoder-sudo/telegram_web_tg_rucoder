<?php
/**
 * Plugin Name: WooCommerce МойСклад Интеграция
 * Plugin URI: https://рукодер.рф/
 * Description: Полная интеграция между WooCommerce и MoySklad.ru (МойСклад) для синхронизации товаров, остатков и заказов.
 * Version: 1.0.0
 * Author: RUCODER
 * Author URI: https://рукодер.рф/
 * Text Domain: woo-moysklad-integration
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 7.0.0
 *
 * @package WooMoySklad
 * 
 * 
 * ВАЖНО - АВТОРСКИЕ ПРАВА!
 * =========================
 * Этот плагин является интеллектуальной собственностью разработчика.
 * Распространение, копирование, модификация, продажа данного плагина
 * без письменного разрешения владельца строго запрещена.
 * Нарушение авторских прав преследуется по закону.
 * 
 * Разработчик: RUCODER - Разработка сайтов.
 * Сайт: https://рукодер.рф/
 * Телеграм: https://t.me/RussCoder
 * VK: https://vk.com/rucoderweb
 * Instagram: https://www.instagram.com/rucoder.web/
 * Email: rucoder.rf@yandex.ru
 * 
 * По всем вопросам и для заказа разработки сайтов
 * обращайтесь по контактным данным выше.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 */
define('WOO_MOYSKLAD_VERSION', '1.0.0');
define('WOO_MOYSKLAD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOO_MOYSKLAD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WOO_MOYSKLAD_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Check if WooCommerce is active
 */
function woo_moysklad_is_woocommerce_active() {
    $active_plugins = (array) get_option('active_plugins', array());
    
    if (is_multisite()) {
        $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
    }
    
    return in_array('woocommerce/woocommerce.php', $active_plugins) || array_key_exists('woocommerce/woocommerce.php', $active_plugins);
}

/**
 * The code that runs during plugin activation.
 */
function activate_woo_moysklad() {
    if (!woo_moysklad_is_woocommerce_active()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('Please install and activate WooCommerce before activating WooCommerce MoySklad Integration.', 'woo-moysklad-integration'));
    }
    
    require_once plugin_dir_path(__FILE__) . 'includes/class-woo-moysklad-integration-activator.php';
    Woo_Moysklad_Integration_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_woo_moysklad() {
    require_once plugin_dir_path(__FILE__) . 'includes/class-woo-moysklad-integration-deactivator.php';
    Woo_Moysklad_Integration_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_woo_moysklad');
register_deactivation_hook(__FILE__, 'deactivate_woo_moysklad');

/**
 * The core plugin class
 */
require plugin_dir_path(__FILE__) . 'includes/class-woo-moysklad-integration.php';

/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 */
function run_woo_moysklad() {
    $plugin = new Woo_Moysklad_Integration();
    $plugin->run();
}

// Only run the plugin if WooCommerce is active
if (woo_moysklad_is_woocommerce_active()) {
    run_woo_moysklad();
}
