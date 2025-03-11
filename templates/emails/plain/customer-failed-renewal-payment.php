<?php
/**
 * Customer Failed Renewal Payment email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/customer-failed-renewal-payment.php.
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

echo esc_html__('The automatic payment for your subscription renewal has failed. As a result, your subscription has been suspended. Don\'t worry, we can help you get it back on track!', 'bocs-wordpress') . "\n\n";

echo esc_html__('PAYMENT FAILED', 'bocs-wordpress') . "\n\n";

echo esc_html__('To reactivate your subscription, please update your payment information or make a manual payment.', 'bocs-wordpress') . "\n";

if ($order_url = $order->get_view_order_url()) {
    echo esc_html__('Pay Now:', 'bocs-wordpress') . ' ' . esc_url($order_url) . "\n\n";
}

// Check for Bocs App attribution
$source_type = get_post_meta($order->get_id(), '_wc_order_attribution_source_type', true);
$utm_source = get_post_meta($order->get_id(), '_wc_order_attribution_utm_source', true);

if ($source_type === 'referral' && $utm_source === 'Bocs App') {
    echo esc_html__('Your subscription was created through the Bocs App.', 'bocs-wordpress') . "\n\n";
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__('SUBSCRIPTION DETAILS', 'bocs-wordpress') . "\n";
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
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

// Output next payment attempt
if (isset($next_payment_attempt) && ! empty($next_payment_attempt)) {
    echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
    echo esc_html__('NEXT PAYMENT ATTEMPT', 'bocs-wordpress') . "\n";
    echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
    
    /* translators: %s: date */
    printf(esc_html__('We will attempt to process your subscription payment again on %s', 'bocs-wordpress'), esc_html(date_i18n(wc_date_format(), $next_payment_attempt))) . "\n\n";
}

if ($additional_content) {
    echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
    echo esc_html(wp_strip_all_tags(wptexturize($additional_content)));
    echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
}

echo esc_html__('If you need help or have any questions about your subscription, please contact our customer support team.', 'bocs-wordpress') . "\n\n";
echo esc_html__('We appreciate your business and look forward to continuing to serve you.', 'bocs-wordpress') . "\n\n";

echo wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'))); 