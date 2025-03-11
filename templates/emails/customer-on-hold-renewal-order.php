<?php
/**
 * Customer On-hold Renewal Order email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-on-hold-renewal-order.php.
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

<div style="padding: 0 12px; max-width: 100%;">
    <?php /* translators: %s: Customer first name */ ?>
    <p><?php printf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($order->get_billing_first_name())); ?></p>
    
    <div style="background-color: #f8f9fa; border-left: 4px solid #3C7B7C; padding: 15px 20px; margin-bottom: 25px; border-radius: 4px;">
        <p style="font-size: 16px; margin-bottom: 10px;"><?php esc_html_e('Thanks for your renewal order. It\'s on hold until we confirm payment has been received.', 'bocs-wordpress'); ?></p>
        <p style="margin-bottom: 5px;"><?php esc_html_e('For your reference, your order details are shown below.', 'bocs-wordpress'); ?></p>
    </div>
    
    <?php
    // Check for Bocs App attribution
    $source_type = get_post_meta($order->get_id(), '_wc_order_attribution_source_type', true);
    $utm_source = get_post_meta($order->get_id(), '_wc_order_attribution_utm_source', true);

    if ($source_type === 'referral' && $utm_source === 'Bocs App') : ?>
    <div style="background-color: #fff8e1; padding: 12px 15px; margin-bottom: 25px; border-radius: 4px; border: 1px dashed #ffc107;">
        <p><span style="color: #ff6b00; font-weight: 500;"><?php esc_html_e('This order was created through the Bocs App.', 'bocs-wordpress'); ?></span></p>
    </div>
    <?php endif; ?>

    <?php if ($order->get_payment_method_title()) : ?>
    <div style="background-color: #f8f9fa; border-radius: 6px; padding: 20px; margin-bottom: 30px; border: 1px solid #e5e5e5;">
        <p style="margin-bottom: 15px;">
            <span style="display: inline-block; padding: 6px 12px; border-radius: 30px; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; background-color: #f3e5f5; color: #7b1fa2;">
                <?php esc_html_e('Payment Pending', 'bocs-wordpress'); ?>
            </span>
        </p>
        <p>
            <?php 
            /* translators: %s: payment method */
            printf(
                esc_html__('Payment method: %s', 'bocs-wordpress'),
                '<strong>' . esc_html($order->get_payment_method_title()) . '</strong>'
            ); 
            ?>
        </p>
    </div>
    <?php endif; ?>

    <h2 style="color: #333333; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 22px; font-weight: 500; line-height: 130%; margin: 0 0 18px; text-align: left;"><?php esc_html_e('Order Details', 'bocs-wordpress'); ?></h2>
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

<div style="padding: 0 12px; max-width: 100%;">
    <?php if ($additional_content) : ?>
        <div style="margin-bottom: 25px; padding: 0 5px;">
            <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
        </div>
    <?php endif; ?>

    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e5e5; color: #757575; font-size: 13px;">
        <p>
            <?php esc_html_e('If you have any questions about your order or payment status, please contact our customer support team.', 'bocs-wordpress'); ?>
        </p>
        
        <p>
            <?php esc_html_e('Thank you for your continued business with Bocs!', 'bocs-wordpress'); ?>
        </p>
    </div>
</div>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email); 