<?php
/**
 * Customer Failed Payment Retry email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/bocs-customer-failed-payment-retry.php.
 *
 * @package Bocs/Templates/Emails/Plain
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading)) . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/* translators: %s: Customer first name */
echo sprintf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($order->get_billing_first_name())) . "\n\n";

echo esc_html__('We\'re sorry, but our attempt to retry the payment for your subscription renewal was unsuccessful. This could be due to expired card details, insufficient funds, or a technical issue with your payment method.', 'bocs-wordpress') . "\n\n";

echo esc_html__('PAYMENT RETRY FAILED', 'bocs-wordpress') . "\n\n";

echo esc_html__('To ensure your subscription continues uninterrupted, please update your payment information or make a manual payment as soon as possible.', 'bocs-wordpress') . "\n";

if ($order_url = $order->get_view_order_url()) {
    echo esc_html__('Pay Now:', 'bocs-wordpress') . ' ' . esc_url($order_url) . "\n\n";
}

// Check for Bocs App attribution
if (function_exists('bocs_order_created_via_app') && bocs_order_created_via_app($order)) {
    echo esc_html__('Your subscription was created through the Bocs App. You can update your payment details directly through the Bocs mobile app.', 'bocs-wordpress') . "\n\n";
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__('ORDER DETAILS', 'bocs-wordpress') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 */
do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__('CUSTOMER DETAILS', 'bocs-wordpress') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ($additional_content) {
    echo esc_html(wp_strip_all_tags(wptexturize($additional_content)));
    echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
}

echo wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'))); 