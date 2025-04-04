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
    
    <!-- Add loading overlay -->
    <div class="bocs-loading-overlay" style="display: none;">
        <div class="bocs-loading-content">
            <div class="bocs-loading-spinner"></div>
            <div class="bocs-loading-text"><?php esc_html_e("Processing your request...", "bocs-wordpress"); ?></div>
        </div>
    </div>
    
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
                $current_bocs_type = '';
                if (!empty($current_bocs_id) && isset($bocs_items) && is_array($bocs_items)) {
                    foreach ($bocs_items as $bocs) {
                        if (isset($bocs['id']) && $bocs['id'] === $current_bocs_id) {
                            $current_bocs_name = $bocs['name'];
                            $current_bocs_type = $bocs['type'] ?? '';
                            $current_bocs = $bocs; // Store full current bocs object
                            break;
                        }
                    }
                }
                echo esc_html($current_bocs_name);
                ?>
            </strong>
            <?php if (!empty($current_bocs_type)): ?>
                <span class="bocs-type-badge <?php echo esc_attr(strtolower($current_bocs_type)); ?>-type">
                    <?php echo esc_html(ucfirst(strtolower($current_bocs_type))); ?> <?php esc_html_e('Box', 'bocs-wordpress'); ?>
                </span>
            <?php endif; ?>
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
    
    <div class="bocs-current-options">
        <h3><?php esc_html_e('Update Current Box Options', 'bocs-wordpress'); ?></h3>
        
        <div class="bocs-update-card">
            <div class="bocs-update-section">
                <h4><?php esc_html_e('Change Frequency', 'bocs-wordpress'); ?></h4>
                <p><?php esc_html_e('You can update the frequency of your current box without changing the product selection.', 'bocs-wordpress'); ?></p>
                <button class="button update-frequency-button" data-bocs-id="<?php echo esc_attr($current_bocs_id); ?>" data-bocs-name="<?php echo esc_attr($current_bocs_name); ?>">
                    <?php esc_html_e('Change Frequency', 'bocs-wordpress'); ?>
                </button>
            </div>
            
            <?php if (!empty($current_bocs_type) && $current_bocs_type == 'custom'): ?>
            <div class="bocs-update-section">
                <h4><?php esc_html_e('Update Products', 'bocs-wordpress'); ?></h4>
                <p><?php esc_html_e('You can modify the products in your custom box subscription.', 'bocs-wordpress'); ?></p>
                <button class="button update-products-button" data-bocs-id="<?php echo esc_attr($current_bocs_id); ?>" data-bocs-name="<?php echo esc_attr($current_bocs_name); ?>">
                    <?php esc_html_e('Change Products', 'bocs-wordpress'); ?>
                </button>
            </div>
            <?php elseif (!empty($current_bocs_type) && $current_bocs_type == 'fixed'): ?>
            <div class="bocs-update-section bocs-fixed-products">
                <h4><?php esc_html_e('Update Products', 'bocs-wordpress'); ?></h4>
                <p><?php esc_html_e('This is a fixed box with pre-selected products that cannot be modified.', 'bocs-wordpress'); ?></p>
                <button class="button disabled" disabled>
                    <?php esc_html_e('Change Products', 'bocs-wordpress'); ?>
                </button>
                <div class="bocs-fixed-note">
                    <div class="note-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M12,2C6.48,2 2,6.48 2,12C2,17.52 6.48,22 12,22C17.52,22 22,17.52 22,12C22,6.48 17.52,2 12,2M12,20C7.59,20 4,16.41 4,12C4,7.59 7.59,4 12,4C16.41,4 20,7.59 20,12C20,16.41 16.41,20 12,20M11,7H13V13H11V7M11,15H13V17H11V15Z"/>
                        </svg>
                    </div>
                    <?php esc_html_e('To select different products, switch to a custom box below.', 'bocs-wordpress'); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="bocs-switch-options">
        <h3><?php esc_html_e('Switch to a Different Box', 'bocs-wordpress'); ?></h3>
        <p><?php esc_html_e('Select a new Bocs below to switch your subscription:', 'bocs-wordpress'); ?></p>
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
    
    <!-- Product Selection Dialog -->
    <div id="product-selection-dialog" style="display:none;" title="<?php esc_attr_e('Select Products', 'bocs-wordpress'); ?>">
        <p>
            <?php esc_html_e('Select products for', 'bocs-wordpress'); ?>
            <strong id="products-bocs-name"></strong>:
        </p>
        <div class="product-selection-info">
            <p class="range-info">
                <?php esc_html_e('Please select between', 'bocs-wordpress'); ?> <span id="min-products">0</span> <?php esc_html_e('and', 'bocs-wordpress'); ?> <span id="max-products">0</span> <?php esc_html_e('products', 'bocs-wordpress'); ?>.
            </p>
            <p class="total-selected">
                <?php esc_html_e('Products selected:', 'bocs-wordpress'); ?> <span id="product-count">0</span>
            </p>
        </div>
        <div id="using-current-products" style="display:none;" class="current-products-note">
            <div class="note-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12,2C6.48,2 2,6.48 2,12C2,17.52 6.48,22 12,22C17.52,22 22,17.52 22,12C22,6.48 17.52,2 12,2M12,20C7.59,20 4,16.41 4,12C4,7.59 7.59,4 12,4C16.41,4 20,7.59 20,12C20,16.41 16.41,20 12,20M11,7H13V13H11V7M11,15H13V17H11V15Z"/>
                </svg>
            </div>
            <?php esc_html_e('Showing your current products. Adjust quantities as needed.', 'bocs-wordpress'); ?>
        </div>
        <div class="product-options">
            <!-- Product options will be dynamically loaded here -->
        </div>
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

