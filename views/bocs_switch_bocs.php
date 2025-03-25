<?php
/**
 * BOCS Switch Bocs Template
 * 
 * This template handles the display and functionality for switching between different Bocs subscriptions.
 * It allows customers to change their subscription from one Bocs type to another.
 *
 * @package BOCS
 * @subpackage Templates
 */

// Initialize helper
$helper = new Bocs_Helper();

// Get current subscription ID from URL
global $wp;
$subscription_id = isset($wp->query_vars['bocs-switch-bocs']) ? sanitize_text_field($wp->query_vars['bocs-switch-bocs']) : '';

// Get options for API headers
$options = get_option('bocs_plugin_options');
$headers = [];
if (!empty($options['bocs_headers'])) {
    $headers = [
        'Organization' => $options['bocs_headers']['organization'] ?? '',
        'Store' => $options['bocs_headers']['store'] ?? '',
        'Authorization' => $options['bocs_headers']['authorization'] ?? '',
        'Content-Type' => 'application/json'
    ];
}

// Fetch current subscription details
$url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
$subscription = $helper->curl_request($url, 'GET', [], $headers);

// Check if subscription is WP_Error
if (is_wp_error($subscription)) {
    $has_errors = true;
    $error_message = $subscription->get_error_message();
} else {
    // Check for API errors
    $has_errors = false;
    if (!isset($subscription['data']) || empty($subscription['data'])) {
        $has_errors = true;
    }
}

// Fetch available Bocs options
$url = BOCS_API_URL . 'bocs';
$available_bocs = $helper->curl_request($url, 'GET', [], $headers);

// Check if available_bocs is WP_Error
if (is_wp_error($available_bocs)) {
    $has_errors = true;
    $error_message = isset($error_message) ? $error_message : $available_bocs->get_error_message();
} else {
    // Handle nested data structure - bocs items are in data.data
    if (!isset($available_bocs['data']) || 
        !isset($available_bocs['data']['data']) || 
        empty($available_bocs['data']['data'])) {
        $has_errors = true;
    } else {
        // Store the actual bocs items for easier access
        $bocs_items = $available_bocs['data']['data'];
    }
}

// Get current Bocs ID
$current_bocs_id = '';
if (!$has_errors && isset($subscription['data']['bocs']) && isset($subscription['data']['bocs']['id'])) {
    $current_bocs_id = $subscription['data']['bocs']['id'];
}

// Enqueue styles and scripts
wp_enqueue_style('wp-jquery-ui-dialog');
wp_enqueue_script('jquery-ui-dialog');
?>

