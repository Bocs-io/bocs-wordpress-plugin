<?php
/**
 * Payment retry email (Bocs specific variant)
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
    <p style="margin: 0 0 16px;">Hi <?php echo esc_html($order->get_billing_first_name()); ?>,</p>
    
    <p style="margin: 0 0 16px;"><?php esc_html_e('We\'re writing to let you know that we will automatically retry the payment for your subscription renewal.', 'bocs-wordpress'); ?></p>
    
    <!-- Payment retry notification box -->
    <div style="background-color: #fff8e1; border-left: 4px solid #ffa000; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px;">
        <p style="margin: 0 0 16px; color: #ff6b00; font-weight: 600;"><?php esc_html_e('Payment Will Be Retried', 'bocs-wordpress'); ?></p>
        <p style="margin: 0 0 16px;"><?php 
            printf(
                esc_html__('We will automatically retry the payment for your subscription renewal on %s.', 'bocs-wordpress'),
                '<strong>' . esc_html(date_i18n(wc_date_format(), strtotime($retry_date))) . '</strong>'
            ); 
        ?></p>
    </div>
    
    <!-- Payment method section, if applicable -->
    <?php if ( ! empty( $order->get_payment_method_title() ) ) : ?>
    <div style="background-color: #f8f9fa; padding: 15px 20px; margin-bottom: 25px; border-radius: 4px; border: 1px solid #e0e0e0;">
        <h3 style="font-size: 16px; color: #333333; margin-bottom: 10px; font-weight: 500;"><?php esc_html_e('Payment Method', 'bocs-wordpress'); ?></h3>
        <p style="margin: 0 0 16px;"><?php echo esc_html($order->get_payment_method_title()); ?></p>
        <p style="margin: 0 0 16px;"><?php esc_html_e('If you want to update your payment details before the retry, please use the button below.', 'bocs-wordpress'); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Bocs App notice, if applicable -->
    <?php if ( function_exists('bocs_order_created_via_app') && bocs_order_created_via_app($order) ) : ?>
    <div style="background-color: #fff8e1; padding: 12px 15px; margin-bottom: 25px; border-radius: 4px; border: 1px dashed #ffa000;">
        <p style="margin: 0 0 16px;"><span style="color: #ff6b00; font-weight: 500;"><?php esc_html_e('This subscription was created through the Bocs App.', 'bocs-wordpress'); ?></span></p>
        <p style="margin: 0 0 16px;"><?php esc_html_e('You can update your payment details directly through the Bocs mobile app.', 'bocs-wordpress'); ?></p>
    </div>
    <?php endif; ?>
</div>

<h2 style="color: #3C7B7C !important; display: block; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 18px; font-weight: bold; line-height: 130%; margin: 0 0 18px; text-align: left;">
    <?php printf(esc_html__('[Order #%s] (%s)', 'bocs-wordpress'), $order->get_order_number(), date_i18n(wc_date_format(), strtotime($order->get_date_created()))); ?>
</h2>

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
    <!-- Update payment button -->
    <div style="margin: 40px 0; text-align: center;">
        <a href="<?php echo esc_url($order->get_checkout_payment_url()); ?>" style="display: inline-block; background-color: #3C7B7C; color: #ffffff; font-size: 16px; font-weight: bold; line-height: 100%; text-decoration: none; padding: 12px 25px; border-radius: 4px;">
            <?php esc_html_e('Update Payment Method', 'bocs-wordpress'); ?>
        </a>
    </div>
    
    <?php if ($additional_content) : ?>
        <div style="margin-bottom: 25px; padding: 0 5px;">
            <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
        </div>
    <?php endif; ?>
    
    <!-- Standard footer info -->
    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e5e5; color: #757575; font-size: 13px;">
        <p style="margin: 0 0 16px;"><?php esc_html_e('If you have any questions or need assistance, please contact our customer support team.', 'bocs-wordpress'); ?></p>
        <p style="margin: 0 0 16px;"><?php esc_html_e('Thank you for choosing Bocs!', 'bocs-wordpress'); ?></p>
    </div>
</div>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email); 