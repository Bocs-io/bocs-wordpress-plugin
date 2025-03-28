<?php
/**
 * Bocs Customer Payment Method Updated Email Template
 *
 * This template can be overridden by copying it to yourtheme/bocs-wordpress/emails/bocs-customer-payment-method-updated.php
 *
 * @package Bocs/Templates/Emails
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

// Direct content generation without relying on hooks and output buffering
$content = '';

// Email header
$header_content = $email->get_template_header($email_heading);
// Replace the WooCommerce purple background with Bocs teal directly in the inline styles
$header_content = str_replace(
    "background-color: #7f54b3", 
    "background-color: #3C7B7C", 
    $header_content
);
$header_content = str_replace(
    "bgcolor=\"#7f54b3\"", 
    "bgcolor=\"#3C7B7C\"", 
    $header_content
);
$header_content = str_replace(
    "text-shadow: 0 1px 0 #9976c2", 
    "text-shadow: none", 
    $header_content
);
$content .= $header_content;

// Main content
$content .= '<div style="padding: 0 12px; max-width: 100%;">';

// Greeting
$customer_name = '';
if (isset($user) && $user instanceof WP_User) {
    $customer_name = $user->first_name ? $user->first_name : $user->display_name;
}
$content .= '<p style="margin: 0 0 16px;">Hi ' . esc_html($customer_name) . ',</p>';

// Updated message
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('Your payment method has been successfully updated. Here are the details:', 'bocs-wordpress') . '</p>';

// Success notification box
$content .= '<div style="background-color: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px;">';
$content .= '<p style="margin: 0 0 16px; color: #4caf50; font-weight: 600;">' . esc_html__('Payment Method Updated', 'bocs-wordpress') . '</p>';
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('Your payment method information has been successfully updated in our system.', 'bocs-wordpress') . '</p>';
$content .= '</div>';

// Payment method details
if (isset($payment_method) && !empty($payment_method)) {
    $content .= '<div style="margin-bottom: 40px; padding: 15px; background-color: #f8f8f8; border-radius: 8px;">';
    $content .= '<h3 style="color: #3C7B7C; margin-top: 0;">Payment Method Details</h3>';
    
    $card_type = $payment_method['card']['brand'] ?? '';
    $last4 = $payment_method['card']['last4'] ?? '';
    $exp_month = $payment_method['card']['exp_month'] ?? '';
    $exp_year = $payment_method['card']['exp_year'] ?? '';
    
    $content .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
    
    // Card Brand/Type
    if (!empty($card_type)) {
        $content .= '<tr>';
        $content .= '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Card Type</th>';
        $content .= '<td style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html(ucfirst($card_type)) . '</td>';
        $content .= '</tr>';
    }
    
    // Last 4
    if (!empty($last4)) {
        $content .= '<tr>';
        $content .= '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Card Number</th>';
        $content .= '<td style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">**** **** **** ' . esc_html($last4) . '</td>';
        $content .= '</tr>';
    }
    
    // Expiry Date
    if (!empty($exp_month) && !empty($exp_year)) {
        $content .= '<tr>';
        $content .= '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Expiry Date</th>';
        $content .= '<td style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">' . sprintf('%02d/%d', $exp_month, $exp_year) . '</td>';
        $content .= '</tr>';
    }
    
    $content .= '</table>';
    $content .= '</div>';
}

// Security notice
$content .= '<div style="margin-bottom: 40px; padding: 15px; background-color: #fff8e1; border-radius: 8px; border: 1px dashed #ffc107;">';
$content .= '<h3 style="color: #ff6b00; margin-top: 0;">Security Notice</h3>';
$content .= '<p>' . esc_html__('If you did not make this change, please contact us immediately.', 'bocs-wordpress') . '</p>';
$content .= '</div>';

// Footer text
// View subscription button - if we have a URL
$manage_url = '';
if (function_exists('wc_get_account_endpoint_url')) {
    $manage_url = wc_get_account_endpoint_url('payment-methods');
}

if (!empty($manage_url)) {
    $content .= '<div style="margin: 40px 0; text-align: center;">';
    $content .= '<a href="' . esc_url($manage_url) . '" style="display: inline-block; background-color: #3C7B7C; color: #ffffff; font-size: 16px; font-weight: bold; line-height: 100%; text-decoration: none; padding: 12px 25px; border-radius: 4px;">';
    $content .= esc_html__('Manage Payment Methods', 'bocs-wordpress');
    $content .= '</a>';
    $content .= '</div>';
}

// Additional content from settings
if ($additional_content) {
    $content .= '<div style="margin-bottom: 25px; padding: 0 5px;">';
    $content .= wp_kses_post(wpautop(wptexturize($additional_content)));
    $content .= '</div>';
}

$content .= '</div>';

// Email footer
$content .= $email->get_template_footer();

// Print the final email content
echo $content; 