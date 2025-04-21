<?php
/**
 * Bocs Edit Details Template
 *
 * @package    Bocs
 * @subpackage Bocs/templates/myaccount
 * @since      1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue required styles and scripts
wp_enqueue_script('jquery');
wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
wp_enqueue_style('bocs-edit-details', BOCS_PLUGIN_URL . 'assets/css/bocs-edit-details.css', array(), '20250421.8');
wp_enqueue_script('bocs-edit-details', BOCS_PLUGIN_URL . 'assets/js/bocs-edit-details.js', array('jquery'), '20250421.2', true);

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

// Get the BOCS ID from the subscription
$bocs_id = '';
if (isset($subscription['bocs']['id']) && !empty($subscription['bocs']['id'])) {
    $bocs_id = $subscription['bocs']['id'];
} else {
    // Look in metadata for BOCS ID
    if (isset($subscription['metaData']) && is_array($subscription['metaData'])) {
        foreach ($subscription['metaData'] as $meta) {
            if (isset($meta['key']) && ($meta['key'] === '__bocs_bocs_id' || $meta['key'] === '_bocs_id')) {
                $bocs_id = $meta['value'];
                break;
            }
        }
    }
}

// Get BOCS data to retrieve all available products
$bocs_data = null;
$all_products = [];
$min_products = 0;
$max_products = 0;

if (!empty($bocs_id)) {
    // Debug log
    error_log('Fetching BOCS data for ID: ' . $bocs_id);
    
    $bocs_request_url = BOCS_API_URL . 'bocs/' . $bocs_id;
    
    // Log request details
    error_log('BOCS API Request URL: ' . $bocs_request_url);
    
    // Get API headers 
    $api_headers = [
        'Organization' => $options['bocs_headers']['organization'] ?? '',
        'Store' => $options['bocs_headers']['store'] ?? '',
        'Authorization' => $options['bocs_headers']['authorization'] ?? '',
        'Content-Type' => 'application/json'
    ];
    
    // Log headers (sanitize auth token)
    $log_headers = $api_headers;
    if (isset($log_headers['Authorization'])) {
        $log_headers['Authorization'] = substr($log_headers['Authorization'], 0, 10) . '...';
    }
    error_log('BOCS API Headers: ' . json_encode($log_headers));
    
    $bocs_response = $helper->curl_request(
        $bocs_request_url,
        'GET',
        [],
        $api_headers
    );
    
    // Log response status
    error_log('BOCS API Response received: ' . (isset($bocs_response['success']) ? 'Success' : 'Failed'));
    
    $bocs_data = isset($bocs_response['data']) ? $bocs_response['data'] : null;
    
    if ($bocs_data) {
        error_log('BOCS data retrieved successfully. Type: ' . ($bocs_data['type'] ?? 'unknown'));
        
        if (isset($bocs_data['products']) && is_array($bocs_data['products'])) {
            $all_products = $bocs_data['products'];
            error_log('Found ' . count($all_products) . ' products in BOCS data');
            
            // Format products for JavaScript
            foreach ($all_products as &$product) {
                $image_url = '';
                if (isset($product['images']) && !empty($product['images']) && !empty($product['images'][0]['url'])) {
                    $image_url = $product['images'][0]['url'];
                }
                
                // Add formatted price
                $product['price_html'] = $helper->format_price($product['price'] ?? 0, $subscription['currency'] ?? 'USD');
                $product['image'] = $image_url;
            }
        } else {
            error_log('No products found in BOCS data. Product key exists: ' . (isset($bocs_data['products']) ? 'Yes' : 'No'));
            if (isset($bocs_data['products'])) {
                error_log('Products is array: ' . (is_array($bocs_data['products']) ? 'Yes' : 'No'));
            }
        }
        
        // Get min/max products range
        if (isset($bocs_data['range']) && is_array($bocs_data['range']) && count($bocs_data['range']) >= 2) {
            $min_products = intval($bocs_data['range'][0]);
            $max_products = intval($bocs_data['range'][1]);
            error_log("Product range: min={$min_products}, max={$max_products}");
        } else {
            error_log('No product range found in BOCS data');
        }
    } else {
        error_log('Failed to retrieve BOCS data. Response: ' . json_encode($bocs_response));
    }
} else {
    error_log('No BOCS ID found for this subscription');
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

// Get the return URL for the main subscriptions page
$return_url = wc_get_account_endpoint_url('bocs-subscriptions');

// Check if this is a custom box type
$is_custom_box = false;
if ($bocs_data && isset($bocs_data['type'])) {
    $bocs_type = strtolower($bocs_data['type']);
    $is_custom_box = $bocs_type === 'custom';
    error_log('BOCS type (raw): "' . $bocs_data['type'] . '"');
    error_log('BOCS type (lowercase): "' . $bocs_type . '"');
    error_log('Is custom box: ' . ($is_custom_box ? 'Yes' : 'No'));
} else {
    $is_custom_box = false;
    error_log('BOCS type not found in data');
}

// Localize script for AJAX
wp_localize_script('bocs-edit-details', 'bocs_edit_details_data', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'subscription_id' => $subscription_id,
    'nonce' => wp_create_nonce('bocs_ajax_nonce'),
    'edit_details_nonce' => wp_create_nonce('bocs_edit_details_nonce'),
    'subscription_items' => $subscription['lineItems'] ?? [],
    'all_products' => $all_products,
    'is_custom_box' => $is_custom_box,
    'min_products' => $min_products,
    'max_products' => $max_products,
    'subscription_list_url' => $return_url
));

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
        
        <div class="subscription-actions">
            <button class="bocs-button bocs-pause-subscription-btn"><?php esc_html_e('Pause Subscription', 'bocs-wordpress'); ?></button>
        </div>
    </div>

    <!-- Products Section -->
    <div class="edit-section products-section">
        <div class="section-header">
            <h3><?php esc_html_e('Subscription Products', 'bocs-wordpress'); ?></h3>
            <?php if ($is_custom_box): ?>
            <button class="edit-button modify-products" data-subscription-id="<?php echo esc_attr($subscription_id); ?>">
                <?php esc_html_e('Modify Products', 'bocs-wordpress'); ?>
            </button>
            <?php else: ?>
            <span class="non-custom-box-info"><?php esc_html_e('Product selection not available for this subscription type', 'bocs-wordpress'); ?></span>
            <?php endif; ?>
        </div>
        
        <div class="section-content">
            <div class="bocs-order-details">
                <table class="bocs-order-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Product', 'bocs-wordpress'); ?></th>
                            <th><?php esc_html_e('Quantity', 'bocs-wordpress'); ?></th>
                            <th><?php esc_html_e('Price', 'bocs-wordpress'); ?></th>
                            <th><?php esc_html_e('Total', 'bocs-wordpress'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $subtotal = 0;
                    
                    // Properly access items from the API response structure
                    $items = [];
                    if (isset($subscription['lineItems']) && is_array($subscription['lineItems'])) {
                        $items = $subscription['lineItems'];
                    }
                    
                    if (!empty($items)): 
                        foreach ($items as $item): 
                            $product_id = isset($item['productId']) ? $item['productId'] : '';
                            $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
                            $price = isset($item['price']) ? floatval($item['price']) : 0;
                            $total = $quantity * $price;
                            $subtotal += $total;
                            
                            // Get product image if available
                            $image_url = '';
                            if (isset($item['images']) && is_array($item['images']) && !empty($item['images'])) {
                                // Try to get image URL directly from the first image in the array
                                if (isset($item['images'][0]['url'])) {
                                    $image_url = esc_url($item['images'][0]['url']);
                                }
                                // If url is not found but raw is present, use that
                                elseif (isset($item['images'][0]['raw'])) {
                                    $image_url = esc_url($item['images'][0]['raw']);
                                }
                            }
                            
                            // Look for image in all_products if still no image found
                            if (empty($image_url) && !empty($all_products)) {
                                // Find the product in all_products by ID
                                foreach ($all_products as $prod) {
                                    if (isset($prod['id']) && $prod['id'] === $product_id) {
                                        if (isset($prod['images']) && is_array($prod['images']) && !empty($prod['images'])) {
                                            if (isset($prod['images'][0]['url'])) {
                                                $image_url = esc_url($prod['images'][0]['url']);
                                            } elseif (isset($prod['images'][0]['raw'])) {
                                                $image_url = esc_url($prod['images'][0]['raw']);
                                            }
                                        }
                                        break;
                                    }
                                }
                            }
                            
                            // Use placeholder image if no image is available
                            if (empty($image_url)) {
                                $image_url = 'https://via.placeholder.com/150';
                            }
                        ?>
                        <tr data-product-id="<?php echo esc_attr($product_id); ?>">
                            <td class="product-name">
                                <?php if (!empty($image_url)): ?>
                                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($item['name'] ?? ''); ?>" class="product-thumbnail">
                                <?php endif; ?>
                                <?php echo esc_html($item['name'] ?? ''); ?>
                            </td>
                            <td class="product-quantity">
                                <span class="quantity-value"><?php echo esc_html($quantity); ?></span>
                            </td>
                            <td class="product-price">
                                <?php echo wp_kses_post(wc_price($price)); ?>
                            </td>
                            <td class="product-total">
                                <?php echo wp_kses_post(wc_price($total)); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e('No items found in this subscription.', 'bocs-wordpress'); ?></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bocs-subtotal-row">
                            <th colspan="3"><?php esc_html_e('Subtotal', 'bocs-wordpress'); ?></th>
                            <td data-title="<?php esc_attr_e('Subtotal', 'bocs-wordpress'); ?>">
                                <?php echo wp_kses_post(wc_price($subtotal)); ?>
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
                                            echo wp_kses_post(wc_price($coupon['discount'] * -1));
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if (!empty($subscription['shippingTotal'])): ?>
                        <tr class="bocs-shipping-row">
                            <th colspan="3"><?php esc_html_e('Shipping', 'bocs-wordpress'); ?></th>
                            <td data-title="<?php esc_attr_e('Shipping', 'bocs-wordpress'); ?>">
                                <?php echo wp_kses_post(wc_price($subscription['shippingTotal'])); ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if (!empty($subscription['taxTotal'])): ?>
                        <tr class="bocs-tax-row">
                            <th colspan="3"><?php esc_html_e('Tax', 'bocs-wordpress'); ?></th>
                            <td data-title="<?php esc_attr_e('Tax', 'bocs-wordpress'); ?>">
                                <?php echo wp_kses_post(wc_price($subscription['taxTotal'])); ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        
                        <tr class="bocs-total-row">
                            <th colspan="3"><?php esc_html_e('Subscription Total', 'bocs-wordpress'); ?></th>
                            <td data-title="<?php esc_attr_e('Total', 'bocs-wordpress'); ?>">
                                <?php echo wp_kses_post(wc_price($subscription['total'])); ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Product Selection Modal -->
<div class="bocs-modal" id="bocs-product-modal" aria-hidden="true">
    <div class="bocs-modal-overlay" tabindex="-1"></div>
    <div class="bocs-modal-container" role="dialog" aria-modal="true">
        <div class="bocs-modal-header">
            <h2>Select Products</h2>
            <div class="product-selection-info">
                Selected products: <span class="selected-product-count">0</span>
            </div>
            <button class="bocs-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="bocs-modal-content">
            <div id="product-selection">
                <div class="product-list product-selection-list">
                    <!-- Products will be populated here by JavaScript -->
                </div>
            </div>
        </div>
        <div class="bocs-modal-footer">
            <button type="button" class="bocs-modal-cancel bocs-button secondary">Cancel</button>
            <button type="button" class="bocs-save-products-btn bocs-button primary">
                <span class="button-text">Save Products</span>
                <span class="loading-spinner" style="display: none;">
                    <svg class="spinner" viewBox="0 0 50 50">
                        <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
                    </svg>
                </span>
            </button>
        </div>
    </div>
</div>

<!-- Add Pause Subscription Modal -->
<div id="bocs-pause-modal" class="bocs-modal">
    <div class="bocs-modal-overlay"></div>
    <div class="bocs-modal-container">
        <div class="bocs-modal-header">
            <h2><?php esc_html_e('Pause Subscription', 'bocs-wordpress'); ?></h2>
            <button class="bocs-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="bocs-modal-content">
            <p><?php esc_html_e('Update the schedule for your subscription by selecting a new date:', 'bocs-wordpress'); ?></p>
            <div class="bocs-form-row">
                <label for="next-payment-date"><?php esc_html_e('Next Payment Date:', 'bocs-wordpress'); ?></label>
                <input type="date" id="next-payment-date" name="next_payment_date" value="<?php echo esc_attr(date('Y-m-d', strtotime($next_payment_date))); ?>">
                <div class="field-description"><?php esc_html_e('Select a future date for your next payment', 'bocs-wordpress'); ?></div>
            </div>
            <div class="bocs-form-row">
                <label for="pause-reason"><?php esc_html_e('Reason for change (optional):', 'bocs-wordpress'); ?></label>
                <textarea id="pause-reason" name="pause_reason" rows="3"></textarea>
            </div>
        </div>
        <div class="bocs-modal-footer">
            <button type="button" class="bocs-modal-cancel bocs-button secondary"><?php esc_html_e('Cancel', 'bocs-wordpress'); ?></button>
            <button type="button" class="bocs-confirm-pause-btn bocs-button primary"><?php esc_html_e('Update Schedule', 'bocs-wordpress'); ?></button>
        </div>
    </div>
</div>
