<?php
/**
 * Box Updated Email Template (Plain text)
 *
 * This template can be overridden by copying it to yourtheme/bocs/emails/plain/bocs-subscription-switched.php.
 *
 * @package Bocs/Templates/Emails/Plain
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading)) . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

// Determine the type of update - box update or frequency update
$is_frequency_update = !empty($email->frequency_id);

if ($is_frequency_update) {
    echo esc_html__('Your subscription frequency has been updated successfully.', 'bocs-wordpress') . "\n\n";
} else {
    echo esc_html__('Your box contents have been updated successfully.', 'bocs-wordpress') . "\n\n";
}

if (!empty($subscription_data)) {
    echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
    echo esc_html__('BOX DETAILS', 'bocs-wordpress') . "\n";
    echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
    
    if (!empty($subscription_data['bocs']['name'])) {
        echo esc_html__('Box Type:', 'bocs-wordpress') . ' ' . esc_html($subscription_data['bocs']['name']) . "\n\n";
    }

    if (!empty($subscription_data['id'])) {
        echo esc_html__('Subscription ID:', 'bocs-wordpress') . ' ' . esc_html($subscription_data['id']) . "\n\n";
    }

    if ($is_frequency_update && !empty($subscription_data['frequency'])) {
        echo esc_html__('SUBSCRIPTION FREQUENCY', 'bocs-wordpress') . "\n";
        echo "----------------------------------------\n\n";
        
        // Display frequency information
        $frequency = $subscription_data['frequency'];
        echo esc_html__('Frequency:', 'bocs-wordpress') . ' ';
        printf(
            esc_html__('Every %1$s %2$s', 'bocs-wordpress'),
            esc_html($frequency['frequency']),
            esc_html($frequency['timeUnit'])
        );
        
        // Display discount if available
        if (isset($frequency['discount']) && $frequency['discount'] > 0) {
            echo ' (';
            if (isset($frequency['discountType']) && $frequency['discountType'] === 'DOLLAR') {
                echo '$' . esc_html($frequency['discount']) . ' off';
            } else {
                echo esc_html($frequency['discount']) . '% off';
            }
            echo ')';
        }
        echo "\n\n";
    }

    if (!$is_frequency_update && !empty($subscription_data['lineItems'])) {
        echo esc_html__('BOX CONTENTS', 'bocs-wordpress') . "\n";
        echo "----------------------------------------\n\n";
        
        echo esc_html__('Product', 'bocs-wordpress') . "\t\t" . esc_html__('Quantity', 'bocs-wordpress') . "\n";
        echo "----------------------------------------\n";
        
        foreach ($subscription_data['lineItems'] as $item) {
            if ($item['productId'] !== 'shipping') {
                echo esc_html($item['name']) . "\t\t" . esc_html($item['quantity']) . "\n";
            }
        }
        echo "\n";
    }

    if (!empty($subscription_data['nextPaymentDateGmt'])) {
        echo esc_html__('Next Delivery:', 'bocs-wordpress') . ' ' . esc_html(date_i18n(get_option('date_format'), strtotime($subscription_data['nextPaymentDateGmt']))) . "\n\n";
    }
}

echo apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text', '')); 