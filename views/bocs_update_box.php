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

// Get available products
$url = BOCS_API_URL . 'products?query=status:PUBLISHED&limit=100';
$products_response = $helper->curl_request($url, 'GET', [], $this->headers);

if (is_wp_error($products_response) || !isset($products_response['data']['data'])) {
    echo '<div class="woocommerce-error">' . esc_html__('Unable to retrieve available products.', 'bocs-wordpress') . '</div>';
    return;
}

$available_products = $products_response['data']['data'];

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
    <a href="<?php echo esc_url(wc_get_account_endpoint_url('bocs-subscriptions')); ?>" class="bocs-back-link">
        &larr; <?php esc_html_e('Back to My Subscriptions', 'bocs-wordpress'); ?>
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
        <p class="bocs-instructions"><?php esc_html_e('Select the products you want in your next box.', 'bocs-wordpress'); ?></p>
        
        <div class="bocs-products-grid" id="available-products-container">
            <?php if (!empty($available_products)) : ?>
                <?php foreach ($available_products as $product) : ?>
                    <?php 
                        $product_id = isset($product['id']) ? $product['id'] : '';
                        $product_name = isset($product['name']) ? $product['name'] : '';
                        $product_price = isset($product['price']) ? floatval($product['price']) : 0;
                        $product_image = isset($product['images'][0]['src']) ? $product['images'][0]['src'] : '';
                        $product_description = isset($product['shortDescription']) ? $product['shortDescription'] : '';
                        
                        // Check if product is in current box
                        $current_quantity = 0;
                        foreach ($line_items as $item) {
                            if (isset($item['productId']) && $item['productId'] === $product_id) {
                                $current_quantity = isset($item['quantity']) ? intval($item['quantity']) : 0;
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
            <div class="bocs-summary-items" id="box-summary-items">
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
                        <div class="bocs-summary-item" data-product-id="<?php echo esc_attr($item_product_id); ?>">
                            <span class="bocs-summary-item-name"><?php echo esc_html($item_name); ?></span>
                            <span class="bocs-summary-item-quantity">x<?php echo esc_html($item_quantity); ?></span>
                            <span class="bocs-summary-item-price"><?php echo $helper->format_price($item_total, isset($subscription['currency']) ? $subscription['currency'] : ''); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="bocs-summary-total">
                <strong><?php esc_html_e('Total:', 'bocs-wordpress'); ?></strong>
                <span id="box-total-price"><?php echo $helper->format_price(isset($subscription['total']) ? $subscription['total'] : 0, isset($subscription['currency']) ? $subscription['currency'] : ''); ?></span>
            </div>
        </div>
        
        <div class="bocs-update-box-actions">
            <button type="button" id="save-box-changes" class="button"><?php esc_html_e('Save Changes', 'bocs-wordpress'); ?></button>
            <a href="<?php echo esc_url(wc_get_account_endpoint_url('bocs-subscriptions')); ?>" class="button cancel"><?php esc_html_e('Cancel', 'bocs-wordpress'); ?></a>
        </div>
    </div>
</div>

<style>
    .bocs-account-update-box-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .bocs-back-link {
        display: inline-block;
        margin-bottom: 20px;
        text-decoration: none;
    }
    
    .bocs-update-box-details {
        margin-bottom: 30px;
        padding: 15px;
        background-color: #f8f8f8;
        border-radius: 5px;
    }
    
    .bocs-products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .bocs-product-card {
        border: 1px solid #ddd;
        border-radius: 5px;
        overflow: hidden;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .bocs-product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .bocs-product-image {
        height: 180px;
        overflow: hidden;
        background-color: #f5f5f5;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .bocs-product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .bocs-no-image {
        color: #888;
        font-size: 14px;
    }
    
    .bocs-product-details {
        padding: 15px;
    }
    
    .bocs-product-name {
        margin-top: 0;
        margin-bottom: 10px;
        font-size: 16px;
    }
    
    .bocs-product-price {
        font-weight: bold;
        margin-bottom: 10px;
        color: #333;
    }
    
    .bocs-product-description {
        font-size: 14px;
        color: #666;
        margin-bottom: 15px;
        max-height: 60px;
        overflow: hidden;
    }
    
    .bocs-quantity-controls {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-top: 10px;
    }
    
    .bocs-quantity-minus,
    .bocs-quantity-plus {
        width: 30px;
        height: 30px;
        background-color: #f1f1f1;
        border: 1px solid #ddd;
        border-radius: 3px;
        font-size: 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .bocs-quantity-input {
        width: 50px;
        height: 30px;
        text-align: center;
        border: 1px solid #ddd;
        margin: 0 5px;
    }
    
    .bocs-update-box-summary {
        background-color: #f8f8f8;
        padding: 20px;
        border-radius: 5px;
        margin-top: 30px;
    }
    
    .bocs-summary-item {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #eee;
    }
    
    .bocs-summary-total {
        margin-top: 15px;
        font-size: 18px;
        display: flex;
        justify-content: space-between;
    }
    
    .bocs-update-box-actions {
        margin-top: 20px;
        display: flex;
        gap: 10px;
    }
    
    .bocs-empty-box {
        font-style: italic;
        color: #888;
    }
    
    /* Mobile responsiveness */
    @media (max-width: 768px) {
        .bocs-products-grid {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        }
    }
    
    @media (max-width: 480px) {
        .bocs-products-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
jQuery(document).ready(function($) {
    var subscriptionId = '<?php echo esc_js($subscription_id); ?>';
    var currency = '<?php echo esc_js(isset($subscription['currency']) ? $subscription['currency'] : 'USD'); ?>';
    var boxProducts = [];
    
    // Initialize box products from line items
    <?php if (!empty($line_items)) : ?>
        <?php foreach ($line_items as $item) : ?>
            <?php 
                $item_product_id = isset($item['productId']) ? $item['productId'] : '';
                $item_name = isset($item['name']) ? $item['name'] : '';
                $item_price = isset($item['price']) ? floatval($item['price']) : 0;
                $item_quantity = isset($item['quantity']) ? intval($item['quantity']) : 0;
                
                // Skip if product ID is empty
                if (empty($item_product_id)) continue;
            ?>
            boxProducts.push({
                id: '<?php echo esc_js($item_product_id); ?>',
                name: '<?php echo esc_js($item_name); ?>',
                price: <?php echo esc_js($item_price); ?>,
                quantity: <?php echo esc_js($item_quantity); ?>
            });
        <?php endforeach; ?>
    <?php endif; ?>
    
    // Handle quantity changes
    $(document).on('click', '.bocs-quantity-minus', function() {
        var input = $(this).siblings('.bocs-quantity-input');
        var value = parseInt(input.val()) || 0;
        if (value > 0) {
            input.val(value - 1);
            input.trigger('change');
        }
    });
    
    $(document).on('click', '.bocs-quantity-plus', function() {
        var input = $(this).siblings('.bocs-quantity-input');
        var value = parseInt(input.val()) || 0;
        input.val(value + 1);
        input.trigger('change');
    });
    
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
        var summaryContainer = $('#box-summary-items');
        var emptyBoxMessage = $('.bocs-empty-box');
        var totalPrice = 0;
        
        summaryContainer.empty();
        
        // Filter out products with quantity 0
        var activeProducts = boxProducts.filter(function(product) {
            return product.quantity > 0;
        });
        
        if (activeProducts.length === 0) {
            emptyBoxMessage.show();
        } else {
            emptyBoxMessage.hide();
            
            activeProducts.forEach(function(product) {
                var itemTotal = product.price * product.quantity;
                totalPrice += itemTotal;
                
                var itemHtml = '<div class="bocs-summary-item" data-product-id="' + product.id + '">' +
                    '<span class="bocs-summary-item-name">' + product.name + '</span>' +
                    '<span class="bocs-summary-item-quantity">x' + product.quantity + '</span>' +
                    '<span class="bocs-summary-item-price">' + formatPrice(itemTotal, currency) + '</span>' +
                    '</div>';
                
                summaryContainer.append(itemHtml);
            });
        }
        
        // Update total price
        $('#box-total-price').text(formatPrice(totalPrice, currency));
    }
    
    // Format price using WooCommerce price format
    function formatPrice(price, currencyCode) {
        return (price).toFixed(2) + ' ' + currencyCode;
    }
    
    // Handle save changes button
    $('#save-box-changes').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        
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
            alert('<?php esc_attr_e('Please add at least one product to your box.', 'bocs-wordpress'); ?>');
            return;
        }
        
        // Show loading state
        button.text('<?php esc_attr_e('Saving...', 'bocs-wordpress'); ?>').prop('disabled', true);
        
        // Prepare data for API
        var data = {
            lineItems: lineItems
        };
        
        // Make AJAX request to save changes
        $.ajax({
            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            type: 'POST',
            data: {
                action: 'bocs_update_box',
                subscription_id: subscriptionId,
                box_data: JSON.stringify(data),
                security: '<?php echo esc_js(wp_create_nonce('bocs-update-box-nonce')); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Show success message and redirect
                    alert('<?php esc_attr_e('Your box has been updated successfully!', 'bocs-wordpress'); ?>');
                    window.location.href = '<?php echo esc_url(wc_get_account_endpoint_url('bocs-subscriptions')); ?>';
                } else {
                    // Show error message
                    alert(response.data.message || '<?php esc_attr_e('An error occurred while updating your box.', 'bocs-wordpress'); ?>');
                    button.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                // Show error message
                alert('<?php esc_attr_e('An error occurred while updating your box. Please try again.', 'bocs-wordpress'); ?>');
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Initialize box summary on page load
    updateBoxSummary();
});
</script> 