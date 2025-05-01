<?php
/**
 * MoySklad API Handler
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */

/**
 * MoySklad API Handler class.
 *
 * This class handles all API requests to the MoySklad REST API.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */
class Woo_Moysklad_API {

    /**
     * API base URL.
     *
     * @since    1.0.0
     * @access   public
     * @var      string    $api_base    The base URL for the MoySklad API.
     */
    public $api_base = 'https://api.moysklad.ru/api/remap/1.2';
    
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
     * @param    Woo_Moysklad_Logger    $logger    Logger instance.
     */
    public function __construct($logger) {
        $this->logger = $logger;
    }
    
    /**
     * Get API token.
     *
     * @since    1.0.0
     * @return   string    The API token.
     */
    private function get_api_token() {
        return get_option('woo_moysklad_api_token', '');
    }
    
    /**
     * Get API login and password.
     *
     * @since    1.0.0
     * @return   array    The API login and password.
     */
    private function get_credentials() {
        return array(
            'login' => get_option('woo_moysklad_api_login', ''),
            'password' => get_option('woo_moysklad_api_password', ''),
        );
    }
    
    /**
     * Check if API is configured.
     *
     * @since    1.0.0
     * @return   boolean    Whether API token or login/password is set.
     */
    public function is_configured() {
        $token = $this->get_api_token();
        if (!empty($token)) {
            return true;
        }
        
        $credentials = $this->get_credentials();
        return !empty($credentials['login']) && !empty($credentials['password']);
    }
    
    /**
     * Make an API request to MoySklad.
     *
     * @since    1.0.0
     * @param    string    $endpoint    The API endpoint.
     * @param    string    $method      The HTTP method (GET, POST, PUT, DELETE).
     * @param    array     $data        The data to send with the request.
     * @return   array|WP_Error         The response or error.
     */
    /**
     * Make an API request to MoySklad with automatic token refresh.
     *
     * @since    1.0.0
     * @param    string    $endpoint    The API endpoint.
     * @param    string    $method      The HTTP method (GET, POST, PUT, DELETE).
     * @param    array     $data        The data to send with the request.
     * @param    bool      $retry       Whether this is a retry after token error.
     * @return   array|WP_Error         The response or error.
     */
    public function request($endpoint, $method = 'GET', $data = array(), $retry = false) {
        if (!$this->is_configured()) {
            $this->logger->error('API не настроен');
            return new WP_Error('api_not_configured', __('Настройки API МойСклад не заданы. Пожалуйста, укажите токен API или логин/пароль', 'woo-moysklad-integration'));
        }
        
        $url = $this->api_base . $endpoint;
        
        $headers = array(
            'Content-Type'  => 'application/json;charset=utf-8',
            'Accept'        => 'application/json;charset=utf-8',
        );
        
        $token = $this->get_api_token();
        if (!empty($token) && !$retry) {
            // Используем токен, если он задан и это не повторная попытка после ошибки
            $headers['Authorization'] = 'Bearer ' . $token;
        } else {
            // Иначе используем логин и пароль
            $credentials = $this->get_credentials();
            if (!empty($credentials['login']) && !empty($credentials['password'])) {
                $headers['Authorization'] = 'Basic ' . base64_encode($credentials['login'] . ':' . $credentials['password']);
            } else if ($retry) {
                $this->logger->error('Не удалось повторить запрос: отсутствуют логин/пароль для бэкапа');
                return new WP_Error('auth_failed', __('Ошибка аутентификации. Токен недействителен, а логин/пароль не настроены.', 'woo-moysklad-integration'));
            }
        }
        
        $args = array(
            'method'    => $method,
            'timeout'   => 45,
            'headers'   => $headers,
            'sslverify' => true,
        );
        
        if (!empty($data) && in_array($method, array('POST', 'PUT'))) {
            $args['body'] = json_encode($data);
        }
        
        $this->logger->debug("Making API request: $method $url", array('data' => $data));
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $this->logger->error('API request error: ' . $response->get_error_message(), array('endpoint' => $endpoint));
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        // Если получили ошибку авторизации и еще не было повторной попытки,
        // попробуем использовать другой метод авторизации
        if ($response_code == 401 && !$retry && !empty($token)) {
            $this->logger->info('Токен просрочен или недействителен, пробуем логин/пароль');
            return $this->request($endpoint, $method, $data, true);
        }
        
        if ($response_code >= 400) {
            $error_message = isset($response_data['errors'][0]['error']) 
                ? $response_data['errors'][0]['error'] 
                : __('Неизвестная ошибка API', 'woo-moysklad-integration');
            
            // Добавляем более подробную информацию для конкретных ошибок
            $error_code = isset($response_data['errors'][0]['code']) ? $response_data['errors'][0]['code'] : 0;
            $more_info = isset($response_data['errors'][0]['moreInfo']) ? $response_data['errors'][0]['moreInfo'] : '';
            
            // Обработка известных ошибок
            switch ($error_code) {
                case 1005:
                    $error_details = __('Неверный тип метаданных. Возможно, API МойСклад был изменен. Проверьте документацию МойСклад.', 'woo-moysklad-integration');
                    break;
                case 1062:
                    $error_details = __('Ошибка в заголовках запроса. Проверьте настройки API.', 'woo-moysklad-integration');
                    break;
                case 1056:
                    $error_details = __('Ошибка аутентификации. Проверьте токен API или логин/пароль.', 'woo-moysklad-integration');
                    break;
                case 1034:
                    $error_details = __('Ошибка фильтрации: ' . $error_message . '. Дополнительная информация: ' . $more_info, 'woo-moysklad-integration');
                    break;
                case 1002:
                    $error_details = __('Неопознанный путь: ' . $error_message . '. Дополнительная информация: ' . $more_info, 'woo-moysklad-integration');
                    break;
                case 1998:
                    $error_details = __('JSON API через указанный URL больше не поддерживается: ' . $error_message, 'woo-moysklad-integration');
                    break;
                case 14010:
                    $error_details = __('Доступ запрещён: ' . $error_message . '. Дополнительная информация: ' . $more_info, 'woo-moysklad-integration');
                    break;
                default:
                    $error_details = isset($response_data['errors'][0]['moreInfo']) 
                        ? __('Дополнительная информация: ', 'woo-moysklad-integration') . $response_data['errors'][0]['moreInfo']
                        : '';
            }
            
            if (!empty($error_details)) {
                $error_message = $error_details;
            }
                
            $this->logger->error("API error: $error_message", array(
                'endpoint' => $endpoint,
                'response_code' => $response_code,
                'response' => $response_data
            ));
            
            return new WP_Error('api_error', $error_message, array(
                'response_code' => $response_code,
                'response' => $response_data,
                'error_code' => $error_code
            ));
        }
        
        return $response_data;
    }
    
