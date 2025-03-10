<?php
/**
 * The main plugin file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://bocs.io
 * @since             0.0.1
 * @package           Bocs
 *
 * @wordpress-plugin
 * Plugin Name:       Bocs (alpha)
 * Plugin URI:        https://bocs.io
 * Description:       The Bocs service is a powerful sales channel for your products.
 * Version:           0.0.118
 * Author:            Bocs.io
 * Author URI:        https://bocs.io
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bocs-wordpress
 * Domain Path:       /languages
 * Requires at least: 5.6.0
 * Requires PHP:      7.3.5
 */

// If this file is called directly, abort.
if (! defined('WPINC') || ! defined('ABSPATH')) {
    die();
}

/**
 * Current plugin version.
 * Start at version 0.0.109 and use SemVer - https://semver.org
 */
define('BOCS_VERSION', '0.0.118');

/**
 * Plugin name and slug definitions.
 */
define('BOCS_NAME', 'Bocs');
define('BOCS_SLUG', 'bocs');

/**
 * API Configuration
 * @todo Move to a separate config file for better maintainability
 */
define('BOCS_API_ENDPOINTS', [
    'dev'  => 'https://9nelk4erd7.execute-api.ap-southeast-2.amazonaws.com/dev',
    'prod' => 'https://hudaq97o4b.execute-api.ap-southeast-2.amazonaws.com/prod'
]);

/**
 * Determine environment and set API endpoints
 */
$options = get_option('bocs_plugin_options', ['developer_mode' => 'off']);
$developer_mode = isset($options['developer_mode']) ? sanitize_text_field($options['developer_mode']) : 'off';
define('BOCS_ENVIRONMENT', $developer_mode === 'on' ? 'dev' : 'prod');

$api_base = BOCS_API_ENDPOINTS[BOCS_ENVIRONMENT];

/**
 * Define all API-related constants
 */
define('BOCS_API_URL', $api_base . '/');
define('VITE_API_EXTERNAL_URL', $api_base);
define('NEXT_PUBLIC_API_EXTERNAL_URL', $api_base);
define('BOCS_LIST_WIDGETS_URL', BOCS_API_URL . 'list-widgets/');

/**
 * Load Action Scheduler if not already loaded
 */
if (! function_exists('as_has_scheduled_action')) {
    require_once(plugin_dir_path(__FILE__) . '/libraries/action-scheduler/action-scheduler.php');
}

/**
 * Load Composer autoloader if available
 */
if (file_exists(dirname(__FILE__) . '/includes/vendor/autoload.php')) {
    require_once dirname(__FILE__) . '/includes/vendor/autoload.php';
}

/**
 * Load WordPress core dependencies
 */
