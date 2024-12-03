<?php

use function Loader\add_filter;

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

    public function __construct()
    {
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
            '20241126.20'         // Version number for cache busting
        );

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

        // Get collections and widgets data (currently unused but available)
        $bocs_collections = get_option("bocs_collections");
        $bocs_widgets = get_option("bocs_widgets");

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

        global $wp;

        // this to make sure that the keys were added or updated
        $bocs = new Bocs();
        $bocs->auto_add_bocs_keys();

        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();

        // Get the appropriate widget URL based on developer mode
        $widget_url = BOCS_ENVIRONMENT === 'dev' 
            ? "https://dev.widget.v2.bocs.io/script/index.js"
            : "https://widget.v2.bocs.io/script/index.js";

        wp_enqueue_script(
            "bocs-widget-script", 
            $widget_url, 
            array(), 
            '20241203.0', 
            true
        );

        if (class_exists('woocommerce')) {
            wp_enqueue_script('wc-add-to-cart');
            wp_enqueue_script('wc-cart-fragments');
        }

        $redirect = wc_get_checkout_url();
        $cart_nonce = wp_create_nonce('wc_store_api');

        wp_enqueue_script("bocs-add-to-cart", plugin_dir_url(__FILE__) . '../assets/js/add-to-cart.js', array(
            'jquery',
            'bocs-widget-script'
        ), '2024.11.15.6', true);

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
            'couponNonce' => wp_create_nonce('ajax-create-coupon-nonce')
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

        if (empty($frequency_id) && isset(WC()->session)) {
            $bocs_value = WC()->session->get('bocs_frequency');

            if (empty($bocs_value)) {
                if (isset($_COOKIE['__bocs_frequency_id'])) {
                    $bocs_value = sanitize_text_field($_COOKIE['__bocs_frequency_id']);
                }
            }

            if (! empty($bocs_value)) {
                $frequency_id = $bocs_value;
            }
        }

        $current_frequency = null;
        $bocs_body = $this->get_bocs_data_from_api($bocs_id);

        if (is_checkout()) {
            // checks the stripe checkbox and make it checked as default
            wp_enqueue_script('bocs-stripe-checkout-js', plugin_dir_url(__FILE__) . '../assets/js/custom-stripe-checkout.js', array(
                'jquery'
            ), '20240611.8', true);

            wp_enqueue_script('bocs-checkout-js', plugin_dir_url(__FILE__) . '../assets/js/bocs-checkout.js', array(
                'jquery'
            ), '20241105.1', true);

            wp_localize_script('bocs-checkout-js', 'bocsCheckoutObject', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ajax-nonce'),
                'storeId' => $options['bocs_headers']['store'] ?? '',
                'orgId' => $options['bocs_headers']['organization'] ?? '',
                'authId' => $options['bocs_headers']['authorization'] ?? '',
                'frequency' => $current_frequency,
                'bocs' => $bocs_body['data']
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
                $bocs_conversion_total = 0;
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
                                    "externalSourceId" => $product_id,
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

            wp_enqueue_script('bocs-cart-js', plugin_dir_url(__FILE__) . '../assets/js/bocs-cart.js', array(
                'jquery'
            ), '20240705.1', true);

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
        // add_submenu_page("bocs", "Sync Store", "Sync Store", "manage_options", 'bocs-sync-store', [$this, 'bocs_sync_store_page'] );
        // add_submenu_page("bocs", "Error Logs", "Error Logs", "manage_options", 'bocs-error-logs', [$this, 'bocs_error_logs_page'] );

        remove_submenu_page('bocs', 'bocs');
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
        <h2>Bocs Settings</h2>
        <form action="options.php" method="post">
            <?php
            settings_fields('bocs_plugin_options');
            do_settings_sections('bocs_plugin');
            ?>
            <input name="submit" class="button button-primary" type="submit" value="<?php

                                                                                    esc_attr_e('Save');
                                                                                    ?>" />
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
        echo '<p>Here you can set all the options for using the API</p>';
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
        if (isset($newinput['api_key'])) {
            $newinput['api_key'] = trim($input['api_key']);
            if (! preg_match('/^[-a-z0-9]{36}$/i', $newinput['api_key'])) {
                $newinput['api_key'] = '';
            }
        }

        $newinput['sync_contacts_to_bocs'] = trim($input['sync_contacts_to_bocs']) == '1' ? 1 : 0;
        // $newinput['sync_contacts_from_bocs'] = trim( $input['sync_contacts_from_bocs'] ) == '1' ? 1 : 0;

        $newinput['sync_daily_contacts_to_bocs'] = trim($input['sync_daily_contacts_to_bocs']) == '1' ? 1 : 0;
        $newinput['sync_daily_contacts_from_bocs'] = trim($input['sync_daily_contacts_from_bocs']) == '1' ? 1 : 0;

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
            die('Invalid nonce');
        }

        // Get the product data from the AJAX request
        $product_title = $_POST['title'];
        $product_price = $_POST['price'];
        $product_sku = isset($_POST['sku']) ? $_POST['sku'] : '';
        $product_type = isset($_POST['type']) ? $_POST['type'] : "product";
        $bocs_product_id = isset($_POST['bocs_product_id']) ? $_POST['bocs_product_id'] : "";
        $parent_id = isset($_POST['parent_id']) ? $_POST['parent_id'] : 0;
        $variation_attributes = array();

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
     * Process WooCommerce order when status changes to processing and create corresponding Bocs subscription
     * 
     * This method handles the creation of a subscription in the Bocs system when a WooCommerce order
     * transitions to "processing" status. It performs the following key operations:
     * 
     * 1. Validates API credentials and order data
     * 2. Retrieves order details and subscription parameters
     * 3. Synchronizes customer data between WooCommerce and Bocs
     * 4. Creates subscription with line items, billing/shipping info
     * 5. Handles frequency and payment scheduling
     * 
     * Logging Levels:
     * - DEBUG: Detailed flow information and data states
     * - INFO: Successful operations and state transitions
     * - WARNING: Non-critical issues that might need attention
     * - ERROR: Critical failures that prevent subscription creation
     *
     * @param int $order_id WooCommerce order ID to process
     * @return bool|void False on validation failure, void on success/error
     * @throws Exception When critical API operations fail
     * 
     * @since 1.0.0
     */
    public function bocs_order_status_processing($order_id = 0) 
    {
        // Initialize logging context
        $log_context = ['order_id' => $order_id];
        
        // SECTION: Initial Validation
        // Validate required credentials and order data
        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();

        $required_headers = ['organization', 'store', 'authorization'];
        foreach ($required_headers as $header) {
            if (empty($options['bocs_headers'][$header])) {
                error_log("ERROR: Missing required Bocs header: {$header}");
                return false;
            }
        }

        if (empty($order_id)) {
            error_log("ERROR: Invalid order ID provided");
            return false;
        }

        // SECTION: Order Data Retrieval
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("ERROR: Order not found for ID: {$order_id}");
            return false;
        }

        // Debug logging of order totals
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("DEBUG: Order totals - Total: {$order->get_total()}, Subtotal: {$order->get_subtotal()}, Discount: {$order->get_discount_total()}");
        }

        // SECTION: Subscription Parameters
        // Initialize subscription parameters with defaults
        $subscription_params = [
            'interval' => 'month',
            'interval_count' => 1,
            'items' => [],
            'line_items' => []
        ];

        // SECTION: Bocs Data Collection
        // Retrieve Bocs-specific identifiers from order meta or session
        $bocs_identifiers = $this->get_bocs_identifiers($order);
        
        // Early return if not a Bocs order
        if (!$bocs_identifiers['is_bocs']) {
            error_log("INFO: Not a Bocs order, skipping subscription creation");
            return;
        }

        // SECTION: Customer Synchronization
        $customer_data = $this->sync_bocs_customer($order, $options['bocs_headers']);
        if (!$customer_data) {
            error_log("ERROR: Failed to sync customer data with Bocs");
            return false;
        }

        // SECTION: Subscription Creation
        try {
            $subscription_data = $this->prepare_subscription_data(
                $order,
                $bocs_identifiers,
                $customer_data,
                $subscription_params
            );
            
            $response = $this->create_bocs_subscription($subscription_data, $options['bocs_headers']);
            
            if ($response['success']) {
                error_log("INFO: Successfully created Bocs subscription for order {$order_id}");
                $order->add_order_note("Bocs subscription created successfully");
            } else {
                error_log("ERROR: Failed to create Bocs subscription: " . $response['message']);
                $order->add_order_note("Failed to create Bocs subscription: " . $response['message']);
            }
            
        } catch (Exception $e) {
            error_log("ERROR: Exception during subscription creation: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Helper method to retrieve Bocs identifiers from various sources
     * 
     * @param WC_Order $order
     * @return array
     */
    private function get_bocs_identifiers($order) {
        $identifiers = [
            'bocs_id' => $order->get_meta('__bocs_bocs_id'),
            'collection_id' => $order->get_meta('__bocs_collections_id'),
            'frequency_id' => $order->get_meta('__bocs_frequency_id'),
            'is_bocs' => false
        ];

        // Try to get from session if not in meta
        if (isset(WC()->session)) {
            foreach (['bocs' => 'bocs_id', 
                     'bocs_collection' => 'collection_id',
                     'bocs_frequency' => 'frequency_id'] as $session_key => $id_key) {
                if (empty($identifiers[$id_key])) {
                    $identifiers[$id_key] = $this->get_from_session_or_cookie($session_key);
                }
            }
        }

        $identifiers['is_bocs'] = !empty($identifiers['bocs_id']);
        return $identifiers;
    }

    /**
     * Helper method to get value from session or cookie
     * 
     * @param string $key
     * @return string|null
     */
    private function get_from_session_or_cookie($key) {
        $value = WC()->session->get($key);
        if (empty($value) && isset($_COOKIE["__${key}_id"])) {
            $value = sanitize_text_field($_COOKIE["__${key}_id"]);
        }
        return $value;
    }

    /**
     * Prepare subscription data for API request
     * 
     * @param WC_Order $order WooCommerce order object
     * @param array $identifiers Bocs identifiers (bocs_id, collection_id, frequency_id)
     * @param array $customer_data Customer information from Bocs
     * @param array $params Additional subscription parameters
     * @return array Formatted subscription data for API request
     */
    private function prepare_subscription_data($order, $identifiers, $customer_data, $params) {
        // Basic subscription details
        $subscription_data = [
            'bocsId' => $identifiers['bocs_id'],
            'collectionId' => $identifiers['collection_id'],
            'frequencyId' => $identifiers['frequency_id'],
            'customerId' => $customer_data['id'],
            'status' => 'active',
            'orderNumber' => $order->get_order_number(),
            'orderExternalId' => $order->get_id(),
            'currency' => $order->get_currency(),
            
            // Payment details
            'paymentMethod' => $order->get_payment_method(),
            'paymentMethodTitle' => $order->get_payment_method_title(),
            'transactionId' => $order->get_transaction_id(),
            
            // Totals
            'subtotal' => $order->get_subtotal(),
            'total' => $order->get_total(),
            'taxTotal' => $order->get_total_tax(),
            'shippingTotal' => $order->get_shipping_total(),
            'discountTotal' => $order->get_discount_total(),
            
            // Addresses
            'billingAddress' => [
                'firstName' => $order->get_billing_first_name(),
                'lastName' => $order->get_billing_last_name(),
                'company' => $order->get_billing_company(),
                'address1' => $order->get_billing_address_1(),
                'address2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'postcode' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone()
            ],
            'shippingAddress' => [
                'firstName' => $order->get_shipping_first_name(),
                'lastName' => $order->get_shipping_last_name(),
                'company' => $order->get_shipping_company(),
                'address1' => $order->get_shipping_address_1(),
                'address2' => $order->get_shipping_address_2(),
                'city' => $order->get_shipping_city(),
                'state' => $order->get_shipping_state(),
                'postcode' => $order->get_shipping_postcode(),
                'country' => $order->get_shipping_country()
            ],
            
            // Line items
            'items' => []
        ];

        // Add line items
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;

            $subscription_data['items'][] = [
                'productId' => $product->get_id(),
                'name' => $item->get_name(),
                'sku' => $product->get_sku(),
                'quantity' => $item->get_quantity(),
                'price' => $item->get_total() / $item->get_quantity(), // Unit price
                'total' => $item->get_total(),
                'taxTotal' => $item->get_total_tax(),
                'metadata' => [
                    'productType' => $product->get_type(),
                    'taxClass' => $item->get_tax_class(),
                    'taxStatus' => $product->get_tax_status()
                ]
            ];
        }

        // Add applied coupons
        if ($order->get_coupon_codes()) {
            $subscription_data['coupons'] = array_map(function($code) {
                return ['code' => $code];
            }, $order->get_coupon_codes());
        }

        // Add any additional metadata
        $subscription_data['metadata'] = [
            'source' => 'woocommerce',
            'sourceVersion' => WC()->version,
            'orderCreatedDate' => $order->get_date_created()->format('c'),
            'customerNote' => $order->get_customer_note()
        ];

        return $subscription_data;
    }

    /**
     * Create subscription via Bocs API
     * 
     * @param array $subscription_data Formatted subscription data
     * @param array $headers API authentication headers
     * @return array Response with success status and message
     */
    private function create_bocs_subscription($subscription_data, $headers) {
        try {
            // Initialize cURL request
            $curl = curl_init();
            if (!$curl) {
                throw new Exception('Failed to initialize cURL');
            }

            // Set up the API endpoint
            $api_url = BOCS_API_URL . 'subscriptions';

            // Configure cURL options
            curl_setopt_array($curl, [
                CURLOPT_URL => $api_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($subscription_data),
                CURLOPT_HTTPHEADER => [
                    'Organization: ' . $headers['organization'],
                    'Store: ' . $headers['store'], 
                    'Authorization: ' . $headers['authorization'],
                    'Content-Type: application/json'
                ]
            ]);

            // Execute request and get response
            $response = curl_exec($curl);
            $err = curl_error($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            // Clean up
            curl_close($curl);

            // Handle errors
            if ($err) {
                throw new Exception('cURL Error: ' . $err);
            }

            // Parse response
            $result = json_decode($response, true);
            if (!$result) {
                throw new Exception('Failed to parse API response');
            }

            // Check for successful response (usually 201 Created)
            if ($http_code !== 201 && $http_code !== 200) {
                $error_message = isset($result['message']) ? $result['message'] : 'Unknown error';
                throw new Exception('API Error: ' . $error_message);
            }

            // Return success response
            return [
                'success' => true,
                'data' => $result,
                'message' => 'Subscription created successfully'
            ];

        } catch (Exception $e) {
            // Log the error
            error_log('[Bocs][ERROR] Failed to create subscription: ' . $e->getMessage());
            
            // Return error response
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function search_product_ajax_callback(){

        // Verify the AJAX nonce
        $nonce = $_POST['nonce'];

        if (! wp_verify_nonce($nonce, 'ajax-search-nonce')) {
            die('Invalid nonce');
        }

        $product_id = 0;

        $name = isset($_POST['name']) ? $_POST['name'] : '';
        $bocs_frequency_id = isset($_POST['bocs_frequency_id']) ? $_POST['bocs_frequency_id'] : 0;
        $bocs_bocs_id = isset($_POST['bocs_bocs_id']) ? $_POST['bocs_bocs_id'] : 0;
        $bocs_sku = isset($_POST['bocs_sku']) ? $_POST['bocs_sku'] : 0;
        $is_bocs = isset($_POST['is_bocs']) ? $_POST['is_bocs'] : 0;
        $bocs_product_id = isset($_POST['bocs_product_id']) ? $_POST['bocs_product_id'] : '';
        $bocs_product_type = 'bocs_bocs_id';

        // first we need to search by sku and frequency id
        global $wpdb;

        if ($bocs_bocs_id !== 0) {
            $bocs_product_type = 'bocs_bocs_id';
        } else if ($bocs_product_id !== '') {
            $bocs_product_type = 'bocs_product_id';
        }

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

        // @TODO appending the source on the url
        $current_source = isset($_GET['source']) ? $_GET['source'] : '';

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
     * Display the Bocs Widget Metabox content.
     *
     * @param WP_Post $page The current page object.
     * @return void
     */
    public function bocs_widget_metabox_content($page)
    {
        // Output the HTML structure for the metabox.
        echo "<div id='bocs-page-sidebar'>
            <!-- Label and select dropdown for Collections -->
            <label for='bocs-page-sidebar-collections'>Collections</label><br /> 
            <select id='bocs-page-sidebar-collections' name='collections'></select><br />

            <!-- Label and select dropdown for Widget -->
            <br /><label for='bocs-page-sidebar-widgets'>Widget</label><br />
            <select id='bocs-page-sidebar-widgets' name='widgets'></select><br />

            <!-- Code output section -->
            <br />
            <label><b>Copy the shortcode below</b></label><br />
            <code id='bocs-shortcode-copy'></code>
          </div>";
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

    public function add_cart_item_meta_to_order_items($item, $cart_item_key, $values, $order)
    {
        $__bocs_bocs_id = '';
        $__bocs_collections_id = '';

        // Check if cart item has meta data
        if (isset($values['meta_data']) && ! empty($values['meta_data'])) {
            foreach ($values['meta_data'] as $meta) {

                if ($meta->key == '__bocs_bocs_id' && $__bocs_bocs_id == '') {
                    $__bocs_bocs_id = trim($meta->value);
                }

                if ($meta->key == '__bocs_collections_id' && $__bocs_collections_id == '') {
                    $__bocs_collections_id = trim($meta->value);
                }

                if ($__bocs_bocs_id != '' && $__bocs_collections_id != '') {
                    break;
                }
            }
        }

        if ($__bocs_bocs_id != '') {
            $order->update_meta_data('__bocs_bocs_id', $__bocs_bocs_id);
        }

        if ($__bocs_collections_id != '') {
            $order->update_meta_data('__bocs_collections_id', $__bocs_collections_id);
        }
    }

    public function add_custom_cart_item_data($cart_item_data, $product_id, $variation_id)
    {
        return $cart_item_data;
    }

    public function add_custom_to_cart_data($add_to_cart_data, $request)
    {
        if (! empty($request['meta_data'])) {
            $add_to_cart_data['meta_data'] = sanitize_text_field($request['meta_data']);
        }

        return $add_to_cart_data;
    }

    public function capture_bocs_parameter()
    {
        if (is_cart() || is_checkout()) {

            if (isset($_GET['bocs'])) {
                $bocs_value = sanitize_text_field($_GET['bocs']);
                if (! empty($bocs_value))
                    WC()->session->set('bocs', $bocs_value);
            } else {
                // check the cookie
                if (isset($_COOKIE['__bocs_id'])) {
                    $bocs_value = sanitize_text_field($_COOKIE['__bocs_id']);
                    if (! empty($bocs_value)) {
                        WC()->session->set('bocs', $bocs_value);
                    }
                }
            }

            if (isset($_GET['collection'])) {
                $bocs_value = sanitize_text_field($_GET['collection']);
                if (! empty($bocs_value))
                    WC()->session->set('bocs_collection', $bocs_value);
            } else {
                // check the cookie
                if (isset($_COOKIE['__bocs_collection_id'])) {
                    $bocs_value = sanitize_text_field($_COOKIE['__bocs_collection_id']);
                    if (! empty($bocs_value)) {
                        WC()->session->set('bocs_collection', $bocs_value);
                    }
                }
            }

            if (isset($_GET['frequency'])) {
                $bocs_value = sanitize_text_field($_GET['frequency']);
                if (! empty($bocs_value))
                    WC()->session->set('bocs_frequency', $bocs_value);
            } else {
                // check the cookie
                if (isset($_COOKIE['__bocs_frequency_id'])) {
                    $bocs_value = sanitize_text_field($_COOKIE['__bocs_frequency_id']);
                    if (! empty($bocs_value)) {
                        WC()->session->set('bocs_frequency', $bocs_value);
                    }
                }
            }
        }
    }

    public function custom_order_created_action($order_id, $posted_data, $order)
    {
        // Get the order object
        $order = wc_get_order($order_id);

        // Example: Add a custom order meta

        $bocs_value = WC()->session->get('bocs');

        if (empty($bocs_value)) {
            if (isset($_COOKIE['__bocs_id'])) {
                $bocs_value = sanitize_text_field($_COOKIE['__bocs_id']);
            }
        }

        if ($bocs_value) {
            $order->update_meta_data('__bocs_bocs_id', $bocs_value);
        }

        $bocs_value = WC()->session->get('bocs_collection');

        if (empty($bocs_value)) {
            if (isset($_COOKIE['__bocs_collection_id'])) {
                $bocs_value = sanitize_text_field($_COOKIE['__bocs_collection_id']);
            }
        }

        if ($bocs_value) {

            $order->update_meta_data('__bocs_collections_id', $bocs_value);
        }

        $bocs_value = WC()->session->get('bocs_frequency');

        if (empty($bocs_value)) {
            if (isset($_COOKIE['__bocs_frequency_id'])) {
                $bocs_value = sanitize_text_field($_COOKIE['__bocs_frequency_id']);
            }
        }

        if ($bocs_value) {

            $order->update_meta_data('__bocs_frequency_id', $bocs_value);
        }

        // Save the order to ensure meta data is saved
        $order->save();
    }

    /**
     * Fetches BOCS data from the API for a given ID.
     *
     * This method constructs a URL using the provided ID and sends a GET request
     * to the BOCS API to retrieve widget data. The request is made using the
     * Bocs_Helper class, which handles the cURL request.
     *
     * @param string $id The ID of the BOCS widget to fetch data for.
     * @return array The data returned from the BOCS API.
     */
    public function get_bocs_data_from_api($id)
    {
        $url = BOCS_LIST_WIDGETS_URL . $id;
        $bocs_helper = new Bocs_Helper();
        $widgets_data = $bocs_helper->curl_request($url, 'GET', [], $this->headers);
        return $widgets_data;
    }

    public function get_order_data_as_json($order_id)
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
            'total' => $order->get_total(),
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
                'total' => $item->get_total(),
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
                'total' => $shipping_item->get_total(),
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
                'total' => $fee_item->get_total(),
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
                'total' => $refund->get_amount()
            );
        }

        // Encode the data to JSON and return
        return json_encode($order_data, JSON_PRETTY_PRINT);
    }

    /**
     * Checks and synchronizes user IDs between WordPress and Bocs systems.
     * 
     * This method is triggered when a user logs in and performs the following:
     * 1. Validates required Bocs API credentials
     * 2. Checks if the user already has a Bocs ID in WordPress
     * 3. If no Bocs ID exists, queries the Bocs API to find matching user
     * 4. Updates WordPress user meta with Bocs ID if found
     *
     * API Response Structure:
     * The method expects one of two possible response structures from Bocs API:
     * - object->data->data[]: Paginated response format
     * - object->data[]: Direct array response format
     *
     * Error Handling:
     * - Validates API credentials before making request
     * - Handles cURL initialization and execution errors
     * - Validates HTTP response codes
     * - Handles JSON parsing errors
     * - Logs all errors and optionally displays admin notices
     *
     * @param string   $user_login WordPress username of the logging in user
     * @param WP_User  $user       WordPress user object containing user data
     * 
     * @throws Exception On API communication or data processing errors
     * 
     * @return void
     *
     * @since 1.0.0
     * @access public
     *
     * @uses get_option()           To retrieve plugin settings
     * @uses get_user_meta()        To check existing Bocs ID
     * @uses update_user_meta()     To store Bocs ID
     * @uses curl_init()            To initialize API request
     * @uses curl_setopt_array()    To configure API request
     * @uses curl_exec()            To execute API request
     * @uses error_log()            To log processing information and errors
     *
     * @example
     * // The method is typically hooked to WordPress login action
     * add_action('wp_login', array($this, 'bocs_user_id_check'), 10, 2);
     *
     * Meta Keys Used:
     * - bocs_user_id: Stores the Bocs system user ID
     */
    public function bocs_user_id_check($user_login, $user) 
    {
        if (!$user instanceof WP_User) {
            error_log('[Bocs][ERROR] Invalid user object provided');
            return;
        }

        try {
            // Rate limiting to prevent API abuse
            $rate_limit_key = 'bocs_api_check_' . $user->ID;
            if (get_transient($rate_limit_key)) {
                error_log('[Bocs][INFO] Rate limit hit for user ' . $user->ID);
                return;
            }
            set_transient($rate_limit_key, true, HOUR_IN_SECONDS);

            // Validate and sanitize inputs
            $user_id = absint($user->ID);
            $user_email = sanitize_email($user->user_email);
            
            if (!is_email($user_email)) {
                throw new Exception('Invalid email format');
            }

            // Get cached Bocs ID first
            $bocs_user_id = get_user_meta($user_id, 'bocs_user_id', true);
            if (!empty($bocs_user_id)) {
                $this->log_debug("User $user_id already has Bocs ID: $bocs_user_id");
                return;
            }

            // Get and validate API credentials
            $credentials = $this->get_api_credentials();
            if (!$credentials) {
                return;
            }

            // Prepare API request
            $api_client = $this->get_api_client();
            $response = $this->make_api_request($api_client, $user_email, $credentials);
            
            // Process response
            $this->process_api_response($response, $user_id);

        } catch (Exception $e) {
            $this->handle_error($e);
        }
    }

    /**
     * Gets API credentials from WordPress options
     *
     * @return array|false Array of credentials or false if invalid
     */
    private function get_api_credentials() 
    {
        $options = get_option('bocs_plugin_options', []);
        $headers = $options['bocs_headers'] ?? [];

        $required_fields = ['organization', 'store', 'authorization'];
        foreach ($required_fields as $field) {
            if (empty($headers[$field])) {
                $this->log_error("Missing required API credential: $field");
                return false;
            }
        }

        return $headers;
    }

    /**
     * Creates and configures API client
     *
     * @return CurlHandle|false
     */
    private function get_api_client() 
    {
        $curl = curl_init();
        if ($curl === false) {
            throw new Exception('Failed to initialize cURL');
        }

        // Set default cURL options
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30, // Reduced from unlimited
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET'
        ]);

        return $curl;
    }

    /**
     * Makes API request to Bocs
     *
     * @param CurlHandle $curl
     * @param string $email
     * @param array $credentials
     * @return array Response data
     */
    private function make_api_request($curl, $email, $credentials) 
    {
        // URL encode email for safety
        $encoded_email = urlencode($email);
        $api_url = BOCS_API_URL . "contacts?query=email:\"$encoded_email\"";

        curl_setopt_array($curl, [
            CURLOPT_URL => $api_url,
            CURLOPT_HTTPHEADER => [
                'Organization: ' . $credentials['organization'],
                'Content-Type: application/json',
                'Store: ' . $credentials['store'],
                'Authorization: ' . $credentials['authorization']
            ]
        ]);

        // Add request logging with unique ID for tracing
        $request_id = uniqid('bocs_');
        $this->log_debug("[$request_id] API Request - URL: $api_url");

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $total_time = curl_getinfo($curl, CURLINFO_TOTAL_TIME);

        curl_close($curl);

        if ($response === false) {
            throw new Exception("cURL request failed: $error");
        }

        $this->log_debug("[$request_id] API Response - Status: $http_code, Time: {$total_time}s");

        if ($http_code !== 200) {
            throw new Exception("API request failed with status code: $http_code");
        }

        return json_decode($response, false);
    }

    /**
     * Processes API response and updates user meta
     *
     * @param object $response
     * @param int $user_id
     * @return void
     */
    private function process_api_response($response, $user_id) 
    {
        if (!$response) {
            throw new Exception('Invalid API response format');
        }

        $bocs_users = $this->extract_users_from_response($response);
        if (empty($bocs_users)) {
            $this->log_warning("No valid users found for user ID: $user_id");
            return;
        }

        foreach ($bocs_users as $bocs_user) {
            if (!empty($bocs_user->id)) {
                // Use update_user_meta with a third parameter for better performance
                $updated = update_user_meta($user_id, 'bocs_user_id', $bocs_user->id, '');
                
                if ($updated) {
                    $this->log_info("Updated user $user_id with bocs_user_id: {$bocs_user->id}");
                    
                    // Trigger action for other plugins
                    do_action('bocs_user_id_updated', $user_id, $bocs_user->id);
                    
                    break;
                }
            }
        }
    }

    /**
     * Extracts user data from API response
     *
     * @param object $response
     * @return array
     */
    private function extract_users_from_response($response) 
    {
        if (isset($response->data->data) && is_array($response->data->data)) {
            return $response->data->data;
        }
        
        if (isset($response->data) && is_array($response->data)) {
            return $response->data;
        }
        
        return [];
    }

    /**
     * Handles errors consistently
     *
     * @param Exception $e
     * @return void
     */
    private function handle_error(Exception $e) 
    {
        $error_message = sprintf(
            '[Bocs][ERROR] %s in %s:%d',
            $e->getMessage(),
            basename($e->getFile()),
            $e->getLine()
        );
        
        error_log($error_message);
        
        if (current_user_can('manage_options')) {
            add_action('admin_notices', function() use ($e) {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html('Error checking Bocs user ID: ' . $e->getMessage()); ?></p>
                </div>
                <?php
            });
        }
    }

    /**
     * Logging helpers with consistent formatting
     */
    private function log_debug($message) {
        error_log("[Bocs][DEBUG] $message");
    }

    private function log_info($message) {
        error_log("[Bocs][INFO] $message");
    }

    private function log_warning($message) {
        error_log("[Bocs][WARNING] $message");
    }

    private function log_error($message) {
        error_log("[Bocs][ERROR] $message");
    }

    public function show_related_orders()
    {
        try {
            // Check if required GET parameters exist
            if (!isset($_GET['page']) || !isset($_GET['action']) || !isset($_GET['id'])) {
                return;
            }

            $page = sanitize_text_field($_GET['page']);
            $action = sanitize_text_field($_GET['action']);
            $id = absint($_GET['id']);

            // Validate parameters
            if (empty($page) || empty($action) || empty($id)) {
                return;
            }

            // Check if we're on the correct page and action
            if ($page === 'wc-orders' && $action === 'edit') {
                // Verify WooCommerce is active and function exists
                if (!function_exists('wc_get_order')) {
                    return;
                }

                // Get order details
                $order = wc_get_order($id);
                
                // Verify order exists and is valid
                if ($order && !is_wp_error($order)) {
                    add_meta_box(
                        'bocs_order_meta_box',
                        'Bocs Related Orders',
                        array($this, 'order_meta_box_content'),
                        NULL,
                        'normal',
                        'low'
                    );
                }
            }
        } catch (Exception $e) {
            // Log the error but don't display it to users
            error_log('Bocs show_related_orders error: ' . $e->getMessage());
        }
    }

    public function order_meta_box_content($post)
    {
        error_log('order_meta_box_content');
        $order_id = $post->ID;
        error_log($order_id);

        $parent_subscription = $this->get_bocs_subscription($order_id);

        $related_orders = $this->get_related_orders($order_id);

        if (! empty($related_orders) || ! empty($parent_subscription)) {

            echo '<table class="widefat fixed">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Order Number</th>';
            echo '<th>Relationship</th>';
            echo '<th>Date</th>';
            echo '<th>Status</th>';
            echo '<th>Total</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            if ($parent_subscription) {

                $billingInterval = 0;
                $billingPeriod = '';

                if (isset($parent_subscription['billingInterval'])) {
                    $billingInterval = $parent_subscription['billingInterval'];
                }

                if (empty($billingInterval) && isset($parent_subscription['frequency']['frequency'])) {
                    $billingInterval = $parent_subscription['frequency']['frequency'];
                }

                if (isset($parent_subscription['billingPeriod'])) {
                    $billingPeriod = $parent_subscription['billingPeriod'];
                }

                if (empty($billingPeriod) && isset($parent_subscription['frequency']['timeUnit'])) {
                    $billingPeriod = $parent_subscription['frequency']['timeUnit'];
                }

                $billingPeriod = $billingPeriod . 's';

                if ($billingInterval <= 1) {
                    // Remove trailing 's' if it exists
                    $billingPeriod = rtrim($billingPeriod, 's');
                    $billingInterval = '';
                }

                $date_started = '';
                if (isset($parent_subscription['startDateGmt'])) {
                    $date = new DateTime($parent_subscription['startDateGmt']);
                    $date_started = $date->format('F j, Y');
                }

                echo '<tr>';
                echo '<td>' . $parent_subscription['id'] . '</td>';
                echo '<td>Bocs Subscription</td>';
                echo '<td>' . $date_started . '</td>';
                echo '<td>' . ucfirst($parent_subscription['subscriptionStatus']) . '</td>';
                echo '<td>' . $parent_subscription['total'] . ' every ' . trim($billingInterval . ' ' . $billingPeriod) . '</td>';
                echo '</tr>';
            }

            foreach ($related_orders as $related_order) {
                echo '<tr>';
                echo '<td><a href="' . get_edit_post_link($related_order->get_id()) . '">' . $related_order->get_order_number() . '</a></td>';
                echo '<td>Renewal Order</td>';
                echo '<td>' . $related_order->get_date_created()->date('Y-m-d H:i:s') . '</td>';
                echo '<td>' . wc_get_order_status_name($related_order->get_status()) . '</td>';
                echo '<td>' . $related_order->get_formatted_order_total() . '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>No related orders found.</p>';
        }
    }

    /**
     *
     * @param integer $order_id
     * @return boolean|boolean|string
     */
    public function get_bocs_subscription($order_id)
    {
        $order = wc_get_order($order_id);

        if (! $order)
            return FALSE;

        $bocs_subscription_id = $order->get_meta('__bocs_subscription_id', true);

        if (empty($bocs_subscription_id))
            return FALSE;

        // get the details of the bocs subscription
        $helper = new Bocs_Helper();
        $url = BOCS_API_URL . 'subscriptions/' . $bocs_subscription_id;
        $subscription = $helper->curl_request($url, 'GET', NULL, $this->headers);

        return $subscription['data'];
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

    public function get_order_relationship($order_id, $related_order_id)
    {
        // Determine the relationship between orders. This is a placeholder.
        // Implement your logic to determine the relationship between orders.
        return 'Renewal Order';
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
}