    /**
     * Get all products from MoySklad.
     *
     * @since    1.0.0
     * @param    int       $limit       Limit of products to retrieve.
     * @param    int       $offset      Offset for pagination.
     * @return   array|WP_Error         The products or error.
     */
    /**
     * Get products from MoySklad with improved debugging.
     *
     * @since    1.0.0
     * @param    int       $limit    The number of products to retrieve.
     * @param    int       $offset   The offset for pagination.
     * @return   array|WP_Error     The products or error.
     */
    public function get_products($limit = 100, $offset = 0) {
        $this->logger->info("Получение товаров из МойСклад: лимит=$limit, смещение=$offset");
        $endpoint = "/entity/product?limit=$limit&offset=$offset&expand=images,attributes,productFolder";
        
        $response = $this->request($endpoint);
        
        if (is_wp_error($response)) {
            $this->logger->error("Ошибка при получении товаров из МойСклад: " . $response->get_error_message());
            return $response;
        }
        
        // Логируем количество полученных товаров
        if (isset($response['rows'])) {
            $this->logger->info("Успешно получены товары из МойСклад: " . count($response['rows']) . " из " . $response['meta']['size']);
            
            // Добавляем дополнительную информацию по первым нескольким товарам для отладки
            $sample_count = min(3, count($response['rows']));
            for ($i = 0; $i < $sample_count; $i++) {
                $product = $response['rows'][$i];
                $this->logger->debug("Образец товара #" . ($i+1) . ": " . 
                    "ID=" . $product['id'] . ", " .
                    "Название=" . $product['name'] . ", " .
                    "Код=" . (isset($product['code']) ? $product['code'] : 'Отсутствует'));
            }
        } else {
            $this->logger->warning("Получен ответ от МойСклад, но в нем отсутствует элемент 'rows'");
        }
        
        return $response;
    }
    
