<?php
/**
 * Bocs Completed Renewal Order Email Class
 *
 * Handles email notifications for completed subscription renewal orders.
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

if (!class_exists('WC_Bocs_Email_Completed_Renewal_Order')) :

/**
 * Class WC_Bocs_Email_Completed_Renewal_Order
 *
 * @package     Bocs\Emails
 * @version     0.0.118
 * @since       0.0.118
 * @author      Bocs
 * @category    Emails
 */
class WC_Bocs_Email_Completed_Renewal_Order extends WC_Email_Customer_Completed_Order {

    /**
     * Constructor
     *
     * Initializes email parameters and settings.
     * Sets up the email's ID, title, description, and template paths.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->id             = 'bocs_customer_completed_renewal_order';
        $this->title          = __('[Bocs] Completed Renewal Order', 'bocs-wordpress');
        $this->description    = __('Completed renewal order emails are sent to customers when their renewal orders are marked complete and usually indicate that their renewal order goods are on their way.', 'bocs-wordpress');
        $this->customer_email = true;
        $this->template_html  = 'emails/customer-completed-renewal-order.php';
        $this->template_plain = 'emails/plain/customer-completed-renewal-order.php';
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
     * Defines the default subject line for the completed renewal order email.
     *
     * @since 1.0.0
     * @return string Default email subject
     */
    public function get_default_subject() {
        return __('[Bocs] Your {site_title} renewal order is now complete', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     * 
     * Defines the default heading for the completed renewal order email.
     *
     * @since 1.0.0
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('Your renewal order is complete', 'bocs-wordpress');
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
                    $this->recipient = $this->object->get_billing_email();
                    $this->placeholders['{order_date}'] = wc_format_datetime($this->object->get_date_created());
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
        return __('Thanks for shopping with us.', 'bocs-wordpress');
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
                'description' => __('This controls the email subject line. Leave blank to use the default subject: <code>[Bocs] Your {site_title} renewal order is now complete</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'            => array(
                'title'       => __('Email Heading', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>Your renewal order is complete</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
            ),
            'additional_content' => array(
                'title'       => __('Additional content', 'bocs-wordpress'),
                'description' => __('Text to appear below the main email content.', 'bocs-wordpress'),
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __('Thanks for shopping with us.', 'bocs-wordpress'),
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

return new WC_Bocs_Email_Completed_Renewal_Order();
