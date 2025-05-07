<?php
/**
 * Bocs Customer Failed Payment Retry Email Template
 *
 * This template is used exclusively for Bocs subscription renewal orders when payment retry fails.
 *
 * @package Bocs/Templates/Emails
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
$email_improvements_enabled = FeaturesUtil::feature_is_enabled( 'email_improvements' );

// Load colors from WooCommerce settings
$base_color      = get_option('woocommerce_email_base_color');
$bg_color        = get_option('woocommerce_email_background_color');
$body_color      = get_option('woocommerce_email_body_background_color');
$text_color      = get_option('woocommerce_email_text_color');
$base_text = wc_light_or_dark( $base_color, '#202020', '#ffffff' );

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

// Get customer first name
$first_name = $order->get_billing_first_name();

// Define placeholder for first name if not available
if (empty($first_name)) {
    $first_name = __('valued customer', 'bocs-wordpress');
}

// Define consistent Bocs.io brand color
$bocs_teal = '#3C7B7C';

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);
?>

<div style="padding: 0 12px; max-width: 100%;">
    <p style="margin: 0 0 16px;"><?php printf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($first_name)); ?></p>

    <p style="margin: 0 0 16px;"><?php esc_html_e('We\'re sorry, but our attempt to retry the payment for your subscription renewal was unsuccessful.', 'bocs-wordpress'); ?></p>

    <!-- Failed payment notification box -->
    <div style="background-color: #ffebee; border-left: 4px solid #f44336; padding: 15px 20px; margin-bottom: 20px; border-radius: 4px;">
        <p style="margin: 0 0 16px; color: #d32f2f; font-weight: 600;"><?php esc_html_e('Payment Retry Failed', 'bocs-wordpress'); ?></p>
        <p style="margin: 0 0 16px;"><?php 
            printf(
                esc_html__('We tried to process your payment again, but it was declined. This could be due to expired card details, insufficient funds, or a technical issue with your payment method.', 'bocs-wordpress')
            ); 
        ?></p>
    </div>
    
    <!-- Action required -->
    <h3 style="font-size: 16px; margin-bottom: 15px; color: #333333;"><?php esc_html_e('Action Required', 'bocs-wordpress'); ?></h3>
    <p style="margin: 0 0 16px;"><?php esc_html_e('Please update your payment information or complete the payment manually to avoid any interruption to your subscription.', 'bocs-wordpress'); ?></p>
    
    <?php if ($order->get_view_order_url()) : ?>
    <div style="margin: 25px 0; text-align: center;">
        <a href="<?php echo esc_url($order->get_view_order_url()); ?>" style="background-color: <?php echo esc_attr( $email_improvements_enabled ? $body_color : $base_color ); ?>; border-radius: 4px; color: <?php echo esc_attr( $email_improvements_enabled ? $text_color : $base_text ); ?>; display: inline-block; font-size: 16px; font-weight: 500; padding: 12px 24px; text-decoration: none;"><?php esc_html_e('Update Payment Details', 'bocs-wordpress'); ?></a>
    </div>
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

if ($additional_content) {
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);
?> 