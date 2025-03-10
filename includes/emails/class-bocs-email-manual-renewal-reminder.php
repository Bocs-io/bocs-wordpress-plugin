<?php
/**
 * Class WC_Bocs_Email_Manual_Renewal_Reminder
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
 * Manual Renewal Reminder Email
 *
 * An email sent to the customer to remind them to manually renew their subscription.
 * This notification informs customers that they need to take action to renew
 * their subscription, providing a link to complete the renewal process.
 *
 * @class       WC_Bocs_Email_Manual_Renewal_Reminder
 * @version     0.0.118
 * @package     Bocs\Emails
 * @extends     WC_Email
 */
class WC_Bocs_Email_Manual_Renewal_Reminder extends WC_Email {

    /**
     * Constructor
     *
     * Initializes email parameters and settings.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->id             = 'bocs_manual_renewal_reminder';
        $this->customer_email = true;
        $this->title          = __('[Bocs] Manual Renewal Reminder', 'bocs-wordpress');
        $this->description    = __('Manual renewal reminder emails are sent to customers when they need to manually renew their subscriptions.', 'bocs-wordpress');
        $this->template_html  = 'emails/customer-manual-renewal-reminder.php';
        $this->template_plain = 'emails/plain/customer-manual-renewal-reminder.php';
        $this->template_base  = BOCS_TEMPLATE_PATH;
        $this->placeholders   = array(
            '{subscription_date}'   => '',
            '{subscription_number}' => '',
            '{renewal_url}'         => '',
            '{expiry_date}'         => '',
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
        return __('[Bocs] Your {site_title} subscription needs to be renewed', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     *
     * @since 1.0.0
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('Subscription Renewal Reminder', 'bocs-wordpress');
    }

    /**
     * Trigger the sending of this email.
     *
     * @since 1.0.0
     * @param int    $subscription_id The subscription ID.
     * @param string $expiry_date     The date the subscription expires (optional).
     * @return void
     */
    public function trigger($subscription_id, $expiry_date = '') {
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
                        
                        // Generate renewal URL if WC Subscriptions is active
                        if (function_exists('wcs_get_early_renewal_url') && is_callable('wcs_get_early_renewal_url')) {
                            $this->placeholders['{renewal_url}'] = esc_url(wcs_get_early_renewal_url($subscription));
                        } else {
                            $this->placeholders['{renewal_url}'] = esc_url(wc_get_account_endpoint_url('subscriptions'));
                        }
                    } else {
                        $this->placeholders['{subscription_date}'] = wc_format_datetime($subscription->get_date_created());
                        $this->placeholders['{subscription_number}'] = $subscription->get_order_number();
                        $this->placeholders['{renewal_url}'] = esc_url(wc_get_account_endpoint_url('orders'));
                    }
                    
                    // Set expiry date
                    $this->placeholders['{expiry_date}'] = $expiry_date ? date_i18n(get_option('date_format'), strtotime($expiry_date)) : '';
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
                'renewal_url'        => $this->placeholders['{renewal_url}'],
                'expiry_date'        => $this->placeholders['{expiry_date}'],
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
                'renewal_url'        => $this->placeholders['{renewal_url}'],
                'expiry_date'        => $this->placeholders['{expiry_date}'],
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
        return __('If you have any questions about your subscription, please contact us.', 'bocs-wordpress');
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
                'description' => __('This controls the email subject line. Leave blank to use the default subject: <code>[Bocs] Your {site_title} subscription needs to be renewed</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'            => array(
                'title'       => __('Email Heading', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>Subscription Renewal Reminder</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
            ),
            'additional_content' => array(
                'title'       => __('Additional content', 'bocs-wordpress'),
                'description' => __('Text to appear below the main email content.', 'bocs-wordpress'),
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __('If you have any questions about your subscription, please contact us.', 'bocs-wordpress'),
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