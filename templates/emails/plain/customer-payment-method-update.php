<?php
/**
 * Customer Payment Method Update email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/customer-payment-method-update.php.
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

echo esc_html__('This is a confirmation that your payment method for your subscription(s) has been updated.', 'bocs-wordpress') . "\n\n";

echo esc_html__('PAYMENT METHOD UPDATED', 'bocs-wordpress') . "\n\n";

if ($payment_method_title) {
    echo esc_html__('New Payment Method:', 'bocs-wordpress') . ' ' . esc_html($payment_method_title) . "\n\n";
}

// Check for Bocs App attribution
$source_type = get_post_meta($order->get_id(), '_wc_order_attribution_source_type', true);
$utm_source = get_post_meta($order->get_id(), '_wc_order_attribution_utm_source', true);

if ($source_type === 'referral' && $utm_source === 'Bocs App') {
    echo esc_html__('Your subscription was created through the Bocs App.', 'bocs-wordpress') . "\n\n";
}

echo esc_html__('You can manage all your payment methods from your account dashboard:', 'bocs-wordpress') . "\n";
echo esc_url(wc_get_account_endpoint_url('payment-methods')) . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__('SUBSCRIPTIONS UPDATED', 'bocs-wordpress') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

if (!empty($subscriptions) && is_array($subscriptions)) {
    foreach ($subscriptions as $subscription_id => $subscription) {
        echo esc_html__('Subscription:', 'bocs-wordpress') . ' #' . esc_html($subscription_id) . "\n";
        
        if ($subscription->get_formatted_order_total()) {
            /* translators: %s: Subscription's formatted order total */
            echo sprintf(esc_html__('Amount: %s', 'bocs-wordpress'), $subscription->get_formatted_order_total()) . "\n";
        }
        
        if ($subscription->get_date('next_payment') > 0) {
            /* translators: %s: Subscription's next payment date */
            echo sprintf(esc_html__('Next Payment: %s', 'bocs-wordpress'), date_i18n(wc_date_format(), $subscription->get_date('next_payment'))) . "\n";
        }
        
        echo "\n";
    }
}

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

echo esc_html__('If you didn\'t make this change or need assistance, please contact our customer support team immediately.', 'bocs-wordpress') . "\n\n";
echo esc_html__('Thank you for your business with Bocs!', 'bocs-wordpress') . "\n\n";

echo wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'))); 