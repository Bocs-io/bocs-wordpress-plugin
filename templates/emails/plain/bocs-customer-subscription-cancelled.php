<?php
/**
 * Bocs Customer Subscription Cancelled Email Template (Plain text)
 *
 * This template can be overridden by copying it to yourtheme/bocs-wordpress/emails/plain/bocs-customer-subscription-cancelled.php
 *
 * @package Bocs/Templates/Emails/Plain
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading)) . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

// Greeting
$customer_name = '';
// Check billing info first since that's where customer data is stored
if (isset($subscription['billing']) && isset($subscription['billing']['firstName'])) {
    $customer_name = $subscription['billing']['firstName'];
} 
// Fallback to customer data if available
elseif (isset($subscription['customer']) && isset($subscription['customer']['firstName'])) {
    $customer_name = $subscription['customer']['firstName'];
}
echo sprintf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($customer_name)) . "\n\n";

echo esc_html__('Your subscription has been cancelled as requested. Here are the details for your reference:', 'bocs-wordpress') . "\n\n";

// Cancelled notification
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__('SUBSCRIPTION CANCELLED', 'bocs-wordpress') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html__('Your subscription has been cancelled. You will no longer be charged for this subscription.', 'bocs-wordpress') . "\n\n";

// Add cancellation date
if (isset($subscription['updatedAt']) || isset($subscription['updatedAtGmt'])) {
    $date_string = isset($subscription['updatedAtGmt']) ? $subscription['updatedAtGmt'] : $subscription['updatedAt'];
    $cancel_date = new DateTime($date_string);
    echo esc_html__('Cancelled on:', 'bocs-wordpress') . ' ' . esc_html($cancel_date->format('F j, Y')) . "\n\n";
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__('SUBSCRIPTION DETAILS', 'bocs-wordpress') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

// Subscription ID and creation date
echo sprintf(__('[Subscription #%s]', 'bocs-wordpress'), $subscription['id'] ?? '') . ' ';
if (isset($subscription['createdAt'])) {
    $created_date = new DateTime($subscription['createdAt']);
    echo '(' . esc_html($created_date->format('F j, Y')) . ')';
}
echo "\n\n";

// Subscription items
echo esc_html__('SUBSCRIPTION ITEMS', 'bocs-wordpress') . "\n";
echo "----------------------------------------\n\n";

if (isset($subscription['lineItems']) && is_array($subscription['lineItems']) && !empty($subscription['lineItems'])) {
    echo esc_html__('Product', 'bocs-wordpress') . "\t\t" . esc_html__('Quantity', 'bocs-wordpress') . "\t\t" . esc_html__('Price', 'bocs-wordpress') . "\n";
    echo "----------------------------------------\n";
    
    foreach ($subscription['lineItems'] as $item) {
        $product_name = isset($item['name']) ? $item['name'] : 'Product';
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
        $price = isset($item['price']) ? floatval($item['price']) : 0;
        $total = $price * $quantity;
        $currency = isset($subscription['currency']) ? $subscription['currency'] : 'USD';
        
        echo esc_html($product_name) . "\t\t" . esc_html($quantity) . "\t\t" . esc_html(number_format($total, 2)) . ' ' . esc_html($currency) . "\n";
    }
    echo "\n";
} else {
    echo esc_html__('No items found in this subscription.', 'bocs-wordpress') . "\n\n";
}

// Frequency details
if (isset($subscription['frequency'])) {
    $frequency = $subscription['frequency'];
    echo esc_html__('BILLING FREQUENCY', 'bocs-wordpress') . "\n";
    echo "----------------------------------------\n\n";
    
    echo sprintf(
        esc_html__('You were billed every %1$s %2$s', 'bocs-wordpress'),
        esc_html($frequency['frequency']),
        esc_html($frequency['timeUnit'])
    );
    
    // Display discount if available
    if (isset($frequency['discount']) && $frequency['discount'] > 0) {
        echo ' (';
        if (isset($frequency['discountType']) && $frequency['discountType'] === 'DOLLAR') {
            echo '$' . esc_html($frequency['discount']) . ' off';
        } else {
            echo esc_html($frequency['discount']) . '% off';
        }
        echo ')';
    }
    echo "\n\n";
}

echo apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text', '')); 