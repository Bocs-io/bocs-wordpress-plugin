<?php
/**
 * Bocs Customer Payment Method Updated Email Template (Plain Text)
 *
 * This template can be overridden by copying it to yourtheme/bocs-wordpress/emails/plain/bocs-customer-payment-method-updated.php
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
if (isset($user) && $user instanceof WP_User) {
    $customer_name = $user->first_name ? $user->first_name : $user->display_name;
}
echo sprintf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($customer_name)) . "\n\n";

// Updated message
echo esc_html__('Your payment method has been successfully updated. Here are the details:', 'bocs-wordpress') . "\n\n";

// Success notification
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__('PAYMENT METHOD UPDATED', 'bocs-wordpress') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html__('Your payment method information has been successfully updated in our system.', 'bocs-wordpress') . "\n\n";

// Payment method details
if (isset($payment_method) && !empty($payment_method)) {
    echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
    echo esc_html__('PAYMENT METHOD DETAILS', 'bocs-wordpress') . "\n";
    echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
    
    $card_type = $payment_method['card']['brand'] ?? '';
    $last4 = $payment_method['card']['last4'] ?? '';
    $exp_month = $payment_method['card']['exp_month'] ?? '';
    $exp_year = $payment_method['card']['exp_year'] ?? '';
    
    // Card Brand/Type
    if (!empty($card_type)) {
        echo esc_html__('Card Type:', 'bocs-wordpress') . ' ' . esc_html(ucfirst($card_type)) . "\n";
    }
    
    // Last 4
    if (!empty($last4)) {
        echo esc_html__('Card Number:', 'bocs-wordpress') . ' **** **** **** ' . esc_html($last4) . "\n";
    }
    
    // Expiry Date
    if (!empty($exp_month) && !empty($exp_year)) {
        echo esc_html__('Expiry Date:', 'bocs-wordpress') . ' ' . sprintf('%02d/%d', $exp_month, $exp_year) . "\n\n";
    }
}

echo apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text', '')); 