<?php
/**
 * New customer subscription confirmation email (Bocs specific variant)
 *
 * @package Bocs/Templates/Emails
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

// Load colors from WooCommerce settings
$base_color      = get_option('woocommerce_email_base_color');
$bg_color        = get_option('woocommerce_email_background_color');
$body_color      = get_option('woocommerce_email_body_background_color');
$text_color      = get_option('woocommerce_email_text_color');

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);
?>

<div style="padding: 0 12px; max-width: 100%;">
    <p style="margin: 0 0 16px;">Hi <?php echo esc_html($order->get_billing_first_name()); ?>,</p>

    <p style="margin: 0 0 16px;">Welcome to <?php echo esc_html(get_bloginfo('name')); ?>!</p>

</div>
   <!-- Subscription box -->
   <div style="background-color: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px;">
        <p style="margin: 0 0 16px; color: #4caf50; font-weight: 600;"><?php esc_html_e('Subscription Confirmed', 'bocs-wordpress'); ?></p>
        <p style="margin: 0 0 16px;"><?php esc_html_e('Your new subscription has been processed successfully.', 'bocs-wordpress'); ?></p>
    </div>
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

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);
?>