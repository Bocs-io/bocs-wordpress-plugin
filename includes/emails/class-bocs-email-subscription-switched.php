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
        
        // Log for debugging
        error_log('BOCS EMAIL DEBUG: Initializing ' . $this->id . ' email class');
        
        // Make sure we use the correct template path
        if (defined('BOCS_TEMPLATE_PATH')) {
            $this->template_base = BOCS_TEMPLATE_PATH;
            error_log('BOCS EMAIL DEBUG: Using template path: ' . BOCS_TEMPLATE_PATH);
        } else {
            // Fallback to plugin directory
            $this->template_base = plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/';
            error_log('BOCS EMAIL DEBUG: Using fallback template path: ' . $this->template_base);
        }
        
        $this->placeholders   = array(
            '{subscription_id}' => '',
        );

        // Force enable this email
        $this->enabled = 'yes';
        error_log('BOCS EMAIL DEBUG: Force enabling email');

        // Call parent constructor
        parent::__construct();
        
        // Do not set a default recipient - we'll set it in the trigger method based on the subscription
        
        // Register with WooCommerce explicitly
        add_action('woocommerce_email', array($this, 'register_with_woocommerce'));
        
        // Add a filter to ensure this email is always enabled
        add_filter('woocommerce_email_enabled_' . $this->id, function($enabled) {
            error_log('BOCS EMAIL DEBUG: Email enabled filter triggered, returning "yes"');
            return 'yes'; // Always enable this email
        }, 999, 1);
        
        // Add action to trigger this email when a subscription is switched
        add_action('bocs_subscription_switched', array($this, 'trigger'), 10, 5);
        error_log('BOCS EMAIL DEBUG: Added action hook for bocs_subscription_switched');
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
                error_log('BOCS EMAIL DEBUG: Explicitly registered with WooCommerce mailer');
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
        return __('[Bocs] Your subscription has been updated', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     *
     * @since 1.0.0
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('Your Subscription Has Been Updated', 'bocs-wordpress');
    }

    /**
     * Get box updated email subject.
     *
     * @since 1.0.0
     * @return string Box updated email subject
     */
    public function get_box_updated_subject() {
        return __('[Bocs] Your box contents have been updated', 'bocs-wordpress');
    }

    /**
     * Get box updated email heading.
     *
     * @since 1.0.0
     * @return string Box updated email heading
     */
    public function get_box_updated_heading() {
        return __('Your Box Contents Have Been Updated', 'bocs-wordpress');
    }

    /**
     * Get frequency updated email subject.
     *
     * @since 1.0.0
     * @return string Frequency updated email subject
     */
    public function get_frequency_updated_subject() {
        return __('[Bocs] Your subscription frequency has been updated', 'bocs-wordpress');
    }

    /**
     * Get frequency updated email heading.
     *
     * @since 1.0.0
     * @return string Frequency updated email heading
     */
    public function get_frequency_updated_heading() {
        return __('Your Subscription Frequency Has Been Updated', 'bocs-wordpress');
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
     * @param string $bocs_id Optional. The Bocs ID that was switched to
     * @param string $frequency_id Optional. The frequency ID that was chosen
     * @param bool $is_box_update Optional. Whether this is a box content update rather than a plan switch
     * @param bool $is_frequency_update Optional. Whether this is a frequency update
     * @return void
     */
    public function trigger($subscription_data, $bocs_id = '', $frequency_id = '', $is_box_update = false, $is_frequency_update = false) {
        // Enhanced logging for debugging
        error_log('BOCS EMAIL DEBUG: Email trigger method called');
        
        // Setup localization
        $this->setup_locale();
        
        // Check if we have valid subscription data
        if (empty($subscription_data) || !is_array($subscription_data)) {
            error_log('BOCS EMAIL DEBUG: Invalid subscription data - ' . print_r($subscription_data, true));
            $this->restore_locale();
            return;
        }
        
        // Set object and email recipient
        $this->object = $subscription_data;
        
        // Get recipient email from subscription data
        $this->recipient = isset($subscription_data['customer']) && isset($subscription_data['customer']['email']) 
            ? $subscription_data['customer']['email'] 
            : '';
        
        error_log('BOCS EMAIL DEBUG: Recipient set to: ' . $this->recipient);
        
        // Skip if no recipient
        if (!$this->recipient) {
            error_log('BOCS EMAIL DEBUG: No recipient found, aborting email');
            $this->restore_locale();
            return;
        }
        
        // Set the placeholders for email template
        $this->placeholders['{subscription_id}'] = $subscription_data['id'] ?? '';
        
        // Set the Bocs ID (if available)
        $this->bocs_id = $bocs_id ?: ($subscription_data['bocs']['id'] ?? '');
        
        // Use different subject and heading based on the update type
        if ($is_frequency_update) {
            $this->heading = $this->get_frequency_updated_heading();
            $this->subject = $this->get_frequency_updated_subject();
            error_log('BOCS EMAIL DEBUG: Using frequency update template');
        } else if ($is_box_update) {
            $this->heading = $this->get_box_updated_heading();
            $this->subject = $this->get_box_updated_subject();
            error_log('BOCS EMAIL DEBUG: Using box update template');
        } else {
            $this->heading = $this->get_default_heading();
            $this->subject = $this->get_default_subject();
            error_log('BOCS EMAIL DEBUG: Using default template');
        }
        
        // Check if email is enabled
        error_log('BOCS EMAIL DEBUG: Email enabled status: ' . ($this->is_enabled() ? 'YES' : 'NO'));
        error_log('BOCS EMAIL DEBUG: Email recipient: ' . $this->get_recipient());
        
        // Send the email if enabled
        if ($this->is_enabled() && $this->get_recipient()) {
            error_log('BOCS EMAIL DEBUG: Attempting to send email to: ' . $this->get_recipient());
            
            // Get email content
            $html_content = $this->get_content_html();
            error_log('BOCS EMAIL DEBUG: Email content length: ' . strlen($html_content));
            
            // Check WP mail configuration
            $mailserver_url = ini_get('SMTP') ?: 'Not set';
            $mailserver_port = ini_get('smtp_port') ?: 'Not set';
            $default_from = get_option('admin_email') ?: 'Not set';
            
            error_log('BOCS EMAIL DEBUG: Mail configuration - SMTP: ' . $mailserver_url . ', Port: ' . $mailserver_port . ', From: ' . $default_from);
            error_log('BOCS EMAIL DEBUG: WordPress mail function exists: ' . (function_exists('wp_mail') ? 'Yes' : 'No'));
            
            // Check if a mail plugin is active
            $active_plugins = get_option('active_plugins');
            $mail_plugins = array_filter($active_plugins, function($plugin) {
                return (
                    stripos($plugin, 'mail') !== false || 
                    stripos($plugin, 'smtp') !== false || 
                    stripos($plugin, 'post') !== false
                );
            });
            
            if (!empty($mail_plugins)) {
                error_log('BOCS EMAIL DEBUG: Mail related plugins found: ' . implode(', ', $mail_plugins));
            } else {
                error_log('BOCS EMAIL DEBUG: No mail related plugins found');
            }
            
            // Send email and check result
            $send_result = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
            error_log('BOCS EMAIL DEBUG: Email send result: ' . ($send_result ? 'SUCCESS' : 'FAILED'));
            
            // Try direct wp_mail as a fallback if WooCommerce email fails
            if (!$send_result && function_exists('wp_mail')) {
                error_log('BOCS EMAIL DEBUG: Attempting fallback with direct wp_mail');
                $direct_result = wp_mail(
                    $this->get_recipient(), 
                    'DIRECT TEST - ' . $this->get_subject(), 
                    'This is a direct test of the WordPress mail system. If you received this, it means WooCommerce email is failing but direct WordPress mail works.' . "\n\n" . $this->get_content_plain(),
                    $this->get_headers()
                );
                error_log('BOCS EMAIL DEBUG: Direct wp_mail result: ' . ($direct_result ? 'SUCCESS' : 'FAILED'));
            }
            
            // Log that we sent the email
            $message = '';
            if ($is_frequency_update) {
                $message = __('Frequency updated email notification sent to customer.', 'bocs-wordpress');
            } else if ($is_box_update) {
                $message = __('Box updated email notification sent to customer.', 'bocs-wordpress');
            } else {
                $message = __('Subscription switched email notification sent to customer.', 'bocs-wordpress');
            }
            error_log($message);
        } else {
            error_log('BOCS EMAIL DEBUG: Email not sent - Enabled: ' . ($this->is_enabled() ? 'YES' : 'NO') . ', Recipient: ' . $this->get_recipient());
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
                    'subscription'     => $this->object,
                    'email_heading'    => $this->get_heading(),
                    'additional_content' => $this->get_additional_content(),
                    'email'             => $this,
                    'bocs_id'          => $this->bocs_id,
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
                    'subscription'     => $this->object,
                    'email_heading'    => $this->get_heading(),
                    'additional_content' => $this->get_additional_content(),
                    'email'            => $this,
                    'bocs_id'          => $this->bocs_id,
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
        return __('Thank you for choosing Bocs. If you have any questions about your updated subscription, please contact us.', 'bocs-wordpress');
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
            'additional_content' => array(
                'title'         => __('Additional Content', 'bocs-wordpress'),
                'description'   => __('Text to appear below the main email content.', 'bocs-wordpress') . ' ' . sprintf(__('Leave blank to use the default content: <code>%s</code>.', 'bocs-wordpress'), $this->get_default_additional_content()),
                'type'          => 'textarea',
                'default'       => $this->get_default_additional_content(),
                'placeholder'   => $this->get_default_additional_content(),
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