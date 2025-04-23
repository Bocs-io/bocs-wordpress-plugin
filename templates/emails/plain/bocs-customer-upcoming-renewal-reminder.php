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

// Ensure order object exists
if (!isset($order) || !is_object($order)) {
    $order = null;
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading)) . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/* translators: %s: Customer first name */
$customer_name = ($order && is_callable(array($order, 'get_billing_first_name'))) ? $order->get_billing_first_name() : __('there', 'bocs-wordpress');
echo sprintf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($customer_name)) . "\n\n";

// translators: %1$s: order number, %2$s: renewal date
$order_number = ($order && is_callable(array($order, 'get_order_number'))) ? $order->get_order_number() : __('N/A', 'bocs-wordpress');
printf(
    esc_html__('This is a reminder that your subscription #%1$s will automatically renew on %2$s.', 'bocs-wordpress'),
    esc_html($order_number),
    esc_html($renewal_date ?: __('soon', 'bocs-wordpress'))
) . "\n\n";

echo esc_html__('UPCOMING RENEWAL', 'bocs-wordpress') . "\n\n";

$payment_method = ($order && is_callable(array($order, 'get_payment_method_title'))) ? $order->get_payment_method_title() : '';
if ($payment_method) {
    echo esc_html__('Payment Method:', 'bocs-wordpress') . ' ' . esc_html($payment_method) . "\n\n";
}

// Check for Bocs App attribution
$source_type = $order && is_callable(array($order, 'get_meta')) ? $order->get_meta('_wc_order_attribution_source_type') : '';
$utm_source = $order && is_callable(array($order, 'get_meta')) ? $order->get_meta('_wc_order_attribution_utm_source') : '';

if ($source_type === 'referral' && $utm_source === 'Bocs App') {
    echo esc_html__('This subscription was created through the Bocs App.', 'bocs-wordpress') . "\n\n";
}

echo esc_html__('To view or manage your subscription, please visit:', 'bocs-wordpress') . "\n";
$myaccount_url = wc_get_page_permalink('myaccount');
$view_order_url = wc_get_endpoint_url('view-order', '', $myaccount_url);
$manage_url = $order && is_callable(array($order, 'get_order_number')) ? 
    add_query_arg(array('view-order' => $order->get_order_number()), $view_order_url) : 
    $myaccount_url;
echo esc_url($manage_url) . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__('SUBSCRIPTION DETAILS', 'bocs-wordpress') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

// Output order details
echo esc_html__('Product:', 'bocs-wordpress') . ' ';
$order_items = $order && is_callable(array($order, 'get_items')) ? $order->get_items() : array();
if (!empty($order_items)) {
    $product_names = array();
    foreach ($order_items as $item) {
        if (is_callable(array($item, 'get_name'))) {
            $product_names[] = $item->get_name();
        }
    }
    echo implode(', ', $product_names) . "\n";
} else {
    echo __('N/A', 'bocs-wordpress') . "\n";
}

if ($order && is_callable(array($order, 'get_formatted_order_total'))) {
    echo esc_html__('Price:', 'bocs-wordpress') . ' ' . wp_kses_post($order->get_formatted_order_total()) . "\n";
}

if ($order && is_callable(array($order, 'get_status'))) {
    echo esc_html__('Status:', 'bocs-wordpress') . ' ' . esc_html(wc_get_order_status_name($order->get_status())) . "\n";
}

if ($renewal_date) {
    echo esc_html__('Next Payment Date:', 'bocs-wordpress') . ' ' . esc_html($renewal_date) . "\n";
}

echo "\n";

if ($order) {
    echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
    echo esc_html__('CUSTOMER DETAILS', 'bocs-wordpress') . "\n";
    echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

    /*
     * @hooked WC_Emails::customer_details() Shows customer details
     * @hooked WC_Emails::email_address() Shows email address
     */
    do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);
}

if ($additional_content) {
    echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
    echo esc_html(wp_strip_all_tags(wptexturize($additional_content)));
    echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
}

echo esc_html__('If you need to update your payment information or have questions about your subscription, please contact our customer support team.', 'bocs-wordpress') . "\n\n";
echo esc_html__('Thank you for your continued business with Bocs!', 'bocs-wordpress') . "\n\n";

echo wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'))); 