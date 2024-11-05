<?php

/**
 *
 * @link              https://bocs.io
 * @since             0.0.1
 * @package           bocs
 *
 * @wordpress-plugin
 * Plugin Name:       Bocs
 * Plugin URI:        https://bocs.io
 * Description:       The Bocs service is a powerful sales channel for your products.
 * Version:           0.0.74
 * Author:            Bocs.io
 * Author URI:        https://bocs.io
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bocs
 * Tag
 * Requires at least: 5.6.0
 * Requires PHP: 7.3.5
 */
if (! defined('WPINC') || ! defined('ABSPATH')) {
    die();
}

define('BOCS_VERSION', '0.0.74');
define('BOCS_NAME', 'Bocs');
define('BOCS_SLUG', 'bocs');
define("BOCS_API_URL", "https://hudaq97o4b.execute-api.ap-southeast-2.amazonaws.com/prod/");
define("BOCS_LIST_WIDGETS_URL", BOCS_API_URL. "list-widgets/");

// just in case the action scheduler is not yet installed in woommerce (or other plugins)
if (! function_exists('as_has_scheduled_action')) {
    require_once(plugin_dir_path(__FILE__) . '/libraries/action-scheduler/action-scheduler.php');
}

if (file_exists(dirname(__FILE__) . '/includes/vendor/autoload.php')) {
    require_once dirname(__FILE__) . '/includes/vendor/autoload.php';
}

if (! class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

if (! class_exists('Updater')) {
    require_once dirname(__FILE__) . '/includes/Updater.php';
}

if (! function_exists('wp_create_nonce')) {
    require_once(ABSPATH . 'wp-includes/pluggable.php');
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/Bocs.php';
require plugin_dir_path(__FILE__) . 'includes/Bocs_Account.php';

/**
 * The code that runs during plugin activation.
 */
function activate_bocs_plugin()
{
    if (! class_exists('WooCommerce')) {
        // Deactivate the current plugin
        deactivate_plugins(plugin_basename(__FILE__));

        // Throw an error in the WordPress admin console
        wp_die('Sorry, but this plugin requires the WooCommerce Plugin to be installed and active.');
    }
    Bocs::activate();
    $bocs_account = new Bocs_Account();
    $bocs_account->register_bocs_account_endpoint();
    flush_rewrite_rules();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_bocs_plugin()
{
    Bocs::deactivate();
}

/**
 * The code that runs when the plugin is activated.
 */
function action_bocs_plugin($links)
{
    $links = array_merge(array(
        '<a href="' . esc_url(admin_url('/options-general.php?page=bocs')) . '">' . __('Settings', 'textdomain') . '</a>'
    ), $links);
    return $links;
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */
function run_plugin()
{
    $plugin = new Bocs();
    $plugin->run();
}

add_action('plugin_action_links_' . plugin_basename(__FILE__), 'action_bocs_plugin', 10);
register_activation_hook(__FILE__, 'activate_bocs_plugin');
register_deactivation_hook(__FILE__, 'deactivate_bocs_plugin');
run_plugin();

if (! class_exists("Bocs\\Updater\\Repository\\AbstractRepository")) {
    require_once dirname(__FILE__) . '/includes/Updater/Repository/AbstractRepository.php';
}

if (! class_exists("Bocs\Updater\Repository\Github")) {
    require_once dirname(__FILE__) . '/includes/Updater/Repository/Github.php';
}

if (! class_exists("Bocs\Updater\Repository\BocsRepo")) {
    require_once dirname(__FILE__) . '/includes/Updater/Repository/BocsRepo.php';
}

if (! class_exists("Bocs\Updater\Updater")) {
    require_once dirname(__FILE__) . '/includes/Updater/Updater.php';
}

$repo = new \Bocs\Updater\Repository\BocsRepo();
$updater = new \Bocs\Updater\Updater(__FILE__, $repo);
$updater->bootstrap();
