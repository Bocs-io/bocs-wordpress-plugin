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

echo "BOCS.IO - PAYMENT METHOD UPDATE REQUIRED\n\n";

/* translators: %s: Customer first name */
echo sprintf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($subscription->get_billing_first_name())) . "\n\n";

if (!empty($reason)) {
    echo esc_html($reason) . "\n\n";
} else {
    echo esc_html__('We\'re contacting you regarding your subscription because your payment method needs to be updated. This could be because your card is expiring, has already expired, or has been declined.', 'bocs-wordpress') . "\n\n";
}

// Display Bocs App attribution
$source_type = get_post_meta($subscription->get_id(), '_wc_order_attribution_source_type', true);
$utm_source = get_post_meta($subscription->get_id(), '_wc_order_attribution_utm_source', true);

if ($source_type === 'referral' && $utm_source === 'Bocs App') {
    echo "** " . esc_html__('This subscription was created through the Bocs App.', 'bocs-wordpress') . " **\n\n";
}

echo esc_html__('To update your payment method, please log in to your account and navigate to the subscription details.', 'bocs-wordpress') . "\n\n";

echo esc_html__('Update payment method at:', 'bocs-wordpress') . "\n";
echo esc_url($subscription->get_view_order_url()) . "\n\n";

echo "----------------------------------------\n";
echo esc_html__('SUBSCRIPTION DETAILS', 'bocs-wordpress') . "\n";
echo "----------------------------------------\n\n";

/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 */
do_action('woocommerce_email_order_details', $subscription, $sent_to_admin, $plain_text, $email);

echo "\n----------------------------------------\n\n";

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action('woocommerce_email_order_meta', $subscription, $sent_to_admin, $plain_text, $email);

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action('woocommerce_email_customer_details', $subscription, $sent_to_admin, $plain_text, $email);

echo "\n----------------------------------------\n\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ($additional_content) {
    echo esc_html(wp_strip_all_tags(wptexturize($additional_content)));
    echo "\n\n----------------------------------------\n\n";
}

echo "Bocs.io - " . wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'))); 