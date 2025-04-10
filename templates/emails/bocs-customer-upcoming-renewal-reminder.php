<?php
/**
 * Upcoming renewal reminder email (Bocs specific variant)
 *
 * @package Bocs/Templates/Emails
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

// Ensure we have the required variables
if (!isset($email_heading)) {
    $email_heading = '';
}

if (!isset($email)) {
    $email = null;
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);
?>

<div style="padding: 0 12px; max-width: 100%;">
    <p style="margin: 0 0 16px;">Hi <?php echo esc_html($order->get_billing_address_1() ? $order->get_billing_first_name() : __('there', 'bocs-wordpress')); ?>,</p>
    
    <p style="margin: 0 0 16px;"><?php esc_html_e('This is a reminder that your subscription renewal payment will be automatically processed soon.', 'bocs-wordpress'); ?></p>
    
    <!-- Reminder notification box -->
    <div style="background-color: #fff8e1; border-left: 4px solid #ffa000; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px;">
        <p style="margin: 0 0 16px; color: #ff6b00; font-weight: 600;"><?php esc_html_e('Upcoming Renewal Reminder', 'bocs-wordpress'); ?></p>
        <p style="margin: 0 0 16px;"><?php 
            if (isset($renewal_date) && !empty($renewal_date)) {
                printf(
                    esc_html__('Your subscription renewal will be automatically processed on %s.', 'bocs-wordpress'),
                    '<strong>' . esc_html($renewal_date) . '</strong>'
                );
            } else {
                esc_html_e('Your subscription renewal will be automatically processed soon.', 'bocs-wordpress');
            }
        ?></p>
    </div>
    
    <!-- Payment method info, if applicable -->
    <?php 
    $payment_method_title = $order->get_payment_method_title();
    if (!empty($payment_method_title)) : 
    ?>
    <div style="background-color: #f8f9fa; padding: 15px 20px; margin-bottom: 25px; border-radius: 4px; border: 1px solid #e0e0e0;">
        <h3 style="font-size: 16px; color: #333333; margin-bottom: 10px; font-weight: 500;"><?php esc_html_e('Payment Method', 'bocs-wordpress'); ?></h3>
        <p style="margin: 0 0 16px;"><strong><?php echo esc_html($payment_method_title); ?></strong></p>
        
        <?php if (strpos(strtolower($payment_method_title), 'stripe') !== false) : ?>
        <p style="margin: 0 0 16px;"><?php esc_html_e('Your card will be automatically charged. No action is needed from you.', 'bocs-wordpress'); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Bocs App notice, if applicable -->
    <?php 
    if (function_exists('bocs_order_created_via_app') && bocs_order_created_via_app($order)) : 
    ?>
    <div style="background-color: #fff8e1; padding: 12px 15px; margin-bottom: 25px; border-radius: 4px; border: 1px dashed #ffa000;">
        <p style="margin: 0 0 16px;"><span style="color: #ff6b00; font-weight: 500;"><?php esc_html_e('This subscription was created through the Bocs App.', 'bocs-wordpress'); ?></span></p>
        <p style="margin: 0 0 16px;"><?php esc_html_e('You can manage your subscription directly through the Bocs mobile app.', 'bocs-wordpress'); ?></p>
    </div>
    <?php endif; ?>
</div>

<h2 style="color: #3C7B7C !important; display: block; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 18px; font-weight: bold; line-height: 130%; margin: 0 0 18px; text-align: left;">
    <?php 
        $order_date = $order->get_date_created();
        $formatted_date = $order_date ? $order_date->format(wc_date_format()) : date_i18n(wc_date_format());
        printf(esc_html__('[Order #%s] (%s)', 'bocs-wordpress'), $order->get_order_number(), $formatted_date); 
    ?>
</h2>

<?php
// Check if this is a "placeholder" order for upcoming renewals
$items = $order->get_items();
$order_for_display = $order;

// If this is a placeholder order with no items, find the original order
if (empty($items) && method_exists($order, 'get_meta')) {
    $bocs_subscription_id = $order->get_meta('__bocs_subscription_id');
    if (!empty($bocs_subscription_id)) {
        // Query for the most recent completed order with this subscription ID
        $args = array(
            'status' => array('completed', 'processing'),
            'limit' => 1,
            'meta_key' => '__bocs_subscription_id',
            'meta_value' => $bocs_subscription_id,
            'return' => 'ids',
        );
        
        $original_orders = wc_get_orders($args);
        
        if (!empty($original_orders)) {
            $original_order_id = $original_orders[0];
            $original_order = wc_get_order($original_order_id);
            
            if ($original_order && $original_order->get_items()) {
                $order_for_display = $original_order;
                $items = $original_order->get_items();
            }
        }
    }
}
?>

<!-- Order Items Table -->
<div style="margin-bottom: 40px;">
    <table class="td" cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #e5e5e5; border-collapse: collapse; color: #636363; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;">
        <thead>
            <tr>
                <th class="td" scope="col" style="text-align: left; color: #636363; border: 1px solid #e5e5e5; padding: 12px; font-weight: bold;"><?php esc_html_e('Product', 'woocommerce'); ?></th>
                <th class="td" scope="col" style="text-align: left; color: #636363; border: 1px solid #e5e5e5; padding: 12px; font-weight: bold;"><?php esc_html_e('Quantity', 'woocommerce'); ?></th>
                <th class="td" scope="col" style="text-align: left; color: #636363; border: 1px solid #e5e5e5; padding: 12px; font-weight: bold;"><?php esc_html_e('Price', 'woocommerce'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($items)) : ?>
                <?php foreach ($items as $item_id => $item) : 
                    $product = $item->get_product();
                    $sku = $product ? $product->get_sku() : '';
                    $name = $item->get_name();
                ?>
                <tr class="<?php echo esc_attr(apply_filters('woocommerce_order_item_class', 'order_item', $item, $order_for_display)); ?>">
                    <td class="td" style="text-align: left; border: 1px solid #e5e5e5; padding: 12px; color: #636363; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; word-wrap: break-word;">
                        <?php echo wp_kses_post($name); ?>
                        <?php if ($sku) : ?>
                            <small><?php echo esc_html__('SKU:', 'woocommerce') . ' ' . esc_html($sku); ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="td" style="text-align: left; border: 1px solid #e5e5e5; padding: 12px; color: #636363; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;">
                        <?php echo wp_kses_post($item->get_quantity()); ?>
                    </td>
                    <td class="td" style="text-align: left; border: 1px solid #e5e5e5; padding: 12px; color: #636363; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;">
                        <?php echo wp_kses_post($order_for_display->get_formatted_line_subtotal($item)); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" style="text-align: center; padding: 12px; color: #636363; border: 1px solid #e5e5e5;">
                        <?php esc_html_e('No items found in this order.', 'woocommerce'); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <?php
            $totals = $order_for_display->get_order_item_totals();
            if ($totals) :
                $i = 0;
                foreach ($totals as $total) :
                    $i++;
                    ?>
                    <tr>
                        <th class="td" scope="row" colspan="2" style="text-align: right; border: 1px solid #e5e5e5; padding: 12px; font-weight: bold;"><?php echo wp_kses_post($total['label']); ?></th>
                        <td class="td" style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;"><?php echo wp_kses_post($total['value']); ?></td>
                    </tr>
                    <?php
                endforeach;
            endif;
            ?>
        </tfoot>
    </table>
</div>

<?php
// Skip the customer details hooks since we're handling the order details manually
/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);
?>

<div style="padding: 0 12px; max-width: 100%;">
    <!-- Manage subscription button -->
    <div style="margin: 40px 0; text-align: center;">
        <?php
        $order_number = $order->get_order_number();
        $myaccount_url = wc_get_page_permalink('myaccount');
        $view_order_url = wc_get_endpoint_url('view-order', '', $myaccount_url);
        $manage_url = add_query_arg(array('view-order' => $order_number), $view_order_url);
        ?>
        <a href="<?php echo esc_url($manage_url); ?>" style="display: inline-block; background-color: #3C7B7C; color: #ffffff; font-size: 16px; font-weight: bold; line-height: 100%; text-decoration: none; padding: 12px 25px; border-radius: 4px;">
            <?php esc_html_e('Manage Subscription', 'bocs-wordpress'); ?>
        </a>
    </div>
    
    <?php if (isset($additional_content) && !empty($additional_content)) : ?>
        <div style="margin-bottom: 25px; padding: 0 5px;">
            <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
        </div>
    <?php endif; ?>
    
    <!-- Standard footer info -->
    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e5e5; color: #757575; font-size: 13px;">
        <p style="margin: 0 0 16px;"><?php esc_html_e('If you have any questions about your subscription, please contact our customer support team.', 'bocs-wordpress'); ?></p>
        <p style="margin: 0 0 16px;"><?php esc_html_e('Thank you for choosing Bocs!', 'bocs-wordpress'); ?></p>
    </div>
</div>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email); 