<?php

/**
 *
 * @link              https://bocs.io
 * @since             0.9.15
 * @package           bocs
 *
 * @wordpress-plugin
 * Plugin Name:       Bocs
 * Plugin URI:        https://bocs.io
 * Description:       The Bocs service is a powerful sales channel for your products.
 * Version:           0.9.15
 * Author:            Bocs.io
 * Author URI:        https://bocs.io
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bocs
 */

if (!defined('WPINC') || !defined('ABSPATH')) {
	die;
}

define('BOCS_VERSION', '0.9.15');
define('BOCS_NAME', 'Bocs');
define('BOCS_SLUG', 'bocs');

if ( ! in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

	// WooCommerce is needed to be installed and activated
	echo '<div class="error"><p>To use <b>Bocs</b> requires <a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a> to be installed and active.</p></div>';
	die;

} else {

	if (file_exists(dirname(__FILE__).'/includes/vendor/autoload.php')) {
		require_once dirname(__FILE__).'/includes/vendor/autoload.php';
	}

	/**
	 * The core plugin class that is used to define internationalization,
	 * admin-specific hooks, and public-facing site hooks.
	 */
	require plugin_dir_path(__FILE__).'includes/Bocs.php';

	/**
	 * The code that runs during plugin activation.
	 */
	function activate_custom_page()
	{
		Bocs::activate();
	}

	/**
	 * The code that runs during plugin deactivation.
	 */
	function deactivate_custom_page()
	{
		Bocs::deactivate();
	}

	/**
	 * The code that runs when the plugin is activate.
	 */
	function action_custom_page( $links ) {
		$links = array_merge( array(
			'<a href="' . esc_url( admin_url( '/options-general.php?page=bocs-plugin' ) ) . '">' . __( 'Settings', 'textdomain' ) . '</a>'
		), $links );
		return $links;
	}

	/**
	 * Begins execution of the plugin.
	 *
	 * Since everything within the plugin is registered via hooks,
	 * then kicking off the plugin from this point in the file does
	 * not affect the page life cycle.
	 *
	 */
	function run_plugin()
	{
		$plugin = new Bocs();
		$plugin->run();
	}

	add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'action_custom_page', 10 );
	register_activation_hook(__FILE__, 'activate_custom_page');
	register_deactivation_hook(__FILE__, 'deactivate_custom_page');
	run_plugin();
}