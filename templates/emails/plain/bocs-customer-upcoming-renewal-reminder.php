<?php
/**
 * Customer Upcoming Renewal Reminder email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/customer-upcoming-renewal-reminder.php.
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

// translators: %1$s: order number, %2$s: renewal date
printf(
    esc_html__('This is a reminder that your subscription #%1$s will automatically renew on %2$s.', 'bocs-wordpress'),
    esc_html($order->get_order_number()),
    esc_html($renewal_date ?: __('soon', 'bocs-wordpress'))
) . "\n\n";

echo esc_html__('UPCOMING RENEWAL', 'bocs-wordpress') . "\n\n";

if ($order->get_payment_method_title()) {
    echo esc_html__('Payment Method:', 'bocs-wordpress') . ' ' . esc_html($order->get_payment_method_title()) . "\n\n";
}

// Check for Bocs App attribution
$source_type = $order->get_meta('_wc_order_attribution_source_type');
$utm_source = $order->get_meta('_wc_order_attribution_utm_source');

if ($source_type === 'referral' && $utm_source === 'Bocs App') {
    echo esc_html__('This subscription was created through the Bocs App.', 'bocs-wordpress') . "\n\n";
}

echo esc_html__('To view or manage your subscription, please visit:', 'bocs-wordpress') . "\n";
echo esc_url(add_query_arg(array('view-order' => $order->get_order_number()), wc_get_endpoint_url('view-order', '', wc_get_page_permalink('myaccount')))) . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__('SUBSCRIPTION DETAILS', 'bocs-wordpress') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

// Output order details
echo esc_html__('Product:', 'bocs-wordpress') . ' ';
$order_items = $order->get_items();
if (!empty($order_items)) {
    $product_names = array();
    foreach ($order_items as $item) {
        $product_names[] = $item->get_name();
    }
    echo implode(', ', $product_names) . "\n";
}

echo esc_html__('Price:', 'bocs-wordpress') . ' ' . wp_kses_post($order->get_formatted_order_total()) . "\n";
echo esc_html__('Status:', 'bocs-wordpress') . ' ' . esc_html(wc_get_order_status_name($order->get_status())) . "\n";

if ($renewal_date) {
    echo esc_html__('Next Payment Date:', 'bocs-wordpress') . ' ' . esc_html($renewal_date) . "\n";
}

echo "\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__('CUSTOMER DETAILS', 'bocs-wordpress') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

if ($additional_content) {
    echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
    echo esc_html(wp_strip_all_tags(wptexturize($additional_content)));
    echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
}

echo esc_html__('If you need to update your payment information or have questions about your subscription, please contact our customer support team.', 'bocs-wordpress') . "\n\n";
echo esc_html__('Thank you for your continued business with Bocs!', 'bocs-wordpress') . "\n\n";

echo wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'))); 