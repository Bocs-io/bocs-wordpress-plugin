<?php
/**
 * Class WC_Bocs_Email_Subscription_Cancelled
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
 * Subscription Cancelled Email
 *
 * An email sent to the customer when a subscription is cancelled.
 * Triggers when a customer cancels their Bocs subscription, providing them with
 * confirmation of the cancellation and any necessary next steps.
 *
 * @class       WC_Bocs_Email_Subscription_Cancelled
 * @version     0.0.118
 * @package     Bocs\Emails
 * @extends     WC_Email
 */
class WC_Bocs_Email_Subscription_Cancelled extends WC_Email {

    /**
     * Constructor
     *
     * Initializes email parameters and settings.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->id             = 'bocs_subscription_cancelled';
        $this->customer_email = true;
        $this->title          = __('[Bocs] Subscription Cancelled', 'bocs-wordpress');
        $this->description    = __('Subscription cancelled emails are sent when customers cancel their subscriptions.', 'bocs-wordpress');
        $this->template_html  = 'emails/bocs-customer-subscription-cancelled.php';
        $this->template_plain = 'emails/plain/bocs-customer-subscription-cancelled.php';
        $this->template_base  = BOCS_TEMPLATE_PATH;
        $this->placeholders   = array(
            '{subscription_date}'   => '',
            '{subscription_number}' => '',
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
        return __('[Bocs] Your {site_title} subscription has been cancelled', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     *
     * @since 1.0.0
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('[Bocs] Subscription Cancelled', 'bocs-wordpress');
    }

    /**
     * Trigger the sending of this email.
     *
     * @since 1.0.0
     * @param int $subscription_id The subscription ID.
     */
    public function trigger($subscription_id) {
        $this->setup_locale();

        if ($subscription_id) {
            // For WooCommerce Subscriptions
            if (function_exists('wcs_get_subscription')) {
                $subscription = wcs_get_subscription($subscription_id);
            } 
            // Fallback to regular order
            else {
                $subscription = wc_get_order($subscription_id);
            }

            if (is_a($subscription, 'WC_Order') || is_a($subscription, 'WC_Subscription')) {
                // Get parent order if this is a subscription
                $order_id = method_exists($subscription, 'get_parent_id') ? $subscription->get_parent_id() : $subscription->get_id();
                
                // Check if the order has the required meta data
                $source_type = get_post_meta($order_id, '_wc_order_attribution_source_type', true);
                $utm_source = get_post_meta($order_id, '_wc_order_attribution_utm_source', true);
                
                // Only proceed if the order meets Bocs requirements
                if ($source_type === 'referral' && $utm_source === 'Bocs App') {
                    $this->object = $subscription;
                    $this->recipient = $subscription->get_billing_email();

                    if (is_a($subscription, 'WC_Subscription')) {
                        $this->placeholders['{subscription_date}'] = wc_format_datetime($subscription->get_date_created());
                        $this->placeholders['{subscription_number}'] = $subscription->get_order_number();
                    } else {
                        $this->placeholders['{subscription_date}'] = wc_format_datetime($subscription->get_date_created());
                        $this->placeholders['{subscription_number}'] = $subscription->get_order_number();
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
                'subscription'       => $this->object,
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
                'subscription'       => $this->object,
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
        return __('We hope to see you again in the future. If you would like to resubscribe, please visit our website.', 'bocs-wordpress');
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
                'description' => __('This controls the email subject line. Leave blank to use the default subject: <code>[Bocs] Your {site_title} subscription has been cancelled</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'            => array(
                'title'       => __('Email Heading', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>Subscription Cancelled</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
            ),
            'additional_content' => array(
                'title'       => __('Additional content', 'bocs-wordpress'),
                'description' => __('Text to appear below the main email content.', 'bocs-wordpress'),
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __('We hope to see you again in the future. If you would like to resubscribe, please visit our website.', 'bocs-wordpress'),
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