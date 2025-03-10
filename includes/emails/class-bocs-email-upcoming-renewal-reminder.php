<?php
/**
 * Class WC_Bocs_Email_Upcoming_Renewal_Reminder
 *
 * @package Bocs\Emails
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Upcoming Renewal Reminder Email
 *
 * An email sent to the customer a few days before a subscription renewal.
 *
 * @class       WC_Bocs_Email_Upcoming_Renewal_Reminder
 * @version     0.0.118
 * @extends     WC_Email
 */
class WC_Bocs_Email_Upcoming_Renewal_Reminder extends WC_Email {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id             = 'bocs_upcoming_renewal_reminder';
        $this->customer_email = true;
        $this->title          = __('[Bocs] Upcoming Renewal Reminder', 'bocs-wordpress');
        $this->description    = __('Upcoming renewal reminder emails are sent to customers a few days before their subscription is due to renew.', 'bocs-wordpress');
        $this->template_html  = 'emails/customer-upcoming-renewal-reminder.php';
        $this->template_plain = 'emails/plain/customer-upcoming-renewal-reminder.php';
        $this->template_base  = BOCS_TEMPLATE_PATH;
        $this->placeholders   = array(
            '{subscription_date}'   => '',
            '{subscription_number}' => '',
            '{renewal_date}'        => '',
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
        return __('[Bocs] Your {site_title} subscription will renew soon', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     *
     * @return string
     */
    public function get_default_heading() {
        return __('Upcoming Subscription Renewal', 'bocs-wordpress');
    }

    /**
     * Trigger the sending of this email.
     *
     * @param int $subscription_id The subscription ID.
     * @param string $renewal_date The date of the upcoming renewal.
     */
    public function trigger($subscription_id, $renewal_date = '') {
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
                    
                    $this->placeholders['{renewal_date}'] = $renewal_date ? date_i18n(get_option('date_format'), strtotime($renewal_date)) : '';
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
                'subscription'      => $this->object,
                'email_heading'     => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'     => false,
                'plain_text'        => false,
                'email'             => $this,
                'renewal_date'      => $this->placeholders['{renewal_date}'],
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
                'subscription'      => $this->object,
                'email_heading'     => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'     => false,
                'plain_text'        => true,
                'email'             => $this,
                'renewal_date'      => $this->placeholders['{renewal_date}'],
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
        return __('If you wish to make any changes to your subscription before the renewal, please contact us.', 'bocs-wordpress');
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
                'description' => __('This controls the email subject line. Leave blank to use the default subject: <code>[Bocs] Your {site_title} subscription will renew soon</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'            => array(
                'title'       => __('Email Heading', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>Upcoming Subscription Renewal</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
            ),
            'additional_content' => array(
                'title'       => __('Additional content', 'bocs-wordpress'),
                'description' => __('Text to appear below the main email content.', 'bocs-wordpress'),
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __('If you wish to make any changes to your subscription before the renewal, please contact us.', 'bocs-wordpress'),
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