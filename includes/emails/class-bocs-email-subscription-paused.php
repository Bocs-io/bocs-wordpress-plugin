<?php
/**
 * Class WC_Bocs_Email_Subscription_Paused
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
 * Subscription Paused Email
 *
 * An email sent to customers when they pause their subscription.
 * This handles the email notification sent to the customer after their Bocs subscription is paused.
 *
 * @class       WC_Bocs_Email_Subscription_Paused
 * @version     1.0.0
 * @package     Bocs\Emails
 * @extends     WC_Email
 */
class WC_Bocs_Email_Subscription_Paused extends WC_Email {

    /**
     * Subscription data
     *
     * @var array
     */
    public $subscription_data;
    
    /**
     * Pause reason
     *
     * @var string
     */
    public $pause_reason;

    /**
     * Constructor
     *
     * Initializes email parameters and settings.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->id             = 'bocs_subscription_paused';
        $this->customer_email = true;
        $this->title          = __('[Bocs Customer] Subscription Customer Paused', 'bocs-wordpress');
        $this->description    = __('When a subscription is paused by the customer on customer portal', 'bocs-wordpress');
        $this->template_html  = 'emails/bocs-customer-subscription-paused.php';
        $this->template_plain = 'emails/plain/bocs-customer-subscription-paused.php';
        
        // Make sure we use the correct template path
        $this->template_base = plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/';
        
        // Call parent constructor first
        parent::__construct();
        
        // Force enable this email
        $this->enabled = 'yes';
        
        // Add action to trigger this email when a subscription is paused
        add_action('bocs_subscription_paused', array($this, 'trigger'), 10, 2);
    }

    /**
     * Get email subject.
     *
     * @since 1.0.0
     * @return string Default email subject
     */
    public function get_default_subject() {
        return __('[Bocs] Your subscription has been paused', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     *
     * @since 1.0.0
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('Subscription Paused', 'bocs-wordpress');
    }

    /**
     * Trigger the sending of this email.
     *
     * @since 1.0.0
     * @param array $subscription_data The subscription data from Bocs API
     * @param string $pause_reason Optional pause reason
     * @return bool Whether the email was sent successfully
     */
    public function trigger($subscription_data = array(), $pause_reason = '') {
        // Setup localization
        $this->setup_locale();
        
        // Check if we have valid subscription data
        if (empty($subscription_data) || !is_array($subscription_data)) {
            $this->restore_locale();
            return false;
        }
        
        // Set object and email recipient
        $this->subscription_data = $subscription_data;
        $this->pause_reason = $pause_reason;
        $this->object = $subscription_data;
        
        // Add pause reason to metadata if provided
        if (!empty($pause_reason)) {
            if (!isset($subscription_data['metaData'])) {
                $subscription_data['metaData'] = array();
            }
            
            // Add or update the pause reason in metadata
            $found = false;
            foreach ($subscription_data['metaData'] as &$meta) {
                if (isset($meta['key']) && $meta['key'] === 'pause_reason') {
                    $meta['value'] = $pause_reason;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $subscription_data['metaData'][] = array(
                    'key' => 'pause_reason',
                    'value' => $pause_reason
                );
            }
        }
        
        // Get recipient email - prioritize billing email as it's more reliable
        $this->recipient = '';
        
        // Try billing email first (this is where the API actually stores the email)
        if (isset($subscription_data['billing']) && isset($subscription_data['billing']['email'])) {
            $this->recipient = $subscription_data['billing']['email'];
        }
        // Fallback to customer email if billing email isn't available
        elseif (isset($subscription_data['customer']) && isset($subscription_data['customer']['email'])) {
            $this->recipient = $subscription_data['customer']['email'];
        }
        
        // Skip if no recipient
        if (!$this->recipient) {
            $this->restore_locale();
            return false;
        }
        
        // Set the placeholders for email template
        $this->placeholders['{subscription_id}'] = $subscription_data['id'] ?? '';

        // Send email
        try {
            $result = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
            $this->restore_locale();
            return $result;
        } catch (Exception $e) {
            $this->restore_locale();
            return false;
        }
    }

    /**
     * Get content html.
     *
     * @return string
     */
    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            [
                'subscription' => $this->subscription_data, // Changed from subscription_data to subscription
                'email_heading' => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'email' => $this,
            ],
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
            [
                'subscription' => $this->subscription_data, // Changed from subscription_data to subscription
                'email_heading' => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'email' => $this,
            ],
            '',
            $this->template_base
        );
    }

    /**
     * Get the additional content for this email.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_additional_content() {
        return $this->get_option('additional_content', '');
    }

    /**
     * Initialize email form fields.
     *
     * @since 1.0.0
     * @return void
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled'            => array(
                'title'   => esc_html__('Enable/Disable', 'bocs-wordpress'),
                'type'    => 'checkbox',
                'label'   => esc_html__('Enable this email notification', 'bocs-wordpress'),
                'default' => 'yes',
            ),
            'subject'            => array(
                'title'       => esc_html__('Subject', 'bocs-wordpress'),
                'type'        => 'text',
                'description' => esc_html__('This controls the email subject line. Leave blank to use the default subject: ', 'bocs-wordpress') . $this->get_default_subject(),
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'heading'            => array(
                'title'       => esc_html__('Email Heading', 'bocs-wordpress'),
                'type'        => 'text',
                'description' => esc_html__('This controls the main heading contained within the email notification. Leave blank to use the default heading: ', 'bocs-wordpress') . $this->get_default_heading(),
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'additional_content' => array(
                'title'       => esc_html__('Additional content', 'bocs-wordpress'),
                'description' => esc_html__('Text to appear below the main email content.', 'bocs-wordpress'),
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => esc_html__('N/A', 'bocs-wordpress'),
                'type'        => 'textarea',
                'default'     => $this->get_default_additional_content(),
                'desc_tip'    => true,
            ),
            'email_type'         => array(
                'title'       => esc_html__('Email type', 'bocs-wordpress'),
                'type'        => 'select',
                'description' => esc_html__('Choose which format of email to send.', 'bocs-wordpress'),
                'default'     => 'html',
                'class'       => 'email_type wc-enhanced-select',
                'options'     => $this->get_email_type_options(),
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Get the default additional content.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_default_additional_content() {
        return esc_html__('You can resume your subscription at any time by visiting your account.', 'bocs-wordpress');
    }
} 