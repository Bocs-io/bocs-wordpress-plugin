<?php
/**
 * BOCS Order Line Items Component
 *
 * Reusable component for displaying order line items in subscription pages.
 *
 * @package BOCS
 * @subpackage Templates/Components
 * @version 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Ensure we have items to display
if (empty($items) || !is_array($items)) {
    $items = [];
}

// Set default values for optional parameters
$subtotal = isset($subtotal) ? $subtotal : 0;
$discount = isset($discount) ? $discount : 0;
$shipping = isset($shipping) ? $shipping : 0;
$tax = isset($tax) ? $tax : 0;
$total = isset($total) ? $total : 0;
$coupon_lines = isset($coupon_lines) ? $coupon_lines : [];
$tax_display = isset($tax_display) ? $tax_display : true;
$is_editable = isset($is_editable) ? $is_editable : false;
$component_id = isset($component_id) ? $component_id : 'bocs-order-items-' . uniqid();
$frequency = isset($frequency) ? $frequency : [];
$discount_type = isset($discount_type) ? $discount_type : '';
$discount_percent = isset($discount_percent) ? $discount_percent : '';
$has_empty_products = isset($has_empty_products) ? $has_empty_products : false;

// Get WooCommerce tax settings
$prices_include_tax = get_option('woocommerce_prices_include_tax', 'no') === 'yes';
$tax_display_shop = get_option('woocommerce_tax_display_shop', 'excl');
$tax_display_cart = get_option('woocommerce_tax_display_cart', 'excl');
$tax_rate = 0.1; // 10% GST for Australia

// Note: CSS and JS should be enqueued by the parent template, not here
// to avoid duplicate enqueues
?>

<div class="bocs-order-details" id="<?php echo esc_attr($component_id); ?>">
    <div class="bocs-order-header">
        <h4><?php esc_html_e('Order details', 'bocs-wordpress'); ?></h4>
        <?php if (isset($payment_url) && !empty($payment_url)) : ?>
            <a href="<?php echo esc_url($payment_url); ?>" class="bocs-button pay-now"><?php esc_html_e('Pay Now', 'bocs-wordpress'); ?></a>
        <?php endif; ?>
    </div>
    <table class="bocs-order-table"<?php echo $has_empty_products ? ' style="display:none;"' : ''; ?>>
        <thead>
            <tr>
                <th><?php esc_html_e('Product', 'bocs-wordpress'); ?></th>
                <th><?php esc_html_e('Quantity', 'bocs-wordpress'); ?></th>
                <th><?php esc_html_e('Price', 'bocs-wordpress'); ?></th>
                <th><?php esc_html_e('Total', 'bocs-wordpress'); ?></th>
                <th><?php esc_html_e('GST', 'bocs-wordpress'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $calculated_subtotal = 0;
            $calculated_tax = 0;
            
            if (!empty($items)) {
                foreach ($items as $item) : 
                    $product_name = isset($item['name']) ? $item['name'] : '';
                    $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
                    $unit_price = isset($item['price']) ? (float)$item['price'] : 0;
                    
                    // Calculate price based on tax settings
                    if ($prices_include_tax) {
                        // Prices already include tax
                        $unit_price_excl_tax = $unit_price / (1 + $tax_rate);
                        $unit_price_incl_tax = $unit_price;
                        $unit_tax = $unit_price - $unit_price_excl_tax;
                    } else {
                        // Prices exclude tax
                        $unit_price_excl_tax = $unit_price;
                        $unit_price_incl_tax = $unit_price * (1 + $tax_rate);
                        $unit_tax = $unit_price * $tax_rate;
                    }
                    
                    // Always display price excluding tax since we have a GST column
                    $display_unit_price = $unit_price_excl_tax;
                    
                    // Calculate line totals
                    $line_total_excl_tax = $unit_price_excl_tax * $quantity;
                    $line_total_incl_tax = $unit_price_incl_tax * $quantity;
                    $line_tax = $unit_tax * $quantity;
                    
                    // Always display line total excluding tax since we have a GST column
                    $display_line_total = $line_total_excl_tax;
                    
                    // Add to running totals
                    $calculated_subtotal += $line_total_excl_tax;
                    $calculated_tax += $line_tax;
            ?>
            <tr>
                <td class="product-name" data-title="<?php esc_attr_e('Product', 'bocs-wordpress'); ?>">
                    <?php echo esc_html($product_name); ?>
                    <?php if (!empty($item['metadata']) && is_array($item['metadata'])): ?>
                        <div class="product-meta">
                            <?php foreach ($item['metadata'] as $meta): ?>
                                <div class="meta-item">
                                    <span class="meta-key"><?php echo esc_html($meta['key'] ?? ''); ?>:</span>
                                    <span class="meta-value"><?php echo esc_html($meta['value'] ?? ''); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td data-title="<?php esc_attr_e('Quantity', 'bocs-wordpress'); ?>" class="text-center quantity-value"><?php echo esc_html($quantity); ?></td>
                <td data-title="<?php esc_attr_e('Price', 'bocs-wordpress'); ?>" class="text-right">
                    <?php 
                    if (function_exists('wc_price')) {
                        echo wp_kses_post(wc_price($display_unit_price));
                    } else {
                        echo esc_html('$' . number_format($display_unit_price, 2));
                    }
                    ?>
                </td>
                <td data-title="<?php esc_attr_e('Total', 'bocs-wordpress'); ?>" class="text-right">
                    <?php 
                    if (function_exists('wc_price')) {
                        echo wp_kses_post(wc_price($display_line_total));
                    } else {
                        echo esc_html('$' . number_format($display_line_total, 2));
                    }
                    ?>
                </td>
                <td data-title="<?php esc_attr_e('GST', 'bocs-wordpress'); ?>" class="text-right">
                    <?php 
                    if (function_exists('wc_price')) {
                        echo wp_kses_post(wc_price($line_tax));
                    } else {
                        echo esc_html('$' . number_format($line_tax, 2));
                    }
                    ?>
                </td>
            </tr>
            <?php endforeach; 
            } else {
                echo '<tr><td colspan="5">' . esc_html__('No items found', 'bocs-wordpress') . '</td></tr>';
            }
            ?>
        </tbody>
        <tfoot>
            <tr class="bocs-subtotal-row">
                <th colspan="3"><?php esc_html_e('Subtotal', 'bocs-wordpress'); ?> <small><?php esc_html_e('(excl. GST)', 'bocs-wordpress'); ?></small></th>
                <td class="text-right" data-title="<?php esc_attr_e('Subtotal', 'bocs-wordpress'); ?>">
                    <?php 
                    // Use calculated subtotal or provided subtotal
                    $display_subtotal = ($calculated_subtotal > 0) ? $calculated_subtotal : $subtotal;
                    
                    if (function_exists('wc_price')) {
                        echo wp_kses_post(wc_price($display_subtotal));
                    } else {
                        echo esc_html('$' . number_format($display_subtotal, 2));
                    }
                    ?>
                </td>
                <td></td>
            </tr>
            
            <tr class="bocs-discount-row">
                <th colspan="3">
                    <?php
                    // Show subscription discount with percentage when available
                    $discount_label = esc_html__('Subscription Discount', 'bocs-wordpress');
                    
                    // Check if we have the discount percent passed directly
                    if (!empty($discount_percent) && ($discount_type === 'percent' || $discount_type === 'percentage')) {
                        $discount_label = sprintf(
                            esc_html__('Subscription Discount (%s%% discount)', 'bocs-wordpress'),
                            $discount_percent
                        );
                    }
                    
                    echo esc_html($discount_label);
                    ?>
                </th>
                <td class="text-right discount" data-title="<?php esc_attr_e('Discount', 'bocs-wordpress'); ?>">
                    <?php 
                    // Ensure discount is a numeric value
                    $discount_amount = $discount_percent;
                    if( $discount_type === 'percent' || $discount_type === 'percentage' ){
                        $discount_amount = floatval($discount_percent) / 100 * ($calculated_subtotal + $calculated_tax);
                    }
                    
                    if (function_exists('wc_price')) {
                        echo wp_kses_post(wc_price(floatval($discount_amount) * -1));
                    } else {
                        echo esc_html('-$' . number_format($discount_amount, 2));
                    }
                    ?>
                </td>
                <td></td>
            </tr>
            
            <?php 
            // Calculate shipping tax
            $shipping_amount = is_array($shipping) ? 0 : (float)$shipping;
            $shipping_tax = round($shipping_amount * $tax_rate, 2);
            
            // Check if shipping is set
            if (!empty($shipping)): 
            ?>
            <tr class="bocs-shipping-row">
                <th colspan="3"><?php esc_html_e('Shipping', 'bocs-wordpress'); ?></th>
                <td class="text-right" data-title="<?php esc_attr_e('Shipping', 'bocs-wordpress'); ?>">
                    <?php 
                    if (function_exists('wc_price')) {
                        echo wp_kses_post(wc_price($shipping_amount));
                    } else {
                        echo esc_html('$' . number_format($shipping_amount, 2));
                    }
                    ?>
                </td>
                <td class="text-right">
                    <?php 
                    if (function_exists('wc_price')) {
                        echo wp_kses_post(wc_price($shipping_tax));
                    } else {
                        echo esc_html('$' . number_format($shipping_tax, 2));
                    }
                    ?>
                </td>
            </tr>
            <?php endif; ?>
            
            <?php 
            // Calculate the total GST for display
            $total_tax = $calculated_tax + $shipping_tax;
            
            // Define display_tax regardless of whether we show the tax row
            $display_tax = ($total_tax > 0) ? $total_tax : (is_array($tax) ? 0 : (float)$tax);
            ?>
            
            <?php
            // Show tax row if tax display is enabled and either tax is not empty or we have calculated tax
            if ($tax_display && (!empty($tax) || $total_tax > 0)): 
            ?>
            <tr class="bocs-tax-row">
                <th colspan="3"><?php esc_html_e('GST', 'bocs-wordpress'); ?></th>
                <td></td>
                <td class="text-right" data-title="<?php esc_attr_e('GST', 'bocs-wordpress'); ?>">
                    <?php 
                    if (function_exists('wc_price')) {
                        echo wp_kses_post(wc_price($display_tax));
                    } else {
                        echo esc_html('$' . number_format($display_tax, 2));
                    }
                    
                    // If prices include tax, add a note
                    if ($prices_include_tax) {
                        echo '<br><small>' . esc_html__('(included in prices)', 'bocs-wordpress') . '</small>';
                    }
                    ?>
                </td>
            </tr>
            <?php endif; ?>
            
            <tr class="bocs-total-row">
                <th colspan="3"><?php esc_html_e('Subscription Total', 'bocs-wordpress'); ?> <small><?php esc_html_e('(excl. GST)', 'bocs-wordpress'); ?></small></th>
                <td class="text-right order-total" data-title="<?php esc_attr_e('Total', 'bocs-wordpress'); ?>">
                    <?php 
                    // Calculate total based on WooCommerce tax display settings
                    $discount_amount = is_array($discount) ? 0 : (float)$discount;
                    
                    if (!empty($coupon_lines) && is_array($coupon_lines)) {
                        $discount_amount = 0;
                        foreach ($coupon_lines as $coupon) {
                            if (!empty($coupon['discount'])) {
                                $coupon_discount = is_array($coupon['discount']) ? 0 : (float)$coupon['discount'];
                                $discount_amount += $coupon_discount;
                            }
                        }
                    }
                    
                    // Always calculate total excluding tax since we have a GST column
                    $calculated_total = $calculated_subtotal + $shipping_amount - $discount_amount;
                    
                    // Use the calculated total or fall back to the provided total
                    $display_total = ($calculated_total > 0) ? $calculated_total : $total;
                    
                    if (function_exists('wc_price')) {
                        echo wp_kses_post(wc_price($display_total));
                    } else {
                        echo esc_html('$' . number_format($display_total, 2));
                    }

                    // Make sure we have a valid display_tax value
                    if (!isset($display_tax)) {
                        $display_tax = ($total_tax > 0) ? $total_tax : (is_array($tax) ? 0 : (float)$tax);
                    }
                    
                    // Calculate the total with GST
                    $total_with_tax = $display_total + $display_tax;
                    ?>
                </td>
                <td></td>
            </tr>
            
            <tr class="bocs-final-total-row">
                <th colspan="3"><?php esc_html_e('Total with GST', 'bocs-wordpress'); ?></th>
                <td class="text-right" data-title="<?php esc_attr_e('Total with GST', 'bocs-wordpress'); ?>">
                    <?php
                    if (function_exists('wc_price')) {
                        echo wp_kses_post(wc_price($total_with_tax));
                    } else {
                        echo esc_html('$' . number_format($total_with_tax, 2));
                    }
                    ?>
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div> 