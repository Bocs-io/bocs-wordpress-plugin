<?php

/**
 * Core Bocs Plugin Class
 *
 * This class serves as the main plugin controller, handling initialization,
 * hook registration, and core functionality management for the Bocs plugin.
 * It orchestrates all plugin components including admin interfaces, public
 * facing features, API integrations, and WooCommerce extensions.
 *
 * @package Bocs
 * @since 0.0.100
 */
class Bocs
{

    /**
     * The loader responsible for managing all action and filter hooks.
     *
     * @since 0.0.100
     * @access protected
     * @var Loader $loader Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier for this plugin.
     *
     * @since 0.0.100
     * @access protected
     * @var string $plugin_name The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since 0.0.100
     * @access protected
     * @var string $version The current version of the plugin.
     */
    protected $version;

    /**
     * Initialize the plugin and set its core properties.
     *
     * This constructor sets up the plugin's basic attributes and initiates
     * all core functionality by loading dependencies and registering hooks
     * for various plugin components.
     *
     * @since 0.0.100
     * @access public
     */
    public function __construct()
    {
        $this->version = BOCS_VERSION;
        $this->plugin_name = BOCS_NAME;

        $this->load_dependencies();
        $this->define_updater_hooks();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_email_hooks();
        $this->define_checkout_page_hooks();
        // $this->define_order_hooks();

        $this->define_account_profile_hooks();
        $this->define_sync_hooks();
        $this->define_bocs_email_api();
        $this->define_payment_api_hooks();
        $this->define_stripe_hooks();
    }

    /**
     * Load and register all plugin dependencies.
     *
     * This method includes all required plugin files and initializes the
     * hook loader. It handles core functionality files, admin interfaces,
     * public facing components, and third-party integrations.
     *
     * Files loaded include:
     * - Core plugin loader
     * - Admin interface handlers
     * - Public facing components
     * - WooCommerce integrations
     * - API handlers
     * - Email system
     * - Payment processing
     *
     * @since 0.0.100
     * @access private
     * @return void
     */
    private function load_dependencies()
    {

        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Loader.php';

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        // require_once plugin_dir_path(dirname(__FILE__)).'includes/Internationalization.php';

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/constants.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Admin.php';

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_List_Table.php';

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Updater.php';

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Sync.php';

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Curl.php';

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Error_Logs_List_Table.php';

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Log_Handler.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        // require_once plugin_dir_path(dirname(__FILE__)).'includes/Frontend.php';
        // require_once plugin_dir_path(dirname(__FILE__)).'includes/Auth.php';

        // Bocs's Shortcode
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Shortcode.php';

        // Api class - for the custom rest api
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Api.php';

        // require_once plugin_dir_path(dirname(__FILE__)).'includes/Contact.php';
        // require_once plugin_dir_path(dirname(__FILE__)).'includes/Tag.php';
        // require_once plugin_dir_path(dirname(__FILE__)).'includes/Widget.php';

        // require_once plugin_dir_path(dirname(__FILE__)).'libraries/action-scheduler/action-scheduler.php';

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Cart.php';

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Account.php';

        // email rest api
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Email_API.php';

        // require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Email.php';

        /*
         * if (!class_exists('WC_Email')) {
         * require_once WC_ABSPATH . 'includes/class-wc-emails.php';
         * }
         */

        // emails
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/emails/class-bocs-email-processing-renewal-order.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/emails/class-bocs-email-completed-renewal-order.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/emails/class-bocs-email-on-hold-renewal-order.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/emails/class-bocs-email-customer-renewal-invoice.php';

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Bocs.php';

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Payment_API.php';

        // require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Order_Hooks.php';

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Stripe_Hooks.php';

        $this->loader = new Loader();
    }

