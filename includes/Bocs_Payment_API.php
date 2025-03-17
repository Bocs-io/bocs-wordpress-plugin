<?php

/**
 * Bocs Payment API
 *
 * @package Bocs
 * @subpackage Bocs/includes
 * @since 0.0.98
 */

/**
 * Handles payment-related REST API endpoints for WooCommerce orders.
 *
 * This class provides functionality to trigger payments for WooCommerce orders via REST API.
 * It supports Stripe payment processing and includes detailed logging via order notes.
 *
 * @since 0.0.98
 */
class Bocs_Payment_API {
    /**
     * Register the payment-related REST routes.
     *
     * @since 0.0.98
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
     * Check if the request has permission to trigger payments.
     *
     * @since 0.0.98
     * @param WP_REST_Request $request The incoming request object.
     * @return bool|WP_Error True if authorized, WP_Error if not.
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
                __('Sorry, you are not allowed to trigger payments.', 'bocs-wordpress'),
                array('status' => rest_authorization_required_code())
            );
        }

        return true;
    }

    /**
     * Handle the payment trigger request.
     *
     * @since 0.0.98
     * @param WP_REST_Request $request The incoming REST request object.
     * @return WP_REST_Response|WP_Error Success response or error on failure.
     */
    public function trigger_payment($request) {
        $order_id = $request->get_param('order_id');
        
        // Retrieve the order object
        $order = wc_get_order($order_id);

        // Validate order exists
        if (!$order) {
            error_log(sprintf(
                /* translators: %d: Order ID */
                __('Critical: Order #%d not found for payment trigger', 'bocs-wordpress'),
                $order_id
            ));
            return new WP_Error(
                'not_found',
                __('Order not found', 'bocs-wordpress'),
                array('status' => 404)
            );
        }

        // Verify order can be paid
        if (!$order->needs_payment()) {
            error_log(sprintf(
                /* translators: 1: Order ID, 2: Order status */
                __('Critical: Order #%1$d cannot be paid. Status: %2$s', 'bocs-wordpress'),
                $order_id,
                $order->get_status()
            ));
            $order->add_order_note(
                __('Payment trigger attempted but order does not need payment.', 'bocs-wordpress'),
                false
            );
            return new WP_Error(
                'invalid_order_status',
                __('This order cannot be paid for.', 'bocs-wordpress'),
                array('status' => 400)
            );
        }

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
                
                $order->add_order_note(
                    sprintf(
                        /* translators: %s: Gateway title */
                        __('Processing payment via %s', 'bocs-wordpress'),
                        $gateway->get_title()
                    ),
                    false
                );

                // For Stripe payments, ensure we have the payment method
                if ($payment_method === 'stripe') {
                    // Get Stripe customer ID and payment details
                    $stripe_customer_id = $this->get_stripe_customer_id($order);
                    $payment_method_id = $this->get_stripe_payment_method($order);

                    // Verify we have both customer ID and payment method
                    if (empty($stripe_customer_id) || empty($payment_method_id)) {
                        throw new Exception(
                            __('Missing required Stripe customer or payment method information.', 'bocs-wordpress')
                        );
                    }

                    // Process Stripe payment
                    $payment_result = $this->process_stripe_payment(
                        $order,
                        $stripe_customer_id,
                        $payment_method_id
                    );

                    if ($payment_result['success']) {
                        return new WP_REST_Response(
                            array(
                                'success' => true,
                                'message' => __('Payment processed successfully.', 'bocs-wordpress')
                            ),
                            200
                        );
                    }

                    throw new Exception($payment_result['message']);
                }

                // For other gateways
                $result = $gateway->process_payment($order_id);
                
                if ($result['result'] === 'success') {
                    return new WP_REST_Response(
                        array(
                            'success' => true,
                            'message' => __('Payment processed successfully.', 'bocs-wordpress')
                        ),
                        200
                    );
                }

                throw new Exception(__('Payment processing failed.', 'bocs-wordpress'));
            }

