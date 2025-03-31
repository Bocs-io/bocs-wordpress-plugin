<?php
/**
 * Bocs Customer Subscription Reactivated Email Template
 *
 * This template can be overridden by copying it to yourtheme/bocs-wordpress/emails/bocs-customer-subscription-reactivated.php
 *
 * @package Bocs/Templates/Emails
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);

// Main content
?>
<div style="padding: 0 12px; max-width: 100%;">

<?php
// Greeting
$customer_name = '';
if (isset($subscription['customer']) && isset($subscription['customer']['firstName'])) {
    $customer_name = $subscription['customer']['firstName'];
} elseif (isset($subscription['billing']) && isset($subscription['billing']['firstName'])) {
    $customer_name = $subscription['billing']['firstName'];
}
?>
<p style="margin: 0 0 16px;">Hi <?php echo esc_html($customer_name); ?>,</p>

<p style="margin: 0 0 16px;"><?php echo esc_html__('Great news! Your subscription has been successfully reactivated. You\'re back on track to receive your products on schedule.', 'bocs-wordpress'); ?></p>

<!-- Success notification box -->
<div style="background-color: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px;">
<p style="margin: 0 0 16px; color: #2e7d32; font-weight: 600;"><?php echo esc_html__('Subscription Reactivated', 'bocs-wordpress'); ?></p>
<p style="margin: 0 0 16px;"><?php echo esc_html__('Your subscription is now active again and your regular billing schedule has resumed.', 'bocs-wordpress'); ?></p>

<?php
// Add resume reason if provided
$resume_reason = '';
if (isset($subscription['metaData']) && is_array($subscription['metaData'])) {
    foreach ($subscription['metaData'] as $meta) {
        if (isset($meta['key']) && $meta['key'] === 'resume_reason' && !empty($meta['value'])) {
            $resume_reason = $meta['value'];
            break;
        }
    }
}

if (!empty($resume_reason)) {
    ?>
    <p style="margin: 0 0 16px;"><strong><?php echo esc_html__('Reason for reactivation:', 'bocs-wordpress'); ?></strong> <?php echo esc_html($resume_reason); ?></p>
    <?php
}

// Add reactivation date
if (isset($subscription['updatedAt']) || isset($subscription['updatedAtGmt'])) {
    $date_string = isset($subscription['updatedAtGmt']) ? $subscription['updatedAtGmt'] : $subscription['updatedAt'];
    $resume_date = new DateTime($date_string);
    ?>
    <p style="margin: 0 0 16px;"><strong><?php echo esc_html__('Reactivated on:', 'bocs-wordpress'); ?></strong> <?php echo esc_html($resume_date->format('F j, Y')); ?></p>
    <?php
}

// Next payment details
if (isset($subscription['nextPaymentDateGmt'])) {
    $next_date = new DateTime($subscription['nextPaymentDateGmt']);
    $total = isset($subscription['total']) ? floatval($subscription['total']) : 0;
    $currency = isset($subscription['currency']) ? $subscription['currency'] : 'USD';
    ?>
    <p style="margin: 0 0 16px;"><strong>
    <?php echo sprintf(
        esc_html__('Your next payment of %1$s is scheduled for %2$s.', 'bocs-wordpress'),
        esc_html(number_format($total, 2)) . ' ' . esc_html($currency),
        esc_html($next_date->format('F j, Y'))
    ); ?>
    </strong></p>
    <?php
}
?>
</div>

<!-- Order details heading -->
<h2 style="display: block; color: #333333; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 22px; font-weight: 500; line-height: 130%; margin: 30px 0 18px; text-align: left;">Subscription Details</h2>
</div>

<!-- Order details with Bocs teal color -->
<h2 style="color: #3C7B7C !important; display: block; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 18px; font-weight: bold; line-height: 130%; margin: 0 0 18px; text-align: left;">
<?php echo sprintf(__('[Subscription #%s]', 'bocs-wordpress'), $subscription['id'] ?? ''); ?>
<?php
if (isset($subscription['createdAt'])) {
    $created_date = new DateTime($subscription['createdAt']);
    echo ' (' . esc_html($created_date->format('F j, Y')) . ')';
}
?>
</h2>

<!-- Subscription items -->
<div style="margin-bottom: 40px; padding: 15px; background-color: #f8f8f8; border-radius: 8px;">
<h3 style="color: #3C7B7C; margin-top: 0;">Subscription Items</h3>

<?php if (isset($subscription['lineItems']) && is_array($subscription['lineItems']) && !empty($subscription['lineItems'])) : ?>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
    <tr>
    <th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Product</th>
    <th style="text-align: center; padding: 8px; border-bottom: 1px solid #ddd;">Quantity</th>
    <th style="text-align: right; padding: 8px; border-bottom: 1px solid #ddd;">Price</th>
    </tr>
    
    <?php foreach ($subscription['lineItems'] as $item) :
        $product_name = isset($item['name']) ? $item['name'] : 'Product';
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
        $price = isset($item['price']) ? floatval($item['price']) : 0;
        $total = $price * $quantity;
        $currency = isset($subscription['currency']) ? $subscription['currency'] : 'USD';
    ?>
        <tr>
        <td style="padding: 8px; border-bottom: 1px solid #ddd;"><?php echo esc_html($product_name); ?></td>
        <td style="text-align: center; padding: 8px; border-bottom: 1px solid #ddd;"><?php echo esc_html($quantity); ?></td>
        <td style="text-align: right; padding: 8px; border-bottom: 1px solid #ddd;"><?php echo esc_html(number_format($total, 2)); ?> <?php echo esc_html($currency); ?></td>
        </tr>
    <?php endforeach; ?>
    </table>
<?php else : ?>
    <p>No items found in this subscription.</p>
<?php endif; ?>

<!-- Frequency details -->
<?php if (isset($subscription['frequency'])) :
    $frequency = $subscription['frequency'];
?>
    <div style="margin-top: 20px; padding: 10px 15px; background-color: #e9f7f7; border-radius: 4px;">
    <h4 style="margin-top: 0; color: #3C7B7C;">Billing Frequency</h4>
    <p>
    <?php echo sprintf(
        esc_html__('You will be billed every %1$s %2$s', 'bocs-wordpress'),
        '<strong>' . esc_html($frequency['frequency']) . '</strong>',
        '<strong>' . esc_html($frequency['timeUnit']) . '</strong>'
    ); ?>
    
    <?php if (isset($frequency['discount']) && $frequency['discount'] > 0) : ?>
        <span style="color: #d26e4b;">(
        <?php if (isset($frequency['discountType']) && $frequency['discountType'] === 'DOLLAR') : ?>
            $<?php echo esc_html($frequency['discount']); ?> off
        <?php else : ?>
            <?php echo esc_html($frequency['discount']); ?>% off
        <?php endif; ?>
        )</span>
    <?php endif; ?>
    </p>
    </div>
<?php endif; ?>

</div>

<!-- Manage subscription section -->
<div style="margin-bottom: 40px; padding: 20px; background-color: #f0f7f7; border-radius: 6px; text-align: center;">
<h3 style="color: #3C7B7C; margin-top: 0;">Manage Your Subscription</h3>
<p style="margin-bottom: 20px;">Need to make changes? You can manage your subscription at any time by logging into your account.</p>

<?php
// My account URL
$account_url = wc_get_account_endpoint_url('bocs-subscriptions');
if ($account_url) :
?>
    <a href="<?php echo esc_url($account_url); ?>" style="display: inline-block; background-color: #3C7B7C; color: #ffffff; font-size: 16px; font-weight: bold; line-height: 100%; text-decoration: none; padding: 12px 25px; border-radius: 4px;">
    <?php echo esc_html__('Manage Subscription', 'bocs-wordpress'); ?>
    </a>
<?php endif; ?>
</div>

<!-- Footer text -->
<div style="padding: 0 12px; max-width: 100%;">

<?php if ($additional_content) : ?>
    <div style="margin-bottom: 25px; padding: 0 5px;">
    <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
    </div>
<?php endif; ?>

<p style="margin: 0 0 16px;"><?php echo esc_html__('Thank you for continuing to be our valued customer!', 'bocs-wordpress'); ?></p>

</div>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email); 