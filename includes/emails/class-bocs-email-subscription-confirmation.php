<?php
/**
 * Class WC_Bocs_Email_Subscription_Confirmation
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
 * Subscription Order Confirmation Email
 *
 * An email sent to customers when they set up a subscription through Bocs.
 * This handles the email notification sent to the customer after their Bocs subscription is created.
 *
 * @class       WC_Bocs_Email_Subscription_Confirmation
 * @version     0.0.118
 * @package     Bocs\Emails
 * @extends     WC_Email
 */
class WC_Bocs_Email_Subscription_Confirmation extends WC_Email {

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
        $this->id             = 'bocs_subscription_confirmation';
        $this->customer_email = true;
        $this->title          = __('[Bocs Customer] Existing Customer New Subscription', 'bocs-wordpress');
        $this->description    = __('Confirmation email sent to existing customers after adding a new Bocs subscription.', 'bocs-wordpress');
        $this->template_html  = 'emails/bocs-subscription-order-confirmation.php';
        $this->template_plain = 'emails/plain/bocs-subscription-order-confirmation.php';
        
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
    }

    /**
     * Get email subject.
     *
     * @since 1.0.0
     * @return string Default email subject
     */
    public function get_default_subject() {
        return __('[Bocs] Thank you for your new subscription', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     *
     * @since 1.0.0
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('Your New Subscription is Confirmed', 'bocs-wordpress');
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
        
        // Get the order
        $order_obj = $order instanceof WC_Order ? $order : wc_get_order($order_id);
        
        // If we don't have a valid order, bail
        if (!$order_obj || !is_a($order_obj, 'WC_Order')) {
            $this->restore_locale();
            return;
        }
        
        // Set object and email recipient
        $this->object = $order_obj;
        $this->recipient = $order_obj->get_billing_email();
        
        // Skip if we've already sent this email for this order (check meta)
        $already_sent = get_post_meta($order_id, '_bocs_subscription_confirmation_email_sent', true);
        if ($already_sent === 'yes') {
            $this->restore_locale();
            return;
        }
        
        // Check if this is the customer's first Bocs order - if so, skip as they'll get the welcome email instead
        $customer_id = $order_obj->get_customer_id();
        $is_new_customer = true; // Assume new customer by default
        
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
            
            // If customer has previous orders, check if any of them had Bocs products
            if (!empty($previous_orders)) {
                $had_bocs_products = false;
                
                foreach ($previous_orders as $prev_order_id) {
                    $prev_order = wc_get_order($prev_order_id);
                    if (!$prev_order) continue;
                    
                    // Check if order has Bocs meta
                    if ($prev_order->get_meta('__bocs_subscription_id')) {
                        $had_bocs_products = true;
                        break;
                    }
                }
                
                $is_new_customer = !$had_bocs_products;
            }
        }
        
        // Skip if this is a new customer (they'll get the welcome email instead)
        if ($is_new_customer) {
            $this->restore_locale();
            return;
        }
        
        // Set the placeholders for email template
        $this->placeholders['{order_date}'] = wc_format_datetime($this->object->get_date_created());
        $this->placeholders['{order_number}'] = $this->object->get_order_number();
        
        // Set the Bocs ID (if available)
        $this->bocs_id = $this->object->get_meta('__bocs_subscription_id');
        
        // Send the email if enabled
        if ($this->is_enabled() && $this->get_recipient()) {
            // Only send if we have a valid Bocs ID
            if (empty($this->bocs_id)) {
                $this->restore_locale();
                return;
            }
            
            // Actually send the email
            $sent = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
            
            // Mark as sent to prevent duplicates
            if ($sent) {
                update_post_meta($order_id, '_bocs_subscription_confirmation_email_sent', 'yes');
            }
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
        return __('Thank you for expanding your Bocs experience with this new subscription. We value your continued trust and look forward to serving you!', 'bocs-wordpress');
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
                'description' => __('This controls the email subject line. Leave blank to use the default subject: <code>[Bocs] Thank you for your new subscription</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'            => array(
                'title'       => __('Email Heading', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>Your New Subscription is Confirmed</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
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