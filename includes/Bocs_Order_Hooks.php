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
        
        // Hook into order status changes for renewal order confirmation
        add_action('woocommerce_order_status_pending_to_processing', array($this, 'process_renewal_order_confirmation'), 10, 1);
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
     * Process renewal order confirmation when an order changes from pending to processing
     * 
     * @param int $order_id The order ID
     */
    public function process_renewal_order_confirmation($order_id) {
        // Check if the order has the required __bocs_order_status meta
        $bocs_order_status = get_post_meta($order_id, '__bocs_order_status', true);
        if ($bocs_order_status !== 'upcoming') {
            return;
        }
        
        // Check if email has already been sent
        $email_sent = get_post_meta($order_id, '_bocs_renewal_confirmation_email_sent', true);
        $email_sent_transient = 'bocs_renewal_email_sent_' . $order_id;
        
        if ($email_sent === 'yes' || get_transient($email_sent_transient)) {
            // Email already sent, just update meta but don't trigger email again
            update_post_meta($order_id, '__bocs_order_status', 'processing');
            return;
        }
        
        // Update the order status in the meta
        update_post_meta($order_id, '__bocs_order_status', 'processing');
        
        // Update the order status in the BOCS API
        $this->update_order_in_bocs_api($order_id);
        
        // Send the confirmation email - this is now handled in the email class itself
        // to avoid duplicate email sending
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
                CURLOPT_URL => BOCS_API_URL . 'orders/' . $bocs_id,
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
}