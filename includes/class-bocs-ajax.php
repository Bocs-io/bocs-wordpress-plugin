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
        // Check security nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'bocs-subscription-switched')) {
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
        $subscription_data = $helper->curl_request($url, 'GET', [], $headers);
        
        if (is_wp_error($subscription_data) || !isset($subscription_data['data'])) {
            wp_send_json_error('Failed to fetch subscription data from API');
            return;
        }

        // Trigger the switched email notification
        // Pass false for box_update and the frequency_update value for the 5th parameter
        do_action('bocs_subscription_switched', $subscription_data['data'], '', '', false, $is_frequency_update);

        // Send success response
        wp_send_json_success('Subscription email triggered successfully');
    }
    
    /**
     * AJAX handler for triggering box updated email notification
     */
    public function trigger_box_updated_email() {
        error_log('BOCS EMAIL DEBUG: trigger_box_updated_email AJAX handler called');
        
        // Check security nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'bocs-box-updated')) {
            error_log('BOCS EMAIL DEBUG: Invalid security token in trigger_box_updated_email');
            wp_send_json_error('Invalid security token');
            return;
        }

        // Get subscription ID
        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : 0;
        if (empty($subscription_id)) {
            error_log('BOCS EMAIL DEBUG: Empty subscription ID in trigger_box_updated_email');
            wp_send_json_error('Subscription ID is required');
            return;
        }
        
        error_log('BOCS EMAIL DEBUG: Processing box update email for subscription: ' . $subscription_id);

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
        error_log('BOCS EMAIL DEBUG: Fetching subscription data from: ' . $url);
        $subscription_data = $helper->curl_request($url, 'GET', [], $headers);
        
        if (is_wp_error($subscription_data)) {
            error_log('BOCS EMAIL DEBUG: API error: ' . $subscription_data->get_error_message());
            wp_send_json_error('Failed to fetch subscription data from API');
            return;
        }

        if (!isset($subscription_data['data'])) {
            error_log('BOCS EMAIL DEBUG: No subscription data returned from API: ' . print_r($subscription_data, true));
            wp_send_json_error('Failed to fetch subscription data from API');
            return;
        }

        error_log('BOCS EMAIL DEBUG: Successfully fetched subscription data, going to trigger email');
        
        // Check if WooCommerce is active and email class is registered
        if (!class_exists('WC_Emails')) {
            error_log('BOCS EMAIL DEBUG: WooCommerce emails class not found');
        } else {
            // Check WooCommerce email configuration
            $wc_emails = WC()->mailer();
            error_log('BOCS EMAIL DEBUG: WooCommerce mailer instance: ' . (is_object($wc_emails) ? 'Found' : 'Not found'));
            
            if (is_object($wc_emails) && isset($wc_emails->emails)) {
                $registered_emails = array_keys($wc_emails->emails);
                error_log('BOCS EMAIL DEBUG: Registered WC emails: ' . implode(', ', $registered_emails));
                
                // Check if our email is registered
                if (isset($wc_emails->emails['bocs_subscription_switched'])) {
                    error_log('BOCS EMAIL DEBUG: Our email is registered in WC_Emails!');
                } else {
                    error_log('BOCS EMAIL DEBUG: Our email is NOT registered in WC_Emails!');
                    
                    // Try to manually register our email if not found
                    try {
                        if (class_exists('WC_Bocs_Email_Subscription_Switched')) {
                            error_log('BOCS EMAIL DEBUG: Attempting to manually register email');
                            // Check if there are any existing instances of our email
                            foreach ($wc_emails->emails as $email) {
                                if ($email instanceof WC_Bocs_Email_Subscription_Switched) {
                                    error_log('BOCS EMAIL DEBUG: Found existing instance with different ID: ' . $email->id);
                                }
                            }
                            
                            // Manually add our email
                            $wc_emails->emails['bocs_subscription_switched'] = new WC_Bocs_Email_Subscription_Switched();
                            error_log('BOCS EMAIL DEBUG: Manually registered our email');
                        }
                    } catch (Exception $e) {
                        error_log('BOCS EMAIL DEBUG: Error trying to register email: ' . $e->getMessage());
                    }
                }
            } else {
                error_log('BOCS EMAIL DEBUG: WooCommerce emails not initialized properly');
            }
        }

        // List registered hooks for our action
        global $wp_filter;
        if (isset($wp_filter['bocs_subscription_switched'])) {
            error_log('BOCS EMAIL DEBUG: Found hooks for bocs_subscription_switched: ' . count($wp_filter['bocs_subscription_switched']->callbacks));
            foreach ($wp_filter['bocs_subscription_switched']->callbacks as $priority => $callbacks) {
                error_log('BOCS EMAIL DEBUG: Priority ' . $priority . ' has ' . count($callbacks) . ' callbacks');
                foreach ($callbacks as $key => $callback) {
                    if (is_array($callback['function'])) {
                        error_log('BOCS EMAIL DEBUG: Callback: ' . (is_object($callback['function'][0]) ? get_class($callback['function'][0]) : print_r($callback['function'][0], true)) . '->' . $callback['function'][1]);
                    } else {
                        error_log('BOCS EMAIL DEBUG: Callback: ' . (is_string($callback['function']) ? $callback['function'] : 'Closure'));
                    }
                }
            }
        } else {
            error_log('BOCS EMAIL DEBUG: No hooks found for bocs_subscription_switched action!');
        }

        // Trigger the box updated email notification using the same email template
        // Pass true as the 4th parameter to indicate this is a box update
        error_log('BOCS EMAIL DEBUG: About to call do_action for bocs_subscription_switched');
        $result = do_action('bocs_subscription_switched', $subscription_data['data'], '', '', true);
        error_log('BOCS EMAIL DEBUG: do_action completed for bocs_subscription_switched');

        // Check if our email class exists and is properly initialized
        if (class_exists('WC_Bocs_Email_Subscription_Switched')) {
            error_log('BOCS EMAIL DEBUG: WC_Bocs_Email_Subscription_Switched class exists');
            
            // Try to directly initialize and trigger email for testing
            try {
                $email = new WC_Bocs_Email_Subscription_Switched();
                error_log('BOCS EMAIL DEBUG: Directly initializing email class for testing');
                $email->trigger($subscription_data['data'], '', '', true);
                error_log('BOCS EMAIL DEBUG: Direct email trigger completed');
            } catch (Exception $e) {
                error_log('BOCS EMAIL DEBUG: Error directly triggering email: ' . $e->getMessage());
            }
        } else {
            error_log('BOCS EMAIL DEBUG: WC_Bocs_Email_Subscription_Switched class not found!');
        }

        // Send success response
        wp_send_json_success('Box updated email triggered successfully');
        
        // Try direct email as a very last resort
        if (isset($subscription_data['data']['customer']['email']) && !empty($subscription_data['data']['customer']['email'])) {
            $customer_email = $subscription_data['data']['customer']['email'];
            
            // Make sure we have WordPress mail function
            if (function_exists('wp_mail')) {
                error_log('BOCS EMAIL DEBUG: Last resort - attempting direct email send to ' . $customer_email);
                
                // Basic email content
                $subject = '[Bocs] Your box contents have been updated';
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
                $mail_result = wp_mail($customer_email, $subject, $message, $headers);
                
                error_log('BOCS EMAIL DEBUG: Emergency direct mail result: ' . ($mail_result ? 'SUCCESS' : 'FAILED'));
            }
        }
    }
    
    /**
     * Public function to manually trigger box updated email notification.
     * This can be called directly from PHP.
     * 
     * @param int $subscription_id The subscription ID to send the notification for
     * @return bool True if the email was triggered successfully, false otherwise
     */
    public function manual_trigger_box_updated_email($subscription_id) {
        if (empty($subscription_id)) {
            return false;
        }

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
        $subscription_data = $helper->curl_request($url, 'GET', [], $headers);
        
        if (is_wp_error($subscription_data) || !isset($subscription_data['data'])) {
            return false;
        }

        // Trigger the box updated email notification
        do_action('bocs_subscription_switched', $subscription_data['data'], '', '', true);
        
        return true;
    }
    
    /**
     * AJAX handler for triggering subscription cancelled email notification
     */
    public function trigger_subscription_cancelled_email() {
        // Check security nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'bocs-subscription-cancelled')) {
            wp_send_json_error('Invalid security token');
            return;
        }

        // Get subscription ID
        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : 0;
        if (empty($subscription_id)) {
            wp_send_json_error('Subscription ID is required');
            return;
        }
        
        // Get cancellation reason if provided
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';

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
        $subscription_data = $helper->curl_request($url, 'GET', [], $headers);
        
        if (is_wp_error($subscription_data) || !isset($subscription_data['data'])) {
            wp_send_json_error('Failed to fetch subscription data from API');
            return;
        }

        // Trigger the cancelled email notification by firing the action
        do_action('bocs_subscription_cancelled', $subscription_data['data'], $reason);

        // Send success response
        wp_send_json_success('Subscription cancellation email triggered successfully');
    }
    
    /**
     * AJAX handler for triggering subscription paused email notification
     */
    public function trigger_subscription_paused_email() {
        // Check security nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'bocs-subscription-paused')) {
            wp_send_json_error('Invalid security token');
            return;
        }

        // Get subscription ID
        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : 0;
        if (empty($subscription_id)) {
            wp_send_json_error('Subscription ID is required');
            return;
        }
        
        // Get pause reason if provided
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';

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
        $subscription_data = $helper->curl_request($url, 'GET', [], $headers);
        
        if (is_wp_error($subscription_data) || !isset($subscription_data['data'])) {
            wp_send_json_error('Failed to fetch subscription data from API');
            return;
        }

        // Trigger the paused email notification by firing the action
        do_action('bocs_subscription_paused', $subscription_data['data'], $reason);

        // Send success response
        wp_send_json_success('Subscription paused email triggered successfully');
    }
    
    /**
     * AJAX handler for triggering subscription resumed email notification
     */
    public function trigger_subscription_resumed_email() {
        // Check security nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'bocs-subscription-resumed')) {
            wp_send_json_error('Invalid security token');
            return;
        }

        // Get subscription ID
        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : 0;
        if (empty($subscription_id)) {
            wp_send_json_error('Subscription ID is required');
            return;
        }
        
        // Get resume reason if provided
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';

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
        $subscription_data = $helper->curl_request($url, 'GET', [], $headers);
        
        if (is_wp_error($subscription_data) || !isset($subscription_data['data'])) {
            wp_send_json_error('Failed to fetch subscription data from API');
            return;
        }

        // Trigger the resumed email notification by firing the action
        do_action('bocs_subscription_resumed', $subscription_data['data'], $reason);

        // Send success response
        wp_send_json_success('Subscription reactivated email triggered successfully');
    }

    /**
     * AJAX handler for direct email fallback
     */
    public function direct_email_fallback() {
        error_log('BOCS DIRECT EMAIL: Direct email fallback AJAX handler called');
        
        // Check security nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'bocs-direct-email')) {
            error_log('BOCS DIRECT EMAIL: Invalid security token in direct_email_fallback');
            wp_send_json_error('Invalid security token');
            return;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            error_log('BOCS DIRECT EMAIL: User not logged in');
            wp_send_json_error(array('message' => __('You must be logged in to perform this action.', 'bocs-wordpress')));
            return;
        }

        // Get subscription ID
        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
        if (empty($subscription_id)) {
            error_log('BOCS DIRECT EMAIL: Invalid subscription ID');
            wp_send_json_error(array('message' => __('Invalid subscription ID.', 'bocs-wordpress')));
            return;
        }
        
        error_log('BOCS DIRECT EMAIL: Processing for subscription ID: ' . $subscription_id);

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
        error_log('BOCS DIRECT EMAIL: Fetching subscription data from API: ' . $url);
        $subscription_data = $helper->curl_request($url, 'GET', [], $headers);
        
        if (is_wp_error($subscription_data)) {
            error_log('BOCS DIRECT EMAIL: API error: ' . $subscription_data->get_error_message());
            wp_send_json_error('Failed to fetch subscription data from API');
            return;
        }
        
        if (!isset($subscription_data['data'])) {
            error_log('BOCS DIRECT EMAIL: No subscription data returned from API');
            wp_send_json_error('Failed to fetch subscription data from API');
            return;
        }
        
        error_log('BOCS DIRECT EMAIL: Successfully retrieved subscription data');
        
        // Get customer email
        $customer_email = '';
        if (isset($subscription_data['data']['customer']) && isset($subscription_data['data']['customer']['email'])) {
            $customer_email = $subscription_data['data']['customer']['email'];
            error_log('BOCS DIRECT EMAIL: Customer email found: ' . $customer_email);
        } else {
            error_log('BOCS DIRECT EMAIL: No customer email found in subscription data');
            wp_send_json_error('No customer email found');
            return;
        }
        
        // Attempt the WC method first
        do_action('bocs_subscription_switched', $subscription_data['data'], '', '', true);
        error_log('BOCS DIRECT EMAIL: Called bocs_subscription_switched action');
        
        // EMERGENCY: Send mail using all possible methods
        $this->emergency_mail_test($customer_email, $subscription_data['data']);
        
        // Direct mail fallback
        if (function_exists('wp_mail')) {
            error_log('BOCS DIRECT EMAIL: Attempting direct wp_mail');
            
            // Basic email content
            $subject = '[Bocs] Your box contents have been updated';
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
            
            error_log('BOCS DIRECT EMAIL: Sending to: ' . $customer_email);
            error_log('BOCS DIRECT EMAIL: Using headers: ' . implode(', ', $headers));
            
            $mail_result = wp_mail($customer_email, $subject, $message, $headers);
            
            error_log('BOCS DIRECT EMAIL: Direct wp_mail result: ' . ($mail_result ? 'SUCCESS' : 'FAILED'));
            
            if ($mail_result) {
                wp_send_json_success('Box updated email sent via direct wp_mail');
                return;
            }
        } else {
            error_log('BOCS DIRECT EMAIL: wp_mail function not available');
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
        error_log('BOCS EMERGENCY MAIL: Starting emergency mail test');
        
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
        error_log('BOCS EMERGENCY MAIL: Testing WordPress mail function to admin: ' . $test_recipient);
        if (function_exists('wp_mail')) {
            $wp_mail_result = wp_mail($test_recipient, $subject, $message, $headers);
            error_log('BOCS EMERGENCY MAIL: wp_mail result: ' . ($wp_mail_result ? 'SUCCESS' : 'FAILED'));
        } else {
            error_log('BOCS EMERGENCY MAIL: wp_mail function not available');
        }
        
        // METHOD 2: PHP mail function
        if (function_exists('mail')) {
            error_log('BOCS EMERGENCY MAIL: Testing PHP mail function');
            $header_str = implode("\r\n", $headers);
            $php_mail_result = mail($test_recipient, $subject, $message, $header_str);
            error_log('BOCS EMERGENCY MAIL: PHP mail result: ' . ($php_mail_result ? 'SUCCESS' : 'FAILED'));
            
            // Also try the customer email
            $customer_php_mail = mail($recipient, $subject, $message, $header_str);
            error_log('BOCS EMERGENCY MAIL: PHP mail to customer result: ' . ($customer_php_mail ? 'SUCCESS' : 'FAILED'));
        } else {
            error_log('BOCS EMERGENCY MAIL: PHP mail function not available');
        }
        
        // METHOD 3: Try using WP PHPMailer directly
        error_log('BOCS EMERGENCY MAIL: Testing PHPMailer directly');
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
            error_log('BOCS EMERGENCY MAIL: PHPMailer direct result: ' . ($phpmailer_result ? 'SUCCESS' : 'FAILED'));
        } catch (Exception $e) {
            error_log('BOCS EMERGENCY MAIL: PHPMailer error: ' . $e->getMessage());
        }
        
        // Log mail configuration for debugging
        error_log('BOCS EMERGENCY MAIL: Mail configuration:');
        error_log('BOCS EMERGENCY MAIL: PHP mail enabled: ' . (function_exists('mail') ? 'Yes' : 'No'));
        error_log('BOCS EMERGENCY MAIL: sendmail_path: ' . ini_get('sendmail_path'));
        error_log('BOCS EMERGENCY MAIL: SMTP settings: ' . ini_get('SMTP') . ':' . ini_get('smtp_port'));
        
        // Check for mail plugins that might be interfering
        $active_plugins = get_option('active_plugins');
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'mail') !== false || strpos($plugin, 'smtp') !== false) {
                error_log('BOCS EMERGENCY MAIL: Possible mail plugin detected: ' . $plugin);
            }
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