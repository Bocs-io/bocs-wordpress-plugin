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

        // Add AJAX handlers
        add_action('wp_ajax_switch_bocs_subscription', array($this, 'switch_bocs_subscription'));
        add_action('wp_ajax_nopriv_switch_bocs_subscription', array($this, 'must_login_first'));

        // Add new AJAX handler
        add_action('wp_ajax_bocs_trigger_subscription_switched_email', array($this, 'trigger_subscription_switched_email'));
        
        // Add handler for sending email after product update
        add_action('wp_ajax_bocs_send_subscription_switched_email', array($this, 'trigger_subscription_switched_email'));
        add_action('wp_ajax_nopriv_bocs_send_subscription_switched_email', array($this, 'must_login_first'));

        // Add cancellation email trigger
        add_action('wp_ajax_bocs_trigger_subscription_cancelled_email', array($this, 'trigger_subscription_cancelled_email'));
        add_action('wp_ajax_nopriv_bocs_trigger_subscription_cancelled_email', array($this, 'must_login_first'));

        // Add pause email trigger
        add_action('wp_ajax_bocs_trigger_subscription_paused_email', array($this, 'trigger_subscription_paused_email'));
        add_action('wp_ajax_nopriv_bocs_trigger_subscription_paused_email', array($this, 'must_login_first'));

        // Add resume email trigger
        add_action('wp_ajax_bocs_trigger_subscription_resumed_email', array($this, 'trigger_subscription_resumed_email'));
        add_action('wp_ajax_nopriv_bocs_trigger_subscription_resumed_email', array($this, 'must_login_first'));

        // Add box updated email trigger
        add_action('wp_ajax_bocs_trigger_box_updated_email', array($this, 'trigger_box_updated_email'));
        add_action('wp_ajax_nopriv_bocs_trigger_box_updated_email', array($this, 'must_login_first'));

        // Add direct email fallback
        add_action('wp_ajax_bocs_direct_email_fallback', array($this, 'direct_email_fallback'));
        add_action('wp_ajax_nopriv_bocs_direct_email_fallback', array($this, 'must_login_first'));

        // Add product mappings update
        add_action('wp_ajax_bocs_update_product_mappings', array($this, 'update_product_mappings'));

        // Admin AJAX actions
        add_action('wp_ajax_bocs_add_product_mapping', array($this, 'add_product_mapping'));
        add_action('wp_ajax_bocs_remove_product_mapping', array($this, 'remove_product_mapping'));

        // Add direct pause subscription handler
        add_action('wp_ajax_bocs_pause_subscription', array($this, 'pause_subscription'));
        add_action('wp_ajax_nopriv_bocs_pause_subscription', array($this, 'must_login_first'));
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
                // error_log('BOCS AJAX Error - Invalid nonce for get_bocs_products');
                wp_send_json_error(array(
                    'message' => 'Security check failed',
                    'code' => 'invalid_nonce'
                ));
                return;
            }

            // Check if BOCS ID is provided
            if (!isset($_POST['bocs_id']) || empty($_POST['bocs_id'])) {
                // error_log('BOCS AJAX Error - Missing BOCS ID in request');
                wp_send_json_error(array(
                    'message' => 'BOCS ID is required',
                    'code' => 'missing_bocs_id'
                ));
                return;
            }

            // Sanitize input
            $bocs_id = sanitize_text_field($_POST['bocs_id']);

            // error_log('BOCS AJAX - Fetching products for BOCS ID: ' . $bocs_id);

            // Get products from API
            $response = $this->api->get_bocs_products($bocs_id);

            // Handle API errors
            if (is_wp_error($response)) {
                // error_log('BOCS AJAX Error - API Error: ' . $response->get_error_message());
                wp_send_json_error(array(
                    'message' => $response->get_error_message(),
                    'code' => $response->get_error_code()
                ));
                return;
            }

            // Check if we have products data
            if (!isset($response['data']) || !is_array($response['data'])) {
                // error_log('BOCS AJAX Error - Invalid products data structure received from API');
                wp_send_json_error(array(
                    'message' => 'Invalid products data received from API',
                    'code' => 'invalid_data_structure'
                ));
                return;
            }

            // error_log('BOCS AJAX Success - Returning ' . count($response['data']) . ' products');

            // Return success response
            wp_send_json_success(array(
                'message' => 'Products retrieved successfully',
                'data' => $response['data']
            ));

        } catch (Exception $e) {
            // error_log('BOCS AJAX Error - Unexpected error: ' . $e->getMessage());
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
            // Trigger email notification for the box update
            do_action('bocs_subscription_switched', $response['data'], '', '', true);

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

    /**
     * AJAX handler for switching a subscription's Bocs type
     *
     * Processes the request to change a subscription from one Bocs type to another.
     * Validates the request, sends the update to the Bocs API, and returns a success/error response.
     *
     * @since 1.0.0
     * @access public
     * @return void Sends JSON response
     */
    public function switch_bocs_subscription() {
        // Check nonce for security
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'switch-bocs-nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check for required fields
        if (!isset($_POST['subscription_id']) || !isset($_POST['bocs_id']) || !isset($_POST['frequency_id'])) {
            wp_send_json_error('Missing required fields');
            return;
        }

        $subscription_id = sanitize_text_field($_POST['subscription_id']);
        $bocs_id = sanitize_text_field($_POST['bocs_id']);
        $frequency_id = sanitize_text_field($_POST['frequency_id']);

        // Prepare API request
        $helper = new Bocs_Helper();
        $options = get_option('bocs_plugin_options');
        $headers = [];

        if (!empty($options['bocs_headers'])) {
            $headers = [
                'Organization' => $options['bocs_headers']['organization'] ?? '',
                'Store' => $options['bocs_headers']['store'] ?? '',
                'Authorization' => $options['bocs_headers']['authorization'] ?? '',
                'Content-Type' => 'application/json'
            ];
        }

        // API endpoint for updating subscription
        $url = BOCS_API_URL . 'subscriptions/' . $subscription_id;

        // Data to update - match the API's expected format exactly
        $data = [
            'bocsId' => $bocs_id,
            'frequencyId' => $frequency_id
        ];

        // Check if line items are provided (for custom Bocs)
        if (isset($_POST['line_items']) && !empty($_POST['line_items'])) {
            $line_items = json_decode(stripslashes($_POST['line_items']), true);

            // Validate line items
            if (is_array($line_items) && !empty($line_items)) {
                // Sanitize line items
                $sanitized_line_items = [];
                foreach ($line_items as $item) {
                    if (isset($item['productId']) && isset($item['quantity'])) {
                        $sanitized_line_items[] = [
                            'productId' => sanitize_text_field($item['productId']),
                            'quantity' => intval($item['quantity'])
                        ];
                    }
                }

                // Add to data if we have valid line items
                if (!empty($sanitized_line_items)) {
                    $data['lineItems'] = $sanitized_line_items;
                }
            }
        }

        // Log the request for debugging
        $helper->log('Switch Bocs request data: ' . json_encode($data), 'info');

        // Make API request
        $response = $helper->curl_request($url, 'PATCH', $data, $headers);

        // Check if response is a WP_Error
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }

        // Process response
        if (isset($response['code']) && $response['code'] === 200) {
            // Get subscription data via Bocs API
            // We'll use the helper to make API requests
            $helper = new Bocs_Helper();
            $options = get_option('bocs_plugin_options');
            $headers = [];

            if (!empty($options['bocs_headers'])) {
                $headers = [
                    'Organization' => $options['bocs_headers']['organization'] ?? '',
                    'Store' => $options['bocs_headers']['store'] ?? '',
                    'Authorization' => $options['bocs_headers']['authorization'] ?? '',
                    'Content-Type' => 'application/json'
                ];
            }

            // Fetch subscription details from Bocs API
            $url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
            $subscription_data = $helper->curl_request($url, 'GET', [], $headers);

            if (!is_wp_error($subscription_data) && isset($subscription_data['data'])) {
                // Trigger an action that can be hooked by email notifications
                do_action('bocs_subscription_switched', $subscription_data['data'], $bocs_id, $frequency_id);
            }

            wp_send_json_success('Subscription updated successfully');
        } else {
            $error_message = isset($response['message']) ? $response['message'] : 'Failed to update subscription';
            wp_send_json_error($error_message);
        }
    }

    /**
     * Handle unauthorized AJAX requests
     */
    public function must_login_first() {
        wp_send_json_error(array('message' => __('You must be logged in to perform this action.', 'bocs-wordpress')));
    }

    /**
     * AJAX handler for triggering subscription switched email
     */
    public function trigger_subscription_switched_email() {
        // Check security nonce - accept either 'security' (old format) or 'nonce' (new format)
        $has_valid_nonce = false;
        
        if (isset($_POST['security']) && wp_verify_nonce($_POST['security'], 'bocs-subscription-switched')) {
            $has_valid_nonce = true;
        } elseif (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'bocs-ajax-nonce')) {
            $has_valid_nonce = true;
        }
        
        if (!$has_valid_nonce) {
            wp_send_json_error('Invalid security token');
            return;
        }

        // Get subscription ID
        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : 0;
        if (empty($subscription_id)) {
            wp_send_json_error('Subscription ID is required');
            return;
        }

        // Check if this is a frequency update
        $is_frequency_update = isset($_POST['is_frequency_update']) ? (bool)$_POST['is_frequency_update'] : false;
        $is_box_update = ! $is_frequency_update;
        $frequency_id = isset($_POST['frequency_id']) ? sanitize_text_field($_POST['frequency_id']) : '';
        
        // Log the request for debugging
        $helper = new Bocs_Helper();
        $helper->log('Triggering subscription switched email', 'info', [
            'subscription_id' => $subscription_id,
            'is_frequency_update' => $is_frequency_update,
            'frequency_id' => $frequency_id,
            'method' => 'AJAX'
        ]);

        // Get subscription via Bocs API
        $options = get_option('bocs_plugin_options');
        $headers = [];

        if (!empty($options['bocs_headers'])) {
            $headers = [
                'Organization' => $options['bocs_headers']['organization'] ?? '',
                'Store' => $options['bocs_headers']['store'] ?? '',
                'Authorization' => $options['bocs_headers']['authorization'] ?? '',
                'Content-Type' => 'application/json'
            ];
        }

        // Fetch subscription details from Bocs API
        $url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
        $subscription_data = $helper->curl_request($url, 'GET', [], $headers);

        if (is_wp_error($subscription_data) || !isset($subscription_data['data'])) {
            wp_send_json_error('Failed to fetch subscription data from API');
            $helper->log('Failed to fetch subscription data from API', 'error', [
                'subscription_id' => $subscription_id,
                'error' => is_wp_error($subscription_data) ? $subscription_data->get_error_message() : 'Invalid response'
            ]);
            return;
        }

        $bocs_id = '';
        if (isset($subscription_data['data']['bocs']) && isset($subscription_data['data']['bocs']['id'])) {
            $bocs_id = $subscription_data['data']['bocs']['id'];
        }

        if(isset($subscription_data['data']['frequency']) && isset($subscription_data['data']['frequency']['id'])) {
            $frequency_id = $subscription_data['data']['frequency']['id'];
        }

        // Try direct email first
        $email_sent = false;
        try {
            if (class_exists('WC_Bocs_Email_Subscription_Switched')) {
                $email = new WC_Bocs_Email_Subscription_Switched();
                $email_sent = $email->trigger($subscription_data['data'], $bocs_id, $frequency_id, $is_box_update);
                $helper->log('Direct email result', 'info', [
                    'subscription_id' => $subscription_id,
                    'result' => $email_sent ? 'success' : 'failed'
                ]);
            }
        } catch (Exception $e) {
            $helper->log('Error in direct email', 'error', [
                'subscription_id' => $subscription_id,
                'error' => $e->getMessage()
            ]);
        }

        // If direct email failed, try action hook
        if (!$email_sent) {
            $helper->log('Trying action hook method', 'info', [
                'subscription_id' => $subscription_id
            ]);
            do_action('bocs_subscription_switched', $subscription_data['data'], $bocs_id, $frequency_id, $is_box_update);
        }

        // Try emergency email if needed
        if (!$email_sent) {
            $helper->log('Trying emergency email', 'info', [
                'subscription_id' => $subscription_id
            ]);
            $email_sent = $this->send_emergency_email($subscription_data['data'], 'frequency_update');
        }

        // Send success response
        wp_send_json_success('Subscription email triggered successfully');
    }

    /**
     * AJAX handler for triggering box updated email notification
     */
    public function trigger_box_updated_email() {
        try {
            // error_log('BOCS EMAIL DEBUG: Starting trigger_box_updated_email handler');

            // Check security nonce
            if (!isset($_POST['security'])) {
                // error_log('BOCS EMAIL DEBUG: Security token not set');
                wp_send_json_error(['message' => 'Security token missing']);
                return;
            }

            if (!wp_verify_nonce($_POST['security'], 'bocs-box-updated')) {
                // error_log('BOCS EMAIL DEBUG: Invalid nonce: ' . sanitize_text_field($_POST['security']));
                wp_send_json_error(['message' => 'Invalid security token']);
                return;
            }

            // Get subscription ID
            $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
            if (empty($subscription_id)) {
                // error_log('BOCS EMAIL DEBUG: Empty subscription ID');
                wp_send_json_error(['message' => 'Subscription ID is required']);
                return;
            }

            // error_log('BOCS EMAIL DEBUG: Processing email for subscription: ' . $subscription_id);

            // Get subscription via Bocs API
            $helper = new Bocs_Helper();
            $options = get_option('bocs_plugin_options');

            // Validate API headers
            if (empty($options['bocs_headers']) ||
                empty($options['bocs_headers']['organization']) ||
                empty($options['bocs_headers']['store']) ||
                empty($options['bocs_headers']['authorization'])) {
                // error_log('BOCS EMAIL DEBUG: Missing required API headers');
                wp_send_json_error(['message' => 'API configuration missing']);
                return;
            }

            $headers = [
                'Organization' => $options['bocs_headers']['organization'],
                'Store' => $options['bocs_headers']['store'],
                'Authorization' => $options['bocs_headers']['authorization'],
                'Content-Type' => 'application/json'
            ];

            // Fetch subscription details
            $url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
            // error_log('BOCS EMAIL DEBUG: Fetching subscription data from: ' . $url);

            $subscription_data = $helper->curl_request($url, 'GET', [], $headers);

            if (is_wp_error($subscription_data)) {
                // error_log('BOCS EMAIL DEBUG: API error: ' . $subscription_data->get_error_message());
                wp_send_json_error(['message' => 'Failed to fetch subscription data: ' . $subscription_data->get_error_message()]);
                return;
            }

            if (!isset($subscription_data['data'])) {
                // error_log('BOCS EMAIL DEBUG: Invalid API response: ' . json_encode($subscription_data));
                wp_send_json_error(['message' => 'Invalid subscription data received']);
                return;
            }

            // Verify WooCommerce is active
            if (!class_exists('WC_Emails')) {
                // error_log('BOCS EMAIL DEBUG: WooCommerce emails not available');
                // Don't return, try direct email instead
            }

            // Try direct email first
            $email_sent = false;

            try {
                if (class_exists('WC_Bocs_Email_Subscription_Switched')) {
                    $email = new WC_Bocs_Email_Subscription_Switched();
                    $email_sent = $email->trigger($subscription_data['data'], '', '', true);
                    // error_log('BOCS EMAIL DEBUG: Direct email result: ' . ($email_sent ? 'SUCCESS' : 'FAILED'));
                }
            } catch (Exception $e) {
                // error_log('BOCS EMAIL DEBUG: Error in direct email: ' . $e->getMessage());
            }

            // If direct email failed, try action hook
            if (!$email_sent) {
                // error_log('BOCS EMAIL DEBUG: Trying action hook method');
                do_action('bocs_subscription_switched', $subscription_data['data'], '', '', true);
            }

            // Try emergency email if needed
            if (!$email_sent) {
                // error_log('BOCS EMAIL DEBUG: Trying emergency email');
                $email_sent = $this->send_emergency_email($subscription_data['data'], 'box_update');
            }

            if ($email_sent) {
                wp_send_json_success(['message' => 'Email sent successfully']);
            } else {
                wp_send_json_error(['message' => 'Failed to send email through all methods']);
            }

        } catch (Exception $e) {
            // error_log('BOCS EMAIL CRITICAL ERROR: ' . $e->getMessage());
            // error_log('BOCS EMAIL CRITICAL ERROR: ' . $e->getTraceAsString());
            wp_send_json_error(['message' => 'Internal server error: ' . $e->getMessage()]);
        }
    }

    /**
     * Send emergency direct email when other methods fail
     *
     * @param array $subscription_data Subscription data
     * @param string $email_type The type of email (cancellation, box_update, paused, resumed)
     * @return bool Success or failure
     */
    private function send_emergency_email($subscription_data, $email_type = 'box_update') {
        error_log('BOCS EMAIL DEBUG: Attempting emergency direct email for type: ' . $email_type);

        // Get customer email
        $customer_email = '';
        if (isset($subscription_data['customer']) && isset($subscription_data['customer']['email'])) {
            $customer_email = $subscription_data['customer']['email'];
        } elseif (isset($subscription_data['billing']) && isset($subscription_data['billing']['email'])) {
            $customer_email = $subscription_data['billing']['email'];
        } elseif (isset($subscription_data['user']) && isset($subscription_data['user']['email'])) {
            $customer_email = $subscription_data['user']['email'];
        } elseif (isset($subscription_data['email'])) {
            $customer_email = $subscription_data['email'];
        } else {
            error_log('BOCS EMAIL DEBUG: No email address found in subscription data');
            return false;
        }

        error_log('BOCS EMAIL DEBUG: Emergency email to: ' . $customer_email);

        // Make sure we have WordPress mail function
        if (function_exists('wp_mail')) {
            // Set email content based on type
            $subject = '';
            $message = '';

            switch ($email_type) {
                case 'cancellation':
                    $subject = 'Your subscription has been cancelled';
                    $message = "Hello,\n\nYour subscription has been cancelled as requested.\n\n";

                    // Add subscription details
                    if (isset($subscription_data['id'])) {
                        $message .= "Subscription ID: " . $subscription_data['id'] . "\n";
                    }
                    break;

                case 'frequency_update':
                    $subject = 'Your subscription frequency has been updated';
                    $message = "Hello,\n\nYour subscription frequency has been updated successfully.\n\n";
                    
                    // Add subscription details
                    if (isset($subscription_data['id'])) {
                        $message .= "Subscription ID: " . $subscription_data['id'] . "\n";
                    }
                    
                    // Add frequency details if available
                    if (isset($subscription_data['frequency']) && isset($subscription_data['frequency']['frequency']) && isset($subscription_data['frequency']['timeUnit'])) {
                        $freq = $subscription_data['frequency']['frequency'];
                        $unit = $subscription_data['frequency']['timeUnit'];
                        $message .= "New Frequency: Every " . $freq . " " . $unit . "(s)\n";
                        
                        // Add discount info if available
                        if (isset($subscription_data['frequency']['discount']) && $subscription_data['frequency']['discount'] > 0) {
                            $discount = $subscription_data['frequency']['discount'];
                            $discount_type = isset($subscription_data['frequency']['discountType']) ? $subscription_data['frequency']['discountType'] : 'percent';
                            
                            if ($discount_type === 'percent') {
                                $message .= "Discount: " . $discount . "% off\n";
                            } else {
                                $message .= "Discount: $" . $discount . " off\n";
                            }
                        }
                    }
                    
                    // Add next payment date if available
                    if (isset($subscription_data['nextPaymentDateGmt'])) {
                        $next_date = new DateTime($subscription_data['nextPaymentDateGmt']);
                        $message .= "Next Payment Date: " . $next_date->format('F j, Y') . "\n";
                    }
                    break;

                case 'paused':
                    $subject = 'Your subscription has been paused';
                    $message = "Hello,\n\nYour subscription has been paused. You won't be charged until you decide to resume.\n\n";

                    // Add subscription details
                    if (isset($subscription_data['id'])) {
                        $message .= "Subscription ID: " . $subscription_data['id'] . "\n";
                    }
                    break;

                case 'resumed':
                    $subject = 'Your subscription has been resumed';
                    $message = "Hello,\n\nYour subscription has been resumed. Your next payment has been scheduled.\n\n";

                    // Add subscription details
                    if (isset($subscription_data['id'])) {
                        $message .= "Subscription ID: " . $subscription_data['id'] . "\n";
                    }

                    // Add next payment date if available
                    if (isset($subscription_data['nextPaymentDateGmt'])) {
                        $next_date = new DateTime($subscription_data['nextPaymentDateGmt']);
                        $message .= "Next Payment Date: " . $next_date->format('F j, Y') . "\n";
                    }
                    break;

                case 'box_update':
                default:
                    $subject = 'Your subscription box has been updated';
                    $message = "Hello,\n\nYour subscription box contents have been updated successfully.\n\n";

                    // Add subscription details
                    if (isset($subscription_data['id'])) {
                        $message .= "Subscription ID: " . $subscription_data['id'] . "\n";
                    }

                    // Add item details
                    if (isset($subscription_data['lineItems']) && is_array($subscription_data['lineItems'])) {
                        $message .= "\nYour updated box contains:\n";
                        foreach ($subscription_data['lineItems'] as $item) {
                            if (isset($item['name']) && isset($item['quantity'])) {
                                $message .= "- " . $item['quantity'] . "x " . $item['name'] . "\n";
                            }
                        }
                    }
                    break;
            }

            // Set up proper headers
            $site_name = get_bloginfo('name');
            $admin_email = get_option('admin_email');
            $headers = [
                'Content-Type: text/plain; charset=UTF-8',
                'From: ' . $site_name . ' <' . $admin_email . '>'
            ];

            error_log('BOCS EMAIL DEBUG: Sending with headers: ' . implode(', ', $headers));

            // Send the direct email
            $mail_result = wp_mail($customer_email, $subject, $message, $headers);
            error_log('BOCS EMAIL DEBUG: Emergency wp_mail result: ' . ($mail_result ? 'SUCCESS' : 'FAILED'));

            // If WordPress mail fails, try PHP mail directly
            if (!$mail_result && function_exists('mail')) {
                error_log('BOCS EMAIL DEBUG: WordPress mail failed, trying PHP mail directly');
                $header_str = implode("\r\n", $headers);
                $mail_result = mail($customer_email, $subject, $message, $header_str);
                error_log('BOCS EMAIL DEBUG: Emergency PHP mail result: ' . ($mail_result ? 'SUCCESS' : 'FAILED'));
            }

            return $mail_result;
        }

        error_log('BOCS EMAIL DEBUG: WordPress mail function not available');
        return false;
    }

    /**
     * AJAX handler for triggering subscription cancelled email notification
     */
    public function trigger_subscription_cancelled_email() {
        error_log('BOCS EMAIL: Starting cancelled email handler');

        try {
            // Check security nonce
            if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'bocs-subscription-cancelled')) {
                error_log('BOCS EMAIL: Invalid security token');
                wp_send_json_error('Invalid security token');
                return;
            }

            // Get subscription ID and cancellation reason
            $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
            $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';

            if (empty($subscription_id)) {
                error_log('BOCS EMAIL: Missing subscription ID');
                wp_send_json_error('Missing subscription ID');
                return;
            }

            error_log('BOCS EMAIL: Processing subscription ID: ' . $subscription_id);
            error_log('BOCS EMAIL: Cancellation reason: ' . $reason);

            // Get subscription via Bocs API
            $helper = new Bocs_Helper();
            $options = get_option('bocs_plugin_options');
            $headers = [];

            if (!empty($options['bocs_headers'])) {
                $headers = [
                    'Organization' => $options['bocs_headers']['organization'] ?? '',
                    'Store' => $options['bocs_headers']['store'] ?? '',
                    'Authorization' => $options['bocs_headers']['authorization'] ?? '',
                    'Content-Type' => 'application/json'
                ];
            }

            // Fetch subscription details from Bocs API
            $url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
            error_log('BOCS EMAIL: Fetching subscription data from API: ' . $url);

            $subscription_data = $helper->curl_request($url, 'GET', [], $headers);

            if (is_wp_error($subscription_data)) {
                error_log('BOCS EMAIL: API error: ' . $subscription_data->get_error_message());
                wp_send_json_error('Failed to fetch subscription data from API');
                return;
            }

            if (!isset($subscription_data['data'])) {
                error_log('BOCS EMAIL: Invalid API response - no data key');
                wp_send_json_error('Invalid subscription data from API');
                return;
            }

            error_log('BOCS EMAIL: Successfully retrieved subscription data');

            // Try direct email first
            $email_sent = false;

            try {
                if (class_exists('WC_Bocs_Email_Subscription_Cancelled')) {
                    $email = new WC_Bocs_Email_Subscription_Cancelled();
                    $email_sent = $email->trigger($subscription_data['data'], $reason);
                    error_log('BOCS EMAIL: Direct email result: ' . ($email_sent ? 'SUCCESS' : 'FAILED'));
                } else {
                    error_log('BOCS EMAIL: WC_Bocs_Email_Subscription_Cancelled class not found');
                }
            } catch (Exception $e) {
                error_log('BOCS EMAIL: Error in direct email: ' . $e->getMessage());
            }

            // If direct email failed, try action hook
            if (!$email_sent) {
                error_log('BOCS EMAIL: Trying action hook method');
                do_action('bocs_subscription_cancelled', $subscription_data['data'], $reason);
            }

            // Try emergency email if needed
            if (!$email_sent) {
                error_log('BOCS EMAIL: Trying emergency email');
                $email_sent = $this->send_emergency_email($subscription_data['data'], 'cancellation');
            }

            if ($email_sent) {
                wp_send_json_success('Email notification triggered successfully');
            } else {
                wp_send_json_error('Failed to send email through all methods');
            }

        } catch (Exception $e) {
            error_log('BOCS EMAIL: Critical error in trigger_subscription_cancelled_email: ' . $e->getMessage());
            error_log('BOCS EMAIL: Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error('Internal server error');
        }
    }

    /**
     * AJAX handler for triggering subscription paused email notification
     */
    public function trigger_subscription_paused_email() {
        error_log('BOCS EMAIL: Starting paused email handler');

        try {
            // Check security nonce
            if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'bocs-subscription-paused')) {
                error_log('BOCS EMAIL: Invalid security token');
                wp_send_json_error('Invalid security token');
                return;
            }

            // Get subscription ID and pause reason
            $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
            $pause_reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';

            if (empty($subscription_id)) {
                error_log('BOCS EMAIL: Missing subscription ID');
                wp_send_json_error('Missing subscription ID');
                return;
            }

            error_log('BOCS EMAIL: Processing subscription ID: ' . $subscription_id);
            error_log('BOCS EMAIL: Pause reason: ' . $pause_reason);

            // Get subscription via Bocs API
            $helper = new Bocs_Helper();
            $options = get_option('bocs_plugin_options');
            $headers = [];

            if (!empty($options['bocs_headers'])) {
                $headers = [
                    'Organization' => $options['bocs_headers']['organization'] ?? '',
                    'Store' => $options['bocs_headers']['store'] ?? '',
                    'Authorization' => $options['bocs_headers']['authorization'] ?? '',
                    'Content-Type' => 'application/json'
                ];
            }

            // Fetch subscription details from Bocs API
            $url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
            error_log('BOCS EMAIL: Fetching subscription data from API: ' . $url);

            $subscription_response = $helper->curl_request($url, 'GET', [], $headers);

            if (is_wp_error($subscription_response)) {
                error_log('BOCS EMAIL: API error: ' . $subscription_response->get_error_message());
                wp_send_json_error('Failed to fetch subscription data from API');
                return;
            }

            if (!isset($subscription_response['data'])) {
                error_log('BOCS EMAIL: Invalid API response - no data key');
                wp_send_json_error('Invalid subscription data from API');
                return;
            }

            $subscription_data = $subscription_response['data'];
            error_log('BOCS EMAIL: Successfully retrieved subscription data');

            // Try direct email first
            $email_sent = false;

            try {
                if (class_exists('WC_Bocs_Email_Subscription_Paused')) {
                    $email = new WC_Bocs_Email_Subscription_Paused();
                    $email_sent = $email->trigger($subscription_data, $pause_reason);
                    error_log('BOCS EMAIL: Direct email result: ' . ($email_sent ? 'SUCCESS' : 'FAILED'));
                } else {
                    error_log('BOCS EMAIL: WC_Bocs_Email_Subscription_Paused class not found');
                }
            } catch (Exception $e) {
                error_log('BOCS EMAIL: Error in direct email: ' . $e->getMessage());
            }

            // If direct email failed, try action hook
            if (!$email_sent) {
                error_log('BOCS EMAIL: Trying action hook method');
                do_action('bocs_subscription_paused', $subscription_data, $pause_reason);
            }

            // Try emergency email if needed
            if (!$email_sent) {
                error_log('BOCS EMAIL: Trying emergency email');
                $email_sent = $this->send_emergency_email($subscription_data, 'paused');
            }

            if ($email_sent) {
                wp_send_json_success('Email notification triggered successfully');
            } else {
                wp_send_json_error('Failed to send email through all methods');
            }

        } catch (Exception $e) {
            error_log('BOCS EMAIL: Critical error in trigger_subscription_paused_email: ' . $e->getMessage());
            error_log('BOCS EMAIL: Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error('Internal server error');
        }
    }

    /**
     * AJAX handler for triggering subscription resumed email notification
     */
    public function trigger_subscription_resumed_email() {
        error_log('BOCS EMAIL: Starting resumed email handler');

        try {
            // Check security nonce
            if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'bocs-subscription-resumed')) {
                error_log('BOCS EMAIL: Invalid security token');
                wp_send_json_error('Invalid security token');
                return;
            }

            // Get subscription ID
            $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
            if (empty($subscription_id)) {
                error_log('BOCS EMAIL: Missing subscription ID');
                wp_send_json_error('Subscription ID is required');
                return;
            }

            // Get resume reason if provided
            $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';

            error_log('BOCS EMAIL: Processing subscription ID: ' . $subscription_id);
            error_log('BOCS EMAIL: Resume reason: ' . $reason);

            // Get subscription via Bocs API
            $helper = new Bocs_Helper();
            $options = get_option('bocs_plugin_options');
            $headers = [];

            if (!empty($options['bocs_headers'])) {
                $headers = [
                    'Organization' => $options['bocs_headers']['organization'] ?? '',
                    'Store' => $options['bocs_headers']['store'] ?? '',
                    'Authorization' => $options['bocs_headers']['authorization'] ?? '',
                    'Content-Type' => 'application/json'
                ];
            }

            // Fetch subscription details from Bocs API
            $url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
            error_log('BOCS EMAIL: Fetching subscription data from API: ' . $url);

            $subscription_data = $helper->curl_request($url, 'GET', [], $headers);

            if (is_wp_error($subscription_data)) {
                error_log('BOCS EMAIL: API error: ' . $subscription_data->get_error_message());
                wp_send_json_error('Failed to fetch subscription data from API');
                return;
            }

            if (!isset($subscription_data['data'])) {
                error_log('BOCS EMAIL: Invalid API response - no data key');
                wp_send_json_error('Invalid subscription data from API');
                return;
            }

            error_log('BOCS EMAIL: Successfully retrieved subscription data');

            // Try direct email first
            $email_sent = false;

            try {
                if (class_exists('WC_Bocs_Email_Subscription_Reactivated')) {
                    $email = new WC_Bocs_Email_Subscription_Reactivated();
                    $email_sent = $email->trigger($subscription_data['data'], $reason);
                    error_log('BOCS EMAIL: Direct email result: ' . ($email_sent ? 'SUCCESS' : 'FAILED'));
                } else {
                    error_log('BOCS EMAIL: WC_Bocs_Email_Subscription_Reactivated class not found');
                }
            } catch (Exception $e) {
                error_log('BOCS EMAIL: Error in direct email: ' . $e->getMessage());
            }

            // If direct email failed, try action hook
            if (!$email_sent) {
                error_log('BOCS EMAIL: Trying action hook method');
                do_action('bocs_subscription_resumed', $subscription_data['data'], $reason);
            }

            // Try emergency email if needed
            if (!$email_sent) {
                error_log('BOCS EMAIL: Trying emergency email');
                $email_sent = $this->send_emergency_email($subscription_data['data'], 'resumed');
            }

            if ($email_sent) {
                wp_send_json_success('Subscription reactivated email triggered successfully');
            } else {
                wp_send_json_error('Failed to send email through all methods');
            }

        } catch (Exception $e) {
            error_log('BOCS EMAIL: Critical error in trigger_subscription_resumed_email: ' . $e->getMessage());
            error_log('BOCS EMAIL: Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error('Internal server error');
        }
    }

    /**
     * AJAX handler for pausing a subscription
     */
    public function pause_subscription() {
        // Log the request for debugging
        if (function_exists('bocs_log')) {
            bocs_log('Pause subscription AJAX request received in class-bocs-ajax.php', 'debug', array(
                'post_data' => $_POST,
                'nonce_exists' => isset($_POST['nonce']),
                'nonce_value' => isset($_POST['nonce']) ? substr($_POST['nonce'], 0, 5) . '...' : 'not set'
            ));
        }

        // Check security nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bocs-ajax-nonce')) {
            // Log the nonce verification failure
            if (function_exists('bocs_log')) {
                bocs_log('Pause subscription nonce verification failed in class-bocs-ajax.php', 'error', array(
                    'nonce_exists' => isset($_POST['nonce']),
                    'nonce_value' => isset($_POST['nonce']) ? substr($_POST['nonce'], 0, 5) . '...' : 'not set',
                    'expected_nonce_action' => 'bocs-ajax-nonce'
                ));
            }

            wp_send_json_error(array(
                'message' => 'Invalid security token'
            ));
            return;
        }

        // Check subscription ID
        if (!isset($_POST['subscription_id']) || empty($_POST['subscription_id'])) {
            wp_send_json_error(array(
                'message' => 'Subscription ID is required'
            ));
            return;
        }

        $subscription_id = sanitize_text_field($_POST['subscription_id']);

        // Get options - try both option names since there seems to be inconsistency
        $options = get_option('bocs_options', array());
        $plugin_options = get_option('bocs_plugin_options', array());

        // Check if we have headers in either option
        $headers = array();

        if (isset($options['bocs_headers']) && !empty($options['bocs_headers'])) {
            $headers = $options['bocs_headers'];
        } elseif (isset($plugin_options['bocs_headers']) && !empty($plugin_options['bocs_headers'])) {
            $headers = $plugin_options['bocs_headers'];
        } else {
            wp_send_json_error(array(
                'message' => 'API headers not configured'
            ));
            return;
        }

        // Log the headers we're using
        if (function_exists('bocs_log')) {
            bocs_log('Pause subscription headers', 'info', array(
                'headers' => $headers
            ));
        }

        // Log the request
        if (function_exists('bocs_log')) {
            bocs_log('Pause subscription AJAX request', 'info', array(
                'subscription_id' => $subscription_id
            ));
        }

        // Make sure the Bocs_Helper class is loaded
        if (!class_exists('Bocs_Helper')) {
            require_once(plugin_dir_path(dirname(__FILE__)) . 'includes/Bocs_Helper.php');
        }

        // Make API request
        $helper = new Bocs_Helper();
        $url = BOCS_API_URL . 'subscriptions/' . $subscription_id . '/pause';

        // Ensure we have all required headers
        $api_headers = array(
            'Organization' => $headers['organization'] ?? $headers['Organization'] ?? '',
            'Store' => $headers['store'] ?? $headers['Store'] ?? '',
            'Authorization' => $headers['authorization'] ?? $headers['Authorization'] ?? '',
            'Content-Type' => 'application/json'
        );

        // Make sure we have all required headers
        if (empty($api_headers['Organization']) || empty($api_headers['Store']) || empty($api_headers['Authorization'])) {
            // Try to get from plugin options directly
            $plugin_options = get_option('bocs_plugin_options', array());
            if (!empty($plugin_options['bocs_headers'])) {
                $api_headers['Organization'] = $plugin_options['bocs_headers']['organization'] ?? $plugin_options['bocs_headers']['Organization'] ?? '';
                $api_headers['Store'] = $plugin_options['bocs_headers']['store'] ?? $plugin_options['bocs_headers']['Store'] ?? '';
                $api_headers['Authorization'] = $plugin_options['bocs_headers']['authorization'] ?? $plugin_options['bocs_headers']['Authorization'] ?? '';
            }
        }

        // Log the API request
        if (function_exists('bocs_log')) {
            bocs_log('Pause subscription API request', 'info', array(
                'url' => $url,
                'method' => 'PUT',
                'headers' => $api_headers
            ));
        }

        $response = $helper->curl_request($url, 'PUT', array(), $api_headers);

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => $response->get_error_message()
            ));
            return;
        }

        // Log the response
        if (function_exists('bocs_log')) {
            bocs_log('Pause subscription AJAX response', 'info', array(
                'response' => $response
            ));
        }

        wp_send_json_success(array(
            'message' => 'Subscription paused successfully',
            'data' => $response
        ));
    }

    /**
     * AJAX handler for direct email fallback
     */
    public function direct_email_fallback() {
        // error_log('BOCS DIRECT EMAIL: Direct email fallback AJAX handler called');

        // Check security nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'bocs-direct-email')) {
            // error_log('BOCS DIRECT EMAIL: Invalid security token in direct_email_fallback');
            wp_send_json_error('Invalid security token');
            return;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            // error_log('BOCS DIRECT EMAIL: User not logged in');
            wp_send_json_error(array('message' => __('You must be logged in to perform this action.', 'bocs-wordpress')));
            return;
        }

        // Get subscription ID
        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
        if (empty($subscription_id)) {
            // error_log('BOCS DIRECT EMAIL: Invalid subscription ID');
            wp_send_json_error(array('message' => __('Invalid subscription ID.', 'bocs-wordpress')));
            return;
        }

        // error_log('BOCS DIRECT EMAIL: Processing for subscription ID: ' . $subscription_id);

        // Get subscription via Bocs API
        $helper = new Bocs_Helper();
        $options = get_option('bocs_plugin_options');
        $headers = [];

        if (!empty($options['bocs_headers'])) {
            $headers = [
                'Organization' => $options['bocs_headers']['organization'] ?? '',
                'Store' => $options['bocs_headers']['store'] ?? '',
                'Authorization' => $options['bocs_headers']['authorization'] ?? '',
                'Content-Type' => 'application/json'
            ];
        }

        // Fetch subscription details from Bocs API
        $url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
        // error_log('BOCS DIRECT EMAIL: Fetching subscription data from API: ' . $url);
        $subscription_data = $helper->curl_request($url, 'GET', [], $headers);

        if (is_wp_error($subscription_data)) {
            // error_log('BOCS DIRECT EMAIL: API error: ' . $subscription_data->get_error_message());
            wp_send_json_error('Failed to fetch subscription data from API');
            return;
        }

        if (!isset($subscription_data['data'])) {
            // error_log('BOCS DIRECT EMAIL: No subscription data returned from API');
            wp_send_json_error('Failed to fetch subscription data from API');
            return;
        }

        // error_log('BOCS DIRECT EMAIL: Successfully retrieved subscription data');

        // Get customer email
        $customer_email = '';
        if (isset($subscription_data['data']['customer']) && isset($subscription_data['data']['customer']['email'])) {
            $customer_email = $subscription_data['data']['customer']['email'];
            // error_log('BOCS DIRECT EMAIL: Customer email found: ' . $customer_email);
        } else {
            // error_log('BOCS DIRECT EMAIL: No customer email found in subscription data');
            wp_send_json_error('No customer email found');
            return;
        }

        // Attempt the WC method first
        do_action('bocs_subscription_switched', $subscription_data['data'], '', '', true);
        // error_log('BOCS DIRECT EMAIL: Called bocs_subscription_switched action');

        // EMERGENCY: Send mail using all possible methods
        $this->emergency_mail_test($customer_email, $subscription_data['data']);

        // Direct mail fallback
        if (function_exists('wp_mail')) {
            // error_log('BOCS DIRECT EMAIL: Attempting direct wp_mail');

            // Basic email content
            $subject = 'Your box contents have been updated';
            $message = "Hello,\n\nYour Bocs box contents have been updated successfully.\n\n";

            // Add subscription details if available
            if (isset($subscription_data['data']['id'])) {
                $message .= "Subscription ID: " . $subscription_data['data']['id'] . "\n";
            }

            if (isset($subscription_data['data']['bocs']['name'])) {
                $message .= "Box Type: " . $subscription_data['data']['bocs']['name'] . "\n\n";
            }

            $message .= "Thank you for choosing Bocs!\n";

            // Send the direct email
            $headers = ['Content-Type: text/plain; charset=UTF-8'];

            // Add sender information
            $site_name = get_bloginfo('name');
            $admin_email = get_option('admin_email');
            $headers[] = 'From: ' . $site_name . ' <' . $admin_email . '>';

            // error_log('BOCS DIRECT EMAIL: Sending to: ' . $customer_email);
            // error_log('BOCS DIRECT EMAIL: Using headers: ' . implode(', ', $headers));

            $mail_result = wp_mail($customer_email, $subject, $message, $headers);

            // error_log('BOCS DIRECT EMAIL: Direct wp_mail result: ' . ($mail_result ? 'SUCCESS' : 'FAILED'));

            if ($mail_result) {
                wp_send_json_success('Box updated email sent via direct wp_mail');
                return;
            }
        } else {
            // error_log('BOCS DIRECT EMAIL: wp_mail function not available');
        }

        wp_send_json_error('Failed to send email via any method');
    }

    /**
     * Emergency mail test using all possible methods
     *
     * @param string $recipient Recipient email address
     * @param array $subscription_data Subscription data
     * @return void
     */
    private function emergency_mail_test($recipient, $subscription_data) {
        // error_log('BOCS EMERGENCY MAIL: Starting emergency mail test');

        // Set up test message
        $subject = '[URGENT BOCS TEST] Mail System Test';
        $message = "This is an emergency mail test from the BOCS plugin.\n\n";
        $message .= "If you're receiving this, please notify the developer that mail is working via this method.\n\n";
        $message .= "Subscription ID: " . ($subscription_data['id'] ?? 'Unknown') . "\n";
        $message .= "Customer Email: " . $recipient . "\n";
        $message .= "Test Time: " . date('Y-m-d H:i:s') . "\n";

        // Add server information
        $message .= "\nServer Information:\n";
        $message .= "PHP Version: " . PHP_VERSION . "\n";
        $message .= "WordPress Version: " . get_bloginfo('version') . "\n";
        $message .= "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";

        // Basic headers
        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
        ];

        // Add sender information
        $site_name = get_bloginfo('name');
        $admin_email = get_option('admin_email');
        $headers[] = 'From: ' . $site_name . ' <' . $admin_email . '>';

        // Get a test recipient (admin email)
        $test_recipient = $admin_email;

        // METHOD 1: WordPress mail
        // error_log('BOCS EMERGENCY MAIL: Testing WordPress mail function to admin: ' . $test_recipient);
        if (function_exists('wp_mail')) {
            $wp_mail_result = wp_mail($test_recipient, $subject, $message, $headers);
            // error_log('BOCS EMERGENCY MAIL: wp_mail result: ' . ($wp_mail_result ? 'SUCCESS' : 'FAILED'));
        } else {
            // error_log('BOCS EMERGENCY MAIL: wp_mail function not available');
        }

        // METHOD 2: PHP mail function
        if (function_exists('mail')) {
            // error_log('BOCS EMERGENCY MAIL: Testing PHP mail function');
            $header_str = implode("\r\n", $headers);
            $php_mail_result = mail($test_recipient, $subject, $message, $header_str);
            // error_log('BOCS EMERGENCY MAIL: PHP mail result: ' . ($php_mail_result ? 'SUCCESS' : 'FAILED'));

            // Also try the customer email
            $customer_php_mail = mail($recipient, $subject, $message, $header_str);
            // error_log('BOCS EMERGENCY MAIL: PHP mail to customer result: ' . ($customer_php_mail ? 'SUCCESS' : 'FAILED'));
        } else {
            // error_log('BOCS EMERGENCY MAIL: PHP mail function not available');
        }

        // METHOD 3: Try using WP PHPMailer directly
        // error_log('BOCS EMERGENCY MAIL: Testing PHPMailer directly');
        try {
            // Try to use WordPress PHPMailer
            global $phpmailer;

            // Initialize if not set
            if (!is_object($phpmailer) || !($phpmailer instanceof PHPMailer\PHPMailer\PHPMailer)) {
                require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
                require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
                require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
                $phpmailer = new PHPMailer\PHPMailer\PHPMailer(true);
            }

            // Clear all recipients and previous data
            $phpmailer->clearAllRecipients();
            $phpmailer->clearAttachments();
            $phpmailer->clearCustomHeaders();
            $phpmailer->clearReplyTos();

            // Set up mailer
            $phpmailer->isMail();
            $phpmailer->CharSet = 'UTF-8';
            $phpmailer->From = $admin_email;
            $phpmailer->FromName = $site_name;
            $phpmailer->Subject = $subject . ' (Direct PHPMailer)';
            $phpmailer->Body = $message . "\n\nSent using direct PHPMailer";
            $phpmailer->addAddress($test_recipient);

            // Send mail
            $phpmailer_result = $phpmailer->send();
            // error_log('BOCS EMERGENCY MAIL: PHPMailer direct result: ' . ($phpmailer_result ? 'SUCCESS' : 'FAILED'));
        } catch (Exception $e) {
            // error_log('BOCS EMERGENCY MAIL: PHPMailer error: ' . $e->getMessage());
        }

        // Log mail configuration for debugging
        // error_log('BOCS EMERGENCY MAIL: Mail configuration:');
        // error_log('BOCS EMERGENCY MAIL: PHP mail enabled: ' . (function_exists('mail') ? 'Yes' : 'No'));
        // error_log('BOCS EMERGENCY MAIL: sendmail_path: ' . ini_get('sendmail_path'));
        // error_log('BOCS EMERGENCY MAIL: SMTP settings: ' . ini_get('SMTP') . ':' . ini_get('smtp_port'));

        // Check for mail plugins that might be interfering
        $active_plugins = get_option('active_plugins');
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'mail') !== false || strpos($plugin, 'smtp') !== false) {
                // error_log('BOCS EMERGENCY MAIL: Possible mail plugin detected: ' . $plugin);
            }
        }
    }

    /**
     * Handle AJAX request to update subscription products
     */
    public function update_subscription_products() {
        check_ajax_referer('bocs_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in to update your subscription.', 'bocs-wordpress')]);
            return;
        }

        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
        $products_json = isset($_POST['products']) ? sanitize_text_field($_POST['products']) : '';

        if (empty($subscription_id)) {
            wp_send_json_error(['message' => __('Subscription ID is required.', 'bocs-wordpress')]);
            return;
        }

        if (empty($products_json)) {
            wp_send_json_error(['message' => __('No products provided.', 'bocs-wordpress')]);
            return;
        }

        // Decode products JSON
        $products = json_decode($products_json, true);
        if (!$products || !is_array($products)) {
            wp_send_json_error(['message' => __('Invalid products data.', 'bocs-wordpress')]);
            return;
        }


        // Check if products have complete data (with all required fields)
        $has_complete_data = true;
        $preserve_all_fields = isset($_POST['preserve_all_fields']) && $_POST['preserve_all_fields'] === 'true';

        if (isset($products[0])) {
            $required_fields = ['id', 'productId', 'quantity', 'name', 'price', 'subtotal', 'total', 'taxClass', 'taxes', 'totalTax', 'subtotalTax', 'metaData', 'parentName', 'variationId'];
            foreach ($required_fields as $field) {
                if (!isset($products[0][$field])) {
                    $has_complete_data = false;
                    break;
                }
            }
        }

        // For now, just pass the products directly to the API
        if ($has_complete_data || $preserve_all_fields) {
            $api = new BOCS_API();
            $response = $api->update_subscription_products($subscription_id, ['lineItems' => $products]);
        } else {
            $api = new BOCS_API();
            $response = $api->update_subscription_products($subscription_id, $products);
        }

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            wp_send_json_error(['message' => $error_message]);
            return;
        }

        // Check for API error
        if (isset($response['code']) && $response['code'] != 200) {
            $error_message = isset($response['message']) ? $response['message'] : 'Unknown API error';
            wp_send_json_error(['message' => $error_message]);
            return;
        }

        // Success
        wp_send_json_success(['message' => __('Subscription products updated successfully.', 'bocs-wordpress')]);
    }

    /**
     * AJAX handler for updating product mappings
     *
     * Updates the product mapping option with mappings from the frontend
     */
    public function update_product_mappings() {
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bocs_update_mappings_nonce')) {
            wp_send_json_error(['message' => 'Invalid security token']);
            return;
        }

        // Check user permissions - allow any logged in user
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'You must be logged in to update product mappings']);
            return;
        }

        // Get mappings from request
        if (!isset($_POST['mappings']) || empty($_POST['mappings'])) {
            wp_send_json_error(['message' => 'No mappings provided']);
            return;
        }

        $mappings = json_decode(stripslashes($_POST['mappings']), true);

        if (!is_array($mappings)) {
            wp_send_json_error(['message' => 'Invalid mappings format']);
            return;
        }

        // Clean up mappings - ensure all values are strings
        $product_mapping = [];
        foreach ($mappings as $bocs_id => $wc_id) {
            if (!empty($bocs_id) && !empty($wc_id)) {
                $product_mapping[$bocs_id] = (string)$wc_id;
            }
        }

        // Get existing mappings
        $existing_mappings = get_option('bocs_product_mapping', []);

        // Check if anything has actually changed
        $has_changes = false;
        foreach ($product_mapping as $bocs_id => $wc_id) {
            if (!isset($existing_mappings[$bocs_id]) || $existing_mappings[$bocs_id] !== $wc_id) {
                $has_changes = true;
                break;
            }
        }

        // Merge with existing mappings (prioritize new mappings)
        $updated_mappings = array_merge($existing_mappings, $product_mapping);

        // If nothing changed, return success anyway
        if (!$has_changes) {
            error_log('BOCS: No changes detected in product mappings.');
            wp_send_json_success(['message' => 'Product mappings are up to date']);
            return;
        }

        // Save to options with a force update flag (true as third parameter)
        $result = update_option('bocs_product_mapping', $updated_mappings, false);

        if ($result) {
            // Log the update
            error_log('BOCS: Updated product mappings. ' . count($product_mapping) . ' mappings processed.');
            wp_send_json_success(['message' => 'Product mappings updated successfully']);
        } else {
            // Log detailed error information
            error_log('BOCS Error: Failed to update product mappings. ' .
                      'Updated mappings count: ' . count($updated_mappings) .
                      ', Option type: ' . gettype($updated_mappings));
            wp_send_json_error(['message' => 'Failed to update product mappings']);
        }
    }

    /**
     * AJAX handler for adding a product mapping in admin
     */
    public function add_product_mapping() {
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bocs_admin_nonce')) {
            wp_send_json_error(['message' => 'Invalid security token']);
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'You do not have permission to manage product mappings']);
            return;
        }

        // Get mapping data
        if (!isset($_POST['bocs_id']) || empty($_POST['bocs_id']) || !isset($_POST['wc_id']) || empty($_POST['wc_id'])) {
            wp_send_json_error(['message' => 'Missing required fields']);
            return;
        }

        $bocs_id = sanitize_text_field($_POST['bocs_id']);
        $wc_id = sanitize_text_field($_POST['wc_id']);

        // Validate WooCommerce product ID
        $product = wc_get_product($wc_id);
        if (!$product) {
            wp_send_json_error(['message' => 'Invalid WooCommerce product ID']);
            return;
        }

        // Get existing mappings
        $product_mapping = get_option('bocs_product_mapping', []);

        // Add new mapping
        $product_mapping[$bocs_id] = (string)$wc_id;

        // Save to options
        $result = update_option('bocs_product_mapping', $product_mapping);

        if ($result) {
            // Log the update
            error_log('BOCS Admin: Added product mapping ' . $bocs_id . ' -> ' . $wc_id);
            wp_send_json_success(['message' => 'Product mapping added successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to add product mapping']);
        }
    }

    /**
     * AJAX handler for removing a product mapping in admin
     */
    public function remove_product_mapping() {
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bocs_admin_nonce')) {
            wp_send_json_error(['message' => 'Invalid security token']);
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'You do not have permission to manage product mappings']);
            return;
        }

        // Get BOCS ID to remove
        if (!isset($_POST['bocs_id']) || empty($_POST['bocs_id'])) {
            wp_send_json_error(['message' => 'Missing BOCS product ID']);
            return;
        }

        $bocs_id = sanitize_text_field($_POST['bocs_id']);

        // Get existing mappings
        $product_mapping = get_option('bocs_product_mapping', []);

        // Check if mapping exists
        if (!isset($product_mapping[$bocs_id])) {
            wp_send_json_error(['message' => 'Mapping not found']);
            return;
        }

        // Remove mapping
        unset($product_mapping[$bocs_id]);

        // Save to options
        $result = update_option('bocs_product_mapping', $product_mapping);

        if ($result) {
            // Log the update
            error_log('BOCS Admin: Removed product mapping for ' . $bocs_id);
            wp_send_json_success(['message' => 'Product mapping removed successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to remove product mapping']);
        }
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