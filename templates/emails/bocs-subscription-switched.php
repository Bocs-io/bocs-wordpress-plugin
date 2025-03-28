<?php
/**
 * Bocs Customer Subscription Switched Email Template
 *
 * This template can be overridden by copying it to yourtheme/bocs-wordpress/emails/bocs-subscription-switched.php
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

// Switched message
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('Your subscription has been switched successfully. Your new subscription details are shown below for your reference:', 'bocs-wordpress') . '</p>';

// Success notification box
$content .= '<div style="background-color: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px;">';
$content .= '<p style="margin: 0 0 16px; color: #4caf50; font-weight: 600;">' . esc_html__('Subscription Updated', 'bocs-wordpress') . '</p>';
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('Your subscription has been successfully switched to a new plan.', 'bocs-wordpress') . '</p>';

// Next payment details
if (isset($subscription['nextPaymentDateGmt'])) {
    $next_date = new DateTime($subscription['nextPaymentDateGmt']);
    $total = isset($subscription['total']) ? floatval($subscription['total']) : 0;
    $currency = isset($subscription['currency']) ? $subscription['currency'] : 'USD';
    
    $content .= '<p style="margin: 0 0 16px;">';
    $content .= sprintf(
        esc_html__('Your next payment of %1$s is scheduled for %2$s.', 'bocs-wordpress'),
        '<strong>' . esc_html(number_format($total, 2)) . ' ' . esc_html($currency) . '</strong>',
        '<strong>' . esc_html($next_date->format('F j, Y')) . '</strong>'
    );
    $content .= '</p>';
}
$content .= '</div>';

// Check for Bocs App attribution
$bocs_id = $subscription['bocs']['id'] ?? '';

if (!empty($bocs_id)) {
    $content .= '<div style="background-color: #fff8e1; padding: 12px 15px; margin-bottom: 25px; border-radius: 4px; border: 1px dashed #ffc107;">';
    $content .= '<p style="margin: 0 0 16px;"><span style="color: #ff6b00; font-weight: 500;">' . esc_html__('This subscription was created through the Bocs App.', 'bocs-wordpress') . '</span></p>';
    $content .= '</div>';
}

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

// Customer details 
if (isset($subscription['customer']) || isset($subscription['billing'])) {
    $content .= '<div style="margin-bottom: 40px;">';
    $content .= '<h3 style="color: #3C7B7C;">Customer Details</h3>';
    
    // Billing details
    $content .= '<div style="margin-bottom: 20px;">';
    $content .= '<h4 style="margin-bottom: 10px;">Billing Information</h4>';
    
    $billing_info = '';
    
    // Try to get customer info from different possible structures
    $customer = isset($subscription['customer']) ? $subscription['customer'] : (isset($subscription['billing']) ? $subscription['billing'] : []);
    
    if (isset($customer['firstName']) || isset($customer['lastName'])) {
        $billing_info .= '<p>' . esc_html($customer['firstName'] ?? '') . ' ' . esc_html($customer['lastName'] ?? '') . '</p>';
    }
    
    if (isset($customer['email'])) {
        $billing_info .= '<p>Email: ' . esc_html($customer['email']) . '</p>';
    }
    
    if (isset($customer['phone'])) {
        $billing_info .= '<p>Phone: ' . esc_html($customer['phone']) . '</p>';
    }
    
    // Add billing address if available
    if (isset($customer['address']) && is_array($customer['address'])) {
        $address = $customer['address'];
        $address_parts = [];
        
        if (!empty($address['line1'])) {
            $address_parts[] = $address['line1'];
        }
        
        if (!empty($address['line2'])) {
            $address_parts[] = $address['line2'];
        }
        
        $city_state_zip = '';
        if (!empty($address['city'])) {
            $city_state_zip .= $address['city'];
        }
        
        if (!empty($address['state'])) {
            $city_state_zip .= (!empty($city_state_zip) ? ', ' : '') . $address['state'];
        }
        
        if (!empty($address['postalCode'])) {
            $city_state_zip .= ' ' . $address['postalCode'];
        }
        
        if (!empty($city_state_zip)) {
            $address_parts[] = $city_state_zip;
        }
        
        if (!empty($address['country'])) {
            $address_parts[] = $address['country'];
        }
        
        if (!empty($address_parts)) {
            $billing_info .= '<p>' . implode('<br>', array_map('esc_html', $address_parts)) . '</p>';
        }
    }
    
    if (empty($billing_info)) {
        $billing_info = '<p>No billing information available.</p>';
    }
    
    $content .= $billing_info;
    $content .= '</div>';
    
    $content .= '</div>';
}

// Footer text
$content .= '<div style="padding: 0 12px; max-width: 100%;">';

// View subscription button - if we have a URL
$view_url = '';
if (function_exists('wc_get_account_endpoint_url')) {
    $view_url = wc_get_account_endpoint_url('bocs-subscriptions');
}

if (!empty($view_url)) {
    $content .= '<div style="margin: 40px 0; text-align: center;">';
    $content .= '<a href="' . esc_url($view_url) . '" style="display: inline-block; background-color: #3C7B7C; color: #ffffff; font-size: 16px; font-weight: bold; line-height: 100%; text-decoration: none; padding: 12px 25px; border-radius: 4px;">';
    $content .= esc_html__('Manage Subscription', 'bocs-wordpress');
    $content .= '</a>';
    $content .= '</div>';
}

// Additional content from settings
if ($additional_content) {
    $content .= '<div style="margin-bottom: 25px; padding: 0 5px;">';
    $content .= wp_kses_post(wpautop(wptexturize($additional_content)));
    $content .= '</div>';
}

// Standard footer info
$content .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e5e5; color: #757575; font-size: 13px;">';
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('If you have any questions about your subscription changes, please contact our customer support team.', 'bocs-wordpress') . '</p>';
$content .= '<p style="margin: 0 0 16px;">' . esc_html__('Thank you for your subscription with Bocs!', 'bocs-wordpress') . '</p>';
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