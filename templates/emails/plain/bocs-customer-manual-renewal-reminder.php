<?php
/**
 * Customer Manual Renewal Reminder email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/customer-manual-renewal-reminder.php.
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

// translators: %1$s: date, %2$s: subscription number
printf(
    esc_html__('This is a reminder that your subscription #%2$s will expire on %1$s. To renew, please make a manual payment before your expiration date to avoid any interruption in service.', 'bocs-wordpress'),
    esc_html(date_i18n(wc_date_format(), $subscription->get_time('end', 'site'))),
    esc_html($subscription->get_order_number())
) . "\n\n";

echo esc_html__('RENEWAL REQUIRED', 'bocs-wordpress') . "\n\n";

echo esc_html__('Please use the link below to renew your subscription.', 'bocs-wordpress') . "\n";

$renewal_url = $subscription->get_view_order_url();
if (!empty($renewal_url)) {
    echo esc_html__('Renew Now:', 'bocs-wordpress') . ' ' . esc_url($renewal_url) . "\n\n";
}

// Check for Bocs App attribution
$source_type = get_post_meta($subscription->get_id(), '_wc_order_attribution_source_type', true);
$utm_source = get_post_meta($subscription->get_id(), '_wc_order_attribution_utm_source', true);

if ($source_type === 'referral' && $utm_source === 'Bocs App') {
    echo esc_html__('This subscription was created through the Bocs App.', 'bocs-wordpress') . "\n\n";
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__('SUBSCRIPTION DETAILS', 'bocs-wordpress') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 */
do_action('woocommerce_email_order_details', $subscription, $sent_to_admin, $plain_text, $email);

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__('CUSTOMER DETAILS', 'bocs-wordpress') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action('woocommerce_email_order_meta', $subscription, $sent_to_admin, $plain_text, $email);

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

echo esc_html__('If you have any questions or need assistance with your renewal, please contact our customer support team.', 'bocs-wordpress') . "\n\n";
echo esc_html__('Thank you for your continued business with Bocs!', 'bocs-wordpress') . "\n\n";

echo wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'))); 