if (! class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

if (! function_exists('wp_create_nonce')) {
    require_once(ABSPATH . 'wp-includes/pluggable.php');
}

/**
 * Load core plugin files
 */
require plugin_dir_path(__FILE__) . 'includes/Bocs.php';
require plugin_dir_path(__FILE__) . 'includes/Bocs_Account.php';

/**
 * Load plugin text domain for translations.
 *
 * @since    0.0.109
 * @return   void
 */
function bocs_load_textdomain() {
    load_plugin_textdomain(
        'bocs-wordpress',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}
add_action('plugins_loaded', 'bocs_load_textdomain');

/**
 * Check if another instance of Bocs plugin is already active
 * 
 * @since    0.0.109
 * @return   void
 */
function check_duplicate_bocs_plugin() {
    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $all_plugins = get_plugins();
    $bocs_instances = 0;
    $current_plugin_basename = plugin_basename(__FILE__);

    foreach ($all_plugins as $plugin_path => $plugin_data) {
        if (strpos(strtolower($plugin_data['Name']), 'bocs') !== false) {
            $bocs_instances++;
            // If more than one instance is found and this isn't the active one
            if ($bocs_instances > 1 && is_plugin_active($plugin_path) && $plugin_path !== $current_plugin_basename) {
                deactivate_plugins($current_plugin_basename);
                wp_die(
                    sprintf(
                        '%s<br><br>%s<br><br>%s<br><br><a href="%s">%s</a>',
                        __('Another instance of Bocs plugin is already active.', 'bocs-wordpress'),
                        __('You can either:', 'bocs-wordpress'),
                        __('1. Update the existing plugin if an update is available, or<br>2. Deactivate the existing Bocs plugin before activating this new instance.', 'bocs-wordpress'),
                        esc_url(admin_url('plugins.php')),
                        __('← Go back to plugins page', 'bocs-wordpress')
                    ),
                    __('Plugin Activation Error', 'bocs-wordpress'),
                    array('back_link' => false)
                );
            }
        }
    }
}

/**
 * Runs on plugin activation.
 *
 * Verifies compatibility requirements and initializes plugin components.
 *
 * @since    0.0.1
 * @throws   Exception If activation requirements are not met or initialization fails.
 * @return   void
 */
function activate_bocs_plugin() {
    // Check for duplicate plugin instances
    check_duplicate_bocs_plugin();

    // Check WordPress version
    if (version_compare(get_bloginfo('version'), '5.6.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            sprintf(
                '%s<br><br><a href="%s">%s</a>',
                __('This plugin requires WordPress version 5.6.0 or higher.', 'bocs-wordpress'),
                esc_url(admin_url('plugins.php')),
                __('← Go back to plugins page', 'bocs-wordpress')
            ),
            __('Plugin Activation Error', 'bocs-wordpress'),
            array('back_link' => false)
        );
    }

    // Check PHP version
    if (version_compare(PHP_VERSION, '7.3.5', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            sprintf(
                '%s<br><br><a href="%s">%s</a>',
                __('This plugin requires PHP version 7.3.5 or higher.', 'bocs-wordpress'),
                esc_url(admin_url('plugins.php')),
                __('← Go back to plugins page', 'bocs-wordpress')
            ),
            __('Plugin Activation Error', 'bocs-wordpress'),
            array('back_link' => false)
        );
    }

    // Check WooCommerce dependency
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            sprintf(
                '%s<br><br><a href="%s">%s</a>',
                __('This plugin requires WooCommerce to be installed and active. Please install and activate WooCommerce first.', 'bocs-wordpress'),
                esc_url(admin_url('plugins.php')),
                __('← Go back to plugins page', 'bocs-wordpress')
            ),
            __('Plugin Activation Error', 'bocs-wordpress'),
            array('back_link' => false)
        );
    }

    // Check if WooCommerce version is compatible
    if (defined('WC_VERSION') && version_compare(WC_VERSION, '5.0.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            sprintf(
                '%s (Current: %s)<br><br><a href="%s">%s</a>',
                __('This plugin requires WooCommerce version 5.0.0 or higher.', 'bocs-wordpress'),
                WC_VERSION,
                esc_url(admin_url('plugins.php')),
                __('← Go back to plugins page', 'bocs-wordpress')
            ),
            __('Plugin Activation Error', 'bocs-wordpress'),
            array('back_link' => false)
        );
    }

    // Check for required PHP extensions
    $required_extensions = array('curl', 'json', 'mbstring');
    $missing_extensions = array();
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $missing_extensions[] = $ext;
        }
    }
    if (!empty($missing_extensions)) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            sprintf(
                '%s: %s<br><br><a href="%s">%s</a>',
                __('Missing required PHP extensions', 'bocs-wordpress'),
                implode(', ', $missing_extensions),
                esc_url(admin_url('plugins.php')),
                __('← Go back to plugins page', 'bocs-wordpress')
            ),
            __('Plugin Activation Error', 'bocs-wordpress'),
            array('back_link' => false)
        );
    }

    // Check write permissions for necessary directories
    $required_writable_paths = array(
        WP_CONTENT_DIR . '/uploads'
    );
    $non_writable_paths = array();
    foreach ($required_writable_paths as $path) {
        if (!is_writable($path)) {
            $non_writable_paths[] = $path;
        }
    }
    if (!empty($non_writable_paths)) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            sprintf(
                '%s: %s<br><br><a href="%s">%s</a>',
                __('The following directories need to be writable', 'bocs-wordpress'),
                implode(', ', $non_writable_paths),
                esc_url(admin_url('plugins.php')),
                __('← Go back to plugins page', 'bocs-wordpress')
            ),
            __('Plugin Activation Error', 'bocs-wordpress'),
            array('back_link' => false)
        );
    }

    try {
        // Initialize Bocs account and endpoints
        $bocs_account = new Bocs_Account();
        $bocs_account->register_bocs_account_endpoint();
        
        // Call Bocs class activation method
        $bocs = new Bocs();
        $bocs->auto_add_bocs_keys();
        Bocs::activate();
        
        flush_rewrite_rules();
    } catch (Exception $e) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            sprintf(
                '%s: %s<br><br><a href="%s">%s</a>',
                __('Error activating plugin', 'bocs-wordpress'),
                esc_html($e->getMessage()),
                esc_url(admin_url('plugins.php')),
                __('← Go back to plugins page', 'bocs-wordpress')
            ),
            __('Plugin Activation Error', 'bocs-wordpress'),
            array('back_link' => false)
        );
    }
}

