<?php
/**
 * Template for the Update My Box page
 *
 * This template displays a dedicated page for updating subscription box contents.
 *
 * @package    Bocs
 * @subpackage Bocs/views
 * @since      0.0.123
 */

// If this file is called directly, abort.
if (!defined('WPINC') || !defined('ABSPATH')) {
    die;
}

$user_id = get_current_user_id();
$subscription_id = isset($wp->query_vars['bocs-update-box']) ? sanitize_text_field($wp->query_vars['bocs-update-box']) : '';

if (empty($subscription_id)) {
    echo '<div class="woocommerce-error">' . esc_html__('Invalid subscription ID.', 'bocs-wordpress') . '</div>';
    return;
}

// Get subscription details
$helper = new Bocs_Helper();
$url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
$subscription_response = $helper->curl_request($url, 'GET', [], $this->headers);

if (is_wp_error($subscription_response) || !isset($subscription_response['data'])) {
    echo '<div class="woocommerce-error">' . esc_html__('Unable to retrieve subscription details.', 'bocs-wordpress') . '</div>';
    return;
}

$subscription = $subscription_response['data'];

// Get products from the collection or bocs endpoint
$collection_id = isset($subscription['collection']['id']) ? $subscription['collection']['id'] : '';
$bocs_id = isset($subscription['bocs']['id']) ? $subscription['bocs']['id'] : '';

if (!empty($collection_id)) {
    // Use collection endpoint if collection ID exists
    $url = BOCS_API_URL . 'collections/' . $collection_id;
    $products_response = $helper->curl_request($url, 'GET', [], $this->headers);
    $endpoint_type = 'collection';
} elseif (!empty($bocs_id)) {
    // Fall back to bocs endpoint if no collection ID but bocs ID exists
    $url = BOCS_API_URL . 'bocs/' . $bocs_id;
    $products_response = $helper->curl_request($url, 'GET', [], $this->headers);
    $endpoint_type = 'bocs';
} else {
    echo '<div class="woocommerce-error">' . esc_html__('Unable to find collection or bocs ID for this subscription.', 'bocs-wordpress') . '</div>';
    return;
}

if (is_wp_error($products_response) || !isset($products_response['data'])) {
    echo '<div class="woocommerce-error">' . esc_html__('Unable to retrieve available products.', 'bocs-wordpress') . '</div>';
    return;
}

$response_data = $products_response['data'];
$available_products = isset($response_data['products']) ? $response_data['products'] : array();

// Get range for minimum and maximum quantity
$min_quantity = 0;
$max_quantity = 0;
if (isset($response_data['range']) && is_array($response_data['range']) && count($response_data['range']) >= 2) {
    $min_quantity = intval($response_data['range'][0]);
    $max_quantity = intval($response_data['range'][1]);
}

// Get discount information
$discount_type = 'NONE';
$discount_amount = 0;
$discount_unit = '';

// Get discount information from the subscription response, prioritizing frequency object
if (isset($subscription['frequency']) && isset($subscription['frequency']['discount']) && isset($subscription['frequency']['discountType'])) {
    $discount_amount = floatval($subscription['frequency']['discount']);
    $discount_unit = strtolower($subscription['frequency']['discountType']);
    $discount_type = $discount_amount > 0 ? 'DISCOUNT' : 'NONE';
} else {
    // Fall back to metadata if frequency object doesn't have discount info
    if (isset($subscription['metaData']) && is_array($subscription['metaData'])) {
        $discount_meta = null;
        $discount_type_meta = null;
        
        foreach ($subscription['metaData'] as $meta) {
            if (isset($meta['key']) && $meta['key'] === '__bocs_discount' && isset($meta['value'])) {
                $discount_meta = $meta['value'];
            }
            if (isset($meta['key']) && $meta['key'] === '__bocs_discount_type' && isset($meta['value'])) {
                $discount_type_meta = $meta['value'];
            }
        }
        
        if ($discount_meta !== null && $discount_type_meta !== null) {
            $discount_amount = floatval($discount_meta);
            $discount_unit = strtolower($discount_type_meta);
            $discount_type = $discount_amount > 0 ? 'DISCOUNT' : 'NONE';
        }
    }
    
    // Last resort, check coupon lines
    if ($discount_type === 'NONE' && isset($subscription['couponLines']) && is_array($subscription['couponLines']) && !empty($subscription['couponLines'])) {
        $coupon = $subscription['couponLines'][0];
        if (isset($coupon['discount']) && floatval($coupon['discount']) > 0) {
            $discount_amount = floatval($coupon['discount']);
            // Default to dollar as we don't know the type from coupon data
            $discount_unit = 'dollar';
            $discount_type = 'DISCOUNT';
        }
    }
}

// Get current box contents (line items)
$line_items = isset($subscription['lineItems']) ? $subscription['lineItems'] : array();

// Format date
$next_payment_date = isset($subscription['nextPaymentDateGmt']) ? $subscription['nextPaymentDateGmt'] : '';
$next_payment_display = '';

if (!empty($next_payment_date)) {
    $next_payment_timestamp = strtotime($next_payment_date);
    $next_payment_display = date_i18n(get_option('date_format'), $next_payment_timestamp);
}

// Format frequency
$billing_period = isset($subscription['billingPeriod']) ? strtolower($subscription['billingPeriod']) : '';
$frequency = isset($subscription['frequency']) ? intval($subscription['frequency']) : 1;
$frequency_text = '';

if (!empty($billing_period)) {
    $frequency_text = $helper->get_interval_label($billing_period, $frequency);
}

?>

