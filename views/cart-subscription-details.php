<?php
/**
 * Template for displaying subscription details in the cart
 *
 * @package Bocs
 * @since 0.0.1
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if subscription info is available
if (empty($subscription_info)) {
    return;
}
?>

<div class="bocs-subscription-details">
    <h3><?php esc_html_e('Subscription Details', 'bocs-wordpress'); ?></h3>
    
    <?php if (isset($subscription_info['name'])): ?>
        <div class="bocs-subscription-name">
            <strong><?php esc_html_e('Subscription:', 'bocs-wordpress'); ?></strong>
            <?php echo esc_html($subscription_info['name']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($subscription_info['frequencies']) && !empty($subscription_info['frequencies'])): ?>
        <div class="bocs-frequency-details">
            <strong><?php esc_html_e('Delivery Frequency:', 'bocs-wordpress'); ?></strong>
            <?php
            // Get frequency details from cookie
            $frequency_id = isset($_COOKIE['__bocs_frequency_id']) ? sanitize_text_field($_COOKIE['__bocs_frequency_id']) : '';
            $frequency_details = '';
            
            if (!empty($frequency_id) && !empty($subscription_info['frequencies'])) {
                foreach ($subscription_info['frequencies'] as $frequency) {
                    if (isset($frequency['id']) && $frequency['id'] === $frequency_id) {
                        // Format the frequency display
                        $interval = isset($frequency['frequency']) ? intval($frequency['frequency']) : 0;
                        $time_unit = isset($frequency['timeUnit']) ? sanitize_text_field($frequency['timeUnit']) : '';
                        
                        if ($interval > 0 && !empty($time_unit)) {
                            // Format time unit for display (e.g., "day" to "Daily", "month" to "Monthly")
                            $display_unit = '';
                            switch (strtolower($time_unit)) {
                                case 'day':
                                    $display_unit = $interval === 1 ? esc_html__('Daily', 'bocs-wordpress') : sprintf(esc_html__('Every %d days', 'bocs-wordpress'), $interval);
                                    break;
                                case 'week':
                                    $display_unit = $interval === 1 ? esc_html__('Weekly', 'bocs-wordpress') : sprintf(esc_html__('Every %d weeks', 'bocs-wordpress'), $interval);
                                    break;
                                case 'month':
                                    $display_unit = $interval === 1 ? esc_html__('Monthly', 'bocs-wordpress') : sprintf(esc_html__('Every %d months', 'bocs-wordpress'), $interval);
                                    break;
                                case 'year':
                                    $display_unit = $interval === 1 ? esc_html__('Yearly', 'bocs-wordpress') : sprintf(esc_html__('Every %d years', 'bocs-wordpress'), $interval);
                                    break;
                                default:
                                    $display_unit = sprintf(esc_html__('Every %d %s', 'bocs-wordpress'), $interval, $time_unit);
                            }
                            
                            $frequency_details = $display_unit;
                            
                            // Add discount info if available
                            if (isset($frequency['discount']) && floatval($frequency['discount']) > 0) {
                                $discount_type = isset($frequency['discountType']) ? $frequency['discountType'] : 'fixed_cart';
                                
                                if (strpos(strtolower($discount_type), 'percent') !== false) {
                                    $frequency_details .= sprintf(esc_html__(' (%.2f%% discount)', 'bocs-wordpress'), floatval($frequency['discount']));
                                } else {
                                    $frequency_details .= sprintf(esc_html__(' ($%.2f discount)', 'bocs-wordpress'), floatval($frequency['discount']));
                                }
                            }
                        }
                        break;
                    }
                }
            }
            
            echo esc_html($frequency_details);
            ?>
        </div>
    <?php endif; ?>
    
    <?php
    // Display subtotal and total if available
    $subtotal = isset($_COOKIE['__bocs_subtotal']) ? floatval($_COOKIE['__bocs_subtotal']) : 0;
    $discount = isset($_COOKIE['__bocs_discount']) ? floatval($_COOKIE['__bocs_discount']) : 0;
    $total = isset($_COOKIE['__bocs_total']) ? floatval($_COOKIE['__bocs_total']) : 0;
    
    if ($subtotal > 0):
    ?>
        <div class="bocs-subscription-pricing">
            <div class="bocs-subtotal">
                <strong><?php esc_html_e('Subtotal:', 'bocs-wordpress'); ?></strong>
                <?php echo wc_price($subtotal); ?>
            </div>
            
            <?php if ($discount > 0): ?>
            <div class="bocs-discount">
                <strong><?php esc_html_e('Discount:', 'bocs-wordpress'); ?></strong>
                <?php echo wc_price($discount); ?>
            </div>
            <?php endif; ?>
            
            <div class="bocs-total">
                <strong><?php esc_html_e('Total:', 'bocs-wordpress'); ?></strong>
                <?php echo wc_price($total); ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="bocs-subscription-actions">
        <a href="<?php echo esc_url(wc_get_cart_url() . '?remove_bocs=1'); ?>" class="button">
            <?php esc_html_e('Remove Subscription', 'bocs-wordpress'); ?>
        </a>
    </div>
</div>
