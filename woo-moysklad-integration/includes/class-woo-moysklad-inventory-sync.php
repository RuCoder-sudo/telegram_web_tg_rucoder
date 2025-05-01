<?php
/**
 * Inventory Synchronization
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */

/**
 * Inventory Synchronization class.
 *
 * This class handles synchronization of inventory levels from MoySklad to WooCommerce.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */
class Woo_Moysklad_Inventory_Sync {

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
     * Synchronize inventory levels from MoySklad to WooCommerce.
     *
     * @since    1.0.0
     * @return   array    Sync results.
     */
    public function sync_inventory() {
        if (!$this->api->is_configured()) {
            $this->logger->error('Inventory sync failed: API not configured');
            return array(
                'success' => false,
                'message' => __('API not configured', 'woo-moysklad-integration'),
            );
        }
        
        $this->logger->info('Starting inventory synchronization');
        
        // Get warehouse ID
        $warehouse_id = get_option('woo_moysklad_inventory_warehouse_id', '');
        
        // Get product mapping
        global $wpdb;
        $table_name = $wpdb->prefix . 'woo_moysklad_product_mapping';
        $product_mapping = $wpdb->get_results("SELECT woo_product_id, ms_product_id FROM $table_name");
        
        if (empty($product_mapping)) {
            $this->logger->info('No products to sync inventory for');
            return array(
                'success' => true,
                'message' => __('No products to sync inventory for', 'woo-moysklad-integration'),
                'stats' => array(
                    'updated' => 0,
                    'failed' => 0,
                ),
            );
        }
        
        // Process in batches of 50 products
        $stats = array(
            'updated' => 0,
            'failed' => 0,
        );
        
        $product_chunks = array_chunk($product_mapping, 50);
        
        foreach ($product_chunks as $chunk) {
            // Проверяем флаг остановки синхронизации
            if (get_option('woo_moysklad_sync_stopped_by_user', '0') === '1') {
                $this->logger->info('Синхронизация остатков остановлена пользователем');
                // Сбрасываем флаг остановки
                update_option('woo_moysklad_sync_stopped_by_user', '0');
                break;
            }
            
            // Get MoySklad product IDs in this chunk
            $ms_product_ids = array_map(function($item) {
                return $item->ms_product_id;
            }, $chunk);
            
            // Get stock for these products
            $stock_response = $this->api->get_stock_batch($ms_product_ids, $warehouse_id);
            
            if (is_wp_error($stock_response)) {
                $this->logger->error('Failed to get stock: ' . $stock_response->get_error_message());
                $stats['failed'] += count($chunk);
                continue;
            }
            
            if (!isset($stock_response['rows']) || empty($stock_response['rows'])) {
                $this->logger->debug('No stock information returned for batch');
                continue;
            }
            
            // Process stock information
            $stock_data = array();
            foreach ($stock_response['rows'] as $stock_item) {
                if (!isset($stock_item['assortment']) || !isset($stock_item['assortment']['meta']) || !isset($stock_item['assortment']['meta']['href'])) {
                    continue;
                }
                
                // Extract product ID from href
                $ms_product_id = '';
                if (preg_match('/product\/([^\/]+)/', $stock_item['assortment']['meta']['href'], $matches)) {
                    $ms_product_id = $matches[1];
                } elseif (preg_match('/variant\/([^\/]+)/', $stock_item['assortment']['meta']['href'], $matches)) {
                    $ms_product_id = 'variant_' . $matches[1];
                }
                
                if (empty($ms_product_id)) {
                    continue;
                }
                
                // Store stock level
                $stock_data[$ms_product_id] = array(
                    'stock' => isset($stock_item['stock']) ? (int)$stock_item['stock'] : 0,
                    'reserve' => isset($stock_item['reserve']) ? (int)$stock_item['reserve'] : 0,
                );
            }
            
            // Update WooCommerce products
            foreach ($chunk as $mapping) {
                // Дополнительная проверка на остановку внутри цикла обработки
                if (get_option('woo_moysklad_sync_stopped_by_user', '0') === '1') {
                    $this->logger->info('Синхронизация остатков остановлена пользователем в процессе обновления товаров');
                    update_option('woo_moysklad_sync_stopped_by_user', '0');
                    break 2; // Выходим из обоих циклов
                }
                
                $woo_product_id = $mapping->woo_product_id;
                $ms_product_id = $mapping->ms_product_id;
                
                if (!isset($stock_data[$ms_product_id])) {
                    // Check if this is a variable product
                    $product = wc_get_product($woo_product_id);
                    
                    if (!$product || !$product->is_type('variable')) {
                        $this->logger->debug("No stock information for product: $ms_product_id");
                        continue;
                    }
                    
                    // For variable products, update variations instead
                    $this->update_variation_stock($product, $stock_data);
                    $stats['updated']++;
                } else {
                    // Update simple product stock
                    $result = $this->update_product_stock($woo_product_id, $stock_data[$ms_product_id]);
                    
                    if ($result) {
                        $stats['updated']++;
                    } else {
                        $stats['failed']++;
                    }
                }
            }
        }
        
        $this->logger->info('Inventory synchronization completed', $stats);
        
        update_option('woo_moysklad_last_inventory_sync_time', current_time('mysql'));
        
        return array(
            'success' => true,
            'message' => sprintf(
                __('Inventory synchronization completed: %d updated, %d failed', 'woo-moysklad-integration'),
                $stats['updated'],
                $stats['failed']
            ),
            'stats' => $stats,
        );
    }
    
