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
$content .= $email->get_template_header($email_heading);

// Main content
$content .= '<div style="padding: 0 12px; max-width: 100%;">';

// Greeting
$first_name = $order->get_billing_first_name();
$content .= '<p>Hi ' . esc_html($first_name) . ',</p>';

// Renewal notice
$content .= '<div style="background-color: #f8f9fa; border-left: 4px solid #3C7B7C; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px;">';
$content .= '<p style="font-size: 16px; margin-bottom: 15px;">Your subscription renewal invoice for order #<strong>' . esc_html($order->get_order_number()) . '</strong> is now available.</p>';

if ($order->has_status('pending')) {
    $content .= '<p style="margin-bottom: 15px;">To ensure uninterrupted service, please submit payment at your earliest convenience.</p>';
} else {
    $content .= '<p style="margin-bottom: 15px;">Your payment method will be charged automatically, so no action is required on your part.</p>';
}
$content .= '</div>';

// Check for Bocs App attribution
$source_type = get_post_meta($order->get_id(), '_wc_order_attribution_source_type', true);
$utm_source = get_post_meta($order->get_id(), '_wc_order_attribution_utm_source', true);

if ($source_type === 'referral' && $utm_source === 'Bocs App') {
    $content .= '<div style="background-color: #fff8e1; padding: 12px 15px; margin-bottom: 25px; border-radius: 4px; border: 1px dashed #ffc107;">';
    $content .= '<p><span style="color: #ff6b00; font-weight: 500;">This order was created through the Bocs App.</span></p>';
    $content .= '</div>';
}

// Order status section
$content .= '<div style="background-color: #f8f9fa; border-radius: 6px; padding: 20px; margin-bottom: 30px; border: 1px solid #e5e5e5;">';
$content .= '<h3 style="margin-top: 0; margin-bottom: 15px; color: #333333; font-size: 18px;">Order Status</h3>';
$content .= '<p style="margin-bottom: 15px;">';

$status = $order->get_status();
$status_colors = array(
    'pending' => 'background-color: #fff8e1; color: #ff6d00;',
    'processing' => 'background-color: #e1f5fe; color: #0288d1;',
    'on-hold' => 'background-color: #f3e5f5; color: #7b1fa2;',
    'completed' => 'background-color: #e8f5e9; color: #388e3c;',
    'failed' => 'background-color: #ffebee; color: #d32f2f;',
    'cancelled' => 'background-color: #f5f5f5; color: #616161;'
);
$status_style = isset($status_colors[$status]) ? $status_colors[$status] : 'background-color: #f5f5f5; color: #616161;';

$content .= '<span style="display: inline-block; padding: 6px 12px; border-radius: 30px; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; ' . $status_style . '">';
$content .= esc_html(wc_get_order_status_name($status));
$content .= '</span></p>';

if ($order->has_status('pending')) {
    $content .= '<p>Your renewal payment is required to maintain your subscription.</p>';
    $content .= '<div style="text-align: center; margin: 25px 0 10px;">';
    $content .= '<a href="' . esc_url($order->get_checkout_payment_url()) . '" style="background-color: #3C7B7C; border-radius: 4px; color: #ffffff !important; display: inline-block; font-weight: 500; line-height: 100%; margin: 0; text-align: center; text-decoration: none !important; font-size: 14px; padding: 12px 25px; text-transform: uppercase; letter-spacing: 0.5px;">Pay Now</a>';
    $content .= '</div>';
} else {
    $content .= '<p>Your subscription is active and your renewal payment is being processed automatically.</p>';
}
$content .= '</div>';

// Order details heading
$content .= '<h2 style="color: #333333; font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; font-size: 22px; font-weight: 500; line-height: 130%; margin: 0 0 18px; text-align: left;">Order Details</h2>';
$content .= '</div>'; // Close main content div

// Order details table - create manually
$content .= '<div style="margin-bottom: 40px;">';
$content .= '<h2 style="margin-top: 0; color: #333333; padding-left: 12px;">Order Summary</h2>';
$content .= '<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #e5e5e5; margin-bottom: 20px;" border="1">';
$content .= '<thead><tr>';
$content .= '<th class="td" scope="col" style="text-align: left; color: #636363; border: 1px solid #e5e5e5; padding: 12px;">Product</th>';
$content .= '<th class="td" scope="col" style="text-align: left; color: #636363; border: 1px solid #e5e5e5; padding: 12px;">Quantity</th>';
$content .= '<th class="td" scope="col" style="text-align: left; color: #636363; border: 1px solid #e5e5e5; padding: 12px;">Price</th>';
$content .= '</tr></thead><tbody>';

