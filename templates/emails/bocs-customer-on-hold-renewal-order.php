<?php
/**
 * Bocs Customer On-Hold Renewal Order Email Template
 *
 * This template is used exclusively for Bocs subscription renewal orders that are on-hold.
 * It uses a direct content generation approach with inline styles (no CSS declarations).
 * This template ensures that only one email is sent per on-hold renewal order.
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
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title><?php echo esc_html($email_heading); ?></title>
</head>
<body style="font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; color: #333333; line-height: 150%; background-color: #f7f7f7; margin: 0; padding: 0;">
    <!-- Email Container -->
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f7f7f7;">
        <tr>
            <td align="center" style="padding: 20px;">
                <!-- Email Content -->
                <table border="0" cellpadding="0" cellspacing="0" width="600" style="background-color: #ffffff; border-radius: 4px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);">
                    <!-- Email Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <!-- Email Heading -->
                            <h2 style="color: <?php echo esc_attr($bocs_teal); ?>; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 18px; font-weight: bold; line-height: 130%; margin: 0 0 24px; text-align: left;">
                                <?php esc_html_e('Your subscription renewal order is on-hold', 'bocs-wordpress'); ?>
                            </h2>

                            <!-- Greeting -->
                            <p style="margin: 0 0 16px; font-size: 15px; line-height: 24px; color: #333333;">
                                <?php printf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($first_name)); ?>
                            </p>

                            <!-- Order Introduction -->
                            <p style="margin: 0 0 24px; font-size: 15px; line-height: 24px; color: #333333;">
                                <?php esc_html_e('Thanks for your subscription renewal order. It\'s on-hold until we confirm your payment has been received. Your order details are shown below for your reference:', 'bocs-wordpress'); ?>
                            </p>

                            <!-- On-Hold Notification Box -->
                            <div style="background-color: #fff9c4; border: 1px solid #ffc107; border-radius: 3px; padding: 15px; margin-bottom: 30px;">
                                <p style="margin: 0; color: #856404; font-size: 14px; line-height: 21px;">
                                    <?php esc_html_e('Your subscription renewal order is currently on-hold. We\'ll notify you once your payment has been confirmed and your order is processed.', 'bocs-wordpress'); ?>
                                </p>
                            </div>

                            <?php if (!empty($bocs_id)) : ?>
                            <!-- Bocs App Attribution Notice -->
                            <p style="margin: 0 0 24px; font-style: italic; color: #555; font-size: 14px; line-height: 21px;">
                                <?php esc_html_e('This order was created through the Bocs App.', 'bocs-wordpress'); ?>
                            </p>
                            <?php endif; ?>

                            <!-- Order Details Heading -->
                            <h2 style="color: <?php echo esc_attr($bocs_teal); ?>; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 16px; font-weight: bold; line-height: 130%; margin: 0 0 18px; text-align: left; border-bottom: 1px solid #e5e5e5; padding-bottom: 10px;">
                                <?php printf(esc_html__('[Order #%s]', 'bocs-wordpress'), $order->get_order_number()); ?> 
                                <span style="font-weight: normal; font-size: 14px;">(<?php echo esc_html(date_i18n(get_option('date_format'), strtotime($order->get_date_created()))); ?>)</span>
                            </h2>

                            <!-- Order Details -->
                            <div style="margin-bottom: 40px;">
                                <?php
                                // Order details with styled table
                                do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

                                // Additional order information
                                do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

                                // Customer details
                                do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);
                                ?>
                            </div>

                            <?php if ($additional_content) : ?>
                            <!-- Additional Content -->
                            <div style="margin-bottom: 30px; color: #333333; font-size: 15px; line-height: 24px;">
                                <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
                            </div>
                            <?php endif; ?>

                            <!-- Footer Message -->
                            <p style="margin: 30px 0 16px; padding-top: 20px; border-top: 1px solid #e5e5e5; text-align: center; color: #636363; font-size: 14px; line-height: 21px;">
                                <?php esc_html_e('Thanks for your continued business.', 'bocs-wordpress'); ?>
                            </p>
                        </td>
                    </tr>
                    <!-- Email Footer -->
                    <tr>
                        <td style="background-color: <?php echo esc_attr($bocs_teal); ?>; padding: 15px; text-align: center; color: #ffffff; font-size: 12px;">
                            <p style="margin: 0; color: #ffffff;">© <?php echo date('Y'); ?> Bocs.io - All rights reserved</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html> 