    /**
     * Register synchronization hooks with the Bocs App.
     *
     * Handles all hooks related to keeping WordPress user data in sync
     * with the Bocs application. This includes user profile updates,
     * account modifications, and new user registrations.
     *
     * @since 0.0.100
     * @access private
     * @return void
     */
    private function define_sync_hooks()
    {
        $syncing = new Sync();

        $this->loader->add_action('profile_update', $syncing, 'profile_update', 10, 3);
        // $this->loader->add_filter('insert_user_meta', $syncing, 'insert_user_meta', 10, 4);

        $this->loader->add_action('woocommerce_save_account_details', $syncing, 'save_account_details');

        // add new user hooks
        $this->loader->add_action('user_register', $syncing, 'bocs_user_register');
    }

    /**
     * handles hooks related to the My Account / My Profile
     *
     * @return void
     */
    private function define_account_profile_hooks()
    {
        $bocs_account = new Bocs_Account();

        // bocs subscriptions under My Account
        $this->loader->add_filter('woocommerce_account_menu_items', $bocs_account, 'bocs_account_menu_item');
        $this->loader->add_action('init', $bocs_account, 'register_bocs_account_endpoint');
        $this->loader->add_action('woocommerce_account_bocs-subscriptions_endpoint', $bocs_account, 'bocs_endpoint_content');

        // bocs subscription under My Account page
        $this->loader->add_action('init', $bocs_account, 'register_bocs_view_subscription_endpoint');

        $this->loader->add_action('woocommerce_account_bocs-view-subscription_endpoint', $bocs_account, 'bocs_view_subscription_endpoint_content');
    }

