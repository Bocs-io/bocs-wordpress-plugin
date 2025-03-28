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
            // error_log("BOCS DEBUG [Bocs_Email]: Email classes already registered, skipping");
            return $email_classes;
        }

        // error_log("BOCS DEBUG [Bocs_Email]: Starting to add BOCS email classes to WooCommerce");
        // error_log("BOCS DEBUG [Bocs_Email]: Current email classes: " . print_r(array_keys($email_classes), true));
        
        // Load WooCommerce email classes if not already loaded
        if (!class_exists('WC_Email', false)) {
            include_once WC_ABSPATH . 'includes/emails/class-wc-email.php';
        }

        // Load all Bocs email classes
        $this->load_email_classes();

        // Register email classes with correct keys
        if (class_exists('WC_Bocs_Email_Subscription_Confirmation')) {
            $email_classes['bocs_subscription_confirmation'] = new WC_Bocs_Email_Subscription_Confirmation();
            // error_log("BOCS DEBUG [Bocs_Email]: Registered subscription confirmation email class");
        } else {
            // error_log("BOCS DEBUG [Bocs_Email]: WC_Bocs_Email_Subscription_Confirmation class not found");
        }
        
        if (class_exists('WC_Bocs_Email_New_Customer_Subscription')) {
            $email_classes['bocs_new_customer_subscription'] = new WC_Bocs_Email_New_Customer_Subscription();
            // error_log("BOCS DEBUG [Bocs_Email]: Registered new customer subscription email class");
        } else {
            // error_log("BOCS DEBUG [Bocs_Email]: WC_Bocs_Email_New_Customer_Subscription class not found");
        }
        
        if (class_exists('WC_Bocs_Email_Failed_Payment_Retry')) {
            $email_classes['bocs_failed_payment_retry'] = new WC_Bocs_Email_Failed_Payment_Retry();
            // error_log("BOCS DEBUG [Bocs_Email]: Registered failed payment retry email class");
        } else {
            // error_log("BOCS DEBUG [Bocs_Email]: WC_Bocs_Email_Failed_Payment_Retry class not found");
        }
        
        if (class_exists('WC_Bocs_Email_Upcoming_Renewal_Reminder')) {
            $email_classes['bocs_upcoming_renewal_reminder'] = new WC_Bocs_Email_Upcoming_Renewal_Reminder();
            // error_log("BOCS DEBUG [Bocs_Email]: Registered upcoming renewal reminder email class");
        } else {
            // error_log("BOCS DEBUG [Bocs_Email]: WC_Bocs_Email_Upcoming_Renewal_Reminder class not found");
        }
        
        if (class_exists('WC_Bocs_Email_Renewal_Order_Confirmation')) {
            $email_classes['bocs_renewal_order_confirmation'] = new WC_Bocs_Email_Renewal_Order_Confirmation();
            // error_log("BOCS DEBUG [Bocs_Email]: Registered renewal order confirmation email class");
        } else {
            // error_log("BOCS DEBUG [Bocs_Email]: WC_Bocs_Email_Renewal_Order_Confirmation class not found");
        }

        self::$email_classes_registered = true;
        // error_log("BOCS DEBUG [Bocs_Email]: BOCS email classes added successfully");
        // error_log("BOCS DEBUG [Bocs_Email]: Final registered email classes: " . print_r(array_keys($email_classes), true));
        
        // Verify the email classes are registered
        foreach ($email_classes as $key => $class) {
            // error_log("BOCS DEBUG [Bocs_Email]: Verified email class - Key: " . $key . ", Class: " . get_class($class));
        }
        
        return $email_classes;
    }

    /**
     * Load all BOCS email classes
     */
    private function load_email_classes() {
        // error_log("BOCS DEBUG [Bocs_Email]: Starting to load email classes");
        $email_class_files = array(
            'class-bocs-email-subscription-confirmation.php',
            'class-bocs-email-new-customer-subscription.php',
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
                // error_log("BOCS DEBUG [Bocs_Email]: Loading email class file: " . $file);
                include_once $file_path;
            } else {
                // error_log("BOCS DEBUG [Bocs_Email]: Email class file not found: " . $file_path);
            }
        }
        // error_log("BOCS DEBUG [Bocs_Email]: Finished loading email classes");
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
        // error_log('BOCS DEBUG [Bocs_Email]: Starting init method');
        
        if (!class_exists('WC_Email')) {
            // error_log('BOCS DEBUG [Bocs_Email]: WooCommerce email class not found, skipping initialization');
            return;
        }

        // Add filter to register email classes
        add_filter('woocommerce_email_classes', array($this, 'add_bocs_email_classes'), 20);
        // error_log('BOCS DEBUG [Bocs_Email]: Added woocommerce_email_classes filter');

        // Register email hooks
        $this->register_email_hooks();
        // error_log('BOCS DEBUG [Bocs_Email]: Registered email hooks');
    }

    private function register_email_hooks()
    {
        // error_log('BOCS DEBUG [Bocs_Email]: Starting register_email_hooks method');
        
        // Get the email class instance
        $emails = WC()->mailer()->get_emails();
        // error_log('BOCS DEBUG [Bocs_Email]: Available email classes: ' . print_r(array_keys($emails), true));
        
        // Handle failed payment retry email
        if (!isset($emails['bocs_failed_payment_retry'])) {
            // error_log('BOCS DEBUG [Bocs_Email]: Creating new instance of failed payment retry email class');
            $failed_payment_email = new WC_Bocs_Email_Failed_Payment_Retry();
        } else {
            $failed_payment_email = $emails['bocs_failed_payment_retry'];
            // error_log('BOCS DEBUG [Bocs_Email]: Found failed payment retry email class in mailer');
        }

        // Remove any existing hooks to prevent duplicates
        // error_log('BOCS DEBUG [Bocs_Email]: Removing existing hooks for failed payment retry email');
        remove_action('woocommerce_order_status_failed', array($failed_payment_email, 'trigger'));
        remove_action('woocommerce_rest_insert_shop_order_object', array($failed_payment_email, 'trigger'));
        remove_action('woocommerce_rest_shop_order_object_updated', array($failed_payment_email, 'trigger'));
        remove_action('woocommerce_store_api_checkout_order_processed', array($failed_payment_email, 'trigger'));
        remove_action('woocommerce_api_create_order', array($failed_payment_email, 'trigger'));
        remove_action('woocommerce_new_order', array($failed_payment_email, 'trigger'));

        // Register hooks for failed payment retry with high priority
        // error_log('BOCS DEBUG [Bocs_Email]: Registering hooks for failed payment retry email with priority 5');
        add_action('woocommerce_order_status_failed', array($failed_payment_email, 'trigger'), 5, 1);
        add_action('woocommerce_rest_insert_shop_order_object', array($failed_payment_email, 'trigger'), 5, 1);
        add_action('woocommerce_rest_shop_order_object_updated', array($failed_payment_email, 'trigger'), 5, 1);
        add_action('woocommerce_store_api_checkout_order_processed', array($failed_payment_email, 'trigger'), 5, 1);
        add_action('woocommerce_api_create_order', array($failed_payment_email, 'trigger'), 5, 1);
        add_action('woocommerce_new_order', array($failed_payment_email, 'trigger'), 5, 1);
        
        // error_log('BOCS DEBUG [Bocs_Email]: Registered hooks for failed payment retry email with priority 5');

        // Handle upcoming renewal reminder email
        if (!isset($emails['bocs_upcoming_renewal_reminder'])) {
            // error_log('BOCS DEBUG [Bocs_Email]: Creating new instance of upcoming renewal reminder email class');
            $upcoming_renewal_email = new WC_Bocs_Email_Upcoming_Renewal_Reminder();
        } else {
            $upcoming_renewal_email = $emails['bocs_upcoming_renewal_reminder'];
            // error_log('BOCS DEBUG [Bocs_Email]: Found upcoming renewal email class in mailer');
        }

        // Remove any existing hooks to prevent duplicates
        // error_log('BOCS DEBUG [Bocs_Email]: Removing existing hooks for upcoming renewal reminder email');
        remove_action('woocommerce_order_status_pending', array($upcoming_renewal_email, 'trigger'));
        remove_action('woocommerce_rest_insert_shop_order_object', array($upcoming_renewal_email, 'trigger'));
        remove_action('woocommerce_rest_shop_order_object_updated', array($upcoming_renewal_email, 'trigger'));
        remove_action('woocommerce_store_api_checkout_order_processed', array($upcoming_renewal_email, 'trigger'));
        remove_action('woocommerce_api_create_order', array($upcoming_renewal_email, 'trigger'));
        remove_action('woocommerce_new_order', array($upcoming_renewal_email, 'trigger'));

        // Register hooks for upcoming renewal reminder with high priority
        // error_log('BOCS DEBUG [Bocs_Email]: Registering hooks for upcoming renewal reminder email with priority 5');
        add_action('woocommerce_order_status_pending', array($upcoming_renewal_email, 'trigger'), 5, 1);
        add_action('woocommerce_rest_insert_shop_order_object', array($upcoming_renewal_email, 'trigger'), 5, 1);
        add_action('woocommerce_rest_shop_order_object_updated', array($upcoming_renewal_email, 'trigger'), 5, 1);
        add_action('woocommerce_store_api_checkout_order_processed', array($upcoming_renewal_email, 'trigger'), 5, 1);
        add_action('woocommerce_api_create_order', array($upcoming_renewal_email, 'trigger'), 5, 1);
        add_action('woocommerce_new_order', array($upcoming_renewal_email, 'trigger'), 5, 1);
        
        // error_log('BOCS DEBUG [Bocs_Email]: Registered hooks for upcoming renewal reminder email with priority 5');

        // Handle renewal order confirmation email
        if (!isset($emails['bocs_renewal_order_confirmation'])) {
            //error_log('BOCS DEBUG [Bocs_Email]: Creating new instance of renewal order confirmation email class');
            $renewal_confirmation_email = new WC_Bocs_Email_Renewal_Order_Confirmation();
        } else {
            $renewal_confirmation_email = $emails['bocs_renewal_order_confirmation'];
            //error_log('BOCS DEBUG [Bocs_Email]: Found renewal order confirmation email class in mailer');
        }

        // Remove any existing hooks to prevent duplicates
        //error_log('BOCS DEBUG [Bocs_Email]: Removing existing hooks for renewal order confirmation email');
        remove_action('woocommerce_order_status_pending_to_processing', array($renewal_confirmation_email, 'trigger'));
        remove_action('woocommerce_order_status_failed_to_processing', array($renewal_confirmation_email, 'trigger'));
        remove_action('woocommerce_order_status_on-hold_to_processing', array($renewal_confirmation_email, 'trigger'));
        remove_action('woocommerce_rest_insert_shop_order_object', array($renewal_confirmation_email, 'trigger'));
        remove_action('woocommerce_rest_shop_order_object_updated', array($renewal_confirmation_email, 'trigger'));

        // Register hooks for renewal order confirmation with high priority
        //error_log('BOCS DEBUG [Bocs_Email]: Registering hooks for renewal order confirmation email with priority 5');
        add_action('woocommerce_order_status_pending_to_processing', array($renewal_confirmation_email, 'trigger'), 5, 1);
        add_action('woocommerce_order_status_failed_to_processing', array($renewal_confirmation_email, 'trigger'), 5, 1);
        add_action('woocommerce_order_status_on-hold_to_processing', array($renewal_confirmation_email, 'trigger'), 5, 1);
        add_action('woocommerce_rest_insert_shop_order_object', array($renewal_confirmation_email, 'trigger'), 5, 1);
        add_action('woocommerce_rest_shop_order_object_updated', array($renewal_confirmation_email, 'trigger'), 5, 1);

        //error_log('BOCS DEBUG [Bocs_Email]: Registered hooks for renewal order confirmation email with priority 5');
    }
}

endif;
