<?php

use function Loader\add_filter;

class Admin
{
    private $headers;
    private $save_widget_nonce = '';

    public function __construct()
    {
        // Initialize the nonce used for saving widget options
        $this->save_widget_nonce = wp_create_nonce('save-widget-nonce');

        // Retrieve plugin options
        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();

        // If required headers are set, store them for later use
        if (!empty($options['bocs_headers']['organization']) && !empty($options['bocs_headers']['store']) && !empty($options['bocs_headers']['authorization'])) {
            $this->headers = [
                'Organization' => $options['bocs_headers']['organization'],
                'Store' => $options['bocs_headers']['store'],
                'Authorization' => $options['bocs_headers']['authorization'],
                'Content-Type' => 'application/json'
            ];
        }
    }

    /**
     * Register a custom log handler for Bocs.
     */
    public function bocs_register_log_handlers($handlers)
    {
        array_push($handlers, new Bocs_Log_Handler());
        return $handlers;
    }

    /**
     * Process and save custom product meta fields for Bocs products.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function bocs_process_product_meta($post_id)
    {
        // Sanitize and save each meta field if it exists in the request
        $fields = [
            'bocs_product_interval',
            'bocs_product_interval_count',
            'bocs_product_discount_type',
            'bocs_product_discount'
        ];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, esc_attr(trim($_POST[$field])));
            }
        }
    }

    /**
     * Include custom JS file for Bocs in the admin area.
     */
    public function bocs_admin_custom_js()
    {
        require_once dirname(__FILE__) . '/../views/bocs_admin_custom.php';
    }

    /**
     * Include product panel view for Bocs products.
     */
    public function bocs_product_panel()
    {
        require_once dirname(__FILE__) . '/../views/bocs_product_panel.php';
    }

    /**
     * Add a custom Bocs tab to the product data tabs.
     *
     * @param array $tabs The existing product data tabs.
     * @return array The modified product data tabs.
     */
    public function bocs_product_tab($tabs)
    {
        $tabs['bocs'] = array(
            'label' => __('Bocs Product', 'bocs_product'),
            'target' => 'bocs_product_options',
            'class' => 'show_if_bocs'
        );

        // Add Bocs-specific class to existing tabs
        $tabs['general']['class'][] = 'show_if_bocs';
        $tabs['inventory']['class'][] = 'show_if_bocs';

        return $tabs;
    }

    /**
     * Register a custom Bocs product type with WooCommerce.
     */
    public function register_bocs_product_type()
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/WC_Bocs_Product_Type.php';

