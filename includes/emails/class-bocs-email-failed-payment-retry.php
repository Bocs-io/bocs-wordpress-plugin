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
 * and provides instructions for updating their payment method to maintain
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
    }

    /**
     * Get email subject.
     *
     * @since 1.0.0
     * @return string Default email subject
     */
    public function get_default_subject() {
        return __('[Bocs] Payment retry failed for order {order_number}', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     *
     * @since 1.0.0
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('[Bocs] Payment Retry Failed', 'bocs-wordpress');
    }

    /**
     * Trigger the sending of this email.
     *
     * @since 1.0.0
     * @param int $order_id The order ID.
     * @return void
     */
    public function trigger($order_id) {
        $this->setup_locale();

        if ($order_id) {
            $this->object = wc_get_order($order_id);
            
            if (is_a($this->object, 'WC_Order')) {
                // Check if the order has the required Bocs meta fields
                $bocs_subscription_id = get_post_meta($order_id, '__bocs_subscription_id', true);
                
                // Skip if the required meta fields are empty
                if (empty($bocs_subscription_id)) {
                    $this->restore_locale();
                    return;
                }
                
                // Update the order via Bocs API to ensure it's in sync
                $this->update_order_in_bocs_api($order_id);
                
                // Set recipient
                $this->recipient = $this->object->get_billing_email();
                
                // Setup placeholders
                $this->placeholders['{order_date}'] = wc_format_datetime($this->object->get_date_created());
                $this->placeholders['{order_number}'] = $this->object->get_order_number();
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
            'additional_content' => array(
                'title'       => __('Additional Content', 'bocs-wordpress'),
                'description' => __('Text to appear below the main email content.', 'bocs-wordpress'),
                'css'         => 'width: 400px; height: 75px;',
                'placeholder' => $this->get_default_additional_content(),
                'type'        => 'textarea',
                'default'     => $this->get_default_additional_content(),
                'desc_tip'    => true,
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
}

endif; 