// Add order items to the table
foreach ($order->get_items() as $item_id => $item) {
    $product = $item->get_product();
    $content .= '<tr class="order_item">';
    
    // Product name
    $content .= '<td class="td" style="text-align: left; vertical-align: middle; border: 1px solid #e5e5e5; padding: 12px;">';
    $content .= '<strong>' . esc_html($item->get_name()) . '</strong>';
    
    // Item meta
    $content .= '</td>';
    
    // Quantity
    $content .= '<td class="td" style="text-align: left; vertical-align: middle; border: 1px solid #e5e5e5; padding: 12px;">';
    $content .= esc_html($item->get_quantity());
    $content .= '</td>';
    
    // Price
    $content .= '<td class="td" style="text-align: left; vertical-align: middle; border: 1px solid #e5e5e5; padding: 12px;">';
    $content .= wp_kses_post($order->get_formatted_line_subtotal($item));
    $content .= '</td>';
    $content .= '</tr>';
}

// Add order totals
$content .= '</tbody><tfoot>';

// Subtotal
$content .= '<tr>';
$content .= '<th class="td" scope="row" colspan="2" style="text-align: right; border: 1px solid #e5e5e5; padding: 12px;">Subtotal:</th>';
$content .= '<td class="td" style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;">' . wp_kses_post($order->get_subtotal_to_display()) . '</td>';
$content .= '</tr>';

// Tax (if any)
if (wc_tax_enabled() && $order->get_total_tax() > 0) {
    $content .= '<tr>';
    $content .= '<th class="td" scope="row" colspan="2" style="text-align: right; border: 1px solid #e5e5e5; padding: 12px;">Tax:</th>';
    $content .= '<td class="td" style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;">' . wp_kses_post($order->get_tax_totals_html()) . '</td>';
    $content .= '</tr>';
}

// Payment method
$content .= '<tr>';
$content .= '<th class="td" scope="row" colspan="2" style="text-align: right; border: 1px solid #e5e5e5; padding: 12px;">Payment Method:</th>';
$content .= '<td class="td" style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;">' . esc_html($order->get_payment_method_title()) . '</td>';
$content .= '</tr>';

// Total
$content .= '<tr>';
$content .= '<th class="td" scope="row" colspan="2" style="text-align: right; border: 1px solid #e5e5e5; padding: 12px;">Total:</th>';
$content .= '<td class="td" style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;">' . wp_kses_post($order->get_formatted_order_total()) . '</td>';
$content .= '</tr>';

$content .= '</tfoot></table>';
$content .= '</div>';

// Customer details
$content .= '<div style="padding: 0 12px; max-width: 100%;">';
$content .= '<h2 style="color: #333333; font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; font-size: 22px; font-weight: 500; line-height: 130%; margin: 0 0 18px; text-align: left;">Customer Details</h2>';

// Billing address
$content .= '<div style="display: inline-block; vertical-align: top; width: 48%;">';
$content .= '<h3 style="color: #333333; font-size: 18px; font-weight: 500; margin-bottom: 10px;">Billing Address</h3>';
$content .= '<address style="padding: 12px; color: #636363; border: 1px solid #e5e5e5;">';
$content .= wp_kses_post($order->get_formatted_billing_address());
if ($order->get_billing_phone()) {
    $content .= '<br>Phone: ' . esc_html($order->get_billing_phone());
}
if ($order->get_billing_email()) {
    $content .= '<br>Email: ' . esc_html($order->get_billing_email());
}
$content .= '</address>';
$content .= '</div>';

// Shipping address
if ($order->needs_shipping_address()) {
    $content .= '<div style="display: inline-block; vertical-align: top; width: 48%; margin-left: 3%;">';
    $content .= '<h3 style="color: #333333; font-size: 18px; font-weight: 500; margin-bottom: 10px;">Shipping Address</h3>';
    $content .= '<address style="padding: 12px; color: #636363; border: 1px solid #e5e5e5;">' . wp_kses_post($order->get_formatted_shipping_address()) . '</address>';
    $content .= '</div>';
}

// Additional content
if ($additional_content) {
    $content .= '<div style="margin-top: 30px; margin-bottom: 25px; padding: 0 5px;">';
    $content .= wp_kses_post(wpautop(wptexturize($additional_content)));
    $content .= '</div>';
}

// Footer info
$content .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e5e5; color: #757575; font-size: 13px;">';
$content .= '<p>If you have any questions about your subscription, please contact our customer support team.</p>';
$content .= '<p>Thank you for your continued business with Bocs!</p>';
$content .= '</div>';
$content .= '</div>'; // Close main content div

// Email footer
$content .= $email->get_template_footer();

// Output all at once to prevent buffering issues
echo $content; 