<div class="bocs-switch-container">
    <h2><?php esc_html_e('Switch Bocs Subscription', 'bocs-wordpress'); ?></h2>
    
    <?php if ($has_errors) : ?>
        <div class="woocommerce-error">
            <?php 
            if (isset($error_message) && !empty($error_message)) {
                echo esc_html($error_message);
            } else {
                esc_html_e('There was an error loading your subscription or available Bocs options. Please try again later.', 'bocs-wordpress');
            }
            ?>
        </div>
        <p>
            <a href="<?php echo esc_url(wc_get_account_endpoint_url('bocs-subscriptions')); ?>" class="button">
                <?php esc_html_e('Return to Subscriptions', 'bocs-wordpress'); ?>
            </a>
        </p>
    <?php else : ?>
    
    <div class="bocs-switch-intro">
        <p>
            <?php esc_html_e('You are currently subscribed to:', 'bocs-wordpress'); ?>
            <strong>
                <?php 
                // Get the current bocs name by matching IDs
                $current_bocs_name = '';
                if (!empty($current_bocs_id) && isset($bocs_items) && is_array($bocs_items)) {
                    foreach ($bocs_items as $bocs) {
                        if (isset($bocs['id']) && $bocs['id'] === $current_bocs_id) {
                            $current_bocs_name = $bocs['name'];
                            break;
                        }
                    }
                }
                echo esc_html($current_bocs_name);
                ?>
            </strong>
        </p>
        <?php if (isset($subscription['data']['frequency'])) : ?>
        <p>
            <?php esc_html_e('Current frequency:', 'bocs-wordpress'); ?>
            <strong>
                <?php 
                $frequency = $subscription['data']['frequency'];
                echo esc_html($frequency['frequency'] . ' ' . $frequency['timeUnit']);
                ?>
            </strong>
            <?php if ($frequency['discount'] > 0) : ?>
                (<?php echo esc_html($frequency['discountType'] === 'DOLLAR' ? '$' . $frequency['discount'] : $frequency['discount'] . '%'); ?> <?php esc_html_e('discount', 'bocs-wordpress'); ?>)
            <?php endif; ?>
        </p>
        <?php endif; ?>
        <p>
            <?php esc_html_e('Next payment date:', 'bocs-wordpress'); ?>
            <strong>
                <?php 
                if (isset($subscription['data']['nextPaymentDateGmt'])) {
                    $next_date = new DateTime($subscription['data']['nextPaymentDateGmt']);
                    echo esc_html($next_date->format('F j, Y'));
                } else {
                    esc_html_e('Not scheduled', 'bocs-wordpress');
                }
                ?>
            </strong>
        </p>
        <p>
            <?php esc_html_e('Select a new Bocs below to switch your subscription:', 'bocs-wordpress'); ?>
        </p>
    </div>
    
    <div class="bocs-options-grid">
        <?php if (isset($bocs_items) && is_array($bocs_items)) : ?>
            <?php foreach ($bocs_items as $bocs) : ?>
                <?php 
                // Skip current Bocs
                if ($current_bocs_id == $bocs['id']) {
                    continue;
                }
                
                // Extract Bocs details
                $bocs_id = isset($bocs['id']) ? sanitize_text_field($bocs['id']) : '';
                $bocs_name = isset($bocs['name']) ? sanitize_text_field($bocs['name']) : '';
                $bocs_description = isset($bocs['description']) ? sanitize_text_field($bocs['description']) : '';
                
                // Calculate total price based on products
                $bocs_price = 0;
                if (isset($bocs['products']) && is_array($bocs['products'])) {
                    foreach ($bocs['products'] as $product) {
                        $product_price = isset($product['price']) ? floatval($product['price']) : 0;
                        $product_quantity = isset($product['quantity']) ? intval($product['quantity']) : 1;
                        $bocs_price += ($product_price * $product_quantity);
                    }
                }
                
                // Get image URL from Bocs
                $bocs_image = '';
                if (isset($bocs['images']) && !empty($bocs['images']) && isset($bocs['images'][0]['url'])) {
                    $bocs_image = esc_url($bocs['images'][0]['url']);
                } elseif (isset($bocs['products']) && !empty($bocs['products']) && 
                         isset($bocs['products'][0]['images']) && !empty($bocs['products'][0]['images']) &&
                         isset($bocs['products'][0]['images'][0]['url'])) {
                    // Fallback to first product image if bocs image not available
                    $bocs_image = esc_url($bocs['products'][0]['images'][0]['url']);
                }
                
                // Use placeholder image if still none found
                if (empty($bocs_image)) {
                    $bocs_image = wc_placeholder_img_src();
                }
                ?>
                
                <div class="bocs-option" data-bocs-id="<?php echo esc_attr($bocs_id); ?>">
                    <div class="bocs-option-image">
                        <img src="<?php echo esc_url($bocs_image); ?>" alt="<?php echo esc_attr($bocs_name); ?>">
                    </div>
                    <div class="bocs-option-content">
                        <h3><?php echo esc_html($bocs_name); ?></h3>
                        <p class="bocs-option-description"><?php echo esc_html($bocs_description); ?></p>
                        <?php if (isset($bocs['type']) && !empty($bocs['type'])): ?>
                        <div class="bocs-type-badge <?php echo esc_attr(strtolower($bocs['type'])); ?>-type">
                            <?php echo esc_html(ucfirst(strtolower($bocs['type']))); ?> <?php esc_html_e('Box', 'bocs-wordpress'); ?>
                        </div>
                        <?php endif; ?>
                        <div class="bocs-option-details">
                            <p class="bocs-option-price"><?php echo $helper->format_price($bocs_price, $subscription['data']['currency'] ?? ''); ?></p>
                            
                            <?php if (isset($bocs['products']) && is_array($bocs['products']) && !empty($bocs['products'])): ?>
                            <div class="bocs-option-products">
                                <p class="bocs-products-title"><?php esc_html_e('Box Contents:', 'bocs-wordpress'); ?></p>
                                <ul>
                                    <?php 
                                    $max_products = 3; // Show only first 3 products
                                    $product_count = count($bocs['products']);
                                    $shown_products = min($max_products, $product_count);
                                    
                                    for ($i = 0; $i < $shown_products; $i++) {
                                        $product = $bocs['products'][$i];
                                        echo '<li>' . esc_html($product['name']) . '</li>';
                                    }
                                    
                                    // Show count of remaining products if there are more
                                    if ($product_count > $max_products) {
                                        echo '<li>' . sprintf(
                                            esc_html__('+ %d more items', 'bocs-wordpress'),
                                            $product_count - $max_products
                                        ) . '</li>';
                                    }
                                    ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </div>
                        <button class="button select-bocs-button" data-bocs-id="<?php echo esc_attr($bocs_id); ?>" data-bocs-name="<?php echo esc_attr($bocs_name); ?>">
                            <?php esc_html_e('Choose Frequency', 'bocs-wordpress'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <p><?php esc_html_e('No other Bocs options available at this time.', 'bocs-wordpress'); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="bocs-switch-actions">
        <a href="<?php echo esc_url(wc_get_account_endpoint_url('bocs-subscriptions')); ?>" class="button cancel">
            <?php esc_html_e('Cancel', 'bocs-wordpress'); ?>
        </a>
    </div>
    
    <!-- Frequency Selection Dialog -->
    <div id="frequency-selection-dialog" style="display:none;" title="<?php esc_attr_e('Select Frequency', 'bocs-wordpress'); ?>">
        <p>
            <?php esc_html_e('Select frequency for', 'bocs-wordpress'); ?>
            <strong id="frequency-bocs-name"></strong>:
        </p>
        <div class="frequency-options">
            <!-- Frequency options will be dynamically loaded here -->
        </div>
    </div>
    
    <!-- Confirmation Dialog -->
    <div id="switch-confirmation-dialog" style="display:none;" title="<?php esc_attr_e('Confirm Bocs Switch', 'bocs-wordpress'); ?>">
        <p>
            <?php esc_html_e('Are you sure you want to switch from', 'bocs-wordpress'); ?>
            <strong><?php echo esc_html($current_bocs_name); ?></strong>
            <?php esc_html_e('to', 'bocs-wordpress'); ?>
            <strong id="target-bocs-name"></strong>?
        </p>
        <p>
            <?php esc_html_e('with frequency', 'bocs-wordpress'); ?>:
            <strong id="target-frequency"></strong>
        </p>
        <p>
            <?php esc_html_e('Your next box will be updated with the new Bocs selection.', 'bocs-wordpress'); ?>
        </p>
    </div>
    
    <?php endif; ?>
</div>

<style>
:root {
    --bocs-primary: #3c7b7c;
    --bocs-primary-light: #e9f7f7;
    --bocs-primary-dark: #2a5a5b;
    --bocs-secondary: #d26e4b;
    --bocs-secondary-light: #f8ece7;
    --bocs-gray-light: #f7f7f7;
    --bocs-gray-medium: #e0e0e0;
    --bocs-gray-dark: #666;
    --bocs-border-radius: 8px;
    --bocs-box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    --bocs-transition: all 0.3s ease;
}

.bocs-switch-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 30px 20px;
    font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
    color: #333;
}

