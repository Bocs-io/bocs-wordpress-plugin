<?php
/**
 * Customer Failed Renewal Payment email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-failed-renewal-payment.php.
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

	<p style="background-color: #ffebee; padding: 12px 15px; border-radius: 4px; border-left: 4px solid #d32f2f; font-weight: 500; color: #d32f2f; margin-bottom: 25px;"><?php esc_html_e('The automatic payment for your subscription renewal has failed. As a result, your subscription has been suspended. Don\'t worry, we can help you get it back on track!', 'bocs-wordpress'); ?></p>

	<div style="background-color: #f8f9fa; border-radius: 6px; padding: 20px; margin-bottom: 30px; border: 1px solid #e5e5e5;">
		<p style="margin-bottom: 15px;">
			<span style="display: inline-block; padding: 6px 12px; border-radius: 30px; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; background-color: #ffebee; color: #d32f2f;">
                <?php esc_html_e('Payment Failed', 'bocs-wordpress'); ?>
            </span>
		</p>

		<div style="background-color: #fffde7; padding: 15px; border-radius: 4px; border: 1px solid #fff9c4; margin-top: 15px;">
			<p style="margin-bottom: 15px;"><?php esc_html_e('To reactivate your subscription, please update your payment information or make a manual payment using the button below.', 'bocs-wordpress'); ?></p>
			<?php if ($order_url = $order->get_view_order_url()) { ?>
				<div style="text-align: center; margin: 20px 0 10px;">
                    <a href="<?php echo esc_url($order_url); ?>" style="background-color: #3C7B7C; border-radius: 4px; color: #ffffff !important; display: inline-block; font-weight: 500; line-height: 100%; margin: 0; text-align: center; text-decoration: none !important; font-size: 14px; padding: 12px 25px; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e('Pay Now', 'bocs-wordpress'); ?></a>
                </div>
			<?php } ?>
		</div>
	</div>
	
	<?php
	// Check for Bocs App attribution
	$source_type = get_post_meta($order->get_id(), '_wc_order_attribution_source_type', true);
	$utm_source = get_post_meta($order->get_id(), '_wc_order_attribution_utm_source', true);

	if ($source_type === 'referral' && $utm_source === 'Bocs App') : ?>
	<div style="background-color: #fff8e1; padding: 12px 15px; margin-bottom: 25px; border-radius: 4px; border: 1px dashed #ffc107;">
        <p><span style="color: #ff6b00; font-weight: 500;"><?php esc_html_e('Your subscription was created through the Bocs App.', 'bocs-wordpress'); ?></span></p>
    </div>
	<?php endif; ?>

	<h2 style="color: #333333; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 22px; font-weight: 500; line-height: 130%; margin: 0 0 18px; text-align: left;"><?php esc_html_e('Subscription Details', 'bocs-wordpress'); ?></h2>
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

// Output next payment attempt
if (isset($next_payment_attempt) && ! empty($next_payment_attempt)) {
	/* translators: %s: date */
	?>
	<div style="padding: 0 12px; max-width: 100%;">
		<h2 style="color: #333333; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 22px; font-weight: 500; line-height: 130%; margin: 0 0 18px; text-align: left;"><?php esc_html_e('Next Payment Attempt', 'bocs-wordpress'); ?></h2>
		<p style="background-color: #e8f5e9; padding: 12px 15px; border-radius: 4px; border-left: 4px solid #388e3c; margin-bottom: 25px;"><?php printf(esc_html__('We will attempt to process your subscription payment again on %s', 'bocs-wordpress'), esc_html(date_i18n(wc_date_format(), $next_payment_attempt))); ?></p>
	</div>
	<?php
}
?>

<div style="padding: 0 12px; max-width: 100%;">
	<?php if ($additional_content) : ?>
		<div style="margin-bottom: 25px; padding: 0 5px;">
            <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
        </div>
	<?php endif; ?>

	<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e5e5; color: #757575; font-size: 13px;">
        <p>
            <?php esc_html_e('If you need help or have any questions about your subscription, please contact our customer support team.', 'bocs-wordpress'); ?>
        </p>
        
        <p>
            <?php esc_html_e('We appreciate your business and look forward to continuing to serve you.', 'bocs-wordpress'); ?>
        </p>
    </div>
</div>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email); 