<div class="bocs-account-update-box-container">
    <div class="bocs-brand">
        <div class="bocs-logo">bocs.io</div>
    </div>

    <a href="<?php echo esc_url(wc_get_account_endpoint_url('bocs-subscriptions')); ?>" class="bocs-back-link">
        <?php esc_html_e('Back to My Subscriptions', 'bocs-wordpress'); ?>
    </a>
    
    <h2><?php esc_html_e('Update My Box Contents', 'bocs-wordpress'); ?></h2>
    
    <div class="bocs-update-box-details">
        <div class="bocs-update-box-info">
            <h3><?php esc_html_e('Subscription Details', 'bocs-wordpress'); ?></h3>
            <p>
                <strong><?php esc_html_e('Next Payment:', 'bocs-wordpress'); ?></strong> 
                <span id="next-payment-date"><?php echo esc_html($next_payment_display); ?></span>
            </p>
            <p>
                <strong><?php esc_html_e('Frequency:', 'bocs-wordpress'); ?></strong> 
                <span id="frequency-text"><?php echo esc_html($frequency_text); ?></span>
            </p>
        </div>
    </div>
    
    <div class="bocs-update-box-products">
        <h3><?php esc_html_e('Available Products', 'bocs-wordpress'); ?></h3>
        <p class="bocs-instructions">
            <?php esc_html_e('Select the products you want in your next box.', 'bocs-wordpress'); ?>
            <?php if ($min_quantity > 0 && $max_quantity > 0) : ?>
                <span class="bocs-quantity-range">
                    <?php printf(esc_html__('You must select between %1$d and %2$d items in total.', 'bocs-wordpress'), $min_quantity, $max_quantity); ?>
                </span>
            <?php endif; ?>
        </p>
        
        <div class="bocs-products-grid" id="available-products-container">
            <?php if (!empty($available_products)) : ?>
                <?php foreach ($available_products as $product) : ?>
                    <?php 
                        $product_id = isset($product['id']) ? $product['id'] : '';
                        $product_name = isset($product['name']) ? $product['name'] : '';
                        $product_price = isset($product['price']) ? floatval($product['price']) : 0;
                        $product_image = isset($product['images'][0]['url']) ? $product['images'][0]['url'] : '';
                        $product_description = isset($product['description']) ? $product['description'] : '';
                        
                        // Check if product is in current box
                        $current_quantity = isset($product['quantity']) ? intval($product['quantity']) : 0;
                        // Also check line items for this product
                        foreach ($line_items as $item) {
                            if (isset($item['productId']) && $item['productId'] === $product_id) {
                                $current_quantity = isset($item['quantity']) ? intval($item['quantity']) : $current_quantity;
                                break;
                            }
                        }
                    ?>
                    <div class="bocs-product-card" data-product-id="<?php echo esc_attr($product_id); ?>" data-product-price="<?php echo esc_attr($product_price); ?>">
                        <div class="bocs-product-image">
                            <?php if (!empty($product_image)) : ?>
                                <img src="<?php echo esc_url($product_image); ?>" alt="<?php echo esc_attr($product_name); ?>">
                            <?php else : ?>
                                <div class="bocs-no-image"><?php esc_html_e('No image', 'bocs-wordpress'); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="bocs-product-details">
                            <h4 class="bocs-product-name"><?php echo esc_html($product_name); ?></h4>
                            <div class="bocs-product-price"><?php echo $helper->format_price($product_price, isset($subscription['currency']) ? $subscription['currency'] : ''); ?></div>
                            <div class="bocs-product-description"><?php echo wp_kses_post($product_description); ?></div>
                            <div class="bocs-quantity-controls">
                                <button type="button" class="bocs-quantity-minus" aria-label="<?php esc_attr_e('Decrease quantity', 'bocs-wordpress'); ?>">-</button>
                                <input type="number" class="bocs-quantity-input" value="<?php echo esc_attr($current_quantity); ?>" min="0" max="99">
                                <button type="button" class="bocs-quantity-plus" aria-label="<?php esc_attr_e('Increase quantity', 'bocs-wordpress'); ?>">+</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <p class="bocs-no-products"><?php esc_html_e('No products available.', 'bocs-wordpress'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="bocs-update-box-summary">
        <h3><?php esc_html_e('Box Summary', 'bocs-wordpress'); ?></h3>
        <div id="box-summary-container">
            <p class="bocs-empty-box" style="<?php echo empty($line_items) ? '' : 'display: none;'; ?>"><?php esc_html_e('Your box is empty. Add some products!', 'bocs-wordpress'); ?></p>
            
            <table class="bocs-summary-table">
                <thead>
                    <tr>
                        <th class="bocs-summary-product"><?php esc_html_e('Product', 'bocs-wordpress'); ?></th>
                        <th class="bocs-summary-quantity"><?php esc_html_e('Quantity', 'bocs-wordpress'); ?></th>
                        <th class="bocs-summary-total"><?php esc_html_e('Total', 'bocs-wordpress'); ?></th>
                    </tr>
                </thead>
                <tbody id="box-summary-items">
                    <?php if (!empty($line_items)) : ?>
                        <?php foreach ($line_items as $item) : ?>
                            <?php 
                                $item_product_id = isset($item['productId']) ? $item['productId'] : '';
                                $item_name = isset($item['name']) ? $item['name'] : '';
                                $item_price = isset($item['price']) ? floatval($item['price']) : 0;
                                $item_quantity = isset($item['quantity']) ? intval($item['quantity']) : 0;
                                $item_total = $item_price * $item_quantity;
                                
                                // Skip if quantity is 0
                                if ($item_quantity <= 0) continue;
                            ?>
                            <tr class="bocs-summary-item" data-product-id="<?php echo esc_attr($item_product_id); ?>">
                                <td class="bocs-summary-item-name"><?php echo esc_html($item_name); ?></td>
                                <td class="bocs-summary-item-quantity"><?php echo esc_html($item_quantity); ?></td>
                                <td class="bocs-summary-item-price"><?php echo $helper->format_price($item_total, isset($subscription['currency']) ? $subscription['currency'] : ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot id="box-summary-totals">
                    <tr class="bocs-summary-subtotal">
                        <th colspan="2"><?php esc_html_e('Subtotal:', 'bocs-wordpress'); ?></th>
                        <td id="box-subtotal-price"><?php echo $helper->format_price(isset($subscription['total']) ? $subscription['total'] : 0, isset($subscription['currency']) ? $subscription['currency'] : ''); ?></td>
                    </tr>
                    <!-- Discount row will be added here by JS if applicable -->
                    <!-- Tax row will be added here by JS if applicable -->
                    <!-- Shipping row will be added here by JS if applicable -->
                    <tr class="bocs-summary-grand-total">
                        <th colspan="2"><?php esc_html_e('Total:', 'bocs-wordpress'); ?></th>
                        <td id="box-total-price"><?php echo $helper->format_price(isset($subscription['total']) ? $subscription['total'] : 0, isset($subscription['currency']) ? $subscription['currency'] : ''); ?></td>
                    </tr>
                </tfoot>
            </table>
            <div class="bocs-quantity-validation"></div>
        </div>
        
        <div class="bocs-update-box-actions">
            <button type="button" id="save-box-changes" class="button"><?php esc_html_e('Save Changes', 'bocs-wordpress'); ?></button>
            <a href="<?php echo esc_url(wc_get_account_endpoint_url('bocs-subscriptions')); ?>" class="button cancel"><?php esc_html_e('Cancel', 'bocs-wordpress'); ?></a>
        </div>
    </div>
</div>

<style>
    :root {
        --bocs-primary: #0065A9;
        --bocs-secondary: #00A5B5;
        --bocs-accent: #FFCC00;
        --bocs-text: #333333;
        --bocs-light-bg: #F9FAFB;
        --bocs-border: #E5E7EB;
        --bocs-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        --bocs-error: #E53E3E;
        --bocs-success: #38A169;
        --bocs-radius: 8px;
        --bocs-transition: all 0.3s ease;
    }
    
    .bocs-account-update-box-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 24px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        color: var(--bocs-text);
        line-height: 1.6;
    }
    
    .bocs-back-link {
        display: inline-flex;
        align-items: center;
        margin-bottom: 24px;
        text-decoration: none;
        color: var(--bocs-primary);
        font-weight: 500;
        transition: var(--bocs-transition);
    }
    
    .bocs-back-link:hover {
        color: var(--bocs-secondary);
    }
    
    .bocs-back-link:before {
        content: "←";
        margin-right: 8px;
        font-size: 18px;
    }
    
    h2, h3, h4 {
        color: var(--bocs-primary);
        font-weight: 600;
    }
    
    h2 {
        font-size: 28px;
        margin-bottom: 24px;
        position: relative;
    }
    
    h2:after {
        content: "";
        display: block;
        height: 4px;
        width: 60px;
        background: var(--bocs-accent);
        margin-top: 12px;
        border-radius: 2px;
    }
    
    h3 {
        font-size: 20px;
        margin-top: 0;
        margin-bottom: 16px;
    }
    
    .bocs-update-box-details {
        margin-bottom: 32px;
        padding: 24px;
        background-color: var(--bocs-light-bg);
        border-radius: var(--bocs-radius);
        box-shadow: var(--bocs-shadow);
        border-left: 4px solid var(--bocs-primary);
    }
    
    .bocs-update-box-info p {
        margin: 8px 0;
        display: flex;
        justify-content: space-between;
    }
    
    .bocs-update-box-info p strong {
        font-weight: 600;
        color: var(--bocs-primary);
    }
    
    .bocs-instructions {
        margin-bottom: 24px;
        line-height: 1.6;
    }
    
    .bocs-quantity-range {
        display: inline-block;
        margin-top: 8px;
        padding: 8px 16px;
        background-color: var(--bocs-light-bg);
        border-radius: var(--bocs-radius);
        border-left: 3px solid var(--bocs-accent);
        font-weight: 500;
        color: var(--bocs-text);
    }
    
    .bocs-products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 24px;
        margin-bottom: 32px;
    }
    
    .bocs-product-card {
        border-radius: var(--bocs-radius);
        overflow: hidden;
        background: white;
        box-shadow: var(--bocs-shadow);
        transition: var(--bocs-transition);
        border: 1px solid var(--bocs-border);
        position: relative;
    }
    
    .bocs-product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.12);
        border-color: var(--bocs-secondary);
    }
    
    .bocs-product-image {
        height: 200px;
        overflow: hidden;
        background-color: var(--bocs-light-bg);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    
    .bocs-product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: var(--bocs-transition);
    }
    
    .bocs-product-card:hover .bocs-product-image img {
        transform: scale(1.05);
    }
    
    .bocs-no-image {
        color: #888;
        font-size: 14px;
        padding: 20px;
        text-align: center;
    }
    
    .bocs-product-details {
        padding: 20px;
    }
    
    .bocs-product-name {
        margin-top: 0;
        margin-bottom: 10px;
        font-size: 18px;
        color: var(--bocs-primary);
        font-weight: 600;
    }
    
    .bocs-product-price {
        font-weight: bold;
        margin-bottom: 12px;
        color: var(--bocs-text);
        font-size: 16px;
    }
    
    .bocs-product-description {
        font-size: 14px;
        color: #666;
        margin-bottom: 20px;
        max-height: 60px;
        overflow: hidden;
        position: relative;
    }
    
    .bocs-product-description:after {
        content: "";
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 20px;
        background: linear-gradient(transparent, white);
    }
    
    .bocs-quantity-controls {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-top: 15px;
        border-radius: var(--bocs-radius);
        overflow: hidden;
        border: 1px solid var(--bocs-border);
        background: var(--bocs-light-bg);
    }
    
    .bocs-quantity-minus,
    .bocs-quantity-plus {
        width: 40px;
        height: 40px;
        background-color: var(--bocs-light-bg);
        border: none;
        font-size: 18px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: var(--bocs-transition);
        color: var(--bocs-primary);
    }
    
    .bocs-quantity-minus:hover,
    .bocs-quantity-plus:hover {
        background-color: var(--bocs-primary);
        color: white;
    }
    
    .bocs-quantity-input {
        width: 60px;
        height: 40px;
        text-align: center;
        border: none;
        border-left: 1px solid var(--bocs-border);
        border-right: 1px solid var(--bocs-border);
        font-size: 16px;
        font-weight: 500;
        color: var(--bocs-text);
        background: white;
    }
    
    .bocs-quantity-input:focus {
        outline: none;
        background-color: var(--bocs-light-bg);
    }
    
    .bocs-update-box-summary {
        background-color: white;
        padding: 24px;
        border-radius: var(--bocs-radius);
        margin-top: 32px;
        box-shadow: var(--bocs-shadow);
        border-top: 4px solid var(--bocs-secondary);
    }
    
    .bocs-summary-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    
    .bocs-summary-table thead {
        border-bottom: 1px solid var(--bocs-border);
    }
    
    .bocs-summary-table th {
        text-align: left;
        padding: 12px 0;
        font-weight: 600;
        color: var(--bocs-primary);
    }
    
    .bocs-summary-table tbody tr {
        border-bottom: 1px solid var(--bocs-border);
    }
    
    .bocs-summary-table td {
        padding: 12px 0;
        vertical-align: top;
    }
    
    .bocs-summary-item-quantity {
        text-align: center;
        color: #666;
    }
    
    .bocs-summary-item-price {
        text-align: right;
        font-weight: 600;
        color: var(--bocs-primary);
    }
    
    .bocs-summary-table tfoot {
        border-top: 2px solid var(--bocs-border);
    }
    
    .bocs-summary-table tfoot th {
        text-align: right;
        padding: 8px 0;
    }
    
    .bocs-summary-table tfoot td {
        text-align: right;
        padding: 8px 0;
    }
    
    .bocs-summary-discount th,
    .bocs-summary-discount td {
        color: var(--bocs-error);
    }
    
    .bocs-summary-grand-total {
        border-top: 2px dashed var(--bocs-border);
        font-size: 1.1em;
    }
    
    .bocs-summary-grand-total th,
    .bocs-summary-grand-total td {
        padding-top: 16px;
        font-weight: 700;
    }
    
    .bocs-update-box-actions {
        margin-top: 24px;
        display: flex;
        gap: 12px;
    }
    
    button.button, a.button {
        padding: 12px 24px;
        border-radius: var(--bocs-radius);
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--bocs-transition);
        border: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    button.button {
        background-color: var(--bocs-primary);
        color: white;
    }
    
    button.button:hover {
        background-color: var(--bocs-secondary);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }
    
    button.button:disabled,
    button.button.disabled {
        background-color: #ccc;
        cursor: not-allowed;
        box-shadow: none;
        opacity: 0.7;
    }
    
    a.button.cancel {
        background-color: white;
        color: var(--bocs-text);
        border: 1px solid var(--bocs-border);
    }
    
    a.button.cancel:hover {
        background-color: #f5f5f5;
        border-color: #ddd;
    }
    
    .bocs-empty-box {
        font-style: italic;
        color: #888;
        text-align: center;
        padding: 20px;
        background: var(--bocs-light-bg);
        border-radius: var(--bocs-radius);
    }
    
    .bocs-validation-error {
        color: var(--bocs-error);
        margin-top: 12px;
        padding: 12px;
        background-color: rgba(229, 62, 62, 0.1);
        border-radius: var(--bocs-radius);
        border-left: 3px solid var(--bocs-error);
        font-weight: 500;
    }
    
    /* Bocs Brand Logo */
    .bocs-brand {
        margin-bottom: 24px;
        display: flex;
        align-items: center;
    }
    
    .bocs-logo {
        font-size: 24px;
        font-weight: 700;
        color: var(--bocs-primary);
        display: flex;
        align-items: center;
    }
    
    .bocs-logo:before {
        content: "□";
        display: inline-block;
        color: var(--bocs-accent);
        margin-right: 8px;
        transform: rotate(45deg);
    }
    
    /* Mobile responsiveness */
    @media (max-width: 768px) {
        .bocs-products-grid {
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        }
        
        .bocs-update-box-actions {
            flex-direction: column;
        }
        
        button.button, a.button {
            width: 100%;
        }
    }
    
    @media (max-width: 480px) {
        .bocs-products-grid {
            grid-template-columns: 1fr;
        }
        
        .bocs-product-image {
            height: 180px;
        }
        
        .bocs-update-box-details,
        .bocs-update-box-summary {
            padding: 16px;
        }
        
        .bocs-summary-table {
            font-size: 14px;
        }
        
        .bocs-summary-product {
            width: 50%;
        }
    }
    
    /* Animations */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .bocs-product-card {
        animation: fadeIn 0.3s ease-out;
        animation-fill-mode: both;
    }
    
    .bocs-product-card:nth-child(1) { animation-delay: 0.1s; }
    .bocs-product-card:nth-child(2) { animation-delay: 0.2s; }
    .bocs-product-card:nth-child(3) { animation-delay: 0.3s; }
    .bocs-product-card:nth-child(4) { animation-delay: 0.4s; }
    .bocs-product-card:nth-child(5) { animation-delay: 0.5s; }
    .bocs-product-card:nth-child(6) { animation-delay: 0.6s; }
