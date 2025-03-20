<?php
/**
 * Bocs Edit Subscription Details Template
 *
 * @package    Bocs
 * @subpackage Bocs/views
 * @since      1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get subscription ID from the query vars
global $wp;
$subscription_id = isset($wp->query_vars['bocs-edit-details']) ? sanitize_text_field($wp->query_vars['bocs-edit-details']) : '';

// Get plugin options
$options = get_option('bocs_plugin_options');
$helper = new Bocs_Helper();

// Initialize the API request to get subscription details
$request_url = BOCS_API_URL . 'subscriptions/' . $subscription_id;

// Get subscription data
$subscription_response = $helper->curl_request(
    $request_url,
    'GET',
    [],
    [
        'Organization' => $options['bocs_headers']['organization'] ?? '',
        'Store' => $options['bocs_headers']['store'] ?? '',
        'Authorization' => $options['bocs_headers']['authorization'] ?? '',
        'Content-Type' => 'application/json'
    ]
);

$subscription = isset($subscription_response['data']) ? $subscription_response['data'] : null;

if (!$subscription) {
    echo '<div class="woocommerce-error">' . esc_html__('Unable to retrieve subscription details.', 'bocs-wordpress') . '</div>';
    return;
}

// Format subscription status for display
$status = strtolower($subscription['subscriptionStatus'] ?? '');
$status_class = 'status-' . $status;
$status_label = ucfirst($status);

// Format dates
$start_date = '';
if (!empty($subscription['startDateGmt'])) {
    $start_date = date_i18n(get_option('date_format'), strtotime($subscription['startDateGmt']));
}

$next_payment_date = '';
if (!empty($subscription['nextPaymentDateGmt'])) {
    $next_payment_date = date_i18n(get_option('date_format'), strtotime($subscription['nextPaymentDateGmt']));
}

// Script and style includes
wp_enqueue_script('jquery');
wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');

// Get the return URL for the main subscriptions page
$return_url = wc_get_account_endpoint_url('bocs-subscriptions');
?>

<div class="bocs-edit-details-container">
    <a href="<?php echo esc_url($return_url); ?>" class="bocs-back-link">
        <?php esc_html_e('← Back to My Subscriptions', 'bocs-wordpress'); ?>
    </a>
    
    <h2>
        <?php 
        echo sprintf(
            esc_html__('Edit Subscription #%s', 'bocs-wordpress'), 
            esc_html($subscription['externalSourceParentOrderId'] ?? $subscription_id)
        ); 
        ?>
    </h2>

    <div class="bocs-subscription-overview">
        <div class="subscription-status <?php echo esc_attr($status_class); ?>">
            <?php echo esc_html($status_label); ?>
        </div>
        
        <div class="subscription-dates">
            <?php if ($start_date): ?>
            <div class="date-item">
                <span class="date-label"><?php esc_html_e('Start Date:', 'bocs-wordpress'); ?></span>
                <span class="date-value"><?php echo esc_html($start_date); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($next_payment_date): ?>
            <div class="date-item">
                <span class="date-label"><?php esc_html_e('Next Payment:', 'bocs-wordpress'); ?></span>
                <span class="date-value"><?php echo esc_html($next_payment_date); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bocs-edit-sections">
        <!-- Frequency Section -->
        <div class="edit-section frequency-section">
            <div class="section-header">
                <h3><?php esc_html_e('Subscription Frequency', 'bocs-wordpress'); ?></h3>
            </div>
            
            <div class="section-content">
                <div class="current-frequency-display">
                    <span class="label"><?php esc_html_e('Current Frequency:', 'bocs-wordpress'); ?></span>
                    <span class="value current-frequency">
                    <?php 
                        $frequency = isset($subscription['frequency']['frequency']) ? $subscription['frequency']['frequency'] : 1;
                        $period = isset($subscription['frequency']['timeUnit']) ? $subscription['frequency']['timeUnit'] : '';
                        if ($frequency > 1) {
                            $period = rtrim($period, 's') . 's';
                        } else {
                            $period = rtrim($period, 's');
                        }
                        echo esc_html(sprintf(__('Every %d %s', 'bocs-wordpress'), $frequency, $period));
                    ?>
                    </span>
                    <button class="edit-button edit-frequency"><?php esc_html_e('Edit', 'bocs-wordpress'); ?></button>
                </div>
                
                <div class="frequency-editor" style="display: none;">
                    <div class="frequency-options-loading" style="display: none;">
                        <div class="loading-spinner"></div>
                        <p><?php esc_html_e('Loading frequency options...', 'bocs-wordpress'); ?></p>
                    </div>
                    
                    <div class="frequency-options-content"></div>
                    
                    <div class="button-group">
                        <button type="button" class="button cancel-frequency"><?php esc_html_e('Cancel', 'bocs-wordpress'); ?></button>
                        <button type="button" class="button alt save-frequency"><?php esc_html_e('Save Changes', 'bocs-wordpress'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Shipping Address Section -->
        <div class="edit-section shipping-section">
            <div class="section-header">
                <h3><?php esc_html_e('Shipping Address', 'bocs-wordpress'); ?></h3>
            </div>
            
            <div class="section-content">
                <div class="current-address-display">
                    <?php if (!empty($subscription['shipping'])): ?>
                        <address>
                            <?php
                            $shipping = $subscription['shipping'];
                            echo esc_html(implode(', ', array_filter([
                                ($shipping['firstName'] ?? '') . ' ' . ($shipping['lastName'] ?? ''),
                                $shipping['address1'] ?? '',
                                $shipping['address2'] ?? '',
                                $shipping['city'] ?? '',
                                $shipping['state'] ?? '',
                                $shipping['postcode'] ?? '',
                                $shipping['country'] ?? ''
                            ])));
                            ?>
                        </address>
                    <?php else: ?>
                        <span><?php esc_html_e('No shipping address provided', 'bocs-wordpress'); ?></span>
                    <?php endif; ?>
                    <button class="edit-button edit-shipping"><?php esc_html_e('Edit', 'bocs-wordpress'); ?></button>
                </div>
                
                <div class="address-editor shipping-editor" style="display: none;">
                    <form class="edit-address-form" data-type="shipping">
                        <div class="form-row">
                            <label for="shipping_first_name"><?php esc_html_e('First Name', 'bocs-wordpress'); ?></label>
                            <input type="text" id="shipping_first_name" name="firstName" value="<?php echo esc_attr($subscription['shipping']['firstName'] ?? ''); ?>" required>
                        </div>
                        <div class="form-row">
                            <label for="shipping_last_name"><?php esc_html_e('Last Name', 'bocs-wordpress'); ?></label>
                            <input type="text" id="shipping_last_name" name="lastName" value="<?php echo esc_attr($subscription['shipping']['lastName'] ?? ''); ?>" required>
                        </div>
                        <div class="form-row full-width">
                            <label for="shipping_address1"><?php esc_html_e('Address Line 1', 'bocs-wordpress'); ?></label>
                            <input type="text" id="shipping_address1" name="address1" value="<?php echo esc_attr($subscription['shipping']['address1'] ?? ''); ?>" required>
                        </div>
                        <div class="form-row full-width">
                            <label for="shipping_address2"><?php esc_html_e('Address Line 2', 'bocs-wordpress'); ?></label>
                            <input type="text" id="shipping_address2" name="address2" value="<?php echo esc_attr($subscription['shipping']['address2'] ?? ''); ?>">
                        </div>
                        <div class="form-row">
                            <label for="shipping_city"><?php esc_html_e('City', 'bocs-wordpress'); ?></label>
                            <input type="text" id="shipping_city" name="city" value="<?php echo esc_attr($subscription['shipping']['city'] ?? ''); ?>" required>
                        </div>
                        <div class="form-row">
                            <label for="shipping_state"><?php esc_html_e('State', 'bocs-wordpress'); ?></label>
                            <input type="text" id="shipping_state" name="state" value="<?php echo esc_attr($subscription['shipping']['state'] ?? ''); ?>" required>
                        </div>
                        <div class="form-row">
                            <label for="shipping_postcode"><?php esc_html_e('Postcode', 'bocs-wordpress'); ?></label>
                            <input type="text" id="shipping_postcode" name="postcode" value="<?php echo esc_attr($subscription['shipping']['postcode'] ?? ''); ?>" required>
                        </div>
                        <div class="form-row">
                            <label for="shipping_country"><?php esc_html_e('Country', 'bocs-wordpress'); ?></label>
                            <input type="text" id="shipping_country" name="country" value="<?php echo esc_attr($subscription['shipping']['country'] ?? ''); ?>" required>
                        </div>
                        <div class="button-group">
                            <button type="button" class="button cancel-address-edit"><?php esc_html_e('Cancel', 'bocs-wordpress'); ?></button>
                            <button type="submit" class="button alt save-address"><?php esc_html_e('Save Address', 'bocs-wordpress'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Billing Address Section -->
        <div class="edit-section billing-section">
            <div class="section-header">
                <h3><?php esc_html_e('Billing Address', 'bocs-wordpress'); ?></h3>
            </div>
            
            <div class="section-content">
                <div class="current-address-display">
                    <?php if (!empty($subscription['billing'])): ?>
                        <address>
                            <?php
                            $billing = $subscription['billing'];
                            echo esc_html(implode(', ', array_filter([
                                ($billing['firstName'] ?? '') . ' ' . ($billing['lastName'] ?? ''),
                                $billing['address1'] ?? '',
                                $billing['address2'] ?? '',
                                $billing['city'] ?? '',
                                $billing['state'] ?? '',
                                $billing['postcode'] ?? '',
                                $billing['country'] ?? ''
                            ])));
                            ?>
                        </address>
                    <?php else: ?>
                        <span><?php esc_html_e('No billing address provided', 'bocs-wordpress'); ?></span>
                    <?php endif; ?>
                    <button class="edit-button edit-billing"><?php esc_html_e('Edit', 'bocs-wordpress'); ?></button>
                </div>
                
                <div class="address-editor billing-editor" style="display: none;">
                    <form class="edit-address-form" data-type="billing">
                        <div class="form-row">
                            <label for="billing_first_name"><?php esc_html_e('First Name', 'bocs-wordpress'); ?></label>
                            <input type="text" id="billing_first_name" name="firstName" value="<?php echo esc_attr($subscription['billing']['firstName'] ?? ''); ?>" required>
                        </div>
                        <div class="form-row">
                            <label for="billing_last_name"><?php esc_html_e('Last Name', 'bocs-wordpress'); ?></label>
                            <input type="text" id="billing_last_name" name="lastName" value="<?php echo esc_attr($subscription['billing']['lastName'] ?? ''); ?>" required>
                        </div>
                        <div class="form-row full-width">
                            <label for="billing_address1"><?php esc_html_e('Address Line 1', 'bocs-wordpress'); ?></label>
                            <input type="text" id="billing_address1" name="address1" value="<?php echo esc_attr($subscription['billing']['address1'] ?? ''); ?>" required>
                        </div>
                        <div class="form-row full-width">
                            <label for="billing_address2"><?php esc_html_e('Address Line 2', 'bocs-wordpress'); ?></label>
                            <input type="text" id="billing_address2" name="address2" value="<?php echo esc_attr($subscription['billing']['address2'] ?? ''); ?>">
                        </div>
                        <div class="form-row">
                            <label for="billing_city"><?php esc_html_e('City', 'bocs-wordpress'); ?></label>
                            <input type="text" id="billing_city" name="city" value="<?php echo esc_attr($subscription['billing']['city'] ?? ''); ?>" required>
                        </div>
                        <div class="form-row">
                            <label for="billing_state"><?php esc_html_e('State', 'bocs-wordpress'); ?></label>
                            <input type="text" id="billing_state" name="state" value="<?php echo esc_attr($subscription['billing']['state'] ?? ''); ?>" required>
                        </div>
                        <div class="form-row">
                            <label for="billing_postcode"><?php esc_html_e('Postcode', 'bocs-wordpress'); ?></label>
                            <input type="text" id="billing_postcode" name="postcode" value="<?php echo esc_attr($subscription['billing']['postcode'] ?? ''); ?>" required>
                        </div>
                        <div class="form-row">
                            <label for="billing_country"><?php esc_html_e('Country', 'bocs-wordpress'); ?></label>
                            <input type="text" id="billing_country" name="country" value="<?php echo esc_attr($subscription['billing']['country'] ?? ''); ?>" required>
                        </div>
                        <div class="button-group">
                            <button type="button" class="button cancel-address-edit"><?php esc_html_e('Cancel', 'bocs-wordpress'); ?></button>
                            <button type="submit" class="button alt save-address"><?php esc_html_e('Save Address', 'bocs-wordpress'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Line Items Section -->
        <div class="edit-section line-items-section">
            <div class="section-header">
                <h3><?php esc_html_e('Subscription Items', 'bocs-wordpress'); ?></h3>
            </div>
            
            <div class="section-content">
                <div class="line-items-container">
                    <?php if (!empty($subscription['lineItems']) && is_array($subscription['lineItems'])): ?>
                        <table class="line-items-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Product', 'bocs-wordpress'); ?></th>
                                    <th><?php esc_html_e('Quantity', 'bocs-wordpress'); ?></th>
                                    <th><?php esc_html_e('Price', 'bocs-wordpress'); ?></th>
                                    <th><?php esc_html_e('Total', 'bocs-wordpress'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subscription['lineItems'] as $item): ?>
                                    <tr data-product-id="<?php echo esc_attr($item['productId'] ?? ''); ?>">
                                        <td class="product-name">
                                            <?php echo esc_html($item['name'] ?? __('Product', 'bocs-wordpress')); ?>
                                            <?php if (!empty($item['metadata']) && is_array($item['metadata'])): ?>
                                                <div class="product-meta">
                                                    <?php foreach ($item['metadata'] as $meta): ?>
                                                        <div class="meta-item">
                                                            <span class="meta-key"><?php echo esc_html($meta['key'] ?? ''); ?>:</span>
                                                            <span class="meta-value"><?php echo esc_html($meta['value'] ?? ''); ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="product-quantity"><?php echo esc_html($item['quantity'] ?? 1); ?></td>
                                        <td class="product-price">
                                            <?php 
                                            if (!empty($item['price'])) {
                                                if (function_exists('wc_price')) {
                                                    $price = wc_price($item['price'], array('currency' => $subscription['currency'] ?? 'USD'));
                                                    echo wp_kses_post($price);
                                                } else {
                                                    echo esc_html(($subscription['currencySymbol'] ?? '$') . number_format($item['price'], 2));
                                                }
                                            }
                                            ?>
                                        </td>
                                        <td class="product-total">
                                            <?php 
                                            if (!empty($item['price'])) {
                                                // Calculate total as quantity * price
                                                $quantity = !empty($item['quantity']) ? $item['quantity'] : 1;
                                                $itemTotal = $item['price'] * $quantity;
                                                
                                                if (function_exists('wc_price')) {
                                                    $total = wc_price($itemTotal, array('currency' => $subscription['currency'] ?? 'USD'));
                                                    echo wp_kses_post($total);
                                                } else {
                                                    echo esc_html(($subscription['currencySymbol'] ?? '$') . number_format($itemTotal, 2));
                                                }
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <?php if (!empty($subscription['total'])): ?>
                            <tfoot>
                                <?php if (!empty($subscription['subtotal'])): ?>
                                <tr class="subtotal-row">
                                    <th colspan="3"><?php esc_html_e('Subtotal', 'bocs-wordpress'); ?></th>
                                    <td>
                                        <?php 
                                        if (function_exists('wc_price')) {
                                            $subtotal = wc_price($subscription['subtotal'], array('currency' => $subscription['currency'] ?? 'USD'));
                                            echo wp_kses_post($subtotal);
                                        } else {
                                            echo esc_html(($subscription['currencySymbol'] ?? '$') . number_format($subscription['subtotal'], 2));
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if (!empty($subscription['couponLines']) && is_array($subscription['couponLines'])): ?>
                                <?php foreach ($subscription['couponLines'] as $coupon): ?>
                                <tr class="discount-row">
                                    <th colspan="3">
                                        <?php 
                                        if (!empty($coupon['code'])) {
                                            echo sprintf(esc_html__('Discount (%s)', 'bocs-wordpress'), esc_html($coupon['code']));
                                        } else {
                                            esc_html_e('Discount', 'bocs-wordpress');
                                        }
                                        ?>
                                    </th>
                                    <td>
                                        <?php 
                                        if (!empty($coupon['discount'])) {
                                            if (function_exists('wc_price')) {
                                                $discount = wc_price($coupon['discount'] * -1, array('currency' => $subscription['currency'] ?? 'USD'));
                                                echo wp_kses_post($discount);
                                            } else {
                                                echo esc_html('-' . ($subscription['currencySymbol'] ?? '$') . number_format($coupon['discount'], 2));
                                            }
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <?php if (!empty($subscription['shipping']) && !empty($subscription['shippingTotal'])): ?>
                                <tr class="shipping-row">
                                    <th colspan="3"><?php esc_html_e('Shipping', 'bocs-wordpress'); ?></th>
                                    <td>
                                        <?php 
                                        if (function_exists('wc_price')) {
                                            $shipping = wc_price($subscription['shippingTotal'], array('currency' => $subscription['currency'] ?? 'USD'));
                                            echo wp_kses_post($shipping);
                                        } else {
                                            echo esc_html(($subscription['currencySymbol'] ?? '$') . number_format($subscription['shippingTotal'], 2));
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if (!empty($subscription['taxTotal'])): ?>
                                <tr class="tax-row">
                                    <th colspan="3"><?php esc_html_e('Tax', 'bocs-wordpress'); ?></th>
                                    <td>
                                        <?php 
                                        if (function_exists('wc_price')) {
                                            $tax = wc_price($subscription['taxTotal'], array('currency' => $subscription['currency'] ?? 'USD'));
                                            echo wp_kses_post($tax);
                                        } else {
                                            echo esc_html(($subscription['currencySymbol'] ?? '$') . number_format($subscription['taxTotal'], 2));
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <tr class="total-row">
                                    <th colspan="3"><?php esc_html_e('Subscription Total', 'bocs-wordpress'); ?></th>
                                    <td>
                                        <?php 
                                        // Calculate the total from line items, shipping, tax, and discounts
                                        $calculated_total = 0;
                                        
                                        // Add up line item totals
                                        if (!empty($subscription['lineItems']) && is_array($subscription['lineItems'])) {
                                            foreach ($subscription['lineItems'] as $item) {
                                                if (!empty($item['price'])) {
                                                    $quantity = !empty($item['quantity']) ? $item['quantity'] : 1;
                                                    $calculated_total += $item['price'] * $quantity;
                                                }
                                            }
                                        }
                                        
                                        // Add shipping if present
                                        if (!empty($subscription['shippingTotal'])) {
                                            $calculated_total += $subscription['shippingTotal'];
                                        }
                                        
                                        // Add tax if present
                                        if (!empty($subscription['taxTotal'])) {
                                            $calculated_total += $subscription['taxTotal'];
                                        }
                                        
                                        // Subtract discounts if present
                                        if (!empty($subscription['couponLines']) && is_array($subscription['couponLines'])) {
                                            foreach ($subscription['couponLines'] as $coupon) {
                                                if (!empty($coupon['discount'])) {
                                                    $calculated_total -= $coupon['discount'];
                                                }
                                            }
                                        }
                                        
                                        // Use the calculated total or fall back to API total if needed
                                        $display_total = $calculated_total > 0 ? $calculated_total : ($subscription['total'] ?? 0);
                                        
                                        if (function_exists('wc_price')) {
                                            $total = wc_price($display_total, array('currency' => $subscription['currency'] ?? 'USD'));
                                            echo wp_kses_post($total);
                                        } else {
                                            echo esc_html(($subscription['currencySymbol'] ?? '$') . number_format($display_total, 2));
                                        }
                                        ?>
                                    </td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    <?php else: ?>
                        <p><?php esc_html_e('No items found for this subscription.', 'bocs-wordpress'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Payment Method Section -->
        <div class="edit-section payment-section">
            <div class="section-header">
                <h3><?php esc_html_e('Payment Method', 'bocs-wordpress'); ?></h3>
            </div>
            
            <div class="section-content">
                <div class="current-payment-display">
                    <span class="label"><?php esc_html_e('Current Payment Method:', 'bocs-wordpress'); ?></span>
                    <span class="value">
                        <?php echo esc_html($subscription['paymentMethodTitle'] ?? __('Unknown', 'bocs-wordpress')); ?>
                    </span>
                    <button class="edit-button edit-payment" 
                            data-subscription-id="<?php echo esc_attr($subscription_id); ?>">
                        <?php esc_html_e('Edit', 'bocs-wordpress'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="bocs-notification" style="display: none;">
    <div class="notification-content">
        <span class="message"></span>
        <div class="loading-spinner" style="display: none;"></div>
    </div>
</div>

<style>
.bocs-edit-details-container {
    background: #fff;
    padding: 2em;
    margin-bottom: 2em;
}

.bocs-back-link {
    display: inline-block;
    margin-bottom: 1em;
    text-decoration: none;
    color: #3c7b7c;
}

.bocs-back-link:hover {
    text-decoration: underline;
}

.bocs-subscription-overview {
    display: flex;
    justify-content: space-between;
    margin-bottom: 2em;
    padding-bottom: 1em;
    border-bottom: 1px solid #eee;
}

.subscription-status {
    background: #f0f0f0;
    padding: 0.5em 1em;
    border-radius: 4px;
    font-weight: 600;
    display: inline-block;
}

.status-active {
    background: #c6e1c6;
    color: #5b8a5b;
}

.status-paused,
.status-pending {
    background: #f8dda7;
    color: #94660c;
}

.status-cancelled,
.status-expired {
    background: #eba3a3;
    color: #761919;
}

.subscription-dates {
    display: flex;
    gap: 2em;
}

.date-item {
    display: flex;
    flex-direction: column;
}

.date-label {
    color: #6d6d6d;
    font-size: 0.9em;
}

.date-value {
    font-weight: 600;
}

.bocs-edit-sections {
    display: flex;
    flex-direction: column;
    gap: 2em;
}

.edit-section {
    background: #f9f9f9;
    border-radius: 4px;
    padding: 1.5em;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1em;
    border-bottom: 1px solid #eee;
    padding-bottom: 0.5em;
}

.section-header h3 {
    margin: 0;
    font-size: 1.2em;
}

.section-content {
    position: relative;
}

.current-frequency-display,
.current-address-display,
.current-payment-display {
    display: flex;
    align-items: center;
    gap: 1em;
}

.current-address-display {
    align-items: flex-start;
}

.current-address-display address {
    flex: 1;
    font-style: normal;
    margin: 0;
}

.edit-button {
    background: none;
    border: none;
    color: #3c7b7c;
    cursor: pointer;
    text-decoration: underline;
    padding: 0;
}

.edit-button:hover {
    color: #2a5758;
}

.address-editor,
.frequency-editor {
    margin-top: 1em;
    padding: 1em;
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.edit-address-form {
    display: flex;
    flex-wrap: wrap;
    gap: 1em;
}

.form-row {
    flex: 1 0 calc(50% - 0.5em);
    display: flex;
    flex-direction: column;
}

.form-row.full-width {
    flex: 1 0 100%;
}

.form-row label {
    margin-bottom: 0.5em;
    font-size: 0.9em;
    color: #555;
}

.form-row input {
    padding: 0.7em;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.button-group {
    display: flex;
    justify-content: flex-end;
    gap: 1em;
    margin-top: 1em;
    width: 100%;
}

.loading-spinner {
    display: inline-block;
    width: 1.5em;
    height: 1.5em;
    border: 2px solid rgba(0, 0, 0, 0.1);
    border-radius: 50%;
    border-top-color: #3c7b7c;
    animation: spin 1s ease-in-out infinite;
    vertical-align: middle;
    margin-right: 0.5em;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.frequency-options-content {
    display: flex;
    flex-direction: column;
    gap: 0.5em;
}

.frequency-option {
    display: flex;
    align-items: center;
    padding: 0.5em;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
}

.frequency-option:hover {
    background: #f5f5f5;
}

.frequency-option input {
    margin-right: 1em;
}

#bocs-notification {
    position: fixed;
    top: 30px;
    right: 30px;
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.2);
    padding: 1em 1.5em;
    z-index: 9999;
    display: none;
}

#bocs-notification.success {
    border-left: 4px solid #46b450;
}

#bocs-notification.error {
    border-left: 4px solid #dc3232;
}

#bocs-notification.loading {
    border-left: 4px solid #3c7b7c;
}

.notification-content {
    display: flex;
    align-items: center;
}

@media (max-width: 768px) {
    .bocs-subscription-overview {
        flex-direction: column;
        gap: 1em;
    }
    
    .subscription-dates {
        flex-direction: column;
        gap: 0.5em;
    }
    
    .current-frequency-display,
    .current-address-display,
    .current-payment-display {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .edit-address-form {
        flex-direction: column;
    }
    
    .form-row {
        flex: 1 0 100%;
    }
}

/* Line Items Section Styles */
.line-items-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1em;
}

