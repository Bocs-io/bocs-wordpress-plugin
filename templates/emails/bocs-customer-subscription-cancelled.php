<?php
/**
 * Subscription cancelled email (Bocs specific variant)
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
    <p style="margin: 0 0 16px;">Hi <?php echo esc_html($subscription->get_billing_first_name()); ?>,</p>
    
    <p style="margin: 0 0 16px;"><?php esc_html_e('Your subscription has been cancelled. We\'re sorry to see you go.', 'bocs-wordpress'); ?></p>
    
    <!-- Cancellation notification box -->
    <div style="background-color: #ffe7e6; border-left: 4px solid #f44336; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px;">
        <p style="margin: 0 0 16px; color: #f44336; font-weight: 600;"><?php esc_html_e('Subscription Cancelled', 'bocs-wordpress'); ?></p>
        
        <?php if (!empty($cancellation_reason)) : ?>
            <p style="margin: 0 0 16px;"><strong><?php esc_html_e('Cancellation reason:', 'bocs-wordpress'); ?></strong> <?php echo esc_html($cancellation_reason); ?></p>
        <?php endif; ?>
        
        <p style="margin: 0 0 16px;"><?php esc_html_e('Your subscription has been cancelled as requested. You will no longer be charged for this subscription.', 'bocs-wordpress'); ?></p>
    </div>
    
    <!-- Bocs App notice, if applicable -->
    <?php if ( function_exists('bocs_order_created_via_app') && bocs_order_created_via_app($subscription) ) : ?>
    <div style="background-color: #fff8e1; padding: 12px 15px; margin-bottom: 25px; border-radius: 4px; border: 1px dashed #ffa000;">
        <p style="margin: 0 0 16px;"><span style="color: #ff6b00; font-weight: 500;"><?php esc_html_e('This subscription was cancelled through the Bocs App.', 'bocs-wordpress'); ?></span></p>
    </div>
    <?php endif; ?>
    
    <!-- Feedback section -->
    <div style="background-color: #f5f5f5; padding: 20px; margin-bottom: 30px; border-radius: 4px;">
        <h3 style="color: #333333; font-size: 16px; font-weight: 600; margin-bottom: 15px;"><?php esc_html_e('We Value Your Feedback', 'bocs-wordpress'); ?></h3>
        <p style="margin: 0 0 16px;"><?php esc_html_e('We\'d love to know why you decided to cancel your subscription. Your feedback helps us improve our service.', 'bocs-wordpress'); ?></p>
        
        <!-- Feedback button -->
        <div style="margin: 20px 0; text-align: center;">
            <a href="https://app.bocs.io/feedback" style="display: inline-block; background-color: #ff6b00; color: #ffffff; font-size: 16px; font-weight: bold; line-height: 100%; text-decoration: none; padding: 12px 25px; border-radius: 4px;">
                <?php esc_html_e('Share Feedback', 'bocs-wordpress'); ?>
            </a>
        </div>
    </div>
</div>

<h2 style="color: #3C7B7C !important; display: block; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 18px; font-weight: bold; line-height: 130%; margin: 0 0 18px; text-align: left;">
    <?php printf(esc_html__('[Order #%s] (%s)', 'bocs-wordpress'), $subscription->get_order_number(), date_i18n(wc_date_format(), strtotime($subscription->get_date_created()))); ?>
</h2>

<?php
/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 */
do_action('woocommerce_email_order_details', $subscription, $sent_to_admin, $plain_text, $email);

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action('woocommerce_email_order_meta', $subscription, $sent_to_admin, $plain_text, $email);

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action('woocommerce_email_customer_details', $subscription, $sent_to_admin, $plain_text, $email);
?>

<div style="padding: 0 12px; max-width: 100%;">
    <!-- Reactivation message -->
    <div style="background-color: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px 20px; margin: 30px 0; border-radius: 4px;">
        <p style="margin: 0 0 16px; color: #4caf50; font-weight: 600;"><?php esc_html_e('Want to Come Back?', 'bocs-wordpress'); ?></p>
        <p style="margin: 0 0 16px;"><?php esc_html_e('If you change your mind, you can reactivate your subscription at any time through your account.', 'bocs-wordpress'); ?></p>
    </div>
    
    <?php if ($additional_content) : ?>
        <div style="margin-bottom: 25px; padding: 0 5px;">
            <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
        </div>
    <?php endif; ?>
    
    <!-- Standard footer info -->
    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e5e5; color: #757575; font-size: 13px;">
        <p style="margin: 0 0 16px;"><?php esc_html_e('If you have any questions about your cancelled subscription, please contact our customer support team.', 'bocs-wordpress'); ?></p>
        <p style="margin: 0 0 16px;"><?php esc_html_e('Thank you for your time with Bocs!', 'bocs-wordpress'); ?></p>
    </div>
</div>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email); 