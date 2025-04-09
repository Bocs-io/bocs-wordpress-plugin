<?php
/**
 * Class WC_Bocs_Email_Existing_Customer_Subscription
 *
 * @package     Bocs\Emails
 * @version     0.0.118
 * @since       0.0.118
 * @author      Bocs
 * @category    Emails
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Existing Customer New Subscription Confirmation Email
 *
 * An email sent to existing customers when they set up a new subscription through Bocs.
 * This handles the email notification sent to returning customers after a new Bocs subscription is created.
 *
 * @class       WC_Bocs_Email_Existing_Customer_Subscription
 * @version     0.0.118
 * @package     Bocs\Emails
 * @extends     WC_Email
 */
class WC_Bocs_Email_Existing_Customer_Subscription extends WC_Email {

    /**
     * Bocs ID associated with this order.
     *
     * @var string
     */
    public $bocs_id;

    /**
     * Constructor
     *
     * Initializes email parameters and settings.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->id             = 'bocs_existing_customer_subscription';
        $this->customer_email = true;
        $this->title          = __('[Bocs Customer] Additional Subscription for Existing Customer', 'bocs-wordpress');
        $this->description    = __('Email sent to existing customers when they set up an additional Bocs subscription.', 'bocs-wordpress');
        $this->template_html  = 'emails/bocs-existing-customer-subscription.php';
        $this->template_plain = 'emails/plain/bocs-existing-customer-subscription.php';
        
        // Make sure we use the correct template path
        if (defined('BOCS_TEMPLATE_PATH')) {
            $this->template_base = BOCS_TEMPLATE_PATH;
        } else {
            // Fallback to plugin directory
            $this->template_base = plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/';
        }
        
        $this->placeholders   = array(
            '{order_date}'   => '',
            '{order_number}' => '',
        );

        // Force enable this email
        $this->enabled = 'yes';

        // Call parent constructor
        parent::__construct();
        
        // Add a filter to ensure this email is always enabled
        add_filter('woocommerce_email_enabled_' . $this->id, function($enabled) {
            return 'yes'; // Always enable this email
        }, 999, 1);
        
        // Prevent default WooCommerce emails for Bocs subscription orders
        add_filter('woocommerce_email_enabled_new_order', array($this, 'maybe_disable_wc_email'), 999, 2);
        add_filter('woocommerce_email_enabled_customer_processing_order', array($this, 'maybe_disable_wc_email'), 999, 2);
        add_filter('woocommerce_email_enabled_customer_completed_order', array($this, 'maybe_disable_wc_email'), 999, 2);
        add_filter('woocommerce_email_enabled_customer_on_hold_order', array($this, 'maybe_disable_wc_email'), 999, 2);
        add_filter('woocommerce_email_enabled_customer_invoice', array($this, 'maybe_disable_wc_email'), 999, 2);
        add_filter('woocommerce_email_enabled_customer_note', array($this, 'maybe_disable_wc_email'), 999, 2);
        add_filter('woocommerce_email_enabled_customer_refunded_order', array($this, 'maybe_disable_wc_email'), 999, 2);
        add_filter('woocommerce_email_enabled_customer_new_account', array($this, 'maybe_disable_wc_email'), 999, 2);
        
        // Add a more aggressive filter to catch all emails
        add_filter('woocommerce_mail_callback', array($this, 'maybe_disable_all_wc_emails'), 10, 1);
        
        // Also hook earlier in the process to disable emails
        add_action('woocommerce_before_template_part', array($this, 'maybe_disable_email_template'), 10, 4);
    }

    /**
     * Get email subject.
     *
     * @since 1.0.0
     * @return string Default email subject
     */
    public function get_default_subject() {
        return __('[Bocs] Your New Subscription is Confirmed', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     *
     * @since 1.0.0
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('Your New Bocs Subscription', 'bocs-wordpress');
    }

    /**
     * Override the get_heading method to ensure our heading is used regardless of stored options
     * 
     * @return string The email heading
     */
    public function get_heading() {
        // Force our custom heading to avoid duplication with site title
        return $this->format_string($this->get_default_heading());
    }

    /**
     * Override get_subject to ensure our subject is properly formatted
     * 
     * @return string The email subject
     */
    public function get_subject() {
        $subject = $this->get_option('subject', $this->get_default_subject());
        return $this->format_string($subject);
    }

    /**
     * Trigger the sending of this email.
     *
     * @since 1.0.0
     * @param int $order_id The order ID.
     * @param WC_Order|bool $order Order object.
     * @return void
     */
    public function trigger($order_id, $order = false) {
        error_log('BOCS DEBUG [Existing Customer Email]: Starting trigger method for order ID: ' . (is_object($order_id) ? 'object' : $order_id));
        
        // Setup localization
        $this->setup_locale();
        
        // Handle case where order is passed as object instead of ID
        if (is_object($order_id) && is_a($order_id, 'WC_Order')) {
            $order = $order_id;
            $order_id = $order->get_id();
            error_log('BOCS DEBUG [Existing Customer Email]: Order ID is a WC_Order object, extracting ID: ' . $order_id);
        }
        
        // Get the order
        $order_obj = $order instanceof WC_Order ? $order : wc_get_order($order_id);
        
        // If we don't have a valid order, bail
        if (!$order_obj || !is_a($order_obj, 'WC_Order')) {
            error_log('BOCS DEBUG [Existing Customer Email]: Invalid order for order #' . $order_id);
            $this->restore_locale();
            return;
        }

        // Get order status
        $status = $order_obj->get_status();
        error_log('BOCS DEBUG [Existing Customer Email]: Order status: ' . $status);

        // Skip if order is deleted/trashed/cancelled
        if (in_array($status, array('trash', 'cancelled'))) {
            error_log('BOCS DEBUG [Existing Customer Email]: Order #' . $order_id . ' is ' . $status . ', skipping email');
            $this->restore_locale();
            return;
        }

        // Check if email was already sent
        $email_sent = $order_obj->get_meta('_bocs_existing_customer_subscription_email_sent');
        error_log('BOCS DEBUG [Existing Customer Email]: Email sent status: ' . ($email_sent ? $email_sent : 'not set'));
        
        if ($email_sent === 'yes') {
            error_log('BOCS DEBUG [Existing Customer Email]: Email already sent for order #' . $order_id);
            $this->restore_locale();
            return;
        }
        
        // Check if new customer email was already sent - don't send both
        $new_customer_email_sent = $order_obj->get_meta('_bocs_new_customer_subscription_email_sent');
        if ($new_customer_email_sent === 'yes') {
            error_log('BOCS DEBUG [Existing Customer Email]: New customer email already sent for order #' . $order_id . ', skipping existing customer email');
            // Mark as sent to avoid repeated checks
            $order_obj->update_meta_data('_bocs_existing_customer_subscription_email_sent', 'skipped');
            $order_obj->save_meta_data();
            $this->restore_locale();
            return;
        }
        
        // Set object and email recipient
        $this->object = $order_obj;
        $this->recipient = $order_obj->get_billing_email();
        
        error_log('BOCS DEBUG [Existing Customer Email]: Processing email for order #' . $order_id . ' with recipient: ' . $this->recipient);
        
        // Check for any Bocs ID - try multiple meta keys
        $bocs_id = $order_obj->get_meta('__bocs_id');
        if (empty($bocs_id)) {
            $bocs_id = $order_obj->get_meta('__bocs_subscription_id');
        }
        if (empty($bocs_id)) {
            $bocs_id = $order_obj->get_meta('__bocs_frequency_id');
        }
        
        error_log('BOCS DEBUG [Existing Customer Email]: Found Bocs ID: ' . $bocs_id);
        
        // If no Bocs ID, try to get it again after a short delay
        if (empty($bocs_id)) {
            error_log('BOCS DEBUG [Existing Customer Email]: No Bocs ID found, waiting 2 seconds and retrying...');
            sleep(2); // Wait 2 seconds
            
            // Retry getting Bocs IDs
            $bocs_id = $order_obj->get_meta('__bocs_id');
            if (empty($bocs_id)) {
                $bocs_id = $order_obj->get_meta('__bocs_subscription_id');
            }
            if (empty($bocs_id)) {
                $bocs_id = $order_obj->get_meta('__bocs_frequency_id');
            }
            
            if (empty($bocs_id)) {
                error_log('BOCS DEBUG [Existing Customer Email]: Still no Bocs ID found after retry, skipping email');
                $this->restore_locale();
                return;
            }
        }
        
        error_log('BOCS DEBUG [Existing Customer Email]: Using Bocs ID: ' . $bocs_id);
        $this->bocs_id = $bocs_id;

        // Check customer ID
        $customer_id = $order_obj->get_customer_id();
        if (!$customer_id) {
            error_log('BOCS DEBUG [Existing Customer Email]: No customer ID for order #' . $order_id . ', skipping');
            $this->restore_locale();
            return;
        }
        
        error_log('BOCS DEBUG [Existing Customer Email]: Customer ID: ' . $customer_id);
        
        // Check if this is an existing customer by looking at order count
        $order_count = wc_get_customer_order_count($customer_id);
        error_log('BOCS DEBUG [Existing Customer Email]: Customer order count: ' . $order_count);
        
        // For an existing customer subscription, we want to make sure they have more than 1 order
        if ($order_count <= 1) {
            error_log('BOCS DEBUG [Existing Customer Email]: This appears to be a new customer (order count: ' . $order_count . '), skipping existing customer email');
            $this->restore_locale();
            return;
        }
        
        // Check UTM source
        $utm_source = $order_obj->get_meta('_wc_order_attribution_utm_source');
        error_log('BOCS DEBUG [Existing Customer Email]: UTM source: ' . ($utm_source ? $utm_source : 'not set'));
        
        // Continue with more specific customer eligibility check
        if (!$this->is_customer_eligible_for_email($order_obj)) {
            error_log('BOCS DEBUG [Existing Customer Email]: Customer not eligible for email, skipping');
            $this->restore_locale();
            return;
        }
        
        // Replace placeholders in the email content before sending
        $this->placeholders['{order_date}'] = wc_format_datetime($order_obj->get_date_created());
        $this->placeholders['{order_number}'] = $order_obj->get_order_number();
        
        // Send the email
        error_log('BOCS DEBUG [Existing Customer Email]: Preparing to send email for order #' . $order_id);
        
        if ($this->is_enabled() && $this->get_recipient()) {
            error_log('BOCS DEBUG [Existing Customer Email]: Email is enabled and recipient is set');
            
            try {
                // Send the email
                $result = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
                
                if ($result) {
                    // Mark the email as sent to prevent duplicate sends
                    $order_obj->update_meta_data('_bocs_existing_customer_subscription_email_sent', 'yes');
                    $order_obj->save_meta_data();
                    
                    error_log('BOCS DEBUG [Existing Customer Email]: Email sent successfully for order #' . $order_id);
                } else {
                    error_log('BOCS DEBUG [Existing Customer Email]: Failed to send email for order #' . $order_id);
                }
            } catch (Exception $e) {
                error_log('BOCS DEBUG [Existing Customer Email]: Exception when sending email: ' . $e->getMessage());
            }
        } else {
            $reason = !$this->is_enabled() ? 'email is disabled' : 'no recipient';
            error_log('BOCS DEBUG [Existing Customer Email]: Email not sent because ' . $reason);
            if (!$this->is_enabled()) {
                error_log('BOCS DEBUG [Existing Customer Email]: Email enabled setting: ' . $this->enabled);
            }
            if (!$this->get_recipient()) {
                error_log('BOCS DEBUG [Existing Customer Email]: Recipient: ' . $this->recipient);
            }
        }
        
        // Restore locale
        $this->restore_locale();
    }

    /**
     * Get content html.
     *
     * @since 1.0.0
     * @return string Email HTML content
     */
    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            array(
                'order'              => $this->object,
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => false,
                'plain_text'         => false,
                'email'              => $this,
            ),
            '',
            $this->template_base
        );
    }

    /**
     * Get content plain.
     *
     * @since 1.0.0
     * @return string Email plain text content
     */
    public function get_content_plain() {
        return wc_get_template_html(
            $this->template_plain,
            array(
                'order'              => $this->object,
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => false,
                'plain_text'         => true,
                'email'              => $this,
            ),
            '',
            $this->template_base
        );
    }

    /**
     * Default content to show below main email content.
     *
     * @since 1.0.0
     * @return string Default additional content
     */
    public function get_default_additional_content() {
        return __('Thank you for your continued trust in Bocs! If you have any questions about your new subscription, our customer support team is always here to help.', 'bocs-wordpress');
    }

    /**
     * Maybe disable WooCommerce emails for Bocs subscription orders
     *
     * @param bool $enabled Whether the email is enabled.
     * @param object $order The order object if available.
     * @return bool
     */
    public function maybe_disable_wc_email($enabled, $order = null) {
        // Only proceed if we have an order
        if (!$order || !is_a($order, 'WC_Order')) {
            return $enabled;
        }
        
        // Check if this is a Bocs subscription order by checking all possible Bocs IDs
        $bocs_id = $order->get_meta('__bocs_id');
        $subscription_id = $order->get_meta('__bocs_subscription_id');
        $frequency_id = $order->get_meta('__bocs_frequency_id');
        
        // If this is a Bocs subscription order, disable the default email
        if (!empty($bocs_id) || !empty($subscription_id) || !empty($frequency_id)) {
            // Check if we've already sent our custom email for this order
            $order_id = $order->get_id();
            $already_sent = $order->get_meta('_bocs_existing_customer_subscription_email_sent');
            
            // If our email has been sent or will be sent, disable the default email
            if ($already_sent === 'yes') {
                return false;
            }
            
            // Check if customer is eligible for our email
            if ($this->is_customer_eligible_for_email($order)) {
                return false;
            }
        }
        
        return $enabled;
    }
    
    /**
     * Check if customer is eligible for the existing customer email
     *
     * @param WC_Order $order The order object
     * @return bool
     */
    private function is_customer_eligible_for_email($order) {
        error_log('BOCS DEBUG [Existing Customer Email]: Checking customer eligibility');
        
        if (!$order || !is_a($order, 'WC_Order')) {
            error_log('BOCS DEBUG [Existing Customer Email]: Invalid order object');
            return false;
        }
        
        $customer_id = $order->get_customer_id();
        if (!$customer_id) {
            error_log('BOCS DEBUG [Existing Customer Email]: No customer ID found');
            return false;
        }
        
        error_log('BOCS DEBUG [Existing Customer Email]: Checking previous orders for customer ID: ' . $customer_id);
        
        // Get all customer's previous completed or processing orders
        $previous_orders = wc_get_orders(array(
            'customer_id' => $customer_id,
            'status' => array('wc-completed', 'wc-processing'),
            'limit' => -1,
            'return' => 'ids',
        ));
        
        error_log('BOCS DEBUG [Existing Customer Email]: Found ' . count($previous_orders) . ' previous orders');
        
        // Exclude current order
        $current_order_id = $order->get_id();
        error_log('BOCS DEBUG [Existing Customer Email]: Current order ID: ' . $current_order_id);
        $previous_orders = array_diff($previous_orders, array($current_order_id));
        
        error_log('BOCS DEBUG [Existing Customer Email]: After filtering current order, ' . count($previous_orders) . ' orders remain');
        
        // For existing customer subscription email, check if they've had previous Bocs orders
        $previous_bocs_orders = 0;
        
        foreach ($previous_orders as $prev_order_id) {
            $prev_order = wc_get_order($prev_order_id);
            if (!$prev_order) {
                error_log('BOCS DEBUG [Existing Customer Email]: Could not get order #' . $prev_order_id);
                continue;
            }
            
            // Check if order has any Bocs meta
            $has_bocs_id = $prev_order->get_meta('__bocs_id');
            $has_subscription_id = $prev_order->get_meta('__bocs_subscription_id');
            $has_frequency_id = $prev_order->get_meta('__bocs_frequency_id');
            
            if ($has_bocs_id || $has_subscription_id || $has_frequency_id) {
                $previous_bocs_orders++;
                error_log('BOCS DEBUG [Existing Customer Email]: Found previous Bocs order #' . $prev_order_id);
            }
        }
        
        error_log('BOCS DEBUG [Existing Customer Email]: Found ' . $previous_bocs_orders . ' previous Bocs orders');
        
        // For existing customer email, eligibility requires:
        // 1. Customer must have at least one previous Bocs order
        // 2. Current order must have a Bocs ID
        $has_current_bocs_id = $order->get_meta('__bocs_id') || 
                              $order->get_meta('__bocs_subscription_id') || 
                              $order->get_meta('__bocs_frequency_id');
        
        $is_eligible = $previous_bocs_orders > 0 && $has_current_bocs_id;
        
        error_log('BOCS DEBUG [Existing Customer Email]: Customer is ' . 
                 ($is_eligible ? 'eligible' : 'not eligible') . 
                 ' for existing customer email (previous Bocs orders: ' . $previous_bocs_orders . 
                 ', has current Bocs ID: ' . ($has_current_bocs_id ? 'yes' : 'no') . ')');
        
        return $is_eligible;
    }

    /**
     * Maybe disable all WooCommerce emails for Bocs subscription orders
     *
     * @param callable $callback The original email callback
     * @return callable|bool
     */
    public function maybe_disable_all_wc_emails($callback) {
        global $post;
        
        // Try to get the current order
        $order = null;
        
        // Check if we're in an email about a specific order
        if (isset($_REQUEST['order_id'])) {
            $order = wc_get_order($_REQUEST['order_id']);
        } elseif (is_object($post) && isset($post->ID) && get_post_type($post->ID) === 'shop_order') {
            $order = wc_get_order($post->ID);
        }
        
        // If we have an order, check if it's a Bocs subscription
        if ($order && is_a($order, 'WC_Order')) {
            // Check if this is a Bocs subscription order by checking all possible Bocs IDs
            $bocs_id = $order->get_meta('__bocs_id');
            $subscription_id = $order->get_meta('__bocs_subscription_id');
            $frequency_id = $order->get_meta('__bocs_frequency_id');
            
            // If this is a Bocs subscription order, check if our email is relevant
            if (!empty($bocs_id) || !empty($subscription_id) || !empty($frequency_id)) {
                // Check if this is a customer's subsequent order (not their first)
                if ($this->is_customer_eligible_for_email($order)) {
                    error_log('BOCS DEBUG [Existing Customer Email Filter]: Disabling all WooCommerce emails for order #' . $order->get_id());
                    return false; // Disable the email entirely
                }
            }
        }
        
        return $callback;
    }

    /**
     * Maybe disable WooCommerce email templates for Bocs subscription orders
     *
     * @param string $template_name Template name
     * @param string $template_path Template path
     * @param string $located Located template path
     * @param array $args Arguments
     */
    public function maybe_disable_email_template($template_name, $template_path, $located, $args) {
        // Check if this is an email template
        if (strpos($template_name, 'emails/') !== false) {
            // Check if we have access to the order
            if (isset($args['order']) && is_a($args['order'], 'WC_Order')) {
                $order = $args['order'];
                
                // Check if this is a Bocs subscription order by checking all possible IDs
                $bocs_id = $order->get_meta('__bocs_id');
                $subscription_id = $order->get_meta('__bocs_subscription_id');
                $frequency_id = $order->get_meta('__bocs_frequency_id');
                
                // If this is a Bocs subscription order and customer is eligible, prevent the template from loading
                if ((!empty($bocs_id) || !empty($subscription_id) || !empty($frequency_id)) && 
                    $this->is_customer_eligible_for_email($order)) {
                    error_log('BOCS DEBUG [Existing Customer Email Filter]: Disabling email template: ' . $template_name);
                    
                    // For admin notifications, use a filter to prevent rendering
                    if (strpos($template_name, 'emails/admin-new-order.php') !== false || 
                        strpos($template_name, 'emails/admin-') !== false) {
                        // Add a filter that will return an empty string for this template
                        add_filter('wc_get_template', function($template, $template_name_filter) use ($template_name) {
                            if ($template_name_filter === $template_name) {
                                return plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/emails/empty-template.php';
                            }
                            return $template;
                        }, 90, 2);
                    }
                }
            }
        }
    }

    /**
     * Log debug message
     *
     * @param string $message Message to log
     * @return void
     */
    private function log_debug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BOCS EMAIL DEBUG [Existing Customer]: ' . $message);
        }
    }

    /**
     * Log error message
     *
     * @param string $message Message to log
     * @return void
     */
    private function log_error($message) {
        error_log('BOCS EMAIL ERROR [Existing Customer]: ' . $message);
    }

    /**
     * Initialise settings form fields.
     *
     * @since 1.0.0
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled'            => array(
                'title'   => __('Enable/Disable', 'bocs-wordpress'),
                'type'    => 'checkbox',
                'label'   => __('Enable this email notification', 'bocs-wordpress'),
                'default' => 'yes',
            ),
            'subject'            => array(
                'title'       => __('Subject', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('This controls the email subject line. Leave blank to use the default subject: <code>[Bocs] Your New Subscription is Confirmed</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'            => array(
                'title'       => __('Email Heading', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>Your New Bocs Subscription</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
            ),
            'additional_content' => array(
                'title'       => __('Additional content', 'bocs-wordpress'),
                'description' => __('Text to appear below the main email content.', 'bocs-wordpress'),
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __('Thank you for your continued trust in Bocs! If you have any questions about your new subscription, our customer support team is always here to help.', 'bocs-wordpress'),
                'type'        => 'textarea',
                'default'     => $this->get_default_additional_content(),
                'desc_tip'    => true,
            ),
            'email_type'         => array(
                'title'       => __('Email type', 'bocs-wordpress'),
                'type'        => 'select',
                'description' => __('Choose which format of email to send.', 'bocs-wordpress'),
                'default'     => 'html',
                'class'       => 'email_type wc-enhanced-select',
                'options'     => $this->get_email_type_options(),
                'desc_tip'    => true,
            ),
        );
    }
} 