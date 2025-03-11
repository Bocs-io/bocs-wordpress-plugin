<?php
/**
 * Customer Renewal Invoice email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/customer-renewal-invoice.php.
 *
 * @package Bocs/Templates/Emails/Plain
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

// Direct content generation without relying on hooks and output buffering
$content = '';

// Add email heading
$content .= "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
$content .= esc_html(wp_strip_all_tags($email_heading)) . "\n";
$content .= "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

// Greeting
$content .= sprintf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($order->get_billing_first_name())) . "\n\n";

// Renewal information
$content .= sprintf(esc_html__('Your subscription renewal invoice for order #%s is now available.', 'bocs-wordpress'), $order->get_order_number()) . "\n\n";

// Payment information
if ($order->has_status('pending')) {
    $content .= esc_html__('To ensure uninterrupted service, please submit payment at your earliest convenience.', 'bocs-wordpress') . "\n";
    $content .= esc_html__('Payment Link:', 'bocs-wordpress') . " " . esc_url($order->get_checkout_payment_url()) . "\n\n";
} else {
    $content .= esc_html__('Your payment method will be charged automatically, so no action is required on your part.', 'bocs-wordpress') . "\n\n";
}

// Check for Bocs App attribution
$source_type = get_post_meta($order->get_id(), '_wc_order_attribution_source_type', true);
$utm_source = get_post_meta($order->get_id(), '_wc_order_attribution_utm_source', true);

if ($source_type === 'referral' && $utm_source === 'Bocs App') {
    $content .= "** " . esc_html__('This order was created through the Bocs App.', 'bocs-wordpress') . " **\n\n";
}

// Order status section
$content .= "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
$content .= esc_html__('ORDER STATUS', 'bocs-wordpress') . "\n";
$content .= "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

$content .= esc_html__('Status:', 'bocs-wordpress') . " " . esc_html(wc_get_order_status_name($order->get_status())) . "\n";

if ($order->has_status('pending')) {
    $content .= esc_html__('Your renewal payment is required to maintain your subscription.', 'bocs-wordpress') . "\n\n";
} else {
    $content .= esc_html__('Your subscription is active and your renewal payment is being processed automatically.', 'bocs-wordpress') . "\n\n";
}

// Order details section
$content .= "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
$content .= esc_html__('ORDER DETAILS', 'bocs-wordpress') . "\n";
$content .= "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

// Items
foreach ($order->get_items() as $item_id => $item) {
    $product = $item->get_product();
    $content .= $item->get_name() . " × " . $item->get_quantity() . " - " . $order->get_formatted_line_subtotal($item) . "\n";
}

$content .= "\n";

// Order totals
$content .= esc_html__('Subtotal:', 'bocs-wordpress') . " " . $order->get_subtotal_to_display() . "\n";

// Tax (if any)
if (wc_tax_enabled() && $order->get_total_tax() > 0) {
    $content .= esc_html__('Tax:', 'bocs-wordpress') . " " . wc_price($order->get_total_tax()) . "\n";
}

// Payment method
$content .= esc_html__('Payment Method:', 'bocs-wordpress') . " " . $order->get_payment_method_title() . "\n";

// Total
$content .= esc_html__('Total:', 'bocs-wordpress') . " " . $order->get_formatted_order_total() . "\n\n";

// Customer details section
$content .= "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
$content .= esc_html__('CUSTOMER DETAILS', 'bocs-wordpress') . "\n";
$content .= "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

// Billing address
$content .= esc_html__('Billing Address:', 'bocs-wordpress') . "\n";
$content .= preg_replace('#<br\s*/?>#i', "\n", $order->get_formatted_billing_address()) . "\n";
if ($order->get_billing_phone()) {
    $content .= esc_html__('Phone:', 'bocs-wordpress') . " " . $order->get_billing_phone() . "\n";
}
if ($order->get_billing_email()) {
    $content .= esc_html__('Email:', 'bocs-wordpress') . " " . $order->get_billing_email() . "\n";
}
$content .= "\n";

// Shipping address
if ($order->needs_shipping_address()) {
    $content .= esc_html__('Shipping Address:', 'bocs-wordpress') . "\n";
    $content .= preg_replace('#<br\s*/?>#i', "\n", $order->get_formatted_shipping_address()) . "\n\n";
}

// Additional content
if ($additional_content) {
    $content .= "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
    $content .= esc_html(wp_strip_all_tags(wptexturize($additional_content)));
    $content .= "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
}

// Footer info
$content .= esc_html__('If you have any questions about your subscription, please contact our customer support team.', 'bocs-wordpress') . "\n\n";
$content .= esc_html__('Thank you for your continued business with Bocs!', 'bocs-wordpress') . "\n\n";

// Footer
$content .= wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));

// Output all at once
echo $content; 