            throw new Exception(__('Payment gateway not available.', 'bocs-wordpress'));

        } catch (Exception $e) {
            error_log(sprintf(
                /* translators: 1: Order ID, 2: Error message */
                __('Critical: Payment processing failed for order #%1$d: %2$s', 'bocs-wordpress'),
                $order_id,
                $e->getMessage()
            ));
            
            $order->add_order_note(
                sprintf(
                    /* translators: %s: Error message */
                    __('Payment processing failed: %s', 'bocs-wordpress'),
                    $e->getMessage()
                ),
                false
            );

            return new WP_Error(
                'payment_failed',
                $e->getMessage(),
                array('status' => 400)
            );
        }
    }

    /**
     * Get the Stripe customer ID for an order.
     *
     * @since 0.0.98
     * @param WC_Order $order The WooCommerce order object.
     * @return string|null The Stripe customer ID or null if not found.
     */
    private function get_stripe_customer_id($order) {
        $stripe_customer_id = $order->get_meta('_stripe_customer_id');
        
        if (empty($stripe_customer_id)) {
            // Get or create Stripe customer
            $user_id = $order->get_user_id();
            if ($user_id) {
                $stripe_customer_id = get_user_meta($user_id, '_stripe_customer_id', true);
                
                // If we found a customer ID, update the order meta
                if (!empty($stripe_customer_id)) {
                    $order->update_meta_data('_stripe_customer_id', $stripe_customer_id);
                    $order->save();
                }
            }
        }

        // Log the customer ID retrieval attempt
        error_log(sprintf(
            /* translators: 1: Order ID, 2: Customer ID or 'not found' */
            __('Stripe customer ID for order #%1$d: %2$s', 'bocs-wordpress'),
            $order->get_id(),
            $stripe_customer_id ?: 'not found'
        ));

        return $stripe_customer_id;
    }

    /**
     * Get the Stripe payment method ID for an order.
     *
     * @since 0.0.98
     * @param WC_Order $order The WooCommerce order object.
     * @return string|null The Stripe payment method ID or null if not found.
     */
    private function get_stripe_payment_method($order) {
        $payment_method_id = $order->get_meta('_stripe_source_id');
        
        // If no payment method found, try to get it from the order's payment method
        if (empty($payment_method_id)) {
            $payment_method = $order->get_payment_method();
            if ($payment_method === 'stripe') {
                // Try to get the payment method from the order's payment method data
                $payment_method_data = $order->get_meta('_stripe_payment_method_data');
                if (!empty($payment_method_data) && isset($payment_method_data['id'])) {
                    $payment_method_id = $payment_method_data['id'];
                    $order->update_meta_data('_stripe_source_id', $payment_method_id);
                    $order->save();
                }
            }
        }

        // Log the payment method retrieval attempt
        error_log(sprintf(
            /* translators: 1: Order ID, 2: Payment method ID or 'not found' */
            __('Stripe payment method for order #%1$d: %2$s', 'bocs-wordpress'),
            $order->get_id(),
            $payment_method_id ?: 'not found'
        ));

        return $payment_method_id;
    }

    /**
     * Process a Stripe payment for an order.
     *
     * @since 0.0.98
     * @param WC_Order $order The WooCommerce order object.
     * @param string $stripe_customer_id The Stripe customer ID.
     * @param string $payment_method_id The Stripe payment method ID.
     * @return array Array containing success status and message.
     */
    private function process_stripe_payment($order, $stripe_customer_id, $payment_method_id) {
        try {
            // Validate required data
            if (empty($stripe_customer_id)) {
                throw new Exception(__('Stripe customer ID is missing. Please ensure the order has a valid Stripe customer.', 'bocs-wordpress'));
            }

            if (empty($payment_method_id)) {
                throw new Exception(__('Stripe payment method is missing. Please ensure the order has a valid payment method.', 'bocs-wordpress'));
            }

            // Get Stripe gateway instance
            $gateway = WC()->payment_gateways->payment_gateways()['stripe'];
            
            // Get Stripe secret key
            $secret_key = $gateway->get_option('testmode') === 'yes' ? $gateway->get_option('test_secret_key') : $gateway->get_option('secret_key');
            if (empty($secret_key)) {
                throw new Exception(__('Stripe secret key is not configured.', 'bocs-wordpress'));
            }
            
            // Log payment attempt
            error_log(sprintf(
                /* translators: 1: Order ID, 2: Customer ID, 3: Payment method ID */
                __('Attempting Stripe payment for order #%1$d with customer %2$s and payment method %3$s', 'bocs-wordpress'),
                $order->get_id(),
                $stripe_customer_id,
                $payment_method_id
            ));

            // Check for existing payment intent
            $existing_intent_id = $order->get_meta('_stripe_intent_id');
            if (!empty($existing_intent_id)) {
                try {
                    // Initialize Stripe client
                    $stripe = new \Stripe\StripeClient($secret_key);

                    // Get the current payment intent
                    $payment_intent = $stripe->paymentIntents->retrieve($existing_intent_id);

                    // If the payment intent is not succeeded, try to update and confirm it
                    if ($payment_intent->status !== 'succeeded') {
                        // First, update the payment intent with the latest data
                        $payment_intent = $stripe->paymentIntents->update(
                            $existing_intent_id,
                            array(
                                'payment_method' => $payment_method_id,
                                'customer' => $stripe_customer_id,
                                'amount' => $order->get_total() * 100,
                                'currency' => strtolower($order->get_currency())
                            )
                        );

                        // Then confirm the payment intent
                        $payment_intent = $stripe->paymentIntents->confirm(
                            $existing_intent_id,
                            array(
                                'payment_method' => $payment_method_id,
                                'customer' => $stripe_customer_id
                            )
                        );
                    }

                    // Handle success response
                    if ($payment_intent->status === 'succeeded') {
                        $order->payment_complete($payment_intent->id);
                        return array(
                            'success' => true,
                            'message' => __('Payment processed successfully.', 'bocs-wordpress')
                        );
                    }

                    // If we get here, the payment intent is not succeeded
                    throw new Exception(sprintf(
                        /* translators: 1: Order ID, 2: Payment intent status */
                        __('Payment intent for order #%1$d is in state: %2$s', 'bocs-wordpress'),
                        $order->get_id(),
                        $payment_intent->status
                    ));

                } catch (Exception $e) {
                    error_log(sprintf(
                        /* translators: 1: Order ID, 2: Error message */
                        __('Failed to process existing payment intent for order #%1$d: %2$s', 'bocs-wordpress'),
                        $order->get_id(),
                        $e->getMessage()
                    ));
                }
            }
            
            // If no existing intent or processing failed, create a new one
            try {
                // Create a new payment intent directly with Stripe
                $stripe = new \Stripe\StripeClient($secret_key);
                $payment_intent = $stripe->paymentIntents->create(array(
                    'amount' => $order->get_total() * 100,
                    'currency' => strtolower($order->get_currency()),
                    'customer' => $stripe_customer_id,
                    'payment_method' => $payment_method_id,
                    'confirm' => true,
                    'off_session' => true
                ));

                // Handle success response
                if ($payment_intent->status === 'succeeded') {
                    $order->payment_complete($payment_intent->id);
                    return array(
                        'success' => true,
                        'message' => __('Payment processed successfully.', 'bocs-wordpress')
                    );
                }

                // If we get here, the payment intent is not succeeded
                throw new Exception(sprintf(
                    /* translators: 1: Order ID, 2: Payment intent status */
                    __('New payment intent for order #%1$d is in state: %2$s', 'bocs-wordpress'),
                    $order->get_id(),
                    $payment_intent->status
                ));

            } catch (Exception $e) {
                error_log(sprintf(
                    /* translators: 1: Order ID, 2: Error message */
                    __('Failed to create new payment intent for order #%1$d: %2$s', 'bocs-wordpress'),
                    $order->get_id(),
                    $e->getMessage()
                ));
                throw $e;
            }

        } catch (Exception $e) {
            // Log the error
            error_log(sprintf(
                /* translators: 1: Order ID, 2: Error message */
                __('Stripe payment processing failed for order #%1$d: %2$s', 'bocs-wordpress'),
                $order->get_id(),
                $e->getMessage()
            ));
            
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
} 