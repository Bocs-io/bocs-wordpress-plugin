<?php

/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    Bocs
 * @subpackage Bocs/includes
 */
class Bocs
{
    /** @var Loader */
    protected $loader;

    /** @var string */
    protected $plugin_name;

    /** @var string */
    protected $version;

    /**
     * Initialize the plugin.
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
        $this->define_account_profile_hooks();
        $this->define_sync_hooks();
        $this->define_bocs_email_api();
        $this->define_product_hooks();
        $this->define_order_hooks();
    }

    /**
     * Load dependencies and initialize loader.
     */
    private function load_dependencies()
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Loader.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/constants.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Admin.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_List_Table.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Updater.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Sync.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Curl.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_API_Handler.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Error_Logs_List_Table.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Log_Handler.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Shortcode.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Api.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Cart.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Account.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Email_API.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Order_Hooks.php';

        // Check if WooCommerce is active and email classes exist
        if (function_exists('WC')) {
            // Load core WooCommerce email classes
            if (!class_exists('WC_Email', false)) {
                include_once WC_ABSPATH . 'includes/emails/class-wc-email.php';
            }
            
            // Ensure WooCommerce parent email classes are loaded
            $wc_email_classes = array(
                'WC_Email' => 'emails/class-wc-email.php', // Base class first
                'WC_Email_Customer_Completed_Order' => 'emails/class-wc-email-customer-completed-order.php',
                'WC_Email_Customer_Processing_Order' => 'emails/class-wc-email-customer-processing-order.php',
                'WC_Email_Customer_On_Hold_Order' => 'emails/class-wc-email-customer-on-hold-order.php',
                'WC_Email_Customer_Invoice' => 'emails/class-wc-email-customer-invoice.php',
                'WC_Email_Failed_Order' => 'emails/class-wc-email-failed-order.php',
                'WC_Email_Customer_Note' => 'emails/class-wc-email-customer-note.php',
                'WC_Email_Customer_Reset_Password' => 'emails/class-wc-email-customer-reset-password.php',
                'WC_Email_Customer_New_Account' => 'emails/class-wc-email-customer-new-account.php'
            );
            
            // Try to include all email classes
            foreach ($wc_email_classes as $class => $path) {
                if (!class_exists($class, false)) {
                    $full_path = WC_ABSPATH . 'includes/' . $path;
                    if (file_exists($full_path)) {
                        include_once $full_path;
                    } else {
                        error_log("Bocs: Unable to find WooCommerce email class file: {$path}");
                    }
                }
            }
            
            // Now load our custom email classes
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Email.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/emails/class-bocs-email-processing-order.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/emails/class-bocs-email-processing-renewal-order.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/emails/class-bocs-email-completed-renewal-order.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/emails/class-bocs-email-on-hold-renewal-order.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/emails/class-bocs-email-customer-renewal-invoice.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/emails/class-bocs-email-subscription-switched.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/emails/class-bocs-email-welcome.php';
            // New email classes
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/emails/class-bocs-email-failed-renewal-payment.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/emails/class-bocs-email-upcoming-renewal-reminder.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/emails/class-bocs-email-subscription-cancelled.php';
        }

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Bocs.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Product.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Payment_Method.php';

