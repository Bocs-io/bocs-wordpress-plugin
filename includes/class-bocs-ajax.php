<?php

/**
 * Class BOCS_AJAX
 *
 * Handles all AJAX requests for the BOCS plugin.
 *
 * @since 1.0.0
 */
class BOCS_AJAX {
    /**
     * API instance
     *
     * @var BOCS_API
     */
    private $api;

    /** @var array API headers for Bocs authentication */
    private $headers;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new BOCS_API();
        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();

        if (!empty($options['bocs_headers']['organization']) && !empty($options['bocs_headers']['store']) && !empty($options['bocs_headers']['authorization'])) {
            $this->headers = [
                'Organization' => $options['bocs_headers']['organization'] ?? '',
                'Store' => $options['bocs_headers']['store'] ?? '',
                'Authorization' => $options['bocs_headers']['authorization'] ?? '',
                'Content-Type' => 'application/json'
            ];
        }

        $this->init();
    }

    /**
     * Initialize AJAX hooks
     *
     * @return void
     */
    public function init() {
        // AJAX actions for logged-in users
        add_action('wp_ajax_get_bocs_products', array($this, 'get_bocs_products'));
        
        // AJAX actions for non-logged-in users (if needed)
        add_action('wp_ajax_nopriv_get_bocs_products', array($this, 'get_bocs_products'));

        // Register AJAX actions
        add_action('wp_ajax_bocs_update_box', array($this, 'handle_update_box'));
        add_action('wp_ajax_nopriv_bocs_update_box', array($this, 'handle_unauthorized_request'));
    }

    /**
     * Get products for a specific BOCS
     *
     * Handles the AJAX request to fetch products for a given BOCS ID.
     * Validates the request, fetches the data, and returns a formatted response.
     *
     * @since 1.0.0
     * @return void Sends JSON response and exits
     */
    public function get_bocs_products() {
        try {
            // Verify nonce
            if (!check_ajax_referer('get_bocs_products', 'nonce', false)) {
                error_log('BOCS AJAX Error - Invalid nonce for get_bocs_products');
                wp_send_json_error(array(
                    'message' => 'Security check failed',
                    'code' => 'invalid_nonce'
                ));
                return;
            }

            // Check if BOCS ID is provided
            if (!isset($_POST['bocs_id']) || empty($_POST['bocs_id'])) {
                error_log('BOCS AJAX Error - Missing BOCS ID in request');
                wp_send_json_error(array(
                    'message' => 'BOCS ID is required',
                    'code' => 'missing_bocs_id'
                ));
                return;
            }

            // Sanitize input
            $bocs_id = sanitize_text_field($_POST['bocs_id']);
            
            error_log('BOCS AJAX - Fetching products for BOCS ID: ' . $bocs_id);
            
            // Get products from API
            $response = $this->api->get_bocs_products($bocs_id);
            
            // Handle API errors
            if (is_wp_error($response)) {
                error_log('BOCS AJAX Error - API Error: ' . $response->get_error_message());
                wp_send_json_error(array(
                    'message' => $response->get_error_message(),
                    'code' => $response->get_error_code()
                ));
                return;
            }

            // Check if we have products data
            if (!isset($response['data']) || !is_array($response['data'])) {
                error_log('BOCS AJAX Error - Invalid products data structure received from API');
                wp_send_json_error(array(
                    'message' => 'Invalid products data received from API',
                    'code' => 'invalid_data_structure'
                ));
                return;
            }

            error_log('BOCS AJAX Success - Returning ' . count($response['data']) . ' products');

            // Return success response
            wp_send_json_success(array(
                'message' => 'Products retrieved successfully',
                'data' => $response['data']
            ));

        } catch (Exception $e) {
            error_log('BOCS AJAX Error - Unexpected error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'An unexpected error occurred',
                'code' => 'unexpected_error',
                'details' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle the update box AJAX request
     */
    public function handle_update_box() {
        // Check nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'bocs-update-box-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'bocs-wordpress')));
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to update your box.', 'bocs-wordpress')));
        }

        // Get subscription ID
        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
        if (empty($subscription_id)) {
            wp_send_json_error(array('message' => __('Invalid subscription ID.', 'bocs-wordpress')));
        }

        // Get box data
        $box_data_json = isset($_POST['box_data']) ? $_POST['box_data'] : '';
        if (empty($box_data_json)) {
            wp_send_json_error(array('message' => __('Invalid box data.', 'bocs-wordpress')));
        }

        $box_data = json_decode($box_data_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => __('Invalid JSON data.', 'bocs-wordpress')));
        }

        // Validate box data
        if (!isset($box_data['lineItems']) || !is_array($box_data['lineItems']) || empty($box_data['lineItems'])) {
            wp_send_json_error(array('message' => __('Please add at least one product to your box.', 'bocs-wordpress')));
        }

        // Sanitize line items
        $line_items = array();
        foreach ($box_data['lineItems'] as $item) {
            if (isset($item['productId']) && isset($item['quantity'])) {
                $line_items[] = array(
                    'productId' => sanitize_text_field($item['productId']),
                    'quantity' => intval($item['quantity'])
                );
            }
        }

        if (empty($line_items)) {
            wp_send_json_error(array('message' => __('Please add at least one valid product to your box.', 'bocs-wordpress')));
        }

        // Load helper class
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Helper.php';
        $helper = new Bocs_Helper();

        // Make API request to update subscription
        $url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
        $data = array(
            'lineItems' => $line_items
        );

        $response = $helper->curl_request($url, 'PATCH', $data, $this->headers);

        if (is_wp_error($response)) {
            $helper->log('Error updating subscription box: ' . $response->get_error_message(), 'error');
            wp_send_json_error(array('message' => __('Failed to update your box. Please try again.', 'bocs-wordpress')));
        }

        // Check if the update was successful
        if (isset($response['data']) && isset($response['data']['id'])) {
            wp_send_json_success(array(
                'message' => __('Your box has been updated successfully!', 'bocs-wordpress'),
                'subscription' => $response['data']
            ));
        } else {
            $helper->log('Error updating subscription box: ' . json_encode($response), 'error');
            wp_send_json_error(array('message' => __('Failed to update your box. Please try again.', 'bocs-wordpress')));
        }
    }

    /**
     * Handle unauthorized AJAX requests
     */
    public function handle_unauthorized_request() {
        wp_send_json_error(array('message' => __('You must be logged in to perform this action.', 'bocs-wordpress')));
    }

    /**
     * Validates and sanitizes the BOCS ID
     *
     * @param string $bocs_id The BOCS ID to validate
     * @return bool|WP_Error Returns true if valid, WP_Error if invalid
     */
    private function validate_bocs_id($bocs_id) {
        // Remove any whitespace
        $bocs_id = trim($bocs_id);

        // Check if empty after trimming
        if (empty($bocs_id)) {
            return new WP_Error('invalid_bocs_id', 'BOCS ID cannot be empty');
        }

        // Check if it matches the expected format (UUID v4)
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $bocs_id)) {
            return new WP_Error('invalid_bocs_id', 'Invalid BOCS ID format');
        }

        return true;
    }
}

