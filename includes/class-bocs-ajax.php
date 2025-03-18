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