<?php
/**
 * Customer Welcome email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-welcome.php.
 *
 * @package Bocs/Templates/Emails
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email); ?>

<?php /* translators: %s: Customer first name */ ?>
<p><?php printf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($order->get_billing_first_name())); ?></p>
<p><?php esc_html_e('Welcome to Bocs! Thank you for your first purchase. We\'re excited to have you as a customer.', 'bocs-wordpress'); ?></p>

<?php
// Display Bocs ID information
$bocs_id = get_post_meta($order->get_id(), '__bocs_bocs_id', true);

if (!empty($bocs_id)) : ?>
<p><strong><?php printf(esc_html__('Your Bocs ID: %s', 'bocs-wordpress'), esc_html($bocs_id)); ?></strong></p>
<p><?php esc_html_e('This is your first purchase through the Bocs platform. Your Bocs ID helps us identify your account and provide you with seamless service.', 'bocs-wordpress'); ?></p>
<?php endif; ?>

<p><?php esc_html_e('Here are the details of your order:', 'bocs-wordpress'); ?></p>

<?php

/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 */
do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ($additional_content) {
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email); 