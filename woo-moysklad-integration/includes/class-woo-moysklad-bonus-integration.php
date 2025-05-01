<?php
/**
 * WooCommerce MoySklad Integration - Bonus Integration
 *
 * Интеграция с плагином "Бонусы для Woo"
 *
 * @package    Woo_Moysklad_Integration
 * @subpackage Woo_Moysklad_Integration/includes
 * @author     RUCODER
 */

// Если файл вызван напрямую, прерываем выполнение
if (!defined('WPINC')) {
    die;
}

/**
 * Класс для интеграции с плагином "Бонусы для Woo"
 *
 * @since      1.0.0
 * @package    Woo_Moysklad_Integration
 * @subpackage Woo_Moysklad_Integration/includes
 * @author     RUCODER
 */
class Woo_Moysklad_Bonus_Integration {
    
    /**
     * API объект для работы с МойСклад
     *
     * @since    1.0.0
     * @access   private
     * @var      Woo_Moysklad_API    $api    API объект для работы с МойСклад
     */
    private $api;
    
    /**
     * Логгер для записи событий
     *
     * @since    1.0.0
     * @access   private
     * @var      Woo_Moysklad_Logger    $logger    Логгер для записи событий
     */
    private $logger;
    
    /**
     * Инициализация класса интеграции с бонусной системой
     *
     * @since    1.0.0
     * @param    Woo_Moysklad_API       $api       API объект для работы с МойСклад
     * @param    Woo_Moysklad_Logger    $logger    Логгер для записи событий
     */
    public function __construct($api, $logger) {
        $this->api = $api;
        $this->logger = $logger;
        
        // Добавляем хуки интеграции с бонусной системой, если она активирована
        if ($this->is_bonus_plugin_active()) {
            $this->add_hooks();
        }
    }
    
    /**
     * Проверяет, активирован ли плагин "Бонусы для Woo"
     *
     * @since    1.0.0
     * @return   boolean    Флаг активности плагина
     */
    private function is_bonus_plugin_active() {
        return function_exists('wc_bonus_for_woo_active') || class_exists('Bonus_For_Woo');
    }
    
    /**
     * Добавляет хуки для интеграции с плагином бонусов
     *
     * @since    1.0.0
     */
    private function add_hooks() {
        // Хук для добавления информации о бонусах в заказ перед отправкой в МойСклад
        add_filter('woo_moysklad_order_data_before_sync', array($this, 'add_bonus_info_to_order'), 10, 2);
        
        // Хук для обновления бонусов клиента в МойСклад при изменении в WooCommerce
        add_action('bonus_for_woo_points_updated', array($this, 'update_customer_bonus_in_moysklad'), 10, 2);
        
        $this->logger->info('Интеграция с плагином "Бонусы для Woo" инициализирована');
    }
    
    /**
     * Добавляет информацию о бонусах в данные заказа перед отправкой в МойСклад
     *
     * @since    1.0.0
     * @param    array     $order_data    Данные заказа для МойСклад
     * @param    WC_Order  $order         Объект заказа WooCommerce
     * @return   array                    Модифицированные данные заказа
     */
    public function add_bonus_info_to_order($order_data, $order) {
        try {
            // Проверяем, использовались ли бонусы в заказе
            $used_bonus_points = $this->get_used_bonus_points($order);
            $earned_bonus_points = $this->get_earned_bonus_points($order);
            
            if ($used_bonus_points > 0 || $earned_bonus_points > 0) {
                if (!isset($order_data['attributes'])) {
                    $order_data['attributes'] = array();
                }
                
                // Добавляем информацию о использованных бонусах как атрибут заказа
                if ($used_bonus_points > 0) {
                    $used_bonus_attribute_id = get_option('woo_moysklad_bonus_used_attribute_id', '6af5c95b-f91b-11eb-0a80-0656000e3f2c');
                    $order_data['attributes'][] = array(
                        'meta' => array(
                            'href' => $this->api->api_base . '/entity/attributemetadata/' . $used_bonus_attribute_id,
                            'type' => 'attributemetadata',
                            'mediaType' => 'application/json'
                        ),
                        'value' => $used_bonus_points
                    );
                    
                    $this->logger->info("Добавлена информация о использованных бонусах: $used_bonus_points", array(
                        'order_id' => $order->get_id()
                    ));
                }
                
                // Добавляем информацию о начисленных бонусах как атрибут заказа
                if ($earned_bonus_points > 0) {
                    $earned_bonus_attribute_id = get_option('woo_moysklad_bonus_earned_attribute_id', '7bc8dfbb-f91b-11eb-0a80-0656000e3f2d');
                    $order_data['attributes'][] = array(
                        'meta' => array(
                            'href' => $this->api->api_base . '/entity/attributemetadata/' . $earned_bonus_attribute_id,
                            'type' => 'attributemetadata',
                            'mediaType' => 'application/json'
                        ),
                        'value' => $earned_bonus_points
                    );
                    
                    $this->logger->info("Добавлена информация о начисленных бонусах: $earned_bonus_points", array(
                        'order_id' => $order->get_id()
                    ));
                }
                
                // Добавляем комментарий о бонусах
                if (!isset($order_data['description'])) {
                    $order_data['description'] = '';
                }
                
                $bonus_description = '';
                if ($used_bonus_points > 0) {
                    $bonus_description .= sprintf("Использовано бонусов: %d. ", $used_bonus_points);
                }
                if ($earned_bonus_points > 0) {
                    $bonus_description .= sprintf("Начислено бонусов: %d. ", $earned_bonus_points);
                }
                
                $order_data['description'] = trim($bonus_description . ' ' . $order_data['description']);
            }
        } catch (Exception $e) {
            $this->logger->error('Ошибка при добавлении информации о бонусах: ' . $e->getMessage(), array(
                'order_id' => $order->get_id()
            ));
        }
        
        return $order_data;
    }
    
