<?php
/**
 * Customer Subscription Paused email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-subscription-paused.php.
 *
 * @package Bocs/Templates/Emails
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);

// Include email styles
include(dirname(__FILE__) . '/email-styles.php');
?>

<div class="bocs-email-container">
    <div class="bocs-email-content">
        <!-- Bocs.io Logo (SVG embedded with fallback) -->
        <div class="bocs-logo">
            <svg width="180" height="60" viewBox="0 0 465 140" xmlns="http://www.w3.org/2000/svg">
                <path d="M171.2,71.8c-2.6-3.5-6.4-6.1-11.4-6.1c-8.5,0-15.3,7.1-15.3,15.8c0,8.7,6.8,15.8,15.3,15.8c5,0,8.8-2.6,11.4-6.1v5.3h9.5V66.5h-9.5V71.8z M161.7,87.3c-3.3,0-6-2.7-6-6s2.7-6,6-6c3.3,0,6,2.7,6,6S165,87.3,161.7,87.3z" fill="#3C7B7C"/>
                <path d="M220.7,53.8c-18.3,0-33.2,14.9-33.2,33.2c0,18.3,14.9,33.2,33.2,33.2s33.2-14.9,33.2-33.2C253.9,68.6,239,53.8,220.7,53.8z M220.7,110.1c-12.8,0-23.2-10.4-23.2-23.2c0-12.8,10.4-23.2,23.2-23.2c12.8,0,23.2,10.4,23.2,23.2C243.9,99.7,233.5,110.1,220.7,110.1z" fill="#3C7B7C"/>
                <path d="M328.5,66.5v5.3c-2.6-3.5-6.4-6.1-11.4-6.1c-8.5,0-15.3,7.1-15.3,15.8c0,8.7,6.8,15.8,15.3,15.8c5,0,8.8-2.6,11.4-6.1v5.3h9.5V66.5H328.5z M319,87.3c-3.3,0-6-2.7-6-6s2.7-6,6-6c3.3,0,6,2.7,6,6S322.3,87.3,319,87.3z" fill="#3C7B7C"/>
                <path d="M127.1,20c-8.1-9.9-23.3-22.7-44.1-11.1C62,20.5,58.3,46.7,58.3,46.7S32.1,50.3,20.6,71.2C9,92.1,21.8,107.3,31.7,115.4c9.9,8.1,25.1,20.9,46,9.3c20.9-11.6,24.6-37.7,24.6-37.7s26.2-3.7,37.7-24.6C151.4,41.6,138.6,26.3,127.1,20z" fill="#3C7B7C"/>
                <circle cx="415.4" cy="72" r="20" fill="#3C7B7C"/>
                <path d="M415.4,116.4c-9.9,0-17.9-8-17.9-17.9s8-17.9,17.9-17.9s17.9,8,17.9,17.9S425.3,116.4,415.4,116.4z M415.4,86.6c-6.6,0-12,5.4-12,12c0,6.6,5.4,12,12,12c6.6,0,12-5.4,12-12C427.4,92,422,86.6,415.4,86.6z" fill="#3C7B7C"/>
            </svg>
        </div>

        <h2 class="bocs-header"><?php esc_html_e('Subscription Paused', 'bocs-wordpress'); ?></h2>

        <p><?php printf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($subscription->get_billing_first_name())); ?></p>

        <p><?php esc_html_e('Your subscription has been paused. No further payments will be processed until it\'s reactivated.', 'bocs-wordpress'); ?></p>

        <?php
        // Display Bocs App attribution
        $source_type = get_post_meta($subscription->get_id(), '_wc_order_attribution_source_type', true);
        $utm_source = get_post_meta($subscription->get_id(), '_wc_order_attribution_utm_source', true);

        if ($source_type === 'referral' && $utm_source === 'Bocs App') : ?>
            <div class="bocs-app-notice">
                <p><strong><?php esc_html_e('This subscription was created through the Bocs App.', 'bocs-wordpress'); ?></strong></p>
            </div>
        <?php endif; ?>

        <p><?php esc_html_e('You can reactivate your subscription at any time from your account.', 'bocs-wordpress'); ?></p>

        <a href="<?php echo esc_url($subscription->get_view_order_url()); ?>" class="bocs-button">
            <?php esc_html_e('View Subscription', 'bocs-wordpress'); ?>
        </a>

        <h3 class="bocs-header"><?php esc_html_e('Subscription Details', 'bocs-wordpress'); ?></h3>
    </div>
</div>

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
?> 