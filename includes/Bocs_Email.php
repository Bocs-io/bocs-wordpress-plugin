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
        
        // Make sure welcome email is ALWAYS registered
        if (class_exists('WC_Bocs_Email_Welcome')) {
            $email_classes['WC_Bocs_Email_Welcome'] = new WC_Bocs_Email_Welcome();
        } else {
            include_once BOCS_PLUGIN_DIR . 'includes/emails/class-bocs-email-welcome.php';
            if (class_exists('WC_Bocs_Email_Welcome')) {
                $email_classes['WC_Bocs_Email_Welcome'] = new WC_Bocs_Email_Welcome();
            }
        }
        
        if (class_exists('WC_Bocs_Email_Failed_Renewal_Payment')) {
            $email_classes['WC_Bocs_Email_Failed_Renewal_Payment'] = new WC_Bocs_Email_Failed_Renewal_Payment();
        }
        if (class_exists('WC_Bocs_Email_Upcoming_Renewal_Reminder')) {
            $email_classes['WC_Bocs_Email_Upcoming_Renewal_Reminder'] = new WC_Bocs_Email_Upcoming_Renewal_Reminder();
        }
        if (class_exists('WC_Bocs_Email_Subscription_Cancelled')) {
            $email_classes['WC_Bocs_Email_Subscription_Cancelled'] = new WC_Bocs_Email_Subscription_Cancelled();
        }
        if (class_exists('WC_Bocs_Email_Payment_Method_Update')) {
            $email_classes['WC_Bocs_Email_Payment_Method_Update'] = new WC_Bocs_Email_Payment_Method_Update();
        }
        if (class_exists('WC_Bocs_Email_Payment_Retry')) {
            $email_classes['WC_Bocs_Email_Payment_Retry'] = new WC_Bocs_Email_Payment_Retry();
        }
        if (class_exists('WC_Bocs_Email_Subscription_Paused')) {
            $email_classes['WC_Bocs_Email_Subscription_Paused'] = new WC_Bocs_Email_Subscription_Paused();
        }
        if (class_exists('WC_Bocs_Email_Subscription_Reactivated')) {
            $email_classes['WC_Bocs_Email_Subscription_Reactivated'] = new WC_Bocs_Email_Subscription_Reactivated();
        }
        if (class_exists('WC_Bocs_Email_Manual_Renewal_Reminder')) {
            $email_classes['WC_Bocs_Email_Manual_Renewal_Reminder'] = new WC_Bocs_Email_Manual_Renewal_Reminder();
        }

        return $email_classes;
    }

    /**
     * Load all BOCS email classes
     */
    public function load_email_classes() {
        $email_class_files = array(
            'class-bocs-email-processing-renewal-order.php',
            'class-bocs-email-completed-renewal-order.php',
            'class-bocs-email-on-hold-renewal-order.php',
            'class-bocs-email-customer-renewal-invoice.php',
            'class-bocs-email-subscription-switched.php',
            'class-bocs-email-welcome.php',
            'class-bocs-email-failed-renewal-payment.php',
            'class-bocs-email-upcoming-renewal-reminder.php',
            'class-bocs-email-subscription-cancelled.php',
            'class-bocs-email-payment-method-update.php',
            'class-bocs-email-payment-retry.php',
            'class-bocs-email-subscription-paused.php',
            'class-bocs-email-subscription-reactivated.php',
            'class-bocs-email-manual-renewal-reminder.php'
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
        // Get WooCommerce mailer instance
        $mailer = WC()->mailer();
        if (!$mailer) {
            return;
        }

        // Load all Bocs email classes
        $this->load_email_classes();

        // Map our email IDs to WooCommerce default email IDs
        $email_mapping = array(
            // Processing Renewal Order
            'bocs_processing_renewal_order' => array(
                'customer_processing_order',
                'customer_processing_renewal_order'
            ),
            // Completed Renewal Order
            'bocs_completed_renewal_order' => array(
                'customer_completed_order',
                'customer_completed_renewal_order'
            ),
            // On Hold Renewal Order
            'bocs_on_hold_renewal_order' => array(
                'customer_on_hold_order',
                'customer_on_hold_renewal_order'
            ),
            // Customer Renewal Invoice
            'bocs_customer_renewal_invoice' => array(
                'customer_invoice',
                'customer_renewal_invoice'
            ),
            // Subscription Switched
            'bocs_subscription_switched' => array(
                'customer_subscription_switched'
            ),
            // Welcome Email
            'bocs_welcome' => array(
                'customer_new_account',
                'customer_processing_order'
            ),
            // Failed Renewal Payment
            'bocs_failed_renewal_payment' => array(
                'failed_order',
                'failed_subscription_renewal'
            ),
            // Upcoming Renewal Reminder
            'bocs_upcoming_renewal_reminder' => array(
                'customer_renewal_invoice',
                'customer_payment_retry'
            ),
            // Subscription Cancelled
            'bocs_subscription_cancelled' => array(
                'cancelled_subscription',
                'cancelled_order'
            ),
            // Payment Retry
            'bocs_payment_retry' => array(
                'failed_order',
                'failed_subscription_renewal',
                'customer_payment_retry'
            ),
            // Payment Method Update
            'bocs_payment_method_update' => array(
                'customer_payment_retry',
                'failed_subscription_renewal'
            ),
            // Subscription Paused
            'bocs_subscription_paused' => array(
                'customer_on_hold_order',
                'subscription_put_on_hold'
            ),
            // Subscription Reactivated
            'bocs_subscription_reactivated' => array(
                'customer_processing_order',
                'subscription_activated'
            ),
            // Manual Renewal Reminder
            'bocs_manual_renewal_reminder' => array(
                'customer_renewal_invoice',
                'customer_payment_retry'
            )
        );

        // Add filters for each email type
        foreach ($email_mapping as $bocs_email_id => $wc_email_ids) {
            foreach ($wc_email_ids as $wc_email_id) {
                add_filter("woocommerce_email_enabled_{$wc_email_id}", function($enabled, $order = null) use ($bocs_email_id) {
                    // If no order object is provided, check if we're in a subscription context
                    if (!$order && function_exists('wcs_get_subscription')) {
                        global $wp;
                        if (!empty($wp->query_vars['subscription_id'])) {
                            $order = wcs_get_subscription($wp->query_vars['subscription_id']);
                        }
                    }

                    // If still no order, return original enabled status
                    if (!$order) {
                        return $enabled;
                    }

                    // Get the order ID (handle both orders and subscriptions)
                    $order_id = is_callable(array($order, 'get_parent_id')) ? $order->get_parent_id() : $order->get_id();
                    
                    // Check parent order if this is a subscription and has a parent
                    if (is_callable(array($order, 'get_parent_id')) && $order->get_parent_id()) {
                        $parent_order_id = $order->get_parent_id();
                        $source_type = get_post_meta($parent_order_id, '_wc_order_attribution_source_type', true);
                        $utm_source = get_post_meta($parent_order_id, '_wc_order_attribution_utm_source', true);
                        
                        if ($source_type === 'referral' && $utm_source === 'Bocs App') {
                            return false;
                        }
                    }

                    // Check the order itself
                    $source_type = get_post_meta($order_id, '_wc_order_attribution_source_type', true);
                    $utm_source = get_post_meta($order_id, '_wc_order_attribution_utm_source', true);

                    // If this is a Bocs order, disable the corresponding WooCommerce email
                    if ($source_type === 'referral' && $utm_source === 'Bocs App') {
                        return false;
                    }

                    return $enabled;
                }, 10, 2);
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
        
        // Register direct test endpoint for welcome email
        add_action('wp_ajax_test_bocs_welcome_email', array($this, 'test_welcome_email'));
        add_action('wp_ajax_nopriv_test_bocs_welcome_email', array($this, 'test_welcome_email'));
    }

    /**
     * Initialize email classes after WooCommerce is loaded
     */
    public function init_email_classes() {
        // Make sure WooCommerce is loaded
        if (!function_exists('WC')) {
            return;
        }

        // Load all Bocs email classes
        $this->load_email_classes();

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
        
        // Welcome Email - Make it trigger on multiple key WooCommerce hooks
        if (class_exists('WC_Bocs_Email_Welcome')) {
            $welcome_email = new WC_Bocs_Email_Welcome();
            
            // Hook into all order creation and status change events
            add_action('woocommerce_new_order', array($welcome_email, 'trigger'), 10, 1);
            add_action('woocommerce_order_status_pending_to_processing', array($welcome_email, 'trigger'), 10, 1);
            add_action('woocommerce_order_status_pending_to_completed', array($welcome_email, 'trigger'), 10, 1);
            add_action('woocommerce_order_status_on-hold_to_processing', array($welcome_email, 'trigger'), 10, 1);
            add_action('woocommerce_checkout_order_processed', array($welcome_email, 'trigger'), 10, 1);
            add_action('woocommerce_thankyou', array($welcome_email, 'trigger'), 10, 1);
            
            // Also force it on WooCommerce payment complete
            add_action('woocommerce_payment_complete', array($welcome_email, 'trigger'), 10, 1);
        }

        // Failed Renewal Payment Email
        if (class_exists('WC_Bocs_Email_Failed_Renewal_Payment')) {
            $failed_payment = new WC_Bocs_Email_Failed_Renewal_Payment();
            add_action('woocommerce_subscription_payment_failed', array($failed_payment, 'trigger'), 10, 1);
            add_action('woocommerce_order_status_failed', array($failed_payment, 'trigger'), 10, 1);
        }

        // Upcoming Renewal Reminder Email
        if (class_exists('WC_Bocs_Email_Upcoming_Renewal_Reminder')) {
            $renewal_reminder = new WC_Bocs_Email_Upcoming_Renewal_Reminder();
            
            // Register a daily cron event to check for upcoming renewals
            if (!wp_next_scheduled('bocs_check_upcoming_renewals')) {
                wp_schedule_event(time(), 'daily', 'bocs_check_upcoming_renewals');
            }
            
            // Add the custom hook for upcoming renewals
            add_action('bocs_upcoming_renewal_reminder', array($renewal_reminder, 'trigger'), 10, 2);
            
            // Handler for the cron job to find subscriptions about to renew
            add_action('bocs_check_upcoming_renewals', function() use ($renewal_reminder) {
                if (function_exists('wcs_get_subscriptions')) {
                    // Get subscriptions that will renew in 3 days
                    $days_before = 3; // Number of days before renewal to send the reminder
                    $renewal_date = date('Y-m-d', strtotime("+{$days_before} days"));
                    
                    // Get subscriptions with next payment date around the target date
                    $subscriptions = wcs_get_subscriptions(array(
                        'subscriptions_per_page' => -1,
                        'subscription_status'    => 'active',
                        'meta_query'            => array(
                            array(
                                'key'     => '_schedule_next_payment',
                                'value'   => array(
                                    strtotime($renewal_date . ' 00:00:00'),
                                    strtotime($renewal_date . ' 23:59:59'),
                                ),
                                'compare' => 'BETWEEN',
                                'type'    => 'NUMERIC',
                            ),
                        ),
                    ));
                    
                    // Send reminder for each subscription
                    foreach ($subscriptions as $subscription_id => $subscription) {
                        // Get the next payment date
                        $next_payment = $subscription->get_date('next_payment');
                        if ($next_payment) {
                            do_action('bocs_upcoming_renewal_reminder', $subscription_id, $next_payment);
                        }
                    }
                }
            });
        }

        // Subscription Cancelled Email
        if (class_exists('WC_Bocs_Email_Subscription_Cancelled')) {
            $subscription_cancelled = new WC_Bocs_Email_Subscription_Cancelled();
            
            // Hook into subscription status changes
            add_action('woocommerce_subscription_status_cancelled', array($subscription_cancelled, 'trigger'), 10, 1);
            add_action('woocommerce_subscription_status_active_to_cancelled', array($subscription_cancelled, 'trigger'), 10, 1);
        }
    }

    /**
     * Test function to trigger welcome email directly
     */
    public function test_welcome_email() {
        // Check if WooCommerce is active
        if (!function_exists('WC')) {
            wp_die('WooCommerce is not active');
        }
        
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        
        if (!$order_id) {
            // Get the most recent order
            $orders = wc_get_orders(array('limit' => 1, 'orderby' => 'date', 'order' => 'DESC'));
            if (!empty($orders)) {
                $order_id = $orders[0]->get_id();
            }
        }
        
        if ($order_id) {
            // Load the welcome email class
            require_once BOCS_PLUGIN_DIR . 'includes/emails/class-bocs-email-welcome.php';
            
            $welcome_email = new WC_Bocs_Email_Welcome();
            
            // Force enable the email
            $welcome_email->enabled = 'yes';
            
            // Send the email
            $welcome_email->trigger($order_id);
            
            echo 'Attempted to send welcome email for order #' . $order_id;
        } else {
            echo 'No orders found';
        }
        
        wp_die();
    }
}

endif;
