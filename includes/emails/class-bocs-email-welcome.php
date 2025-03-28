<?php
/**
 * Legacy class for backward compatibility
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

// Load the new class if it's not already loaded
if (!class_exists('WC_Bocs_Email_Subscription_Confirmation')) {
    require_once plugin_dir_path(__FILE__) . 'class-bocs-email-subscription-confirmation.php';
}

/**
 * Legacy Welcome Email class - extends the new Subscription Confirmation class
 * 
 * This class is maintained for backward compatibility with existing code.
 * It inherits all functionality from the new WC_Bocs_Email_Subscription_Confirmation class.
 *
 * @class       WC_Bocs_Email_Welcome
 * @version     0.0.118
 * @package     Bocs\Emails
 * @extends     WC_Bocs_Email_Subscription_Confirmation
 */
class WC_Bocs_Email_Welcome extends WC_Bocs_Email_Subscription_Confirmation {
    /**
     * Constructor
     * 
     * Just calls the parent constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        parent::__construct();
        // ID remains 'bocs_welcome' for backward compatibility with existing records
        $this->id = 'bocs_welcome';
    }
    
    /**
     * Trigger the sending of this email - maintains backward compatibility
     * with the _bocs_welcome_email_sent meta field
     *
     * @since 1.0.0
     * @param int $order_id The order ID.
     * @param WC_Order|bool $order Order object.
     * @return void
     */
    public function trigger($order_id, $order = false) {
        // Setup localization
        $this->setup_locale();
        
        // Get the order
        $order_obj = $order instanceof WC_Order ? $order : wc_get_order($order_id);
        
        // If we don't have a valid order, bail
        if (!$order_obj || !is_a($order_obj, 'WC_Order')) {
            $this->restore_locale();
            return;
        }
        
        // Set object and email recipient
        $this->object = $order_obj;
        $this->recipient = $order_obj->get_billing_email();
        
        // Check both the old and new meta keys for backward compatibility
        $already_sent_old = get_post_meta($order_id, '_bocs_welcome_email_sent', true);
        $already_sent_new = get_post_meta($order_id, '_bocs_subscription_confirmation_email_sent', true);
        
        if ($already_sent_old === 'yes' || $already_sent_new === 'yes') {
            $this->restore_locale();
            return;
        }
        
        // Set the placeholders
        $this->placeholders['{order_date}'] = wc_format_datetime($this->object->get_date_created());
        $this->placeholders['{order_number}'] = $this->object->get_order_number();
        
        // Set the Bocs ID
        $this->bocs_id = $this->object->get_meta('__bocs_bocs_id');
        
        // Send the email if enabled
        if ($this->is_enabled() && $this->get_recipient()) {
            $sent = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
            
            // Mark as sent in both old and new meta keys for backward compatibility
            if ($sent) {
                update_post_meta($order_id, '_bocs_welcome_email_sent', 'yes');
                update_post_meta($order_id, '_bocs_subscription_confirmation_email_sent', 'yes');
            }
        }
        
        $this->restore_locale();
    }
} 