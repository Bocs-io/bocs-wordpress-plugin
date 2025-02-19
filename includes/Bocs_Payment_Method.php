<?php

/**
 * Bocs Payment Method Class
 *
 * Handles the payment method functionality for Bocs subscriptions
*/

class Bocs_Payment_Method {
    public function __construct() {
        // Ensure Stripe PHP SDK is loaded
        if (!class_exists('\Stripe\StripeClient')) {
            require_once(plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php');
        }
    }
    
    public function add_edit_payment_method_button($method, $payment_method) {
        if ($payment_method->get_type() === 'stripe') {
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
    }

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

            // Create a SetupIntent
            $setup_intent = $stripe->setupIntents->create([
                'payment_method_types' => ['card'],
                'usage' => 'off_session', // This allows the payment method to be used for future payments
                'metadata' => [
                    'subscription_id' => $subscription_id,
                    'source' => 'bocs_wordpress'
                ]
            ]);

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
                'subscription_id' => $subscription_id,
                'is_test_mode' => $test_mode,
                'saved_methods' => $saved_methods
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
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
     * Enqueue required scripts and styles
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
                BOCS_VERSION,
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
     * Handle the Stripe setup completion and save the payment token
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

            if ($setup_intent->status !== 'succeeded') {
                throw new Exception('Setup was not completed successfully');
            }

            // Get current user
            $user_id = get_current_user_id();
            if (!$user_id) {
                throw new Exception('User not logged in');
            }

            // Get the payment method details
            $payment_method = $stripe->paymentMethods->retrieve($setup_intent->payment_method);

            // Create WC payment token
            $token = new WC_Payment_Token_CC();
            $token->set_token($payment_method->id);
            $token->set_gateway_id('stripe');
            $token->set_card_type(strtolower($payment_method->card->brand));
            $token->set_last4($payment_method->card->last4);
            $token->set_expiry_month($payment_method->card->exp_month);
            $token->set_expiry_year($payment_method->card->exp_year);
            $token->set_user_id($user_id);
            
            // Save the token
            if (!$token->save()) {
                throw new Exception('Failed to save payment method');
            }

            // Redirect back to the subscriptions page with success message
            wp_redirect(add_query_arg('payment_updated', 'success', wc_get_account_endpoint_url('bocs-subscriptions')));
            exit;

        } catch (Exception $e) {
            // Redirect back with error
            wp_redirect(add_query_arg('payment_updated', 'error', wc_get_account_endpoint_url('bocs-subscriptions')));
            exit;
        }
    }

    /**
     * Display notices after payment method update
     */
    public function display_payment_update_notices() {
        if (isset($_GET['payment_updated'])) {
            if ($_GET['payment_updated'] === 'success') {
                wc_add_notice(__('Payment method successfully updated.', 'bocs-wordpress'), 'success');
            } else if ($_GET['payment_updated'] === 'error') {
                wc_add_notice(__('Failed to update payment method. Please try again.', 'bocs-wordpress'), 'error');
            }
        }
    }

    public function update_subscription_payment() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bocs_ajax_nonce')) {
            wp_send_json_error(['message' => 'Invalid security token']);
            return;
        }

        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';
        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';

        if (empty($payment_method) || empty($subscription_id)) {
            wp_send_json_error(['message' => 'Missing required parameters']);
            return;
        }

        try {
            // Verify the payment method belongs to the user
            $token = WC_Payment_Tokens::get($payment_method);
            if (!$token || $token->get_user_id() !== get_current_user_id()) {
                throw new Exception('Invalid payment method');
            }

            // Here you would update the subscription's payment method in your system
            // This depends on how you store subscription payment methods

            wp_send_json_success(['message' => 'Payment method updated successfully']);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}