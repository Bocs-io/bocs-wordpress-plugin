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
    <?php /* translators: %s: Customer first name */ ?>
    <p><?php printf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($subscription->get_billing_first_name())); ?></p>
    
    <p><?php esc_html_e('Your subscription has been paused. Your subscription details are shown below for your reference:', 'bocs-wordpress'); ?></p>
    
    <?php
    // For subscription, check the parent order for Bocs App attribution
    $parent_order_id = $subscription->get_parent_id();
    $source_type = get_post_meta($parent_order_id, '_wc_order_attribution_source_type', true);
    $utm_source = get_post_meta($parent_order_id, '_wc_order_attribution_utm_source', true);

    if ($source_type === 'referral' && $utm_source === 'Bocs App') : ?>
    <div class="bocs-app-notice">
        <p><span class="bocs-highlight"><?php esc_html_e('This subscription was created through the Bocs App.', 'bocs-wordpress'); ?></span></p>
    </div>
    <?php endif; ?>
    
    <?php if ($subscription->get_date('next_payment')) : ?>
    <div class="subscription-details">
        <p>
            <span class="subscription-status status-paused"><?php esc_html_e('Paused', 'bocs-wordpress'); ?></span>
        </p>
        <p>
            <?php 
            printf(
                esc_html__('Your subscription is paused and will automatically resume on %s.', 'bocs-wordpress'),
                '<strong>' . esc_html(date_i18n(wc_date_format(), strtotime($subscription->get_date('next_payment')))) . '</strong>'
            ); 
            ?>
        </p>
    </div>
    <?php else: ?>
    <div class="subscription-details">
        <p>
            <span class="subscription-status status-paused"><?php esc_html_e('Paused', 'bocs-wordpress'); ?></span>
        </p>
        <p><?php esc_html_e('Your subscription is paused indefinitely and will not automatically resume.', 'bocs-wordpress'); ?></p>
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

<div class="bocs-email-container">
    <?php if ($additional_content) : ?>
        <div class="bocs-email-content">
            <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
        </div>
    <?php endif; ?>

    <p>
        <?php esc_html_e('If you need to reactivate your subscription sooner, please contact our customer support team.', 'bocs-wordpress'); ?>
    </p>
    
    <p>
        <?php esc_html_e('Thank you for choosing Bocs!', 'bocs-wordpress'); ?>
    </p>
</div>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);
?> 