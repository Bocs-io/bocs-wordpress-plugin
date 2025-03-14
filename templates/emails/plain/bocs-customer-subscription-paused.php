<?php
/**
 * Customer Subscription Paused email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/customer-subscription-paused.php.
 *
 * @package Bocs/Templates/Emails/Plain
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading)) . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/* translators: %s: Customer first name */
echo sprintf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($subscription->get_billing_first_name())) . "\n\n";

/* translators: %1$s: subscription ID, %2$s: pause date */
echo sprintf(esc_html__('Your subscription #%1$s has been paused. This subscription will automatically resume on %2$s.', 'bocs-wordpress'), esc_html($subscription->get_id()), esc_html(date_i18n(wc_date_format(), $subscription->get_time('next_payment', 'site')))) . "\n\n";

echo esc_html__('SUBSCRIPTION PAUSED', 'bocs-wordpress') . "\n\n";

if ($subscription->get_suspension_count() > 0) {
    echo esc_html__('Pause Count:', 'bocs-wordpress') . ' ' . esc_html($subscription->get_suspension_count()) . "\n\n";
}

// Check for Bocs App attribution
$source_type = get_post_meta($subscription->get_id(), '_wc_order_attribution_source_type', true);
$utm_source = get_post_meta($subscription->get_id(), '_wc_order_attribution_utm_source', true);

if ($source_type === 'referral' && $utm_source === 'Bocs App') {
    echo esc_html__('This subscription was created through the Bocs App.', 'bocs-wordpress') . "\n\n";
}

echo esc_html__('You can reactivate your subscription early by visiting your account:', 'bocs-wordpress') . "\n";
echo esc_url($subscription->get_view_order_url()) . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__('SUBSCRIPTION DETAILS', 'bocs-wordpress') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

// Output subscription details
echo esc_html__('Product:', 'bocs-wordpress') . ' ';
$subscription_items = $subscription->get_items();
if (!empty($subscription_items)) {
    $product_names = array();
    foreach ($subscription_items as $item) {
        $product_names[] = $item->get_name();
    }
    echo implode(', ', $product_names) . "\n";
}

echo esc_html__('Price:', 'bocs-wordpress') . ' ' . wp_kses_post($subscription->get_formatted_order_total()) . "\n";
echo esc_html__('Frequency:', 'bocs-wordpress') . ' ' . esc_html(wcs_get_subscription_period_interval_strings($subscription->get_billing_interval())) . ' ' . esc_html(wcs_get_subscription_period_strings(1, $subscription->get_billing_period())) . "\n";
echo esc_html__('Status:', 'bocs-wordpress') . ' ' . esc_html(wcs_get_subscription_status_name($subscription->get_status())) . "\n";

if ($subscription->get_time('next_payment') > 0) {
    echo esc_html__('Next Payment Date:', 'bocs-wordpress') . ' ' . esc_html(date_i18n(wc_date_format(), $subscription->get_time('next_payment', 'site'))) . "\n";
}

echo "\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__('CUSTOMER DETAILS', 'bocs-wordpress') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action('woocommerce_email_customer_details', $subscription, $sent_to_admin, $plain_text, $email);

if ($additional_content) {
    echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
    echo esc_html(wp_strip_all_tags(wptexturize($additional_content)));
    echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
}

echo esc_html__('If you need assistance or want to reactivate your subscription earlier, please contact our customer support team.', 'bocs-wordpress') . "\n\n";
echo esc_html__('Thank you for being a Bocs customer!', 'bocs-wordpress') . "\n\n";

echo wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'))); 