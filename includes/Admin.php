<?php

// use function Loader\add_filter;

/**
 * Admin Class
 * 
 * Handles all WordPress admin functionality for the Bocs plugin including:
 * - Product management
 * - Order processing
 * - Widget integration
 * - Settings pages
 * - User management
 * - API communication
 */
class Admin
{

    /** @var array API headers for Bocs authentication */
    private $headers;

    /** @var string Nonce for widget saving operations */
    private $save_widget_nonce = '';
    
    /** @var Bocs_Log_Handler Logger instance */
    private $logger;

    public function __construct()
    {
        $this->plugin_name = 'woocommerce-bocs';
        $this->version = '1.0.0';
        $this->load_dependencies();
        
        // Initialize logger
        $this->logger = new Bocs_Log_Handler();
        
        // Add this line to register the query var
        add_filter('query_vars', [$this, 'add_bocs_query_vars']);
        
        $this->save_widget_nonce = wp_create_nonce('save-widget-nonce');

        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();

        // Set up API authentication headers if credentials exist
        if (! empty($options['bocs_headers']['organization']) && 
            ! empty($options['bocs_headers']['store']) && 
            ! empty($options['bocs_headers']['authorization'])) {
            $this->headers = [
                'Organization' => $options['bocs_headers']['organization'],
                'Store' => $options['bocs_headers']['store'],
                'Authorization' => $options['bocs_headers']['authorization'],
                'Content-Type' => 'application/json'
            ];
        }
        
        // Add action to track cart item meta passed to order items
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_cart_item_meta_to_order_items'), 10, 4);
        
        // Add action to trigger welcome email after checkout
        add_action('woocommerce_checkout_order_processed', array($this, 'trigger_welcome_email_after_checkout'), 99, 3);
        
        // Also hook into thank you page just to be sure
        add_action('woocommerce_thankyou', array($this, 'trigger_welcome_email_on_thankyou'), 10, 1);
    }

    /**
     * Load dependencies required for admin functionality
     *
     * @return void
     */
    private function load_dependencies()
    {
        /**
         * The class responsible for defining all actions related to stock management
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Stock.php';
        
        // Set up AJAX handlers
        add_action('wp_ajax_get_product_stock', array($this, 'get_product_stock_ajax'));
        add_action('wp_ajax_nopriv_get_product_stock', array($this, 'get_product_stock_ajax'));
    }
    
    /**
     * AJAX handler to retrieve product stock quantity directly from metadata
     * 
     * @return void
     */
    public function get_product_stock_ajax() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ajax-nonce')) {
            wp_send_json_error('Invalid security token');
            return;
        }
        
