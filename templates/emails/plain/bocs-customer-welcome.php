<?php
/**
 * Customer Welcome email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/bocs-wordpress/emails/plain/bocs-customer-welcome.php.
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
echo esc_html__('Welcome to Bocs! Thank you for your first purchase. We\'re excited to have you as a customer.', 'bocs-wordpress') . "\n\n";

// Display Bocs ID information
$bocs_id = get_post_meta($order->get_id(), '__bocs_bocs_id', true);

if (!empty($bocs_id)) {
    echo sprintf(esc_html__('Your Bocs ID: %s', 'bocs-wordpress'), esc_html($bocs_id)) . "\n\n";
    echo esc_html__('This is your first purchase through the Bocs platform. Your Bocs ID helps us identify your account and provide you with seamless service.', 'bocs-wordpress') . "\n\n";
}

echo esc_html__('ORDER DETAILS', 'bocs-wordpress') . "\n";
echo esc_html__('Here are the details of your order:', 'bocs-wordpress') . "\n\n";

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

// Additional content
if ($additional_content) {
    echo esc_html(wp_strip_all_tags(wptexturize($additional_content)));
    echo "\n\n";
}

echo esc_html__('If you have any questions about your order, please contact our customer support team.', 'bocs-wordpress') . "\n\n";
echo esc_html__('Thank you for choosing Bocs!', 'bocs-wordpress') . "\n\n";

echo "\n----------------------------------------\n\n";
echo esc_html(wp_strip_all_tags(get_option('woocommerce_email_footer_text', ''))); 