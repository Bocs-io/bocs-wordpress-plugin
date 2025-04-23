<?php
/**
 * Bocs Sentry Integration Class
 *
 * Handles Sentry error tracking specifically for the Bocs plugin.
 *
 * @package    Bocs
 * @subpackage Bocs/includes
 * @since      0.0.139
 */

// Check if Sentry classes exist before attempting to use them
if (!class_exists('\\Sentry\\State\\Scope')) {
    require_once dirname(__FILE__) . '/../vendor/autoload.php';
}

// Define class aliases for Sentry classes to prevent fatal errors
if (!class_exists('\\Sentry\\Severity')) {
    class_alias('stdClass', '\\Sentry\\Severity');
    if (class_exists('\\Sentry\\Severity')) {
        // Add constants to the aliased class
        if (!defined('\\Sentry\\Severity::DEBUG')) {
            define('\\Sentry\\Severity::DEBUG', 'debug');
            define('\\Sentry\\Severity::INFO', 'info');
            define('\\Sentry\\Severity::WARNING', 'warning');
            define('\\Sentry\\Severity::ERROR', 'error');
            define('\\Sentry\\Severity::FATAL', 'fatal');
        }
    }
}

if (!class_exists('\\Sentry\\Breadcrumb') && class_exists('stdClass')) {
    class_alias('stdClass', '\\Sentry\\Breadcrumb');
    if (class_exists('\\Sentry\\Breadcrumb')) {
        // Add constants to the aliased class
        if (!defined('\\Sentry\\Breadcrumb::LEVEL_INFO')) {
            define('\\Sentry\\Breadcrumb::LEVEL_INFO', 'info');
        }
    }
}

use Sentry\State\Scope;
use Sentry\Severity;

class Bocs_Sentry {

    /**
     * Sentry DSN
     *
     * @var string
     */
    private $dsn;

    /**
     * Environment (production, development, etc.)
     *
     * @var string
     */
    private $environment;

    /**
     * Plugin version
     *
     * @var string
     */
    private $version;

    /**
     * Whether Sentry is enabled
     *
     * @var bool
     */
    private $enabled = false;

    /**
     * Singleton instance
     *
     * @var Bocs_Sentry
     */
    private static $instance = null;

    /**
     * Constructor
     */
    private function __construct() {
        // Set default values
        $this->environment = defined('BOCS_ENVIRONMENT') ? BOCS_ENVIRONMENT : 'production';
        $this->version = defined('BOCS_VERSION') ? BOCS_VERSION : '0.0.0';
        $this->enabled = false;
        
        // Try to load Sentry SDK if available
        if (file_exists(dirname(__FILE__) . '/../vendor/autoload.php')) {
            try {
                require_once dirname(__FILE__) . '/../vendor/autoload.php';
            } catch (\Exception $e) {
                // Silently fail, init() will handle errors properly
            }
        }
    }

