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
                __('Sorry, you are not allowed to trigger payments.', 'bocs-wordpress'),
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

                // For other payment gateways
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

    // Helper methods would go here...
} 