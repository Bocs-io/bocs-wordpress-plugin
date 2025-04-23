<?php
/**
 * BOCS Subscription List
 *
 * Displays a list of customer subscriptions in an accordion layout.
 * All accordions are closed by default - clicking a header will open that item.
 *
 * @package BOCS
 * @subpackage Templates/MyAccount
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

// Ensure script and style dependencies are loaded
wp_enqueue_script('bocs-subscriptions', BOCS_PLUGIN_URL . 'assets/js/bocs-subscriptions.js', array('jquery'), '20250423.1', true);
wp_enqueue_style('bocs-subscriptions', BOCS_PLUGIN_URL . 'assets/css/bocs-subscriptions.css', array(), '20250423.5');

// Add Stripe JS if available
if (class_exists('WC_Gateway_Stripe') && function_exists('wc_stripe_get_publishable_key')) {
    wp_enqueue_script('stripe', 'https://js.stripe.com/v3/', [], null, true);
    
    // Add inline script to set publishable key
    wp_add_inline_script('stripe', 'window.stripePublishableKey = "' . wc_stripe_get_publishable_key() . '";', 'after');
}

// Log template loading - for debugging
if (class_exists('Bocs_Log_Handler')) {
    $logger = new Bocs_Log_Handler();
    $logger->insert_log('debug', '[Subscription List Template] Template loaded', [
        'template' => 'subscription-list.php',
        'time' => current_time('mysql')
    ]);
    
    // Add raw data logging
    $logger->insert_log('debug', '[Subscription List Template] Raw subscription data', [
        'has_subscriptions' => isset($subscriptions) && is_array($subscriptions) ? 'Yes' : 'No',
        'has_data' => isset($subscriptions['data']) ? 'Yes' : 'No',
        'has_data_data' => isset($subscriptions['data']['data']) ? 'Yes' : 'No',
        'data_count' => isset($subscriptions['data']['data']) ? count($subscriptions['data']['data']) : 0,
        'first_item_keys' => isset($subscriptions['data']['data'][0]) ? implode(', ', array_keys($subscriptions['data']['data'][0])) : 'No items'
    ]);
}

// Subscription data is now formatted in Bocs_Account.php before including this template
// $subscriptions_formatted = bocs_get_customer_subscriptions();

// Log the formatted data
if (class_exists('Bocs_Log_Handler')) {
    $logger = new Bocs_Log_Handler();
    $logger->insert_log('debug', '[Subscription List Template] Formatted subscription data', [
        'count' => isset($subscriptions_formatted) ? count($subscriptions_formatted) : 0,
        'first_item_keys' => !empty($subscriptions_formatted) ? implode(', ', array_keys($subscriptions_formatted[0])) : 'No items'
    ]);
}

wp_localize_script('bocs-subscriptions', 'bocsSubscriptionsData', array(
    'subscriptions' => $subscriptions_formatted,
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('bocs-subscriptions-nonce'),
    'apiUrl' => BOCS_API_URL,
    'headers' => array(
        'store' => $options['bocs_headers']['store'],
        'organization' => $options['bocs_headers']['organization'],
        'authorization' => $options['bocs_headers']['authorization']
    ),
    'i18n' => array(
        'processing' => __('Processing...', 'bocs-wordpress'),
        'saveChanges' => __('Save Changes', 'bocs-wordpress'),
        'loading' => __('Loading...', 'bocs-wordpress')
    )
));

// Log subscription data for debugging
if (function_exists('bocs_log')) {
    bocs_log('Loading subscription-list.php template', 'info', [
        'raw_subscriptions' => isset($subscriptions) ? 'Found' : 'Not Found',
        'raw_count' => isset($subscriptions['data']['data']) ? count($subscriptions['data']['data']) : 0,
        'formatted_count' => isset($subscriptions_formatted) ? count($subscriptions_formatted) : 0
    ]);
    
    // Log the structure of the subscription data
    if (isset($subscriptions)) {
        bocs_log('Subscription data structure', 'debug', [
            'structure' => is_array($subscriptions) ? 'Array structure' : 'Non-array structure'
        ]);
    }
}

?>

<div class="bocs-subscriptions-container">
    <?php if (!empty($subscriptions_formatted)) : ?>
        <!-- Accordion list - all items closed by default -->
        <div id="bocs-subscriptions-list">
            <?php foreach ($subscriptions_formatted as $subscription) : 
                $status = isset($subscription['status']) ? $subscription['status'] : 'active';
                $status_class = 'status-' . strtolower($status);
                $status_label = ucfirst($status);
                
                $subscription_id = isset($subscription['id']) ? $subscription['id'] : '';
                $price = isset($subscription['price']) ? $subscription['price'] : '';
                $frequency = isset($subscription['frequency']) ? $subscription['frequency'] : '';
                $frequency_formatted = isset($subscription['frequency_formatted']) ? $subscription['frequency_formatted'] : '';
                
                $next_payment_date = isset($subscription['next_payment_date']) ? $subscription['next_payment_date'] : '';
                $next_delivery_date = isset($subscription['next_delivery_date']) ? $subscription['next_delivery_date'] : '';
                
                $billing_date = isset($subscription['billing_date']) ? $subscription['billing_date'] : '';
                $change_by_date = isset($subscription['change_by_date']) ? $subscription['change_by_date'] : '';
                
                $delivery_address = isset($subscription['delivery_address']) ? $subscription['delivery_address'] : array();
                $address_formatted = isset($delivery_address['formatted']) ? $delivery_address['formatted'] : '';
                
                $payment_method = isset($subscription['payment_method']) ? $subscription['payment_method'] : array();
                $payment_method_formatted = isset($payment_method['formatted']) ? $payment_method['formatted'] : '';
                
                $items = isset($subscription['items']) ? $subscription['items'] : array();
                $discount = isset($subscription['discount']) ? $subscription['discount'] : '';
                $shipping = isset($subscription['shipping']) ? $subscription['shipping'] : '5.00';
                $total = isset($subscription['total']) ? $subscription['total'] : $price;
            ?>
            <div class="bocs-subscription-item" data-subscription-id="<?php echo esc_attr($subscription_id); ?>">
                <div class="bocs-subscription-header">
                    <div class="bocs-subscription-toggle">
                        <span class="bocs-toggle-icon"></span>
                    </div>
                    <div class="bocs-subscription-info">
                        <div class="bocs-subscription-id-row">
                            <div class="bocs-subscription-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></div>
                            <div class="bocs-subscription-id">
                                <?php 
                                // Use externalSourceParentOrderId if available, otherwise use subscription ID
                                $external_order_id = isset($subscription['externalSourceParentOrderId']) ? $subscription['externalSourceParentOrderId'] : '';
                                
                                if (!empty($external_order_id)) {
                                    echo esc_html('SUB-' . $external_order_id);
                                } else {
                                    // Use shortened UUID if no order ID is available
                                    $short_id = substr($subscription_id, 0, 8);
                                    echo esc_html('SUB-' . $short_id);
                                }
                                ?>
                            </div>
                            <div class="bocs-subscription-price">
                                <?php 
                                // Format the price with the subscription data
                                // Ensure total is a numeric value
                                $numeric_total = is_array($total) ? 0 : (float)$total;
                                $formatted_price = '$' . number_format($numeric_total, 2, '.', ',');
                                $formatted_frequency = strtolower($frequency_formatted);
                                echo esc_html($formatted_price . ' ' . $formatted_frequency); 
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="bocs-subscription-billing">
                        <div class="bocs-billing-dates">
                            <div>Billing on <?php echo esc_html($billing_date); ?></div>
                            <div>Make any changes by <?php echo esc_html($change_by_date); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="bocs-subscription-details">
                    <div class="bocs-subscription-actions">
                        <a href="<?php echo esc_url(wc_get_account_endpoint_url('bocs-edit-details') . $subscription_id); ?>" class="bocs-button edit-contents">Edit contents</a>
                        <a href="<?php echo esc_url(wc_get_account_endpoint_url('bocs-switch-bocs') . $subscription_id); ?>" class="bocs-button change-box">Change box</a>
                        <button class="bocs-button early-renewal">Early Renewal</button>
                    </div>
                    
                    <div class="bocs-subscription-name">
                        <h3>
                        <?php 
                        // Get subscription name - check if BOCS name is empty
                        $bocs_name = '';
                        if (isset($subscription['bocs']['name']) && !empty($subscription['bocs']['name'])) {
                            $bocs_name = $subscription['bocs']['name'];
                        } elseif (isset($subscription['bocs']['id']) && !empty($subscription['bocs']['id'])) {
                            // Fetch BOCS details if name is empty but we have ID
                            $bocs_id = $subscription['bocs']['id'];
                            $url = BOCS_API_URL . 'bocs/' . $bocs_id;
                            $helper = new Bocs_Helper();
                            $bocs_details = $helper->curl_request($url, 'GET', [], $options['bocs_headers']);
                            
                            if (isset($bocs_details['data']['name']) && !empty($bocs_details['data']['name'])) {
                                $bocs_name = $bocs_details['data']['name'];
                            }
                        }
                        
                        // If we still don't have a name, use a generic one
                        if (empty($bocs_name)) {
                            $bocs_name = __('Premium Subscription', 'bocs-wordpress');
                        }
                        
                        echo esc_html($bocs_name);
                        ?>
                        </h3>
                    </div>
                    
                    <div class="bocs-subscription-sections">
                        <div class="bocs-section">
                            <h4>Schedule</h4>
                            <div class="bocs-section-content">
                                <div class="bocs-section-lines">
                                    <div class="bocs-section-line">Next payment date: <?php echo esc_html($next_payment_date); ?></div>
                                    <div class="bocs-section-line">Next delivery date: <?php echo esc_html($next_delivery_date); ?></div>
                                </div>
                                <button class="bocs-edit-button edit-schedule">Edit</button>
                            </div>
                        </div>
                        
                        <div class="bocs-section">
                            <h4>Frequency</h4>
                            <div class="bocs-section-content">
                                <div class="bocs-section-lines">
                                    <div class="bocs-section-line">
                                        <?php 
                                        if (isset($frequency_formatted)) {
                                            echo esc_html($frequency_formatted);
                                            echo !empty($discount) ? ' (' . esc_html($discount) . ' Discount)' : '';
                                        } else {
                                            echo esc_html('EVERY month');
                                        }
                                        ?>
                                    </div>
                                </div>
                                <button class="bocs-edit-button edit-frequency">Edit</button>
                            </div>
                        </div>
                        
                        <div class="bocs-section">
                            <h4>Delivery Address</h4>
                            <div class="bocs-section-content">
                                <div class="bocs-section-lines">
                                    <div class="bocs-section-line"><?php echo esc_html($address_formatted); ?></div>
                                </div>
                                <button class="bocs-edit-button edit-address">Edit</button>
                            </div>
                        </div>
                        
                        <div class="bocs-section">
                            <h4>Payment Method</h4>
                            <div class="bocs-section-content">
                                <div class="bocs-section-lines">
                                    <div class="bocs-section-line"><?php echo esc_html($payment_method_formatted); ?></div>
                                </div>
                                <button class="bocs-edit-button edit-payment">Edit</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bocs-order-details">
                        <div class="bocs-order-header">
                            <h4>Order details</h4>
                            <?php if (strtolower($status) === 'upcoming') : 
                                // Get the payment URL for any pending orders for this subscription
                                $bocs_account = new Bocs_Account();
                                $payment_url = $bocs_account->get_pending_order_url($subscription_id);
                                
                                if ($payment_url) : ?>
                                    <a href="<?php echo esc_url($payment_url); ?>" class="bocs-button pay-now">Pay Now</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <table class="bocs-order-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $subtotal = 0;
                                if (!empty($items) && is_array($items)) {
                                    foreach ($items as $item) : 
                                        $product_name = isset($item['name']) ? $item['name'] : '';
                                        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
                                        $unit_price = isset($item['price']) ? (float)$item['price'] : 0;
                                        $total_price = $quantity * $unit_price;
                                        $subtotal += $total_price;
                                ?>
                                <tr>
                                    <td class="product-name" data-title="<?php esc_attr_e('Product', 'bocs-wordpress'); ?>">
                                        <?php echo esc_html($product_name); ?>
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
                                    <td data-title="<?php esc_attr_e('Quantity', 'bocs-wordpress'); ?>"><?php echo esc_html($quantity); ?></td>
                                    <td data-title="<?php esc_attr_e('Price', 'bocs-wordpress'); ?>">
                                        <?php 
                                        if (function_exists('wc_price')) {
                                            echo wp_kses_post(wc_price($unit_price));
                                        } else {
                                            echo esc_html('$' . number_format($unit_price, 2));
                                        }
                                        ?>
                                    </td>
                                    <td data-title="<?php esc_attr_e('Total', 'bocs-wordpress'); ?>">
                                        <?php 
                                        if (function_exists('wc_price')) {
                                            echo wp_kses_post(wc_price($total_price));
                                        } else {
                                            echo esc_html('$' . number_format($total_price, 2));
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; 
                                } else {
                                    echo '<tr><td colspan="4">' . esc_html__('No items found', 'bocs-wordpress') . '</td></tr>';
                                }
                                ?>
                            </tbody>
                            <tfoot>
                                <tr class="bocs-subtotal-row">
                                    <th colspan="3">Subtotal</th>
                                    <td data-title="<?php esc_attr_e('Subtotal', 'bocs-wordpress'); ?>">
                                        <?php 
                                        if (function_exists('wc_price')) {
                                            echo wp_kses_post(wc_price($subtotal));
                                        } else {
                                            echo esc_html('$' . number_format($subtotal, 2));
                                        }
                                        ?>
                                    </td>
                                </tr>
                                
                                <?php if (!empty($subscription['couponLines']) && is_array($subscription['couponLines'])): ?>
                                    <?php foreach ($subscription['couponLines'] as $coupon): ?>
                                        <tr class="bocs-discount-row">
                                            <th colspan="3">
                                                <?php 
                                                if (!empty($coupon['code'])) {
                                                    echo sprintf(esc_html__('Discount (%s)', 'bocs-wordpress'), esc_html($coupon['code']));
                                                } else {
                                                    esc_html_e('Discount', 'bocs-wordpress');
                                                }
                                                ?>
                                            </th>
                                            <td data-title="<?php esc_attr_e('Discount', 'bocs-wordpress'); ?>">
                                                <?php 
                                                if (!empty($coupon['discount'])) {
                                                    if (function_exists('wc_price')) {
                                                        echo wp_kses_post(wc_price($coupon['discount'] * -1));
                                                    } else {
                                                        echo esc_html('-$' . number_format($coupon['discount'], 2));
                                                    }
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <?php if (!empty($shipping)): ?>
                                <tr class="bocs-shipping-row">
                                    <th colspan="3">Shipping</th>
                                    <td data-title="<?php esc_attr_e('Shipping', 'bocs-wordpress'); ?>">
                                        <?php 
                                        // Use shippingTotal if available, otherwise use shipping
                                        $shipping_amount = isset($subscription['shippingTotal']) ? $subscription['shippingTotal'] : $shipping;
                                        // Ensure shipping_amount is a numeric value
                                        $shipping_amount = is_array($shipping_amount) ? 0 : (float)$shipping_amount;
                                        
                                        if (function_exists('wc_price')) {
                                            echo wp_kses_post(wc_price($shipping_amount));
                                        } else {
                                            echo esc_html('$' . number_format($shipping_amount, 2));
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if (!empty($subscription['taxTotal'])): ?>
                                <tr class="bocs-tax-row">
                                    <th colspan="3">Tax</th>
                                    <td data-title="<?php esc_attr_e('Tax', 'bocs-wordpress'); ?>">
                                        <?php 
                                        // Ensure taxTotal is a numeric value
                                        $tax_amount = is_array($subscription['taxTotal']) ? 0 : (float)$subscription['taxTotal'];
                                        
                                        if (function_exists('wc_price')) {
                                            echo wp_kses_post(wc_price($tax_amount));
                                        } else {
                                            echo esc_html('$' . number_format($tax_amount, 2));
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <tr class="bocs-total-row">
                                    <th colspan="3">Subscription Total</th>
                                    <td data-title="<?php esc_attr_e('Total', 'bocs-wordpress'); ?>">
                                        <?php 
                                        // Calculate the total from line items, shipping, tax, and discounts
                                        $calculated_total = $subtotal;
                                        
                                        // Add shipping if present
                                        if (!empty($shipping)) {
                                            $shipping_amount = isset($subscription['shippingTotal']) ? $subscription['shippingTotal'] : $shipping;
                                            // Ensure shipping_amount is a numeric value
                                            $shipping_amount = is_array($shipping_amount) ? 0 : (float)$shipping_amount;
                                            $calculated_total += $shipping_amount;
                                        }
                                        
                                        // Add tax if present
                                        if (!empty($subscription['taxTotal'])) {
                                            // Ensure taxTotal is a numeric value
                                            $tax_amount = is_array($subscription['taxTotal']) ? 0 : (float)$subscription['taxTotal'];
                                            $calculated_total += $tax_amount;
                                        }
                                        
                                        // Subtract discounts if present
                                        if (!empty($subscription['couponLines']) && is_array($subscription['couponLines'])) {
                                            foreach ($subscription['couponLines'] as $coupon) {
                                                if (!empty($coupon['discount'])) {
                                                    // Ensure discount is a numeric value
                                                    $discount_amount = is_array($coupon['discount']) ? 0 : (float)$coupon['discount'];
                                                    $calculated_total -= $discount_amount;
                                                }
                                            }
                                        }
                                        
                                        // Use the calculated total or fall back to the provided total if needed
                                        $display_total = $calculated_total > 0 ? $calculated_total : $total;
                                        
                                        if (function_exists('wc_price')) {
                                            echo wp_kses_post(wc_price($display_total));
                                        } else {
                                            echo esc_html('$' . number_format($display_total, 2));
                                        }
                                        ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <div class="bocs-no-subscriptions">
            <p><?php esc_html_e('You don\'t have any active subscriptions.', 'bocs-wordpress'); ?></p>
            <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="button"><?php esc_html_e('Browse products', 'bocs-wordpress'); ?></a>
        </div>
    <?php endif; ?>
</div>

<!-- Modals for subscription actions -->
<div id="bocs-edit-schedule-modal" class="bocs-modal">
    <div class="bocs-modal-content">
        <span class="bocs-modal-close">&times;</span>
        <h3>Edit Schedule</h3>
        <form id="edit-schedule-form">
            <div class="bocs-form-row">
                <label for="next-payment-date">Next Payment Date</label>
                <input type="date" id="next-payment-date" name="next_payment_date">
            </div>
            <div class="bocs-form-actions">
                <button type="button" class="bocs-button cancel">Cancel</button>
                <button type="submit" class="bocs-button primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div id="bocs-edit-frequency-modal" class="bocs-modal">
    <div class="bocs-modal-content">
        <span class="bocs-modal-close">&times;</span>
        <h3>Edit Frequency</h3>
        <form id="edit-frequency-form">
            <input type="hidden" id="frequency-id" name="frequency_id">
            <input type="hidden" id="time-unit" name="time_unit">
            
            <div class="bocs-form-row">
                <label for="frequency-value">Frequency</label>
                <select id="frequency-value" name="frequency_value">
                    <!-- Will be populated dynamically -->
                </select>
            </div>
            
            <div class="bocs-form-row">
                <label for="discount">Discount (%)</label>
                <input type="number" id="discount" name="discount" min="0" max="100" step="1" disabled>
            </div>
            
            <div class="bocs-form-row">
                <label for="discount-type">Discount Type</label>
                <select id="discount-type" name="discount_type" disabled>
                    <option value="percent">Percent</option>
                    <option value="fixed">Fixed Amount</option>
                </select>
            </div>
            
            <div class="bocs-form-actions">
                <button type="button" class="bocs-button cancel">Cancel</button>
                <button type="submit" class="bocs-button primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div id="bocs-edit-address-modal" class="bocs-modal">
    <div class="bocs-modal-content">
        <span class="bocs-modal-close">&times;</span>
        <h3>Edit Delivery Address</h3>
        <form id="edit-address-form">
            <div class="bocs-form-row">
                <label for="address">Street Address</label>
                <input type="text" id="address" name="address">
            </div>
            <div class="bocs-form-row">
                <label for="city">City</label>
                <input type="text" id="city" name="city">
            </div>
            <div class="bocs-form-row">
                <label for="state">State</label>
                <input type="text" id="state" name="state">
            </div>
            <div class="bocs-form-row">
                <label for="postcode">Postcode</label>
                <input type="text" id="postcode" name="postcode">
            </div>
            <div class="bocs-form-actions">
                <button type="button" class="bocs-button cancel">Cancel</button>
                <button type="submit" class="bocs-button primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Payment Method Modal -->
<div id="bocs-edit-payment-modal" class="bocs-modal">
    <div class="bocs-modal-content">
        <span class="bocs-modal-close">&times;</span>
        <h3>Edit Payment Method</h3>
        <form id="edit-payment-form">
            <div class="bocs-form-row">
                <label for="payment-method">Payment Method</label>
                <select id="payment-method" name="payment_method">
                    <!-- Will be populated dynamically -->
                </select>
            </div>
            
            <!-- Stripe Card Element - initially hidden -->
            <div id="stripe-payment-element-container" style="display: none;">
                <div class="bocs-form-row">
                    <label for="card-element">Credit or Debit Card</label>
                    <div id="card-element">
                        <!-- Stripe Card Element will be inserted here -->
                    </div>
                    <div id="card-errors" role="alert"></div>
                </div>
            </div>
            
            <div class="bocs-form-actions">
                <button type="button" class="bocs-button cancel">Cancel</button>
                <button type="submit" class="bocs-button primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div id="bocs-cancel-subscription-modal" class="bocs-modal">
    <div class="bocs-modal-content">
        <span class="bocs-modal-close">&times;</span>
        <h3>Cancel Subscription</h3>
        <p>Are you sure you want to cancel this subscription? This action cannot be undone.</p>
        <div class="bocs-modal-actions">
            <button class="bocs-button modal-cancel">No, Keep Subscription</button>
            <button class="bocs-button primary modal-confirm">Yes, Cancel Subscription</button>
        </div>
    </div>
</div> 