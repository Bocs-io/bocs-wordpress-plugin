<?php
/**
 * Bocs On-hold Renewal Order Email Class
 *
 * Handles email notifications for on-hold subscription renewal orders.
 *
 * @package    Bocs
 * @subpackage Bocs/includes/emails
 * @since      0.0.1
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

if (!class_exists('WC_Bocs_Email_On_Hold_Renewal_Order')) :

/**
 * On Hold Renewal Order Email Class
 *
 * @class    WC_Bocs_Email_On_Hold_Renewal_Order
 * @extends  WC_Email
 */
class WC_Bocs_Email_On_Hold_Renewal_Order extends WC_Email {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id             = 'bocs_on_hold_renewal_order';
        $this->title         = __('On-hold Renewal Order', 'bocs-wordpress-plugin');
        $this->description   = __('This email is sent to customers when their renewal order is placed on-hold.', 'bocs-wordpress-plugin');
        $this->customer_email = true;
        $this->template_html  = 'emails/customer-on-hold-renewal-order.php';
        $this->template_plain = 'emails/plain/customer-on-hold-renewal-order.php';
        $this->template_base  = plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/';
        $this->placeholders   = array(
            '{site_title}'   => $this->get_blogname(),
            '{order_date}'   => '',
            '{order_number}' => '',
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
        return __('Your renewal order is on-hold', 'bocs-wordpress-plugin');
    }

    /**
     * Get email heading.
     *
     * @return string
     */
    public function get_default_heading() {
        return __('Renewal Order On-hold', 'bocs-wordpress-plugin');
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
                $this->recipient = $this->object->get_billing_email();

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
     * @return string
     */
    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            array(
                'order'              => $this->object,
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'     => false,
                'plain_text'        => false,
                'email'             => $this,
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
                'sent_to_admin'     => false,
                'plain_text'        => true,
                'email'             => $this,
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
        return __('We look forward to fulfilling your order soon.', 'bocs-wordpress-plugin');
    }

    /**
     * Initialize Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'         => __('Enable/Disable', 'bocs-wordpress-plugin'),
                'type'         => 'checkbox',
                'label'        => __('Enable this email notification', 'bocs-wordpress-plugin'),
                'default'      => 'yes',
            ),
            'subject' => array(
                'title'         => __('Subject', 'bocs-wordpress-plugin'),
                'type'         => 'text',
                'description'  => sprintf(__('Available placeholders: %s', 'bocs-wordpress-plugin'), '<code>{site_title}, {order_date}, {order_number}</code>'),
                'placeholder'  => $this->get_default_subject(),
                'default'      => '',
                'desc_tip'     => true,
            ),
            'heading' => array(
                'title'         => __('Email Heading', 'bocs-wordpress-plugin'),
                'type'         => 'text',
                'description'  => sprintf(__('Available placeholders: %s', 'bocs-wordpress-plugin'), '<code>{site_title}, {order_date}, {order_number}</code>'),
                'placeholder'  => $this->get_default_heading(),
                'default'      => '',
                'desc_tip'     => true,
            ),
            'additional_content' => array(
                'title'       => __('Additional content', 'bocs-wordpress-plugin'),
                'description' => __('Text to appear below the main email content.', 'bocs-wordpress-plugin'),
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __('N/A', 'bocs-wordpress-plugin'),
                'type'        => 'textarea',
                'default'     => $this->get_default_additional_content(),
                'desc_tip'    => true,
            ),
            'email_type' => array(
                'title'         => __('Email type', 'bocs-wordpress-plugin'),
                'type'         => 'select',
                'description'  => __('Choose which format of email to send.', 'bocs-wordpress-plugin'),
                'default'      => 'html',
                'class'        => 'email_type wc-enhanced-select',
                'options'      => $this->get_email_type_options(),
                'desc_tip'     => true,
            ),
        );
    }
}

endif;

return new WC_Bocs_Email_On_Hold_Renewal_Order();
