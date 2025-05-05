<?php
/**
 * Bocs Customer Subscription Paused Email (Plain Text)
 *
 * This template can be overridden by copying it to yourtheme/bocs-wordpress/emails/plain/bocs-customer-subscription-paused.php
 *
 * @package Bocs/Templates/Emails/Plain
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

echo "= " . esc_html($email_heading) . " =\n\n";

// Greeting
$customer_name = '';
if (isset($subscription['customer']) && isset($subscription['customer']['firstName'])) {
    $customer_name = $subscription['customer']['firstName'];
} elseif (isset($subscription['billing']) && isset($subscription['billing']['firstName'])) {
    $customer_name = $subscription['billing']['firstName'];
}
echo "Hi " . esc_html($customer_name) . ",\n\n";

// Paused message
echo esc_html__('Your subscription has been paused as requested. Here are the details for your reference:', 'bocs-wordpress') . "\n\n";

// Paused notification box
echo "= " . esc_html__('SUBSCRIPTION PAUSED', 'bocs-wordpress') . " =\n";
echo esc_html__('Your subscription has been temporarily paused. You will not be charged until you decide to resume your subscription.', 'bocs-wordpress') . "\n";

// Add pause reason if provided
$pause_reason = '';
if (isset($subscription['metaData']) && is_array($subscription['metaData'])) {
    foreach ($subscription['metaData'] as $meta) {
        if (isset($meta['key']) && $meta['key'] === 'pause_reason' && !empty($meta['value'])) {
            $pause_reason = $meta['value'];
            break;
        }
    }
}

if (!empty($pause_reason)) {
    echo esc_html__('Reason for pausing:', 'bocs-wordpress') . ' ' . esc_html($pause_reason) . "\n";
}

// Add pause date
if (isset($subscription['updatedAt']) || isset($subscription['updatedAtGmt'])) {
    $date_string = isset($subscription['updatedAtGmt']) ? $subscription['updatedAtGmt'] : $subscription['updatedAt'];
    $pause_date = new DateTime($date_string);
    echo esc_html__('Paused on:', 'bocs-wordpress') . ' ' . esc_html($pause_date->format('F j, Y')) . "\n";
}
echo "\n";

// Subscription details
echo "= " . esc_html__('SUBSCRIPTION DETAILS', 'bocs-wordpress') . " =\n";
echo sprintf(esc_html__('Subscription #%s', 'bocs-wordpress'), $subscription['id'] ?? '') . "\n";
if (isset($subscription['createdAt'])) {
    $created_date = new DateTime($subscription['createdAt']);
    echo esc_html__('Created on:', 'bocs-wordpress') . ' ' . esc_html($created_date->format('F j, Y')) . "\n";
}
echo "\n";

// Subscription items
echo "= " . esc_html__('SUBSCRIPTION ITEMS', 'bocs-wordpress') . " =\n";

if (isset($subscription['lineItems']) && is_array($subscription['lineItems']) && !empty($subscription['lineItems'])) {
    echo esc_html__('Product', 'bocs-wordpress') . ' | ' . esc_html__('Quantity', 'bocs-wordpress') . ' | ' . esc_html__('Price', 'bocs-wordpress') . "\n";
    echo "---------------------------------------\n";
    
    foreach ($subscription['lineItems'] as $item) {
        $product_name = isset($item['name']) ? $item['name'] : 'Product';
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
        $price = isset($item['price']) ? floatval($item['price']) : 0;
        $total = $price * $quantity;
        $currency = isset($subscription['currency']) ? $subscription['currency'] : 'USD';
        
        echo esc_html($product_name) . ' | ' . esc_html($quantity) . ' | ' . esc_html(number_format($total, 2)) . ' ' . esc_html($currency) . "\n";
    }
} else {
    echo esc_html__('No items found in this subscription.', 'bocs-wordpress') . "\n";
}
echo "\n";

// Frequency details
if (isset($subscription['frequency'])) {
    $frequency = $subscription['frequency'];
    echo "= " . esc_html__('BILLING FREQUENCY', 'bocs-wordpress') . " =\n";
    
    echo sprintf(
        esc_html__('You are normally billed every %1$s %2$s', 'bocs-wordpress'),
        esc_html($frequency['frequency']),
        esc_html($frequency['timeUnit'])
    );
    
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

// Resume subscription section
echo "= " . esc_html__('READY TO RESUME?', 'bocs-wordpress') . " =\n";
echo esc_html__('You can resume your subscription at any time by logging into your account.', 'bocs-wordpress') . "\n";

// My account URL
$account_url = wc_get_account_endpoint_url('my-subscriptions');
if ($account_url) {
    echo esc_html__('Manage your subscription:', 'bocs-wordpress') . ' ' . esc_url($account_url) . "\n\n";
}

// Additional content from settings
if ($additional_content) {
    echo "= " . esc_html__('ADDITIONAL INFORMATION', 'bocs-wordpress') . " =\n";
    echo wp_strip_all_tags(wp_kses_post(wpautop(wptexturize($additional_content))));
    echo "\n\n";
}

// Thank you message
echo esc_html__('Thank you for being our customer.', 'bocs-wordpress') . "\n\n";

// Footer
echo apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text', '')); 