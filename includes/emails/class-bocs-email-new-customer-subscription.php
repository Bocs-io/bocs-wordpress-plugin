<?php
/**
 * Class WC_Bocs_Email_New_Customer_Subscription
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
 * New Customer Subscription Confirmation Email
 *
 * An email sent to customers when they set up their first subscription through Bocs.
 * This handles the email notification sent to new customers after their Bocs subscription is created.
 *
 * @class       WC_Bocs_Email_New_Customer_Subscription
 * @version     0.0.118
 * @package     Bocs\Emails
 * @extends     WC_Email
 */
class WC_Bocs_Email_New_Customer_Subscription extends WC_Email {

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
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->id             = 'bocs_new_customer_subscription';
        $this->customer_email = true;
        $this->title          = __('[Bocs Customer] Welcome New Customer New Subscription', 'bocs-wordpress');
        $this->description    = __('Welcome email sent to new customers after setting up their first Bocs subscription.', 'bocs-wordpress');
        $this->template_html  = 'emails/bocs-new-customer-subscription.php';
        $this->template_plain = 'emails/plain/bocs-new-customer-subscription.php';
        
        // Make sure we use the correct template path
        if (defined('BOCS_TEMPLATE_PATH')) {
            $this->template_base = BOCS_TEMPLATE_PATH;
        } else {
            // Fallback to plugin directory
            $this->template_base = plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/';
        }
        
        $this->placeholders   = array(
            '{order_date}'   => '',
            '{order_number}' => '',
        );

        // Force enable this email
        $this->enabled = 'yes';

        // Call parent constructor
        parent::__construct();
        
        // Do not set a default recipient - we'll set it in the trigger method based on the order
        
        // Add a filter to ensure this email is always enabled
        add_filter('woocommerce_email_enabled_' . $this->id, function($enabled) {
            return 'yes'; // Always enable this email
        }, 999, 1);
        
        // Prevent ALL default WooCommerce emails for Bocs subscription orders with highest priority (999)
        // For admin emails
        add_filter('woocommerce_email_enabled_new_order', array($this, 'maybe_disable_wc_email'), 999, 2);
        
        // For customer emails
        add_filter('woocommerce_email_enabled_customer_processing_order', array($this, 'maybe_disable_wc_email'), 999, 2);
        add_filter('woocommerce_email_enabled_customer_completed_order', array($this, 'maybe_disable_wc_email'), 999, 2);
        add_filter('woocommerce_email_enabled_customer_new_account', array($this, 'maybe_disable_wc_email'), 999, 2);
        add_filter('woocommerce_email_enabled_customer_on_hold_order', array($this, 'maybe_disable_wc_email'), 999, 2);
        add_filter('woocommerce_email_enabled_customer_invoice', array($this, 'maybe_disable_wc_email'), 999, 2);
        add_filter('woocommerce_email_enabled_customer_note', array($this, 'maybe_disable_wc_email'), 999, 2);
        add_filter('woocommerce_email_enabled_customer_refunded_order', array($this, 'maybe_disable_wc_email'), 999, 2);
        add_filter('woocommerce_email_enabled_customer_reset_password', array($this, 'maybe_disable_wc_email'), 999, 2);
        add_filter('woocommerce_email_enabled_failed_order', array($this, 'maybe_disable_wc_email'), 999, 2);
        add_filter('woocommerce_email_enabled_cancelled_order', array($this, 'maybe_disable_wc_email'), 999, 2);
        
        // Add a more aggressive filter to catch all emails at mail sending level
        add_filter('woocommerce_mail_callback', array($this, 'maybe_disable_all_wc_emails'), 999, 1);
        
        // Also hook earlier in the process to disable email templates
        add_action('woocommerce_before_template_part', array($this, 'maybe_disable_email_template'), 5, 4);
        
