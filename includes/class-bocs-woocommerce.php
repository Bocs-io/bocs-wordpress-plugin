<?php
/**
 * Bocs WooCommerce Integration
 *
 * @package    Bocs
 * @subpackage Bocs/includes
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class Bocs_WooCommerce
 * 
 * Handles integration with WooCommerce functionality
 */
class Bocs_WooCommerce {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register WooCommerce email classes
        add_filter('woocommerce_email_classes', array($this, 'add_email_classes'));
        
        // Hook into order creation and status changes
        add_action('woocommerce_checkout_order_processed', array($this, 'trigger_subscription_emails'), 30, 1);
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 3);
    }
    
    /**
     * Add Bocs email classes to WooCommerce
     *
     * @param array $email_classes WC_Email classes
     * @return array
     */
    public function add_email_classes($email_classes) {
        // Debug log
        error_log('BOCS DEBUG: Adding email classes to WooCommerce');
        
        // Include email class files if they're not already loaded
        if (!class_exists('WC_Bocs_Email_New_Customer_Subscription', false)) {
            include_once BOCS_PLUGIN_DIR . 'includes/emails/class-bocs-email-new-customer-subscription.php';
            error_log('BOCS DEBUG: Loaded new customer subscription email class');
        }
        
        if (!class_exists('WC_Bocs_Email_Existing_Customer_Subscription', false)) {
            include_once BOCS_PLUGIN_DIR . 'includes/emails/class-bocs-email-existing-customer-subscription.php';
            error_log('BOCS DEBUG: Loaded existing customer subscription email class');
        }
        
        if (!class_exists('WC_Bocs_Email_Customer_Renewal_Invoice', false)) {
            include_once BOCS_PLUGIN_DIR . 'includes/emails/class-bocs-email-customer-renewal-invoice.php';
            error_log('BOCS DEBUG: Loaded customer renewal invoice email class');
        }
        
        // Add the email classes
        $email_classes['bocs_new_customer_subscription'] = new WC_Bocs_Email_New_Customer_Subscription();
        $email_classes['bocs_existing_customer_subscription'] = new WC_Bocs_Email_Existing_Customer_Subscription();
        $email_classes['bocs_customer_renewal_invoice'] = new WC_Bocs_Email_Customer_Renewal_Invoice();
        
        error_log('BOCS DEBUG: Registered email classes: ' . implode(', ', array_keys($email_classes)));
        
        return $email_classes;
    }
    
    /**
     * Trigger subscription emails based on order details
     * 
     * @param int $order_id Order ID
     */
    public function trigger_subscription_emails($order_id) {
        error_log('BOCS DEBUG: Triggering subscription emails for order #' . $order_id);
        
        // Check if this is a Bocs subscription order
        $subscription_id = get_post_meta($order_id, '__bocs_subscription_id', true);
        
        if (empty($subscription_id)) {
            error_log('BOCS DEBUG: No subscription ID found for order #' . $order_id);
            return;
        }
        
        error_log('BOCS DEBUG: Found subscription ID: ' . $subscription_id . ' for order #' . $order_id);
        
        // Check if either email has already been sent
        $new_customer_email_sent = get_post_meta($order_id, '_bocs_new_customer_subscription_email_sent', true) === 'yes';
        $existing_customer_email_sent = get_post_meta($order_id, '_bocs_existing_customer_subscription_email_sent', true) === 'yes';
        
        error_log('BOCS DEBUG: Email status - New: ' . ($new_customer_email_sent ? 'sent' : 'not sent') . ', Existing: ' . ($existing_customer_email_sent ? 'sent' : 'not sent'));
        
        // Skip if either email has already been sent
        if ($new_customer_email_sent || $existing_customer_email_sent) {
            error_log('BOCS DEBUG: Email already sent for order #' . $order_id);
            return;
        }
        
        // Get the order object
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('BOCS DEBUG: Could not get order object for order #' . $order_id);
            return;
        }
        
        // Only proceed for processing or completed orders
        $status = $order->get_status();
        if (!in_array($status, array('processing', 'completed'))) {
            error_log('BOCS DEBUG: Order #' . $order_id . ' status is ' . $status . ', skipping email');
            return;
        }
        
        // Get the email classes
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();
        
        error_log('BOCS DEBUG: Available email classes: ' . implode(', ', array_keys($emails)));
        
        // Check if this is an existing customer
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
            
            error_log('BOCS DEBUG: Found ' . count($previous_orders) . ' previous orders for customer #' . $customer_id);
            
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
        
        error_log('BOCS DEBUG: Is existing customer: ' . ($is_existing_customer ? 'yes' : 'no'));
        
        try {
            // Send the appropriate email
            if ($is_existing_customer && isset($emails['bocs_existing_customer_subscription'])) {
                error_log('BOCS DEBUG: Sending existing customer email for order #' . $order_id);
                $emails['bocs_existing_customer_subscription']->trigger($order_id);
            } elseif (isset($emails['bocs_new_customer_subscription'])) {
                error_log('BOCS DEBUG: Sending new customer email for order #' . $order_id);
                $emails['bocs_new_customer_subscription']->trigger($order_id);
            } else {
                error_log('BOCS DEBUG: No appropriate email class found for order #' . $order_id);
            }
        } catch (Exception $e) {
            error_log('BOCS DEBUG: Failed to send subscription email: ' . $e->getMessage());
        }
    }

    /**
     * Handle order status changes
     *
     * @param int $order_id Order ID
     * @param string $old_status Old status
     * @param string $new_status New status
     */
    public function handle_order_status_change($order_id, $old_status, $new_status) {
        error_log('BOCS DEBUG: Order status changed from ' . $old_status . ' to ' . $new_status . ' for order #' . $order_id);
        
        // Get the order
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('BOCS DEBUG: Could not get order object for order #' . $order_id);
            return;
        }
        
        // Check for Bocs ID
        $bocs_id = $order->get_meta('__bocs_id');
        
        // If no Bocs ID, wait and retry
        if (empty($bocs_id)) {
            error_log('BOCS DEBUG: No Bocs ID found, waiting 2 seconds and retrying...');
            sleep(2); // Wait 2 seconds
            
            // Retry getting Bocs ID
            $bocs_id = $order->get_meta('__bocs_id');
            
            if (empty($bocs_id)) {
                error_log('BOCS DEBUG: Still no Bocs ID found after retry for order #' . $order_id);
                return;
            }
        }
        
        // Only proceed if status changed to processing or completed
        if (!in_array($new_status, array('processing', 'completed'))) {
            error_log('BOCS DEBUG: Order status is not processing/completed, skipping: ' . $new_status);
            return;
        }
        
        // Get the email classes
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();
        
        // Check if we have the existing customer subscription email class
        if (isset($emails['bocs_existing_customer_subscription'])) {
            error_log('BOCS DEBUG: Triggering existing customer subscription email for order #' . $order_id);
            $emails['bocs_existing_customer_subscription']->trigger($order_id);
        }
    }
} 