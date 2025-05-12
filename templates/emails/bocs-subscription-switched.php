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
        
        <h2 style="display: block; color: #333333; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 22px; font-weight: 500; line-height: 130%; margin: 30px 0 18px; text-align: left;"><?php esc_html_e('Subscription Details', 'bocs-wordpress'); ?></h2>
        
        <!-- Subscription Information -->
        <table cellspacing="0" cellpadding="6" style="width: 100%; margin-bottom: 20px; border: 1px solid #e5e5e5; border-collapse: collapse;">
            <tr>
                <th scope="row" style="text-align: left; padding: 12px; border: 1px solid #e5e5e5; background-color: #f8f8f8;"><?php esc_html_e('Subscription ID', 'bocs-wordpress'); ?></th>
                <td style="text-align: left; padding: 12px; border: 1px solid #e5e5e5;">
                    <?php echo !empty($subscription_data['id']) ? esc_html($subscription_data['id']) : '—'; ?>
                </td>
            </tr>
            <tr>
                <th scope="row" style="text-align: left; padding: 12px; border: 1px solid #e5e5e5; background-color: #f8f8f8;"><?php esc_html_e('Status', 'bocs-wordpress'); ?></th>
                <td style="text-align: left; padding: 12px; border: 1px solid #e5e5e5;">
                    <?php echo !empty($subscription_data['status']) ? esc_html(ucfirst(strtolower($subscription_data['status']))) : '—'; ?>
                </td>
            </tr>
            <tr>
                <th scope="row" style="text-align: left; padding: 12px; border: 1px solid #e5e5e5; background-color: #f8f8f8;"><?php esc_html_e('Next Delivery', 'bocs-wordpress'); ?></th>
                <td style="text-align: left; padding: 12px; border: 1px solid #e5e5e5;">
                    <?php echo !empty($subscription_data['nextPaymentDateGmt']) ? esc_html(date_i18n(get_option('date_format'), strtotime($subscription_data['nextPaymentDateGmt']))) : '—'; ?>
                </td>
            </tr>
            <?php if (!empty($subscription_data['frequency'])) : ?>
            <tr>
                <th scope="row" style="text-align: left; padding: 12px; border: 1px solid #e5e5e5; background-color: #f8f8f8;"><?php esc_html_e('Frequency', 'bocs-wordpress'); ?></th>
                <td style="text-align: left; padding: 12px; border: 1px solid #e5e5e5;">
                    <?php 
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
                </td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($subscription_data['subtotal']) || !empty($subscription_data['total'])) : ?>
            <tr>
                <th scope="row" style="text-align: left; padding: 12px; border: 1px solid #e5e5e5; background-color: #f8f8f8;"><?php esc_html_e('Price', 'bocs-wordpress'); ?></th>
                <td style="text-align: left; padding: 12px; border: 1px solid #e5e5e5;">
                    <?php 
                        if (!empty($subscription_data['subtotal']) && !empty($subscription_data['total']) && $subscription_data['subtotal'] !== $subscription_data['total']) {
                            echo '<del>' . wc_price($subscription_data['subtotal']) . '</del> ';
                            echo wc_price($subscription_data['total']);
                        } elseif (!empty($subscription_data['total'])) {
                            echo wc_price($subscription_data['total']);
                        } elseif (!empty($subscription_data['subtotal'])) {
                            echo wc_price($subscription_data['subtotal']);
                        } else {
                            echo '—';
                        }
                    ?>
                </td>
            </tr>
            <?php endif; ?>
        </table>

        <!-- Box Contents Section -->
        <?php if (!empty($subscription_data['lineItems'])) : ?>
            <h3 style="display: block; color: #333333; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 500; line-height: 130%; margin: 25px 0 15px; text-align: left;"><?php esc_html_e('Box Contents', 'bocs-wordpress'); ?></h3>
            <table cellspacing="0" cellpadding="6" style="width: 100%; margin-bottom: 20px; border: 1px solid #e5e5e5; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th scope="col" style="text-align: left; padding: 12px; border: 1px solid #e5e5e5; background-color: #f8f8f8;"><?php esc_html_e('Product', 'bocs-wordpress'); ?></th>
                        <th scope="col" style="text-align: center; padding: 12px; border: 1px solid #e5e5e5; background-color: #f8f8f8;"><?php esc_html_e('Quantity', 'bocs-wordpress'); ?></th>
                        <?php if (array_column($subscription_data['lineItems'], 'price')) : ?>
                        <th scope="col" style="text-align: right; padding: 12px; border: 1px solid #e5e5e5; background-color: #f8f8f8;"><?php esc_html_e('Price', 'bocs-wordpress'); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $has_shipping = false;
                    $shipping_item = null;
                    
                    foreach ($subscription_data['lineItems'] as $item) : 
                        if ($item['productId'] === 'shipping') {
                            $has_shipping = true;
                            $shipping_item = $item;
                            continue;
                        }
                    ?>
                        <tr>
                            <td style="text-align: left; padding: 12px; border: 1px solid #e5e5e5; vertical-align: middle;">
                                <?php echo esc_html($item['name']); ?>
                                <?php if (!empty($item['variant'])): ?>
                                    <small><?php echo esc_html($item['variant']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; padding: 12px; border: 1px solid #e5e5e5; vertical-align: middle;">
                                <?php echo esc_html($item['quantity']); ?>
                            </td>
                            <?php if (array_column($subscription_data['lineItems'], 'price')) : ?>
                            <td style="text-align: right; padding: 12px; border: 1px solid #e5e5e5; vertical-align: middle;">
                                <?php 
                                    if (!empty($item['price'])) {
                                        echo wc_price($item['price']);
                                        if (!empty($item['quantity']) && $item['quantity'] > 1) {
                                            echo ' <small>(' . wc_price($item['price'] / $item['quantity']) . ' each)</small>';
                                        }
                                    } else {
                                        echo '—';
                                    }
                                ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if ($has_shipping && $shipping_item) : ?>
                    <tr>
                        <td colspan="<?php echo array_column($subscription_data['lineItems'], 'price') ? '2' : '1'; ?>" style="text-align: right; padding: 12px; border: 1px solid #e5e5e5; vertical-align: middle; border-right: none;">
                            <?php esc_html_e('Shipping', 'bocs-wordpress'); ?>:
                        </td>
                        <td style="text-align: right; padding: 12px; border: 1px solid #e5e5e5; vertical-align: middle; border-left: none;">
                            <?php 
                                if (!empty($shipping_item['price'])) {
                                    echo wc_price($shipping_item['price']);
                                } else {
                                    echo esc_html__('Free', 'bocs-wordpress');
                                }
                            ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($subscription_data['total'])) : ?>
                    <tr>
                        <td colspan="<?php echo array_column($subscription_data['lineItems'], 'price') ? '2' : '1'; ?>" style="text-align: right; padding: 12px; border: 1px solid #e5e5e5; vertical-align: middle; border-right: none;">
                            <strong><?php esc_html_e('Total', 'bocs-wordpress'); ?>:</strong>
                        </td>
                        <td style="text-align: right; padding: a2px; border: 1px solid #e5e5e5; vertical-align: middle; border-left: none;">
                            <strong><?php echo wc_price($subscription_data['total']); ?></strong>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
    
    <p style="margin: 20px 0 16px; text-align: center;"><?php echo sprintf(esc_html__('Thank you for shopping with %s', 'bocs-wordpress'), get_option('blogname')); ?></p>
</div>

<?php
/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);
?> 