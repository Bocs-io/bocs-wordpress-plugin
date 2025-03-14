<?php
/**
 * Customer Renewal Invoice email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-renewal-invoice.php.
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
$first_name = $order->get_billing_first_name();
$content .= '<p style="margin: 0 0 16px;">Hi ' . esc_html($first_name) . ',</p>';

// Renewal notice
$content .= '<div style="background-color: #f8f9fa; border-left: 4px solid #3C7B7C; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px;">';
$content .= '<p style="font-size: 16px; margin-bottom: 15px;">Your subscription renewal invoice for order #<strong>' . esc_html($order->get_order_number()) . '</strong> is now available.</p>';

if ($order->has_status('pending')) {
    $content .= '<p style="margin-bottom: 15px;">To ensure uninterrupted service, please submit payment at your earliest convenience.</p>';
    
    // Add payment button only for pending orders
    $pay_url = $order->get_checkout_payment_url();
    if (!empty($pay_url)) {
        $content .= '<div style="text-align: center; margin: 15px 0;">';
        $content .= '<a href="' . esc_url($pay_url) . '" style="background-color: #3C7B7C; border-radius: 4px; display: inline-block; font-weight: 500; line-height: 100%; margin: 0; text-align: center; font-size: 14px; padding: 12px 25px; text-transform: uppercase; letter-spacing: 0.5px; color: #ffffff; text-decoration: none;">';
        $content .= 'Pay for this order';
        $content .= '</a>';
        $content .= '</div>';
    }
} else {
    $content .= '<p style="margin-bottom: 15px;">Your payment method will be charged automatically, so no action is required on your part.</p>';
}
$content .= '</div>';

// Check for Bocs App attribution
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

// Order status for pending payments
if ($order->has_status('pending')) {
    $content .= '<div style="background-color: #f8f9fa; border-radius: 6px; padding: 20px; margin-bottom: 30px; border: 1px solid #e5e5e5;">';
    $content .= '<h3 style="display: block; font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif; font-weight: bold; line-height: 130%; margin: 16px 0 8px; text-align: left; margin-top: 0; margin-bottom: 15px; color: #333333; font-size: 18px;">Order Status</h3>';
    $content .= '<p style="margin: 0 0 16px; margin-bottom: 15px;">';
    $content .= '<span style="display: inline-block; padding: 6px 12px; border-radius: 30px; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; background-color: #fff8e1; color: #ff6d00;">';
    $content .= 'Pending payment';
    $content .= '</span>';
    $content .= '</p>';
    $content .= '</div>';
}

$content .= '<h2 style="display: block; color: #333333; font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; font-size: 22px; font-weight: 500; line-height: 130%; margin: 0 0 18px; text-align: left;">Order Details</h2>';
$content .= '</div>';

// Order details with Bocs teal color
$content .= '<h2 style="color: #3C7B7C !important; display: block; font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif; font-size: 18px; font-weight: bold; line-height: 130%; margin: 0 0 18px; text-align: left;">';
$content .= sprintf(__('[Order #%s] (%s)', 'bocs-wordpress'), $order->get_order_number(), date_i18n(wc_date_format(), strtotime($order->get_date_created())));
$content .= '</h2>';

// Order items
ob_start();
wc_get_template(
    'emails/email-order-details.php',
    array(
        'order'         => $order,
        'sent_to_admin' => false,
        'plain_text'    => false,
        'email'         => $email,
    )
);
$order_details = ob_get_clean();

// Replace all WooCommerce purple with Bocs teal in inline styles
$order_details = str_replace("color: #7f54b3", "color: #3C7B7C", $order_details);
$order_details = str_replace("color:#7f54b3", "color:#3C7B7C", $order_details);
$order_details = str_replace("color: #7f54b3;", "color: #3C7B7C;", $order_details);
$order_details = str_replace("color:#7f54b3;", "color:#3C7B7C;", $order_details);
// Also handle actual HTML color attributes
$order_details = str_replace('color="#7f54b3"', 'color="#3C7B7C"', $order_details);

$content .= $order_details;

// Customer details 
ob_start();
wc_get_template(
    'emails/email-customer-details.php',
    array(
        'order'         => $order,
        'sent_to_admin' => false,
        'plain_text'    => false,
        'email'         => $email,
    )
);
$customer_details = ob_get_clean();

// Replace all WooCommerce purple with Bocs teal in customer details
$customer_details = str_replace("color: #7f54b3", "color: #3C7B7C", $customer_details);
$customer_details = str_replace("color:#7f54b3", "color:#3C7B7C", $customer_details);
$customer_details = str_replace("color: #7f54b3;", "color: #3C7B7C;", $customer_details);
$customer_details = str_replace("color:#7f54b3;", "color:#3C7B7C;", $customer_details);
$customer_details = str_replace('color="#7f54b3"', 'color="#3C7B7C"', $customer_details);

// Replace h2 styles for Billing address
$customer_details = preg_replace(
    '/<h2 style=\'color: #7f54b3; display: block;([^>]*)\'>(.*?)<\/h2>/i',
    '<h2 style=\'color: #3C7B7C; display: block;$1\'>$2</h2>',
    $customer_details
);

$content .= $customer_details;

// Footer text
$content .= '<div style="padding: 0 12px; max-width: 100%;">';
$content .= '<div style="margin-bottom: 25px; padding: 0 5px;">';
$content .= '<p style="margin: 0 0 16px;">Thanks for using Bocs. We hope you enjoy your subscription!</p>';
$content .= '</div>';

// Additional content from settings
if ($additional_content) {
    $content .= '<div style="margin-bottom: 25px; padding: 0 5px;">';
    $content .= wp_kses_post(wpautop(wptexturize($additional_content)));
    $content .= '</div>';
}

// Standard footer info
$content .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e5e5; color: #757575; font-size: 13px;">';
$content .= '<p style="margin: 0 0 16px;">If you have any questions about your order, please contact our customer support team.</p>';
$content .= '<p style="margin: 0 0 16px;">Thank you for your business with Bocs!</p>';
$content .= '</div>';
$content .= '</div>';

// Get and modify the email footer
$footer_content = $email->get_template_footer();
$footer_content = str_replace("color: #7f54b3", "color: #3C7B7C", $footer_content);
$footer_content = str_replace('color="#7f54b3"', 'color="#3C7B7C"', $footer_content);
$content .= $footer_content;

// Get final output and do a final color replacement to catch any we missed
$final_output = $content;
$final_output = str_replace("#7f54b3", "#3C7B7C", $final_output);

// Output the email content directly
echo $final_output; 