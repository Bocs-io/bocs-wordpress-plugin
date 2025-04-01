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
        add_action('woocommerce_order_status_changed', array($this, 'trigger_subscription_emails'), 30, 1);
    }
    
    /**
     * Add Bocs email classes to WooCommerce
     *
     * @param array $email_classes WC_Email classes
     * @return array
     */
    public function add_email_classes($email_classes) {
        // Include email class files if they're not already loaded
        if (!class_exists('WC_Bocs_Email_New_Customer_Subscription', false)) {
            include_once BOCS_PLUGIN_DIR . 'includes/emails/class-bocs-email-new-customer-subscription.php';
        }
        
        if (!class_exists('WC_Bocs_Email_Existing_Customer_Subscription', false)) {
            include_once BOCS_PLUGIN_DIR . 'includes/emails/class-bocs-email-existing-customer-subscription.php';
        }
        
        if (!class_exists('WC_Bocs_Email_Customer_Renewal_Invoice', false)) {
            include_once BOCS_PLUGIN_DIR . 'includes/emails/class-bocs-email-customer-renewal-invoice.php';
        }
        
        // Add the email classes
        $email_classes['bocs_new_customer_subscription'] = new WC_Bocs_Email_New_Customer_Subscription();
        $email_classes['bocs_existing_customer_subscription'] = new WC_Bocs_Email_Existing_Customer_Subscription();
        $email_classes['bocs_customer_renewal_invoice'] = new WC_Bocs_Email_Customer_Renewal_Invoice();
        
        return $email_classes;
    }
    
    /**
     * Trigger subscription emails based on order details
     * 
     * @param int $order_id Order ID
     */
    public function trigger_subscription_emails($order_id) {
        // Check if this is a Bocs subscription order
        $subscription_id = get_post_meta($order_id, '__bocs_subscription_id', true);
        
        if (empty($subscription_id)) {
            return;
        }
        
        // Check if either email has already been sent
        $new_customer_email_sent = get_post_meta($order_id, '_bocs_new_customer_subscription_email_sent', true) === 'yes';
        $existing_customer_email_sent = get_post_meta($order_id, '_bocs_existing_customer_subscription_email_sent', true) === 'yes';
        
        // Skip if either email has already been sent
        if ($new_customer_email_sent || $existing_customer_email_sent) {
            return;
        }
        
        // Get the order object
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Only proceed for processing or completed orders
        $status = $order->get_status();
        if (!in_array($status, array('processing', 'completed'))) {
            return;
        }
        
        // Get the email classes
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();
        
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
        
        try {
            // Send the appropriate email
            if ($is_existing_customer && isset($emails['bocs_existing_customer_subscription'])) {
                $emails['bocs_existing_customer_subscription']->trigger($order_id);
            } elseif (isset($emails['bocs_new_customer_subscription'])) {
                $emails['bocs_new_customer_subscription']->trigger($order_id);
            }
        } catch (Exception $e) {
            error_log('BOCS ERROR: Failed to send subscription email: ' . $e->getMessage());
        }
    }
} 