</style>

<script>
jQuery(document).ready(function($) {
    var subscriptionId = '<?php echo esc_js($subscription_id); ?>';
    var currency = '<?php echo esc_js(isset($subscription['currency']) ? $subscription['currency'] : 'USD'); ?>';
    var boxProducts = [];
    var minQuantity = <?php echo esc_js($min_quantity); ?>;
    var maxQuantity = <?php echo esc_js($max_quantity); ?>;
    var discountType = '<?php echo esc_js($discount_type); ?>';
    var discountAmount = <?php echo esc_js($discount_amount); ?>;
    var discountUnit = '<?php echo esc_js($discount_unit); ?>';
    
    // Initialize box products from bocs products
    <?php if (!empty($available_products)) : ?>
        <?php foreach ($available_products as $product) : ?>
            <?php 
                $product_id = isset($product['id']) ? $product['id'] : '';
                $product_name = isset($product['name']) ? $product['name'] : '';
                $product_price = isset($product['price']) ? floatval($product['price']) : 0;
                $product_quantity = isset($product['quantity']) ? intval($product['quantity']) : 0;
                
                // Skip if product ID is empty
                if (empty($product_id)) continue;
            ?>
            boxProducts.push({
                id: '<?php echo esc_js($product_id); ?>',
                name: '<?php echo esc_js($product_name); ?>',
                price: <?php echo esc_js($product_price); ?>,
                quantity: <?php echo esc_js($product_quantity); ?>
            });
        <?php endforeach; ?>
    <?php endif; ?>
    
    // Add smooth increment/decrement animations
    function animateQuantityChange(input, oldValue, newValue) {
        input.addClass('animating');
        setTimeout(function() {
            input.removeClass('animating');
        }, 300);
    }
    
    // Handle quantity changes
    $(document).on('click', '.bocs-quantity-minus', function() {
        var input = $(this).siblings('.bocs-quantity-input');
        var value = parseInt(input.val()) || 0;
        if (value > 0) {
            var newValue = value - 1;
            animateQuantityChange(input, value, newValue);
            input.val(newValue);
            input.trigger('change');
        }
    });
    
    $(document).on('click', '.bocs-quantity-plus', function() {
        // Check if adding one more would exceed the maximum
        if (maxQuantity > 0) {
            var currentTotal = 0;
            $('.bocs-quantity-input').each(function() {
                currentTotal += parseInt($(this).val()) || 0;
            });
            
            // Calculate new total if we add one more
            var input = $(this).siblings('.bocs-quantity-input');
            var value = parseInt(input.val()) || 0;
            var newTotal = currentTotal + 1;
            
            // Don't allow increment if it would exceed maximum
            if (newTotal > maxQuantity) {
                showNotification('error', '<?php echo esc_js(__('Cannot add more items. Maximum allowed is %s items in total.', 'bocs-wordpress')); ?>'.replace('%s', maxQuantity));
                return;
            }
            
            var newValue = value + 1;
            animateQuantityChange(input, value, newValue);
            input.val(newValue);
            input.trigger('change');
        } else {
            var input = $(this).siblings('.bocs-quantity-input');
            var value = parseInt(input.val()) || 0;
            var newValue = value + 1;
            animateQuantityChange(input, value, newValue);
            input.val(newValue);
            input.trigger('change');
        }
    });
    
    // Also validate direct input to prevent typing values that exceed maximum
    $(document).on('input', '.bocs-quantity-input', function() {
        if (maxQuantity > 0) {
            var $this = $(this);
            var currentValue = parseInt($this.val()) || 0;
            var previousValue = parseInt($this.data('previous-value')) || 0;
            var difference = currentValue - previousValue;
            
            if (difference > 0) {
                var totalQuantity = 0;
                $('.bocs-quantity-input').each(function() {
                    totalQuantity += parseInt($(this).val()) || 0;
                });
                
                if (totalQuantity > maxQuantity) {
                    // Reset to previous value
                    $this.val(previousValue);
                    showNotification('error', '<?php echo esc_js(__('Cannot add more items. Maximum allowed is %s items in total.', 'bocs-wordpress')); ?>'.replace('%s', maxQuantity));
                }
            }
            
            // Store current value for next comparison
            $this.data('previous-value', parseInt($this.val()) || 0);
        }
        
        // Update box summary on every input change
        updateBoxProductsFromInputs();
        updateBoxSummary();
    });
    
    // Update products array from all inputs
    function updateBoxProductsFromInputs() {
        $('.bocs-quantity-input').each(function() {
            var input = $(this);
            var productCard = input.closest('.bocs-product-card');
            var productId = productCard.data('product-id');
            var productPrice = parseFloat(productCard.data('product-price')) || 0;
            var productName = productCard.find('.bocs-product-name').text();
            var quantity = parseInt(input.val()) || 0;
            
            // Update or add product in boxProducts array
            var found = false;
            for (var i = 0; i < boxProducts.length; i++) {
                if (boxProducts[i].id === productId) {
                    boxProducts[i].quantity = quantity;
                    found = true;
                    break;
                }
            }
            
            if (!found && quantity > 0) {
                boxProducts.push({
                    id: productId,
                    name: productName,
                    price: productPrice,
                    quantity: quantity
                });
            }
        });
    }
    
    // Update box when quantity changes
    $(document).on('change', '.bocs-quantity-input', function() {
        var productCard = $(this).closest('.bocs-product-card');
        var productId = productCard.data('product-id');
        var productPrice = parseFloat(productCard.data('product-price')) || 0;
        var productName = productCard.find('.bocs-product-name').text();
        var quantity = parseInt($(this).val()) || 0;
        
        // Update or add product in boxProducts array
        var found = false;
        for (var i = 0; i < boxProducts.length; i++) {
            if (boxProducts[i].id === productId) {
                boxProducts[i].quantity = quantity;
                found = true;
                break;
            }
        }
        
        if (!found && quantity > 0) {
            boxProducts.push({
                id: productId,
                name: productName,
                price: productPrice,
                quantity: quantity
            });
        }
        
        // Update summary
        updateBoxSummary();
    });
    
    // Update box summary
    function updateBoxSummary() {
        var summaryItemsContainer = $('#box-summary-items');
        var summaryTotalsContainer = $('#box-summary-totals');
        var emptyBoxMessage = $('.bocs-empty-box');
        var subtotal = 0;
        var totalQuantity = 0;
        var saveButton = $('#save-box-changes');
        
        summaryItemsContainer.empty();
        
        // Filter out products with quantity 0
        var activeProducts = boxProducts.filter(function(product) {
            return product.quantity > 0;
        });
        
        if (activeProducts.length === 0) {
            emptyBoxMessage.show();
            $('.bocs-summary-table').hide();
            saveButton.prop('disabled', true);
        } else {
            emptyBoxMessage.hide();
            $('.bocs-summary-table').show();
            
            activeProducts.forEach(function(product) {
                var itemTotal = product.price * product.quantity;
                subtotal += itemTotal;
                totalQuantity += product.quantity;
                
                var itemHtml = '<tr class="bocs-summary-item" data-product-id="' + product.id + '">' +
                    '<td class="bocs-summary-item-name">' + product.name + '</td>' +
                    '<td class="bocs-summary-item-quantity">' + product.quantity + '</td>' +
                    '<td class="bocs-summary-item-price">' + formatPrice(itemTotal, currency) + '</td>' +
                    '</tr>';
                
                summaryItemsContainer.append(itemHtml);
            });
            
            // Calculate discount if applicable
            var discountValue = 0;
            var taxValue = 0;
            var shippingValue = 0;
            var finalPrice = subtotal;
            
            // Update the subtotal row (first row in tfoot)
            summaryTotalsContainer.find('tr.bocs-summary-subtotal td').html(formatPrice(subtotal, currency));
            
            // Rebuild the totals section (excluding subtotal and grand total)
            summaryTotalsContainer.find('tr:not(.bocs-summary-subtotal):not(.bocs-summary-grand-total)').remove();
            
            // Add discount row if applicable
            if (discountAmount > 0 && discountType !== 'NONE') {
                if (discountUnit === 'percent') {
                    discountValue = subtotal * (discountAmount / 100);
                    finalPrice = subtotal - discountValue;
                } else if (discountUnit === 'dollar') {
                    discountValue = discountAmount;
                    finalPrice = subtotal - discountValue;
                    if (finalPrice < 0) finalPrice = 0;
                }
                
                var discountLabel = discountUnit === 'percent' ? 
                    '<?php esc_html_e('Discount (%s%):', 'bocs-wordpress'); ?>'.replace('%s', discountAmount) : 
                    '<?php esc_html_e('Discount:', 'bocs-wordpress'); ?>';
                
                var discountRow = '<tr class="bocs-summary-discount">' +
                    '<th colspan="2">' + discountLabel + '</th>' +
                    '<td>-' + formatPrice(discountValue, currency) + '</td>' +
                    '</tr>';
                
                $(discountRow).insertBefore(summaryTotalsContainer.find('tr.bocs-summary-grand-total'));
            }
            
            // Calculate tax (assuming 10% GST)
            taxValue = finalPrice * 0.1;
            
            // Add tax row
            var taxRow = '<tr class="bocs-summary-tax">' +
                '<th colspan="2"><?php esc_html_e('Tax:', 'bocs-wordpress'); ?></th>' +
                '<td>' + formatPrice(taxValue, currency) + '</td>' +
                '</tr>';
            
            $(taxRow).insertBefore(summaryTotalsContainer.find('tr.bocs-summary-grand-total'));
            
            // Include shipping if available from subscription
            <?php if (isset($subscription['shippingTotal']) && floatval($subscription['shippingTotal']) > 0) : ?>
                shippingValue = <?php echo floatval($subscription['shippingTotal']); ?>;
                
                var shippingRow = '<tr class="bocs-summary-shipping">' +
                    '<th colspan="2"><?php esc_html_e('Shipping:', 'bocs-wordpress'); ?></th>' +
                    '<td>' + formatPrice(shippingValue, currency) + '</td>' +
                    '</tr>';
                
                $(shippingRow).insertBefore(summaryTotalsContainer.find('tr.bocs-summary-grand-total'));
            <?php endif; ?>
            
            // Update final total (including tax and shipping)
            var total = finalPrice + taxValue + shippingValue;
            summaryTotalsContainer.find('tr.bocs-summary-grand-total td').html(formatPrice(total, currency));
            
            // Update floating summary if it exists
            updateFloatingSummary(totalQuantity, total);
        }
        
        // Display quantity validation message if needed
        var quantityValidation = $('.bocs-quantity-validation');
        quantityValidation.empty();
        
        // Check if the quantity is within range and update button state
        var isQuantityValid = true;
        
        if (minQuantity > 0 && maxQuantity > 0) {
            if (totalQuantity < minQuantity) {
                quantityValidation.html('<div class="bocs-validation-error">' + 
                    '<?php echo esc_js(__('Please select at least %s items in total.', 'bocs-wordpress')); ?>'.replace('%s', minQuantity) + 
                    '</div>').show();
                isQuantityValid = false;
            } else if (totalQuantity > maxQuantity) {
                quantityValidation.html('<div class="bocs-validation-error">' + 
                    '<?php echo esc_js(__('Please select no more than %s items in total.', 'bocs-wordpress')); ?>'.replace('%s', maxQuantity) + 
                    '</div>').show();
                isQuantityValid = false;
            }
        }
        
        // Enable or disable the save button based on quantity validity
        saveButton.prop('disabled', !isQuantityValid || activeProducts.length === 0);
        
        // Update button text based on state
        updateSaveButtonState(isQuantityValid && activeProducts.length > 0);
        
        // Update button styling based on disabled state
        if (saveButton.prop('disabled')) {
            saveButton.addClass('disabled');
        } else {
            saveButton.removeClass('disabled');
        }
    }
    
    // Add floating summary for better UX on mobile and long pages
    function addFloatingSummary() {
        if ($('.bocs-floating-summary').length === 0) {
            $('body').append(
                '<div class="bocs-floating-summary">' +
                '<div class="bocs-floating-summary-content">' +
                '<div class="bocs-floating-items"><span>0</span> <?php esc_html_e('items', 'bocs-wordpress'); ?></div>' +
                '<div class="bocs-floating-price"></div>' +
                '</div>' +
                '<button type="button" class="bocs-floating-save-button disabled"><?php esc_html_e('Save Changes', 'bocs-wordpress'); ?></button>' +
                '</div>'
            );
            
            // Scroll to summary when floating button is clicked
            $('.bocs-floating-save-button').on('click', function() {
                if (!$(this).hasClass('disabled')) {
                    $('html, body').animate({
                        scrollTop: $('.bocs-update-box-summary').offset().top - 100
                    }, 500);
                    // Flash the real button to draw attention
                    $('#save-box-changes').addClass('highlight');
                    setTimeout(function() {
                        $('#save-box-changes').removeClass('highlight');
                    }, 1000);
                }
            });
            
            // Show/hide floating summary based on scroll position
            $(window).on('scroll', function() {
                var summaryPosition = $('.bocs-update-box-summary').offset().top;
                var scrollPosition = $(window).scrollTop() + $(window).height();
                
                if (scrollPosition < summaryPosition) {
                    $('.bocs-floating-summary').addClass('show');
                } else {
                    $('.bocs-floating-summary').removeClass('show');
                }
            });
        }
    }
    
    // Update floating summary content
    function updateFloatingSummary(totalQuantity, finalPrice) {
        if ($('.bocs-floating-summary').length > 0) {
            $('.bocs-floating-items span').text(totalQuantity);
            $('.bocs-floating-price').text(formatPrice(finalPrice, currency));
            
            var saveButton = $('#save-box-changes');
            if (saveButton.prop('disabled')) {
                $('.bocs-floating-save-button').addClass('disabled');
            } else {
                $('.bocs-floating-save-button').removeClass('disabled');
            }
        }
    }
    
    // Initialize floating summary
    $(window).on('load', function() {
        addFloatingSummary();
        updateBoxSummary(); // Make sure summary is updated on page load
    });
    
    // Update save button text based on state
    function updateSaveButtonState(isValid) {
        var saveButton = $('#save-box-changes');
        if (isValid) {
            saveButton.html('<?php esc_attr_e('Save Changes', 'bocs-wordpress'); ?>');
        } else {
            saveButton.html('<?php esc_attr_e('Complete Your Selection', 'bocs-wordpress'); ?>');
        }
    }
    
    // Format price using WooCommerce price format
    function formatPrice(price, currencyCode) {
        return (price).toFixed(2) + ' ' + currencyCode;
    }
    
    // Add a notification system
    $('body').append('<div id="bocs-notification-container"></div>');
    
    function showNotification(type, message) {
        var notificationClass = type === 'error' ? 'bocs-notification-error' : 'bocs-notification-success';
        var notification = $('<div class="bocs-notification ' + notificationClass + '">' + message + '</div>');
        $('#bocs-notification-container').append(notification);
        
        setTimeout(function() {
            notification.addClass('show');
        }, 10);
        
        setTimeout(function() {
            notification.removeClass('show');
            setTimeout(function() {
                notification.remove();
            }, 300);
        }, 3000);
    }
    
    // Handle save changes button with improved feedback
    $('#save-box-changes').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        
        // Filter out products with quantity 0
        var lineItems = boxProducts.filter(function(product) {
            return product.quantity > 0;
        }).map(function(product) {
            return {
                productId: product.id,
                quantity: product.quantity
            };
        });
        
        if (lineItems.length === 0) {
            showNotification('error', '<?php esc_attr_e('Please add at least one product to your box.', 'bocs-wordpress'); ?>');
            return;
        }
        
        // Validate total quantity
        var totalQuantity = 0;
        lineItems.forEach(function(item) {
            totalQuantity += item.quantity;
        });
        
        if (minQuantity > 0 && totalQuantity < minQuantity) {
            showNotification('error', '<?php esc_attr_e('Please select at least %s items in total.', 'bocs-wordpress'); ?>'.replace('%s', minQuantity));
            return;
        }
        
        if (maxQuantity > 0 && totalQuantity > maxQuantity) {
            showNotification('error', '<?php esc_attr_e('Please select no more than %s items in total.', 'bocs-wordpress'); ?>'.replace('%s', maxQuantity));
            return;
        }
        
        // Show loading state with subtle animation
        button.html('<span class="bocs-loading-spinner"></span> <?php esc_attr_e('Saving...', 'bocs-wordpress'); ?>').prop('disabled', true);
        
        // Prepare data for API
        var data = {
            lineItems: lineItems
        };
        
        // Calculate totals for API
        var subtotal = 0;
        var totalTax = 0;
        lineItems.forEach(function(item) {
            // Calculate item total and tax
            var itemTotal = (boxProducts.find(p => p.id === item.productId).price * item.quantity).toFixed(2);
            subtotal += parseFloat(itemTotal);
        });
        
        // Round subtotal to 2 decimal places
        subtotal = parseFloat(subtotal.toFixed(2));
        
        // Calculate discount
        var discountValue = 0;
        var finalPrice = subtotal;
        
        if (discountAmount > 0 && discountType !== 'NONE') {
            if (discountUnit === 'percent') {
                discountValue = parseFloat((subtotal * (discountAmount / 100)).toFixed(2));
                finalPrice = parseFloat((subtotal - discountValue).toFixed(2));
            } else if (discountUnit === 'dollar') {
                discountValue = parseFloat(discountAmount.toFixed(2));
                finalPrice = parseFloat((subtotal - discountValue).toFixed(2));
                if (finalPrice < 0) finalPrice = 0;
            }
            
            // Create coupon line item
            var couponCode = 'bocs-' + (discountUnit === 'percent' ? discountAmount + '-percent-off' : discountAmount + '-dollar-off') + '-' + Date.now();
            
            data.couponLines = [{
                discount: parseFloat(discountValue.toFixed(2)),
                code: couponCode,
                discountTax: parseFloat((discountValue * 0.1).toFixed(2)) // Assuming 10% tax rate for calculation
            }];
            
            data.discountTotal = parseFloat(discountValue.toFixed(2));
            data.discountTax = parseFloat((discountValue * 0.1).toFixed(2)); // Assuming 10% tax rate for calculation
        }
        
        // Calculate tax (assuming 10% GST)
        totalTax = parseFloat((finalPrice * 0.1).toFixed(2));
        
        // Add total fields
        data.total = parseFloat((finalPrice + totalTax).toFixed(2));
        data.totalTax = parseFloat(totalTax.toFixed(2));
        data.cartTax = parseFloat(totalTax.toFixed(2));
        data.shippingTax = 0; // Default to 0 if no shipping
        
        // Ensure all monetary fields are numbers, not strings
        if (data.discountTotal) {
            data.discountTotal = parseFloat(data.discountTotal);
        }
        if (data.discountTax) {
            data.discountTax = parseFloat(data.discountTax);
        }
        if (data.couponLines && data.couponLines.length > 0) {
            data.couponLines.forEach(function(coupon) {
                coupon.discount = parseFloat(coupon.discount);
                coupon.discountTax = parseFloat(coupon.discountTax);
            });
        }
        
        // Make API request directly to Bocs API
        $.ajax({
            url: '<?php echo esc_url(BOCS_API_URL); ?>subscriptions/' + subscriptionId,
            type: 'PUT',
            data: JSON.stringify(data),
            contentType: 'application/json',
            timeout: 30000, // Increase timeout to 30 seconds
            beforeSend: function(xhr) {
                xhr.setRequestHeader('Store', '<?php echo esc_js($this->headers['Store']); ?>');
                xhr.setRequestHeader('Organization', '<?php echo esc_js($this->headers['Organization']); ?>');
                xhr.setRequestHeader('Authorization', '<?php echo esc_js($this->headers['Authorization']); ?>');
                
                // Log the request data for debugging
                console.log('Request URL:', '<?php echo esc_url(BOCS_API_URL); ?>subscriptions/' + subscriptionId);
                console.log('Request Data:', data);
            },
            success: function(response) {
                console.log('API Response:', response);
                if (response && response.code === 200) {
                    // Show success message and redirect
                    showNotification('success', '<?php esc_attr_e('Your box has been updated successfully!', 'bocs-wordpress'); ?>');
                    window.location.href = '<?php echo esc_url(wc_get_account_endpoint_url('bocs-subscriptions')); ?>';
                } else {
                    // Show error message
                    var errorMessage = response && response.message ? response.message : '<?php esc_attr_e('An error occurred while updating your box.', 'bocs-wordpress'); ?>';
                    showNotification('error', errorMessage);
                    button.html(originalText).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error Status:', status);
                console.error('AJAX Error:', error);
                console.error('Status Code:', xhr.status);
                console.error('Response Text:', xhr.responseText);
                
                var errorMessage = '';
                
                // Handle 502 Bad Gateway specifically
                if (xhr.status === 502) {
                    errorMessage = '<?php esc_attr_e('Server error (502 Bad Gateway). The server might be overloaded or experiencing issues. Please try again in a few moments.', 'bocs-wordpress'); ?>';
                    
                    // Try a simplified request as fallback
                    retryWithSimplifiedData(subscriptionId, lineItems, button, originalText);
                    return;
                }
                
                try {
                    var response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || '';
                } catch (e) {
                    // If parsing fails, use generic message
                    console.error('Error parsing response:', e);
                }
                
                showNotification('error', errorMessage || '<?php esc_attr_e('An error occurred while updating your box. Please try again.', 'bocs-wordpress'); ?>');
                button.html(originalText).prop('disabled', false);
            }
        });
    });
    
    // Fallback function to retry with simplified data
    function retryWithSimplifiedData(subscriptionId, lineItems, button, originalText) {
        // Create a simplified version of the request with only the essential data
        var simplifiedData = {
            lineItems: lineItems
        };
        
        // Ensure monetary fields are sent as numbers if we include them
        if (discountAmount > 0 && discountType !== 'NONE') {
            // Calculate basic discount values
            var subtotal = 0;
            lineItems.forEach(function(item) {
                var itemTotal = (boxProducts.find(p => p.id === item.productId).price * item.quantity);
                subtotal += itemTotal;
            });
            
            var discountValue = 0;
            if (discountUnit === 'percent') {
                discountValue = subtotal * (discountAmount / 100);
            } else if (discountUnit === 'dollar') {
                discountValue = discountAmount;
            }
            
            // Only include minimal fields needed
            simplifiedData.discountTotal = parseFloat(discountValue.toFixed(2));
        }
        
        console.log('Retrying with simplified data:', simplifiedData);
        
        // Make a simplified request
        $.ajax({
            url: '<?php echo esc_url(BOCS_API_URL); ?>subscriptions/' + subscriptionId,
            type: 'PUT',
            data: JSON.stringify(simplifiedData),
            contentType: 'application/json',
            timeout: 45000, // Even longer timeout for retry
            beforeSend: function(xhr) {
                xhr.setRequestHeader('Store', '<?php echo esc_js($this->headers['Store']); ?>');
                xhr.setRequestHeader('Organization', '<?php echo esc_js($this->headers['Organization']); ?>');
                xhr.setRequestHeader('Authorization', '<?php echo esc_js($this->headers['Authorization']); ?>');
            },
            success: function(response) {
                console.log('Retry Response:', response);
                if (response && response.code === 200) {
                    showNotification('success', '<?php esc_attr_e('Your box has been updated successfully!', 'bocs-wordpress'); ?>');
                    window.location.href = '<?php echo esc_url(wc_get_account_endpoint_url('bocs-subscriptions')); ?>';
                } else {
                    var errorMessage = response && response.message ? response.message : '<?php esc_attr_e('An error occurred while updating your box.', 'bocs-wordpress'); ?>';
                    showNotification('error', errorMessage);
                    button.html(originalText).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Retry Error Status:', status);
                console.error('Retry Error:', error);
                showNotification('error', '<?php esc_attr_e('Unable to update your box. Please try again later or contact support.', 'bocs-wordpress'); ?>');
                button.html(originalText).prop('disabled', false);
            }
        });
    }
});
</script>

