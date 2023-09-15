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
		// $this->set_locale();
        $this->define_updater_hooks();
		$this->define_admin_hooks();
		$this->define_public_hooks();

        $this->define_account_profile_hooks();
        $this->define_sync_hooks();

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
		//require_once plugin_dir_path(dirname(__FILE__)).'includes/Internationalization.php';

		require_once plugin_dir_path(dirname(__FILE__)).'includes/constants.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path(dirname(__FILE__)).'includes/Admin.php';
        require_once plugin_dir_path(dirname(__FILE__)).'includes/Account.php';

        require_once plugin_dir_path(dirname(__FILE__)).'includes/Bocs_List_Table.php';

        require_once plugin_dir_path(dirname(__FILE__)).'includes/Updater.php';

        require_once plugin_dir_path(dirname(__FILE__)).'includes/Sync.php';

        require_once plugin_dir_path(dirname(__FILE__)).'includes/Curl.php';

		require_once plugin_dir_path(dirname(__FILE__)).'includes/Error_Logs_List_Table.php';

		require_once plugin_dir_path(dirname(__FILE__)).'includes/Bocs_Log_Handler.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		//require_once plugin_dir_path(dirname(__FILE__)).'includes/Frontend.php';
		//require_once plugin_dir_path(dirname(__FILE__)).'includes/Auth.php';

		// Bocs's Shortcode
		require_once plugin_dir_path(dirname(__FILE__)).'includes/Shortcode.php';

		//require_once plugin_dir_path(dirname(__FILE__)).'includes/Contact.php';
		//require_once plugin_dir_path(dirname(__FILE__)).'includes/Tag.php';
		//require_once plugin_dir_path(dirname(__FILE__)).'includes/Widget.php';

		// require_once plugin_dir_path(dirname(__FILE__)).'libraries/action-scheduler/action-scheduler.php';

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
	 * This will handle the hooks related to Bocs' App syncs
	 *
	 */
	private function define_sync_hooks(){

        $syncing = new Sync();

        $this->loader->add_action('profile_update', $syncing, 'profile_update', 10, 3);
        $this->loader->add_filter('insert_user_meta', $syncing, 'insert_user_meta', 10, 4);

		$this->loader->add_action('woocommerce_save_account_details', $syncing, 'save_account_details');

		// add new user hooks
		$this->loader->add_action('user_register', $syncing, 'bocs_user_register');
    }

    /**
     * handles hooks related to the My Account / My Profile
     *
     * @return void
     */
    private function define_account_profile_hooks(){

        $account = new Account();

        $this->loader->add_filter('woocommerce_account_menu_items', $account, 'add_bocs_menu');
        $this->loader->add_action('init', $account, 'bocs_subscription_endpoint');
        $this->loader->add_filter('woocommerce_account_bocs-subscription_endpoint', $account, 'bocs_subscription_endpoint_template');

    }

    private function define_updater_hooks(){

        $updater = new Updater(plugin_dir_path(dirname(__FILE__)) . 'bocs.php' );

        $this->loader->add_action('admin_init', $updater, 'set_plugin_properties');


        $this->loader->add_filter('pre_set_site_transient_update_plugins', $updater, 'modify_transient');
        $this->loader->add_filter('plugins_api', $updater, 'plugin_popup', 10, 3);
        $this->loader->add_filter('upgrader_post_install', $updater, 'after_install', 10, 3);
        /*$this->loader->add_filter('upgrader_pre_download', $this, function (){
            global $updater;
            $this->loader->add_filter('http_request_args', $updater, 'download_package', 15, 2);
            return false;
        });*/
    }

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 */
	private function define_admin_hooks()
	{
		$plugin_admin = new Admin();

		$this->loader->add_action('enqueue_block_editor_assets', $plugin_admin, 'bocs_widget_script_register');
		$this->loader->add_action('wp_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'admin_enqueue_scripts');

		$this->loader->add_action('admin_menu', $plugin_admin, 'bocs_add_settings_page');

		$this->loader->add_action('init', $plugin_admin, 'register_bocs_product_type');
		$this->loader->add_filter('woocommerce_product_data_tabs', $plugin_admin, 'bocs_product_tab');
		$this->loader->add_action('woocommerce_product_data_panels', $plugin_admin, 'bocs_product_panel');
		$this->loader->add_action('admin_footer', $plugin_admin, 'bocs_admin_custom_js');
		$this->loader->add_action('woocommerce_process_product_meta', $plugin_admin, 'bocs_process_product_meta');

        // add product
        $this->loader->add_action('wp_ajax_create_product', $plugin_admin, 'create_product_ajax_callback');
        $this->loader->add_action('wp_ajax_nopriv_create_product', $plugin_admin, 'create_product_ajax_callback');

        // create coupon
        $this->loader->add_action('wp_ajax_create_coupon', $plugin_admin, 'create_coupon_ajax_callback');
        $this->loader->add_action('wp_ajax_nopriv_create_coupon', $plugin_admin, 'create_coupon_ajax_callback');

        // update product
        $this->loader->add_action('wp_ajax_update_product', $plugin_admin, 'update_product_ajax_callback');
        $this->loader->add_action('wp_ajax_nopriv_update_product', $plugin_admin, 'update_product_ajax_callback');

        // search product
        $this->loader->add_action('wp_ajax_search_product', $plugin_admin, 'search_product_ajax_callback');
        $this->loader->add_action('wp_ajax_nopriv_search_product', $plugin_admin, 'search_product_ajax_callback');

        // create bocs subscription and order if the order is in processing
        $this->loader->add_action('woocommerce_order_status_processing', $plugin_admin, 'bocs_order_status_processing');

        // this is for the saving of the bocs and collections list
        // so that it will show the default and/or the selected option
        // with the ones listed
        $this->loader->add_action('wp_ajax_save_widget_options', $plugin_admin, 'save_widget_options_callback');
        $this->loader->add_action('wp_ajax_nopriv_save_widget_options', $plugin_admin, 'save_widget_options_callback');

		// adding icons on the list of users
		// to determine which one is from WordPress or Bocs
		$this->loader->add_action('admin_head-users.php', $plugin_admin, 'custom_user_admin_icon_css');
		$this->loader->add_filter('manage_users_columns', $plugin_admin, 'custom_add_user_column');
		$this->loader->add_filter('manage_users_custom_column', $plugin_admin, 'custom_admin_user_icon', 15, 3);

		// adds filter to the list of users
		$this->loader->add_action('restrict_manage_users', $plugin_admin, 'custom_add_source_filter');
		$this->loader->add_action('pre_get_users', $plugin_admin, 'custom_filter_users_by_source');

		// adding metabox on the right side of the page
		$this->loader->add_action('add_meta_boxes', $plugin_admin, 'add_bocs_widget_metabox');

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 */
	private function define_public_hooks()
	{

		//$plugin_public = new Frontend();
		// Scripts and styles
		//$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles', 10);
		//$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts', 10);
		//$this->loader->add_action('template_redirect', $plugin_public, 'bocs_add_to_cart_and_redirect');

		//$auth_public = new Auth();
		//$this->loader->add_action('rest_api_init', $auth_public, 'add_api_routes');
		//$this->loader->add_filter('rest_api_init', $auth_public, 'add_cors_support');
		//$this->loader->add_filter('determine_current_user', $auth_public, 'determine_current_user', 99);
		//$this->loader->add_filter('rest_pre_dispatch', $auth_public, 'rest_pre_dispatch', 10, 2);

		$shortcode = new Shortcode();
		$this->loader->add_action('init', $shortcode, 'bocs_shortcodes_init');

		//$contact = new Contact();

		// syncs the Add User to Bocs
		//$this->loader->add_action('user_register', $contact, 'sync_add_contact');
		// syncs the Update User to Bocs
		//$this->loader->add_action('edit_user_profile_update', $contact, 'sync_update_contact');
		// syncs the delete user to Bocs
		//$this->loader->add_action('delete_user', $contact, 'sync_delete_contact');

		//$this->loader->add_action('show_user_profile', $contact, 'user_render_fields');
		//$this->loader->add_action('edit_user_profile', $contact, 'user_render_fields');

	}

	/**
	 * Activates the plugin
	 *
	 * @return void
	 */
	public static function activate()
	{
		// Activate registration required
        // we will check if the api@bocs.io user is created
        $bocs_account = get_user_by('email', 'api@bocs.io');

        if ($bocs_account){

            // get will get the user meta
            $bocs_store_id = get_user_meta($bocs_account->ID, 'bocs_store', true);
            $bocs_organization = get_user_meta($bocs_account->ID, 'bocs_organization', true);
            $bocs_authorization = get_user_meta($bocs_account->ID, 'bocs_authorization', true);
            $bocs_woocommerce_key = get_user_meta($bocs_account->ID, 'bocs_wookey', true);
            $bocs_woocommerce_secret = get_user_meta($bocs_account->ID, 'bocs_woosecret', true);

            // then we well update bocs settings
            $options = get_option( 'bocs_plugin_options' );
            $options['bocs_headers'] = $options['bocs_headers'] ?? array();

            $settings_counter = 0;

            if( $bocs_organization !== false) {
                if (trim($bocs_organization) !== ""){
                    $options['bocs_headers']['organization'] = $bocs_organization;
                    $settings_counter++;
                }
            }

            if( $bocs_authorization !== false) {
                if (trim($bocs_authorization) !== ""){
                    $options['bocs_headers']['authorization'] = $bocs_authorization;
                    $settings_counter++;
                }
            }

            if( $bocs_store_id !== false) {
                if (trim($bocs_store_id) !== ""){
                    $options['bocs_headers']['store'] = $bocs_store_id;
                    $settings_counter++;
                }
            }

            if( $bocs_woocommerce_key !== false) {
                if (trim($bocs_woocommerce_key) !== ""){
                    $options['bocs_headers']['woocommerce_key'] = $bocs_woocommerce_key;
                    $settings_counter++;
                }
            }

            if( $bocs_woocommerce_secret !== false) {
                if (trim($bocs_woocommerce_secret) !== ""){
                    $options['bocs_headers']['woocommerce_secret'] = $bocs_woocommerce_secret;
                    $settings_counter++;
                }
            }

            // then save the bocs settings
            update_option('bocs_plugin_options', $options);

            // and once it was saved, we will delete the user meta
            // will be only delete if the 5 meta values were created/added
            if ($settings_counter === 5){
                delete_user_meta($bocs_account->ID, 'bocs_store');
                delete_user_meta($bocs_account->ID, 'bocs_organization');
                delete_user_meta($bocs_account->ID, 'bocs_authorization');
                delete_user_meta($bocs_account->ID, 'bocs_wookey');
                delete_user_meta($bocs_account->ID, 'bocs_woosecret');
            }

        }
	}

    public function auto_add_bocs_keys(){
        // we will check if the api@bocs.io user is created
        $bocs_account = get_user_by('email', 'api@bocs.io');

        if ($bocs_account){

            // get will get the user meta
            $bocs_store_id = get_user_meta($bocs_account->ID, 'bocs_store', true);
            $bocs_organization = get_user_meta($bocs_account->ID, 'bocs_organization', true);
            $bocs_authorization = get_user_meta($bocs_account->ID, 'bocs_authorization', true);
            $bocs_woocommerce_key = get_user_meta($bocs_account->ID, 'bocs_wookey', true);
            $bocs_woocommerce_secret = get_user_meta($bocs_account->ID, 'bocs_woosecret', true);

            // then we well update bocs settings
            $options = get_option( 'bocs_plugin_options' );
            $options['bocs_headers'] = $options['bocs_headers'] ?? array();

            $settings_counter = 0;

            if( $bocs_organization !== false) {
                if (trim($bocs_organization) !== ""){
                    $options['bocs_headers']['organization'] = $bocs_organization;
                    $settings_counter++;
                }
            }

            if( $bocs_authorization !== false) {
                if (trim($bocs_authorization) !== ""){
                    $options['bocs_headers']['authorization'] = $bocs_authorization;
                    $settings_counter++;
                }
            }

            if( $bocs_store_id !== false) {
                if (trim($bocs_store_id) !== ""){
                    $options['bocs_headers']['store'] = $bocs_store_id;
                    $settings_counter++;
                }
            }

            if( $bocs_woocommerce_key !== false) {
                if (trim($bocs_woocommerce_key) !== ""){
                    $options['bocs_headers']['woocommerce_key'] = $bocs_woocommerce_key;
                    $settings_counter++;
                }
            }

            if( $bocs_woocommerce_secret !== false) {
                if (trim($bocs_woocommerce_secret) !== ""){
                    $options['bocs_headers']['woocommerce_secret'] = $bocs_woocommerce_secret;
                    $settings_counter++;
                }
            }

            // and once it was saved, we will delete the user meta
            // will be only delete if the 5 meta values were created/added
            if ($settings_counter === 5){

                // then save the bocs settings
                update_option('bocs_plugin_options', $options);

                delete_user_meta($bocs_account->ID, 'bocs_store');
                delete_user_meta($bocs_account->ID, 'bocs_organization');
                delete_user_meta($bocs_account->ID, 'bocs_authorization');
                delete_user_meta($bocs_account->ID, 'bocs_wookey');
                delete_user_meta($bocs_account->ID, 'bocs_woosecret');
            }

        }
    }


	/**
	 * Deactivates the plugin
	 *
	 * @return void
	 */
	public static function deactivate()
	{
        // do nothing
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