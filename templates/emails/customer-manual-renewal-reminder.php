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
do_action('woocommerce_email_header', $email_heading, $email); ?>

<?php /* translators: %s: Customer first name */ ?>
<p><?php printf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($subscription->get_billing_first_name())); ?></p>

<?php if (!empty($expiry_date)) : ?>
<p>
    <?php
    printf(
        esc_html__('This is a reminder that your subscription will expire on %s if you don\'t renew it.', 'bocs-wordpress'), 
        '<strong>' . esc_html($expiry_date) . '</strong>'
    );
    ?>
</p>
<?php else : ?>
<p><?php esc_html_e('This is a friendly reminder that your subscription will expire soon if you don\'t renew it.', 'bocs-wordpress'); ?></p>
<?php endif; ?>

<?php
// Display Bocs App attribution
$parent_order_id = is_callable(array($subscription, 'get_parent_id')) ? $subscription->get_parent_id() : $subscription->get_id();
$source_type = get_post_meta($parent_order_id, '_wc_order_attribution_source_type', true);
$utm_source = get_post_meta($parent_order_id, '_wc_order_attribution_utm_source', true);

if ($source_type === 'referral' && $utm_source === 'Bocs App') : ?>
<p><strong><?php esc_html_e('This subscription was created through the Bocs App.', 'bocs-wordpress'); ?></strong></p>
<?php endif; ?>

<p><?php esc_html_e('To ensure uninterrupted service, please renew your subscription by clicking the button below:', 'bocs-wordpress'); ?></p>

<p>
    <a href="<?php echo esc_url($renewal_url); ?>" class="button button-primary" style="display: inline-block; padding: 10px 15px; background-color: #0066cc; color: #ffffff; text-decoration: none; border-radius: 4px; margin: 10px 0;">
        <?php esc_html_e('Renew Now', 'bocs-wordpress'); ?>
    </a>
</p>

<p><?php esc_html_e('Here are the details of your subscription:', 'bocs-wordpress'); ?></p>

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

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ($additional_content) {
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email); 