.line-items-table th,
.line-items-table td {
    padding: 0.75em;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.line-items-table th {
    background-color: #f5f5f5;
    font-weight: 600;
}

.line-items-table tbody tr:hover {
    background-color: #f9f9f9;
}

.product-name {
    width: 40%;
}

.product-quantity,
.product-price,
.product-total {
    width: 20%;
    text-align: right;
}

.product-meta {
    font-size: 0.85em;
    color: #6d6d6d;
    margin-top: 0.5em;
}

.meta-item {
    margin-bottom: 0.25em;
}

.meta-key {
    font-weight: 600;
    margin-right: 0.5em;
}

.line-items-table tfoot {
    font-weight: 600;
}

.line-items-table tfoot td {
    text-align: right;
}

/* Styling for different totals rows */
.line-items-table .subtotal-row {
    border-top: 1px solid #e5e5e5;
}

.line-items-table .discount-row td {
    color: #4caf50;
}

.line-items-table .total-row {
    font-size: 1.1em;
    font-weight: 700;
    border-top: 2px solid #e5e5e5;
}

.line-items-table .total-row th,
.line-items-table .total-row td {
    padding-top: 1em;
}

/* Preview price styles */
.preview-price {
    background-color: #f9f9e0;
    transition: background-color 0.3s ease;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    'use strict';
    
    const subscriptionId = '<?php echo esc_js($subscription_id); ?>';
    
    // Helper functions
    const helpers = {
        showNotification: function(message, type = 'info') {
            const notification = $('#bocs-notification');
            
            // Reset
            notification.removeClass('success error loading');
            notification.find('.loading-spinner').hide();
            
            // Set new state
            notification.addClass(type);
            notification.find('.message').text(message);
            
            if (type === 'loading') {
                notification.find('.loading-spinner').show();
            }
            
            // Show notification
            notification.fadeIn();
            
            // Auto hide for success/error
            if (type !== 'loading') {
                setTimeout(() => {
                    notification.fadeOut();
                }, 3000);
            }
        },
        
        hideNotification: function() {
            $('#bocs-notification').fadeOut();
        },
        
        loadFrequencyOptions: async function(bocsId, currentFrequency) {
            if (!bocsId) {
                console.error('No BOCS ID provided for frequency options');
                contentElements.html('<p class="error">Missing BOCS ID. Unable to load frequency options.</p>');
                return;
            }
            
            const loadingElements = $('.frequency-options-loading');
            const contentElements = $('.frequency-options-content');
            
            try {
                loadingElements.show();
                contentElements.empty();
                
                let apiEndpoint = `<?php echo BOCS_API_URL; ?>bocs/${bocsId}`;
                console.log('Fetching frequencies from:', apiEndpoint);
                
                try {
                    // Try fetching with BOCS ID first
                    const response = await $.ajax({
                        url: apiEndpoint,
                        method: 'GET',
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                            xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                            xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                        }
                    });
                    
                    console.log('BOCS API response:', response);
                    
                    if (!response.data) {
                        console.error('Invalid API response - no data field:', response);
                        throw new Error('Invalid API response structure - no data field');
                    }
                    
                    // Extract frequencies from response
                    let frequencies = [];
                    
                    // Check if data contains frequencies directly
                    if (response.data.frequencies && Array.isArray(response.data.frequencies)) {
                        console.log('Found frequencies in response.data.frequencies');
                        frequencies = response.data.frequencies;
                    }
                    // Check if data contains priceAdjustment with adjustments
                    else if (response.data.priceAdjustment && 
                             response.data.priceAdjustment.adjustments && 
                             Array.isArray(response.data.priceAdjustment.adjustments)) {
                        console.log('Found frequencies in response.data.priceAdjustment.adjustments');
                        
                        frequencies = response.data.priceAdjustment.adjustments.map(adj => {
                            // Normalize timeUnit to lowercase
                            const timeUnit = adj.timeUnit ? adj.timeUnit.toLowerCase() : 'day';
                            
                            // Ensure discount is a valid number
                            let discount = 0;
                            if (adj.discount !== undefined && adj.discount !== null) {
                                discount = parseFloat(adj.discount);
                                if (isNaN(discount)) discount = 0;
                            }
                            
                            return {
                                id: adj.id,
                                frequency: adj.frequency,
                                timeUnit: timeUnit,
                                discount: discount,
                                discountType: adj.discountType ? adj.discountType.toLowerCase() : 'percent'
                            };
                        });
                    }
                    // No frequencies found
                    else {
                        console.error('No frequencies found in BOCS response');
                        throw new Error('No frequencies found in API response');
                    }
                    
                    if (!frequencies.length) {
                        throw new Error('No valid frequency options available');
                    }
                    
                    // Generate HTML for frequency options
                    const frequencyOptionsHtml = frequencies.map(freq => {
                        const freqName = `Every ${freq.frequency} ${freq.frequency > 1 ? freq.timeUnit : freq.timeUnit.replace(/s$/, '')}`;
                        
                        // Handle both discount types
                        const discountType = freq.discountType ? freq.discountType.toLowerCase() : 'percent';
                        let discountText = '';
                        
                        if (freq.discount > 0) {
                            if (discountType === 'percent' || discountType === 'percentage') {
                                discountText = ` (${freq.discount}% off)`;
                            } else {
                                // For fixed/dollar discount
                                discountText = ` ($${freq.discount} off)`;
                            }
                        }
                        
                        const isChecked = currentFrequency && currentFrequency.id === freq.id;
                        
                        return `
                            <label class="frequency-option">
                                <input type="radio" name="frequency" 
                                    value="${freq.id}" 
                                    data-name="${freqName}${discountText}"
                                    data-discount="${freq.discount}"
                                    data-discount-type="${discountType}"
                                    data-time-unit="${freq.timeUnit}"
                                    data-frequency="${freq.frequency}"
                                    ${isChecked ? 'checked' : ''}>
                                <span class="radio-label">${freqName}${discountText}</span>
                            </label>
                        `;
                    }).join('');
                    
                    contentElements.html(frequencyOptionsHtml);
                    
                    // If no frequency is checked, check the first one
                    if (contentElements.find('input:checked').length === 0) {
                        contentElements.find('input:first').prop('checked', true);
                    }
                } catch (bocsError) {
                    console.error('Error fetching with BOCS ID, falling back to subscription frequencies:', bocsError);
                    
                    // Fallback - try to get frequencies directly from subscription data
                    <?php if (!empty($subscription['availableFrequencies']) && is_array($subscription['availableFrequencies'])): ?>
                    // Use available frequencies from subscription data
                    const availableFrequencies = <?php echo json_encode($subscription['availableFrequencies'] ?? []); ?>;
                    
                    if (availableFrequencies && availableFrequencies.length > 0) {
                        const frequencyOptionsHtml = availableFrequencies.map(freq => {
                            const freqName = `Every ${freq.frequency} ${freq.frequency > 1 ? freq.timeUnit : freq.timeUnit.replace(/s$/, '')}`;
                            
                            // Handle both discount types
                            const discountType = freq.discountType ? freq.discountType.toLowerCase() : 'percent';
                            let discountText = '';
                            
                            if (freq.discount > 0) {
                                if (discountType === 'percent' || discountType === 'percentage') {
                                    discountText = ` (${freq.discount}% off)`;
                                } else {
                                    // For fixed/dollar discount
                                    discountText = ` ($${freq.discount} off)`;
                                }
                            }
                            
                            const isChecked = currentFrequency && currentFrequency.id === freq.id;
                            
                            return `
                                <label class="frequency-option">
                                    <input type="radio" name="frequency" 
                                        value="${freq.id}" 
                                        data-name="${freqName}${discountText}"
                                        data-discount="${freq.discount}"
                                        data-discount-type="${discountType}"
                                        data-time-unit="${freq.timeUnit}"
                                        data-frequency="${freq.frequency}"
                                        ${isChecked ? 'checked' : ''}>
                                    <span class="radio-label">${freqName}${discountText}</span>
                                </label>
                            `;
                        }).join('');
                        
                        contentElements.html(frequencyOptionsHtml);
                        
                        if (contentElements.find('input:checked').length === 0) {
                            contentElements.find('input:first').prop('checked', true);
                        }
                    } else {
                        throw new Error('No frequency options available');
                    }
                    <?php else: ?>
                    // If no available frequencies in subscription data, show basic options
                    const basicFrequencies = [
                        { frequency: 1, timeUnit: 'week', discount: 0, discountType: 'percent' },
                        { frequency: 2, timeUnit: 'weeks', discount: 0, discountType: 'percent' },
                        { frequency: 1, timeUnit: 'month', discount: 0, discountType: 'percent' },
                        { frequency: 3, timeUnit: 'months', discount: 0, discountType: 'percent' }
                    ];
                    
                    const frequencyOptionsHtml = basicFrequencies.map(freq => {
                        const freqName = `Every ${freq.frequency} ${freq.frequency > 1 ? freq.timeUnit : freq.timeUnit.replace(/s$/, '')}`;
                        
                        // Handle discount display (should be 0 in fallback options)
                        const discountType = freq.discountType || 'percent';
                        let discountText = '';
                        
                        if (freq.discount > 0) {
                            if (discountType === 'percent' || discountType === 'percentage') {
                                discountText = ` (${freq.discount}% off)`;
                            } else {
                                discountText = ` ($${freq.discount} off)`;
                            }
                        }
                        
                        const isChecked = currentFrequency && 
                                          currentFrequency.frequency == freq.frequency && 
                                          currentFrequency.timeUnit == freq.timeUnit;
                        
                        return `
                            <label class="frequency-option">
                                <input type="radio" name="frequency" 
                                    value="custom_${freq.frequency}_${freq.timeUnit}" 
                                    data-name="${freqName}${discountText}"
                                    data-discount="${freq.discount}"
                                    data-discount-type="${discountType}"
                                    data-time-unit="${freq.timeUnit}"
                                    data-frequency="${freq.frequency}"
                                    ${isChecked ? 'checked' : ''}>
                                <span class="radio-label">${freqName}${discountText}</span>
                            </label>
                        `;
                    }).join('');
                    
                    contentElements.html(frequencyOptionsHtml);
                    
                    if (contentElements.find('input:checked').length === 0) {
                        contentElements.find('input:first').prop('checked', true);
                    }
                    <?php endif; ?>
                }
                
            } catch (error) {
                console.error('Error fetching frequency data:', error);
                contentElements.html('<p class="error">Failed to load frequency options. Please try refreshing the page.</p>');
            } finally {
                loadingElements.hide();
            }
        },
        
        // Edit shipping/billing address
        editAddress: function(e) {
            e.preventDefault();
            const button = $(this);
            const isShipping = button.hasClass('edit-shipping');
            const isBilling = button.hasClass('edit-billing');
            
            if (isShipping) {
                $('.shipping-section .current-address-display').hide();
                $('.shipping-section .address-editor').show();
            } else if (isBilling) {
                $('.billing-section .current-address-display').hide();
                $('.billing-section .address-editor').show();
            }
        },
        
        // Cancel address edit
        cancelAddressEdit: function(e) {
            e.preventDefault();
            const form = $(this).closest('form');
            const isShipping = form.data('type') === 'shipping';
            const isBilling = form.data('type') === 'billing';
            
            if (isShipping) {
                $('.shipping-section .address-editor').hide();
                $('.shipping-section .current-address-display').show();
            } else if (isBilling) {
                $('.billing-section .address-editor').hide();
                $('.billing-section .current-address-display').show();
            }
        },
        
        // Save address changes
        saveAddress: async function(e) {
            e.preventDefault();
            const form = $(this);
            const addressType = form.data('type');
            const submitButton = form.find('.save-address');
            const originalButtonText = submitButton.html();
            
            try {
                submitButton.prop('disabled', true)
                           .html('<span class="loading-spinner"></span> Saving...');
                
                helpers.showNotification('Updating address...', 'loading');
                
                // Collect form data
                const formData = {};
                form.serializeArray().forEach(item => {
                    formData[item.name] = item.value;
                });
                
                // Prepare update payload
                const updatePayload = {
                    [addressType]: formData
                };
                
                // Send update to API
                const response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}`,
                    method: 'PUT',
                    data: JSON.stringify(updatePayload),
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });
                
                if (response.code === 200) {
                    // Update displayed address
                    const addressText = Object.values(formData).filter(Boolean).join(', ');
                    const section = addressType === 'shipping' ? '.shipping-section' : '.billing-section';
                    
                    $(section).find('address').text(addressText);
                    $(section).find('.address-editor').hide();
                    $(section).find('.current-address-display').show();
                    
                    helpers.showNotification('Address updated successfully', 'success');
                } else {
                    throw new Error(response.message || 'Failed to update address');
                }
                
            } catch (error) {
                console.error('Error updating address:', error);
                helpers.showNotification('Failed to update address. Please try again.', 'error');
            } finally {
                submitButton.prop('disabled', false)
                           .html(originalButtonText);
            }
        },
        
        // Edit payment method
        editPayment: function(e) {
            e.preventDefault();
            const subscriptionId = $(this).data('subscription-id');
            window.location.href = '<?php echo esc_js(wc_get_account_endpoint_url('payment-methods')); ?>';
        }
    };
    
    // Event handlers
    const eventHandlers = {
        // Edit frequency
        editFrequency: function(e) {
            e.preventDefault();
            const button = $(this);
            
            // Hide the display and show the editor
            $('.current-frequency-display').hide();
            $('.frequency-editor').show();
            
            // Load frequency options if not already loaded
            if ($('.frequency-options-content').children().length === 0) {
                // Find the BOCS ID from different possible locations in the subscription data
                let bocsId = '<?php 
                    // Try different paths to find BOCS ID
                    $bocs_id = '';
                    if (!empty($subscription['bocs']['id'])) {
                        $bocs_id = $subscription['bocs']['id'];
                    } elseif (!empty($subscription['bocsId'])) {
                        $bocs_id = $subscription['bocsId'];
                    } elseif (!empty($subscription['id'])) {
                        // Use subscription ID as fallback
                        $bocs_id = $subscription['id'];
                    }
                    echo esc_js($bocs_id);
                ?>';
                
                // Log the BOCS ID for debugging
                console.log('Using BOCS ID:', bocsId);
                
                const currentFrequency = {
                    id: '<?php echo esc_js($subscription['frequency']['id'] ?? ''); ?>',
                    frequency: <?php echo esc_js($subscription['frequency']['frequency'] ?? 'null'); ?>,
                    timeUnit: '<?php echo esc_js($subscription['frequency']['timeUnit'] ?? ''); ?>'
                };
                
                if (bocsId) {
                    helpers.loadFrequencyOptions(bocsId, currentFrequency);
                } else {
                    // Handle case when BOCS ID is not available
                    $('.frequency-options-loading').hide();
                    $('.frequency-options-content').html('<p class="error">Unable to load frequency options. BOCS ID not found.</p>');
                }
            }
        },
        
        // Cancel frequency edit
        cancelFrequency: function(e) {
            e.preventDefault();
            $('.frequency-editor').hide();
            $('.current-frequency-display').show();
        },
        
        // Save frequency changes
        saveFrequency: async function(e) {
            e.preventDefault();
            const button = $(this);
            const originalButtonText = button.html();
            
            try {
                // Get selected frequency data
                const selectedFrequency = $('input[name="frequency"]:checked');
                if (!selectedFrequency.length) {
                    throw new Error('Please select a frequency');
                }
                
                const frequencyId = selectedFrequency.val();
                const frequencyData = {
                    frequency: parseInt(selectedFrequency.data('frequency')),
                    timeUnit: selectedFrequency.data('time-unit')
                };
                
                // Validate frequency data
                if (!frequencyData.frequency || !frequencyData.timeUnit) {
                    console.error('Invalid frequency data:', frequencyData);
                    throw new Error('Invalid frequency selection');
                }
                
                // Only include ID if it's not a custom ID (custom IDs start with "custom_")
                if (!frequencyId.startsWith('custom_')) {
                    frequencyData.id = frequencyId;
                }
                
                // Get discount information from the selected frequency
                let discountAmount = parseFloat(selectedFrequency.data('discount') || 0);
                let discountType = selectedFrequency.data('discount-type') || 'percent';
                
                // Log for debugging
                console.log('Selected frequency:', frequencyData);
                console.log('Initial discount info:', {amount: discountAmount, type: discountType});
                
                // Initialize variables in the parent scope so they're available later
                let updatedLineItems = [];
                let discountTotal = 0;
                let finalTotal = 0;
                let currencySymbol = '<?php echo esc_js($subscription['currencySymbol'] ?? '$'); ?>';
                
                // If no discount found in selected frequency, check subscription metadata (mimicking bocs_update_box.php approach)
                if (discountAmount <= 0) {
                    // Check subscription metadata
                    const subscriptionData = <?php echo json_encode($subscription); ?>;
                    console.log('Checking subscription for discount data:', subscriptionData);
                    
                    // First try frequency object
                    if (subscriptionData.frequency && 
                        typeof subscriptionData.frequency.discount !== 'undefined' && 
                        subscriptionData.frequency.discountType) {
                        discountAmount = parseFloat(subscriptionData.frequency.discount);
                        discountType = subscriptionData.frequency.discountType.toLowerCase();
                        console.log('Found discount in frequency object:', {amount: discountAmount, type: discountType});
                    } 
                    // Then check metadata
                    else if (subscriptionData.metaData && Array.isArray(subscriptionData.metaData)) {
                        let discountMeta = null;
                        let discountTypeMeta = null;
                        
                        subscriptionData.metaData.forEach(meta => {
                            if (meta.key === '__bocs_discount' && meta.value) {
                                discountMeta = parseFloat(meta.value);
                            }
                            if (meta.key === '__bocs_discount_type' && meta.value) {
                                discountTypeMeta = meta.value.toLowerCase();
                            }
                        });
                        
                        if (discountMeta !== null && discountTypeMeta !== null) {
                            discountAmount = discountMeta;
                            discountType = discountTypeMeta;
                            console.log('Found discount in metadata:', {amount: discountAmount, type: discountType});
                        }
                    }
                    // Finally check coupon lines
                    else if (subscriptionData.couponLines && 
                             Array.isArray(subscriptionData.couponLines) && 
                             subscriptionData.couponLines.length > 0) {
                        const coupon = subscriptionData.couponLines[0];
                        if (coupon.discount && parseFloat(coupon.discount) > 0) {
                            discountAmount = parseFloat(coupon.discount);
                            // Default to dollar as we don't know the type from coupon data
                            discountType = 'dollar';
                            console.log('Found discount in coupon lines:', {amount: discountAmount, type: discountType});
                        }
                    }
                }
                
                // Ensure discount amount is a valid number
                if (isNaN(discountAmount)) {
                    console.warn('Invalid discount amount, defaulting to 0');
                    discountAmount = 0;
                }
                
                // Normalize discount type
                if (discountType === 'percentage') {
                    discountType = 'percent';
                } else if (discountType === 'fixed') {
                    discountType = 'dollar';
                }
                
                // Ensure discount type is valid
                if (discountType !== 'percent' && discountType !== 'dollar') {
                    console.warn('Invalid discount type, defaulting to percent:', discountType);
                    discountType = 'percent';
                }
                
                console.log('Using final discount:', {amount: discountAmount, type: discountType});
                
                // Show loading state
                button.prop('disabled', true)
                      .html('<span class="loading-spinner"></span> Saving...');
                helpers.showNotification('Updating subscription...', 'loading');
                
                // Prepare the update payload - start with minimal data to ensure success
                const updatePayload = {
                    frequency: frequencyData
                };
                
                // Add discount information to frequency data if available
                if (discountAmount > 0) {
                    updatePayload.frequency.discount = discountAmount;
                    updatePayload.frequency.discountType = discountType.toUpperCase();
                }
                
                // If there's a discount, update line items with the new pricing
                if (discountAmount > 0) {
                    try {
                        const currentLineItems = <?php echo json_encode($subscription['lineItems'] ?? []); ?>;
                        console.log('Original line items:', currentLineItems);
                        
                        // Validate line items
                        if (!currentLineItems || !Array.isArray(currentLineItems) || currentLineItems.length === 0) {
                            console.warn('No valid line items to update');
                        } else {
                            // Update each line item with discount applied
                            updatedLineItems = currentLineItems.map(item => {
                                try {
                                    // Skip shipping or special items
                                    if (item.productId === 'shipping' || !item.price) {
                                        return item;
                                    }
                                    
                                    // Clone the item to avoid modifying the original
                                    const updatedItem = {...item};
                                    
                                    // Calculate new price with discount based on discount type
                                    const originalPrice = parseFloat(item.price);
                                    if (isNaN(originalPrice)) {
                                        console.warn('Invalid price found:', item.price);
                                        return item;
                                    }
                                    
                                    let discountedPrice;
                                    
                                    if (discountType === 'percent') {
                                        // Percentage discount: reduce by percentage
                                        discountedPrice = originalPrice * (1 - (discountAmount / 100));
                                    } else {
                                        // Fixed/dollar discount: subtract fixed amount
                                        // but ensure price doesn't go below zero
                                        discountedPrice = Math.max(0, originalPrice - discountAmount);
                                    }
                                    
                                    // Ensure quantity is valid
                                    const quantity = parseInt(item.quantity) || 1;
                                    
                                    // Update price and related fields
                                    updatedItem.price = parseFloat(discountedPrice.toFixed(2));
                                    updatedItem.total = parseFloat((discountedPrice * quantity).toFixed(2));
                                    
                                    // If tax values exist, recalculate them
                                    if (item.totalTax) {
                                        // Calculate effective tax rate from original values
                                        const originalTotal = parseFloat(item.total) || parseFloat(item.price) * quantity;
                                        const taxRate = parseFloat(item.totalTax) / (originalTotal || 1); // Avoid division by zero
                                        updatedItem.totalTax = parseFloat((taxRate * updatedItem.total).toFixed(2));
                                    }
                                    
                                    return updatedItem;
                                } catch (itemError) {
                                    console.error('Error updating line item:', itemError, item);
                                    return item; // Return original item on error
                                }
                            });
                            
                            console.log('Updated line items:', updatedLineItems);
                            
                            // Add the updated line items to the payload
                            updatePayload.lineItems = updatedLineItems;
                            
                            try {
                                // Calculate subtotal and discount totals
                                let subtotal = 0;
                                let discountTotal = 0;
                                
                                // Calculate original subtotal without discount
                                currentLineItems.forEach(item => {
                                    if (item.productId !== 'shipping' && item.price) {
                                        const price = parseFloat(item.price);
                                        const quantity = parseInt(item.quantity) || 1;
                                        if (!isNaN(price)) {
                                            subtotal += price * quantity;
                                        }
                                    }
                                });
                                
                                // Round to 2 decimal places
                                subtotal = parseFloat(subtotal.toFixed(2));
                                console.log('Calculated subtotal:', subtotal);
                                
                                // Calculate discount total
                                if (discountType === 'percent') {
                                    discountTotal = subtotal * (discountAmount / 100);
                                } else {
                                    // For dollar discount, apply to the subtotal
                                    discountTotal = Math.min(subtotal, discountAmount);
                                }
                                
                                // Round to 2 decimal places
                                discountTotal = parseFloat(discountTotal.toFixed(2));
                                console.log('Calculated discount total:', discountTotal);
                                
                                // Calculate totals with tax and shipping
                                let finalTotal = 0;
                                let totalTax = 0;
                                let shippingTotal = 0;
                                let shippingTax = 0;
                                
                                // Get shipping line item if any
                                const shippingItem = currentLineItems.find(item => item.productId === 'shipping');
                                if (shippingItem) {
                                    shippingTotal = parseFloat(shippingItem.price || 0);
                                    shippingTax = parseFloat(shippingItem.totalTax || 0);
                                }
                                
                                console.log('Shipping:', {total: shippingTotal, tax: shippingTax});
                                
                                // Calculate tax
                                updatedLineItems.forEach(item => {
                                    if (item.productId !== 'shipping' && item.totalTax) {
                                        const itemTax = parseFloat(item.totalTax);
                                        if (!isNaN(itemTax)) {
                                            totalTax += itemTax;
                                        }
                                    }
                                });
                                
                                // Calculate final total = sum of updated line items + tax + shipping
                                const lineItemsTotal = updatedLineItems
                                    .filter(item => item.productId !== 'shipping')
                                    .reduce((sum, item) => {
                                        const itemTotal = parseFloat(item.total);
                                        return sum + (isNaN(itemTotal) ? 0 : itemTotal);
                                    }, 0);
                                    
                                finalTotal = lineItemsTotal + totalTax + shippingTotal + shippingTax;
                                
                                // Round to 2 decimal places
                                finalTotal = parseFloat(finalTotal.toFixed(2));
                                totalTax = parseFloat(totalTax.toFixed(2));
                                
                                console.log('Calculated totals:', {
                                    lineItemsTotal,
                                    totalTax,
                                    shippingTotal,
                                    shippingTax,
                                    finalTotal
                                });
                                
                                // Add discount metadata to payload
                                if (discountTotal > 0) {
                                    // Create a unique coupon code based on discount and time
                                    const couponCode = `bocs-${discountType === 'percent' ? 
                                        discountAmount + 'percent' : 
                                        discountAmount + 'dollar'}-${new Date().getTime()}`;
                                        
                                    // Add coupon lines
                                    updatePayload.couponLines = [{
                                        code: couponCode,
                                        discount: discountTotal,
                                        discountTax: 0 // Calculate tax if needed
                                    }];
                                    
                                    // Add discount totals
                                    updatePayload.discountTotal = discountTotal;
                                    updatePayload.discountTax = 0; // Calculate if needed
                                }
                                
                                // Add totals to payload
                                updatePayload.total = finalTotal;
                                updatePayload.totalTax = totalTax + shippingTax;
                                updatePayload.cartTax = totalTax;
                                updatePayload.shippingTax = shippingTax;
                                
                                if (shippingTotal > 0) {
                                    updatePayload.shippingTotal = shippingTotal;
                                }
                            } catch (calcError) {
                                console.error('Error calculating totals:', calcError);
                                // Continue with the basic payload without totals
                            }
                        }
                    } catch (lineItemsError) {
                        console.error('Error processing line items:', lineItemsError);
                        // Continue with basic frequency update
                    }
                } else {
                    // No discount applied - restore original prices and remove any discounts
                    try {
                        const currentLineItems = <?php echo json_encode($subscription['lineItems'] ?? []); ?>;
                        console.log('Original line items with no discount:', currentLineItems);
                        
                        // Just include original line items without modification
                        updatePayload.lineItems = currentLineItems;
                        
                        // Set empty coupon lines to remove existing discounts
                        updatePayload.couponLines = [];
                        updatePayload.discountTotal = 0;
                        updatePayload.discountTax = 0;
                        
                        // Calculate totals without discounts
                        try {
                            let subtotal = 0;
                            let totalTax = 0;
                            let shippingTotal = 0;
                            let shippingTax = 0;
                            
                            // Get shipping line item if any
                            const shippingItem = currentLineItems.find(item => item.productId === 'shipping');
                            if (shippingItem) {
                                shippingTotal = parseFloat(shippingItem.price || 0);
                                shippingTax = parseFloat(shippingItem.totalTax || 0);
                            }
                            
                            // Calculate line items total and tax
                            currentLineItems.forEach(item => {
                                if (item.productId !== 'shipping') {
                                    const price = parseFloat(item.price);
                                    const quantity = parseInt(item.quantity) || 1;
                                    if (!isNaN(price)) {
                                        subtotal += price * quantity;
                                    }
                                    
                                    if (item.totalTax) {
                                        totalTax += parseFloat(item.totalTax);
                                    }
                                }
                            });
                            
                            // Round to 2 decimal places
                            subtotal = parseFloat(subtotal.toFixed(2));
                            totalTax = parseFloat(totalTax.toFixed(2));
                            
                            // Calculate final total without discount
                            const finalTotal = subtotal + totalTax + shippingTotal + shippingTax;
                            
                            // Add totals to payload
                            updatePayload.total = parseFloat(finalTotal.toFixed(2));
                            updatePayload.totalTax = totalTax + shippingTax;
                            updatePayload.cartTax = totalTax;
                            updatePayload.shippingTax = shippingTax;
                            
                            if (shippingTotal > 0) {
                                updatePayload.shippingTotal = shippingTotal;
                            }
                            
                            console.log('Calculated totals without discount:', {
                                subtotal,
                                totalTax,
                                shippingTotal,
                                shippingTax,
                                finalTotal
                            });
                        } catch (calcError) {
                            console.error('Error calculating totals without discount:', calcError);
                            // Continue with the basic payload without totals
                        }
                    } catch (lineItemsError) {
                        console.error('Error processing line items without discount:', lineItemsError);
                        // Continue with basic frequency update
                    }
                }
                
                console.log('Final update payload:', updatePayload);
                
                // Send update to API
                const response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}`,
                    method: 'PUT',
                    data: JSON.stringify(updatePayload),
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });
                
                console.log('API response:', response);
                
                if (response.code === 200) {
                    // Update displayed frequency
                    const frequencyText = `Every ${frequencyData.frequency} ${frequencyData.frequency > 1 ? frequencyData.timeUnit : frequencyData.timeUnit.replace(/s$/, '')}`;
                    $('.current-frequency').text(frequencyText);
                    
                    // Hide editor and show display
                    $('.frequency-editor').hide();
                    $('.current-frequency-display').show();
                    
                    // Update display of line items
                    if (discountAmount > 0) {
                        // Update the line items table with new prices
                        updatedLineItems.forEach(item => {
                            if (item.productId !== 'shipping') {
                                const $row = $(`.line-items-table tr[data-product-id="${item.productId}"]`);
                                if ($row.length) {
                                    // Update price column
                                    $row.find('.product-price').text(currencySymbol + parseFloat(item.price).toFixed(2));
                                    
                                    // Update total column
                                    $row.find('.product-total').text(currencySymbol + parseFloat(item.total).toFixed(2));
                                }
                            }
                        });
                        
                        // Update totals
                        if (discountTotal > 0) {
                            // Add discount row if it doesn't exist
                            if ($('.line-items-table .discount-row').length === 0) {
                                $('.line-items-table .subtotal-row').after(
                                    `<tr class="discount-row">
                                        <th colspan="3">Discount</th>
                                        <td>-${currencySymbol}${discountTotal.toFixed(2)}</td>
                                    </tr>`
                                );
                            } else {
                                // Update existing discount row
                                $('.line-items-table .discount-row td').text(`-${currencySymbol}${discountTotal.toFixed(2)}`);
                            }
                        }
                        
                        // Update subscription total
                        $('.line-items-table .total-row td').text(`${currencySymbol}${finalTotal.toFixed(2)}`);
                    } else {
                        // No discount - restore original prices and remove discount rows
                        const currentLineItems = <?php echo json_encode($subscription['lineItems'] ?? []); ?>;
                        
                        // Update the line items table with original prices
                        currentLineItems.forEach(item => {
                            if (item.productId !== 'shipping') {
                                const $row = $(`.line-items-table tr[data-product-id="${item.productId}"]`);
                                if ($row.length) {
                                    const price = parseFloat(item.price);
                                    const quantity = parseInt(item.quantity) || 1;
                                    const total = price * quantity;
                                    
                                    // Update price column
                                    $row.find('.product-price').text(currencySymbol + price.toFixed(2));
                                    
                                    // Update total column
                                    $row.find('.product-total').text(currencySymbol + total.toFixed(2));
                                }
                            }
                        });
                        
                        // Remove any discount rows
                        $('.line-items-table .discount-row').remove();
                        
                        // Calculate and update the total
                        let totalAmount = 0;
                        let taxAmount = 0;
                        let shippingAmount = 0;
                        
                        currentLineItems.forEach(item => {
                            if (item.productId === 'shipping') {
                                shippingAmount += parseFloat(item.price || 0);
                            } else {
                                const price = parseFloat(item.price || 0);
                                const quantity = parseInt(item.quantity) || 1;
                                totalAmount += price * quantity;
                                
                                if (item.totalTax) {
                                    taxAmount += parseFloat(item.totalTax);
                                }
                            }
                        });
                        
                        const finalAmount = totalAmount + taxAmount + shippingAmount;
                        
                        // Update subscription total
                        $('.line-items-table .total-row td').text(`${currencySymbol}${finalAmount.toFixed(2)}`);
                    }
                    
                    // Show success message with discount info if applicable
                    let message = 'Subscription updated successfully';
                    if (discountAmount > 0) {
                        if (discountType === 'percent') {
                            message += ` with ${discountAmount}% discount applied`;
                        } else {
                            message += ` with $${discountAmount} discount applied`;
                        }
                    } else if ($('.line-items-table .discount-row').length > 0) {
                        message += ` and discount removed`;
                    }
                    helpers.showNotification(message, 'success');
                    
                    // No longer need to reload the page since we update the UI directly
                    // setTimeout(() => {
                    //     window.location.reload();
                    // }, 1500);
                } else {
                    throw new Error(response.message || 'Failed to update frequency');
                }
            } catch (error) {
                console.error('Error updating subscription:', error);
                button.html(originalButtonText).prop('disabled', false);
                helpers.showNotification(error.message || 'An error occurred. Please try again.', 'error');
                
                // Provide more detailed error guidance
                if (error.status === 400) {
                    console.error('API Error 400: Bad Request. Check payload format:', error.responseText);
                } else if (error.status === 401 || error.status === 403) {
                    console.error('API Error: Authentication/Authorization issue');
                } else if (error.status === 500) {
                    console.error('API Error 500: Server error:', error.responseText);
                }
            }
        },
        
        // Edit shipping/billing address
        editAddress: function(e) {
            e.preventDefault();
            const button = $(this);
            const isShipping = button.hasClass('edit-shipping');
            const isBilling = button.hasClass('edit-billing');
            
            if (isShipping) {
                $('.shipping-section .current-address-display').hide();
                $('.shipping-section .address-editor').show();
            } else if (isBilling) {
                $('.billing-section .current-address-display').hide();
                $('.billing-section .address-editor').show();
            }
        },
        
        // Cancel address edit
        cancelAddressEdit: function(e) {
            e.preventDefault();
            const form = $(this).closest('form');
            const isShipping = form.data('type') === 'shipping';
            const isBilling = form.data('type') === 'billing';
            
            if (isShipping) {
                $('.shipping-section .address-editor').hide();
                $('.shipping-section .current-address-display').show();
            } else if (isBilling) {
                $('.billing-section .address-editor').hide();
                $('.billing-section .current-address-display').show();
            }
        },
        
        // Save address changes
        saveAddress: async function(e) {
            e.preventDefault();
            const form = $(this);
            const addressType = form.data('type');
            const submitButton = form.find('.save-address');
            const originalButtonText = submitButton.html();
            
            try {
                submitButton.prop('disabled', true)
                           .html('<span class="loading-spinner"></span> Saving...');
                
                helpers.showNotification('Updating address...', 'loading');
                
                // Collect form data
                const formData = {};
                form.serializeArray().forEach(item => {
                    formData[item.name] = item.value;
                });
                
                // Prepare update payload
                const updatePayload = {
                    [addressType]: formData
                };
                
                // Send update to API
                const response = await $.ajax({
                    url: `<?php echo BOCS_API_URL; ?>subscriptions/${subscriptionId}`,
                    method: 'PUT',
                    data: JSON.stringify(updatePayload),
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('Store', '<?php echo esc_js($options['bocs_headers']['store']); ?>');
                        xhr.setRequestHeader('Organization', '<?php echo esc_js($options['bocs_headers']['organization']); ?>');
                        xhr.setRequestHeader('Authorization', '<?php echo esc_js($options['bocs_headers']['authorization']); ?>');
                    }
                });
                
                if (response.code === 200) {
                    // Update displayed address
                    const addressText = Object.values(formData).filter(Boolean).join(', ');
                    const section = addressType === 'shipping' ? '.shipping-section' : '.billing-section';
                    
                    $(section).find('address').text(addressText);
                    $(section).find('.address-editor').hide();
                    $(section).find('.current-address-display').show();
                    
                    helpers.showNotification('Address updated successfully', 'success');
                } else {
                    throw new Error(response.message || 'Failed to update address');
                }
                
            } catch (error) {
                console.error('Error updating address:', error);
                helpers.showNotification('Failed to update address. Please try again.', 'error');
            } finally {
                submitButton.prop('disabled', false)
                           .html(originalButtonText);
            }
        },
        
        // Edit payment method
        editPayment: function(e) {
            e.preventDefault();
            const subscriptionId = $(this).data('subscription-id');
            window.location.href = '<?php echo esc_js(wc_get_account_endpoint_url('payment-methods')); ?>';
        }
    };
    
    // Event bindings
    $('.edit-frequency').on('click', eventHandlers.editFrequency);
    $('.cancel-frequency').on('click', eventHandlers.cancelFrequency);
    $('.save-frequency').on('click', eventHandlers.saveFrequency);
    $('.edit-shipping, .edit-billing').on('click', eventHandlers.editAddress);
    $('.cancel-address-edit').on('click', eventHandlers.cancelAddressEdit);
    $('.edit-address-form').on('submit', eventHandlers.saveAddress);
    $('.edit-payment').on('click', eventHandlers.editPayment);
    
    // Add real-time preview of price changes when frequency option is clicked
    $(document).on('change', 'input[name="frequency"]', function() {
        const selectedFrequency = $(this);
        let discountAmount = parseFloat(selectedFrequency.data('discount') || 0);
        let discountType = selectedFrequency.data('discount-type') || 'percent';
        
        console.log('Selected frequency option:', {
            name: selectedFrequency.data('name'),
            discount: discountAmount,
            discountType: discountType
        });
        
        // Get current line items from the page
        const currentLineItems = <?php echo json_encode($subscription['lineItems'] ?? []); ?>;
        const currencySymbol = '<?php echo esc_js($subscription['currencySymbol'] ?? '$'); ?>';
        
        // Store original prices if not already stored
        if (!window.originalLineItems) {
            window.originalLineItems = currentLineItems.map(item => ({...item}));
            console.log('Stored original line items:', window.originalLineItems);
        }
        
        // Clear any previous preview styling
        $('.preview-price').removeClass('preview-price');
        
        // Preview updated prices
        let subtotal = 0;
        let discountTotal = 0;
        
        // Always use original prices as the starting point to ensure accurate calculations
        let updatedLineItems = window.originalLineItems.map(item => {
            // Skip shipping or special items
            if (item.productId === 'shipping' || !item.price) {
                return item;
            }
            
            // Calculate new price with discount
            const originalPrice = parseFloat(item.price);
            if (isNaN(originalPrice)) {
                return item;
            }
            
            // Add to subtotal for discount calculation (using original price)
            const quantity = parseInt(item.quantity) || 1;
            subtotal += originalPrice * quantity;
            
            // Calculate discounted price if applicable
            let discountedPrice;
            if (discountAmount > 0) {
                if (discountType === 'percent') {
                    discountedPrice = originalPrice * (1 - (discountAmount / 100));
                } else {
                    discountedPrice = Math.max(0, originalPrice - discountAmount);
                }
            } else {
                // No discount - use original price
                discountedPrice = originalPrice;
            }
            
            // Calculate line item total
            const updatedTotal = discountedPrice * quantity;
            
            // Update display in the table
            const $row = $(`.line-items-table tr[data-product-id="${item.productId}"]`);
            if ($row.length) {
                $row.find('.product-price').text(currencySymbol + discountedPrice.toFixed(2)).addClass('preview-price');
                $row.find('.product-total').text(currencySymbol + updatedTotal.toFixed(2)).addClass('preview-price');
            }
            
            return {
                ...item,
                price: discountedPrice,
                total: updatedTotal
            };
        });
        
        // Handle discount display
        if (discountAmount > 0) {
            // Calculate discount total
            if (discountType === 'percent') {
                discountTotal = subtotal * (discountAmount / 100);
            } else {
                discountTotal = Math.min(subtotal, discountAmount * (currentLineItems.filter(item => item.productId !== 'shipping').length));
            }
            discountTotal = parseFloat(discountTotal.toFixed(2));
            
            console.log('Calculated discount total:', discountTotal);
            
            // Update or add discount row
            if (discountTotal > 0) {
                if ($('.line-items-table .discount-row').length === 0) {
                    $('.line-items-table .subtotal-row').after(
                        `<tr class="discount-row">
                            <th colspan="3">Discount (Preview)</th>
                            <td class="preview-price">-${currencySymbol}${discountTotal.toFixed(2)}</td>
                        </tr>`
                    );
                } else {
                    $('.line-items-table .discount-row th').text('Discount (Preview)');
                    $('.line-items-table .discount-row td').text(`-${currencySymbol}${discountTotal.toFixed(2)}`).addClass('preview-price');
                }
            }
        } else {
            // No discount - remove any discount rows
            $('.line-items-table .discount-row').remove();
        }
        
        // Update total preview
        const calculatedTotal = updatedLineItems.reduce((sum, item) => {
            if (item.productId !== 'shipping') {
                return sum + (parseFloat(item.total) || 0);
            }
            return sum;
        }, 0);
        
        // Add shipping if present
        const shippingItem = currentLineItems.find(item => item.productId === 'shipping');
        const shippingTotal = shippingItem ? parseFloat(shippingItem.price || 0) : 0;
        
        // Add taxes (simplified - using original tax rate)
        let totalTax = 0;
        updatedLineItems.forEach(item => {
            const originalItem = window.originalLineItems.find(orig => orig.productId === item.productId);
            if (item.productId !== 'shipping' && originalItem && originalItem.totalTax) {
                const originalTotal = parseFloat(originalItem.total);
                const taxRate = parseFloat(originalItem.totalTax) / (originalTotal || 1);
                totalTax += taxRate * parseFloat(item.total);
            }
        });
        
        const finalTotal = calculatedTotal + totalTax + shippingTotal;
        
        // Update total row with preview indicator
        $('.line-items-table .total-row td').text(`${currencySymbol}${finalTotal.toFixed(2)} (Preview)`).addClass('preview-price');
    });
});
</script> 