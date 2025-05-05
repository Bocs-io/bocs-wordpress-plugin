<?php
/**
 * Bocs Customer Renewal Order Confirmation Email Template
 *
 * This template is used for renewal orders that transition from Pending payment to Processing
 * with a __bocs_order_status of "upcoming".
 *
 * @package Bocs
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

// Get the order - template may receive either an order object or order ID
if (isset($order) && is_a($order, 'WC_Order')) {
    // We already have the order object
} elseif (isset($order_id)) {
    // We have an order ID, get the order object
    $order = wc_get_order($order_id);
} else {
    // Neither order nor order_id is available
    return;
}

// Proceed only if we have a valid order
if (!$order) {
    return;
}

$email_heading = isset($email_heading) ? $email_heading : '';
$additional_content = isset($additional_content) ? $additional_content : '';
$email = isset($email) ? $email : false;
$bocs_id = isset($bocs_id) ? $bocs_id : '';

// Get customer first name
$first_name = $order->get_billing_first_name();

// Additional safety checks
$sent_to_admin = isset($sent_to_admin) ? $sent_to_admin : false;
$plain_text = isset($plain_text) ? $plain_text : false;

// Define placeholder for first name if not available
if (empty($first_name)) {
    $first_name = __('valued customer', 'bocs-wordpress');
}

// Load colors from WooCommerce settings
$base_color      = get_option('woocommerce_email_base_color');
$bg_color        = get_option('woocommerce_email_background_color');
$body_color      = get_option('woocommerce_email_body_background_color');
$text_color      = get_option('woocommerce_email_text_color');

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);
?>

<div style="padding: 0 12px; max-width: 100%;">
    <p style="margin: 0 0 16px;"><?php printf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($first_name)); ?></p>
    
    <p style="margin: 0 0 16px;">
        <?php esc_html_e('Good news! Your renewal order has been confirmed and is now being processed. Here are the details of your order:', 'bocs-wordpress'); ?>
    </p>
    
    <!-- Success notification -->
    <div style="background-color: #e6f7e6; border: 1px solid #4CAF50; border-radius: 3px; padding: 15px; margin-bottom: 16px;">
        <p style="margin: 0; color: #1e581e; font-size: 14px; line-height: 21px;">
            <?php esc_html_e('Your subscription renewal has been confirmed and payment is being processed. Your order will be shipped soon.', 'bocs-wordpress'); ?>
        </p>
    </div>

    <?php if (!empty($bocs_id)) : ?>
    <!-- Bocs App Attribution Notice -->
    <p style="margin: 0 0 16px; font-style: italic; color: #555; font-size: 14px; line-height: 21px;">
        <?php esc_html_e('This order was created through the Bocs App.', 'bocs-wordpress'); ?>
    </p>
    <?php endif; ?>
</div>

<?php
/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 */
do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);
?> 