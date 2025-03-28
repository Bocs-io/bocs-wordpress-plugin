<?php
/**
 * Bocs Customer Payment Method Updated Email (Plain Text)
 *
 * This template can be overridden by copying it to yourtheme/bocs-wordpress/emails/plain/bocs-customer-payment-method-updated.php
 *
 * @package Bocs/Templates/Emails/Plain
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

// Get customer name
$customer_name = '';
if (isset($user) && $user instanceof WP_User) {
    $customer_name = $user->first_name ? $user->first_name : $user->display_name;
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading)) . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

// Greeting
echo sprintf(esc_html__('Hi %s,', 'bocs-wordpress'), $customer_name) . "\n\n";

// Main content
echo esc_html__('Your payment method has been successfully updated. Here are the details:', 'bocs-wordpress') . "\n\n";

echo "--- " . esc_html__('PAYMENT METHOD UPDATED', 'bocs-wordpress') . " ---\n\n";

// Payment method details
if (isset($payment_method) && !empty($payment_method)) {
    echo esc_html__('PAYMENT METHOD DETAILS', 'bocs-wordpress') . "\n";
    echo "----------------------------------------\n";
    
    $card_type = $payment_method['card']['brand'] ?? '';
    $last4 = $payment_method['card']['last4'] ?? '';
    $exp_month = $payment_method['card']['exp_month'] ?? '';
    $exp_year = $payment_method['card']['exp_year'] ?? '';
    
    if (!empty($card_type)) {
        echo esc_html__('Card Type:', 'bocs-wordpress') . ' ' . esc_html(ucfirst($card_type)) . "\n";
    }
    
    if (!empty($last4)) {
        echo esc_html__('Card Number:', 'bocs-wordpress') . ' **** **** **** ' . esc_html($last4) . "\n";
    }
    
    if (!empty($exp_month) && !empty($exp_year)) {
        echo esc_html__('Expiry Date:', 'bocs-wordpress') . ' ' . sprintf('%02d/%d', $exp_month, $exp_year) . "\n";
    }
    
    echo "----------------------------------------\n\n";
}

// Security notice
echo "*** " . esc_html__('SECURITY NOTICE', 'bocs-wordpress') . " ***\n";
echo esc_html__('If you did not make this change, please contact us immediately.', 'bocs-wordpress') . "\n\n";

// Footer text
echo "----------------------------------------\n\n";

// Additional content
echo esc_html(wp_strip_all_tags(wptexturize($additional_content))) . "\n\n";

// Get account URL
if (function_exists('wc_get_account_endpoint_url')) {
    $account_url = wc_get_account_endpoint_url('payment-methods');
    echo esc_html__('Manage your payment methods:', 'bocs-wordpress') . ' ' . esc_url($account_url) . "\n";
} 