    private function define_updater_hooks()
    {
        $updater = new Updater(plugin_dir_path(dirname(__FILE__)) . 'bocs.php');

        $this->loader->add_action('admin_init', $updater, 'set_plugin_properties');

        $this->loader->add_filter('pre_set_site_transient_update_plugins', $updater, 'modify_transient');
        $this->loader->add_filter('plugins_api', $updater, 'plugin_popup', 10, 3);
        $this->loader->add_filter('upgrader_post_install', $updater, 'after_install', 10, 3);
        
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     */
    private function define_admin_hooks()
    {
        $plugin_admin = new Admin();

        $this->loader->add_action('enqueue_block_editor_assets', $plugin_admin, 'bocs_widget_script_register');
        // $this->loader->add_action('wp_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'admin_enqueue_scripts');

        $this->loader->add_action('admin_menu', $plugin_admin, 'bocs_add_settings_page');

        // @todo - add bocs product type
        // $this->loader->add_action('init', $plugin_admin, 'register_bocs_product_type');

        $this->loader->add_action('init', $plugin_admin, 'update_widgets_collections');

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
        // $this->loader->add_filter('woocommerce_store_api_add_to_cart_data', $plugin_admin, 'add_custom_to_cart_data', 10, 2);
        // $this->loader->add_action('woocommerce_add_cart_item_data', $plugin_admin, 'add_custom_cart_item_data', 10, 3);
        // $this->loader->add_action('woocommerce_checkout_create_order_line_item', $plugin_admin, 'add_cart_item_meta_to_order_items', 10, 4);

        // $this->loader->add_action('woocommerce_checkout_create_order', $plugin_admin, 'add_custom_order_meta', 10, 2);
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

        // adding meta box on the product page
        $this->loader->add_action('add_meta_boxes', $plugin_admin, 'add_product_sidebar_to_woocommerce_admin');

        // adding meta box for the related orders
        $this->loader->add_action('add_meta_boxes', $plugin_admin, 'show_related_orders');

        $this->loader->add_filter('woocommerce_account_settings', $plugin_admin, 'add_guest_checkout_setting_note', 10, 1);
        $this->loader->add_filter('woocommerce_payment_gateways_settings', $plugin_admin, 'add_guest_checkout_setting_note', 10, 1);
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     */
    private function define_public_hooks()
    {
        $shortcode = new Shortcode();
        $this->loader->add_action('init', $shortcode, 'bocs_shortcodes_init');

        $api_class = new Api();
        $this->loader->add_action('rest_api_init', $api_class, 'custom_api_routes');

        $bocs_cart = new Bocs_Cart();
        $this->loader->add_action('woocommerce_cart_collaterals', $bocs_cart, 'add_subscription_options_to_cart');

        $plugin_admin = new Admin();
        $this->loader->add_action('wp_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('template_redirect', $plugin_admin, 'capture_bocs_parameter');
        $this->loader->add_action('woocommerce_checkout_order_processed', $plugin_admin, 'custom_order_created_action', 10, 3);
        $this->loader->add_action('wp_login', $plugin_admin, 'bocs_user_id_check', 10, 2);

        $this->loader->add_filter('login_message', $plugin_admin, 'display_bocs_login_message');

        // $bocs_cart = new Bocs_Cart();
        // $this->loader->add_action('woocommerce_cart_totals_before_shipping', $bocs_cart, 'bocs_cart_totals_before_shipping');
    }

    public function define_email_hooks()
    {
        $processing_orders = new WC_Bocs_Email_Processing_Renewal_Order();
        // $bocs_email = new Bocs_Email();

        // $this->loader->add_filter('woocommerce_email_classes', $bocs_email, 'add_bocs_processing_renewal_email_class', 10, 1);

        // $this->loader->add_action('woocommerce_order_status_pending_to_processing', $processing_orders, 'trigger', 10, 1);
        // $this->loader->add_action('woocommerce_order_status_failed_to_processing', $processing_orders, 'trigger', 10, 1);
        // $this->loader->add_action('woocommerce_order_status_cancelled_to_processing', $processing_orders, 'trigger', 10, 1);
        // $this->loader->add_action('woocommerce_order_status_on-hold_to_processing', $processing_orders, 'trigger', 10, 1);
        $this->loader->add_action('woocommerce_order_status_processing', $processing_orders, 'trigger', 10, 1);

        $completed_orders = new WC_Bocs_Email_Completed_Renewal_Order();
        // $this->loader->add_action('woocommerce_order_status_pending_to_completed', $completed_orders, 'trigger', 10, 1);
        // $this->loader->add_action('woocommerce_order_status_failed_to_completed', $completed_orders, 'trigger', 10, 1);
        // $this->loader->add_action('woocommerce_order_status_cancelled_to_completed', $completed_orders, 'trigger', 10, 1);
        $this->loader->add_action('woocommerce_order_status_completed', $completed_orders, 'trigger', 10, 1);

        $onhold_orders = new WC_Bocs_Email_On_Hold_Renewal_Order();
        // $this->loader->add_action('woocommerce_order_status_pending_to_on-hold', $onhold_orders, 'trigger', 10, 1);
        // $this->loader->add_action('woocommerce_order_status_failed_to_on-hold', $onhold_orders, 'trigger', 10, 1);
        // $this->loader->add_action('woocommerce_order_status_cancelled_to_on-hold', $onhold_orders, 'trigger', 10, 1);
        $this->loader->add_action('woocommerce_order_status_on-hold', $onhold_orders, 'trigger', 10, 1);

        $renewal_invoice = new WC_Bocs_Email_Customer_Renewal_Invoice();
        $this->loader->add_action('woocommerce_order_status_pending', $renewal_invoice, 'trigger', 10, 1);
        // $this->loader->add_action('woocommerce_order_status_pending_to_failed', $renewal_invoice, 'trigger', 10, 1);
        // $this->loader->add_action('woocommerce_order_status_on-hold_to_failed', $renewal_invoice, 'trigger', 10, 1);
        
    }

    /**
     * Register all hooks related to WooCommerce checkout functionality.
     *
     * Sets up hooks for customizing the checkout process, including:
     * - Cart total modifications
     * - Order review customizations
     * - Registration requirements
     * - Account creation handling
     *
     * @since 0.0.100
     * @access public
     * @return void
     */
    public function define_checkout_page_hooks()
    {
        $bocs_cart = new Bocs_Cart();
        $this->loader->add_action('woocommerce_review_order_before_order_total', $bocs_cart, 'bocs_review_order_before_order_total');
        $this->loader->add_action('woocommerce_cart_totals_before_order_total', $bocs_cart, 'bocs_cart_totals_before_order_total');

        $bocs_account = new Bocs_Account();
        $this->loader->add_filter('woocommerce_checkout_process', $bocs_account, 'require_registration_during_checkout');
        $this->loader->add_action('woocommerce_before_checkout_process', $bocs_account, 'force_registration_during_checkout');
        $this->loader->add_filter('woocommerce_checkout_registration_enabled', $bocs_account, 'maybe_enable_registration');
    }

    public function define_bocs_email_api()
    {
        $email_api = new Bocs_Email_API();
        $this->loader->add_action('rest_api_init', $email_api, 'register_routes');
    }

    /**
     * Register payment gateway API endpoints and handlers.
     *
     * Sets up REST API routes and handlers for payment processing,
     * including integration with payment gateways and transaction
     * management.
     *
     * @since 0.0.100
     * @access private
     * @return void
     */
    private function define_payment_api_hooks()
    {
        $payment_api = new Bocs_Payment_API();
        $this->loader->add_action('rest_api_init', $payment_api, 'register_routes');
    }

    private function define_stripe_hooks()
    {
        if (!class_exists('Bocs_Stripe_Hooks')) {
            error_log('[Bocs][ERROR] Bocs_Stripe_Hooks class not found');
            return;
        }

        $stripe_hooks = new Bocs_Stripe_Hooks();
        $this->loader->add_filter('wc_stripe_payment_metadata', $stripe_hooks, 'force_stripe_save_source', 10, 3);
        $this->loader->add_filter('wc_stripe_payment_intent_params', $stripe_hooks, 'modify_payment_intent_params', 10, 2);
        $this->loader->add_filter('wc_stripe_force_save_source', $stripe_hooks, 'should_save_source', 10, 1);
        $this->loader->add_action('woocommerce_checkout_before_customer_processing', $stripe_hooks, 'ensure_stripe_customer');
        $this->loader->add_action('woocommerce_payment_complete', $stripe_hooks, 'attach_payment_method_to_customer', 10, 1);
        $this->loader->add_action('woocommerce_checkout_update_order_meta', $stripe_hooks, 'save_stripe_customer_to_order', 10, 2);
    }

    /**
     * Register the order hooks
     */
    private function define_order_hooks()
    {
        
    }

    /**
     * Activates the plugin and migrates API credentials from user meta to plugin settings
     * 
     * This method handles the initial plugin activation process, specifically focusing on
     * migrating Bocs API credentials from a service account's user meta to the plugin's
     * centralized settings. This ensures proper credential management and backwards
     * compatibility with older plugin versions.
     *
     * The following credentials are processed:
     * - Store ID: Unique identifier for the Bocs store
     * - Organization: Organization identifier in Bocs system
     * - Authorization: API authorization token
     * - WooCommerce Key: WooCommerce API consumer key
     * - WooCommerce Secret: WooCommerce API consumer secret
     *
     * @since 0.0.100
     * @access public
     * @static
     * 
     * @return void
     */
    public static function activate() 
    {
        // Look for the Bocs service account using its designated email
        // This account is created during initial Bocs setup and stores temporary credentials
        $serviceAccount = get_user_by('email', 'api@bocs.io');
        if (!$serviceAccount) {
            return; // Exit if service account doesn't exist - nothing to migrate
        }

        // Define the mapping between user meta fields and their corresponding plugin setting keys
        // This mapping ensures consistent credential migration and storage
        $credentialMappings = [
            'bocs_store' => 'store',             // Store identifier
            'bocs_organization' => 'organization', // Organization identifier
            'bocs_authorization' => 'authorization', // Auth token
            'bocs_wookey' => 'woocommerce_key',     // WooCommerce API key
            'bocs_woosecret' => 'woocommerce_secret' // WooCommerce API secret
        ];

        // Retrieve existing plugin settings or initialize if none exist
        // The bocs_plugin_options stores all plugin-related settings
        $pluginSettings = get_option('bocs_plugin_options', []);
        
        // Initialize or ensure headers configuration exists
        // Headers are used for API communication with Bocs services
        $pluginSettings['bocs_headers'] = $pluginSettings['bocs_headers'] ?? [];
        
        // Track whether any credentials were actually migrated
        // This prevents unnecessary database updates
        $hasCredentialChanges = false;

        // Iterate through each credential mapping and process migrations
        foreach ($credentialMappings as $metaKeyName => $settingKeyName) {
            // Retrieve the credential value from user meta
            $credentialValue = get_user_meta($serviceAccount->ID, $metaKeyName, true);
            
            // Only process credentials that have actual values
            // This prevents storing empty or invalid credentials
            if (!empty(trim($credentialValue))) {
                // Store the credential in plugin settings under appropriate key
                $pluginSettings['bocs_headers'][$settingKeyName] = trim($credentialValue);
                $hasCredentialChanges = true;
                
                // Clean up by removing the credential from user meta
                // This prevents duplicate processing in future activations
                delete_user_meta($serviceAccount->ID, $metaKeyName);
            }
        }

        // Only update plugin settings if actual changes were made
        // This prevents unnecessary database writes
        if ($hasCredentialChanges) {
            update_option('bocs_plugin_options', $pluginSettings);
        }
    }

    /**
     * Auto-adds API keys and credentials from user meta to plugin options
     * 
     * This method handles the automatic migration of Bocs API credentials from user meta 
     * to plugin options. It specifically looks for a user with email 'api@bocs.io' and
     * transfers their stored credentials to the plugin's settings.
     * 
     * The following credentials are processed:
     * - Store ID
     * - Organization
     * - Authorization
     * - WooCommerce API Key
     * - WooCommerce API Secret
     * 
     * After successful transfer, the original user meta entries are deleted to prevent
     * duplicate processing and maintain data cleanliness.
     * 
     * @since 0.0.100
     * @access public
     * @return void
     */
    public function auto_add_bocs_keys() 
    {
        // Check for the Bocs API service account
        $serviceAccount = get_user_by('email', 'api@bocs.io');
        if (!$serviceAccount) {
            return;
        }

        // Map user meta keys to their corresponding plugin settings keys
        $credentialMappings = [
            'bocs_store' => 'store',             
            'bocs_organization' => 'organization', 
            'bocs_authorization' => 'authorization', 
            'bocs_wookey' => 'woocommerce_key',     
            'bocs_woosecret' => 'woocommerce_secret' 
        ];

        // Get existing plugin settings
        $pluginSettings = get_option('bocs_plugin_options', []);
        
        // Initialize headers configuration
        $pluginSettings['bocs_headers'] = $pluginSettings['bocs_headers'] ?? [];
        
        $hasUpdates = false;

        // Transfer credentials from user meta to plugin settings
        foreach ($credentialMappings as $metaKey => $settingKey) {
            $credentialValue = get_user_meta($serviceAccount->ID, $metaKey, true);
            
            if (!empty(trim($credentialValue))) {
                // Store credential in plugin settings
                $pluginSettings['bocs_headers'][$settingKey] = trim($credentialValue);
                
                // Clean up user meta
                delete_user_meta($serviceAccount->ID, $metaKey);
                
                $hasUpdates = true;
            }
        }

        // Save updates if any credentials were transferred
        if ($hasUpdates) {
            update_option('bocs_plugin_options', $pluginSettings);
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
     * Execute the plugin by running the hook loader.
     *
     * This method initiates the execution of all registered WordPress
     * hooks and filters, effectively starting the plugin's operation.
     *
     * @since 0.0.100
     * @access public
     * @return void
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
