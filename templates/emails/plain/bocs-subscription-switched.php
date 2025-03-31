<?php
/**
 * Bocs Customer Subscription Switched Email (Plain Text)
 *
 * This template can be overridden by copying it to yourtheme/bocs-wordpress/emails/plain/bocs-subscription-switched.php
 *
 * @package Bocs/Templates/Emails/Plain
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading));
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/* translators: %s: Customer first name */
$customer_name = '';
if (isset($subscription_data['customer']) && isset($subscription_data['customer']['firstName'])) {
    $customer_name = $subscription_data['customer']['firstName'];
} elseif (isset($subscription_data['billing']) && isset($subscription_data['billing']['firstName'])) {
    $customer_name = $subscription_data['billing']['firstName'];
}
echo sprintf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($customer_name)) . "\n\n";

// Determine the type of update - box update or frequency update
$is_frequency_update = !empty($email->frequency_id);

if ($is_frequency_update) {
    echo esc_html__('Your subscription frequency has been updated successfully. Your new subscription details are shown below for your reference:', 'bocs-wordpress') . "\n\n";
    
    echo "= " . esc_html__('FREQUENCY UPDATED', 'bocs-wordpress') . " =\n\n";
    echo esc_html__('Your subscription frequency has been successfully changed.', 'bocs-wordpress') . "\n\n";
    
    // Display the new frequency details
    if (isset($subscription_data['frequency'])) {
        $frequency = $subscription_data['frequency'];
        echo "= " . esc_html__('New Billing Frequency', 'bocs-wordpress') . " =\n\n";
        
        echo sprintf(
            esc_html__('You will be billed every %1$s %2$s', 'bocs-wordpress'),
            $frequency['frequency'],
            $frequency['timeUnit']
        );
        
        if (isset($frequency['discount']) && $frequency['discount'] > 0) {
            echo ' (';
            if (isset($frequency['discountType']) && $frequency['discountType'] === 'DOLLAR') {
                echo '$' . $frequency['discount'] . ' off';
            } else {
                echo $frequency['discount'] . '% off';
            }
            echo ')';
        }
        echo "\n\n";
    }
} else {
    echo esc_html__('Your subscription has been switched successfully. Your new subscription details are shown below for your reference:', 'bocs-wordpress') . "\n\n";
    
    echo "= " . esc_html__('SUBSCRIPTION UPDATED', 'bocs-wordpress') . " =\n\n";
    echo esc_html__('Your subscription has been successfully switched to a new plan.', 'bocs-wordpress') . "\n\n";
}

if (isset($subscription_data['nextPaymentDateGmt'])) {
    $next_date = new DateTime($subscription_data['nextPaymentDateGmt']);
    $total = isset($subscription_data['total']) ? floatval($subscription_data['total']) : 0;
    $currency = isset($subscription_data['currency']) ? $subscription_data['currency'] : 'USD';
    
    echo sprintf(
        esc_html__('Your next payment of %1$s is scheduled for %2$s.', 'bocs-wordpress'),
        number_format($total, 2) . ' ' . $currency,
        $next_date->format('F j, Y')
    ) . "\n\n";
}

// Check for Bocs App attribution
$parent_order_id = is_callable(array($subscription_data, 'get_parent_id')) ? $subscription_data->get_parent_id() : $subscription_data->get_id();
$source_type = get_post_meta($parent_order_id, '_wc_order_attribution_source_type', true);
$utm_source = get_post_meta($parent_order_id, '_wc_order_attribution_utm_source', true);
$bocs_id = get_post_meta($parent_order_id, '__bocs_bocs_id', true);

if (
    (isset($source_type) && strpos(strtolower($source_type), 'bocs') !== false) ||
    (isset($utm_source) && strpos(strtolower($utm_source), 'bocs') !== false) ||
    !empty($bocs_id)
) {
    echo esc_html__('This subscription was created through the Bocs App.', 'bocs-wordpress') . "\n\n";
}

echo "= " . esc_html__('Subscription Details', 'bocs-wordpress') . " =\n\n";

