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
.bocs-switch-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.bocs-switch-intro {
    margin-bottom: 20px;
    padding: 15px;
    background: #f7f7f7;
    border-radius: 5px;
}

.bocs-switch-intro p {
    margin-bottom: 10px;
}

.bocs-options-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.bocs-option {
    border: 1px solid #ddd;
    border-radius: 5px;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
    background: #fff;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.bocs-option:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.bocs-option-image img {
    width: 100%;
    height: auto;
    object-fit: cover;
    max-height: 200px;
}

.bocs-option-content {
    padding: 15px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}

.bocs-option-content h3 {
    margin-top: 0;
    margin-bottom: 10px;
}

.bocs-option-description {
    color: #666;
    margin-bottom: 15px;
}

.bocs-option-details {
    margin-bottom: 15px;
    flex-grow: 1;
}

.bocs-option-price {
    font-weight: bold;
    font-size: 1.2em;
    margin-bottom: 10px;
    color: #333;
}

.bocs-option-products {
    background: #f9f9f9;
    padding: 10px;
    border-radius: 4px;
    margin-top: 10px;
}

.bocs-products-title {
    font-weight: 600;
    margin-bottom: 5px;
}

.bocs-option-products ul {
    margin: 0;
    padding-left: 20px;
}

.bocs-option-products li {
    margin-bottom: 3px;
    font-size: 0.9em;
}

/* Frequency Selection Styles */
.frequency-options {
    margin: 15px 0;
}

.frequency-option {
    padding: 12px 15px;
    margin-bottom: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.frequency-option:hover {
    background-color: #f5f5f5;
    border-color: #999;
}

.frequency-option.selected {
    background-color: #f0f7f7;
    border-color: #3c7b7c;
}

.frequency-details {
    display: flex;
    flex-direction: column;
}

.frequency-name {
    font-weight: 600;
}

.frequency-discount {
    font-size: 0.9em;
    color: #d26e4b;
}

.bocs-switch-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 20px;
}

#switch-confirmation-dialog {
    text-align: center;
    line-height: 1.6;
}

.bocs-loading {
    background: #f7f7f7;
    padding: 10px 15px;
    border-radius: 4px;
    margin-bottom: 15px;
    text-align: center;
    font-weight: 600;
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
                
                // Update confirmation dialog with selected frequency
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
            $(this).find('.dashicons').show();
            
            selectedFrequencyId = $(this).data('frequency-id');
        });
    }
    
    // Process the Bocs switch
    function processSwitchBocs() {
        if (!selectedBocsId || !selectedFrequencyId) {
            return;
        }
        
        // Show loading state
        $(".bocs-switch-container").prepend('<div class="bocs-loading">Processing your request...</div>');
        
        // Make API request to switch Bocs
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'switch_bocs_subscription',
                subscription_id: '<?php echo esc_js($subscription_id); ?>',
                bocs_id: selectedBocsId,
                frequency_id: selectedFrequencyId,
                security: '<?php echo wp_create_nonce('switch-bocs-nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Success message
                    $(".bocs-loading").remove();
                    $(".bocs-switch-container").prepend(
                        '<div class="woocommerce-message">' + 
                        'Your subscription has been successfully switched to ' + selectedBocsName + '. ' +
                        'You will be redirected to your subscriptions in a few seconds.' + 
                        '</div>'
                    );
                    
                    // Redirect after delay
                    setTimeout(function() {
                        window.location.href = '<?php echo esc_js(wc_get_account_endpoint_url('bocs-subscriptions')); ?>';
                    }, 3000);
                } else {
                    // Error message
                    $(".bocs-loading").remove();
                    $(".bocs-switch-container").prepend(
                        '<div class="woocommerce-error">' + 
                        (response.data || 'There was an error processing your request. Please try again.') + 
                        '</div>'
                    );
                }
            },
            error: function() {
                // Network error
                $(".bocs-loading").remove();
                $(".bocs-switch-container").prepend(
                    '<div class="woocommerce-error">' + 
                    'There was a network error. Please try again later.' + 
                    '</div>'
                );
            }
        });
    }
});
</script> 