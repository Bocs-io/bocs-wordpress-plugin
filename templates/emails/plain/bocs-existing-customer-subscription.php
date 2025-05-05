<?php defined("ABSPATH") || exit;

echo "= " . esc_html($email_heading) . "\n\n";

echo "Hi " . esc_html($order->get_billing_first_name()) . ",\n\n";

echo "Thank you for your continued support! Your new Bocs subscription has been successfully set up.\n\n";

echo "ADDITIONAL SUBSCRIPTION CONFIRMED\n";
echo "Your new Bocs subscription has been processed successfully. You now have access to all benefits of this additional subscription.\n\n";

echo "VALUED BOCS CUSTOMER\n";
echo "As a returning customer, we appreciate your continued trust. Thank you for choosing Bocs again for your needs.\n\n";

echo "This subscription was created through the Bocs App. You can manage all your subscriptions and access services directly through the Bocs mobile app.\n\n";

echo "SUBSCRIPTION DETAILS\n";
echo "Order #" . $order->get_order_number() . " (" . date_i18n(wc_date_format(), strtotime($order->get_date_created())) . ")\n\n";

// Order items
foreach ($order->get_items() as $item_id => $item) {
    $product = $item->get_product();
    echo $item->get_name() . " × " . $item->get_quantity() . " - " . $order->get_formatted_line_subtotal($item) . "\n";
}

// Order totals
$totals = $order->get_order_item_totals();
if ($totals) {
    echo "\n";
    foreach ($totals as $total) {
        echo $total['label'] . ": " . $total['value'] . "\n";
    }
}

echo "\n";
do_action('woocommerce_email_order_details', $order, $sent_to_admin, true, $email);
do_action('woocommerce_email_order_meta', $order, $sent_to_admin, true, $email);
do_action('woocommerce_email_customer_details', $order, $sent_to_admin, true, $email);

echo "\nYOU CAN MANAGE YOUR SUBSCRIPTION ANYTIME THROUGH YOUR CUSTOMER PORTAL:\n";
echo "* Update your box: Change products, qualities or swap your box for another.\n";
echo "* Change your delivery dates: Get your products more often by updating your delivery schedule.\n";
echo "* Edit your details: Update your payment methods, or personal details.\n\n";

echo "GETTING STARTED WITH BOCS\n";
echo "* Download the Bocs mobile app to manage your subscription\n";
echo "* Set up your profile to get personalized recommendations\n";
echo "* Explore the available features and services in your subscription\n\n";

echo "Visit your account to manage your subscription: " . esc_url(wc_get_account_endpoint_url('my-subscriptions')) . "\n\n";

if ($additional_content) {
    echo esc_html(wp_strip_all_tags(wptexturize($additional_content)));
    echo "\n\n";
}

echo "If you have any questions about your new subscription, please contact our customer support team.\n\n";

echo "Thank you for your continued support with Bocs!\n";
?>