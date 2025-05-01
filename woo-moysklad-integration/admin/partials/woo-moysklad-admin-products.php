<?php
/**
 * Products admin template.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$sync_enabled = get_option('woo_moysklad_sync_enabled', '0');
$sync_in_progress = get_option('woo_moysklad_sync_in_progress', '0');
$last_sync_time = get_option('woo_moysklad_last_sync_time', '');
$last_inventory_sync_time = get_option('woo_moysklad_last_inventory_sync_time', '');

$sync_enabled_text = $sync_enabled === '1' 
    ? __('Включена', 'woo-moysklad-integration')
    : __('Отключена', 'woo-moysklad-integration');

$active_text = __('Выполняется синхронизация', 'woo-moysklad-integration');
$inactive_text = __('Синхронизация не выполняется', 'woo-moysklad-integration');
?>

<div class="woo-moysklad-card">
    <h2><?php _e('Синхронизация товаров', 'woo-moysklad-integration'); ?></h2>
    
    <div class="sync-status-wrapper">
        <div class="sync-status-indicator <?php echo ($sync_in_progress === '1') ? 'sync-active' : 'sync-inactive'; ?>"
            data-active-text="<?php echo esc_attr($active_text); ?>"
            data-inactive-text="<?php echo esc_attr($inactive_text); ?>">
        </div>
        <span class="sync-status-text">
            <?php echo ($sync_in_progress === '1') ? esc_html($active_text) : esc_html($inactive_text); ?>
        </span>
    </div>
    
    <table class="form-table">
        <tr>
            <th><?php _e('Статус синхронизации', 'woo-moysklad-integration'); ?></th>
            <td><?php echo esc_html($sync_enabled_text); ?></td>
        </tr>
        <tr>
            <th><?php _e('Последняя синхронизация товаров', 'woo-moysklad-integration'); ?></th>
            <td id="last-sync-time">
                <?php 
                if (!empty($last_sync_time)) {
                    echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync_time)));
                } else {
                    _e('Никогда', 'woo-moysklad-integration');
                }
                ?>
            </td>
        </tr>
        <tr>
            <th><?php _e('Последняя синхронизация остатков', 'woo-moysklad-integration'); ?></th>
            <td id="last-inventory-sync-time">
                <?php 
                if (!empty($last_inventory_sync_time)) {
                    echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_inventory_sync_time)));
                } else {
                    _e('Никогда', 'woo-moysklad-integration');
                }
                ?>
            </td>
        </tr>
    </table>
    
    <div class="woo-moysklad-actions">
        <button id="sync-products-button" class="button button-primary"<?php echo ($sync_enabled !== '1') ? ' disabled' : ''; ?>>
            <?php _e('Синхронизировать товары', 'woo-moysklad-integration'); ?>
        </button>
        
        <button id="sync-categories-button" class="button"<?php echo ($sync_enabled !== '1') ? ' disabled' : ''; ?>>
            <?php _e('Только категории', 'woo-moysklad-integration'); ?>
        </button>
        
        <button id="sync-inventory-button" class="button"<?php echo ($sync_enabled !== '1') ? ' disabled' : ''; ?>>
            <?php _e('Только остатки', 'woo-moysklad-integration'); ?>
        </button>
        
        <button id="stop-sync-button" class="button" style="background-color: #dc3545; color: white; display: <?php echo ($sync_in_progress === '1') ? 'inline-block' : 'none'; ?>;">
            <?php _e('Остановить синхронизацию', 'woo-moysklad-integration'); ?>
        </button>
        
        <a href="<?php echo admin_url('admin.php?page=woo-moysklad-settings'); ?>" class="button">
            <?php _e('Настройки', 'woo-moysklad-integration'); ?>
        </a>
    </div>
    
    <div id="sync-status" class="sync-status"></div>
    <div id="category-sync-status" class="sync-status"></div>
    <div id="inventory-sync-status" class="sync-status"></div>
</div>

<div class="woo-moysklad-card">
    <h2><?php _e('Статистика синхронизации', 'woo-moysklad-integration'); ?></h2>
    
    <?php
    global $wpdb;
    $product_mapping_table = $wpdb->prefix . 'woo_moysklad_product_mapping';
    
    // Get product count
    $product_count = $wpdb->get_var("SELECT COUNT(*) FROM $product_mapping_table");
    
    // Get simple product count
    $simple_product_count = $wpdb->get_var("
        SELECT COUNT(*) FROM $wpdb->posts p
        JOIN $product_mapping_table m ON p.ID = m.woo_product_id
        WHERE p.post_type = 'product'
        AND p.post_status = 'publish'
    ");
    
    // Get variable product count
    $variable_product_count = $wpdb->get_var("
        SELECT COUNT(*) FROM $wpdb->posts p
        JOIN $product_mapping_table m ON p.ID = m.woo_product_id
        JOIN $wpdb->term_relationships tr ON p.ID = tr.object_id
        JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        JOIN $wpdb->terms t ON tt.term_id = t.term_id
        WHERE p.post_type = 'product'
        AND p.post_status = 'publish'
        AND tt.taxonomy = 'product_type'
        AND t.name = 'variable'
    ");
    
    // Get variation count
    $variation_count = $wpdb->get_var("
        SELECT COUNT(*) FROM $wpdb->posts
        WHERE post_type = 'product_variation'
        AND post_status = 'publish'
    ");
    
    // Get category count
    $category_count = $wpdb->get_var("
        SELECT COUNT(*) FROM $wpdb->termmeta
        WHERE meta_key = '_ms_category_id'
    ");
    ?>
    
    <table class="form-table">
        <tr>
            <th><?php _e('Всего синхронизировано товаров', 'woo-moysklad-integration'); ?></th>
            <td><?php echo esc_html($product_count); ?></td>
        </tr>
        <tr>
            <th><?php _e('Простые товары', 'woo-moysklad-integration'); ?></th>
            <td><?php echo esc_html($simple_product_count); ?></td>
        </tr>
        <tr>
            <th><?php _e('Вариативные товары', 'woo-moysklad-integration'); ?></th>
            <td><?php echo esc_html($variable_product_count); ?></td>
        </tr>
        <tr>
            <th><?php _e('Вариации товаров', 'woo-moysklad-integration'); ?></th>
            <td><?php echo esc_html($variation_count); ?></td>
        </tr>
        <tr>
            <th><?php _e('Синхронизировано категорий', 'woo-moysklad-integration'); ?></th>
            <td><?php echo esc_html($category_count); ?></td>
        </tr>
    </table>
</div>

<div class="woo-moysklad-card">
    <h2><?php _e('Руководство по синхронизации', 'woo-moysklad-integration'); ?></h2>
    
    <p>
        <?php _e('Этот плагин синхронизирует товары, остатки и заказы между WooCommerce и МойСклад. Инструкция по использованию:', 'woo-moysklad-integration'); ?>
    </p>
    
    <ol>
        <li><?php _e('Настройте учетные данные API МойСклад на вкладке Настройки.', 'woo-moysklad-integration'); ?></li>
        <li><?php _e('Включите синхронизацию и настройте параметры на вкладке Настройки.', 'woo-moysklad-integration'); ?></li>
        <li><?php _e('Нажмите "Синхронизировать товары" для запуска синхронизации товаров вручную.', 'woo-moysklad-integration'); ?></li>
        <li><?php _e('Нажмите "Только остатки" для обновления только уровней остатков товаров.', 'woo-moysklad-integration'); ?></li>
        <li><?php _e('Заказы автоматически синхронизируются при создании в WooCommerce.', 'woo-moysklad-integration'); ?></li>
        <li><?php _e('Для обновлений в реальном времени включите и настройте вебхуки на вкладке Настройки.', 'woo-moysklad-integration'); ?></li>
    </ol>
    
    <p>
        <?php _e('Плагин также поддерживает запланированную синхронизацию через задания cron WordPress на основе ваших настроек.', 'woo-moysklad-integration'); ?>
    </p>
</div>
