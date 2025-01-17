<?php

/**
 * Class Bocs_Payment_API
 * 
 * Handles payment-related REST API endpoints for the Bocs WordPress plugin.
 * This class provides functionality to trigger payments for WooCommerce orders via REST API.
 * 
 * Key features:
 * - Registers a REST endpoint for triggering payments
 * - Handles authentication and permissions
 * - Supports Stripe payment processing
 * - Provides detailed logging via order notes
 * 
 * @package Bocs
 * @since 0.0.98
 */
class Bocs_Payment_API {
    /**
     * Register the payment-related REST routes
     * 
     * Registers a POST endpoint at /wc/v3/orders/{order_id}/trigger-payment
     * that allows triggering payments for specific orders.
     * 
     * Endpoint: POST /wc/v3/orders/{order_id}/trigger-payment
     * Authentication: Requires WC REST API credentials or shop_manager capabilities
     * 
     * @since 1.0.0
     * @return void
     */
    public function register_routes() {
        register_rest_route('wc/v3', '/orders/(?P<order_id>[\d]+)/trigger-payment', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'           => array($this, 'trigger_payment'),
            'permission_callback' => array($this, 'check_permission'),
            'args'               => array(
                'order_id' => array(
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));
    }

    /**
     * Check if the request has permission to trigger payments
     * 
     * Validates the request authentication using either:
     * 1. WooCommerce REST API authentication headers
     * 2. WordPress user capabilities (edit_shop_orders)
     *
     * @param WP_REST_Request $request The incoming request object
     * @return bool|WP_Error True if authorized, WP_Error if not
     */
    public function check_permission($request) {
        // Verify if this is an authenticated WC REST API request
        if (!empty($request->get_headers()['authorization'])) {
            return true; // WC REST API handles authentication already
        }

        // Fallback to WordPress user capabilities check
        if (!current_user_can('edit_shop_orders')) {
            return new WP_Error(
                'rest_forbidden',
                __('Sorry, you are not allowed to trigger payments.', 'bocs'),
                array('status' => rest_authorization_required_code())
            );
        }

        return true;
    }

    /**
     * Handle the payment trigger request
     * 
     * Processes a payment trigger request for a specific order. This method handles
     * the complete payment flow including validation, gateway selection, and processing.
     * 
     * Process flow:
     * 1. Validates order exists and can be paid
     * 2. Initializes WC session if needed
     * 3. Retrieves appropriate payment gateway
     * 4. For Stripe payments:
     *    - Validates customer and payment method
     *    - Creates and confirms payment intent
     *    - Handles authentication requirements
     * 5. For other gateways:
     *    - Processes payment through standard gateway
     * 6. Handles success/failure scenarios
     * 
     * All steps are logged via order notes for debugging and tracking.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The incoming REST request object
     * @return WP_REST_Response|WP_Error Success response or error on failure
     */
    public function trigger_payment($request) {
        $order_id = $request->get_param('order_id');
        
        // Retrieve the order object
        $order = wc_get_order($order_id);

        // Validate order exists
        if (!$order) {
            return new WP_Error(
                'not_found',
                __('Order not found', 'bocs-wordpress'),
                array('status' => 404)
            );
        }

        // Verify order can be paid
        if (!$order->needs_payment()) {
            $order->add_order_note(__('Payment trigger attempted but order does not need payment.', 'bocs-wordpress'), false);
            return new WP_Error(
                'invalid_order_status',
                __('This order cannot be paid for.', 'bocs-wordpress'),
                array('status' => 400)
            );
        }
        /*
        // Get User-Agent from the request
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Determine device type
        $device_type = 'Desktop'; // Default to Desktop

        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $user_agent)) {
            $device_type = 'Tablet';
        } else if (preg_match('/(Mobile|Android|iPhone|iPod|IEMobile|BlackBerry|webOS)/i', $user_agent)) {
            $device_type = 'Mobile';
        }

        $order->update_meta_data('_wc_order_attribution_device_type', $device_type);

        // Set attribution meta data for API-created orders
        $order->update_meta_data('_wc_order_attribution_source_type', 'referral');
        $order->update_meta_data('_wc_order_attribution_utm_source', 'Bocs App');
        $order->update_meta_data('_wc_order_attribution_user_agent', $_SERVER['HTTP_USER_AGENT']);
        $order->save();
        */

        try {
            // Initialize WC session if needed for API requests
            if (!WC()->session) {
                WC()->initialize_session();
            }

            // Get available payment gateways and the order's payment method
            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
            $payment_method = $order->get_payment_method();

            // Check if the payment gateway is available
            if (isset($available_gateways[$payment_method])) {
                $gateway = $available_gateways[$payment_method];
                
                // Log the initiation of payment processing
                $order->add_order_note(
                    sprintf(__('Processing payment via %s', 'bocs-wordpress'), 
                    $gateway->get_title()), 
                    false
                );

                // For Stripe payments, ensure we have the payment method
                if ($payment_method === 'stripe') {
                    // Get Stripe customer ID and payment details from order meta
                    $stripe_customer_id = $order->get_meta('_stripe_customer_id');
                    
                    // If Stripe customer ID is empty, try to get it from user meta
                    if (empty($stripe_customer_id)) {
                        $user_id = $order->get_user_id();
                        if ($user_id) {
                            $stripe_customer_id = get_user_meta($user_id, '_stripe_customer_id', true);
                            
                            // If still empty, try with table prefix
                            if (empty($stripe_customer_id)) {
                                global $wpdb;
                                $stripe_customer_id = get_user_meta($user_id, $wpdb->prefix . '_stripe_customer_id', true);
                            }
                        }
                    }

                    $payment_method_id = $order->get_meta('_stripe_source_id');
                    
                    // If payment method ID is empty, try to get default payment token
                    if (empty($payment_method_id)) {
                        $user_id = $order->get_user_id();
                        if ($user_id) {
                            $tokens = WC_Payment_Tokens::get_customer_tokens($user_id, 'stripe');
                            foreach ($tokens as $token) {
                                if ($token->get_is_default()) {
                                    $payment_method_id = $token->get_token();
                                    break;
                                }
                            }
                        }
                    }

                    $payment_intent_id = $order->get_meta('_stripe_intent_id');
                    $upe_payment_type = $order->get_meta('_stripe_upe_payment_type');
                    
                    try {
                        // Get Stripe gateway to check test mode
                        $stripe_gateway = WC()->payment_gateways()->payment_gateways()['stripe'];
                        $is_test_mode = $stripe_gateway->testmode === 'yes';
                        
                        // Ensure we're using the correct API keys based on mode
                        /*if ($is_test_mode) {
                            WC_Stripe_API::set_secret_key($stripe_gateway->get_test_secret_key());
                        } else {
                            WC_Stripe_API::set_secret_key($stripe_gateway->get_secret_key());
                        }*/
                        
                        // If we have a payment method, ensure it's attached to the customer
                        if (!empty($payment_method_id)) {
                            try {
                                WC_Stripe_API::request(
                                    array(
                                        'customer' => $stripe_customer_id
                                    ),
                                    "payment_methods/$payment_method_id/attach",
                                    'POST'
                                );
                                
                                // Set this payment method as the default for the customer
                                WC_Stripe_API::request(
                                    array(
                                        'invoice_settings' => array(
                                            'default_payment_method' => $payment_method_id
                                        )
                                    ),
                                    "customers/$stripe_customer_id",
                                    'POST'
                                );
                            } catch (Exception $e) {
                                $order->add_order_note(
                                    sprintf(__('Warning: Unable to attach payment method to customer: %s', 'bocs-wordpress'), 
                                    $e->getMessage()),
                                    false
                                );
                            }
                        }

                        // Create a new payment intent
                        $amount = $order->get_total();
                        $currency = $order->get_currency();
                        
                        $intent_data = array(
                            'amount' => WC_Stripe_Helper::get_stripe_amount($amount, $currency),
                            'currency' => strtolower($currency),
                            'customer' => $stripe_customer_id,
                            'payment_method' => $payment_method_id,
                            'payment_method_types' => array($upe_payment_type ?: 'card'),
                            'confirmation_method' => 'automatic',
                            'confirm' => 'true',
                            'off_session' => 'true',
                            'metadata' => array(
                                'order_id' => (string)$order->get_id(),
                                'order_number' => (string)$order->get_order_number(),
                                'environment' => $is_test_mode ? 'test' : 'live'
                            ),
                            'description' => sprintf(
                                __('Order %s', 'bocs-wordpress'),
                                $order->get_order_number()
                            ),
                            'return_url' => $order->get_checkout_payment_url(true)
                        );

                        try {
                            $payment_intent = WC_Stripe_API::request($intent_data, 'payment_intents');
                            
                            if (!empty($payment_intent->error)) {
                                throw new Exception($payment_intent->error->message);
                            }
                            
                            if (!empty($payment_intent->id)) {
                                $payment_intent_id = $payment_intent->id;
                                $order->update_meta_data('_stripe_intent_id', $payment_intent_id);
                                $order->save();
                                
                                $order->add_order_note(
                                    sprintf(__('Stripe payment initiated (Payment Intent ID: %s)', 'bocs-wordpress'), 
                                    $payment_intent_id),
                                    false
                                );

                                if ($payment_intent->status === 'succeeded') {
                                    $order->payment_complete($payment_intent_id);
                                    $order->add_order_note(
                                        sprintf(__('Payment completed via Stripe (Payment Intent ID: %s)', 'bocs-wordpress'),
                                        $payment_intent_id),
                                        false
                                    );
                                    return new WP_REST_Response(array(
                                        'success' => true,
                                        'message' => __('Payment processed successfully.', 'bocs-wordpress')
                                    ), 200);
                                } elseif ($payment_intent->status === 'requires_action') {
                                    throw new Exception(__('This payment requires additional authentication.', 'bocs-wordpress'));
                                } else {
                                    throw new Exception(
                                        sprintf(__('Payment failed. Status: %s', 'bocs-wordpress'), 
                                        $payment_intent->status)
                                    );
                                }
                            }
                        } catch (Exception $e) {
                            $order->add_order_note(
                                sprintf(__('Stripe payment failed: %s', 'bocs-wordpress'), 
                                $e->getMessage()),
                                false
                            );
                            throw $e;
                        }

                        throw new Exception(__('Failed to create Stripe payment.', 'bocs-wordpress'));

                    } catch (Exception $e) {
                        $order->add_order_note(
                            sprintf(__('Stripe payment error: %s', 'bocs-wordpress'), 
                            $e->getMessage()),
                            false
                        );

                        return new WP_Error(
                            'payment_error',
                            $e->getMessage(),
                            array('status' => 400)
                        );
                    }
                }

                // Process the payment through the gateway
                $result = $gateway->process_payment($order_id);

                // Handle successful payment processing
                if ($result && isset($result['result']) && $result['result'] === 'success') {
                    $order->add_order_note(
                        sprintf(__('Payment completed via %s.', 'bocs-wordpress'),
                        $gateway->get_title()),
                        false
                    );

                    return new WP_REST_Response(array(
                        'success' => true,
                        'message' => __('Payment processed successfully.', 'bocs-wordpress'),
                        'redirect' => $result['redirect'] ?? null
                    ), 200);
                }

                // Log failure if process_payment succeeded but returned error
                $order->add_order_note(
                    sprintf(__('Payment failed via %s: Gateway returned unsuccessful result.', 'bocs-wordpress'),
                    $gateway->get_title()),
                    false
                );
            } else {
                // Log if payment gateway is not available
                $order->add_order_note(
                    sprintf(__('Payment failed: %s gateway is not available.', 'bocs-wordpress'), 
                    $payment_method),
                    false
                );
            }

            throw new Exception(__('Payment processing failed.', 'bocs'));

        } catch (Exception $e) {
            // Log any errors that occurred during processing
            $order->add_order_note(
                sprintf(__('Payment processing error: %s', 'bocs-wordpress'), $e->getMessage()),
                false
            );

            return new WP_Error(
                'payment_error',
                $e->getMessage(),
                array('status' => 400)
            );
        }
    }
} 