/**
 * Runs on plugin deactivation.
 *
 * Cleans up plugin data and settings.
 *
 * @since    0.0.1
 * @return   void
 */
function deactivate_bocs_plugin() {
    // Call Bocs class deactivation method
    Bocs::deactivate();
    
    // Clean up any scheduled actions
    if (function_exists('as_unschedule_all_actions')) {
        as_unschedule_all_actions('', array(), 'bocs');
    }

    // Clear any plugin-specific rewrite rules
    flush_rewrite_rules();

    // Optionally, you can add more cleanup tasks here
    // For example, removing temporary files, clearing caches, etc.
}

/**
 * Adds plugin action links.
 *
 * Adds a settings link to the plugin listing page.
 *
 * @since    0.0.1
 * @param    array $links    Existing plugin action links
 * @return   array           Modified plugin action links
 */
function action_bocs_plugin($links) {
    $links = array_merge(array(
        '<a href="' . esc_url(admin_url('/admin.php?page=bocs-settings')) . '">' . __('Settings', 'bocs-wordpress') . '</a>'
    ), $links);
    return $links;
}

/**
 * Begins execution of the plugin.
 *
 * Initializes the plugin core functionality.
 *
 * @since    0.0.1
 * @return   void
 */
function bocs_check_woocommerce() {
    // Check if WooCommerce class exists AND is active
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    if (!class_exists('WooCommerce') || !is_plugin_active('woocommerce/woocommerce.php')) {
        add_action('admin_notices', function() {
            ?>
            <div class="error">
                <p><?php _e('Bocs requires WooCommerce to be installed and active.', 'bocs-wordpress'); ?></p>
            </div>
            <?php
        });
        return false;
    }
    return true;
}

// Define plugin constants
define('BOCS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BOCS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BOCS_TEMPLATE_PATH', BOCS_PLUGIN_DIR . 'templates/');

/**
 * Initialize the plugin.
 */
function run_plugin() {
    // Only run the check after plugins are loaded
    if (!did_action('plugins_loaded')) {
        add_action('plugins_loaded', 'run_plugin');
        return;
    }
    
    if (bocs_check_woocommerce()) {
        $plugin = new Bocs();
        $plugin->run();
    }
}

// Register hooks
add_action('plugin_action_links_' . plugin_basename(__FILE__), 'action_bocs_plugin', 10);
register_activation_hook(__FILE__, 'activate_bocs_plugin');
register_deactivation_hook(__FILE__, 'deactivate_bocs_plugin');
run_plugin();

/**
 * Initialize the plugin updater in admin area
 */
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'includes/Bocs_Updater.php';
    try {
        $updater = new Bocs_Updater(__FILE__);
    } catch (Exception $e) {
        error_log(sprintf('Bocs Plugin: Error initializing updater - %s', $e->getMessage()));
    }
}