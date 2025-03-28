<?php
/**
 * Class Bocs_Email_Subscription_Reactivated
 *
 * Email sent to customers when they reactivate their subscription.
 *
 * @extends \WC_Email
 * @package Bocs/Emails
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * Subscription Reactivated email.
 */
class Bocs_Email_Subscription_Reactivated extends WC_Email {
    /**
     * Subscription data.
     *
     * @var array
     */
    protected $subscription;

    /**
     * Constructor
     */
    public function __construct() {
        $this->id             = 'bocs_subscription_reactivated';
        $this->customer_email = true;
        $this->title          = __('[Bocs Customer] Subscription Customer Reactivated', 'bocs-wordpress');
        $this->description    = __('When a subscription is reactivated by the customer on customer portal', 'bocs-wordpress');
        $this->template_html  = 'emails/bocs-customer-subscription-reactivated.php';
        $this->template_plain = 'emails/plain/bocs-customer-subscription-reactivated.php';
        
        // Make sure we use the correct template path
        if (defined('BOCS_TEMPLATE_PATH')) {
            $this->template_base = BOCS_TEMPLATE_PATH;
        } else {
            // Fallback to plugin directory
            $this->template_base = plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/';
        }
        
        $this->placeholders   = array(
            '{subscription_id}' => '',
        );

        // Force enable this email
        $this->enabled = 'yes';

        // Call parent constructor
        parent::__construct();
        
        // Do not set a default recipient - we'll set it in the trigger method based on the subscription
        
        // Add a filter to ensure this email is always enabled
        add_filter('woocommerce_email_enabled_' . $this->id, function($enabled) {
            return 'yes'; // Always enable this email
        }, 999, 1);
        
        // Add action to trigger this email when a subscription is reactivated
        add_action('bocs_subscription_resumed', array($this, 'trigger'), 10, 2);
    }

    /**
     * Get email subject.
     *
     * @since 1.0.0
     * @return string Default email subject
     */
    public function get_default_subject() {
        return __('[{site_title}] Your subscription has been reactivated', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     *
     * @since 1.0.0
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('Subscription Reactivated', 'bocs-wordpress');
    }

    /**
     * Trigger the sending of this email.
     *
     * @since 1.0.0
     * @param array $subscription_data The subscription data from Bocs API
     * @param string $resume_reason Optional resume reason
     * @return void
     */
    public function trigger($subscription_data, $resume_reason = '') {
        // Setup localization
        $this->setup_locale();
        
        // Check if we have valid subscription data
        if (empty($subscription_data) || !is_array($subscription_data)) {
            $this->restore_locale();
            return;
        }
        
        // Add resume reason to subscription data if provided
        if (!empty($resume_reason)) {
            if (!isset($subscription_data['metaData'])) {
                $subscription_data['metaData'] = [];
            }
            
            // Add or update the resume reason in metadata
            $found = false;
            foreach ($subscription_data['metaData'] as &$meta) {
                if (isset($meta['key']) && $meta['key'] === 'resume_reason') {
                    $meta['value'] = $resume_reason;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $subscription_data['metaData'][] = [
                    'key' => 'resume_reason',
                    'value' => $resume_reason
                ];
            }
        }
        
        // Set object and email recipient
        $this->subscription = $subscription_data;
        $this->object = $subscription_data;
        
        // Get recipient email from subscription data
        $this->recipient = isset($subscription_data['customer']) && isset($subscription_data['customer']['email']) 
            ? $subscription_data['customer']['email'] 
            : '';
        
        // Try billing email as fallback
        if (!$this->recipient && isset($subscription_data['billing']) && isset($subscription_data['billing']['email'])) {
            $this->recipient = $subscription_data['billing']['email'];
        }
        
        // Skip if no recipient
        if (!$this->recipient) {
            $this->restore_locale();
            return;
        }
        
        // Set the placeholders for email template
        $this->placeholders['{subscription_id}'] = $subscription_data['id'] ?? '';

        // Send email
        $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        
        // Restore localization
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
            [
                'subscription'      => $this->subscription,
                'email_heading'     => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'email'             => $this,
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
                'subscription'      => $this->subscription,
                'email_heading'     => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'email'             => $this,
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
        return esc_html__('Thank you for continuing to be our valued customer!', 'bocs-wordpress');
    }
} 