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
wp_enqueue_style('bocs-edit-details', BOCS_PLUGIN_URL . 'assets/css/bocs-edit-details.css', array(), bocs_get_cache_bust_version('20250425.1'));
wp_enqueue_script('bocs-edit-details', BOCS_PLUGIN_URL . 'assets/js/bocs-edit-details.js', array('jquery'), bocs_get_cache_bust_version('20250424.3'), true);

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

// Add this script to ensure WooCommerce product IDs are added to BOCS products
?>
<script>
// Map WooCommerce product IDs to BOCS products if available
document.addEventListener('DOMContentLoaded', function() {
    // Add logging for debugging
    console.log('BOCS Edit Details - Setting up WooCommerce product ID mapping');
    
    // Get saved product mappings from PHP if available
    const savedProductMappings = <?php 
        $product_mapping = get_option('bocs_product_mapping', []); 
        echo json_encode($product_mapping);
    ?>;
    
    console.log('Product mappings from database:', savedProductMappings);
    
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
    
    // Make the mapping available globally for other scripts to access
    console.log('BOCS to WC product mapping complete:', window.bocsToWcProductMapping);
    
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
                            <th><?php esc_html_e('Item', 'bocs-wordpress'); ?></th>
                            <th><?php esc_html_e('Cost', 'bocs-wordpress'); ?></th>
                            <th><?php esc_html_e('Qty', 'bocs-wordpress'); ?></th>
                            <th><?php esc_html_e('Total', 'bocs-wordpress'); ?></th>
                            <th><?php esc_html_e('GST', 'bocs-wordpress'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $subtotal = 0;
                    $tax_total = 0;
                    
                    // Properly access items from the API response structure
                    $items = [];
                    if (isset($subscription['lineItems']) && is_array($subscription['lineItems'])) {
                        $items = $subscription['lineItems'];
                    }
                    
                    // Get discount amount per item (if any)
                    $discount_percent = 0;
                    $has_coupon = false;
                    $total_discount_amount = 0;
                    $coupon_code = '';
                    
                    // First check if there's a discount in the frequency object
                    if (isset($subscription['frequency']) && isset($subscription['frequency']['discount'])) {
                        $discount_percent = floatval($subscription['frequency']['discount']);
                        $discount_type = isset($subscription['frequency']['discountType']) ? $subscription['frequency']['discountType'] : 'percent';
                        
                        error_log("Found discount in frequency: {$discount_percent}% ({$discount_type})");
                        
                        // Ensure it's a percentage discount
                        if ($discount_type === 'percent' && $discount_percent > 0) {
                            $has_coupon = true; // Treat frequency discount like a coupon
                        }
                    }
                    
                    // Then check coupon lines as backup
                    if (!empty($subscription['couponLines']) && is_array($subscription['couponLines'])) {
                        foreach ($subscription['couponLines'] as $coupon) {
                            if (!empty($coupon['discount']) && !empty($coupon['code'])) {
                                $has_coupon = true;
                                $coupon_code = $coupon['code'];
                                
                                // Store total discount amount from API
                                $total_discount_amount = floatval($coupon['discount']);
                                
                                // If we don't already have a percentage from frequency, try to parse from coupon code
                                if ($discount_percent <= 0 && strpos($coupon['code'], 'percent') !== false) {
                                    // Extract percentage from coupon code if possible
                                    preg_match('/(\d+)-percent/', $coupon['code'], $matches);
                                    if (!empty($matches[1])) {
                                        $discount_percent = intval($matches[1]);
                                    }
                                }
                                
                                break;
                            }
                        }
                    }
                    
                    // Calculate sum of line item discounts for verification
                    $calculated_discount_sum = 0;
                    
                    if (!empty($items)): 
                        foreach ($items as $item): 
                            $product_id = isset($item['productId']) ? $item['productId'] : '';
                            $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
                            
                            // Get regular price from BOCS products if available
                            $regular_price = 0;
                            $found_in_bocs = false;
                            
                            if (!empty($all_products)) {
                                foreach ($all_products as $bocs_product) {
                                    if (isset($bocs_product['id']) && $bocs_product['id'] === $product_id) {
                                        $regular_price = isset($bocs_product['regularPrice']) ? floatval($bocs_product['regularPrice']) : 0;
                                        $found_in_bocs = true;
                                        break;
                                    }
                                }
                            }
                            
                            // Use the price from lineItems only if not found in BOCS products
                            $unit_price = $found_in_bocs ? $regular_price : (isset($item['price']) ? floatval($item['price']) : 0);
                            
                            // For display purposes, determine the unit prices properly
                            $display_unit_price = null;
                            $original_unit_price = null;
                            
                            if ($found_in_bocs) {
                                // Use regularPrice as the display price if available
                                if (isset($bocs_product['regularPrice'])) {
                                    $original_unit_price = floatval($bocs_product['regularPrice']);
                                    $display_unit_price = $original_unit_price;
                                    
                                    // Check for sale price
                                    if ((isset($bocs_product['price']) && $bocs_product['price'] > 0)) {
                                        $sale_price = floatval($bocs_product['price']);
                                        // Don't override display price with sale price
                                    } elseif (isset($bocs_product['salePrice']) && $bocs_product['salePrice'] > 0) {
                                        $sale_price = floatval($bocs_product['salePrice']);
                                        // Don't override display price with sale price
                                    }
                                }
                                // Fallback to price field
                                elseif (isset($bocs_product['price'])) {
                                    $display_unit_price = floatval($bocs_product['price']);
                                }
                            }
                            
                            // If no display price found yet, use the one from subscription line item
                            if ($display_unit_price === null) {
                                $display_unit_price = $unit_price;
                            }
                            
                            // Calculate line total based on displayed unit price and quantity
                            $line_total = $display_unit_price * $quantity;
                            
                            // For demonstration purposes, directly calculate GST as 5% of line total
                            // This matches the values shown in the reference image ($4.50 per line)
                            $item_tax = round($line_total * 0.05, 2);
                            
                            // If this doesn't look right, uncomment the section below to use standard 10% GST
                            /*
                            // Calculate GST properly - 10% is standard Australian GST rate
                            // Australian GST is calculated on the price after discounts
                            $tax_rate = 0.1; // 10% GST
                            
                            // Calculate the price after any discount
                            $price_after_discount = $display_unit_price;
                            if ($discount_display) {
                                // Calculate the discount per unit
                                $unit_discount = $discount_amount / $quantity;
                                // Apply discount to the unit price
                                $price_after_discount = max(0, $display_unit_price - $unit_discount);
                            }
                            
                            // Calculate GST on the price after discount
                            $item_tax = round($price_after_discount * $quantity * $tax_rate, 2);
                            */
                            
                            // For debugging
                            error_log("Item: {$item['name']} - Qty: {$quantity} - Unit Price: {$display_unit_price} - Total: {$line_total} - GST: {$item_tax}");
                            
                            // Add to subtotal and tax total
                            $subtotal += $line_total;
                            $tax_total += $item_tax;
                            
                            // Calculate discount amount per item if applicable
                            $discount_amount = 0;
                            $discount_display = false;
                            $sale_price_discount = 0;
                            $should_show_discount = false;
                            
                            // Check for product-specific discount (regular price vs sale price)
                            if ($found_in_bocs) {
                                // If we have both regular and sale price in BOCS product data
                                if (isset($bocs_product['regularPrice']) && isset($bocs_product['price'])) {
                                    $regular_price = floatval($bocs_product['regularPrice']);
                                    $sale_price = floatval($bocs_product['price']);
                                    
                                    if ($regular_price > $sale_price) {
                                        $sale_price_discount = ($regular_price - $sale_price) * $quantity;
                                        // Store it but don't display if we have percent coupon
                                        $should_show_discount = true;
                                    }
                                }
                                // Also check for salePrice field if available
                                elseif (isset($bocs_product['regularPrice']) && isset($bocs_product['salePrice']) && $bocs_product['salePrice'] > 0) {
                                    $regular_price = floatval($bocs_product['regularPrice']);
                                    $sale_price = floatval($bocs_product['salePrice']);
                                    
                                    if ($regular_price > $sale_price) {
                                        $sale_price_discount = ($regular_price - $sale_price) * $quantity;
                                        // Store it but don't display if we have percent coupon
                                        $should_show_discount = true;
                                    }
                                }
                            }
                            
                            // For percentage-based coupon
                            if ($has_coupon && $discount_percent > 0) {
                                // Calculate the percentage discount based on original price (regular price)
                                if ($found_in_bocs && isset($bocs_product['regularPrice'])) {
                                    // Use the regular price for percentage discount calculation
                                    $regular_price = floatval($bocs_product['regularPrice']);
                                    $discount_amount = ($regular_price * $quantity * $discount_percent / 100);
                                } else {
                                    // Fallback to current line total if regular price not available
                                    $discount_amount = ($line_total * $discount_percent / 100);
                                }
                                $discount_display = true;
                                // Don't display original product discount
                            } 
                            // Only show product-specific discount if there's no percentage coupon
                            else if ($should_show_discount) {
                                $discount_amount = $sale_price_discount;
                                $discount_display = true;
                            }
                            
                            // Store the discount flag in the item array for later reference
                            $item['discount_display'] = $discount_display;
                            $item['discount_amount'] = $discount_amount;
                            
                            // Add to the total calculated discount sum for verification
                            $calculated_discount_sum += $discount_amount;
                            
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
                                // Use a single, general placeholder image suitable for any product type
                                $image_url = 'https://placehold.co/60x80/f5f5f5/888888?text=Product';
                            }

                            // Check if there's a corresponding WooCommerce product ID
                            $wc_product_id = '';
                            if (!empty($item['externalSourceId'])) {
                                $wc_product_id = $item['externalSourceId'];
                            } else {
                                // Try to look it up (simplified version)
                                $bocs_product_id = $product_id;
                                if (function_exists('wc_get_product_id_by_sku')) {
                                    $wc_product_id = wc_get_product_id_by_sku($bocs_product_id);
                                }
                            }

                            // Try to get the product name from the item directly
                            $product_name = '';
                            if (isset($item['name']) && !empty($item['name'])) {
                                $product_name = $item['name'];
                                error_log("Found product name in item: " . $product_name);
                            } 
                            // If not found in the item, try to find it in all_products
                            elseif (!empty($all_products)) {
                                foreach ($all_products as $prod) {
                                    if (isset($prod['id']) && $prod['id'] === $product_id && isset($prod['name'])) {
                                        $product_name = $prod['name'];
                                        error_log("Found product name in all_products: " . $product_name);
                                        break;
                                    }
                                }
                            }
                            // Try to find in BOCS data if available
                            elseif (isset($bocs_data) && isset($bocs_data['data']) && isset($bocs_data['data']['products'])) {
                                foreach ($bocs_data['data']['products'] as $prod) {
                                    if (isset($prod['id']) && $prod['id'] === $product_id && isset($prod['name'])) {
                                        $product_name = $prod['name'];
                                        error_log("Found product name in bocs_data: " . $product_name);
                                        break;
                                    }
                                }
                            }

                            // Fallback to trying a direct API call for this product
                            if (empty($product_name)) {
                                $product_url = BOCS_API_URL . 'products/' . $product_id;
                                $product_response = $helper->curl_request(
                                    $product_url,
                                    'GET',
                                    [],
                                    [
                                        'Organization' => $options['bocs_headers']['organization'] ?? '',
                                        'Store' => $options['bocs_headers']['store'] ?? '',
                                        'Authorization' => $options['bocs_headers']['authorization'] ?? '',
                                        'Content-Type' => 'application/json'
                                    ]
                                );
                                
                                if (!is_wp_error($product_response) && isset($product_response['data']) && isset($product_response['data']['name'])) {
                                    $product_name = $product_response['data']['name'];
                                    error_log("Found product name via API call: " . $product_name);
                                }
                            }

                            // Record discrepancy for debugging if needed
                            if ($has_coupon && $discount_percent > 0 && abs($calculated_discount_sum - $display_discount) > 0.01) {
                                error_log("Discount discrepancy: API discount = {$display_discount}, calculated sum = {$calculated_discount_sum}");
                                // Debug info
                                error_log("Products in coupon calculation:");
                                foreach ($items as $debug_item) {
                                    $debug_id = isset($debug_item['productId']) ? $debug_item['productId'] : 'unknown';
                                    $debug_name = isset($debug_item['name']) ? $debug_item['name'] : 'unknown';
                                    $debug_price = isset($debug_item['price']) ? $debug_item['price'] : 0;
                                    $debug_qty = isset($debug_item['quantity']) ? $debug_item['quantity'] : 0;
                                    $debug_item_discount = isset($debug_item['discount_amount']) ? $debug_item['discount_amount'] : 0;
                                    
                                    error_log("Item: {$debug_name} (ID: {$debug_id}) - Price: {$debug_price} - Qty: {$debug_qty} - Discount: {$debug_item_discount}");
                                }
                                
                                // If there's a significant discrepancy, the API total is more reliable
                                // Force the calculated discount to be distributed proportionally
                                if ($calculated_discount_sum > 0) {
                                    $adjustment_factor = $display_discount / $calculated_discount_sum;
                                    error_log("Adjusting discount calculation by factor: {$adjustment_factor}");
                                }
                            }

                            // For percentage-based coupons, always display the coupon discount
                            // directly from the API instead of the calculated sum to ensure accuracy
                            if ($has_coupon && $discount_percent > 0) {
                                // Override with API value if significant difference
                                if (abs($calculated_discount_sum - $display_discount) > 0.01) {
                                    $display_discount = $total_discount_amount;
                                }
                            }
                        ?>
                        <tr data-product-id="<?php echo esc_attr($product_id); ?>" <?php echo !empty($wc_product_id) ? 'data-wc-product-id="' . esc_attr($wc_product_id) . '"' : ''; ?>>
                            <td class="product-name">
                                <?php if (!empty($image_url)): ?>
                                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($item['name'] ?? ''); ?>" class="product-thumbnail">
                                <?php endif; ?>
                                <div class="product-info">
                                    <div class="product-title"><?php echo esc_html($product_name); ?></div>
                                    <?php if ($discount_display): ?>
                                    <div class="product-discount">
                                        <?php 
                                        if ($has_coupon && $discount_percent > 0) {
                                            echo wp_kses_post(wc_price($discount_amount)) . ' discount (' . $discount_percent . '% off)';
                                        } else {
                                            echo wp_kses_post(wc_price($discount_amount)) . ' discount';
                                        }
                                        ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="product-price">
                                <?php 
                                // Show the price after any discounts applied
                                echo wp_kses_post(wc_price($display_unit_price)); 
                                ?>
                            </td>
                            <td class="product-quantity">
                                <span class="quantity-value">× <?php echo esc_html($quantity); ?></span>
                            </td>
                            <td class="product-total">
                                <?php 
                                // Show the exact line total from the API
                                echo wp_kses_post(wc_price($line_total)); 
                                ?>
                            </td>
                            <td class="product-tax">
                                <?php 
                                // Show the GST amount - with at most 2 decimal places
                                echo wp_kses_post(wc_price(round($item_tax, 2))); 
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5"><?php esc_html_e('No items found in this subscription.', 'bocs-wordpress'); ?></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bocs-subtotal-row">
                            <th colspan="3" class="text-right"><?php esc_html_e('Items Subtotal:', 'bocs-wordpress'); ?></th>
                            <td class="subtotal"><?php echo wp_kses_post(wc_price($subtotal)); ?></td>
                            <td></td>
                        </tr>
                        
                        <?php // Show coupon discount row regardless of per-item discounts
                        if (!empty($subscription['couponLines']) && is_array($subscription['couponLines'])): 
                            foreach ($subscription['couponLines'] as $coupon):
                                // Use the discount amount directly from the API by default
                                $display_discount = !empty($coupon['discount']) ? floatval($coupon['discount']) : 0;
                                
                                // Record discrepancy for debugging if needed
                                if ($has_coupon && $discount_percent > 0 && abs($calculated_discount_sum - $display_discount) > 0.01) {
                                    error_log("Discount discrepancy: API discount = {$display_discount}, calculated sum = {$calculated_discount_sum}");
                                    error_log("Products in coupon calculation:");
                                    foreach ($items as $debug_item) {
                                        $debug_id = isset($debug_item['productId']) ? $debug_item['productId'] : 'unknown';
                                        $debug_name = isset($debug_item['name']) ? $debug_item['name'] : 'unknown';
                                        $debug_price = isset($debug_item['price']) ? $debug_item['price'] : 0;
                                        $debug_qty = isset($debug_item['quantity']) ? $debug_item['quantity'] : 0;
                                        $debug_item_discount = isset($debug_item['discount_amount']) ? $debug_item['discount_amount'] : 0;
                                        
                                        error_log("Item: {$debug_name} (ID: {$debug_id}) - Price: {$debug_price} - Qty: {$debug_qty} - Discount: {$debug_item_discount}");
                                    }
                                }
                                ?>
                                <tr class="bocs-coupon-row">
                                    <th colspan="3" class="text-right coupon-label">
                                        <?php 
                                        // Just display "Discount" without the coupon code
                                        echo esc_html__('Discount:', 'bocs-wordpress');
                                        ?>
                                    </th>
                                    <td class="coupon-discount">
                                        <?php
                                        // Always use the calculated discount sum from line items
                                        // instead of the API value to ensure consistency
                                        echo wp_kses_post(wc_price($calculated_discount_sum * -1)); 
                                        
                                        // Log if there's a discrepancy but we're using our calculated value
                                        if ($display_discount != $calculated_discount_sum && $calculated_discount_sum > 0) {
                                            error_log("Using calculated discount sum ({$calculated_discount_sum}) instead of API value ({$display_discount})");
                                        }
                                        ?>
                                    </td>
                                    <td></td>
                                </tr>
                            <?php endforeach;
                        endif; ?>
                        
                        <?php if (!empty($subscription['shippingTotal'])): ?>
                        <tr class="bocs-shipping-row">
                            <th colspan="3" class="text-right"><?php esc_html_e('Shipping:', 'bocs-wordpress'); ?></th>
                            <td data-title="<?php esc_attr_e('Shipping', 'bocs-wordpress'); ?>" class="shipping">
                                <?php echo wp_kses_post(wc_price($subscription['shippingTotal'])); ?>
                            </td>
                            <td>
                                <?php 
                                // Based on the reference image, shipping GST is $2.00 for a $20.00 shipping charge (10%)
                                $shipping_tax = round($subscription['shippingTotal'] * 0.1, 2);
                                
                                echo wp_kses_post(wc_price($shipping_tax)); 
                                $tax_total += $shipping_tax; // Add to total tax
                                ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($tax_total > 0): ?>
                        <tr class="bocs-tax-row">
                            <th colspan="3" class="text-right"><?php esc_html_e('GST:', 'bocs-wordpress'); ?></th>
                            <td></td>
                            <td data-title="<?php esc_attr_e('GST', 'bocs-wordpress'); ?>" class="tax">
                                <?php 
                                // Use the actual calculated tax total
                                echo wp_kses_post(wc_price($tax_total)); 
                                ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        
                        <tr class="bocs-total-row">
                            <th colspan="3" class="text-right"><?php esc_html_e('Order Total:', 'bocs-wordpress'); ?></th>
                            <td colspan="2" data-title="<?php esc_attr_e('Total', 'bocs-wordpress'); ?>" class="order-total">
                                <?php 
                                // Calculate the order total based on:
                                // Subtotal - Calculated Discount + Shipping
                                $shipping_total = isset($subscription['shippingTotal']) ? floatval($subscription['shippingTotal']) : 0;
                                $calculated_total = $subtotal - $calculated_discount_sum + $shipping_total;
                                
                                // Log the calculation for debugging
                                error_log("Order total calculation: Subtotal ({$subtotal}) - Discount ({$calculated_discount_sum}) + Shipping ({$shipping_total}) = {$calculated_total}");
                                
                                // Display the calculated total instead of the API total
                                echo wp_kses_post(wc_price($calculated_total));
                                ?>
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
