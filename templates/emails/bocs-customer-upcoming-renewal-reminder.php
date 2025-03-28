<?php
/**
 * Upcoming renewal reminder email (Bocs specific variant)
 *
 * @package Bocs/Templates/Emails
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

// Ensure we have the required variables
if (!isset($email_heading)) {
    $email_heading = '';
}

if (!isset($email)) {
    $email = null;
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);
?>

<div style="padding: 0 12px; max-width: 100%;">
    <p style="margin: 0 0 16px;">Hi <?php echo esc_html($order->get_billing_address_1() ? $order->get_billing_first_name() : __('there', 'bocs-wordpress')); ?>,</p>
    
    <p style="margin: 0 0 16px;"><?php esc_html_e('This is a reminder that your subscription renewal payment will be automatically processed soon.', 'bocs-wordpress'); ?></p>
    
    <!-- Reminder notification box -->
    <div style="background-color: #fff8e1; border-left: 4px solid #ffa000; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px;">
        <p style="margin: 0 0 16px; color: #ff6b00; font-weight: 600;"><?php esc_html_e('Upcoming Renewal Reminder', 'bocs-wordpress'); ?></p>
        <p style="margin: 0 0 16px;"><?php 
            if (isset($renewal_date) && !empty($renewal_date)) {
                printf(
                    esc_html__('Your subscription renewal will be automatically processed on %s.', 'bocs-wordpress'),
                    '<strong>' . esc_html($renewal_date) . '</strong>'
                );
            } else {
                esc_html_e('Your subscription renewal will be automatically processed soon.', 'bocs-wordpress');
            }
        ?></p>
    </div>
    
    <!-- Payment method info, if applicable -->
    <?php 
    $payment_method_title = $order->get_payment_method_title();
    if (!empty($payment_method_title)) : 
    ?>
    <div style="background-color: #f8f9fa; padding: 15px 20px; margin-bottom: 25px; border-radius: 4px; border: 1px solid #e0e0e0;">
        <h3 style="font-size: 16px; color: #333333; margin-bottom: 10px; font-weight: 500;"><?php esc_html_e('Payment Method', 'bocs-wordpress'); ?></h3>
        <p style="margin: 0 0 16px;"><strong><?php echo esc_html($payment_method_title); ?></strong></p>
        
        <?php if (strpos(strtolower($payment_method_title), 'stripe') !== false) : ?>
        <p style="margin: 0 0 16px;"><?php esc_html_e('Your card will be automatically charged. No action is needed from you.', 'bocs-wordpress'); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Bocs App notice, if applicable -->
    <?php 
    if (function_exists('bocs_order_created_via_app') && bocs_order_created_via_app($order)) : 
    ?>
    <div style="background-color: #fff8e1; padding: 12px 15px; margin-bottom: 25px; border-radius: 4px; border: 1px dashed #ffa000;">
        <p style="margin: 0 0 16px;"><span style="color: #ff6b00; font-weight: 500;"><?php esc_html_e('This subscription was created through the Bocs App.', 'bocs-wordpress'); ?></span></p>
        <p style="margin: 0 0 16px;"><?php esc_html_e('You can manage your subscription directly through the Bocs mobile app.', 'bocs-wordpress'); ?></p>
    </div>
    <?php endif; ?>
</div>

<h2 style="color: #3C7B7C !important; display: block; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 18px; font-weight: bold; line-height: 130%; margin: 0 0 18px; text-align: left;">
    <?php 
        $order_date = $order->get_date_created();
        $formatted_date = $order_date ? $order_date->format(wc_date_format()) : date_i18n(wc_date_format());
        printf(esc_html__('[Order #%s] (%s)', 'bocs-wordpress'), $order->get_order_number(), $formatted_date); 
    ?>
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
    <!-- Manage subscription button -->
    <div style="margin: 40px 0; text-align: center;">
        <?php
        $order_number = $order->get_order_number();
        $myaccount_url = wc_get_page_permalink('myaccount');
        $view_order_url = wc_get_endpoint_url('view-order', '', $myaccount_url);
        $manage_url = add_query_arg(array('view-order' => $order_number), $view_order_url);
        ?>
        <a href="<?php echo esc_url($manage_url); ?>" style="display: inline-block; background-color: #3C7B7C; color: #ffffff; font-size: 16px; font-weight: bold; line-height: 100%; text-decoration: none; padding: 12px 25px; border-radius: 4px;">
            <?php esc_html_e('Manage Subscription', 'bocs-wordpress'); ?>
        </a>
    </div>
    
    <?php if (isset($additional_content) && !empty($additional_content)) : ?>
        <div style="margin-bottom: 25px; padding: 0 5px;">
            <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
        </div>
    <?php endif; ?>
    
    <!-- Standard footer info -->
    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e5e5; color: #757575; font-size: 13px;">
        <p style="margin: 0 0 16px;"><?php esc_html_e('If you have any questions about your subscription, please contact our customer support team.', 'bocs-wordpress'); ?></p>
        <p style="margin: 0 0 16px;"><?php esc_html_e('Thank you for choosing Bocs!', 'bocs-wordpress'); ?></p>
    </div>
</div>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email); 