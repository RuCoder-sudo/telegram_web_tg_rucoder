<?php
/**
 * Category Synchronization
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */

/**
 * Category Synchronization class.
 *
 * This class handles synchronization of product categories/folders from MoySklad to WooCommerce.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */
class Woo_Moysklad_Category_Sync {

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
     * Synchronize categories from MoySklad to WooCommerce.
     *
     * @since    1.0.0
     * @return   array    Sync results.
     */
    public function sync_categories() {
        if (!$this->api->is_configured()) {
            $this->logger->error('Category sync failed: API not configured');
            return array(
                'success' => false,
                'message' => __('API not configured', 'woo-moysklad-integration'),
            );
        }
        
        $sync_product_groups = get_option('woo_moysklad_sync_product_groups', '1');
        if ($sync_product_groups !== '1') {
            $this->logger->info('Category synchronization is disabled');
            return array(
                'success' => false,
                'message' => __('Category synchronization is disabled', 'woo-moysklad-integration'),
            );
        }
        
        $this->logger->info('Starting category synchronization');
        
        try {
            // Get categories from MoySklad
            $response = $this->api->get_product_folders();
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            if (!isset($response['rows']) || empty($response['rows'])) {
                $this->logger->info('No categories found in MoySklad');
                return array(
                    'success' => true,
                    'message' => __('No categories found in MoySklad', 'woo-moysklad-integration'),
                );
            }
            
            $ms_categories = $response['rows'];
            
            // Process categories
            $stats = array(
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
            );
            
            // First, map categories by ID for parent-child relationships
            $categories_map = array();
            foreach ($ms_categories as $category) {
                $categories_map[$category['id']] = $category;
            }
            
            // Построение полной иерархии категорий для лучшей обработки родительских зависимостей
            $categories_by_id = array();
            $parent_map = array();
            $processed_categories = array();
            
            // Сначала создаем карту категорий по ID и строим карту родитель-потомок
            foreach ($ms_categories as $category) {
                $categories_by_id[$category['id']] = $category;
                
                // Извлекаем родительскую категорию, если она есть
                if (isset($category['productFolder']) && isset($category['productFolder']['meta']) && isset($category['productFolder']['meta']['href'])) {
                    $parent_id = '';
                    if (preg_match('/productfolder\/([^\/]+)/', $category['productFolder']['meta']['href'], $matches)) {
                        $parent_id = $matches[1];
                        $parent_map[$category['id']] = $parent_id;
                    }
                }
            }
            
            // Функция для создания или обновления категории с рекурсивной обработкой родителей
            $create_category_with_parents = function($category_id) use (
                &$categories_by_id, &$parent_map, &$processed_categories, 
                &$stats, &$create_category_with_parents
            ) {
                // Если категория уже обработана, возвращаем её ID в WooCommerce
                if (in_array($category_id, $processed_categories)) {
                    return $this->get_woo_category_id_by_ms_id($category_id);
                }
                
                // Если категории нет в списке - что-то пошло не так
                if (!isset($categories_by_id[$category_id])) {
                    $this->logger->error("Category with ID $category_id not found in MoySklad data");
                    return 0;
                }
                
                $category = $categories_by_id[$category_id];
                
                // Проверка на остановку синхронизации
                if (get_option('woo_moysklad_sync_stopped_by_user', '0') === '1') {
                    $this->logger->info('Синхронизация категорий остановлена пользователем');
                    update_option('woo_moysklad_sync_stopped_by_user', '0');
                    return 0;
                }
                
                // Если у категории есть родитель, сначала создаем его
                $parent_wc_id = 0;
                if (isset($parent_map[$category_id])) {
                    $parent_id = $parent_map[$category_id];
                    $parent_wc_id = $create_category_with_parents($parent_id);
                }
                
                // Создаем или обновляем текущую категорию
                $this->process_category($category, $parent_wc_id, $stats);
                $processed_categories[] = $category_id;
                
                return $this->get_woo_category_id_by_ms_id($category_id);
            };
            
            // Обрабатываем каждую категорию, гарантируя что родители будут созданы перед потомками
            foreach ($ms_categories as $category) {
                // Проверка на остановку синхронизации
                if (get_option('woo_moysklad_sync_stopped_by_user', '0') === '1') {
                    $this->logger->info('Синхронизация категорий остановлена пользователем');
                    update_option('woo_moysklad_sync_stopped_by_user', '0');
                    break;
                }
                
                if (!in_array($category['id'], $processed_categories)) {
                    $create_category_with_parents($category['id']);
                }
            }
            
            // Проверяем, есть ли необработанные категории
            $remaining_categories = array_filter($ms_categories, function($category) use ($processed_categories) {
                return !in_array($category['id'], $processed_categories);
            });
            
            // Лог необработанных категорий, если таковые остались
            if (!empty($remaining_categories)) {
                foreach ($remaining_categories as $category) {
                    $this->logger->warning("Category processing failed: {$category['name']}");
                    $stats['skipped']++;
                }
            }
            
            $this->logger->info('Category synchronization completed', $stats);
            
            return array(
                'success' => true,
                'message' => sprintf(
                    __('Categories synchronized: %d created, %d updated, %d skipped', 'woo-moysklad-integration'),
                    $stats['created'],
                    $stats['updated'],
                    $stats['skipped']
                ),
                'stats' => $stats,
            );
        } catch (Exception $e) {
            $this->logger->error('Category sync error: ' . $e->getMessage());
            
            return array(
                'success' => false,
                'message' => $e->getMessage(),
            );
        }
    }
    
