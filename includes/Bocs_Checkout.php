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
        add_filter('woocommerce_checkout_registration_enabled', array($this, 'maybe_enable_registration'), 99);
        
        // Filter checkout fields for account creation
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_account_creation'));
        
        // Override default account creation state with high priority
        add_filter('woocommerce_create_account_default_checked', array($this, 'conditional_auto_create_account'), 99);

        // Force account creation during checkout process
        add_action('woocommerce_before_checkout_process', array($this, 'force_registration_during_checkout'), 5);

        // Make registration required for subscription purchases with highest priority
        add_filter('woocommerce_checkout_registration_required', array($this, 'require_registration_during_checkout'), 99);
        
        // Add custom message to checkout when login is required
        add_filter('woocommerce_checkout_login_message', array($this, 'subscription_checkout_login_message'));

        // Ensure login form appears but doesn't block checkout display
        add_filter('woocommerce_checkout_must_be_logged_in_message', array($this, 'show_login_message_with_cart'), 10, 1);
        
        // Allow checkout form to display with login form
        add_filter('woocommerce_checkout_registration_required', array($this, 'modify_registration_required_behavior'), 20);
        
        // Add registration fields to checkout when needed
        add_action('woocommerce_before_checkout_billing_form', array($this, 'add_registration_fields_to_checkout'));
        
        // Enhance the checkout form with login reminder
        add_action('woocommerce_before_checkout_form', array($this, 'enhance_checkout_login_form'), 10);
        
        // Add custom notice for subscription products requiring login
        add_action('woocommerce_before_checkout_form', array($this, 'maybe_add_subscription_login_notice'), 9);
        
        // Add styles for checkout login integration
        add_action('wp_enqueue_scripts', array($this, 'add_checkout_login_styles'));
        
        // Filter WooCommerce settings to ensure guest checkout is disabled for BOCS subscriptions
        add_filter('pre_option_woocommerce_enable_guest_checkout', array($this, 'disable_guest_checkout_for_subscriptions'), 99);

        // Hooks for checkout
        add_action('woocommerce_checkout_before_customer_details', array($this, 'add_to_checkout_before_customer_details'));
        add_filter('woocommerce_checkout_fields', array($this, 'add_customer_information_to_checkout'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_bocs_order_information'));
        add_action('wp_enqueue_scripts', array($this, 'modify_checkout_styles'));
        add_filter('woocommerce_enable_guest_checkout', array($this, 'disable_guest_checkout_for_subscriptions'));

        // Add filter to force save payment method for BOCS subscriptions
        add_filter('wc_stripe_force_save_source', array($this, 'force_save_payment_method'));
        add_filter('wc_stripe_payment_method_save_checkbox_always_on', array($this, 'force_save_payment_method'));
        add_filter('woocommerce_payment_token_save_to_order_option', array($this, 'force_save_payment_method'), 10, 2);
        add_filter('wc_stripe_display_save_payment_method_checkbox', array($this, 'always_display_save_checkbox'), 100);
        add_filter('wc_stripe_save_to_account_text', array($this, 'modify_save_payment_text'));
        
        // Remove original recurring totals and add our own
        add_action('woocommerce_review_order_before_order_total', array($this, 'remove_original_recurring_totals'), 5);
    }

    /**
     * Customizes the checkout account creation fields based on Bocs ID presence
     * 
     * @param array $fields WooCommerce checkout fields
     * @return array Modified checkout fields
     */
    public function customize_checkout_account_creation($fields) {
        if ($this->cart_contains_bocs_subscription()) {
            // Hide the createaccount checkbox but ensure account is created
            if (isset($fields['account']['createaccount'])) {
                $fields['account']['createaccount']['class'][] = 'hidden';
            }
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
        
        return $this->cart_contains_bocs_subscription();
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
     * Force registration during the checkout process for carts containing BOCS subscriptions
     */
    public function force_registration_during_checkout() {
        if ($this->cart_contains_bocs_subscription() && !is_user_logged_in()) {
            // Force account creation by setting createaccount to 1
            $_POST['createaccount'] = 1;
            
            // Also prevent guest checkout by adding filter with high priority
            add_filter('woocommerce_checkout_registration_enabled', '__return_true', 999);
            add_filter('woocommerce_checkout_registration_required', '__return_true', 20);
            
            // Add validation hook to ensure account creation fields are filled
            add_action('woocommerce_checkout_process', array($this, 'validate_required_account_fields'));
        }
    }
    
    /**
     * Validates that account fields are filled when purchasing a BOCS subscription
     * Called during checkout validation
     */
    public function validate_required_account_fields() {
        if ($this->cart_contains_bocs_subscription() && !is_user_logged_in()) {
            // Check if account username/email is set
            if (empty($_POST['account_username']) && empty($_POST['account_email'])) {
                wc_add_notice(__('You must create an account to purchase subscription products. Please enter your account details.', 'bocs-wordpress'), 'error');
            }
            
            // Check if password is set
            if (empty($_POST['account_password'])) {
                wc_add_notice(__('Please enter a password to create your account for subscription management.', 'bocs-wordpress'), 'error');
            }
        }
    }

    /**
     * Make registration required when purchasing BOCS subscription products
     *
     * @param bool $account_required Whether an account is required to checkout
     * @return bool
     */
    public function require_registration_during_checkout($account_required) {
        if ($this->cart_contains_bocs_subscription() && !is_user_logged_in()) {
            $account_required = true;
        }
        
        return $account_required;
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
        
        // Also check for Bocs ID in session/cookie
        if (!empty($this->get_bocs_id())) {
            return true;
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

    /**
     * Customizes the login message on checkout page for subscription purchases
     *
     * @param string $message The default login message
     * @return string The modified login message
     */
    public function subscription_checkout_login_message($message) {
        if ($this->cart_contains_bocs_subscription() && !is_user_logged_in()) {
            return __('Subscription products require an account. Please log in or create an account to continue.', 'bocs-wordpress');
        }
        
        return $message;
    }

    /**
     * Customizes the message shown when user needs to be logged in,
     * allowing the cart to still display
     *
     * @param string $message The default required login message
     * @return string Modified message that doesn't block checkout display
     */
    public function show_login_message_with_cart($message) {
        // Only modify if we have BOCS subscription in cart
        if ($this->cart_contains_bocs_subscription() && !is_user_logged_in()) {
            // Return an empty string so that the checkout form continues to display
            // The login form will be shown via the woocommerce_checkout_login_message filter
            return '';
        }
        
        return $message;
    }
    
    /**
     * Modifies the registration required behavior to allow checkout form to display 
     * even when login is required for subscription products
     *
     * @param bool $registration_required Whether registration is required
     * @return bool Modified registration requirement
     */
    public function modify_registration_required_behavior($registration_required) {
        // Check if this is a subscription checkout
        if ($this->cart_contains_bocs_subscription() && !is_user_logged_in()) {
            // Set to false to bypass WooCommerce's login gate, we'll handle login differently
            add_filter('woocommerce_checkout_registration_enabled', '__return_true', 999);
            
            // We'll still require registration, but we want the form to display
            // so we return false to allow the form to render
            return false;
        }
        
        return $registration_required;
    }
    
    /**
     * Adds registration fields to the checkout when users aren't logged in
     * and cart contains subscription products
     *
     * @param WC_Checkout $checkout Checkout object
     */
    public function add_registration_fields_to_checkout($checkout) {
        // Only add fields if user is not logged in and cart contains BOCS subscription
        if (!is_user_logged_in() && $this->cart_contains_bocs_subscription()) {
            // Ensure account fields are shown by setting createaccount field
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Check the createaccount checkbox and make it checked by default
                    $('#createaccount').prop('checked', true);
                    
                    // Make the checkbox disabled so user can't uncheck it
                    $('#createaccount').prop('disabled', true);
                    
                    // Add a hidden input to ensure the value is passed even when disabled
                    if ($('#createaccount').length && !$('#createaccount_hidden').length) {
                        $('<input type="hidden" name="createaccount" id="createaccount_hidden" value="1" />').insertAfter('#createaccount');
                    }
                    
                    // Show the password field
                    $('.create-account').show();
                    
                    // Add note about required account
                    if (!$('.account-required-notice').length) {
                        $('<p class="account-required-notice" style="color:#3C7B7C; font-style:italic; margin-top:5px;"><?php echo esc_js(__('Account creation is required for subscription products.', 'bocs-wordpress')); ?></p>').insertAfter('.woocommerce-account-fields .form-row-wide');
                    }
                });
            </script>
            <?php
        }
    }
    
    /**
     * Enhances the checkout login form with additional information for subscription purchases
     */
    public function enhance_checkout_login_form() {
        // Only modify if user is not logged in and cart contains a subscription
        if (!is_user_logged_in() && $this->cart_contains_bocs_subscription() && 
            'yes' === get_option('woocommerce_enable_checkout_login_reminder', 'yes')) {
            
            // Add custom login section above the checkout
            ?>
            <div class="bocs-checkout-login-wrapper">
                <div class="bocs-checkout-login-info">
                    <h3><?php _e('Login for Subscription Purchase', 'bocs-wordpress'); ?></h3>
                    <p><?php _e('You are purchasing a subscription product which requires an account. You can:', 'bocs-wordpress'); ?></p>
                    <ul>
                        <li><?php _e('Login if you already have an account', 'bocs-wordpress'); ?></li>
                        <li><?php _e('Create a new account during checkout below', 'bocs-wordpress'); ?></li>
                    </ul>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Add a notice at the top of checkout for subscription products requiring login
     */
    public function maybe_add_subscription_login_notice() {
        // Only show if cart has subscription products and user is not logged in
        if (!is_user_logged_in() && $this->cart_contains_bocs_subscription()) {
            wc_print_notice(
                sprintf(
                    '<strong>%s</strong> %s<br><br>%s %s',
                    __('Account Required:', 'bocs-wordpress'),
                    __('You are purchasing a subscription product.', 'bocs-wordpress'),
                    __('Please', 'bocs-wordpress'),
                    sprintf(
                        '%1$s%3$s%2$s %4$s',
                        '<a href="#" class="showlogin"><strong>',
                        '</strong></a>',
                        __('log in', 'bocs-wordpress'),
                        __('if you have an account, or complete the registration fields below.', 'bocs-wordpress')
                    )
                ),
                'notice'
            );
            
            // Add an additional highlighted message about account requirements
            echo '<div class="bocs-account-required-notice" style="margin-bottom: 20px; padding: 15px; background-color: #f8f8f8; border-left: 4px solid #3C7B7C; font-size: 14px;">';
            echo '<h3 style="margin-top: 0; color: #3C7B7C;">' . __('Why is an account required?', 'bocs-wordpress') . '</h3>';
            echo '<p>' . __('An account is necessary to:', 'bocs-wordpress') . '</p>';
            echo '<ul style="list-style-type: disc; margin-left: 20px;">';
            echo '<li>' . __('Manage your subscription', 'bocs-wordpress') . '</li>';
            echo '<li>' . __('Access your subscription details', 'bocs-wordpress') . '</li>';
            echo '<li>' . __('Update payment methods', 'bocs-wordpress') . '</li>';
            echo '<li>' . __('Control delivery preferences', 'bocs-wordpress') . '</li>';
            echo '</ul>';
            echo '</div>';
        }
    }
    
    /**
     * Add custom styles for the checkout login integration
     */
    public function add_checkout_login_styles() {
        // Only add styles on the checkout page
        if (!is_checkout()) {
            return;
        }
        
        $custom_css = "
            .bocs-checkout-login-wrapper {
                margin-bottom: 30px;
                padding: 20px;
                background-color: #f8f8f8;
                border-left: 4px solid #3C7B7C;
            }
            .bocs-checkout-login-info h3 {
                margin-top: 0;
                color: #3C7B7C;
            }
            .bocs-checkout-login-info ul {
                list-style-type: disc;
                margin-left: 20px;
            }
            /* Ensure the login form is visible */
            .woocommerce-form-login {
                display: block !important;
                margin-bottom: 30px;
            }
        ";
        
        wp_add_inline_style('woocommerce-inline', $custom_css);
    }

    /**
     * Disables guest checkout for BOCS subscriptions
     *
     * @param mixed $value The current value of the option
     * @return mixed Modified value of the option
     */
    public function disable_guest_checkout_for_subscriptions($value) {
        if ($this->cart_contains_bocs_subscription()) {
            // Only set to 'no' if not a REST API request or if user is logged in
            // This prevents 'woocommerce_rest_guest_checkout_disabled' error
            if (!$this->is_rest_api_request() || is_user_logged_in()) {
                $value = 'no';
            }
        }
        return $value;
    }
    
    /**
     * Check if current request is a REST API request
     * 
     * @return bool True if it's a REST API request
     */
    private function is_rest_api_request() {
        if (function_exists('WC') && is_callable(array(WC(), 'is_rest_api_request'))) {
            return WC()->is_rest_api_request();
        }
        
        // Fallback method if WC()->is_rest_api_request() is not available
        if (empty($_SERVER['REQUEST_URI'])) {
            return false;
        }
        
        $rest_prefix = trailingslashit(rest_get_url_prefix());
        return (strpos($_SERVER['REQUEST_URI'], $rest_prefix) !== false);
    }

    /**
     * Automatically checks the save payment method checkbox during checkout
     * for Bocs subscription products
     *
     * @since 1.0.0
     * @param bool $save_payment_method Whether to save the payment method
     * @return bool Modified value based on cart contents
     */
    public function force_save_payment_method($save_payment_method) {
        if ($this->cart_contains_bocs_subscription()) {
            return true;
        }
        return $save_payment_method;
    }

    /**
     * Modifies the save payment method text for BOCS subscriptions
     * 
     * @since 1.0.0
     * @param string $text The current text
     * @return string Modified text
     */
    public function modify_save_payment_text($text) {
        if ($this->cart_contains_bocs_subscription()) {
            return __('Your payment method will be securely saved for future subscription payments.', 'bocs-wordpress');
        }
        return $text;
    }

    /**
     * Ensures the save payment method checkbox is always displayed for BOCS subscriptions
     *
     * @since 1.0.0
     * @param bool $display Whether to display the checkbox
     * @return bool Always true for BOCS subscriptions
     */
    public function always_display_save_checkbox($display) {
        return $this->cart_contains_bocs_subscription() ? true : $display;
    }

    /**
     * Adds custom styles for the checkout page with BOCS products
     */
    public function modify_checkout_styles() {
        if (!is_checkout() || !$this->cart_contains_bocs_subscription()) {
            return;
        }
        
        wp_enqueue_style(
            'bocs-checkout-styles',
            plugins_url('/assets/css/bocs-checkout.css', dirname(__FILE__)),
            array(),
            BOCS_VERSION
        );
    }
    
    /**
     * Adds Bocs-specific customer information fields to the checkout form
     *
     * @param array $fields WooCommerce checkout fields
     * @return array Modified checkout fields
     */
    public function add_customer_information_to_checkout($fields) {
        // Only modify fields if cart contains a BOCS subscription
        if (!$this->cart_contains_bocs_subscription()) {
            return $fields;
        }
        
        // Get BOCS ID
        $bocs_id = $this->get_bocs_id();
        
        if (!empty($bocs_id)) {
            // Add hidden field to store BOCS ID
            $fields['billing']['bocs_id'] = array(
                'type'  => 'hidden',
                'default' => $bocs_id
            );
            
            // Collection ID - check URL then cookie
            $collection_id = '';
            if (isset($_GET['collection']) && !empty($_GET['collection'])) {
                $collection_id = sanitize_text_field($_GET['collection']);
            } elseif (isset($_COOKIE['__bocs_collection_id'])) {
                $collection_id = sanitize_text_field($_COOKIE['__bocs_collection_id']);
            }
            
            if (!empty($collection_id)) {
                $fields['billing']['bocs_collection_id'] = array(
                    'type'  => 'hidden',
                    'default' => $collection_id
                );
            }
            
            // Frequency - check URL then cookie
            $frequency_id = '';
            if (isset($_GET['frequency']) && !empty($_GET['frequency'])) {
                $frequency_id = sanitize_text_field($_GET['frequency']);
            } elseif (isset($_COOKIE['__bocs_frequency_id'])) {
                $frequency_id = sanitize_text_field($_COOKIE['__bocs_frequency_id']);
            }
            
            if (!empty($frequency_id)) {
                $fields['billing']['bocs_frequency'] = array(
                    'type'  => 'hidden',
                    'default' => $frequency_id
                );
            }
            
            // Frequency Time Unit from cookie
            if (isset($_COOKIE['__bocs_frequency_time_unit'])) {
                $fields['billing']['bocs_frequency_time_unit'] = array(
                    'type'  => 'hidden',
                    'default' => sanitize_text_field($_COOKIE['__bocs_frequency_time_unit'])
                );
            }
            
            // Frequency Interval from cookie
            if (isset($_COOKIE['__bocs_frequency_interval'])) {
                $fields['billing']['bocs_frequency_interval'] = array(
                    'type'  => 'hidden',
                    'default' => sanitize_text_field($_COOKIE['__bocs_frequency_interval'])
                );
            }
            
            // Frequency Discount from cookie
            if (isset($_COOKIE['__bocs_frequency_discount'])) {
                $fields['billing']['bocs_frequency_discount'] = array(
                    'type'  => 'hidden',
                    'default' => sanitize_text_field($_COOKIE['__bocs_frequency_discount'])
                );
            }
            
            // Discount Type from cookie
            if (isset($_COOKIE['__bocs_discount_type'])) {
                $fields['billing']['bocs_discount_type'] = array(
                    'type'  => 'hidden',
                    'default' => sanitize_text_field($_COOKIE['__bocs_discount_type'])
                );
            }
            
            // Total - check URL then cookie
            $total = '';
            if (isset($_GET['total']) && !empty($_GET['total'])) {
                $total = sanitize_text_field($_GET['total']);
            } elseif (isset($_COOKIE['__bocs_total'])) {
                $total = sanitize_text_field($_COOKIE['__bocs_total']);
            }
            
            if (!empty($total)) {
                $fields['billing']['bocs_total'] = array(
                    'type'  => 'hidden',
                    'default' => $total
                );
            }
            
            // Discount - check URL then cookie
            $discount = '';
            if (isset($_GET['discount']) && !empty($_GET['discount'])) {
                $discount = sanitize_text_field($_GET['discount']);
            } elseif (isset($_COOKIE['__bocs_discount'])) {
                $discount = sanitize_text_field($_COOKIE['__bocs_discount']);
            }
            
            if (!empty($discount)) {
                $fields['billing']['bocs_discount'] = array(
                    'type'  => 'hidden',
                    'default' => $discount
                );
            }
            
            // Price/Subtotal - check URL then cookie
            $price = '';
            if (isset($_GET['price']) && !empty($_GET['price'])) {
                $price = sanitize_text_field($_GET['price']);
            } elseif (isset($_COOKIE['__bocs_subtotal'])) {
                $price = sanitize_text_field($_COOKIE['__bocs_subtotal']);
            }
            
            if (!empty($price)) {
                $fields['billing']['bocs_price'] = array(
                    'type'  => 'hidden',
                    'default' => $price
                );
            }
        }
        
        return $fields;
    }
    
    /**
     * Adds BOCS subscription information to the checkout page
     */
    public function add_to_checkout_before_customer_details() {
        // Only show for BOCS subscriptions
        if (!$this->cart_contains_bocs_subscription()) {
            return;
        }
        
        // Get frequency information
        $frequency_id = '';
        $frequency_interval = '';
        $frequency_unit = '';
        
        // Check URL params first, then cookies
        if (isset($_GET['frequency']) && !empty($_GET['frequency'])) {
            $frequency_id = sanitize_text_field($_GET['frequency']);
        } elseif (isset($_COOKIE['__bocs_frequency_id'])) {
            $frequency_id = sanitize_text_field($_COOKIE['__bocs_frequency_id']);
        }
        
        if (isset($_COOKIE['__bocs_frequency_interval'])) {
            $frequency_interval = sanitize_text_field($_COOKIE['__bocs_frequency_interval']);
        }
        
        if (isset($_COOKIE['__bocs_frequency_time_unit'])) {
            $frequency_unit = sanitize_text_field($_COOKIE['__bocs_frequency_time_unit']);
        }
        
        // Only display if we have frequency information
        if (!empty($frequency_id) && (!empty($frequency_interval) || !empty($frequency_unit))) {
            // Format the subscription details
            $subscription_text = __('Subscription', 'bocs-wordpress');
            
            if (!empty($frequency_interval) && !empty($frequency_unit)) {
                // Format display text based on frequency unit
                if ($frequency_unit === 'day') {
                    $unit_text = $frequency_interval == 1 ? __('day', 'bocs-wordpress') : __('days', 'bocs-wordpress');
                } elseif ($frequency_unit === 'week') {
                    $unit_text = $frequency_interval == 1 ? __('week', 'bocs-wordpress') : __('weeks', 'bocs-wordpress');
                } elseif ($frequency_unit === 'month') {
                    $unit_text = $frequency_interval == 1 ? __('month', 'bocs-wordpress') : __('months', 'bocs-wordpress');
                } else {
                    $unit_text = $frequency_unit;
                }
                
                $subscription_text = sprintf(
                    __('Subscription: Every %1$s %2$s', 'bocs-wordpress'),
                    $frequency_interval,
                    $unit_text
                );
            }
            
            // Get discount if available
            $discount_text = '';
            if (isset($_COOKIE['__bocs_frequency_discount']) && !empty($_COOKIE['__bocs_frequency_discount'])) {
                $discount = sanitize_text_field($_COOKIE['__bocs_frequency_discount']);
                $discount_type = isset($_COOKIE['__bocs_discount_type']) ? sanitize_text_field($_COOKIE['__bocs_discount_type']) : 'fixed_cart';
                
                if ($discount_type === 'percent') {
                    $discount_text = sprintf(__('(%s%% discount)', 'bocs-wordpress'), $discount);
                } else {
                    $discount_text = sprintf(__('($%s discount)', 'bocs-wordpress'), $discount);
                }
            }
            
            // Display subscription information
            echo '<div class="bocs-subscription-info" style="margin-bottom: 20px; padding: 15px; background-color: #f8f9fa; border-left: 3px solid #4e3dc8;">';
            echo '<h3 style="margin-top: 0; color: #4e3dc8;">' . __('Subscription Details', 'bocs-wordpress') . '</h3>';
            echo '<p style="font-size: 16px;"><strong>' . $subscription_text . '</strong> ' . $discount_text . '</p>';
            
            // Add a note about managing subscriptions
            echo '<p style="font-size: 14px;">' . __('You can manage this subscription from your account dashboard after purchase.', 'bocs-wordpress') . '</p>';
            echo '</div>';
        }
    }

    /**
     * Saves BOCS-related information as order meta
     *
     * @param int $order_id The WooCommerce order ID
     */
    public function save_bocs_order_information($order_id) {
        // Only proceed if we have a valid order
        if (!$order_id) {
            return;
        }
        
        // Get the order
        $order = wc_get_order($order_id);
        
        // Check if this is a BOCS order by looking for BOCS ID
        $bocs_id = isset($_POST['bocs_id']) ? sanitize_text_field($_POST['bocs_id']) : '';
        
        if (empty($bocs_id) && isset($_COOKIE['__bocs_id'])) {
            $bocs_id = sanitize_text_field($_COOKIE['__bocs_id']);
        }
        
        if (!empty($bocs_id)) {
            // Save BOCS ID
            $order->update_meta_data('_bocs_id', $bocs_id);
            
            // Save Collection ID
            $collection_id = isset($_POST['bocs_collection_id']) ? sanitize_text_field($_POST['bocs_collection_id']) : '';
            if (empty($collection_id) && isset($_COOKIE['__bocs_collection_id'])) {
                $collection_id = sanitize_text_field($_COOKIE['__bocs_collection_id']);
            }
            if (!empty($collection_id)) {
                $order->update_meta_data('_bocs_collection_id', $collection_id);
            }
            
            // Save Frequency information
            $frequency_id = isset($_POST['bocs_frequency']) ? sanitize_text_field($_POST['bocs_frequency']) : '';
            if (empty($frequency_id) && isset($_COOKIE['__bocs_frequency_id'])) {
                $frequency_id = sanitize_text_field($_COOKIE['__bocs_frequency_id']);
            }
            if (!empty($frequency_id)) {
                $order->update_meta_data('_bocs_frequency_id', $frequency_id);
            }
            
            // Save Frequency Time Unit
            $frequency_time_unit = isset($_POST['bocs_frequency_time_unit']) ? sanitize_text_field($_POST['bocs_frequency_time_unit']) : '';
            if (empty($frequency_time_unit) && isset($_COOKIE['__bocs_frequency_time_unit'])) {
                $frequency_time_unit = sanitize_text_field($_COOKIE['__bocs_frequency_time_unit']);
            }
            if (!empty($frequency_time_unit)) {
                $order->update_meta_data('_bocs_frequency_time_unit', $frequency_time_unit);
            }
            
            // Save Frequency Interval
            $frequency_interval = isset($_POST['bocs_frequency_interval']) ? sanitize_text_field($_POST['bocs_frequency_interval']) : '';
            if (empty($frequency_interval) && isset($_COOKIE['__bocs_frequency_interval'])) {
                $frequency_interval = sanitize_text_field($_COOKIE['__bocs_frequency_interval']);
            }
            if (!empty($frequency_interval)) {
                $order->update_meta_data('_bocs_frequency_interval', $frequency_interval);
            }
            
            // Save Frequency Discount
            $frequency_discount = isset($_POST['bocs_frequency_discount']) ? sanitize_text_field($_POST['bocs_frequency_discount']) : '';
            if (empty($frequency_discount) && isset($_COOKIE['__bocs_frequency_discount'])) {
                $frequency_discount = sanitize_text_field($_COOKIE['__bocs_frequency_discount']);
            }
            if (!empty($frequency_discount)) {
                $order->update_meta_data('_bocs_frequency_discount', $frequency_discount);
            }
            
            // Save Discount Type
            $discount_type = isset($_POST['bocs_discount_type']) ? sanitize_text_field($_POST['bocs_discount_type']) : '';
            if (empty($discount_type) && isset($_COOKIE['__bocs_discount_type'])) {
                $discount_type = sanitize_text_field($_COOKIE['__bocs_discount_type']);
            }
            if (!empty($discount_type)) {
                $order->update_meta_data('_bocs_discount_type', $discount_type);
            }
            
            // Save Total
            $total = isset($_POST['bocs_total']) ? sanitize_text_field($_POST['bocs_total']) : '';
            if (empty($total) && isset($_COOKIE['__bocs_total'])) {
                $total = sanitize_text_field($_COOKIE['__bocs_total']);
            }
            if (!empty($total)) {
                $order->update_meta_data('_bocs_total', $total);
            }
            
            // Save Discount
            $discount = isset($_POST['bocs_discount']) ? sanitize_text_field($_POST['bocs_discount']) : '';
            if (empty($discount) && isset($_COOKIE['__bocs_discount'])) {
                $discount = sanitize_text_field($_COOKIE['__bocs_discount']);
            }
            if (!empty($discount)) {
                $order->update_meta_data('_bocs_discount', $discount);
            }
            
            // Save Price/Subtotal
            $price = isset($_POST['bocs_price']) ? sanitize_text_field($_POST['bocs_price']) : '';
            if (empty($price) && isset($_COOKIE['__bocs_subtotal'])) {
                $price = sanitize_text_field($_COOKIE['__bocs_subtotal']);
            }
            if (!empty($price)) {
                $order->update_meta_data('_bocs_price', $price);
            }
            
            // Save the order meta
            $order->save();
        }
    }

    /**
     * Removes the original recurring totals section to prevent duplication
     */
    public function remove_original_recurring_totals() {
        if (!$this->cart_contains_bocs_subscription()) {
            return;
        }
        
        // Remove the action that displays recurring totals
        remove_action('woocommerce_review_order_after_order_total', 'wcs_display_recurring_totals', 10);
        
        // Add our own action to display recurring totals in the proper position
        add_action('woocommerce_review_order_after_order_total', array($this, 'display_custom_recurring_totals'), 10);
    }
    
    /**
     * Displays our custom recurring totals template
     */
    public function display_custom_recurring_totals() {
        if (!$this->cart_contains_bocs_subscription()) {
            return;
        }
        
        // Static text with minimal formatting, no dynamic price calculation
        echo '<tr class="bocs-recurring-total"><th>Recurring total</th><td data-title="Recurring total">Same as order total</td></tr>';
    }
}