    /**
     * Получает количество использованных бонусов в заказе
     *
     * @since    1.0.0
     * @param    WC_Order  $order    Объект заказа WooCommerce
     * @return   int                 Количество использованных бонусов
     */
    private function get_used_bonus_points($order) {
        $used_points = 0;
        
        // Проверяем метаданные заказа для плагина Бонусы для Woo
        $bonus_meta = $order->get_meta('_wc_bonus_points_used');
        if (!empty($bonus_meta)) {
            $used_points = (int)$bonus_meta;
        }
        
        // Альтернативный вариант получения данных через WooCommerce Coupons
        if ($used_points === 0) {
            $coupons = $order->get_coupon_codes();
            foreach ($coupons as $coupon_code) {
                if (strpos($coupon_code, 'bonus_') === 0) {
                    $coupon = new WC_Coupon($coupon_code);
                    $used_points += (int)$coupon->get_amount();
                }
            }
        }
        
        return $used_points;
    }
    
    /**
     * Получает количество начисленных бонусов за заказ
     *
     * @since    1.0.0
     * @param    WC_Order  $order    Объект заказа WooCommerce
     * @return   int                 Количество начисленных бонусов
     */
    private function get_earned_bonus_points($order) {
        $earned_points = 0;
        
        // Проверяем метаданные заказа для плагина Бонусы для Woo
        $bonus_meta = $order->get_meta('_wc_bonus_points_earned');
        if (!empty($bonus_meta)) {
            $earned_points = (int)$bonus_meta;
        }
        
        return $earned_points;
    }
    
    /**
     * Обновляет информацию о бонусах клиента в МойСклад
     *
     * @since    1.0.0
     * @param    int       $user_id       ID пользователя WooCommerce
     * @param    int       $bonus_points  Новое количество бонусов
     */
    public function update_customer_bonus_in_moysklad($user_id, $bonus_points) {
        // Проверяем, есть ли связь с МойСклад
        $ms_customer_id = get_user_meta($user_id, '_ms_customer_id', true);
        
        if (empty($ms_customer_id)) {
            $this->logger->debug('Невозможно обновить бонусы: пользователь не связан с МойСклад', array(
                'user_id' => $user_id
            ));
            return;
        }
        
        try {
            // Получаем данные о контрагенте
            $endpoint = "/entity/counterparty/$ms_customer_id";
            $customer_data = $this->api->request($endpoint);
            
            if (is_wp_error($customer_data)) {
                throw new Exception($customer_data->get_error_message());
            }
            
            // Обновляем атрибут с бонусами
            $updated_data = array(
                'attributes' => array(
                    array(
                        'meta' => array(
                            'href' => $this->api->api_base . '/entity/attributemetadata/' . get_option('woo_moysklad_bonus_balance_attribute_id', '8c24e9bb-f91b-11eb-0a80-0656000e3f2e'),
                            'type' => 'attributemetadata',
                            'mediaType' => 'application/json'
                        ),
                        'value' => $bonus_points
                    )
                )
            );
            
            // Отправляем обновленные данные
            $result = $this->api->request($endpoint, 'PUT', $updated_data);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            $this->logger->info("Обновлены бонусы пользователя в МойСклад: $bonus_points", array(
                'user_id' => $user_id,
                'ms_customer_id' => $ms_customer_id
            ));
        } catch (Exception $e) {
            $this->logger->error('Ошибка при обновлении бонусов в МойСклад: ' . $e->getMessage(), array(
                'user_id' => $user_id,
                'ms_customer_id' => $ms_customer_id
            ));
        }
    }
    
