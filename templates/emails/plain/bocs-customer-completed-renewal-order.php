<?php
/**
 * Bocs Customer Completed Renewal Order Email (Plain Text)
 *
 * This template is used exclusively for Bocs subscription renewal orders that are completed.
 * Plain text version with Bocs.io branding.
 * This template ensures that only one email is sent per completed renewal order.
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
$plain_text = isset($plain_text) ? $plain_text : true;

// Define placeholder for first name if not available
if (empty($first_name)) {
    $first_name = __('valued customer', 'bocs-wordpress');
}

// Get formatted date
$order_date = date_i18n(get_option('date_format'), strtotime($order->get_date_created()));

echo "= " . esc_html($email_heading) . " =\n\n";

echo sprintf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($first_name)) . "\n\n";

echo esc_html__('We\'re pleased to let you know that your subscription renewal order has been completed and is ready for delivery. Your order details are shown below for your reference:', 'bocs-wordpress') . "\n\n";

echo "========== SUBSCRIPTION RENEWAL CONFIRMATION ==========\n\n";

echo esc_html__('YOUR SUBSCRIPTION RENEWAL ORDER IS NOW COMPLETE!', 'bocs-wordpress') . "\n";
echo esc_html__('Thank you for your continued business with Bocs.io!', 'bocs-wordpress') . "\n\n";

if (!empty($bocs_id)) {
	echo esc_html__('This order was created through the Bocs App.', 'bocs-wordpress') . "\n\n";
}

echo "========== ORDER INFORMATION ==========\n\n";

echo sprintf(esc_html__('Order #%s (%s)', 'bocs-wordpress'), $order->get_order_number(), $order_date) . "\n\n";

do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

echo "\n========== ORDER DETAILS ==========\n\n";

do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

echo "\n========== CUSTOMER INFORMATION ==========\n\n";

do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

echo "\n=========================================\n\n";

if ($additional_content) {
	echo esc_html(wp_strip_all_tags(wptexturize($additional_content)));
	echo "\n\n=========================================\n\n";
}

echo esc_html__('Thanks for your continued business with Bocs.io!', 'bocs-wordpress') . "\n\n";

// Add Bocs.io copyright
echo "© " . date('Y') . " Bocs.io - All rights reserved\n\n";

echo apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')); 