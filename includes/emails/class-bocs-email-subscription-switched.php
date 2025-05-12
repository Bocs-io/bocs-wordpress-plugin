<?php
/**
 * Class WC_Bocs_Email_Subscription_Switched
 *
 * @package     Bocs\Emails
 * @version     1.0.0
 * @since       1.0.0
 * @author      Bocs
 * @category    Emails
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Subscription Switched Email
 *
 * An email sent to customers when they switch their subscription product or frequency.
 * This handles the email notification sent to the customer after their Bocs subscription is switched.
 *
 * @class       WC_Bocs_Email_Subscription_Switched
 * @version     1.0.0
 * @package     Bocs\Emails
 * @extends     WC_Email
 */
class WC_Bocs_Email_Subscription_Switched extends WC_Email {

    /**
     * Bocs ID associated with this subscription.
     *
     * @var string
     */
    public $bocs_id;

    /**
     * Subscription data
     *
     * @var array
     */
    public $subscription_data;
    
    /**
     * Frequency ID when frequency is updated
     *
     * @var string
     */
    public $frequency_id;

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
        $this->title          = __('[Bocs Customer] Subscription Switched or Updated', 'bocs-wordpress');
        $this->description    = __('When a product or frequency is updated', 'bocs-wordpress');
        $this->template_html  = 'emails/bocs-subscription-switched.php';
        $this->template_plain = 'emails/plain/bocs-subscription-switched.php';
        
        
        // Make sure we use the correct template path
        if (defined('BOCS_TEMPLATE_PATH')) {
            $this->template_base = BOCS_TEMPLATE_PATH;
        } else {
            // Fallback to plugin directory
            $this->template_base = plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/';
        }
        
        // Call parent constructor first
        parent::__construct();
        
        // Force enable this email
        $this->enabled = 'yes';
        
