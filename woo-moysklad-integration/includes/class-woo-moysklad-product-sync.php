<?php
/**
 * Product Synchronization
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */

/**
 * Product Synchronization class.
 *
 * This class handles synchronization of products from MoySklad to WooCommerce.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */
class Woo_Moysklad_Product_Sync {

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
     * @param    Woo_Moysklad_Logger    $logger    Logger instance.
     */
    public function __construct($api, $logger) {
        $this->api = $api;
        $this->logger = $logger;
    }
    
    /**
     * Synchronize products from MoySklad to WooCommerce.
     *
     * @since    1.0.0
     * @return   array    Sync results.
     */
    public function sync_products() {
        if (!$this->api->is_configured()) {
            $this->logger->error('Product sync failed: API not configured');
            return array(
                'success' => false,
                'message' => __('API not configured', 'woo-moysklad-integration'),
            );
        }
        
        $sync_enabled = get_option('woo_moysklad_sync_enabled', '0');
        if ($sync_enabled !== '1') {
            $this->logger->info('Product sync is disabled in settings');
            return array(
                'success' => false,
                'message' => __('Product synchronization is disabled', 'woo-moysklad-integration'),
            );
        }
        
        $this->logger->info('Starting product synchronization');
        
        // Set sync status flag
        update_option('woo_moysklad_sync_in_progress', '1');
        
        // Sync mode
        $sync_mode = get_option('woo_moysklad_sync_mode', 'standard');
        
        // Sync categories first
        $category_sync = new Woo_Moysklad_Category_Sync($this->api, $this->logger);
        $category_sync->sync_categories();
        
        // Sync products
        $stats = array(
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
        );
        
        try {
            $start_time = microtime(true);
            
            if ($sync_mode === 'accelerated') {
                $this->sync_products_accelerated($stats);
            } else {
                $this->sync_products_standard($stats);
            }
            
            $end_time = microtime(true);
            $execution_time = round($end_time - $start_time, 2);
            
            $this->logger->info('Синхронизация товаров завершена', array_merge($stats, array('execution_time' => $execution_time)));
            
            // Clear sync status flag
            update_option('woo_moysklad_sync_in_progress', '0');
            update_option('woo_moysklad_last_sync_time', current_time('mysql'));
            
            $diagnostic_info = '';
            if ($stats['failed'] > 0) {
                $diagnostic_info = __('Проверьте лог ошибок для получения подробной информации о проблемах синхронизации.', 'woo-moysklad-integration');
            }
            
            return array(
                'success' => true,
                'message' => sprintf(
                    __('Синхронизация завершена: %d создано, %d обновлено, %d не удалось, %d пропущено (время выполнения: %s сек.)', 'woo-moysklad-integration'),
                    $stats['created'],
                    $stats['updated'],
                    $stats['failed'],
                    $stats['skipped'],
                    $execution_time
                ) . (!empty($diagnostic_info) ? ' ' . $diagnostic_info : ''),
                'stats' => $stats,
                'execution_time' => $execution_time
            );
        } catch (Exception $e) {
            $this->logger->error('Ошибка синхронизации товаров: ' . $e->getMessage());
            
            // Clear sync status flag
            update_option('woo_moysklad_sync_in_progress', '0');
            
            // Добавление диагностической информации
            $diagnostic_info = '';
            if (is_wp_error($e) && $e->get_error_data()) {
                $error_data = $e->get_error_data();
                if (isset($error_data['response_code'])) {
                    $diagnostic_info = sprintf(
                        __('Код ответа: %d. ', 'woo-moysklad-integration'),
                        $error_data['response_code']
                    );
                }
            }
            
            return array(
                'success' => false,
                'message' => $e->getMessage() . (!empty($diagnostic_info) ? ' ' . $diagnostic_info : ''),
                'stats' => $stats,
            );
        }
    }
    