.bocs-switch-container h2 {
    font-weight: 600;
    margin-bottom: 25px;
    color: var(--bocs-primary-dark);
    font-size: 1.8em;
}

.bocs-switch-intro {
    margin-bottom: 30px;
    padding: 20px;
    background: var(--bocs-primary-light);
    border-radius: var(--bocs-border-radius);
    box-shadow: var(--bocs-box-shadow);
}

.bocs-switch-intro p {
    margin-bottom: 12px;
    line-height: 1.6;
    font-size: 1.05em;
}

.bocs-switch-intro strong {
    color: var(--bocs-primary-dark);
    font-weight: 600;
}

.bocs-options-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.bocs-option {
    border: 1px solid var(--bocs-gray-medium);
    border-radius: var(--bocs-border-radius);
    overflow: hidden;
    transition: var(--bocs-transition);
    background: #fff;
    height: 100%;
    display: flex;
    flex-direction: column;
    box-shadow: var(--bocs-box-shadow);
}

.bocs-option:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.12);
    border-color: var(--bocs-primary);
}

.bocs-option-image {
    position: relative;
    overflow: hidden;
}

.bocs-option-image img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    transition: var(--bocs-transition);
}

.bocs-option:hover .bocs-option-image img {
    transform: scale(1.05);
}

.bocs-option-content {
    padding: 20px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}

.bocs-option-content h3 {
    margin-top: 0;
    margin-bottom: 12px;
    font-weight: 600;
    color: var(--bocs-primary-dark);
    font-size: 1.3em;
}

.bocs-option-description {
    color: var(--bocs-gray-dark);
    margin-bottom: 15px;
    line-height: 1.5;
}

.bocs-option-details {
    margin-bottom: 20px;
    flex-grow: 1;
}

.bocs-option-price {
    font-weight: 600;
    font-size: 1.4em;
    margin-bottom: 12px;
    color: var(--bocs-primary-dark);
}

.bocs-option-products {
    background: var(--bocs-gray-light);
    padding: 15px;
    border-radius: var(--bocs-border-radius);
    margin-top: 15px;
}

.bocs-products-title {
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--bocs-primary);
}

.bocs-option-products ul {
    margin: 0;
    padding-left: 20px;
}

.bocs-option-products li {
    margin-bottom: 5px;
    font-size: 0.95em;
    color: var(--bocs-gray-dark);
}

.select-bocs-button {
    background: var(--bocs-primary) !important;
    color: white !important;
    padding: 12px 20px !important;
    border: none !important;
    border-radius: var(--bocs-border-radius) !important;
    font-weight: 600 !important;
    font-size: 1em !important;
    cursor: pointer !important;
    transition: var(--bocs-transition) !important;
    text-align: center !important;
    display: inline-block !important;
    width: 100% !important;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1) !important;
}

.select-bocs-button:hover {
    background: var(--bocs-primary-dark) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 12px rgba(0,0,0,0.15) !important;
}

/* Frequency Selection Styles */
.frequency-options {
    margin: 20px 0;
}

.frequency-option {
    padding: 15px;
    margin-bottom: 12px;
    border: 2px solid var(--bocs-gray-medium);
    border-radius: var(--bocs-border-radius);
    cursor: pointer;
    transition: var(--bocs-transition);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: white;
}

.frequency-option:hover {
    background-color: var(--bocs-primary-light);
    border-color: var(--bocs-primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
}

.frequency-option.selected {
    background-color: var(--bocs-primary-light);
    border-color: var(--bocs-primary);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.frequency-details {
    display: flex;
    flex-direction: column;
}

.frequency-name {
    font-weight: 600;
    color: var(--bocs-primary-dark);
    font-size: 1.1em;
}

.frequency-discount {
    font-size: 0.95em;
    color: var(--bocs-secondary);
    margin-top: 4px;
    font-weight: 500;
}

.frequency-select .dashicons {
    color: var(--bocs-primary);
    font-size: 24px;
}

.bocs-switch-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 30px;
}

.bocs-switch-actions .button {
    padding: 12px 24px !important;
    border-radius: var(--bocs-border-radius) !important;
    font-weight: 500 !important;
    transition: var(--bocs-transition) !important;
}

.bocs-switch-actions .cancel {
    background: transparent !important;
    border: 1px solid var(--bocs-gray-medium) !important;
    color: var(--bocs-gray-dark) !important;
}

.bocs-switch-actions .cancel:hover {
    background: var(--bocs-gray-light) !important;
    border-color: var(--bocs-gray-dark) !important;
}

.ui-dialog {
    border-radius: var(--bocs-border-radius) !important;
    padding: 0 !important;
    box-shadow: 0 12px 30px rgba(0,0,0,0.2) !important;
    border: none !important;
}

.ui-dialog .ui-dialog-titlebar {
    background: var(--bocs-primary) !important;
    color: white !important;
    border: none !important;
    border-radius: var(--bocs-border-radius) var(--bocs-border-radius) 0 0 !important;
    padding: 15px 20px !important;
    font-weight: 500 !important;
}

.ui-dialog .ui-dialog-titlebar-close {
    background: transparent !important;
    border: none !important;
    color: white !important;
}

.ui-dialog .ui-dialog-content {
    padding: 20px !important;
}

.ui-dialog .ui-dialog-content p {
    font-size: 1.05em !important;
    line-height: 1.6 !important;
    margin-bottom: 15px !important;
}

.ui-dialog .ui-dialog-content strong {
    color: var(--bocs-primary-dark) !important;
    font-weight: 600 !important;
}

.ui-dialog .ui-dialog-buttonpane {
    border-top: 1px solid var(--bocs-gray-medium) !important;
    margin-top: 0 !important;
    padding: 15px !important;
}

.ui-dialog .ui-dialog-buttonpane button {
    border-radius: var(--bocs-border-radius) !important;
    padding: 10px 20px !important;
    transition: var(--bocs-transition) !important;
    font-weight: 500 !important;
}

.ui-dialog .ui-button:first-child {
    background: var(--bocs-primary) !important;
    color: white !important;
    border: none !important;
}

.ui-dialog .ui-button:first-child:hover {
    background: var(--bocs-primary-dark) !important;
}

.ui-dialog .ui-button:last-child {
    background: transparent !important;
    border: 1px solid var(--bocs-gray-medium) !important;
    color: var(--bocs-gray-dark) !important;
}

.ui-dialog .ui-button:last-child:hover {
    background: var(--bocs-gray-light) !important;
    border-color: var(--bocs-gray-dark) !important;
}

#switch-confirmation-dialog {
    text-align: center;
    line-height: 1.6;
}

