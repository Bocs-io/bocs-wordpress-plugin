<?php
/**
 * Customer Subscription Cancelled email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-subscription-cancelled.php.
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
$content .= '<p style="margin: 0 0 16px;">Hi ' . esc_html($subscription->get_billing_first_name()) . ',</p>';

// Cancellation message
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('Your subscription has been cancelled. Your subscription details are shown below for your reference:', 'bocs-wordpress') . '</p>';

// Cancellation notification box
$content .= '<div style="background-color: #ffebee; border-left: 4px solid #d32f2f; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px;">';
$content .= '<p style="margin: 0 0 16px; color: #d32f2f; font-weight: 600;">' . esc_html__('Subscription Cancelled', 'bocs-wordpress') . '</p>';
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('Your subscription has been cancelled and will not renew.', 'bocs-wordpress') . '</p>';
$content .= '</div>';

// Check for Bocs App attribution
$parent_order_id = is_callable(array($subscription, 'get_parent_id')) ? $subscription->get_parent_id() : $subscription->get_id();
$source_type = get_post_meta($parent_order_id, '_wc_order_attribution_source_type', true);
$utm_source = get_post_meta($parent_order_id, '_wc_order_attribution_utm_source', true);
$bocs_id = get_post_meta($parent_order_id, '__bocs_bocs_id', true);

if (
    (isset($source_type) && strpos(strtolower($source_type), 'bocs') !== false) ||
    (isset($utm_source) && strpos(strtolower($utm_source), 'bocs') !== false) ||
    !empty($bocs_id)
) {
    $content .= '<div style="background-color: #fff8e1; padding: 12px 15px; margin-bottom: 25px; border-radius: 4px; border: 1px dashed #ffc107;">';
    $content .= '<p style="margin: 0 0 16px;"><span style="color: #ff6b00; font-weight: 500;">' . esc_html__('This subscription was created through the Bocs App.', 'bocs-wordpress') . '</span></p>';
    $content .= '</div>';
}

// Order details heading
$content .= '<h2 style="display: block; color: #333333; font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; font-size: 22px; font-weight: 500; line-height: 130%; margin: 30px 0 18px; text-align: left;">Subscription Details</h2>';
$content .= '</div>';

// Order details
ob_start();
wc_get_template(
    'emails/email-order-details.php',
    array(
        'order'         => $subscription,
        'sent_to_admin' => false,
        'plain_text'    => false,
        'email'         => $email,
    )
);
$order_details = ob_get_clean();

// Replace all WooCommerce purple with Bocs teal in inline styles
if (function_exists('bocs_replace_woocommerce_colors')) {
    $order_details = bocs_replace_woocommerce_colors($order_details);
} else {
    // Fallback if the helper function doesn't exist
    $order_details = str_replace("color: #7f54b3", "color: #3C7B7C", $order_details);
    $order_details = str_replace("color:#7f54b3", "color:#3C7B7C", $order_details);
    $order_details = str_replace('color="#7f54b3"', 'color="#3C7B7C"', $order_details);
    $order_details = str_replace('#7f54b3', '#3C7B7C', $order_details);
}

$content .= $order_details;

// Customer details 
ob_start();
wc_get_template(
    'emails/email-customer-details.php',
    array(
        'order'         => $subscription,
        'sent_to_admin' => false,
        'plain_text'    => false,
        'email'         => $email,
    )
);
$customer_details = ob_get_clean();

// Replace all WooCommerce purple with Bocs teal
if (function_exists('bocs_replace_woocommerce_colors')) {
    $customer_details = bocs_replace_woocommerce_colors($customer_details);
} else {
    // Fallback if the helper function doesn't exist
    $customer_details = str_replace("color: #7f54b3", "color: #3C7B7C", $customer_details);
    $customer_details = str_replace("color:#7f54b3", "color:#3C7B7C", $customer_details);
    $customer_details = str_replace('color="#7f54b3"', 'color="#3C7B7C"', $customer_details);
    $customer_details = str_replace('#7f54b3', '#3C7B7C', $customer_details);
}

$content .= $customer_details;

// Footer text
$content .= '<div style="padding: 0 12px; max-width: 100%;">';

// Additional content from settings
if ($additional_content) {
    $content .= '<div style="margin-bottom: 25px; padding: 0 5px;">';
    $content .= wp_kses_post(wpautop(wptexturize($additional_content)));
    $content .= '</div>';
}

// Standard footer info
$content .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e5e5; color: #757575; font-size: 13px;">';
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('If you\'d like to resubscribe in the future, please visit our website.', 'bocs-wordpress') . '</p>';
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('Thank you for being a customer at Bocs!', 'bocs-wordpress') . '</p>';
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