<?php
/**
 * Logger
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */

/**
 * Logger class.
 *
 * This class handles logging for the plugin.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/includes
 */
class Woo_Moysklad_Logger {

    /**
     * Log levels.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $levels    Log levels.
     */
    private $levels = array(
        'emergency' => 0,
        'alert'     => 1,
        'critical'  => 2,
        'error'     => 3,
        'warning'   => 4,
        'notice'    => 5,
        'info'      => 6,
        'debug'     => 7,
    );
    
    /**
     * Constructor.
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Nothing to initialize
    }
    
    /**
     * Log a message.
     *
     * @since    1.0.0
     * @param    string    $level      The log level.
     * @param    string    $message    The log message.
     * @param    array     $context    Additional context.
     * @return   bool                  Whether the log was successful.
     */
    public function log($level, $message, $context = array()) {
        if (!in_array($level, array_keys($this->levels))) {
            $level = 'info';
        }
        
        $log_level = get_option('woo_moysklad_log_level', 'info');
        
        // Check if we should log this message based on log level
        if ($this->levels[$level] > $this->levels[$log_level]) {
            return false;
        }
        
        $log_to_file = get_option('woo_moysklad_log_to_file', '0');
        $log_to_db = get_option('woo_moysklad_log_to_db', '1');
        
        // Format context
        $context_str = empty($context) ? '' : json_encode($context);
        
        // Log to database
        if ($log_to_db === '1') {
            $this->log_to_database($level, $message, $context_str);
        }
        
        // Log to file
        if ($log_to_file === '1') {
            $this->log_to_file($level, $message, $context_str);
        }
        
        return true;
    }
    
    /**
     * Log to database.
     *
     * @since    1.0.0
     * @param    string    $level        The log level.
     * @param    string    $message      The log message.
     * @param    string    $context_str  The serialized context.
     */
    private function log_to_database($level, $message, $context_str) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'woo_moysklad_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'timestamp' => current_time('mysql'),
                'level'     => $level,
                'message'   => $message,
                'context'   => $context_str,
            )
        );
        
        // Clean up old logs (older than 30 days)
        $days_to_keep = apply_filters('woo_moysklad_logs_days_to_keep', 30);
        $timestamp = gmdate('Y-m-d H:i:s', time() - ($days_to_keep * DAY_IN_SECONDS));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE timestamp < %s",
            $timestamp
        ));
    }
    
    /**
     * Log to file.
     *
     * @since    1.0.0
     * @param    string    $level        The log level.
     * @param    string    $message      The log message.
     * @param    string    $context_str  The serialized context.
     */
    private function log_to_file($level, $message, $context_str) {
        $log_dir = WP_CONTENT_DIR . '/uploads/woo-moysklad-logs';
        
        // Create log directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            
            // Create .htaccess file to protect logs
            $htaccess_file = $log_dir . '/.htaccess';
            if (!file_exists($htaccess_file)) {
                $htaccess_content = "Order Deny,Allow\nDeny from all";
                file_put_contents($htaccess_file, $htaccess_content);
            }
            
            // Create index.php file
            $index_file = $log_dir . '/index.php';
            if (!file_exists($index_file)) {
                $index_content = "<?php\n// Silence is golden.";
                file_put_contents($index_file, $index_content);
            }
        }
        
        $date = date('Y-m-d');
        $log_file = $log_dir . '/moysklad-' . $date . '.log';
        
        $timestamp = date('Y-m-d H:i:s');
        $formatted_message = "[$timestamp] [$level] $message";
        
        if (!empty($context_str)) {
            $formatted_message .= " - Context: $context_str";
        }
        
        $formatted_message .= PHP_EOL;
        
        // Append to log file
        file_put_contents($log_file, $formatted_message, FILE_APPEND);
    }
    
    /**
     * Log a debug message.
     *
     * @since    1.0.0
     * @param    string    $message    The log message.
     * @param    array     $context    Additional context.
     * @return   bool                  Whether the log was successful.
     */
    public function debug($message, $context = array()) {
        return $this->log('debug', $message, $context);
    }
    
    /**
     * Log an info message.
     *
     * @since    1.0.0
     * @param    string    $message    The log message.
     * @param    array     $context    Additional context.
     * @return   bool                  Whether the log was successful.
     */
    public function info($message, $context = array()) {
        return $this->log('info', $message, $context);
    }
    
    /**
     * Log a notice message.
     *
     * @since    1.0.0
     * @param    string    $message    The log message.
     * @param    array     $context    Additional context.
     * @return   bool                  Whether the log was successful.
     */
    public function notice($message, $context = array()) {
        return $this->log('notice', $message, $context);
    }
    
    /**
     * Log a warning message.
     *
     * @since    1.0.0
     * @param    string    $message    The log message.
     * @param    array     $context    Additional context.
     * @return   bool                  Whether the log was successful.
     */
    public function warning($message, $context = array()) {
        return $this->log('warning', $message, $context);
    }
    
    /**
     * Log an error message.
     *
     * @since    1.0.0
     * @param    string    $message    The log message.
     * @param    array     $context    Additional context.
     * @return   bool                  Whether the log was successful.
     */
    public function error($message, $context = array()) {
        return $this->log('error', $message, $context);
    }
    
    /**
     * Log a critical message.
     *
     * @since    1.0.0
     * @param    string    $message    The log message.
     * @param    array     $context    Additional context.
     * @return   bool                  Whether the log was successful.
     */
    public function critical($message, $context = array()) {
        return $this->log('critical', $message, $context);
    }
    
    /**
     * Log an alert message.
     *
     * @since    1.0.0
     * @param    string    $message    The log message.
     * @param    array     $context    Additional context.
     * @return   bool                  Whether the log was successful.
     */
    public function alert($message, $context = array()) {
        return $this->log('alert', $message, $context);
    }
    
    /**
     * Log an emergency message.
     *
     * @since    1.0.0
     * @param    string    $message    The log message.
     * @param    array     $context    Additional context.
     * @return   bool                  Whether the log was successful.
     */
    public function emergency($message, $context = array()) {
        return $this->log('emergency', $message, $context);
    }
    
    /**
     * Get logs from the database.
     *
     * @since    1.0.0
     * @param    int       $limit     Number of logs to retrieve.
     * @param    int       $offset    Offset for pagination.
     * @param    string    $level     Filter by log level.
     * @return   array                Array of logs.
     */
    public function get_logs($limit = 100, $offset = 0, $level = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'woo_moysklad_logs';
        
        $sql = "SELECT * FROM $table_name";
        $args = array();
        
        if (!empty($level)) {
            $sql .= " WHERE level = %s";
            $args[] = $level;
        }
        
        $sql .= " ORDER BY timestamp DESC LIMIT %d OFFSET %d";
        $args[] = $limit;
        $args[] = $offset;
        
        if (!empty($args)) {
            $sql = $wpdb->prepare($sql, $args);
        }
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        return $results;
    }
    
    /**
     * Get log count.
     *
     * @since    1.0.0
     * @param    string    $level    Filter by log level.
     * @return   int                 Count of logs.
     */
    public function get_log_count($level = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'woo_moysklad_logs';
        
        $sql = "SELECT COUNT(*) FROM $table_name";
        
        if (!empty($level)) {
            $sql = $wpdb->prepare("$sql WHERE level = %s", $level);
        }
        
        return (int)$wpdb->get_var($sql);
    }
    
    /**
     * Clear logs.
     *
     * @since    1.0.0
     * @return   int    Number of rows deleted.
     */
    public function clear_logs() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'woo_moysklad_logs';
        
        return $wpdb->query("TRUNCATE TABLE $table_name");
    }
}