        // Ensure the class exists before adding the product type
        if (class_exists('WC_Bocs_Product_Type')) {
            add_filter('product_type_selector', [$this, 'add_custom_product_type']);
        }
    }

    /**
     * Add custom product type to the WooCommerce product type selector.
     *
     * @param array $types The existing product types.
     * @return array The modified product types.
     */
    public function add_custom_product_type($types)
    {
        $types['bocs'] = __('Bocs Product', 'woocommerce-bocs');
        return $types;
    }

    /**
     * Register and localize scripts for the Bocs widget block.
     */
    public function bocs_widget_script_register()
    {
        // Ensure Bocs keys are added or updated
        $bocs = new Bocs();
        $bocs->auto_add_bocs_keys();

        // Retrieve plugin options
        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();

        // Enqueue styles and scripts for the Bocs widget block
        wp_enqueue_style('font-awesome', plugin_dir_url(__FILE__) . '../assets/css/font-awesome.min.css', null, '0.0.1');
        wp_enqueue_style('bocs-custom-block-css', plugin_dir_url(__FILE__) . '../assets/css/bocs-widget.css', null, '0.0.15');
        wp_enqueue_script('jquery');

        wp_register_script('bocs-custom-block', plugin_dir_url(__FILE__) . '../assets/js/bocs-widget.js', array(
            'wp-components',
            'wp-block-editor',
            'wp-blocks',
            'wp-i18n',
            'wp-editor',
            'wp-data',
            'jquery'
        ), '20240828.5');

        // Get the current post ID and selected widget details
        $post_id = get_the_ID();
        $selected_widget_id = get_post_meta($post_id, 'selected_bocs_widget_id', true);
        $selected_widget_name = get_post_meta($post_id, 'selected_bocs_widget_name', true);

        // Get the list of collections and widgets
        $bocs_collections = get_option('bocs_collections');
        $bocs_widgets = get_option('bocs_widgets');

        // Localize script with necessary data
        $params = array(
            'bocsURL' => BOCS_API_URL . 'bocs',
            'widgetsURL' => BOCS_API_URL . 'list-widgets',
            'collectionsURL' => BOCS_API_URL . 'collections',
            'Organization' => $options['bocs_headers']['organization'],
            'Store' => $options['bocs_headers']['store'],
            'Authorization' => $options['bocs_headers']['authorization'],
            'nonce' => $this->save_widget_nonce,
            'ajax_url' => admin_url('admin-ajax.php'),
            'selected_id' => $selected_widget_id,
            'selected_name' => $selected_widget_name,
            'bocs_collections' => $bocs_collections,
            'bocs_widgets' => $bocs_widgets
        );

        wp_enqueue_script('bocs-custom-block');
        wp_localize_script('bocs-custom-block', 'bocs_widget_object', $params);
    }

    /**
     * Enqueue admin-specific scripts and styles.
     */
    public function admin_enqueue_scripts()
    {
        wp_enqueue_style('bocs-admin-css', plugin_dir_url(__FILE__) . '../assets/css/admin.css', null, '0.0.2');

        // Retrieve plugin options
        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();

        wp_register_script('bocs-admin-js', plugin_dir_url(__FILE__) . '../assets/js/admin.js', array('jquery'), '20240828.4');
        wp_enqueue_script('bocs-admin-js');

        // Get the list of collections and widgets
        $bocs_collections = get_option('bocs_collections');
        $bocs_widgets = get_option('bocs_widgets');

        wp_localize_script('bocs-admin-js', 'bocsAjaxObject', array(
            'bocsURL' => BOCS_API_URL . 'bocs',
            'collectionsURL' => BOCS_API_URL . 'collections',
            'widgetsURL' => BOCS_API_URL . 'list-widgets',
            'Organization' => $options['bocs_headers']['organization'] ?? '',
            'Store' => $options['bocs_headers']['store'] ?? '',
            'Authorization' => $options['bocs_headers']['authorization'] ?? '',
            'bocs_collections' => $bocs_collections,
            'bocs_widgets' => $bocs_widgets
        ));
    }

    /**
     * Enqueue frontend scripts and styles for Bocs widgets and functionality.
     */
    public function enqueue_scripts()
    {
        // Ensure Bocs keys are added or updated
        $bocs = new Bocs();
        $bocs->auto_add_bocs_keys();

        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();

        // Enqueue the main Bocs widget script
        wp_enqueue_script('bocs-widget-script', 'https://bocs-widget-bocs.vercel.app/script/index.js', array(), '20240827.1', true);

        // Enqueue WooCommerce scripts if WooCommerce is active
        if (class_exists('woocommerce')) {
            wp_enqueue_script('wc-add-to-cart');
            wp_enqueue_script('wc-cart-fragments');
        }

        // Set up AJAX data for cart-related operations
        $redirect = wc_get_checkout_url();
        $cart_nonce = wp_create_nonce('wc_store_api');

        wp_enqueue_script('bocs-add-to-cart', plugin_dir_url(__FILE__) . '../assets/js/add-to-cart.js', array(
            'jquery',
            'bocs-widget-script'
        ), '20240607.1', true);

        wp_localize_script('bocs-add-to-cart', 'bocsAjaxObject', array(
            'cartNonce' => $cart_nonce,
            'cartURL' => $redirect,
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ajax-nonce'),
            'search_nonce' => wp_create_nonce('ajax-search-nonce'),
            'bocsGetUrl' => BOCS_API_URL . 'bocs/',
            'storeId' => $options['bocs_headers']['store'],
            'orgId' => $options['bocs_headers']['organization'],
            'authId' => $options['bocs_headers']['authorization'],
            'update_product_nonce' => wp_create_nonce('ajax-update-product-nonce'),
            'couponNonce' => wp_create_nonce('ajax-create-coupon-nonce')
        ));

        // Enqueue scripts for checkout, cart, and specific WooCommerce pages
        if (is_checkout()) {
            wp_enqueue_script('bocs-stripe-checkout-js', plugin_dir_url(__FILE__) . '../assets/js/custom-stripe-checkout.js', array('jquery'), '20240611.8', true);
            wp_enqueue_script('bocs-checkout-js', plugin_dir_url(__FILE__) . '../assets/js/bocs-checkout.js', array('jquery'), '20240624.1', true);

            wp_localize_script('bocs-checkout-js', 'bocsCheckoutObject', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ajax-nonce'),
                'storeId' => $options['bocs_headers']['store'],
                'orgId' => $options['bocs_headers']['organization'],
                'authId' => $options['bocs_headers']['authorization'],
                'frequency' => $current_frequency,
                'bocs' => $bocs_body['data']
            ));
        }

        if (is_cart()) {
            wp_enqueue_script('bocs-cart-js', plugin_dir_url(__FILE__) . '../assets/js/bocs-cart.js', array('jquery'), '20240705.1', true);

            wp_localize_script('bocs-cart-js', 'bocsCartObject', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ajax-nonce'),
                'storeId' => $options['bocs_headers']['store'],
                'orgId' => $options['bocs_headers']['organization'],
                'authId' => $options['bocs_headers']['authorization'],
                'frequency' => $current_frequency,
                'bocs' => $bocs_body['data'],
                'bocsConversion' => $bocs_options,
                'bocsConversionTotal' => $bocs_conversion_total
            ));
        }
    }

    /**
     * Render the Bocs homepage view.
     */
    public function bocs_homepage()
    {
        require_once dirname(__FILE__) . '/../views/bocs_homepage.php';
    }

    /**
     * Render the Bocs tags view.
     */
    public function bocs_tags()
    {
        require_once dirname(__FILE__) . '/../views/bocs_tag.php';
    }

    /**
     * Render the Bocs categories view.
     */
    public function bocs_categories()
    {
        require_once dirname(__FILE__) . '/../views/bocs_category.php';
    }

    /**
     * Save contact synchronization settings for Bocs.
     */
    private function _bocs_post_contact()
    {
        $options = get_option('bocs_plugin_options');

        if ($_POST) {
            $fields = [
                'sync_contacts_to_bocs',
                'sync_contacts_from_bocs',
                'sync_daily_contacts_to_bocs',
                'sync_daily_contacts_from_bocs'
            ];

            foreach ($fields as $field) {
                if (isset($_POST["bocs_plugin_options"][$field])) {
                    $options[$field] = $_POST["bocs_plugin_options"][$field];
                }
            }

            update_option('bocs_plugin_options', $options);
        }
    }

    /**
     * Render the Bocs contact settings page.
     */
    public function bocs_contact_page()
    {
        $this->_bocs_post_contact();
        require_once dirname(__FILE__) . '/../views/bocs_setting.php';
    }

    /**
     * Save Bocs headers settings.
     */
    private function _bocs_post_headers_settings()
    {
        $options = get_option('bocs_plugin_options');

        if ($_POST) {
            if (isset($_POST["bocs_plugin_options"]['bocs_headers'])) {
                $headers = ['store', 'organization', 'authorization'];
                foreach ($headers as $header) {
                    $options['bocs_headers'][$header] = $_POST["bocs_plugin_options"]["bocs_headers"][$header];
                }
            }
        }

        update_option('bocs_plugin_options', $options);
    }

    /**
     * Placeholder for saving Bocs sync options.
     */
    private function _bocs_post_sync_options()
    {
        // @TODO: Implement sync options save functionality
    }

    /**
     * Render the Bocs error logs page.
     */
    public function bocs_error_logs_page()
    {
        require_once dirname(__FILE__) . '/../views/bocs_error_logs.php';
    }

    /**
     * Render the Bocs sync store page.
     */
    public function bocs_sync_store_page()
    {
        $this->_bocs_post_sync_options();
        require_once dirname(__FILE__) . '/../views/bocs_sync_store.php';
    }

    /**
     * Render the Bocs settings page.
     */
    public function bocs_settings_page()
    {
        $this->_bocs_post_headers_settings();
        require_once dirname(__FILE__) . '/../views/bocs_settings.php';
    }

    /**
     * Add the Bocs menu and settings pages to the WordPress admin.
     */
    public function bocs_add_settings_page()
    {
        add_menu_page("Bocs", "Bocs", 'manage_options', 'bocs', [$this, 'bocs_homepage'], 'data:image/svg+xml;base64,' . base64_encode('<svg width="20" height="20" viewBox="0 0 36 36" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" xmlnsSerif="http://www.serif.com/" aria-hidden="true" focusable="false" style="fill-rule: evenodd; clip-rule: evenodd; stroke-linejoin: round; stroke-miterlimit: 2;"><g transform="matrix(1,0,0,1,-647.753,-303.839)"><g transform="matrix(1,0,0,1,-8.46249,-21.314)"><path d="M686.684,328.173C686.258,326.125 684.248,324.807 682.199,325.234L659.235,330.012C657.186,330.439 655.869,332.448 656.295,334.497L661.074,357.461C661.5,359.51 663.51,360.827 665.558,360.401L688.523,355.622C690.572,355.196 691.889,353.187 691.463,351.138L686.684,328.173ZM673.879,334.202C678.21,334.202 681.726,338.062 681.726,342.817C681.726,347.572 678.21,351.432 673.879,351.432C669.548,351.432 666.032,347.572 666.032,342.817C666.032,338.062 669.548,334.202 673.879,334.202Z" style="fill: rgb(0, 132, 139);"></path></g></g></svg>'), 2);
        add_submenu_page("bocs", "Subscriptions", "Subscriptions", "manage_options", 'bocs-subscriptions', [$this, 'bocs_list_subscriptions']);
        add_submenu_page("bocs", "Settings", "Settings", "manage_options", 'bocs-settings', [$this, 'bocs_settings_page']);

        // Remove the default Bocs submenu to avoid redundancy
        remove_submenu_page('bocs', 'bocs');
    }

    /**
     * Render the Bocs subscriptions list page.
     */
    public function bocs_list_subscriptions()
    {
        require_once dirname(__FILE__) . '/../views/bocs_list_subscriptions.php';
    }

    /**
     * Render the Bocs plugin settings page.
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
            <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Save'); ?>" />
        </form>
<?php
    }

    /**
     * Register the Bocs settings with WordPress.
     */
    public function bocs_register_settings()
    {
        register_setting('bocs_plugin_options', 'bocs_plugin_options', [$this, 'bocs_plugin_options_validate']);

        add_settings_section('api_settings', 'API Settings', [$this, 'bocs_plugin_section_text'], 'bocs_plugin');

        // Add fields to the settings section
        $fields = [
            'bocs_plugin_setting_api_key' => 'Public API Key',
            'bocs_plugin_setting_sync_contacts_to_bocs' => 'Sync Contacts to Bocs',
            'bocs_plugin_setting_sync_daily_contacts_to_bocs' => 'Daily Autosync Contacts to Bocs',
            'bocs_plugin_setting_sync_daily_contacts_from_bocs' => 'Daily Autosync Contacts From Bocs'
        ];

        foreach ($fields as $field_id => $label) {
            add_settings_field($field_id, $label, [$this, $field_id], 'bocs_plugin', 'api_settings');
        }
    }

    /**
     * Display the API settings section text.
     */
    public function bocs_plugin_section_text()
    {
        echo '<p>Here you can set all the options for using the API</p>';
    }

    /**
     * Display the API Key setting field.
     */
    public function bocs_plugin_setting_api_key()
    {
        $options = get_option('bocs_plugin_options');
        echo "<input id='bocs_plugin_setting_api_key' name='bocs_plugin_options[api_key]' type='text' value='" . esc_attr($options['api_key']) . "' />";
    }

    /**
     * Display the Sync Contacts to Bocs setting field.
     */
    public function bocs_plugin_setting_sync_contacts_to_bocs()
    {
        $options = get_option('bocs_plugin_options');
        $options['sync_contacts_to_bocs'] = $options['sync_contacts_to_bocs'] ?? 0;

        $html = '<input id="bocs_plugin_setting_sync_contacts_to_bocs" type="radio" name="bocs_plugin_options[sync_contacts_to_bocs]" value="1"';
        $html .= $options['sync_contacts_to_bocs'] == 1 ? ' checked' : '';
        $html .= '><label for="1">Yes</label>&nbsp;&nbsp;&nbsp;';
        $html .= '<input id="bocs_plugin_setting_sync_contacts_to_bocs_no" type="radio" name="bocs_plugin_options[sync_contacts_to_bocs]" value="0"';
        $html .= $options['sync_contacts_to_bocs'] != 1 ? ' checked' : '';
        $html .= '><label for="0">No</label>';
        $html .= '<br /><button class="button button-primary" id="syncContactsToBocs" type="button">Sync Now</button><p id="syncContactsToBocs-response"></p>';

        echo $html;
    }

    /**
     * Display the Daily Autosync Contacts to Bocs setting field.
     */
    public function bocs_plugin_setting_sync_daily_contacts_to_bocs()
    {
        $options = get_option('bocs_plugin_options');
        $options['sync_daily_contacts_to_bocs'] = $options['sync_daily_contacts_to_bocs'] ?? 0;

        $html = '<input id="bocs_plugin_setting_sync_daily_contacts_to_bocs" type="radio" name="bocs_plugin_options[sync_daily_contacts_to_bocs]" value="1"';
        $html .= $options['sync_daily_contacts_to_bocs'] == 1 ? ' checked' : '';
        $html .= '><label for="1">Yes</label>&nbsp;&nbsp;&nbsp;';
        $html .= '<input id="bocs_plugin_setting_sync_daily_contacts_to_bocs_no" type="radio" name="bocs_plugin_options[sync_daily_contacts_to_bocs]" value="0"';
        $html .= $options['sync_daily_contacts_to_bocs'] != 1 ? ' checked' : '';
        $html .= '><label for="0">No</label>';

        echo $html;
    }

    /**
     * Display the Daily Autosync Contacts From Bocs setting field.
     */
    public function bocs_plugin_setting_sync_daily_contacts_from_bocs()
    {
        $options = get_option('bocs_plugin_options');
        $options['sync_daily_contacts_from_bocs'] = $options['sync_daily_contacts_from_bocs'] ?? 0;

        $html = '<input id="bocs_plugin_setting_sync_daily_contacts_from_bocs" type="radio" name="bocs_plugin_options[sync_daily_contacts_from_bocs]" value="1"';
        $html .= $options['sync_daily_contacts_from_bocs'] == 1 ? ' checked' : '';
        $html .= '><label for="1">Yes</label>&nbsp;&nbsp;&nbsp;';
        $html .= '<input id="bocs_plugin_setting_sync_daily_contacts_from_bocs_no" type="radio" name="bocs_plugin_options[sync_daily_contacts_from_bocs]" value="0"';
        $html .= $options['sync_daily_contacts_from_bocs'] != 1 ? ' checked' : '';
        $html .= '><label for="0">No</label>';

        echo $html;
    }

    /**
     * Validate and sanitize plugin options.
     *
     * @param array $input The input data to validate.
     * @return array The validated and sanitized input data.
     */
    public function bocs_plugin_options_validate($input)
    {
        $newinput = array();

        if (isset($input['api_key'])) {
            $newinput['api_key'] = trim($input['api_key']);
            if (!preg_match('/^[-a-z0-9]{36}$/i', $newinput['api_key'])) {
                $newinput['api_key'] = '';
            }
        }

        $fields = [
            'sync_contacts_to_bocs',
            'sync_daily_contacts_to_bocs',
            'sync_daily_contacts_from_bocs'
        ];

        foreach ($fields as $field) {
            $newinput[$field] = trim($input[$field]) == '1' ? 1 : 0;
        }

        return $newinput;
    }

    /**
     * AJAX callback to create a new WooCommerce product.
     */
    public function create_product_ajax_callback()
    {
        // Verify the AJAX nonce
        $nonce = $_POST['nonce'];

        if (!wp_verify_nonce($nonce, 'ajax-nonce')) {
            die('Invalid nonce');
        }

        // Get the product data from the AJAX request
        $product_data = [
            'post_title' => sanitize_text_field($_POST['title']),
            'post_status' => 'publish',
            'post_type' => 'product',
            'post_content' => ''
        ];

        // Insert the new product
        $product_id = wp_insert_post($product_data);

        if ($product_id) {
            $meta_data = [
                '_price' => $_POST['price'],
                '_regular_price' => $_POST['price'],
                '_sku' => $_POST['sku'] ?? '',
                '_backorders' => 'no',
                '_download_expiry' => '-1',
                '_download_limit' => '-1',
                '_downloadable' => 'no',
                '_manage_stock' => 'no',
                '_sold_individually' => 'no',
                '_stock_status' => 'instock',
                '_virtual' => 'no',
                '_wc_average_rating' => '0',
                '_wc_review_count' => '0',
            ];

            foreach ($meta_data as $key => $value) {
                update_post_meta($product_id, $key, $value);
            }

            // Save additional Bocs-specific meta if applicable
            if ($_POST['type'] === 'bocs') {
                $bocs_meta = [
                    'bocs_product_discount',
                    'bocs_product_discount_type',
                    'bocs_product_interval',
                    'bocs_product_interval_count',
                    'bocs_frequency_id',
                    'bocs_bocs_id',
                    'bocs_type',
                    'bocs_sku',
                    'bocs_price'
                ];

                foreach ($bocs_meta as $meta) {
                    update_post_meta($product_id, $meta, sanitize_text_field($_POST[$meta]));
                }
            }
        }

        wp_send_json($product_id);
    }

    /**
     * AJAX callback to create an order and subscription in Bocs.
     *
     * @param int $order_id The ID of the WooCommerce order.
     * @return false|void
     */
    public function bocs_order_status_processing($order_id = 0)
    {
        if (empty($order_id)) return false;

        $order = wc_get_order($order_id);
        $bocs_product_interval = 'month';
        $bocs_product_interval_count = 1;
        $subscription_line_items = [];

        // Retrieve Bocs data from session or cookies if not available in order meta
        $bocs_data = [
            'bocsid' => $order->get_meta('__bocs_bocs_id'),
            'collectionid' => $order->get_meta('__bocs_collections_id'),
            'frequency_id' => $order->get_meta('__bocs_frequency_id')
        ];

        foreach ($bocs_data as $key => &$value) {
            if (empty($value) && isset(WC()->session)) {
                $value = WC()->session->get($key);
                if (empty($value) && isset($_COOKIE["__$key"])) {
                    $value = sanitize_text_field($_COOKIE["__$key"]);
                }
                if (!empty($value)) {
                    $order->update_meta_data("__{$key}", $value);
                }
            }
        }

        // Retrieve Bocs subscription data
        $current_frequency = null;
        $bocs_body = $this->get_bocs_data_from_api($bocs_data['bocsid']);

        if (isset($bocs_body['data']['priceAdjustment']['adjustments'])) {
            foreach ($bocs_body['data']['priceAdjustment']['adjustments'] as $frequency) {
                if ($frequency['id'] == $bocs_data['frequency_id']) {
                    $current_frequency = $frequency;
                    break;
                }
            }
        }

        // Collect subscription line items
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $product_id = get_post_meta($item->get_product_id(), 'bocs_product_id', true) ?: get_post_meta($item->get_product_id(), 'bocs_id', true);
            $subscription_line_items[] = json_encode([
                'sku' => $product->get_sku(),
                'price' => round($product->get_regular_price(), 2),
                'quantity' => $item->get_quantity(),
                'productId' => $product_id,
                'total' => $item->get_total(),
                'externalSourceId' => $product->get_id()
            ]);
        }

        // Create Bocs subscription if Bocs ID is available
        if (!empty($bocs_data['bocsid'])) {
            $this->create_bocs_subscription($order, $bocs_data, $subscription_line_items, $current_frequency);
        }
    }

    /**
     * Create a Bocs subscription using the Bocs API.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @param array $bocs_data The Bocs-related data (Bocs ID, collection ID, etc.).
     * @param array $subscription_line_items The line items for the subscription.
     * @param array $current_frequency The current frequency data for the subscription.
     */
    private function create_bocs_subscription($order, $bocs_data, $subscription_line_items, $current_frequency)
    {
        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();

        $customer_id = $order->get_customer_id();
        $bocs_customer_id = '';

        if (!empty($customer_id)) {
            $bocs_customer_id = $this->get_bocs_customer_id($customer_id, $order->get_billing_email());
        }

        // Prepare subscription data
        $start_date = $order->get_date_paid();
        $dateTime = $this->get_utc_datetime($start_date);
        $start_date = $dateTime->format('Y-m-d\TH:i:s') . '.000Z';

        $post_data = json_encode([
            'bocs' => ['id' => $bocs_data['bocsid']],
            'collection' => ['id' => $bocs_data['collectionid']],
            'billing' => $order->get_address('billing'),
            'shipping' => $order->get_address('shipping'),
            'customer' => ['id' => $bocs_customer_id, 'externalSourceId' => $customer_id],
            'lineItems' => $subscription_line_items,
            'frequency' => [
                'price' => $current_frequency['price'],
                'discount' => $current_frequency['discount'],
                'discountType' => $current_frequency['discountType'],
                'id' => $current_frequency['id'],
                'frequency' => $current_frequency['frequency'],
                'timeUnit' => $current_frequency['timeUnit']
            ],
            'startDateGmt' => $start_date,
            'order' => $this->get_order_data_as_json($order->get_id()),
            'total' => $order->get_total(),
            'discountTotal' => round($order->get_discount_total() + $order->get_discount_tax(), 2)
        ]);

        // Send the subscription data to Bocs API
        $this->send_bocs_api_request('subscriptions', $post_data);
    }

    /**
     * Get the Bocs customer ID from the API or create a new one.
     *
     * @param int $customer_id The WordPress user ID.
     * @param string $email The customer's email address.
     * @return string The Bocs customer ID.
     */
    private function get_bocs_customer_id($customer_id, $email)
    {
        $bocs_customer_id = get_user_meta($customer_id, 'bocs_user_id', true);

        if (empty($bocs_customer_id)) {
            $bocs_customer_id = $this->fetch_bocs_customer_id_by_email($email);
            if ($bocs_customer_id) {
                update_user_meta($customer_id, 'bocs_user_id', $bocs_customer_id);
            }
        }

        return $bocs_customer_id;
    }

    /**
     * Fetch the Bocs customer ID by email address using the Bocs API.
     *
     * @param string $email The customer's email address.
     * @return string|null The Bocs customer ID or null if not found.
     */
    private function fetch_bocs_customer_id_by_email($email)
    {
        $options = get_option('bocs_plugin_options');
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => BOCS_API_URL . 'contacts?email=' . $email,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'Organization: ' . $options['bocs_headers']['organization'],
                'Content-Type: application/json',
                'Store: ' . $options['bocs_headers']['store'],
                'Authorization: ' . $options['bocs_headers']['authorization']
            )
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        $data = json_decode($response, true);
        return $data['data'][0]['id'] ?? null;
    }

    /**
     * Send a request to the Bocs API.
     *
     * @param string $endpoint The API endpoint to send the request to.
     * @param string $post_data The data to send in the request.
     * @return void
     */
    private function send_bocs_api_request($endpoint, $post_data)
    {
        $options = get_option('bocs_plugin_options');
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => BOCS_API_URL . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $post_data,
            CURLOPT_HTTPHEADER => array(
                'Organization: ' . $options['bocs_headers']['organization'],
                'Content-Type: application/json',
                'Store: ' . $options['bocs_headers']['store'],
                'Authorization: ' . $options['bocs_headers']['authorization']
            )
        ));

        curl_exec($curl);
        if (curl_errno($curl)) {
            error_log('Bocs API request error: ' . curl_error($curl));
        }
        curl_close($curl);
    }

    /**
     * Convert a date to UTC and return a DateTime object.
     *
     * @param string $date The date to convert.
     * @return DateTime The converted DateTime object.
     */
    private function get_utc_datetime($date)
    {
        $timezoneString = get_option('timezone_string');
        if (empty($timezoneString)) {
            $offset = get_option('gmt_offset');
            $timezoneString = timezone_name_from_abbr('', $offset * 3600, false);
        }

        $timezone = new DateTimeZone($timezoneString);
        $dateTime = new DateTime($date, $timezone);
        $dateTime->setTimezone(new DateTimeZone('UTC'));

        return $dateTime;
    }

    /**
     * Get Bocs data from the API.
     *
     * @param string $id The Bocs ID.
     * @return array The Bocs data.
     */
    public function get_bocs_data_from_api($id)
    {
        $url = BOCS_API_URL . "bocs/" . $id;
        $bocs_helper = new Bocs_Helper();
        return $bocs_helper->curl_request($url, 'GET', [], $this->headers);
    }

    /**
     * Get the WooCommerce order data as a JSON string.
     *
     * @param int $order_id The WooCommerce order ID.
     * @return string The order data in JSON format.
     */
    public function get_order_data_as_json($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            return json_encode(array('error' => 'Order not found'));
        }

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
            'line_items' => [],
            'tax_lines' => [],
            'shipping_lines' => [],
            'fee_lines' => [],
            'coupon_lines' => [],
            'refunds' => []
        );

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

        foreach ($order->get_items('coupon') as $coupon_item_id => $coupon_item) {
            $order_data['coupon_lines'][] = array(
                'id' => $coupon_item_id,
                'code' => $coupon_item->get_code(),
                'discount' => $coupon_item->get_discount(),
                'discount_tax' => $coupon_item->get_discount_tax()
            );
        }

        foreach ($order->get_refunds() as $refund) {
            $order_data['refunds'][] = array(
                'id' => $refund->get_id(),
                'reason' => $refund->get_reason(),
                'total' => $refund->get_amount()
            );
        }

        return json_encode($order_data, JSON_PRETTY_PRINT);
    }
}
