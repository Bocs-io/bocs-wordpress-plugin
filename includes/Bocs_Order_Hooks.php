<?php
/**
 * Bocs Order Hooks
 * 
 * Handles order-related functionality for the Bocs plugin
 * 
 * @package    Bocs
 * @subpackage Bocs/includes
 */

class Bocs_Order_Hooks {
    
    /**
     * Initialize hooks
     */
    public function __construct() {
        // Hook into REST API order creation
        add_action('woocommerce_rest_insert_shop_order_object', array($this, 'process_api_order'), 10, 3);
    }
    
    /**
     * Send Bocs renewal invoice email for orders created via the REST API
     * 
     * @param WC_Order $order The order object
     * @param WP_REST_Request $request The request object
     * @param bool $creating Whether this is a new order being created
     */
    public function process_api_order($order, $request, $creating) {
        // Only handle new orders
        if (!$creating) {
            return;
        }
        
        $order_id = $order->get_id();
        
        // Set all required Bocs meta data
        update_post_meta($order_id, '_wc_order_attribution_source_type', 'referral');
        update_post_meta($order_id, '_wc_order_attribution_utm_source', 'Bocs App');
        
        // Set Bocs IDs if they don't already exist
        if (!get_post_meta($order_id, '__bocs_bocs_id', true)) {
            update_post_meta($order_id, '__bocs_bocs_id', wp_generate_uuid4());
        }
        
        if (!get_post_meta($order_id, '__bocs_id', true)) {
            update_post_meta($order_id, '__bocs_id', wp_generate_uuid4());
        }
        
        if (!get_post_meta($order_id, '__bocs_subscription_id', true)) {
            update_post_meta($order_id, '__bocs_subscription_id', wp_generate_uuid4());
        }
        
        // Send the renewal invoice email
        if (class_exists('WC_Bocs_Email_Customer_Renewal_Invoice')) {
            error_log("Bocs - Sending renewal invoice email for API-created order #$order_id");
            $email = new WC_Bocs_Email_Customer_Renewal_Invoice();
            $email->trigger($order_id);
        }
    }
}