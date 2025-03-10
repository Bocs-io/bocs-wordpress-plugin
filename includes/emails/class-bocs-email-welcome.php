<?php
/**
 * Class WC_Bocs_Email_Welcome
 *
 * @package Bocs\Emails
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Welcome Email for First Bocs Purchase
 *
 * An email sent to the customer when they make their first purchase through Bocs.
 *
 * @class       WC_Bocs_Email_Welcome
 * @version     0.0.118
 * @extends     WC_Email
 */
class WC_Bocs_Email_Welcome extends WC_Email {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id             = 'bocs_welcome';
        $this->customer_email = true;
        $this->title          = __('[Bocs] Welcome Email', 'bocs-wordpress');
        $this->description    = __('Welcome email sent to customers after their first Bocs purchase.', 'bocs-wordpress');
        $this->template_html  = 'emails/customer-welcome.php';
        $this->template_plain = 'emails/plain/customer-welcome.php';
        $this->template_base  = BOCS_TEMPLATE_PATH;
        $this->placeholders   = array(
            '{order_date}'   => '',
            '{order_number}' => '',
        );

        // Call parent constructor
        parent::__construct();

        // Other settings
        $this->recipient = $this->get_option('recipient', get_option('admin_email'));
    }

    /**
     * Get email subject.
     *
     * @return string
     */
    public function get_default_subject() {
        return __('[Bocs] Welcome to {site_title}!', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     *
     * @return string
     */
    public function get_default_heading() {
        return __('Welcome to Bocs on {site_title}', 'bocs-wordpress');
    }

    /**
     * Trigger the sending of this email.
     *
     * @param int $order_id The order ID.
     */
    public function trigger($order_id) {
        $this->setup_locale();

        if ($order_id) {
            $this->object = wc_get_order($order_id);
            if (is_a($this->object, 'WC_Order')) {
                // Check if this order has the required meta data
                $bocs_id = get_post_meta($order_id, '__bocs_bocs_id', true);
                
                // Check if the order does NOT have the attribution meta keys
                $source_type = get_post_meta($order_id, '_wc_order_attribution_source_type', true);
                $utm_source = get_post_meta($order_id, '_wc_order_attribution_utm_source', true);
                
                // Only proceed if:
                // 1. Order has a non-empty bocs_id
                // 2. Order does NOT have attribution meta keys (or they're empty)
                if (!empty($bocs_id) && empty($source_type) && empty($utm_source)) {
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
        return __('Thanks for choosing Bocs. We look forward to serving you!', 'bocs-wordpress');
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
                'description' => __('This controls the email subject line. Leave blank to use the default subject: <code>Welcome to {site_title}!</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'            => array(
                'title'       => __('Email Heading', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>Welcome to {site_title}</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
            ),
            'additional_content' => array(
                'title'       => __('Additional content', 'bocs-wordpress'),
                'description' => __('Text to appear below the main email content.', 'bocs-wordpress'),
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __('Thanks for choosing Bocs. We look forward to serving you!', 'bocs-wordpress'),
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