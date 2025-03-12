<?php
/**
 * Admin cancelled order email
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

// Cancellation message
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('The following order has been cancelled. Order details are as follows:', 'bocs-wordpress') . '</p>';

// Cancelled box
$content .= '<div style="background-color: #ffebee; border-left: 4px solid #d32f2f; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px;">';
$content .= '<p style="margin: 0 0 16px; color: #d32f2f; font-weight: 600;">' . esc_html__('Order Cancelled', 'bocs-wordpress') . '</p>';
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('This order has been cancelled. Inventory has been restored.', 'bocs-wordpress') . '</p>';
$content .= '</div>';

// Source notification (Bocs App)
$source_type = get_post_meta($order->get_id(), '_wc_order_attribution_source_type', true);
$utm_source = get_post_meta($order->get_id(), '_wc_order_attribution_utm_source', true);

if (
    (isset($source_type) && strpos(strtolower($source_type), 'bocs') !== false) ||
    (isset($utm_source) && strpos(strtolower($utm_source), 'bocs') !== false) ||
    get_post_meta($order->get_id(), '__bocs_bocs_id', true)
) {
    $content .= '<div style="background-color: #fff8e1; padding: 12px 15px; margin-bottom: 25px; border-radius: 4px; border: 1px dashed #ffc107;">';
    $content .= '<p style="margin: 0 0 16px;"><span style="color: #ff6b00; font-weight: 500;">This order was created through the Bocs App.</span></p>';
    $content .= '</div>';
}

// Order details heading
$content .= '<h2 style="display: block; color: #333333; font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; font-size: 22px; font-weight: 500; line-height: 130%; margin: 30px 0 18px; text-align: left;">' . esc_html__('Order Details', 'bocs-wordpress') . '</h2>';
$content .= '</div>';

// Order details with Bocs teal color
$content .= '<h2 style="color: #3C7B7C !important; display: block; font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif; font-size: 18px; font-weight: bold; line-height: 130%; margin: 0 0 18px; text-align: left;">';
$content .= sprintf(__('[Order #%s] (%s)', 'bocs-wordpress'), $order->get_order_number(), date_i18n(wc_date_format(), strtotime($order->get_date_created())));
$content .= '</h2>';

// View order button
$content .= '<div style="margin-bottom: 30px; text-align: center;">';
$content .= '<a href="' . esc_url(admin_url('post.php?post=' . $order->get_id() . '&action=edit')) . '" style="background-color: #3C7B7C; border-radius: 4px; display: inline-block; font-weight: 500; line-height: 100%; margin: 0 10px; text-align: center; text-decoration: none !important; font-size: 14px; padding: 12px 25px; color: #ffffff !important;">';
$content .= esc_html__('View Order', 'bocs-wordpress');
$content .= '</a>';
$content .= '</div>';

// Order items
ob_start();
wc_get_template(
    'emails/email-order-details.php',
    array(
        'order'         => $order,
        'sent_to_admin' => true,
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
        'order'         => $order,
        'sent_to_admin' => true,
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