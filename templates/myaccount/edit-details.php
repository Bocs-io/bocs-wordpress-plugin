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
wp_enqueue_style('bocs-edit-details', BOCS_PLUGIN_URL . 'assets/css/bocs-edit-details.css', array(), '20250428.3');
wp_enqueue_script('bocs-edit-details', BOCS_PLUGIN_URL . 'assets/js/bocs-edit-details.js', array('jquery'), bocs_get_cache_bust_version('20250425.2'), true);

// Add order line items component
wp_enqueue_style('bocs-order-line-items', BOCS_PLUGIN_URL . 'assets/css/bocs-order-line-items.css', array(), bocs_get_cache_bust_version('20250425.1'));
wp_enqueue_script('bocs-order-line-items', BOCS_PLUGIN_URL . 'assets/js/bocs-order-line-items.js', array('jquery'), "20250425.10", true);

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
    
    $bocs_request_url = BOCS_API_URL . 'bocs/' . $bocs_id;
    
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
    
    $bocs_response = $helper->curl_request(
        $bocs_request_url,
        'GET',
        [],
        $api_headers
    );
    
    $bocs_data = isset($bocs_response['data']) ? $bocs_response['data'] : null;
    
    if ($bocs_data) {
        if (isset($bocs_data['products']) && is_array($bocs_data['products'])) {
            $all_products = $bocs_data['products'];
            
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
        }
        
        // Get min/max products range
        if (isset($bocs_data['range']) && is_array($bocs_data['range']) && count($bocs_data['range']) >= 2) {
            $min_products = intval($bocs_data['range'][0]);
            $max_products = intval($bocs_data['range'][1]);
        }
    }
}

// Format subscription status for display
$status = strtolower($subscription['subscriptionStatus'] ?? '');
$status_class = 'status-' . $status;
$status_label = ucfirst($status);

// Get WooCommerce tax settings
$prices_include_tax = get_option('woocommerce_prices_include_tax', 'no') === 'yes';
$tax_display_shop = get_option('woocommerce_tax_display_shop', 'excl');
$tax_display_cart = get_option('woocommerce_tax_display_cart', 'excl');

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
} else {
    $is_custom_box = false;
}

// Localize scripts
wp_localize_script('bocs-edit-details', 'bocs_edit_details_data', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('bocs-ajax-nonce'),
    'edit_details_nonce' => wp_create_nonce('bocs_edit_details_nonce'),
    'subscription_id' => $subscription_id,
    'subscription_items' => $subscription['lineItems'] ?? [],
    'all_products' => $all_products,
    'is_custom_box' => $is_custom_box,
    'min_products' => $min_products,
    'max_products' => $max_products,
    'subscription_list_url' => $return_url,
    'is_admin' => current_user_can('manage_options'),
    'box_title' => isset($box_title) ? $box_title : 'Custom Box',
    'api_url' => BOCS_API_URL
));

// Pass data specifically for order line items component
wp_localize_script('bocs-order-line-items', 'bocs_data', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('bocs-ajax-nonce'),
    'is_admin' => current_user_can('manage_options')
));

// Add this script to ensure WooCommerce product IDs are added to BOCS products
?>
<script>
// Map WooCommerce product IDs to BOCS products if available
document.addEventListener('DOMContentLoaded', function() {
    // Add logging for debugging
    
    // Get saved product mappings from PHP if available
    const savedProductMappings = <?php 
        $product_mapping = get_option('bocs_product_mapping', []); 
        echo json_encode($product_mapping);
    ?>;
    
    // Get all product items in the table
    const tableRows = document.querySelectorAll('.bocs-order-table tbody tr[data-product-id]');
    
    // Create a mapping of BOCS product IDs to WooCommerce product IDs
    const productMapping = {};
    
    // Process each product row
    tableRows.forEach(function(row) {
        const bocsProductId = row.getAttribute('data-product-id');
        let wcProductId = row.getAttribute('data-wc-product-id');
        
        // If the row has a WC product ID, use it
        if (bocsProductId && wcProductId) {
            productMapping[bocsProductId] = wcProductId;
            console.log('Found WooCommerce ID in DOM for BOCS product: ' + bocsProductId + ' -> ' + wcProductId);
        }
        // Check if we have it in saved mappings
        else if (bocsProductId && savedProductMappings && savedProductMappings[bocsProductId]) {
            productMapping[bocsProductId] = savedProductMappings[bocsProductId];
            console.log('Using saved mapping for BOCS product: ' + bocsProductId + ' -> ' + savedProductMappings[bocsProductId]);
            
            // Add the data attribute to the row for future reference
            row.setAttribute('data-wc-product-id', savedProductMappings[bocsProductId]);
        }
    });
    
    // Merge mappings and save to window 
    window.bocsToWcProductMapping = {...savedProductMappings, ...productMapping};
    
    <?php
    // Save the mappings from the current subscription to the database
    if (!empty($subscription['lineItems'])) {
        echo "// Auto-update mappings from current subscription items\n";
        echo "const currentSubscriptionItems = " . json_encode($subscription['lineItems']) . ";\n";
        echo "
        // Check for items with both productId and externalSourceId
        if (currentSubscriptionItems && currentSubscriptionItems.length) {
            let updatedMappings = false;
            const updatedItems = [];
            
            currentSubscriptionItems.forEach(function(item) {
                if (item.productId && item.externalSourceId) {
                    // Update our local mapping
                    window.bocsToWcProductMapping[item.productId] = item.externalSourceId;
                    updatedMappings = true;
                    updatedItems.push({
                        bocs_id: item.productId,
                        wc_id: item.externalSourceId,
                        name: item.name || ''
                    });
                }
            });
            
            if (updatedMappings) {
                console.log('Updated mappings from subscription items:', updatedItems);
                
                // Send updated mappings back to server to save
                fetch('" . admin_url('admin-ajax.php') . "', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'bocs_update_product_mappings',
                        nonce: '" . wp_create_nonce('bocs_update_mappings_nonce') . "',
                        mappings: JSON.stringify(window.bocsToWcProductMapping)
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Product mappings saved successfully');
                    } else {
                        console.error('Failed to save product mappings', data);
                    }
                })
                .catch(error => {
                    console.error('Error saving product mappings:', error);
                });
            }
        }
        ";
    }
    ?>
});
</script>

<div class="bocs-edit-details-container">
    <a href="<?php echo esc_url($return_url); ?>" class="bocs-back-link">
        <?php esc_html_e('← Back to My Subscriptions', 'bocs-wordpress'); ?>
    </a>
    
    <h2>
        <?php 
        esc_html_e('Edit Products', 'bocs-wordpress');
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
                    <?php 
            // Set up data for the order line items component
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
            $shipping = isset($subscription['shipping']) ? $subscription['shipping'] : 0;
            $tax = isset($subscription['taxTotal']) ? $subscription['taxTotal'] : 0;
            $total = isset($subscription['total']) ? $subscription['total'] : 0;
            $coupon_lines = isset($subscription['couponLines']) ? $subscription['couponLines'] : array();
            
            // Pass the frequency object to make discount percentage available
            $frequency = isset($subscription['frequency']) ? $subscription['frequency'] : array();
            $discount_type = isset($frequency['discountType']) ? $frequency['discountType'] : '';
            $discount_percent = isset($frequency['discount']) ? $frequency['discount'] : '';
            
            // Include the order line items component
            include(dirname(dirname(__FILE__)) . '/components/order-line-items.php');
            ?>
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
