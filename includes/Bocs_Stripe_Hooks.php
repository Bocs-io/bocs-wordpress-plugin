<?php

/**
 * Class Bocs_Stripe_Hooks
 * 
 * Handles Stripe-related hooks and functionality for the BOCS subscription system.
 * This class manages Stripe customer creation, payment method attachment, and order processing
 * for both logged-in users and guest checkouts.
 */
class Bocs_Stripe_Hooks {

    
    public function modify_source_data($source_data, $order) {
        // Check if either __bocs_id cookie exists or order meta exists
        $has_bocs_cookie = isset($_COOKIE['__bocs_id']) && !empty($_COOKIE['__bocs_id']);
        $has_bocs_meta = !empty($order->get_meta('__bocs_id'));
        
        if (!$has_bocs_cookie && !$has_bocs_meta) {
            error_log('No BOCS cookie found');
            return $source_data;
        }

        error_log('Order status during source data: ' . $order->get_status());
        $customer_id = $order->get_meta('_stripe_customer_id');
        
        if ($customer_id) {
            $source_data['customer'] = $customer_id;
            $source_data['usage'] = 'reusable';
            $source_data['setup_future_usage'] = 'off_session';
        }
        
        return $source_data;
    }
    
    public function handle_add_payment_method($source_id, $source) {
        // Only check for cookie since we don't have an order object here
        $has_bocs_cookie = isset($_COOKIE['__bocs_id']) && !empty($_COOKIE['__bocs_id']);
        
        if (!$has_bocs_cookie) {
            error_log('Payment method not added: No BOCS cookie found');
            return;
        }

        try {
            error_log('Attempting to add payment method. Source ID: ' . $source_id);
            $customer_id = get_current_user_id();
            $stripe_customer_id = get_user_meta($customer_id, '_stripe_customer_id', true);
            
            if ($stripe_customer_id && $source_id) {
                // Attach payment method to Stripe customer
                error_log('Attempting to attach payment method to Stripe customer: ' . $stripe_customer_id);
                $response = WC_Stripe_API::request([
                    'payment_method' => $source_id,
                    'customer' => $stripe_customer_id,
                ], 'payment_methods/' . $source_id . '/attach');

                if (!is_wp_error($response)) {
                    error_log('Successfully attached payment method to Stripe. Creating WC Payment Token...');
                    // Create WC Payment Token
                    $token = new WC_Payment_Token_CC();
                    $token->set_token($source_id);
                    $token->set_gateway_id('stripe');
                    $token->set_card_type($source->brand);
                    $token->set_last4($source->last4);
                    $token->set_expiry_month($source->exp_month);
                    $token->set_expiry_year($source->exp_year);
                    $token->set_user_id(get_current_user_id());
                    
                    // Save the token
                    if ($token->save()) {
                        error_log('Successfully saved WC Payment Token. ID: ' . $token->get_id());
                        // Store Stripe customer ID in token metadata
                        update_metadata('payment_token', $token->get_id(), '_stripe_customer_id', $stripe_customer_id);
                        
                        // Set as default if this is the only token
                        $tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), 'stripe');
                        if (count($tokens) === 1) {
                            error_log('Setting token as default payment method');
                            $token->set_default(true);
                            $token->save();
                        }
                    } else {
                        error_log('Failed to save WC Payment Token');
                    }
                } else {
                    error_log('Failed to attach payment method to Stripe: ' . $response->get_error_message());
                }
            } else {
                error_log('Missing required data. Stripe Customer ID: ' . ($stripe_customer_id ? 'Yes' : 'No') . ', Source ID: ' . ($source_id ? 'Yes' : 'No'));
            }
        } catch (Exception $e) {
            error_log('Payment method attachment failed: ' . $e->getMessage());
            error_log('Exception trace: ' . $e->getTraceAsString());
        }
    }
} 