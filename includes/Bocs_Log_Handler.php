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
}