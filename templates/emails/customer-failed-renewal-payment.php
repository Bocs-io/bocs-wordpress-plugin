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

<div class="bocs-email-container">
	<?php /* translators: %s: Customer first name */ ?>
	<p><?php printf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($order->get_billing_first_name())); ?></p>

	<p class="bocs-important-notice"><?php esc_html_e('The automatic payment for your subscription renewal has failed. As a result, your subscription has been suspended. Don\'t worry, we can help you get it back on track!', 'bocs-wordpress'); ?></p>

	<div class="subscription-details">
		<p>
			<span class="subscription-status status-failed"><?php esc_html_e('Payment Failed', 'bocs-wordpress'); ?></span>
		</p>

		<div class="bocs-callout">
			<p><?php esc_html_e('To reactivate your subscription, please update your payment information or make a manual payment using the button below.', 'bocs-wordpress'); ?></p>
			<?php if ($order_url = $order->get_view_order_url()) { ?>
				<a href="<?php echo esc_url($order_url); ?>" class="bocs-button"><?php esc_html_e('Pay Now', 'bocs-wordpress'); ?></a>
			<?php } ?>
		</div>
	</div>
	
	<?php
	// Check for Bocs App attribution
	$source_type = get_post_meta($order->get_id(), '_wc_order_attribution_source_type', true);
	$utm_source = get_post_meta($order->get_id(), '_wc_order_attribution_utm_source', true);

	if ($source_type === 'referral' && $utm_source === 'Bocs App') : ?>
	<div class="bocs-app-notice">
		<p><span class="bocs-highlight"><?php esc_html_e('Your subscription was created through the Bocs App.', 'bocs-wordpress'); ?></span></p>
	</div>
	<?php endif; ?>

	<h2><?php esc_html_e('Subscription Details', 'bocs-wordpress'); ?></h2>
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
	<div class="bocs-email-container">
		<h2><?php esc_html_e('Next Payment Attempt', 'bocs-wordpress'); ?></h2>
		<p><?php printf(esc_html__('We will attempt to process your subscription payment again on %s', 'bocs-wordpress'), esc_html(date_i18n(wc_date_format(), $next_payment_attempt))); ?></p>
	</div>
	<?php
}
?>

<div class="bocs-email-container">
	<?php if ($additional_content) : ?>
		<div class="bocs-email-content">
			<?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
		</div>
	<?php endif; ?>

	<p>
		<?php esc_html_e('If you need help or have any questions about your subscription, please contact our customer support team.', 'bocs-wordpress'); ?>
	</p>
	
	<p>
		<?php esc_html_e('We appreciate your business and look forward to continuing to serve you.', 'bocs-wordpress'); ?>
	</p>
</div>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email); 