/* translators: %s: Order ID. */
echo sprintf(esc_html__('[Subscription #%s]', 'bocs-wordpress'), $subscription_data['id'] ?? '') . "\n";
if (isset($subscription_data['createdAt'])) {
    $created_date = new DateTime($subscription_data['createdAt']);
    echo '(' . esc_html($created_date->format('F j, Y')) . ")\n\n";
} else {
    echo "\n\n";
}

// Only show line items if this is not a frequency update
if (!$is_frequency_update && isset($subscription_data['lineItems']) && is_array($subscription_data['lineItems']) && !empty($subscription_data['lineItems'])) {
    echo esc_html__('Subscription Items:', 'bocs-wordpress') . "\n";
    
    foreach ($subscription_data['lineItems'] as $item) {
        $product_name = isset($item['name']) ? $item['name'] : 'Product';
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
        $price = isset($item['price']) ? floatval($item['price']) : 0;
        $total = $price * $quantity;
        $currency = isset($subscription_data['currency']) ? $subscription_data['currency'] : 'USD';
        
        echo '* ' . $product_name . ' × ' . $quantity . ' - ' . number_format($total, 2) . ' ' . $currency . "\n";
    }
    
    echo "\n";
}

// Customer details
echo "= " . esc_html__('Customer Details', 'bocs-wordpress') . " =\n\n";

// Try to get customer info from different possible structures
$customer = isset($subscription_data['customer']) ? $subscription_data['customer'] : (isset($subscription_data['billing']) ? $subscription_data['billing'] : []);

if (!empty($customer)) {
    // Billing details
    echo esc_html__('Billing Information:', 'bocs-wordpress') . "\n";
    
    if (isset($customer['firstName']) || isset($customer['lastName'])) {
        echo esc_html($customer['firstName'] ?? '') . ' ' . esc_html($customer['lastName'] ?? '') . "\n";
    }
    
    if (isset($customer['email'])) {
        echo esc_html__('Email:', 'bocs-wordpress') . ' ' . esc_html($customer['email']) . "\n";
    }
    
    if (isset($customer['phone'])) {
        echo esc_html__('Phone:', 'bocs-wordpress') . ' ' . esc_html($customer['phone']) . "\n";
    }
    
    // Add billing address if available
    if (isset($customer['address']) && is_array($customer['address'])) {
        $address = $customer['address'];
        
        if (!empty($address['line1'])) {
            echo $address['line1'] . "\n";
        }
        
        if (!empty($address['line2'])) {
            echo $address['line2'] . "\n";
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
            echo $city_state_zip . "\n";
        }
        
        if (!empty($address['country'])) {
            echo $address['country'] . "\n";
        }
    }
} else {
    echo esc_html__('No customer information available.', 'bocs-wordpress') . "\n";
}

echo "\n";

// Manage subscription link
echo "= " . esc_html__('Manage Your Subscription', 'bocs-wordpress') . " =\n\n";
echo esc_html__('To view or manage your subscription, please visit:', 'bocs-wordpress') . "\n";

// View subscription URL - if we have a URL
$view_url = '';
if (function_exists('wc_get_account_endpoint_url')) {
    $view_url = wc_get_account_endpoint_url('bocs-subscriptions');
    echo esc_url($view_url) . "\n\n";
} else {
    echo esc_html__('Please log in to your account to manage your subscription.', 'bocs-wordpress') . "\n\n";
}

// Additional content from settings
if ($additional_content) {
    echo "= " . esc_html__('Additional Information', 'bocs-wordpress') . " =\n\n";
    echo wp_kses_post(wp_strip_all_tags(wptexturize($additional_content)));
    echo "\n\n";
}

// Footer text
echo esc_html__('If you have any questions about your subscription changes, please contact our customer support team.', 'bocs-wordpress') . "\n\n";
echo esc_html__('Thank you for your subscription with Bocs!', 'bocs-wordpress');

echo "\n\n----------------------------------------\n\n";

echo wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'))); 