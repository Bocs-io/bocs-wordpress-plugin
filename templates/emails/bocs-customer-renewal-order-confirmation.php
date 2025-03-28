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

// Define consistent Bocs.io brand color
$bocs_teal = '#3C7B7C';
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title><?php echo esc_html($email_heading); ?></title>
</head>
<body style="background-color: #f7f7f7; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333333; margin: 0; padding: 0;">
    <div style="padding: 50px 0;">
        <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e5e5e5; border-radius: 6px; box-shadow: 0 1px 10px rgba(0, 0, 0, 0.1);">
            <!-- Header -->
            <div style="background-color: <?php echo esc_attr($bocs_teal); ?>; padding: 36px 48px; border-radius: 6px 6px 0 0; color: #ffffff;">
                <h1 style="font-size: 30px; font-weight: 300; line-height: 150%; margin: 0; text-align: left; color: #ffffff;">
                    <?php echo esc_html($email_heading); ?>
                </h1>
            </div>
            
            <!-- Content -->
            <div style="padding: 48px 48px 32px; background-color: #ffffff;">
                <!-- Greeting -->
                <p style="margin: 0 0 24px;">
                    <?php printf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($first_name)); ?>
                </p>
                
                <!-- Main message -->
                <p style="margin: 0 0 24px;">
                    <?php esc_html_e('Good news! Your renewal order has been confirmed and is now being processed. Here are the details of your order:', 'bocs-wordpress'); ?>
                </p>
                
                <!-- Success notification -->
                <div style="background-color: #e6f7e6; border: 1px solid #4CAF50; border-radius: 3px; padding: 15px; margin-bottom: 30px;">
                    <p style="margin: 0; color: #1e581e; font-size: 14px; line-height: 21px;">
                        <?php esc_html_e('Your subscription renewal has been confirmed and payment is being processed. Your order will be shipped soon.', 'bocs-wordpress'); ?>
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
                ?>
                
                <!-- Additional Content -->
                <?php if ($additional_content) : ?>
                <div style="margin-top: 30px; border-top: 1px solid #e5e5e5; padding-top: 20px;">
                    <p style="margin: 0 0 16px;"><?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Footer -->
            <div style="background-color: #f7f7f7; padding: 24px 48px; border-top: 1px solid #e5e5e5; border-radius: 0 0 6px 6px; font-size: 12px; color: #8a8a8a; text-align: center;">
                <p style="margin: 0 0 16px;">
                    <?php esc_html_e('Thank you for being a valued Bocs customer!', 'bocs-wordpress'); ?>
                </p>
                <p style="margin: 0;">
                    <?php echo wp_kses_post(make_clickable(wpautop(wptexturize(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text', '')))), array('a'))); ?>
                </p>
            </div>
        </div>
    </div>
</body>
</html> 