    /**
     * Get singleton instance
     *
     * @return Bocs_Sentry
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize Sentry for Bocs plugin
     *
     * @param array $config Configuration options
     * @return void
     */
    public function init($config = []) {
        // First check if required Sentry dependencies are available
        if (!$this->check_sentry_dependencies()) {
            // Dependencies missing, log and exit gracefully
            if (class_exists('Bocs_Log_Handler')) {
                $logger = new Bocs_Log_Handler();
                $logger->insert_log('error', '[Sentry] Sentry SDK dependencies not found. Sentry will be disabled.', []);
            } else {
                error_log('Bocs Sentry: Sentry SDK dependencies not found. Sentry will be disabled.');
            }
            return;
        }
        
        // Log the initialization attempt
        $this->log_debug('Bocs_Sentry::init() called', [
            'already_enabled' => $this->enabled ? 'true' : 'false',
            'dev_mode' => defined('BOCS_ENVIRONMENT') && BOCS_ENVIRONMENT === 'dev' ? 'true' : 'false'
        ]);
        
        // Already initialized
        if ($this->enabled) {
            $this->log_debug('Sentry already initialized, skipping');
            return;
        }
        
        // Check if Sentry SDK is available
        if (!class_exists('\\Sentry\\SentrySdk') || !class_exists('\\Sentry\\State\\Scope')) {
            $this->log_error('Sentry SDK not found. Ensure the Composer autoloader is properly included.', [
                'autoloader_checked' => file_exists(dirname(__FILE__) . '/../vendor/autoload.php') ? 'true' : 'false'
            ]);
            return;
        }
        
        // Check if the WordPress Sentry plugin is active
        if ($this->is_wp_sentry_plugin_active()) {
            // WP Sentry plugin is active, we don't need to initialize Sentry,
            // we'll just add our context to their existing instance
            $this->log_debug('WordPress Sentry plugin detected, skipping direct initialization');
            
            $this->dsn = defined('WP_SENTRY_PHP_DSN') ? WP_SENTRY_PHP_DSN : '';
            $this->environment = defined('BOCS_ENVIRONMENT') ? BOCS_ENVIRONMENT : 'production';
            $this->version = defined('BOCS_VERSION') ? BOCS_VERSION : '0.0.0';
            
            // Try to add our context to the existing Sentry instance if it's already initialized
            if ($this->is_sentry_client_initialized()) {
                $this->log_debug('Sentry client already initialized by WordPress Sentry, adding Bocs context');
                $this->configure_context();
                $this->enabled = true;
            } else {
                $this->log_debug('WordPress Sentry detected but client not yet initialized');
            }
            
            // We won't initialize our own instance since WP Sentry will handle it
            return;
        }

        // Set default configuration values
        $defaults = [
            'dsn' => $this->get_dsn(),
            'environment' => defined('BOCS_ENVIRONMENT') ? BOCS_ENVIRONMENT : 'production',
            'release' => defined('BOCS_VERSION') ? 'bocs-wordpress@' . BOCS_VERSION : '',
            'send_default_pii' => defined('WP_SENTRY_SEND_DEFAULT_PII') ? WP_SENTRY_SEND_DEFAULT_PII : false,
            'traces_sample_rate' => defined('WP_SENTRY_TRACES_SAMPLE_RATE') ? WP_SENTRY_TRACES_SAMPLE_RATE : 0.1
        ];

        // Log the DSN and config (redacted for security)
        $this->log_debug('Sentry configuration prepared', [
            'dsn_provided' => !empty($defaults['dsn']) ? 'true' : 'false',
            'environment' => $defaults['environment'],
            'pii_enabled' => $defaults['send_default_pii'] ? 'true' : 'false'
        ]);

        // Merge with provided config
        $config = array_merge($defaults, $config);

        // Store key configuration values
        $this->dsn = $config['dsn'];
        $this->environment = $config['environment'];
        $this->version = defined('BOCS_VERSION') ? BOCS_VERSION : '0.0.0';

        // Only initialize if DSN is provided
        if (empty($this->dsn)) {
            $this->log_debug('Skipping Sentry initialization - no DSN provided');
            return;
        }

        try {
            // Initialize Sentry with configuration
            $this->log_debug('Initializing Sentry client');
            
            // Make sure we're using the correct namespaced function
            if (function_exists('\\Sentry\\init')) {
                \Sentry\init([
                    'dsn' => $this->dsn,
                    'environment' => $this->environment,
                    'release' => $config['release'],
                    'send_default_pii' => $config['send_default_pii'],
                    'traces_sample_rate' => $config['traces_sample_rate'],
                    'max_breadcrumbs' => 50,
                    'before_send' => [$this, 'filter_events'],
                    'before_breadcrumb' => [$this, 'filter_breadcrumbs']
                ]);

                // Configure default tags and user context
                $this->log_debug('Adding Bocs context to Sentry');
                $this->configure_context();

                $this->enabled = true;
                $this->log_debug('Sentry initialization complete', ['enabled' => 'true']);
            } else {
                $this->log_error('Sentry\\init function not found. Ensure Sentry SDK is properly installed.');
            }
        } catch (\Exception $e) {
            // Log initialization failure but don't disrupt plugin operation
            $error_message = 'Failed to initialize Sentry: ' . $e->getMessage();
            $this->log_error($error_message, [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * Check if required Sentry dependencies are available
     *
     * @return bool Whether all required Sentry dependencies are available
     */
    private function check_sentry_dependencies() {
        // Try to load autoloader if not already loaded
        if (!class_exists('\\Sentry\\SentrySdk') && file_exists(dirname(__FILE__) . '/../vendor/autoload.php')) {
            try {
                require_once dirname(__FILE__) . '/../vendor/autoload.php';
            } catch (\Exception $e) {
                return false;
            }
        }

        // Check for required Sentry classes
        $required_classes = [
            '\\Sentry\\SentrySdk',
            '\\Sentry\\State\\Scope',
            '\\Sentry\\Severity',
            '\\Sentry\\Breadcrumb'
        ];

        foreach ($required_classes as $class) {
            if (!class_exists($class)) {
                return false;
            }
        }

        // Check for required Sentry functions
        $required_functions = [
            '\\Sentry\\init',
            '\\Sentry\\captureException',
            '\\Sentry\\captureMessage',
            '\\Sentry\\configureScope'
        ];

        foreach ($required_functions as $function) {
            if (!function_exists($function)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if WordPress Sentry plugin is installed and active
     *
     * @return bool Whether the WP Sentry plugin is active
     */
    private function is_wp_sentry_plugin_active() {
        // Check if the plugin function exists
        if (function_exists('is_plugin_active')) {
            return is_plugin_active('wp-sentry-integration/wp-sentry.php');
        }
        
        // Load the plugin.php file if needed
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
            if (function_exists('is_plugin_active')) {
                return is_plugin_active('wp-sentry-integration/wp-sentry.php');
            }
        }
        
        // Fallback: check for WP Sentry class and constants
        if (class_exists('\\WP_Sentry\\WP_Sentry') && defined('WP_SENTRY_PHP_DSN')) {
            return true;
        }
        
        // Check for other typical WP Sentry files
        if (file_exists(WP_PLUGIN_DIR . '/wp-sentry-integration/wp-sentry.php')) {
            // Plugin exists but may not be active, check active plugins option
            $active_plugins = get_option('active_plugins', []);
            return in_array('wp-sentry-integration/wp-sentry.php', $active_plugins);
        }
        
        return false;
    }

    /**
     * Check if Sentry client is already initialized (by WP Sentry or another plugin)
     *
     * @return bool Whether the Sentry client is already initialized
     */
    private function is_sentry_client_initialized() {
        if (!class_exists('\\Sentry\\SentrySdk')) {
            return false;
        }

        try {
            $currentHub = \Sentry\SentrySdk::getCurrentHub();
            $client = $currentHub->getClient();
            
            // If we have a client, Sentry is already initialized
            return $client !== null;
        } catch (\Exception $e) {
            // Something went wrong, assume Sentry is not initialized
            $this->log_error('Error checking Sentry initialization: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the Sentry DSN from various possible sources
     *
     * @return string The Sentry DSN
     */
    private function get_dsn() {
        // Priority 1: Check if passed in query parameters for debugging (admin users only)
        if (is_admin() && current_user_can('manage_options') && !empty($_GET['bocs_sentry_dsn'])) {
            return sanitize_text_field($_GET['bocs_sentry_dsn']);
        }
        
        // Priority 2: Check if WP Sentry plugin is configured
        if (defined('WP_SENTRY_PHP_DSN')) {
            return WP_SENTRY_PHP_DSN;
        }
        
        // Priority 3: Check if defined as a constant in wp-config.php
        if (defined('BOCS_SENTRY_DSN_GLOBAL')) {
            return BOCS_SENTRY_DSN_GLOBAL;
        }
        
        // Priority 4: Check environment-specific constants
        if (defined('BOCS_ENVIRONMENT') && BOCS_ENVIRONMENT === 'prod' && defined('BOCS_SENTRY_DSN_PROD')) {
            return BOCS_SENTRY_DSN_PROD;
        }
        
        if (defined('BOCS_ENVIRONMENT') && BOCS_ENVIRONMENT === 'dev' && defined('BOCS_SENTRY_DSN_DEV')) {
            return BOCS_SENTRY_DSN_DEV;
        }
        
        // Priority 5: Use the plugin-defined DSN
        if (defined('BOCS_SENTRY_DSN')) {
            return BOCS_SENTRY_DSN;
        }
        
        // Default: Return the hardcoded DSN as a last resort to ensure Sentry is always enabled
        return 'https://7ffc8fdea3d7c1d0dcf42008e08197bb@o4506097184473088.ingest.us.sentry.io/4508368230481920';
    }

    /**
     * Configure default context information
     *
     * @return void
     */
    private function configure_context() {
        if (!class_exists('\\Sentry\\State\\Scope') || !function_exists('\\Sentry\\configureScope')) {
            $this->log_error('Cannot configure Sentry context - SDK not available');
            return;
        }

        try {
            \Sentry\configureScope(function (Scope $scope): void {
                // Add plugin details
                $scope->setTag('plugin', 'bocs-wordpress');
                $scope->setTag('plugin.version', $this->version);
                $scope->setTag('wordpress.version', get_bloginfo('version'));
                $scope->setTag('php.version', PHP_VERSION);

                // Add WooCommerce details if available
                if (defined('WC_VERSION')) {
                    $scope->setTag('woocommerce.version', WC_VERSION);
                }

                // Add server details
                $scope->setTag('server.os', PHP_OS);
                $scope->setExtra('server.software', $_SERVER['SERVER_SOFTWARE'] ?? 'unknown');
            });
        } catch (\Exception $e) {
            $this->log_error('Failed to configure Sentry context: ' . $e->getMessage());
        }
    }

    /**
     * Filter events before sending to Sentry
     * Ensures only events from the Bocs plugin are sent
     *
     * @param \Sentry\Event $event The event to filter
     * @return \Sentry\Event|null The filtered event or null to skip sending
     */
    public function filter_events(\Sentry\Event $event) {
        // If WordPress Sentry plugin is active, we need to be even more selective
        // to avoid duplicating error reports
        $is_wp_sentry = $this->is_wp_sentry_plugin_active();
        
        // Add a tag to identify this event as coming from Bocs
        $event->setTag('source', 'bocs-wordpress-plugin');
        
        // Get the exception from the event
        $exception = $event->getExceptions()[0] ?? null;
        if (!$exception) {
            // If no exception, check if this is a message event
            if ($event->getMessage() && strpos($event->getMessage()->getMessage(), '[Bocs]') === 0) {
                // It's a Bocs message, so we'll keep it
                return $event;
            }
            
            // No exception and not a Bocs message, let WP Sentry handle it if active
            if ($is_wp_sentry) {
                return null;
            }
            
            return $event;
        }

        // Get the exception trace
        $stacktrace = $exception->getStacktrace();
        if (!$stacktrace) {
            // No stacktrace to filter by, let WP Sentry handle it if active
            if ($is_wp_sentry) {
                return null;
            }
            
            return $event;
        }

        // Check if any frame in the stack is from the Bocs plugin
        $frames = $stacktrace->getFrames();
        $is_bocs_error = false;

        foreach ($frames as $frame) {
            $file = $frame->getFile();
            if ($file && $this->is_bocs_file($file)) {
                $is_bocs_error = true;
                break;
            }
        }

        // If WP Sentry is active, we only want to report errors that:
        // 1. Are definitely from Bocs plugin AND
        // 2. Either have a message that starts with [Bocs] OR have a stack frame in a Bocs file
        if ($is_wp_sentry) {
            $message = $exception->getValue();
            $has_bocs_message = strpos($message, '[Bocs]') === 0;
            
            if ($is_bocs_error || $has_bocs_message) {
                return $event;
            }
            
            // Not clearly a Bocs error, let WP Sentry handle it
            return null;
        }
        
        // If WP Sentry is not active, we'll be more permissive but still
        // only report errors that are likely from the Bocs plugin
        return $is_bocs_error ? $event : null;
    }

    /**
     * Filter breadcrumbs before adding to Sentry
     *
     * @param \Sentry\Breadcrumb|null $breadcrumb The breadcrumb to filter
     * @return \Sentry\Breadcrumb|null The filtered breadcrumb or null to skip adding
     */
    public function filter_breadcrumbs(?\Sentry\Breadcrumb $breadcrumb) {
        if (!$breadcrumb) {
            return null;
        }

        // Filter based on breadcrumb data
        $data = $breadcrumb->getMetadata();
        
        // Check for bocs-related breadcrumbs
        if (isset($data['category']) && stripos($data['category'], 'bocs') !== false) {
            return $breadcrumb;
        }
        
        return $breadcrumb;
    }

    /**
     * Check if a file is from the Bocs plugin
     *
     * @param string $file_path The file path to check
     * @return bool Whether the file is part of the Bocs plugin
     */
    private function is_bocs_file($file_path) {
        if (empty($file_path)) {
            return false;
        }
        
        // Normalize path separators for consistent checking
        $file_path = str_replace('\\', '/', $file_path);
        
        // List of patterns that identify Bocs plugin files
        $bocs_patterns = [
            'bocs-wordpress-plugin',
            '/plugins/bocs/',
            '/plugins/bocs-wordpress/',
            '/wp-content/plugins/bocs/',
            '/wp-content/plugins/bocs-wordpress/'
        ];
        
        foreach ($bocs_patterns as $pattern) {
            if (strpos($file_path, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Manually capture an exception
     *
     * @param \Throwable $exception The exception to capture
     * @param array $context Additional context
     * @return string|null The event ID or null if Sentry is not enabled
     */
    public function capture_exception(\Throwable $exception, array $context = []) {
        if (!$this->enabled) {
            return null;
        }
        
        // Check if Sentry SDK is available
        if (!function_exists('\\Sentry\\captureException')) {
            $this->log_error('Cannot capture exception - Sentry SDK not available');
            return null;
        }

        try {
            return \Sentry\captureException($exception, $context);
        } catch (\Exception $e) {
            $this->log_error('Failed to capture exception in Sentry: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Safely create a Severity object for Sentry
     *
     * @param string $level The severity level (info, error, warning, etc.)
     * @return \Sentry\Severity|null A Severity object or null if not available
     */
    private function create_severity($level = 'info') {
        if (!class_exists('\\Sentry\\Severity')) {
            return null;
        }
        
        try {
            // Create a new Severity object using the factory method
            switch (strtolower($level)) {
                case 'debug':
                    return \Sentry\Severity::debug();
                case 'warning':
                case 'warn':
                    return \Sentry\Severity::warning();
                case 'error':
                    return \Sentry\Severity::error();
                case 'fatal':
                    return \Sentry\Severity::fatal();
                case 'info':
                default:
                    return \Sentry\Severity::info();
            }
        } catch (\Exception $e) {
            $this->log_error('Failed to create Severity object: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Manually capture a message
     *
     * @param string $message The message to capture
     * @param array $context Additional context
     * @param string $level The message level (info, error, warning, etc.)
     * @return string|null The event ID or null if Sentry is not enabled
     */
    public function capture_message($message, array $context = [], $level = 'info') {
        if (!$this->enabled) {
            return null;
        }
        
        // Check if Sentry SDK is available
        if (!function_exists('\\Sentry\\captureMessage')) {
            $this->log_error('Cannot capture message - Sentry SDK not available');
            return null;
        }
        
        // Convert level string to Severity object
        $severity = $this->create_severity($level);
        
        // Log what type of severity we got
        $this->log_debug('Created severity object', [
            'level' => $level,
            'severity_type' => is_object($severity) ? get_class($severity) : gettype($severity),
            'severity_value' => is_object($severity) ? 'object' : $severity
        ]);
        
        // If we couldn't create a Severity object, we can't proceed
        if ($severity === null) {
            $this->log_error('Cannot capture message - Severity class not available');
            return null;
        }

        try {
            // Add context data as tags instead of trying to use as EventHint
            if (!empty($context) && function_exists('\\Sentry\\configureScope')) {
                \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($context): void {
                    foreach ($context as $key => $value) {
                        if (is_scalar($value)) {
                            $scope->setTag($key, (string)$value);
                        } else if (is_array($value)) {
                            $scope->setExtra($key, $value);
                        }
                    }
                });
            }
            
            // Pass null as the third parameter (EventHint)
            return \Sentry\captureMessage($message, $severity);
        } catch (\Exception $e) {
            $this->log_error('Failed to capture message in Sentry: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Add breadcrumb for tracking the sequence of events
     *
     * @param string $message The breadcrumb message
     * @param string $category The breadcrumb category
     * @param array $data Additional data
     * @param string $level The breadcrumb level
     * @return void
     */
    public function add_breadcrumb($message, $category = 'bocs', array $data = [], $level = null) {
        if (!$this->enabled) {
            return;
        }
        
        // Check if Sentry SDK is available
        if (!function_exists('\\Sentry\\addBreadcrumb') || !class_exists('\\Sentry\\Breadcrumb')) {
            $this->log_error('Cannot add breadcrumb - Sentry SDK not available');
            return;
        }
        
        // Default level if Breadcrumb class constants are not available
        if ($level === null) {
            $level = defined('\\Sentry\\Breadcrumb::LEVEL_INFO') ? \Sentry\Breadcrumb::LEVEL_INFO : 'info';
        }

        try {
            \Sentry\addBreadcrumb(
                new \Sentry\Breadcrumb(
                    $level,
                    $category,
                    $data['type'] ?? 'default',
                    $message,
                    $data
                )
            );
        } catch (\Exception $e) {
            $this->log_error('Failed to add breadcrumb in Sentry: ' . $e->getMessage());
        }
    }

    /**
     * Add Bocs context to WordPress Sentry instance
     * 
     * @return void
     */
    public function add_context_to_wp_sentry() {
        // Log the attempt
        $this->log_debug('add_context_to_wp_sentry() called');
        
        // Check if Sentry SDK is available
        if (!class_exists('\\Sentry\\SentrySdk') || !class_exists('\\Sentry\\State\\Scope')) {
            $this->log_error('Sentry SDK not found. Cannot add context to WP Sentry.');
            return;
        }
        
        // Check if Sentry is initialized
        if (!$this->is_sentry_client_initialized()) {
            $this->log_debug('Skipping adding context - Sentry client not initialized');
            return;
        }
        
        // Set our version and other basic info
        $this->version = defined('BOCS_VERSION') ? BOCS_VERSION : '0.0.0';
        $this->environment = defined('BOCS_ENVIRONMENT') ? BOCS_ENVIRONMENT : 'production';
        $this->enabled = true;
        
        // Add our context to the existing Sentry instance
        $this->log_debug('Adding Bocs context to WordPress Sentry instance');
        $this->configure_context();
        
        // Hook into the existing before_send callback if possible
        try {
            $hub = \Sentry\SentrySdk::getCurrentHub();
            $client = $hub->getClient();
            
            if ($client) {
                $options = $client->getOptions();
                $originalBeforeSend = $options->getBeforeSendCallback();
                
                $this->log_debug('Setting up before_send callback', [
                    'has_original_callback' => $originalBeforeSend ? 'true' : 'false'
                ]);
                
                // Create a new before_send callback that chains our filter with the original
                $options->setBeforeSendCallback(function (\Sentry\Event $event, ?\Sentry\EventHint $hint) use ($originalBeforeSend) {
                    // First add our tag
                    $event->setTag('plugin', 'bocs-wordpress');
                    
                    // Only filter if it's a Bocs error
                    $shouldSend = $this->should_send_bocs_event($event);
                    
                    // Log the decision (for debug only)
                    $this->log_debug('Before_send callback executed', [
                        'should_send' => $shouldSend ? 'true' : 'false',
                        'has_exception' => !empty($event->getExceptions()) ? 'true' : 'false'
                    ]);
                    
                    if (!$shouldSend) {
                        return $event;
                    }
                    
                    // Then run the original callback if it exists
                    if ($originalBeforeSend) {
                        return call_user_func($originalBeforeSend, $event, $hint);
                    }
                    
                    return $event;
                });
                
                $this->log_debug('Successfully set before_send callback');
            } else {
                $this->log_debug('Could not set before_send callback - client not available');
            }
        } catch (\Exception $e) {
            // If this fails, the WP Sentry will still work, we just won't filter events
            $error_message = 'Failed to hook into WP Sentry before_send: ' . $e->getMessage();
            $this->log_error($error_message, [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * Filter to determine if an event should be sent to Sentry
     * Only allows Bocs-related errors to be sent
     *
     * @param \Sentry\Event $event The event to be sent
     * @return bool Whether the event should be sent
     */
    private function should_send_bocs_event(\Sentry\Event $event) {
        // Check if Sentry classes are available
        if (!class_exists('\\Sentry\\Event')) {
            $this->log_error('Cannot filter Sentry event - Sentry SDK not available');
            return false;
        }

        try {
            // Always send events explicitly tagged with bocs
            $tags = $event->getTags();
            if (isset($tags['plugin']) && $tags['plugin'] === 'bocs-wordpress') {
                $this->log_debug('Allowing event - explicitly tagged as bocs-wordpress');
                return true;
            }
            
            // Check exceptions for Bocs-related code
            $exceptions = $event->getExceptions();
            
            if (empty($exceptions)) {
                // No exceptions, check if it's a message we sent
                $message = $event->getMessage();
                if ($message && strpos($message->getMessage(), '[Bocs]') === 0) {
                    $this->log_debug('Allowing event - message is from Bocs');
                    return true;
                }
                
                // No exceptions and not our message - reject it
                return false;
            }
            
            // Loop through the exception stack
            foreach ($exceptions as $exception) {
                $stacktrace = $exception->getStacktrace();
                if (!$stacktrace) continue;
                
                $frames = $stacktrace->getFrames();
                if (empty($frames)) continue;
                
                // Check each frame in the stack trace
                foreach ($frames as $frame) {
                    $file = $frame->getFile();
                    if (!$file) continue;
                    
                    // Check if the error is from our plugin using is_bocs_file method
                    if ($this->is_bocs_file($file)) {
                        $this->log_debug('Allowing event - stack trace contains bocs-related file', [
                            'file' => $file
                        ]);
                        return true;
                    }
                }
            }
            
            // Not a Bocs-related error
            $this->log_debug('Rejecting event - not related to Bocs');
            return false;
        } catch (\Exception $e) {
            // If something goes wrong, default to allowing the event
            $this->log_error('Error in should_send_bocs_event: ' . $e->getMessage(), [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return true;
        }
    }

    /**
     * Log a debug message
     *
     * @param string $message The message to log
     * @param array $context Additional context
     * @return void
     */
    private function log_debug($message, $context = []) {
        if (class_exists('Bocs_Log_Handler')) {
            try {
                $logger = new Bocs_Log_Handler();
                $logger->insert_log('debug', '[Sentry] ' . $message, $context);
            } catch (\Throwable $e) {
                error_log('Bocs Sentry debug log error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Log an error message
     *
     * @param string $message The message to log
     * @param array $context Additional context
     * @return void
     */
    private function log_error($message, $context = []) {
        if (class_exists('Bocs_Log_Handler')) {
            try {
                $logger = new Bocs_Log_Handler();
                $logger->insert_log('error', '[Sentry] ' . $message, $context);
            } catch (\Throwable $e) {
                // Fallback to WordPress error log
                error_log('Bocs Sentry: ' . $message);
            }
        } else {
            // Fallback to WordPress error log
            error_log('Bocs Sentry: ' . $message);
        }
    }

    /**
     * Send a test event to Sentry to verify it's working
     *
     * @return bool Whether the test was successful
     */
    public function send_test_event() {
        try {
            $this->log_debug('Sending test event to Sentry');
            
            if (!$this->enabled) {
                $this->log_debug('Cannot send test event - Sentry not enabled');
                return false;
            }
            
            // Use our own capture_message method which handles level conversion properly
            $eventId = $this->capture_message(
                '[Bocs] Test event from Bocs plugin ' . date('Y-m-d H:i:s'),
                ['source' => 'bocs_test', 'test' => true],
                'info'
            );
            
            if ($eventId) {
                $this->log_debug('Test event sent successfully', [
                    'event_id' => $eventId,
                    'time' => date('Y-m-d H:i:s')
                ]);
                return true;
            } else {
                $this->log_debug('Test event failed - no event ID returned');
                return false;
            }
        } catch (\Exception $e) {
            $this->log_error('Test event failed with exception', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return false;
        }
    }

    /**
     * Check if Sentry is enabled
     *
     * @return bool Whether Sentry is enabled
     */
    public function is_enabled() {
        return $this->enabled;
    }
} 