<style>
/* Additional UI elements */
.bocs-notification-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    width: 320px;
}

.bocs-notification {
    padding: 15px 20px;
    margin-bottom: 10px;
    border-radius: var(--bocs-radius);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    font-weight: 500;
    opacity: 0;
    transform: translateX(30px);
    transition: all 0.3s ease;
}

.bocs-notification.show {
    opacity: 1;
    transform: translateX(0);
}

.bocs-notification-success {
    background-color: var(--bocs-success);
    color: white;
}

.bocs-notification-error {
    background-color: var(--bocs-error);
    color: white;
}

.bocs-quantity-input.animating {
    background-color: var(--bocs-accent);
    color: var(--bocs-text);
    transition: background-color 0.3s ease;
}

.bocs-loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    vertical-align: middle;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 0.8s linear infinite;
    margin-right: 8px;
}

/* Floating summary for better UX on mobile and long pages */
.bocs-floating-summary {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.1);
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 100;
    transform: translateY(100%);
    transition: transform 0.3s ease;
}

.bocs-floating-summary.show {
    transform: translateY(0);
}

.bocs-floating-summary-content {
    display: flex;
    flex-direction: column;
}

.bocs-floating-items {
    font-weight: 600;
    color: var(--bocs-primary);
}

.bocs-floating-price {
    font-size: 18px;
    font-weight: 700;
}

.bocs-floating-save-button {
    background-color: var(--bocs-primary);
    color: white;
    border: none;
    padding: 10px 16px;
    border-radius: var(--bocs-radius);
    font-weight: 600;
    cursor: pointer;
}

.bocs-floating-save-button.disabled {
    background-color: #ccc;
    cursor: not-allowed;
}

/* Highlight animation for save button */
@keyframes highlight {
    0% { box-shadow: 0 0 0 0 rgba(0, 101, 169, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(0, 101, 169, 0); }
    100% { box-shadow: 0 0 0 0 rgba(0, 101, 169, 0); }
}

.button.highlight {
    animation: highlight 1s ease-out;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style> 