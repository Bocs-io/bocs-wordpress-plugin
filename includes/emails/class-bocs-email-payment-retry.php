<?php
/**
 * Class WC_Bocs_Email_Payment_Retry
 *
 * @package Bocs\Emails
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Payment Retry Email
 *
 * An email sent to the customer when a failed payment is about to be retried.
 *
 * @class       WC_Bocs_Email_Payment_Retry
 * @version     0.0.118
 * @extends     WC_Email
 */
class WC_Bocs_Email_Payment_Retry extends WC_Email {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id             = 'bocs_payment_retry';
        $this->customer_email = true;
        $this->title          = __('[Bocs] Payment Retry', 'bocs-wordpress');
        $this->description    = __('Payment retry emails are sent when a previously failed payment is about to be retried.', 'bocs-wordpress');
        $this->template_html  = 'emails/customer-payment-retry.php';
        $this->template_plain = 'emails/plain/customer-payment-retry.php';
        $this->template_base  = BOCS_TEMPLATE_PATH;
        $this->placeholders   = array(
            '{order_date}'   => '',
            '{order_number}' => '',
            '{retry_date}'   => '',
        );

        // Call parent constructor
        parent::__construct();
    }

    /**
     * Get email subject.
     *
     * @return string
     */
    public function get_default_subject() {
        return __('[Bocs] Payment retry scheduled for your {site_title} subscription', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     *
     * @return string
     */
    public function get_default_heading() {
        return __('Payment Retry Scheduled', 'bocs-wordpress');
    }

    /**
     * Trigger the sending of this email.
     *
     * @param int $order_id The order ID.
     * @param string $retry_date The date of the scheduled retry (optional).
     */
    public function trigger($order_id, $retry_date = '') {
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
                    
                    // Set retry date
                    $this->placeholders['{retry_date}'] = $retry_date ? date_i18n(get_option('date_format'), strtotime($retry_date)) : '';
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
     * @return string
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
                'retry_date'         => $this->placeholders['{retry_date}'],
            ),
            '',
            $this->template_base
        );
    }

    /**
     * Get content plain.
     *
     * @return string
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
                'retry_date'         => $this->placeholders['{retry_date}'],
            ),
            '',
            $this->template_base
        );
    }

    /**
     * Default content to show below main email content.
     *
     * @return string
     */
    public function get_default_additional_content() {
        return __('If you want to update your payment method before the retry, please log in to your account.', 'bocs-wordpress');
    }

    /**
     * Initialise settings form fields.
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
                'description' => __('This controls the email subject line. Leave blank to use the default subject: <code>[Bocs] Payment retry scheduled for your {site_title} subscription</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'            => array(
                'title'       => __('Email Heading', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>Payment Retry Scheduled</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
            ),
            'additional_content' => array(
                'title'       => __('Additional content', 'bocs-wordpress'),
                'description' => __('Text to appear below the main email content.', 'bocs-wordpress'),
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __('If you want to update your payment method before the retry, please log in to your account.', 'bocs-wordpress'),
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