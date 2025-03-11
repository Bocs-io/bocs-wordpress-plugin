<?php
/**
 * Customer Manual Renewal Reminder email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-manual-renewal-reminder.php.
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
	<p><?php printf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($subscription->get_billing_first_name())); ?></p>

	<p class="bocs-important-notice">
		<?php
		// translators: %1$s: date, %2$s: subscription number
		printf(
			esc_html__('This is a reminder that your subscription #%2$s will expire on %1$s. To renew, please make a manual payment before your expiration date to avoid any interruption in service.', 'bocs-wordpress'),
			esc_html(date_i18n(wc_date_format(), $subscription->get_time('end', 'site'))),
			esc_html($subscription->get_order_number())
		);
		?>
	</p>

	<div class="subscription-details">
		<p>
			<span class="subscription-status status-active"><?php esc_html_e('Renewal Required', 'bocs-wordpress'); ?></span>
		</p>

		<div class="bocs-callout">
			<p><?php esc_html_e('Please click the button below to renew your subscription.', 'bocs-wordpress'); ?></p>
			<?php
			$renewal_url = $subscription->get_view_order_url();
			if (!empty($renewal_url)) : ?>
				<a href="<?php echo esc_url($renewal_url); ?>" class="bocs-button"><?php esc_html_e('Renew Now', 'bocs-wordpress'); ?></a>
			<?php endif; ?>
		</div>
	</div>
	
	<?php
	// Check for Bocs App attribution
	$source_type = get_post_meta($subscription->get_id(), '_wc_order_attribution_source_type', true);
	$utm_source = get_post_meta($subscription->get_id(), '_wc_order_attribution_utm_source', true);

	if ($source_type === 'referral' && $utm_source === 'Bocs App') : ?>
	<div class="bocs-app-notice">
		<p><span class="bocs-highlight"><?php esc_html_e('This subscription was created through the Bocs App.', 'bocs-wordpress'); ?></span></p>
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
?>

<div class="bocs-email-container">
	<?php if ($additional_content) : ?>
		<div class="bocs-email-content">
			<?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
		</div>
	<?php endif; ?>

	<p>
		<?php esc_html_e('If you have any questions or need assistance with your renewal, please contact our customer support team.', 'bocs-wordpress'); ?>
	</p>
	
	<p>
		<?php esc_html_e('Thank you for your continued business with Bocs!', 'bocs-wordpress'); ?>
	</p>
</div>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email); 