<?php
/**
 * Customer Welcome email
 *
 * This template can be overridden by copying it to yourtheme/bocs-wordpress/emails/bocs-customer-welcome.php.
 *
 * @package Bocs/Templates/Emails
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);
?>

<div class="bocs-email-container">
    <?php /* translators: %s: Customer first name */ ?>
    <p><?php printf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($order->get_billing_first_name())); ?></p>
    
    <p><?php esc_html_e('Welcome to Bocs! Thank you for your first purchase. We\'re excited to have you as a customer.', 'bocs-wordpress'); ?></p>

    <?php
    // Display Bocs ID information
    $bocs_id = get_post_meta($order->get_id(), '__bocs_bocs_id', true);

    if (!empty($bocs_id)) : ?>
    <div class="bocs-app-notice">
        <p><span class="bocs-highlight"><?php printf(esc_html__('Your Bocs ID: %s', 'bocs-wordpress'), esc_html($bocs_id)); ?></span></p>
        <p><?php esc_html_e('This is your first purchase through the Bocs platform. Your Bocs ID helps us identify your account and provide you with seamless service.', 'bocs-wordpress'); ?></p>
    </div>
    <?php else: ?>
    <div class="bocs-app-notice">
        <p><?php esc_html_e('This is your first purchase. Welcome to the Bocs platform!', 'bocs-wordpress'); ?></p>
    </div>
    <?php endif; ?>

    <h2><?php esc_html_e('Order Details', 'bocs-wordpress'); ?></h2>
    <p><?php esc_html_e('Here are the details of your order:', 'bocs-wordpress'); ?></p>
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
?>

<div class="bocs-email-container">
    <?php if ($additional_content) : ?>
        <div class="bocs-email-content">
            <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
        </div>
    <?php endif; ?>

    <p>
        <?php esc_html_e('If you have any questions about your order, please contact our customer support team.', 'bocs-wordpress'); ?>
    </p>
    
    <p>
        <?php esc_html_e('Thank you for choosing Bocs!', 'bocs-wordpress'); ?>
    </p>
</div>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email); 