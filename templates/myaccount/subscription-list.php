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

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Ensure script and style dependencies are loaded
wp_enqueue_style('bocs-subscriptions', BOCS_PLUGIN_URL . 'assets/css/bocs-subscriptions.css', array(), "20250428.2");
wp_enqueue_script('bocs-subscriptions', BOCS_PLUGIN_URL . 'assets/js/bocs-subscriptions.js', array('jquery'), "20250429.2", true);

// Add order line items component
wp_enqueue_style('bocs-order-line-items', BOCS_PLUGIN_URL . 'assets/css/bocs-order-line-items.css', array(), bocs_get_cache_bust_version('20250425.1'));
wp_enqueue_script('bocs-order-line-items', BOCS_PLUGIN_URL . 'assets/js/bocs-order-line-items.js', array('jquery'), "20250425.10", true);

// Add Stripe JS if available
if (class_exists('WC_Gateway_Stripe') && function_exists('wc_stripe_get_publishable_key')) {
    wp_enqueue_script('stripe', 'https://js.stripe.com/v3/', [], null, true);
    
    // Add inline script to set publishable key
    wp_add_inline_script('stripe', 'window.stripePublishableKey = "' . wc_stripe_get_publishable_key() . '";', 'after');
    
    // Initialize stripe for the payment methods
    wp_add_inline_script('bocs-subscriptions', '
        // Ensure Stripe is properly initialized
        document.addEventListener("DOMContentLoaded", function() {
            if (typeof Stripe !== "undefined") {
                if (window.stripePublishableKey) {
                    console.log("Pre-initializing Stripe with publishable key");
                    window.stripe = Stripe(window.stripePublishableKey);
                } else {
                    console.error("Stripe publishable key not found");
                }
            } else {
                console.error("Stripe.js not loaded");
            }
        });
    ', 'after');
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

// Pass data to JavaScript
wp_localize_script('bocs-subscriptions', 'bocsSubscriptionsData', array(
    'subscriptions' => array_map(function($sub) {
        // Add frequencies to each subscription if available
        if (!isset($sub['bocs'])) {
            $sub['bocs'] = array();
        }
        
        // Ensure bocs has a frequencies array
        if (!isset($sub['bocs']['frequencies'])) {
            $sub['bocs']['frequencies'] = array();
            
            // Try to get frequencies from price adjustments if available
            if (isset($sub['bocs']['priceAdjustment']['adjustments']) && 
                is_array($sub['bocs']['priceAdjustment']['adjustments'])) {
                $sub['bocs']['frequencies'] = $sub['bocs']['priceAdjustment']['adjustments'];
            }
        }
        
        return $sub;
    }, $subscriptions_formatted),
    'apiUrl' => BOCS_API_URL,
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('bocs-ajax-nonce'),
    'headers' => array(
        'organization' => isset($options['bocs_headers']['organization']) ? $options['bocs_headers']['organization'] : '',
        'store' => isset($options['bocs_headers']['store']) ? $options['bocs_headers']['store'] : '',
        'authorization' => isset($options['bocs_headers']['authorization']) ? $options['bocs_headers']['authorization'] : '',
    ),
    'redirectUrl' => wc_get_endpoint_url('bocs-subscriptions'),
    'orderEndpoint' => wc_get_endpoint_url('bocs-edit-details', ''),
    'updateBoxEndpoint' => wc_get_endpoint_url('bocs-update-box', ''),
    'viewSubscriptionEndpoint' => wc_get_endpoint_url('bocs-view-subscription', ''),
    'i18n' => array(
        'processing' => __('Processing...', 'bocs-wordpress'),
        'saveChanges' => __('Save Changes', 'bocs-wordpress'),
        'loading' => __('Loading...', 'bocs-wordpress')
    )
));

// Pass data specifically for order line items component
wp_localize_script('bocs-order-line-items', 'bocs_data', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('bocs-ajax-nonce'),
    'is_admin' => current_user_can('manage_options')
));

// Add console logging for frequencies in the JavaScript
wp_add_inline_script('bocs-subscriptions', '
// Log subscription data for debugging frequencies
console.log("BOCS Subscriptions Data:", bocsSubscriptionsData);
bocsSubscriptionsData.subscriptions.forEach(function(sub) {
    console.log("Subscription " + sub.id + " BOCS frequencies:", sub.bocs.frequencies);
});
', 'after');

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
                
                // Get BOCS frequencies if available
                $bocs_frequencies = [];
                if (isset($subscription['bocs']['frequencies']) && is_array($subscription['bocs']['frequencies'])) {
                    $bocs_frequencies = $subscription['bocs']['frequencies'];
                } elseif (isset($subscription['bocs']['priceAdjustment']['adjustments']) && is_array($subscription['bocs']['priceAdjustment']['adjustments'])) {
                    $bocs_frequencies = $subscription['bocs']['priceAdjustment']['adjustments'];
                }
                
                // Log frequencies for debugging if Bocs_Log_Handler exists
                if (class_exists('Bocs_Log_Handler') && !empty($subscription['id'])) {
                    $logger = new Bocs_Log_Handler();
                    $logger->insert_log('debug', '[Subscription List] Frequencies for subscription ' . $subscription['id'], [
                        'bocs_frequencies' => !empty($bocs_frequencies) ? json_encode($bocs_frequencies) : 'None found',
                        'bocs_id' => isset($subscription['bocs']['id']) ? $subscription['bocs']['id'] : 'None'
                    ]);
                }
                
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
                $coupon_lines = isset($subscription['couponLines']) ? $subscription['couponLines'] : array();
                
                // Make sure discount_type and discount_percent are set for order-line-items component
                $discount_type = isset($subscription['discountType']) ? $subscription['discountType'] : 'percent';
                $discount_percent = isset($subscription['discount']) ? $subscription['discount'] : '';
                
                // For debugging
                if (current_user_can('manage_options')) {
                    echo '<!-- DEBUG: Items count: ' . count($items) . ' -->';
                    if (count($items) === 0) {
                        echo '<!-- DEBUG: Items sources check: lineItems=' . (isset($subscription['lineItems']) ? 'yes' : 'no') . ', items=' . (isset($subscription['items']) ? 'yes' : 'no') . ' -->';
                        // Check the entire subscription structure
                        echo '<!-- DEBUG: Subscription keys: ' . implode(', ', array_keys($subscription)) . ' -->';
                    }
                }
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
                        <button onclick="window.location.href='<?php echo esc_url(wc_get_account_endpoint_url('bocs-edit-details') . $subscription_id); ?>'" class="bocs-button edit-contents">Edit contents</button>
                        <button onclick="window.location.href='<?php echo esc_url(wc_get_account_endpoint_url('bocs-switch-bocs') . $subscription_id); ?>'" class="bocs-button change-box">Change box</button>
                        <button class="bocs-button early-renewal" id="early-renewal-<?php echo esc_attr($subscription_id); ?>" data-sub-id="<?php echo esc_attr($subscription_id); ?>">Early Renewal</button>
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
                                            if (!empty($discount)) {
                                                // Check if discount is a percentage or fixed amount
                                                // Note: We're getting this from subscription data but not overwriting the global discount_type
                                                $freq_discount_type = isset($subscription['discountType']) ? $subscription['discountType'] : 'percent';
                                                if ($freq_discount_type === 'fixed') {
                                                    echo ' ($' . esc_html($discount) . ' discount)';
                                                } else {
                                                    echo ' (' . esc_html($discount) . '% discount)';
                                                }
                                            }
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
                    
                    <?php
                    // Prepare the payment URL for any upcoming orders
                    $payment_url = '';
                    if (strtolower($status) === 'upcoming') {
                                $bocs_account = new Bocs_Account();
                                $payment_url = $bocs_account->get_pending_order_url($subscription_id);
                    }
                    
                    // Set up data for our reusable component
                    $component_id = 'bocs-order-items-' . $subscription_id;
                    
                    // Check for lineItems first (API-style keys)
                    if (isset($subscription['lineItems']) && is_array($subscription['lineItems'])) {
                        $items = $subscription['lineItems'];
                                                }
                    // Fall back to items format (formatted data keys)
                    elseif (isset($subscription['items']) && is_array($subscription['items'])) {
                        $items = $subscription['items'];
                                        } else {
                        $items = array();
                                        }
                    
                    $subtotal = isset($subscription['subtotal']) ? $subscription['subtotal'] : 0;
                    $discount = isset($subscription['discount']) ? $subscription['discount'] : 0;
                    $shipping = isset($subscription['shipping']) ? $subscription['shipping'] : '5.00';
                    $tax = isset($subscription['taxTotal']) ? $subscription['taxTotal'] : 0;
                    $total = isset($subscription['total']) ? $subscription['total'] : $price;
                    $coupon_lines = isset($subscription['couponLines']) ? $subscription['couponLines'] : array();
                    
                    // Make sure discount_type and discount_percent are set for order-line-items component
                    $discount_type = isset($subscription['discountType']) ? $subscription['discountType'] : 'percent';
                    $discount_percent = isset($subscription['discount']) ? $subscription['discount'] : '';
                    
                    // For debugging
                    if (current_user_can('manage_options')) {
                        echo '<!-- DEBUG: Items count: ' . count($items) . ' -->';
                        if (count($items) === 0) {
                            echo '<!-- DEBUG: Items sources check: lineItems=' . (isset($subscription['lineItems']) ? 'yes' : 'no') . ', items=' . (isset($subscription['items']) ? 'yes' : 'no') . ' -->';
                            // Check the entire subscription structure
                            echo '<!-- DEBUG: Subscription keys: ' . implode(', ', array_keys($subscription)) . ' -->';
                        }
                    }
                    
                    // Check if any products have empty names
                    $has_empty_products = false;
                    foreach ($items as $item) {
                        if (empty($item['name']) || $item['name'] == '0' || $item['name'] == 'Unknown product') {
                            $has_empty_products = true;
                            break;
                        }
                    }
                    
                    // Add a wrapper div with loading state and unique ID
                    $wrapper_id = 'bocs-order-details-wrapper-' . esc_attr($subscription_id);
                    echo '<div class="bocs-order-details-wrapper" id="' . $wrapper_id . '" data-subscription-id="' . esc_attr($subscription_id) . '" data-needs-loading="' . ($has_empty_products ? 'true' : 'false') . '">';
                    
                    if ($has_empty_products) {
                        // Show loading indicator only if we have empty products
                        echo '<div class="bocs-order-details-loading">Retrieving product information...</div>';
                        echo '<div class="bocs-order-details-content" style="display:none;">';
                    } else {
                        // Show content immediately if all products have names
                        echo '<div class="bocs-order-details-content" style="opacity:1; visibility:visible;">';
                    }
                    
                    // Include the line items component within a buffer to prevent partial display
                    ob_start();
                    include(dirname(dirname(__FILE__)) . '/components/order-line-items.php');
                    $order_content = ob_get_clean();
                    
                    // Only replace empty product names if we need to
                    if ($has_empty_products) {
                        // Replace any "Unknown product" or empty product names with a placeholder
                        $order_content = preg_replace('/<td class="product-name"[^>]*>\s*(?:Unknown product|0)?\s*<\/td>/', '<td class="product-name"><span class="product-placeholder"></span></td>', $order_content);
                        
                        // Hide the entire bocs-order-details div initially
                        $order_content = str_replace('<div class="bocs-order-details"', '<div class="bocs-order-details" style="display:none;"', $order_content);
                    }
                    
                    // Output the buffered and modified content
                    echo $order_content;
                    
                    // Close wrapper divs
                    echo '</div>'; // End of content div
                    echo '</div>'; // End of wrapper div
                    ?>
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
                <button type="button" class="bocs-button pause-subscription" id="pause-button">Pause Subscription</button>
                <button type="submit" class="bocs-button primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal for edit frequency -->
<div id="bocs-edit-frequency-modal" class="bocs-modal">
    <div class="bocs-modal-content">
        <span class="bocs-modal-close">&times;</span>
        <h3>Edit Frequency</h3>
        <form id="edit-frequency-form">
            <input type="hidden" id="frequency-id" name="frequency_id">
            <input type="hidden" id="time-unit" name="time_unit">
            <input type="hidden" id="discount" name="discount">
            <input type="hidden" id="discount-type" name="discount_type">
            
            <div class="bocs-form-row">
                <label for="frequency-value">Frequency</label>
                <select id="frequency-value" name="frequency_value">
                    <!-- Will be populated dynamically -->
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
            <div class="form-row-container">
                <div class="bocs-form-row half-width">
                    <label for="first-name">First Name</label>
                    <input type="text" id="first-name" name="first_name">
                </div>
                <div class="bocs-form-row half-width">
                    <label for="last-name">Last Name</label>
                    <input type="text" id="last-name" name="last_name">
                </div>
            </div>
            
            <div class="form-row-container">
                <div class="bocs-form-row half-width">
                    <label for="company">Company (optional)</label>
                    <input type="text" id="company" name="company">
                </div>
                <div class="bocs-form-row half-width">
                    <label for="phone">Phone (optional)</label>
                    <input type="tel" id="phone" name="phone">
                </div>
            </div>
            
            <div class="bocs-form-row">
                <label for="address">Address 1</label>
                <input type="text" id="address" name="address">
            </div>
            <div class="bocs-form-row">
                <label for="address2">Address 2 (optional)</label>
                <input type="text" id="address2" name="address2">
            </div>
            
            <div class="form-row-container">
                <div class="bocs-form-row half-width">
                    <label for="country">Country</label>
                    <select id="country" name="country">
                        <option value="AU">Australia</option>
                        <option value="NZ">New Zealand</option>
                        <option value="US">United States</option>
                        <option value="GB">United Kingdom</option>
                    </select>
                </div>
                <div class="bocs-form-row half-width">
                    <label for="state">State</label>
                    <select id="state" name="state">
                        <option value="VIC">Victoria</option>
                        <option value="NSW">New South Wales</option>
                        <option value="QLD">Queensland</option>
                        <option value="WA">Western Australia</option>
                        <option value="SA">South Australia</option>
                        <option value="TAS">Tasmania</option>
                        <option value="ACT">Australian Capital Territory</option>
                        <option value="NT">Northern Territory</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row-container">
                <div class="bocs-form-row half-width">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city">
                </div>
                <div class="bocs-form-row half-width">
                    <label for="postcode">Post Code</label>
                    <input type="text" id="postcode" name="postcode">
                </div>
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

<!-- Early Renewal Modal -->
<div id="bocs-early-renewal-modal" class="bocs-modal">
    <div class="bocs-modal-content">
        <span class="bocs-modal-close">&times;</span>
        <h3>Early Renewal</h3>
        <p>This will create an order with all the products in your subscription, and will automatically move your next order date.</p>
        <div class="bocs-modal-actions">
            <button class="bocs-button modal-cancel">Cancel</button>
            <button class="bocs-button primary modal-confirm">Confirm Early Renewal</button>
        </div>
    </div>
</div>

<!-- Pause Subscription Modal -->
<div id="bocs-pause-subscription-modal" class="bocs-modal">
    <div class="bocs-modal-content">
        <span class="bocs-modal-close">&times;</span>
        <h3>Pause Subscription</h3>
        <p>This will pause your subscription. You won't be charged until you resume your subscription.</p>
        <div class="bocs-form-row">
            <label for="pause-reason">Reason for pausing (optional)</label>
            <select id="pause-reason" name="pause_reason">
                <option value="">Select a reason...</option>
                <option value="going_away">Going away/vacation</option>
                <option value="too_many">Have too many products right now</option>
                <option value="financial">Financial reasons</option>
                <option value="other">Other reason</option>
            </select>
        </div>
        <div class="bocs-form-row">
            <label for="pause-until-date">Resume on (optional)</label>
            <input type="date" id="pause-until-date" name="pause_until_date">
        </div>
        <div class="bocs-modal-actions">
            <button class="bocs-button modal-cancel">Cancel</button>
            <button class="bocs-button primary modal-confirm">Confirm Pause</button>
        </div>
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