<?php
/**
 * Bocs Cart Class
 * 
 * Handles cart functionality for Bocs subscriptions including:
 * - Cart validation
 * - Subscription options
 * - Cart totals
 * - Product management
 *
 * @package Bocs
 * @since 0.0.1
 */
class Bocs_Cart {

    /**
     * Constructor
     */
    public function __construct() {
        // Add hooks for custom price functionality
        add_action('woocommerce_before_calculate_totals', array($this, 'apply_custom_prices'), 10, 1);
        
        // Add hook to handle removing BOCS from cart
        add_action('template_redirect', array($this, 'handle_remove_bocs_parameter'));
    }

    /**
     * Get available Bocs options for cart
     */
    public function get_bocs_options() {
        $template_path = dirname(__FILE__) . '/../views/bocs_homepage.php';
        
        // Get current Bocs ID from session
        $bocs_id = $this->get_current_bocs_id();

        if (empty($bocs_id)) {
            $product_ids = $this->get_cart_product_ids();
            $bocs_options = $this->get_available_bocs_options($product_ids);
        }

        // Display template if options are available
        if (file_exists($template_path) && !empty($bocs_options)) {
            include $template_path;
        }
    }

    /**
     * Get the current Bocs ID from session or cookie
     *
     * @since 0.0.1
     * @return string Bocs ID or empty string if not found
     */
    private function get_current_bocs_id() {
        $bocs_id = '';

        if (isset(WC()->session)) {
            $bocs_id = WC()->session->get('bocs');

            if (empty($bocs_id) && isset($_COOKIE['__bocs_id'])) {
                $bocs_id = sanitize_text_field($_COOKIE['__bocs_id']);
            }
        }

        return $bocs_id;
    }

