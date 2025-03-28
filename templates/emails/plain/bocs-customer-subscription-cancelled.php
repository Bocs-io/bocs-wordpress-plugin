<?php
/**
 * Bocs Customer Subscription Cancelled Email (Plain Text)
 *
 * This template can be overridden by copying it to yourtheme/bocs-wordpress/emails/plain/bocs-customer-subscription-cancelled.php
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

// Cancellation message
echo esc_html__('Your subscription has been cancelled as requested. Below are the details of your cancelled subscription for your reference:', 'bocs-wordpress') . "\n\n";

// Cancellation notification box
echo "= " . esc_html__('SUBSCRIPTION CANCELLED', 'bocs-wordpress') . " =\n";
echo esc_html__('Your subscription has been cancelled and you will no longer be billed for this service.', 'bocs-wordpress') . "\n";

// Add cancellation reason if provided
$cancellation_reason = '';
if (isset($subscription['metaData']) && is_array($subscription['metaData'])) {
    foreach ($subscription['metaData'] as $meta) {
        if (isset($meta['key']) && $meta['key'] === 'cancellation_reason' && !empty($meta['value'])) {
            $cancellation_reason = $meta['value'];
            break;
        }
    }
}

if (!empty($cancellation_reason)) {
    echo esc_html__('Reason for cancellation:', 'bocs-wordpress') . ' ' . esc_html($cancellation_reason) . "\n";
}

// Add cancellation date
if (isset($subscription['updatedAt']) || isset($subscription['updatedAtGmt'])) {
    $date_string = isset($subscription['updatedAtGmt']) ? $subscription['updatedAtGmt'] : $subscription['updatedAt'];
    $cancel_date = new DateTime($date_string);
    echo esc_html__('Cancelled on:', 'bocs-wordpress') . ' ' . esc_html($cancel_date->format('F j, Y')) . "\n";
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
        esc_html__('You were billed every %1$s %2$s', 'bocs-wordpress'),
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

// Resubscribe section
echo "= " . esc_html__('WANT TO RESUBSCRIBE?', 'bocs-wordpress') . " =\n";
echo esc_html__('If you change your mind, you can always sign up for a new subscription from our site.', 'bocs-wordpress') . "\n";

// Shop URL - adjust as needed
$shop_url = get_permalink(wc_get_page_id('shop'));
if ($shop_url) {
    echo esc_html__('Visit our shop:', 'bocs-wordpress') . ' ' . esc_url($shop_url) . "\n\n";
}

// Additional content from settings
if ($additional_content) {
    echo "= " . esc_html__('ADDITIONAL INFORMATION', 'bocs-wordpress') . " =\n";
    echo wp_strip_all_tags(wp_kses_post(wpautop(wptexturize($additional_content))));
    echo "\n\n";
}

// Thank you message
echo esc_html__('Thank you for being our customer. We hope to see you again soon!', 'bocs-wordpress') . "\n\n";

// Footer
echo apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text', '')); 