<?php
/**
 * Class WC_Bocs_Email_New_Customer_Subscription
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
 * New Customer Subscription Confirmation Email
 *
 * An email sent to customers when they set up their first subscription through Bocs.
 * This handles the email notification sent to new customers after their Bocs subscription is created.
 *
 * @class       WC_Bocs_Email_New_Customer_Subscription
 * @version     0.0.118
 * @package     Bocs\Emails
 * @extends     WC_Email
 */
class WC_Bocs_Email_New_Customer_Subscription extends WC_Email {

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
        $this->id             = 'bocs_new_customer_subscription';
        $this->customer_email = true;
        $this->title          = __('[Bocs Customer] Welcome New Customer New Subscription', 'bocs-wordpress');
        $this->description    = __('Welcome email sent to new customers after setting up their first Bocs subscription.', 'bocs-wordpress');
        $this->template_html  = 'emails/bocs-new-customer-subscription.php';
        $this->template_plain = 'emails/plain/bocs-new-customer-subscription.php';
        
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
        
        // Do not set a default recipient - we'll set it in the trigger method based on the order
        
        // Add a filter to ensure this email is always enabled
        add_filter('woocommerce_email_enabled_' . $this->id, function($enabled) {
            return 'yes'; // Always enable this email
        }, 999, 1);
        
