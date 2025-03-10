<?php

/**
 * Bocs Stripe Hooks Handler Class
 *
 * Handles Stripe-related hooks and functionality for the BOCS subscription system.
 * This class manages Stripe customer creation, payment method attachment, and order processing
 * for both logged-in users and guest checkouts.
 *
 * @package    Bocs
 * @subpackage Bocs/includes
 * @since      0.0.1
 */
class Bocs_Stripe_Hooks {

    /**
     * Modify Stripe source data for payment processing
     *
     * @since 0.0.1
     * @param array    $source_data Source data to be modified
     * @param WC_Order $order Order object
     * @return array Modified source data
     */
    public function modify_source_data($source_data, $order) {
        // Check if either __bocs_id cookie exists or order meta exists
        $has_bocs_cookie = isset($_COOKIE['__bocs_id']) && !empty($_COOKIE['__bocs_id']);
        $has_bocs_meta = !empty($order->get_meta('__bocs_id'));
        
        if (!$has_bocs_cookie && !$has_bocs_meta) {
            $this->log_message(__('No BOCS cookie found', 'bocs-wordpress'));
            return $source_data;
        }

        $this->log_message(
            sprintf(
                /* translators: %s: Order status */
                __('Order status during source data: %s', 'bocs-wordpress'),
                $order->get_status()
            )
        );

        $customer_id = $order->get_meta('_stripe_customer_id');
        
        if ($customer_id) {
            $source_data['customer'] = $customer_id;
            $source_data['usage'] = 'reusable';
            $source_data['setup_future_usage'] = 'off_session';
        }
        
        return $source_data;
    }
    
    /**
     * Handle adding payment method to customer
     *
     * @since 0.0.1
     * @param string $source_id Source ID
     * @param object $source Source object
     */
    public function handle_add_payment_method($source_id, $source) {
        // Only check for cookie since we don't have an order object here
        $has_bocs_cookie = isset($_COOKIE['__bocs_id']) && !empty($_COOKIE['__bocs_id']);
        
        if (!$has_bocs_cookie) {
            $this->log_message(__('Payment method not added: No BOCS cookie found', 'bocs-wordpress'));
            return;
        }

        try {
            $this->log_message(
                sprintf(
                    /* translators: %s: Source ID */
                    __('Attempting to add payment method. Source ID: %s', 'bocs-wordpress'),
                    $source_id
                )
            );

            $customer_id = get_current_user_id();
            $stripe_customer_id = get_user_meta($customer_id, '_stripe_customer_id', true);
            
            if ($stripe_customer_id && $source_id) {
                $this->log_message(
                    sprintf(
                        /* translators: %s: Stripe customer ID */
                        __('Attempting to attach payment method to Stripe customer: %s', 'bocs-wordpress'),
                        $stripe_customer_id
                    )
                );

                // Attach payment method to Stripe customer
                $response = WC_Stripe_API::request([
                    'payment_method' => $source_id,
                    'customer' => $stripe_customer_id,
                ], 'payment_methods/' . $source_id . '/attach');

                if (!is_wp_error($response)) {
                    $this->log_message(__('Successfully attached payment method to Stripe. Creating WC Payment Token...', 'bocs-wordpress'));
                    
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
                        $this->log_message(
                            sprintf(
                                /* translators: %d: Token ID */
                                __('Successfully saved WC Payment Token. ID: %d', 'bocs-wordpress'),
                                $token->get_id()
                            )
                        );

                        // Store Stripe customer ID in token metadata
                        update_metadata('payment_token', $token->get_id(), '_stripe_customer_id', $stripe_customer_id);
                        
                        // Set as default if this is the only token
                        $tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), 'stripe');
                        if (count($tokens) === 1) {
                            $this->log_message(__('Setting token as default payment method', 'bocs-wordpress'));
                            $token->set_default(true);
                            $token->save();
                        }
                    } else {
                        $this->log_message(__('Failed to save WC Payment Token', 'bocs-wordpress'));
                    }
                } else {
                    $this->log_message(
                        sprintf(
                            /* translators: %s: Error message */
                            __('Failed to attach payment method to Stripe: %s', 'bocs-wordpress'),
                            $response->get_error_message()
                        )
                    );
                }
            } else {
                $this->log_message(
                    sprintf(
                        /* translators: 1: Stripe customer ID status, 2: Source ID status */
                        __('Missing required data. Stripe Customer ID: %1$s, Source ID: %2$s', 'bocs-wordpress'),
                        $stripe_customer_id ? __('Yes', 'bocs-wordpress') : __('No', 'bocs-wordpress'),
                        $source_id ? __('Yes', 'bocs-wordpress') : __('No', 'bocs-wordpress')
                    )
                );
            }
        } catch (Exception $e) {
            $this->log_message(
                sprintf(
                    /* translators: %s: Error message */
                    __('Payment method attachment failed: %s', 'bocs-wordpress'),
                    $e->getMessage()
                )
            );
            $this->log_message(
                sprintf(
                    /* translators: %s: Stack trace */
                    __('Exception trace: %s', 'bocs-wordpress'),
                    $e->getTraceAsString()
                )
            );
        }
    }

    /**
     * Log a message to the error log
     *
     * @since 0.0.1
     * @param string $message Message to log
     */
    private function log_message($message) {
        error_log($message);
    }
} 