    /**
     * Get a single product from MoySklad by ID.
     *
     * @since    1.0.0
     * @param    string    $product_id  The product ID.
     * @return   array|WP_Error         The product or error.
     */
    public function get_product($product_id) {
        $endpoint = "/entity/product/$product_id?expand=images,attributes,productFolder";
        return $this->request($endpoint);
    }
    
    /**
     * Get product variants.
     *
     * @since    1.0.0
     * @param    string    $product_id  The product ID.
     * @return   array|WP_Error         The variants or error.
     */
    public function get_product_variants($product_id) {
        $endpoint = "/entity/variant?filter=productid=$product_id&expand=characteristics";
        return $this->request($endpoint);
    }
    
    /**
     * Get all product folders (categories).
     *
     * @since    1.0.0
     * @return   array|WP_Error         The product folders or error.
     */
    public function get_product_folders() {
        $endpoint = "/entity/productfolder?limit=100";
        return $this->request($endpoint);
    }
    
    /**
     * Get stock for a product.
     *
     * @since    1.0.0
     * @param    string    $product_id      The product ID.
     * @param    string    $warehouse_id    The warehouse ID (optional).
     * @return   array|WP_Error             The stock information or error.
     */
    /**
     * Get stock for a product with rate limiting.
     *
     * @since    1.0.0
     * @param    string    $product_id      The product ID.
     * @param    string    $warehouse_id    The warehouse ID (optional).
     * @return   array|WP_Error             The stock information or error.
     */
    /**
     * Get stock for a product with optimized request handling for MoySklad API.
     *
     * @since    1.0.0
     * @param    string    $product_id      The product ID.
     * @param    string    $warehouse_id    The warehouse ID (optional).
     * @return   array                      The stock information in a standardized format.
     */
    public function get_product_stock($product_id, $warehouse_id = '') {
        // На основе актуальной документации МойСклад от апреля 2025
        // При превышении лимита запросов необходимо сократить количество попыток и увеличить интервал
        
        // Согласно документации, самые надежные эндпоинты для остатков:
        // https://dev.moysklad.ru/doc/api/remap/1.2/отчеты/отчет-остатки/
        
        // Формируем базовый ответ в случае ошибки
        $empty_response = array(
            'rows' => array(),
            'meta' => array(
                'size' => 0,
                'limit' => 0,
                'offset' => 0
            )
        );
        
        // Используем путь, который 100% работает согласно документации МойСклад
        $endpoint = "/report/stock/all";
        
        // Пути в API МойСклад периодически меняются, поэтому оптимальный вариант
        // исходя из последних тестов - использовать только /report/stock/all
        
        // Если есть множество ошибок с лимитами запросов, мы должны избегать повторных попыток
        static $api_limit_reached = false;
        
        if ($api_limit_reached) {
            $this->logger->warning("Лимит API уже достигнут, пропускаем запрос остатков для товара $product_id");
            return $empty_response;
        }
        
        // Проверка последнего времени запроса для ограничения частоты
        static $last_request_time = 0;
        $current_time = microtime(true);
        
        if ($current_time - $last_request_time < 1) {
            // Если прошло менее 1 секунды с последнего запроса, добавляем задержку
            $delay = 1 - ($current_time - $last_request_time);
            $this->logger->debug("Добавляем задержку {$delay}s для соблюдения лимитов API");
            usleep($delay * 1000000);
        }
        
        $last_request_time = microtime(true);
        
        // Формируем запрос с минимальным количеством параметров
        $query_params = array("filter=assortment=$product_id");
        
        // Добавляем склад, если указан
        if (!empty($warehouse_id)) {
            $query_params[] = "stockstore=$warehouse_id";
        }
        
        $request_url = $endpoint . "?" . implode("&", $query_params);
        $this->logger->info("Запрос остатков: $request_url");
        
        // Добавляем повторные попытки с экспоненциальной задержкой
        $max_retries = 3;
        $retry_delay = 1; // Начальная задержка 1 секунда
        $success = false;
        $response = null;
        
        for ($retry = 0; $retry < $max_retries && !$success; $retry++) {
            if ($retry > 0) {
                $this->logger->info("Повторная попытка {$retry} запроса остатков с задержкой {$retry_delay}с");
                sleep($retry_delay);
                $retry_delay *= 2; // Увеличиваем задержку в 2 раза с каждой попыткой
            }
            
            $response = $this->request($request_url);
            
            if (!is_wp_error($response)) {
                $success = true;
                break;
            } elseif (strpos($response->get_error_message(), '1049') !== false) {
                // Если превышен лимит запросов, делаем более длительную паузу
                $this->logger->warning("Достигнут лимит API запросов, увеличиваем задержку");
                $retry_delay = max($retry_delay, 5); // Минимум 5 секунд при превышении лимита
            }
        }
        
        // Обработка ответа после всех попыток
        if (!$success) {
            $error_message = $response->get_error_message();
            
            // Проверка на превышение лимита запросов
            if (strpos($error_message, '1049') !== false) {
                $api_limit_reached = true;
                $this->logger->error("Достигнут лимит API запросов для получения остатков. Дальнейшие запросы будут пропущены.");
            } else {
                $this->logger->error("Ошибка при получении остатков для товара $product_id после $max_retries попыток: $error_message");
            }
            
            return $empty_response;
        }
        
        // Если получен ответ без ошибок, но без нужной структуры
        if (!isset($response['rows'])) {
            $this->logger->warning("Получен некорректный формат ответа от API для остатков товара $product_id");
            return $empty_response;
        }
        
        // Фильтруем результаты, если запрос вернул все товары
        $filtered_rows = array();
        foreach ($response['rows'] as $row) {
            if (isset($row['assortment']) && isset($row['assortment']['meta']) &&
                isset($row['assortment']['meta']['href']) && 
                strpos($row['assortment']['meta']['href'], $product_id) !== false) {
                
                // Фильтруем по складу, если указан
                if (empty($warehouse_id) || 
                    (isset($row['stockStore']) && isset($row['stockStore']['meta']) && 
                     strpos($row['stockStore']['meta']['href'], $warehouse_id) !== false)) {
                    $filtered_rows[] = $row;
                }
            }
        }
        
        // Если найдены соответствующие записи, возвращаем их
        if (!empty($filtered_rows)) {
            $response['rows'] = $filtered_rows;
            $this->logger->info("Успешно получены и отфильтрованы остатки для товара $product_id");
            return $response;
        }
        
        // Если после фильтрации ничего не найдено, возвращаем пустую структуру
        $this->logger->warning("Остатки для товара $product_id не найдены после фильтрации");
        return $empty_response;
    }
    
