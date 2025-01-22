<?php

class Bocs_Checkout {

    public function customize_checkout_account_creation($fields) {
        // Check for cookie or URL parameter
        $has_bocs_cookie = isset($_COOKIE['__bocs_id']);
        $has_bocs_param = isset($_GET['bocs']) && !empty($_GET['bocs']);
        
        if ($has_bocs_cookie || $has_bocs_param) {
            // Hide the create account checkbox
            $fields['account']['createaccount']['class'][] = 'hidden';
        }
        return $fields;
    }

    public function conditional_auto_create_account($checked) {
        $has_bocs_cookie = isset($_COOKIE['__bocs_id']);
        $has_bocs_param = isset($_GET['bocs']) && !empty($_GET['bocs']);
        
        return ($has_bocs_cookie || $has_bocs_param) ? true : $checked;
    }

}