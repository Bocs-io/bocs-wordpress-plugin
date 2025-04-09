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
 * Handles integration with WooCommerce subscription emails
 */
class Bocs_WooCommerce {

    /**
     * Constructor
     */
    public function __construct() {
        // Register email classes
        add_filter('woocommerce_email_classes', array($this, 'register_email_classes'));
        
        // Hook into order status changes to ensure subscription emails are sent
        add_action('woocommerce_order_status_changed', array($this, 'ensure_subscription_email_sent'), 20, 1);
        
        // Add this hook to send emails for orders that might have been missed
        add_action('woocommerce_after_order_object_save', array($this, 'ensure_subscription_email_sent'), 20, 1);
        
        // Backup hook for admin-initiated order status changes
        add_action('woocommerce_process_shop_order_meta', array($this, 'ensure_subscription_email_sent'), 50, 1);
    }

    /**
     * Register Bocs email classes with WooCommerce
     *
     * @param array $email_classes Existing email classes
     * @return array Updated email classes
     */
    public function register_email_classes($email_classes) {
        // Load email class files if needed
        if (!class_exists('WC_Bocs_Email_New_Customer_Subscription', false)) {
            if (file_exists(plugin_dir_path(__FILE__) . 'emails/class-bocs-email-new-customer-subscription.php')) {
                include_once plugin_dir_path(__FILE__) . 'emails/class-bocs-email-new-customer-subscription.php';
            }
        }
        
        if (!class_exists('WC_Bocs_Email_Existing_Customer_Subscription', false)) {
            if (file_exists(plugin_dir_path(__FILE__) . 'emails/class-bocs-email-existing-customer-subscription.php')) {
                include_once plugin_dir_path(__FILE__) . 'emails/class-bocs-email-existing-customer-subscription.php';
            }
        }
        
        // Register the email classes if they're available
        if (class_exists('WC_Bocs_Email_New_Customer_Subscription')) {
            $email_classes['bocs_new_customer_subscription'] = new WC_Bocs_Email_New_Customer_Subscription();
        }
        
        if (class_exists('WC_Bocs_Email_Existing_Customer_Subscription')) {
            $email_classes['bocs_existing_customer_subscription'] = new WC_Bocs_Email_Existing_Customer_Subscription();
        }
        
        return $email_classes;
    }

    /**
     * Ensure subscription emails are sent for processed orders
     * 
     * @param int $order_id
     */
    public function ensure_subscription_email_sent($order_id) {
        
        if (!$order_id) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $status = $order->get_status();
        if (!in_array($status, array('processing', 'completed'))) {
            return;
        }
        
        
        // Check if this is a Bocs subscription order
        $bocs_id = $order->get_meta('__bocs_id');
        $subscription_id = $order->get_meta('__bocs_subscription_id');
        $frequency_id = $order->get_meta('__bocs_frequency_id');
        
        
        // Skip if not a Bocs order
        if (empty($bocs_id) && empty($subscription_id) && empty($frequency_id)) {
            return;
        }
        
        // Check if emails were already sent
        $new_customer_email_sent = $order->get_meta('_bocs_new_customer_subscription_email_sent');
        $existing_customer_email_sent = $order->get_meta('_bocs_existing_customer_subscription_email_sent');
        
        
        if ($new_customer_email_sent === 'yes' || $existing_customer_email_sent === 'yes') {
            return;
        }
        
        // Get customer info
        $customer_id = $order->get_customer_id();
        if (!$customer_id) {
            return;
        }
        
        
        // Check if this is an existing customer
        $order_count = wc_get_customer_order_count($customer_id);
        
        // Get the email classes
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();
        
        // For existing customers (with more than 1 order), trigger the existing customer email
        if ($order_count > 1) {
            if (isset($emails['bocs_existing_customer_subscription'])) {
                $emails['bocs_existing_customer_subscription']->trigger($order_id);
            } else {
                do_action('bocs_existing_customer_subscription_email', $order_id);
            }
        } else {
            // For new customers, trigger the new customer email
            if (isset($emails['bocs_new_customer_subscription'])) {
                $emails['bocs_new_customer_subscription']->trigger($order_id);
            } else {
                do_action('bocs_new_customer_subscription_email', $order_id);
            }
        }
    }
}

// Initialize the class
add_action('plugins_loaded', function() {
    new Bocs_WooCommerce();
}); 