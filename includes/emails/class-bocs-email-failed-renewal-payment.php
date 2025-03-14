<?php
/**
 * Class WC_Bocs_Email_Failed_Renewal_Payment
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

// Check if WooCommerce is active
if (!function_exists('WC')) {
    return;
}

// Load WooCommerce email classes if not already loaded
if (!class_exists('WC_Email', false)) {
    include_once WC_ABSPATH . 'includes/emails/class-wc-email.php';
    
    // If parent class still doesn't exist after attempting to load, log error and return
    if (!class_exists('WC_Email', false)) {
        error_log('Bocs: WC_Email class not found. Cannot initialize WC_Bocs_Email_Failed_Renewal_Payment.');
        return;
    }
}

if (!class_exists('WC_Bocs_Email_Failed_Renewal_Payment')) :

/**
 * Failed Renewal Payment Email
 *
 * An email sent to the customer when a renewal payment fails.
 * This notification informs customers about failed subscription renewal payments
 * and provides instructions for updating their payment method to maintain
 * uninterrupted service.
 *
 * @class       WC_Bocs_Email_Failed_Renewal_Payment
 * @version     0.0.118
 * @package     Bocs\Emails
 * @extends     WC_Email
 */
class WC_Bocs_Email_Failed_Renewal_Payment extends WC_Email {

    /**
     * Constructor
     *
     * Initializes email parameters and settings.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->id             = 'bocs_failed_renewal_payment';
        $this->customer_email = true;
        $this->title          = __('[Bocs] Failed Renewal Payment', 'bocs-wordpress');
        $this->description    = __('Failed renewal payment emails are sent to customers when automatic subscription renewal payments fail.', 'bocs-wordpress');
        $this->template_html  = 'emails/bocs-customer-failed-renewal-payment.php';
        $this->template_plain = 'emails/plain/bocs-customer-failed-renewal-payment.php';
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
     * @since 1.0.0
     * @return string Default email subject
     */
    public function get_default_subject() {
        return __('[Bocs] Renewal payment failed for order {order_number}', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     *
     * @since 1.0.0
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('Renewal Payment Failed', 'bocs-wordpress');
    }

    /**
     * Trigger the sending of this email.
     *
     * @since 1.0.0
     * @param int $order_id The order ID.
     * @return void
     */
    public function trigger($order_id) {
        $this->setup_locale();

        if ($order_id) {
            $this->object = wc_get_order($order_id);
            if (is_a($this->object, 'WC_Order')) {
                // Check if this order has the required meta data
                $source_type = get_post_meta($order_id, '_wc_order_attribution_source_type', true);
                $utm_source = get_post_meta($order_id, '_wc_order_attribution_utm_source', true);
                
                // Only proceed if the order meets Bocs requirements
                if ($source_type === 'referral' && $utm_source === 'Bocs App') {
                    $this->recipient = $this->object->get_billing_email();

                    $this->placeholders['{order_date}']   = wc_format_datetime($this->object->get_date_created());
                    $this->placeholders['{order_number}'] = $this->object->get_order_number();
                }
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
        return __('Please update your payment method to ensure uninterrupted service for your subscription.', 'bocs-wordpress');
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
                'description' => __('This controls the email subject line. Leave blank to use the default subject: <code>[Bocs] Renewal payment failed for order {order_number}</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'            => array(
                'title'       => __('Email Heading', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>Renewal Payment Failed</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
            ),
            'additional_content' => array(
                'title'       => __('Additional content', 'bocs-wordpress'),
                'description' => __('Text to appear below the main email content.', 'bocs-wordpress'),
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __('Please update your payment method to ensure uninterrupted service for your subscription.', 'bocs-wordpress'),
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