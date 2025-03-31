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

<?php if ($is_frequency_update): ?>
    <p><?php esc_html_e('Your subscription frequency has been updated successfully.', 'bocs-wordpress'); ?></p>
<?php else: ?>
    <p><?php esc_html_e('Your box contents have been updated successfully.', 'bocs-wordpress'); ?></p>
<?php endif; ?>

<?php if (!empty($subscription_data)) : ?>
    <h2><?php esc_html_e('Box Details', 'bocs-wordpress'); ?></h2>
    
    <?php if (!empty($subscription_data['bocs']['name'])) : ?>
        <p><strong><?php esc_html_e('Box Type:', 'bocs-wordpress'); ?></strong> <?php echo esc_html($subscription_data['bocs']['name']); ?></p>
    <?php endif; ?>

    <?php if (!empty($subscription_data['id'])) : ?>
        <p><strong><?php esc_html_e('Subscription ID:', 'bocs-wordpress'); ?></strong> <?php echo esc_html($subscription_data['id']); ?></p>
    <?php endif; ?>

    <?php if ($is_frequency_update && !empty($subscription_data['frequency'])) : ?>
        <h3><?php esc_html_e('Subscription Frequency', 'bocs-wordpress'); ?></h3>
        <p>
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
        <h3><?php esc_html_e('Box Contents', 'bocs-wordpress'); ?></h3>
        <table class="td" cellspacing="0" cellpadding="6" style="width: 100%; margin-bottom: 20px;">
            <thead>
                <tr>
                    <th class="td" scope="col" style="text-align: left;"><?php esc_html_e('Product', 'bocs-wordpress'); ?></th>
                    <th class="td" scope="col" style="text-align: center;"><?php esc_html_e('Quantity', 'bocs-wordpress'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subscription_data['lineItems'] as $item) : ?>
                    <?php if ($item['productId'] !== 'shipping') : ?>
                        <tr>
                            <td class="td" style="text-align: left;">
                                <?php echo esc_html($item['name']); ?>
                            </td>
                            <td class="td" style="text-align: center;">
                                <?php echo esc_html($item['quantity']); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (!empty($subscription_data['nextPaymentDateGmt'])) : ?>
        <p><strong><?php esc_html_e('Next Delivery:', 'bocs-wordpress'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($subscription_data['nextPaymentDateGmt']))); ?></p>
    <?php endif; ?>
<?php endif; ?>

<p><?php esc_html_e('Thank you for choosing Bocs!', 'bocs-wordpress'); ?></p>

<?php
/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);
?> 