    /**
     * Get all product IDs from the current cart
     *
     * @since 0.0.1
     * @return array Array of product IDs
     */
    private function get_cart_product_ids() {
        $product_ids = array();

        if (WC()->cart && !WC()->cart->is_empty()) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product_ids[] = $cart_item['product_id'];
            }
        }

        return $product_ids;
    }

    /**
     * Get available Bocs options for given product IDs
     *
     * @since 0.0.1
     * @param array $product_ids Array of product IDs
     * @return array Array of available Bocs options
     */
    private function get_available_bocs_options($product_ids) {
        $bocs_options = array();

        if (!empty($product_ids)) {
            try {
                $bocs_class = new Bocs_Bocs();
                $bocs_list = $bocs_class->get_all_bocs();

                if (!empty($bocs_list)) {
                    $bocs_options = $this->filter_bocs_options($bocs_list, $product_ids);

                    if (empty($bocs_options)) {
                        $bocs_options = $this->create_new_bocs_options($bocs_list, $product_ids);
                    }
                }
            } catch (Exception $e) {
                // Silently handle error
            }
        }

        return $bocs_options;
    }

    /**
     * Filter Bocs options based on product IDs
     *
     * @since 0.0.1
     * @param array $bocs_list List of all Bocs options
     * @param array $product_ids Array of product IDs
     * @return array Filtered Bocs options
     */
    private function filter_bocs_options($bocs_list, $product_ids)
    {
        $bocs_options = array();

        foreach ($bocs_list as $bocs_item) {
            $bocs_wp_ids = array();
            
            if (!empty($bocs_item['products'])) {
                foreach ($bocs_item['products'] as $bocs_product) {
                    $bocs_wp_ids[] = $bocs_product['externalSourceId'];
                }
            }

            if (empty(array_diff($product_ids, $bocs_wp_ids))) {
                $bocs_options[] = $bocs_item;
            }
        }

        return $bocs_options;
    }

    /**
     * Create new Bocs options for given product IDs
     *
     * @since 0.0.1
     * @param array $bocs_list List of all Bocs options
     * @param array $product_ids Array of product IDs
     * @return array New Bocs options
     */
    private function create_new_bocs_options($bocs_list, $product_ids)
    {
        foreach ($bocs_list as &$bocs_item) {
            $bocs_item['products'] = array();
            
            foreach ($product_ids as $product_id) {
                $wc_product = wc_get_product($product_id);
                if ($wc_product) {
                    $bocs_item['products'][] = array(
                        "description" => $wc_product->get_description(),
                        "externalSource" => "WP",
                        "externalSourceId" => $product_id,
                        "id" => "",
                        "name" => $wc_product->get_name(),
                        "price" => $wc_product->get_regular_price(),
                        "quantity" => 1,
                        "regularPrice" => $wc_product->get_regular_price(),
                        "salePrice" => $wc_product->get_price(),
                        "sku" => $wc_product->get_sku(),
                        "stockQuantity" => $wc_product->get_stock_quantity()
                    );
                }
            }
        }

        return $bocs_list;
    }

    /**
     * Display recurring totals before shipping
     *
     * @since 0.0.1
     * @return void
     */
    public function bocs_cart_totals_before_shipping() {
        ?>
        <div class="wc-block-components-totals-wrapper slot-wrapper">
            <div class="wc-block-components-order-meta">
                <div class="wcs-recurring-totals-panel">
                    <div class="wc-block-components-totals-item wcs-recurring-totals-panel__title">
                        <span class="wc-block-components-totals-item__label">
                            <?php esc_html_e('Monthly recurring total', 'bocs-wordpress'); ?>
                        </span>
                        <span class="wc-block-formatted-money-amount wc-block-components-formatted-money-amount wc-block-components-totals-item__value"></span>
                        <div class="wc-block-components-totals-item__description">
                            <span>
                                <?php 
                                printf(
                                    /* translators: %s: Starting date */
                                    esc_html__('Starting: %s', 'bocs-wordpress'),
                                    date_i18n(get_option('date_format'), strtotime('July 4, 2024'))
                                ); 
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Display content after cart contents in review order
     *
     * @since 0.0.1
     * @return void
     */
    public function bocs_review_order_after_cart_contents()
    {
        echo esc_html__('Additional cart contents information', 'bocs-wordpress');
    }

    /**
     * Display content before order total in review order
     *
     * @since 0.0.1
     * @return void
     */
    public function bocs_review_order_before_order_total()
    {
        printf(
            '<tr class="custom-text-before-subtotal"><th>%s</th><td>%s</td></tr>',
            esc_html__('Additional Info:', 'bocs-wordpress'),
            esc_html__('Your custom message here.', 'bocs-wordpress')
        );
    }

    /**
     * Display content before order total in cart totals
     *
     * @since 0.0.1
     * @return void
     */
    public function bocs_cart_totals_before_order_total()
    {
        printf(
            '<tr class="custom-text-before-subtotal"><th>%s</th><td>%s</td></tr>',
            esc_html__('Additional Info:', 'bocs-wordpress'),
            esc_html__('Your custom message here.', 'bocs-wordpress')
        );
    }

    /**
     * Check if cart contains a Bocs subscription
     *
     * @since 0.0.1
     * @return bool True if cart contains a Bocs subscription
     */
    public function cart_contains_bocs_subscription() {
        $bocs_id = $this->get_current_bocs_id();
        return !empty($bocs_id);
    }

    /**
     * Apply custom prices to cart items that have BOCS custom price metadata
     *
     * This function checks for product-specific price cookies and applies them to cart items
     * 
     * @param WC_Cart $cart The cart object
     * @return void
     */
    public function apply_custom_prices($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        // Don't run calculations twice
        if (did_action('woocommerce_before_calculate_totals') >= 2) {
            return;
        }
        
        // Check for BOCS frequency and discount information
        $frequency_id = isset($_COOKIE['__bocs_frequency_id']) ? sanitize_text_field($_COOKIE['__bocs_frequency_id']) : '';
        $frequency_unit = isset($_COOKIE['__bocs_frequency_time_unit']) ? sanitize_text_field($_COOKIE['__bocs_frequency_time_unit']) : '';
        $frequency_interval = isset($_COOKIE['__bocs_frequency_interval']) ? intval($_COOKIE['__bocs_frequency_interval']) : 0;
        $discount_type = isset($_COOKIE['__bocs_discount_type']) ? sanitize_text_field($_COOKIE['__bocs_discount_type']) : '';
        $discount_amount = isset($_COOKIE['__bocs_discount']) ? floatval($_COOKIE['__bocs_discount']) : 0;
        
        // Loop through cart items
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = isset($cart_item['product_id']) ? $cart_item['product_id'] : 0;
            $product_name = isset($cart_item['data']) ? $cart_item['data']->get_name() : 'Unknown product';
            
            if (!$product_id) {
                continue;
            }
            
            // Check for product-specific cookie
            $cookie_name = "__bocs_price_{$product_id}";
            if (isset($_COOKIE[$cookie_name])) {
                $custom_price = floatval($_COOKIE[$cookie_name]);
                
                if ($custom_price > 0) {
                    $original_price = $cart_item['data']->get_price();
                    $cart_item['data']->set_price($custom_price);
                }
            } else {
                // Fallback to general cookie for backwards compatibility
                if (isset($_COOKIE['__bocs_product_price']) && isset($_COOKIE['__bocs_product_id'])) {
                    $cookie_product_id = intval($_COOKIE['__bocs_product_id']);
                    $cookie_price = floatval($_COOKIE['__bocs_product_price']);
                    
                    if ($cookie_product_id === $product_id && $cookie_price > 0) {
                        $original_price = $cart_item['data']->get_price();
                        $cart_item['data']->set_price($cookie_price);
                    }
                }
            }
        }
    }
    
    /**
     * Add subscription options to the cart
     * 
     * Display BOCS subscription options in the cart collaterals area
     * 
     * @since 0.0.1
     * @return void 
     */
    public function add_subscription_options_to_cart() {
        // Check if we have a BOCS ID
        $bocs_id = $this->get_current_bocs_id();
        
        if (empty($bocs_id)) {
            // If no BOCS ID, display available options instead
            $this->get_bocs_options();
            return;
        }
        
        // Otherwise, display current subscription details
        $subscription_info = $this->get_subscription_details($bocs_id);
        
        if (!empty($subscription_info)) {
            include dirname(__FILE__) . '/../views/cart-subscription-details.php';
        }
    }
    
    /**
     * Get subscription details for a given BOCS ID
     * 
     * @param string $bocs_id The BOCS ID
     * @return array|false Subscription details or false if not found
     */
    private function get_subscription_details($bocs_id) {
        try {
            $bocs_class = new Bocs_Bocs();
            return $bocs_class->get_bocs($bocs_id);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Handle removing BOCS from cart via URL parameter
     * 
     * @since 0.0.1
     * @return void
     */
    public function handle_remove_bocs_parameter() {
        // Check if we have the remove_bocs parameter
        if (isset($_GET['remove_bocs']) && $_GET['remove_bocs'] == '1') {
            // Clear BOCS cookies
            $this->clear_bocs_cookies();
            
            // Clear BOCS from session
            if (isset(WC()->session)) {
                WC()->session->set('bocs', '');
            }
            
            // Redirect back to cart
            wp_safe_redirect(wc_get_cart_url());
            exit;
        }
    }
    
    /**
     * Clear all BOCS cookies
     * 
     * @since 0.0.1
     * @return void
     */
    private function clear_bocs_cookies() {
        $cookies_to_clear = array(
            '__bocs_id',
            '__bocs_collection_id',
            '__bocs_frequency_id',
            '__bocs_frequency_time_unit',
            '__bocs_frequency_interval',
            '__bocs_discount_type',
            '__bocs_total',
            '__bocs_discount',
            '__bocs_subtotal'
        );
        
        foreach ($cookies_to_clear as $cookie_name) {
            if (isset($_COOKIE[$cookie_name])) {
                setcookie($cookie_name, '', time() - 3600, '/');
            }
        }
    }
}
