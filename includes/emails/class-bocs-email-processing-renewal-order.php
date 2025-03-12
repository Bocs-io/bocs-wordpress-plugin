<?php
/**
 * Bocs Processing Renewal Order Email Class
 *
 * Handles email notifications for processing subscription renewal orders.
 *
 * @package    Bocs
 * @subpackage Bocs/includes/emails
 * @since      0.0.118
 */

if (!defined('ABSPATH')) {
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

// Load parent class for processing orders if not already loaded
if (!class_exists('WC_Email_Customer_Processing_Order', false)) {
    if (file_exists(WC_ABSPATH . 'includes/emails/class-wc-email-customer-processing-order.php')) {
        include_once WC_ABSPATH . 'includes/emails/class-wc-email-customer-processing-order.php';
    } else {
        // If the parent class cannot be found, log an error and return
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->error('Cannot load WC_Email_Customer_Processing_Order class. BOCS email classes cannot be initialized.');
        }
        return;
    }
}

if (!class_exists('WC_Bocs_Email_Processing_Renewal_Order') && class_exists('WC_Email_Customer_Processing_Order')):

/**
 * Class WC_Bocs_Email_Processing_Renewal_Order
 *
 * @package     Bocs\Emails
 * @version     0.0.118
 * @since       0.0.118
 * @author      Bocs
 * @category    Emails
 */
class WC_Bocs_Email_Processing_Renewal_Order extends WC_Email_Customer_Processing_Order {

    /**
     * Bocs ID associated with this order.
     *
     * @var string
     */
    public $bocs_id;

    /**
     * Constructor
     *
     * Initializes email parameters and settings.
     * Sets up the email's ID, title, description, and template paths.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->id             = 'bocs_processing_renewal_order';
        $this->customer_email = true;
        $this->title          = __('[Bocs] Processing Renewal Order', 'bocs-wordpress');
        $this->description    = __('Processing renewal order emails are sent to customers when their renewal order is processed.', 'bocs-wordpress');
        $this->template_html  = 'emails/bocs-customer-processing-renewal-order.php';
        $this->template_plain = 'emails/plain/bocs-customer-processing-renewal-order.php';
        $this->template_base  = BOCS_TEMPLATE_PATH;
        $this->placeholders   = array(
            '{order_date}'   => '',
            '{order_number}' => '',
        );

        // Call parent constructor
        parent::__construct();
    }

    /**
     * Get email subject.
     * 
     * Defines the default subject line for the processing renewal order email.
     *
     * @since 1.0.0
     * @since 3.1.0 in WooCommerce core
     * @return string Default email subject
     */
    public function get_default_subject() {
        return __('[Bocs] Your {site_title} renewal order has been received!', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     * 
     * Defines the default heading for the processing renewal order email.
     *
     * @since 1.0.0
     * @since 3.1.0 in WooCommerce core
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('Processing Renewal Order', 'bocs-wordpress');
    }

    /**
     * Trigger the sending of this email.
     *
     * @since 1.0.0
     * @param int $order_id The order ID.
     * @param WC_Order|bool $order Order object.
     * @return void
     */
    public function trigger($order_id, $order = false) {
        $this->setup_locale();

        // Static tracking to prevent duplicate emails
        static $processed_orders = array();
        
        // Skip if we've already processed this order
        if (in_array($order_id, $processed_orders)) {
            error_log("Bocs Renewal Email: Skipping duplicate processing for order #{$order_id}");
            return;
        }
        
        // Add to tracking array to prevent duplicate processing
        $processed_orders[] = $order_id;
        
        if ($order_id) {
            $this->object = $order ? $order : wc_get_order($order_id);
            if (is_a($this->object, 'WC_Order')) {
                // Check if this is a renewal order
                $is_renewal = get_post_meta($order_id, '_subscription_renewal', true);
                
                // Skip if it's not a renewal order
                if (empty($is_renewal)) {
                    error_log("Bocs Renewal Email: Skipping order #{$order_id} - not a renewal order");
                    return;
                }
                
                // Check parent subscription or order for Bocs attribution
                $parent_id = $is_renewal;
                if (empty($parent_id)) {
                    $parent_id = $order_id;
                }
                
                // Check if the order has the required meta data
                $source_type = get_post_meta($parent_id, '_wc_order_attribution_source_type', true);
                $utm_source = get_post_meta($parent_id, '_wc_order_attribution_utm_source', true);
                
                // Set recipient regardless of source to ensure renewal orders get emails
                $this->recipient = $this->object->get_billing_email();
                $this->placeholders['{order_date}'] = wc_format_datetime($this->object->get_date_created());
                $this->placeholders['{order_number}'] = $this->object->get_order_number();
                
                // Check for Bocs IDs - legacy support
                $bocs_bocs_id = get_post_meta($order_id, '__bocs_bocs_id', true);
                $bocs_id = get_post_meta($order_id, '__bocs_id', true);
                $bocs_subscription_id = get_post_meta($order_id, '__bocs_subscription_id', true);
                
                // Set the Bocs ID for the email template if available
                if (!empty($bocs_bocs_id)) {
                    $this->bocs_id = $bocs_bocs_id;
                } elseif (!empty($bocs_id)) {
                    $this->bocs_id = $bocs_id;
                } elseif (!empty($bocs_subscription_id)) {
                    $this->bocs_id = $bocs_subscription_id;
                }
                
                // Only log for debugging if not from Bocs App
                if ($source_type !== 'referral' || $utm_source !== 'Bocs App') {
                    error_log("Bocs Renewal Email: Processing order #{$order_id} without Bocs App attribution");
                }
            }
        }

        if ($this->is_enabled() && $this->get_recipient()) {
            error_log("Bocs Renewal Email: Sending email to {$this->get_recipient()} for order #{$order_id}");
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        } else {
            error_log("Bocs Renewal Email not sent - enabled: " . ($this->is_enabled() ? "yes" : "no") . ", recipient: " . $this->get_recipient());
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
                'order'              => $this->object,
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
                'order'              => $this->object,
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
        return __('Thanks for your continued business.', 'bocs-wordpress');
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
                'description' => __('This controls the email subject line. Leave blank to use the default subject: <code>[Bocs] Your {site_title} renewal order has been received!</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'            => array(
                'title'       => __('Email Heading', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>Processing Renewal Order</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
            ),
            'additional_content' => array(
                'title'       => __('Additional content', 'bocs-wordpress'),
                'description' => __('Text to appear below the main email content.', 'bocs-wordpress'),
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __('Thanks for your continued business.', 'bocs-wordpress'),
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

    /**
     * Helper method to document how Bocs subscription renewal order emails work
     * 
     * This method does nothing functional but serves as documentation.
     *
     * @since 1.0.0
     * @return void
     */
    public static function email_functionality_explanation() {
        /**
         * Bocs Subscription Renewal Order Email Functionality:
         * 
         * 1. WooCommerce default order emails are disabled for renewal orders through filters
         *    in Bocs_Email::disable_wc_emails() which hooks into 'woocommerce_email_enabled_{email_id}'
         * 
         * 2. Duplicate emails are prevented through:
         *    - Static tracking in the trigger() method to prevent multiple calls
         *    - Static flags in init_email_classes() methods to prevent duplicate hook registration
         *    - Early return in the trigger() method for non-renewal orders
         * 
         * 3. The email template is used exclusively for subscription renewal orders
         *    and includes inline styles matching the Bocs.io branding
         */
    }
}

endif;

return new WC_Bocs_Email_Processing_Renewal_Order();