.bocs-loading {
    background: var(--bocs-primary-light);
    padding: 15px;
    border-radius: var(--bocs-border-radius);
    margin-bottom: 20px;
    text-align: center;
    font-weight: 600;
    color: var(--bocs-primary-dark);
    display: flex;
    align-items: center;
    justify-content: center;
}

.bocs-loading:before {
    content: '';
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid var(--bocs-primary-light);
    border-top: 2px solid var(--bocs-primary);
    border-radius: 50%;
    margin-right: 10px;
    animation: bocs-spinner 1s linear infinite;
}

@keyframes bocs-spinner {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Product Selection Styles */
.bocs-product-selection-info {
    background: var(--bocs-primary-light);
    padding: 15px;
    margin-bottom: 20px;
    border-radius: var(--bocs-border-radius);
    font-size: 0.95em;
}

.bocs-product-selection {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid var(--bocs-gray-medium);
    border-radius: var(--bocs-border-radius);
    padding: 15px;
}

.bocs-product-item {
    padding: 15px;
    margin-bottom: 12px;
    border: 1px solid var(--bocs-gray-medium);
    border-radius: var(--bocs-border-radius);
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: var(--bocs-transition);
}

.bocs-product-item:hover {
    border-color: var(--bocs-primary);
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
}

.bocs-product-details {
    display: flex;
    flex: 1;
    align-items: center;
}

.bocs-product-image {
    width: 70px;
    height: 70px;
    margin-right: 15px;
    border-radius: var(--bocs-border-radius);
    overflow: hidden;
}

.bocs-product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.bocs-product-info {
    flex: 1;
}

.bocs-product-name {
    font-weight: 600;
    margin-bottom: 6px;
    color: var(--bocs-primary-dark);
}

.bocs-product-price {
    color: var(--bocs-gray-dark);
    font-size: 0.95em;
}

.bocs-product-quantity {
    display: flex;
    align-items: center;
}

.quantity-btn {
    border: 1px solid var(--bocs-gray-medium);
    background: white;
    width: 30px;
    height: 30px;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    user-select: none;
    border-radius: 4px;
    transition: var(--bocs-transition);
}

.quantity-btn:hover {
    background: var(--bocs-primary-light);
    border-color: var(--bocs-primary);
}

.quantity-input {
    width: 45px;
    text-align: center;
    margin: 0 8px;
    border: 1px solid var(--bocs-gray-medium);
    border-radius: 4px;
    padding: 5px;
    font-weight: 500;
}

#total-selected-quantity {
    color: var(--bocs-primary);
    font-weight: 600;
}

