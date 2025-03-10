<?php
/**
 * Bocs Log Handler Class
 *
 * Handles logging functionality for the Bocs plugin.
 *
 * @package    Bocs
 * @subpackage Bocs/includes
 * @since      0.0.1
 */

class Bocs_Log_Handler {

    /**
     * Log file directory path
     *
     * @var string
     */
    private $log_directory;

    /**
     * Maximum log file size in bytes (default: 5MB)
     *
     * @var int
     */
    private $max_file_size = 5242880;

    /**
     * Maximum number of log files to keep
     *
     * @var int
     */
    private $max_files = 5;

    /**
     * Initialize the log handler
     *
     * @since 0.0.1
     */
    public function __construct() {
        $this->log_directory = WP_CONTENT_DIR . '/bocs-logs/';
        
        if (!file_exists($this->log_directory)) {
            $this->create_log_directory();
        }
    }

    /**
     * Handle log entry
     *
     * @since 0.0.1
     * @param int    $timestamp Log timestamp
     * @param string $level Emergency|Alert|Critical|Error|Warning|Notice|Info|Debug
     * @param string $message Log message
     * @param array  $context Additional information
     * @return bool True if logged successfully
     */
    public function handle($timestamp, $level, $message, $context) {
        if (!$this->should_handle($level)) {
            return false;
        }

        $entry = $this->format_entry($timestamp, $level, $message, $context);
        
        if (empty($entry)) {
            return false;
        }

        return $this->write_to_log($entry);
    }

    /**
     * Check if we should handle this log level
     *
     * @since 0.0.1
     * @param string $level Log level
     * @return bool True if should handle
     */
    protected function should_handle($level) {
        $log_levels = array(
            'emergency',
            'alert',
            'critical',
            'error',
            'warning',
            'notice',
            'info',
            'debug'
        );

        return in_array(strtolower($level), $log_levels, true);
    }

    /**
     * Format log entry
     *
     * @since 0.0.1
     * @param int    $timestamp Log timestamp
     * @param string $level Log level
     * @param string $message Log message
     * @param array  $context Additional context
     * @return string Formatted log entry
     */
    protected function format_entry($timestamp, $level, $message, $context) {
        $time = wp_date('Y-m-d H:i:s', $timestamp);
        $level = strtoupper($level);
        
        $entry = "[{$time}] [{$level}] {$message}";

        if (!empty($context)) {
            $entry .= ' ' . wp_json_encode($context);
        }

        return $entry . PHP_EOL;
    }

    /**
     * Write entry to log file
     *
     * @since 0.0.1
     * @param string $entry Log entry
     * @return bool True if written successfully
     */
    protected function write_to_log($entry) {
        $file = $this->get_log_file();

        if (!$file) {
            return false;
        }

        $handle = @fopen($file, 'a');
        
        if (!$handle) {
            return false;
        }

        $result = @fwrite($handle, $entry);
        @fclose($handle);

        if ($result === false) {
            return false;
        }

        $this->maybe_rotate_logs();

        return true;
    }

    /**
     * Get current log file path
     *
     * @since 0.0.1
     * @return string|bool Log file path or false on failure
     */
    protected function get_log_file() {
        $filename = 'bocs-' . wp_date('Y-m-d') . '.log';
        $file = $this->log_directory . $filename;

        if (!is_writable($this->log_directory)) {
            error_log(
                sprintf(
                    /* translators: %s: Log directory path */
                    __('Bocs log directory is not writable: %s', 'bocs-wordpress'),
                    $this->log_directory
                )
            );
            return false;
        }

        return $file;
    }

    /**
     * Create log directory if it doesn't exist
     *
     * @since 0.0.1
     * @return bool True if directory exists or was created
     */
    protected function create_log_directory() {
        if (!wp_mkdir_p($this->log_directory)) {
            error_log(
                sprintf(
                    /* translators: %s: Log directory path */
                    __('Failed to create Bocs log directory: %s', 'bocs-wordpress'),
                    $this->log_directory
                )
            );
            return false;
        }

        // Create .htaccess to protect log files
        $htaccess_file = $this->log_directory . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Order deny,allow\nDeny from all";
            @file_put_contents($htaccess_file, $htaccess_content);
        }

        // Create index.html to prevent directory listing
        $index_file = $this->log_directory . 'index.html';
        if (!file_exists($index_file)) {
            @file_put_contents($index_file, '');
        }

