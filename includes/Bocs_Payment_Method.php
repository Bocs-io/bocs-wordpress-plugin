<?php

/**
 * Bocs Payment Method Class
 *
 * Handles payment method management for Bocs subscriptions, including:
 * - Stripe payment method setup and management
 * - Payment token handling
 * - Payment method updates for subscriptions
 *
 * @package Bocs
 * @since 0.0.115
 */

class Bocs_Payment_Method {
    /**
     * Constructor.
     *
     * Initialize payment method handling and set up required hooks.
     *
     * @since 0.0.115
     */
    public function __construct() {
        // Ensure Stripe PHP SDK is loaded
        if (!class_exists('\Stripe\StripeClient')) {
            require_once(plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php');
        }
        
        // Add hooks for payment tokens
        add_filter('woocommerce_payment_methods_list_item', array($this, 'add_edit_payment_method_button'), 10, 2);
        add_action('woocommerce_payment_token_deleted', array($this, 'payment_token_deleted'), 10, 2);
        add_filter('woocommerce_get_customer_payment_tokens', array($this, 'get_customer_payment_tokens'), 10, 3);
        
        // Make Stripe available in the list of payment gateways for the payment methods page
        add_filter('woocommerce_payment_gateways', array($this, 'add_stripe_to_gateways'));
        
        // Ensure our tokens are displayed on the payment methods page
        add_action('woocommerce_account_payment-methods_endpoint', array($this, 'maybe_show_saved_cards'), 5);
        
        // Add AJAX handler for getting payment methods from subscription
        add_action('wp_ajax_get_subscription_payment_methods', array($this, 'get_subscription_payment_methods'));
        
        // Add shortcode for displaying payment methods from subscription
        add_shortcode('bocs_subscription_payment_methods', array($this, 'subscription_payment_methods_shortcode'));
        
        // Add hook to display subscription payment methods on the subscription page
        add_action('bocs_subscription_details_after', array($this, 'render_subscription_payment_methods'), 20, 1);
        
        // Add hook to handle direct payment method loading via AJAX 
        add_action('wp_ajax_get_subscription_payment_method_direct', array($this, 'get_subscription_payment_method_direct'));
        
        // Add AJAX handler for getting user billing details
        add_action('wp_ajax_get_user_billing_details', array($this, 'get_user_billing_details'));
        
        // Add direct debug for payment method retrieval
        add_action('wp_ajax_debug_payment_method', array($this, 'debug_payment_method'));
        
        // Add hooks for managing payment methods
        add_action('wp_ajax_bocs_get_payment_methods', array($this, 'ajax_get_payment_methods'));
        add_action('wp_ajax_nopriv_bocs_get_payment_methods', array($this, 'ajax_get_payment_methods'));
        add_action('wp_ajax_bocs_update_payment_method', array($this, 'ajax_update_payment_method'));
        
        // Show any payment method status messages
        add_action('woocommerce_before_account_payment_methods', array($this, 'show_status_messages'));
    }
    
    /**
     * Add edit button to payment method list items.
     *
     * Adds an edit button to each payment method in the customer's payment methods list.
     *
     * @since 0.0.115
     * @param array             $method         Payment method data array.
     * @param WC_Payment_Token $payment_method Payment token object.
     * @return array Modified payment method data array.
     */
    public function add_edit_payment_method_button($method, $payment_method) {
        try {
            if ($payment_method instanceof WC_Payment_Token) {
                // Add edit button for all payment methods, not just stripe
                $method['actions']['edit'] = sprintf(
                    '<a href="%s" class="button edit-payment-method" data-payment-method-id="%s">%s</a>',
                    wp_nonce_url(add_query_arg(
                        array(
                            'edit-payment-method' => $payment_method->get_id(),
                            'token_id' => $payment_method->get_id()
                        ),
                        wc_get_account_endpoint_url('payment-methods')
                    ), 'edit_payment_method'),
                    esc_attr($payment_method->get_id()),
                    esc_html__('Edit', 'bocs-wordpress')
                );
            }
            return $method;
        } catch (Exception $e) {
            return $method; // Return original method if there's an error
        }
    }

    /**
     * Set up Stripe payment configuration.
     *
     * Creates a Stripe SetupIntent and returns necessary configuration for the frontend.
     * Handles test/live mode settings and retrieves saved payment methods.
     *
     * @since 0.0.115
     * @return void Sends JSON response.
     */
    public function get_stripe_setup() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bocs_ajax_nonce')) {
            wp_send_json_error(['message' => 'Invalid security token']);
            return;
        }

        // Get subscription ID
        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
        if (empty($subscription_id)) {
            wp_send_json_error(['message' => 'Subscription ID is required']);
            return;
        }

