<?php
/**
 * Bocs Customer Processing Renewal Order Email (Plain Text)
 *
 * This template is used when a renewal order is processed and is ready for fulfillment.
 * Plain text version.
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

echo "= " . esc_html($email_heading) . " =\n\n";

echo sprintf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($first_name)) . "\n\n";

echo esc_html__('Just to let you know — your subscription renewal has been received and is now being processed. Your order details are shown below for your reference:', 'bocs-wordpress') . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html__('Your subscription renewal payment was successful. Thank you for your continued support!', 'bocs-wordpress') . "\n\n";

if (!empty($bocs_id)) {
	echo esc_html__('This order was created through the Bocs App.', 'bocs-wordpress') . "\n\n";
}

echo sprintf(esc_html__('[Order #%s]', 'bocs-wordpress'), $order->get_order_number()) . "\n\n";

do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

if ($additional_content) {
	echo esc_html(wp_strip_all_tags(wptexturize($additional_content)));
	echo "\n\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
}

echo esc_html__('Thanks for shopping with us.', 'bocs-wordpress') . "\n\n";

echo apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')); 