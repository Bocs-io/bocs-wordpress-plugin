<?php
/**
 * Bocs Customer Subscription Reactivated Email Template
 *
 * This template can be overridden by copying it to yourtheme/bocs-wordpress/emails/bocs-customer-subscription-reactivated.php
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
if (isset($subscription['customer']) && isset($subscription['customer']['firstName'])) {
    $customer_name = $subscription['customer']['firstName'];
} elseif (isset($subscription['billing']) && isset($subscription['billing']['firstName'])) {
    $customer_name = $subscription['billing']['firstName'];
}
$content .= '<p style="margin: 0 0 16px;">Hi ' . esc_html($customer_name) . ',</p>';

// Reactivation message
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('Great news! Your subscription has been successfully reactivated. You\'re back on track to receive your products on schedule.', 'bocs-wordpress') . '</p>';

// Success notification box
$content .= '<div style="background-color: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px;">';
$content .= '<p style="margin: 0 0 16px; color: #2e7d32; font-weight: 600;">' . esc_html__('Subscription Reactivated', 'bocs-wordpress') . '</p>';
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('Your subscription is now active again and your regular billing schedule has resumed.', 'bocs-wordpress') . '</p>';

// Add resume reason if provided
$resume_reason = '';
if (isset($subscription['metaData']) && is_array($subscription['metaData'])) {
    foreach ($subscription['metaData'] as $meta) {
        if (isset($meta['key']) && $meta['key'] === 'resume_reason' && !empty($meta['value'])) {
            $resume_reason = $meta['value'];
            break;
        }
    }
}

if (!empty($resume_reason)) {
    $content .= '<p style="margin: 0 0 16px;"><strong>' . esc_html__('Reason for reactivation:', 'bocs-wordpress') . '</strong> ' . esc_html($resume_reason) . '</p>';
}

// Add reactivation date
if (isset($subscription['updatedAt']) || isset($subscription['updatedAtGmt'])) {
    $date_string = isset($subscription['updatedAtGmt']) ? $subscription['updatedAtGmt'] : $subscription['updatedAt'];
    $resume_date = new DateTime($date_string);
    $content .= '<p style="margin: 0 0 16px;"><strong>' . esc_html__('Reactivated on:', 'bocs-wordpress') . '</strong> ' . esc_html($resume_date->format('F j, Y')) . '</p>';
}

// Next payment details
if (isset($subscription['nextPaymentDateGmt'])) {
    $next_date = new DateTime($subscription['nextPaymentDateGmt']);
    $total = isset($subscription['total']) ? floatval($subscription['total']) : 0;
    $currency = isset($subscription['currency']) ? $subscription['currency'] : 'USD';
    
    $content .= '<p style="margin: 0 0 16px;"><strong>';
    $content .= sprintf(
        esc_html__('Your next payment of %1$s is scheduled for %2$s.', 'bocs-wordpress'),
        esc_html(number_format($total, 2)) . ' ' . esc_html($currency),
        esc_html($next_date->format('F j, Y'))
    );
    $content .= '</strong></p>';
}

$content .= '</div>';

// Order details heading
$content .= '<h2 style="display: block; color: #333333; font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; font-size: 22px; font-weight: 500; line-height: 130%; margin: 30px 0 18px; text-align: left;">Subscription Details</h2>';
$content .= '</div>';

// Order details with Bocs teal color
$content .= '<h2 style="color: #3C7B7C !important; display: block; font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif; font-size: 18px; font-weight: bold; line-height: 130%; margin: 0 0 18px; text-align: left;">';
$content .= sprintf(__('[Subscription #%s]', 'bocs-wordpress'), $subscription['id'] ?? '');
if (isset($subscription['createdAt'])) {
    $created_date = new DateTime($subscription['createdAt']);
    $content .= ' (' . esc_html($created_date->format('F j, Y')) . ')';
}
$content .= '</h2>';

// Subscription items
$content .= '<div style="margin-bottom: 40px; padding: 15px; background-color: #f8f8f8; border-radius: 8px;">';
$content .= '<h3 style="color: #3C7B7C; margin-top: 0;">Subscription Items</h3>';

if (isset($subscription['lineItems']) && is_array($subscription['lineItems']) && !empty($subscription['lineItems'])) {
    $content .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
    $content .= '<tr>';
    $content .= '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Product</th>';
    $content .= '<th style="text-align: center; padding: 8px; border-bottom: 1px solid #ddd;">Quantity</th>';
    $content .= '<th style="text-align: right; padding: 8px; border-bottom: 1px solid #ddd;">Price</th>';
    $content .= '</tr>';
    
    foreach ($subscription['lineItems'] as $item) {
        $product_name = isset($item['name']) ? $item['name'] : 'Product';
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
        $price = isset($item['price']) ? floatval($item['price']) : 0;
        $total = $price * $quantity;
        $currency = isset($subscription['currency']) ? $subscription['currency'] : 'USD';
        
        $content .= '<tr>';
        $content .= '<td style="padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html($product_name) . '</td>';
        $content .= '<td style="text-align: center; padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html($quantity) . '</td>';
        $content .= '<td style="text-align: right; padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html(number_format($total, 2)) . ' ' . esc_html($currency) . '</td>';
        $content .= '</tr>';
    }
    $content .= '</table>';
} else {
    $content .= '<p>No items found in this subscription.</p>';
}

// Frequency details
if (isset($subscription['frequency'])) {
    $frequency = $subscription['frequency'];
    $content .= '<div style="margin-top: 20px; padding: 10px 15px; background-color: #e9f7f7; border-radius: 4px;">';
    $content .= '<h4 style="margin-top: 0; color: #3C7B7C;">Billing Frequency</h4>';
    $content .= '<p>';
    $content .= sprintf(
        esc_html__('You will be billed every %1$s %2$s', 'bocs-wordpress'),
        '<strong>' . esc_html($frequency['frequency']) . '</strong>',
        '<strong>' . esc_html($frequency['timeUnit']) . '</strong>'
    );
    
    if (isset($frequency['discount']) && $frequency['discount'] > 0) {
        $content .= ' <span style="color: #d26e4b;">(';
        if (isset($frequency['discountType']) && $frequency['discountType'] === 'DOLLAR') {
            $content .= '$' . esc_html($frequency['discount']) . ' off';
        } else {
            $content .= esc_html($frequency['discount']) . '% off';
        }
        $content .= ')</span>';
    }
    
    $content .= '</p>';
    $content .= '</div>';
}

$content .= '</div>';

// Manage subscription section
$content .= '<div style="margin-bottom: 40px; padding: 20px; background-color: #f0f7f7; border-radius: 6px; text-align: center;">';
$content .= '<h3 style="color: #3C7B7C; margin-top: 0;">Manage Your Subscription</h3>';
$content .= '<p style="margin-bottom: 20px;">Need to make changes? You can manage your subscription at any time by logging into your account.</p>';

// My account URL
$account_url = wc_get_account_endpoint_url('bocs-subscriptions');
if ($account_url) {
    $content .= '<a href="' . esc_url($account_url) . '" style="display: inline-block; background-color: #3C7B7C; color: #ffffff; font-size: 16px; font-weight: bold; line-height: 100%; text-decoration: none; padding: 12px 25px; border-radius: 4px;">';
    $content .= esc_html__('Manage Subscription', 'bocs-wordpress');
    $content .= '</a>';
}
$content .= '</div>';

// Footer text
$content .= '<div style="padding: 0 12px; max-width: 100%;">';

// Additional content from settings
if ($additional_content) {
    $content .= '<div style="margin-bottom: 25px; padding: 0 5px;">';
    $content .= wp_kses_post(wpautop(wptexturize($additional_content)));
    $content .= '</div>';
}

// Thank you message
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('Thank you for continuing to be our valued customer!', 'bocs-wordpress') . '</p>';

$content .= '</div>';

// Email footer
$content .= $email->get_template_footer();

// Send the email
echo $content; 