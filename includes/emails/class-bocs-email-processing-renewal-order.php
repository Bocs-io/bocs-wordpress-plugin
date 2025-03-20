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

if (!class_exists('WC_Bocs_Email_Processing_Renewal_Order')):

/**
 * Class WC_Bocs_Email_Processing_Renewal_Order
 *
 * @package     Bocs\Emails
 * @version     0.0.118
 * @since       0.0.118
 * @author      Bocs
 * @category    Emails
 */
class WC_Bocs_Email_Processing_Renewal_Order extends WC_Email {

    /**
     * Bocs ID associated with this order.
     *
     * @var string
     */
    public $bocs_id;

    /**
     * The order object.
     *
     * @var WC_Order
     */
    public $object;

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
            '{site_title}'   => $this->get_blogname(),
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
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('[Bocs] Processing Renewal Order', 'bocs-wordpress');
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
                    return;
                }
                
                // Set recipient
                $this->recipient = $this->object->get_billing_email();
                
                // Setup placeholders
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
                'description' => __('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>[Bocs] Processing Renewal Order</code>.', 'bocs-wordpress'),
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
         * 1. This email is specifically for subscription renewal orders
         *    and does NOT override WooCommerce default order emails
         * 
         * 2. Duplicate emails are prevented through:
         *    - Static tracking in the trigger() method to prevent multiple calls
         *    - Early return in the trigger() method for non-renewal orders
         * 
         * 3. This email template is for subscription renewal orders only
         *    and extends WC_Email directly for cleaner separation from standard emails
         */
    }
}

endif;

return new WC_Bocs_Email_Processing_Renewal_Order();
