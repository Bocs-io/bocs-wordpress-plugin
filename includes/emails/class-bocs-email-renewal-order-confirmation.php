<?php
/**
 * Bocs Renewal Order Confirmation Email Class
 *
 * Handles email notifications for renewal orders going from Pending payment to Processing.
 *
 * @package    Bocs
 * @subpackage Bocs/includes/emails
 * @since      0.0.1
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

if (!class_exists('WC_Bocs_Email_Renewal_Order_Confirmation')):

/**
 * Class WC_Bocs_Email_Renewal_Order_Confirmation
 *
 * @package     Bocs\Emails
 * @version     0.0.1
 * @since       0.0.1
 * @author      Bocs
 * @category    Emails
 */
class WC_Bocs_Email_Renewal_Order_Confirmation extends WC_Email {

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
     * @since 0.0.1
     */
    public function __construct() {
        $this->id             = 'bocs_renewal_order_confirmation';
        $this->customer_email = true;
        $this->title          = __('[Bocs Customer] Renewal Order Confirmation', 'bocs-wordpress');
        $this->description    = __('When a renewal order goes from Pending payment to Processing', 'bocs-wordpress');
        $this->template_html  = 'emails/bocs-customer-renewal-order-confirmation.php';
        $this->template_plain = 'emails/plain/bocs-customer-renewal-order-confirmation.php';
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
     * Defines the default subject line for the renewal order confirmation email.
     *
     * @since 0.0.1
     * @return string Default email subject
     */
    public function get_default_subject() {
        return __('[Bocs] Your {site_title} renewal order has been confirmed!', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     * 
     * Defines the default heading for the renewal order confirmation email.
     *
     * @since 0.0.1
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('[Bocs] Renewal Order Confirmation', 'bocs-wordpress');
    }

    /**
     * Trigger the sending of this email.
     *
     * @since 0.0.1
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
        
        // Check for transient to prevent duplicate emails across multiple PHP executions
        $email_sent_transient = 'bocs_renewal_email_sent_' . $order_id;
        if (get_transient($email_sent_transient)) {
            return;
        }
        
        if ($order_id) {
            $this->object = $order ? $order : wc_get_order($order_id);
            if (is_a($this->object, 'WC_Order')) {
                // Check if the order has the required metadata
                $bocs_subscription_id = get_post_meta($order_id, '__bocs_subscription_id', true);
                $bocs_order_status = get_post_meta($order_id, '__bocs_order_status', true);
                
                // Skip if the Bocs order status is not "upcoming"
                if (empty(bocs_subscription_id)) {
                    return;
                }
                
                // Check if the email has already been sent (persistent meta)
                $email_sent = get_post_meta($order_id, '_bocs_renewal_confirmation_email_sent', true);
                if ($email_sent === 'yes') {
                    return;
                }
                
                // Update the Bocs order status to "processing"
                update_post_meta($order_id, '__bocs_order_status', 'processing');
                
                // Update order via Bocs API
                $this->update_order_in_bocs($order_id);
                
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
            $sent = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
            if ($sent && $order_id) {
                // Mark as sent in post meta for permanent record
                update_post_meta($order_id, '_bocs_renewal_confirmation_email_sent', 'yes');
                
                // Set transient to prevent duplicate emails for 1 hour
                set_transient($email_sent_transient, true, HOUR_IN_SECONDS);
            }
        }

        $this->restore_locale();
    }

    /**
     * Update the order status in Bocs API
     *
     * @since 0.0.1
     * @param int $order_id The order ID
     * @return bool Whether the update was successful
     */
    private function update_order_in_bocs($order_id) {
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
                    $metaData[$key]['value'] = 'processing';
                    $found_meta = true;
                    break;
                }
            }
            
            // If the meta doesn't exist, add it
            if (!$found_meta) {
                $metaData[] = array(
                    'key' => '__bocs_order_status',
                    'value' => 'processing'
                );
            }
            
            // Update the order data
            $bocs_order['metaData'] = $metaData;
            $bocs_order['orderStatus'] = $wc_status;
            $bocs_order['status'] = 'processing';
            
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
            
            return $update_http_code >= 200 && $update_http_code < 300;
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get content html.
     *
     * @since 0.0.1
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
                'bocs_id'            => $this->bocs_id,
            ),
            '',
            $this->template_base
        );
    }

    /**
     * Get content plain.
     *
     * @since 0.0.1
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
                'bocs_id'            => $this->bocs_id,
            ),
            '',
            $this->template_base
        );
    }

    /**
     * Default content to show below main email content.
     *
     * @since 0.0.1
     * @return string Default additional content
     */
    public function get_default_additional_content() {
        return __('Thanks for your continued business.', 'bocs-wordpress');
    }

    /**
     * Initialise settings form fields.
     *
     * @since 0.0.1
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
                'description' => __('This controls the email subject line. Leave blank to use the default subject: <code>[Bocs] Your {site_title} renewal order has been confirmed!</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'            => array(
                'title'       => __('Email Heading', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>[Bocs] Renewal Order Confirmation</code>.', 'bocs-wordpress'),
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
     * Explanation of email functionality for WooCommerce settings.
     *
     * @since 0.0.1
     * @return string
     */
    public static function email_functionality_explanation() {
        return __('This email is sent to customers when their renewal order transitions from Pending payment to Processing and has the __bocs_order_status=upcoming meta set. It also updates the order status via the BOCS API.', 'bocs-wordpress');
    }
}

endif; 