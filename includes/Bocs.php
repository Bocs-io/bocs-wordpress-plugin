<?php
/**
 * Bocs
 * COPYRIGHT Bocs.io PTY LTD 2019
 * URL: https://bocs.io
 * Email: hello@bocs.io
 *
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 */
class Bocs
{

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 */
	public function __construct()
	{
		$this->version = BOCS_VERSION;
		$this->plugin_name = BOCS_NAME;

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Bocs_Service_Loader. Orchestrates the hooks of the plugin.
	 * - Bocs_Service_i18n. Defines internationalization functionality.
	 * - Bocs_Service_Admin. Defines all hooks for the admin area.
	 * - Bocs_Service_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 */
	private function load_dependencies(){

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path(dirname(__FILE__)).'includes/Loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path(dirname(__FILE__)).'includes/Internationalization.php';

		require_once plugin_dir_path(dirname(__FILE__)).'includes/constants.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path(dirname(__FILE__)).'includes/Admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path(dirname(__FILE__)).'includes/Frontend.php';
		require_once plugin_dir_path(dirname(__FILE__)).'includes/Auth.php';
        // Bocs's Shortcode
        require_once plugin_dir_path(dirname(__FILE__)).'includes/Shortcode.php';
        require_once plugin_dir_path(dirname(__FILE__)).'includes/Contact.php';

		$this->loader = new Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Bocs_Service_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 */
	private function set_locale()
	{
		$plugin_i18n = new Internationalization();
		$this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 */
	private function define_admin_hooks()
	{

		$plugin_admin = new Admin();

		// $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles', 10);
		// $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts', 10);

		// Actions
		$this->loader->add_action('admin_menu', $plugin_admin, 'bocs_add_settings_page');
		$this->loader->add_action('admin_init', $plugin_admin, 'bocs_register_settings');

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 */
	private function define_public_hooks()
	{

		$plugin_public = new Frontend();
		// Scripts and styles
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles', 10);
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts', 10);
        $this->loader->add_action('template_redirect', $plugin_public, 'bocs_add_to_cart_and_redirect');

		$auth_public = new Auth();
		$this->loader->add_action('rest_api_init', $auth_public, 'add_api_routes');
		$this->loader->add_filter('rest_api_init', $auth_public, 'add_cors_support');
		$this->loader->add_filter('determine_current_user', $auth_public, 'determine_current_user', 99);
		$this->loader->add_filter('rest_pre_dispatch', $auth_public, 'rest_pre_dispatch', 10, 2);

        $shortcode = new Shortcode();
        $this->loader->add_action('init', $shortcode, 'bocs_shortcodes_init');

        $contact = new Contact();

        // syncs the Add User to Bocs
        $this->loader->add_action('user_register', $contact, 'sync_add_contact');
        // syncs the Update User to Bocs
        $this->loader->add_action('edit_user_profile_update', $contact, 'sync_update_contact');
        // sybcs the delete user to Bocs
        $this->loader->add_action('delete_user', $contact, 'sync_delete_contact');

	}

	/**
	 * Activates the plugin
	 *
	 * @return void
	 */
	public static function activate()
	{
		// Activate registration required
		update_option('woocommerce_enable_guest_checkout', 'no');
		update_option('woocommerce_enable_checkout_login_reminder', 'yes');
	}

	/**
	 * Deactivates the plugin
	 *
	 * @return void
	 */
	public static function deactivate()
	{

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run()
	{
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 */
	public function get_plugin_name()
	{
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 */
	public function get_loader()
	{
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 */
	public function get_version()
	{
		return $this->version;
	}

}