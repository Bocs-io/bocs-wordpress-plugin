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
        
        // Disable default WooCommerce processing email for Bocs renewal orders
        add_action('woocommerce_email_before_order_table', array($this, 'maybe_disable_wc_processing_email'), 5, 4);
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
    public function trigger($order_id, $order = null) {
        // If this is a WC_Order object, extract the ID directly
        if (is_object($order_id) && method_exists($order_id, 'get_id')) {
            $order_id = $order_id->get_id();
        }
        // If we have a string that looks like JSON, try to parse it
        else if (is_string($order_id) && (strpos($order_id, '{') === 0 || strpos($order_id, '[') === 0)) {
            // This appears to be a JSON object/array, attempt to extract ID
            try {
                $order_data = json_decode($order_id, true);
                if (is_array($order_data) && isset($order_data['id'])) {
                    $order_id = $order_data['id'];
                } else {
                    // Fallback: check if the order parameter is valid
                    if (is_object($order) && method_exists($order, 'get_id')) {
                        $order_id = $order->get_id();
                    } else {
                        $order_id = 0; // Set to invalid value
                    }
                }
            } catch (Exception $e) {
                // Fallback: check if the order parameter is valid
                if (is_object($order) && method_exists($order, 'get_id')) {
                    $order_id = $order->get_id();
                } else {
                    $order_id = 0; // Set to invalid value
                }
            }
        }
        
        // Ensure order_id is an integer
        $order_id = is_numeric($order_id) ? (int)$order_id : 0;
        
        if ($order_id === 0) {
            return;
        }
        
        if (!$this->is_enabled()) {
            return;
        }

        $this->setup_locale();

        if (!$order_id) {
            $this->restore_locale();
            return;
        }

        $this->object = $order ? $order : wc_get_order($order_id);
        
        if (!is_a($this->object, 'WC_Order')) {
            $this->restore_locale();
            return;
        }

        // Define the email transient name
        $email_sent_transient = 'bocs_renewal_email_sent_' . $order_id;

        // Explicitly check if the order status is 'processing' - only send for processing status
        if ($this->object->get_status() !== 'processing') {
            delete_transient($email_sent_transient);
            $this->restore_locale();
            return;
        }

        // Check if the order has the required metadata
        $bocs_subscription_id = $this->object->get_meta('__bocs_subscription_id');
        $bocs_order_status = $this->object->get_meta('__bocs_order_status');
        $source_type = $this->object->get_meta('_wc_order_attribution_source_type');
        $utm_source = $this->object->get_meta('_wc_order_attribution_utm_source');

        // Skip if no subscription ID
        if (empty($bocs_subscription_id)) {
            $this->restore_locale();
            return;
        }
        
        // Skip if __bocs_order_status is not 'upcoming'
        if (empty($bocs_order_status) || $bocs_order_status !== 'upcoming') {
            $this->restore_locale();
            return;
        }

        // Check if the email has already been sent
        $email_sent = $this->object->get_meta('_bocs_renewal_confirmation_email_sent');
        
        // Check transient to prevent duplicate processing
        if (get_transient($email_sent_transient)) {
            $this->restore_locale();
            return;
        }
        
        // Set temporary transient to prevent duplicate emails
        set_transient($email_sent_transient, 'sending', 60);
        
        // Update the Bocs order status to match WooCommerce status
        $this->object->update_meta_data('__bocs_order_status', 'processing');
        $this->object->save();

        // Update order via Bocs API
        $api_updated = $this->update_order_in_bocs_api($order_id);
        
        // Set recipient
        $this->recipient = $this->object->get_billing_email();
        if (!$this->recipient) {
            $this->restore_locale();
            return;
        }

        // Setup placeholders
        $this->placeholders['{order_date}'] = wc_format_datetime($this->object->get_date_created());
        $this->placeholders['{order_number}'] = $this->object->get_order_number();

        // Set the Bocs ID for the email template
        $bocs_bocs_id = $this->object->get_meta('__bocs_bocs_id');
        $bocs_id = $this->object->get_meta('__bocs_id');
        
        if (!empty($bocs_bocs_id)) {
            $this->bocs_id = $bocs_bocs_id;
        } elseif (!empty($bocs_id)) {
            $this->bocs_id = $bocs_id;
        } elseif (!empty($bocs_subscription_id)) {
            $this->bocs_id = $bocs_subscription_id;
        }
        
        try {
            if ($this->is_enabled() && $this->get_recipient()) {
                $sent = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
                
                if ($sent) {
                    // Mark as sent in post meta
                    $this->object->update_meta_data('_bocs_renewal_confirmation_email_sent', 'yes');
                    $this->object->save();
                    // Delete the transient since we've now set the permanent meta
                    delete_transient($email_sent_transient);
                } else {
                    // Delete the transient to allow retry
                    delete_transient($email_sent_transient);
                }
            }
        } catch (Exception $e) {
            error_log('BOCS DEBUG [Renewal Order Confirmation]: Exception while sending email: ' . $e->getMessage());
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
    private function update_order_in_bocs_api($order_id) {
        // Get order data
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        // Get order status and Bocs ID
        $wc_status = $order->get_status();
        $bocs_subscription_id = $order->get_meta('__bocs_subscription_id');
        
        // Don't proceed if we don't have subscription ID
        if (empty($bocs_subscription_id)) {
            return false;
        }
        
        // Get API credentials
        $options = get_option('bocs_plugin_options');
        if (!isset($options['bocs_headers'])) {
            $options['bocs_headers'] = array();
        }
        
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
            $metaData = isset($bocs_order['metaData']) ? $bocs_order['metaData'] : array();
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
                CURLOPT_URL => BOCS_API_URL . 'orders/' . $bocs_order['id'],
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

    /**
     * Disable the default WooCommerce processing email for Bocs renewal orders
     *
     * @since 0.0.1
     * @param WC_Order $order Order object
     * @param bool $sent_to_admin Whether the email is being sent to admin
     * @param bool $plain_text Whether the email is plain text
     * @param WC_Email $email The email object
     */
    public function maybe_disable_wc_processing_email($order, $sent_to_admin, $plain_text, $email) {
        // Only proceed if this is the WooCommerce processing email
        if (!is_a($email, 'WC_Email_Customer_Processing_Order')) {
            return;
        }
        
        // Check if this is a Bocs renewal order
        $bocs_subscription_id = $order->get_meta('__bocs_subscription_id');
        $bocs_order_status = $order->get_meta('__bocs_order_status');
        
        // If this is a Bocs renewal order, disable the default processing email
        if (!empty($bocs_subscription_id) && !empty($bocs_order_status)) {
            add_filter('woocommerce_email_enabled_customer_processing_order', '__return_false', 999);
        }
    }
}

endif; 