<?php

/**
 * Class Bocs_Checkout
 * Handles WooCommerce checkout customizations for Bocs integration
 */
class Bocs_Checkout {

    /**
     * Initializes hooks
     */
    public function __construct() {
        // Enable registration if cart contains BOCS subscriptions
        add_filter('woocommerce_checkout_registration_enabled', array($this, 'maybe_enable_registration'));
        
        // Filter checkout fields for account creation
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_account_creation'));
        
        // Override default account creation state
        add_filter('woocommerce_create_account_default_checked', array($this, 'conditional_auto_create_account'));
    }

    /**
     * Customizes the checkout account creation fields based on Bocs ID presence
     * 
     * @param array $fields WooCommerce checkout fields
     * @return array Modified checkout fields
     */
    public function customize_checkout_account_creation($fields) {
        if ($this->get_bocs_id() || $this->cart_contains_bocs_subscription()) {
            $fields['account']['createaccount']['class'][] = 'hidden';
        }
        return $fields;
    }

    /**
     * Conditionally enables automatic account creation for Bocs users
     * 
     * @param bool $checked Current account creation checkbox state
     * @return bool Modified account creation state
     */
    public function conditional_auto_create_account($checked) {
        if ($checked) {
            return true;
        }
        
        return !empty($this->get_bocs_id()) || $this->cart_contains_bocs_subscription();
    }

    /**
     * Gets the Bocs ID from session or cookie
     * 
     * @return string Bocs ID or empty string if not found
     */
    private function get_bocs_id() {
        $bocs_id = '';
        
        if (isset(WC()->session)) {
            $bocs_id = WC()->session->get('bocs');
        }
        
        if (empty($bocs_id) && isset($_COOKIE['__bocs_id'])) {
            $bocs_id = sanitize_text_field($_COOKIE['__bocs_id']);
        }

        return $bocs_id;
    }

    /**
     * Conditionally force account creation based on cart contents or Bocs ID
     *
     * @return bool Whether to force account creation
     */
    public function conditional_create_account_default() {
        // Check if user is logged in
        if (is_user_logged_in()) {
            return false;
        }

        return !empty($this->get_bocs_id()) || $this->cart_contains_bocs_subscription();
    }
    
    /**
     * Enables registration for carts containing BOCS subscriptions if admin allows it
     *
     * @param bool $registration_enabled Whether registration is enabled on checkout
     * @return bool
     */
    public function maybe_enable_registration($registration_enabled) {
        // Exit early if registration is already allowed
        if ($registration_enabled) {
            return $registration_enabled;
        }

        // Exit if user is logged in or cart doesn't contain BOCS subscription
        if (is_user_logged_in() || !$this->cart_contains_bocs_subscription()) {
            return $registration_enabled;
        }

        // Check if BOCS registration setting is enabled
        if ($this->is_bocs_registration_enabled()) {
            $registration_enabled = true;
        }

        return $registration_enabled;
    }
    
    /**
     * Check if the cart contains BOCS subscription products
     *
     * @return bool
     */
    public function cart_contains_bocs_subscription() {
        if (!is_object(WC()->cart) || WC()->cart->is_empty()) {
            return false;
        }
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            
            // Check if this is a BOCS subscription product
            if (isset($cart_item['bocs_data']) || 
                (is_a($product, 'WC_Product') && $product->get_meta('_bocs_product'))) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if BOCS subscription registration is enabled in settings
     *
     * @return bool
     */
    private function is_bocs_registration_enabled() {
        return 'yes' === get_option('woocommerce_enable_signup_from_checkout_for_bocs_subscriptions', 'yes');
    }
}