        // Highest level interception - WordPress mail filter - only use if needed
        add_filter('pre_wp_mail', array($this, 'maybe_block_wp_mail'), 999, 2);
    }

    /**
     * Get email subject.
     *
     * @since 1.0.0
     * @return string Default email subject
     */
    public function get_default_subject() {
        return __('Your subscription is confirmed', 'bocs-wordpress');
    }

    /**
     * Get email heading.
     *
     * @since 1.0.0
     * @return string Default email heading
     */
    public function get_default_heading() {
        return __('Welcome!', 'bocs-wordpress');
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
     * @param int $order_id The order ID.
     * @param WC_Order|bool $order Order object.
     * @return void
     */
    public function trigger($order_id, $order = false) {
        // Setup localization
        $this->setup_locale();
        
        // Handle case where order is passed as object instead of ID
        if (is_object($order_id) && is_a($order_id, 'WC_Order')) {
            $order = $order_id;
            $order_id = $order->get_id();
        }
        
        // Get the order
        $order_obj = $order instanceof WC_Order ? $order : wc_get_order($order_id);
        
        // If we don't have a valid order, bail
        if (!$order_obj || !is_a($order_obj, 'WC_Order')) {
            $this->log_error("Invalid order for ID: {$order_id}");
            $this->restore_locale();
            return;
        }
        
        // Set object and email recipient
        $this->object = $order_obj;
        $this->recipient = $order_obj->get_billing_email();
        
        // Double check order count before proceeding - we only want this email for first-time customers
        $customer_id = $order_obj->get_customer_id();
        if ($customer_id > 0) {
            $order_count = wc_get_customer_order_count($customer_id);
            
            if ($order_count !== 1) {
                $this->restore_locale();
                return;
            }
        }
        
        // Check if this is a Bocs subscription order
        $bocs_subscription_id = $order_obj->get_meta('__bocs_subscription_id');
        $bocs_frequency_id = $order_obj->get_meta('__bocs_frequency_id');
        
        // If no Bocs subscription ID, check for frequency ID as a fallback
        if (empty($bocs_subscription_id) && !empty($bocs_frequency_id)) {
            $bocs_subscription_id = $bocs_frequency_id;
        }
        
        // If still no Bocs subscription ID, bail
        if (empty($bocs_subscription_id)) {
            $this->restore_locale();
            return;
        }
        
        // Set the Bocs ID
        $this->bocs_id = $bocs_subscription_id;
        
        // Set the placeholders for email template
        $this->placeholders['{order_date}'] = wc_format_datetime($this->object->get_date_created());
        $this->placeholders['{order_number}'] = $this->object->get_order_number();
        
        // Check if we've already sent this email 
        $already_sent = get_post_meta($order_id, '_bocs_new_customer_subscription_email_sent', true);
        
        if ($already_sent === 'yes') {
            $this->restore_locale();
            return;
        }
        
        // Check if existing customer email was already sent - don't send both
        $existing_customer_email_sent = $order_obj->get_meta('_bocs_existing_customer_subscription_email_sent');
        if ($existing_customer_email_sent === 'yes') {
            // Mark as sent to avoid repeated checks
            update_post_meta($order_id, '_bocs_new_customer_subscription_email_sent', 'skipped');
            $this->restore_locale();
            return;
        }
        
        // Make sure customer meets eligibility criteria
        if (!$this->is_customer_eligible_for_email($order_obj)) {
            $this->restore_locale();
            return;
        }
        
        error_log("BOCS DEBUG [New Customer Email]: Customer is eligible, preparing to send email");
        
        // Always send for Bocs subscription orders with valid email
        if ($this->is_enabled() && $this->get_recipient()) {
            try {
                // Verify content is being generated
                $html_content = $this->get_content_html();
                $plain_content = $this->get_content_plain();
                
                // Debug checking headers and attachments
                $headers = $this->get_headers();
                $attachments = $this->get_attachments();
                
                // Send the email with a generous timeout
                add_filter('wp_mail_timeout', function() { return 30; }); // 30 second timeout
                
                // Send the email
                $sent = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
                
                // Mark as sent to prevent duplicates
                if ($sent) {
                    update_post_meta($order_id, '_bocs_new_customer_subscription_email_sent', 'yes');
                } else {
                    $this->log_error("Failed to send email for order #{$order_id}");
                    $mail_error = error_get_last();
                    if ($mail_error) {
                        error_log("BOCS DEBUG [New Customer Email]: Mail error: " . print_r($mail_error, true));
                    }
                }
            } catch (Exception $e) {
                $this->log_error("Exception when sending email for order #{$order_id}: " . $e->getMessage());
            }
        }
        
        // Restore localization
        $this->restore_locale();
    }

    /**
     * Get content html.
     *
     * @since 1.0.0
     * @return string Email HTML content
     */
    public function get_content_html() {
        // Check if template exists before trying to load it
        $template_file = $this->template_base . $this->template_html;
        if (!file_exists($template_file)) {
            
            // Try alternate locations
            $alternate_locations = [
                plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/' . $this->template_html,
                get_stylesheet_directory() . '/woocommerce/' . $this->template_html,
                get_stylesheet_directory() . '/bocs-wordpress/' . $this->template_html,
            ];
            
            foreach ($alternate_locations as $location) {
                if (file_exists($location)) {
                    error_log("BOCS DEBUG [New Customer Email]: Found template at alternate location: " . $location);
                    break;
                }
            }
        }
        
        $args = [
            'order'              => $this->object,
            'email_heading'      => $this->get_heading(),
            'sent_to_admin'      => false,
            'plain_text'         => false,
            'email'              => $this,
        ];
        
        try {
            $content = wc_get_template_html(
                $this->template_html,
                $args,
                '',
                $this->template_base
            );
            
            $content_length = strlen($content);
                
            if ($content_length < 100 && $content_length > 0) {
                error_log("BOCS DEBUG [New Customer Email]: WARNING - Content seems too short: " . $content);
            }
            
            return $content;
        } catch (Exception $e) {
            return '<p>Error loading email template.</p>';
        }
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
        return __('We\'re excited to have you as a Bocs customer! If you have any questions or need assistance with your new subscription, our customer support team is always here to help.', 'bocs-wordpress');
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
                'description' => __('This controls the email subject line. Leave blank to use the default subject: <code>Your subscription is confirmed</code>.', 'bocs-wordpress'),
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'            => array(
                'title'       => __('Email Heading', 'bocs-wordpress'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>Welcome!</code>.', 'bocs-wordpress'),
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
     * Maybe disable WooCommerce emails for Bocs subscription orders
     *
     * @param bool $enabled Whether the email is enabled.
     * @param object $order The order object if available.
     * @return bool
     */
    public function maybe_disable_wc_email($enabled, $order = null) {
        // Only proceed if we have an order
        if (!$order || !is_a($order, 'WC_Order')) {
            return $enabled;
        }
        
        // Check if this is a Bocs subscription order by looking for any Bocs identifiers
        $bocs_id = $order->get_meta('__bocs_id');
        $subscription_id = $order->get_meta('__bocs_subscription_id');
        $frequency_id = $order->get_meta('__bocs_frequency_id');
        
        // If this has any Bocs identifiers, let's disable standard WooCommerce emails
        if (!empty($bocs_id) || !empty($subscription_id) || !empty($frequency_id)) {
            
            // Check if we've already sent our custom email for this order
            $order_id = $order->get_id();
            $already_sent = get_post_meta($order_id, '_bocs_new_customer_subscription_email_sent', true);
            
            // If our email has been sent or will be sent, disable the default email
            if ($already_sent === 'yes' || $this->is_customer_eligible_for_email($order)) {
                return false;
            }
            
            // Also check if this is a new customer with exactly 1 order - if so, we'll handle it, disable WC emails
            $customer_id = $order->get_customer_id();
            if ($customer_id > 0) {
                $order_count = wc_get_customer_order_count($customer_id);
                if ($order_count === 1) {
                    return false;
                }
            }
        }
        
        return $enabled;
    }

    /**
     * Check if customer is eligible for the welcome email
     *
     * @param WC_Order $order The order object
     * @return bool
     */
    private function is_customer_eligible_for_email($order) {
        // Check if this is the customer's first Bocs order
        $customer_id = $order->get_customer_id();
        $is_new_customer = true;
        
        // Get total order count first - this is more reliable than counting previous orders
        $order_count = 0;
        if ($customer_id > 0) {
            $order_count = wc_get_customer_order_count($customer_id);

            // IMPORTANT: For new customer email, we only want to send if total order count is EXACTLY 1
            if ($order_count > 1) {
                return false;
            }
        }
        
        if ($customer_id > 0) {
            // Get customer's previous orders with Bocs products (this is just to check for previous Bocs orders)
            $previous_orders = wc_get_orders(array(
                'customer_id' => $customer_id,
                'status' => array('wc-completed', 'wc-processing'),
                'limit' => -1,
                'return' => 'ids',
            ));
            
            $previous_orders = array_diff($previous_orders, array($order->get_id()));
                
            // If customer has previous orders, check if any of them had Bocs products
            if (!empty($previous_orders)) {
                $had_bocs_products = false;
                $bocs_order_count = 0;
                
                foreach ($previous_orders as $prev_order_id) {
                    $prev_order = wc_get_order($prev_order_id);
                    if (!$prev_order) continue;
                    
                    // Check if order has any Bocs meta
                    $has_bocs_id = $prev_order->get_meta('__bocs_id');
                    $has_subscription_id = $prev_order->get_meta('__bocs_subscription_id');
                    $has_frequency_id = $prev_order->get_meta('__bocs_frequency_id');
                    
                    
                    if ($has_bocs_id || $has_subscription_id || $has_frequency_id) {
                        $had_bocs_products = true;
                        $bocs_order_count++;
                    }
                }
                
                
                $is_new_customer = !$had_bocs_products;
                
                // If they had previous Bocs orders, they're not eligible for new customer email
                if ($had_bocs_products) {
                    return false;
                }
            }
        }
        
        // Check if current order has a Bocs ID
        $has_bocs_id = $order->get_meta('__bocs_id');
        $has_subscription_id = $order->get_meta('__bocs_subscription_id');
        $has_frequency_id = $order->get_meta('__bocs_frequency_id');
        
        $has_current_bocs_id = !empty($has_bocs_id) || !empty($has_subscription_id) || !empty($has_frequency_id);
        
        
        // FINAL ELIGIBILITY CHECK:
        // 1. Must be their first order (order count = 1)
        // 2. Must have a Bocs ID in current order
        // 3. Must not have previous Bocs orders
        $is_eligible = ($order_count === 1) && $is_new_customer && $has_current_bocs_id;
                      
        return $is_eligible;
    }

    /**
     * Maybe disable all WooCommerce emails for Bocs subscription orders - this is a more aggressive approach
     * that intercepts the mail callback
     *
     * @param callable $callback The original email callback
     * @return callable|bool
     */
    public function maybe_disable_all_wc_emails($callback) {
        global $post;
        
        // Try to get the current order
        $order = null;
        
        // Check if we're in an email about a specific order
        if (isset($_REQUEST['order_id'])) {
            $order = wc_get_order($_REQUEST['order_id']);
        } elseif (is_object($post) && isset($post->ID) && get_post_type($post->ID) === 'shop_order') {
            $order = wc_get_order($post->ID);
        }
        
        // If we have an order, check if it's a Bocs subscription
        if ($order && is_a($order, 'WC_Order')) {
            // Check if this is a Bocs subscription order by looking for any Bocs identifiers
            $bocs_id = $order->get_meta('__bocs_id');
            $subscription_id = $order->get_meta('__bocs_subscription_id');
            $frequency_id = $order->get_meta('__bocs_frequency_id');
            
            // If this has any Bocs identifiers, let's disable standard WooCommerce emails
            if (!empty($bocs_id) || !empty($subscription_id) || !empty($frequency_id)) {
                // Get order details
                $order_id = $order->get_id();
                $customer_id = $order->get_customer_id();
                
                // Check if this is a first-time customer
                if ($customer_id > 0) {
                    $order_count = wc_get_customer_order_count($customer_id);
                    if ($order_count === 1) {
                        return false; // Disable the email entirely
                    }
                }
                
                // Also check if our email would apply to this order
                if ($this->is_customer_eligible_for_email($order)) {
                    return false; // Disable the email entirely
                }
            }
        }
        
        return $callback;
    }

    /**
     * Maybe disable WooCommerce email templates for Bocs subscription orders
     * This is the earliest hook we can use to prevent template rendering
     *
     * @param string $template_name Template name
     * @param string $template_path Template path
     * @param string $located Located template path
     * @param array $args Arguments
     */
    public function maybe_disable_email_template($template_name, $template_path, $located, $args) {
        // Check if this is an email template
        if (strpos($template_name, 'emails/') !== false) {
            // Check if we have access to the order
            if (isset($args['order']) && is_a($args['order'], 'WC_Order')) {
                $order = $args['order'];
                $order_id = $order->get_id();
                
                // Check if this is a Bocs subscription order by looking for any Bocs identifiers
                $bocs_id = $order->get_meta('__bocs_id');
                $subscription_id = $order->get_meta('__bocs_subscription_id');
                $frequency_id = $order->get_meta('__bocs_frequency_id');
                
                // If this has any Bocs identifiers
                if (!empty($bocs_id) || !empty($subscription_id) || !empty($frequency_id)) {
                    // Check if this is a first-time customer
                    $customer_id = $order->get_customer_id();
                    if ($customer_id > 0) {
                        $order_count = wc_get_customer_order_count($customer_id);
                        
                        // For first-time customers, we'll use our own email
                        if ($order_count === 1) {
                            
                            // Use the empty template for all email templates
                            add_filter('wc_get_template', function($template, $template_name_filter) use ($template_name) {
                                if ($template_name_filter === $template_name) {
                                    // Return empty template
                                    return plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/emails/empty-template.php';
                                }
                                return $template;
                            }, 999, 2);
                        }
                    }
                    
                    // Also check if our email would apply to this order
                    if ($this->is_customer_eligible_for_email($order)) {
                        
                        // Use the empty template for all email templates
                        add_filter('wc_get_template', function($template, $template_name_filter) use ($template_name) {
                            if ($template_name_filter === $template_name) {
                                // Return empty template
                                return plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/emails/empty-template.php';
                            }
                            return $template;
                        }, 999, 2);
                    }
                }
            }
        }
    }
    
    /**
     * Final fallback - intercept WordPress mail function
     * This is a last resort to block any emails that might slip through
     *
     * @param null|bool $return Whether to preempt wp_mail().
     * @param array $atts Array of the `wp_mail()` arguments.
     * @return null|bool
     */
    public function maybe_block_wp_mail($return, $atts) {
        // Skip if already preempted or no order metadata
        if (null !== $return) {
            return $return;
        }
        
        // Check if this is a WooCommerce email (check subject & headers)
        $is_wc_email = false;
        
        if (isset($atts['subject']) && isset($atts['headers'])) {
            // Check subject for common WooCommerce patterns
            $subject = $atts['subject'];
            if (strpos($subject, 'Order') !== false || 
                strpos($subject, 'order') !== false || 
                strpos($subject, 'Purchase') !== false) {
                $is_wc_email = true;
            }
            
            // Look for WooCommerce in headers
            if (is_array($atts['headers'])) {
                foreach ($atts['headers'] as $header) {
                    if (strpos($header, 'WooCommerce') !== false) {
                        $is_wc_email = true;
                        break;
                    }
                }
            } else if (is_string($atts['headers']) && strpos($atts['headers'], 'WooCommerce') !== false) {
                $is_wc_email = true;
            }
            
            // Check message body for order information
            if (isset($atts['message']) && 
                (strpos($atts['message'], 'Order #') !== false || 
                 strpos($atts['message'], 'order #') !== false)) {
                $is_wc_email = true;
            }
            
            // If this looks like a WooCommerce email, check if we need to block it
            if ($is_wc_email) {
                // Looking for order ID in subject or message
                $order_id = null;
                
                // Try to extract order ID from subject or message
                preg_match('/order #?(\d+)/i', $subject, $matches);
                if (!empty($matches[1])) {
                    $order_id = $matches[1];
                } else if (isset($atts['message'])) {
                    preg_match('/order #?(\d+)/i', $atts['message'], $matches);
                    if (!empty($matches[1])) {
                        $order_id = $matches[1];
                    }
                }
                
                // If we found an order ID, check if it's a Bocs order
                if ($order_id) {
                    $order = wc_get_order($order_id);
                    if ($order) {
                        // Check if this is a Bocs subscription order
                        $bocs_id = $order->get_meta('__bocs_id');
                        $subscription_id = $order->get_meta('__bocs_subscription_id');
                        $frequency_id = $order->get_meta('__bocs_frequency_id');
                        
                        // If this has any Bocs identifiers and is for a new customer, block the email
                        if (!empty($bocs_id) || !empty($subscription_id) || !empty($frequency_id)) {
                            $customer_id = $order->get_customer_id();
                            if ($customer_id > 0) {
                                $order_count = wc_get_customer_order_count($customer_id);
                                if ($order_count === 1) {
                                    return false; // Block the email
                                }
                            }
                            
                            // Also check if our email would apply to this order
                            if ($this->is_customer_eligible_for_email($order)) {
                                return false; // Block the email
                            }
                        }
                    }
                }
            }
        }
        
        // Let the email through if no reason to block
        return $return;
    }

    /**
     * Log debug message
     *
     * @param string $message Message to log
     * @return void
     */
    private function log_debug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BOCS EMAIL DEBUG: ' . $message);
        }
    }

    /**
     * Log error message
     *
     * @param string $message Message to log
     * @return void
     */
    private function log_error($message) {
        error_log('BOCS EMAIL ERROR: ' . $message);
    }
} 