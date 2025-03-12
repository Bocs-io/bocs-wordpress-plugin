<?php
/**
 * Customer new account email
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

// Welcome message
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('Hi,', 'bocs-wordpress') . '</p>';
$content .= '<p style="margin: 0 0 16px;">' . sprintf(esc_html__('Thanks for creating an account on %s. We\'re excited to have you join our community!', 'bocs-wordpress'), esc_html(get_bloginfo('name'))) . '</p>';

// Welcome box
$content .= '<div style="background-color: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px;">';
$content .= '<p style="margin: 0 0 16px; color: #4caf50; font-weight: 600;">' . esc_html__('Welcome to Bocs!', 'bocs-wordpress') . '</p>';
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('Your account has been created successfully. Below are your account details:', 'bocs-wordpress') . '</p>';
$content .= '</div>';

// Account details
$content .= '<div style="background-color: #f8f9fa; padding: 20px; border-radius: 4px; margin-bottom: 30px;">';
$content .= '<h2 style="display: block; color: #3C7B7C; font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 600; line-height: 130%; margin: 0 0 18px; text-align: left;">' . esc_html__('Account Details', 'bocs-wordpress') . '</h2>';
$content .= '<p style="margin: 0 0 16px;"><strong>' . esc_html__('Username:', 'bocs-wordpress') . '</strong> ' . esc_html($user_login) . '</p>';

// Show password field if set
if (!empty($password_generated)) {
    $content .= '<p style="margin: 0 0 16px;"><strong>' . esc_html__('Password:', 'bocs-wordpress') . '</strong> ' . esc_html($user_pass) . '</p>';
    
    // Password security notice
    $content .= '<div style="background-color: #fff8e1; border-left: 4px solid #ffa000; padding: 15px 20px; margin: 15px 0; border-radius: 4px;">';
    $content .= '<p style="margin: 0 0 16px; color: #ffa000; font-weight: 600;">' . esc_html__('Security Notice', 'bocs-wordpress') . '</p>';
    $content .= '<p style="margin: 0 0 16px;">' . esc_html__('We recommend changing this auto-generated password to something more memorable when you first log in.', 'bocs-wordpress') . '</p>';
    $content .= '</div>';
}
$content .= '</div>';

// Login button
$content .= '<div style="text-align: center; margin: 40px 0;">';
$content .= '<a href="' . esc_url(wc_get_page_permalink('myaccount')) . '" style="display: inline-block; background-color: #3C7B7C; color: #ffffff; font-size: 16px; font-weight: bold; line-height: 100%; text-decoration: none; padding: 12px 25px; border-radius: 4px;">' . esc_html__('Login to Your Account', 'bocs-wordpress') . '</a>';
$content .= '</div>';

// Additional content from settings
if ($additional_content) {
    $content .= '<div style="margin-bottom: 25px; padding: 0 5px;">';
    $content .= wp_kses_post(wpautop(wptexturize($additional_content)));
    $content .= '</div>';
}

// Footer text
$content .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e5e5; color: #757575; font-size: 13px;">';
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('If you have any questions about your account, please contact our support team.', 'bocs-wordpress') . '</p>';
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('Thank you for choosing Bocs!', 'bocs-wordpress') . '</p>';
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