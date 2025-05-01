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
        
        $this->logger->info("Начало подготовки данных заказа #{$order->get_id()}");
        
        $order_prefix = get_option('woo_moysklad_order_prefix', 'WC-');
        $organization_id = get_option('woo_moysklad_order_organization_id', '');
        $warehouse_id = get_option('woo_moysklad_order_warehouse_id', '');
        
        $this->logger->debug("Настройки заказа: префикс='$order_prefix', организация='$organization_id', склад='$warehouse_id'");
        
        // Prepare customer data
        $this->logger->info("Подготовка данных клиента для заказа #{$order->get_id()}");
        $customer_data = $this->prepare_customer_data($order);
        $this->logger->debug("Данные клиента", array('customer_data' => $customer_data));
        
        // Находим или создаем клиента в МойСклад
        $this->logger->info("Поиск или создание клиента в МойСклад");
        $customer = $this->api->find_or_create_customer($customer_data);
        
        if (is_wp_error($customer)) {
            $this->logger->error('Ошибка поиска/создания клиента: ' . $customer->get_error_message());
            throw new Exception('Ошибка поиска/создания клиента: ' . $customer->get_error_message());
        }
        
        $this->logger->info("Клиент успешно найден/создан в МойСклад", array('customer_id' => isset($customer['id']) ? $customer['id'] : 'unknown'));
        
        // Prepare order items
        $this->logger->info("Подготовка позиций заказа #{$order->get_id()}");
        $positions = array();
        $items = $order->get_items();
        
        if (empty($items)) {
            $this->logger->warning("Заказ #{$order->get_id()} не содержит товаров");
        }
        
        $this->logger->debug("Количество позиций в заказе: " . count($items));
        
        foreach ($items as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $target_id = $variation_id ? $variation_id : $product_id;
            $product_name = $item->get_name();
            
            $this->logger->debug("Обработка товара: $product_name (ID: $target_id)");
            
            // Get MoySklad product ID
            global $wpdb;
            $table_name = $wpdb->prefix . 'woo_moysklad_product_mapping';
            
            // Проверка существования таблицы маппинга
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            
            if (!$table_exists) {
                $this->logger->error("Таблица маппинга товаров $table_name не существует");
                continue;
            }
            
            $ms_product_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ms_product_id FROM $table_name WHERE woo_product_id = %d",
                $target_id
            ));
            
            $this->logger->debug("Поиск товара $target_id в маппинге: " . ($ms_product_id ? $ms_product_id : 'не найден'));
            
            if (!$ms_product_id) {
                // Try parent product if variation not found
                if ($variation_id) {
                    $this->logger->debug("Попытка найти родительский товар $product_id для вариации");
                    $ms_product_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT ms_product_id FROM $table_name WHERE woo_product_id = %d",
                        $product_id
                    ));
                    $this->logger->debug("Результат поиска родительского товара: " . ($ms_product_id ? $ms_product_id : 'не найден'));
                }
                
                if (!$ms_product_id) {
                    $this->logger->warning("Товар не найден в МойСклад: $product_name (ID: $target_id). Пробуем создать автоматически.");
                    
                    // Автоматически создаем товар в МойСклад
                    try {
                        $product = $item->get_product();
                        $price = 0;
                        
                        try {
                            $price = wc_get_price_excluding_tax($product);
                        } catch (Exception $e) {
                            $price = $item->get_total() / $item->get_quantity();
                        }
                        
                        $sku = $product ? $product->get_sku() : '';
                        $description = $product ? $product->get_description() : '';
                        
                        $new_product = $this->api->create_simple_product($product_name, $price, $sku, $description);
                        
                        if (is_wp_error($new_product)) {
                            $this->logger->error("Не удалось создать товар: " . $new_product->get_error_message());
                            
                            // Создаем просто товар в МойСклад без ценовых характеристик
                            try {
                                $simple_product_data = array(
                                    'name' => $product_name,
                                    'code' => $sku
                                );
                                
                                $this->logger->info("Пробуем создать упрощенный товар без цены");
                                $simple_response = $this->api->request('/entity/product', 'POST', $simple_product_data);
                                
                                if (is_wp_error($simple_response)) {
                                    $this->logger->error("Не удалось создать упрощенный товар: " . $simple_response->get_error_message());
                                    continue;
                                }
                                
                                $ms_product_id = $simple_response['id'];
                                $this->logger->info("Упрощенный товар успешно создан в МойСклад с ID: $ms_product_id");
                                
                                // Сохраняем соответствие в таблицу маппинга
                                global $wpdb;
                                $wpdb->insert(
                                    $table_name,
                                    array(
                                        'ms_product_id' => $ms_product_id,
                                        'woo_product_id' => $target_id,
                                        'name' => $product_name
                                    )
                                );
                                
                                $this->logger->info("Соответствие упрощенного товара добавлено в таблицу маппинга");
                            } catch (Exception $inner_e) {
                                $this->logger->error("Ошибка при создании упрощенного товара: " . $inner_e->getMessage());
                                continue;
                            }
                        }
                        
                        $ms_product_id = $new_product['id'];
                        $this->logger->info("Товар успешно создан в МойСклад с ID: $ms_product_id");
                        
                        // Сохраняем соответствие в таблицу маппинга
                        global $wpdb;
                        $wpdb->insert(
                            $table_name,
                            array(
                                'ms_product_id' => $ms_product_id,
                                'woo_product_id' => $target_id,
                                'name' => $product_name
                            )
                        );
                        
                        $this->logger->info("Соответствие товара добавлено в таблицу маппинга");
                    } catch (Exception $e) {
                        $this->logger->error("Ошибка при автоматическом создании товара: " . $e->getMessage());
                        // Если товар не удалось создать, пропускаем его и продолжаем с другими товарами
                        continue;
                    }
                }
            }
            
            // For variations, we need to get the variant ID
            if ($variation_id) {
                $ms_variant_id = get_post_meta($variation_id, '_ms_variant_id', true);
                
                if ($ms_variant_id) {
                    $assortment_url = "/entity/variant/$ms_variant_id";
                    $this->logger->debug("Используем вариант товара: $ms_variant_id");
                } else {
                    $assortment_url = "/entity/product/$ms_product_id";
                    $this->logger->debug("Вариант не найден, используем основной товар: $ms_product_id");
                }
            } else {
                $assortment_url = "/entity/product/$ms_product_id";
                $this->logger->debug("Используем основной товар: $ms_product_id");
            }
            
            $quantity = $item->get_quantity();
            $price = 0;
            
            try {
                $price = wc_get_price_excluding_tax($item->get_product()) * 100; // MoySklad uses kopecks
            } catch (Exception $e) {
                $this->logger->warning("Ошибка получения цены товара: " . $e->getMessage());
                $price = $item->get_total() / $item->get_quantity() * 100;
            }
            
            $this->logger->debug("Добавление позиции: $product_name, количество: $quantity, цена: $price");
            
            $position = array(
                'quantity' => $quantity,
                'price' => $price,
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
            
            $positions[] = $position;
        }
        
        $this->logger->info("Подготовлено " . count($positions) . " позиций для заказа #{$order->get_id()}");
        
        // Если нет ни одной позиции, не создаем заказ
        if (empty($positions)) {
            $this->logger->error("Невозможно создать заказ без позиций для заказа #{$order->get_id()}");
            throw new Exception('Невозможно создать заказ без позиций');
        }
        
        // Подготовка данных заказа
        $this->logger->info("Формирование данных заказа для отправки в МойСклад");
        
        $order_number = $order->get_order_number();
        $order_name = $order_prefix . $order_number;
        $external_code = (string)$order->get_id();
        $moment = gmdate('Y-m-d H:i:s', strtotime($order->get_date_created()));
        
        $description = sprintf(
            __('WooCommerce Order #%s from %s', 'woo-moysklad-integration'),
            $order_number,
            $order->get_date_created()->date_i18n(get_option('date_format') . ' ' . get_option('time_format'))
        );
        
        $this->logger->debug("Основные данные заказа: имя=$order_name, внешний код=$external_code, дата=$moment");
        
        $order_data = array(
            'name' => $order_name,
            'externalCode' => (string)$external_code,
            'moment' => $moment,
            'description' => $description,
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
        
        // Обрабатываем externalCode как строку для совместимости с МойСклад
        $external_code = '';
        if ($order->get_customer_id()) {
            $external_code = strval($order->get_customer_id());
        } else {
            $external_code = 'guest_' . strval($order->get_id());
        }
        
        $this->logger->debug("Значение externalCode для клиента: $external_code");
        
        // Подготавливаем адрес в читаемом формате
        $address_parts = array();
        
        if (!empty($order->get_billing_address_1())) {
            $address_parts[] = $order->get_billing_address_1();
        }
        if (!empty($order->get_billing_address_2())) {
            $address_parts[] = $order->get_billing_address_2();
        }
        if (!empty($order->get_billing_city())) {
            $address_parts[] = $order->get_billing_city();
        }
        if (!empty($order->get_billing_state())) {
            $address_parts[] = $order->get_billing_state();
        }
        if (!empty($order->get_billing_postcode())) {
            $address_parts[] = $order->get_billing_postcode();
        }
        if (!empty($order->get_billing_country())) {
            $address_parts[] = $order->get_billing_country();
        }
        
        $actual_address = implode(', ', $address_parts);
        
        $customer_data = array(
            'name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'externalCode' => (string)$external_code,
            'email' => $email,
            'phone' => $phone,
            'description' => sprintf(
                __('WooCommerce customer created from order #%s', 'woo-moysklad-integration'),
                $order->get_order_number()
            ),
            'actualAddress' => $actual_address
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
