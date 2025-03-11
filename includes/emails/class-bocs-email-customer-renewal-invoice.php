<?php
/**
 * Bocs Customer Renewal Invoice Email Class
 *
 * Handles email notifications for subscription renewal invoices.
 *
 * @package    Bocs
 * @subpackage Bocs/includes/emails
 * @since      0.0.118
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Check if WooCommerce is active
if (!function_exists('WC')) {
    return;
}

// Load WooCommerce email classes if not already loaded
if (!class_exists('WC_Email', false)) {
    include_once WC_ABSPATH . 'includes/emails/class-wc-email.php';
}

// Check if parent invoice class exists or try to load it
if (!class_exists('WC_Email_Customer_Invoice', false)) {
    $parent_class_file = WC_ABSPATH . 'includes/emails/class-wc-email-customer-invoice.php';
    if (file_exists($parent_class_file)) {
        include_once $parent_class_file;
    }
    
    // If parent class still doesn't exist after attempting to load, log error and return
    if (!class_exists('WC_Email_Customer_Invoice', false)) {
        error_log('Bocs: WC_Email_Customer_Invoice class not found. Cannot initialize WC_Bocs_Email_Customer_Renewal_Invoice.');
        return;
    }
}

if (!class_exists('WC_Bocs_Email_Customer_Renewal_Invoice')) :

/**
 * Class WC_Bocs_Email_Customer_Renewal_Invoice
 *
 * @package     Bocs\Emails
 * @version     0.0.118
 * @since       0.0.118
 * @author      Bocs
 * @category    Emails
 */
class WC_Bocs_Email_Customer_Renewal_Invoice extends WC_Email_Customer_Invoice {

    /**
     * Constructor
     *
     * Initializes email parameters and settings.
     * Sets up the email's ID, title, description, and template paths.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->id             = 'bocs_customer_renewal_invoice';
        $this->title          = __('[Bocs] Subscription Renewal Invoice', 'bocs-wordpress');
        $this->description    = __('Renewal invoice emails are sent to customers when a renewal has been created and needs payment.', 'bocs-wordpress');
        $this->customer_email = true;
        $this->template_html  = 'emails/bocs-customer-renewal-invoice.php';
        $this->template_plain = 'emails/plain/bocs-customer-renewal-invoice.php';
        $this->template_base  = BOCS_TEMPLATE_PATH;
        $this->placeholders   = array(
            '{order_date}'   => '',
            '{order_number}' => '',
        );

        // Call parent constructor
        parent::__construct();
    }

    /**
     * Get email subject.
     * 
     * Defines the default subject line for the customer renewal invoice email.
     *
     * @since 1.0.0
     * @param bool $paid Whether the order has been paid or not
     * @return string Default email subject
     */
    public function get_default_subject($paid = false) {
        return __('[Bocs] Subscription Renewal Invoice for Order {order_number}', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     * 
     * Defines the default heading for the customer renewal invoice email.
     *
     * @since 1.0.0
     * @param bool $paid Whether the order has been paid or not
     * @return string Default email heading
     */
    public function get_default_heading($paid = false) {
        return __('Subscription Renewal Invoice', 'bocs-wordpress');
    }

    /**
     * Get email subject.
     *
     * @return string
     */
    public function get_subject() {
        if ($this->object->has_status(array('completed', 'processing'))) {
            $subject = $this->get_option('subject_paid', $this->get_default_subject(true));
            return apply_filters('woocommerce_email_subject_customer_renewal_invoice_paid', $this->format_string($subject), $this->object, $this);
        }

        $subject = $this->get_option('subject', $this->get_default_subject());
        return apply_filters('woocommerce_email_subject_customer_renewal_invoice', $this->format_string($subject), $this->object, $this);
    }

    /**
     * Get email heading.
     *
     * @return string
     */
    public function get_heading() {
        if ($this->object->has_status(wc_get_is_paid_statuses())) {
            $heading = $this->get_option('heading_paid', $this->get_default_heading(true));
            return apply_filters('woocommerce_email_heading_customer_renewal_invoice_paid', $this->format_string($heading), $this->object, $this);
        }

        $heading = $this->get_option('heading', $this->get_default_heading());
        return apply_filters('woocommerce_email_heading_customer_renewal_invoice', $this->format_string($heading), $this->object, $this);
    }

    /**
     * @since 1.0.0
     * @param int       $order_id The order ID.
     * @param WC_Order  $order Optional. The order object.
     * @return void
     */
    public function trigger($order_id, $order = false) {
        $this->setup_locale();

        if ($order_id && !is_a($order, 'WC_Order')) {
            $order = wc_get_order($order_id);
        }

        if (is_a($order, 'WC_Order')) {
            $this->object = $order;
            
            // Check parent subscription or order for Bocs attribution
            $parent_id = get_post_meta($order_id, '_subscription_renewal', true);
            if (empty($parent_id)) {
                $parent_id = $order_id;
            }
            
            // Check if the order has the required meta data
            $source_type = get_post_meta($parent_id, '_wc_order_attribution_source_type', true);
            $utm_source = get_post_meta($parent_id, '_wc_order_attribution_utm_source', true);
            
            // Only proceed if this is a Bocs order
            if ($source_type === 'referral' && $utm_source === 'Bocs App') {
                $this->recipient                      = $this->object->get_billing_email();
                $this->placeholders['{order_date}']   = wc_format_datetime($this->object->get_date_created());
                $this->placeholders['{order_number}'] = $this->object->get_order_number();
            }
        }

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }

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
        return __('Thanks for using Bocs. We hope you enjoy your subscription!', 'bocs-wordpress');
    }

    /**
     * Initialise settings form fields.
     *
     * @since 1.0.0
     */
    public function init_form_fields() {
        $placeholder_text = sprintf(__('Available placeholders: %s', 'bocs-wordpress'), '<code>' . esc_html(implode('</code>, <code>', array_keys($this->placeholders))) . '</code>');
        
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
                'description' => $placeholder_text,
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'            => array(
                'title'       => __('Email Heading', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
            ),
            'subject_paid'       => array(
                'title'       => __('Subject (paid)', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'placeholder' => $this->get_default_subject(true),
                'default'     => '',
            ),
            'heading_paid'       => array(
                'title'       => __('Email Heading (paid)', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'placeholder' => $this->get_default_heading(true),
                'default'     => '',
            ),
            'additional_content' => array(
                'title'       => __('Additional content', 'bocs-wordpress'),
                'description' => __('Text to appear below the main email content.', 'bocs-wordpress') . ' ' . $placeholder_text,
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __('Thanks for using Bocs. We hope you enjoy your subscription!', 'bocs-wordpress'),
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

endif;

return new WC_Bocs_Email_Customer_Renewal_Invoice();