        try {
            // Get Stripe settings from WooCommerce
            $stripe_settings = get_option('woocommerce_stripe_settings', []);
            
            // Check if we're in test mode
            $test_mode = isset($stripe_settings['testmode']) && $stripe_settings['testmode'] === 'yes';
            
            // Get the appropriate keys
            $publishable_key = $test_mode ? 
                $stripe_settings['test_publishable_key'] : 
                $stripe_settings['publishable_key'];
            
            $secret_key = $test_mode ? 
                $stripe_settings['test_secret_key'] : 
                $stripe_settings['secret_key'];

            // Verify we have the keys
            if (empty($publishable_key) || empty($secret_key)) {
                throw new Exception('Stripe keys are not properly configured');
            }

            // Initialize Stripe with secret key
            $stripe = new \Stripe\StripeClient($secret_key);
            
            // Get current user's customer ID
            $user_id = get_current_user_id();
            $customer_id = get_user_meta($user_id, '_stripe_customer_id', true);
            
            // If no customer ID exists, check if we need to create one
            if (empty($customer_id)) {
                // Try to create a new customer
                try {
                    $customer = $stripe->customers->create([
                        'email' => wp_get_current_user()->user_email,
                        'metadata' => [
                            'wordpress_user_id' => $user_id,
                            'source' => 'bocs_wordpress'
                        ]
                    ]);
                    $customer_id = $customer->id;
                    update_user_meta($user_id, '_stripe_customer_id', $customer_id);
                } catch (Exception $e) {
                    // Continue without customer ID if creation fails
                }
            }

            // Create a SetupIntent with customer ID if available
            $setup_params = [
                'payment_method_types' => ['card'],
                'usage' => 'off_session', // This allows the payment method to be used for future payments
                'metadata' => [
                    'subscription_id' => $subscription_id,
                    'source' => 'bocs_wordpress'
                ]
            ];
            
            // Associate with customer if we have an ID
            if (!empty($customer_id)) {
                $setup_params['customer'] = $customer_id;
            }
            
            $setup_intent = $stripe->setupIntents->create($setup_params);

            // Get saved payment methods for the current user
            $tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), 'stripe');
            
            $saved_methods = [];
            foreach ($tokens as $token) {
                $saved_methods[] = [
                    'id' => $token->get_token(),
                    'last4' => $token->get_last4(),
                    'brand' => $token->get_card_type(),
                    'exp_month' => $token->get_expiry_month(),
                    'exp_year' => $token->get_expiry_year(),
                ];
            }

            // Send the setup data back to the client
            wp_send_json_success([
                'publishable_key' => $publishable_key,
                'client_secret' => $setup_intent->client_secret,
                'setup_intent_id' => $setup_intent->id,
                'subscription_id' => $subscription_id, // Include subscription ID in response
                'is_test_mode' => $test_mode,
                'saved_methods' => $saved_methods
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle payment method updates.
     *
     * Processes AJAX requests to update payment method details in Stripe.
     *
     * @since 0.0.115
     * @return void Sends JSON response.
     */
    public function handle_payment_method_update() {
        check_ajax_referer('bocs_ajax_nonce', 'nonce');
        
        if (!isset($_POST['payment_method_id'])) {
            wp_send_json_error(['message' => 'Payment method ID is required']);
            return;
        }
    
        try {
            $stripe_settings = get_option('woocommerce_stripe_settings', []);
            $stripe = new \Stripe\StripeClient($stripe_settings['secret_key']);
    
            // Update the payment method
            $payment_method = $stripe->paymentMethods->update(
                sanitize_text_field($_POST['payment_method_id']),
                ['metadata' => ['updated_at' => time()]]
            );
    
            wp_send_json_success([
                'message' => 'Payment method updated successfully',
                'payment_method' => $payment_method
            ]);
    
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Enqueue payment-related scripts and styles.
     *
     * Loads Stripe.js and custom payment handling scripts with localized data.
     *
     * @since 0.0.115
     * @return void
     */
    public function enqueue_scripts() {
        // Only load on our subscription page
        //if (is_account_page() && is_wc_endpoint_url('bocs-subscriptions')) {
            // Get Stripe settings
            $stripe_settings = get_option('woocommerce_stripe_settings', []);
            $test_mode = isset($stripe_settings['testmode']) && $stripe_settings['testmode'] === 'yes';
            
            // Enqueue Stripe.js only once
            wp_enqueue_script(
                'stripe-js',
                'https://js.stripe.com/v3/',
                [],
                null,
                true
            );

            // Enqueue our custom script
            wp_enqueue_script(
                'bocs-payment-methods',
                plugins_url('assets/js/payment-methods.js', dirname(__FILE__)),
                ['jquery', 'stripe-js'],
                '20250410.2', // Updated version to force browser refresh
                true
            );

            // Pass necessary data to JavaScript
            wp_localize_script('bocs-payment-methods', 'bocsPaymentData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bocs_ajax_nonce'),
                'isTestMode' => $test_mode,
                'i18n' => [
                    'errorGeneric' => __('An error occurred. Please try again.', 'bocs-wordpress'),
                    'successUpdate' => __('Payment method updated successfully', 'bocs-wordpress'),
                    'processing' => __('Processing...', 'bocs-wordpress'),
                    'updatePaymentMethod' => __('Update Payment Method', 'bocs-wordpress'),
                    'cancel' => __('Cancel', 'bocs-wordpress'),
                    'addNewPaymentMethod' => __('Add new payment method', 'bocs-wordpress'),
                    'savedPaymentMethods' => __('Saved Payment Methods', 'bocs-wordpress'),
                    'editPaymentMethod' => __('Edit Payment Method', 'bocs-wordpress'),
                    'expires' => __('Expires', 'bocs-wordpress'),
                ]
            ]);
        //}
    }

    /**
     * Handle Stripe setup completion.
     *
     * Processes the redirect after Stripe setup, saves payment tokens,
     * and updates subscription payment details.
     *
     * @since 0.0.115
     * @return void
     */
    public function handle_setup_completion() {
        if (!isset($_GET['setup_intent']) || !isset($_GET['setup_intent_client_secret'])) {
            return;
        }

        try {
            // Get Stripe settings
            $stripe_settings = get_option('woocommerce_stripe_settings', []);
            $test_mode = isset($stripe_settings['testmode']) && $stripe_settings['testmode'] === 'yes';
            $secret_key = $test_mode ? $stripe_settings['test_secret_key'] : $stripe_settings['secret_key'];

            if (empty($secret_key)) {
                throw new Exception('Stripe secret key is not configured');
            }

            // Initialize Stripe
            $stripe = new \Stripe\StripeClient($secret_key);

            // Retrieve the SetupIntent
            $setup_intent = $stripe->setupIntents->retrieve($_GET['setup_intent']);

            // Check status - change to a more inclusive check (requires_action might be in progress)
            if ($setup_intent->status !== 'succeeded' && $setup_intent->status !== 'processing') {
                // Add potential status check for requires_action
                if ($setup_intent->status === 'requires_action') {
                    wp_redirect(add_query_arg(['payment_updated' => 'pending'], wc_get_account_endpoint_url('bocs-subscriptions')));
                    exit;
                }
                throw new Exception('Setup was not completed successfully. Status: ' . $setup_intent->status);
            }

            // Get current user
            $user_id = get_current_user_id();
            if (!$user_id) {
                throw new Exception('User not logged in');
            }

            // Get subscription ID from setup intent metadata or fallback to a stored value
            $subscription_id = isset($setup_intent->metadata->subscription_id) ? $setup_intent->metadata->subscription_id : '';
            
            // If no subscription ID in metadata, check for session storage value via cookies
            if (empty($subscription_id) && isset($_COOKIE['bocs_subscription_id'])) {
                $subscription_id = sanitize_text_field($_COOKIE['bocs_subscription_id']);
            }
            
            // Final check - subscription ID is required
            if (empty($subscription_id)) {
                throw new Exception('Subscription ID not found in setup intent or session');
            }

            // Get or create Stripe customer
            $customer_id = get_user_meta($user_id, '_stripe_customer_id', true);
            
            // Fallback: Try to get customer ID from payment intent if not available directly
            if (empty($customer_id) && isset($setup_intent->payment_method) && !empty($setup_intent->payment_method)) {
                try {
                    // Try to get customer ID from payment method
                    $payment_method = $stripe->paymentMethods->retrieve($setup_intent->payment_method);
                    if (!empty($payment_method->customer)) {
                        $customer_id = $payment_method->customer;
                        update_user_meta($user_id, '_stripe_customer_id', $customer_id);
                    }
                } catch (Exception $e) {
                    // Continue trying other methods
                }
            }
            
            // If still no customer ID, try to find from existing payment intents
            if (empty($customer_id)) {
                try {
                    // Search for payment intents associated with this user's email
                    $email = wp_get_current_user()->user_email;
                    $payment_intents = $stripe->paymentIntents->all([
                        'limit' => 5,
                        'customer' => null,
                    ]);
                    
                    foreach ($payment_intents->data as $intent) {
                        if (!empty($intent->customer)) {
                            $customer = $stripe->customers->retrieve($intent->customer);
                            if ($customer->email === $email) {
                                $customer_id = $intent->customer;
                                update_user_meta($user_id, '_stripe_customer_id', $customer_id);
                                break;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Continue
                }
            }
            
            // If still no customer ID, create a new customer
            if (empty($customer_id)) {
                // Create a new customer
                $customer = $stripe->customers->create([
                    'email' => wp_get_current_user()->user_email,
                    'metadata' => [
                        'wordpress_user_id' => $user_id,
                        'source' => 'bocs_wordpress'
                    ]
                ]);
                $customer_id = $customer->id;
                update_user_meta($user_id, '_stripe_customer_id', $customer_id);
            }

            // Get the payment method details
            $payment_method = $stripe->paymentMethods->retrieve($setup_intent->payment_method);
            $payment_method_id = $payment_method->id;

            // Check if this payment method already exists as a token
            $existing_tokens = WC_Payment_Tokens::get_customer_tokens($user_id, 'stripe');
            $token_exists = false;
            
            foreach ($existing_tokens as $existing_token) {
                if ($existing_token->get_token() === $payment_method_id) {
                    // Token already exists, update it instead of creating a new one
                    $token = $existing_token;
                    $token_exists = true;
                    
                    // Update token details if needed
                    $token->set_card_type(strtolower($payment_method->card->brand));
                    $token->set_expiry_month($payment_method->card->exp_month);
                    $token->set_expiry_year($payment_method->card->exp_year);
                    
                    // Save the updated token
                    $token->save();
                    break;
                }
            }

            // Attach payment method to customer if needed
            if ($payment_method->customer !== $customer_id) {
                try {
                    // If payment method is already attached to another customer, detach it first
                    if (!empty($payment_method->customer)) {
                        try {
                            $stripe->paymentMethods->detach($payment_method_id);
                        } catch (Exception $detach_error) {
                            // Continue anyway, trying to attach
                        }
                    }
                    
                    // Now attach to the correct customer
                    $stripe->paymentMethods->attach($payment_method_id, [
                        'customer' => $customer_id
                    ]);
                } catch (Exception $e) {
                    throw new Exception('Failed to attach payment method: ' . $e->getMessage());
                }
            }

            // Only create a new token if one doesn't already exist
            if (!$token_exists) {
                // Create and save WC payment token
                $token = new WC_Payment_Token_CC();
                
                // Set token data
                $token->set_token($payment_method_id);
                $token->set_gateway_id('stripe');
                $token->set_card_type(strtolower($payment_method->card->brand));
                $token->set_last4($payment_method->card->last4);
                $token->set_expiry_month($payment_method->card->exp_month);
                $token->set_expiry_year($payment_method->card->exp_year);
                $token->set_user_id($user_id);

                // Save the token
                if (!$token->save()) {
                    throw new Exception('Failed to save payment token');
                }

                // Verify token was saved
                $saved_token = WC_Payment_Tokens::get($token->get_id());
                if (!$saved_token) {
                    throw new Exception('Token verification failed after save');
                }

                // Update token meta
                update_metadata('payment_token', $token->get_id(), '_stripe_customer_id', $customer_id);
                update_metadata('payment_token', $token->get_id(), '_stripe_source_id', $payment_method_id);
            }

            // Update Bocs subscription with new payment details
            $options = get_option('bocs_plugin_options');
            $options['bocs_headers'] = $options['bocs_headers'] ?? array();

            $get_response = wp_remote_get(
                BOCS_API_URL . "subscriptions/{$subscription_id}",
                array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Organization' => $options['bocs_headers']['organization'],
                        'Store' => $options['bocs_headers']['store'],
                        'Authorization' => $options['bocs_headers']['authorization'],
                    ),
                    'timeout' => 30    // Increase timeout to 30 seconds
                )
            );

            if (is_wp_error($get_response)) {
                throw new Exception('Failed to fetch subscription details: ' . $get_response->get_error_message());
            }

            $subscription_data = json_decode(wp_remote_retrieve_body($get_response), true);
            $existing_metadata = isset($subscription_data['data']['metaData']) ? $subscription_data['data']['metaData'] : array();
            
            // Direct modification approach - modify original metadata array
            $updated_metadata = $existing_metadata; // Start with the complete original array
            
            // Find and update Stripe-related keys (or add if they don't exist)
            $has_source_id = false;
            $has_customer_id = false;
            
            // First check if keys exist and update them
            foreach ($updated_metadata as &$meta) {
                if ($meta['key'] === '_stripe_source_id') {
                    $meta['value'] = $payment_method_id;
                    $has_source_id = true;
                } else if ($meta['key'] === '_stripe_customer_id') {
                    $meta['value'] = $customer_id;
                    $has_customer_id = true;
                }
            }
            
            // If keys don't exist, add them
            if (!$has_source_id) {
                $updated_metadata[] = array(
                    'key' => '_stripe_source_id',
                    'value' => $payment_method_id
                );
            }
            
            if (!$has_customer_id) {
                $updated_metadata[] = array(
                    'key' => '_stripe_customer_id',
                    'value' => $customer_id
                );
            }
            
            // Add payment intent if it exists and isn't already in metadata
            if (!empty($setup_intent->payment_intent)) {
                $has_intent_id = false;
                foreach ($updated_metadata as &$meta) {
                    if ($meta['key'] === '_stripe_intent_id') {
                        $meta['value'] = $setup_intent->payment_intent;
                        $has_intent_id = true;
                        break;
                    }
                }
                
                if (!$has_intent_id) {
                    $updated_metadata[] = array(
                        'key' => '_stripe_intent_id',
                        'value' => $setup_intent->payment_intent
                    );
                }
            }
            
            // Filter out any invalid metadata items and fix empty values
            $validated_metadata = [];
            foreach ($updated_metadata as $index => $meta_item) {
                // Check if it's an array with 'key' property
                if (is_array($meta_item) && isset($meta_item['key'])) {
                    // Make sure value is set, even if it's empty
                    if (!isset($meta_item['value'])) {
                        $meta_item['value'] = '';
                    }
                    $validated_metadata[] = $meta_item;
                }
            }
            
            // Update the metadata array with validated items
            $updated_metadata = $validated_metadata;
            
            // Check if BOCS_API_URL is defined
            if (!defined('BOCS_API_URL')) {
                throw new Exception('BOCS API URL is not configured');
            }
            
            // Validate BOCS headers
            if (empty($options['bocs_headers']['organization']) || 
                empty($options['bocs_headers']['store']) || 
                empty($options['bocs_headers']['authorization'])) {
                throw new Exception('BOCS API credentials are not properly configured');
            }
            
            // Add retry logic for API call
            $max_retries = 3;
            $retry_count = 0;
            $success = false;
            $last_error = '';
            $retry_delay = 1; // Start with 1 second delay
            $request_url = BOCS_API_URL . "subscriptions/{$subscription_id}";
            $request_body = json_encode(array('metaData' => $updated_metadata));
            
            while ($retry_count < $max_retries && !$success) {
                // If this is a retry, add delay and log
                if ($retry_count > 0) {
                    sleep($retry_delay);
                    $retry_delay *= 2; // Exponential backoff
                }
                
                // Update the subscription with merged metadata
                $api_response = wp_remote_request(
                    $request_url,
                    array(
                        'method' => 'PUT',
                        'headers' => array(
                            'Content-Type' => 'application/json',
                            'Organization' => $options['bocs_headers']['organization'],
                            'Store' => $options['bocs_headers']['store'],
                            'Authorization' => $options['bocs_headers']['authorization'],
                        ),
                        'body' => $request_body,
                        'timeout' => 30
                    )
                );
    
                if (is_wp_error($api_response)) {
                    $error_message = $api_response->get_error_message();
                    $last_error = 'Failed to update subscription payment details: ' . $error_message;
                    $retry_count++;
                    continue;
                }
                
                $response_code = wp_remote_retrieve_response_code($api_response);
                $response_body = wp_remote_retrieve_body($api_response);
                
                // Try to parse response body as JSON for more detailed debugging
                $parsed_body = json_decode($response_body, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($parsed_body)) {
                    // Check for specific error messages in the response
                    if (isset($parsed_body['message'])) {
                    }
                }
                
                // Check if successful (2xx status code)
                if ($response_code >= 200 && $response_code < 300) {
                    $success = true;
                    break;
                }
                
                // If we got a 500 error, retry
                if ($response_code == 500) {
                    $last_error = "API server error (500) - please try again";
                    $retry_count++;
                    continue;
                }
                
                // Other error codes are not retried
                $parsed_response = json_decode($response_body, true);
                $error_message = isset($parsed_response['message']) ? $parsed_response['message'] : "API returned status {$response_code}";
                $last_error = "Failed to update subscription: {$error_message}";
                break;
            }
            
            // If not successful after all retries, throw exception
            if (!$success) {
                throw new Exception($last_error);
            }
            
            // Send email notification for successful payment method update
            $this->send_payment_method_updated_email($payment_method, get_user_by('id', $user_id));

            // Set this payment method as the default for the user
            $this->set_default_payment_method($user_id, $token->get_id());

            // Redirect to the subscriptions page with success message
            wp_redirect(add_query_arg(['payment_updated' => 'success'], wc_get_account_endpoint_url('bocs-subscriptions')));
            exit;

        } catch (Exception $e) {
            // Improve error handling with detailed logging
            
            // Redirect to the subscriptions page with error
            wp_redirect(add_query_arg([
                'payment_updated' => 'error',
                'error_type' => 'server',
                'error_message' => urlencode($e->getMessage())
            ], wc_get_account_endpoint_url('bocs-subscriptions')));
            exit;
        }
    }

    /**
     * Set the default payment method for a user.
     *
     * @since 1.0.0
     * @param int $user_id User ID
     * @param int $token_id Payment token ID to set as default
     * @return bool Whether the operation was successful
     */
    private function set_default_payment_method($user_id, $token_id) {
        try {
            // Get all tokens for this user
            $tokens = WC_Payment_Tokens::get_customer_tokens($user_id);
            
            // Set the new token as default
            foreach ($tokens as $token) {
                if ($token->get_id() == $token_id) {
                    $token->set_default(true);
                    $token->save();
                } else if ($token->is_default()) {
                    // Remove default status from other tokens
                    $token->set_default(false);
                    $token->save();
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log('Error setting default payment method: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send payment method updated email.
     *
     * Triggers the email notification for when a payment method has been
     * successfully added or updated.
     *
     * @since 1.0.0
     * @param object $payment_method The payment method object from Stripe
     * @param WP_User $user The WordPress user
     * @return void
     */
    private function send_payment_method_updated_email($payment_method, $user) {
        // Load WC mailer
        $mailer = WC()->mailer();
        
        // Load the email class file if it's not already loaded
        if (!class_exists('WC_Bocs_Email_Payment_Method_Updated', false)) {
            include_once(plugin_dir_path(dirname(__FILE__)) . 'includes/emails/class-bocs-email-payment-method-updated.php');
        }
        
        // Check if the class file exists, instantiate it, and trigger the email
        if (class_exists('WC_Bocs_Email_Payment_Method_Updated', false)) {
            $email = new WC_Bocs_Email_Payment_Method_Updated();
            $email->trigger($payment_method, $user);
        } else {
            // Fall back to a simple email if the class isn't found
            $to = $user->user_email;
            $subject = sprintf(__('[%s] Your payment method has been updated', 'bocs-wordpress'), get_bloginfo('name'));
            $message = sprintf(__('Hi %s, your payment method has been successfully updated.', 'bocs-wordpress'), $user->first_name);
            wp_mail($to, $subject, $message);
        }
    }

    /**
     * Display payment update notices.
     *
     * Shows success/error messages after payment method updates.
     *
     * @since 0.0.115
     * @return void
     */
    public function display_payment_update_notices() {
        if (isset($_GET['payment_updated'])) {
            $status = $_GET['payment_updated'];
            
            if ($status === 'success') {
                wc_add_notice(__('Payment method successfully updated.', 'bocs-wordpress'), 'success');
            } 
            else if ($status === 'pending') {
                wc_add_notice(__('Your payment verification is in progress. Please complete any additional steps required by your bank.', 'bocs-wordpress'), 'notice');
            }
            else if ($status === 'error') {
                $error_message = isset($_GET['error_message']) ? urldecode($_GET['error_message']) : __('Failed to update payment method. Please try again.', 'bocs-wordpress');
                
                // Sanitize error message for security
                $error_message = wp_kses($error_message, [
                    'a' => ['href' => [], 'title' => []],
                    'br' => [],
                    'em' => [],
                    'strong' => [],
                ]);
                
                wc_add_notice($error_message, 'error');
                
                // Log the error for debugging
                error_log('Payment update error displayed: ' . $error_message);
            }
        }
    }

    /**
     * Update subscription payment method.
     *
     * Handles AJAX requests to update a subscription's payment method.
     *
     * @since 0.0.115
     * @return void Sends JSON response.
     */
    public function update_subscription_payment() {
        // Prevent any non-JSON output
        ob_start();
        
        try {
            // Force errors to be logged but not displayed
            ini_set('display_errors', 0);
            error_reporting(E_ALL);
            
            // Regular processing starts here
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bocs_ajax_nonce')) {
                throw new Exception('Invalid security token');
            }
            
            // Verify minimum required parameters
            $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';
            $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
            
            if (empty($payment_method) || empty($subscription_id)) {
                throw new Exception('Missing required parameters: payment_method and subscription_id');
            }
            
            // Get Stripe settings to check test mode
            $stripe_settings = get_option('woocommerce_stripe_settings', []);
            $test_mode = isset($stripe_settings['testmode']) && $stripe_settings['testmode'] === 'yes';
            
            // Verify the payment method belongs to the user
            $token = WC_Payment_Tokens::get($payment_method);
            if (!$token) {
                throw new Exception('Invalid payment method - token not found');
            }
            
            $user_id = get_current_user_id();
            if ($token->get_user_id() !== $user_id) {
                throw new Exception('Invalid payment method - token does not belong to user');
            }
            
            // Get the Stripe payment method ID associated with this token
            $payment_method_id = $token->get_token();
            if (empty($payment_method_id)) {
                throw new Exception('Invalid payment method token');
            }
            
            // Get customer ID from token meta or user meta
            $customer_id = get_metadata('payment_token', $token->get_id(), '_stripe_customer_id', true);
            
            if (empty($customer_id)) {
                $customer_id = get_user_meta(get_current_user_id(), '_stripe_customer_id', true);
                
                if (empty($customer_id)) {
                    // Try to get customer ID from subscription data
                    // First get existing subscription data
                    $options = get_option('bocs_plugin_options');
                    $options['bocs_headers'] = $options['bocs_headers'] ?? array();

                    $api_url = BOCS_API_URL . "subscriptions/{$subscription_id}";
                    $get_response = wp_remote_get(
                        $api_url,
                        array(
                            'headers' => array(
                                'Content-Type' => 'application/json',
                                'Organization' => $options['bocs_headers']['organization'],
                                'Store' => $options['bocs_headers']['store'],
                                'Authorization' => $options['bocs_headers']['authorization'],
                            ),
                            'timeout' => 30
                        )
                    );

                    if (!is_wp_error($get_response)) {
                        $response_code = wp_remote_retrieve_response_code($get_response);
                        $response_body = wp_remote_retrieve_body($get_response);
                        
                        if ($response_code === 200) {
                            $subscription_data = json_decode($response_body, true);
                            
                            if (isset($subscription_data['data']) && isset($subscription_data['data']['metaData'])) {
                                $metadata = $subscription_data['data']['metaData'];
                                
                                // Look for _stripe_customer_id in metadata
                                foreach ($metadata as $meta) {
                                    if ($meta['key'] === '_stripe_customer_id') {
                                        $customer_id = $meta['value'];
                                        
                                        // Save the customer ID to token and user meta for future use
                                        update_metadata('payment_token', $token->get_id(), '_stripe_customer_id', $customer_id);
                                        update_user_meta(get_current_user_id(), '_stripe_customer_id', $customer_id);
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    
                    // If still empty, throw exception
                    if (empty($customer_id)) {
                        throw new Exception('Unable to determine Stripe customer ID');
                    }
                }
                
                // Save the customer ID to token meta for future use
                update_metadata('payment_token', $token->get_id(), '_stripe_customer_id', $customer_id);
            }
            
            // Ensure the token has the source ID set
            if (!get_metadata('payment_token', $token->get_id(), '_stripe_source_id', true)) {
                update_metadata('payment_token', $token->get_id(), '_stripe_source_id', $payment_method_id);
            }
            
            // Set this as the default payment method
            $this->set_default_payment_method(get_current_user_id(), $token->get_id());
            
            // Update Bocs subscription with the payment details
            $options = get_option('bocs_plugin_options');
            $options['bocs_headers'] = $options['bocs_headers'] ?? array();

            // First get existing subscription data
            $api_url = BOCS_API_URL . "subscriptions/{$subscription_id}";
            $get_response = wp_remote_get(
                $api_url,
                array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Organization' => $options['bocs_headers']['organization'],
                        'Store' => $options['bocs_headers']['store'],
                        'Authorization' => $options['bocs_headers']['authorization'],
                    ),
                    'timeout' => 30
                )
            );

            if (is_wp_error($get_response)) {
                throw new Exception('Failed to fetch subscription details: ' . $get_response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($get_response);
            $response_body = wp_remote_retrieve_body($get_response);
            
            if ($response_code !== 200) {
                throw new Exception("Failed to fetch subscription details: API returned status {$response_code}");
            }

            $subscription_data = json_decode($response_body, true);
            
            if (!isset($subscription_data['data']) || !isset($subscription_data['data']['metaData'])) {
                throw new Exception('Invalid subscription data format');
            }
            
            $existing_metadata = $subscription_data['data']['metaData'];
            
            // Direct modification approach - modify original metadata array
            $updated_metadata = $existing_metadata; // Start with the complete original array
            
            // Find and update Stripe-related keys (or add if they don't exist)
            $has_source_id = false;
            $has_customer_id = false;
            
            // First check if keys exist and update them
            foreach ($updated_metadata as &$meta) {
                if ($meta['key'] === '_stripe_source_id') {
                    $meta['value'] = $payment_method_id;
                    $has_source_id = true;
                } else if ($meta['key'] === '_stripe_customer_id') {
                    $meta['value'] = $customer_id;
                    $has_customer_id = true;
                }
            }
            
            // If keys don't exist, add them
            if (!$has_source_id) {
                $updated_metadata[] = array(
                    'key' => '_stripe_source_id',
                    'value' => $payment_method_id
                );
            }
            
            if (!$has_customer_id) {
                $updated_metadata[] = array(
                    'key' => '_stripe_customer_id',
                    'value' => $customer_id
                );
            }
            
            // Add retry logic for API call
            $max_retries = 3;
            $retry_count = 0;
            $success = false;
            $last_error = '';
            $retry_delay = 1; // Start with 1 second delay
            $api_url = BOCS_API_URL . "subscriptions/{$subscription_id}";
            $request_body = json_encode(array('metaData' => $updated_metadata));
            
            while ($retry_count < $max_retries && !$success) {
                // If this is a retry, add delay and log
                if ($retry_count > 0) {
                    sleep($retry_delay);
                    $retry_delay *= 2; // Exponential backoff
                }
                
                // Update the subscription with merged metadata
                $api_response = wp_remote_request(
                    $api_url,
                    array(
                        'method' => 'PUT',
                        'headers' => array(
                            'Content-Type' => 'application/json',
                            'Organization' => $options['bocs_headers']['organization'],
                            'Store' => $options['bocs_headers']['store'],
                            'Authorization' => $options['bocs_headers']['authorization'],
                        ),
                        'body' => $request_body,
                        'timeout' => 30
                    )
                );
    
                if (is_wp_error($api_response)) {
                    $error_message = $api_response->get_error_message();
                    $last_error = 'Failed to update subscription payment details: ' . $error_message;
                    $retry_count++;
                    continue;
                }
                
                $response_code = wp_remote_retrieve_response_code($api_response);
                $response_body = wp_remote_retrieve_body($api_response);
                
                // Try to parse response body as JSON for more detailed debugging
                $parsed_body = json_decode($response_body, true);
                
                // Check if successful (2xx status code)
                if ($response_code >= 200 && $response_code < 300) {
                    $success = true;
                    break;
                }
                
                // If we got a 500 error, retry
                if ($response_code == 500) {
                    $last_error = "API server error (500) - please try again";
                    $retry_count++;
                    continue;
                }
                
                // Other error codes are not retried
                $parsed_response = json_decode($response_body, true);
                $error_message = isset($parsed_response['message']) ? $parsed_response['message'] : "API returned status {$response_code}";
                $last_error = "Failed to update subscription: {$error_message}";
                break;
            }
            
            // If not successful after all retries, throw exception
            if (!$success) {
                throw new Exception($last_error);
            }
            
            // Send email notification for successful payment method update
            try {
                $user = get_user_by('id', get_current_user_id());
                $secret_key = isset($stripe_settings['testmode']) && $stripe_settings['testmode'] === 'yes' ? 
                    $stripe_settings['test_secret_key'] : $stripe_settings['secret_key'];
                    
                if (!empty($secret_key)) {
                    $stripe = new \Stripe\StripeClient($secret_key);
                    $stripe_payment_method = $stripe->paymentMethods->retrieve($payment_method_id);
                    $this->send_payment_method_updated_email($stripe_payment_method, $user);
                }
            } catch (Exception $e) {
                // Just log email errors but don't fail the whole request
                error_log('Error sending payment method update email: ' . $e->getMessage());
            }

            // Return success response
            $success_response = [
                'success' => true,
                'message' => 'Payment method updated successfully',
                'token_id' => $token->get_id(),
                'payment_method_id' => $payment_method_id
            ];
            
            // Always return JSON
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode($success_response);
            exit;
            
        } catch (Throwable $t) {
            // Catch all types of errors, including fatal ones
            $error_message = $t->getMessage();
            
            // Clean output buffer and ensure JSON response
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $error_message
            ]);
            exit;
        }
    }

    /**
     * Handle payment token deletion.
     *
     * Cleans up metadata when a payment token is deleted.
     *
     * @since 0.0.115
     * @param int             $token_id The payment token ID being deleted.
     * @param WC_Payment_Token $token    The payment token being deleted.
     * @return void
     */
    public function payment_token_deleted($token_id, $token) {
        // Clean up any associated metadata
        delete_metadata('payment_token', $token_id, '_stripe_customer_id');
        delete_metadata('payment_token', $token_id, '_stripe_source_id');
    }

    /**
     * Filter customer payment tokens.
     *
     * Ensures payment tokens are properly loaded with metadata.
     *
     * @since 0.0.115
     * @param array  $tokens      Array of payment token objects.
     * @param int    $customer_id Customer ID.
     * @param string $gateway_id  Payment gateway ID.
     * @return array Filtered array of payment token objects.
     */
    public function get_customer_payment_tokens($tokens, $customer_id, $gateway_id) {
        if ($gateway_id === 'stripe') {
            // Ensure tokens are properly loaded
            foreach ($tokens as $token) {
                if ($token instanceof WC_Payment_Token_CC) {
                    // Load any missing meta data if needed
                    $token->read_meta_data();
                }
            }
        }
        return $tokens;
    }

    /**
     * Add Stripe to available payment gateways if not already included
     *
     * Ensures that Stripe is available in the list of payment gateways for the payment methods page
     *
     * @since 1.0.0
     * @param array $gateways Array of payment gateway classes
     * @return array Modified array of payment gateway classes
     */
    public function add_stripe_to_gateways($gateways) {
        $has_stripe = false;
        
        // Check if Stripe is already included
        foreach ($gateways as $gateway) {
            if (is_string($gateway) && strpos(strtolower($gateway), 'stripe') !== false) {
                $has_stripe = true;
                break;
            } elseif (is_object($gateway) && strpos(strtolower(get_class($gateway)), 'stripe') !== false) {
                $has_stripe = true;
                break;
            }
        }
        
        // Only add if not already there
        if (!$has_stripe) {
            // Check if the Stripe gateway class exists
            if (class_exists('WC_Gateway_Stripe')) {
                $gateways[] = 'WC_Gateway_Stripe';
                error_log('Added Stripe to payment gateways');
            }
        }
        
        return $gateways;
    }
    
    /**
     * Display saved cards directly on the payment methods page
     *
     * Ensures that all saved cards are visible on the payment methods page
     *
     * @since 1.0.0
     * @return void
     */
    public function maybe_show_saved_cards() {
        // Only run this once at the beginning of the page
        if (did_action('woocommerce_account_payment-methods_endpoint') > 1) {
            return;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }
        
        // Get all stripe tokens for this user
        $tokens = WC_Payment_Tokens::get_customer_tokens($user_id, 'stripe');
        
        // If we have tokens but they're not showing up on the page, force refresh the tokens
        if (!empty($tokens)) {
            // Force WooCommerce to refresh the tokens list
            foreach ($tokens as $token) {
                // Log token details for debugging
                error_log("Found payment token: " . json_encode([
                    'id' => $token->get_id(),
                    'token' => $token->get_token(),
                    'user_id' => $token->get_user_id(),
                    'type' => $token->get_type(),
                    'gateway_id' => $token->get_gateway_id(), 
                    'last4' => $token->get_last4(),
                    'expiry' => $token->get_expiry_month() . '/' . $token->get_expiry_year()
                ]));
                
                // Make sure type is credit card
                if (!($token instanceof WC_Payment_Token_CC)) {
                    continue;
                }
                
                // Ensure the token is properly set up
                if (empty($token->get_card_type()) || empty($token->get_last4())) {
                    // Try to fetch missing data from Stripe if possible
                    $this->maybe_update_token_details($token);
                }
            }
        }
    }
    
    /**
     * Update token details from Stripe if necessary
     *
     * Fetches missing token details directly from Stripe
     *
     * @since 1.0.0
     * @param WC_Payment_Token_CC $token The payment token to update
     * @return bool Whether the token was updated
     */
    private function maybe_update_token_details($token) {
        if (!($token instanceof WC_Payment_Token_CC)) {
            return false;
        }
        
        // Only update if we're missing details
        if (!empty($token->get_card_type()) && !empty($token->get_last4())) {
            return false;
        }
        
        try {
            // Get Stripe settings
            $stripe_settings = get_option('woocommerce_stripe_settings', []);
            $test_mode = isset($stripe_settings['testmode']) && $stripe_settings['testmode'] === 'yes';
            $secret_key = $test_mode ? $stripe_settings['test_secret_key'] : $stripe_settings['secret_key'];
            
            if (empty($secret_key)) {
                return false;
            }
            
            // Initialize Stripe
            $stripe = new \Stripe\StripeClient($secret_key);
            
            // Fetch payment method details from Stripe
            $payment_method = $stripe->paymentMethods->retrieve($token->get_token());
            
            if (isset($payment_method->card)) {
                // Update token with details from Stripe
                $token->set_card_type(strtolower($payment_method->card->brand));
                $token->set_last4($payment_method->card->last4);
                $token->set_expiry_month($payment_method->card->exp_month);
                $token->set_expiry_year($payment_method->card->exp_year);
                $token->save();
                
                error_log("Updated token details from Stripe: " . $token->get_id());
                return true;
            }
        } catch (Exception $e) {
            error_log("Error updating token details: " . $e->getMessage());
        }
        
        return false;
    }

    /**
     * Get payment method details from subscription metadata.
     *
     * Retrieves payment method details from Stripe using the source ID stored in subscription metadata.
     *
     * @since 1.0.0
     * @return void Sends JSON response.
     */
    public function get_subscription_payment_methods() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bocs_ajax_nonce')) {
            wp_send_json_error(['message' => 'Invalid security token']);
            return;
        }

        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
        if (empty($subscription_id)) {
            wp_send_json_error(['message' => 'Subscription ID is required']);
            return;
        }

        try {
            // Get subscription data from Bocs API
            $options = get_option('bocs_plugin_options');
            $options['bocs_headers'] = $options['bocs_headers'] ?? array();

            $get_response = wp_remote_get(
                BOCS_API_URL . "subscriptions/{$subscription_id}",
                array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Organization' => $options['bocs_headers']['organization'],
                        'Store' => $options['bocs_headers']['store'],
                        'Authorization' => $options['bocs_headers']['authorization'],
                    ),
                    'timeout' => 30
                )
            );

            if (is_wp_error($get_response)) {
                throw new Exception('Failed to fetch subscription details: ' . $get_response->get_error_message());
            }

            $subscription_data = json_decode(wp_remote_retrieve_body($get_response), true);
            
            // Check if we have subscription data
            if (!isset($subscription_data['data']) || !isset($subscription_data['data']['metaData'])) {
                throw new Exception('Invalid subscription data format');
            }
            
            // Extract Stripe payment details from metadata
            $metadata = $subscription_data['data']['metaData'];
            $stripe_source_id = '';
            $stripe_customer_id = '';
            
            foreach ($metadata as $item) {
                if ($item['key'] === '_stripe_source_id') {
                    $stripe_source_id = $item['value'];
                } else if ($item['key'] === '_stripe_customer_id') {
                    $stripe_customer_id = $item['value'];
                }
            }
            
            if (empty($stripe_source_id)) {
                error_log("No Stripe source ID found in metadata");
                // Modified to show "Add new payment method" option instead of exception
                ob_start();
                echo '<h3>' . __('Edit Payment Method', 'bocs-wordpress') . '</h3>';
                echo '<div class="saved-payment-methods">';
                echo '<h4>' . __('Saved Payment Methods', 'bocs-wordpress') . '</h4>';
                echo '<p>' . __('No payment method found for this subscription', 'bocs-wordpress') . '</p>';
                
                echo '<form class="payment-methods-form">';
                
                // Add new payment method option
                echo '<label class="payment-method-option">';
                echo '<input type="radio" name="payment_method" value="new" />';
                echo ' ' . __('Add new payment method', 'bocs-wordpress');
                echo '</label>';
                
                // Footer buttons (Cancel / Update)
                echo '<div class="modal-buttons">';
                echo '<button type="button" class="button cancel-update">' . __('Cancel', 'bocs-wordpress') . '</button>';
                echo '<button type="button" class="button primary update-payment-method">' . __('Add Payment Method', 'bocs-wordpress') . '</button>';
                echo '</div>';
                
                echo '</form>';
                echo '</div>';
                
                // Add JavaScript for form handling
                ?>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('.cancel-update').on('click', function() {
                            $('#payment-method-modal').hide();
                            // Clear modal content except for loading message
                            $('#payment-method-modal').find('.bocs-modal-body').html('<p class="loading"><?php _e('Loading payment methods...', 'bocs-wordpress'); ?></p>');
                        });
                        
                        $('.update-payment-method').on('click', function() {
                            // Redirect to add new payment method
                            window.location.href = '<?php echo esc_url(add_query_arg(['add-payment-method' => '1', 'subscription_id' => $subscription_id], wc_get_endpoint_url('add-payment-method'))); ?>';
                        });
                    });
                </script>
                <style>
                    .saved-payment-methods {
                        margin-top: 20px;
                    }
                    .payment-method-option {
                        display: block;
                        margin-bottom: 10px;
                    }
                    .modal-buttons {
                        margin-top: 20px;
                        text-align: right;
                    }
                    .modal-buttons .button {
                        margin-left: 10px;
                    }
                    .button.primary {
                        background-color: #7b68ee;
                        color: white;
                    }
                </style>
                <?php
                $html = ob_get_clean();
                
                wp_send_json_success([
                    'html' => $html,
                    'subscription_id' => $subscription_id,
                    'debug_info' => [
                        'source_id' => 'none',
                        'has_payment_method' => false
                    ]
                ]);
                return;
            }
            
            // Get Stripe payment method details
            $stripe_settings = get_option('woocommerce_stripe_settings', []);
            $test_mode = isset($stripe_settings['testmode']) && $stripe_settings['testmode'] === 'yes';
            $secret_key = $test_mode ? $stripe_settings['test_secret_key'] : $stripe_settings['secret_key'];
            
            // Initialize Stripe
            $stripe = new \Stripe\StripeClient($secret_key);
            
            // Get payment method details
            $payment_method = null;
            $retrieval_error = null;
            
            try {
                // Get payment method details
                error_log("Retrieving payment method details from Stripe");
                
                $payment_method = null;
                $retrieval_error = null;
                
                // First try to retrieve as a payment method (pm_)
                try {
                    if (strpos($stripe_source_id, 'pm_') === 0) {
                        $payment_method = $stripe->paymentMethods->retrieve($stripe_source_id);
                    } else if (strpos($stripe_source_id, 'src_') === 0 || strpos($stripe_source_id, 'card_') === 0) {
                        // If it's a source or card, try to retrieve it that way
                        $source = $stripe->sources->retrieve($stripe_source_id);
                        
                        // Create a synthetic payment method object from the source
                        $payment_method = new stdClass();
                        $payment_method->id = $source->id;
                        $payment_method->type = $source->type;
                        $payment_method->card = new stdClass();
                        $payment_method->card->brand = $source->card->brand;
                        $payment_method->card->last4 = $source->card->last4;
                        $payment_method->card->exp_month = $source->card->exp_month;
                        $payment_method->card->exp_year = $source->card->exp_year;
                    } else {
                        // Try both approaches - first as payment method
                        try {
                            $payment_method = $stripe->paymentMethods->retrieve($stripe_source_id);
                        } catch (\Exception $e) {
                            // Then as source
                            try {
                                $source = $stripe->sources->retrieve($stripe_source_id);
                                
                                // Create a synthetic payment method object
                                $payment_method = new stdClass();
                                $payment_method->id = $source->id;
                                $payment_method->type = $source->type;
                                $payment_method->card = new stdClass();
                                $payment_method->card->brand = $source->card->brand;
                                $payment_method->card->last4 = $source->card->last4;
                                $payment_method->card->exp_month = $source->card->exp_month;
                                $payment_method->card->exp_year = $source->card->exp_year;
                            } catch (\Exception $e2) {
                                // Combined error
                                throw new Exception("Failed to retrieve as payment method: {$e->getMessage()} and as source: {$e2->getMessage()}");
                            }
                        }
                    }
                    
                    // Log the payment method details
                    error_log("Payment method retrieved: " . json_encode([
                        'id' => $payment_method->id,
                        'type' => $payment_method->type ?? 'unknown',
                        'card' => $payment_method->card ? [
                            'brand' => $payment_method->card->brand,
                            'last4' => $payment_method->card->last4,
                            'exp_month' => $payment_method->card->exp_month,
                            'exp_year' => $payment_method->card->exp_year
                        ] : 'No card data'
                    ]));
                } catch (\Exception $e) {
                    $retrieval_error = $e->getMessage();
                    error_log("Error retrieving payment method: " . $retrieval_error);
                    
                    // Try to get payment methods for customer if we have customer ID
                    $customer_id = null;
                    foreach ($metadata as $item) {
                        if ($item['key'] === '_stripe_customer_id') {
                            $customer_id = $item['value'];
                            break;
                        }
                    }
                    
                    if (!empty($customer_id)) {
                        try {
                            error_log("Attempting to list payment methods for customer: {$customer_id}");
                            $methods = $stripe->paymentMethods->all([
                                'customer' => $customer_id,
                                'type' => 'card'
                            ]);
                            
                            if (!empty($methods->data)) {
                                // Use the first payment method
                                $payment_method = $methods->data[0];
                                error_log("Found customer payment method: " . $payment_method->id);
                                
                                // Update subscription with this payment method
                                foreach ($metadata as &$meta) {
                                    if ($meta['key'] === '_stripe_source_id') {
                                        $meta['value'] = $payment_method->id;
                                        break;
                                    }
                                }
                                
                                // Update subscription metadata with correct payment method
                                $api_response = wp_remote_request(
                                    BOCS_API_URL . "subscriptions/{$subscription_id}",
                                    array(
                                        'method' => 'PUT',
                                        'headers' => array(
                                            'Content-Type' => 'application/json',
                                            'Organization' => $options['bocs_headers']['organization'],
                                            'Store' => $options['bocs_headers']['store'],
                                            'Authorization' => $options['bocs_headers']['authorization'],
                                        ),
                                        'body' => json_encode(array(
                                            'metaData' => $metadata
                                        )),
                                        'timeout' => 30
                                    )
                                );
                                
                                if (is_wp_error($api_response)) {
                                    error_log("Failed to update subscription metadata: " . $api_response->get_error_message());
                                } else {
                                    error_log("Updated subscription metadata with correct payment method ID");
                                }
                            } else {
                                error_log("No payment methods found for customer");
                            }
                        } catch (\Exception $e2) {
                            error_log("Error getting customer payment methods: " . $e2->getMessage());
                        }
                    }
                }
                
                if (!$payment_method && $retrieval_error) {
                    throw new Exception("Could not retrieve payment method: {$retrieval_error}");
                }
            } catch (\Stripe\Exception\ApiErrorException $e) {
                error_log("Stripe API error: " . $e->getMessage());
                throw new Exception('Error retrieving payment details from Stripe: ' . $e->getMessage());
            }
            
            // Format the response with card details
            $payment_methods = [];
            if ($payment_method && isset($payment_method->card)) {
                $payment_methods[] = [
                    'id' => $payment_method->id,
                    'last4' => $payment_method->card->last4,
                    'brand' => $payment_method->card->brand,
                    'exp_month' => $payment_method->card->exp_month,
                    'exp_year' => $payment_method->card->exp_year,
                    'formatted' => sprintf(
                        '**** **** **** %s Expires %s/%s',
                        $payment_method->card->last4,
                        $payment_method->card->exp_month,
                        $payment_method->card->exp_year
                    ),
                    'is_subscription_method' => true
                ];
            }
            
            // Get all stored payment methods from WooCommerce
            $wc_tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), 'stripe');
            foreach ($wc_tokens as $token) {
                if ($token instanceof WC_Payment_Token_CC) {
                    // Skip if it's the same payment method as from subscription
                    if ($token->get_token() === $stripe_source_id) {
                        continue;
                    }
                    
                    $payment_methods[] = [
                        'id' => $token->get_token(),
                        'last4' => $token->get_last4(),
                        'brand' => $token->get_card_type(),
                        'exp_month' => $token->get_expiry_month(),
                        'exp_year' => $token->get_expiry_year(),
                        'formatted' => sprintf(
                            '**** **** **** %s Expires %s/%s',
                            $token->get_last4(),
                            $token->get_expiry_month(),
                            $token->get_expiry_year()
                        ),
                        'is_subscription_method' => false,
                        'token_id' => $token->get_id()
                    ];
                }
            }
            
            wp_send_json_success([
                'payment_methods' => $payment_methods,
                'current_method' => $stripe_source_id
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Display payment methods for subscription shortcode
     *
     * Creates a shortcode to display payment methods for a subscription
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function subscription_payment_methods_shortcode($atts) {
        $atts = shortcode_atts(array(
            'subscription_id' => '',
        ), $atts, 'bocs_subscription_payment_methods');
        
        if (empty($atts['subscription_id'])) {
            return '<p>' . __('No subscription specified', 'bocs-wordpress') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="bocs-subscription-payment-methods" data-subscription-id="<?php echo esc_attr($atts['subscription_id']); ?>">
            <h3><?php _e('Payment Methods', 'bocs-wordpress'); ?></h3>
            <p class="loading"><?php _e('Loading payment methods...', 'bocs-wordpress'); ?></p>
            <div class="methods-list" style="display:none;"></div>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    var $container = $('.bocs-subscription-payment-methods');
                    var subscriptionId = $container.data('subscription-id');
                    
                    // Get payment methods for this subscription
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'get_subscription_payment_methods',
                            nonce: '<?php echo wp_create_nonce('bocs_ajax_nonce'); ?>',
                            subscription_id: subscriptionId
                        },
                        success: function(response) {
                            $container.find('.loading').hide();
                            
                            if (response.success && response.data.payment_methods.length > 0) {
                                var $list = $container.find('.methods-list');
                                var paymentMethods = response.data.payment_methods;
                                var currentMethod = response.data.current_method;
                                
                                // Create method selection form
                                var $form = $('<form class="payment-methods-form"></form>');
                                
                                // Add each method as a radio option
                                $.each(paymentMethods, function(index, method) {
                                    var $label = $('<label class="payment-method-option"></label>');
                                    var isSelected = method.id === currentMethod;
                                    
                                    var $input = $('<input type="radio" name="payment_method" value="' + method.id + '" ' + (isSelected ? 'checked' : '') + ' />');
                                    if (method.token_id) {
                                        $input.attr('data-token-id', method.token_id);
                                    }
                                    
                                    $label.append($input);
                                    $label.append(' ' + method.formatted);
                                    
                                    if (method.is_subscription_method) {
                                        $label.append(' <span class="current-method-label">(Current)</span>');
                                    }
                                    
                                    $form.append($label);
                                    $form.append('<br>');
                                });
                                
                                // Add option for new payment method
                                var $newLabel = $('<label class="payment-method-option"></label>');
                                var $newInput = $('<input type="radio" name="payment_method" value="new" />');
                                $newLabel.append($newInput);
                                $newLabel.append(' <?php _e('Add new payment method', 'bocs-wordpress'); ?>');
                                $form.append($newLabel);
                                
                                // Add update button
                                $form.append('<div class="update-button-container"><button type="button" class="button update-payment-method"><?php _e('Update Payment Method', 'bocs-wordpress'); ?></button></div>');
                                
                                $list.append($form);
                                $list.show();
                                
                                // Handle update button click
                                $form.find('.update-payment-method').on('click', function() {
                                    var selectedMethod = $form.find('input[name="payment_method"]:checked').val();
                                    
                                    if (selectedMethod === 'new') {
                                        // Redirect to add new payment method
                                        window.location.href = '<?php echo esc_url(add_query_arg(['add-payment-method' => '1', 'subscription_id' => $atts['subscription_id']], wc_get_endpoint_url('add-payment-method'))); ?>';
                                    } else if (selectedMethod === currentMethod) {
                                        alert('<?php _e('This is already the current payment method', 'bocs-wordpress'); ?>');
                                    } else {
                                        // Update the subscription with the selected payment method
                                        var tokenId = $form.find('input[name="payment_method"]:checked').data('token-id');
                                        
                                        $.ajax({
                                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                                            type: 'POST',
                                            data: {
                                                action: 'update_subscription_payment',
                                                nonce: '<?php echo wp_create_nonce('bocs_ajax_nonce'); ?>',
                                                subscription_id: subscriptionId,
                                                payment_method: tokenId
                                            },
                                            beforeSend: function() {
                                                $form.find('.update-payment-method').text('<?php _e('Processing...', 'bocs-wordpress'); ?>').prop('disabled', true);
                                            },
                                            success: function(response) {
                                                if (response.success) {
                                                    alert('<?php _e('Payment method updated successfully', 'bocs-wordpress'); ?>');
                                                    window.location.reload();
                                                } else {
                                                    alert(response.data.message || '<?php _e('Failed to update payment method', 'bocs-wordpress'); ?>');
                                                    $form.find('.update-payment-method').text('<?php _e('Update Payment Method', 'bocs-wordpress'); ?>').prop('disabled', false);
                                                }
                                            },
                                            error: function() {
                                                alert('<?php _e('An error occurred. Please try again.', 'bocs-wordpress'); ?>');
                                                $form.find('.update-payment-method').text('<?php _e('Update Payment Method', 'bocs-wordpress'); ?>').prop('disabled', false);
                                            }
                                        });
                                    }
                                });
                            } else {
                                $container.append('<p><?php _e('No payment methods found', 'bocs-wordpress'); ?></p>');
                            }
                        },
                        error: function() {
                            $container.find('.loading').hide();
                            $container.append('<p><?php _e('Error loading payment methods', 'bocs-wordpress'); ?></p>');
                        }
                    });
                });
            </script>
            <style>
                .bocs-subscription-payment-methods .payment-method-option {
                    display: block;
                    margin-bottom: 10px;
                }
                .bocs-subscription-payment-methods .current-method-label {
                    font-style: italic;
                    color: #007cba;
                }
                .bocs-subscription-payment-methods .update-button-container {
                    margin-top: 15px;
                }
            </style>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render payment methods directly on the subscription details page
     *
     * @since 1.0.0
     * @param string $subscription_id The subscription ID
     * @return void
     */
    public function render_subscription_payment_methods($subscription_id) {
        if (empty($subscription_id)) {
            return;
        }
        
        // Get subscription data
        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();
        
        try {
            $get_response = wp_remote_get(
                BOCS_API_URL . "subscriptions/{$subscription_id}",
                array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Organization' => $options['bocs_headers']['organization'],
                        'Store' => $options['bocs_headers']['store'],
                        'Authorization' => $options['bocs_headers']['authorization'],
                    ),
                    'timeout' => 30
                )
            );
            
            if (is_wp_error($get_response)) {
                echo '<p>' . __('Error loading subscription details', 'bocs-wordpress') . '</p>';
                return;
            }
            
            $subscription_data = json_decode(wp_remote_retrieve_body($get_response), true);
            
            if (!isset($subscription_data['data']) || !isset($subscription_data['data']['metaData'])) {
                echo '<p>' . __('Invalid subscription data format', 'bocs-wordpress') . '</p>';
                return;
            }
            
            // Extract payment details
            $metadata = $subscription_data['data']['metaData'];
            $stripe_source_id = '';
            $stripe_customer_id = '';
            
            foreach ($metadata as $item) {
                if ($item['key'] === '_stripe_source_id') {
                    $stripe_source_id = $item['value'];
                } else if ($item['key'] === '_stripe_customer_id') {
                    $stripe_customer_id = $item['value'];
                }
            }
            
            if (empty($stripe_source_id)) {
                error_log("No Stripe source ID found in metadata");
                // Modified to show "Add new payment method" option instead of exception
                ob_start();
                echo '<h3>' . __('Edit Payment Method', 'bocs-wordpress') . '</h3>';
                echo '<div class="saved-payment-methods">';
                echo '<h4>' . __('Saved Payment Methods', 'bocs-wordpress') . '</h4>';
                echo '<p>' . __('No payment method found for this subscription', 'bocs-wordpress') . '</p>';
                
                echo '<form class="payment-methods-form">';
                
                // Add new payment method option
                echo '<label class="payment-method-option">';
                echo '<input type="radio" name="payment_method" value="new" />';
                echo ' ' . __('Add new payment method', 'bocs-wordpress');
                echo '</label>';
                
                // Footer buttons (Cancel / Update)
                echo '<div class="modal-buttons">';
                echo '<button type="button" class="button cancel-update">' . __('Cancel', 'bocs-wordpress') . '</button>';
                echo '<button type="button" class="button primary update-payment-method">' . __('Add Payment Method', 'bocs-wordpress') . '</button>';
                echo '</div>';
                
                echo '</form>';
                echo '</div>';
                
                // Add JavaScript for form handling
                ?>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('.cancel-update').on('click', function() {
                            $('#payment-method-modal').hide();
                            // Clear modal content except for loading message
                            $('#payment-method-modal').find('.bocs-modal-body').html('<p class="loading"><?php _e('Loading payment methods...', 'bocs-wordpress'); ?></p>');
                        });
                        
                        $('.update-payment-method').on('click', function() {
                            // Redirect to add new payment method
                            window.location.href = '<?php echo esc_url(add_query_arg(['add-payment-method' => '1', 'subscription_id' => $subscription_id], wc_get_endpoint_url('add-payment-method'))); ?>';
                        });
                    });
                </script>
                <style>
                    .saved-payment-methods {
                        margin-top: 20px;
                    }
                    .payment-method-option {
                        display: block;
                        margin-bottom: 10px;
                    }
                    .modal-buttons {
                        margin-top: 20px;
                        text-align: right;
                    }
                    .modal-buttons .button {
                        margin-left: 10px;
                    }
                    .button.primary {
                        background-color: #7b68ee;
                        color: white;
                    }
                </style>
                <?php
                $html = ob_get_clean();
                
                wp_send_json_success([
                    'html' => $html,
                    'subscription_id' => $subscription_id,
                    'debug_info' => [
                        'source_id' => 'none',
                        'has_payment_method' => false
                    ]
                ]);
                return;
            }
            
            // Get payment method details from Stripe
            $stripe_settings = get_option('woocommerce_stripe_settings', []);
            $test_mode = isset($stripe_settings['testmode']) && $stripe_settings['testmode'] === 'yes';
            $secret_key = $test_mode ? $stripe_settings['test_secret_key'] : $stripe_settings['secret_key'];
            
            if (empty($secret_key)) {
                echo '<p>' . __('Stripe is not properly configured', 'bocs-wordpress') . '</p>';
                return;
            }
            
            // Initialize Stripe
            $stripe = new \Stripe\StripeClient($secret_key);
            
            try {
                // Get payment method details
                $payment_method = null;
                $retrieval_error = null;
                
                // First try to retrieve as a payment method (pm_)
                try {
                    if (strpos($stripe_source_id, 'pm_') === 0) {
                        $payment_method = $stripe->paymentMethods->retrieve($stripe_source_id);
                    } else if (strpos($stripe_source_id, 'src_') === 0 || strpos($stripe_source_id, 'card_') === 0) {
                        // If it's a source or card, try to retrieve it that way
                        $source = $stripe->sources->retrieve($stripe_source_id);
                        
                        // Create a synthetic payment method object from the source
                        $payment_method = new stdClass();
                        $payment_method->id = $source->id;
                        $payment_method->type = $source->type;
                        $payment_method->card = new stdClass();
                        $payment_method->card->brand = $source->card->brand;
                        $payment_method->card->last4 = $source->card->last4;
                        $payment_method->card->exp_month = $source->card->exp_month;
                        $payment_method->card->exp_year = $source->card->exp_year;
                    } else {
                        // Try both approaches - first as payment method
                        try {
                            $payment_method = $stripe->paymentMethods->retrieve($stripe_source_id);
                        } catch (\Exception $e) {
                            // Then as source
                            try {
                                $source = $stripe->sources->retrieve($stripe_source_id);
                                
                                // Create a synthetic payment method object
                                $payment_method = new stdClass();
                                $payment_method->id = $source->id;
                                $payment_method->type = $source->type;
                                $payment_method->card = new stdClass();
                                $payment_method->card->brand = $source->card->brand;
                                $payment_method->card->last4 = $source->card->last4;
                                $payment_method->card->exp_month = $source->card->exp_month;
                                $payment_method->card->exp_year = $source->card->exp_year;
                            } catch (\Exception $e2) {
                                // Combined error
                                throw new Exception("Failed to retrieve as payment method: {$e->getMessage()} and as source: {$e2->getMessage()}");
                            }
                        }
                    }
                    
                    // Log the payment method details
                    error_log("Payment method retrieved: " . json_encode([
                        'id' => $payment_method->id,
                        'type' => $payment_method->type ?? 'unknown',
                        'card' => $payment_method->card ? [
                            'brand' => $payment_method->card->brand,
                            'last4' => $payment_method->card->last4,
                            'exp_month' => $payment_method->card->exp_month,
                            'exp_year' => $payment_method->card->exp_year
                        ] : 'No card data'
                    ]));
                } catch (\Exception $e) {
                    $retrieval_error = $e->getMessage();
                    error_log("Error retrieving payment method: " . $retrieval_error);
                    
                    // Try to get payment methods for customer if we have customer ID
                    $customer_id = null;
                    foreach ($metadata as $item) {
                        if ($item['key'] === '_stripe_customer_id') {
                            $customer_id = $item['value'];
                            break;
                        }
                    }
                    
                    if (!empty($customer_id)) {
                        try {
                            error_log("Attempting to list payment methods for customer: {$customer_id}");
                            $methods = $stripe->paymentMethods->all([
                                'customer' => $customer_id,
                                'type' => 'card'
                            ]);
                            
                            if (!empty($methods->data)) {
                                // Use the first payment method
                                $payment_method = $methods->data[0];
                                error_log("Found customer payment method: " . $payment_method->id);
                                
                                // Update subscription with this payment method
                                foreach ($metadata as &$meta) {
                                    if ($meta['key'] === '_stripe_source_id') {
                                        $meta['value'] = $payment_method->id;
                                        break;
                                    }
                                }
                                
                                // Update subscription metadata with correct payment method
                                $api_response = wp_remote_request(
                                    BOCS_API_URL . "subscriptions/{$subscription_id}",
                                    array(
                                        'method' => 'PUT',
                                        'headers' => array(
                                            'Content-Type' => 'application/json',
                                            'Organization' => $options['bocs_headers']['organization'],
                                            'Store' => $options['bocs_headers']['store'],
                                            'Authorization' => $options['bocs_headers']['authorization'],
                                        ),
                                        'body' => json_encode(array(
                                            'metaData' => $metadata
                                        )),
                                        'timeout' => 30
                                    )
                                );
                                
                                if (is_wp_error($api_response)) {
                                    error_log("Failed to update subscription metadata: " . $api_response->get_error_message());
                                } else {
                                    error_log("Updated subscription metadata with correct payment method ID");
                                }
                            } else {
                                error_log("No payment methods found for customer");
                            }
                        } catch (\Exception $e2) {
                            error_log("Error getting customer payment methods: " . $e2->getMessage());
                        }
                    }
                }
                
                if (!$payment_method && $retrieval_error) {
                    throw new Exception("Could not retrieve payment method: {$retrieval_error}");
                }
            } catch (\Stripe\Exception\ApiErrorException $e) {
                error_log("Stripe API error: " . $e->getMessage());
                throw new Exception('Error retrieving payment details from Stripe: ' . $e->getMessage());
            }
            
            // Build the HTML for payment methods
            ob_start();
            
            // Start with the Edit Payment Method title
            echo '<h3>' . __('Edit Payment Method', 'bocs-wordpress') . '</h3>';
            
            echo '<div class="saved-payment-methods">';
            echo '<h4>' . __('Saved Payment Methods', 'bocs-wordpress') . '</h4>';
            
            echo '<form class="payment-methods-form">';
            
            // First add the current subscription payment method
            if ($payment_method && isset($payment_method->card)) {
                $formatted_subscription_method = sprintf(
                    '**** **** **** %s Expires %s/%s',
                    $payment_method->card->last4,
                    $payment_method->card->exp_month,
                    $payment_method->card->exp_year
                );
                
                echo '<label class="payment-method-option">';
                echo '<input type="radio" name="payment_method" value="subscription_method" checked />';
                echo ' ' . $formatted_subscription_method;
                echo '</label>';
                echo '<br>';
            } else {
                error_log("Payment method doesn't have card details");
                echo '<p>' . __('Unable to display current payment method details', 'bocs-wordpress') . '</p>';
            }
            
            // Get saved payment methods from WooCommerce
            $tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), 'stripe');
            
            error_log("Found " . count($tokens) . " WooCommerce payment tokens");
            
            foreach ($tokens as $token) {
                if ($token instanceof WC_Payment_Token_CC) {
                    // Skip if it's the same as the current payment method
                    if ($token->get_token() === $stripe_source_id) {
                        error_log("Skipping token {$token->get_id()} as it matches current source ID");
                        continue;
                    }
                    
                    error_log("Adding token to list: " . json_encode([
                        'id' => $token->get_id(),
                        'token' => $token->get_token(),
                        'last4' => $token->get_last4(),
                        'exp_month' => $token->get_expiry_month(),
                        'exp_year' => $token->get_expiry_year()
                    ]));
                    
                    $formatted_method = sprintf(
                        '**** **** **** %s Expires %s/%s',
                        $token->get_last4(),
                        $token->get_expiry_month(),
                        $token->get_expiry_year()
                    );
                    
                    echo '<label class="payment-method-option">';
                    echo '<input type="radio" name="payment_method" value="' . esc_attr($token->get_id()) . '" />';
                    echo ' ' . $formatted_method;
                    echo '</label>';
                    echo '<br>';
                }
            }
            
            // Add new payment method option
            echo '<label class="payment-method-option">';
            echo '<input type="radio" name="payment_method" value="new" />';
            echo ' ' . __('Add new payment method', 'bocs-wordpress');
            echo '</label>';
            
            // Footer buttons (Cancel / Update)
            echo '<div class="modal-buttons">';
            echo '<button type="button" class="button cancel-update">' . __('Cancel', 'bocs-wordpress') . '</button>';
            echo '<button type="button" class="button primary update-payment-method">' . __('Update Payment Method', 'bocs-wordpress') . '</button>';
            echo '</div>';
            
            echo '</form>';
            echo '</div>';
            
            // Add JavaScript for form handling
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('.cancel-update').on('click', function() {
                        $('#payment-method-modal').hide();
                        // Clear modal content except for loading message
                        $('#payment-method-modal').find('.bocs-modal-body').html('<p class="loading"><?php _e('Loading payment methods...', 'bocs-wordpress'); ?></p>');
                    });
                    
                    $('.update-payment-method').on('click', function() {
                        var selectedMethod = $('input[name="payment_method"]:checked').val();
                        
                        if (!selectedMethod) {
                            alert('<?php _e('Please select a payment method', 'bocs-wordpress'); ?>');
                            return;
                        }
                        
                        if (selectedMethod === 'subscription_method') {
                            // Current method is already selected
                            alert('<?php _e('This is already the current payment method', 'bocs-wordpress'); ?>');
                            return;
                        }
                        
                        if (selectedMethod === 'new') {
                            // Redirect to add new payment method
                            window.location.href = '<?php echo esc_url(add_query_arg(['add-payment-method' => '1', 'subscription_id' => $subscription_id], wc_get_endpoint_url('add-payment-method'))); ?>';
                            return;
                        }
                        
                        // Update the subscription with the selected payment method
                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'update_subscription_payment',
                                nonce: '<?php echo wp_create_nonce('bocs_ajax_nonce'); ?>',
                                subscription_id: '<?php echo esc_js($subscription_id); ?>',
                                payment_method: selectedMethod
                            },
                            beforeSend: function() {
                                $('.update-payment-method').text('<?php _e('Processing...', 'bocs-wordpress'); ?>').prop('disabled', true);
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert('<?php _e('Payment method updated successfully', 'bocs-wordpress'); ?>');
                                    window.location.reload();
                                } else {
                                    alert(response.data.message || '<?php _e('Failed to update payment method', 'bocs-wordpress'); ?>');
                                    $('.update-payment-method').text('<?php _e('Update Payment Method', 'bocs-wordpress'); ?>').prop('disabled', false);
                                }
                            },
                            error: function() {
                                alert('<?php _e('An error occurred. Please try again.', 'bocs-wordpress'); ?>');
                                $('.update-payment-method').text('<?php _e('Update Payment Method', 'bocs-wordpress'); ?>').prop('disabled', false);
                            }
                        });
                    });
                });
            </script>
            <style>
                .saved-payment-methods {
                    margin-top: 20px;
                }
                .payment-method-option {
                    display: block;
                    margin-bottom: 10px;
                }
                .modal-buttons {
                    margin-top: 20px;
                    text-align: right;
                }
                .modal-buttons .button {
                    margin-left: 10px;
                }
                .button.primary {
                    background-color: #7b68ee;
                    color: white;
                }
                .payment-method-error {
                    padding: 10px;
                    background: #fef8f8;
                    border-left: 4px solid #d63638;
                    margin-bottom: 15px;
                }
            </style>
            <?php
            
            $html = ob_get_clean();
            
            wp_send_json_success([
                'html' => $html,
                'subscription_id' => $subscription_id,
                'debug_info' => [
                    'source_id' => $stripe_source_id,
                    'customer_id' => $stripe_customer_id,
                    'has_payment_method' => !empty($payment_method),
                    'has_card_details' => !empty($payment_method) && isset($payment_method->card),
                    'retrieval_attempts' => $debug_info['attempts'],
                    'test_mode' => $test_mode,
                    'error' => $retrieval_error
                ]
            ]);
        } catch (Exception $e) {
            error_log("Error in get_subscription_payment_method_direct: " . $e->getMessage());
            wp_send_json_error([
                'message' => $e->getMessage(),
                'subscription_id' => $subscription_id ?? 'not set'
            ]);
        }
    }

    /**
     * Debug function to test retrieving a payment method directly from Stripe
     *
     * @since 1.0.0
     * @return void Sends JSON response
     */
    public function debug_payment_method() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
            return;
        }
        
        $payment_method_id = isset($_GET['payment_method_id']) ? sanitize_text_field($_GET['payment_method_id']) : '';
        if (empty($payment_method_id)) {
            wp_send_json_error(['message' => 'Payment method ID is required']);
            return;
        }
        
        try {
            // Get Stripe settings
            $stripe_settings = get_option('woocommerce_stripe_settings', []);
            $test_mode = isset($stripe_settings['testmode']) && $stripe_settings['testmode'] === 'yes';
            $secret_key = $test_mode ? $stripe_settings['test_secret_key'] : $stripe_settings['secret_key'];
            
            if (empty($secret_key)) {
                throw new Exception('Stripe secret key is not configured');
            }
            
            // Initialize Stripe
            $stripe = new \Stripe\StripeClient($secret_key);
            
            $results = [];
            $errors = [];
            
            // Try to retrieve as payment method (pm_)
            try {
                if (strpos($payment_method_id, 'pm_') === 0) {
                    $payment_method = $stripe->paymentMethods->retrieve($payment_method_id);
                    $results['payment_method'] = [
                        'id' => $payment_method->id,
                        'type' => $payment_method->type,
                        'card' => isset($payment_method->card) ? [
                            'brand' => $payment_method->card->brand,
                            'last4' => $payment_method->card->last4,
                            'exp_month' => $payment_method->card->exp_month,
                            'exp_year' => $payment_method->card->exp_year
                        ] : null
                    ];
                }
            } catch (\Exception $e) {
                $errors['payment_method'] = $e->getMessage();
            }
            
            // Try to retrieve as source (src_)
            try {
                if (strpos($payment_method_id, 'src_') === 0 || strpos($payment_method_id, 'card_') === 0) {
                    $source = $stripe->sources->retrieve($payment_method_id);
                    $results['source'] = [
                        'id' => $source->id,
                        'type' => $source->type,
                        'card' => isset($source->card) ? [
                            'brand' => $source->card->brand,
                            'last4' => $source->card->last4,
                            'exp_month' => $source->card->exp_month,
                            'exp_year' => $source->card->exp_year
                        ] : null
                    ];
                }
            } catch (\Exception $e) {
                $errors['source'] = $e->getMessage();
            }
            
            // Try to get customer ID for this payment method
            $customer_id = '';
            foreach ($_GET as $key => $value) {
                if ($key === 'customer_id') {
                    $customer_id = sanitize_text_field($value);
                    break;
                }
            }
            
            if (!empty($customer_id)) {
                try {
                    $methods = $stripe->paymentMethods->all([
                        'customer' => $customer_id,
                        'type' => 'card'
                    ]);
                    
                    $results['customer_methods'] = [];
                    foreach ($methods->data as $method) {
                        $results['customer_methods'][] = [
                            'id' => $method->id,
                            'type' => $method->type,
                            'card' => isset($method->card) ? [
                                'brand' => $method->card->brand,
                                'last4' => $method->card->last4,
                                'exp_month' => $method->card->exp_month,
                                'exp_year' => $method->card->exp_year
                            ] : null
                        ];
                    }
                } catch (\Exception $e) {
                    $errors['customer_methods'] = $e->getMessage();
                }
            }
            
            // Get API info
            $results['api_info'] = [
                'test_mode' => $test_mode,
                'secret_key_prefix' => substr($secret_key, 0, 10) . '...'
            ];
            
            if (empty($results['payment_method']) && empty($results['source']) && empty($results['customer_methods'])) {
                if (!empty($errors)) {
                    throw new Exception('Failed to retrieve payment details: ' . json_encode($errors));
                } else {
                    throw new Exception('No payment details found for the given ID');
                }
            }
            
            wp_send_json_success([
                'results' => $results,
                'errors' => $errors
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle payment method retrieval directly from Stripe
     *
     * @since 1.0.0
     * @return void Sends JSON response
     */
    public function get_subscription_payment_method_direct() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bocs_ajax_nonce')) {
            wp_send_json_error(['message' => 'Invalid security token']);
            return;
        }

        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
        if (empty($subscription_id)) {
            wp_send_json_error(['message' => 'Subscription ID is required']);
            return;
        }

        try {
            // Get subscription data
            $options = get_option('bocs_plugin_options');
            $options['bocs_headers'] = $options['bocs_headers'] ?? array();

            error_log("Fetching subscription data for ID: {$subscription_id}");
            
            $get_response = wp_remote_get(
                BOCS_API_URL . "subscriptions/{$subscription_id}",
                array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Organization' => $options['bocs_headers']['organization'],
                        'Store' => $options['bocs_headers']['store'],
                        'Authorization' => $options['bocs_headers']['authorization'],
                    ),
                    'timeout' => 30
                )
            );

            if (is_wp_error($get_response)) {
                error_log("Error fetching subscription: " . $get_response->get_error_message());
                throw new Exception('Failed to fetch subscription details: ' . $get_response->get_error_message());
            }

            $subscription_data = json_decode(wp_remote_retrieve_body($get_response), true);
            
            // Extract payment details
            $metadata = $subscription_data['data']['metaData'] ?? [];
            $stripe_source_id = '';
            $stripe_customer_id = '';
            
            error_log("Subscription metadata: " . json_encode($metadata));
            
            foreach ($metadata as $item) {
                if ($item['key'] === '_stripe_source_id') {
                    $stripe_source_id = $item['value'];
                    error_log("Found Stripe source ID: {$stripe_source_id}");
                } elseif ($item['key'] === '_stripe_customer_id') {
                    $stripe_customer_id = $item['value'];
                    error_log("Found Stripe customer ID: {$stripe_customer_id}");
                }
            }
            
            if (empty($stripe_source_id)) {
                error_log("No Stripe source ID found in metadata");
                // Modified to show "Add new payment method" option instead of exception
                ob_start();
                echo '<h3>' . __('Edit Payment Method', 'bocs-wordpress') . '</h3>';
                echo '<div class="saved-payment-methods">';
                echo '<h4>' . __('Saved Payment Methods', 'bocs-wordpress') . '</h4>';
                echo '<p>' . __('No payment method found for this subscription', 'bocs-wordpress') . '</p>';
                
                echo '<form class="payment-methods-form">';
                
                // Add new payment method option
                echo '<label class="payment-method-option">';
                echo '<input type="radio" name="payment_method" value="new" />';
                echo ' ' . __('Add new payment method', 'bocs-wordpress');
                echo '</label>';
                
                // Footer buttons (Cancel / Update)
                echo '<div class="modal-buttons">';
                echo '<button type="button" class="button cancel-update">' . __('Cancel', 'bocs-wordpress') . '</button>';
                echo '<button type="button" class="button primary update-payment-method">' . __('Add Payment Method', 'bocs-wordpress') . '</button>';
                echo '</div>';
                
                echo '</form>';
                echo '</div>';
                
                // Add JavaScript for form handling
                ?>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('.cancel-update').on('click', function() {
                            $('#payment-method-modal').hide();
                            // Clear modal content except for loading message
                            $('#payment-method-modal').find('.bocs-modal-body').html('<p class="loading"><?php _e('Loading payment methods...', 'bocs-wordpress'); ?></p>');
                        });
                        
                        $('.update-payment-method').on('click', function() {
                            // Redirect to add new payment method
                            window.location.href = '<?php echo esc_url(add_query_arg(['add-payment-method' => '1', 'subscription_id' => $subscription_id], wc_get_endpoint_url('add-payment-method'))); ?>';
                        });
                    });
                </script>
                <style>
                    .saved-payment-methods {
                        margin-top: 20px;
                    }
                    .payment-method-option {
                        display: block;
                        margin-bottom: 10px;
                    }
                    .modal-buttons {
                        margin-top: 20px;
                        text-align: right;
                    }
                    .modal-buttons .button {
                        margin-left: 10px;
                    }
                    .button.primary {
                        background-color: #7b68ee;
                        color: white;
                    }
                </style>
                <?php
                $html = ob_get_clean();
                
                wp_send_json_success([
                    'html' => $html,
                    'subscription_id' => $subscription_id,
                    'debug_info' => [
                        'source_id' => 'none',
                        'has_payment_method' => false
                    ]
                ]);
                return;
            }
            
            // Get Stripe payment method details
            $stripe_settings = get_option('woocommerce_stripe_settings', []);
            $test_mode = isset($stripe_settings['testmode']) && $stripe_settings['testmode'] === 'yes';
            $secret_key = $test_mode ? $stripe_settings['test_secret_key'] : $stripe_settings['secret_key'];
            
            if (empty($secret_key)) {
                error_log("Stripe secret key is not configured");
                throw new Exception('Stripe secret key is not configured');
            }
            
            error_log("Initializing Stripe with source ID: {$stripe_source_id}");
            
            // Initialize Stripe
            $stripe = new \Stripe\StripeClient($secret_key);
            
            $payment_method = null;
            $retrieval_error = null;
            $debug_info = ['attempts' => []];
            
            // First try to retrieve as a payment method (pm_)
            try {
                if (strpos($stripe_source_id, 'pm_') === 0) {
                    error_log("Attempting to retrieve as payment method (pm_)");
                    $payment_method = $stripe->paymentMethods->retrieve($stripe_source_id);
                    $debug_info['attempts'][] = ['type' => 'payment_method', 'id' => $stripe_source_id, 'success' => true];
                } else if (strpos($stripe_source_id, 'src_') === 0 || strpos($stripe_source_id, 'card_') === 0) {
                    // If it's a source or card, try to retrieve it that way
                    error_log("Attempting to retrieve as source (src_ or card_)");
                    $source = $stripe->sources->retrieve($stripe_source_id);
                    $debug_info['attempts'][] = ['type' => 'source', 'id' => $stripe_source_id, 'success' => true];
                    
                    // Create a synthetic payment method object from the source
                    $payment_method = new stdClass();
                    $payment_method->id = $source->id;
                    $payment_method->type = $source->type;
                    $payment_method->card = new stdClass();
                    $payment_method->card->brand = $source->card->brand;
                    $payment_method->card->last4 = $source->card->last4;
                    $payment_method->card->exp_month = $source->card->exp_month;
                    $payment_method->card->exp_year = $source->card->exp_year;
                } else {
                    // Try both approaches - first as payment method
                    error_log("Attempting to retrieve unknown ID format as payment method");
                    try {
                        $payment_method = $stripe->paymentMethods->retrieve($stripe_source_id);
                        $debug_info['attempts'][] = ['type' => 'payment_method', 'id' => $stripe_source_id, 'success' => true];
                    } catch (\Exception $e) {
                        $debug_info['attempts'][] = ['type' => 'payment_method', 'id' => $stripe_source_id, 'success' => false, 'error' => $e->getMessage()];
                        
                        // Then as source
                        error_log("Failed as payment method, trying as source: " . $e->getMessage());
                        try {
                            $source = $stripe->sources->retrieve($stripe_source_id);
                            $debug_info['attempts'][] = ['type' => 'source', 'id' => $stripe_source_id, 'success' => true];
                            
                            // Create a synthetic payment method object
                            $payment_method = new stdClass();
                            $payment_method->id = $source->id;
                            $payment_method->type = $source->type;
                            $payment_method->card = new stdClass();
                            $payment_method->card->brand = $source->card->brand;
                            $payment_method->card->last4 = $source->card->last4;
                            $payment_method->card->exp_month = $source->card->exp_month;
                            $payment_method->card->exp_year = $source->card->exp_year;
                        } catch (\Exception $e2) {
                            // Combined error
                            $debug_info['attempts'][] = ['type' => 'source', 'id' => $stripe_source_id, 'success' => false, 'error' => $e2->getMessage()];
                            error_log("Failed as source: " . $e2->getMessage());
                            throw new Exception("Failed to retrieve as payment method: {$e->getMessage()} and as source: {$e2->getMessage()}");
                        }
                    }
                }
                
                // Log the payment method details
                if ($payment_method && isset($payment_method->card)) {
                    error_log("Payment method retrieved: " . json_encode([
                        'id' => $payment_method->id,
                        'type' => $payment_method->type ?? 'unknown',
                        'card' => [
                            'brand' => $payment_method->card->brand,
                            'last4' => $payment_method->card->last4,
                            'exp_month' => $payment_method->card->exp_month,
                            'exp_year' => $payment_method->card->exp_year
                        ]
                    ]));
                } else {
                    error_log("Retrieved payment method but no card details available");
                }
            } catch (\Exception $e) {
                $retrieval_error = $e->getMessage();
                error_log("Error retrieving payment method: " . $retrieval_error);
                
                // Try to get payment methods for customer if we have customer ID
                if (!empty($stripe_customer_id)) {
                    try {
                        error_log("Attempting to list payment methods for customer: {$stripe_customer_id}");
                        $debug_info['attempts'][] = ['type' => 'customer_methods', 'id' => $stripe_customer_id, 'attempted' => true];
                        
                        $methods = $stripe->paymentMethods->all([
                            'customer' => $stripe_customer_id,
                            'type' => 'card'
                        ]);
                        
                        if (!empty($methods->data)) {
                            // Use the first payment method
                            $payment_method = $methods->data[0];
                            error_log("Found customer payment method: " . $payment_method->id);
                            $debug_info['attempts'][] = ['type' => 'customer_methods', 'id' => $stripe_customer_id, 'success' => true, 'methods_found' => count($methods->data)];
                            
                            // Update subscription with this payment method
                            foreach ($metadata as &$meta) {
                                if ($meta['key'] === '_stripe_source_id') {
                                    $meta['value'] = $payment_method->id;
                                    break;
                                }
                            }
                            
                            // Update subscription metadata with correct payment method
                            $api_response = wp_remote_request(
                                BOCS_API_URL . "subscriptions/{$subscription_id}",
                                array(
                                    'method' => 'PUT',
                                    'headers' => array(
                                        'Content-Type' => 'application/json',
                                        'Organization' => $options['bocs_headers']['organization'],
                                        'Store' => $options['bocs_headers']['store'],
                                        'Authorization' => $options['bocs_headers']['authorization'],
                                    ),
                                    'body' => json_encode(array(
                                        'metaData' => $metadata
                                    )),
                                    'timeout' => 30
                                )
                            );
                            
                            if (is_wp_error($api_response)) {
                                error_log("Failed to update subscription metadata: " . $api_response->get_error_message());
                                $debug_info['update_metadata'] = ['success' => false, 'error' => $api_response->get_error_message()];
                            } else {
                                error_log("Updated subscription metadata with correct payment method ID");
                                $debug_info['update_metadata'] = ['success' => true];
                            }
                        } else {
                            error_log("No payment methods found for customer");
                            $debug_info['attempts'][] = ['type' => 'customer_methods', 'id' => $stripe_customer_id, 'success' => false, 'error' => 'No methods found'];
                        }
                    } catch (\Exception $e2) {
                        error_log("Error getting customer payment methods: " . $e2->getMessage());
                        $debug_info['attempts'][] = ['type' => 'customer_methods', 'id' => $stripe_customer_id, 'success' => false, 'error' => $e2->getMessage()];
                    }
                } else {
                    error_log("No customer ID available to try alternative retrieval methods");
                }
            }
            
            // Build the HTML for payment methods
            ob_start();
            
            // Start with the Edit Payment Method title
            echo '<h3>' . __('Edit Payment Method', 'bocs-wordpress') . '</h3>';
            
            echo '<div class="saved-payment-methods">';
            echo '<h4>' . __('Saved Payment Methods', 'bocs-wordpress') . '</h4>';
            
            echo '<form class="payment-methods-form">';
            
            // First add the current subscription payment method
            if ($payment_method && isset($payment_method->card)) {
                $formatted_subscription_method = sprintf(
                    '**** **** **** %s Expires %s/%s',
                    $payment_method->card->last4,
                    $payment_method->card->exp_month,
                    $payment_method->card->exp_year
                );
                
                echo '<label class="payment-method-option">';
                echo '<input type="radio" name="payment_method" value="subscription_method" checked />';
                echo ' ' . $formatted_subscription_method;
                echo '</label>';
                echo '<br>';
            } else {
                error_log("Payment method doesn't have card details");
                echo '<p>' . __('Unable to display current payment method details. The payment information may need to be updated.', 'bocs-wordpress') . '</p>';
                
                if ($retrieval_error) {
                    echo '<p class="error-details" style="color: #d63638; font-size: 12px;">Error: ' . esc_html($retrieval_error) . '</p>';
                }
                echo '</div>';
            }
            
            // Get saved payment methods from WooCommerce
            $tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), 'stripe');
            
            error_log("Found " . count($tokens) . " WooCommerce payment tokens");
            
            $has_tokens = false;
            foreach ($tokens as $token) {
                if ($token instanceof WC_Payment_Token_CC) {
                    // Skip if it's the same as the current payment method
                    if ($payment_method && isset($payment_method->id) && $token->get_token() === $payment_method->id) {
                        error_log("Skipping token {$token->get_id()} as it matches current source ID");
                        continue;
                    }
                    
                    error_log("Adding token to list: " . json_encode([
                        'id' => $token->get_id(),
                        'token' => $token->get_token(),
                        'last4' => $token->get_last4(),
                        'exp_month' => $token->get_expiry_month(),
                        'exp_year' => $token->get_expiry_year()
                    ]));
                    
                    $formatted_method = sprintf(
                        '**** **** **** %s Expires %s/%s',
                        $token->get_last4(),
                        $token->get_expiry_month(),
                        $token->get_expiry_year()
                    );
                    
                    echo '<label class="payment-method-option">';
                    echo '<input type="radio" name="payment_method" value="' . esc_attr($token->get_id()) . '" />';
                    echo ' ' . $formatted_method;
                    echo '</label>';
                    echo '<br>';
                    $has_tokens = true;
                }
            }
            
            // Add new payment method option
            echo '<label class="payment-method-option">';
            echo '<input type="radio" name="payment_method" value="new" />';
            echo ' ' . __('Add new payment method', 'bocs-wordpress');
            echo '</label>';
            
            // Footer buttons (Cancel / Update)
            echo '<div class="modal-buttons">';
            echo '<button type="button" class="button cancel-update">' . __('Cancel', 'bocs-wordpress') . '</button>';
            echo '<button type="button" class="button primary update-payment-method">' . __('Update Payment Method', 'bocs-wordpress') . '</button>';
            echo '</div>';
            
            echo '</form>';
            echo '</div>';
            
            // Add JavaScript for form handling
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('.cancel-update').on('click', function() {
                        $('#payment-method-modal').hide();
                        // Clear modal content except for loading message
                        $('#payment-method-modal').find('.bocs-modal-body').html('<p class="loading"><?php _e('Loading payment methods...', 'bocs-wordpress'); ?></p>');
                    });
                    
                    $('.update-payment-method').on('click', function() {
                        var selectedMethod = $('input[name="payment_method"]:checked').val();
                        
                        if (!selectedMethod) {
                            alert('<?php _e('Please select a payment method', 'bocs-wordpress'); ?>');
                            return;
                        }
                        
                        if (selectedMethod === 'subscription_method') {
                            // Current method is already selected
                            alert('<?php _e('This is already the current payment method', 'bocs-wordpress'); ?>');
                            return;
                        }
                        
                        if (selectedMethod === 'new') {
                            // Redirect to add new payment method
                            window.location.href = '<?php echo esc_url(add_query_arg(['add-payment-method' => '1', 'subscription_id' => $subscription_id], wc_get_endpoint_url('add-payment-method'))); ?>';
                            return;
                        }
                        
                        // Update the subscription with the selected payment method
                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'update_subscription_payment',
                                nonce: '<?php echo wp_create_nonce('bocs_ajax_nonce'); ?>',
                                subscription_id: '<?php echo esc_js($subscription_id); ?>',
                                payment_method: selectedMethod
                            },
                            beforeSend: function() {
                                $('.update-payment-method').text('<?php _e('Processing...', 'bocs-wordpress'); ?>').prop('disabled', true);
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert('<?php _e('Payment method updated successfully', 'bocs-wordpress'); ?>');
                                    window.location.reload();
                                } else {
                                    alert(response.data.message || '<?php _e('Failed to update payment method', 'bocs-wordpress'); ?>');
                                    $('.update-payment-method').text('<?php _e('Update Payment Method', 'bocs-wordpress'); ?>').prop('disabled', false);
                                }
                            },
                            error: function() {
                                alert('<?php _e('An error occurred. Please try again.', 'bocs-wordpress'); ?>');
                                $('.update-payment-method').text('<?php _e('Update Payment Method', 'bocs-wordpress'); ?>').prop('disabled', false);
                            }
                        });
                    });
                });
            </script>
            <style>
                .saved-payment-methods {
                    margin-top: 20px;
                }
                .payment-method-option {
                    display: block;
                    margin-bottom: 10px;
                }
                .modal-buttons {
                    margin-top: 20px;
                    text-align: right;
                }
                .modal-buttons .button {
                    margin-left: 10px;
                }
                .button.primary {
                    background-color: #7b68ee;
                    color: white;
                }
                .payment-method-error {
                    padding: 10px;
                    background: #fef8f8;
                    border-left: 4px solid #d63638;
                    margin-bottom: 15px;
                }
            </style>
            <?php
            
            $html = ob_get_clean();
            
            wp_send_json_success([
                'html' => $html,
                'subscription_id' => $subscription_id,
                'debug_info' => [
                    'source_id' => $stripe_source_id,
                    'customer_id' => $stripe_customer_id,
                    'has_payment_method' => !empty($payment_method),
                    'has_card_details' => !empty($payment_method) && isset($payment_method->card),
                    'retrieval_attempts' => $debug_info['attempts'],
                    'test_mode' => $test_mode,
                    'error' => $retrieval_error
                ]
            ]);
        } catch (Exception $e) {
            error_log("Error in get_subscription_payment_method_direct: " . $e->getMessage());
            wp_send_json_error([
                'message' => $e->getMessage(),
                'subscription_id' => $subscription_id ?? 'not set'
            ]);
        }
    }

    /**
     * Display status messages.
     *
     * Shows success/error messages after payment method updates.
     *
     * @since 0.0.115
     * @return void
     */
    public function show_status_messages() {
        // Check for the payment_method_updated flag
        $payment_method_updated = get_transient('bocs_payment_method_updated_' . get_current_user_id());
        
        // Show message if payment method was updated
        if ($payment_method_updated) {
            wc_print_notice(__('Your payment method has been successfully updated.', 'bocs-wordpress'), 'success');
            
            // Delete the transient so the message is only shown once
            delete_transient('bocs_payment_method_updated_' . get_current_user_id());
        }
    }

    /**
     * Get user billing details for Stripe
     */
    public function get_user_billing_details() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bocs_ajax_nonce')) {
            wp_send_json_error(['message' => 'Invalid security token']);
            return;
        }

        // Get current user
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'User not logged in']);
            return;
        }

        // Try to get existing Stripe customer ID from WooCommerce
        $stripe_customer_id = get_user_meta($user_id, '_stripe_customer_id', true);
        if (empty($stripe_customer_id)) {
            // If test mode is enabled, try the test mode customer ID
            $stripe_settings = get_option('woocommerce_stripe_settings', []);
            $test_mode = isset($stripe_settings['testmode']) && $stripe_settings['testmode'] === 'yes';
            if ($test_mode) {
                $stripe_customer_id = get_user_meta($user_id, '_stripe_customer_id', true);
            }
        }

        // Get billing information
        $billing_details = [
            'name' => get_user_meta($user_id, 'billing_first_name', true) . ' ' . get_user_meta($user_id, 'billing_last_name', true),
            'email' => get_user_meta($user_id, 'billing_email', true),
            'phone' => get_user_meta($user_id, 'billing_phone', true),
            'address' => [
                'line1' => get_user_meta($user_id, 'billing_address_1', true),
                'line2' => get_user_meta($user_id, 'billing_address_2', true), // Line2 is required, even if empty
                'city' => get_user_meta($user_id, 'billing_city', true),
                'state' => get_user_meta($user_id, 'billing_state', true),
                'postal_code' => get_user_meta($user_id, 'billing_postcode', true),
                'country' => get_user_meta($user_id, 'billing_country', true)
            ]
        ];

        // Validate and provide defaults for required fields
        if (empty(trim($billing_details['name']))) {
            $billing_details['name'] = 'Customer';
        }

        if (empty($billing_details['address']['country'])) {
            $billing_details['address']['country'] = 'AU'; // Default to Australia if empty
        }

        if (empty($billing_details['address']['postal_code'])) {
            $billing_details['address']['postal_code'] = '2000'; // Default to a valid postal code if empty
        }
        
        // Ensure line2 is set, even if empty
        if (!isset($billing_details['address']['line2'])) {
            $billing_details['address']['line2'] = '';
        }

        $response_data = [
            'billing_details' => $billing_details
        ];
        
        // Include Stripe customer ID if it exists
        if (!empty($stripe_customer_id)) {
            $response_data['stripe_customer_id'] = $stripe_customer_id;
        }

        wp_send_json_success($response_data);
    }
}