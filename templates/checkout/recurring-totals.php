<?php
/**
 * Recurring totals for checkout
 *
 * This template overrides the WooCommerce Subscriptions template to display recurring totals correctly
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Get cart total amount (what will be charged)
$cart_total = WC()->cart->get_total();

// Get subscription details from cookies
$frequency_interval = isset($_COOKIE['__bocs_frequency_interval']) ? sanitize_text_field($_COOKIE['__bocs_frequency_interval']) : '';
$frequency_unit = isset($_COOKIE['__bocs_frequency_time_unit']) ? sanitize_text_field($_COOKIE['__bocs_frequency_time_unit']) : '';

// Format the recurring text
$recurring_text = __('Future Recurring Total', 'bocs-wordpress');

if (!empty($frequency_interval) && !empty($frequency_unit)) {
    // Format display text based on frequency unit
    if ($frequency_unit === 'day') {
        $unit_text = $frequency_interval == 1 ? __('day', 'bocs-wordpress') : __('days', 'bocs-wordpress');
    } elseif ($frequency_unit === 'week') {
        $unit_text = $frequency_interval == 1 ? __('week', 'bocs-wordpress') : __('weeks', 'bocs-wordpress');
    } elseif ($frequency_unit === 'month') {
        $unit_text = $frequency_interval == 1 ? __('month', 'bocs-wordpress') : __('months', 'bocs-wordpress');
    } else {
        $unit_text = $frequency_unit;
    }
    
    $recurring_text = sprintf(
        __('Recurring total every %1$s %2$s', 'bocs-wordpress'),
        $frequency_interval,
        $unit_text
    );
}

// Get starting date - use 1 month from now
$recurring_start_date = date_i18n(get_option('date_format'), strtotime('+1 month'));

// Only display the table if we have subscription items
?>
<tr class="bocs-recurring-totals-header">
    <th colspan="2"><?php echo esc_html($recurring_text); ?></th>
</tr>
<tr class="bocs-recurring-total">
    <th><?php esc_html_e('Starting', 'bocs-wordpress'); ?>: <?php echo esc_html($recurring_start_date); ?></th>
    <td data-title="<?php esc_attr_e('Recurring Total', 'bocs-wordpress'); ?>"><?php echo wc_price($cart_total); ?></td>
</tr> 