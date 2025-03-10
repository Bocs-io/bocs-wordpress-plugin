<?php
/**
 * Class WC_Bocs_Email_On_Hold_Renewal_Order
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
}

if (!class_exists('WC_Email_Customer_On_Hold_Order', false)) {
    include_once WC_ABSPATH . 'includes/emails/class-wc-email-customer-on-hold-order.php';
}

if (!class_exists('WC_Bocs_Email_On_Hold_Renewal_Order')) :

/**
 * On Hold Renewal Order Email
 *
 * An email sent to the customer when a subscription renewal order is placed on-hold.
 * This email notifies customers that their renewal order is pending payment or 
 * additional verification before processing can continue.
 *
 * @class       WC_Bocs_Email_On_Hold_Renewal_Order
 * @version     0.0.118
 * @package     Bocs\Emails
 * @extends     WC_Email_Customer_On_Hold_Order
 */
class WC_Bocs_Email_On_Hold_Renewal_Order extends WC_Email_Customer_On_Hold_Order {

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
     * Sets up the email's ID, title, description, and template paths.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->id             = 'bocs_on_hold_renewal_order';
        $this->customer_email = true;
        $this->title          = __('[Bocs] On-hold Renewal Order', 'bocs-wordpress');
        $this->description    = __('On-hold renewal order emails are sent to customers when their renewal orders are marked on-hold.', 'bocs-wordpress');
        $this->template_html  = 'emails/customer-on-hold-renewal-order.php';
        $this->template_plain = 'emails/plain/customer-on-hold-renewal-order.php';
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
     * Defines the default subject line for the on-hold renewal order email.
     *
     * @since 1.0.0
     * @return string Default email subject
     */
    public function get_default_subject() {
        return __('[Bocs] Your {site_title} renewal order is on-hold', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     * 
     * Defines the default heading for the on-hold renewal order email.
     *
     * @since 1.0.0
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('Renewal Order On-hold', 'bocs-wordpress');
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
                    
                    // Check for Bocs IDs - legacy support
                    $bocs_bocs_id = get_post_meta($order_id, '__bocs_bocs_id', true);
                    $bocs_id = get_post_meta($order_id, '__bocs_id', true);
                    $bocs_subscription_id = get_post_meta($order_id, '__bocs_subscription_id', true);
                    
                    // Set the Bocs ID for the email template if available
                    if (!empty($bocs_bocs_id)) {
                        $this->bocs_id = $bocs_bocs_id;
                    } elseif (!empty($bocs_id)) {
                        $this->bocs_id = $bocs_id;
                    } elseif (!empty($bocs_subscription_id)) {
                        $this->bocs_id = $bocs_subscription_id;
                    }
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
        return __('We look forward to fulfilling your order soon.', 'bocs-wordpress');
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
                'description' => __('This controls the email subject line. Leave blank to use the default subject: <code>[Bocs] Your {site_title} renewal order is on-hold</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'            => array(
                'title'       => __('Email Heading', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>Renewal Order On-hold</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
            ),
            'additional_content' => array(
                'title'       => __('Additional content', 'bocs-wordpress'),
                'description' => __('Text to appear below the main email content.', 'bocs-wordpress'),
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __('We look forward to fulfilling your order soon.', 'bocs-wordpress'),
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

return new WC_Bocs_Email_On_Hold_Renewal_Order();
