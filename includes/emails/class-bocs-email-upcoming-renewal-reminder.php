<?php
/**
 * Class WC_Bocs_Email_Upcoming_Renewal_Reminder
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
 * Upcoming Renewal Reminder Email
 *
 * An email sent to the customer a few days before a subscription renewal.
 * This email notifies customers about an upcoming subscription renewal payment
 * and provides details about the renewal date and payment amount.
 *
 * @class       WC_Bocs_Email_Upcoming_Renewal_Reminder
 * @version     0.0.118
 * @package     Bocs\Emails
 * @extends     WC_Email
 */
class WC_Bocs_Email_Upcoming_Renewal_Reminder extends WC_Email {

    /**
     * Constructor
     *
     * Initializes email parameters and settings.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->id             = 'bocs_upcoming_renewal_reminder';
        $this->customer_email = true;
        $this->title          = __('Upcoming Subscription Renewal Reminder', 'bocs-wordpress');
        $this->description    = __('When an order is created in Pending payment mode - with notes', 'bocs-wordpress');
        $this->template_html  = 'emails/bocs-customer-upcoming-renewal-reminder.php';
        $this->template_plain = 'emails/plain/bocs-customer-upcoming-renewal-reminder.php';
        $this->template_base  = BOCS_TEMPLATE_PATH;
        $this->placeholders   = array(
            '{subscription_date}'   => '',
            '{subscription_number}' => '',
            '{renewal_date}'        => '',
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
        return __('Your {site_title} subscription will renew soon', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     *
     * @since 1.0.0
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('Upcoming Subscription Renewal Reminder', 'bocs-wordpress');
    }

    /**
     * Trigger the sending of this email.
     *
     * @since 1.0.0
     * @param int    $id           The order ID.
     * @param string $renewal_date The date of the upcoming renewal (optional).
     * @return void
     */
    public function trigger($id, $renewal_date = '') {
        if (!$this->is_enabled()) {
            return;
        }
        
        $this->setup_locale();

        if (!$id) {
            $this->restore_locale();
            return;
        }

        $order = wc_get_order($id);
        
        if (!is_a($order, 'WC_Order')) {
            $this->restore_locale();
            return;
        }

        // Get required meta values
        $bocs_order_status = $order->get_meta('__bocs_order_status');
        $bocs_subscription_id = $order->get_meta('__bocs_subscription_id');
        $source_type = $order->get_meta('_wc_order_attribution_source_type');
        $utm_source = $order->get_meta('_wc_order_attribution_utm_source');

        // Check if this is a renewal order based on BOCS meta
        $is_renewal = false;
        if ($bocs_order_status === 'upcoming' && !empty($bocs_subscription_id)) {
            $is_renewal = true;
        }
        
        if (!$is_renewal) {
            $this->restore_locale();
            return;
        }

        // Check if email has already been sent
        $email_sent = $order->get_meta('_bocs_upcoming_renewal_reminder_email_sent');
        if ($email_sent === 'yes') {
            $this->restore_locale();
            return;
        }

        // Set up email recipient
        $this->recipient = $order->get_billing_email();
        
        if (!$this->recipient) {
            $this->restore_locale();
            return;
        }

        // Set the order object for the template first
        $this->object = $order;

        // Set up email placeholders
        $order_date = $order->get_date_created();
        if ($order_date) {
            $this->placeholders['{subscription_date}'] = $order_date->format(wc_date_format());
        } else {
            $this->placeholders['{subscription_date}'] = date_i18n(wc_date_format());
        }
        
        $this->placeholders['{subscription_number}'] = $order->get_order_number();
        $this->placeholders['{renewal_date}'] = $renewal_date ? date_i18n(wc_date_format(), strtotime($renewal_date)) : '';

        // Get email content before sending
        $content_html = $this->get_content_html();
        $content_plain = $this->get_content_plain();
        $subject = $this->get_subject();
        $headers = $this->get_headers();

        // Send the email
        $sent = $this->send($this->recipient, $subject, $content_html, $headers, $this->get_attachments());

        if ($sent) {
            $order->update_meta_data('_bocs_upcoming_renewal_reminder_email_sent', 'yes');
            $order->save();
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
        // If this is a preview, create a mock order
        if (is_admin() && empty($this->object)) {
            $this->object = $this->get_preview_order();
        }

        return wc_get_template_html(
            $this->template_html,
            array(
                'order'              => $this->object,
                'subscription'       => $this->object,
                'email_heading'      => $this->get_heading(),
                'sent_to_admin'      => false,
                'plain_text'         => false,
                'email'              => $this,
                'renewal_date'       => $this->placeholders['{renewal_date}'],
            ),
            $this->template_base,
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
        // If this is a preview, create a mock order
        if (is_admin() && empty($this->object)) {
            $this->object = $this->get_preview_order();
        }

        return wc_get_template_html(
            $this->template_plain,
            array(
                'order'              => $this->object,
                'subscription'       => $this->object,
                'email_heading'      => $this->get_heading(),
                'sent_to_admin'      => false,
                'plain_text'         => true,
                'email'              => $this,
                'renewal_date'       => $this->placeholders['{renewal_date}'],
            ),
            $this->template_base,
            $this->template_base
        );
    }

    /**
     * Create a mock WC_Order for previewing the email template.
     * 
     * @since 1.0.0
     * @return WC_Order
     */
    protected function get_preview_order() {
        // Try to get an existing order for preview
        $orders = wc_get_orders(array(
            'limit' => 1,
            'status' => array('completed', 'processing'),
            'return' => 'ids',
        ));

        if (!empty($orders)) {
            $order = wc_get_order($orders[0]);
            
            // Add required metadata for preview purposes
            $order->update_meta_data('__bocs_order_status', 'upcoming');
            $order->update_meta_data('__bocs_subscription_id', 'preview_sub_123');
            
            // Don't save these changes to the database
            
            // Set up placeholder renewal date for preview
            $this->placeholders['{renewal_date}'] = date_i18n(wc_date_format(), strtotime('+7 days'));
            
            return $order;
        } else {
            // If no real orders exist, create a mock order object
            $order = new WC_Order();
            
            // Set up basic order data for preview
            $order->set_billing_first_name('Sample');
            $order->set_billing_last_name('Customer');
            $order->set_billing_email('customer@example.com');
            $order->set_billing_address_1('123 Preview St');
            $order->set_billing_city('Sample City');
            $order->set_billing_state('CA');
            $order->set_billing_postcode('90210');
            $order->set_billing_country('US');
            $order->set_payment_method_title('Credit Card');
            $order->set_total('49.99');
            
            // Set mock metadata
            $order->update_meta_data('__bocs_order_status', 'upcoming');
            $order->update_meta_data('__bocs_subscription_id', 'preview_sub_123');
            
            // Set date created
            $order->set_date_created(time());
            
            // Add a sample product
            if (class_exists('WC_Order_Item_Product')) {
                $item = new WC_Order_Item_Product();
                $item->set_name('Sample Subscription Box');
                $item->set_quantity(1);
                $item->set_total(49.99);
                $order->add_item($item);
            }
            
            // Set up placeholder renewal date
            $this->placeholders['{renewal_date}'] = date_i18n(wc_date_format(), strtotime('+7 days'));
            
            return $order;
        }
    }

    /**
     * Default content to show below main email content.
     *
     * @since 1.0.0
     * @return string Default additional content
     */
    public function get_default_additional_content() {
        return __('If you wish to make any changes to your subscription before the renewal, please contact us.', 'bocs-wordpress');
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
                'description' => __('This controls the email subject line. Leave blank to use the default subject: <code>Your {site_title} subscription will renew soon</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'            => array(
                'title'       => __('Email Heading', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>Upcoming Subscription Renewal</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
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

    public function get_heading() {
        $heading = $this->get_option('heading');
        if (empty($heading)) {
            $heading = $this->get_default_heading();
        }
        return $this->format_string($heading);
    }
} 