        // Prevent default WooCommerce emails for Bocs subscription orders
        add_filter('woocommerce_email_enabled_new_order', array($this, 'maybe_disable_wc_email'), 10, 2);
        add_filter('woocommerce_email_enabled_customer_processing_order', array($this, 'maybe_disable_wc_email'), 10, 2);
        add_filter('woocommerce_email_enabled_customer_completed_order', array($this, 'maybe_disable_wc_email'), 10, 2);
        add_filter('woocommerce_email_enabled_customer_new_account', array($this, 'maybe_disable_wc_email'), 10, 2);
        add_filter('woocommerce_email_enabled_customer_on_hold_order', array($this, 'maybe_disable_wc_email'), 10, 2);
        add_filter('woocommerce_email_enabled_customer_invoice', array($this, 'maybe_disable_wc_email'), 10, 2);
        add_filter('woocommerce_email_enabled_customer_note', array($this, 'maybe_disable_wc_email'), 10, 2);
        add_filter('woocommerce_email_enabled_customer_refunded_order', array($this, 'maybe_disable_wc_email'), 10, 2);
        
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
        return __('[Bocs] Welcome to Bocs! Your subscription is confirmed', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     *
     * @since 1.0.0
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('Welcome to Bocs!', 'bocs-wordpress');
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
        // Setup localization
        $this->setup_locale();
        
        // Handle case where order is passed as object instead of ID
        if (is_object($order_id) && is_a($order_id, 'WC_Order')) {
            $order = $order_id;
            $order_id = $order->get_id();
        }
        
        // Get the order
        $order_obj = $order instanceof WC_Order ? $order : wc_get_order($order_id);
        
        // If we don't have a valid order, bail
        if (!$order_obj || !is_a($order_obj, 'WC_Order')) {
            $this->log_error("Invalid order for ID: {$order_id}");
            $this->restore_locale();
            return;
        }
        
        // Set object and email recipient
        $this->object = $order_obj;
        $this->recipient = $order_obj->get_billing_email();
        
        // Debug log
        $this->log_debug("Processing email for order #{$order_id} with recipient: {$this->recipient}");
        
        // Check if this is a Bocs subscription order
        $bocs_subscription_id = $order_obj->get_meta('__bocs_subscription_id');
        
        // If no Bocs subscription ID, bail
        if (empty($bocs_subscription_id)) {
            $this->log_debug("No __bocs_subscription_id found for order #{$order_id}, skipping email.");
            $this->restore_locale();
            return;
        }
        
        // Set the Bocs ID
        $this->bocs_id = $bocs_subscription_id;
        $this->log_debug("Using Bocs subscription ID: {$this->bocs_id} for order #{$order_id}");
        
        // Set the placeholders for email template
        $this->placeholders['{order_date}'] = wc_format_datetime($this->object->get_date_created());
        $this->placeholders['{order_number}'] = $this->object->get_order_number();
        
        // Check if we've already sent this email 
        $already_sent = get_post_meta($order_id, '_bocs_new_customer_subscription_email_sent', true);
        if ($already_sent === 'yes') {
            $this->log_debug("Email already sent for order #{$order_id}, skipping duplicate.");
            $this->restore_locale();
            return;
        }
        
        // Check if existing customer email was already sent - don't send both
        $existing_customer_email_sent = $order_obj->get_meta('_bocs_existing_customer_subscription_email_sent');
        if ($existing_customer_email_sent === 'yes') {
            $this->log_debug("Existing customer email already sent for order #{$order_id}, skipping new customer email");
            // Mark as sent to avoid repeated checks
            update_post_meta($order_id, '_bocs_new_customer_subscription_email_sent', 'skipped');
            $this->restore_locale();
            return;
        }
        
        // Make sure customer meets eligibility criteria
        if (!$this->is_customer_eligible_for_email($order_obj)) {
            $this->log_debug("Customer not eligible for new customer email for order #{$order_id}");
            $this->restore_locale();
            return;
        }
        
        // Always send for Bocs subscription orders with valid email
        if ($this->is_enabled() && $this->get_recipient()) {
            try {
                // Send the email with a generous timeout
                add_filter('wp_mail_timeout', function() { return 15; }); // 15 second timeout
                
                // Send the email
                $sent = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
                
                // Debug log
                $this->log_debug("Email send attempted for order #{$order_id}: " . ($sent ? 'SUCCESS' : 'FAILED'));
                
                // Mark as sent to prevent duplicates
                if ($sent) {
                    update_post_meta($order_id, '_bocs_new_customer_subscription_email_sent', 'yes');
                } else {
                    $this->log_error("Failed to send email for order #{$order_id}");
                }
            } catch (Exception $e) {
                $this->log_error("Exception when sending email for order #{$order_id}: " . $e->getMessage());
            }
        } else {
            $this->log_debug("Email not sent - email disabled or no recipient for order #{$order_id}");
        }
        
        // Restore localization
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
        return __('We\'re excited to have you as a Bocs customer! If you have any questions or need assistance with your new subscription, our customer support team is always here to help.', 'bocs-wordpress');
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
                'description' => __('This controls the email subject line. Leave blank to use the default subject: <code>[Bocs] Welcome to Bocs! Your subscription is confirmed</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'            => array(
                'title'       => __('Email Heading', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>Welcome to Bocs!</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
            ),
            'additional_content' => array(
                'title'       => __('Additional content', 'bocs-wordpress'),
                'description' => __('Text to appear below the main email content.', 'bocs-wordpress'),
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __('We\'re excited to have you as a Bocs customer! If you have any questions or need assistance with your new subscription, our customer support team is always here to help.', 'bocs-wordpress'),
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
        
        // Check if this is a Bocs subscription order
        $bocs_subscription_id = $order->get_meta('__bocs_subscription_id');
        
        // If this is a Bocs subscription order, disable the default email
        if (!empty($bocs_subscription_id)) {
            // Check if we've already sent our custom email for this order
            $order_id = $order->get_id();
            $already_sent = get_post_meta($order_id, '_bocs_new_customer_subscription_email_sent', true);
            
            // If our email has been sent or will be sent, disable the default email
            if ($already_sent === 'yes' || $this->is_customer_eligible_for_email($order)) {
                return false;
            }
        }
        
        return $enabled;
    }

    /**
     * Check if customer is eligible for the welcome email
     *
     * @param WC_Order $order The order object
     * @return bool
     */
    private function is_customer_eligible_for_email($order) {
        // Check if this is the customer's first Bocs order
        $customer_id = $order->get_customer_id();
        $is_new_customer = true;
        
        if ($customer_id > 0) {
            // Get customer's previous orders with Bocs products
            $previous_orders = wc_get_orders(array(
                'customer_id' => $customer_id,
                'status' => array('wc-completed', 'wc-processing'),
                'limit' => -1,
                'return' => 'ids',
            ));
            
            // Exclude current order
            $previous_orders = array_diff($previous_orders, array($order->get_id()));
            
            // If customer has previous orders, check if any of them had Bocs products
            if (!empty($previous_orders)) {
                $had_bocs_products = false;
                
                foreach ($previous_orders as $prev_order_id) {
                    $prev_order = wc_get_order($prev_order_id);
                    if (!$prev_order) continue;
                    
                    // Check if order has any Bocs meta
                    $has_bocs_id = $prev_order->get_meta('__bocs_id');
                    $has_subscription_id = $prev_order->get_meta('__bocs_subscription_id');
                    $has_frequency_id = $prev_order->get_meta('__bocs_frequency_id');
                    
                    if ($has_bocs_id || $has_subscription_id || $has_frequency_id) {
                        $had_bocs_products = true;
                        break;
                    }
                }
                
                $is_new_customer = !$had_bocs_products;
            }
        }
        
        // Check if current order has a Bocs ID
        $has_current_bocs_id = $order->get_meta('__bocs_id') || 
                              $order->get_meta('__bocs_subscription_id') || 
                              $order->get_meta('__bocs_frequency_id');
        
        // Only eligible if this is a new customer with a Bocs ID in current order
        return $is_new_customer && $has_current_bocs_id;
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
            // Check if this is a Bocs subscription order
            $bocs_subscription_id = $order->get_meta('__bocs_subscription_id');
            
            // If this is a Bocs subscription order, disable the default email
            if (!empty($bocs_subscription_id)) {
                return false; // Disable the email entirely
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
                
                // Check if this is a Bocs subscription order
                $bocs_subscription_id = $order->get_meta('__bocs_subscription_id');
                
                // If this is a Bocs subscription order, prevent the template from loading
                if (!empty($bocs_subscription_id)) {
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
            error_log('BOCS EMAIL DEBUG: ' . $message);
        }
    }

    /**
     * Log error message
     *
     * @param string $message Message to log
     * @return void
     */
    private function log_error($message) {
        error_log('BOCS EMAIL ERROR: ' . $message);
    }
} 