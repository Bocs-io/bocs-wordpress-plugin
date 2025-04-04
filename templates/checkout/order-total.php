<?php
/**
 * Order total
 *
 * This template overrides the default order total template to ensure proper order of elements
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$cart = WC()->cart;
$total = $cart->get_total();
$tax = $cart->get_total_tax();
$cart_contains_subscription = false;

// Check for BOCS cookies to determine if this is a subscription checkout
if (isset($_COOKIE['__bocs_id']) || isset($_COOKIE['__bocs_frequency_id'])) {
    $cart_contains_subscription = true;
}

?>
<tr class="order-total">
    <th><?php esc_html_e('Total due today', 'bocs-wordpress'); ?></th>
    <td data-title="<?php esc_attr_e('Total', 'bocs-wordpress'); ?>"><?php wc_cart_totals_order_total_html(); ?></td>
</tr>
<?php if ($tax > 0) : ?>
<tr class="tax-info">
    <th></th>
    <td data-title="<?php esc_attr_e('Tax Info', 'bocs-wordpress'); ?>">
        <span class="includes-tax"><?php echo sprintf(__('Including %s in taxes', 'bocs-wordpress'), wc_price($tax)); ?></span>
    </td>
</tr>
<?php endif; ?>

<?php 
// Only include the recurring totals template for subscription orders
if ($cart_contains_subscription) {
    // include_once BOCS_PLUGIN_DIR . 'templates/checkout/recurring-totals.php';
}
?> 