<?php
/**
 * Class Bocs_Email_Subscription_Cancelled
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
 * Subscription Cancelled Email
 *
 * An email sent to customers when they cancel their subscription.
 * This handles the email notification sent to the customer after their Bocs subscription is cancelled.
 *
 * @class       Bocs_Email_Subscription_Cancelled
 * @version     1.0.0
 * @package     Bocs\Emails
 * @extends     WC_Email
 */
class Bocs_Email_Subscription_Cancelled extends WC_Email {

    /**
     * Bocs ID associated with this subscription.
     *
     * @var string
     */
    public $bocs_id;

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
        $this->title          = __('[Bocs Customer] Subscription Customer Cancelled', 'bocs-wordpress');
        $this->description    = __('When a subscription is cancelled by the customer on customer portal', 'bocs-wordpress');
        $this->template_html  = 'emails/bocs-customer-subscription-cancelled.php';
        $this->template_plain = 'emails/plain/bocs-customer-subscription-cancelled.php';
        
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
        
        // Add action to trigger this email when a subscription is cancelled
        add_action('bocs_subscription_cancelled', array($this, 'trigger'), 10, 2);
    }

    /**
     * Get email subject.
     *
     * @since 1.0.0
     * @return string Default email subject
     */
    public function get_default_subject() {
        return __('[Bocs] Your subscription has been cancelled', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     *
     * @since 1.0.0
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('Your Subscription Has Been Cancelled', 'bocs-wordpress');
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
     * @param array|object $subscription_data The subscription data from Bocs API
     * @param string $bocs_id Optional. The Bocs ID associated with the subscription
     * @return void
     */
    public function trigger($subscription_data, $bocs_id = '') {
        // Setup localization
        $this->setup_locale();
        
        // Check if we have valid subscription data
        if (empty($subscription_data) || !is_array($subscription_data)) {
            $this->restore_locale();
            return;
        }
        
        // Set object and email recipient
        $this->object = $subscription_data;
        
        // Get recipient email from subscription data
        $this->recipient = isset($subscription_data['customer']) && isset($subscription_data['customer']['email']) 
            ? $subscription_data['customer']['email'] 
            : '';
        
        // Skip if no recipient
        if (!$this->recipient) {
            $this->restore_locale();
            return;
        }
        
        // Set the placeholders for email template
        $this->placeholders['{subscription_id}'] = $subscription_data['id'] ?? '';
        
        // Set the Bocs ID (if available)
        $this->bocs_id = $bocs_id ?: ($subscription_data['bocs']['id'] ?? '');
        
        // Use default heading and subject
        $this->heading = $this->get_default_heading();
        $this->subject = $this->get_default_subject();
        
        // Send the email if enabled
        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
            
            // Log that we sent the email
            error_log(__('Subscription cancellation email notification sent to customer.', 'bocs-wordpress'));
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
        ob_start();
        
        // Include our custom template
        if (file_exists($this->template_base . $this->template_html)) {
            wc_get_template(
                $this->template_html,
                array(
                    'subscription'      => $this->object,
                    'email_heading'     => $this->get_heading(),
                    'additional_content' => $this->get_additional_content(),
                    'email'             => $this,
                    'bocs_id'           => $this->bocs_id,
                ),
                '',
                $this->template_base
            );
        }
        
        return ob_get_clean();
    }

    /**
     * Get content plain.
     *
     * @since 1.0.0
     * @return string Email plain content
     */
    public function get_content_plain() {
        ob_start();
        
        // Include our custom template
        if (file_exists($this->template_base . $this->template_plain)) {
            wc_get_template(
                $this->template_plain,
                array(
                    'subscription'      => $this->object,
                    'email_heading'     => $this->get_heading(),
                    'additional_content' => $this->get_additional_content(),
                    'email'             => $this,
                    'bocs_id'           => $this->bocs_id,
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
        return __('We hope to see you again soon!', 'bocs-wordpress');
    }

    /**
     * Initialize form fields.
     * 
     * This overrides the parent method to add custom fields for the email settings page.
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
            ),
            'subject' => array(
                'title'         => __('Subject', 'bocs-wordpress'),
                'type'          => 'text',
                'description'   => sprintf(__('This controls the email subject line. Default: %s', 'bocs-wordpress'), $this->get_default_subject()),
                'placeholder'   => $this->get_default_subject(),
                'default'       => $this->get_default_subject(),
            ),
            'heading' => array(
                'title'         => __('Email Heading', 'bocs-wordpress'),
                'type'          => 'text',
                'description'   => sprintf(__('This controls the main heading contained within the email notification. Default: %s', 'bocs-wordpress'), $this->get_default_heading()),
                'placeholder'   => $this->get_default_heading(),
                'default'       => $this->get_default_heading(),
            ),
            'additional_content' => array(
                'title'       => __('Additional Content', 'bocs-wordpress'),
                'description' => __('Text to appear below the main email content.', 'bocs-wordpress'),
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __('N/A', 'bocs-wordpress'),
                'type'        => 'textarea',
                'default'     => $this->get_default_additional_content(),
            ),
            'email_type' => array(
                'title'         => __('Email type', 'bocs-wordpress'),
                'type'          => 'select',
                'description'   => __('Choose which format of email to send.', 'bocs-wordpress'),
                'default'       => 'html',
                'class'         => 'email_type wc-enhanced-select',
                'options'       => $this->get_email_type_options(),
            ),
        );
    }
}

endif; 