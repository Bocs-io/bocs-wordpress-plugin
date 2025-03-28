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
echo sprintf(esc_html__('Hi %s,', 'bocs-wordpress'), esc_html($subscription->get_billing_first_name())) . "\n\n";

echo esc_html__('Your subscription has been switched successfully. Your new subscription details are shown below for your reference:', 'bocs-wordpress') . "\n\n";

echo "= " . esc_html__('SUBSCRIPTION UPDATED', 'bocs-wordpress') . " =\n\n";
echo esc_html__('Your subscription has been successfully switched to a new plan.', 'bocs-wordpress') . "\n\n";

if ($subscription->get_date('next_payment')) {
    echo sprintf(
        esc_html__('Your next payment of %1$s is scheduled for %2$s.', 'bocs-wordpress'),
        strip_tags(wc_price($subscription->get_total(), array('currency' => $subscription->get_currency()))),
        date_i18n(wc_date_format(), strtotime($subscription->get_date('next_payment')))
    ) . "\n\n";
}

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
    echo esc_html__('This subscription was created through the Bocs App.', 'bocs-wordpress') . "\n\n";
}

echo "= " . esc_html__('Subscription Details', 'bocs-wordpress') . " =\n\n";

/* translators: %s: Order ID. */
echo sprintf(esc_html__('[Order #%s]', 'bocs-wordpress'), $subscription->get_order_number()) . ' (' . date_i18n(wc_date_format(), strtotime($subscription->get_date_created())) . ")\n";

$subscription_items = $subscription->get_items();
if (!empty($subscription_items)) {
    foreach ($subscription_items as $item_id => $item) {
        $product = $item->get_product();
        $item_name = $item->get_name();
        $qty = $item->get_quantity();
        $price = $subscription->get_line_subtotal($item, false, false);
        
        if ($product && $product->get_sku()) {
            $item_name .= ' (' . $product->get_sku() . ')';
        }
        
        echo '* ' . $item_name . ' × ' . $qty . ' - ' . strip_tags(wc_price($price, array('currency' => $subscription->get_currency()))) . "\n";
        
        // Get any meta information and output
        $item_meta_display = wc_display_item_meta($item, array('echo' => false, 'before' => '', 'separator' => ', ', 'after' => '', 'autop' => false));
        if ($item_meta_display) {
            echo '  ' . strip_tags($item_meta_display) . "\n";
        }
    }
}

echo "\n";

// Membership details if applicable
if (function_exists('wc_memberships_get_user_memberships') && $subscription->get_user_id()) {
    $user_id = $subscription->get_user_id();
    if ($user_id) {
        $memberships = wc_memberships_get_user_memberships($user_id);
        if (!empty($memberships)) {
            echo "= " . esc_html__('Membership Details', 'bocs-wordpress') . " =\n\n";
            foreach ($memberships as $membership) {
                echo sprintf(esc_html__('Plan: %s', 'bocs-wordpress'), $membership->get_plan()->get_name()) . "\n";
                echo sprintf(esc_html__('Status: %s', 'bocs-wordpress'), wc_memberships_get_user_membership_status_name($membership->get_status())) . "\n";
                
                if ($membership->get_start_date()) {
                    echo sprintf(esc_html__('Start Date: %s', 'bocs-wordpress'), date_i18n(wc_date_format(), strtotime($membership->get_start_date()))) . "\n";
                }
                
                if ($membership->get_end_date()) {
                    echo sprintf(esc_html__('End Date: %s', 'bocs-wordpress'), date_i18n(wc_date_format(), strtotime($membership->get_end_date()))) . "\n";
                }
                echo "\n";
            }
        }
    }
}

// Billing and shipping details
echo "= " . esc_html__('Customer Details', 'bocs-wordpress') . " =\n\n";

// Billing details
echo esc_html__('Billing Address:', 'bocs-wordpress') . "\n";
echo wp_kses_post($subscription->get_formatted_billing_address()) . "\n\n";

// Add phone number if provided
if ($subscription->get_billing_phone()) {
    echo esc_html__('Phone:', 'bocs-wordpress') . ' ' . esc_html($subscription->get_billing_phone()) . "\n\n";
}

// Add email address
echo esc_html__('Email:', 'bocs-wordpress') . ' ' . esc_html($subscription->get_billing_email()) . "\n\n";

// Payment method
echo esc_html__('Payment Method:', 'bocs-wordpress') . ' ' . esc_html($subscription->get_payment_method_title()) . "\n\n";

// Shipping address if different
if ($subscription->get_formatted_shipping_address()) {
    echo "= " . esc_html__('Shipping Details', 'bocs-wordpress') . " =\n\n";
    echo esc_html__('Shipping Address:', 'bocs-wordpress') . "\n";
    echo wp_kses_post($subscription->get_formatted_shipping_address()) . "\n\n";
}

// Manage subscription link
echo "= " . esc_html__('Manage Your Subscription', 'bocs-wordpress') . " =\n\n";
echo esc_html__('To view or manage your subscription, please visit:', 'bocs-wordpress') . "\n";
echo esc_url($subscription->get_view_order_url()) . "\n\n";

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