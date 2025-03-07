<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'Bocs_Email' ) ) :

/**
 * Bocs Email Class
 *
 * Handles the registration and initialization of all BOCS email notifications
 *
 * @since 1.0.0
 */
class Bocs_Email
{
    /**
     * Add BOCS email classes to WooCommerce
     *
     * @param array $email_classes Array of WooCommerce email classes
     * @return array Modified array of WooCommerce email classes
     */
    public function add_bocs_email_classes($email_classes)
    {
        // Check if WooCommerce is active
        if (!function_exists('WC')) {
            return $email_classes;
        }

        // Load WooCommerce email classes if not already loaded
        if (!class_exists('WC_Email', false)) {
            include_once WC_ABSPATH . 'includes/emails/class-wc-email.php';
        }

        // Add email classes
        if (class_exists('WC_Bocs_Email_Processing_Renewal_Order')) {
            $email_classes['WC_Bocs_Email_Processing_Renewal_Order'] = new WC_Bocs_Email_Processing_Renewal_Order();
        }
        if (class_exists('WC_Bocs_Email_Completed_Renewal_Order')) {
            $email_classes['WC_Bocs_Email_Completed_Renewal_Order'] = new WC_Bocs_Email_Completed_Renewal_Order();
        }
        if (class_exists('WC_Bocs_Email_On_Hold_Renewal_Order')) {
            $email_classes['WC_Bocs_Email_On_Hold_Renewal_Order'] = new WC_Bocs_Email_On_Hold_Renewal_Order();
        }
        if (class_exists('WC_Bocs_Email_Customer_Renewal_Invoice')) {
            $email_classes['WC_Bocs_Email_Customer_Renewal_Invoice'] = new WC_Bocs_Email_Customer_Renewal_Invoice();
        }
        if (class_exists('WC_Bocs_Email_Subscription_Switched')) {
            $email_classes['WC_Bocs_Email_Subscription_Switched'] = new WC_Bocs_Email_Subscription_Switched();
        }

        return $email_classes;
    }

    /**
     * Initialize the email hooks
     */
    public function init() {
        // Make sure WooCommerce is loaded
        if (!function_exists('WC')) {
            return;
        }

        // Add email classes after WooCommerce loads its own email classes
        add_filter('woocommerce_email_classes', array($this, 'add_bocs_email_classes'), 20);
    }

    /**
     * Initialize email classes after WooCommerce is loaded
     */
    public function init_email_classes() {
        // Make sure WooCommerce is loaded
        if (!function_exists('WC')) {
            return;
        }

        // Processing Renewal Order Email
        if (class_exists('WC_Bocs_Email_Processing_Renewal_Order')) {
            $processing_orders = new WC_Bocs_Email_Processing_Renewal_Order();
            add_action('woocommerce_order_status_processing', array($processing_orders, 'trigger'), 10, 1);
            add_action('woocommerce_order_status_pending_to_processing', array($processing_orders, 'trigger'), 10, 1);
            add_action('woocommerce_order_status_failed_to_processing', array($processing_orders, 'trigger'), 10, 1);
        }

        // Completed Renewal Order Email
        if (class_exists('WC_Bocs_Email_Completed_Renewal_Order')) {
            $completed_orders = new WC_Bocs_Email_Completed_Renewal_Order();
            add_action('woocommerce_order_status_completed', array($completed_orders, 'trigger'), 10, 1);
            add_action('woocommerce_order_status_processing_to_completed', array($completed_orders, 'trigger'), 10, 1);
        }

        // On-hold Renewal Order Email
        if (class_exists('WC_Bocs_Email_On_Hold_Renewal_Order')) {
            $onhold_orders = new WC_Bocs_Email_On_Hold_Renewal_Order();
            add_action('woocommerce_order_status_on-hold', array($onhold_orders, 'trigger'), 10, 1);
            add_action('woocommerce_order_status_pending_to_on-hold', array($onhold_orders, 'trigger'), 10, 1);
            add_action('woocommerce_order_status_failed_to_on-hold', array($onhold_orders, 'trigger'), 10, 1);
        }

        // Customer Renewal Invoice Email
        if (class_exists('WC_Bocs_Email_Customer_Renewal_Invoice')) {
            $renewal_invoice = new WC_Bocs_Email_Customer_Renewal_Invoice();
            add_action('woocommerce_order_status_pending', array($renewal_invoice, 'trigger'), 10, 1);
            add_action('woocommerce_order_status_failed', array($renewal_invoice, 'trigger'), 10, 1);
        }

        // Subscription Switched Email
        if (class_exists('WC_Bocs_Email_Subscription_Switched')) {
            $subscription_switched = new WC_Bocs_Email_Subscription_Switched();
            add_action('bocs_subscription_switched', array($subscription_switched, 'trigger'), 10, 2);
        }
    }
}

endif;
