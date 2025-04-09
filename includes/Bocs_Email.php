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
    private static $instance = null;
    private static $hooks_registered = false;
    private static $email_classes_registered = false;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the class and set its properties.
     */
    private function __construct() {
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
        if (self::$email_classes_registered) {
            return $email_classes;
        }

        // Load WooCommerce email classes if not already loaded
        if (!class_exists('WC_Email', false)) {
            include_once WC_ABSPATH . 'includes/emails/class-wc-email.php';
        }

        // Load all Bocs email classes
        $this->load_email_classes();

        // Manual check for the existing customer subscription class
        if (!class_exists('WC_Bocs_Email_Existing_Customer_Subscription') && !class_exists('WC_Bocs_Email_Existing_customer_subscription')) {
            
            $file_path = BOCS_PLUGIN_DIR . 'includes/emails/class-bocs-email-existing-customer-subscription.php';
            if (file_exists($file_path)) {
                include_once $file_path;   
            }
        }

        // Register email classes with correct keys
        if (class_exists('WC_Bocs_Email_Subscription_Confirmation')) {
            $email_classes['bocs_subscription_confirmation'] = new WC_Bocs_Email_Subscription_Confirmation();
        }
        
        if (class_exists('WC_Bocs_Email_New_Customer_Subscription')) {
            $email_classes['bocs_new_customer_subscription'] = new WC_Bocs_Email_New_Customer_Subscription();
        }
        
        if (class_exists('WC_Bocs_Email_Existing_Customer_Subscription') || class_exists('WC_Bocs_Email_Existing_customer_subscription')) {
            // Get the actual class name that exists
            $class_name = class_exists('WC_Bocs_Email_Existing_Customer_Subscription') ? 
                'WC_Bocs_Email_Existing_Customer_Subscription' : 'WC_Bocs_Email_Existing_customer_subscription';
            
            $email_classes['bocs_existing_customer_subscription'] = new $class_name();
        }
        
        if (class_exists('WC_Bocs_Email_Failed_Payment_Retry')) {
            $email_classes['bocs_failed_payment_retry'] = new WC_Bocs_Email_Failed_Payment_Retry();
        }
        
        if (class_exists('WC_Bocs_Email_Upcoming_Renewal_Reminder')) {
            $email_classes['bocs_upcoming_renewal_reminder'] = new WC_Bocs_Email_Upcoming_Renewal_Reminder();
        }
        
        if (class_exists('WC_Bocs_Email_Renewal_Order_Confirmation')) {
            $email_classes['bocs_renewal_order_confirmation'] = new WC_Bocs_Email_Renewal_Order_Confirmation();
        }
        
        // Add the box updated email class
        if (class_exists('WC_Bocs_Email_Subscription_Switched')) {
            $email_classes['bocs_subscription_switched'] = new WC_Bocs_Email_Subscription_Switched();
            // Register with WooCommerce mailer
            if (isset($email_classes['bocs_subscription_switched'])) {
                $email_classes['bocs_subscription_switched']->register_with_woocommerce($email_classes);
            }
        }
        
        // Add the subscription paused email class
        if (class_exists('WC_Bocs_Email_Subscription_Paused')) {
            $email_classes['bocs_subscription_paused'] = new WC_Bocs_Email_Subscription_Paused();
            // Register with WooCommerce mailer
            if (isset($email_classes['bocs_subscription_paused'])) {
                $email_classes['bocs_subscription_paused']->register_with_woocommerce($email_classes);
            }
        }
        
        // Add the subscription cancelled email class
        if (class_exists('WC_Bocs_Email_Subscription_Cancelled')) {
            $email_classes['bocs_subscription_cancelled'] = new WC_Bocs_Email_Subscription_Cancelled();
            // Register with WooCommerce mailer
            if (isset($email_classes['bocs_subscription_cancelled'])) {
                $email_classes['bocs_subscription_cancelled']->register_with_woocommerce($email_classes);
            }
        }
        
        // Add the subscription reactivated email class
        if (class_exists('WC_Bocs_Email_Subscription_Reactivated')) {
            $email_classes['bocs_subscription_reactivated'] = new WC_Bocs_Email_Subscription_Reactivated();
            // Register with WooCommerce mailer
            if (isset($email_classes['bocs_subscription_reactivated'])) {
                $email_classes['bocs_subscription_reactivated']->register_with_woocommerce($email_classes);
            }
        }
        
        // Add the payment method updated email class
        if (class_exists('WC_Bocs_Email_Payment_Method_Updated')) {
            $email_classes['bocs_payment_method_updated'] = new WC_Bocs_Email_Payment_Method_Updated();
            // Register with WooCommerce mailer
            if (isset($email_classes['bocs_payment_method_updated'])) {
                $email_classes['bocs_payment_method_updated']->register_with_woocommerce($email_classes);
            }
        }

        self::$email_classes_registered = true;
        
        return $email_classes;
    }

    /**
     * Load all BOCS email classes
     */
    private function load_email_classes() {
        $email_class_files = array(
            'class-bocs-email-subscription-confirmation.php',
            'class-bocs-email-new-customer-subscription.php',
            'class-bocs-email-existing-customer-subscription.php',
            'class-bocs-email-failed-payment-retry.php',
            'class-bocs-email-upcoming-renewal-reminder.php',
            'class-bocs-email-renewal-order-confirmation.php',
            'class-bocs-email-subscription-switched.php',
            'class-bocs-email-subscription-cancelled.php',
            'class-bocs-email-subscription-paused.php',
            'class-bocs-email-subscription-reactivated.php',
            'class-bocs-email-payment-method-updated.php'
        );

        foreach ($email_class_files as $file) {
            $file_path = BOCS_PLUGIN_DIR . 'includes/emails/' . $file;
            
            if (file_exists($file_path)) {
                include_once $file_path;
                
                // Check if class was successfully loaded
                $class_name = str_replace(array('class-bocs-email-', '.php'), array('WC_Bocs_Email_', ''), $file);
                
                // Fix string case transformation for proper capitalization
                $class_name = str_replace('-', '_', $class_name);
                $parts = explode('_', $class_name);
                $class_name = '';
                
                foreach ($parts as $part) {
                    $class_name .= ucfirst($part) . '_';
                }
                
                $class_name = rtrim($class_name, '_');
                
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
    public function init()
    {
        if (!class_exists('WC_Email')) {
            return;
        }

        // Add filter to register email classes
        add_filter('woocommerce_email_classes', array($this, 'add_bocs_email_classes'), 20);
        
        // Register email hooks
        $this->register_email_hooks();
        
    }

    private function register_email_hooks()
    {
        
        // Get the email class instance
        $emails = WC()->mailer()->get_emails();
        
        // Register the existing customer subscription email hook
        if (!isset($emails['bocs_existing_customer_subscription'])) {
            
            if (class_exists('WC_Bocs_Email_Existing_Customer_Subscription')) {
                $existing_customer_email = new WC_Bocs_Email_Existing_Customer_Subscription();
                // Add the hook to trigger this email
                add_action('bocs_existing_customer_subscription_email', array($existing_customer_email, 'trigger'), 10, 1);
            } elseif (class_exists('WC_Bocs_Email_Existing_customer_subscription')) {
                $existing_customer_email = new WC_Bocs_Email_Existing_Customer_Subscription();
                // Add the hook to trigger this email
                add_action('bocs_existing_customer_subscription_email', array($existing_customer_email, 'trigger'), 10, 1);
            }
        } else {
            // Use the registered email class
            $existing_customer_email = $emails['bocs_existing_customer_subscription'];
            // Add the hook to trigger this email
            add_action('bocs_existing_customer_subscription_email', array($existing_customer_email, 'trigger'), 10, 1);
        }
        
        // Handle failed payment retry email
        if (!isset($emails['bocs_failed_payment_retry'])) {
            $failed_payment_email = new WC_Bocs_Email_Failed_Payment_Retry();
        } else {
            $failed_payment_email = $emails['bocs_failed_payment_retry'];
        }

        // Remove any existing hooks to prevent duplicates
        remove_action('woocommerce_order_status_failed', array($failed_payment_email, 'trigger'));
        remove_action('woocommerce_rest_insert_shop_order_object', array($failed_payment_email, 'trigger'));
        remove_action('woocommerce_rest_shop_order_object_updated', array($failed_payment_email, 'trigger'));
        remove_action('woocommerce_store_api_checkout_order_processed', array($failed_payment_email, 'trigger'));
        remove_action('woocommerce_api_create_order', array($failed_payment_email, 'trigger'));
        remove_action('woocommerce_new_order', array($failed_payment_email, 'trigger'));

        // Register hooks for failed payment retry with high priority
        add_action('woocommerce_order_status_failed', array($failed_payment_email, 'trigger'), 5, 1);
        add_action('woocommerce_rest_insert_shop_order_object', array($failed_payment_email, 'trigger'), 5, 1);
        add_action('woocommerce_rest_shop_order_object_updated', array($failed_payment_email, 'trigger'), 5, 1);
        add_action('woocommerce_store_api_checkout_order_processed', array($failed_payment_email, 'trigger'), 5, 1);
        add_action('woocommerce_api_create_order', array($failed_payment_email, 'trigger'), 5, 1);
        add_action('woocommerce_new_order', array($failed_payment_email, 'trigger'), 5, 1);
        
        // Handle upcoming renewal reminder email
        if (!isset($emails['bocs_upcoming_renewal_reminder'])) {
            $upcoming_renewal_email = new WC_Bocs_Email_Upcoming_Renewal_Reminder();
        } else {
            $upcoming_renewal_email = $emails['bocs_upcoming_renewal_reminder'];
        }

        // Remove any existing hooks to prevent duplicates
        remove_action('woocommerce_order_status_pending', array($upcoming_renewal_email, 'trigger'));
        remove_action('woocommerce_rest_insert_shop_order_object', array($upcoming_renewal_email, 'trigger'));
        remove_action('woocommerce_rest_shop_order_object_updated', array($upcoming_renewal_email, 'trigger'));
        remove_action('woocommerce_store_api_checkout_order_processed', array($upcoming_renewal_email, 'trigger'));
        remove_action('woocommerce_api_create_order', array($upcoming_renewal_email, 'trigger'));
        remove_action('woocommerce_new_order', array($upcoming_renewal_email, 'trigger'));

        // Register hooks for upcoming renewal reminder with high priority
        add_action('woocommerce_order_status_pending', array($upcoming_renewal_email, 'trigger'), 5, 1);
        add_action('woocommerce_rest_insert_shop_order_object', array($upcoming_renewal_email, 'trigger'), 5, 1);
        add_action('woocommerce_rest_shop_order_object_updated', array($upcoming_renewal_email, 'trigger'), 5, 1);
        add_action('woocommerce_store_api_checkout_order_processed', array($upcoming_renewal_email, 'trigger'), 5, 1);
        add_action('woocommerce_api_create_order', array($upcoming_renewal_email, 'trigger'), 5, 1);
        add_action('woocommerce_new_order', array($upcoming_renewal_email, 'trigger'), 5, 1);

        // Handle renewal order confirmation email
        if (!isset($emails['bocs_renewal_order_confirmation'])) {
            $renewal_confirmation_email = new WC_Bocs_Email_Renewal_Order_Confirmation();
        } else {
            $renewal_confirmation_email = $emails['bocs_renewal_order_confirmation'];
        }

        // Remove any existing hooks to prevent duplicates
        remove_action('woocommerce_order_status_pending_to_processing', array($renewal_confirmation_email, 'trigger'));
        remove_action('woocommerce_order_status_failed_to_processing', array($renewal_confirmation_email, 'trigger'));
        remove_action('woocommerce_order_status_on-hold_to_processing', array($renewal_confirmation_email, 'trigger'));
        remove_action('woocommerce_rest_insert_shop_order_object', array($renewal_confirmation_email, 'trigger'));
        remove_action('woocommerce_rest_shop_order_object_updated', array($renewal_confirmation_email, 'trigger'));

        // Register hooks for renewal order confirmation with high priority
        add_action('woocommerce_order_status_pending_to_processing', array($renewal_confirmation_email, 'trigger'), 5, 1);
        add_action('woocommerce_order_status_failed_to_processing', array($renewal_confirmation_email, 'trigger'), 5, 1);
        add_action('woocommerce_order_status_on-hold_to_processing', array($renewal_confirmation_email, 'trigger'), 5, 1);
        add_action('woocommerce_rest_insert_shop_order_object', array($renewal_confirmation_email, 'trigger'), 5, 1);
        add_action('woocommerce_rest_shop_order_object_updated', array($renewal_confirmation_email, 'trigger'), 5, 1);
    }
}

endif;