/**
 * Generate line items in the proper format for BOCS API
 *
 * @param array $products Array of products from BOCS collection
 * @param bool $include_shipping Whether to include shipping costs from WooCommerce
 * @return array Formatted line items with shipping information
 */
function generate_line_items($products, $include_shipping = true) {
    $line_items = array();
    
    if (empty($products) || !is_array($products)) {
        return $line_items;
    }
    
    // Get WooCommerce tax rate
    $tax_rate = 0.1; // Default fallback to 10% GST
    $shipping_total = 0;
    $shipping_tax = 0;
    
    // Check if WooCommerce is active
    if (function_exists('WC')) {
        // Get tax rates from WooCommerce
        $tax_classes = WC_Tax::get_tax_classes();
        $tax_rates = array();
        
        // If no tax classes, use standard rate
        if (empty($tax_classes)) {
            $tax_rates = WC_Tax::get_rates();
        } else {
            // Add standard class
            $tax_rates = WC_Tax::get_rates();
            
            // If we need tax rates from a specific class, we can get them here
            // For example, if we need to match specific product tax classes
        }
        
        // If we have tax rates, calculate the effective rate
        if (!empty($tax_rates)) {
            // Sum up all the rates (handles multiple taxes applied)
            $total_rate = 0;
            foreach ($tax_rates as $rate) {
                $total_rate += floatval($rate['rate']);
            }
            
            // Convert percentage to decimal (e.g., 10% becomes 0.1)
            $tax_rate = $total_rate / 100;
        }
        
        // Get shipping information if needed
        if ($include_shipping) {
            $shipping_methods = WC()->shipping()->get_shipping_methods();
            
            // If WooCommerce cart is available, try to get shipping from there
            if (function_exists('WC') && isset(WC()->cart) && WC()->cart) {
                // Get shipping from cart if available
                $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
                $shipping_packages = WC()->shipping()->get_packages();
                
                if (!empty($shipping_packages) && !empty($chosen_shipping_methods)) {
                    foreach ($shipping_packages as $i => $package) {
                        if (isset($chosen_shipping_methods[$i]) && isset($package['rates'][$chosen_shipping_methods[$i]])) {
                            $method = $package['rates'][$chosen_shipping_methods[$i]];
                            $shipping_total += $method->cost;
                            $shipping_tax += $method->get_shipping_tax();
                        }
                    }
                }
            } else {
                // Default to a standard shipping method if cart isn't available
                foreach ($shipping_methods as $method) {
                    if ($method->enabled === 'yes' && $method->id === 'flat_rate') {
                        // Use flat rate if available and enabled
                        $cost = $method->get_option('cost');
                        if (!empty($cost)) {
                            $shipping_total = floatval($cost);
                            $shipping_tax = $shipping_total * $tax_rate;
                            break;
                        }
                    }
                }
            }
        }
    }
    
    foreach ($products as $product) {
        if (!isset($product['id']) || !isset($product['price'])) {
            continue;
        }
        
        $price = floatval($product['price']);
        $subtotal = $price;
        $quantity = isset($product['quantity']) ? intval($product['quantity']) : 1;
        
        // Calculate tax using the fetched rate
        $subtotalTax = round($subtotal * $tax_rate, 2);
        $totalTax = round($price * $tax_rate, 2);
        
        // Simply use the externalSourceId from the product data
        $external_id = isset($product['externalSourceId']) ? $product['externalSourceId'] : '';
        
        // Get the SKU - if empty, try to get it from WooCommerce using externalSourceId
        $sku = isset($product['sku']) ? $product['sku'] : '';
        
        // If SKU is empty and we have an externalSourceId, try to get it from WooCommerce
        if (empty($sku) && !empty($external_id) && function_exists('wc_get_product')) {
            // Use externalSourceId as WooCommerce product ID
            $wc_product = wc_get_product($external_id);
            if ($wc_product) {
                $sku = $wc_product->get_sku();
            }
        }
        
        $line_item = array(
            'taxClass' => '',
            'quantity' => $quantity,
            'productId' => $product['id'],
            'taxes' => array(),
            'totalTax' => $totalTax,
            'subtotalTax' => $subtotalTax,
            'metaData' => array(),
            'total' => $price,
            'parentName' => '',
            'variationId' => '',
            'subtotal' => $subtotal,
            'price' => $price,
            'name' => isset($product['name']) ? $product['name'] : '',
            'externalSourceId' => $external_id,
            'id' => '',
            'sku' => $sku
        );
        
        $line_items[] = $line_item;
    }
    
    // Add shipping as a separate line item if available
    if ($include_shipping && $shipping_total > 0) {
        $line_items[] = array(
            'taxClass' => '',
            'quantity' => 1,
            'productId' => 'shipping',
            'taxes' => array(),
            'totalTax' => round($shipping_tax, 2),
            'subtotalTax' => round($shipping_tax, 2),
            'metaData' => array(),
            'total' => $shipping_total,
            'parentName' => '',
            'variationId' => '',
            'subtotal' => $shipping_total,
            'price' => $shipping_total,
            'name' => 'Shipping',
            'externalSourceId' => 'shipping',
            'id' => '',
            'sku' => 'shipping'
        );
    }
    
    return $line_items;
} 