        // Check if product ID is provided
        if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
            wp_send_json_error('Product ID is required');
            return;
        }
        
        $product_id = intval($_POST['product_id']);
        
        // Get the product
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error('Product not found');
            return;
        }
        
        // Get stock quantity from product meta
        if ($product->managing_stock()) {
            $stock_quantity = $product->get_stock_quantity();
            wp_send_json_success($stock_quantity);
        } else {
            // If stock is not managed, return null
            wp_send_json_success(null);
        }
    }

    /**
     * Test the Bocs Welcome Email functionality
     */
    public function test_welcome_email() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }
        
        // Find the most recent order
        $orders = wc_get_orders(array(
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        
        if (empty($orders)) {
            wp_die('No orders found to test with');
        }
        
        $order = $orders[0];
        $order_id = $order->get_id();
        
        // Force load the welcome email class
        if (!class_exists('WC_Bocs_Email_Welcome')) {
            require_once BOCS_PLUGIN_DIR . 'includes/emails/class-bocs-email-welcome.php';
        }
        
        // Create an instance and trigger the email
        $welcome_email = new WC_Bocs_Email_Welcome();
        
        // Force-enable the email
        $welcome_email->enabled = 'yes';
        
        // Send the email
        $welcome_email->trigger($order_id);
        
        // Create output
        echo '<div style="max-width: 800px; margin: 20px auto; padding: 20px; background: #fff; border: 1px solid #ccc;">';
        echo '<h1>Bocs Welcome Email Test</h1>';
        echo '<p>Attempted to send welcome email for order #' . esc_html($order_id) . '</p>';
        echo '<p>Sent to: ' . esc_html($order->get_billing_email()) . '</p>';
        echo '<p>Please check your email inbox. If you don\'t receive the email, please check:</p>';
        echo '<ul>';
        echo '<li>Your spam folder</li>';
        echo '<li>WordPress email configuration</li>';
        echo '<li>Server email sending capability</li>';
        echo '</ul>';
        echo '<p><a href="' . esc_url(admin_url()) . '">Return to Dashboard</a></p>';
        echo '</div>';
        
        exit;
    }

    /**
     * Register custom log handler for Bocs operations
     *
     * @param array $handlers Existing log handlers
     * @return array Modified array of handlers including Bocs handler
     */
    public function bocs_register_log_handlers($handlers)
    {
        array_push($handlers, new Bocs_Log_Handler());
        return $handlers;
    }

    /**
     * Process and save Bocs-specific product meta data
     *
     * @param int $post_id Product post ID
     */
    public function bocs_process_product_meta($post_id)
    {
        // Save subscription interval settings
        if (isset($_POST['bocs_product_interval'])) {
            update_post_meta($post_id, 'bocs_product_interval', esc_attr(trim($_POST['bocs_product_interval'])));
        }

        if (isset($_POST['bocs_product_interval_count'])) {
            update_post_meta($post_id, 'bocs_product_interval_count', esc_attr(trim($_POST['bocs_product_interval_count'])));
        }

        if (isset($_POST['bocs_product_discount_type'])) {
            update_post_meta($post_id, 'bocs_product_discount_type', esc_attr(trim($_POST['bocs_product_discount_type'])));
        }

        if (isset($_POST['bocs_product_discount'])) {
            update_post_meta($post_id, 'bocs_product_discount', esc_attr(trim($_POST['bocs_product_discount'])));
        }
    }

    public function bocs_admin_custom_js()
    {
        require_once dirname(__FILE__) . '/../views/bocs_admin_custom.php';
    }

    public function bocs_product_panel()
    {
        require_once dirname(__FILE__) . '/../views/bocs_product_panel.php';
    }

    public function bocs_product_tab($tabs)
    {
        $tabs['bocs'] = array(
            'label' => __('Bocs Product', 'bocs_product'),
            'target' => 'bocs_product_options',
            'class' => 'show_if_bocs'
        );

        $tabs['general']['class'][] = 'show_if_bocs';
        $tabs['inventory']['class'][] = 'show_if_bocs';

        return $tabs;
    }

    public function register_bocs_product_type()
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/WC_Bocs_Product_Type.php';

        class_exists('WC_Bocs_Product_Type');

        add_filter('product_type_selector', [
            $this,
            'add_custom_product_type'
        ]);
    }

    public function add_custom_product_type($types)
    {
        $types['bocs'] = __('Bocs Product', 'woocommerce-bocs'); // The label for your custom product type.
        return $types;
    }

    /**
     * Register and enqueue scripts and styles for the Bocs Widget functionality
     * 
     * This method handles the initialization and setup of all required assets for the Bocs Widget,
     * including:
     * 1. API key validation and initialization
     * 2. Loading required stylesheets (Font Awesome and custom styles)
     * 3. Loading JavaScript dependencies and custom scripts
     * 4. Setting up widget configuration parameters
     * 5. Passing data from PHP to JavaScript
     * 
     * @since 1.0.0
     * @return void
     */
    public function bocs_widget_script_register()
    {
        // Initialize Bocs instance and ensure API keys are set up
        $bocs = new Bocs();
        $bocs->auto_add_bocs_keys();

        // Retrieve plugin settings and ensure headers array exists
        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();

        // Enqueue required stylesheets
        wp_enqueue_style(
            'font-awesome', 
            plugin_dir_url(__FILE__) . '../assets/css/font-awesome.min.css', 
            null, 
            '0.0.1'
        );
        wp_enqueue_style(
            "bocs-custom-block-css", 
            plugin_dir_url(__FILE__) . '../assets/css/bocs-widget.css', 
            null, 
            '0.0.15'
        );

        // Ensure jQuery is loaded as it's required for our widget
        wp_enqueue_script('jquery');

        // Register the main widget script with all required WordPress dependencies
        wp_register_script(
            "bocs-custom-block", 
            plugin_dir_url(__FILE__) . '../assets/js/bocs-widget.js', 
            array(
                'wp-components',    // WordPress UI components
                'wp-block-editor', // Block editor functionality
                'wp-blocks',       // Block registration API
                'wp-i18n',        // Internationalization
                'wp-editor',      // WordPress editor functionality
                'wp-data',        // Data management
                'jquery'          // jQuery library
            ), 
            '2025.03.13.1'         // Version number for cache busting
        );  // Closing parenthesis on its own line

        // Get current post context and any previously selected widget data
        $post_id = get_the_ID();
        $selected_widget_id = get_post_meta($post_id, 'selected_bocs_widget_id', true);
        $selected_widget_name = get_post_meta($post_id, 'selected_bocs_widget_name', true);

        // Retrieve stored collections and widgets data
        $bocs_collections = get_option("bocs_collections");
        $bocs_widgets = get_option("bocs_widgets");

        // Prepare parameters for JavaScript initialization
        $params = array(
            // API endpoints
            'dataURL' => NEXT_PUBLIC_API_EXTERNAL_URL,
            'bocsURL' => BOCS_API_URL . "bocs",
            'widgetsURL' => BOCS_LIST_WIDGETS_URL . '?query=widgetType:bocs',
            'collectionsURL' => BOCS_LIST_WIDGETS_URL . '?query=widgetType:collection',
            
            // Authentication headers
            'Organization' => $options['bocs_headers']['organization'] ?? '',
            'Store' => $options['bocs_headers']['store'] ?? '',
            'Authorization' => $options['bocs_headers']['authorization'] ?? '',
            
            // WordPress integration data
            'nonce' => $this->save_widget_nonce,        // Security nonce
            'ajax_url' => admin_url('admin-ajax.php'),  // WordPress AJAX endpoint
            
            // Widget state data
            'selected_id' => $selected_widget_id,       // Currently selected widget
            'selected_name' => $selected_widget_name,   // Name of selected widget
            'bocs_collections' => $bocs_collections,    // Available collections
            'bocs_widgets' => $bocs_widgets            // Available widgets
        );

        // Enqueue the script and localize the data
        wp_enqueue_script("bocs-custom-block");
        wp_localize_script('bocs-custom-block', 'bocs_widget_object', $params);
    }

    /**
     * Enqueue admin-specific scripts and styles
     * 
     * This method handles the loading of assets specifically for the WordPress admin area.
     * It includes:
     * 1. Admin-specific CSS styles
     * 2. Admin JavaScript functionality
     * 3. Configuration data for AJAX operations
     * 
     * The method ensures that all necessary resources are available for the admin
     * interface to function properly, including API authentication details and
     * endpoint URLs.
     * 
     * @since 1.0.0
     * @return void
     */
    public function admin_enqueue_scripts()
    {
        // Enqueue admin-specific CSS
        wp_enqueue_style(
            "bocs-admin-css", 
            plugin_dir_url(__FILE__) . '../assets/css/admin.css', 
            null, 
            '0.0.2'
        );

        // Retrieve plugin settings and ensure headers exist
        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();

        // Register and enqueue admin JavaScript
        wp_register_script(
            "bocs-admin-js", 
            plugin_dir_url(__FILE__) . '../assets/js/admin.js', 
            array('jquery'), 
            '20241126.4'
        );
        wp_enqueue_script("bocs-admin-js");

        // Pass configuration data to JavaScript
        wp_localize_script('bocs-admin-js', 'bocsAjaxObject', array(
            'widgetsURL' => BOCS_LIST_WIDGETS_URL,
            'Organization' => $options['bocs_headers']['organization'] ?? '',
            'Store' => $options['bocs_headers']['store'] ?? '',
            'Authorization' => $options['bocs_headers']['authorization'] ?? ''
        ));
    }

    /**
     * Enqueue the scripts for the frontend
     *
     * @return void
     */
    public function enqueue_scripts()
    {

        $bocs_conversion_total = 0;

        // this to make sure that the keys were added or updated
        $bocs = new Bocs();
        $bocs->auto_add_bocs_keys();

        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();

        // Get the appropriate widget URL based on developer mode
        $widget_url = BOCS_ENVIRONMENT === 'dev' 
            ? "https://dev.widget.v2.bocs.io/script/index.js"
            : "https://widget.v2.bocs.io/script/index.js";

        // Set version based on environment
        $script_version = BOCS_ENVIRONMENT === 'dev' 
            ? time()  // Use timestamp for dev environment
            : BOCS_VERSION;   // Use plugin version for production
            
        wp_enqueue_script(
            "bocs-widget-script", 
            $widget_url, 
            array(), 
            $script_version, 
            true
        );

        if (class_exists('woocommerce')) {
            wp_enqueue_script('wc-add-to-cart');
            wp_enqueue_script('wc-cart-fragments');
        }

        $redirect = wc_get_checkout_url();
        $cart_nonce = wp_create_nonce('wc_store_api');

        wp_enqueue_script(
            "bocs-add-to-cart",
            plugin_dir_url(__FILE__) . '../assets/js/add-to-cart.js',
            array(
                'jquery',
                'bocs-widget-script'
            ),
            '2025.03.18.2',  // Updated version number to March 17
            true
        );

        wp_localize_script('bocs-add-to-cart', 'bocsAjaxObject', array(
            'productUrl' => BOCS_API_URL . 'products/',
            'cartNonce' => $cart_nonce,
            'cartURL' => $redirect,
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ajax-nonce'),
            'search_nonce' => wp_create_nonce('ajax-search-nonce'),
            'bocsGetUrl' => BOCS_API_URL . 'bocs/',
            'storeId' => $options['bocs_headers']['store'] ?? '',
            'orgId' => $options['bocs_headers']['organization'] ?? '',
            'authId' => $options['bocs_headers']['authorization'] ?? '',
            'update_product_nonce' => wp_create_nonce('ajax-update-product-nonce'),
            'couponNonce' => wp_create_nonce('ajax-create-coupon-nonce'),
            'isLoggedIn' => is_user_logged_in() ? '1' : '0',
            'loginURL' => wp_login_url()
        ));

        // Get the subscription ID from the URL - now safely using get_query_var()
        $bocs_subscription_id = get_query_var('bocs-view-subscription', '');
        
        if (!empty($bocs_subscription_id)) {
            wp_enqueue_script('view-subscription-js', plugin_dir_url(__FILE__) . '../assets/js/view-subscription.js', array(
                'jquery'
            ), '2024.11.15.0', true);
            wp_localize_script('view-subscription-js', 'viewSubscriptionObject', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ajax-nonce'),
                'updateSubscriptionUrl' => BOCS_API_URL . 'subscriptions/' . $bocs_subscription_id,
                'storeId' => $options['bocs_headers']['store'] ?? '',
                'orgId' => $options['bocs_headers']['organization'] ?? '',
                'authId' => $options['bocs_headers']['authorization'] ?? ''
            ));
        }

        // get the current bocs subscription id
        $bocs_id = ! empty($bocs_id) ? $bocs_id : '';

        if (empty($bocs_id) && isset(WC()->session)) {
            $bocs_id = WC()->session->get('bocs');

            if (empty($bocs_id)) {
                if (isset($_COOKIE['__bocs_id'])) {
                    $bocs_id = sanitize_text_field($_COOKIE['__bocs_id']);
                }
            }
        }

        $frequency_id = ! empty($frequency_id) ? $frequency_id : '';

        if (empty($frequency_id) && isset($_COOKIE['__bocs_frequency_id'])) {
            $frequency_id = sanitize_text_field($_COOKIE['__bocs_frequency_id']);
            if (empty($frequency_id) && isset(WC()->session)) {
                $frequency_id = WC()->session->get('bocs_frequency');
            }
        }

        $current_frequency = null;
        $bocs_body = $this->get_bocs_data_from_api($bocs_id);

        // Loop through adjustments to find the current frequency
        if (!empty($bocs_body) && isset($bocs_body['priceAdjustment']) && isset($bocs_body['priceAdjustment']['adjustments'])) {
            foreach ($bocs_body['priceAdjustment']['adjustments'] as $adjustment) {
                if (isset($adjustment['id']) && $adjustment['id'] === $frequency_id) {
                    $current_frequency = $adjustment;
                    break;
                }
            }
        }

        // error_log(print_r($current_frequency, true));
        
        if (is_checkout()) {
            // checks the stripe checkbox and make it checked as default
            /*wp_enqueue_script(
                'bocs-stripe-checkout-js',
                plugin_dir_url(__FILE__) . '../assets/js/custom-stripe-checkout.js',
                array('jquery'),
                '20240611.8',
                true
            );*/

            wp_enqueue_script(
                'bocs-checkout-js', 
                plugin_dir_url(__FILE__) . '../assets/js/bocs-checkout.js',
                array('jquery'),
                '20250318.1',
                true
            );

            // Prepare bocs data with error checking
            $bocs_data = null;
            if (is_array($bocs_body) && isset($bocs_body['data'])) {
                $bocs_data = $bocs_body['data'];
            }

            wp_localize_script('bocs-checkout-js', 'bocsCheckoutObject', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ajax-nonce'),
                'storeId' => $options['bocs_headers']['store'] ?? '',
                'orgId' => $options['bocs_headers']['organization'] ?? '',
                'authId' => $options['bocs_headers']['authorization'] ?? '',
                'frequency' => $current_frequency,
                'bocs' => $bocs_data
            ));
        }

        if (is_cart()) {

            // this is for the Cart if there is no Bocs subscription or collections on it
            // and it is using the WooCommerce' Blocks Template
            $product_ids = array();

            // get the current bocs subscription id
            $bocs_id = ! empty($bocs_id) ? $bocs_id : '';

            if (empty($bocs_id) && isset(WC()->session)) {
                $bocs_id = WC()->session->get('bocs');

                if (empty($bocs_id)) {
                    if (isset($_COOKIE['__bocs_id'])) {
                        $bocs_id = sanitize_text_field($_COOKIE['__bocs_id']);
                    }
                }
            }

            $bocs_options = array();

            if (empty($bocs_id)) {

                // Check if WooCommerce is active and the cart is not empty
                if (WC()->cart && ! WC()->cart->is_empty()) {
                    // Loop through the cart items
                    foreach (WC()->cart->get_cart() as $cart_item) {
                        // Get the product ID and add it to the array
                        $product_ids[] = $cart_item['product_id'];
                    }
                }

                // get the list of available bocs
                $bocs_class = new Bocs_Bocs();

                $bocs_list = $bocs_class->get_all_bocs();
                if (! empty($bocs_list) && ! empty($product_ids)) {

                    foreach ($bocs_list as $bocs_item) {
                        $bocs_wp_ids = array();
                        // loop on its products
                        if (! empty($bocs_item['products'])) {
                            foreach ($bocs_item['products'] as $bocs_product) {
                                $wp_id = $bocs_product['externalSourceId'];
                                $bocs_wp_ids[] = $wp_id;
                                $wc_product = wc_get_product($wp_id);
                                $bocs_conversion_total += $wc_product->get_regular_price();
                            }
                        }
                        if (empty(array_diff($product_ids, $bocs_wp_ids))) {
                            // this bocs is can be an option
                            $bocs_options[] = $bocs_item;
                        }
                    }

                    if (empty($bocs_options)) {
                        foreach ($bocs_list as $bocs_item) {
                            $bocs_item['products'] = [];
                            foreach ($product_ids as $product_id) {
                                $wc_product = wc_get_product($product_id);
                                $bocs_item['products'][] = array(
                                    "description" => $wc_product->get_description(),
                                    "externalSource" => "WP",
                                    "externalSourceId" => (string)$product_id,
                                    "id" => "", // get the bocs id
                                    "name" => $wc_product->get_name(), // get the woocoomerce product name
                                    "price" => $wc_product->get_regular_price(),
                                    "quantity" => 1,
                                    "regularPrice" => $wc_product->get_regular_price(),
                                    "salePrice" => $wc_product->get_price(),
                                    "sku" => $wc_product->get_sku(),
                                    "stockQuantity" => 0
                                );
                            }
                            $bocs_options[] = $bocs_item;
                        }
                    }
                }
            }

            wp_enqueue_script(
                'bocs-cart-js',
                plugin_dir_url(__FILE__) . '../assets/js/bocs-cart.js',
                array('jquery'),
                '20250106.2',
                true
            );

            wp_localize_script('bocs-cart-js', 'bocsCartObject', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ajax-nonce'),
                'storeId' => $options['bocs_headers']['store'] ?? '',
                'orgId' => $options['bocs_headers']['organization'] ?? '',
                'authId' => $options['bocs_headers']['authorization'] ?? '',
                'frequency' => $current_frequency,
                'bocs' => $bocs_body['data'],
                'bocsConversion' => $bocs_options,
                'bocsConversionTotal' => $bocs_conversion_total
            ));
        }
    }

    public function bocs_homepage()
    {
        echo "";
        // require_once dirname(__FILE__) . '/../views/bocs_homepage.php';
    }

    public function bocs_tags()
    {
        require_once dirname(__FILE__) . '/../views/bocs_tag.php';
    }

    public function bocs_categories()
    {
        require_once dirname(__FILE__) . '/../views/bocs_category.php';
    }

    private function _bocs_post_contact()
    {
        $options = get_option('bocs_plugin_options');

        if (isset($_POST)) {
            if (isset($_POST["bocs_plugin_options"]['sync_contacts_to_bocs'])) {
                $options['sync_contacts_to_bocs'] = $_POST["bocs_plugin_options"]["sync_contacts_to_bocs"];
            }
            if (isset($_POST["bocs_plugin_options"]['sync_contacts_from_bocs'])) {
                $options['sync_contacts_from_bocs'] = $_POST["bocs_plugin_options"]["sync_contacts_from_bocs"];
            }
            if (isset($_POST["bocs_plugin_options"]["sync_daily_contacts_to_bocs"])) {
                $options['sync_daily_contacts_to_bocs'] = $_POST["bocs_plugin_options"]["sync_daily_contacts_to_bocs"];
            }
            if (isset($_POST["bocs_plugin_options"]["sync_daily_contacts_from_bocs"])) {
                $options['sync_daily_contacts_from_bocs'] = $_POST["bocs_plugin_options"]["sync_daily_contacts_from_bocs"];
            }
        }

        update_option('bocs_plugin_options', $options);
    }

    public function bocs_contact_page()
    {
        $this->_bocs_post_contact();
        require_once dirname(__FILE__) . '/../views/bocs_setting.php';
    }

    private function _bocs_post_headers_settings()
    {
        $options = get_option('bocs_plugin_options');

        if (isset($_POST)) {
            if (isset($_POST["bocs_plugin_options"]['bocs_headers'])) {
                $options['bocs_headers']['store'] = $_POST["bocs_plugin_options"]["bocs_headers"]['store'];
                $options['bocs_headers']['organization'] = $_POST["bocs_plugin_options"]["bocs_headers"]['organization'];
                $options['bocs_headers']['authorization'] = $_POST["bocs_plugin_options"]["bocs_headers"]['authorization'];
            }

            // Add Stripe settings handling
            if (isset($_POST["bocs_plugin_options"]['stripe'])) {
                $test_mode = isset($_POST["bocs_plugin_options"]["stripe"]['test_mode']) ? 'yes' : 'no';
                
                // Store both live and test keys
                $options['stripe']['live_publishable_key'] = sanitize_text_field($_POST["bocs_plugin_options"]["stripe"]['live_publishable_key'] ?? '');
                $options['stripe']['live_secret_key'] = sanitize_text_field($_POST["bocs_plugin_options"]["stripe"]['live_secret_key'] ?? '');
                $options['stripe']['test_publishable_key'] = sanitize_text_field($_POST["bocs_plugin_options"]["stripe"]['test_publishable_key'] ?? '');
                $options['stripe']['test_secret_key'] = sanitize_text_field($_POST["bocs_plugin_options"]["stripe"]['test_secret_key'] ?? '');
                $options['stripe']['test_mode'] = $test_mode;
                
                // Set current keys based on mode
                $options['stripe']['publishable_key'] = $test_mode === 'yes' 
                    ? $options['stripe']['test_publishable_key']
                    : $options['stripe']['live_publishable_key'];
                    
                $options['stripe']['secret_key'] = $test_mode === 'yes'
                    ? $options['stripe']['test_secret_key']
                    : $options['stripe']['live_secret_key'];
            }

            if (isset($_POST["option_page"]) && $_POST["option_page"] === 'developer_mode' && isset($_POST["action"]) && $_POST["action"] === 'update') {
                $options['developer_mode'] = 'off';
                if (isset($_POST["developer_mode"])) {
                    $options['developer_mode'] = $_POST["developer_mode"] == 'on' ? 'on' : 'off';
                }    
            }
        }

        update_option('bocs_plugin_options', $options);
    }

    private function _bocs_post_sync_options()
    {
        // @TODO
    }

    /**
     * The page for the list of errors
     *
     * @return void
     */
    public function bocs_error_logs_page()
    {
        require_once dirname(__FILE__) . '/../views/bocs_error_logs.php';
    }

    public function bocs_sync_store_page()
    {
        $this->_bocs_post_sync_options();
        require_once dirname(__FILE__) . '/../views/bocs_sync_store.php';
    }

    public function bocs_settings_page()
    {
        $this->_bocs_post_headers_settings();
        
        // Get current options
        $options = get_option('bocs_plugin_options');
        $stripe_settings = isset($options['stripe']) ? $options['stripe'] : array(
            'publishable_key' => '',
            'secret_key' => '',
            'test_mode' => 'no'
        );
        
        // Include the settings view
        require_once dirname(__FILE__) . '/../views/bocs_settings.php';
    }

    /**
     *
     * @return void
     */
    public function bocs_add_settings_page()
    {
        // add_options_page( 'Bocs', 'Bocs', 'manage_options', 'bocs-plugin', [$this, 'bocs_render_plugin_settings_page'] );
        add_menu_page("Bocs", "Bocs", 'manage_options', 'bocs', [
            $this,
            'bocs_homepage'
        ], 'data:image/svg+xml;base64,' . base64_encode('<svg width="20" height="20" viewBox="0 0 36 36" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" xmlnsSerif="http://www.serif.com/" aria-hidden="true" focusable="false" style="fill-rule: evenodd; clip-rule: evenodd; stroke-linejoin: round; stroke-miterlimit: 2;"><g transform="matrix(1,0,0,1,-647.753,-303.839)"><g transform="matrix(1,0,0,1,-8.46249,-21.314)"><path d="M686.684,328.173C686.258,326.125 684.248,324.807 682.199,325.234L659.235,330.012C657.186,330.439 655.869,332.448 656.295,334.497L661.074,357.461C661.5,359.51 663.51,360.827 665.558,360.401L688.523,355.622C690.572,355.196 691.889,353.187 691.463,351.138L686.684,328.173ZM673.879,334.202C678.21,334.202 681.726,338.062 681.726,342.817C681.726,347.572 678.21,351.432 673.879,351.432C669.548,351.432 666.032,347.572 666.032,342.817C666.032,338.062 669.548,334.202 673.879,334.202Z" style="fill: rgb(0, 132, 139);"></path></g></g></svg>'), 2);
        add_submenu_page("bocs", "Subscriptions", "Subscriptions", "manage_options", 'bocs-subscriptions', [
            $this,
            'bocs_list_subscriptions'
        ]);
        add_submenu_page("bocs", "Settings", "Settings", "manage_options", 'bocs-settings', [
            $this,
            'bocs_settings_page'
        ]);
        
        // Add submenu for testing the welcome email
        // Removed Test Welcome Email menu item as requested
        /* add_submenu_page(
            "bocs",
            "Test Welcome Email", 
            "Test Welcome Email",
            "manage_options",
            "admin-post.php?action=test_welcome_email_direct",
            null
        ); */
        
        // add_submenu_page("bocs", "Sync Store", "Sync Store", "manage_options", 'bocs-sync-store', [$this, 'bocs_sync_store_page'] );
        // add_submenu_page("bocs", "Error Logs", "Error Logs", "manage_options", 'bocs-error-logs', [$this, 'bocs_error_logs_page'] );

        remove_submenu_page('bocs', 'bocs');
        
        // Register a direct action for testing the welcome email
        // Removed along with the Test Welcome Email menu item
        // add_action('admin_post_test_welcome_email_direct', array($this, 'test_welcome_email_direct'));
    }
    
    /**
     * Test the welcome email directly with a specific order
     */
    public function test_welcome_email_direct() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }
        
        // Get the most recent order
        $orders = wc_get_orders(array(
            'limit' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        
        if (empty($orders)) {
            wp_die('No orders found to test with');
        }
        
        // Output HTML header
        echo '<!DOCTYPE html><html><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>Bocs Welcome Email Test</title>';
        echo '<style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; line-height: 1.6; padding: 20px; }
            .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 20px; border: 1px solid #ccc; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            h1 { color: #23282d; }
            .order-list { margin: 20px 0; }
            .order { margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; }
            .order:hover { background: #f9f9f9; }
            .button { display: inline-block; background: #0073aa; color: #fff; padding: 5px 15px; text-decoration: none; border-radius: 3px; }
            .button:hover { background: #005d87; }
            .button.danger { background: #d63638; }
            .button.danger:hover { background: #a00; }
            .result { margin-top: 20px; padding: 15px; background: #f0f0f1; border-left: 4px solid #0073aa; }
            .error { border-left-color: #d63638; }
        </style>';
        echo '</head><body>';
        echo '<div class="container">';
        echo '<h1>Bocs Welcome Email Test</h1>';
        
        // If an order was selected for testing
        if (isset($_GET['order_id'])) {
            $order_id = intval($_GET['order_id']);
            $order = wc_get_order($order_id);
            
            if ($order) {
                // Clear any 'already sent' flag if requested
                if (isset($_GET['reset']) && $_GET['reset'] === '1') {
                    delete_post_meta($order_id, '_bocs_welcome_email_sent');
                    echo '<div class="result">Reset "already sent" flag for order #' . esc_html($order_id) . '</div>';
                }
                
                // Force load the welcome email class
                if (!class_exists('WC_Bocs_Email_Welcome')) {
                    require_once BOCS_PLUGIN_DIR . 'includes/emails/class-bocs-email-welcome.php';
                }
                
                // Create an instance and trigger the email
                $welcome_email = new WC_Bocs_Email_Welcome();
                
                // Force-enable the email
                $welcome_email->enabled = 'yes';
                
                // Send the email
                $welcome_email->trigger($order_id);
                
                echo '<div class="result">';
                echo '<p>Attempted to send welcome email for order #' . esc_html($order_id) . '</p>';
                echo '<p>Sent to: ' . esc_html($order->get_billing_email()) . '</p>';
                echo '</div>';
            } else {
                echo '<div class="result error">Order #' . esc_html($order_id) . ' not found.</div>';
            }
        }
        
        // Show list of recent orders for testing
        echo '<h2>Select an order to test:</h2>';
        echo '<div class="order-list">';
        
        foreach ($orders as $order) {
            $order_id = $order->get_id();
            $already_sent = get_post_meta($order_id, '_bocs_welcome_email_sent', true) === 'yes';
            
            echo '<div class="order">';
            echo '<p><strong>Order #' . esc_html($order_id) . '</strong> - ' . esc_html($order->get_date_created()->date('Y-m-d H:i:s')) . '<br>';
            echo 'Customer: ' . esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) . ' (' . esc_html($order->get_billing_email()) . ')<br>';
            echo 'Status: ' . esc_html(wc_get_order_status_name($order->get_status())) . '</p>';
            
            if ($already_sent) {
                echo '<p><strong>Welcome email already sent for this order.</strong></p>';
                echo '<a href="' . esc_url(admin_url('admin-post.php?action=test_welcome_email_direct&order_id=' . $order_id . '&reset=1')) . '" class="button danger">Reset & Send Again</a> ';
            } else {
                echo '<a href="' . esc_url(admin_url('admin-post.php?action=test_welcome_email_direct&order_id=' . $order_id)) . '" class="button">Send Welcome Email</a>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
        echo '<p><a href="' . esc_url(admin_url()) . '">Return to Dashboard</a></p>';
        echo '</div></body></html>';
        
        exit;
    }

    public function bocs_list_subscriptions()
    {
        require_once dirname(__FILE__) . '/../views/bocs_list_subscriptions.php';
    }

    /**
     *
     * @return void
     */
    public function bocs_render_plugin_settings_page()
    {
?>
        <h2><?php esc_html_e('Bocs Settings', 'bocs-wordpress'); ?></h2>
        <form action="options.php" method="post">
            <?php
            settings_fields('bocs_plugin_options');
            do_settings_sections('bocs_plugin');
            ?>
            <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Save', 'bocs-wordpress'); ?>" />
        </form>
<?php
    }

    public function bocs_register_settings()
    {
        register_setting('bocs_plugin_options', 'bocs_plugin_options', [
            $this,
            'bocs_plugin_options_validate'
        ]);

        add_settings_section('api_settings', 'API Settings', [
            $this,
            'bocs_plugin_section_text'
        ], 'bocs_plugin');

        // value for the API Key
        add_settings_field('bocs_plugin_setting_api_key', 'Public API Key', [
            $this,
            'bocs_plugin_setting_api_key'
        ], 'bocs_plugin', 'api_settings');

        // enable/disable the sync from wordpress to bocs
        add_settings_field('bocs_plugin_setting_sync_contacts_to_bocs', 'Sync Contacts to Bocs', [
            $this,
            'bocs_plugin_setting_sync_contacts_to_bocs'
        ], 'bocs_plugin', 'api_settings');

        // enable/disable the daily auto sync from wordpress to bocs
        add_settings_field('bocs_plugin_setting_sync_daily_contacts_to_bocs', 'Daily Autosync Contacts to Bocs', [
            $this,
            'bocs_plugin_setting_sync_daily_contacts_to_bocs'
        ], 'bocs_plugin', 'api_settings');

        // enable/disable the daily auto sync from wordpress to bocs
        add_settings_field('bocs_plugin_setting_sync_daily_contacts_from_bocs', 'Daily Autosync Contacts From Bocs', [
            $this,
            'bocs_plugin_setting_sync_daily_contacts_from_bocs'
        ], 'bocs_plugin', 'api_settings');
    }

    public function bocs_plugin_section_text()
    {
        // Add text domain
        echo '<p>' . esc_html__('Here you can set all the options for using the API', 'bocs-wordpress') . '</p>';
    }

    /**
     * API Key setting
     *
     * @return void
     */
    public function bocs_plugin_setting_api_key()
    {
        $options = get_option('bocs_plugin_options');

        echo "<input id='bocs_plugin_setting_api_key' name='bocs_plugin_options[api_key]' type='text' value='" . esc_attr($options['api_key']) . "' />";
    }

    /**
     * Option for enabling/disabling the sync from wordpress to bocs
     *
     * @return void
     */
    public function bocs_plugin_setting_sync_contacts_to_bocs()
    {
        $options = get_option('bocs_plugin_options');

        $options['sync_contacts_to_bocs'] = $options['sync_contacts_to_bocs'] ?? 0;

        $html = '<input id="bocs_plugin_setting_sync_contacts_to_bocs" type="radio" name="bocs_plugin_options[sync_contacts_to_bocs]" value="1"';

        $html .= $options['sync_contacts_to_bocs'] == 1 ? ' checked' : '';

        $html .= '><label for="1">Yes</label>&nbsp;&nbsp;&nbsp;<input  id="bocs_plugin_setting_sync_contacts_to_bocs_no"  type="radio" name="bocs_plugin_options[sync_contacts_to_bocs]" value="0"';

        $html .= $options['sync_contacts_to_bocs'] != 1 ? ' checked' : '';

        $html .= '><label for="0">No</label>';

        $html .= '<br /><button class="button button-primary" id="syncContactsToBocs" type="button">Sync Now</button><p id="syncContactsToBocs-response"></p>';

        echo $html;
    }

    public function bocs_plugin_setting_sync_daily_contacts_to_bocs()
    {
        $options = get_option('bocs_plugin_options');

        // Daily Autosync

        $options['sync_daily_contacts_to_bocs'] = $options['sync_daily_contacts_to_bocs'] ?? 0;

        $html = '<input id="bocs_plugin_setting_sync_daily_contacts_to_bocs" type="radio" name="bocs_plugin_options[sync_daily_contacts_to_bocs]" value="1"';

        $html .= $options['sync_daily_contacts_to_bocs'] == 1 ? ' checked' : '';

        $html .= '><label for="1">Yes</label>&nbsp;&nbsp;&nbsp;<input  id="bocs_plugin_setting_sync_daily_contacts_to_bocs_no"  type="radio" name="bocs_plugin_options[sync_daily_contacts_to_bocs]" value="0"';

        $html .= $options['sync_daily_contacts_to_bocs'] != 1 ? ' checked' : '';

        $html .= '><label for="0">No</label>';

        echo $html;
    }

    public function bocs_plugin_setting_sync_daily_contacts_from_bocs()
    {
        $options = get_option('bocs_plugin_options');

        $options['sync_daily_contacts_from_bocs'] = $options['sync_daily_contacts_from_bocs'] ?? 0;

        $html = '<input id="bocs_plugin_setting_sync_daily_contacts_from_bocs" type="radio" name="bocs_plugin_options[sync_daily_contacts_from_bocs]" value="1"';

        $html .= $options['sync_daily_contacts_from_bocs'] == 1 ? ' checked' : '';

        $html .= '><label for="1">Yes</label>&nbsp;&nbsp;&nbsp;<input id="bocs_plugin_setting_sync_daily_contacts_from_bocs_no" type="radio" name="bocs_plugin_options[sync_daily_contacts_from_bocs]" value="0"';

        $html .= $options['sync_daily_contacts_from_bocs'] != 1 ? ' checked' : '';

        $html .= '><label for="0">No</label>';

        echo $html;
    }

    /**
     *
     * @param
     *            $input
     * @return array
     */
    public function bocs_plugin_options_validate($input)
    {
        $newinput = [];
        
        if (!isset($input['bocs_headers']) || !is_array($input['bocs_headers'])) {
            add_settings_error(
                'bocs_plugin_options',
                'invalid_headers',
                esc_html__('Invalid headers configuration provided', 'bocs-wordpress')
            );
            return $newinput;
        }

        $required_fields = ['organization', 'store', 'authorization'];
        foreach ($required_fields as $field) {
            if (empty($input['bocs_headers'][$field])) {
                add_settings_error(
                    'bocs_plugin_options',
                    'missing_' . $field,
                    sprintf(
                        /* translators: %s: Field name */
                        esc_html__('Missing required field: %s', 'bocs-wordpress'),
                        esc_html($field)
                    )
                );
            }
        }

        $newinput['bocs_headers'] = $input['bocs_headers'];
        return $newinput;
    }

    /**
     * Creates a product
     *
     * @return void
     */
    public function create_product_ajax_callback()
    {

        // Verify the AJAX nonce
        $nonce = $_POST['nonce'];

        if (! wp_verify_nonce($nonce, 'ajax-nonce')) {
            die(esc_html__('Invalid nonce', 'bocs-wordpress'));
        }

        // Get the product data from the AJAX request
        $product_title = $_POST['title'];
        $product_price = $_POST['price'];
        $product_sku = isset($_POST['sku']) ? $_POST['sku'] : '';
        $product_type = isset($_POST['type']) ? $_POST['type'] : "product";
        $bocs_product_id = isset($_POST['bocs_product_id']) ? $_POST['bocs_product_id'] : "";

        // Create a new WooCommerce product
        $new_product = array(
            'post_title' => $product_title,
            'post_status' => 'publish',
            'post_type' => 'product',
            'post_content' => ''
        );
        $product_id = wp_insert_post($new_product);

        // Set the product price
        if ($product_id) {
            update_post_meta($product_id, '_price', $product_price);
            update_post_meta($product_id, '_regular_price', $product_price);
            update_post_meta($product_id, '_sku', $product_sku);
            update_post_meta($product_id, '_backorders', 'no');
            update_post_meta($product_id, '_download_expiry', '-1');
            update_post_meta($product_id, '_download_limit', '-1');
            update_post_meta($product_id, '_downloadable', 'no');
            update_post_meta($product_id, '_manage_stock', 'no');
            update_post_meta($product_id, '_sold_individually', 'no');
            update_post_meta($product_id, '_stock_status', 'instock');
            update_post_meta($product_id, '_virtual', 'no');
            update_post_meta($product_id, '_wc_average_rating', '0');
            update_post_meta($product_id, '_wc_review_count', '0');
            update_post_meta($product_id, '_wc_review_count', '0');

            if ($product_type == 'bocs') {

                update_post_meta($product_id, 'bocs_product_discount', $_POST['bocs_product_discount']);
                update_post_meta($product_id, 'bocs_product_discount_type', $_POST['bocs_product_discount_type']);
                update_post_meta($product_id, 'bocs_product_interval', $_POST['bocs_product_interval']);
                update_post_meta($product_id, 'bocs_product_interval_count', $_POST['bocs_product_interval_count']);
                update_post_meta($product_id, 'bocs_frequency_id', $_POST['bocs_frequency_id']);

                update_post_meta($product_id, 'bocs_bocs_id', $_POST['bocs_bocs_id']);
                update_post_meta($product_id, 'bocs_type', $_POST['bocs_type']);
                update_post_meta($product_id, 'bocs_sku', $_POST['bocs_sku']);
                update_post_meta($product_id, 'bocs_price', round($_POST['bocs_price'], 2));
            }

            if (isset($_POST['bocs_id'])) {
                update_post_meta($product_id, 'bocs_id', $_POST['bocs_id']);
            }

            if ($bocs_product_id !== "") {
                update_post_meta($product_id, 'bocs_product_id', $bocs_product_id);
            }
        }

        // Return the product ID as the response
        wp_send_json($product_id);
    }

    /**
     *
     * Creates an order and a subscription on Bocs
     * once the WooCommerce order is in processing mode
     *
     * @param integer $order_id
     * @return false|void
     */
    public function bocs_order_status_processing($order_id = 0)
    {
        if (empty($order_id)) {
            return false;
        }

        // Get the order details
        $order = wc_get_order($order_id);
        if (!$order) {
            $this->log_error("Invalid order ID: {$order_id}");
            return false;
        }

        // Initialize variables
        $subscription_line_items = [];

        // Get Bocs data from order meta or session/cookies
        $bocsid = $this->get_bocs_value($order, '__bocs_bocs_id', 'bocs', '__bocs_id');
        $collectionid = $this->get_bocs_value($order, '__bocs_collections_id', 'bocs_collection', '__bocs_collection_id');
        $frequency_id = $this->get_bocs_value($order, '__bocs_frequency_id', 'bocs_frequency', '__bocs_frequency_id');
        $frequency_discount = $this->get_bocs_value($order, '__bocs_frequency_discount', 'bocs_frequency_discount', '__bocs_frequency_discount');
        $frequency_time_unit = $this->get_bocs_value($order, '__bocs_frequency_time_unit', 'bocs_frequency_time_unit', '__bocs_frequency_time_unit');
        $frequency_interval = $this->get_bocs_value($order, '__bocs_frequency_interval', 'bocs_frequency_interval', '__bocs_frequency_interval');
        $discount_type = $this->get_bocs_value($order, '__bocs_discount_type', 'bocs_discount_type', '__bocs_discount_type');

        // Only proceed if this is a Bocs order
        $is_bocs = !empty($bocsid);
        if (!$is_bocs) {
            return false;
        }

        // Process order items
        foreach ($order->get_items() as $item) {
            $item_data = $item->get_data();
            $quantity = $item->get_quantity();
            $product = $item->get_product();
            
            if (!$product) {
                continue;
            }
            
            // Get Bocs product ID
            $product_id = get_post_meta($item_data['product_id'], 'bocs_product_id', true);
            if (empty($product_id)) {
                $product_id = get_post_meta($item_data['product_id'], 'bocs_id', true);
            }
            
            // Create line item
            $subscription_line_items[] = array(
                'sku' => $product->get_sku(),
                'price' => round($product->get_regular_price(), 2),
                'quantity' => $quantity,
                'productId' => $product_id,
                'total' => (float)number_format((float)$item->get_total(), 2, '.', ''),
                'externalSourceId' => (string)$product->get_id()
            );
        }

        // Check if we have any line items
        if (empty($subscription_line_items)) {
            $this->log_error("No valid line items found for order ID: {$order_id}");
            return false;
        }

        // Get API credentials
        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();

        // Check if API credentials are set
        if (empty($options['bocs_headers']['organization']) || 
            empty($options['bocs_headers']['store']) || 
            empty($options['bocs_headers']['authorization'])) {
            $this->log_error("Missing Bocs API credentials");
            return false;
        }

        // Get customer information
        $customer_id = $order->get_customer_id();
        $bocs_customer_id = '';

        if (!empty($customer_id)) {
            $bocs_customer_id = get_user_meta($customer_id, 'bocs_user_id', true);

            // If no Bocs customer ID found, try to fetch it from API
            if (empty($bocs_customer_id)) {
                $bocs_customer_id = $this->get_bocs_customer_id($customer_id, $order, $options);
            }
        }

        // Prepare the start date
        $start_date = $this->format_subscription_start_date($order);

        // Set the frequency data from order meta or cookies
        $current_frequency = [
            'id' => $frequency_id ?: $this->get_sanitized_cookie('__bocs_frequency_id', ''),
            'timeUnit' => $frequency_time_unit ?: $this->get_sanitized_cookie('__bocs_frequency_time_unit', ''),
            'frequency' => intval($frequency_interval ?: $this->get_sanitized_cookie('__bocs_frequency_interval', 0)),
            'discount' => floatval($frequency_discount ?: $this->get_sanitized_cookie('__bocs_frequency_discount', 0.0)),
            'discountType' => $discount_type ?: $this->get_sanitized_cookie('__bocs_discount_type', '')
        ];

        // Validate frequency data
        if (empty($current_frequency['id']) || empty($current_frequency['timeUnit'])) {
            $this->log_warning("Missing frequency data for order ID: {$order_id}. Using defaults.");
            
            // Set default values if missing
            if (empty($current_frequency['timeUnit'])) {
                $current_frequency['timeUnit'] = 'month';
            }
            
            if ($current_frequency['frequency'] <= 0) {
                $current_frequency['frequency'] = 1;
            }
        }

        // Prepare the subscription data
        $post_data_array = [
            'bocs' => ['id' => $bocsid],
            'billing' => [
                'firstName' => $order->get_billing_first_name(),
                'lastName' => $order->get_billing_last_name(),
                'company' => $order->get_billing_company(),
                'address1' => $order->get_billing_address_1(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'country' => $order->get_billing_country(),
                'postcode' => $order->get_billing_postcode(),
                'phone' => $order->get_billing_phone(),
                'email' => $order->get_billing_email()
            ],
            'shipping' => [
                'firstName' => $order->get_shipping_first_name(),
                'lastName' => $order->get_shipping_last_name(),
                'company' => $order->get_shipping_company(),
                'address1' => $order->get_shipping_address_1(),
                'city' => $order->get_shipping_city(),
                'state' => $order->get_shipping_state(),
                'country' => $order->get_shipping_country(),
                'postcode' => $order->get_shipping_postcode(),
                'phone' => '',
                'email' => ''
            ],
            'customer' => [
                'id' => $bocs_customer_id,
                'externalSourceId' => (string)$customer_id
            ],
            'lineItems' => $subscription_line_items,
            'frequency' => $current_frequency,
            'startDateGmt' => $start_date,
            'order' => json_decode($this->get_order_data_as_json($order_id), true),
            'total' => number_format((float)$order->get_total(), 2, '.', ''),
            'discountTotal' => round($order->get_discount_total() + $order->get_discount_tax(), 2)
        ];

        // Add collection ID if present
        if (!empty($collectionid)) {
            $post_data_array['collection'] = ['id' => $collectionid];
        }

        // Create the subscription via API
        $result = $this->create_bocs_subscription($post_data_array, $options);
        
        // Clean up cookies regardless of result
        $this->clear_bocs_cookies($order_id);
        
        return $result;
    }

    /**
     * Format the subscription start date in ISO 8601 format
     * 
     * @param WC_Order $order The order object
     * @return string The formatted date
     */
    private function format_subscription_start_date($order) 
    {
        $start_date = $order->get_date_paid();
        
        // If date_paid is null, fall back to date_created
        if (empty($start_date)) {
            $start_date = $order->get_date_created();
            
            // If still empty, use current time
            if (empty($start_date)) {
                $start_date = current_time('mysql');
            }
        }
        
        // Get WordPress timezone
        $timezone_string = get_option('timezone_string');
        if (empty($timezone_string)) {
            $offset = get_option('gmt_offset');
            $timezone_string = timezone_name_from_abbr('', $offset * 3600, false);
        }
        
        // Create a DateTimeZone object with the WordPress timezone
        $timezone = new DateTimeZone($timezone_string);
        
        // Create a DateTime object from the order date
        $date_time = new DateTime($start_date, $timezone);
        
        // Convert to UTC and format with milliseconds
        $date_time->setTimezone(new DateTimeZone('UTC'));
        return $date_time->format('Y-m-d\TH:i:s') . '.000Z';
    }

    /**
     * Get a value from order meta, session, or cookies
     * 
     * @param WC_Order $order The order object
     * @param string $meta_key The meta key to check
     * @param string $session_key The session key to check
     * @param string $cookie_key The cookie key to check
     * @return string The value found or empty string
     */
    private function get_bocs_value($order, $meta_key, $session_key, $cookie_key) 
    {
        // First try to get from order meta
        $value = $order->get_meta($meta_key);
        
        if (!empty($value)) {
            $this->log_debug("Found {$meta_key} from order meta: {$value}", [
                'order_id' => $order->get_id()
            ]);
        }
        
        // Also check alternative meta key format (some might use __bocs_id instead of __bocs_bocs_id)
        if (empty($value) && $meta_key === '__bocs_bocs_id') {
            $value = $order->get_meta('__bocs_id');
            
            // If found in alternative key, update the standard key for consistency
            if (!empty($value)) {
                $order->update_meta_data($meta_key, $value);
                $order->save();
                $this->log_debug("Found {$meta_key} from alternative meta key '__bocs_id': {$value}", [
                    'order_id' => $order->get_id()
                ]);
            }
        }
        
        // If not found, try from session
        if (empty($value) && isset(WC()->session)) {
            $bocs_value = WC()->session->get($session_key);
            
            if (!empty($bocs_value)) {
                $this->log_debug("Found {$meta_key} from session key '{$session_key}': {$bocs_value}", [
                    'order_id' => $order->get_id()
                ]);
            }
            
            // If not in session, try from cookies
            if (empty($bocs_value)) {
                $cookie_source = '';
                
                // First check the primary cookie key
                if (isset($_COOKIE[$cookie_key])) {
                    $bocs_value = sanitize_text_field($_COOKIE[$cookie_key]);
                    $cookie_source = "primary cookie '{$cookie_key}'";
                } 
                // Try alternative cookie key format if primary not found
                elseif ($cookie_key === '__bocs_id' && isset($_COOKIE['__bocs_bocs_id'])) {
                    $bocs_value = sanitize_text_field($_COOKIE['__bocs_bocs_id']);
                    $cookie_source = "alternative cookie '__bocs_bocs_id'";
                }
                elseif ($cookie_key === '__bocs_collection_id' && isset($_COOKIE['__bocs_collections_id'])) {
                    $bocs_value = sanitize_text_field($_COOKIE['__bocs_collections_id']);
                    $cookie_source = "alternative cookie '__bocs_collections_id'";
                }
                
                if (!empty($bocs_value) && !empty($cookie_source)) {
                    $this->log_debug("Found {$meta_key} from {$cookie_source}: {$bocs_value}", [
                        'order_id' => $order->get_id()
                    ]);
                }
            }
            
            // If we found a value, update the order meta
            if (!empty($bocs_value)) {
                $value = $bocs_value;
                $order->update_meta_data($meta_key, $bocs_value);
                $order->save(); // Make sure to save the order to persist the meta data
            }
        }
        
        if (empty($value)) {
            $this->log_debug("No value found for {$meta_key} in order, session, or cookies", [
                'order_id' => $order->get_id()
            ]);
        }
        
        return $value;
    }
    
    /**
     * Get a sanitized cookie value with optional type casting
     * 
     * @param string $cookie_name The name of the cookie
     * @param mixed $default The default value if cookie is not set
     * @param string $cast_function Optional function to cast the value (intval, floatval)
     * @return mixed The cookie value or default
     */
    private function get_sanitized_cookie($cookie_name, $default = '', $cast_function = null) 
    {
        if (isset($_COOKIE[$cookie_name])) {
            $value = sanitize_text_field($_COOKIE[$cookie_name]);
            if ($cast_function && function_exists($cast_function)) {
                return $cast_function($value);
            }
            return $value;
        }
        return $default;
    }
    
    /**
     * Get Bocs customer ID for a user
     * 
     * @param int $customer_id WooCommerce customer ID
     * @param WC_Order $order The order object
     * @param array $options Plugin options with API credentials
     * @return string Bocs customer ID or empty string on failure
     */
    private function get_bocs_customer_id($customer_id, $order, $options) 
    {
        // Get user email from current user or order
        $user_email = false;
        $current_user = wp_get_current_user();
        
        if ($current_user->exists()) {
            $user_email = esc_html($current_user->user_email);
        }
        
        if ($user_email === false) {
            $user_email = $order->get_billing_email();
        }
        
        if ($user_email === false) {
            $this->log_error('No email address found for customer');
            return '';
        }
        
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => BOCS_API_URL . 'contacts?query=email:"' . $user_email . '"',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Organization: ' . $options['bocs_headers']['organization'],
                    'Content-Type: application/json',
                    'Store: ' . $options['bocs_headers']['store'],
                    'Authorization: ' . $options['bocs_headers']['authorization']
                )
            ));
            
            $response = curl_exec($curl);
            $object = json_decode($response);
            curl_close($curl);
            
            if (isset($object->data)) {
                $data = isset($object->data->data) ? $object->data->data : $object->data;
                if (count($data) > 0) {
                    foreach ($data as $bocs_user) {
                        update_user_meta($customer_id, 'bocs_user_id', $bocs_user->id);
                        return $bocs_user->id;
                    }
                }
            }
        } catch (Exception $e) {
            $this->log_error('Error fetching Bocs customer: ' . $e->getMessage());
        }
        
        return '';
    }
    
    /**
     * Create a subscription in the Bocs API
     * 
     * @param array $subscription_data The subscription data to send
     * @param array $options Plugin options with API credentials
     * @return bool True on success, false on failure
     */
    private function create_bocs_subscription($subscription_data, $options) 
    {
        try {
            // Encode the data array to JSON
            $post_data = json_encode($subscription_data);
            
            // Validate JSON encoding
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log_error('JSON encoding error: ' . json_last_error_msg());
                return false;
            }
            
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => BOCS_API_URL . 'subscriptions',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $post_data,
                CURLOPT_HTTPHEADER => array(
                    'Organization: ' . $options['bocs_headers']['organization'],
                    'Content-Type: application/json',
                    'Store: ' . $options['bocs_headers']['store'],
                    'Authorization: ' . $options['bocs_headers']['authorization']
                )
            ));
            
            $response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            
            if (curl_errno($curl)) {
                $error = curl_error($curl);
                $this->log_error("Failed to create subscription: {$error}");
                curl_close($curl);
                return false;
            }
            
            curl_close($curl);
            
            if ($http_code >= 200 && $http_code < 300) {
                $this->log_info("Subscription created successfully. Response code: {$http_code}");
                return true;
            } else {
                $this->log_error("Subscription creation failed. Response code: {$http_code}");
                return false;
            }
        } catch (Exception $e) {
            $this->log_error("Exception creating subscription: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear all Bocs-related cookies
     * 
     * @param int|null $order_id Optional order ID for logging context
     */
    private function clear_bocs_cookies($order_id = null) 
    {
        $cookies_to_destroy = [
            // Standard cookie names
            '__bocs_id',
            '__bocs_collection_id',
            '__bocs_frequency_id',
            '__bocs_frequency_time_unit',
            '__bocs_frequency_interval',
            '__bocs_discount_type',
            '__bocs_total',
            '__bocs_discount',
            '__bocs_subtotal',
            
            // Alternative cookie names
            '__bocs_bocs_id',
            '__bocs_collections_id'
        ];
        
        $context = [];
        if (!empty($order_id)) {
            $context['order_id'] = $order_id;
        }
        
        $this->log_debug("Clearing all BOCS cookies" . (!empty($order_id) ? " for order #{$order_id}" : ""), $context);
        
        $cleared_count = 0;
        foreach ($cookies_to_destroy as $cookie_name) {
            if (isset($_COOKIE[$cookie_name])) {
                $cookie_value = $_COOKIE[$cookie_name];
                unset($_COOKIE[$cookie_name]);
                setcookie($cookie_name, '', time() - 3600, '/');
                $this->log_debug("Cleared cookie: {$cookie_name} with value: {$cookie_value}", $context);
                $cleared_count++;
            }
        }
        
        if ($cleared_count === 0) {
            $this->log_debug("No BOCS cookies found to clear", $context);
        } else {
            $this->log_debug("Cleared {$cleared_count} BOCS cookies", $context);
        }
    }
    
    /**
     * Get order data as a JSON string for the Bocs API
     * 
     * @param int $order_id The WooCommerce order ID
     * @return string JSON representation of the order
     */
    private function get_order_data_as_json($order_id)
    {
        // Get the order object
        $order = wc_get_order($order_id);

        if (! $order) {
            return json_encode(array(
                'error' => 'Order not found'
            ));
        }

        // Prepare the order data
        $order_data = array(
            'id' => $order->get_id(),
            'parent_id' => $order->get_parent_id(),
            'status' => $order->get_status(),
            'currency' => $order->get_currency(),
            'version' => $order->get_version(),
            'prices_include_tax' => $order->get_prices_include_tax(),
            'date_created' => $order->get_date_created()->date('c'),
            'date_modified' => $order->get_date_modified()->date('c'),
            'discount_total' => $order->get_discount_total(),
            'discount_tax' => $order->get_discount_tax(),
            'shipping_total' => $order->get_shipping_total(),
            'shipping_tax' => $order->get_shipping_tax(),
            'cart_tax' => $order->get_cart_tax(),
            'total' => number_format((float)$order->get_total(), 2, '.', ''),
            'total_tax' => $order->get_total_tax(),
            'customer_id' => $order->get_customer_id(),
            'order_key' => $order->get_order_key(),
            'billing' => $order->get_address('billing'),
            'shipping' => $order->get_address('shipping'),
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'transaction_id' => $order->get_transaction_id(),
            'customer_ip_address' => $order->get_customer_ip_address(),
            'customer_user_agent' => $order->get_customer_user_agent(),
            'created_via' => $order->get_created_via(),
            'customer_note' => $order->get_customer_note(),
            'date_completed' => $order->get_date_completed() ? $order->get_date_completed()->date('c') : null,
            'date_paid' => $order->get_date_paid() ? $order->get_date_paid()->date('c') : null,
            'cart_hash' => $order->get_cart_hash(),
            'meta_data' => $order->get_meta_data(),
            'line_items' => array(),
            'tax_lines' => array(),
            'shipping_lines' => array(),
            'fee_lines' => array(),
            'coupon_lines' => array(),
            'refunds' => array()
        );

        // Get line items
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $order_data['line_items'][] = array(
                'id' => $item_id,
                'name' => $item->get_name(),
                'product_id' => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'quantity' => $item->get_quantity(),
                'tax_class' => $item->get_tax_class(),
                'subtotal' => $item->get_subtotal(),
                'subtotal_tax' => $item->get_subtotal_tax(),
                'total' => (float)number_format((float)$item->get_total(), 2, '.', ''),
                'total_tax' => $item->get_total_tax(),
                'taxes' => $item->get_taxes(),
                'meta_data' => $item->get_meta_data(),
                'sku' => $product ? $product->get_sku() : '',
                'price' => $product ? $product->get_price() : ''
            );
        }

        // Get tax lines
        foreach ($order->get_tax_totals() as $tax) {
            $order_data['tax_lines'][] = array(
                'id' => $tax->id,
                'rate_code' => $tax->rate_id,
                'rate_id' => $tax->rate_id,
                'label' => $tax->label,
                'compound' => isset($tax->compound) ? $tax->compound : 0,
                'tax_total' => isset($tax->tax_total) ? $tax->tax_total : 0,
                'shipping_tax_total' => isset($tax->shipping_tax_total) ? $tax->shipping_tax_total : 0
            );
        }

        // Get shipping lines
        foreach ($order->get_shipping_methods() as $shipping_item_id => $shipping_item) {
            $order_data['shipping_lines'][] = array(
                'id' => $shipping_item_id,
                'method_title' => $shipping_item->get_name(),
                'method_id' => $shipping_item->get_method_id(),
                'total' => (float)number_format((float)$shipping_item->get_total(), 2, '.', ''),
                'total_tax' => $shipping_item->get_total_tax(),
                'taxes' => $shipping_item->get_taxes()
            );
        }

        // Get fee lines
        foreach ($order->get_fees() as $fee_item_id => $fee_item) {
            $order_data['fee_lines'][] = array(
                'id' => $fee_item_id,
                'name' => $fee_item->get_name(),
                'tax_class' => $fee_item->get_tax_class(),
                'tax_status' => $fee_item->get_tax_status(),
                'total' => (float)number_format((float)$fee_item->get_total(), 2, '.', ''),
                'total_tax' => $fee_item->get_total_tax(),
                'taxes' => $fee_item->get_taxes()
            );
        }

        // Get coupon lines
        foreach ($order->get_items('coupon') as $coupon_item_id => $coupon_item) {
            $order_data['coupon_lines'][] = array(
                'id' => $coupon_item_id,
                'code' => $coupon_item->get_code(),
                'discount' => $coupon_item->get_discount(),
                'discount_tax' => $coupon_item->get_discount_tax()
            );
        }

        // Get refunds
        foreach ($order->get_refunds() as $refund) {
            $order_data['refunds'][] = array(
                'id' => $refund->get_id(),
                'reason' => $refund->get_reason(),
                'total' => (float)number_format((float)$refund->get_amount(), 2, '.', '')
            );
        }

        // Encode the data to JSON and return
        return json_encode($order_data, JSON_PRETTY_PRINT);
    }

    public function search_product_ajax_callback(){

        // Verify the AJAX nonce
        $nonce = $_POST['nonce'];

        if (! wp_verify_nonce($nonce, 'ajax-search-nonce')) {
            die('Invalid nonce');
        }

        $product_id = 0;

        $bocs_frequency_id = isset($_POST['bocs_frequency_id']) ? $_POST['bocs_frequency_id'] : 0;
        $bocs_bocs_id = isset($_POST['bocs_bocs_id']) ? $_POST['bocs_bocs_id'] : 0;
        $bocs_product_id = isset($_POST['bocs_product_id']) ? $_POST['bocs_product_id'] : '';

        // first we need to search by sku and frequency id
        global $wpdb;

        if (! empty($bocs_frequency_id) && ! empty($bocs_bocs_id)) {
            $prepare_query = $wpdb->prepare("SELECT pm1.post_id FROM " . $wpdb->prefix . "postmeta as pm1
				INNER JOIN " . $wpdb->prefix . "postmeta as pm2 ON pm2.post_id = pm1.post_id
				WHERE pm1.meta_key = %s AND pm1.meta_value = %s AND pm2.meta_key = %s AND pm2.meta_value = %s", 'bocs_frequency_id', $bocs_frequency_id, 'bocs_bocs_id', $bocs_bocs_id);
        } else {
            $prepare_query = $wpdb->prepare("SELECT meta.post_id FROM  " . $wpdb->prefix . "postmeta as meta
                INNER JOIN " . $wpdb->prefix . "posts as posts
                ON posts.ID = meta.post_id
                WHERE meta.meta_key = %s
                AND meta.meta_value = %s
                AND posts.post_status = %s
                ORDER BY  meta.post_id ASC", 'bocs_product_id', $bocs_product_id, 'publish');
        }

        $products = $wpdb->get_results($prepare_query);

        if (count($products) > 0) {
            $product_id = $products[0]->post_id;
        }

        wp_send_json($product_id);
    }

    public function update_product_ajax_callback()
    {

        // Verify the AJAX nonce
        $nonce = $_POST['nonce'];

        if (! wp_verify_nonce($nonce, 'ajax-update-product-nonce')) {
            die('Invalid nonce');
        }

        // Get the product data from the AJAX request
        $bocs_product_id = $_POST['bocs_product_id'];
        $product_id = $_POST['id'];

        // Set the product price
        if ($product_id) {
            update_post_meta($product_id, 'bocs_product_id', $bocs_product_id);
        }

        // Return the product ID as the response
        wp_send_json($product_id);
    }

    public function create_coupon_ajax_callback()
    {

        // Verify the AJAX nonce
        $nonce = $_POST['nonce'];

        if (! wp_verify_nonce($nonce, 'ajax-create-coupon-nonce')) {
            die('Invalid nonce');
        }

        $coupon_code = sanitize_text_field($_POST['coupon_code']);
        $discount_type = sanitize_text_field($_POST['discount_type']);
        $amount = floatval($_POST['amount']);

        $coupon = array(
            'post_title' => $coupon_code,
            'post_content' => '',
            'post_status' => 'publish',
            'post_author' => 1,
            'post_type' => 'shop_coupon'
        );

        $new_coupon_id = wp_insert_post($coupon);

        if ($new_coupon_id) {
            update_post_meta($new_coupon_id, 'discount_type', $discount_type);
            update_post_meta($new_coupon_id, 'coupon_amount', $amount);
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }

    /**
     * Saved the current bocs and collections,
     * and also the one that is selected by the user
     *
     * @return void
     */
    public function save_widget_options_callback()
    {

        // Verify the AJAX nonce
        $nonce = $_POST['nonce'];

        if (! wp_verify_nonce($nonce, 'save-widget-nonce')) {
            die('Invalid nonce');
        }

        $selectedOption = isset($_POST['selectedOption']) ? $_POST['selectedOption'] : '';
        $selectedOptionName = isset($_POST['selectedOptionName']) ? $_POST['selectedOptionName'] : '';
        $postId = isset($_POST['postId']) ? $_POST['postId'] : '';

        if (! empty($postId) && ! empty($selectedOption)) {
            update_post_meta($postId, 'selected_bocs_widget_id', $selectedOption);
            update_post_meta($postId, 'selected_bocs_widget_name', $selectedOptionName);
            echo 'success';
            die();
        }

        echo 'failed';
        die();
    }

    public function custom_user_admin_icon_css()
    {

        // adds the modified styling here
        // echo '<style></style>';
        echo "";
    }

    public function custom_add_user_column($columns)
    {
        $columns['source'] = "Source";
        return $columns;
    }

    /**
     * Adds an icon before the user's full name
     */
    public function custom_admin_user_icon($val, $column_name, $user_id)
    {
        if ($column_name == 'source') {
            // check user's meta if from bocs
            $bocs_source = get_user_meta($user_id, IS_BOCS_META_KEY, true);

            $val = "Wordpress";

            if ($bocs_source) {
                if ($bocs_source == 1 || $bocs_source == "true") {
                    // we will consider this as source from bocs
                    $val = "Bocs";
                }
            }
        }

        return $val;
    }

    public function custom_add_source_filter()
    {

        echo '<select name="source" id="source">
				<option value=""></option>
				<option value="select">Select Source</option>
				<option value="both">Both</option>
				<option value="bocs">Bocs</option>
				<option value="wordpress">WordPress</option>
			</select>';
    }

    /**
     * Filter the query by source
     */
    public function custom_filter_users_by_source($query)
    {

        // if( !is_admin() || !$query->is_main_query() ) return;
        if (! is_admin())
            return;

        $current_source = isset($_GET['source']) ? $_GET['source'] : '';

        if ($current_source != 'bocs' && $current_source != 'wordpress')
            return;

        $meta_queries = array();

        if ($current_source == 'bocs') {

            $meta_queries[] = array(
                'key' => IS_BOCS_META_KEY,
                'value' => true,
                'compare' => '='
            );

            $meta_queries[] = array(
                'key' => IS_BOCS_META_KEY,
                'value' => 'true',
                'compare' => '='
            );

            $meta_queries[] = array(
                'key' => IS_BOCS_META_KEY,
                'value' => 1,
                'compare' => '='
            );

            $meta_queries[] = array(
                'key' => IS_BOCS_META_KEY,
                'value' => '1',
                'compare' => '='
            );

            $query->set('meta_query', array(
                'relation' => 'OR'
            ) + $meta_queries);
        } else {

            $meta_query = array(
                'relation' => 'OR',
                array(
                    'key' => IS_BOCS_META_KEY,
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => IS_BOCS_META_KEY,
                    'compare' => '=',
                    'value' => ''
                ),
                array(
                    'key' => IS_BOCS_META_KEY,
                    'compare' => '=',
                    'value' => '0'
                ),
                array(
                    'key' => IS_BOCS_META_KEY,
                    'compare' => '=',
                    'value' => 0
                ),
                array(
                    'key' => IS_BOCS_META_KEY,
                    'compare' => '=',
                    'value' => false
                ),
                array(
                    'key' => IS_BOCS_META_KEY,
                    'compare' => '=',
                    'value' => 'false'
                )
            );

            $query->set('meta_query', $meta_query);
        }
    }

    /**
     * Display the Bocs Widget Metabox content in the WordPress admin.
     * 
     * This method renders a metabox containing dropdown menus for Bocs Collections and Widgets,
     * along with a shortcode display section. The metabox appears in the sidebar of page edit screens
     * and allows administrators to:
     * 1. Select from available Bocs Collections
     * 2. Choose specific Widgets within the selected Collection 
     * 3. Copy generated shortcodes for use in page content
     *
     * HTML Structure:
     * - Main container div with ID 'bocs-page-sidebar'
     * - Collections dropdown section
     * - Widgets dropdown section 
     * - Shortcode display section
     *
     * The dropdowns are populated dynamically via JavaScript (handled separately).
     * The shortcode is generated automatically based on selections.
     *
     * @since 1.0.0
     * @access public
     *
     * @param WP_Post $page The WordPress page object being edited
     * @return void
     *
     * @see add_bocs_widget_metabox() Method that registers this metabox
     * @see assets/js/bocs-admin.js JavaScript that handles the dynamic functionality
     *
     * Usage:
     * This method is called automatically by WordPress when displaying the metabox
     * registered via add_meta_box() in add_bocs_widget_metabox().
     */
    public function bocs_widget_metabox_content($page)
    {
        // Begin main container div
        echo "<div id='bocs-page-sidebar'>";

        // Collections dropdown section
        echo "<!-- Collections dropdown with label -->";
        echo "<label for='bocs-page-sidebar-collections'>Collections</label><br />"; 
        echo "<select id='bocs-page-sidebar-collections' name='collections'></select><br />";

        // Widgets dropdown section
        echo "<!-- Widgets dropdown with label and spacing -->";
        echo "<br /><label for='bocs-page-sidebar-widgets'>Widget</label><br />";
        echo "<select id='bocs-page-sidebar-widgets' name='widgets'></select><br />";

        // Shortcode display section
        echo "<!-- Shortcode display section with heading -->";
        echo "<br /><label><b>Copy the shortcode below</b></label><br />";
        echo "<code id='bocs-shortcode-copy'></code>";

        // Close main container
        echo "</div>";
    }

    /**
     * Adds metabox on the right side of the Edit Page
     *
     * @return void
     */
    public function add_bocs_widget_metabox()
    {
        add_meta_box('bocs_widget_metabox', 'Bocs Widget Shortcode', array(
            $this,
            'bocs_widget_metabox_content'
        ), 'page', 'side', 'high');
    }

    /**
     * This will do the auto add/update for getting the list
     * of the collections and widget
     *
     * @return void
     */
    public function update_widgets_collections() {}

    /**
     * Adds sidebar to a product edit page
     * This is for the bocs logs
     *
     * @return void
     */
    public function add_product_sidebar_to_woocommerce_admin()
    {
        add_meta_box('bocs_product_sidebar', 'Bocs Sync', array(
            $this,
            'render_product_sidebar'
        ), 'product', 'side', 'default');
    }

    /**
     *
     * Shows the list of the bocs logs of the product
     *
     * @param WP_Post $post
     *
     * @return void
     */
    public function render_product_sidebar($post)
    {

        // get the list of the logs related on this product and bocs
        $logs_class = new Bocs_Log_Handler();
        $comments = $logs_class->get_product_logs($post->ID);

        if (! empty($comments)) {
            foreach ($comments as $comment) {
                echo "<p>" . $comment['comment_content'] . "</p>";
            }
        }
    }

    /**
     * Adds Bocs meta data from cart items to order meta
     * 
     * This method handles transferring Bocs-specific meta data from cart items to order meta
     * when an order is created. It extracts identifier information from the cart item meta data
     * and adds it to the order's meta data.
     *
     * @since 1.0.0
     * @access public
     *
     * @param WC_Order_Item $item        The order item object
     * @param string        $cart_item_key The cart item key
     * @param array         $values       The cart item values
     * @param WC_Order      $order        The WooCommerce order object
     *
     * @return void
     * 
     * Meta Data Structure Example:
     * $values['meta_data'] = [
     *     0 => [
     *         'key' => '__bocs_bocs_id',
     *         'value' => '123456789'
     *     ]
     * ];
     */
    public function add_cart_item_meta_to_order_items($item, $cart_item_key, $values, $order) 
    {
        // Initialize variables to store Bocs IDs
        $__bocs_bocs_id = '';
        $__bocs_collections_id = '';
        $__bocs_custom_price = '';

        // Get the parent order if this is a revision
        $order_id = $order->get_id();
        $parent_order_id = wp_get_post_parent_id($order_id);
        if ($parent_order_id) {
            $parent_order = wc_get_order($parent_order_id);
            if ($parent_order) {
                // Use parent order for storing meta
                $order = $parent_order;
            }
        }

        // Check if cart item has meta data
        if (isset($values['meta_data']) && !empty($values['meta_data'])) {
            // Iterate through meta data to find Bocs identifiers
            foreach ($values['meta_data'] as $meta) {
                // Check and store Bocs ID if not already found
                if ($meta->key == '__bocs_bocs_id' && $__bocs_bocs_id == '') {
                    $__bocs_bocs_id = trim($meta->value);
                }

                // Check and store Collections ID if not already found
                if ($meta->key == '__bocs_collections_id' && $__bocs_collections_id == '') {
                    $__bocs_collections_id = trim($meta->value);
                }
                
                // Check for custom price metadata
                if ($meta->key == '_bocs_custom_price' && $__bocs_custom_price == '') {
                    $__bocs_custom_price = trim($meta->value);
                    
                    // Also store the custom price in the order item meta
                    $item->add_meta_data('_bocs_custom_price', $__bocs_custom_price, true);
                }
            }
        }

        // Check if we should update BOCS ID
        if ($__bocs_bocs_id != '') {
            // First check if we already have a BOCS ID in order meta
            $existing_bocs_id = $order->get_meta('__bocs_bocs_id');
            
            // Check for cookie value which might be more recent
            $cookie_bocs_id = '';
            if (isset($_COOKIE['__bocs_id'])) {
                $cookie_bocs_id = sanitize_text_field($_COOKIE['__bocs_id']);
            }
            
            // Prioritize cookie value if it exists, otherwise use the meta value
            $final_bocs_id = !empty($cookie_bocs_id) ? $cookie_bocs_id : $__bocs_bocs_id;
            
            // Only update if different from existing value
            if ($existing_bocs_id !== $final_bocs_id) {
                $order->update_meta_data('__bocs_bocs_id', $final_bocs_id);
                $this->log_info('Updated BOCS ID in order meta: ' . $final_bocs_id);
            }
        }

        // Update order meta with Collections ID if found
        if ($__bocs_collections_id != '') {
            $existing_collections_id = $order->get_meta('__bocs_collections_id');
            
            // Check for cookie value
            $cookie_collections_id = '';
            if (isset($_COOKIE['__bocs_collection_id'])) {
                $cookie_collections_id = sanitize_text_field($_COOKIE['__bocs_collection_id']);
            }
            
            // Prioritize cookie value if it exists
            $final_collections_id = !empty($cookie_collections_id) ? $cookie_collections_id : $__bocs_collections_id;
            
            // Only update if different from existing value
            if ($existing_collections_id !== $final_collections_id) {
                $order->update_meta_data('__bocs_collections_id', $final_collections_id);
            }
        }
        
        // Save the order to persist changes
        $order->save();
    }

    /**
     * Filters and potentially modifies WooCommerce cart item data before it's added to the cart.
     * 
     * This method serves as a hook for the 'woocommerce_add_cart_item_data' filter, allowing
     * modification or addition of data to cart items as they're added to the cart. Currently
     * implemented as a pass-through, but provides a foundation for future customization.
     *
     * @since 1.0.0
     * @access public
     *
     * @param array $cart_item_data The existing cart item data array, containing:
     *                             - product details
     *                             - quantity
     *                             - variation data (if applicable)
     *                             - any custom meta data
     * @param int   $product_id    The ID of the product being added to cart
     * @param int   $variation_id  The variation ID if the product is a variable product, 0 otherwise
     *
     * @return array The modified (or unmodified) cart item data
     *
     * @example
     * // To add custom meta data to cart items:
     * public function add_custom_cart_item_data($cart_item_data, $product_id, $variation_id) {
     *     $cart_item_data['custom_data'] = 'some value';
     *     return $cart_item_data;
     * }
     *
     * @filter woocommerce_add_cart_item_data
     * 
     * @todo Consider adding Bocs-specific data modifications:
     *       - Subscription information
     *       - Frequency details
     *       - Collection association
     */
    public function add_custom_cart_item_data($cart_item_data, $product_id, $variation_id)
    {
        // Currently returns unmodified cart item data
        // Future implementations can add Bocs-specific data here
        return $cart_item_data;
    }

    public function add_custom_to_cart_data($add_to_cart_data, $request)
    {
        if (! empty($request['meta_data'])) {
            $add_to_cart_data['meta_data'] = sanitize_text_field($request['meta_data']);
        }

        return $add_to_cart_data;
    }

    /**
     * Captures BOCS parameters from URL or cookies and stores them in the WooCommerce session
     * 
     * This method is responsible for capturing BOCS-related parameters either from
     * URL query parameters or from cookies, and then storing them in the WooCommerce
     * session for later use during checkout. It runs on cart and checkout pages.
     *
     * @since 1.0.0
     * @access public
     * 
     * @return void
     */
    public function capture_bocs_parameter()
    {
        if (is_cart() || is_checkout()) {
            $this->log_info('Starting capture_bocs_parameter process on ' . (is_cart() ? 'cart' : 'checkout') . ' page');
            
            // Log all cookies to see what's available
            if (isset($_COOKIE) && !empty($_COOKIE)) {
                $bocs_cookies = array_filter(array_keys($_COOKIE), function($key) {
                    return strpos($key, '__bocs') === 0;
                });
                
                if (!empty($bocs_cookies)) {
                    $this->log_info('Found BOCS cookies: ' . implode(', ', $bocs_cookies));
                } else {
                    $this->log_warning('No BOCS cookies found');
                }
            }
            
            // Process main BOCS ID
            if (isset($_GET['bocs'])) {
                $bocs_value = sanitize_text_field($_GET['bocs']);
                if (!empty($bocs_value)) {
                    WC()->session->set('bocs', $bocs_value);
                    $this->log_info('Set BOCS ID in session from URL parameter: ' . $bocs_value);
                }
            } elseif (isset($_COOKIE['__bocs_id'])) {
                $bocs_value = sanitize_text_field($_COOKIE['__bocs_id']);
                if (!empty($bocs_value)) {
                    WC()->session->set('bocs', $bocs_value);
                    $this->log_info('Set BOCS ID in session from cookie: ' . $bocs_value);
                }
            }

            // Process collection ID
            if (isset($_GET['collection'])) {
                $bocs_value = sanitize_text_field($_GET['collection']);
                if (!empty($bocs_value)) {
                    WC()->session->set('bocs_collection', $bocs_value);
                    $this->log_info('Set collection ID in session from URL parameter: ' . $bocs_value);
                }
            } elseif (isset($_COOKIE['__bocs_collection_id'])) {
                $bocs_value = sanitize_text_field($_COOKIE['__bocs_collection_id']);
                if (!empty($bocs_value)) {
                    WC()->session->set('bocs_collection', $bocs_value);
                    $this->log_info('Set collection ID in session from cookie: ' . $bocs_value);
                }
            }

            // Process frequency ID
            if (isset($_GET['frequency'])) {
                $bocs_value = sanitize_text_field($_GET['frequency']);
                if (!empty($bocs_value)) {
                    WC()->session->set('bocs_frequency', $bocs_value);
                    $this->log_info('Set frequency ID in session from URL parameter: ' . $bocs_value);
                }
            } elseif (isset($_COOKIE['__bocs_frequency_id'])) {
                $bocs_value = sanitize_text_field($_COOKIE['__bocs_frequency_id']);
                if (!empty($bocs_value)) {
                    WC()->session->set('bocs_frequency', $bocs_value);
                    $this->log_info('Set frequency ID in session from cookie: ' . $bocs_value);
                }
            }
            
            // Process all additional BOCS cookies into session
            $bocs_cookie_keys = [
                '__bocs_frequency_time_unit' => 'bocs_frequency_time_unit',
                '__bocs_frequency_interval' => 'bocs_frequency_interval',
                '__bocs_frequency_discount' => 'bocs_frequency_discount',
                '__bocs_discount_type' => 'bocs_discount_type',
                '__bocs_total' => 'bocs_total',
                '__bocs_discount' => 'bocs_discount',
                '__bocs_subtotal' => 'bocs_subtotal'
            ];
            
            foreach ($bocs_cookie_keys as $cookie_key => $session_key) {
                if (isset($_COOKIE[$cookie_key])) {
                    $value = sanitize_text_field($_COOKIE[$cookie_key]);
                    if (!empty($value)) {
                        WC()->session->set($session_key, $value);
                        $this->log_info("Set {$session_key} in session from cookie: {$value}");
                    }
                }
            }
            
            // Log session data to confirm values were properly set
            if (WC()->session) {
                $session_data = [];
                $session_keys = [
                    'bocs',
                    'bocs_collection',
                    'bocs_frequency',
                    'bocs_frequency_time_unit',
                    'bocs_frequency_interval',
                    'bocs_frequency_discount',
                    'bocs_discount_type',
                    'bocs_total',
                    'bocs_discount',
                    'bocs_subtotal'
                ];
                
                foreach ($session_keys as $key) {
                    $session_data[$key] = WC()->session->get($key);
                }
                
                $this->log_info('Current WC session data: ' . var_export($session_data, true));
            }
        }
    }

    /**
     * Transfers Bocs-specific data from session/cookies to order meta during order creation
     * 
     * This method is called when a new WooCommerce order is created and handles the transfer
     * of Bocs-specific identifiers from either the WooCommerce session or cookies to the
     * order's meta data.
     *
     * @since 1.0.0
     * @access public
     *
     * @param mixed     $order_id_or_order The order ID or WC_Order object (varies by hook)
     * @param array     $posted_data       The posted data from the checkout form (may be null for Store API)
     * @param WC_Order  $order_obj         The WooCommerce order object (may be null for Store API)
     * 
     * @return mixed The order ID or order object (matches input)
     */
    public function custom_order_created_action($order_id_or_order, $posted_data = null, $order_obj = null) 
    {
        // Determine if this is a Store API request based on arguments provided
        $is_store_api = doing_action('woocommerce_store_api_checkout_order_processed');
        $this->log_info("Detected hook: " . ($is_store_api ? 'woocommerce_store_api_checkout_order_processed' : 'woocommerce_checkout_order_processed'));
        
        // Handle different argument patterns between hooks
        if ($is_store_api && $order_obj === null) {
            // For Store API, we receive the order object directly as the first parameter
            $order = $order_id_or_order;
            $order_id = $order->get_id();
            $this->log_info("Store API checkout: Received order object directly, ID: {$order_id}");
        } else {
            // For standard checkout, we receive order_id, posted_data, order
            $order_id = is_object($order_id_or_order) ? $order_id_or_order->get_id() : $order_id_or_order;
            $order = is_object($order_id_or_order) ? $order_id_or_order : $order_obj;
            $this->log_info("Standard checkout: Using provided order ID: {$order_id}");
        }

        // Add debug logging to track execution
        $this->log_info("Starting custom_order_created_action for order ID: {$order_id}");
        
        // Do an immediate dump of all available BOCS data
        $this->debug_dump_bocs_data();
        
        // Validate and get WooCommerce order object
        if (!is_object($order)) {
            $order = wc_get_order($order_id);
        }
        
        if (!$order) {
            $this->log_error("Invalid order ID: {$order_id}");
            return $order_id_or_order;
        }

        // Transfer BOCS data from session to order meta
        $this->log_info("Transferring BOCS data from session to order meta for order ID: {$order_id}");
        
        // Define mapping of session keys to meta keys
        $session_to_meta_mapping = [
            'bocs' => '__bocs_bocs_id',
            'bocs_collection' => '__bocs_collection_id',
            'bocs_frequency' => '__bocs_frequency_id',
            'bocs_frequency_time_unit' => '__bocs_frequency_time_unit',
            'bocs_frequency_interval' => '__bocs_frequency_interval',
            'bocs_frequency_discount' => '__bocs_frequency_discount',
            'bocs_discount_type' => '__bocs_discount_type',
            'bocs_total' => '__bocs_total',
            'bocs_discount' => '__bocs_discount',
            'bocs_subtotal' => '__bocs_subtotal'
        ];
        
        // Check if WC session is available
        if (isset(WC()->session)) {
            foreach ($session_to_meta_mapping as $session_key => $meta_key) {
                $value = WC()->session->get($session_key);
                if (!empty($value)) {
                    // Only update if the meta doesn't already exist or is empty
                    $existing_value = $order->get_meta($meta_key);
                    if (empty($existing_value)) {
                        $order->update_meta_data($meta_key, $value);
                        $this->log_info("Transferred {$session_key} to {$meta_key}: {$value}");
                    } else {
                        $this->log_info("Meta {$meta_key} already exists with value: {$existing_value}, not overwriting");
                    }
                }
            }
            
            // Save the order to persist the meta changes
            $order->save();
            
            // Clean up session data to prevent it from affecting future orders
            $this->clean_bocs_data();
        } else {
            $this->log_warning("WC session not available, could not transfer BOCS data");
        }

        // Return the order to complete the function
        return $order_id_or_order;
    }

    public function get_related_orders($order_id)
    {
        $order = wc_get_order($order_id);

        if (! $order)
            return [];

        $bocs_subscription_id = $order->get_meta('__bocs_subscription_id', true);

        if (empty($bocs_subscription_id))
            return [];

        error_log('get_related_orders ' . $bocs_subscription_id);

        // Query for orders with the same meta value
        $args = array(
            'limit' => -1,
            'orderby' => 'id',
            'order' => 'DESC',
            'meta_key' => '__bocs_subscription_id',
            'meta_value' => $bocs_subscription_id,
            'meta_compare' => '=',
            'return' => 'objects' // Return order objects
        );

        $query = new WC_Order_Query($args);
        $orders = $query->get_orders();

        // Exclude the current order from the results
        return array_filter($orders, function ($order) use ($order_id) {
            return $order->get_id() != $order_id;
        });
    }

    public function has_related_orders($order_id)
    {
        $related_orders = $this->get_related_orders($order_id);
        $parent_subscription = $this->get_bocs_subscription($order_id);
        return ! empty($related_orders) || ! empty($parent_subscription['data']);
    }

    /**
     * Determines the relationship type between two WooCommerce orders.
     *
     * This method analyzes the relationship between a primary order and a related order
     * in the context of Bocs subscriptions. It determines whether the related order is
     * a renewal, parent, child, or other type of related order.
     *
     * Possible relationship types:
     * - 'Renewal Order': A subsequent order generated from a subscription
     * - 'Parent Order': The original order that started the subscription
     * - 'Related Order': Any other type of relationship
     *
     * @since 1.0.0
     * @access public
     *
     * @param int $order_id          The ID of the primary order being viewed
     * @param int $related_order_id  The ID of the order to determine relationship with
     *
     * @return string The relationship type between the orders
     *
     * @throws Exception If either order ID is invalid or orders cannot be loaded
     *
     * Usage example:
     * ```php
     * $relationship = $admin->get_order_relationship(123, 456);
     * echo "Order #456 is a " . $relationship . " to Order #123";
     * ```
     */
    public function get_order_relationship($order_id, $related_order_id) 
    {
        try {
            // Validate input parameters
            $order_id = absint($order_id);
            $related_order_id = absint($related_order_id);

            if (!$order_id || !$related_order_id) {
                throw new Exception('Invalid order ID provided');
            }

            // Load the orders
            $primary_order = wc_get_order($order_id);
            $related_order = wc_get_order($related_order_id);

            if (!$primary_order || !$related_order) {
                throw new Exception('Unable to load one or both orders');
            }

            // Get subscription IDs for both orders
            $primary_subscription_id = $primary_order->get_meta('__bocs_subscription_id', true);
            $related_subscription_id = $related_order->get_meta('__bocs_subscription_id', true);

            // If they share the same subscription ID, determine the temporal relationship
            if ($primary_subscription_id && $primary_subscription_id === $related_subscription_id) {
                // Compare order dates to determine relationship
                $primary_date = $primary_order->get_date_created();
                $related_date = $related_order->get_date_created();

                if ($primary_date && $related_date) {
                    if ($related_date > $primary_date) {
                        return esc_html__('Renewal Order', 'bocs-wordpress');
                    } else {
                        return esc_html__('Parent Order', 'bocs-wordpress');
                    }
                }
            }

            // Default relationship type if no specific relationship is found
            return esc_html__('Related Order', 'bocs-wordpress');

        } catch (Exception $e) {
            // Log the error but return a safe default
            error_log('Error in get_order_relationship: ' . $e->getMessage());
            return esc_html__('Related Order', 'bocs-wordpress');
        }
    }

    /**
     * Register custom query variables for Bocs
     *
     * @param array $vars Existing query variables
     * @return array Modified query variables
     */
    public function add_bocs_query_vars($vars) 
    {
        $vars[] = 'bocs-view-subscription';
        return $vars;
    }

    /**
     * Sync customer data between WooCommerce and Bocs system.
     *
     * @param WC_Order $order WooCommerce order object.
     * @param array    $headers API authentication headers for Bocs.
     * @return array|false Customer data from Bocs on success, false on failure.
     */
    public function sync_bocs_customer($order, $headers) {
        // Validate headers
        foreach (['organization', 'store', 'authorization'] as $key) {
            if (empty($headers[$key])) {
                error_log("[Bocs][Critical] Missing required API header: $key");
                return false;
            }
        }

        // Construct customer data payload
        $customer_data = [
            'email' => $order->get_billing_email(),
            'firstName' => $order->get_billing_first_name(),
            'lastName' => $order->get_billing_last_name(),
            'phone' => $order->get_billing_phone(),
            'address' => [
                'line1' => $order->get_billing_address_1(),
                'line2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'postcode' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country(),
            ]
        ];

        // Prepare API request
        $url = BOCS_API_URL . 'customers';
        $response = wp_remote_post($url, [
            'headers' => [
                'Organization' => $headers['organization'],
                'Store' => $headers['store'],
                'Authorization' => $headers['authorization'],
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($customer_data),
            'timeout' => 30,
        ]);

        // Handle response
        if (is_wp_error($response)) {
            error_log("[Bocs][ERROR] Failed to sync customer: " . $response->get_error_message());
            return false;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($response_body['data'])) {
            return $response_body['data'];
        }

        error_log("[Bocs][ERROR] Unexpected response from Bocs API: " . wp_remote_retrieve_body($response));
        return false;
    }

    /**
     * Displays a login-related message to users based on URL parameters.
     * 
     * This method checks for a 'login_message' parameter in the URL query string and 
     * displays it as a sanitized message within a styled div container. The message
     * is sanitized using WordPress's sanitize_text_field() function to prevent XSS attacks.
     *
     * @since 0.0.88
     * @access public
     *
     * Usage example:
     * - URL: example.com/login?login_message=Welcome+back
     * Will display: <div class="bocs-login-message"><p class="message">Welcome back</p></div>
     *
     * Security features:
     * - Uses sanitize_text_field() to clean input
     * - Uses esc_html() to escape the output HTML
     * - Only processes messages passed via GET parameter
     *
     * @uses sanitize_text_field() To sanitize the message parameter
     * @uses esc_html() To escape the output HTML
     *
     * @return string HTML formatted message if login_message parameter exists, empty string otherwise
     */
    public function display_bocs_login_message() {
        $result = '';
        if (isset($_GET['login_message'])) {
            $message = sanitize_text_field($_GET['login_message']);
            $result = '<div class="bocs-login-message" style="background: #fff; border-left: 4px solid #00848b; box-shadow: 0 1px 1px 0 rgba(0,0,0,.1); margin: 0 0 20px; padding: 12px;">';
            $result .= '<p class="message" style="margin: 0; color: #000;">' . esc_html($message) . '</p>';
            $result .= '</div>';
        }
        return $result;
    }

    private function render_subscription_row($subscription)
    {
        try {
            $billingInterval = $subscription['billingInterval'] ?? 
                ($subscription['frequency']['frequency'] ?? 0);

            $billingPeriod = $subscription['billingPeriod'] ?? 
                ($subscription['frequency']['timeUnit'] ?? '');

            $billingPeriod = $billingPeriod . 's';

            if ($billingInterval <= 1) {
                $billingPeriod = rtrim($billingPeriod, 's');
                $billingInterval = '';
            }

            $date_started = '';
            if (isset($subscription['startDateGmt'])) {
                $date = new DateTime($subscription['startDateGmt']);
                $date_started = $date->format('F j, Y');
            }

            echo '<tr>';
            echo '<td>' . esc_html($subscription['id']) . '</td>';
            echo '<td>Bocs Subscription</td>';
            echo '<td>' . esc_html($date_started) . '</td>';
            echo '<td>' . esc_html(ucfirst($subscription['subscriptionStatus'])) . '</td>';
            echo '<td>' . esc_html($subscription['total']) . ' every ' . 
                 esc_html(trim($billingInterval . ' ' . $billingPeriod)) . '</td>';
            echo '</tr>';
        } catch (Exception $e) {
            error_log('Critical: Error rendering subscription data: ' . $e->getMessage());
        }
    }

    private function render_order_row($order)
    {
        try {
            if (!$order || !is_a($order, 'WC_Order')) {
                throw new Exception('Invalid order object');
            }

            echo '<tr>';
            echo '<td><a href="' . esc_url(get_edit_post_link($order->get_id())) . '">' . 
                 esc_html($order->get_order_number()) . '</a></td>';
            echo '<td>Renewal Order</td>';
            echo '<td>' . esc_html($order->get_date_created()->date('Y-m-d H:i:s')) . '</td>';
            echo '<td>' . esc_html(wc_get_order_status_name($order->get_status())) . '</td>';
            echo '<td>' . wp_kses_post($order->get_formatted_order_total()) . '</td>';
            echo '</tr>';
        } catch (Exception $e) {
            error_log('Critical: Error rendering order data: ' . $e->getMessage());
        }
    }

    private function handle_api_error($e, $context = '') {
        $message = sprintf(
            /* translators: 1: Error context 2: Error message 3: File name 4: Line number */
            esc_html__('[Bocs][ERROR] %1$s%2$s in %3$s:%4$d', 'bocs-wordpress'),
            $context ? "[$context] " : '',
            $e->getMessage(),
            basename($e->getFile()),
            $e->getLine()
        );
        
        error_log($message);
        
        if (current_user_can('manage_options')) {
            add_action('admin_notices', function() use ($message) {
                printf(
                    '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                    esc_html($message)
                );
            });
        }
        
        return false;
    }

    public function bocs_plugin_create_menu() {
        add_options_page(
            esc_html__('Bocs Plugin Settings', 'bocs-wordpress'),
            esc_html__('Bocs Settings', 'bocs-wordpress'),
            'manage_options',
            'bocs_plugin',
            [$this, 'bocs_render_plugin_settings_page']
        );
    }

    public function bocs_plugin_settings_init() {
        register_setting('bocs_plugin_options', 'bocs_plugin_options', [$this, 'bocs_plugin_options_validate']);

        add_settings_section(
            'bocs_plugin_main',
            esc_html__('API Settings', 'bocs-wordpress'),
            [$this, 'bocs_plugin_section_text'],
            'bocs_plugin'
        );

        add_settings_field(
            'bocs_plugin_headers',
            esc_html__('API Headers', 'bocs-wordpress'),
            [$this, 'bocs_plugin_setting_headers'],
            'bocs_plugin',
            'bocs_plugin_main'
        );
    }

    public function bocs_plugin_setting_headers() {
        $options = get_option('bocs_plugin_options');
        $headers = $options['bocs_headers'] ?? [];
        $fields = [
            'organization' => esc_html__('Organization', 'bocs-wordpress'),
            'store' => esc_html__('Store', 'bocs-wordpress'),
            'authorization' => esc_html__('Authorization', 'bocs-wordpress')
        ];

        foreach ($fields as $key => $label) {
            $value = $headers[$key] ?? '';
            printf(
                '<p><label for="bocs_headers_%1$s">%2$s:</label><br/>
                <input type="text" id="bocs_headers_%1$s" name="bocs_plugin_options[bocs_headers][%1$s]" value="%3$s" class="regular-text" /></p>',
                esc_attr($key),
                esc_html($label),
                esc_attr($value)
            );
        }
    }

    public function add_order_actions($actions, $order) {
        if (!$this->has_subscription($order->get_id())) {
            $actions['bocs_create_subscription'] = [
                'url' => wp_nonce_url(admin_url('admin-ajax.php?action=bocs_create_subscription&order_id=' . $order->get_id()), 'bocs_create_subscription'),
                'name' => esc_html__('Create Bocs Subscription', 'bocs-wordpress'),
                'action' => 'bocs_create_subscription'
            ];
        } else {
            $actions['bocs_cancel_subscription'] = [
                'url' => wp_nonce_url(admin_url('admin-ajax.php?action=bocs_cancel_subscription&order_id=' . $order->get_id()), 'bocs_cancel_subscription'),
                'name' => esc_html__('Cancel Bocs Subscription', 'bocs-wordpress'),
                'action' => 'bocs_cancel_subscription'
            ];
        }
        return $actions;
    }

    public function create_subscription_ajax_callback() {
        check_ajax_referer('bocs_create_subscription');
        
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        
        if (empty($order_id)) {
            wp_die(esc_html__('Invalid order ID', 'bocs-wordpress'));
        }

        $result = $this->create_subscription($order_id);
        
        if ($result) {
            wp_redirect(wp_get_referer() ?: admin_url());
            exit;
        } else {
            wp_die(esc_html__('Failed to create subscription', 'bocs-wordpress'));
        }
    }

    public function cancel_subscription_ajax_callback() {
        check_ajax_referer('bocs_cancel_subscription');
        
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        
        if (empty($order_id)) {
            wp_die(esc_html__('Invalid order ID', 'bocs-wordpress'));
        }

        $result = $this->cancel_subscription($order_id);
        
        if ($result) {
            wp_redirect(wp_get_referer() ?: admin_url());
            exit;
        } else {
            wp_die(esc_html__('Failed to cancel subscription', 'bocs-wordpress'));
        }
    }

    /**
     * Explicitly trigger welcome email after checkout is processed
     */
    public function trigger_welcome_email_after_checkout($order_id, $posted_data, $order) {
        if (!$order_id || !$order) {
            return;
        }
        
        // Check if we've already sent this welcome email
        $already_sent = get_post_meta($order_id, '_bocs_welcome_email_sent', true);
        if ($already_sent === 'yes') {
            return;
        }
        
        // Force load the welcome email class
        if (!class_exists('WC_Bocs_Email_Welcome')) {
            if (defined('BOCS_PLUGIN_DIR') && file_exists(BOCS_PLUGIN_DIR . 'includes/emails/class-bocs-email-welcome.php')) {
                require_once BOCS_PLUGIN_DIR . 'includes/emails/class-bocs-email-welcome.php';
            } else {
                return;
            }
        }
        
        try {
            // Create an instance and trigger the email
            $welcome_email = new WC_Bocs_Email_Welcome();
            
            // Force-enable the email
            $welcome_email->enabled = 'yes';
            
            // Send the email
            $welcome_email->trigger($order_id, $order);
            
            // Mark as sent
            update_post_meta($order_id, '_bocs_welcome_email_sent', 'yes');
        } catch (Exception $e) {
            // Silently continue
        }
    }
    
    /**
     * Trigger welcome email on thank you page as a backup
     */
    public function trigger_welcome_email_on_thankyou($order_id) {
        // Check if we've already sent this welcome email
        $already_sent = get_post_meta($order_id, '_bocs_welcome_email_sent', true);
        if ($already_sent === 'yes') {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Force load the welcome email class
        if (!class_exists('WC_Bocs_Email_Welcome')) {
            if (defined('BOCS_PLUGIN_DIR') && file_exists(BOCS_PLUGIN_DIR . 'includes/emails/class-bocs-email-welcome.php')) {
                require_once BOCS_PLUGIN_DIR . 'includes/emails/class-bocs-email-welcome.php';
            } else {
                return;
            }
        }
        
        try {
            // Create an instance and trigger the email
            $welcome_email = new WC_Bocs_Email_Welcome();
            
            // Force-enable the email
            $welcome_email->enabled = 'yes';
            
            // Send the email
            $welcome_email->trigger($order_id, $order);
            
            // Mark as sent
            update_post_meta($order_id, '_bocs_welcome_email_sent', 'yes');
        } catch (Exception $e) {
            // Silently continue
        }
    }

    /**
     * Cleans up all BOCS-related cookies and session data
     * 
     * This method destroys all BOCS-related cookies and session data after they have been
     * successfully stored in the order meta. This prevents any carryover of data that could
     * affect subsequent transactions.
     *
     * @since 1.0.0
     * @access private
     * 
     * @return void
     */
    private function clean_bocs_data() 
    {
        // Log before cleanup for debugging
        $this->log_info('Starting BOCS data cleanup...');
        
        if (isset($_COOKIE) && !empty($_COOKIE)) {
            $bocs_cookies = array_filter(array_keys($_COOKIE), function($key) {
                return strpos($key, '__bocs') === 0;
            });
            
            if (!empty($bocs_cookies)) {
                $this->log_info('Cookies about to be cleared: ' . implode(', ', $bocs_cookies));
            }
        }
        
        // Define all BOCS cookie keys that need to be cleared
        $bocs_cookie_keys = [
            '__bocs_id',
            '__bocs_collection_id',
            '__bocs_frequency_id',
            '__bocs_frequency_time_unit',
            '__bocs_frequency_interval',
            '__bocs_frequency_discount',
            '__bocs_discount_type',
            '__bocs_total',
            '__bocs_discount',
            '__bocs_subtotal'
        ];
        
        // Get the WordPress cookie path and domain
        $cookie_path = COOKIEPATH;
        $cookie_domain = COOKIE_DOMAIN;
        
        // Clear each cookie by setting it to expire in the past
        foreach ($bocs_cookie_keys as $cookie_key) {
            if (isset($_COOKIE[$cookie_key])) {
                $value = $_COOKIE[$cookie_key]; // Save value for logging
                setcookie($cookie_key, '', time() - 3600, $cookie_path, $cookie_domain);
                unset($_COOKIE[$cookie_key]);
                $this->log_info("Cleared cookie: {$cookie_key} with value: {$value}");
            }
        }
        
        // Clear session data if WooCommerce is active
        if (function_exists('WC') && WC()->session) {
            // Define all session keys to clear
            $session_keys = [
                'bocs',
                'bocs_collection',
                'bocs_frequency',
                'bocs_frequency_time_unit',
                'bocs_frequency_interval',
                'bocs_frequency_discount',
                'bocs_discount_type',
                'bocs_total',
                'bocs_discount',
                'bocs_subtotal'
            ];
            
            // Log session data before clearing
            $session_data = [];
            foreach ($session_keys as $key) {
                $session_data[$key] = WC()->session->get($key);
            }
            $this->log_info('Session data before clearing: ' . var_export($session_data, true));
            
            // Clear each session key
            foreach ($session_keys as $key) {
                $value = WC()->session->get($key); // Save value for logging
                WC()->session->__unset($key);
                $this->log_info("Cleared session key: {$key} with value: " . var_export($value, true));
            }
        }
        
        // Log the cleanup for debugging purposes
        $this->log_info('BOCS cookies and session data cleared after order creation');
    }

    /**
     * Debug method to dump cookie and session data
     * 
     * This will be attached to early WooCommerce checkout hooks to verify
     * what data is available during the checkout process.
     * 
     * @return void
     */
    public function debug_dump_bocs_data() {
        // Only run on checkout pages
        if (!is_checkout()) {
            return;
        }
        
        $this->log_info('========== DEBUG: BOCS DATA DUMP ==========');
        
        // Dump all cookies
        $this->log_info('All cookies: ' . var_export($_COOKIE, true));
        
        // Extract just BOCS cookies for easier reading
        $bocs_cookies = array_filter($_COOKIE, function($key) {
            return strpos($key, '__bocs') === 0;
        }, ARRAY_FILTER_USE_KEY);
        
        $this->log_info('BOCS cookies: ' . var_export($bocs_cookies, true));
        
        // Dump session if available
        if (function_exists('WC') && WC()->session) {
            $session_keys = [
                'bocs',
                'bocs_collection',
                'bocs_frequency',
                'bocs_frequency_time_unit',
                'bocs_frequency_interval',
                'bocs_frequency_discount',
                'bocs_discount_type',
                'bocs_total',
                'bocs_discount',
                'bocs_subtotal'
            ];
            
            $session_data = [];
            foreach ($session_keys as $key) {
                $session_data[$key] = WC()->session->get($key);
            }
            
            $this->log_info('WC Session data: ' . var_export($session_data, true));
        } else {
            $this->log_warning('WC Session not available for debugging');
        }
        
        $this->log_info('========== END DEBUG DUMP ==========');
    }

    /**
     * Get subscription details from the BOCS API
     * 
     * @param int $order_id The order ID
     * @return array|false Subscription data or false on failure
     */
    public function get_bocs_subscription($order_id)
    {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }

        $bocs_subscription_id = $order->get_meta('__bocs_subscription_id', true);

        if (empty($bocs_subscription_id)) {
            return false;
        }

        // get the details of the bocs subscription
        $helper = new Bocs_Helper();
        $url = BOCS_API_URL . 'subscriptions/' . $bocs_subscription_id;
        
        // Initialize retry counter
        $max_retries = 3;
        $retry_count = 0;
        $retry_delay = 1; // Initial delay in seconds
        
        while ($retry_count < $max_retries) {
            try {
                $subscription = $helper->curl_request($url, 'GET', NULL, $this->headers);

                // Check if the response is a WP_Error
                if (is_wp_error($subscription)) {
                    throw new Exception($subscription->get_error_message());
                }

                // Check if response is not an array or missing required data
                if (!is_array($subscription) || !isset($subscription['data'])) {
                    throw new Exception('Invalid response format from API');
                }

                // Check for non-200 response code if it exists
                if (isset($subscription['response']) && $subscription['response'] !== 200) {
                    throw new Exception('API returned non-200 response code: ' . $subscription['response']);
                }

                return $subscription;

            } catch (Exception $e) {
                $retry_count++;
                error_log('Critical: API Error: ' . $e->getMessage());

                // If we haven't reached max retries, wait before trying again
                if ($retry_count < $max_retries) {
                    sleep($retry_delay);
                    $retry_delay *= 2; // Exponential backoff
                    continue;
                }
            }
        }

        return false;
    }

    /**
     * Get BOCS data from the API
     * 
     * @param string $bocs_id The BOCS ID
     * @return array|false The BOCS data or false on failure
     */
    public function get_bocs_data_from_api($bocs_id) {
        if (empty($bocs_id)) {
            error_log("[Bocs][WARNING] Cannot fetch BOCS data: Empty BOCS ID");
            return false;
        }
        
        // Get API credentials
        $options = get_option('bocs_plugin_options', array());
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();
        
        $headers = array(
            'organization' => $options['bocs_headers']['organization'] ?? '',
            'store' => $options['bocs_headers']['store'] ?? '',
            'authorization' => $options['bocs_headers']['authorization'] ?? '',
            'Content-Type' => 'application/json'
        );
        
        // Validate headers
        foreach (['organization', 'store', 'authorization'] as $key) {
            if (empty($headers[$key])) {
                error_log("[Bocs][ERROR] Missing required API header: {$key}");
                return false;
            }
        }
        
        // Construct API URL
        $url = BOCS_API_URL . 'bocs/' . $bocs_id;
        error_log("[Bocs][INFO] Fetching BOCS data from: {$url}");
        
        // Initialize Bocs_Helper
        $helper = new Bocs_Helper();
        
        // Make request with retry logic
        $max_retries = 3;
        $retry_count = 0;
        $retry_delay = 1; // Initial delay in seconds
        
        while ($retry_count < $max_retries) {
            try {
                $response = $helper->curl_request($url, 'GET', null, $headers);
                
                // Check for errors
                if (is_wp_error($response)) {
                    throw new Exception($response->get_error_message());
                }
                
                // Validate response format
                if (!is_array($response) || !isset($response['data'])) {
                    throw new Exception('Invalid API response format');
                }
                
                // Check response code
                if (isset($response['response']) && $response['response'] !== 200) {
                    throw new Exception('API returned non-200 response code: ' . $response['response']);
                }
                
                error_log("[Bocs][INFO] Successfully fetched BOCS data for ID: {$bocs_id}");
                return $response;
                
            } catch (Exception $e) {
                $retry_count++;
                error_log("[Bocs][ERROR] Error fetching BOCS data (attempt {$retry_count}): " . $e->getMessage());
                
                // If we haven't reached max retries, wait before trying again
                if ($retry_count < $max_retries) {
                    sleep($retry_delay);
                    $retry_delay *= 2; // Exponential backoff
                    continue;
                }
            }
        }
        
        error_log("[Bocs][ERROR] Failed to fetch BOCS data after {$max_retries} attempts");
        return false;
    }

    /**
     * Log an informational message to the BOCS logs
     * Only logs when BOCS_ENVIRONMENT is not 'prod'
     *
     * @param string $message The message to log
     * @param array  $context Optional. Additional contextual data
     * @return void
     */
    private function log_info($message, $context = [])
    {
        // Only log info messages when not in production
        if (!defined('BOCS_ENVIRONMENT') || BOCS_ENVIRONMENT !== 'prod') {
            if ($this->logger) {
                $this->logger->insert_log('info', $message, $context);
            }
        }
    }
    
    /**
     * Log a debug message to the BOCS logs
     * Only logs when BOCS_ENVIRONMENT is not 'prod'
     *
     * @param string $message The message to log
     * @param array  $context Optional. Additional contextual data
     * @return void
     */
    private function log_debug($message, $context = [])
    {
        // Only log debug messages when not in production
        if (!defined('BOCS_ENVIRONMENT') || BOCS_ENVIRONMENT !== 'prod') {
            if ($this->logger) {
                $this->logger->insert_log('debug', $message, $context);
            }
        }
    }
    
    /**
     * Log an error message to the BOCS logs
     * Always logs regardless of environment
     *
     * @param string $message The message to log
     * @param array  $context Optional. Additional contextual data
     * @return void
     */
    private function log_error($message, $context = [])
    {
        // Always log errors in all environments
        if ($this->logger) {
            $this->logger->insert_log('error', $message, $context);
        }
    }

    /**
     * Log a warning message to the BOCS logs
     * Always logs regardless of environment
     *
     * @param string $message The message to log
     * @param array  $context Optional. Additional contextual data
     * @return void
     */
    private function log_warning($message, $context = [])
    {
        // Always log warnings in all environments
        if ($this->logger) {
            $this->logger->insert_log('warning', $message, $context);
        }
    }

    /**
     * Add a meta box to show related orders on the order edit screen
     * 
     * @since 1.0.0
     * @param string $post_type The post type or screen ID
     * @param object|null $post The post object (optional)
     */
    public function show_related_orders($post_type, $post = null) {
        // Early bail if not on an order screen
        if ($post_type !== 'woocommerce_page_wc-orders' && $post_type !== 'shop_order') {
            return;
        }
        
        // Special handling for the WooCommerce Admin/HPOS where post is null
        if (null === $post) {
            // When on the orders page, the current order ID should be in $_GET['id']
            $order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            
            if ($order_id <= 0) {
                return; // No order ID to work with
            }
            
            // Try to get the order object
            $post = wc_get_order($order_id);
            
            if (!$post) {
                return; // Order not found
            }
        }
        
        // Get order ID - handle both post object and WC_Order object
        $order_id = is_a($post, 'WP_Post') ? $post->ID : $post->get_id();
        
        // Check if this order has related orders
        if ($this->has_related_orders($order_id)) {
            add_meta_box(
                'bocs_related_orders',
                __('BOCS Related Orders', 'bocs-wordpress'),
                array($this, 'render_related_orders_meta_box'),
                $post_type,
                'side',
                'default',
                array('order_id' => $order_id)
            );
        }
    }
    
    /**
     * Render the related orders meta box content
     * 
     * @since 1.0.0
     * @param WP_Post|WC_Order $post The post or order object
     * @param array $metabox Metabox arguments including the order_id in args
     */
    public function render_related_orders_meta_box($post, $metabox) {
        $order_id = $metabox['args']['order_id'] ?? 0;
        if (!$order_id) {
            echo '<p>' . __('No order ID provided.', 'bocs-wordpress') . '</p>';
            return;
        }
        
        $related_orders = $this->get_related_orders($order_id);
        
        if (empty($related_orders)) {
            echo '<p>' . __('No related orders found.', 'bocs-wordpress') . '</p>';
            return;
        }
        
        echo '<ul class="bocs-related-orders-list">';
        
        foreach ($related_orders as $related_order_obj) {
            if (!$related_order_obj) {
                continue;
            }
            
            $edit_url = admin_url('admin.php?page=wc-orders&action=edit&id=' . $related_order_obj->get_id());
            $order_number = $related_order_obj->get_order_number();
            $relationship = $this->get_order_relationship($order_id, $related_order_obj->get_id());
            $date = $related_order_obj->get_date_created() ? $related_order_obj->get_date_created()->date_i18n(get_option('date_format') . ' ' . get_option('time_format')) : '';
            $status = wc_get_order_status_name($related_order_obj->get_status());
            
            echo '<li>';
            echo '<a href="' . esc_url($edit_url) . '">#' . esc_html($order_number) . '</a> - ';
            echo '<span class="bocs-order-relationship">' . esc_html($relationship) . '</span><br>';
            echo '<small>' . esc_html($date) . ' - ' . esc_html($status) . '</small>';
            echo '</li>';
        }
        
        echo '</ul>';
        
        // Add some basic styling
        echo '<style>
            .bocs-related-orders-list {
                margin: 0;
                padding: 0;
            }
            .bocs-related-orders-list li {
                border-bottom: 1px solid #eee;
                padding: 8px 0;
                margin: 0;
            }
            .bocs-related-orders-list li:last-child {
                border-bottom: none;
            }
            .bocs-order-relationship {
                text-transform: capitalize;
                color: #777;
            }
        </style>';
    }

    /**
     * Check and sync user ID with BOCS on login
     * 
     * @param string $user_login Username
     * @param WP_User $user User object
     */
    public function bocs_user_id_check($user_login, $user) {
        if (!$user || !$user->ID) {
            $this->log_warning('Cannot check BOCS user ID: Invalid user', [
                'user_login' => $user_login
            ]);
            return;
        }
        
        $user_id = $user->ID;
        $bocs_id = get_user_meta($user_id, 'bocs_user_id', true);
        
        if (empty($bocs_id)) {
            $this->log_debug('No BOCS ID found for user during login check', [
                'user_id' => $user_id,
                'user_login' => $user_login
            ]);
            return;
        }
        
        // If we have a BOCS ID, we could sync additional data here if needed
        $this->log_debug('User logged in with BOCS ID', [
            'user_id' => $user_id,
            'bocs_id' => $bocs_id,
            'user_login' => $user_login
        ]);
    }
}

