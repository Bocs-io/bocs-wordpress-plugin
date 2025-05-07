<?php
/**
 * Box Updated Email Template
 *
 * This template can be overridden by copying it to yourtheme/bocs/emails/bocs-subscription-switched.php.
 */

defined('ABSPATH') || exit;

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);

// Determine the type of update - box update or frequency update
$is_frequency_update = !empty($email->frequency_id);
?>

<div style="padding: 0 12px; max-width: 100%;">
    <?php if ($is_frequency_update): ?>
        <p style="margin: 0 0 16px;"><?php esc_html_e('Your subscription frequency has been updated successfully.', 'bocs-wordpress'); ?></p>
    <?php else: ?>
        <p style="margin: 0 0 16px;"><?php esc_html_e('Your box contents have been updated successfully.', 'bocs-wordpress'); ?></p>
    <?php endif; ?>

    <?php if (!empty($subscription_data)) : ?>
        <!-- Subscription box -->
        <div style="background-color: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px;">
            <p style="margin: 0 0 16px; color: #4caf50; font-weight: 600;"><?php esc_html_e('Subscription Updated', 'bocs-wordpress'); ?></p>
            <p style="margin: 0 0 16px;"><?php esc_html_e('Your subscription has been updated successfully. The changes are reflected below.', 'bocs-wordpress'); ?></p>
        </div>
        
        <h2 style="display: block; color: #333333; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 22px; font-weight: 500; line-height: 130%; margin: 30px 0 18px; text-align: left;"><?php esc_html_e('Box Details', 'bocs-wordpress'); ?></h2>
        
        <?php if (!empty($subscription_data['bocs']['name'])) : ?>
            <p style="margin: 0 0 16px;"><strong><?php esc_html_e('Box Type:', 'bocs-wordpress'); ?></strong> <?php echo esc_html($subscription_data['bocs']['name']); ?></p>
        <?php endif; ?>

        <?php if (!empty($subscription_data['id'])) : ?>
            <p style="margin: 0 0 16px;"><strong><?php esc_html_e('Subscription ID:', 'bocs-wordpress'); ?></strong> <?php echo esc_html($subscription_data['id']); ?></p>
        <?php endif; ?>

        <?php if ($is_frequency_update && !empty($subscription_data['frequency'])) : ?>
            <h3 style="display: block; color: #333333; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 500; line-height: 130%; margin: 25px 0 15px; text-align: left;"><?php esc_html_e('Subscription Frequency', 'bocs-wordpress'); ?></h3>
            <p style="margin: 0 0 16px;">
                <strong><?php esc_html_e('Frequency:', 'bocs-wordpress'); ?></strong>
                <?php 
                    // Display frequency information
                    $frequency = $subscription_data['frequency'];
                    printf(
                        esc_html__('Every %1$s %2$s', 'bocs-wordpress'),
                        '<strong>' . esc_html($frequency['frequency']) . '</strong>',
                        '<strong>' . esc_html($frequency['timeUnit']) . '</strong>'
                    );
                    
                    // Display discount if available
                    if (isset($frequency['discount']) && $frequency['discount'] > 0) {
                        echo ' <span style="color: #d26e4b;">(';
                        if (isset($frequency['discountType']) && $frequency['discountType'] === 'DOLLAR') {
                            echo '$' . esc_html($frequency['discount']) . ' off';
                        } else {
                            echo esc_html($frequency['discount']) . '% off';
                        }
                        echo ')</span>';
                    }
                ?>
            </p>
        <?php endif; ?>

        <?php if (!$is_frequency_update && !empty($subscription_data['lineItems'])) : ?>
            <h3 style="display: block; color: #333333; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 500; line-height: 130%; margin: 25px 0 15px; text-align: left;"><?php esc_html_e('Box Contents', 'bocs-wordpress'); ?></h3>
            <table cellspacing="0" cellpadding="6" style="width: 100%; margin-bottom: 20px; border: 1px solid #e5e5e5; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th scope="col" style="text-align: left; padding: 12px; border: 1px solid #e5e5e5; background-color: #f8f8f8;"><?php esc_html_e('Product', 'bocs-wordpress'); ?></th>
                        <th scope="col" style="text-align: center; padding: 12px; border: 1px solid #e5e5e5; background-color: #f8f8f8;"><?php esc_html_e('Quantity', 'bocs-wordpress'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscription_data['lineItems'] as $item) : ?>
                        <?php if ($item['productId'] !== 'shipping') : ?>
                            <tr>
                                <td style="text-align: left; padding: 12px; border: 1px solid #e5e5e5; vertical-align: middle;">
                                    <?php echo esc_html($item['name']); ?>
                                </td>
                                <td style="text-align: center; padding: 12px; border: 1px solid #e5e5e5; vertical-align: middle;">
                                    <?php echo esc_html($item['quantity']); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($subscription_data['nextPaymentDateGmt'])) : ?>
            <p style="margin: 0 0 16px;"><strong><?php esc_html_e('Next Delivery:', 'bocs-wordpress'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($subscription_data['nextPaymentDateGmt']))); ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);
?> 