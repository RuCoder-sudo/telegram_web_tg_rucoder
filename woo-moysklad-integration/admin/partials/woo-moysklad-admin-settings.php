<?php
/**
 * Settings admin template.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get API instance
$api = $this->plugin->get_api();

// Get options for select fields
$warehouses = array();
$organizations = array();
$customer_groups = array();
$order_states = array();
$price_types = array();

// Get data from MoySklad if API is configured
if ($api->is_configured()) {
    // Get warehouses
    $warehouses_response = $api->get_warehouses();
    if (!is_wp_error($warehouses_response) && isset($warehouses_response['rows'])) {
        foreach ($warehouses_response['rows'] as $warehouse) {
            $warehouses[$warehouse['id']] = $warehouse['name'];
        }
    }
    
    // Get organizations
    $organizations_response = $api->get_organizations();
    if (!is_wp_error($organizations_response) && isset($organizations_response['rows'])) {
        foreach ($organizations_response['rows'] as $organization) {
            $organizations[$organization['id']] = $organization['name'];
        }
    }
    
    // Get customer groups
    $customer_groups_response = $api->get_customer_groups();
    if (!is_wp_error($customer_groups_response) && isset($customer_groups_response['rows'])) {
        foreach ($customer_groups_response['rows'] as $group) {
            $customer_groups[$group['id']] = $group['name'];
        }
    }
    
    // Get order states
    $order_states_response = $api->get_order_states();
    if (!is_wp_error($order_states_response)) {
        foreach ($order_states_response as $state) {
            $order_states[$state['id']] = $state['name'];
        }
    }
    
    // Get price types
    $price_types_response = $api->get_price_types();
    if (!is_wp_error($price_types_response) && isset($price_types_response['rows'])) {
        foreach ($price_types_response['rows'] as $price_type) {
            $price_types[$price_type['id']] = $price_type['name'];
        }
    }
}

// Get all WooCommerce order statuses
$wc_order_statuses = wc_get_order_statuses();
// Get current status mapping
$status_mapping = get_option('woo_moysklad_order_status_mapping', array());
?>

<div class="wrap woo-moysklad-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="subheader">
        <?php _e('Настройте параметры интеграции WooCommerce с МойСклад.', 'woo-moysklad-integration'); ?>
    </div>
    
    <div class="woo-moysklad-tabs">
        <a href="#api-tab" class="woo-moysklad-tab-link"><?php _e('Настройки API', 'woo-moysklad-integration'); ?></a>
        <a href="#products-tab" class="woo-moysklad-tab-link"><?php _e('Товары', 'woo-moysklad-integration'); ?></a>
        <a href="#inventory-tab" class="woo-moysklad-tab-link"><?php _e('Склад', 'woo-moysklad-integration'); ?></a>
        <a href="#orders-tab" class="woo-moysklad-tab-link"><?php _e('Заказы', 'woo-moysklad-integration'); ?></a>
        <a href="#customers-tab" class="woo-moysklad-tab-link"><?php _e('Клиенты', 'woo-moysklad-integration'); ?></a>
        <a href="#bonus-tab" class="woo-moysklad-tab-link"><?php _e('Бонусы', 'woo-moysklad-integration'); ?></a>
        <a href="#webhooks-tab" class="woo-moysklad-tab-link"><?php _e('Вебхуки', 'woo-moysklad-integration'); ?></a>
        <a href="#logs-tab" class="woo-moysklad-tab-link"><?php _e('Логи', 'woo-moysklad-integration'); ?></a>
    </div>
    
    <!-- API Settings Tab -->
    <div id="api-tab" class="woo-moysklad-tab-content">
        <form method="post" action="options.php">
            <?php settings_fields('woo_moysklad_api_settings'); ?>
            
            <h2><?php _e('Настройки API МойСклад', 'woo-moysklad-integration'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_api_token"><?php _e('API Токен', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="woo_moysklad_api_token" name="woo_moysklad_api_token" 
                               value="<?php echo esc_attr(get_option('woo_moysklad_api_token', '')); ?>" class="regular-text">
                        <p class="description">
                            <?php _e('Ваш токен доступа к API МойСклад. Создается в настройках интеграции в личном кабинете МойСклад.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row" colspan="2">
                        <h3><?php _e('ИЛИ используйте логин и пароль (устаревший метод)', 'woo-moysklad-integration'); ?></h3>
                    </th>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_api_login"><?php _e('Логин', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="woo_moysklad_api_login" name="woo_moysklad_api_login" 
                               value="<?php echo esc_attr(get_option('woo_moysklad_api_login', '')); ?>" class="regular-text">
                        <p class="description">
                            <?php _e('Ваш логин от аккаунта МойСклад.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_api_password"><?php _e('Пароль', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="woo_moysklad_api_password" name="woo_moysklad_api_password" 
                               value="<?php echo esc_attr(get_option('woo_moysklad_api_password', '')); ?>" class="regular-text">
                        <p class="description">
                            <?php _e('Ваш пароль от аккаунта МойСклад.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <div id="connection-status"></div>
            
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php _e('Сохранить настройки API', 'woo-moysklad-integration'); ?>
                </button>
                <button type="button" id="test-connection-button" class="button">
                    <?php _e('Проверить соединение', 'woo-moysklad-integration'); ?>
                </button>
            </p>
            
            <div class="woo-moysklad-instructions">
                <h3><?php _e('Инструкции по настройке и использованию', 'woo-moysklad-integration'); ?></h3>
                <ol>
                    <li><?php _e('Введите токен API или логин/пароль от аккаунта МойСклад', 'woo-moysklad-integration'); ?></li>
                    <li><?php _e('Нажмите "Сохранить настройки API" и затем "Проверить соединение"', 'woo-moysklad-integration'); ?></li>
                    <li><?php _e('После успешного подключения перейдите на вкладки "Товары", "Склад" и "Заказы" для настройки параметров синхронизации', 'woo-moysklad-integration'); ?></li>
                    <li><?php _e('На вкладке "Вебхуки" нажмите "Зарегистрировать вебхуки" для настройки автоматических обновлений при изменениях в МойСклад', 'woo-moysklad-integration'); ?></li>
                    <li><?php _e('Вернитесь на страницу "Товары" и нажмите кнопки "Синхронизировать товары" и "Синхронизировать остатки" для выполнения первой синхронизации', 'woo-moysklad-integration'); ?></li>
                </ol>
                
                <h4><?php _e('О режимах синхронизации', 'woo-moysklad-integration'); ?></h4>
                <p><?php _e('Плагин поддерживает два режима синхронизации:', 'woo-moysklad-integration'); ?></p>
                <ul>
                    <li><strong><?php _e('Стандартный режим:', 'woo-moysklad-integration'); ?></strong> <?php _e('Загружает товары небольшими партиями (рекомендуется для больших каталогов и медленных серверов).', 'woo-moysklad-integration'); ?></li>
                    <li><strong><?php _e('Ускоренный режим:', 'woo-moysklad-integration'); ?></strong> <?php _e('Загружает все товары за один запрос (быстрее, но требует больше ресурсов).', 'woo-moysklad-integration'); ?></li>
                </ul>
                
                <h4><?php _e('Поддержка CommerceML', 'woo-moysklad-integration'); ?></h4>
                <p><?php _e('Плагин использует JSON API МойСклад для интеграции, что является рекомендуемым методом. Поддержка CommerceML не требуется для большинства сценариев использования.', 'woo-moysklad-integration'); ?></p>
            </div>
        </form>
    </div>
    
    <!-- Products Tab -->
    <div id="products-tab" class="woo-moysklad-tab-content">
        <form method="post" action="options.php">
            <?php settings_fields('woo_moysklad_product_settings'); ?>
            
            <h2><?php _e('Настройки синхронизации товаров', 'woo-moysklad-integration'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_sync_enabled"><?php _e('Включить синхронизацию', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="woo_moysklad_sync_enabled" name="woo_moysklad_sync_enabled" value="1" 
                               <?php checked('1', get_option('woo_moysklad_sync_enabled', '0')); ?>>
                        <p class="description">
                            <?php _e('Включает синхронизацию товаров из МойСклад в WooCommerce.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_product_sync_interval"><?php _e('Интервал синхронизации', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <select id="woo_moysklad_product_sync_interval" name="woo_moysklad_product_sync_interval">
                            <option value="hourly" <?php selected('hourly', get_option('woo_moysklad_product_sync_interval', 'daily')); ?>>
                                <?php _e('Каждый час', 'woo-moysklad-integration'); ?>
                            </option>
                            <option value="twicedaily" <?php selected('twicedaily', get_option('woo_moysklad_product_sync_interval', 'daily')); ?>>
                                <?php _e('Два раза в день', 'woo-moysklad-integration'); ?>
                            </option>
                            <option value="daily" <?php selected('daily', get_option('woo_moysklad_product_sync_interval', 'daily')); ?>>
                                <?php _e('Раз в день', 'woo-moysklad-integration'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('Как часто автоматически синхронизировать товары.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_sync_mode"><?php _e('Режим синхронизации', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <select id="woo_moysklad_sync_mode" name="woo_moysklad_sync_mode">
                            <option value="standard" <?php selected('standard', get_option('woo_moysklad_sync_mode', 'standard')); ?>>
                                <?php _e('Стандартный', 'woo-moysklad-integration'); ?>
                            </option>
                            <option value="accelerated" <?php selected('accelerated', get_option('woo_moysklad_sync_mode', 'standard')); ?>>
                                <?php _e('Ускоренный', 'woo-moysklad-integration'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('Стандартный режим загружает товары небольшими партиями (лучше для больших каталогов на медленных серверах). Ускоренный режим загружает все товары за один запрос (быстрее, но требует больше ресурсов).', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_price_type"><?php _e('Тип цены', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <select id="woo_moysklad_price_type" name="woo_moysklad_price_type">
                            <option value="default" <?php selected('default', get_option('woo_moysklad_price_type', 'default')); ?>>
                                <?php _e('По умолчанию', 'woo-moysklad-integration'); ?>
                            </option>
                            <?php foreach ($price_types as $id => $name) : ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected($id, get_option('woo_moysklad_price_type', 'default')); ?>>
                                    <?php echo esc_html($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php _e('Выберите, какой тип цены использовать из МойСклад.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <h3 class="woo-moysklad-section-title"><?php _e('Синхронизация данных товаров', 'woo-moysklad-integration'); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_sync_product_name"><?php _e('Синхронизировать названия', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="woo_moysklad_sync_product_name" name="woo_moysklad_sync_product_name" value="1" 
                               <?php checked('1', get_option('woo_moysklad_sync_product_name', '1')); ?>>
                        <p class="description">
                            <?php _e('Синхронизировать названия товаров из МойСклад.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_sync_product_description"><?php _e('Синхронизировать описания', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="woo_moysklad_sync_product_description" name="woo_moysklad_sync_product_description" value="1" 
                               <?php checked('1', get_option('woo_moysklad_sync_product_description', '1')); ?>>
                        <p class="description">
                            <?php _e('Синхронизировать описания товаров из МойСклад.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_sync_product_images"><?php _e('Синхронизировать изображения', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="woo_moysklad_sync_product_images" name="woo_moysklad_sync_product_images" value="1" 
                               <?php checked('1', get_option('woo_moysklad_sync_product_images', '1')); ?>>
                        <p class="description">
                            <?php _e('Синхронизировать изображения товаров из МойСклад.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_sync_all_product_images"><?php _e('Синхронизировать все изображения', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="woo_moysklad_sync_all_product_images" name="woo_moysklad_sync_all_product_images" value="1" 
                               <?php checked('1', get_option('woo_moysklad_sync_all_product_images', '1')); ?>>
                        <p class="description">
                            <?php _e('Синхронизировать все изображения товаров (галерею) из МойСклад. Если отключено, будет синхронизировано только основное изображение.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_sync_product_groups"><?php _e('Синхронизировать категории', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="woo_moysklad_sync_product_groups" name="woo_moysklad_sync_product_groups" value="1" 
                               <?php checked('1', get_option('woo_moysklad_sync_product_groups', '1')); ?>>
                        <p class="description">
                            <?php _e('Синхронизировать категории товаров (папки товаров) из МойСклад.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_sync_product_modifications"><?php _e('Синхронизировать модификации', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="woo_moysklad_sync_product_modifications" name="woo_moysklad_sync_product_modifications" value="1" 
                               <?php checked('1', get_option('woo_moysklad_sync_product_modifications', '1')); ?>>
                        <p class="description">
                            <?php _e('Синхронизировать модификации товаров как вариативные товары WooCommerce.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_sync_product_bundles"><?php _e('Синхронизировать комплекты', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="woo_moysklad_sync_product_bundles" name="woo_moysklad_sync_product_bundles" value="1" 
                               <?php checked('1', get_option('woo_moysklad_sync_product_bundles', '1')); ?>>
                        <p class="description">
                            <?php _e('Синхронизировать комплекты товаров из МойСклад.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_sync_product_custom_fields"><?php _e('Синхронизировать доп. поля', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="woo_moysklad_sync_product_custom_fields" name="woo_moysklad_sync_product_custom_fields" value="1" 
                               <?php checked('1', get_option('woo_moysklad_sync_product_custom_fields', '1')); ?>>
                        <p class="description">
                            <?php _e('Синхронизировать дополнительные поля товаров как атрибуты товаров WooCommerce.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php _e('Сохранить настройки товаров', 'woo-moysklad-integration'); ?>
                </button>
            </p>
        </form>
    </div>
    
    <!-- Inventory Tab -->
    <div id="inventory-tab" class="woo-moysklad-tab-content">
        <form method="post" action="options.php">
            <?php settings_fields('woo_moysklad_inventory_settings'); ?>
            
            <h2><?php _e('Inventory Synchronization Settings', 'woo-moysklad-integration'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_inventory_sync_interval"><?php _e('Sync Interval', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <select id="woo_moysklad_inventory_sync_interval" name="woo_moysklad_inventory_sync_interval">
                            <option value="hourly" <?php selected('hourly', get_option('woo_moysklad_inventory_sync_interval', 'hourly')); ?>>
                                <?php _e('Hourly', 'woo-moysklad-integration'); ?>
                            </option>
                            <option value="twicedaily" <?php selected('twicedaily', get_option('woo_moysklad_inventory_sync_interval', 'hourly')); ?>>
                                <?php _e('Twice Daily', 'woo-moysklad-integration'); ?>
                            </option>
                            <option value="daily" <?php selected('daily', get_option('woo_moysklad_inventory_sync_interval', 'hourly')); ?>>
                                <?php _e('Daily', 'woo-moysklad-integration'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('How often to automatically synchronize inventory levels.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_inventory_warehouse_id"><?php _e('Warehouse', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <select id="woo_moysklad_inventory_warehouse_id" name="woo_moysklad_inventory_warehouse_id">
                            <option value=""><?php _e('All Warehouses', 'woo-moysklad-integration'); ?></option>
                            <?php foreach ($warehouses as $id => $name) : ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected($id, get_option('woo_moysklad_inventory_warehouse_id', '')); ?>>
                                    <?php echo esc_html($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php _e('Select which warehouse to use for inventory levels. If not selected, total stock from all warehouses will be used.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php _e('Save Inventory Settings', 'woo-moysklad-integration'); ?>
                </button>
            </p>
        </form>
    </div>
    
    <!-- Orders Tab -->
    <div id="orders-tab" class="woo-moysklad-tab-content">
        <form method="post" action="options.php">
            <?php settings_fields('woo_moysklad_order_settings'); ?>
            
            <h2><?php _e('Order Synchronization Settings', 'woo-moysklad-integration'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_order_sync_enabled"><?php _e('Enable Order Sync', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="woo_moysklad_order_sync_enabled" name="woo_moysklad_order_sync_enabled" value="1" 
                               <?php checked('1', get_option('woo_moysklad_order_sync_enabled', '1')); ?>>
                        <p class="description">
                            <?php _e('Enable order synchronization from WooCommerce to MoySklad.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_order_sync_delay"><?php _e('Sync Timing', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <select id="woo_moysklad_order_sync_delay" name="woo_moysklad_order_sync_delay">
                            <option value="0" <?php selected('0', get_option('woo_moysklad_order_sync_delay', '0')); ?>>
                                <?php _e('Immediate', 'woo-moysklad-integration'); ?>
                            </option>
                            <option value="delayed" <?php selected('delayed', get_option('woo_moysklad_order_sync_delay', '0')); ?>>
                                <?php _e('Delayed', 'woo-moysklad-integration'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('Choose when to synchronize orders. Immediate sends orders to MoySklad right away. Delayed waits for the specified time period.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr id="delay-minutes-row" style="<?php echo get_option('woo_moysklad_order_sync_delay', '0') === 'delayed' ? '' : 'display: none;'; ?>">
                    <th scope="row">
                        <label for="woo_moysklad_order_sync_delay_minutes"><?php _e('Delay Minutes', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="woo_moysklad_order_sync_delay_minutes" name="woo_moysklad_order_sync_delay_minutes" 
                               value="<?php echo esc_attr(get_option('woo_moysklad_order_sync_delay_minutes', '60')); ?>" min="1" max="1440" class="small-text">
                        <p class="description">
                            <?php _e('Number of minutes to wait before sending the order to MoySklad.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_order_prefix"><?php _e('Order Prefix', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="woo_moysklad_order_prefix" name="woo_moysklad_order_prefix" 
                               value="<?php echo esc_attr(get_option('woo_moysklad_order_prefix', 'WC-')); ?>" class="regular-text">
                        <p class="description">
                            <?php _e('Prefix to add to order numbers in MoySklad.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_order_organization_id"><?php _e('Organization', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <select id="woo_moysklad_order_organization_id" name="woo_moysklad_order_organization_id">
                            <option value=""><?php _e('Select Organization', 'woo-moysklad-integration'); ?></option>
                            <?php foreach ($organizations as $id => $name) : ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected($id, get_option('woo_moysklad_order_organization_id', '')); ?>>
                                    <?php echo esc_html($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php _e('Select which organization to use for orders in MoySklad.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_order_warehouse_id"><?php _e('Warehouse', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <select id="woo_moysklad_order_warehouse_id" name="woo_moysklad_order_warehouse_id">
                            <option value=""><?php _e('Select Warehouse', 'woo-moysklad-integration'); ?></option>
                            <?php foreach ($warehouses as $id => $name) : ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected($id, get_option('woo_moysklad_order_warehouse_id', '')); ?>>
                                    <?php echo esc_html($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php _e('Select which warehouse to use for orders in MoySklad.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <h3 class="woo-moysklad-section-title"><?php _e('Order Status Synchronization', 'woo-moysklad-integration'); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_order_status_sync_enabled"><?php _e('Sync Order Status', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="woo_moysklad_order_status_sync_enabled" name="woo_moysklad_order_status_sync_enabled" value="1" 
                               <?php checked('1', get_option('woo_moysklad_order_status_sync_enabled', '1')); ?>>
                        <p class="description">
                            <?php _e('Enable synchronization of order status from WooCommerce to MoySklad.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_order_status_sync_from_ms"><?php _e('Sync Status from MoySklad', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="woo_moysklad_order_status_sync_from_ms" name="woo_moysklad_order_status_sync_from_ms" value="1" 
                               <?php checked('1', get_option('woo_moysklad_order_status_sync_from_ms', '1')); ?>>
                        <p class="description">
                            <?php _e('Enable synchronization of order status from MoySklad to WooCommerce. Requires webhooks to be configured.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <h3 class="woo-moysklad-section-title"><?php _e('Order Status Mapping', 'woo-moysklad-integration'); ?></h3>
            
            <p class="description">
                <?php _e('Map WooCommerce order statuses to MoySklad order statuses.', 'woo-moysklad-integration'); ?>
            </p>
            
            <table class="form-table">
                <?php foreach ($wc_order_statuses as $status_key => $status_name) : 
                    $status_key = str_replace('wc-', '', $status_key);
                ?>
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_order_status_mapping_<?php echo esc_attr($status_key); ?>">
                            <?php echo esc_html($status_name); ?>
                        </label>
                    </th>
                    <td>
                        <select id="woo_moysklad_order_status_mapping_<?php echo esc_attr($status_key); ?>" 
                                name="woo_moysklad_order_status_mapping[<?php echo esc_attr($status_key); ?>]">
                            <option value=""><?php _e('Do not sync', 'woo-moysklad-integration'); ?></option>
                            <?php foreach ($order_states as $id => $name) : ?>
                                <option value="<?php echo esc_attr($id); ?>" 
                                        <?php selected($id, isset($status_mapping[$status_key]) ? $status_mapping[$status_key] : ''); ?>>
                                    <?php echo esc_html($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php _e('Save Order Settings', 'woo-moysklad-integration'); ?>
                </button>
            </p>
        </form>
        
        <script>
            jQuery(document).ready(function($) {
                $('#woo_moysklad_order_sync_delay').on('change', function() {
                    if ($(this).val() === 'delayed') {
                        $('#delay-minutes-row').show();
                    } else {
                        $('#delay-minutes-row').hide();
                    }
                });
            });
        </script>
    </div>
    
    <!-- Customers Tab -->
    <div id="customers-tab" class="woo-moysklad-tab-content">
        <form method="post" action="options.php">
            <?php settings_fields('woo_moysklad_customer_settings'); ?>
            
            <h2><?php _e('Customer Synchronization Settings', 'woo-moysklad-integration'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_customer_sync_enabled"><?php _e('Enable Customer Sync', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="woo_moysklad_customer_sync_enabled" name="woo_moysklad_customer_sync_enabled" value="1" 
                               <?php checked('1', get_option('woo_moysklad_customer_sync_enabled', '1')); ?>>
                        <p class="description">
                            <?php _e('Enable customer synchronization from WooCommerce to MoySklad.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_customer_group_id"><?php _e('Customer Group', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <select id="woo_moysklad_customer_group_id" name="woo_moysklad_customer_group_id">
                            <option value=""><?php _e('Default Group', 'woo-moysklad-integration'); ?></option>
                            <?php foreach ($customer_groups as $id => $name) : ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected($id, get_option('woo_moysklad_customer_group_id', '')); ?>>
                                    <?php echo esc_html($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php _e('Select which customer group to use for new customers in MoySklad.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_customer_price_type_sync"><?php _e('Customer Price Type Sync', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="woo_moysklad_customer_price_type_sync" name="woo_moysklad_customer_price_type_sync" value="1" 
                               <?php checked('1', get_option('woo_moysklad_customer_price_type_sync', '0')); ?>>
                        <p class="description">
                            <?php _e('Enable customer-specific price types. This allows assigning different price types to different customer groups.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr id="price-type-mapping-row" style="<?php echo get_option('woo_moysklad_customer_price_type_sync', '0') === '1' ? '' : 'display: none;'; ?>">
                    <th scope="row">
                        <?php _e('Price Type Mapping', 'woo-moysklad-integration'); ?>
                    </th>
                    <td>
                        <p>
                            <?php _e('This feature allows mapping customer groups to price types. To configure customer-specific prices, you need to set up customer groups in MoySklad first.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php _e('Save Customer Settings', 'woo-moysklad-integration'); ?>
                </button>
            </p>
        </form>
        
        <script>
            jQuery(document).ready(function($) {
                $('#woo_moysklad_customer_price_type_sync').on('change', function() {
                    if ($(this).is(':checked')) {
                        $('#price-type-mapping-row').show();
                    } else {
                        $('#price-type-mapping-row').hide();
                    }
                });
            });
        </script>
    </div>
    
    <!-- Bonus Integration Tab -->
    <div id="bonus-tab" class="woo-moysklad-tab-content">
        <form method="post" action="options.php">
            <?php settings_fields('woo_moysklad_bonus_settings'); ?>
            
            <h2><?php _e('Настройки интеграции с плагином "Бонусы для Woo"', 'woo-moysklad-integration'); ?></h2>
            
            <p>
                <?php _e('Данный раздел позволяет настроить интеграцию с плагином "Бонусы для Woo" для передачи информации о бонусных баллах клиентов в МойСклад.', 'woo-moysklad-integration'); ?>
            </p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_bonus_integration_enabled"><?php _e('Включить интеграцию с бонусами', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="woo_moysklad_bonus_integration_enabled" name="woo_moysklad_bonus_integration_enabled" value="1" 
                               <?php checked('1', get_option('woo_moysklad_bonus_integration_enabled', '0')); ?>>
                        <p class="description">
                            <?php _e('Включить интеграцию с плагином "Бонусы для Woo".', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_bonus_used_attribute_id"><?php _e('ID атрибута использованных бонусов', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="woo_moysklad_bonus_used_attribute_id" name="woo_moysklad_bonus_used_attribute_id" 
                               value="<?php echo esc_attr(get_option('woo_moysklad_bonus_used_attribute_id', '6af5c95b-f91b-11eb-0a80-0656000e3f2c')); ?>" class="regular-text">
                        <p class="description">
                            <?php _e('ID атрибута в МойСклад для хранения использованных бонусов в заказе.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_bonus_earned_attribute_id"><?php _e('ID атрибута начисленных бонусов', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="woo_moysklad_bonus_earned_attribute_id" name="woo_moysklad_bonus_earned_attribute_id" 
                               value="<?php echo esc_attr(get_option('woo_moysklad_bonus_earned_attribute_id', '7bc8dfbb-f91b-11eb-0a80-0656000e3f2d')); ?>" class="regular-text">
                        <p class="description">
                            <?php _e('ID атрибута в МойСклад для хранения начисленных бонусов в заказе.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_bonus_balance_attribute_id"><?php _e('ID атрибута баланса бонусов', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="woo_moysklad_bonus_balance_attribute_id" name="woo_moysklad_bonus_balance_attribute_id" 
                               value="<?php echo esc_attr(get_option('woo_moysklad_bonus_balance_attribute_id', '8c24e9bb-f91b-11eb-0a80-0656000e3f2e')); ?>" class="regular-text">
                        <p class="description">
                            <?php _e('ID атрибута в МойСклад для хранения текущего баланса бонусов у клиента.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <div id="bonus-integration-status"></div>
            
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php _e('Сохранить настройки бонусов', 'woo-moysklad-integration'); ?>
                </button>
                <button type="button" id="register-bonus-attributes-button" class="button">
                    <?php _e('Создать атрибуты в МойСклад', 'woo-moysklad-integration'); ?>
                </button>
            </p>
            
            <div class="woo-moysklad-instructions">
                <h3><?php _e('Инструкции по интеграции с бонусами', 'woo-moysklad-integration'); ?></h3>
                <ol>
                    <li><?php _e('Включите интеграцию с бонусами', 'woo-moysklad-integration'); ?></li>
                    <li><?php _e('Нажмите "Создать атрибуты в МойСклад" для создания необходимых атрибутов', 'woo-moysklad-integration'); ?></li>
                    <li><?php _e('После создания атрибутов ID будут автоматически заполнены', 'woo-moysklad-integration'); ?></li>
                    <li><?php _e('Сохраните настройки для активации интеграции', 'woo-moysklad-integration'); ?></li>
                </ol>
                
                <p>
                    <?php _e('После настройки бонусной интеграции, информация о начисленных и использованных бонусах будет автоматически передаваться в МойСклад при оформлении заказов.', 'woo-moysklad-integration'); ?>
                </p>
            </div>
        </form>
    </div>
    
    <!-- Webhooks Tab -->
    <div id="webhooks-tab" class="woo-moysklad-tab-content">
        <form method="post" action="options.php">
            <?php settings_fields('woo_moysklad_webhook_settings'); ?>
            
            <h2><?php _e('Webhook Settings', 'woo-moysklad-integration'); ?></h2>
            
            <p>
                <?php _e('Webhooks allow real-time synchronization between MoySklad and WooCommerce. When enabled, changes in MoySklad will be immediately reflected in your WooCommerce store.', 'woo-moysklad-integration'); ?>
            </p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_webhook_enabled"><?php _e('Enable Webhooks', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="woo_moysklad_webhook_enabled" name="woo_moysklad_webhook_enabled" value="1" 
                               <?php checked('1', get_option('woo_moysklad_webhook_enabled', '0')); ?>>
                        <p class="description">
                            <?php _e('Enable webhook integration with MoySklad.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_webhook_secret"><?php _e('Webhook Secret', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="woo_moysklad_webhook_secret" name="woo_moysklad_webhook_secret" 
                               value="<?php echo esc_attr(get_option('woo_moysklad_webhook_secret', wp_generate_password(32, false))); ?>" class="regular-text">
                        <p class="description">
                            <?php _e('Secret key to validate incoming webhooks. Leave empty to disable validation.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <div id="webhook-status"></div>
            
            <div class="webhook-url-container" style="display: none;">
                <p><?php _e('Your webhook URL:', 'woo-moysklad-integration'); ?></p>
                <pre id="webhook-url"></pre>
            </div>
            
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php _e('Save Webhook Settings', 'woo-moysklad-integration'); ?>
                </button>
                <button type="button" id="register-webhooks-button" class="button" 
                        <?php echo get_option('woo_moysklad_webhook_enabled', '0') !== '1' ? 'disabled' : ''; ?>>
                    <?php _e('Register Webhooks in MoySklad', 'woo-moysklad-integration'); ?>
                </button>
            </p>
        </form>
        
        <script>
            jQuery(document).ready(function($) {
                $('#woo_moysklad_webhook_enabled').on('change', function() {
                    if ($(this).is(':checked')) {
                        $('#register-webhooks-button').prop('disabled', false);
                    } else {
                        $('#register-webhooks-button').prop('disabled', true);
                    }
                });
            });
        </script>
    </div>
    
    <!-- Logs Tab -->
    <div id="logs-tab" class="woo-moysklad-tab-content">
        <form method="post" action="options.php">
            <?php settings_fields('woo_moysklad_log_settings'); ?>
            
            <h2><?php _e('Log Settings', 'woo-moysklad-integration'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_log_level"><?php _e('Log Level', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <select id="woo_moysklad_log_level" name="woo_moysklad_log_level">
                            <option value="emergency" <?php selected('emergency', get_option('woo_moysklad_log_level', 'info')); ?>>
                                <?php _e('Emergency', 'woo-moysklad-integration'); ?>
                            </option>
                            <option value="alert" <?php selected('alert', get_option('woo_moysklad_log_level', 'info')); ?>>
                                <?php _e('Alert', 'woo-moysklad-integration'); ?>
                            </option>
                            <option value="critical" <?php selected('critical', get_option('woo_moysklad_log_level', 'info')); ?>>
                                <?php _e('Critical', 'woo-moysklad-integration'); ?>
                            </option>
                            <option value="error" <?php selected('error', get_option('woo_moysklad_log_level', 'info')); ?>>
                                <?php _e('Error', 'woo-moysklad-integration'); ?>
                            </option>
                            <option value="warning" <?php selected('warning', get_option('woo_moysklad_log_level', 'info')); ?>>
                                <?php _e('Warning', 'woo-moysklad-integration'); ?>
                            </option>
                            <option value="notice" <?php selected('notice', get_option('woo_moysklad_log_level', 'info')); ?>>
                                <?php _e('Notice', 'woo-moysklad-integration'); ?>
                            </option>
                            <option value="info" <?php selected('info', get_option('woo_moysklad_log_level', 'info')); ?>>
                                <?php _e('Info', 'woo-moysklad-integration'); ?>
                            </option>
                            <option value="debug" <?php selected('debug', get_option('woo_moysklad_log_level', 'info')); ?>>
                                <?php _e('Debug', 'woo-moysklad-integration'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('Select the minimum log level to record. Debug is the most verbose, Emergency is the least verbose.', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_log_to_file"><?php _e('Log to File', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="woo_moysklad_log_to_file" name="woo_moysklad_log_to_file" value="1" 
                               <?php checked('1', get_option('woo_moysklad_log_to_file', '0')); ?>>
                        <p class="description">
                            <?php _e('Enable logging to file (wp-content/uploads/woo-moysklad-logs/).', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="woo_moysklad_log_to_db"><?php _e('Log to Database', 'woo-moysklad-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="woo_moysklad_log_to_db" name="woo_moysklad_log_to_db" value="1" 
                               <?php checked('1', get_option('woo_moysklad_log_to_db', '1')); ?>>
                        <p class="description">
                            <?php _e('Enable logging to database (viewable in the Logs page).', 'woo-moysklad-integration'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php _e('Save Log Settings', 'woo-moysklad-integration'); ?>
                </button>
                <a href="<?php echo admin_url('admin.php?page=woo-moysklad-logs'); ?>" class="button">
                    <?php _e('View Logs', 'woo-moysklad-integration'); ?>
                </a>
            </p>
        </form>
    </div>
    
    <script>
        jQuery(document).ready(function($) {
            // Обработчик кнопки создания атрибутов для бонусной интеграции
            $('#register-bonus-attributes-button').on('click', function() {
                var $button = $(this);
                var $statusDiv = $('#bonus-integration-status');
                
                // Показываем индикатор загрузки
                $button.addClass('loading').prop('disabled', true);
                $statusDiv.html('<div class="notice notice-info"><p><?php _e('Создание атрибутов в МойСклад...', 'woo-moysklad-integration'); ?></p></div>');
                
                // Отправляем AJAX запрос на создание атрибутов
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'register_bonus_attributes',
                        nonce: '<?php echo wp_create_nonce('woo_moysklad_register_bonus_attributes'); ?>'
                    },
                    success: function(response) {
                        $button.removeClass('loading').prop('disabled', false);
                        
                        if (response.success) {
                            // Обновляем ID атрибутов если они вернулись в ответе
                            if (response.data.attributes) {
                                if (response.data.attributes.used_bonus_id) {
                                    $('#woo_moysklad_bonus_used_attribute_id').val(response.data.attributes.used_bonus_id);
                                }
                                if (response.data.attributes.earned_bonus_id) {
                                    $('#woo_moysklad_bonus_earned_attribute_id').val(response.data.attributes.earned_bonus_id);
                                }
                                if (response.data.attributes.balance_bonus_id) {
                                    $('#woo_moysklad_bonus_balance_attribute_id').val(response.data.attributes.balance_bonus_id);
                                }
                            }
                            
                            $statusDiv.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        } else {
                            $statusDiv.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $button.removeClass('loading').prop('disabled', false);
                        $statusDiv.html('<div class="notice notice-error"><p><?php _e('Ошибка при создании атрибутов: ', 'woo-moysklad-integration'); ?>' + error + '</p></div>');
                    }
                });
            });
            
            // Отображаем/скрываем поля ID атрибутов в зависимости от состояния чекбокса
            $('#woo_moysklad_bonus_integration_enabled').on('change', function() {
                var fields = $('#woo_moysklad_bonus_used_attribute_id, #woo_moysklad_bonus_earned_attribute_id, #woo_moysklad_bonus_balance_attribute_id').closest('tr');
                var registerButton = $('#register-bonus-attributes-button');
                
                if ($(this).is(':checked')) {
                    fields.show();
                    registerButton.prop('disabled', false);
                } else {
                    fields.hide();
                    registerButton.prop('disabled', true);
                }
            }).trigger('change');
        });
    </script>
</div>
