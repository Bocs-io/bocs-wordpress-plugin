<?php

/**
 * Class Bocs_Stripe_Hooks
 * 
 * Handles Stripe-related hooks and functionality for the BOCS subscription system.
 * This class manages Stripe customer creation, payment method attachment, and order processing
 * for both logged-in users and guest checkouts.
 */
class Bocs_Stripe_Hooks {

    /**
     * Validates the presence of required BOCS cookies
     * 
     * @return boolean Returns true if all required cookies are present, false otherwise
     */
    private function check_required_cookies() {
        error_log('BOCS Debug: Checking required cookies');
        
        if (!isset($_COOKIE['__bocs_id'])) {
            error_log("BOCS Debug: Missing required cookie: {'__bocs_id'}");
            return false;
        }

        error_log('BOCS Debug: All required cookies present');
        return true;
    }

    /**
     * Forces Stripe to save the payment source for future use
     * 
     * @param array $metadata The existing metadata array
     * @param WC_Order $order The WooCommerce order object
     * @param string $source The Stripe source ID
     * @return array Modified metadata array
     */
    public function force_stripe_save_source($metadata, $order, $source) {
        error_log('BOCS Debug: Attempting to force save Stripe source');
        error_log('BOCS Debug: Order ID: ' . $order->get_id());
        error_log('BOCS Debug: Source: ' . print_r($source, true));
        
        if (!$this->check_required_cookies()) {
            error_log('BOCS Debug: Required cookies missing, returning original metadata');
            return $metadata;
        }
        
        error_log('BOCS Debug: Original metadata: ' . print_r($metadata, true));
        if (!isset($metadata['setup_future_usage'])) {
            $metadata['setup_future_usage'] = 'off_session';
            error_log('BOCS Debug: Added setup_future_usage to metadata');
        }
        error_log('BOCS Debug: Final metadata: ' . print_r($metadata, true));
        return $metadata;
    }

    /**
     * Modifies payment intent parameters to enable future usage
     * 
     * @param array $params The payment intent parameters
     * @param WC_Order $order The WooCommerce order object
     * @return array Modified parameters array
     */
    public function modify_payment_intent_params($params, $order) {
        if (!$this->check_required_cookies()) {
            return $params;
        }
        // Set setup_future_usage parameter for future off-session payments
        $params['setup_future_usage'] = 'off_session';
        return $params;
    }

    /**
     * Ensures a Stripe customer exists for the current transaction
     * Creates a new customer if one doesn't exist for the user/email
     * 
     * @param array $data Checkout form data
     */
    public function ensure_stripe_customer($data) {
        error_log('BOCS Debug: Starting ensure_stripe_customer');
        
        if (!$this->check_required_cookies()) {
            error_log('BOCS Debug: Required cookies missing, aborting customer creation');
            return;
        }

        $gateway = new WC_Gateway_Stripe();
        $user_id = get_current_user_id();
        $customer_email = $data['billing_email'];
        
        error_log("BOCS Debug: Processing customer - User ID: {$user_id}, Email: {$customer_email}");
        
        try {
            if ($user_id) {
                $customer_id = get_user_meta($user_id, '_stripe_customer_id', true);
                error_log("BOCS Debug: Existing customer ID for user {$user_id}: " . ($customer_id ?: 'none'));
                
                if (!$customer_id) {
                    error_log('BOCS Debug: Creating new Stripe customer for logged-in user');
                    $customer = $gateway->create_stripe_customer([
                        'email' => $customer_email,
                        'description' => 'Customer ' . $user_id,
                    ]);
                    update_user_meta($user_id, '_stripe_customer_id', $customer->id);
                    error_log("BOCS Debug: Created new customer with ID: {$customer->id}");
                }
            } else {
                error_log('BOCS Debug: Creating new Stripe customer for guest checkout');
                $customer = $gateway->create_stripe_customer([
                    'email' => $customer_email,
                    'description' => 'Guest customer'
                ]);
                error_log("BOCS Debug: Created guest customer with ID: {$customer->id}");
            }
        } catch (Exception $e) {
            error_log('BOCS Debug: Stripe customer creation failed: ' . $e->getMessage());
            error_log('BOCS Debug: Error trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Attaches a payment method to a Stripe customer after successful payment
     * 
     * @param int $order_id WooCommerce order ID
     */
    public function attach_payment_method_to_customer($order_id) {
        error_log("BOCS Debug: Starting payment method attachment for order {$order_id}");
        
        if (!$this->check_required_cookies()) {
            error_log('BOCS Debug: Required cookies missing, aborting payment method attachment');
            return;
        }

        $order = wc_get_order($order_id);
        if ($order->get_payment_method() !== 'stripe') {
            error_log('BOCS Debug: Not a Stripe payment method, aborting');
            return;
        }
    
        $gateway = new WC_Gateway_Stripe();
        $source_id = $order->get_meta('_stripe_source_id');
        $customer_id = $order->get_meta('_stripe_customer_id');
    
        error_log("BOCS Debug: Source ID: {$source_id}, Customer ID: {$customer_id}");
    
        if ($source_id && $customer_id) {
            try {
                error_log('BOCS Debug: Attempting to attach payment method');
                $gateway->attach_payment_method_to_customer(
                    $source_id,
                    $customer_id
                );
                error_log('BOCS Debug: Successfully attached payment method');
            } catch (Exception $e) {
                error_log('BOCS Debug: Failed to attach payment method: ' . $e->getMessage());
                error_log('BOCS Debug: Error trace: ' . $e->getTraceAsString());
            }
        } else {
            error_log('BOCS Debug: Missing source_id or customer_id, cannot attach payment method');
        }
    }

    /**
     * Saves the Stripe customer ID to the WooCommerce order
     * 
     * @param int $order_id WooCommerce order ID
     * @param array $data Checkout form data
     */
    public function save_stripe_customer_to_order($order_id, $data) {
        if (!$this->check_required_cookies()) {
            return;
        }
        $order = wc_get_order($order_id);
        if ($order->get_payment_method() !== 'stripe') {
            return;
        }
    
        $user_id = $order->get_user_id();
        if ($user_id) {
            $customer_id = get_user_meta($user_id, '_stripe_customer_id', true);
            if ($customer_id) {
                // Store Stripe customer ID in order meta for future reference
                $order->update_meta_data('_stripe_customer_id', $customer_id);
                $order->save();
            }
        }
    }

    /**
     * Forces saving of Stripe payment source for BOCS subscriptions
     * Overrides the WooCommerce Stripe 'wc_stripe_force_save_source' filter
     * 
     * @param boolean $force_save_source The original force save source value
     * @return boolean True if BOCS cookies are present, otherwise returns original value
     */
    public function should_save_source($force_save_source) {
        if (!$this->check_required_cookies()) {
            return $force_save_source; // Don't override default behavior if not a BOCS checkout
        }
        return true; // Force save source for BOCS subscriptions
    }
} 