    /**
     * Get stock for multiple products.
     *
     * @since    1.0.0
     * @param    array     $product_ids     Array of product IDs.
     * @param    string    $warehouse_id    The warehouse ID (optional).
     * @return   array|WP_Error             The stock information or error.
     */
    /**
     * Get stock for multiple products with optimized API requests.
     *
     * @since    1.0.0
     * @param    array     $product_ids     Array of product IDs.
     * @param    string    $warehouse_id    The warehouse ID (optional).
     * @return   array                      The stock information in a standardized format.
     */
    public function get_stock_batch($product_ids, $warehouse_id = '') {
        if (empty($product_ids)) {
            return array(
                'rows' => array(),
                'meta' => array(
                    'size' => 0,
                    'limit' => 0,
                    'offset' => 0
                )
            );
        }
        
        // Проверяем лимит API, если он достигнут, то прекращаем запросы
        static $api_limit_reached = false;
        if ($api_limit_reached) {
            $this->logger->warning("Лимит API уже достигнут, пропускаем пакетный запрос остатков");
            return array(
                'rows' => array(),
                'meta' => array(
                    'size' => 0,
                    'limit' => 0,
                    'offset' => 0
                )
            );
        }
        
        // Вместо запроса остатков для каждого продукта (что приводит к достижению лимита API),
        // получаем остатки для всех товаров одним запросом и потом фильтруем
        $this->logger->info("Запрашиваем остатки для " . count($product_ids) . " товаров общим запросом");
        
        // Используем путь, который 100% работает согласно документации МойСклад
        $endpoint = "/report/stock/all";
        
        // Выполняем паузу для соблюдения лимита API
        static $last_request_time = 0;
        $current_time = microtime(true);
        
        if ($current_time - $last_request_time < 2) {
            // Если с предыдущего запроса прошло меньше 2 секунд, добавляем задержку
            $delay = 2 - ($current_time - $last_request_time);
            $this->logger->debug("Добавляем задержку {$delay}s для соблюдения лимитов API при пакетном запросе");
            usleep($delay * 1000000);
        }
        
        $last_request_time = microtime(true);
        
        // Выполняем запрос всех остатков без фильтрации
        // Из-за ограничений API МойСклад на сложные запросы фильтрации, 
        // проще получить все остатки и профильтровать локально
        $response = $this->request($endpoint);
        
        // Если произошла ошибка
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            
            // Проверка на превышение лимита запросов
            if (strpos($error_message, '1049') !== false) {
                $api_limit_reached = true;
                $this->logger->error("Достигнут лимит API запросов для получения остатков. Дальнейшие запросы будут пропущены.");
            } else {
                $this->logger->error("Ошибка при получении остатков для товаров: $error_message");
            }
            
            return array(
                'rows' => array(),
                'meta' => array(
                    'size' => 0,
                    'limit' => 0,
                    'offset' => 0
                )
            );
        }
        
