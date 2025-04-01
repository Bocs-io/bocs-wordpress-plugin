<?php
/**
 * Existing customer new subscription confirmation email (Bocs specific variant)
 *
 * @package Bocs/Templates/Emails
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);
?>

<div style="padding: 0 12px; max-width: 100%;">
    <p style="margin: 0 0 16px;">Hi <?php echo esc_html($order->get_billing_first_name()); ?>,</p>
    
    <p style="margin: 0 0 16px;"><?php esc_html_e('Thank you for your continued support! Your new Bocs subscription has been successfully set up.', 'bocs-wordpress'); ?></p>
    
    <!-- Subscription Confirmation box -->
    <div style="background-color: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px;">
        <p style="margin: 0 0 16px; color: #4caf50; font-weight: 600;"><?php esc_html_e('Additional Subscription Confirmed', 'bocs-wordpress'); ?></p>
        <p style="margin: 0 0 16px;"><?php esc_html_e('Your new Bocs subscription has been processed successfully. You now have access to all benefits of this additional subscription.', 'bocs-wordpress'); ?></p>
    </div>
    
    <!-- Loyal Customer box -->
    <div style="background-color: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px;">
        <p style="margin: 0 0 16px; color: #2196f3; font-weight: 600;"><?php esc_html_e('Valued Bocs Customer', 'bocs-wordpress'); ?></p>
        <p style="margin: 0 0 16px;"><?php esc_html_e('As a returning customer, we appreciate your continued trust. Thank you for choosing Bocs again for your needs.', 'bocs-wordpress'); ?></p>
    </div>
    
    <!-- Special Bocs App notice -->
    <div style="background-color: #fff8e1; padding: 12px 15px; margin-bottom: 25px; border-radius: 4px; border: 1px dashed #ffc107;">
        <p style="margin: 0 0 16px;"><span style="color: #ff6b00; font-weight: 500;"><?php esc_html_e('This subscription was created through the Bocs App.', 'bocs-wordpress'); ?></span></p>
        <p style="margin: 0 0 16px;"><?php esc_html_e('You can manage all your subscriptions and access services directly through the Bocs mobile app.', 'bocs-wordpress'); ?></p>
    </div>
    
    <h2 style="display: block; color: #333333; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 22px; font-weight: 500; line-height: 130%; margin: 30px 0 18px; text-align: left;"><?php esc_html_e('Subscription Details', 'bocs-wordpress'); ?></h2>
</div>

<h2 style="color: #3C7B7C !important; display: block; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 18px; font-weight: bold; line-height: 130%; margin: 0 0 18px; text-align: left;">
    <?php printf(esc_html__('[Order #%s] (%s)', 'bocs-wordpress'), $order->get_order_number(), date_i18n(wc_date_format(), strtotime($order->get_date_created()))); ?>
</h2>

<!-- Custom line items display -->
<div style="margin-bottom: 40px;">
    <table cellspacing="0" cellpadding="6" border="1" style="width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: #ffffff;">
        <thead>
            <tr>
                <th scope="col" style="text-align: left; padding: 12px; background-color: #f7f7f7; color: #636363; border: 1px solid #e5e5e5;"><?php esc_html_e('Product', 'bocs-wordpress'); ?></th>
                <th scope="col" style="text-align: left; padding: 12px; background-color: #f7f7f7; color: #636363; border: 1px solid #e5e5e5;"><?php esc_html_e('Quantity', 'bocs-wordpress'); ?></th>
                <th scope="col" style="text-align: right; padding: 12px; background-color: #f7f7f7; color: #636363; border: 1px solid #e5e5e5;"><?php esc_html_e('Price', 'bocs-wordpress'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($order->get_items() as $item_id => $item) :
                $product = $item->get_product();
                ?>
                <tr>
                    <td style="text-align: left; vertical-align: middle; padding: 12px; color: #636363; border: 1px solid #e5e5e5;">
                        <?php 
                        echo wp_kses_post($item->get_name());
                        if ($product && $product->get_sku()) {
                            echo ' <small>' . esc_html__('SKU:', 'bocs-wordpress') . ' ' . esc_html($product->get_sku()) . '</small>';
                        }
                        
                        // Show item meta data
                        wc_display_item_meta($item);
                        ?>
                    </td>
                    <td style="text-align: left; vertical-align: middle; padding: 12px; color: #636363; border: 1px solid #e5e5e5;">
                        <?php echo esc_html($item->get_quantity()); ?>
                    </td>
                    <td style="text-align: right; vertical-align: middle; padding: 12px; color: #636363; border: 1px solid #e5e5e5;">
                        <?php echo wp_kses_post($order->get_formatted_line_subtotal($item)); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <?php
            $totals = $order->get_order_item_totals();
            if ($totals) :
                foreach ($totals as $total) :
                    ?>
                    <tr>
                        <th scope="row" colspan="2" style="text-align: right; padding: 10px; background-color: #f8f8f8; border: 1px solid #e5e5e5;"><?php echo wp_kses_post($total['label']); ?></th>
                        <td style="text-align: right; padding: 10px; border: 1px solid #e5e5e5;"><?php echo wp_kses_post($total['value']); ?></td>
                    </tr>
                <?php
                endforeach;
            endif;
            ?>
        </tfoot>
    </table>
</div>

<?php
/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 */
do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

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
    <!-- Managing Your Subscriptions section -->
    <div style="margin: 30px 0; padding: 20px; background-color: #f9f9f9; border-radius: 4px;">
        <h3 style="color: #333333; font-size: 18px; margin-top: 0;"><?php esc_html_e('Managing Your Bocs Subscriptions', 'bocs-wordpress'); ?></h3>
        <ul style="padding-left: 20px; margin-bottom: 0;">
            <li style="margin-bottom: 10px;"><?php esc_html_e('Use the Bocs mobile app to manage all your subscriptions in one place', 'bocs-wordpress'); ?></li>
            <li style="margin-bottom: 10px;"><?php esc_html_e('View your subscription history and upcoming renewals', 'bocs-wordpress'); ?></li>
            <li style="margin-bottom: 10px;"><?php esc_html_e('Easily access all services across your subscriptions', 'bocs-wordpress'); ?></li>
        </ul>
    </div>
    
    <!-- Bocs App buttons -->
    <div style="margin: 40px 0; text-align: center;">
        <a href="https://app.bocs.io" style="display: inline-block; background-color: #ff6b00; color: #ffffff; font-size: 16px; font-weight: bold; line-height: 100%; text-decoration: none; padding: 12px 25px; border-radius: 4px; margin: 0 10px;">
            <?php esc_html_e('Open Bocs App', 'bocs-wordpress'); ?>
        </a>
        <a href="<?php echo esc_url($order->get_view_order_url()); ?>" style="display: inline-block; background-color: #3C7B7C; color: #ffffff; font-size: 16px; font-weight: bold; line-height: 100%; text-decoration: none; padding: 12px 25px; border-radius: 4px; margin: 0 10px;">
            <?php esc_html_e('View Order', 'bocs-wordpress'); ?>
        </a>
    </div>
    
    <?php if ($additional_content) : ?>
        <div style="margin-bottom: 25px; padding: 0 5px;">
            <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
        </div>
    <?php endif; ?>
    
    <!-- Standard footer info -->
    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e5e5; color: #757575; font-size: 13px;">
        <p style="margin: 0 0 16px;"><?php esc_html_e('If you have any questions about your new subscription, please contact our customer support team.', 'bocs-wordpress'); ?></p>
        <p style="margin: 0 0 16px;"><?php esc_html_e('Thank you for your continued support with Bocs!', 'bocs-wordpress'); ?></p>
    </div>
</div>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);
?> 