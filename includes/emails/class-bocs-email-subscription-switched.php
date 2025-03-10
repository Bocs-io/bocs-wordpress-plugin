<?php
if ( ! defined( 'ABSPATH' ) ) {
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

if ( ! class_exists( 'WC_Bocs_Email_Subscription_Switched' ) ) :

/**
 * Subscription Switched Email
 *
 * Email sent to customer when their subscription is switched
 */
class WC_Bocs_Email_Subscription_Switched extends WC_Email {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id             = 'bocs_subscription_switched';
        $this->customer_email = true;
        $this->title          = __('[Bocs] Subscription Switched', 'bocs-wordpress');
        $this->description    = __('Subscription switched emails are sent when customers switch to a new subscription.', 'bocs-wordpress');
        $this->template_html  = 'emails/subscription-switched.php';
        $this->template_plain = 'emails/plain/subscription-switched.php';
        $this->template_base  = BOCS_TEMPLATE_PATH;
        $this->placeholders   = array(
            '{subscription_date}'   => '',
            '{subscription_number}' => '',
        );

        // Call parent constructor
        parent::__construct();

        // Other settings
        $this->manual = true;
        $this->heading = $this->get_option( 'heading', $this->get_default_heading() );
        $this->subject = $this->get_option( 'subject', $this->get_default_subject() );
    }

    /**
     * Get email subject.
     *
     * @return string
     */
    public function get_default_subject() {
        return __('[Bocs] Your subscription has been switched', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     *
     * @return string
     */
    public function get_default_heading() {
        return __( 'Subscription Switched', 'bocs-wordpress' );
    }

    /**
     * Trigger the sending of this email.
     *
     * @param int $subscription_id The subscription ID.
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
     * @return string
     */
    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            array(
                'subscription'          => $this->object,
                'old_subscription_data' => $this->old_subscription_data,
                'email_heading'         => $this->get_heading(),
                'additional_content'    => $this->get_additional_content(),
                'sent_to_admin'        => false,
                'plain_text'           => false,
                'email'                => $this,
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
                'subscription'          => $this->object,
                'old_subscription_data' => $this->old_subscription_data,
                'email_heading'         => $this->get_heading(),
                'additional_content'    => $this->get_additional_content(),
                'sent_to_admin'        => false,
                'plain_text'           => true,
                'email'                => $this,
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
        return __( 'Thanks for using {site_title}!', 'bocs-wordpress' );
    }

    /**
     * Initialize Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'         => __( 'Enable/Disable', 'bocs-wordpress' ),
                'type'         => 'checkbox',
                'label'        => __( 'Enable this email notification', 'bocs-wordpress' ),
                'default'      => 'yes',
            ),
            'subject' => array(
                'title'         => __( 'Subject', 'bocs-wordpress' ),
                'type'         => 'text',
                'description'  => sprintf( __( 'Available placeholders: %s', 'bocs-wordpress' ), '<code>{site_title}, {order_date}, {order_number}</code>' ),
                'placeholder'  => $this->get_default_subject(),
                'default'      => '',
                'desc_tip'     => true,
            ),
            'heading' => array(
                'title'         => __( 'Email Heading', 'bocs-wordpress' ),
                'type'         => 'text',
                'description'  => sprintf( __( 'Available placeholders: %s', 'bocs-wordpress' ), '<code>{site_title}, {order_date}, {order_number}</code>' ),
                'placeholder'  => $this->get_default_heading(),
                'default'      => '',
                'desc_tip'     => true,
            ),
            'additional_content' => array(
                'title'       => __( 'Additional content', 'bocs-wordpress' ),
                'description' => __( 'Text to appear below the main email content.', 'bocs-wordpress' ),
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __( 'N/A', 'bocs-wordpress' ),
                'type'        => 'textarea',
                'default'     => $this->get_default_additional_content(),
                'desc_tip'    => true,
            ),
            'email_type' => array(
                'title'         => __( 'Email type', 'bocs-wordpress' ),
                'type'         => 'select',
                'description'  => __( 'Choose which format of email to send.', 'bocs-wordpress' ),
                'default'      => 'html',
                'class'        => 'email_type wc-enhanced-select',
                'options'      => $this->get_email_type_options(),
                'desc_tip'     => true,
            ),
        );
    }
}

endif;

return new WC_Bocs_Email_Subscription_Switched(); 