.bocs-switch-container h3 {
    font-weight: 600;
    margin: 30px 0 15px;
    color: var(--bocs-primary-dark);
    font-size: 1.5em;
    position: relative;
    padding-bottom: 10px;
}

.bocs-switch-container h3:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 60px;
    height: 3px;
    background-color: var(--bocs-secondary);
    border-radius: 3px;
}

.bocs-switch-container h4 {
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--bocs-primary);
    font-size: 1.15em;
}

.bocs-switch-intro {
    margin-bottom: 30px;
    padding: 20px;
    background: var(--bocs-primary-light);
    border-radius: var(--bocs-border-radius);
    box-shadow: var(--bocs-box-shadow);
}

.bocs-current-options {
    margin-bottom: 30px;
}

.bocs-update-card {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    background: #fff;
    border-radius: var(--bocs-border-radius);
    padding: 20px;
    box-shadow: var(--bocs-box-shadow);
    border: 1px solid var(--bocs-gray-medium);
}

.bocs-update-section {
    flex: 1;
    min-width: 250px;
    padding: 15px;
    background: var(--bocs-gray-light);
    border-radius: var(--bocs-border-radius);
    transition: var(--bocs-transition);
}

.bocs-update-section:hover {
    background: #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}

.bocs-update-section p {
    margin-bottom: 15px;
    color: var(--bocs-gray-dark);
}

.update-frequency-button,
.update-products-button {
    background-color: var(--bocs-primary) !important;
    color: white !important;
    border: none !important;
    padding: 10px 15px !important;
    border-radius: 4px !important;
    font-weight: 500 !important;
    transition: var(--bocs-transition) !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.update-frequency-button:hover,
.update-products-button:hover {
    background-color: var(--bocs-primary-dark) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(60, 123, 124, 0.2);
}

/* Disabled button for fixed box */
.bocs-fixed-products button.disabled {
    background-color: #e0e0e0 !important;
    color: #9e9e9e !important;
    cursor: not-allowed !important;
    box-shadow: none !important;
    transform: none !important;
    opacity: 0.7;
}

.bocs-fixed-products button.disabled:hover {
    background-color: #e0e0e0 !important;
    transform: none !important;
    box-shadow: none !important;
}

.bocs-fixed-note {
    margin-top: 15px;
    padding: 10px 12px;
    background-color: #f5f5f5;
    border-radius: 4px;
    font-size: 0.9em;
    color: #666;
    display: flex;
    align-items: center;
    border-left: 3px solid var(--bocs-secondary);
}

.note-icon {
    color: var(--bocs-secondary);
    margin-right: 8px;
    display: flex;
    align-items: center;
}

.bocs-type-badge {
    display: inline-block;
    font-size: 0.8em;
    font-weight: 500;
    padding: 3px 8px;
    border-radius: 30px;
    margin-left: 8px;
    vertical-align: middle;
    text-transform: uppercase;
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

/* Product selection dialog */
.product-selection-info {
    margin-bottom: 20px;
    padding: 10px 15px;
    background-color: var(--bocs-primary-light);
    border-radius: var(--bocs-border-radius);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.product-current-badge {
    display: inline-block;
    background-color: var(--bocs-primary-light);
    color: var(--bocs-primary);
    font-size: 0.75em;
    padding: 3px 8px;
    border-radius: 20px;
    margin-top: 5px;
    border: 1px solid var(--bocs-primary);
    font-weight: 500;
}

.product-option.in-subscription {
    border-color: var(--bocs-primary);
    background-color: rgba(60, 123, 124, 0.05);
}

.product-options {
    max-height: 300px;
    overflow-y: auto;
    padding-right: 5px;
}

.product-option {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    margin-bottom: 10px;
    border: 1px solid var(--bocs-gray-medium);
    border-radius: var(--bocs-border-radius);
    background: #fff;
    transition: var(--bocs-transition);
}

.product-option:hover {
    border-color: var(--bocs-primary);
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.product-info {
    display: flex;
    align-items: center;
    flex: 1;
}

.product-image {
    width: 50px;
    height: 50px;
    margin-right: 15px;
    border-radius: 4px;
    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-details {
    flex: 1;
}

.product-name {
    font-weight: 600;
    margin-bottom: 4px;
    color: #333;
}

.product-price {
    color: var(--bocs-secondary);
    font-weight: 500;
    font-size: 0.9em;
}

.product-description {
    font-size: 0.85em;
    color: var(--bocs-gray-dark);
    margin-top: 5px;
}

.product-quantity {
    display: flex;
    align-items: center;
}

.quantity-btn {
    width: 28px;
    height: 28px;
    background: var(--bocs-gray-light);
    border: 1px solid var(--bocs-gray-medium);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-weight: bold;
    border-radius: 4px;
    transition: var(--bocs-transition);
}

.quantity-btn:hover {
    background: var(--bocs-primary-light);
    border-color: var(--bocs-primary);
}

.quantity-input {
    width: 40px;
    height: 28px;
    text-align: center;
    border: 1px solid var(--bocs-gray-medium);
    margin: 0 5px;
    border-radius: 4px;
}

/* Current frequency badge */
.current-tag {
    display: inline-block;
    background: var(--bocs-primary);
    color: white;
    font-size: 0.7em;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: 6px;
    text-transform: uppercase;
    font-weight: 600;
}

.frequency-option.current {
    border: 1px dashed var(--bocs-primary);
    background-color: var(--bocs-primary-light);
}

/* Current products note */
.current-products-note {
    margin: 0 0 15px 0;
    padding: 10px 12px;
    background-color: var(--bocs-primary-light);
    border-radius: 4px;
    font-size: 0.9em;
    color: var(--bocs-primary-dark);
    display: flex;
    align-items: center;
    border-left: 3px solid var(--bocs-primary);
}

/* Add loading overlay styles */
.bocs-loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 999999;
}

.bocs-loading-content {
    background: white;
    padding: 30px 40px;
    border-radius: var(--bocs-border-radius);
    box-shadow: var(--bocs-box-shadow);
    text-align: center;
}

.bocs-loading-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid var(--bocs-primary-light);
    border-top: 3px solid var(--bocs-primary);
    border-radius: 50%;
    margin: 0 auto 15px;
    animation: bocs-spinner 1s linear infinite;
}

.bocs-loading-text {
    color: var(--bocs-primary-dark);
    font-weight: 500;
    font-size: 1.1em;
}

@keyframes bocs-spinner {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Store data about available Bocs
    const bocsData = <?php echo json_encode($bocs_items ?? []); ?>;
    
    // Convert array to object indexed by ID for easier lookup
    const bocsById = {};
    bocsData.forEach(function(bocs) {
        if (bocs.id) {
            bocsById[bocs.id] = bocs;
        }
    });
    
    // Track selected bocs and frequency
    let selectedBocsId = '';
    let selectedBocsName = '';
    let selectedFrequencyId = '';
    let selectedProducts = [];
    let isUpdatingCurrentBocs = false;
    
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
                
                selectedFrequencyId = selectedFrequency;
                $(this).dialog("close");
                
                if (isUpdatingCurrentBocs) {
                    // If updating the current box frequency, proceed directly
                    processBocsSave();
                } else {
                    // For a new box, continue with product selection if custom box
                    const selectedBocs = bocsById[selectedBocsId];
                    if (selectedBocs && selectedBocs.type && selectedBocs.type.toLowerCase() === 'custom') {
                        // Load products and open product selection dialog
                        loadProductOptions(selectedBocsId);
                        $("#product-selection-dialog").dialog("open");
                        $("#products-bocs-name").text(selectedBocsName);
                    } else {
                        // For fixed box, go directly to confirmation
                        $("#target-frequency").text(selectedFrequencyText);
                        $("#switch-confirmation-dialog").dialog("open");
                    }
                }
            },
            "Cancel": function() {
                $(this).dialog("close");
            }
        }
    });
    
    $("#product-selection-dialog").dialog({
        autoOpen: false,
        modal: true,
        width: 600,
        height: 500,
        buttons: {
            "Continue": function() {
                const selectedCount = selectedProducts.reduce((total, product) => total + product.quantity, 0);
                const minProducts = parseInt($("#min-products").text());
                const maxProducts = parseInt($("#max-products").text());
                
                if (selectedCount < minProducts || selectedCount > maxProducts) {
                    alert(`<?php esc_html_e('Please select between', 'bocs-wordpress'); ?> ${minProducts} <?php esc_html_e('and', 'bocs-wordpress'); ?> ${maxProducts} <?php esc_html_e('products.', 'bocs-wordpress'); ?>`);
                    return;
                }
                
                $(this).dialog("close");
                
                if (isUpdatingCurrentBocs) {
                    // If updating current box products, process directly
                    processBocsSave();
                } else {
                    // For new box selection, continue to confirmation
                    $("#switch-confirmation-dialog").dialog("open");
                }
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
                $(this).dialog("close");
                processSwitchBocs();
            },
            "Cancel": function() {
                $(this).dialog("close");
            }
        }
    });
    
    // Handle selecting a new bocs
    $(".select-bocs-button").on('click', function() {
        isUpdatingCurrentBocs = false;
        selectedBocsId = $(this).data('bocs-id');
        selectedBocsName = $(this).data('bocs-name');
        
        // Set the bocs name in the frequency dialog
        $("#frequency-bocs-name").text(selectedBocsName);
        $("#target-bocs-name").text(selectedBocsName);
        
        // Check if this is a custom box
        const selectedBocs = bocsById[selectedBocsId];
        if (selectedBocs && selectedBocs.type && selectedBocs.type.toLowerCase() === 'custom') {
            console.log('Selected a custom box:', selectedBocsName);
            
            // For new custom box, first select frequency, then products
            // Load frequency options for this bocs
            loadFrequencyOptions(selectedBocsId);
            
            // Open the frequency selection dialog first
            $("#frequency-selection-dialog").dialog("open");
        } else {
            // For fixed box, proceed with frequency selection only
            // Load frequency options for this bocs
            loadFrequencyOptions(selectedBocsId);
            
            // Open the frequency selection dialog
            $("#frequency-selection-dialog").dialog("open");
        }
    });
    
    // Handle updating frequency for current bocs
    $(".update-frequency-button").on('click', function() {
        isUpdatingCurrentBocs = true;
        selectedBocsId = $(this).data('bocs-id');
        selectedBocsName = $(this).data('bocs-name');
        
        // Set the bocs name in the frequency dialog
        $("#frequency-bocs-name").text(selectedBocsName);
        
        // Load frequency options for the current bocs
        loadFrequencyOptions(selectedBocsId);
        
        // Open the frequency selection dialog
        $("#frequency-selection-dialog").dialog("open");
    });
    
    // Handle updating products for current bocs
    $(".update-products-button").on('click', function() {
        isUpdatingCurrentBocs = true;
        selectedBocsId = $(this).data('bocs-id');
        selectedBocsName = $(this).data('bocs-name');
        
        // We need the current frequency to update products only
        if (<?php echo json_encode(isset($subscription['data']['frequency']) && isset($subscription['data']['frequency']['id'])); ?>) {
            selectedFrequencyId = '<?php echo isset($subscription['data']['frequency']['id']) ? esc_js($subscription['data']['frequency']['id']) : ''; ?>';
        }
        
        // Load product options for the current bocs
        loadProductOptions(selectedBocsId);
        
        // Set the bocs name in the product dialog
        $("#products-bocs-name").text(selectedBocsName);
        
        // Open the product selection dialog
        $("#product-selection-dialog").dialog("open");
    });
    
    // Function to load frequency options
    function loadFrequencyOptions(bocsId) {
        const frequencyContainer = $('.frequency-options');
        frequencyContainer.empty();
        
        if (!bocsById[bocsId] || !bocsById[bocsId].priceAdjustment || !bocsById[bocsId].priceAdjustment.adjustments) {
            frequencyContainer.html('<p><?php esc_html_e('No frequency options available', 'bocs-wordpress'); ?></p>');
            return;
        }
        
        const adjustments = bocsById[bocsId].priceAdjustment.adjustments;
        
        // Get current frequency (if updating)
        const currentFrequencyId = '<?php echo isset($subscription['data']['frequency']['id']) ? esc_js($subscription['data']['frequency']['id']) : ''; ?>';
        
        adjustments.forEach(function(adjustment) {
            // Skip if no frequency or timeUnit
            if (!adjustment.frequency || !adjustment.timeUnit) {
                return;
            }
            
            const frequencyText = adjustment.frequency + ' ' + 
                (adjustment.timeUnit.toLowerCase() === 'day' && adjustment.frequency > 1 ? '<?php esc_html_e('days', 'bocs-wordpress'); ?>' : 
                 adjustment.timeUnit.toLowerCase() === 'day' ? '<?php esc_html_e('day', 'bocs-wordpress'); ?>' : 
                 adjustment.timeUnit.toLowerCase() === 'week' && adjustment.frequency > 1 ? '<?php esc_html_e('weeks', 'bocs-wordpress'); ?>' : 
                 adjustment.timeUnit.toLowerCase() === 'week' ? '<?php esc_html_e('week', 'bocs-wordpress'); ?>' : 
                 adjustment.timeUnit.toLowerCase() === 'month' && adjustment.frequency > 1 ? '<?php esc_html_e('months', 'bocs-wordpress'); ?>' : 
                 adjustment.timeUnit.toLowerCase() === 'month' ? '<?php esc_html_e('month', 'bocs-wordpress'); ?>' : adjustment.timeUnit);
            
            // Format discount text
            let discountText = '';
            if (adjustment.discount > 0) {
                discountText = adjustment.discountType === 'DOLLAR' 
                    ? `$${adjustment.discount} <?php esc_html_e('off', 'bocs-wordpress'); ?>` 
                    : `${adjustment.discount}% <?php esc_html_e('off', 'bocs-wordpress'); ?>`;
            }
            
            // Check if this is the current frequency
            const isCurrentFrequency = adjustment.id === currentFrequencyId;
            
            // Create frequency option element with data attributes for discount
            const frequencyOption = $(`
                <div class="frequency-option ${isCurrentFrequency ? 'selected current' : ''}" 
                     data-frequency-id="${adjustment.id}"
                     data-discount="${adjustment.discount || 0}"
                     data-discount-type="${adjustment.discountType || 'PERCENT'}">
                    <div class="frequency-details">
                        <span class="frequency-name">${frequencyText}</span>
                        ${discountText ? `<span class="frequency-discount">${discountText}</span>` : ''}
                        ${isCurrentFrequency ? `<span class="current-tag"><?php esc_html_e('Current Plan', 'bocs-wordpress'); ?></span>` : ''}
                    </div>
                    <span class="dashicons dashicons-yes-alt" ${isCurrentFrequency ? '' : 'style="display:none;"'}></span>
                </div>
            `);
            
            frequencyContainer.append(frequencyOption);
        });
        
        // Add event handlers for selecting frequency options
        $('.frequency-option').on('click', function() {
            $('.frequency-option').removeClass('selected');
            $('.frequency-option .dashicons').hide();
            
            $(this).addClass('selected');
            $(this).find('.dashicons').show();
            
            selectedFrequencyId = $(this).data('frequency-id');
            
            // Store the selected frequency's discount information
            const selectedOption = $(this);
            selectedFrequencyObj = {
                id: selectedFrequencyId,
                frequency: parseInt(selectedOption.find('.frequency-name').text()),
                timeUnit: selectedOption.find('.frequency-name').text().split(' ')[1],
                discount: parseFloat(selectedOption.data('discount')),
                discountType: selectedOption.data('discount-type')
            };
        });
    }
    
    // Function to load product options for custom bocs
    function loadProductOptions(bocsId) {
        const productContainer = $('.product-options');
        productContainer.empty();
        selectedProducts = [];
        $("#using-current-products").hide();
        
        if (!bocsById[bocsId]) {
            productContainer.html('<p><?php esc_html_e('No products available', 'bocs-wordpress'); ?></p>');
            return;
        }
        
        const bocs = bocsById[bocsId];
        
        // Set min/max products based on range
        let minProducts = 1;
        let maxProducts = 10;
        
        if (bocs.range && Array.isArray(bocs.range) && bocs.range.length >= 2) {
            minProducts = parseInt(bocs.range[0]) || 1;
            maxProducts = parseInt(bocs.range[1]) || 10;
        }
        
        $("#min-products").text(minProducts);
        $("#max-products").text(maxProducts);
        
        // Get current products from subscription
        const currentProducts = <?php 
            echo isset($subscription['data']['lineItems']) 
                ? json_encode($subscription['data']['lineItems']) 
                : 'null'; 
        ?>;
        
        // Determine which products to display
        let productsToShow = [];
        let usingCurrentProducts = false;
        
        // Check if we have available products for this box
        if (bocs.availableProducts && Array.isArray(bocs.availableProducts) && bocs.availableProducts.length > 0) {
            productsToShow = bocs.availableProducts;
            
            // If we're updating current box and have subscription items, mark as using current
            if (isUpdatingCurrentBocs && currentProducts && Array.isArray(currentProducts) && currentProducts.length > 0) {
                usingCurrentProducts = true;
            }
        }
        // If no available products in bocs data, try to use API data or current subscription products
        else {
            // Always make an API request to get products for the box, whether updating current or switching
            console.log('Fetching products for box ID:', bocsId);
            $.ajax({
                url: '<?php echo esc_js(BOCS_API_URL); ?>bocs/' + bocsId + '/products',
                type: 'GET',
                async: false,
                headers: {
                    <?php foreach ($headers as $key => $value): ?>
                    '<?php echo esc_js($key); ?>': '<?php echo esc_js($value); ?>',
                    <?php endforeach; ?>
                },
                success: function(response) {
                    console.log('API response for box products:', response);
                    if (response && response.data && Array.isArray(response.data)) {
                        productsToShow = response.data.map(item => ({
                            id: item.id,
                            name: item.name,
                            price: parseFloat(item.price) || 0,
                            images: item.images || [],
                            description: item.description || ''
                        }));
                        if (isUpdatingCurrentBocs && currentProducts && Array.isArray(currentProducts) && currentProducts.length > 0) {
                            usingCurrentProducts = true;
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching products:', status, error);
                    // If API fails, fallback to current subscription products if available
                    if (currentProducts && Array.isArray(currentProducts) && currentProducts.length > 0) {
                        productsToShow = currentProducts.map(item => ({
                            id: item.productId,
                            name: item.name,
                            price: parseFloat(item.price) || 0,
                            images: item.image ? [{ url: item.image }] : [],
                            description: ''
                        }));
                        usingCurrentProducts = true;
                    }
                }
            });
        }
        
        if (productsToShow.length === 0) {
            productContainer.html('<p><?php esc_html_e('No products available for this box. Please contact support for assistance.', 'bocs-wordpress'); ?></p>');
            return;
        }
        
        // Show message if using current subscription products
        if (usingCurrentProducts) {
            $("#using-current-products").show();
        }
        
        // Initialize products array and display product options
        productsToShow.forEach(function(product) {
            let initialQuantity = 0;
            
            // Check if this product is in the current subscription - only set quantity > 0
            // when updating an existing subscription (not when switching)
            if (isUpdatingCurrentBocs && currentProducts && Array.isArray(currentProducts)) {
                const currentProduct = currentProducts.find(item => item.productId === product.id);
                if (currentProduct) {
                    initialQuantity = parseInt(currentProduct.quantity) || 0;
                }
            }
            
            selectedProducts.push({
                id: product.id,
                name: product.name,
                price: parseFloat(product.price) || 0,
                quantity: initialQuantity
            });
            
            // Get image URL or use placeholder
            const productImage = product.images && product.images.length > 0 && product.images[0].url
                ? product.images[0].url
                : '<?php echo esc_url(wc_placeholder_img_src()); ?>';
            
            // Determine if this product is currently in the subscription
            const isInSubscription = initialQuantity > 0;
            
            // Create product option element
            const productOption = $(`
                <div class="product-option ${isInSubscription ? 'in-subscription' : ''}" data-product-id="${product.id}">
                    <div class="product-info">
                        <div class="product-image">
                            <img src="${productImage}" alt="${product.name}">
                        </div>
                        <div class="product-details">
                            <h4 class="product-name">${product.name}</h4>
                            <div class="product-price">${formatPrice(product.price)}</div>
                            ${product.description ? `<p class="product-description">${product.description}</p>` : ''}
                            ${isInSubscription ? '<span class="product-current-badge"><?php esc_html_e("Currently Selected", "bocs-wordpress"); ?></span>' : ''}
                        </div>
                    </div>
                    <div class="product-quantity">
                        <button class="quantity-btn minus" data-product-id="${product.id}">-</button>
                        <input type="number" class="quantity-input" value="${initialQuantity}" min="0" max="99" data-product-id="${product.id}">
                        <button class="quantity-btn plus" data-product-id="${product.id}">+</button>
                    </div>
                </div>
            `);
            
            productContainer.append(productOption);
        });
        
        // Update product count display
        updateProductCount();
        
        // Add event handlers for quantity buttons
        $('.quantity-btn.minus').on('click', function() {
            const productId = $(this).data('product-id');
            const input = $(`.quantity-input[data-product-id="${productId}"]`);
            let value = parseInt(input.val()) || 0;
            
            if (value > 0) {
                value--;
                input.val(value);
                updateProductQuantity(productId, value);
            }
        });
        
        $('.quantity-btn.plus').on('click', function() {
            const productId = $(this).data('product-id');
            const input = $(`.quantity-input[data-product-id="${productId}"]`);
            let value = parseInt(input.val()) || 0;
            
            const selectedCount = selectedProducts.reduce((total, product) => 
                total + (product.id !== productId ? product.quantity : 0), 0);
            
            const maxProducts = parseInt($("#max-products").text());
            
            // Don't allow adding more products if already at max
            if (selectedCount >= maxProducts) {
                alert(`<?php esc_html_e('You can select a maximum of', 'bocs-wordpress'); ?> ${maxProducts} <?php esc_html_e('products.', 'bocs-wordpress'); ?>`);
                return;
            }
            
            value++;
            input.val(value);
            updateProductQuantity(productId, value);
        });
        
        $('.quantity-input').on('change', function() {
            const productId = $(this).data('product-id');
            let value = parseInt($(this).val()) || 0;
            
            if (value < 0) {
                value = 0;
                $(this).val(value);
            }
            
            updateProductQuantity(productId, value);
        });
    }
    
    // Update product quantity in our tracking array
    function updateProductQuantity(productId, quantity) {
        const index = selectedProducts.findIndex(p => p.id === productId);
        if (index !== -1) {
            selectedProducts[index].quantity = quantity;
            updateProductCount();
        }
    }
    
    // Update the displayed product count
    function updateProductCount() {
        const count = selectedProducts.reduce((total, product) => total + product.quantity, 0);
        $("#product-count").text(count);
    }
    
    // Helper function to format price
    function formatPrice(price) {
        return '$' + parseFloat(price).toFixed(2);
    }
    
    // Process saving changes to the current bocs
    function processBocsSave() {
        // Show loading overlay
        $(".bocs-loading-overlay").fadeIn(200);
        
        // Get current subscription data
        const currentSubscription = <?php echo json_encode($subscription['data'] ?? []); ?>;
        
        // Get selected frequency details if updating frequency
        let frequencyData = null;
        let selectedFrequencyObj = null;
        
        if (selectedFrequencyId) {
            selectedFrequencyObj = bocsById[selectedBocsId].priceAdjustment.adjustments.find(
                adj => adj.id === selectedFrequencyId
            );
            
            if (selectedFrequencyObj) {
                frequencyData = {
                    id: selectedFrequencyId,
                    frequency: selectedFrequencyObj.frequency,
                    timeUnit: selectedFrequencyObj.timeUnit,
                    discount: selectedFrequencyObj.discount || 0,
                    discountType: selectedFrequencyObj.discountType || 'PERCENT'
                };
            }
        }
        
        // Prepare data for API request
        const requestData = {
            bocs: {
                id: selectedBocsId
            }
        };
        
        // Add frequency if we're updating it
        if (frequencyData) {
            requestData.frequency = frequencyData;
        } else if (currentSubscription.frequency) {
            // Keep existing frequency
            requestData.frequency = currentSubscription.frequency;
        }
        
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
                    '__bocs_bocs_id': String(selectedBocsId),
                    '__bocs_discount_type': selectedFrequencyObj ? selectedFrequencyObj.discountType || 'PERCENT' : 'PERCENT',
                    '__bocs_frequency_id': String(selectedFrequencyId || ''),
                    '__bocs_frequency_interval': String(selectedFrequencyObj ? selectedFrequencyObj.frequency : ''),
                    '__bocs_frequency_time_unit': selectedFrequencyObj ? selectedFrequencyObj.timeUnit : '',
                    '__bocs_id': String(currentSubscription.id || ''),
                    '__bocs_renewal_date': currentSubscription.nextPaymentDateGmt || '',
                };
                
                // Calculate new prices if needed
                if (bocsById[selectedBocsId]) {
                    const bocs = bocsById[selectedBocsId];
                    let subtotal = 0;
                    
                    // For custom box with selected products
                    if (bocs.type === 'custom' && selectedProducts && selectedProducts.length > 0 && 
                        selectedProducts.some(p => p.quantity > 0)) {
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
                        if (selectedFrequencyObj.discountType.toLowerCase() === 'dollar') {
                            discountAmount = selectedFrequencyObj.discount;
                        } else {
                            // For percentage discounts, calculate based on current subscription line items
                            // if we're not switching products
                            let calculateSubtotal = subtotal;
                            
                            // If we haven't already selected new products but have current subscription items
                            if ((!selectedProducts || !selectedProducts.some(p => p.quantity > 0)) && 
                                currentSubscription.lineItems && currentSubscription.lineItems.length > 0) {
                                calculateSubtotal = currentSubscription.lineItems.reduce((total, item) => {
                                    const price = parseFloat(item.price) || 0;
                                    const quantity = parseInt(item.quantity) || 1;
                                    return total + (price * quantity);
                                }, 0);
                                console.log('Using current subscription line items for percentage discount calculation:', calculateSubtotal);
                            }
                            
                            discountAmount = (calculateSubtotal * selectedFrequencyObj.discount) / 100;
                        }
                    }
                    
                    const total = subtotal - discountAmount;
                    
                    // Update pricing metadata - ensure all values are strings
                    metaUpdates['__bocs_subtotal'] = String(subtotal.toFixed(2));
                    metaUpdates['__bocs_total'] = String(total.toFixed(2));
                    metaUpdates['__bocs_discount'] = String(selectedFrequencyObj.discount || '0');
                    metaUpdates['__bocs_discount_amount'] = String(discountAmount.toFixed(2));
                    
                    // Update discountTotal and couponLines in requestData
                    requestData.discountTotal = discountAmount;
                    if (discountAmount > 0) {
                        requestData.couponLines = [{
                            code: 'FREQUENCY_DISCOUNT',
                            discount: discountAmount,
                            discountTax: 0
                        }];
                    }
                }
                
                metaUpdates['__bocs_renewal_date'] = currentSubscription.nextPaymentDateGmt || '';
                
                // Calculate new prices if needed
                const bocs = bocsById[selectedBocsId];
                let subtotal = 0;
                
                // For custom box with updated products
                if (bocs.type === 'custom' && selectedProducts && selectedProducts.length > 0 && 
                    selectedProducts.some(p => p.quantity > 0)) {
                    subtotal = selectedProducts.reduce((total, product) => {
                        return total + (product.price * product.quantity);
                    }, 0);
                } 
                // For fixed box or if products not updated
                else if (bocs.products && bocs.products.length > 0) {
                    subtotal = bocs.products.reduce((total, product) => {
                        const price = parseFloat(product.price) || 0;
                        const quantity = parseInt(product.quantity) || 1;
                        return total + (price * quantity);
                    }, 0);
                }
                
                // Calculate discount
                let discountAmount = 0;
                const discountObj = frequencyData || currentSubscription.frequency;
                
                if (discountObj && discountObj.discount > 0) {
                    if (discountObj.discountType.toLowerCase() === 'dollar') {
                        discountAmount = discountObj.discount;
                    } else {
                        // For percentage discounts, we need to calculate based on appropriate product data
                        let calculateSubtotal = subtotal;
                        
                        // If we're not selecting new products (not custom box or no products selected)
                        // and we have current line items, use those for percentage calculation
                        if ((!bocs.type || bocs.type !== 'custom' || 
                             !selectedProducts || !selectedProducts.some(p => p.quantity > 0)) && 
                            currentSubscription.lineItems && currentSubscription.lineItems.length > 0) {
                            calculateSubtotal = currentSubscription.lineItems.reduce((total, item) => {
                                const price = parseFloat(item.price) || 0;
                                const quantity = parseInt(item.quantity) || 1;
                                return total + (price * quantity);
                            }, 0);
                            console.log('Using current subscription line items for percentage discount calculation:', calculateSubtotal);
                        }
                        
                        discountAmount = (calculateSubtotal * discountObj.discount) / 100;
                    }
                }
                
                const total = subtotal - discountAmount;
                
                // Update pricing metadata
                metaUpdates['__bocs_subtotal'] = subtotal.toFixed(2);
                metaUpdates['__bocs_total'] = total.toFixed(2);
                
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
        
        // Add line items if updating products
        if (bocsById[selectedBocsId].type === 'custom' && selectedProducts && 
            selectedProducts.length > 0 && selectedProducts.some(p => p.quantity > 0)) {
            requestData.lineItems = selectedProducts
                .filter(product => product.quantity > 0)
                .map(product => ({
                    productId: product.id,
                    name: product.name,
                    quantity: product.quantity,
                    price: product.price,
                    total: product.price * product.quantity, // Remove toFixed(2) to keep as number
                    metaData: []
                }));
        }
        
        // Get total from metadata
        if (currentSubscription && currentSubscription.metaData) {
            const totalMeta = currentSubscription.metaData.find(item => item.key === '__bocs_total');
            if (totalMeta && totalMeta.value) {
                requestData.total = parseFloat(totalMeta.value);
                console.log('Using total from metadata:', requestData.total);
            }
        }
        
        // Log the request data for debugging
        console.log('API Request Data:', JSON.stringify(requestData, null, 2));
        
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
                // Hide loading overlay
                $(".bocs-loading-overlay").fadeOut(200);
                
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
                
                // Trigger email notification via AJAX
                $.ajax({
                    url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                    type: 'POST',
                    data: {
                        action: 'bocs_trigger_subscription_switched_email',
                        subscription_id: '<?php echo esc_js($subscription_id); ?>',
                        security: '<?php echo esc_js(wp_create_nonce('bocs-subscription-switched')); ?>'
                    },
                    success: function(emailResponse) {
                        console.log('Email notification sent:', emailResponse);
                    },
                    error: function(xhr) {
                        console.error('Failed to send email notification:', xhr.responseText);
                    }
                });
                
                // Redirect after delay
                setTimeout(function() {
                    window.location.reload();
                }, 3000);
            },
            error: function(xhr) {
                // Hide loading overlay
                $(".bocs-loading-overlay").fadeOut(200);
                
                // Error handling
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
    
    // Process the Bocs switch
    function processSwitchBocs() {
        if (!selectedBocsId || !selectedFrequencyId) {
            return;
        }
        
        // Show loading overlay
        $(".bocs-loading-overlay").fadeIn(200);
        
        // Get current subscription data
        const currentSubscription = <?php echo json_encode($subscription['data'] ?? []); ?>;
        
        // Get selected frequency details
        const selectedFrequencyObj = bocsById[selectedBocsId].priceAdjustment.adjustments.find(
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
                    '__bocs_bocs_id': String(selectedBocsId),
                    '__bocs_discount_type': selectedFrequencyObj.discountType || 'PERCENT',
                    '__bocs_frequency_id': String(selectedFrequencyId),
                    '__bocs_frequency_interval': String(selectedFrequencyObj.frequency),
                    '__bocs_frequency_time_unit': selectedFrequencyObj.timeUnit,
                    '__bocs_id': String(currentSubscription.id || ''),
                    '__bocs_renewal_date': currentSubscription.nextPaymentDateGmt || '',
                };
                
                // Calculate new prices if needed
                if (bocsById[selectedBocsId]) {
                    const bocs = bocsById[selectedBocsId];
                    let subtotal = 0;
                    
                    // For custom box with selected products
                    if (bocs.type === 'custom' && selectedProducts && selectedProducts.length > 0 && 
                        selectedProducts.some(p => p.quantity > 0)) {
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
                        if (selectedFrequencyObj.discountType.toLowerCase() === 'dollar') {
                            discountAmount = selectedFrequencyObj.discount;
                        } else {
                            // For percentage discounts, we need to calculate based on appropriate product data
                            let calculateSubtotal = subtotal;
                            
                            // If we're not selecting new products (not custom box or no products selected)
                            // and we have current line items, use those for percentage calculation
                            if ((!bocs.type || bocs.type !== 'custom' || 
                                 !selectedProducts || !selectedProducts.some(p => p.quantity > 0)) && 
                                currentSubscription.lineItems && currentSubscription.lineItems.length > 0) {
                                calculateSubtotal = currentSubscription.lineItems.reduce((total, item) => {
                                    const price = parseFloat(item.price) || 0;
                                    const quantity = parseInt(item.quantity) || 1;
                                    return total + (price * quantity);
                                }, 0);
                                console.log('Using current subscription line items for percentage discount calculation:', calculateSubtotal);
                            }
                            
                            discountAmount = (calculateSubtotal * selectedFrequencyObj.discount) / 100;
                        }
                    }
                    
                    const total = subtotal - discountAmount;
                    
                    // Update pricing metadata - ensure all values are strings
                    metaUpdates['__bocs_subtotal'] = String(subtotal.toFixed(2));
                    metaUpdates['__bocs_total'] = String(total.toFixed(2));
                    metaUpdates['__bocs_discount'] = String(selectedFrequencyObj.discount || '0');
                    metaUpdates['__bocs_discount_amount'] = String(discountAmount.toFixed(2));
                    
                    // Update discountTotal and couponLines in requestData
                    requestData.discountTotal = discountAmount;
                    if (discountAmount > 0) {
                        requestData.couponLines = [{
                            code: 'FREQUENCY_DISCOUNT',
                            discount: discountAmount,
                            discountTax: 0
                        }];
                    }
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
        if (bocsById[selectedBocsId] && bocsById[selectedBocsId].type === 'custom') {
            requestData.lineItems = selectedProducts
                .filter(product => product.quantity > 0)
                .map(product => ({
                    productId: product.id,
                    name: product.name,
                    quantity: product.quantity,
                    price: product.price,
                    total: product.price * product.quantity, // Remove toFixed(2) to keep as number
                    metaData: []
                }));
        } else if (bocsById[selectedBocsId] && bocsById[selectedBocsId].products) {
            // Fixed box - use products from the box definition
            requestData.lineItems = bocsById[selectedBocsId].products.map(product => ({
                productId: product.id,
                name: product.name,
                quantity: product.quantity || 1,
                price: parseFloat(product.price) || 0,
                total: (parseFloat(product.price) || 0) * (product.quantity || 1), // Remove toFixed(2) to keep as number
                metaData: []
            }));
        }
        
        // Get total from metadata
        if (currentSubscription && currentSubscription.metaData) {
            const totalMeta = currentSubscription.metaData.find(item => item.key === '__bocs_total');
            if (totalMeta && totalMeta.value) {
                requestData.total = parseFloat(totalMeta.value);
                console.log('Using total from metadata:', requestData.total);
            }
        }
        
        // Log the request data for debugging
        console.log('API Request Data:', JSON.stringify(requestData, null, 2));
        
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
                // Hide loading overlay
                $(".bocs-loading-overlay").fadeOut(200);
                
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
                
                // Trigger email notification via AJAX
                $.ajax({
                    url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                    type: 'POST',
                    data: {
                        action: 'bocs_trigger_subscription_switched_email',
                        subscription_id: '<?php echo esc_js($subscription_id); ?>',
                        security: '<?php echo esc_js(wp_create_nonce('bocs-subscription-switched')); ?>'
                    },
                    success: function(emailResponse) {
                        console.log('Email notification sent:', emailResponse);
                    },
                    error: function(xhr) {
                        console.error('Failed to send email notification:', xhr.responseText);
                    }
                });
                
                // Redirect after delay
                setTimeout(function() {
                    window.location.reload();
                }, 3000);
            },
            error: function(xhr) {
                // Hide loading overlay
                $(".bocs-loading-overlay").fadeOut(200);
                
                // Error handling
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
    
    // Helper function to show error messages - was missing and causing the ReferenceError
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
        $(this).find('.dashicons').show();
        
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