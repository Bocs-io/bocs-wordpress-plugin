<?php
/**
 * Customer invoice email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-invoice.php.
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
    
    <div style="background-color: #f8f9fa; border-left: 4px solid #3C7B7C; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px;">
        <?php if ($order->needs_payment()) : ?>
            <p style="font-size: 16px; margin-bottom: 15px;">
                <?php
                printf(
                    esc_html__('An order has been created for you on %1$s. Your invoice is below, with a link to make payment when you\'re ready.', 'bocs-wordpress'),
                    esc_html(get_bloginfo('name', 'display'))
                );
                ?>
            </p>
            <div style="text-align: center; margin: 15px 0;">
                <a href="<?php echo esc_url($order->get_checkout_payment_url()); ?>" style="background-color: #3C7B7C; border-radius: 4px; color: #ffffff !important; display: inline-block; font-weight: 500; line-height: 100%; margin: 0; text-align: center; text-decoration: none !important; font-size: 14px; padding: 12px 25px; text-transform: uppercase; letter-spacing: 0.5px;">
                    <?php esc_html_e('Pay for this order', 'bocs-wordpress'); ?>
                </a>
            </div>
        <?php else : ?>
            <p style="font-size: 16px; margin-bottom: 15px;">
                <?php
                printf(
                    esc_html__('Here are the details of your order placed on %s.', 'bocs-wordpress'),
                    esc_html(wc_format_datetime($order->get_date_created()))
                );
                ?>
            </p>
        <?php endif; ?>
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

    <div style="background-color: #f8f9fa; border-radius: 6px; padding: 20px; margin-bottom: 30px; border: 1px solid #e5e5e5;">
        <h3 style="margin-top: 0; margin-bottom: 15px; color: #333333; font-size: 18px;"><?php esc_html_e('Order Status', 'bocs-wordpress'); ?></h3>
        <p style="margin-bottom: 15px;">
            <?php 
            $status = $order->get_status();
            $status_colors = array(
                'pending' => 'background-color: #fff8e1; color: #ff6d00;',
                'processing' => 'background-color: #e1f5fe; color: #0288d1;',
                'on-hold' => 'background-color: #f3e5f5; color: #7b1fa2;',
                'completed' => 'background-color: #e8f5e9; color: #388e3c;',
                'failed' => 'background-color: #ffebee; color: #d32f2f;',
                'cancelled' => 'background-color: #f5f5f5; color: #616161;'
            );
            $status_style = isset($status_colors[$status]) ? $status_colors[$status] : 'background-color: #f5f5f5; color: #616161;';
            ?>
            <span style="display: inline-block; padding: 6px 12px; border-radius: 30px; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; <?php echo $status_style; ?>">
                <?php echo esc_html(wc_get_order_status_name($status)); ?>
            </span>
        </p>
    </div>

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
            <?php esc_html_e('If you have any questions about your order, please contact our customer support team.', 'bocs-wordpress'); ?>
        </p>
        
        <p>
            <?php esc_html_e('Thank you for your business with Bocs!', 'bocs-wordpress'); ?>
        </p>
    </div>
</div>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email); 