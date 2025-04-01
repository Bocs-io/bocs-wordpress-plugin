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
        
        // Handle REST API order updates
        add_action('woocommerce_rest_shop_order_object_updated', array($this, 'process_api_order_update'), 10, 3);
        
        // Hook into order status changes for renewal order confirmation
        add_action('woocommerce_order_status_pending_to_processing', array($this, 'process_renewal_order_confirmation'), 10, 1);
        
        // Disable default WooCommerce processing email for Bocs renewal orders
        add_action('woocommerce_email_before_order_table', array($this, 'maybe_disable_wc_processing_email'), 5, 4);
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
            // error_log("Bocs - Sending renewal invoice email for API-created order #$order_id");
            $email = new WC_Bocs_Email_Customer_Renewal_Invoice();
            $email->trigger($order_id);
        }
    }
    
    /**
     * Handle order updates via REST API
     * 
     * @param WC_Order $order The order object
     * @param WP_REST_Request $request The request object
     * @param bool $creating Whether this is a new order being created
     */
    public function process_api_order_update($order, $request, $creating) {
        // Only handle updates, not new orders
        if ($creating) {
            return;
        }

        // Make sure we have a valid order object
        if (!is_object($order) || !method_exists($order, 'get_id')) {
            error_log('BOCS DEBUG [Order Hooks]: Invalid order object in API update');
            return;
        }

        $order_id = $order->get_id();
        error_log('BOCS DEBUG [Order Hooks]: Processing API order update for order ID: ' . $order_id);
        
        // Check if this order has Bocs subscription ID
        $bocs_subscription_id = get_post_meta($order_id, '__bocs_subscription_id', true);
        if (empty($bocs_subscription_id)) {
            error_log('BOCS DEBUG [Order Hooks]: API update - No subscription ID found for order ' . $order_id);
            return;
        }
        
        // Get current status 
        $order_status = $order->get_status();
        $bocs_order_status = get_post_meta($order_id, '__bocs_order_status', true);
        
        error_log('BOCS DEBUG [Order Hooks]: API update - Order status: ' . $order_status . ', Bocs status: ' . $bocs_order_status);
        
        // If current status is processing, check and reset email sent status if needed
        if ($order_status === 'processing') {
            $email_sent = get_post_meta($order_id, '_bocs_renewal_confirmation_email_sent', true);
            error_log('BOCS DEBUG [Order Hooks]: API update - Email sent meta: ' . $email_sent);
            
            // If the email is marked as sent, but order just transitioned to processing,
            // reset it to ensure the confirmation email gets sent
            if ($email_sent === 'yes' && $bocs_order_status !== 'processing') {
                delete_post_meta($order_id, '_bocs_renewal_confirmation_email_sent');
                error_log('BOCS DEBUG [Order Hooks]: API update - Reset email sent meta to allow email sending');
            }
        }
        
        // If Bocs status is empty but we have subscription ID, initialize it to match WC status
        if (empty($bocs_order_status) && !empty($bocs_subscription_id)) {
            error_log('BOCS DEBUG [Order Hooks]: API update - Initializing Bocs status to: ' . $order_status);
            update_post_meta($order_id, '__bocs_order_status', $order_status);
            $bocs_order_status = $order_status;
        }
        
        // If WooCommerce status is processing but Bocs status isn't, update it
        if ($order_status === 'processing' && $bocs_order_status !== 'processing') {
            error_log('BOCS DEBUG [Order Hooks]: API update - Updating Bocs status to processing');
            update_post_meta($order_id, '__bocs_order_status', 'processing');
            
            // Update the order in the BOCS API
            $this->update_order_in_bocs_api($order_id);
        }
    }
    
    /**
     * Process renewal order confirmation when an order changes from pending to processing
     * 
     * @param int $order_id The order ID
     */
    public function process_renewal_order_confirmation($order_id) {
        error_log('BOCS DEBUG [Order Hooks]: Processing renewal order confirmation for order ID: ' . $order_id);

        // Ensure we have a valid order ID
        if (!$order_id || !is_numeric($order_id)) {
            error_log('BOCS DEBUG [Order Hooks]: Invalid order ID, skipping');
            return;
        }

        // Get the order object
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('BOCS DEBUG [Order Hooks]: Could not find order object, skipping');
            return;
        }

        // Verify the order status is 'processing' before proceeding
        if ($order->get_status() !== 'processing') {
            error_log('BOCS DEBUG [Order Hooks]: Order status is not "processing", skipping: ' . $order->get_status());
            return;
        }

        // Check if we have a subscription ID - required for renewal orders
        $subscription_id = get_post_meta($order_id, '__bocs_subscription_id', true);
        if (empty($subscription_id)) {
            error_log('BOCS DEBUG [Order Hooks]: No subscription ID found, skipping renewal confirmation');
            return;
        }

        // Reset the email sent meta to ensure it's not accidentally marked as sent
        // This ensures the email will be sent properly when the order changes to processing
        $email_sent = get_post_meta($order_id, '_bocs_renewal_confirmation_email_sent', true);
        error_log('BOCS DEBUG [Order Hooks]: Email sent meta: ' . $email_sent);
        if ($email_sent === 'yes') {
            // Reset this meta so the email will be sent
            delete_post_meta($order_id, '_bocs_renewal_confirmation_email_sent');
            error_log('BOCS DEBUG [Order Hooks]: Reset _bocs_renewal_confirmation_email_sent meta to allow email sending');
        }

        // Check if the order has the required __bocs_order_status meta
        $bocs_order_status = get_post_meta($order_id, '__bocs_order_status', true);
        
        // If status is empty but we have a subscription ID, default to "upcoming" for backward compatibility
        if (empty($bocs_order_status) && !empty($subscription_id)) {
            error_log('BOCS DEBUG [Order Hooks]: Setting default status "upcoming" for subscription ' . $subscription_id);
            update_post_meta($order_id, '__bocs_order_status', 'upcoming');
            $bocs_order_status = 'upcoming';
        } else if (empty($bocs_order_status)) {
            error_log('BOCS DEBUG [Order Hooks]: Bocs order status is empty, skipping renewal confirmation');
            return;
        }
        
        error_log('BOCS DEBUG [Order Hooks]: Bocs order status: ' . $bocs_order_status);
        
        if ($bocs_order_status !== 'upcoming') {
            error_log('BOCS DEBUG [Order Hooks]: Order status is not "upcoming", skipping renewal confirmation');
            return;
        }
        
        // Check if email has already been sent
        $email_sent = get_post_meta($order_id, '_bocs_renewal_confirmation_email_sent', true);
        $email_sent_transient = 'bocs_renewal_email_sent_' . $order_id;
        
        error_log('BOCS DEBUG [Order Hooks]: Email sent meta: ' . $email_sent);
        
        if ($email_sent === 'yes' || get_transient($email_sent_transient)) {
            // Email already sent, just update meta but don't trigger email again
            error_log('BOCS DEBUG [Order Hooks]: Email already sent, updating order status only');
            update_post_meta($order_id, '__bocs_order_status', 'processing');
            return;
        }
        
        // Set a temporary transient to prevent duplicate emails during concurrent processing
        // This will be replaced by the permanent meta field when the email is actually sent
        set_transient($email_sent_transient, 'processing', 60);
        error_log('BOCS DEBUG [Order Hooks]: Set temporary transient to prevent duplicate emails');
        
        // Update the order status in the meta
        update_post_meta($order_id, '__bocs_order_status', 'processing');
        error_log('BOCS DEBUG [Order Hooks]: Updated order status meta to processing');
        
        // Update the order status in the BOCS API
        $api_updated = $this->update_order_in_bocs_api($order_id);
        error_log('BOCS DEBUG [Order Hooks]: API update ' . ($api_updated ? 'successful' : 'failed'));
        
        // The actual email sending is handled by the email class triggered from the WooCommerce hooks
        // The email class is responsible for setting _bocs_renewal_confirmation_email_sent to 'yes'
        error_log('BOCS DEBUG [Order Hooks]: Email handling delegated to WooCommerce email hooks');
    }
    
    /**
     * Update the order status in the BOCS API
     * 
     * @param int $order_id The order ID
     * @return bool Success status
     */
    private function update_order_in_bocs_api($order_id) {
        // Get order data
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        // Get order status and Bocs ID
        $wc_status = $order->get_status();
        $bocs_id = get_post_meta($order_id, '__bocs_bocs_id', true);
        if (empty($bocs_id)) {
            $bocs_id = get_post_meta($order_id, '__bocs_id', true);
        }
        
        // Don't proceed if we don't have Bocs ID
        if (empty($bocs_id)) {
            return false;
        }
        
        // Get API credentials
        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();
        
        // Don't proceed if we don't have credentials
        if (empty($options['bocs_headers']['organization']) || 
            empty($options['bocs_headers']['store']) || 
            empty($options['bocs_headers']['authorization'])) {
            return false;
        }
        
        // First, fetch the existing order data from the API
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => BOCS_API_URL . 'orders?query=externalSourceId:' . urlencode($order_id),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Organization: ' . $options['bocs_headers']['organization'],
                    'Content-Type: application/json',
                    'Store: ' . $options['bocs_headers']['store'],
                    'Authorization: ' . $options['bocs_headers']['authorization']
                )
            ));
            
            $response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            if ($http_code < 200 || $http_code >= 300) {
                return false;
            }
            
            // Parse the response
            $order_data = json_decode($response, true);
            
            // Check if we have valid data
            if (!isset($order_data['data']['data']) || !is_array($order_data['data']['data']) || count($order_data['data']['data']) === 0) {
                return false;
            }
            
            // Get the first order from the results
            $bocs_order = $order_data['data']['data'][0];
            
            // Update the order status in the metadata
            $metaData = $bocs_order['metaData'] ?? array();
            $found_meta = false;
            
            foreach ($metaData as $key => $meta) {
                if ($meta['key'] === '__bocs_order_status') {
                    $metaData[$key]['value'] = 'processing';
                    $found_meta = true;
                    break;
                }
            }
            
            // If the meta doesn't exist, add it
            if (!$found_meta) {
                $metaData[] = array(
                    'key' => '__bocs_order_status',
                    'value' => 'processing'
                );
            }
            
            // Update the order data
            $bocs_order['metaData'] = $metaData;
            $bocs_order['orderStatus'] = $wc_status;
            $bocs_order['status'] = 'processing';
            
            // Now send the updated order data back to the API
            $update_curl = curl_init();
            curl_setopt_array($update_curl, array(
                CURLOPT_URL => BOCS_API_URL . 'orders/' . $bocs_order['id'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => json_encode($bocs_order),
                CURLOPT_HTTPHEADER => array(
                    'Organization: ' . $options['bocs_headers']['organization'],
                    'Content-Type: application/json',
                    'Store: ' . $options['bocs_headers']['store'],
                    'Authorization: ' . $options['bocs_headers']['authorization']
                )
            ));
            
            $update_response = curl_exec($update_curl);
            $update_http_code = curl_getinfo($update_curl, CURLINFO_HTTP_CODE);
            curl_close($update_curl);
            
            return $update_http_code >= 200 && $update_http_code < 300;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Disable the default WooCommerce processing email for Bocs renewal orders
     *
     * @param WC_Order $order Order object
     * @param bool $sent_to_admin Whether the email is being sent to admin
     * @param bool $plain_text Whether the email is plain text
     * @param WC_Email $email The email object
     */
    public function maybe_disable_wc_processing_email($order, $sent_to_admin, $plain_text, $email) {
        // Only proceed if this is the WooCommerce processing email
        if (!is_a($email, 'WC_Email_Customer_Processing_Order')) {
            return;
        }
        
        // Check if this is a Bocs renewal order
        $subscription_id = get_post_meta($order->get_id(), '__bocs_subscription_id', true);
        
        // If this is a Bocs renewal order, disable the default processing email
        if (!empty($subscription_id)) {
            error_log('BOCS DEBUG [Order Hooks]: Disabling default WooCommerce processing email for order #' . $order->get_id());
            add_filter('woocommerce_email_enabled_customer_processing_order', '__return_false', 999);
        }
    }
}