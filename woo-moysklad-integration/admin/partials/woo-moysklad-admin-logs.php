<?php
/**
 * Logs admin template.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get logger instance
$logger = $this->plugin->get_logger();

// Get log levels for filter
$log_levels = array(
    'emergency' => __('Emergency', 'woo-moysklad-integration'),
    'alert'     => __('Alert', 'woo-moysklad-integration'),
    'critical'  => __('Critical', 'woo-moysklad-integration'),
    'error'     => __('Error', 'woo-moysklad-integration'),
    'warning'   => __('Warning', 'woo-moysklad-integration'),
    'notice'    => __('Notice', 'woo-moysklad-integration'),
    'info'      => __('Info', 'woo-moysklad-integration'),
    'debug'     => __('Debug', 'woo-moysklad-integration'),
);

// Get log settings
$log_to_db = get_option('woo_moysklad_log_to_db', '1');
$log_to_file = get_option('woo_moysklad_log_to_file', '0');
$log_level = get_option('woo_moysklad_log_level', 'info');
?>

<div class="wrap woo-moysklad-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="subheader">
        <?php _e('View logs of plugin activity and synchronization operations.', 'woo-moysklad-integration'); ?>
    </div>
    
    <div class="woo-moysklad-card">
        <div class="woo-moysklad-logs-header">
            <h2><?php _e('Plugin Logs', 'woo-moysklad-integration'); ?></h2>
            
            <div class="woo-moysklad-logs-filter">
                <label for="log-level-filter"><?php _e('Filter by level:', 'woo-moysklad-integration'); ?></label>
                <select id="log-level-filter">
                    <option value=""><?php _e('All Levels', 'woo-moysklad-integration'); ?></option>
                    <?php foreach ($log_levels as $level_key => $level_name) : ?>
                        <option value="<?php echo esc_attr($level_key); ?>">
                            <?php echo esc_html($level_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button id="clear-logs-button" class="button button-secondary">
                    <?php _e('Clear Logs', 'woo-moysklad-integration'); ?>
                </button>
            </div>
        </div>
        
        <?php if ($log_to_db !== '1') : ?>
            <div class="notice notice-warning inline">
                <p>
                    <?php _e('Logging to database is currently disabled. No logs will be shown here.', 'woo-moysklad-integration'); ?>
                    <a href="<?php echo admin_url('admin.php?page=woo-moysklad-settings#logs-tab'); ?>">
                        <?php _e('Enable in settings', 'woo-moysklad-integration'); ?>
                    </a>
                </p>
            </div>
        <?php endif; ?>
        
        <table id="woo-moysklad-logs-table" class="woo-moysklad-logs-table">
            <thead>
                <tr>
                    <th><?php _e('Time', 'woo-moysklad-integration'); ?></th>
                    <th><?php _e('Level', 'woo-moysklad-integration'); ?></th>
                    <th><?php _e('Message', 'woo-moysklad-integration'); ?></th>
                    <th><?php _e('Context', 'woo-moysklad-integration'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="4"><?php _e('Loading logs...', 'woo-moysklad-integration'); ?></td>
                </tr>
            </tbody>
        </table>
        
        <div id="woo-moysklad-logs-pagination"></div>
    </div>
    
    <div class="woo-moysklad-card">
        <h2><?php _e('Log Settings', 'woo-moysklad-integration'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th><?php _e('Current Log Level', 'woo-moysklad-integration'); ?></th>
                <td>
                    <span class="log-level-<?php echo esc_attr($log_level); ?>">
                        <?php echo esc_html($log_levels[$log_level]); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th><?php _e('Log to Database', 'woo-moysklad-integration'); ?></th>
                <td>
                    <?php 
                    if ($log_to_db === '1') {
                        _e('Enabled', 'woo-moysklad-integration');
                    } else {
                        _e('Disabled', 'woo-moysklad-integration');
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th><?php _e('Log to File', 'woo-moysklad-integration'); ?></th>
                <td>
                    <?php 
                    if ($log_to_file === '1') {
                        _e('Enabled', 'woo-moysklad-integration');
                        
                        // Check if log directory exists and is writable
                        $log_dir = WP_CONTENT_DIR . '/uploads/woo-moysklad-logs';
                        if (!file_exists($log_dir)) {
                            echo ' - <span class="log-level-warning">';
                            _e('Log directory does not exist yet. It will be created when the first log is written.', 'woo-moysklad-integration');
                            echo '</span>';
                        } elseif (!is_writable($log_dir)) {
                            echo ' - <span class="log-level-error">';
                            _e('Log directory is not writable!', 'woo-moysklad-integration');
                            echo '</span>';
                        } else {
                            echo ' - <span class="log-level-info">';
                            _e('Log directory is writable.', 'woo-moysklad-integration');
                            echo '</span>';
                        }
                    } else {
                        _e('Disabled', 'woo-moysklad-integration');
                    }
                    ?>
                </td>
            </tr>
        </table>
        
        <p>
            <a href="<?php echo admin_url('admin.php?page=woo-moysklad-settings#logs-tab'); ?>" class="button button-primary">
                <?php _e('Configure Log Settings', 'woo-moysklad-integration'); ?>
            </a>
        </p>
    </div>
    
    <div class="woo-moysklad-card">
        <h2><?php _e('Log Level Guide', 'woo-moysklad-integration'); ?></h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Level', 'woo-moysklad-integration'); ?></th>
                    <th><?php _e('Description', 'woo-moysklad-integration'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="log-level-debug">Debug</span></td>
                    <td><?php _e('Detailed debug information, very verbose.', 'woo-moysklad-integration'); ?></td>
                </tr>
                <tr>
                    <td><span class="log-level-info">Info</span></td>
                    <td><?php _e('Interesting events, normal operation information.', 'woo-moysklad-integration'); ?></td>
                </tr>
                <tr>
                    <td><span class="log-level-notice">Notice</span></td>
                    <td><?php _e('Normal but significant events.', 'woo-moysklad-integration'); ?></td>
                </tr>
                <tr>
                    <td><span class="log-level-warning">Warning</span></td>
                    <td><?php _e('Exceptional occurrences that are not errors.', 'woo-moysklad-integration'); ?></td>
                </tr>
                <tr>
                    <td><span class="log-level-error">Error</span></td>
                    <td><?php _e('Runtime errors that do not require immediate action.', 'woo-moysklad-integration'); ?></td>
                </tr>
                <tr>
                    <td><span class="log-level-critical">Critical</span></td>
                    <td><?php _e('Critical conditions requiring immediate attention.', 'woo-moysklad-integration'); ?></td>
                </tr>
                <tr>
                    <td><span class="log-level-alert">Alert</span></td>
                    <td><?php _e('Action must be taken immediately.', 'woo-moysklad-integration'); ?></td>
                </tr>
                <tr>
                    <td><span class="log-level-emergency">Emergency</span></td>
                    <td><?php _e('System is unusable.', 'woo-moysklad-integration'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
