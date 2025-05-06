<?php
/**
 * Upcoming renewal reminder email (Plain text - Bocs specific variant)
 *
 * @package Bocs/Templates/Emails/Plain
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

// Ensure we have the required variables
if (!isset($email_heading)) {
    $email_heading = '';
}

if (!isset($email)) {
    $email = null;
}

// Ensure order object exists
if (!isset($order) || !is_object($order)) {
    $order = null;
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading)) . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/* translators: %s: Customer first name */
echo sprintf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html(($order && is_callable(array($order, 'get_billing_first_name')) && $order->get_billing_first_name()) ? $order->get_billing_first_name() : __('there', 'bocs-wordpress'))) . "\n\n";

echo esc_html__('This is a reminder that your subscription renewal payment will be automatically processed soon.', 'bocs-wordpress') . "\n\n";

// Reminder notification
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__('UPCOMING RENEWAL REMINDER', 'bocs-wordpress') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

if (isset($renewal_date) && !empty($renewal_date)) {
    printf(
        esc_html__('Your subscription renewal will be automatically processed on %s.', 'bocs-wordpress'),
        esc_html($renewal_date)
    ) . "\n\n";
} else {
    echo esc_html__('Your subscription renewal will be automatically processed soon.', 'bocs-wordpress') . "\n\n";
}

// Payment method info, if applicable
$payment_method_title = ($order && is_callable(array($order, 'get_payment_method_title'))) ? $order->get_payment_method_title() : '';
if (!empty($payment_method_title)) {
    echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
    echo esc_html__('PAYMENT METHOD', 'bocs-wordpress') . "\n";
    echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
    
    echo esc_html($payment_method_title) . "\n\n";
    
    if (strpos(strtolower($payment_method_title), 'stripe') !== false) {
        echo esc_html__('Your card will be automatically charged. No action is needed from you.', 'bocs-wordpress') . "\n\n";
    }
}

if ($order) {
    // Order details header
    $order_date = is_callable(array($order, 'get_date_created')) ? $order->get_date_created() : null;
    $formatted_date = $order_date ? $order_date->format(wc_date_format()) : date_i18n(wc_date_format());
    $order_number = is_callable(array($order, 'get_order_number')) ? $order->get_order_number() : 'N/A';
    
    echo sprintf(esc_html__('[Order #%s] (%s)', 'bocs-wordpress'), $order_number, $formatted_date) . "\n\n";
    
    // Check if this is a "placeholder" order for upcoming renewals
    $items = $order && is_callable(array($order, 'get_items')) ? $order->get_items() : array();
    $order_for_display = $order;

    // If this is a placeholder order with no items, find the original order
    if ($order && empty($items) && is_callable(array($order, 'get_meta'))) {
        $bocs_subscription_id = $order->get_meta('__bocs_subscription_id');
        if (!empty($bocs_subscription_id)) {
            // Query for the most recent completed order with this subscription ID
            $args = array(
                'status' => array('completed', 'processing'),
                'limit' => 1,
                'meta_key' => '__bocs_subscription_id',
                'meta_value' => $bocs_subscription_id,
                'return' => 'ids',
            );
            
            $original_orders = wc_get_orders($args);
            
            if (!empty($original_orders)) {
                $original_order_id = $original_orders[0];
                $original_order = wc_get_order($original_order_id);
                
                if ($original_order && is_callable(array($original_order, 'get_items')) && $original_order->get_items()) {
                    $order_for_display = $original_order;
                    $items = $original_order->get_items();
                }
            }
        }
    }

    echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
    echo esc_html__('ORDER DETAILS', 'bocs-wordpress') . "\n";
    echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

    // Order items
    if (!empty($items) && is_array($items)) {
        echo esc_html__('Product', 'woocommerce') . "\t" . esc_html__('Quantity', 'woocommerce') . "\t" . esc_html__('Price', 'woocommerce') . "\n";
        echo "----------------------------------------\n";
        
        foreach ($items as $item_id => $item) {
            $product = is_callable(array($item, 'get_product')) ? $item->get_product() : null;
            $sku = $product && is_callable(array($product, 'get_sku')) ? $product->get_sku() : '';
            $name = is_callable(array($item, 'get_name')) ? $item->get_name() : __('Product', 'bocs-wordpress');
            $qty = is_callable(array($item, 'get_quantity')) ? $item->get_quantity() : 1;
            
            $price_str = '';
            if ($order_for_display && is_callable(array($order_for_display, 'get_formatted_line_subtotal'))) {
                $price_str = strip_tags($order_for_display->get_formatted_line_subtotal($item));
            } elseif (is_callable(array($item, 'get_total'))) {
                $price_str = strip_tags(wc_price($item->get_total()));
            } else {
                $price_str = strip_tags(wc_price(0));
            }
            
            echo $name . ($sku ? ' (SKU: ' . $sku . ')' : '') . "\t" . $qty . "\t" . $price_str . "\n";
        }
        
        // Order totals
        $totals = $order_for_display && is_callable(array($order_for_display, 'get_order_item_totals')) ? $order_for_display->get_order_item_totals() : array();
        if ($totals) {
            echo "\n";
            foreach ($totals as $total) {
                echo strip_tags($total['label']) . "\t" . strip_tags($total['value']) . "\n";
            }
        }
    } else {
        echo esc_html__('No items found in this order.', 'woocommerce') . "\n\n";
    }
    
    // Skip customer details since we're handling order details manually
    if ($order) {
        do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);
    }
}

// Additional content
if ($additional_content) {
    echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
    echo esc_html(wp_strip_all_tags(wptexturize($additional_content)));
    echo "\n\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
}

echo apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text', '')); 