    /**
     * Update product stock level.
     *
     * @since    1.0.0
     * @param    int       $product_id    The WooCommerce product ID.
     * @param    array     $stock_data    The stock data.
     * @return   bool                     Whether the update was successful.
     */
    private function update_product_stock($product_id, $stock_data) {
        try {
            $product = wc_get_product($product_id);
            
            if (!$product) {
                throw new Exception("Product not found: $product_id");
            }
            
            // Calculate available stock
            $available_stock = $stock_data['stock'] - $stock_data['reserve'];
            $available_stock = max(0, $available_stock);
            
            // Update stock
            $product->set_stock_quantity($available_stock);
            
            // Set stock status
            if ($available_stock > 0) {
                $product->set_stock_status('instock');
            } else {
                $product->set_stock_status('outofstock');
            }
            
            // Save product
            $product->save();
            
            $this->logger->debug("Updated product stock: $product_id to $available_stock units");
            
            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to update product stock: ' . $e->getMessage(), array(
                'product_id' => $product_id,
                'stock_data' => $stock_data
            ));
            return false;
        }
    }
    
    /**
     * Update variation stock levels.
     *
     * @since    1.0.0
     * @param    WC_Product_Variable    $product      The variable product.
     * @param    array                  $stock_data   The stock data.
     * @return   bool                                 Whether the update was successful.
     */
    private function update_variation_stock($product, $stock_data) {
        try {
            $variations = $product->get_children();
            $any_in_stock = false;
            
            foreach ($variations as $variation_id) {
                $ms_variant_id = get_post_meta($variation_id, '_ms_variant_id', true);
                
                if (!$ms_variant_id || !isset($stock_data['variant_' . $ms_variant_id])) {
                    continue;
                }
                
                $variation = wc_get_product($variation_id);
                
                if (!$variation) {
                    continue;
                }
                
                // Calculate available stock
                $variant_stock = $stock_data['variant_' . $ms_variant_id];
                $available_stock = $variant_stock['stock'] - $variant_stock['reserve'];
                $available_stock = max(0, $available_stock);
                
                // Update stock
                $variation->set_stock_quantity($available_stock);
                
                // Set stock status
                if ($available_stock > 0) {
                    $variation->set_stock_status('instock');
                    $any_in_stock = true;
                } else {
                    $variation->set_stock_status('outofstock');
                }
                
                // Save variation
                $variation->save();
                
                $this->logger->debug("Updated variation stock: $variation_id to $available_stock units");
            }
            
            // Update parent product stock status
            if ($any_in_stock) {
                $product->set_stock_status('instock');
            } else {
                $product->set_stock_status('outofstock');
            }
            
            $product->save();
            
            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to update variation stock: ' . $e->getMessage(), array(
                'product_id' => $product->get_id()
            ));
            return false;
        }
    }
}