        // Если получен ответ без нужной структуры
        if (!isset($response['rows'])) {
            $this->logger->warning("Получен некорректный формат ответа от API для пакетного запроса остатков");
            return array(
                'rows' => array(),
                'meta' => array(
                    'size' => 0,
                    'limit' => 0,
                    'offset' => 0
                )
            );
        }
        
        // Фильтруем результаты
        $filtered_rows = array();
        foreach ($response['rows'] as $row) {
            if (isset($row['assortment']) && isset($row['assortment']['meta']) && 
                isset($row['assortment']['meta']['href'])) {
                
                // Проверяем, соответствует ли товар одному из запрошенных ID
                $match_found = false;
                foreach ($product_ids as $product_id) {
                    if (strpos($row['assortment']['meta']['href'], $product_id) !== false) {
                        $match_found = true;
                        break;
                    }
                }
                
                if ($match_found) {
                    // Фильтруем по складу, если указан
                    if (empty($warehouse_id) || 
                        (isset($row['stockStore']) && isset($row['stockStore']['meta']) && 
                         strpos($row['stockStore']['meta']['href'], $warehouse_id) !== false)) {
                        $filtered_rows[] = $row;
                    }
                }
            }
        }
        
        // Если найдены соответствующие записи, возвращаем их
        if (!empty($filtered_rows)) {
            $this->logger->info("Успешно отфильтрованы остатки для " . count($filtered_rows) . " позиций из " . count($product_ids) . " запрошенных");
            $response['rows'] = $filtered_rows;
            return $response;
        }
        
