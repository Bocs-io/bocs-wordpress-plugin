<?php

/**
 * Bocs Payment Method Class
 *
 * Handles the payment method functionality for Bocs subscriptions
*/

class Bocs_Payment_Method {
    /**
     * Constructor for the Bocs_Payment_Method class
     *
     * Initializes the class and sets up the necessary hooks
     */
     
     /**
     * Create Stripe session for updating payment method
     */
    public function bocs_create_payment_update_session() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bocs_payment_update')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Verify subscription ID
        if (!isset($_POST['subscription_id'])) {
            wp_send_json_error('Missing subscription ID');
            return;
        }

        try {
            // Get WC Stripe Gateway instance
            $gateway = WC()->payment_gateways()->payment_gateways()['stripe'];
            
            if (!$gateway) {
                throw new Exception('Stripe gateway not found');
            }

            // Create setup intent
            $setup_intent = $gateway->stripe->setup_intents->create([
                'payment_method_types' => ['card'],
                'usage' => 'off_session',
                'metadata' => [
                    'subscription_id' => sanitize_text_field($_POST['subscription_id']),
                    'customer_id' => get_current_user_id(),
                ]
            ]);

            // Create Stripe Checkout Session
            $session = $gateway->stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'mode' => 'setup',
                'customer' => $gateway->get_stripe_customer_id(get_current_user_id()),
                'setup_intent' => $setup_intent->id,
                'success_url' => add_query_arg([
                    'payment-update' => 'success',
                    'subscription-id' => sanitize_text_field($_POST['subscription_id'])
                ], wc_get_account_endpoint_url('subscriptions')),
                'cancel_url' => add_query_arg('payment-update', 'cancelled', wc_get_account_endpoint_url('subscriptions')),
            ]);

            wp_send_json_success([
                'session_id' => $session->id
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle successful payment method update
     */
    public function bocs_handle_payment_update() {
        if (!isset($_GET['payment-update'])) {
            return;
        }

        if ($_GET['payment-update'] === 'success' && isset($_GET['subscription-id'])) {
            // Add success message
            wc_add_notice(__('Payment method successfully updated.', 'bocs-wordpress'), 'success');
        } elseif ($_GET['payment-update'] === 'cancelled') {
            // Add cancelled message
            wc_add_notice(__('Payment method update cancelled.', 'bocs-wordpress'), 'notice');
        }
    }
    
    /**
     * Enqueue Stripe JS for account page
     */
    public function bocs_enqueue_stripe_js() {
        if (is_account_page()) {
            wp_enqueue_script('stripe', 'https://js.stripe.com/v3/', [], null, true);
        }
    }

    /**
     * Get Stripe API instance from gateway
     *
     * @param WC_Payment_Gateway $gateway
     * @return \Stripe\StripeClient|null
     */
    private function get_stripe_api($gateway) {
        try {
            // Check if gateway has direct stripe property (traditional gateway)
            if (isset($gateway->stripe)) {
                return $gateway->stripe;
            }

            // Check if gateway is UPE gateway
            if (method_exists($gateway, 'get_stripe_client')) {
                return $gateway->get_stripe_client();
            }

            // If we can't get a Stripe client through the gateway methods, throw an exception
            throw new Exception('Unable to initialize Stripe API through WooCommerce gateway');

        } catch (Exception $e) {
            error_log('Error initializing Stripe API: ' . $e->getMessage());
            throw new Exception('Failed to initialize Stripe API: ' . $e->getMessage());
        }
    }

    /**
     * Get or create Stripe customer for the user
     *
     * @param WC_Payment_Gateway $stripe_gateway
     * @param WP_User $user
     * @return string|null Stripe customer ID
     */
    private function get_or_create_stripe_customer($stripe_gateway, $user) {
        try {
            // First try to get the customer ID directly from user meta
            $customer_id = get_user_meta($user->ID, '_stripe_customer_id', true);
            
            if (!empty($customer_id)) {
                return $customer_id;
            }

            // Get Stripe API instance
            $stripe_api = $this->get_stripe_api($stripe_gateway);
            if (!$stripe_api) {
                throw new Exception('Unable to initialize Stripe API');
            }

            // If no customer ID in meta, create a new Stripe customer
            try {
                $customer = $stripe_api->customers->create([
                    'email' => $user->user_email,
                    'metadata' => [
                        'wordpress_user_id' => $user->ID,
                        'source' => 'bocs_wordpress',
                    ],
                    'description' => 'BOCS WordPress customer - ' . $user->user_email
                ]);
                
                // Save the customer ID to user meta
                update_user_meta($user->ID, '_stripe_customer_id', $customer->id);
                
                return $customer->id;
                
            } catch (Exception $e) {
                error_log('Error creating Stripe customer: ' . $e->getMessage());
                throw new Exception('Failed to create Stripe customer: ' . $e->getMessage());
            }
        } catch (Exception $e) {
            error_log('Error in get_or_create_stripe_customer: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle payment update session creation
     */
    public function bocs_get_payment_update_session() {
        try {
            // Enable error reporting for debugging
            if (!empty($_SERVER['HTTP_X_BOCS_DEBUG'])) {
                error_reporting(E_ALL);
                ini_set('display_errors', 1);
            }

            // Log incoming request data
            error_log('BOCS Payment Update - Request Data: ' . print_r($_POST, true));

            // Verify nonce
            if (!check_ajax_referer('bocs_update_payment_method', 'security', false)) {
                throw new Exception('Invalid security token');
            }

            // Verify user is logged in
            if (!is_user_logged_in()) {
                throw new Exception('User not logged in');
            }

            // Get current user
            $user = wp_get_current_user();
            if (!$user || !$user->ID) {
                throw new Exception('Invalid user');
            }

            // Get and validate subscription ID
            $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
            if (empty($subscription_id)) {
                throw new Exception('Missing subscription ID');
            }

            // Validate BOCS subscription data
            $bocs_subscription_json = isset($_POST['bocs_subscription']) ? stripslashes($_POST['bocs_subscription']) : '';
            if (empty($bocs_subscription_json)) {
                throw new Exception('Missing BOCS subscription data');
            }

            $bocs_subscription = json_decode($bocs_subscription_json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid BOCS subscription data: ' . json_last_error_msg());
            }

            // Verify WooCommerce Stripe gateway is available
            if (!class_exists('WC_Gateway_Stripe')) {
                throw new Exception('WooCommerce Stripe gateway not installed');
            }

            $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
            $stripe_gateway = isset($available_gateways['stripe']) ? $available_gateways['stripe'] : null;
            
            if (!$stripe_gateway) {
                throw new Exception('WooCommerce Stripe gateway not available');
            }

            // Get Stripe client based on gateway type
            $stripe = $this->get_stripe_api($stripe_gateway);
            if (!$stripe) {
                throw new Exception('Unable to initialize Stripe client');
            }

            // Get customer ID from user meta
            $customer_id = get_user_meta($user->ID, '_stripe_customer_id', true);
            if (empty($customer_id)) {
                throw new Exception('No Stripe customer found for user');
            }

            // Create setup intent
            try {
                $setup_intent = $stripe->setupIntents->create([
                    'payment_method_types' => ['card'],
                    'usage' => 'off_session',
                    'customer' => $customer_id,
                    'metadata' => [
                        'subscription_id' => $subscription_id,
                        'customer_id' => $user->ID,
                    ]
                ]);
            } catch (Exception $e) {
                throw new Exception('Failed to create setup intent: ' . $e->getMessage());
            }

            // Create Stripe Checkout Session
            try {
                $session = $stripe->checkout->sessions->create([
                    'payment_method_types' => ['card'],
                    'mode' => 'setup',
                    'customer' => $customer_id,
                    'setup_intent' => $setup_intent->id,
                    'success_url' => add_query_arg([
                        'payment-update' => 'success',
                        'subscription-id' => $subscription_id
                    ], wc_get_account_endpoint_url('bocs-subscriptions')),
                    'cancel_url' => add_query_arg(
                        'payment-update', 
                        'cancelled', 
                        wc_get_account_endpoint_url('bocs-subscriptions')
                    ),
                ]);
            } catch (Exception $e) {
                throw new Exception('Failed to create checkout session: ' . $e->getMessage());
            }

            // Return success response
            wp_send_json_success([
                'redirect_url' => $session->url,
                'session_id' => $session->id
            ]);

        } catch (Exception $e) {
            // Log error for debugging
            error_log('BOCS Payment Update Error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());

            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'debug_info' => WP_DEBUG ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ] : null
            ]);
        }
    }
}