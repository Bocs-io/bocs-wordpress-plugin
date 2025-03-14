<?php
/**
 * Customer Reset Password email
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
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('Hi,', 'bocs-wordpress') . '</p>';

// Password reset message
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('Someone has requested a password reset for the following account:', 'bocs-wordpress') . '</p>';
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('Username:', 'bocs-wordpress') . ' ' . esc_html($user_login) . '</p>';
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('If you didn\'t make this request, you can ignore this email. If you\'d like to proceed:', 'bocs-wordpress') . '</p>';

// Reset password button
$content .= '<div style="text-align: center; margin: 40px 0;">';
$content .= '<a href="' . esc_url($reset_key) . '" style="display: inline-block; background-color: #3C7B7C; color: #ffffff; font-size: 16px; font-weight: bold; line-height: 100%; text-decoration: none; padding: 12px 25px; border-radius: 4px;">' . esc_html__('Reset Password', 'bocs-wordpress') . '</a>';
$content .= '</div>';

// Security notice
$content .= '<div style="background-color: #fff8e1; border-left: 4px solid #ffa000; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px;">';
$content .= '<p style="margin: 0 0 16px; color: #ffa000; font-weight: 600;">' . esc_html__('Security Notice', 'bocs-wordpress') . '</p>';
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('This password reset link will expire in 24 hours. If you need a new reset link after that time, please request another password reset.', 'bocs-wordpress') . '</p>';
$content .= '</div>';

// Footer text
$content .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e5e5; color: #757575; font-size: 13px;">';
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('If you did not request a password reset, please contact our support team immediately.', 'bocs-wordpress') . '</p>';
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('Thank you for using Bocs!', 'bocs-wordpress') . '</p>';
$content .= '</div>';
$content .= '</div>';

// Get and modify the email footer
$footer_content = $email->get_template_footer();
if (function_exists('bocs_replace_woocommerce_colors')) {
    $footer_content = bocs_replace_woocommerce_colors($footer_content);
} else {
    // Fallback if the helper function doesn't exist
    $footer_content = str_replace("color: #7f54b3", "color: #3C7B7C", $footer_content);
    $footer_content = str_replace('color="#7f54b3"', 'color="#3C7B7C"', $footer_content);
    $footer_content = str_replace('#7f54b3', '#3C7B7C', $footer_content);
}
$content .= $footer_content;

// Get final output and do a final color replacement to catch any we missed
$final_output = $content;
if (function_exists('bocs_replace_woocommerce_colors')) {
    $final_output = bocs_replace_woocommerce_colors($final_output);
} else {
    // Fallback if the helper function doesn't exist
    $final_output = str_replace("#7f54b3", "#3C7B7C", $final_output);
}

// Output the email content directly
echo $final_output; 