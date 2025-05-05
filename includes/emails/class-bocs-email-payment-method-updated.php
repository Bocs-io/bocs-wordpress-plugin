<?php
/**
 * Class WC_Bocs_Email_Payment_Method_Updated
 *
 * @package     Bocs\Emails
 * @version     1.0.0
 * @since       1.0.0
 * @category    Emails
 */

defined('ABSPATH') || exit;

// Load WooCommerce email classes if not already loaded
if (!class_exists('WC_Email', false)) {
    include_once WC_ABSPATH . 'includes/emails/class-wc-email.php';
}

if (!class_exists('WC_Bocs_Email_Payment_Method_Updated')):

/**
 * Payment Method Updated Email
 *
 * An email sent to the customer when they add or update a payment method on the customer portal.
 * This handles the email notification sent to the customer after their payment method is updated.
 *
 * @class       WC_Bocs_Email_Payment_Method_Updated
 * @extends     WC_Email
 * @package     Bocs\Emails
 */
class WC_Bocs_Email_Payment_Method_Updated extends WC_Email {
    
    /**
     * Payment method details
     * 
     * @var array
     */
    protected $payment_method = array();
    
    /**
     * User data
     * 
     * @var WP_User
     */
    protected $user = null;

    /**
     * Initializes email parameters and settings.
     */
    public function __construct() {
        $this->id             = 'bocs_payment_method_updated';
        $this->title          = __('[Bocs Customer] Payment Method Added or Updated', 'bocs-wordpress');
        $this->description    = __('This email is sent to customers when they add or update a payment method on the customer portal.', 'bocs-wordpress');
        $this->customer_email = true;
        $this->placeholders   = array();
        
        $this->template_html  = 'emails/bocs-customer-payment-method-updated.php';
        $this->template_plain = 'emails/plain/bocs-customer-payment-method-updated.php';
        
        $this->default_subject = __('[Bocs] Your payment method has been updated', 'bocs-wordpress');
        $this->default_heading = __('Payment Method Updated', 'bocs-wordpress');

        // Call parent constructor
        parent::__construct();
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
     * @return string Default email subject
     */
    public function get_default_subject() {
        return $this->default_subject;
    }

    /**
     * Get email heading.
     *
     * @return string Default email heading
     */
    public function get_default_heading() {
        return $this->default_heading;
    }

    /**
     * Trigger the sending of this email.
     *
     * @param array   $payment_method The payment method data
     * @param WP_User $user           The user who updated the payment method
     */
    public function trigger($payment_method, $user) {
        if (!$this->is_enabled()) {
            return;
        }

        $this->payment_method = $payment_method;
        $this->user = $user;
        $this->recipient = $user->user_email;

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send(
                $this->get_recipient(),
                $this->get_subject(),
                $this->get_content(),
                $this->get_headers(),
                $this->get_attachments()
            );

            // Log that we sent the email
            error_log(sprintf(
                'Payment method updated email sent to %s', 
                $this->get_recipient()
            ));
        }
    }

    /**
     * Get content html.
     *
     * @return string Email HTML content
     */
    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            array(
                'payment_method'     => $this->payment_method,
                'user'               => $this->user,
                'email_heading'      => $this->get_heading(),
                'email'              => $this,
            ),
            '',
            $this->template_base
        );
    }

    /**
     * Get content plain.
     *
     * @return string Email plain text content
     */
    public function get_content_plain() {
        return wc_get_template_html(
            $this->template_plain,
            array(
                'payment_method'     => $this->payment_method,
                'user'               => $this->user,
                'email_heading'      => $this->get_heading(),
                'email'              => $this,
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
        return __('Thank you for using our service.', 'bocs-wordpress');
    }

    /**
     * Get the email template header.
     *
     * @param string $email_heading Heading for the email.
     * @return string
     */
    public function get_template_header($email_heading) {
        ob_start();
        wc_get_template(
            'emails/email-header.php',
            array(
                'email_heading' => $email_heading,
            )
        );
        return ob_get_clean();
    }

    /**
     * Get the email template footer.
     *
     * @return string
     */
    public function get_template_footer() {
        ob_start();
        wc_get_template(
            'emails/email-footer.php',
            array()
        );
        return ob_get_clean();
    }

    /**
     * Initialize form fields for the email settings
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled'            => array(
                'title'         => __('Enable/Disable', 'bocs-wordpress'),
                'type'          => 'checkbox',
                'label'         => __('Enable this email notification', 'bocs-wordpress'),
                'default'       => 'yes',
            ),
            'subject'            => array(
                'title'         => __('Subject', 'bocs-wordpress'),
                'type'          => 'text',
                'desc_tip'      => true,
                'description'   => __('This controls the email subject line. Leave blank to use the default subject: <code>[Bocs] Your payment method has been updated</code>.', 'bocs-wordpress'),
                'placeholder'   => $this->get_default_subject(),
                'default'       => '',
            ),
            'heading'            => array(
                'title'         => __('Email Heading', 'bocs-wordpress'),
                'type'          => 'text',
                'desc_tip'      => true,
                'description'   => __('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>Payment Method Updated</code>.', 'bocs-wordpress'),
                'placeholder'   => $this->get_default_heading(),
                'default'       => '',
            ),
            'email_type'         => array(
                'title'         => __('Email type', 'bocs-wordpress'),
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

endif;

// Returns a new instance of the email when called by the woocommerce_email hook
return new WC_Bocs_Email_Payment_Method_Updated(); 