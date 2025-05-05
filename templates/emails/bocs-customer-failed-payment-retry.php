<?php
/**
 * Bocs Customer Failed Payment Retry Email Template
 *
 * This template is used exclusively for Bocs subscription renewal orders when payment retry fails.
 * It uses a direct content generation approach with inline styles (no CSS declarations).
 *
 * @package Bocs
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

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
$bocs_id = ($email && !empty($email->bocs_id)) ? $email->bocs_id : '';

// Get customer first name
$first_name = $order->get_billing_first_name();

// Additional safety checks
$sent_to_admin = isset($sent_to_admin) ? $sent_to_admin : false;
$plain_text = isset($plain_text) ? $plain_text : false;

// Define placeholder for first name if not available
if (empty($first_name)) {
    $first_name = __('valued customer', 'bocs-wordpress');
}

// Define consistent Bocs.io brand color
$bocs_teal = '#3C7B7C';
$bg               = get_option('woocommerce_email_background_color', '#f7f7f7');
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title><?php echo esc_html($email_heading); ?></title>
</head>
<body style="background-color: <?php echo esc_attr($bg); ?>; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 14px; line-height: 1.5; margin: 0; padding: 0;">
    <div style="margin: 0 auto; max-width: 600px; padding: 20px;">
        <div style="background-color: #ffffff; border-radius: 4px; border: 1px solid #eaeaea; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); margin-bottom: 20px; padding: 30px;">
            
            <!-- Email Header with Bocs Logo -->
            <div style="margin-bottom: 30px; text-align: center;">
                <img src="https://bocs.io/wp-content/uploads/2023/01/Bocs-Logo.png" alt="Bocs Logo" width="120" style="max-width: 100%;">
            </div>
            
            <!-- Greeting -->
            <div style="margin-bottom: 25px;">
                <h1 style="color: <?php echo esc_attr($bocs_teal); ?>; font-size: 22px; font-weight: 600; margin: 0 0 15px; text-align: center;"><?php echo esc_html($email_heading); ?></h1>
                <p style="color: #333333; font-size: 16px; margin-bottom: 20px;"><?php printf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($first_name)); ?></p>
                <p style="color: #333333; font-size: 16px; margin-bottom: 20px;"><?php esc_html_e('We\'re sorry, but our attempt to retry the payment for your subscription renewal was unsuccessful.', 'bocs-wordpress'); ?></p>
            </div>
            
            <!-- Failed payment notification box -->
            <div style="background-color: #ffebee; border-left: 4px solid #f44336; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px;">
                <p style="margin: 0 0 16px; color: #d32f2f; font-weight: 600;"><?php esc_html_e('Payment Retry Failed', 'bocs-wordpress'); ?></p>
                <p style="margin: 0 0 16px;"><?php 
                    printf(
                        esc_html__('We tried to process your payment again, but it was declined. This could be due to expired card details, insufficient funds, or a technical issue with your payment method.', 'bocs-wordpress')
                    ); 
                ?></p>
            </div>
            
            <!-- Action required -->
            <div style="margin-bottom: 30px; padding: 0 5px;">
                <h3 style="font-size: 18px; margin-bottom: 15px; color: #333333; text-decoration: none; font-weight: 500; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;"><?php esc_html_e('Action Required', 'bocs-wordpress'); ?></h3>
                <p style="margin: 0 0 16px;"><?php esc_html_e('Please update your payment information or complete the payment manually to avoid any interruption to your subscription.', 'bocs-wordpress'); ?></p>
                
                <?php if ($order->get_view_order_url()) : ?>
                <div style="margin: 25px 0; text-align: center;">
                    <a href="<?php echo esc_url($order->get_view_order_url()); ?>" style="background-color: <?php echo esc_attr($bocs_teal); ?>; border-radius: 4px; color: #ffffff; display: inline-block; font-size: 16px; font-weight: 500; padding: 12px 24px; text-decoration: none;"><?php esc_html_e('Update Payment Details', 'bocs-wordpress'); ?></a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Bocs App notice, if applicable -->
            <?php if ( function_exists('bocs_order_created_via_app') && bocs_order_created_via_app($order) ) : ?>
            <div style="background-color: #fff8e1; padding: 12px 15px; margin-bottom: 25px; border-radius: 4px; border: 1px dashed #ffa000;">
                <p style="margin: 0 0 16px;"><span style="color: #ff6b00; font-weight: 500;"><?php esc_html_e('This subscription was created through the Bocs App.', 'bocs-wordpress'); ?></span></p>
                <p style="margin: 0 0 16px;"><?php esc_html_e('You can update your payment details directly through the Bocs mobile app.', 'bocs-wordpress'); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Order details header -->
            <h2 style="color: <?php echo esc_attr($bocs_teal); ?>; display: block; font-size: 18px; font-weight: bold; line-height: 130%; margin: 30px 0 18px; text-align: left;">
                <?php printf(esc_html__('Order #%s (%s)', 'bocs-wordpress'), $order->get_order_number(), date_i18n(wc_date_format(), strtotime($order->get_date_created()))); ?>
            </h2>
            
            <!-- Render order details -->
            <?php
            do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);
            
            do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);
            
            do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);
            
            if ($additional_content) {
                echo wp_kses_post(wpautop(wptexturize($additional_content)));
            }
            ?>
        </div>
    </div>
</body>
</html> 