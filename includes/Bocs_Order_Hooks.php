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
        // Hook into WooCommerce API endpoints
        add_action('woocommerce_rest_insert_shop_order_object', array($this, 'process_api_order'), 10, 3);
        add_action('woocommerce_rest_update_shop_order_object', array($this, 'process_api_order_update'), 10, 3);
        
        // Hook into order status changes for renewal orders
        add_action('woocommerce_order_status_changed', array($this, 'process_renewal_order_confirmation'), 10, 1);
        
        // Hook into order status changes to sync status to BOCS API
        add_action('woocommerce_order_status_changed', array($this, 'sync_order_status_to_bocs_api'), 10, 4);
        
        // Disable default processing email for Bocs renewal orders
        add_action('woocommerce_email_before_order_table', array($this, 'maybe_disable_wc_processing_email'), 10, 4);
        
        // Hook into payment complete to ensure subscription emails are sent
        add_action('woocommerce_payment_complete', array($this, 'ensure_subscription_email_sent'), 10, 1);
        
        // Also hook into order status changes as a backup for ensuring emails are sent
        add_action('woocommerce_order_status_processing', array($this, 'ensure_subscription_email_sent'), 20, 1);
        add_action('woocommerce_order_status_completed', array($this, 'ensure_subscription_email_sent'), 20, 1);
        
        // Hook into order status changes to update user role on first purchase
        add_action('woocommerce_order_status_processing', array($this, 'update_user_role_on_first_purchase'), 10, 1);
        add_action('woocommerce_order_status_completed', array($this, 'update_user_role_on_first_purchase'), 10, 1);
        
        // Hook into a custom scheduled event to retry failed emails
        add_action('bocs_retry_failed_emails', array($this, 'retry_failed_subscription_emails'));
        
        // Schedule a daily event to retry sending any failed emails
        if (!wp_next_scheduled('bocs_retry_failed_emails')) {
            wp_schedule_event(time(), 'daily', 'bocs_retry_failed_emails');
        }
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
        
        // Mark this as a Bocs order to help with identification
        update_post_meta($order_id, '__bocs_source_type', 'app');
        
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
                    $metaData[$key]['value'] = $wc_status;
                    $found_meta = true;
                    break;
                }
            }
            
            // If the meta doesn't exist, add it
            if (!$found_meta) {
                $metaData[] = array(
                    'key' => '__bocs_order_status',
                    'value' => $wc_status
                );
            }
            
            // Update the order data
            $bocs_order['metaData'] = $metaData;
            $bocs_order['orderStatus'] = $wc_status;
            $bocs_order['status'] = $wc_status;
            
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
        
        // Check if this is a Bocs order
        $subscription_id = get_post_meta($order->get_id(), '__bocs_subscription_id', true);
        
        // If this has a Bocs subscription ID, disable the default processing email
        // as we'll handle it with our custom Bocs email
        if (!empty($subscription_id)) {
            error_log('BOCS DEBUG [Order Hooks]: Disabling default WooCommerce processing email for order #' . $order->get_id());
            add_filter('woocommerce_email_enabled_customer_processing_order', '__return_false', 999);
        }
    }
    
    /**
     * Ensure subscription emails are sent after API timeouts
     * 
     * @param int $order_id Order ID
     * @return void
     */
    public function ensure_subscription_email_sent($order_id) {
        // Check if the order has a bocs subscription ID
        $subscription_id = get_post_meta($order_id, '__bocs_subscription_id', true);
        
        // If we have a subscription ID, check if the subscription email has been sent
        if (!empty($subscription_id)) {
            // Check if either of our subscription emails have been sent
            $new_customer_email_sent = get_post_meta($order_id, '_bocs_new_customer_subscription_email_sent', true) === 'yes';
            $existing_customer_email_sent = get_post_meta($order_id, '_bocs_existing_customer_subscription_email_sent', true) === 'yes';
            
            // If neither email has been sent, try to send the appropriate one
            if (!$new_customer_email_sent && !$existing_customer_email_sent) {
                // Get the email classes
                $mailer = WC()->mailer();
                $emails = $mailer->get_emails();
                
                // Check if customer has previous Bocs orders
                $order = wc_get_order($order_id);
                if ($order) {
                    $customer_id = $order->get_customer_id();
                    $is_existing_customer = false;
                    
                    if ($customer_id > 0) {
                        // Get customer's previous orders with Bocs products
                        $previous_orders = wc_get_orders(array(
                            'customer_id' => $customer_id,
                            'status' => array('wc-completed', 'wc-processing'),
                            'limit' => -1,
                            'return' => 'ids',
                        ));
                        
                        // Exclude current order
                        $previous_orders = array_diff($previous_orders, array($order_id));
                        
                        // Check if any previous orders had Bocs products
                        foreach ($previous_orders as $prev_order_id) {
                            $prev_order = wc_get_order($prev_order_id);
                            if (!$prev_order) continue;
                            
                            // Check if order has Bocs meta
                            if ($prev_order->get_meta('__bocs_subscription_id')) {
                                $is_existing_customer = true;
                                break;
                            }
                        }
                    }
                    
                    // Increase timeouts to help with connection issues
                    set_time_limit(30);
                    
                    try {
                        // Add a filter to increase the email sending timeout
                        add_filter('wp_mail_timeout', function() { return 15; }); // 15 seconds
                        
                        if ($is_existing_customer && isset($emails['bocs_existing_customer_subscription'])) {
                            // Trigger the existing customer email
                            $emails['bocs_existing_customer_subscription']->trigger($order_id);
                            error_log('BOCS RECOVERY: Triggered existing customer subscription email for order #' . $order_id);
                        } elseif (isset($emails['bocs_new_customer_subscription'])) {
                            // Trigger the new customer email
                            $emails['bocs_new_customer_subscription']->trigger($order_id);
                            error_log('BOCS RECOVERY: Triggered new customer subscription email for order #' . $order_id);
                        }
                    } catch (Exception $e) {
                        error_log('BOCS ERROR: Failed to send recovery email: ' . $e->getMessage());
                    }
                }
            }
        }
    }
    
    /**
     * Retry sending subscription emails that previously failed
     * 
     * @return void
     */
    public function retry_failed_subscription_emails() {
        error_log('BOCS RETRY: Starting retry of failed subscription emails');
        
        // Query for orders with __bocs_subscription_id that don't have the email sent meta
        global $wpdb;
        
        // Find orders with Bocs subscription IDs
        $query = $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = %s
            WHERE p.post_type = %s
            AND p.post_status IN ('wc-processing', 'wc-completed')
            AND pm1.meta_key = %s
            AND pm1.meta_value != ''
            AND (pm2.meta_value IS NULL OR pm2.meta_value != %s)
            LIMIT 50",
            '_bocs_new_customer_subscription_email_sent',
            'shop_order',
            '__bocs_subscription_id',
            'yes'
        );
        
        $orders = $wpdb->get_results($query);
        
        error_log('BOCS RETRY: Found ' . count($orders) . ' orders with missing email confirmations');
        
        foreach ($orders as $order) {
            // Try to send the email for this order
            $this->ensure_subscription_email_sent($order->ID);
            
            // Add a small delay to avoid overwhelming the server
            usleep(500000); // 0.5 seconds
        }
        
        error_log('BOCS RETRY: Completed retry of failed subscription emails');
    }

    /**
     * Updates a user's role to "subscriber" on their first purchase of any Bocs product
     * 
     * @param int $order_id The order ID
     * @return void
     */
    public function update_user_role_on_first_purchase($order_id) {
        // Get the order
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Check if this is a Bocs subscription order
        $subscription_id = get_post_meta($order_id, '__bocs_subscription_id', true);
        if (empty($subscription_id)) {
            // Not a Bocs subscription order, skip
            return;
        }

        // Get the customer ID
        $customer_id = $order->get_customer_id();
        if (!$customer_id) {
            return;
        }

        // Get the WP_User object
        $user = get_user_by('id', $customer_id);
        if (!$user) {
            return;
        }

        // Check if this is the user's first order with Bocs products
        $previous_bocs_orders = wc_get_orders(array(
            'customer_id' => $customer_id,
            'status' => array('wc-completed', 'wc-processing'),
            'exclude' => array($order_id), // Exclude current order
            'limit' => 1,
            'return' => 'ids',
            'meta_key' => '__bocs_subscription_id',
            'meta_compare' => 'EXISTS',
        ));

        // If this is not their first Bocs order, skip
        if (!empty($previous_bocs_orders)) {
            return;
        }

        // This is their first Bocs order - set user role to "subscriber"
        $user->set_role('subscriber');
        error_log('BOCS DEBUG [User Role]: Updated user #' . $customer_id . ' role to subscriber for their first Bocs purchase');

        // Sync role with Bocs API if Sync class is available
        if (class_exists('Sync')) {
            $sync = new Sync();
            try {
                // Create user data array for sync
                $user_data = array(
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'email' => $user->user_email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'role' => 'subscriber'
                );
                
                // Get sync method if it exists
                if (method_exists($sync, 'profile_update')) {
                    $sync->profile_update($user->ID, $user, array_merge($user_data, array('role' => 'subscriber')));
                }
            } catch (Exception $e) {
                error_log('BOCS ERROR [User Role]: Failed to sync user role with Bocs API: ' . $e->getMessage());
            }
        }
    }

    /**
     * Sync order status changes to the BOCS API for any order with a subscription ID
     * 
     * @param int $order_id Order ID
     * @param string $status_from Previous status
     * @param string $status_to New status
     * @param WC_Order $order Order object
     */
    public function sync_order_status_to_bocs_api($order_id, $status_from, $status_to, $order) {
        // Check if this order has a Bocs subscription ID
        $subscription_id = get_post_meta($order_id, '__bocs_subscription_id', true);
        if (!$subscription_id) {
            $subscription_id = get_post_meta($order_id, '_bocs_subscription_id', true);
        }
        
        if (empty($subscription_id)) {
            // Not a Bocs subscription order, skip
            return;
        }
        
        error_log('BOCS DEBUG [Order Hooks]: Syncing status change for order #' . $order_id . ' from ' . $status_from . ' to ' . $status_to);
        
        // Update Bocs order status meta
        update_post_meta($order_id, '__bocs_order_status', $status_to);
        
        // Update the order in the BOCS API
        $api_updated = $this->update_order_in_bocs_api($order_id);
        error_log('BOCS DEBUG [Order Hooks]: API status update ' . ($api_updated ? 'successful' : 'failed'));
    }
}