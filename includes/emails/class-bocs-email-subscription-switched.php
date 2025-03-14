<?php
/**
 * Class WC_Bocs_Email_Subscription_Switched
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

if (!class_exists('WC_Bocs_Email_Subscription_Switched')) :

/**
 * Subscription Switched Email
 *
 * An email sent to the customer when their subscription plan is switched.
 * This email notifies customers when they've successfully changed to a different 
 * subscription plan, providing details about their new subscription terms.
 *
 * @class       WC_Bocs_Email_Subscription_Switched
 * @version     0.0.118
 * @package     Bocs\Emails
 * @extends     WC_Email
 */
class WC_Bocs_Email_Subscription_Switched extends WC_Email {

    /**
     * Old subscription data before the switch.
     *
     * @var array
     */
    public $old_subscription_data;

    /**
     * Constructor
     *
     * Initializes email parameters and settings.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->id             = 'bocs_subscription_switched';
        $this->customer_email = true;
        $this->title          = __('[Bocs] Subscription Switched', 'bocs-wordpress');
        $this->description    = __('Subscription switched emails are sent when customers switch their subscription items.', 'bocs-wordpress');
        $this->template_html  = 'emails/bocs-subscription-switched.php';
        $this->template_plain = 'emails/plain/bocs-subscription-switched.php';
        $this->template_base  = BOCS_TEMPLATE_PATH;
        $this->placeholders   = array(
            '{subscription_date}'   => '',
            '{subscription_number}' => '',
        );

        // Call parent constructor
        parent::__construct();

        // Other settings
        $this->manual = true;
        $this->heading = $this->get_option('heading', $this->get_default_heading());
        $this->subject = $this->get_option('subject', $this->get_default_subject());
    }

    /**
     * Get email subject.
     *
     * @since 1.0.0
     * @return string Default email subject
     */
    public function get_default_subject() {
        return __('[Bocs] Your subscription has been switched', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     *
     * @since 1.0.0
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('Subscription Switched', 'bocs-wordpress');
    }

    /**
     * Trigger the sending of this email.
     *
     * @since 1.0.0
     * @param int $subscription_id The subscription ID.
     * @return void
     */
    public function trigger($subscription_id) {
        $this->setup_locale();

        if ($subscription_id) {
            // For subscription switched emails, we need to check if the original order has the required meta data
            $subscription = wcs_get_subscription($subscription_id);
            
            if (is_a($subscription, 'WC_Subscription')) {
                $order_id = $subscription->get_parent_id();
                
                // Check if the parent order has the required meta data
                $source_type = get_post_meta($order_id, '_wc_order_attribution_source_type', true);
                $utm_source = get_post_meta($order_id, '_wc_order_attribution_utm_source', true);
                
                // Only proceed if the order meets Bocs requirements
                if ($source_type === 'referral' && $utm_source === 'Bocs App') {
                    $this->object = $subscription;
                    $this->recipient = $subscription->get_billing_email();
                    
                    $this->placeholders['{subscription_date}'] = wc_format_datetime($this->object->get_date_created());
                    $this->placeholders['{subscription_number}'] = $this->object->get_order_number();
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
                'subscription'          => $this->object,
                'old_subscription_data' => $this->old_subscription_data,
                'email_heading'         => $this->get_heading(),
                'additional_content'    => $this->get_additional_content(),
                'sent_to_admin'         => false,
                'plain_text'            => false,
                'email'                 => $this,
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
                'subscription'          => $this->object,
                'old_subscription_data' => $this->old_subscription_data,
                'email_heading'         => $this->get_heading(),
                'additional_content'    => $this->get_additional_content(),
                'sent_to_admin'         => false,
                'plain_text'            => true,
                'email'                 => $this,
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
        return __('Thanks for using {site_title}!', 'bocs-wordpress');
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
                'description' => __('This controls the email subject line. Leave blank to use the default subject: <code>[Bocs] Your subscription has been switched</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'            => array(
                'title'       => __('Email Heading', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>Subscription Switched</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
            ),
            'additional_content' => array(
                'title'       => __('Additional content', 'bocs-wordpress'),
                'description' => __('Text to appear below the main email content.', 'bocs-wordpress'),
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __('Thanks for using {site_title}!', 'bocs-wordpress'),
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

return new WC_Bocs_Email_Subscription_Switched(); 