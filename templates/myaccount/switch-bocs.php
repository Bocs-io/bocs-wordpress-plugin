<?php
/**
 * BOCS Switch Box Template
 *
 * Displays a page where customers can switch their subscription from one Box type to another.
 * This template handles the UI/UX for box switching functionality.
 *
 * @package BOCS
 * @subpackage Templates/MyAccount
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

// Ensure script and style dependencies are loaded
wp_enqueue_script('bocs-switch-bocs', BOCS_PLUGIN_URL . 'assets/js/bocs-switch-bocs.js', array('jquery'), '20250415.5', true);
wp_enqueue_style('bocs-switch-bocs', BOCS_PLUGIN_URL . 'assets/css/bocs-switch-bocs.css', array(), '20250415.4');

// Add additional inline styles for product selection
wp_add_inline_style('bocs-switch-bocs', '
    .product-options {
        margin: 20px 0;
        max-height: 400px;
        overflow-y: auto;
    }
    
    .product-option {
        border: 1px solid var(--bocs-border);
        border-radius: var(--bocs-radius);
        padding: 15px;
        margin-bottom: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--bocs-white);
        transition: all 0.2s ease;
    }
    
    .product-option:hover {
        box-shadow: var(--bocs-shadow);
        transform: translateY(-2px);
    }
    
    .product-info {
        display: flex;
        align-items: center;
        flex: 1;
    }
    
    .product-image {
        width: 80px;
        height: 80px;
        margin-right: 15px;
        border-radius: var(--bocs-radius);
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
        margin: 0 0 5px;
        color: var(--bocs-primary-dark);
    }
    
    .product-price {
        font-weight: 600;
        color: var(--bocs-text);
        margin-bottom: 5px;
    }
    
    .product-description {
        font-size: 0.9em;
        color: var(--bocs-text-light);
        margin: 0;
    }
    
    .product-quantity {
        display: flex;
        align-items: center;
        margin-left: 15px;
    }
    
    .quantity-btn {
        width: 30px;
        height: 30px;
        border: none;
        background: var(--bocs-primary);
        color: white;
        font-size: 16px;
        cursor: pointer;
        border-radius: 4px;
        transition: all 0.2s;
    }
    
    .quantity-btn:hover {
        background: var(--bocs-primary-dark);
    }
    
    .quantity-input {
        width: 40px;
        height: 30px;
        text-align: center;
        margin: 0 5px;
        border: 1px solid var(--bocs-border);
        border-radius: 4px;
    }
    
    .product-selection-info {
        background: var(--bocs-primary-light);
        padding: 10px 15px;
        border-radius: var(--bocs-radius);
        margin-bottom: 15px;
        font-weight: 500;
    }
');

// Also include the subscription styles since we want to maintain visual consistency
wp_enqueue_style('bocs-subscriptions', BOCS_PLUGIN_URL . 'assets/css/bocs-subscriptions.css', array(), '20250423.5');

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

// Fetch frequency options
$url = BOCS_API_URL . 'frequencies';
$frequency_options = $helper->curl_request($url, 'GET', [], $headers);

// Format frequencies for JavaScript
$formatted_frequencies = [];
if (!is_wp_error($frequency_options) && isset($frequency_options['data']) && isset($frequency_options['data']['data'])) {
    $formatted_frequencies = $frequency_options['data']['data'];
}

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

// Prepare data for javascript
wp_localize_script('bocs-switch-bocs', 'bocsSwitchData', array(
    'subscriptionId' => $subscription_id,
    'currentBocsId' => $current_bocs_id,
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('bocs-switch-nonce'),
    'apiUrl' => BOCS_API_URL,
    'availableBoxes' => $bocs_items,
    'headers' => array(
        'store' => $options['bocs_headers']['store'],
        'organization' => $options['bocs_headers']['organization'],
        'authorization' => $options['bocs_headers']['authorization']
    ),
    'frequencies' => $formatted_frequencies,
    'i18n' => array(
        'processing' => __('Processing...', 'bocs-wordpress'),
        'saveChanges' => __('Save Changes', 'bocs-wordpress'),
        'loading' => __('Loading...', 'bocs-wordpress')
    )
));

// Log template loading - for debugging
if (class_exists('Bocs_Log_Handler')) {
    $logger = new Bocs_Log_Handler();
    $logger->insert_log('debug', '[Switch Bocs Template] Template loaded', [
        'template' => 'switch-bocs.php',
        'subscription_id' => $subscription_id,
        'time' => current_time('mysql')
    ]);
}
?>

<div class="bocs-switch-container">
    <h2><?php esc_html_e('Switch Box Subscription', 'bocs-wordpress'); ?></h2>
    
    <!-- Loading overlay -->
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
                esc_html_e('There was an error loading your subscription or available Box options. Please try again later.', 'bocs-wordpress');
            }
            ?>
        </div>
        <p>
            <a href="<?php echo esc_url(wc_get_account_endpoint_url('bocs-subscriptions')); ?>" class="bocs-button">
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
    </div>
    
    <h3 class="bocs-section-heading"><?php esc_html_e('Available Box Options', 'bocs-wordpress'); ?></h3>
    
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
                        <button class="bocs-button select-bocs-button" 
                                data-bocs-id="<?php echo esc_attr($bocs_id); ?>" 
                                data-bocs-name="<?php echo esc_attr($bocs_name); ?>"
                                data-bocs-type="<?php echo esc_attr(isset($bocs['type']) ? strtolower($bocs['type']) : ''); ?>">
                            <?php esc_html_e('Choose Frequency', 'bocs-wordpress'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <p><?php esc_html_e('No other Box options available at this time.', 'bocs-wordpress'); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="bocs-switch-actions">
        <a href="<?php echo esc_url(wc_get_account_endpoint_url('bocs-subscriptions')); ?>" class="bocs-button cancel">
            <?php esc_html_e('Cancel', 'bocs-wordpress'); ?>
        </a>
    </div>
    
    <!-- Frequency Selection Dialog -->
    <div id="frequency-selection-dialog" class="bocs-modal">
        <div class="bocs-modal-content">
            <span class="bocs-modal-close">&times;</span>
            <h3><?php esc_html_e('Select Frequency', 'bocs-wordpress'); ?></h3>
            <p>
                <?php esc_html_e('Select frequency for', 'bocs-wordpress'); ?>
                <strong id="frequency-bocs-name"></strong>:
            </p>
            <div class="frequency-options">
                <!-- Frequency options will be dynamically loaded here -->
            </div>
            <div class="bocs-form-actions">
                <button type="button" class="bocs-button cancel"><?php esc_html_e('Cancel', 'bocs-wordpress'); ?></button>
                <button type="button" class="bocs-button primary confirm-frequency"><?php esc_html_e('Continue', 'bocs-wordpress'); ?></button>
            </div>
        </div>
    </div>
    
    <!-- Confirmation Dialog -->
    <div id="switch-confirmation-dialog" class="bocs-modal">
        <div class="bocs-modal-content">
            <span class="bocs-modal-close">&times;</span>
            <h3><?php esc_html_e('Confirm Box Switch', 'bocs-wordpress'); ?></h3>
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
                <?php esc_html_e('Your next box will be updated with the new Box selection.', 'bocs-wordpress'); ?>
            </p>
            <div class="bocs-form-actions">
                <button type="button" class="bocs-button cancel"><?php esc_html_e('Cancel', 'bocs-wordpress'); ?></button>
                <button type="button" class="bocs-button primary confirm-switch"><?php esc_html_e('Confirm Switch', 'bocs-wordpress'); ?></button>
            </div>
        </div>
    </div>
    
    <!-- Product Selection Dialog -->
    <div id="product-selection-dialog" class="bocs-modal">
        <div class="bocs-modal-content">
            <span class="bocs-modal-close">&times;</span>
            <h3><?php esc_html_e('Select Products', 'bocs-wordpress'); ?></h3>
            <p class="product-selection-info">
                <?php esc_html_e('Please select between', 'bocs-wordpress'); ?> 
                <span id="min-products">1</span> <?php esc_html_e('and', 'bocs-wordpress'); ?> 
                <span id="max-products">10</span> <?php esc_html_e('products', 'bocs-wordpress'); ?>.
                (<?php esc_html_e('Products selected', 'bocs-wordpress'); ?>: <span id="product-count">0</span>)
            </p>
            <div id="product-selection-content" class="product-options">
                <!-- Product options will be dynamically loaded here -->
            </div>
            <div class="bocs-form-actions">
                <button type="button" class="bocs-button cancel"><?php esc_html_e('Cancel', 'bocs-wordpress'); ?></button>
                <button type="button" id="confirm-products" class="bocs-button primary" disabled><?php esc_html_e('Continue', 'bocs-wordpress'); ?></button>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div> 