    /**
     * Регистрирует необходимые атрибуты в МойСклад для бонусной системы
     * 
     * @since    1.0.0
     * @return   array              Результат операции
     */
    public function register_bonus_attributes() {
        if (!$this->api->is_configured()) {
            return array(
                'success' => false,
                'message' => __('API не настроен', 'woo-moysklad-integration'),
            );
        }
        
        try {
            $this->logger->info('Начата регистрация атрибутов для бонусной системы');
            
            // Делаем тестовый запрос к метаданным, чтобы проверить права доступа
            $test_response = $this->api->request('/entity/customerorder/metadata');
            
            if (is_wp_error($test_response)) {
                $error_message = $test_response->get_error_message();
                
                // Проверяем, связана ли ошибка с правами доступа
                if (strpos($error_message, 'Доступ запрещён') !== false || 
                    strpos($error_message, 'только пользователь с правами администратора') !== false) {
                    
                    $this->logger->warning('Нет прав администратора для создания атрибутов в МойСклад. Используем существующие значения атрибутов.');
                    
                    // Используем существующие ID атрибутов без попыток создания новых
                    return array(
                        'success' => true,
                        'message' => __('Для создания новых атрибутов требуются права администратора в МойСклад. Будут использованы существующие атрибуты, если они доступны.', 'woo-moysklad-integration'),
                        'used_bonus_id' => get_option('woo_moysklad_bonus_used_attribute_id', ''),
                        'earned_bonus_id' => get_option('woo_moysklad_bonus_earned_attribute_id', ''),
                        'balance_bonus_id' => get_option('woo_moysklad_bonus_balance_attribute_id', '')
                    );
                }
            }
            
            // Если прав администратора достаточно, продолжаем создание атрибутов
            // Создаем атрибут для хранения использованных бонусов в заказе
            $used_bonus_attribute_id = get_option('woo_moysklad_bonus_used_attribute_id', '6af5c95b-f91b-11eb-0a80-0656000e3f2c');
            $used_attribute = $this->create_bonus_attribute(
                'customerorder',
                'Использовано бонусов',
                'long',
                $used_bonus_attribute_id
            );
            
            // Создаем атрибут для хранения начисленных бонусов в заказе
            $earned_bonus_attribute_id = get_option('woo_moysklad_bonus_earned_attribute_id', '7bc8dfbb-f91b-11eb-0a80-0656000e3f2d');
            $earned_attribute = $this->create_bonus_attribute(
                'customerorder',
                'Начислено бонусов',
                'long',
                $earned_bonus_attribute_id
            );
            
            // Создаем атрибут для хранения текущего баланса бонусов у контрагента
            $balance_bonus_attribute_id = get_option('woo_moysklad_bonus_balance_attribute_id', '8c24e9bb-f91b-11eb-0a80-0656000e3f2e');
            $balance_attribute = $this->create_bonus_attribute(
                'counterparty',
                'Баланс бонусов',
                'long',
                $balance_bonus_attribute_id
            );
            
            $this->logger->info('Регистрация атрибутов для бонусной системы завершена успешно');
            
            return array(
                'success' => true,
                'message' => __('Атрибуты для бонусной системы успешно зарегистрированы', 'woo-moysklad-integration'),
            );
        } catch (Exception $e) {
            $this->logger->error('Ошибка при регистрации атрибутов для бонусной системы: ' . $e->getMessage());
            
            return array(
                'success' => false,
                'message' => $e->getMessage(),
            );
        }
    }
    