    /**
     * Standard synchronization mode (batch processing).
     *
     * @since    1.0.0
     * @param    array    &$stats    Synchronization statistics.
     */
    private function sync_products_standard(&$stats) {
        // Начинаем подробную запись процесса для отладки
        $this->logger->info("Запуск стандартной синхронизации товаров с МойСклад");
        
        $limit = 10; // Уменьшаем размер пакета до 10 для поэтапной синхронизации и снижения нагрузки
        $offset = 0;
        $total_count = 0;
        $max_retries = 3; // Максимальное количество повторных попыток при ошибках
        
        // Завершаем существующие процессы и обнуляем текущий прогресс
        update_option('woo_moysklad_sync_stopped_by_user', '0');
        
        try {
            do {
                // Добавляем задержку между пакетами запросов для предотвращения превышения лимитов API
                if ($offset > 0) {
                    sleep(1); // Задержка 1 секунда между пакетами
                    $this->logger->info("Переход к следующему пакету товаров: смещение $offset");
                }
                
                // Get products from MoySklad with retries
                $retry_count = 0;
                $response = null;
                $success = false;
                
                $this->logger->info("Запрос товаров из МойСклад: лимит=$limit, смещение=$offset");
                
                while (!$success && $retry_count < $max_retries) {
                    if ($retry_count > 0) {
                        $this->logger->warning("Повторная попытка #$retry_count получения товаров");
                        sleep(2 * $retry_count); // Увеличиваем задержку с каждой попыткой
                    }
                    
                    $response = $this->api->get_products($limit, $offset);
                    
                    if (!is_wp_error($response)) {
                        $success = true;
                        $this->logger->debug("Успешно получены данные от API МойСклад");
                    } else {
                        $retry_count++;
                        $error_message = $response->get_error_message();
                        $this->logger->warning("Ошибка при получении товаров: $error_message. Попытка $retry_count из $max_retries");
                        
                        // Если превышен лимит API, делаем более длительную паузу
                        if (strpos($error_message, '1049') !== false) {
                            $this->logger->warning('Превышен лимит запросов API, делаем дополнительную паузу');
                            sleep(5); // Длительная пауза при ошибке лимита
                        }
                    }
                }
                
                // Если после всех попыток не удалось получить данные, пишем в лог и продолжаем
                if (!$success) {
                    $this->logger->error("Не удалось получить товары после $max_retries попыток: " . $response->get_error_message());
                    // Продолжаем со следующим пакетом вместо остановки всего процесса
                    $offset += $limit;
                    continue;
                }
                
                // Проверяем наличие необходимых данных в ответе
                if (!isset($response['rows']) || !isset($response['meta']['size'])) {
                    $this->logger->error("Неверный формат ответа от API МойСклад", array('response' => $response));
                    // Продолжаем со следующим пакетом вместо остановки всего процесса
                    $offset += $limit;
                    continue;
                }
                
                $products = $response['rows'];
                $total_count = $response['meta']['size'];
                
                $this->logger->info("Успешно получены товары из МойСклад: " . count($products) . " из $total_count");
                $this->logger->info("Обработка пакета товаров: $offset - " . ($offset + count($products)) . " из $total_count");
                
                // Проверяем, что получен непустой массив товаров
                if (empty($products)) {
                    $this->logger->warning("Получен пустой массив товаров, возможно достигнут конец списка или проблема с API");
                    break; // Выходим из цикла, если нет товаров
                }
                
                // Process each product
                $product_count = 0;
                $total_products = count($products);
                $this->logger->info("Начинаем обработку $total_products товаров в текущем пакете");
                
                foreach ($products as $index => $product) {
                    // Прерываем синхронизацию, если пользователь запросил остановку
                    if (get_option('woo_moysklad_sync_stopped_by_user', '0') === '1') {
                        $this->logger->info('Синхронизация товаров остановлена пользователем');
                        update_option('woo_moysklad_sync_stopped_by_user', '0');
                        return;
                    }
                    
                    $product_count++;
                    
                    // Добавляем небольшую задержку между обработкой товаров
                    if ($product_count > 1) {
                        usleep(200000); // 200ms задержка между каждым товаром
                    }
                    
                    try {
                        $this->logger->info("Обработка товара #{$product_count} из {$total_products} в текущем пакете: {$product['name']} (#{$index})");
                        
                        // Обрабатываем товар и проверяем результат
                        $result = $this->process_product($product);
                        $this->logger->info("Результат обработки товара {$product['name']}: $result");
                        
                        if ($result === 'created') {
                            $stats['created']++;
                            $this->logger->info("Товар успешно создан: {$product['name']}");
                        } elseif ($result === 'updated') {
                            $stats['updated']++;
                            $this->logger->info("Товар успешно обновлен: {$product['name']}");
                        } elseif ($result === 'skipped') {
                            $stats['skipped']++;
                            $this->logger->debug("Товар пропущен: {$product['name']}");
                        } else {
                            $stats['failed']++;
                            $this->logger->warning("Ошибка при обработке товара: {$product['name']}");
                        }
                        
                        // Сохраняем прогресс после каждого товара
                        update_option('woo_moysklad_sync_progress', json_encode(array(
                            'processed' => $offset + $product_count,
                            'total' => $total_count,
                            'current_product' => $product['name'],
                            'stats' => $stats
                        )));
                        
                        $this->logger->info("Завершена обработка товара #{$product_count}: {$product['name']}");
                        
                    } catch (Exception $e) {
                        // Ловим исключения при обработке отдельных товаров, чтобы не останавливать весь процесс
                        $error_details = [
                            'product_name' => isset($product['name']) ? $product['name'] : 'Unknown',
                            'product_id' => isset($product['id']) ? $product['id'] : 'Unknown',
                            'error_message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'product_index' => $index,
                            'product_count' => $product_count,
                            'total_products' => $total_products
                        ];
                        
                        $this->logger->error("Критическая ошибка при обработке товара {$product['name']}: " . $e->getMessage(), $error_details);
                        $stats['failed']++;
                        
                        // Запись детальной информации для диагностики
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('WooMoySklad - Ошибка обработки товара: ' . $e->getMessage());
                        }
                    }
                    
                    // Дополнительная проверка для отладки
                    // Добавляем еще больше мер для предотвращения застревания
                    if ($product_count % 5 === 0) {
                        $this->logger->info("Обработано $product_count из $total_products товаров в текущем пакете");
                        sleep(1); // Делаем паузу после каждых 5 товаров
                    }
                }
                
                // Записываем детальную статистику для каждого пакета
                $this->logger->info("Завершена обработка пакета товаров: $offset - " . ($offset + count($products)) . " из $total_count");
                $this->logger->info("Статистика обработки: создано: {$stats['created']}, обновлено: {$stats['updated']}, пропущено: {$stats['skipped']}, ошибок: {$stats['failed']}");
                
                // Инкрементируем смещение для следующего пакета
                $offset += $limit;
                
                // Сохраняем промежуточные результаты
                update_option('woo_moysklad_sync_progress', json_encode(array(
                    'processed' => $offset,
                    'total' => $total_count,
                    'stats' => $stats
                )));
                
            } while ($offset < $total_count);
            
            $this->logger->info("Стандартная синхронизация завершена. Всего обработано: $offset из $total_count");
            
        } catch (Exception $e) {
            $this->logger->error("Критическая ошибка при синхронизации товаров: " . $e->getMessage());
            // Сбрасываем флаг синхронизации
            update_option('woo_moysklad_sync_in_progress', '0');
            throw $e; // Пробрасываем исключение дальше для обработки в основной функции
        }
    }
    
    /**
     * Accelerated synchronization mode (all at once).
     *
     * @since    1.0.0
     * @param    array    &$stats    Synchronization statistics.
     */
    private function sync_products_accelerated(&$stats) {
        // Используем метод get_products из API с лимитом 500
        $batch_size = 500;
        $offset = 0;
        $continue = true;
        $api_delay = 1; // 1 секунда базовая задержка между запросами
        $retry_delay = 2; // 2 секунды начальная задержка между повторными попытками
        $max_retry_attempts = 3;
        
        $this->logger->info("Запуск ускоренной синхронизации с МойСклад с размером пакета $batch_size товаров");
        
        while ($continue && get_option('woo_moysklad_sync_stopped_by_user', '0') !== '1') {
            $retry_count = 0;
            $success = false;
            $current_retry_delay = $retry_delay;
            
            while (!$success && $retry_count < $max_retry_attempts) {
                // Добавляем задержку между попытками
                if ($retry_count > 0) {
                    $this->logger->info("Повторная попытка №{$retry_count} получения товаров с задержкой {$current_retry_delay}с");
                    sleep($current_retry_delay);
                    // Увеличиваем задержку экспоненциально
                    $current_retry_delay *= 2;
                }
                
                // Используем метод get_products вместо прямого запроса к API
                $response = $this->api->get_products($batch_size, $offset);
                
                if (!is_wp_error($response)) {
                    $success = true;
                } else {
                    $retry_count++;
                    $error_message = $response->get_error_message();
                    $this->logger->warning("Попытка {$retry_count}/{$max_retry_attempts} не удалась: {$error_message}");
                    
                    // Если это ошибка с лимитом API, увеличиваем задержку значительно
                    if (strpos($error_message, '1049') !== false) {
                        $current_retry_delay = max($current_retry_delay, 5); // Минимум 5 секунд при ошибке лимита
                    }
                }
            }
            
            if (!$success) {
                $this->logger->error('Не удалось получить товары после нескольких попыток. Переключаемся на стандартный режим синхронизации.');
                // Делаем более длительную паузу перед переключением
                sleep(5);
                return $this->sync_products_standard($stats);
            }
            
            // Проверяем наличие необходимых данных в ответе
            if (!isset($response['rows']) || !isset($response['meta']['size'])) {
                $this->logger->error("Неверный формат ответа от API МойСклад", array('response' => $response));
                throw new Exception("Неверный формат ответа от API МойСклад");
            }
            
            if (empty($response['rows'])) {
                $this->logger->info('Получен пустой список товаров, завершаем синхронизацию');
                $continue = false;
                break;
            }
            
            // Получаем товары из ответа
            $products = $response['rows'];
            $total_count = isset($response['meta']['size']) ? $response['meta']['size'] : count($products);
            
            $this->logger->info("Получено {$total_count} товаров всего, обрабатываем пакет из " . count($products) . " товаров (смещение: $offset)");
            
            // Обрабатываем товары
            $product_count = 0;
            foreach ($products as $product) {
                // Проверяем флаг остановки синхронизации
                if (get_option('woo_moysklad_sync_stopped_by_user', '0') === '1') {
                    $this->logger->info('Синхронизация товаров остановлена пользователем после обработки ' . $product_count . ' из ' . count($products) . ' товаров в текущем пакете');
                    // Сбрасываем флаг остановки
                    update_option('woo_moysklad_sync_stopped_by_user', '0');
                    $continue = false;
                    break;
                }
                
                $product_count++;
                
                // Динамическая задержка на основе обработанного количества
                // Добавляем небольшую задержку через каждые 5 товаров
                if ($product_count > 1 && $product_count % 5 === 0) {
                    usleep(200000); // 200ms задержка
                }
                
                // Более длительная задержка через каждые 50 товаров
                if ($product_count > 1 && $product_count % 50 === 0) {
                    $this->logger->info("Обработано $product_count из " . count($products) . " товаров в текущем пакете, делаем паузу");
                    usleep(500000); // 500ms задержка
                }
                
                try {
                    $result = $this->process_product($product);
                    
                    if ($result === 'created') {
                        $stats['created']++;
                    } elseif ($result === 'updated') {
                        $stats['updated']++;
                    } elseif ($result === 'skipped') {
                        $stats['skipped']++;
                    } else {
                        $stats['failed']++;
                    }
                } catch (Exception $e) {
                    // Ловим исключения при обработке отдельных товаров, чтобы не останавливать весь процесс
                    $this->logger->error("Ошибка при обработке товара {$product['name']}: " . $e->getMessage());
                    $stats['failed']++;
                }
            }
            
            // Если мы обработали все доступные товары в текущем пакете, но есть еще товары
            if (count($products) > 0 && isset($response['meta']['size']) && $response['meta']['size'] > ($offset + count($products))) {
                $offset += count($products);
                // Делаем паузу между запросами пакетов для снижения нагрузки на API
                $this->logger->info("Пауза между пакетами товаров (обработано $offset из {$response['meta']['size']})");
                sleep($api_delay);
            } else {
                // Все товары обработаны
                $continue = false;
            }
        }
    }
    
    /**
     * Process a single product.
     *
     * @since    1.0.0
     * @param    array     $product    The product data from MoySklad.
     * @return   string                Result status ('created', 'updated', 'skipped', or 'failed').
     */
    public function process_product($product) {
        global $wpdb;
        
        // Добавляем дополнительное логирование для отладки
        $this->logger->info("Начало обработки товара: {$product['name']}, ID: {$product['id']}");
        
        try {
            // Skip service products
            if (isset($product['attributes']) && is_array($product['attributes'])) {
                foreach ($product['attributes'] as $attr) {
                    if ($attr['name'] === 'Тип номенклатуры' && $attr['value'] === 'Услуга') {
                        $this->logger->debug('Skipping service product: ' . $product['name']);
                        return 'skipped';
                    }
                }
            }
            
            // Проверка существования таблицы маппинга
            $mapping_table = $wpdb->prefix . 'woo_moysklad_product_mapping';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$mapping_table'") === $mapping_table;
            
            if (!$table_exists) {
                $this->logger->warning("Таблица маппинга $mapping_table не существует. Попытка создания таблицы.");
                
                // Создаем таблицу маппинга
                $charset_collate = $wpdb->get_charset_collate();
                
                $sql = "CREATE TABLE $mapping_table (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    woo_product_id bigint(20) NOT NULL,
                    ms_product_id varchar(255) NOT NULL,
                    ms_product_meta longtext NOT NULL,
                    last_updated datetime NOT NULL,
                    PRIMARY KEY  (id),
                    KEY woo_product_id (woo_product_id),
                    KEY ms_product_id (ms_product_id)
                ) $charset_collate;";
                
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);
                
                // Проверяем, создалась ли таблица
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$mapping_table'") === $mapping_table;
                
                if ($table_exists) {
                    $this->logger->info("Таблица маппинга $mapping_table успешно создана");
                } else {
                    $this->logger->error("Не удалось создать таблицу маппинга $mapping_table");
                }
                
                $woo_product_id = null;
            } else {
                // Check if product already exists by ID
                $woo_product_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT woo_product_id FROM $mapping_table WHERE ms_product_id = %s",
                    $product['id']
                ));
                $this->logger->debug("Поиск товара по МойСклад ID: {$product['id']}, найдено: " . ($woo_product_id ? $woo_product_id : 'не найден'));
            }
            
            // Подготовка данных товара
            $this->logger->debug("Запуск подготовки данных товара: {$product['name']}");
            $product_data = $this->prepare_product_data($product);
            $this->logger->debug("Подготовлены данные товара: {$product['name']}");
            
            // Если товар не найден по ID, попробуем найти его по SKU
            if (!$woo_product_id && !empty($product['code'])) {
                $this->logger->debug("Поиск товара по SKU: {$product['code']}");
                $product_id_by_sku = wc_get_product_id_by_sku($product['code']);
                
                if ($product_id_by_sku) {
                    $this->logger->info("Продукт не найден по ID {$product['id']}, но найден по SKU: {$product['code']}, ID: {$product_id_by_sku}");
                    
                    // Сохраняем соответствие для последующих синхронизаций
                    $this->logger->debug("Сохранение маппинга для товара найденного по SKU");
                    $mapping_result = $this->save_product_mapping($product_id_by_sku, $product['id'], json_encode($product));
                    $this->logger->debug("Результат сохранения маппинга: " . ($mapping_result ? 'успешно' : 'ошибка'));
                    
                    $woo_product_id = $product_id_by_sku;
                }
            }
            
            $result = '';
            if ($woo_product_id) {
                // Update existing product
                $this->logger->info("Обновление существующего товара: {$product['name']}, WooCommerce ID: {$woo_product_id}");
                $result = $this->update_woo_product($woo_product_id, $product_data, $product);
                $this->logger->debug("Результат обновления товара: " . ($result ? 'успешно' : 'ошибка'));
                $this->logger->info("Завершена обработка товара: {$product['name']}, результат: " . ($result ? 'updated' : 'failed'));
                return $result ? 'updated' : 'failed';
            } else {
                // Create new product
                $this->logger->info("Создание нового товара: {$product['name']}");
                $result = $this->create_woo_product($product_data, $product);
                $this->logger->debug("Результат создания товара: " . ($result ? 'успешно, ID: ' . $result : 'ошибка'));
                $this->logger->info("Завершена обработка товара: {$product['name']}, результат: " . ($result ? 'created' : 'failed'));
                return $result ? 'created' : 'failed';
            }
        } catch (Exception $e) {
            $error_details = array(
                'product_name' => isset($product['name']) ? $product['name'] : 'Unknown',
                'product_id' => isset($product['id']) ? $product['id'] : 'Unknown',
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            );
            
            $this->logger->error("Критическая ошибка при обработке товара {$product['name']}: " . $e->getMessage(), $error_details);
            
            // Запись детальной информации для диагностики
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WooMoySklad - Ошибка обработки товара: ' . $e->getMessage() . ' - ' . json_encode($error_details));
            }
            
            return 'failed';
        }
    }
    
    /**
     * Prepare product data for WooCommerce.
     *
     * @since    1.0.0
     * @param    array     $product    The product data from MoySklad.
     * @return   array                 The prepared product data.
     */
    private function prepare_product_data($product) {
        $sync_product_description = get_option('woo_moysklad_sync_product_description', '1');
        $selected_price_type_id = get_option('woo_moysklad_price_type', '');
        
        $data = array(
            'name' => $product['name'],
            'status' => 'publish',
            'catalog_visibility' => 'visible',
            'sku' => isset($product['code']) ? $product['code'] : '',
        );
        
        // Установка цены товара
        if (isset($product['salePrices']) && is_array($product['salePrices'])) {
            // По умолчанию берем первую цену
            $regular_price = isset($product['salePrices'][0]['value']) ? ($product['salePrices'][0]['value'] / 100) : '';
            
            // Если выбран определенный тип цены, найдем его
            if (!empty($selected_price_type_id)) {
                foreach ($product['salePrices'] as $salePrice) {
                    if (isset($salePrice['priceType']) && isset($salePrice['priceType']['id']) && 
                        $salePrice['priceType']['id'] === $selected_price_type_id) {
                        $regular_price = $salePrice['value'] / 100;
                        break;
                    }
                }
            }
            
            $data['regular_price'] = $regular_price;
        } else {
            $data['regular_price'] = '';
        }
        
        // Description
        if ($sync_product_description === '1' && isset($product['description'])) {
            $data['description'] = $product['description'];
        }
        
        // Category
        if (isset($product['productFolder']) && isset($product['productFolder']['meta'])) {
            $category_id = $this->get_woo_category_id_by_ms_id($product['productFolder']['meta']['href']);
            if ($category_id) {
                $data['categories'] = array($category_id);
            }
        }
        
        // Custom attributes
        if (isset($product['attributes']) && is_array($product['attributes'])) {
            $data['attributes'] = array();
            
            foreach ($product['attributes'] as $attribute) {
                if (empty($attribute['value'])) {
                    continue;
                }
                
                $data['attributes'][] = array(
                    'name' => $attribute['name'],
                    'value' => $attribute['value'],
                    'visible' => true,
                );
            }
        }
        
        return $data;
    }
    
    /**
     * Create a new WooCommerce product.
     *
     * @since    1.0.0
     * @param    array     $product_data    The prepared product data.
     * @param    array     $ms_product      The original MoySklad product data.
     * @return   int|bool                   The WooCommerce product ID or false on failure.
     */
    private function create_woo_product($product_data, $ms_product) {
        try {
            $this->logger->info("Начало создания товара в WooCommerce: " . $product_data['name'] . " (SKU: " . $product_data['sku'] . ")");
            
            // Проверка наличия класса WC_Product_Simple
            if (!class_exists('WC_Product_Simple')) {
                $this->logger->error("Класс WC_Product_Simple не найден - отсутствует WooCommerce или проблема с окружением");
                throw new Exception('WooCommerce not installed or WC_Product_Simple class not found');
            }
            
            // Create simple product
            $product = new WC_Product_Simple();
            $this->logger->debug("Объект WC_Product_Simple создан");
            
            // Set product data
            $product->set_name($product_data['name']);
            $this->logger->debug("Установлено название товара: " . $product_data['name']);
            
            $product->set_status($product_data['status']);
            $this->logger->debug("Установлен статус товара: " . $product_data['status']);
            
            $product->set_catalog_visibility($product_data['catalog_visibility']);
            $this->logger->debug("Установлена видимость товара: " . $product_data['catalog_visibility']);
            
            $product->set_sku($product_data['sku']);
            $this->logger->debug("Установлен SKU товара: " . $product_data['sku']);
            
            if (isset($product_data['regular_price']) && $product_data['regular_price'] !== '') {
                $product->set_regular_price($product_data['regular_price']);
                $this->logger->debug("Установлена цена товара: " . $product_data['regular_price']);
            }
            
            if (isset($product_data['description'])) {
                $product->set_description($product_data['description']);
                $this->logger->debug("Установлено описание товара");
            }
            
            // Set category
            if (isset($product_data['categories']) && !empty($product_data['categories'])) {
                $product->set_category_ids($product_data['categories']);
                $this->logger->debug("Установлены категории товара: " . implode(', ', $product_data['categories']));
            } else {
                $this->logger->debug("У товара нет категорий или категории не найдены в WooCommerce");
                
                // Если категория указана в МойСклад, но не найдена в WooCommerce, логируем это
                if (isset($ms_product['productFolder']) && isset($ms_product['productFolder']['meta'])) {
                    $ms_category_url = $ms_product['productFolder']['meta']['href'];
                    $ms_category_name = isset($ms_product['productFolder']['name']) ? $ms_product['productFolder']['name'] : 'Unknown';
                    
                    if (preg_match('/productfolder\/([^\/]+)/', $ms_category_url, $matches)) {
                        $ms_category_id = $matches[1];
                        $this->logger->info("Категория МойСклад с ID $ms_category_id и названием '$ms_category_name' не найдена в WooCommerce");
                    }
                }
            }
            
            // Set attributes
            if (isset($product_data['attributes']) && !empty($product_data['attributes'])) {
                $attributes = array();
                
                foreach ($product_data['attributes'] as $attribute_data) {
                    $attribute = new WC_Product_Attribute();
                    $attribute->set_name($attribute_data['name']);
                    $attribute->set_options(array($attribute_data['value']));
                    $attribute->set_visible($attribute_data['visible']);
                    $attribute->set_position(0);
                    $attributes[] = $attribute;
                }
                
                $product->set_attributes($attributes);
                $this->logger->debug("Установлены атрибуты товара: " . count($attributes));
            }
            
            // Save product
            $this->logger->info("Попытка сохранения товара: " . $product_data['name']);
            $product_id = $product->save();
            $this->logger->info("Результат сохранения товара: " . ($product_id ? $product_id : 'Ошибка'));
            
            if (!$product_id) {
                throw new Exception('Failed to save product: ' . $product_data['name']);
            }
            
            // Save mapping
            $this->logger->info("Сохранение маппинга товара: " . $product_id . " - " . $ms_product['id']);
            $mapping_result = $this->save_product_mapping($product_id, $ms_product['id'], json_encode($ms_product));
            $this->logger->info("Результат сохранения маппинга: " . ($mapping_result ? 'Успешно' : 'Ошибка'));
            
            // Process images - отключаем временно для отладки, так как это может вызывать проблемы
            $sync_product_images = '0'; // Временно отключаем
            if ($sync_product_images === '1' && isset($ms_product['images']) && isset($ms_product['images']['meta'])) {
                $this->logger->info("Начало синхронизации изображений для товара: " . $product_id);
                $this->sync_product_images($product_id, $ms_product);
                $this->logger->info("Синхронизация изображений завершена для товара: " . $product_id);
            }
            
            // Check for variants - отключаем временно для отладки
            $sync_product_modifications = '0'; // Временно отключаем
            if ($sync_product_modifications === '1') {
                $this->logger->info("Проверка вариантов товара: " . $ms_product['id']);
                $variants_response = $this->api->get_product_variants($ms_product['id']);
                
                if (!is_wp_error($variants_response) && !empty($variants_response['rows'])) {
                    // Product has variants - convert to variable product
                    $this->logger->info("Найдены варианты товара, конвертируем в вариативный товар");
                    $this->convert_to_variable_product($product_id, $ms_product, $variants_response['rows']);
                }
            }
            
            $this->logger->info('Успешно создан товар WooCommerce: ' . $product_data['name'], array('product_id' => $product_id));
            
            return $product_id;
        } catch (Exception $e) {
            $error_details = array(
                'product_data' => $product_data,
                'product_name' => isset($product_data['name']) ? $product_data['name'] : 'Unknown',
                'ms_product_id' => isset($ms_product['id']) ? $ms_product['id'] : 'Unknown',
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            );
            
            $this->logger->error('Не удалось создать товар: ' . $e->getMessage(), $error_details);
            
            // Запись детальной информации для диагностики
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WooMoySklad - Ошибка создания товара: ' . $e->getMessage() . ' - ' . json_encode($error_details));
            }
            
            return false;
        }
    }
    
    /**
     * Update an existing WooCommerce product.
     *
     * @since    1.0.0
     * @param    int       $product_id      The WooCommerce product ID.
     * @param    array     $product_data    The prepared product data.
     * @param    array     $ms_product      The original MoySklad product data.
     * @return   bool                       Whether the update was successful.
     */
    private function update_woo_product($product_id, $product_data, $ms_product) {
        $this->logger->info("Начало обновления товара WooCommerce: {$product_data['name']}, ID: $product_id");
        
        try {
            $product = wc_get_product($product_id);
            $this->logger->debug("Получен объект товара: " . ($product ? 'успешно' : 'не найден'));
            
            if (!$product) {
                // Проверим существует ли продукт в WooCommerce и попытаемся найти по SKU
                global $wpdb;
                if (!empty($product_data['sku'])) {
                    $this->logger->debug("Товар не найден по ID $product_id, пытаемся найти по SKU: {$product_data['sku']}");
                    $found_product_id = wc_get_product_id_by_sku($product_data['sku']);
                    if ($found_product_id) {
                        $product = wc_get_product($found_product_id);
                        if ($product) {
                            $this->logger->info("Продукт не найден по ID $product_id, но найден по SKU: {$product_data['sku']}, ID: $found_product_id");
                            
                            // Обновим маппинг и продолжим
                            $mapping_table = $wpdb->prefix . 'woo_moysklad_product_mapping';
                            $wpdb->update(
                                $mapping_table,
                                array('woo_product_id' => $found_product_id),
                                array('woo_product_id' => $product_id)
                            );
                            
                            $product_id = $found_product_id;
                        }
                    }
                }
                
                // Если продукт все равно не найден, создадим новый
                if (!$product) {
                    $this->logger->warning("Продукт не найден: $product_id, пробуем создать новый из данных МойСклад");
                    $result = $this->create_woo_product($product_data, $ms_product);
                    $this->logger->info("Результат создания нового товара: " . ($result ? 'успешно, ID: ' . $result : 'ошибка'));
                    if ($result) {
                        return true;
                    } else {
                        throw new Exception('Product not found: ' . $product_id . ' and could not be created');
                    }
                }
            }
            
            // Skip if it's not a simple or variable product
            if (!$product->is_type('simple') && !$product->is_type('variable')) {
                $this->logger->debug('Skipping unsupported product type: ' . $product->get_type());
                return true;
            }
            
            $this->logger->debug("Начинаем обновление данных товара: {$product_data['name']}");
            
            // Update product data
            $sync_product_name = get_option('woo_moysklad_sync_product_name', '1');
            if ($sync_product_name === '1') {
                $product->set_name($product_data['name']);
                $this->logger->debug("Обновлено название товара: {$product_data['name']}");
            }
            
            $product->set_sku($product_data['sku']);
            $this->logger->debug("Обновлен SKU товара: {$product_data['sku']}");
            
            $sync_product_description = get_option('woo_moysklad_sync_product_description', '1');
            if ($sync_product_description === '1' && isset($product_data['description'])) {
                $product->set_description($product_data['description']);
                $this->logger->debug("Обновлено описание товара");
            }
            
            // Update price for simple products
            if ($product->is_type('simple') && isset($product_data['regular_price']) && $product_data['regular_price'] !== '') {
                $product->set_regular_price($product_data['regular_price']);
                $this->logger->debug("Обновлена цена товара: {$product_data['regular_price']}");
            }
            
            // Update category
            if (isset($product_data['categories']) && !empty($product_data['categories'])) {
                $product->set_category_ids($product_data['categories']);
                $this->logger->debug("Обновлены категории товара: " . implode(', ', $product_data['categories']));
            } else {
                $this->logger->debug("При обновлении у товара нет категорий или категории не найдены в WooCommerce");
                
                // Если категория указана в МойСклад, но не найдена в WooCommerce, логируем это
                if (isset($ms_product['productFolder']) && isset($ms_product['productFolder']['meta'])) {
                    $ms_category_url = $ms_product['productFolder']['meta']['href'];
                    $ms_category_name = isset($ms_product['productFolder']['name']) ? $ms_product['productFolder']['name'] : 'Unknown';
                    
                    if (preg_match('/productfolder\/([^\/]+)/', $ms_category_url, $matches)) {
                        $ms_category_id = $matches[1];
                        $this->logger->info("При обновлении товара категория МойСклад с ID $ms_category_id и названием '$ms_category_name' не найдена в WooCommerce");
                    }
                }
            }
            
            // Update attributes for simple products
            if ($product->is_type('simple') && isset($product_data['attributes']) && !empty($product_data['attributes'])) {
                $attributes = array();
                
                foreach ($product_data['attributes'] as $attribute_data) {
                    $attribute = new WC_Product_Attribute();
                    $attribute->set_name($attribute_data['name']);
                    $attribute->set_options(array($attribute_data['value']));
                    $attribute->set_visible($attribute_data['visible']);
                    $attribute->set_position(0);
                    $attributes[] = $attribute;
                }
                
                $product->set_attributes($attributes);
            }
            
            // Save product
            $product->save();
            
            // Update mapping
            $this->save_product_mapping($product_id, $ms_product['id'], json_encode($ms_product));
            
            // Process images if enabled
            $sync_product_images = get_option('woo_moysklad_sync_product_images', '1');
            if ($sync_product_images === '1' && isset($ms_product['images']) && isset($ms_product['images']['meta'])) {
                $this->sync_product_images($product_id, $ms_product);
            }
            
            // Check for variants
            $sync_product_modifications = get_option('woo_moysklad_sync_product_modifications', '1');
            if ($sync_product_modifications === '1') {
                $variants_response = $this->api->get_product_variants($ms_product['id']);
                
                if (!is_wp_error($variants_response) && !empty($variants_response['rows'])) {
                    if ($product->is_type('simple')) {
                        // Product has variants but is a simple product - convert to variable
                        $this->convert_to_variable_product($product_id, $ms_product, $variants_response['rows']);
                    } else {
                        // Update variants
                        $this->update_product_variants($product_id, $ms_product, $variants_response['rows']);
                    }
                }
            }
            
            $this->logger->info('Updated WooCommerce product: ' . $product_data['name'], array('product_id' => $product_id));
            
            return true;
        } catch (Exception $e) {
            $error_details = array(
                'product_id' => $product_id,
                'product_data' => $product_data,
                'product_name' => isset($product_data['name']) ? $product_data['name'] : 'Unknown',
                'ms_product_id' => isset($ms_product['id']) ? $ms_product['id'] : 'Unknown',
                'error_trace' => $e->getTraceAsString()
            );
            
            $this->logger->error('Не удалось обновить товар: ' . $e->getMessage(), $error_details);
            
            // Запись детальной информации для диагностики
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WooMoySklad - Ошибка обновления товара: ' . $e->getMessage() . ' - ' . json_encode($error_details));
            }
            
            return false;
        }
    }
    
    /**
     * Convert a simple product to a variable product.
     *
     * @since    1.0.0
     * @param    int       $product_id    The WooCommerce product ID.
     * @param    array     $ms_product    The MoySklad product data.
     * @param    array     $variants      The MoySklad product variants.
     */
    private function convert_to_variable_product($product_id, $ms_product, $variants) {
        try {
            // Get the product
            $product = wc_get_product($product_id);
            
            if (!$product) {
                throw new Exception('Product not found: ' . $product_id);
            }
            
            // Don't convert if it's already a variable product
            if ($product->is_type('variable')) {
                $this->update_product_variants($product_id, $ms_product, $variants);
                return;
            }
            
            // Create a new variable product
            $variable_product = new WC_Product_Variable();
            $variable_product->set_name($product->get_name());
            $variable_product->set_status($product->get_status());
            $variable_product->set_catalog_visibility($product->get_catalog_visibility());
            $variable_product->set_description($product->get_description());
            $variable_product->set_short_description($product->get_short_description());
            $variable_product->set_sku($product->get_sku());
            $variable_product->set_category_ids($product->get_category_ids());
            $variable_product->set_image_id($product->get_image_id());
            $variable_product->set_gallery_image_ids($product->get_gallery_image_ids());
            
            // Process variants
            $variation_attributes = array();
            $variant_data = array();
            
            foreach ($variants as $variant) {
                if (!isset($variant['characteristics']) || empty($variant['characteristics'])) {
                    continue;
                }
                
                // Extract variant attributes
                foreach ($variant['characteristics'] as $char) {
                    $attr_name = $char['name'];
                    $attr_value = $char['value'];
                    
                    if (!isset($variation_attributes[$attr_name])) {
                        $variation_attributes[$attr_name] = array();
                    }
                    
                    if (!in_array($attr_value, $variation_attributes[$attr_name])) {
                        $variation_attributes[$attr_name][] = $attr_value;
                    }
                }
                
                // Store variant data
                $variant_data[] = array(
                    'ms_id' => $variant['id'],
                    'characteristics' => $variant['characteristics'],
                    'price' => isset($variant['salePrices'][0]['value']) ? ($variant['salePrices'][0]['value'] / 100) : '',
                    'sku' => isset($variant['code']) ? $variant['code'] : '',
                );
            }
            
            // Create product attributes
            $product_attributes = array();
            
            foreach ($variation_attributes as $name => $values) {
                $attribute_id = wc_attribute_taxonomy_id_by_name($name);
                
                if (!$attribute_id) {
                    // Create attribute
                    $attribute_id = wc_create_attribute(array(
                        'name' => $name,
                        'slug' => sanitize_title($name),
                        'type' => 'select',
                        'order_by' => 'menu_order',
                        'has_archives' => false,
                    ));
                    
                    if (is_wp_error($attribute_id)) {
                        throw new Exception('Failed to create attribute: ' . $attribute_id->get_error_message());
                    }
                }
                
                $taxonomy = wc_attribute_taxonomy_name_by_id($attribute_id);
                
                // Register the taxonomy if needed
                if (!taxonomy_exists($taxonomy)) {
                    register_taxonomy(
                        $taxonomy,
                        apply_filters('woocommerce_taxonomy_objects_' . $taxonomy, array('product')),
                        apply_filters('woocommerce_taxonomy_args_' . $taxonomy, array(
                            'labels' => array(
                                'name' => $name,
                            ),
                            'hierarchical' => true,
                            'show_ui' => false,
                            'query_var' => true,
                            'rewrite' => false,
                        ))
                    );
                }
                
                // Add attribute values
                foreach ($values as $value) {
                    if (!term_exists($value, $taxonomy)) {
                        wp_insert_term($value, $taxonomy);
                    }
                }
                
                // Add attribute to product
                $attribute = new WC_Product_Attribute();
                $attribute->set_id($attribute_id);
                $attribute->set_name($taxonomy);
                $attribute->set_options($values);
                $attribute->set_position(0);
                $attribute->set_visible(true);
                $attribute->set_variation(true);
                $product_attributes[] = $attribute;
            }
            
            $variable_product->set_attributes($product_attributes);
            $variable_product_id = $variable_product->save();
            
            if (!$variable_product_id) {
                throw new Exception('Failed to save variable product');
            }
            
            // Create variations
            foreach ($variant_data as $variant) {
                $variation = new WC_Product_Variation();
                $variation->set_parent_id($variable_product_id);
                $variation->set_status('publish');
                
                if ($variant['sku']) {
                    $variation->set_sku($variant['sku']);
                }
                
                if ($variant['price']) {
                    $variation->set_regular_price($variant['price']);
                }
                
                // Set variation attributes
                $variation_attributes = array();
                
                foreach ($variant['characteristics'] as $char) {
                    $taxonomy = wc_attribute_taxonomy_name_by_name($char['name']);
                    $variation_attributes[$taxonomy] = $char['value'];
                }
                
                $variation->set_attributes($variation_attributes);
                $variation_id = $variation->save();
                
                if ($variation_id) {
                    // Store MoySklad variant ID
                    update_post_meta($variation_id, '_ms_variant_id', $variant['ms_id']);
                }
            }
            
            // Update mapping to point to the new variable product
            $this->save_product_mapping($variable_product_id, $ms_product['id'], json_encode($ms_product));
            
            // Delete the original simple product
            wp_delete_post($product_id, true);
            
            $this->logger->info('Converted simple product to variable product', array(
                'product_id' => $product_id,
                'variable_product_id' => $variable_product_id,
                'variants_count' => count($variants)
            ));
        } catch (Exception $e) {
            $error_details = array(
                'product_id' => $product_id,
                'ms_product_id' => isset($ms_product['id']) ? $ms_product['id'] : 'Unknown',
                'product_name' => isset($ms_product['name']) ? $ms_product['name'] : 'Unknown',
                'variants_count' => count($variants),
                'error_trace' => $e->getTraceAsString()
            );
            
            $this->logger->error('Не удалось конвертировать товар в вариативный: ' . $e->getMessage(), $error_details);
            
            // Запись детальной информации для диагностики
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WooMoySklad - Ошибка конвертации в вариативный товар: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Update variants of a variable product.
     *
     * @since    1.0.0
     * @param    int       $product_id    The WooCommerce product ID.
     * @param    array     $ms_product    The MoySklad product data.
     * @param    array     $variants      The MoySklad product variants.
     */
    private function update_product_variants($product_id, $ms_product, $variants) {
        try {
            $product = wc_get_product($product_id);
            
            if (!$product || !$product->is_type('variable')) {
                throw new Exception('Product is not a variable product: ' . $product_id);
            }
            
            // Get existing variations
            $variations = $product->get_children();
            $existing_variations = array();
            
            foreach ($variations as $variation_id) {
                $ms_variant_id = get_post_meta($variation_id, '_ms_variant_id', true);
                
                if ($ms_variant_id) {
                    $existing_variations[$ms_variant_id] = $variation_id;
                }
            }
            
            // Process variants
            $variation_attributes = array();
            
            foreach ($variants as $variant) {
                // Проверяем флаг остановки синхронизации
                if (get_option('woo_moysklad_sync_stopped_by_user', '0') === '1') {
                    $this->logger->info('Синхронизация вариаций товаров остановлена пользователем');
                    // Сбрасываем флаг остановки
                    update_option('woo_moysklad_sync_stopped_by_user', '0');
                    return false;
                }
                if (!isset($variant['characteristics']) || empty($variant['characteristics'])) {
                    continue;
                }
                
                // Extract variant attributes
                foreach ($variant['characteristics'] as $char) {
                    $attr_name = $char['name'];
                    $attr_value = $char['value'];
                    
                    if (!isset($variation_attributes[$attr_name])) {
                        $variation_attributes[$attr_name] = array();
                    }
                    
                    if (!in_array($attr_value, $variation_attributes[$attr_name])) {
                        $variation_attributes[$attr_name][] = $attr_value;
                    }
                }
                
                // Update or create variation
                if (isset($existing_variations[$variant['id']])) {
                    // Update existing variation
                    $variation_id = $existing_variations[$variant['id']];
                    $variation = wc_get_product($variation_id);
                    
                    if ($variation) {
                        // Update variation data
                        if (isset($variant['code']) && $variant['code']) {
                            $variation->set_sku($variant['code']);
                        }
                        
                        if (isset($variant['salePrices'][0]['value'])) {
                            $variation->set_regular_price($variant['salePrices'][0]['value'] / 100);
                        }
                        
                        // Set variation attributes
                        $variation_attrs = array();
                        
                        foreach ($variant['characteristics'] as $char) {
                            $taxonomy = wc_attribute_taxonomy_name_by_name($char['name']);
                            $variation_attrs[$taxonomy] = $char['value'];
                        }
                        
                        $variation->set_attributes($variation_attrs);
                        $variation->save();
                    }
                } else {
                    // Create new variation
                    $variation = new WC_Product_Variation();
                    $variation->set_parent_id($product_id);
                    $variation->set_status('publish');
                    
                    if (isset($variant['code']) && $variant['code']) {
                        $variation->set_sku($variant['code']);
                    }
                    
                    if (isset($variant['salePrices'][0]['value'])) {
                        $variation->set_regular_price($variant['salePrices'][0]['value'] / 100);
                    }
                    
                    // Set variation attributes
                    $variation_attrs = array();
                    
                    foreach ($variant['characteristics'] as $char) {
                        $taxonomy = wc_attribute_taxonomy_name_by_name($char['name']);
                        $variation_attrs[$taxonomy] = $char['value'];
                    }
                    
                    $variation->set_attributes($variation_attrs);
                    $variation_id = $variation->save();
                    
                    if ($variation_id) {
                        // Store MoySklad variant ID
                        update_post_meta($variation_id, '_ms_variant_id', $variant['id']);
                    }
                }
            }
            
            // Update product attributes if needed
            $product_attributes = array();
            
            foreach ($variation_attributes as $name => $values) {
                // Проверяем флаг остановки синхронизации
                if (get_option('woo_moysklad_sync_stopped_by_user', '0') === '1') {
                    $this->logger->info('Синхронизация атрибутов товаров остановлена пользователем');
                    // Сбрасываем флаг остановки
                    update_option('woo_moysklad_sync_stopped_by_user', '0');
                    return false;
                }
                $attribute_id = wc_attribute_taxonomy_id_by_name($name);
                
                if (!$attribute_id) {
                    // Create attribute
                    $attribute_id = wc_create_attribute(array(
                        'name' => $name,
                        'slug' => sanitize_title($name),
                        'type' => 'select',
                        'order_by' => 'menu_order',
                        'has_archives' => false,
                    ));
                    
                    if (is_wp_error($attribute_id)) {
                        throw new Exception('Failed to create attribute: ' . $attribute_id->get_error_message());
                    }
                }
                
                $taxonomy = wc_attribute_taxonomy_name_by_id($attribute_id);
                
                // Register the taxonomy if needed
                if (!taxonomy_exists($taxonomy)) {
                    register_taxonomy(
                        $taxonomy,
                        apply_filters('woocommerce_taxonomy_objects_' . $taxonomy, array('product')),
                        apply_filters('woocommerce_taxonomy_args_' . $taxonomy, array(
                            'labels' => array(
                                'name' => $name,
                            ),
                            'hierarchical' => true,
                            'show_ui' => false,
                            'query_var' => true,
                            'rewrite' => false,
                        ))
                    );
                }
                
                // Add attribute values
                foreach ($values as $value) {
                    if (!term_exists($value, $taxonomy)) {
                        wp_insert_term($value, $taxonomy);
                    }
                }
                
                // Add attribute to product
                $attribute = new WC_Product_Attribute();
                $attribute->set_id($attribute_id);
                $attribute->set_name($taxonomy);
                $attribute->set_options($values);
                $attribute->set_position(0);
                $attribute->set_visible(true);
                $attribute->set_variation(true);
                $product_attributes[] = $attribute;
            }
            
            $product->set_attributes($product_attributes);
            $product->save();
            
            $this->logger->info('Updated variable product variants', array(
                'product_id' => $product_id,
                'variants_count' => count($variants)
            ));
        } catch (Exception $e) {
            $error_details = array(
                'product_id' => $product_id,
                'ms_product_id' => isset($ms_product['id']) ? $ms_product['id'] : 'Unknown',
                'product_name' => isset($ms_product['name']) ? $ms_product['name'] : 'Unknown',
                'variants_count' => count($variants),
                'error_trace' => $e->getTraceAsString()
            );
            
            $this->logger->error('Не удалось обновить варианты товара: ' . $e->getMessage(), $error_details);
            
            // Запись детальной информации для диагностики
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WooMoySklad - Ошибка обновления вариантов товара: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Synchronize product images.
     *
     * @since    1.0.0
     * @param    int       $product_id    The WooCommerce product ID.
     * @param    array     $ms_product    The MoySklad product data.
     */
    private function sync_product_images($product_id, $ms_product) {
        try {
            if (!isset($ms_product['images']) || !isset($ms_product['images']['meta']) || !isset($ms_product['images']['meta']['href'])) {
                return;
            }
            
            // Get images URL
            $images_url = $ms_product['images']['meta']['href'];
            $images_response = $this->api->request(str_replace($this->api->api_base, '', $images_url));
            
            if (is_wp_error($images_response) || empty($images_response['rows'])) {
                return;
            }
            
            $images = $images_response['rows'];
            $image_ids = array();
            $sync_all_images = get_option('woo_moysklad_sync_all_product_images', '1');
            
            foreach ($images as $index => $image) {
                // Проверяем флаг остановки синхронизации
                if (get_option('woo_moysklad_sync_stopped_by_user', '0') === '1') {
                    $this->logger->info('Синхронизация изображений товаров остановлена пользователем');
                    // Сбрасываем флаг остановки
                    update_option('woo_moysklad_sync_stopped_by_user', '0');
                    return;
                }
                
                if ($index > 0 && $sync_all_images !== '1') {
                    break;
                }
                
                if (!isset($image['miniature']) || !isset($image['miniature']['href'])) {
                    continue;
                }
                
                // Download image
                $image_url = $image['miniature']['href'];
                $image_id = $this->download_image($image_url, $product_id);
                
                if ($image_id) {
                    $image_ids[] = $image_id;
                }
            }
            
            if (!empty($image_ids)) {
                $product = wc_get_product($product_id);
                
                if ($product) {
                    // Set first image as the main image
                    $product->set_image_id($image_ids[0]);
                    
                    // Set additional images as gallery
                    if (count($image_ids) > 1) {
                        $gallery_ids = array_slice($image_ids, 1);
                        $product->set_gallery_image_ids($gallery_ids);
                    }
                    
                    $product->save();
                }
            }
        } catch (Exception $e) {
            $error_details = array(
                'product_id' => $product_id,
                'ms_product_id' => isset($ms_product['id']) ? $ms_product['id'] : 'Unknown',
                'product_name' => isset($ms_product['name']) ? $ms_product['name'] : 'Unknown',
                'error_trace' => $e->getTraceAsString()
            );
            
            $this->logger->error('Не удалось синхронизировать изображения товара: ' . $e->getMessage(), $error_details);
            
            // Запись детальной информации для диагностики
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WooMoySklad - Ошибка синхронизации изображений: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Download an image from MoySklad and attach it to a product.
     *
     * @since    1.0.0
     * @param    string    $image_url     The image URL.
     * @param    int       $product_id    The WooCommerce product ID.
     * @return   int|false                The attachment ID or false on failure.
     */
    private function download_image($image_url, $product_id) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        
        // Get credentials
        $credentials = array(
            'login' => get_option('woo_moysklad_api_login', ''),
            'password' => get_option('woo_moysklad_api_password', ''),
        );
        
        // Download image
        $auth = base64_encode($credentials['login'] . ':' . $credentials['password']);
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . $auth,
            ),
        );
        
        $temp_file = download_url($image_url, 30, false, $args);
        
        if (is_wp_error($temp_file)) {
            $this->logger->error('Failed to download image: ' . $temp_file->get_error_message(), array(
                'image_url' => $image_url,
                'product_id' => $product_id
            ));
            return false;
        }
        
        // Generate a unique filename
        $filename = basename($image_url);
        $filename = sanitize_file_name($filename);
        
        if (empty($filename)) {
            $filename = md5($image_url) . '.jpg';
        }
        
        // Check if image already exists
        $product = wc_get_product($product_id);
        $existing_image_id = $product->get_image_id();
        
        if ($existing_image_id) {
            $existing_image = get_post_meta($existing_image_id, '_ms_image_url', true);
            
            if ($existing_image === $image_url) {
                @unlink($temp_file);
                return $existing_image_id;
            }
        }
        
        // Build file array
        $file = array(
            'name'     => $filename,
            'type'     => mime_content_type($temp_file),
            'tmp_name' => $temp_file,
            'error'    => 0,
            'size'     => filesize($temp_file),
        );
        
        // Upload the image
        $attachment_id = media_handle_sideload($file, $product_id);
        
        // Clean up
        @unlink($temp_file);
        
        if (is_wp_error($attachment_id)) {
            $this->logger->error('Failed to attach image: ' . $attachment_id->get_error_message(), array(
                'image_url' => $image_url,
                'product_id' => $product_id
            ));
            return false;
        }
        
        // Store the original image URL for future reference
        update_post_meta($attachment_id, '_ms_image_url', $image_url);
        
        return $attachment_id;
    }
    
    /**
     * Get WooCommerce category ID by MoySklad ID.
     * Функция улучшена для кеширования результатов и предотвращения повторных запросов.
     *
     * @since    1.0.0
     * @param    string    $ms_category_url    The MoySklad category URL.
     * @return   int|false                     The WooCommerce category ID or false.
     */
    private function get_woo_category_id_by_ms_id($ms_category_url) {
        if (empty($ms_category_url)) {
            return false;
        }
        
        // Используем статический кеш для хранения уже найденных соответствий
        static $category_cache = array();
        
        // Extract ID from URL
        $ms_category_id = '';
        if (preg_match('/productfolder\/([^\/]+)/', $ms_category_url, $matches)) {
            $ms_category_id = $matches[1];
        }
        
        if (empty($ms_category_id)) {
            return false;
        }
        
        // Если ID категории уже есть в кеше, возвращаем сохраненное значение
        if (isset($category_cache[$ms_category_id])) {
            return $category_cache[$ms_category_id];
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
            // Сохраняем в кеш для будущих запросов
            $category_cache[$ms_category_id] = $terms[0]->term_id;
            return $terms[0]->term_id;
        }
        
        // Логируем информацию о ненайденной категории
        $this->logger->debug("Категория МойСклад с ID '$ms_category_id' не найдена в WooCommerce");
        
        // Сохраняем отрицательный результат в кеш, чтобы не повторять запрос
        $category_cache[$ms_category_id] = false;
        
        return false;
    }
    
    /**
     * Save product mapping. Создает или обновляет соответствие между 
     * товаром WooCommerce и товаром МойСклад.
     *
     * @since    1.0.0
     * @param    int       $woo_product_id    The WooCommerce product ID.
     * @param    string    $ms_product_id     The MoySklad product ID.
     * @param    string    $ms_product_meta   The MoySklad product metadata (JSON).
     */
    private function save_product_mapping($woo_product_id, $ms_product_id, $ms_product_meta) {
        global $wpdb;
        
        // Добавляем базовое логирование
        $this->logger->debug("Сохранение маппинга товара. WooCommerce ID: $woo_product_id, МойСклад ID: $ms_product_id");
        
        // Название таблицы маппинга
        $table_name = $wpdb->prefix . 'woo_moysklad_product_mapping';
        
        // Проверяем существование таблицы
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            $this->logger->warning("Таблица $table_name не существует, создаём...");
            
            // Создаем таблицу маппинга
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                woo_product_id bigint(20) NOT NULL,
                ms_product_id varchar(255) NOT NULL,
                ms_product_meta longtext NOT NULL,
                last_updated datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY woo_product_id (woo_product_id),
                KEY ms_product_id (ms_product_id)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            // Проверяем создание таблицы
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            
            if (!$table_exists) {
                $this->logger->error("Не удалось создать таблицу $table_name");
                return false;
            }
            
            $this->logger->info("Таблица $table_name успешно создана");
        }
        
        // Проверяем существующие записи
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE ms_product_id = %s",
            $ms_product_id
        ));
        
        if ($exists) {
            // Обновляем существующую запись
            $result = $wpdb->update(
                $table_name,
                array(
                    'woo_product_id' => $woo_product_id,
                    'ms_product_meta' => $ms_product_meta,
                    'last_updated' => current_time('mysql')
                ),
                array('ms_product_id' => $ms_product_id)
            );
            
            if ($result === false) {
                $this->logger->error("Ошибка обновления маппинга: " . $wpdb->last_error);
                return false;
            }
            
            $this->logger->debug("Обновлен маппинг для МойСклад ID: $ms_product_id");
            return true;
        } else {
            // Создаем новую запись
            $result = $wpdb->insert(
                $table_name,
                array(
                    'woo_product_id' => $woo_product_id,
                    'ms_product_id' => $ms_product_id,
                    'ms_product_meta' => $ms_product_meta,
                    'last_updated' => current_time('mysql')
                )
            );
            
            if ($result === false) {
                $this->logger->error("Ошибка создания маппинга: " . $wpdb->last_error);
                return false;
            }
            
            $this->logger->debug("Создан новый маппинг для МойСклад ID: $ms_product_id");
            return true;
        }
    }
}
