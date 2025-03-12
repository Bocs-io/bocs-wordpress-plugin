<?php
/**
 * Bocs Customer Processing Renewal Order Email Template
 *
 * This template is used when a renewal order is processed and is ready for fulfillment.
 * It uses a direct content generation approach with Bocs branding.
 *
 * @package Bocs
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$order = wc_get_order($order_id);
$email_heading = $email->get_heading();
$additional_content = $email->get_additional_content();
$bocs_id = !empty($email->bocs_id) ? $email->bocs_id : '';

// Get customer first name
$first_name = $order->get_billing_first_name();

?>

<div style="margin-bottom: 40px;">
    <h2 style="color: #3C7B7C; display: block; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 18px; font-weight: bold; line-height: 130%; margin: 0 0 18px; text-align: left;">
        <?php esc_html_e('Your order is now being processed', 'bocs-wordpress'); ?>
    </h2>

    <p style="margin: 0 0 16px;">
        <?php printf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($first_name)); ?>
    </p>

    <p style="margin: 0 0 16px;">
        <?php esc_html_e('Just to let you know — your subscription renewal has been received and is now being processed. Your order details are shown below for your reference:', 'bocs-wordpress'); ?>
    </p>

    <!-- Success notification box -->
    <div style="background-color: #e8f5e9; border: 1px solid #4caf50; border-radius: 3px; padding: 12px 15px; margin-bottom: 20px;">
        <p style="margin: 0; color: #1b5e20; font-size: 14px;">
            <?php esc_html_e('Your subscription renewal payment was successful. Thank you for your continued support!', 'bocs-wordpress'); ?>
        </p>
    </div>

    <?php if (!empty($bocs_id)) : ?>
    <!-- Bocs App attribution notice -->
    <p style="margin: 10px 0 16px; font-style: italic; color: #555;">
        <?php esc_html_e('This order was created through the Bocs App.', 'bocs-wordpress'); ?>
    </p>
    <?php endif; ?>

    <h2 style="color: #3C7B7C; display: block; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 16px; font-weight: bold; line-height: 130%; margin: 0 0 18px; text-align: left;">
        <?php printf(esc_html__('[Order #%s]', 'bocs-wordpress'), $order->get_order_number()); ?>
    </h2>

    <div style="margin-bottom: 40px;">
        <?php
        // Order details
        do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

        // Additional order information
        do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

        // Customer details
        do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);
        ?>
    </div>

    <?php if ($additional_content) : ?>
    <div style="margin-bottom: 30px;">
        <p style="margin: 0 0 16px;"><?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?></p>
    </div>
    <?php endif; ?>

    <p style="margin: 0 0 16px;">
        <?php esc_html_e('Thanks for shopping with us.', 'bocs-wordpress'); ?>
    </p>
</div> 