.bocs-type-badge {
    display: inline-block;
    padding: 6px 10px;
    font-size: 0.8em;
    border-radius: 20px;
    margin-bottom: 15px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.fixed-type {
    background-color: var(--bocs-primary-light);
    color: var(--bocs-primary);
    border: 1px solid var(--bocs-primary);
}

.custom-type {
    background-color: var(--bocs-secondary-light);
    color: var(--bocs-secondary);
    border: 1px solid var(--bocs-secondary);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .bocs-options-grid {
        grid-template-columns: 1fr;
    }
    
    .ui-dialog {
        width: 90% !important;
        max-width: 90% !important;
    }
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize dialogs
    $("#frequency-selection-dialog").dialog({
        autoOpen: false,
        modal: true,
        width: 500,
        buttons: {
            "Continue": function() {
                const selectedFrequency = $('.frequency-option.selected').data('frequency-id');
                const selectedFrequencyText = $('.frequency-option.selected .frequency-name').text();
                
                if (!selectedFrequency) {
                    alert('<?php esc_html_e('Please select a frequency option', 'bocs-wordpress'); ?>');
                    return;
                }
                
                // Close frequency dialog
                $(this).dialog("close");
                
                // Save frequency ID
                selectedFrequencyId = selectedFrequency;
                
                // Check if this is a custom Bocs (needs product selection) or fixed Bocs
                if (bocsData[selectedBocsId] && bocsData[selectedBocsId].type === 'custom') {
                    // For custom bocs, open product selection dialog
                    openProductSelectionDialog();
                } else {
                    // For fixed bocs, go directly to confirmation
                    // Update confirmation dialog with selected frequency
                    $("#target-bocs-name").text(selectedBocsName);
                    $("#target-frequency").text(selectedFrequencyText);
                    $("#switch-confirmation-dialog").dialog("open");
                }
            },
            "Cancel": function() {
                $(this).dialog("close");
            }
        }
    });
    
    // Create a product selection dialog
    $("body").append(`
        <div id="product-selection-dialog" style="display:none;" title="<?php esc_attr_e('Select Products', 'bocs-wordpress'); ?>">
            <p>
                <?php esc_html_e('Select products for your', 'bocs-wordpress'); ?> 
                <strong id="product-bocs-name"></strong> <?php esc_html_e('box', 'bocs-wordpress'); ?>:
            </p>
            <div class="bocs-product-selection-info"></div>
            <div class="bocs-product-selection"></div>
        </div>
    `);
    
    // Initialize product selection dialog
    $("#product-selection-dialog").dialog({
        autoOpen: false,
        modal: true,
        width: 600,
        height: 500,
        buttons: {
            "Continue": function() {
                // Validate product selection
                if (!validateProductSelection()) {
                    return;
                }
                
                // Close product dialog
                $(this).dialog("close");
                
                // Get selected frequency text for display
                const selectedFrequencyObj = bocsData[selectedBocsId].priceAdjustment.adjustments.find(
                    adj => adj.id === selectedFrequencyId
                );
                const selectedFrequencyText = selectedFrequencyObj ? 
                    `${selectedFrequencyObj.frequency} ${selectedFrequencyObj.timeUnit}` : '';
                
                // Update confirmation dialog
                $("#target-bocs-name").text(selectedBocsName);
                $("#target-frequency").text(selectedFrequencyText);
                
                // Open confirmation dialog
                $("#switch-confirmation-dialog").dialog("open");
            },
            "Cancel": function() {
                $(this).dialog("close");
            }
        }
    });
    
    $("#switch-confirmation-dialog").dialog({
        autoOpen: false,
        modal: true,
        width: 400,
        buttons: {
            "Confirm": function() {
                processSwitchBocs();
                $(this).dialog("close");
            },
            "Cancel": function() {
                $(this).dialog("close");
            }
        }
    });
    
    // Selected data
    let selectedBocsId = '';
    let selectedBocsName = '';
    let selectedFrequencyId = '';
    let selectedProducts = []; // For custom bocs product selection
    let bocsData = {}; // Store bocs data for frequency lookup
    
    // Initialize bocs data from PHP
    <?php if (isset($bocs_items) && is_array($bocs_items)) : ?>
        <?php foreach ($bocs_items as $bocs) : ?>
            <?php if (isset($bocs['id'])) : ?>
                bocsData['<?php echo esc_js($bocs['id']); ?>'] = <?php echo json_encode($bocs); ?>;
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    
    // Handle select button click
    $(".select-bocs-button").on("click", function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Get selected Bocs data
        selectedBocsId = $(this).data("bocs-id");
        selectedBocsName = $(this).data("bocs-name");
        
        // Update frequency dialog
        $("#frequency-bocs-name").text(selectedBocsName);
        
        // Load frequency options
        loadFrequencyOptions(selectedBocsId);
        
        // Open frequency selection dialog
        $("#frequency-selection-dialog").dialog("open");
    });
    
    // Load frequency options for selected bocs
    function loadFrequencyOptions(bocsId) {
        const frequencyContainer = $('.frequency-options');
        frequencyContainer.empty();
        
        if (!bocsData[bocsId] || !bocsData[bocsId].priceAdjustment || !bocsData[bocsId].priceAdjustment.adjustments) {
            frequencyContainer.html('<p><?php esc_html_e('No frequency options available', 'bocs-wordpress'); ?></p>');
            return;
        }
        
        const adjustments = bocsData[bocsId].priceAdjustment.adjustments;
        
        adjustments.forEach(function(adjustment) {
            // Skip if no frequency or timeUnit
            if (!adjustment.frequency || !adjustment.timeUnit) {
                return;
            }
            
            const frequencyText = adjustment.frequency + ' ' + adjustment.timeUnit;
            let discountText = '';
            
            if (adjustment.discount > 0) {
                discountText = adjustment.discountType === 'dollar' ? 
                    '$' + adjustment.discount + ' discount' : 
                    adjustment.discount + '% discount';
            }
            
            const html = `
                <div class="frequency-option" data-frequency-id="${adjustment.id}">
                    <div class="frequency-details">
                        <span class="frequency-name">${frequencyText}</span>
                        ${discountText ? '<span class="frequency-discount">' + discountText + '</span>' : ''}
                    </div>
                    <div class="frequency-select">
                        <span class="dashicons dashicons-yes-alt" style="display:none;"></span>
                    </div>
                </div>
            `;
            
            frequencyContainer.append(html);
        });
        
        // Add click handler for frequency options
        $('.frequency-option').on('click', function() {
            $('.frequency-option').removeClass('selected');
            $('.frequency-option .dashicons').hide();
            
            $(this).addClass('selected');
            $(this).find('.dashicons')
                .css('transform', 'scale(0)')
                .show()
                .animate({opacity: 1}, 200)
                .css('transform', 'scale(1.2)')
                .animate({transform: 'scale(1)'}, 200);
            
            selectedFrequencyId = $(this).data('frequency-id');
        });
    }
    
    // Process the Bocs switch
    function processSwitchBocs() {
        if (!selectedBocsId || !selectedFrequencyId) {
            return;
        }
        
        // Show loading state
        $(".bocs-switch-container").prepend('<div class="bocs-loading"><?php esc_html_e("Processing your request...", "bocs-wordpress"); ?></div>');
        
        // Get current subscription data
        const currentSubscription = <?php echo json_encode($subscription['data'] ?? []); ?>;
        
        // Get selected frequency details
        const selectedFrequencyObj = bocsData[selectedBocsId].priceAdjustment.adjustments.find(
            adj => adj.id === selectedFrequencyId
        );
        
        if (!selectedFrequencyObj) {
            showErrorMessage('<?php esc_html_e("Selected frequency not found", "bocs-wordpress"); ?>');
            return;
        }
        
        // Prepare data for API request
        const requestData = {
            bocs: {
                id: selectedBocsId
            },
            frequency: {
                id: selectedFrequencyId,
                frequency: selectedFrequencyObj.frequency,
                timeUnit: selectedFrequencyObj.timeUnit,
                discount: selectedFrequencyObj.discount || 0,
                discountType: selectedFrequencyObj.discountType || 'PERCENT'
            }
        };
        
        // Include existing data that should be preserved
        if (currentSubscription) {
            // Copy these fields from the current subscription if they exist
            ['taxLines', 'nextPaymentDateGmt', 'couponLines', 'discountTotal', 
             'shippingTotal', 'shipping', 'billingInterval', 'discountTax'].forEach(field => {
                if (currentSubscription[field] !== undefined) {
                    requestData[field] = currentSubscription[field];
                }
            });
            
            // Copy metadata but update BOCS-specific values
            if (currentSubscription.metaData) {
                const updatedMetaData = [...currentSubscription.metaData];
                
                // Fields to update in metadata
                const metaUpdates = {
                    '__bocs_bocs_id': selectedBocsId,
                    '__bocs_discount_type': selectedFrequencyObj.discountType || 'PERCENT',
                    '__bocs_frequency_id': selectedFrequencyId,
                    '__bocs_frequency_interval': selectedFrequencyObj.frequency,
                    '__bocs_frequency_time_unit': selectedFrequencyObj.timeUnit,
                    '__bocs_id': currentSubscription.id || '',
                    '__bocs_renewal_date': currentSubscription.nextPaymentDateGmt || '',
                };
                
                // Calculate new prices if needed
                if (bocsData[selectedBocsId]) {
                    const bocs = bocsData[selectedBocsId];
                    let subtotal = 0;
                    
                    // For custom box with selected products
                    if (bocs.type === 'custom' && selectedProducts && selectedProducts.length > 0) {
                        subtotal = selectedProducts.reduce((total, product) => {
                            return total + (product.price * product.quantity);
                        }, 0);
                    } 
                    // For fixed box
                    else if (bocs.products && bocs.products.length > 0) {
                        subtotal = bocs.products.reduce((total, product) => {
                            const price = parseFloat(product.price) || 0;
                            const quantity = parseInt(product.quantity) || 1;
                            return total + (price * quantity);
                        }, 0);
                    }
                    
                    // Calculate discount
                    let discountAmount = 0;
                    if (selectedFrequencyObj.discount > 0) {
                        if (selectedFrequencyObj.discountType === 'DOLLAR') {
                            discountAmount = selectedFrequencyObj.discount;
                        } else {
                            discountAmount = (subtotal * selectedFrequencyObj.discount) / 100;
                        }
                    }
                    
                    const total = subtotal - discountAmount;
                    
                    // Update pricing metadata
                    metaUpdates['__bocs_subtotal'] = subtotal.toFixed(2);
                    metaUpdates['__bocs_total'] = total.toFixed(2);
                }
                
                // Update metadata in the array
                Object.entries(metaUpdates).forEach(([key, value]) => {
                    const existingIndex = updatedMetaData.findIndex(item => item.key === key);
                    if (existingIndex >= 0) {
                        updatedMetaData[existingIndex].value = value;
                    } else {
                        updatedMetaData.push({ key, value });
                    }
                });
                
                requestData.metaData = updatedMetaData;
            }
        }
        
        // Add line items for custom box
        if (bocsData[selectedBocsId] && bocsData[selectedBocsId].type === 'custom') {
            requestData.lineItems = selectedProducts
                .filter(product => product.quantity > 0)
                .map(product => ({
                    productId: product.id,
                    name: product.name,
                    quantity: product.quantity,
                    price: product.price,
                    total: (product.price * product.quantity).toFixed(2),
                    metaData: []
                }));
        } else if (bocsData[selectedBocsId] && bocsData[selectedBocsId].products) {
            // Fixed box - use products from the box definition
            requestData.lineItems = bocsData[selectedBocsId].products.map(product => ({
                productId: product.id,
                name: product.name,
                quantity: product.quantity || 1,
                price: parseFloat(product.price) || 0,
                total: ((parseFloat(product.price) || 0) * (product.quantity || 1)).toFixed(2),
                metaData: []
            }));
        }
        
        // Make direct API request
        $.ajax({
            url: '<?php echo esc_js(BOCS_API_URL); ?>subscriptions/<?php echo esc_js($subscription_id); ?>',
            type: 'PUT',
            data: JSON.stringify(requestData),
            contentType: 'application/json',
            headers: {
                <?php foreach ($headers as $key => $value): ?>
                '<?php echo esc_js($key); ?>': '<?php echo esc_js($value); ?>',
                <?php endforeach; ?>
            },
            success: function(response) {
                // Success handling
                const successMessage = '<?php esc_html_e("Your subscription has been successfully switched to", "bocs-wordpress"); ?> ' + 
                    selectedBocsName + '. ' +
                    '<?php esc_html_e("You will be redirected to your subscriptions in a few seconds.", "bocs-wordpress"); ?>';
                
                const successEl = showSuccessMessage(successMessage);
                
                // Add subtle pulse animation to success message
                successEl.css('animation', 'pulse 2s infinite');
                $('head').append(`
                    <style>
                        @keyframes pulse {
                            0% { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
                            50% { box-shadow: 0 4px 20px rgba(76, 175, 80, 0.2); }
                            100% { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
                        }
                    </style>
                `);
                
                // Redirect after delay
                setTimeout(function() {
                    window.location.href = '<?php echo esc_js(wc_get_account_endpoint_url('bocs-subscriptions')); ?>';
                }, 3000);
            },
            error: function(xhr) {
                // Error handling
                $(".bocs-loading").remove();
                let errorMsg = '<?php esc_html_e("There was an error processing your request. Please try again.", "bocs-wordpress"); ?>';
                
                // Try to get more specific error message from response
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    try {
                        const errorData = JSON.parse(xhr.responseText);
                        if (errorData.message) {
                            errorMsg = errorData.message;
                        }
                    } catch (e) {
                        // Parsing error, use default message
                    }
                }
                
                showErrorMessage(errorMsg);
            }
        });
    }
    
    // Helper function to show error messages
    function showErrorMessage(message) {
        $(".bocs-loading").remove();
        $(".woocommerce-error").remove();
        
        const errorEl = $(`
            <div class="woocommerce-error" style="display:flex; align-items:center; border-radius:var(--bocs-border-radius); box-shadow:var(--bocs-box-shadow); padding:16px; margin-bottom:25px;">
                <div style="background:#e53935; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-right:15px;">
                    <svg viewBox="0 0 24 24" width="16" height="16" style="color:white;">
                        <path fill="currentColor" d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z" />
                    </svg>
                </div>
                <div style="color:#c62828; font-weight:500;">${message}</div>
            </div>
        `);
        
        $(".bocs-switch-container").prepend(errorEl);
        return errorEl;
    }
    
    // Function to open product selection dialog
    function openProductSelectionDialog() {
        $("#product-bocs-name").text(selectedBocsName);
        
        // Load product selection UI
        loadProductSelectionUI();
        
        // Open the dialog
        $("#product-selection-dialog").dialog("open");
    }
    
    // Function to load product selection UI
    function loadProductSelectionUI() {
        const bocs = bocsData[selectedBocsId];
        if (!bocs || !bocs.products || !bocs.products.length) {
            $('.bocs-product-selection').html('<p><?php esc_html_e("No products available for this Bocs.", "bocs-wordpress"); ?></p>');
            return;
        }
        
        // Get range info
        let minQuantity = 0;
        let maxQuantity = 0;
        
        if (bocs.range && Array.isArray(bocs.range) && bocs.range.length >= 2) {
            minQuantity = parseInt(bocs.range[0]) || 0;
            maxQuantity = parseInt(bocs.range[1]) || 0;
        }
        
        // Display range info
        let rangeInfo = '';
        if (minQuantity > 0 && maxQuantity > 0) {
            rangeInfo = `<?php esc_html_e("Please select between", "bocs-wordpress"); ?> ${minQuantity} <?php esc_html_e("and", "bocs-wordpress"); ?> ${maxQuantity} <?php esc_html_e("items.", "bocs-wordpress"); ?>`;
        } else if (minQuantity > 0) {
            rangeInfo = `<?php esc_html_e("Please select at least", "bocs-wordpress"); ?> ${minQuantity} <?php esc_html_e("items.", "bocs-wordpress"); ?>`;
        } else if (maxQuantity > 0) {
            rangeInfo = `<?php esc_html_e("Please select up to", "bocs-wordpress"); ?> ${maxQuantity} <?php esc_html_e("items.", "bocs-wordpress"); ?>`;
        }
        
        $('.bocs-product-selection-info').html(`
            <p>${rangeInfo}</p>
            <p><?php esc_html_e("Total selected", "bocs-wordpress"); ?>: <span id="total-selected-quantity">0</span></p>
        `);
        
        // Clear existing products
        $('.bocs-product-selection').empty();
        selectedProducts = [];
        
        // Add each product to the selection UI
        bocs.products.forEach(product => {
            const productImage = product.images && product.images.length > 0 ? 
                product.images[0].url : 
                '<?php echo esc_url(wc_placeholder_img_src()); ?>';
            
            const productPrice = parseFloat(product.price) || 0;
            const formattedPrice = new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: '<?php echo esc_js($subscription["data"]["currency"] ?? "USD"); ?>'
            }).format(productPrice);
            
            // Add to selected products with initial quantity of 0
            selectedProducts.push({
                id: product.id,
                name: product.name,
                quantity: 0,
                price: productPrice
            });
            
            // Unique ID for this product in the UI
            const productUiId = 'product-' + product.id.replace(/[^a-zA-Z0-9]/g, '');
            
            $('.bocs-product-selection').append(`
                <div class="bocs-product-item" data-product-id="${product.id}" data-product-index="${selectedProducts.length - 1}">
                    <div class="bocs-product-details">
                        <div class="bocs-product-image">
                            <img src="${productImage}" alt="${product.name}">
                        </div>
                        <div class="bocs-product-info">
                            <div class="bocs-product-name">${product.name}</div>
                            <div class="bocs-product-price">${formattedPrice}</div>
                        </div>
                    </div>
                    <div class="bocs-product-quantity">
                        <div class="quantity-btn decrease-quantity" data-product-id="${product.id}">-</div>
                        <input type="number" id="${productUiId}" class="quantity-input" value="0" min="0" max="99" data-product-id="${product.id}">
                        <div class="quantity-btn increase-quantity" data-product-id="${product.id}">+</div>
                    </div>
                </div>
            `);
        });
        
        // Add quantity change handlers
        $('.decrease-quantity').on('click', function() {
            const productId = $(this).data('product-id');
            const inputField = $(`input[data-product-id="${productId}"]`);
            let currentVal = parseInt(inputField.val());
            if (currentVal > 0) {
                inputField.val(currentVal - 1);
                updateProductQuantity(productId, currentVal - 1);
            }
        });
        
        $('.increase-quantity').on('click', function() {
            const productId = $(this).data('product-id');
            const inputField = $(`input[data-product-id="${productId}"]`);
            let currentVal = parseInt(inputField.val());
            inputField.val(currentVal + 1);
            updateProductQuantity(productId, currentVal + 1);
        });
        
        $('.quantity-input').on('change', function() {
            const productId = $(this).data('product-id');
            const quantity = parseInt($(this).val()) || 0;
            updateProductQuantity(productId, quantity);
        });
    }
    
    // Function to update product quantity in the selection
    function updateProductQuantity(productId, quantity) {
        // Find product in selected products
        const productIndex = selectedProducts.findIndex(p => p.id === productId);
        if (productIndex !== -1) {
            selectedProducts[productIndex].quantity = quantity;
            updateTotalQuantity();
        }
    }
    
    // Function to update the total quantity counter
    function updateTotalQuantity() {
        const totalQuantity = selectedProducts.reduce((total, product) => total + product.quantity, 0);
        $('#total-selected-quantity').text(totalQuantity);
    }
    
    // Function to validate product selection
    function validateProductSelection() {
        const bocs = bocsData[selectedBocsId];
        if (!bocs || bocs.type !== 'custom') {
            return true; // No validation needed for fixed bocs
        }
        
        // Get range info
        let minQuantity = 0;
        let maxQuantity = 0;
        
        if (bocs.range && Array.isArray(bocs.range) && bocs.range.length >= 2) {
            minQuantity = parseInt(bocs.range[0]) || 0;
            maxQuantity = parseInt(bocs.range[1]) || 0;
        }
        
        // Calculate total selected quantity
        const totalQuantity = selectedProducts.reduce((total, product) => total + product.quantity, 0);
        
        // Validate against min/max
        if (minQuantity > 0 && totalQuantity < minQuantity) {
            alert(`<?php esc_html_e("Please select at least", "bocs-wordpress"); ?> ${minQuantity} <?php esc_html_e("items.", "bocs-wordpress"); ?>`);
            return false;
        }
        
        if (maxQuantity > 0 && totalQuantity > maxQuantity) {
            alert(`<?php esc_html_e("Please select no more than", "bocs-wordpress"); ?> ${maxQuantity} <?php esc_html_e("items.", "bocs-wordpress"); ?>`);
            return false;
        }
        
        if (totalQuantity === 0) {
            alert('<?php esc_html_e("Please select at least one product.", "bocs-wordpress"); ?>');
            return false;
        }
        
        return true;
    }

    // Add Bocs.io branding
    $('body').prepend('<div class="bocs-brand-header"><div class="bocs-logo">bocs<span>.io</span></div></div>');

    // Add CSS for brand header
    $('head').append(`
        <style>
            .bocs-brand-header {
                background: var(--bocs-primary);
                color: white;
                padding: 15px 30px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            
            .bocs-logo {
                font-size: 24px;
                font-weight: 700;
                letter-spacing: 0.5px;
            }
            
            .bocs-logo span {
                font-weight: 400;
                opacity: 0.8;
            }
            
            @media (max-width: 768px) {
                .bocs-brand-header {
                    padding: 12px 20px;
                }
                
                .bocs-logo {
                    font-size: 20px;
                }
            }
        </style>
    `);

    // Enhanced frequency option selection with animation
    $(document).on('click', '.frequency-option', function() {
        $('.frequency-option').removeClass('selected');
        $('.frequency-option .dashicons').hide();
        
        $(this).addClass('selected');
        $(this).find('.dashicons')
            .css('transform', 'scale(0)')
            .show()
            .animate({opacity: 1}, 200)
            .css('transform', 'scale(1.2)')
            .animate({transform: 'scale(1)'}, 200);
        
        selectedFrequencyId = $(this).data('frequency-id');
    });

    // Add hover effect to product cards
    $('.bocs-product-item').hover(
        function() {
            $(this).css('transform', 'translateY(-2px)');
        }, 
        function() {
            $(this).css('transform', 'translateY(0)');
        }
    );

    // Enhance quantity buttons with visual feedback
    $('.quantity-btn').on('mousedown', function() {
        $(this).css('transform', 'scale(0.95)');
    }).on('mouseup mouseleave', function() {
        $(this).css('transform', 'scale(1)');
    });

    // Add success animation
    function showSuccessMessage(message) {
        // Remove any existing messages
        $(".woocommerce-message, .woocommerce-error, .bocs-loading").remove();
        
        // Create success element with animation
        const successEl = $(`
            <div class="woocommerce-message bocs-success-message">
                <div class="bocs-success-icon">
                    <svg viewBox="0 0 24 24" width="24" height="24">
                        <path fill="none" stroke="currentColor" stroke-width="2" d="M1,12 L8,19 L23,5"></path>
                    </svg>
                </div>
                <div class="bocs-success-text">${message}</div>
            </div>
        `);
        
        // Add styles
        $('head').append(`
            <style>
                .bocs-success-message {
                    display: flex !important;
                    align-items: center !important;
                    background: #edfbf3 !important;
                    border-left: 4px solid #4caf50 !important;
                    padding: 16px !important;
                    border-radius: var(--bocs-border-radius) !important;
                    margin-bottom: 25px !important;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important;
                }
                
                .bocs-success-icon {
                    background: #4caf50;
                    width: 32px;
                    height: 32px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-right: 15px;
                    color: white;
                }
                
                .bocs-success-icon svg {
                    stroke-dasharray: 30;
                    stroke-dashoffset: 30;
                    animation: success-check 0.8s ease forwards;
                }
                
                @keyframes success-check {
                    to {
                        stroke-dashoffset: 0;
                    }
                }
                
                .bocs-success-text {
                    font-weight: 500;
                    color: #2e7d32;
                }
            </style>
        `);
        
        // Insert message and animate
        $(".bocs-switch-container").prepend(successEl);
        successEl.hide().fadeIn(300);
        
        return successEl;
    }
});
</script> 