        // Add action to trigger this email when a subscription is switched
        add_action('bocs_subscription_switched', array($this, 'trigger'), 10, 4);
    }

    /**
     * Register this email with WooCommerce mailer
     * 
     * @param WC_Emails $email_classes WooCommerce email classes
     */
    public function register_with_woocommerce($email_classes) {
        // Make sure we're added to emails
        if ($email_classes && is_object($email_classes) && isset($email_classes->emails)) {
            if (!isset($email_classes->emails[$this->id])) {
                $email_classes->emails[$this->id] = $this;
            }
        }
    }

    /**
     * Get email subject.
     *
     * @since 1.0.0
     * @return string Default email subject
     */
    public function get_default_subject() {
        return __('Your subscription has been updated', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     *
     * @since 1.0.0
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('Your subscription has been updated', 'bocs-wordpress');
    }

    /**
     * Get box updated email subject.
     *
     * @since 1.0.0
     * @return string Box updated email subject
     */
    public function get_box_updated_subject() {
        return __('Your box contents have been updated', 'bocs-wordpress');
    }

    /**
     * Get box updated email heading.
     *
     * @since 1.0.0
     * @return string Box updated email heading
     */
    public function get_box_updated_heading() {
        return __('Good news! Your subscription contents have been updated.', 'bocs-wordpress');
    }

    /**
     * Get frequency updated email subject.
     *
     * @since 1.0.0
     * @return string Frequency updated email subject
     */
    public function get_frequency_updated_subject() {
        return __('Your subscription frequency has been updated', 'bocs-wordpress');
    }

    /**
     * Get frequency updated email heading.
     *
     * @since 1.0.0
     * @return string Frequency updated email heading
     */
    public function get_frequency_updated_heading() {
        return __('Your subscription frequency has been updated', 'bocs-wordpress');
    }

    /**
     * Override the get_heading method to ensure our heading is used regardless of stored options
     * 
     * @return string The email heading
     */
    public function get_heading() {
        // Force our custom heading to avoid duplication with site title
        return $this->format_string($this->get_default_heading());
    }

    /**
     * Override get_subject to ensure our subject is properly formatted
     * 
     * @return string The email subject
     */
    public function get_subject() {
        $subject = $this->get_option('subject', $this->get_default_subject());
        return $this->format_string($subject);
    }

    /**
     * Trigger the sending of this email.
     *
     * @since 1.0.0
     * @param array $subscription_data The subscription data
     * @param string $bocs_id Optional. The Bocs ID that was switched to
     * @param string $frequency_id Optional. The frequency ID that was chosen
     * @param bool $is_box_update Optional. Whether this is a box content update rather than a plan switch
     * @return bool success
     */
    public function trigger($subscription_data = array(), $bocs_id = '', $frequency_id = '', $is_box_update = false) {
        
        $this->subscription_data = $subscription_data;
        
        if (empty($subscription_data)) {
            return false;
        }

        // Get the recipient
        $recipient = '';
        if (!empty($subscription_data['customer']['email'])) {
            $recipient = $subscription_data['customer']['email'];
        } elseif (!empty($subscription_data['billing']['email'])) {
            $recipient = $subscription_data['billing']['email'];
        }

        if (empty($recipient)) {
            return false;
        }

        // Set recipient
        $this->recipient = $recipient;

        // Get site domain
        $site_url = parse_url(get_site_url());
        $domain = isset($site_url['host']) ? $site_url['host'] : '';

        // Set proper from name and email
        $this->from_name = get_bloginfo('name');
        $this->from_email = 'noreply@' . $domain;

        // Replace placeholders in subject/heading
        $this->find['subscription-id'] = '{subscription-id}';
        $this->replace['subscription-id'] = $subscription_data['id'];
        
        // Set appropriate subject and heading based on what was updated
        if (!empty($frequency_id)) {
            // Set frequency-specific subject and heading if frequency was updated
            $this->subject = $this->get_frequency_updated_subject();
            $this->heading = $this->get_frequency_updated_heading();
            
            // Store the frequency ID for use in the template
            $this->frequency_id = $frequency_id;
        } elseif ($is_box_update) {
            // Set box update specific subject and heading
            $this->subject = $this->get_box_updated_subject();
            $this->heading = $this->get_box_updated_heading();
        }
        // Otherwise use the default subject and heading

        if (!$this->get_recipient()) {
            return false;
        }
        
        try {
            
            $sent = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
            return $sent;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get content html.
     *
     * @since 1.0.0
     * @return string Email HTML content
     */
    public function get_content_html() {
        $base_color = get_option('woocommerce_email_base_color', '#557da1');
        return wc_get_template_html(
            $this->template_html,
            array(
                'order'              => $this->object,
                'email_heading'      => $this->get_heading(),
                'sent_to_admin'      => false,
                'plain_text'         => false,
                'email'              => $this,
                'base_color'         => $base_color,
                'subscription_data'  => $this->subscription_data
            ),
            '',
            $this->template_base
        );
    }

    /**
     * Get content plain.
     *
     * @since 1.0.0
     * @return string Email plain content
     */
    public function get_content_plain() {
        ob_start();
        
        // Get WooCommerce email settings
        $base_color = get_option('woocommerce_email_base_color', '#557da1');
        
        // Include our custom template
        if (file_exists($this->template_base . $this->template_plain)) {
            wc_get_template(
                $this->template_plain,
                array(
                    'subscription_data' => $this->subscription_data,
                    'email_heading'    => $this->get_heading(),
                    'sent_to_admin' => false,
                    'plain_text' => true,
                    'email'            => $this,
                    'bocs_id'          => $this->bocs_id,
                    'base_color'       => $base_color
                ),
                '',
                $this->template_base
            );
        }
        
        return ob_get_clean();
    }

    /**
     * Get default additional content.
     *
     * @since 1.0.0
     * @return string Default additional content
     */
    public function get_default_additional_content() {
        return __('Thank you for being a valued customer. If you have any questions about your updated subscription, please contact us.', 'bocs-wordpress');
    }

    /**
     * Initialize form fields for the email settings
     *
     * @since 1.0.0
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'         => __('Enable/Disable', 'bocs-wordpress'),
                'type'          => 'checkbox',
                'label'         => __('Enable this email notification', 'bocs-wordpress'),
                'default'       => 'yes',
                'desc_tip'      => true,
            ),
            'subject' => array(
                'title'         => __('Subject', 'bocs-wordpress'),
                'type'          => 'text',
                'description'   => sprintf(__('This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', 'bocs-wordpress'), $this->get_default_subject()),
                'placeholder'   => $this->get_default_subject(),
                'default'       => $this->get_default_subject(),
                'desc_tip'      => true,
            ),
            'heading' => array(
                'title'         => __('Email Heading', 'bocs-wordpress'),
                'type'          => 'text',
                'description'   => sprintf(__('This controls the main heading contained in the email notification. Leave blank to use the default heading: <code>%s</code>.', 'bocs-wordpress'), $this->get_default_heading()),
                'placeholder'   => $this->get_default_heading(),
                'default'       => $this->get_default_heading(),
                'desc_tip'      => true,
            ),
            'email_type' => array(
                'title'         => __('Email Type', 'bocs-wordpress'),
                'type'          => 'select',
                'description'   => __('Choose which format of email to send.', 'bocs-wordpress'),
                'default'       => 'html',
                'class'         => 'email_type wc-enhanced-select',
                'options'       => $this->get_email_type_options(),
                'desc_tip'      => true,
            ),
        );
    }
} 