    /**
     * Регистрирует атрибуты бонусов в МойСклад через AJAX-запрос
     *
     * @since    1.0.0
     * @return   array|WP_Error    Результат регистрации атрибутов или объект ошибки
     */
    public function register_bonus_attributes_in_moysklad() {
        if (!$this->api->is_configured()) {
            return new WP_Error(
                'api_not_configured', 
                __('API МойСклад не настроен. Проверьте настройки API в разделе "Настройки API".', 'woo-moysklad-integration')
            );
        }
        
        try {
            $this->logger->info('Начата регистрация атрибутов бонусов в МойСклад через AJAX');
            
            // Делаем тестовый запрос к метаданным, чтобы проверить права доступа
            $test_response = $this->api->request('/entity/customerorder/metadata');
            
            if (is_wp_error($test_response)) {
                $error_message = $test_response->get_error_message();
                
                // Проверяем, связана ли ошибка с правами доступа
                if (strpos($error_message, 'Доступ запрещён') !== false || 
                    strpos($error_message, 'только пользователь с правами администратора') !== false) {
                    
                    $this->logger->warning('Для создания атрибутов в МойСклад требуются права администратора');
                    
                    // Возвращаем информацию о необходимости админских прав
                    return array(
                        'used_bonus_id' => get_option('woo_moysklad_bonus_used_attribute_id', ''),
                        'earned_bonus_id' => get_option('woo_moysklad_bonus_earned_attribute_id', ''),
                        'balance_bonus_id' => get_option('woo_moysklad_bonus_balance_attribute_id', ''),
                        'message' => __('Для создания атрибутов бонусов требуются права администратора в МойСклад. Будут использованы существующие атрибуты, если они доступны.', 'woo-moysklad-integration'),
                        'requires_admin' => true
                    );
                }
            }
            
            // Создаем атрибут для использованных бонусов
            $used_bonus = $this->create_bonus_attribute(
                'customerorder',
                'Использовано бонусов',
                'long'
            );
            
            // Создаем атрибут для начисленных бонусов
            $earned_bonus = $this->create_bonus_attribute(
                'customerorder',
                'Начислено бонусов',
                'long'
            );
            
            // Создаем атрибут для баланса бонусов
            $balance_bonus = $this->create_bonus_attribute(
                'counterparty',
                'Баланс бонусов',
                'long'
            );
            
            $this->logger->info('Регистрация атрибутов бонусов в МойСклад завершена успешно', array(
                'used_bonus_id' => $used_bonus['id'],
                'earned_bonus_id' => $earned_bonus['id'],
                'balance_bonus_id' => $balance_bonus['id']
            ));
            
            return array(
                'used_bonus_id' => $used_bonus['id'],
                'earned_bonus_id' => $earned_bonus['id'],
                'balance_bonus_id' => $balance_bonus['id'],
                'message' => __('Атрибуты бонусов успешно созданы в МойСклад', 'woo-moysklad-integration')
            );
            
        } catch (Exception $e) {
            $this->logger->error('Ошибка при регистрации атрибутов бонусов в МойСклад: ' . $e->getMessage());
            
            // Если ошибка связана с правами доступа, возвращаем более понятное сообщение
            if (strpos($e->getMessage(), 'Доступ запрещён') !== false || 
                strpos($e->getMessage(), 'только пользователь с правами администратора') !== false) {
                return new WP_Error(
                    'admin_rights_required', 
                    __('Для создания атрибутов бонусов требуются права администратора в МойСклад. Используйте учетную запись с правами администратора.', 'woo-moysklad-integration')
                );
            }
            
            return new WP_Error('attribute_creation_failed', $e->getMessage());
        }
    }
    
    /**
     * Создает атрибут в МойСклад для бонусной системы
     * 
     * @since    1.0.0
     * @param    string    $entity_type    Тип сущности (customerorder или counterparty)
     * @param    string    $name           Название атрибута
     * @param    string    $type           Тип атрибута (string, long, etc.)
     * @param    string    $id             ID атрибута (опционально)
     * @return   array|WP_Error            Результат операции
     */
    private function create_bonus_attribute($entity_type, $name, $type, $id = null) {
        // Правильный путь для атрибутов сущностей в API МойСклад
        $endpoint = "/entity/$entity_type/metadata/attributes";
        
        // Проверяем, существует ли уже атрибут с таким именем
        $attributes = $this->api->request($endpoint);
        
        if (is_wp_error($attributes)) {
            throw new Exception($attributes->get_error_message());
        }
        
        if (isset($attributes['rows']) && is_array($attributes['rows'])) {
            foreach ($attributes['rows'] as $attribute) {
                if ($attribute['name'] === $name) {
                    $this->logger->info("Атрибут '$name' для '$entity_type' уже существует", array(
                        'attribute_id' => $attribute['id']
                    ));
                    return $attribute;
                }
            }
        }
        
        // Создаем новый атрибут
        $data = array(
            'name' => $name,
            'type' => $type,
            'required' => false
        );
        
        if ($id !== null) {
            $data['id'] = $id;
        }
        
        $result = $this->api->request($endpoint, 'POST', $data);
        
        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }
        
        $this->logger->info("Создан новый атрибут '$name' для '$entity_type'", array(
            'attribute_id' => $result['id']
        ));
        
        return $result;
    }
}