    /**
     * Process a single category.
     *
     * @since    1.0.0
     * @param    array     $category       The category data from MoySklad.
     * @param    int       $parent_id      The parent category ID in WooCommerce (0 for top-level).
     * @param    array     &$stats         Synchronization statistics.
     * @return   int|false                 The WooCommerce category ID or false on failure.
     */
    private function process_category($category, $parent_id, &$stats) {
        // Check if category already exists
        $existing_category_id = $this->get_woo_category_id_by_ms_id($category['id']);
        
        $category_data = array(
            'name' => $category['name'],
            'description' => isset($category['description']) ? $category['description'] : '',
            'parent' => $parent_id,
        );
        
        if ($existing_category_id) {
            // Update existing category
            $category_data['term_id'] = $existing_category_id;
            $result = wp_update_term($existing_category_id, 'product_cat', $category_data);
            
            if (is_wp_error($result)) {
                $this->logger->error('Failed to update category: ' . $result->get_error_message(), array(
                    'category_name' => $category['name'],
                    'ms_id' => $category['id']
                ));
                $stats['skipped']++;
                return false;
            }
            
            $this->logger->debug("Updated category: {$category['name']} (ID: $existing_category_id)");
            $stats['updated']++;
            
            return $existing_category_id;
        } else {
            // Create new category
            $result = wp_insert_term($category['name'], 'product_cat', $category_data);
            
            if (is_wp_error($result)) {
                $this->logger->error('Failed to create category: ' . $result->get_error_message(), array(
                    'category_name' => $category['name'],
                    'ms_id' => $category['id']
                ));
                $stats['skipped']++;
                return false;
            }
            
            $wc_category_id = $result['term_id'];
            
            // Store MoySklad ID as meta
            update_term_meta($wc_category_id, '_ms_category_id', $category['id']);
            
            $this->logger->debug("Created category: {$category['name']} (ID: $wc_category_id)");
            $stats['created']++;
            
            return $wc_category_id;
        }
    }
    
    /**
     * Get WooCommerce category ID by MoySklad ID.
     *
     * @since    1.0.0
     * @param    string    $ms_category_id    The MoySklad category ID.
     * @return   int|false                    The WooCommerce category ID or false.
     */
    private function get_woo_category_id_by_ms_id($ms_category_id) {
        if (empty($ms_category_id)) {
            return false;
        }
        
        // Query category by meta
        $args = array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'meta_query' => array(
                array(
                    'key'     => '_ms_category_id',
                    'value'   => $ms_category_id,
                    'compare' => '='
                )
            )
        );
        
        $terms = get_terms($args);
        
        if (!is_wp_error($terms) && !empty($terms)) {
            return $terms[0]->term_id;
        }
        
        return false;
    }
}