        // Если после фильтрации ничего не найдено
        $this->logger->warning("Остатки для запрошенных товаров не найдены после фильтрации");
        return array(
            'rows' => array(),
            'meta' => array(
                'size' => 0,
                'limit' => 0,
                'offset' => 0
            )
        );
    }
    
    /**
     * Create an order in MoySklad.
     *
     * @since    1.0.0
     * @param    array     $order_data  The order data.
     * @return   array|WP_Error         The created order or error.
     */
    public function create_order($order_data) {
        $endpoint = "/entity/customerorder";
        $this->logger->info("Отправка заказа в МойСклад", array(
            'endpoint' => $endpoint,
            'order_data' => $order_data
        ));
        
        // Добавим проверку обязательных полей
        if (empty($order_data['agent']) || empty($order_data['positions']) || empty($order_data['positions'][0])) {
            $this->logger->error("Ошибка в структуре данных заказа", array('order_data' => $order_data));
            return new WP_Error('invalid_order_data', 'Order data is missing required fields');
        }
        
        $response = $this->request($endpoint, 'POST', $order_data);
        
        if (is_wp_error($response)) {
            $this->logger->error("Ошибка создания заказа в МойСклад: " . $response->get_error_message());
        } else {
            $this->logger->info("Заказ успешно создан в МойСклад", array('response' => $response));
        }
        
        return $response;
    }
    
    /**
     * Update an order in MoySklad.
     *
     * @since    1.0.0
     * @param    string    $order_id    The MoySklad order ID.
     * @param    array     $order_data  The order data.
     * @return   array|WP_Error         The updated order or error.
     */
    public function update_order($order_id, $order_data) {
        $endpoint = "/entity/customerorder/$order_id";
        return $this->request($endpoint, 'PUT', $order_data);
    }
    
    /**
     * Create a simple product in MoySklad.
     *
     * @since    1.0.0
     * @param    string    $name           The product name.
     * @param    float     $price          The product price.
     * @param    string    $sku            The product SKU (optional).
     * @param    string    $description    The product description (optional).
     * @return   array|WP_Error           The response from the API or an error object.
     */
    public function create_simple_product($name, $price = 0, $sku = '', $description = '') {
        $this->logger->info("Создание нового товара в МойСклад: $name");
        
        $this->logger->info("Создание товара с параметрами: имя='$name', цена=$price, артикул='$sku'");
        
        // Для выполнения требований МойСклад мы должны указать тип цены,
        // но для начала нам нужно узнать, что доступно в системе
        $this->logger->info("Получение информации о типах цен");
        $price_types = $this->get_price_types();
        
        if (is_wp_error($price_types)) {
            $this->logger->error("Ошибка при получении типов цен: " . $price_types->get_error_message());
            return $price_types;
        }
        
        $this->logger->debug("Полученный ответ по типам цен: " . json_encode($price_types));
        
        // Используем упрощенный формат для указания типа цены
        $product_data = array(
            'name' => $name,
            'code' => $sku,
            'description' => $description,
            'salePrices' => array(
                array(
                    'value' => intval($price * 100), // МойСклад использует копейки
                    'priceType' => array(
                        'name' => 'Цена продажи'
                    )
                )
            )
        );
        
        $this->logger->debug("Данные для создания товара: " . json_encode($product_data));
        
        $response = $this->request('/entity/product', 'POST', $product_data);
        
        if (is_wp_error($response)) {
            $this->logger->error("Ошибка при создании товара в МойСклад: " . $response->get_error_message());
            return $response;
        }
        
        $this->logger->info("Товар успешно создан в МойСклад");
        return $response;
    }
    
    /**
     * Get an order from MoySklad by ID.
     *
     * @since    1.0.0
     * @param    string    $order_id    The MoySklad order ID.
     * @return   array|WP_Error         The order or error.
     */
    public function get_order($order_id) {
        $endpoint = "/entity/customerorder/$order_id?expand=positions,positions.assortment,agent,state";
        return $this->request($endpoint);
    }
    
    /**
     * Find an order by external ID (WooCommerce order ID).
     *
     * @since    1.0.0
     * @param    string    $external_id The external (WooCommerce) order ID.
     * @return   array|WP_Error         The order or error.
     */
    public function find_order_by_external_id($external_id) {
        $endpoint = "/entity/customerorder?filter=externalCode=$external_id";
        return $this->request($endpoint);
    }
    
    /**
     * Get all warehouses.
     *
     * @since    1.0.0
     * @return   array|WP_Error         The warehouses or error.
     */
    public function get_warehouses() {
        $endpoint = "/entity/store";
        return $this->request($endpoint);
    }
    
    /**
     * Get all organizations.
     *
     * @since    1.0.0
     * @return   array|WP_Error         The organizations or error.
     */
    public function get_organizations() {
        $endpoint = "/entity/organization";
        return $this->request($endpoint);
    }
    
    /**
     * Get all customer groups.
     *
     * @since    1.0.0
     * @return   array|WP_Error         The customer groups or error.
     */
    /**
     * Get all customer groups using the verified endpoint.
     *
     * @since    1.0.0
     * @return   array                     The customer groups in a standardized format.
     */
    public function get_customer_groups() {
        // Согласно актуальной документации МойСклад, правильный эндпоинт для групп контрагентов
        $endpoint = "/entity/group";
        
        $this->logger->info("Запрашиваем группы контрагентов через проверенный путь: $endpoint");
        
        // Добавляем повторные попытки с экспоненциальной задержкой для надежности
        $max_retries = 3;
        $retry_delay = 1; // Начальная задержка 1 секунда
        $success = false;
        $response = null;
        
        for ($retry = 0; $retry < $max_retries && !$success; $retry++) {
            if ($retry > 0) {
                $this->logger->info("Повторная попытка {$retry} запроса групп контрагентов с задержкой {$retry_delay}с");
                sleep($retry_delay);
                $retry_delay *= 2; // Увеличиваем задержку в 2 раза с каждой попыткой
            }
            
            $response = $this->request($endpoint);
            
            if (!is_wp_error($response)) {
                $success = true;
                break;
            } elseif (strpos($response->get_error_message(), '1049') !== false) {
                // Если превышен лимит запросов, делаем более длительную паузу
                $this->logger->warning("Достигнут лимит API запросов, увеличиваем задержку");
                $retry_delay = max($retry_delay, 5); // Минимум 5 секунд при превышении лимита
            }
        }
        
        // Обработка ответа после всех попыток
        if (!$success) {
            $this->logger->error('Ошибка при получении групп контрагентов после нескольких попыток: ' . $response->get_error_message());
            return array('rows' => array());
        }
        
        // Проверяем формат ответа
        if (isset($response['rows']) && !empty($response['rows'])) {
            $this->logger->info("Успешно получены " . count($response['rows']) . " групп контрагентов");
            return $response;
        } elseif (isset($response['groups']) && !empty($response['groups'])) {
            // Преобразуем формат ответа, чтобы он соответствовал ожидаемому (rows)
            $this->logger->info("Успешно получены группы контрагентов в формате groups, преобразуем");
            $response['rows'] = $response['groups'];
            return $response;
        }
        
        // Если формат ответа непредвиденный
        $this->logger->warning('Получен ответ от API, но в неожиданном формате при запросе групп контрагентов');
        return array('rows' => array());
    }
    
    /**
     * Find or create a customer.
     *
     * @since    1.0.0
     * @param    array     $customer_data   The customer data.
     * @return   array|WP_Error             The customer or error.
     */
    public function find_or_create_customer($customer_data) {
        // Check if customer exists by phone or email
        $filter = '';
        
        if (!empty($customer_data['phone'])) {
            $filter = "phone=" . urlencode($customer_data['phone']);
        } elseif (!empty($customer_data['email'])) {
            $filter = "email=" . urlencode($customer_data['email']);
        } else {
            return new WP_Error('customer_data_missing', __('Phone or email is required to find/create customer', 'woo-moysklad-integration'));
        }
        
        $endpoint = "/entity/counterparty?filter=$filter";
        $response = $this->request($endpoint);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // If customer exists, return it
        if (!empty($response['rows'])) {
            return $response['rows'][0];
        }
        
        // Otherwise, create a new customer
        $endpoint = "/entity/counterparty";
        return $this->request($endpoint, 'POST', $customer_data);
    }
    
    /**
     * Get all order states.
     *
     * @since    1.0.0
     * @return   array|WP_Error         The order states or error.
     */
    public function get_order_states() {
        $endpoint = "/entity/customerorder/metadata";
        $response = $this->request($endpoint);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['states']) ? $response['states'] : array();
    }
    
    /**
     * Register a webhook in MoySklad.
     *
     * @since    1.0.0
     * @param    string    $entity_type The entity type for the webhook.
     * @param    string    $action      The action for the webhook (CREATE, UPDATE, DELETE).
     * @param    string    $url         The callback URL for the webhook.
     * @return   array|WP_Error         The webhook or error.
     */
    /**
     * Register a webhook in MoySklad with error handling for permission issues.
     *
     * @since    1.0.0
     * @param    string    $entity_type The entity type for the webhook.
     * @param    string    $action      The action for the webhook (CREATE, UPDATE, DELETE).
     * @param    string    $url         The callback URL for the webhook.
     * @return   array|WP_Error         The webhook or error.
     */
    public function register_webhook($entity_type, $action, $url) {
        $endpoint = "/entity/webhook";
        $data = array(
            'entityType' => $entity_type,
            'action' => $action,
            'url' => $url,
            'enabled' => true,
        );
        
        $response = $this->request($endpoint, 'POST', $data);
        
        // Проверяем, есть ли ошибка 403 (Доступ запрещен) - вебхуки требуют прав администратора
        if (is_wp_error($response) && 
            (strpos($response->get_error_message(), '403') !== false || 
             strpos($response->get_error_message(), '30004') !== false)) {
            
            $this->logger->error('Failed to register webhooks: ' . $response->get_error_message());
            
            // Создаем информативное сообщение об ошибке вместо просто передачи ошибки API
            return new WP_Error(
                'webhook_permission_error',
                __('Для регистрации вебхуков требуются права администратора в МойСклад. Пожалуйста, используйте учетную запись с правами администратора или обратитесь к администратору системы.', 'woo-moysklad-integration'),
                $response->get_error_data()
            );
        }
        
        return $response;
    }
    
    /**
     * Get all registered webhooks.
     *
     * @since    1.0.0
     * @return   array|WP_Error         The webhooks or error.
     */
    public function get_webhooks() {
        $endpoint = "/entity/webhook";
        return $this->request($endpoint);
    }
    
    /**
     * Get all price types.
     *
     * @since    1.0.0
     * @return   array|WP_Error         The price types or error.
     */
    public function get_price_types() {
        $endpoint = "/context/companysettings/pricetype";
        return $this->request($endpoint);
    }
}