        $this->loader = new Loader();
    }

    /**
     * Define sync hooks for Bocs App synchronization.
     */
    private function define_sync_hooks()
    {
        $syncing = new Sync();
        
        // Critical user sync hooks
        $this->loader->add_action('profile_update', $syncing, 'profile_update', 10, 3);
        $this->loader->add_action('woocommerce_save_account_details', $syncing, 'save_account_details');
        $this->loader->add_action('user_register', $syncing, 'bocs_user_register');
    }

    /**
     * Define hooks for My Account/Profile functionality.
     */
    private function define_account_profile_hooks()
    {
        $bocs_account = new Bocs_Account();

        $this->loader->add_filter('woocommerce_account_menu_items', $bocs_account, 'bocs_account_menu_item');
        $this->loader->add_action('init', $bocs_account, 'register_bocs_account_endpoint');
        $this->loader->add_action('woocommerce_account_bocs-subscriptions_endpoint', $bocs_account, 'bocs_endpoint_content');
        $this->loader->add_action('init', $bocs_account, 'register_bocs_view_subscription_endpoint');
        $this->loader->add_action('woocommerce_account_bocs-view-subscription_endpoint', $bocs_account, 'bocs_view_subscription_endpoint_content');

        $bocs_payment_method = new Bocs_Payment_Method();
        $this->loader->add_filter('woocommerce_payment_methods_list_item', $bocs_payment_method, 'add_edit_payment_method_button', 10, 2);
        
        // Add new AJAX action for Stripe setup
        $this->loader->add_action('wp_ajax_bocs_get_stripe_setup', $bocs_payment_method, 'get_stripe_setup');
        $this->loader->add_action('wp_ajax_bocs_update_payment_method', $bocs_payment_method, 'handle_payment_method_update');

        // Add action to handle setup completion
        $this->loader->add_action('init', $bocs_payment_method, 'handle_setup_completion');
        // Add action to display notices
        $this->loader->add_action('woocommerce_account_bocs-subscriptions_endpoint', $bocs_payment_method, 'display_payment_update_notices');
        // Add AJAX action for updating subscription payment method
        $this->loader->add_action('wp_ajax_bocs_update_subscription_payment', $bocs_payment_method, 'update_subscription_payment');
        // Add scripts and styles
        $this->loader->add_action('wp_enqueue_scripts', $bocs_payment_method, 'enqueue_scripts');
    }

    /**
     * Define hooks for plugin updates.
     */
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
        // Initialize email classes
        $bocs_email = new Bocs_Email();
        
        // Add basic initialization
        $this->loader->add_action('init', $bocs_email, 'init', 10);
        
        // Initialize email classes after WooCommerce is loaded
        $this->loader->add_action('woocommerce_init', $bocs_email, 'init_email_classes', 10);
    }

    /**
     * Initialize email classes after WooCommerce is loaded
     */
    public function init_email_classes() {
        // Prevent duplicate email registrations
        static $emails_initialized = false;
        if ($emails_initialized) {
            error_log("Bocs: Prevented duplicate email hook registration in Bocs.php");
            return;
        }
        $emails_initialized = true;
        
        // Regular Processing Order Email
        $processing_regular_orders = new WC_Bocs_Email_Processing_Order();
        $this->loader->add_action('woocommerce_order_status_processing', $processing_regular_orders, 'trigger', 9, 1);
        $this->loader->add_action('woocommerce_order_status_pending_to_processing', $processing_regular_orders, 'trigger', 9, 1);
        $this->loader->add_action('woocommerce_order_status_failed_to_processing', $processing_regular_orders, 'trigger', 9, 1);
        
        // Processing Renewal Order Email
        $processing_orders = new WC_Bocs_Email_Processing_Renewal_Order();
        $this->loader->add_action('woocommerce_order_status_processing', $processing_orders, 'trigger', 10, 1);
        $this->loader->add_action('woocommerce_order_status_pending_to_processing', $processing_orders, 'trigger', 10, 1);
        $this->loader->add_action('woocommerce_order_status_failed_to_processing', $processing_orders, 'trigger', 10, 1);

        // Completed Renewal Order Email
        $completed_orders = new WC_Bocs_Email_Completed_Renewal_Order();
        $this->loader->add_action('woocommerce_order_status_completed', $completed_orders, 'trigger', 10, 1);
        $this->loader->add_action('woocommerce_order_status_processing_to_completed', $completed_orders, 'trigger', 10, 1);

        // On-hold Renewal Order Email
        $onhold_orders = new WC_Bocs_Email_On_Hold_Renewal_Order();
        $this->loader->add_action('woocommerce_order_status_on-hold', $onhold_orders, 'trigger', 10, 1);
        $this->loader->add_action('woocommerce_order_status_pending_to_on-hold', $onhold_orders, 'trigger', 10, 1);
        $this->loader->add_action('woocommerce_order_status_failed_to_on-hold', $onhold_orders, 'trigger', 10, 1);

        // Customer Renewal Invoice Email
        $renewal_invoice = new WC_Bocs_Email_Customer_Renewal_Invoice();
        $this->loader->add_action('woocommerce_order_status_pending', $renewal_invoice, 'trigger', 10, 1);
        $this->loader->add_action('woocommerce_order_status_failed', $renewal_invoice, 'trigger', 10, 1);

        // Subscription Switched Email
        $subscription_switched = new WC_Bocs_Email_Subscription_Switched();
        $this->loader->add_action('bocs_subscription_switched', $subscription_switched, 'trigger', 10, 2);
    }

    public function define_checkout_page_hooks()
    {
        $bocs_cart = new Bocs_Cart();
        $this->loader->add_action('woocommerce_review_order_before_order_total', $bocs_cart, 'bocs_review_order_before_order_total');
        $this->loader->add_action('woocommerce_cart_totals_before_order_total', $bocs_cart, 'bocs_cart_totals_before_order_total');
    }

    public function define_bocs_email_api()
    {
        $email_api = new Bocs_Email_API();
        $this->loader->add_action('rest_api_init', $email_api, 'register_routes');
    }

    /**
     * Define hooks for WooCommerce product functionality
     */
    private function define_product_hooks()
    {
        $product = new Bocs_Product();
        
        // Register AJAX actions for product price and details
        $this->loader->add_action('wp_ajax_get_product_price', $product, 'get_product_price_callback');
        $this->loader->add_action('wp_ajax_nopriv_get_product_price', $product, 'get_product_price_callback');
        
        $this->loader->add_action('wp_ajax_get_product_details', $product, 'get_product_details_ajax');
        $this->loader->add_action('wp_ajax_nopriv_get_product_details', $product, 'get_product_details_ajax');
    }

    /**
     * Define order-related hooks
     */
    private function define_order_hooks()
    {
        $order_hooks = new Bocs_Order_Hooks();
        // The hooks are registered in the class constructor
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
     * @since 1.0.0
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
     * @since 1.0.0
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