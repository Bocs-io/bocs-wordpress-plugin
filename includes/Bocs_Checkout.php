<?php

/**
 * Class Bocs_Checkout
 * Handles WooCommerce checkout customizations for Bocs integration
 */
class Bocs_Checkout {

    /**
     * Customizes the checkout account creation fields based on Bocs ID presence
     * 
     * @param array $fields WooCommerce checkout fields
     * @return array Modified checkout fields
     */
    public function customize_checkout_account_creation($fields) {
        if ($this->get_bocs_id()) {
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
        return !empty($this->get_bocs_id());
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

        return !empty($this->get_bocs_id());
    }
}