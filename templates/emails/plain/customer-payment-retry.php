<?php
/**
 * Customer Payment Retry email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/customer-payment-retry.php.
 *
 * @package Bocs/Templates/Emails/Plain
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading)) . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo "BOCS.IO - PAYMENT RETRY NOTIFICATION\n\n";

/* translators: %s: Customer first name */
echo sprintf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($order->get_billing_first_name())) . "\n\n";

if (!empty($retry_date)) {
    echo sprintf(esc_html__('We wanted to let you know that we will retry charging your payment method for your subscription renewal on %s.', 'bocs-wordpress'), $retry_date) . "\n\n";
} else {
    echo esc_html__('We wanted to let you know that we will retry charging your payment method for your subscription renewal soon.', 'bocs-wordpress') . "\n\n";
}

// Display Bocs App attribution
$source_type = get_post_meta($order->get_id(), '_wc_order_attribution_source_type', true);
$utm_source = get_post_meta($order->get_id(), '_wc_order_attribution_utm_source', true);

if ($source_type === 'referral' && $utm_source === 'Bocs App') {
    echo "** " . esc_html__('This order was created through the Bocs App.', 'bocs-wordpress') . " **\n\n";
}

echo esc_html__('You don\'t need to do anything, but if you\'d like to update your payment information before the retry, you can do so from your account.', 'bocs-wordpress') . "\n\n";

echo esc_html__('To update your payment method, please visit:', 'bocs-wordpress') . "\n";
echo esc_url(wc_get_account_endpoint_url('payment-methods')) . "\n\n";

echo "----------------------------------------\n";
echo esc_html__('ORDER DETAILS', 'bocs-wordpress') . "\n";
echo "----------------------------------------\n\n";

/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 */
do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

echo "\n----------------------------------------\n\n";

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

echo "\n----------------------------------------\n\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ($additional_content) {
    echo esc_html(wp_strip_all_tags(wptexturize($additional_content)));
    echo "\n\n----------------------------------------\n\n";
}

echo "Bocs.io - " . wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'))); 