        return true;
    }

    /**
     * Rotate log files if necessary
     *
     * @since 0.0.1
     */
    protected function maybe_rotate_logs() {
        $current_file = $this->get_log_file();
        
        if (!$current_file || !file_exists($current_file)) {
            return;
        }

        if (filesize($current_file) < $this->max_file_size) {
            return;
        }

        $files = glob($this->log_directory . 'bocs-*.log');
        
        if ($files === false) {
            return;
        }

        // Sort files by modification time
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Remove old files
        while (count($files) >= $this->max_files) {
            $old_file = array_pop($files);
            @unlink($old_file);
        }

        // Rotate current file
        $backup_file = $this->log_directory . 'bocs-' . wp_date('Y-m-d-H-i-s') . '.log';
        @rename($current_file, $backup_file);
    }

    /**
     * Clear all log files
     *
     * @since 0.0.1
     * @return bool True if logs were cleared
     */
    public function clear_logs() {
        $files = glob($this->log_directory . 'bocs-*.log');
        
        if ($files === false) {
            return false;
        }

        $success = true;
        foreach ($files as $file) {
            if (!@unlink($file)) {
                $success = false;
                error_log(
                    sprintf(
                        /* translators: %s: Log file path */
                        __('Failed to delete Bocs log file: %s', 'bocs-wordpress'),
                        $file
                    )
                );
            }
        }

        return $success;
    }

    /**
     * 
     * Add bocs logs to the product
     * 
     * @param integer $post_id
     * @param string $comment
     * 
     * @return void|string|boolean
     * 
     */
    public function add_product_log($post_id, $comment =  ''){
        
        if(empty($comment)) return;
        
        global $wpdb;
        
        $comment_date = current_time( 'mysql' );
        $comment_date_gmt = get_gmt_from_date( $comment_date );
        
        // then we will attempt to add to the table
        $data = array(
            'comment_post_ID'      => $post_id,
            'comment_author'       => 'Bocs',
            'comment_author_email' => 'api@bocs.io',
            'comment_author_url'   => 'bocs.io',
            'comment_content'      => $comment,
            'comment_agent'        => 'Bocs',
            'comment_type'         => 'bocs_product_logs',
            'comment_parent'       => 0,
            'comment_approved'     => 1,
            'comment_date'         => $comment_date,
            'comment_date_gmt'     => $comment_date_gmt
        );
        
        // insert in to the table
        $table_name = $wpdb->prefix . 'comments';
        
        $wpdb->insert( $table_name , $data);
        
        if( $wpdb->last_error ) return $wpdb->last_error;
        
        return true;
        
    }

    /**
     * 
     * Get the list of the bocs logs from the product
     * 
     * @param integer $post_id
     * 
     * @return boolean|array|object|NULL
     */
    public function get_product_logs($post_id = 0){
        
        if( empty( $post_id ) ) return false;
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'comments';
        
        return $wpdb->get_results("SELECT * FROM $table_name WHERE comment_post_ID = " . $post_id . " AND comment_type = 'bocs_product_logs' ORDER BY comment_date DESC", ARRAY_A);
        
    }

    /**
     * Process and log API response results
     * 
     * @since 0.0.1
     * 
     * @param object|WP_Error $result   API response object containing code and message
     *                                  Example: { code: 200, message: "Success" }
     * @param string|bool     $url      API endpoint URL that was called
     *                                  Example: "/api/v1/products"
     * @param array|bool      $params   Request parameters that were sent
     *                                  Example: ["product_id" => 123]
     * @param string          $method   HTTP method used (get, post, put, delete)
     *                                  Example: "post"
     * @param string          $module   Module/section name for context
     *                                  Example: "products"
     * @param string|int      $id       Resource ID if applicable
     *                                  Example: "123"
     * 
     * @return bool False if result is invalid, void on success
     */
    public function process_log_from_result($result, $url = false, $params = false, $method = 'get', $module = '', $id = '') {
        // Validate required response fields
        if (empty($result)) {
            return false; // No response received
        }
        if (empty($result->code)) {
            return false; // No status code in response
        }
        if (empty($result->message)) {
            return false; // No message in response
        }

        // Set default level as notice
        $level = 'notice';
        $message = $result->message;

        // Build context array for detailed logging
        $context = array(
            'module'    => $module,    // Module/section (e.g., 'products', 'orders')
            'id'        => $id,        // Resource identifier
            'method'    => $method,    // HTTP method used
            'data'      => $params,    // Request parameters
            'code'      => $result->code, // Response status code
            'url'       => str_replace(BOCS_API_URL, '', $url) // Relative API path
        );

        // Determine log level based on response code
        // 200: Success -> notice level
        // Other: Error -> error level
        if ($result->code == 200) {
            $level = 'notice';
        } else {
            $level = 'error';
        }

        // Insert log entry with determined level, message and context
        $this->insert_log($level, $message, $context);
    }

    /**
     * Adds logs on the woocommerce_log table
     * 
     * @param string $level 
     *
     * The status numbers and their corresponding severity levels are as follows:
     * 
     * 0 - Emergency: The highest severity level, indicating a system-wide critical failure.
     * 1 - Alert: An urgent situation that requires immediate attention.
     * 2 - Critical: A critical condition that needs to be addressed promptly. 
     * 3 - Error: An error has occurred, but the system can still function.
     * 4 - Warning: An issue that may require attention but doesn’t disrupt the system.
     * 5 - Notice: General information about the system’s operation.
     * 6 - Informational: Additional information about system events.
     * 7 - Debug: Detailed debugging information, useful for troubleshooting.
     *
     * @param string $message
     * @param string $context
     * 
     * @return void
     */
    public function insert_log( $level = 'debug', $message, $context ) {

        global $wpdb;

        $table_name = $wpdb->prefix . "woocommerce_log";

        $level_number = 7;

        switch( $level ){

            case 'emergency':
                $level_number = 0;
                break;
            
            case 'alert':
                $level_number = 1;
                break;
            
            case 'critical':
                $level_number = 2;
                break;
            
            case 'error':
                $level_number = 3;
                break;
            
            case 'warning':
                $level_number = 4;
                break;
            
            case 'notice':
                $level_number = 5;
                break;
            
            case 'info':
            case 'information':
                $level_number = 6;
                break;
            
            case 'debug':

            default:
                $level_number = 7;
                break;

        }

        if( is_array( $context ) || is_object( $context ) ){
            $context = json_encode( $context );
        }

        return $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table_name} (timestamp, level, source, message, context) VALUES ( NOW(), %d, %s, %s, %s )",
                $level_number,
                'bocs',
                $message,
                $context
            )
        );
        
    }
}