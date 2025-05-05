<?php
/**
 * Class WC_Bocs_Email_Failed_Payment_Retry
 *
 * @package     Bocs\Emails
 * @version     0.0.119
 * @since       0.0.119
 * @author      Bocs
 * @category    Emails
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
    
    // If parent class still doesn't exist after attempting to load, log error and return
    if (!class_exists('WC_Email', false)) {
        return;
    }
}

if (!class_exists('WC_Bocs_Email_Failed_Payment_Retry')) :

/**
 * Failed Payment Retry Email
 *
 * An email sent to the customer when a renewal order goes from Pending payment to Failed payment.
 * This notification informs customers about failed payment retry attempts
 * and provides instructions for updating their payment method to maintainBocs Assistance
 * uninterrupted service.
 *
 * @class       WC_Bocs_Email_Failed_Payment_Retry
 * @version     0.0.119
 * @package     Bocs\Emails
 * @extends     WC_Email
 */
class WC_Bocs_Email_Failed_Payment_Retry extends WC_Email {

    /**
     * Constructor
     *
     * Initializes email parameters and settings.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->id             = 'bocs_failed_payment_retry';
        $this->customer_email = true;
        $this->title          = __('[Bocs Customer] Failed Payment Retry', 'bocs-wordpress');
        $this->description    = __('When a renewal order goes from Pending payment to Processing to Failed payment', 'bocs-wordpress');
        $this->template_html  = 'emails/bocs-customer-failed-payment-retry.php';
        $this->template_plain = 'emails/plain/bocs-customer-failed-payment-retry.php';
        $this->template_base  = BOCS_TEMPLATE_PATH;
        $this->placeholders   = array(
            '{order_date}'   => '',
            '{order_number}' => '',
        );

        // Call parent constructor
        parent::__construct();
        
        // Prevent default WooCommerce failed payment emails if this one is sent
        add_action('woocommerce_email_before_send', array($this, 'maybe_prevent_default_emails'), 10, 3);
        
        // Disable WooCommerce default failed order emails for BOCS subscription orders
        add_action('init', array($this, 'disable_wc_failed_order_emails'), 20);
        
        // More aggressive prevention of failed order emails
        add_filter('woocommerce_email_enabled_failed_order', array($this, 'disable_failed_order_email_for_bocs'), 10, 2);
        add_filter('woocommerce_email_enabled_customer_failed_order', array($this, 'disable_failed_order_email_for_bocs'), 10, 2);
        add_filter('woocommerce_email_recipient_failed_order', array($this, 'filter_failed_order_email_recipient'), 10, 2);
        add_filter('woocommerce_email_recipient_customer_failed_order', array($this, 'filter_failed_order_email_recipient'), 10, 2);
    }

    /**
     * Get email subject.
     *
     * @since 1.0.0
     * @return string Default email subject
     */
    public function get_default_subject() {
        return __('Payment retry failed for order {order_number}', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     *
     * @since 1.0.0
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('Payment Retry Failed', 'bocs-wordpress');
    }

    /**
     * Trigger the sending of this email.
     *
     * @since 1.0.0
     * @param int $order_id The order ID.
     * @return void
     */
    public function trigger($order_id) {
        //error_log('BOCS DEBUG [Failed Payment Retry]: Trigger called for order ID: ' . $order_id);
        //error_log('BOCS DEBUG [Failed Payment Retry]: Current hook: ' . current_filter());
        //error_log('BOCS DEBUG [Failed Payment Retry]: Backtrace: ' . print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5), true));
        
        if (!$this->is_enabled()) {
            //error_log('BOCS DEBUG [Failed Payment Retry]: Email is disabled in WooCommerce settings');
            return;
        }
        
        $this->setup_locale();

        if (!$order_id) {
            //error_log('BOCS DEBUG [Failed Payment Retry]: No order ID provided');
            $this->restore_locale();
            return;
        }

        $order = wc_get_order($order_id);
        
        if (!is_a($order, 'WC_Order')) {
            //error_log('BOCS DEBUG [Failed Payment Retry]: Could not find order object for ID: ' . $order_id);
            $this->restore_locale();
            return;
        }

        //error_log('BOCS DEBUG [Failed Payment Retry]: Found order object for ID: ' . $order_id . ', order status: ' . $order->get_status());

        // Get required meta values first
        $bocs_order_status = $order->get_meta('__bocs_order_status');
        $bocs_subscription_id = $order->get_meta('__bocs_subscription_id');
        $source_type = $order->get_meta('_wc_order_attribution_source_type');
        $utm_source = $order->get_meta('_wc_order_attribution_utm_source');

        //error_log('BOCS DEBUG [Failed Payment Retry]: Meta values - status: ' . $bocs_order_status . 
        //         ', subscription_id: ' . $bocs_subscription_id . 
        //         ', source_type: ' . $source_type . 
        //         ', utm_source: ' . $utm_source);

        // Check order details
        $line_items = $order->get_items();
        $order_total = $order->get_total();
        
        //error_log('BOCS DEBUG [Failed Payment Retry]: Order details - ' . 
        //         'Line items count: ' . count($line_items) . 
         //        ', Order total: ' . $order_total);

        // Only skip if there are no line items AND no subscription ID
        /*if (empty($line_items) && empty($bocs_subscription_id)) {
            //error_log('BOCS DEBUG [Failed Payment Retry]: Order has no line items and no subscription ID, skipping email');
            $this->restore_locale();
            return;
        }

        if (!empty($line_items)) {
            //error_log('BOCS DEBUG [Failed Payment Retry]: Order line items: ' . print_r(array_map(function($item) {
                return array(
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total' => $item->get_total()
                );
            }, $line_items), true));
        } else {
            //error_log('BOCS DEBUG [Failed Payment Retry]: Order has no line items but has subscription ID ' . $bocs_subscription_id . ', proceeding with email');
        }*/

        // Check if this is a failed payment order based on BOCS meta
        $is_failed_payment = false;
        if ((!empty($bocs_subscription_id) && 
            ($bocs_order_status === 'failed' || $order->get_status() === 'failed'))) {
            $is_failed_payment = true;
            //error_log('BOCS DEBUG [Failed Payment Retry]: Found failed payment order with subscription ID: ' . $bocs_subscription_id);
        }
        
        //error_log('BOCS DEBUG [Failed Payment Retry]: Is failed payment order: ' . ($is_failed_payment ? 'Yes' : 'No'));
        
        if (!$is_failed_payment) {
            //error_log('BOCS DEBUG [Failed Payment Retry]: Not a failed payment order, skipping');
            $this->restore_locale();
            return;
        }

        // Check if email has already been sent
        $email_sent = $order->get_meta('_bocs_failed_payment_retry_email_sent');
        if ($email_sent === 'yes') {
            //error_log('BOCS DEBUG [Failed Payment Retry]: Email already sent for this order');
            $this->restore_locale();
            return;
        }

        // Set up email recipient
        $this->recipient = $order->get_billing_email();
        
        if (!$this->recipient) {
            //error_log('BOCS DEBUG [Failed Payment Retry]: No recipient email found');
            $this->restore_locale();
            return;
        }

        //error_log('BOCS DEBUG [Failed Payment Retry]: Attempting to send email to: ' . $this->recipient);

        // Set the order object for the template first
        $this->object = $order;
        //error_log('BOCS DEBUG [Failed Payment Retry]: Set order object for template');

        // Set up email placeholders
        $order_date = $order->get_date_created();
        if ($order_date) {
            $this->placeholders['{order_date}'] = $order_date->format(wc_date_format());
        } else {
            $this->placeholders['{order_date}'] = date_i18n(wc_date_format());
        }
        
        $this->placeholders['{order_number}'] = $order->get_order_number();

        //error_log('BOCS DEBUG [Failed Payment Retry]: Email placeholders: ' . print_r($this->placeholders, true));

        // Get email content before sending
        //error_log('BOCS DEBUG [Failed Payment Retry]: Getting email content');
        $content_html = $this->get_content_html();
        //error_log('BOCS DEBUG [Failed Payment Retry]: Got HTML content, length: ' . strlen($content_html));
        
        $content_plain = $this->get_content_plain();
        //error_log('BOCS DEBUG [Failed Payment Retry]: Got plain content, length: ' . strlen($content_plain));
        
        $subject = $this->get_subject();
        //error_log('BOCS DEBUG [Failed Payment Retry]: Got subject: ' . $subject);
        
        $headers = $this->get_headers();
        //error_log('BOCS DEBUG [Failed Payment Retry]: Got headers: ' . print_r($headers, true));

        //error_log('BOCS DEBUG [Failed Payment Retry]: Attempting to send email with all content prepared');

        // Send the email
        try {
            $sent = $this->send($this->recipient, $subject, $content_html, $headers, $this->get_attachments());
            //error_log('BOCS DEBUG [Failed Payment Retry]: Send attempt completed, result: ' . ($sent ? 'success' : 'failed'));

            if ($sent) {
                //error_log('BOCS DEBUG [Failed Payment Retry]: Email sent successfully');
                $order->update_meta_data('_bocs_failed_payment_retry_email_sent', 'yes');
                $order->save();
                //error_log('BOCS DEBUG [Failed Payment Retry]: Updated order meta to mark email as sent');
            } else {
                //error_log('BOCS DEBUG [Failed Payment Retry]: Failed to send email');
                // Check WP Mail errors
                global $phpmailer;
                if (isset($phpmailer)) {
                    //error_log('BOCS DEBUG [Failed Payment Retry]: PHPMailer object exists');
                    if (is_wp_error($phpmailer->ErrorInfo)) {
                        //error_log('BOCS DEBUG [Failed Payment Retry]: WP Mail Error: ' . $phpmailer->ErrorInfo);
                    } else {
                        //error_log('BOCS DEBUG [Failed Payment Retry]: PHPMailer error info: ' . print_r($phpmailer->ErrorInfo, true));
                    }
                } else {
                    //error_log('BOCS DEBUG [Failed Payment Retry]: PHPMailer object not available');
                }
            }
        } catch (Exception $e) {
            //error_log('BOCS DEBUG [Failed Payment Retry]: Exception while sending email: ' . $e->getMessage());
        }

        $this->restore_locale();
        //error_log('BOCS DEBUG [Failed Payment Retry]: Finished processing failed payment retry email');
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
        return __('Thanks for using {site_title}! We hope to successfully process your payment soon.', 'bocs-wordpress');
    }

    /**
     * Initialise settings form fields.
     *
     * @since 1.0.0
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'bocs-wordpress'),
                'type'    => 'checkbox',
                'label'   => __('Enable this email notification', 'bocs-wordpress'),
                'default' => 'yes',
            ),
            'subject' => array(
                'title'       => __('Subject', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => sprintf(__('Available placeholders: %s', 'bocs-wordpress'), '{site_title}, {order_date}, {order_number}'),
                'placeholder' => $this->get_default_subject(),
                'default'     => $this->get_default_subject(),
            ),
            'heading' => array(
                'title'       => __('Email Heading', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => sprintf(__('Available placeholders: %s', 'bocs-wordpress'), '{site_title}, {order_date}, {order_number}'),
                'placeholder' => $this->get_default_heading(),
                'default'     => $this->get_default_heading(),
            ),
            'email_type' => array(
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
     * Update the order status in the BOCS API
     * 
     * @param int $order_id The order ID
     * @return bool Success status
     */
    private function update_order_in_bocs_api($order_id) {
        // Get order data
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        // Get order status and Bocs ID
        $wc_status = $order->get_status();
        $bocs_id = get_post_meta($order_id, '__bocs_id', true);
        if (empty($bocs_id)) {
            $bocs_id = get_post_meta($order_id, '__bocs_bocs_id', true);
        }
        
        // Don't proceed if we don't have Bocs ID
        if (empty($bocs_id)) {
            return false;
        }
        
        // Get API credentials
        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();
        
        // Don't proceed if we don't have credentials
        if (empty($options['bocs_headers']['organization']) || 
            empty($options['bocs_headers']['store']) || 
            empty($options['bocs_headers']['authorization'])) {
            return false;
        }
        
        // First, fetch the existing order data from the API
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => BOCS_API_URL . 'orders?query=externalSourceId:' . urlencode($order_id),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Organization: ' . $options['bocs_headers']['organization'],
                    'Content-Type: application/json',
                    'Store: ' . $options['bocs_headers']['store'],
                    'Authorization: ' . $options['bocs_headers']['authorization']
                )
            ));
            
            $response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            if ($http_code < 200 || $http_code >= 300) {
                return false;
            }
            
            // Parse the response
            $order_data = json_decode($response, true);
            
            // Check if we have valid data
            if (!isset($order_data['data']['data']) || !is_array($order_data['data']['data']) || count($order_data['data']['data']) === 0) {
                return false;
            }
            
            // Get the first order from the results
            $bocs_order = $order_data['data']['data'][0];
            
            // Update the order status in the metadata
            $metaData = $bocs_order['metaData'] ?? array();
            $found_meta = false;
            
            foreach ($metaData as $key => $meta) {
                if ($meta['key'] === '__bocs_order_status') {
                    $metaData[$key]['value'] = $wc_status;
                    $found_meta = true;
                    break;
                }
            }
            
            // If the meta doesn't exist, add it
            if (!$found_meta) {
                $metaData[] = array(
                    'key' => '__bocs_order_status',
                    'value' => $wc_status
                );
            }
            
            // Update the order data
            $bocs_order['metaData'] = $metaData;
            $bocs_order['orderStatus'] = $wc_status;
            $bocs_order['status'] = $wc_status;
            
            // Now send the updated order data back to the API
            $update_curl = curl_init();
            curl_setopt_array($update_curl, array(
                CURLOPT_URL => BOCS_API_URL . 'orders/' . $bocs_id,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => json_encode($bocs_order),
                CURLOPT_HTTPHEADER => array(
                    'Organization: ' . $options['bocs_headers']['organization'],
                    'Content-Type: application/json',
                    'Store: ' . $options['bocs_headers']['store'],
                    'Authorization: ' . $options['bocs_headers']['authorization']
                )
            ));
            
            $update_response = curl_exec($update_curl);
            $update_http_code = curl_getinfo($update_curl, CURLINFO_HTTP_CODE);
            curl_close($update_curl);
            
            $success = $update_http_code >= 200 && $update_http_code < 300;
            
            return $success;
            
        } catch (Exception $e) {
            return false;
        }
        
        return false;
    }

    /**
     * Prevent default WooCommerce failed payment emails if our email has been sent
     *
     * @param bool $send Whether to send the email
     * @param WC_Email $email The email object
     * @param mixed $email_args Email arguments
     * @return bool Whether to send the email
     */
    public function maybe_prevent_default_emails($send, $email, $email_args) {
        // Check if this is a failed order email from WooCommerce
        if ($send && in_array($email->id, array('failed_order', 'customer_failed_order')) && isset($email_args['order_id'])) {
            $order_id = $email_args['order_id'];
            $order = wc_get_order($order_id);
            
            if ($order) {
                // If our failed payment retry email was sent, prevent default failed emails
                $our_email_sent = $order->get_meta('_bocs_failed_payment_retry_email_sent');
                if ($our_email_sent === 'yes') {
                    return false;
                }
                
                // Also check if this is a BOCS subscription order
                $bocs_subscription_id = $order->get_meta('__bocs_subscription_id');
                if (!empty($bocs_subscription_id)) {
                    // This is a BOCS subscription order, our email will be sent
                    // Let's prevent the default email
                    return false;
                }
            }
        }
        
        return $send;
    }

    /**
     * Disable WooCommerce default failed order emails for BOCS orders
     */
    public function disable_wc_failed_order_emails() {
        // Get WooCommerce mailer
        $mailer = WC()->mailer();
        
        if ($mailer) {
            // Remove all failed order email actions to prevent any default emails from being sent
            remove_action('woocommerce_order_status_pending_to_failed_notification', array($mailer, 'failed_order'));
            remove_action('woocommerce_order_status_on-hold_to_failed_notification', array($mailer, 'failed_order'));
            remove_action('woocommerce_order_status_processing_to_failed_notification', array($mailer, 'failed_order'));
            
            // Remove customer_failed_order notifications
            remove_action('woocommerce_order_status_failed_notification', array($mailer->emails['WC_Email_Customer_Failed_Order'], 'trigger'));
            remove_action('woocommerce_order_status_pending_to_failed_notification', array($mailer->emails['WC_Email_Customer_Failed_Order'], 'trigger'));
            remove_action('woocommerce_order_status_on-hold_to_failed_notification', array($mailer->emails['WC_Email_Customer_Failed_Order'], 'trigger'));
            
            // Add our custom filter to check if it's a BOCS order before sending
            add_action('woocommerce_order_status_pending_to_failed_notification', array($this, 'maybe_send_wc_failed_order_email'), 10);
            add_action('woocommerce_order_status_on-hold_to_failed_notification', array($this, 'maybe_send_wc_failed_order_email'), 10);
            add_action('woocommerce_order_status_processing_to_failed_notification', array($this, 'maybe_send_wc_failed_order_email'), 10);
            
            // Handle direct trigger calls for failed orders
            if (isset($mailer->emails['WC_Email_Failed_Order'])) {
                // Override the trigger method to check for BOCS orders
                add_filter('woocommerce_email_enabled_failed_order', array($this, 'disable_failed_order_email_for_bocs'), 10, 2);
            }
            
            if (isset($mailer->emails['WC_Email_Customer_Failed_Order'])) {
                // Override the trigger method to check for BOCS orders
                add_filter('woocommerce_email_enabled_customer_failed_order', array($this, 'disable_failed_order_email_for_bocs'), 10, 2);
            }
        }
        
        // Global action to prevent any failed order emails from being sent for BOCS orders
        add_action('woocommerce_before_resend_order_emails', array($this, 'prevent_resend_failed_emails_for_bocs'), 10, 2);
    }

    /**
     * Conditionally send WooCommerce failed order email only for non-BOCS orders
     *
     * @param int $order_id Order ID
     */
    public function maybe_send_wc_failed_order_email($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Check if this is a BOCS subscription order
        $bocs_subscription_id = $order->get_meta('__bocs_subscription_id');
        
        // If not a BOCS subscription order, trigger the default WooCommerce failed order email
        if (empty($bocs_subscription_id)) {
            // Get WooCommerce mailer
            $mailer = WC()->mailer();
            
            if ($mailer) {
                $mailer->failed_order($order_id);
            }
        }
    }

    /**
     * Disable failed order emails for BOCS orders
     * 
     * @param bool $enabled Whether the email is enabled
     * @param WC_Order $order The order object
     * @return bool
     */
    public function disable_failed_order_email_for_bocs($enabled, $order) {
        if (is_object($order) && method_exists($order, 'get_meta')) {
            $bocs_subscription_id = $order->get_meta('__bocs_subscription_id');
            if (!empty($bocs_subscription_id)) {
                return false; // Disable the email for BOCS orders
            }
        }
        return $enabled;
    }

    /**
     * Filter the recipient email address for failed order emails
     * 
     * @param string $recipient The recipient email address
     * @param WC_Order $order The order object
     * @return string Empty string for BOCS orders, original recipient otherwise
     */
    public function filter_failed_order_email_recipient($recipient, $order) {
        if (is_object($order) && method_exists($order, 'get_meta')) {
            $bocs_subscription_id = $order->get_meta('__bocs_subscription_id');
            if (!empty($bocs_subscription_id)) {
                return ''; // Return empty recipient for BOCS orders
            }
        }
        return $recipient;
    }

    /**
     * Prevent manual resending of failed order emails for BOCS orders
     * 
     * @param WC_Order $order The order object
     * @param array $email_ids Email IDs being resent
     */
    public function prevent_resend_failed_emails_for_bocs($order, $email_ids) {
        if (!is_object($order) || !method_exists($order, 'get_meta')) {
            return;
        }
        
        $bocs_subscription_id = $order->get_meta('__bocs_subscription_id');
        if (!empty($bocs_subscription_id)) {
            // Remove failed order emails from the list
            foreach ($email_ids as $key => $email_id) {
                if (in_array($email_id, array('failed_order', 'customer_failed_order'))) {
                    unset($email_ids[$key]);
                }
            }
        }
    }
}

endif; 