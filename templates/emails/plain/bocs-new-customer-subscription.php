<?php defined("ABSPATH") || exit; echo "= " . esc_html($email_heading) . "

"; echo "Hi " . esc_html($order->get_billing_first_name()) . ",

Welcome to " . esc_html(get_bloginfo('name')) . "!

Congratulations your first subscription has been created.

";

// Order details will be added by WooCommerce hooks

// Billing/Shipping details will be added by WooCommerce hooks

echo "New Footer

";

if ($additional_content) echo esc_html($additional_content) . "

"; ?>
