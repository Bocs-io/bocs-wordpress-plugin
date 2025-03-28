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
     * Initialize the class and set its properties.
     */
    public function __construct() {
        // No direct initialization needed
    }

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

        // Load all Bocs email classes
        $this->load_email_classes();

        // Make sure welcome email is ALWAYS registered
        if (class_exists('WC_Bocs_Email_Subscription_Confirmation')) {
            $email_classes['WC_Bocs_Email_Subscription_Confirmation'] = new WC_Bocs_Email_Subscription_Confirmation();
        } else {
            include_once BOCS_PLUGIN_DIR . 'includes/emails/class-bocs-email-subscription-confirmation.php';
            if (class_exists('WC_Bocs_Email_Subscription_Confirmation')) {
                $email_classes['WC_Bocs_Email_Subscription_Confirmation'] = new WC_Bocs_Email_Subscription_Confirmation();
            }
        }
        
        // Make sure new customer welcome email is ALWAYS registered
        if (class_exists('WC_Bocs_Email_New_Customer_Subscription')) {
            $email_classes['WC_Bocs_Email_New_Customer_Subscription'] = new WC_Bocs_Email_New_Customer_Subscription();
        } else {
            include_once BOCS_PLUGIN_DIR . 'includes/emails/class-bocs-email-new-customer-subscription.php';
            if (class_exists('WC_Bocs_Email_New_Customer_Subscription')) {
                $email_classes['WC_Bocs_Email_New_Customer_Subscription'] = new WC_Bocs_Email_New_Customer_Subscription();
            }
        }
        
        if (class_exists('WC_Bocs_Email_Failed_Payment_Retry')) {
            $email_classes['WC_Bocs_Email_Failed_Payment_Retry'] = new WC_Bocs_Email_Failed_Payment_Retry();
        }
        
        if (class_exists('WC_Bocs_Email_Upcoming_Renewal_Reminder')) {
            $email_classes['WC_Bocs_Email_Upcoming_Renewal_Reminder'] = new WC_Bocs_Email_Upcoming_Renewal_Reminder();
        }
        
        if (class_exists('WC_Bocs_Email_Renewal_Order_Confirmation')) {
            $email_classes['WC_Bocs_Email_Renewal_Order_Confirmation'] = new WC_Bocs_Email_Renewal_Order_Confirmation();
        }

        return $email_classes;
    }

    /**
     * Load all BOCS email classes
     */
    private function load_email_classes() {
        $email_class_files = array(
            'class-bocs-email-subscription-confirmation.php',
            'class-bocs-email-new-customer-subscription.php',
            'class-bocs-email-failed-payment-retry.php',
            'class-bocs-email-upcoming-renewal-reminder.php',
            'class-bocs-email-renewal-order-confirmation.php'
        );

        foreach ($email_class_files as $file) {
            $file_path = BOCS_PLUGIN_DIR . 'includes/emails/' . $file;
            if (file_exists($file_path)) {
                include_once $file_path;
            }
        }
    }

    /**
     * Disable corresponding WooCommerce default emails when Bocs emails are triggered.
     */
    public function disable_wc_emails() {
        $wc_emails = WC()->mailer()->get_emails();
        
        // Configure which WC emails to disable 
        $email_types_to_disable = array(
            'customer_completed_order',
            'customer_processing_order',
            'customer_on_hold_order',
            'customer_invoice',
            'failed_order'
        );
        
        // Disable specified WC emails
        foreach ($email_types_to_disable as $email_type) {
            if (isset($wc_emails[$email_type])) {
                remove_action('woocommerce_order_status_completed', array($wc_emails[$email_type], 'trigger'));
                remove_action('woocommerce_order_status_processing', array($wc_emails[$email_type], 'trigger'));
                remove_action('woocommerce_order_status_on-hold', array($wc_emails[$email_type], 'trigger'));
            }
        }
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

        // Disable corresponding WooCommerce emails
        add_action('woocommerce_init', array($this, 'disable_wc_emails'));
    }

    /**
     * Initialize email classes after WooCommerce is loaded
     */
    public function init_email_classes() {
        // Prevent duplicate email registrations
        static $emails_initialized = false;
        if ($emails_initialized) {
            error_log("Bocs: Prevented duplicate email hook registration");
            return;
        }
        $emails_initialized = true;
        
        // Make sure WooCommerce is loaded
        if (!function_exists('WC')) {
            return;
        }

        // Load all Bocs email classes
        $this->load_email_classes();

        // Failed Payment Retry Email - this occurs when a renewal order transitions from pending to failed
        if (class_exists('WC_Bocs_Email_Failed_Payment_Retry')) {
            $failed_payment_retry = new WC_Bocs_Email_Failed_Payment_Retry();
            add_action('woocommerce_order_status_pending_to_failed', array($failed_payment_retry, 'trigger'), 10, 1);
        }

        // Renewal Order Confirmation Email - this occurs when a renewal order is confirmed
        if (class_exists('WC_Bocs_Email_Renewal_Order_Confirmation')) {
            $renewal_order_confirmation = new WC_Bocs_Email_Renewal_Order_Confirmation();
            add_action('woocommerce_order_status_pending_to_processing', array($renewal_order_confirmation, 'trigger'), 10, 1);
        }

        // Upcoming Renewal Reminder Email
        if (class_exists('WC_Bocs_Email_Upcoming_Renewal_Reminder')) {
            $upcoming_renewal_reminder = new WC_Bocs_Email_Upcoming_Renewal_Reminder();
            add_action('bocs_upcoming_renewal_reminder', array($upcoming_renewal_reminder, 'trigger'), 10, 1);
        }
    }
}

endif;
