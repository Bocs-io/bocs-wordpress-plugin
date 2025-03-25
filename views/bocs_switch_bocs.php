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

// Fetch current subscription details
$url = BOCS_API_URL . 'subscriptions/' . $subscription_id;
$subscription = $helper->curl_request($url, 'GET', [], $GLOBALS['bocs_headers'] ?? []);

// Fetch available Bocs options
$url = BOCS_API_URL . 'bocs?status=active';
$available_bocs = $helper->curl_request($url, 'GET', [], $GLOBALS['bocs_headers'] ?? []);

// Check for API errors
$has_errors = false;
if (!isset($subscription['data']) || empty($subscription['data'])) {
    $has_errors = true;
}

if (!isset($available_bocs['data']) || empty($available_bocs['data'])) {
    $has_errors = true;
}

// Get current Bocs ID
$current_bocs_id = '';
if (isset($subscription['data']['bocs']) && isset($subscription['data']['bocs']['id'])) {
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
            <?php esc_html_e('There was an error loading your subscription or available Bocs options. Please try again later.', 'bocs-wordpress'); ?>
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
            <strong><?php echo esc_html($subscription['data']['bocs']['name'] ?? ''); ?></strong>
        </p>
        <p>
            <?php esc_html_e('Select a new Bocs below to switch your subscription:', 'bocs-wordpress'); ?>
        </p>
    </div>
    
    <div class="bocs-options-grid">
        <?php if (isset($available_bocs['data']) && is_array($available_bocs['data'])) : ?>
            <?php foreach ($available_bocs['data'] as $bocs) : ?>
                <?php 
                // Skip current Bocs
                if ($current_bocs_id == $bocs['id']) {
                    continue;
                }
                
                // Extract Bocs details
                $bocs_id = isset($bocs['id']) ? sanitize_text_field($bocs['id']) : '';
                $bocs_name = isset($bocs['name']) ? sanitize_text_field($bocs['name']) : '';
                $bocs_description = isset($bocs['description']) ? sanitize_text_field($bocs['description']) : '';
                $bocs_price = isset($bocs['price']) ? floatval($bocs['price']) : 0;
                $bocs_image = isset($bocs['imageUrl']) ? esc_url($bocs['imageUrl']) : '';
                
                // Use placeholder image if none provided
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
                        <p class="bocs-option-price"><?php echo $helper->format_price($bocs_price, $subscription['data']['currency'] ?? ''); ?></p>
                        <button class="button select-bocs-button" data-bocs-id="<?php echo esc_attr($bocs_id); ?>" data-bocs-name="<?php echo esc_attr($bocs_name); ?>">
                            <?php esc_html_e('Select this Bocs', 'bocs-wordpress'); ?>
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
    
    <!-- Confirmation Dialog -->
    <div id="switch-confirmation-dialog" style="display:none;" title="<?php esc_attr_e('Confirm Bocs Switch', 'bocs-wordpress'); ?>">
        <p>
            <?php esc_html_e('Are you sure you want to switch from', 'bocs-wordpress'); ?>
            <strong><?php echo esc_html($subscription['data']['bocs']['name'] ?? ''); ?></strong>
            <?php esc_html_e('to', 'bocs-wordpress'); ?>
            <strong id="target-bocs-name"></strong>?
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
}

.bocs-option-content h3 {
    margin-top: 0;
    margin-bottom: 10px;
}

.bocs-option-description {
    color: #666;
    margin-bottom: 15px;
}

.bocs-option-price {
    font-weight: bold;
    font-size: 1.2em;
    margin-bottom: 15px;
    color: #333;
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
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize dialog
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
    
    // Selected Bocs data
    let selectedBocsId = '';
    let selectedBocsName = '';
    
    // Handle select button click
    $(".select-bocs-button").on("click", function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Get selected Bocs data
        selectedBocsId = $(this).data("bocs-id");
        selectedBocsName = $(this).data("bocs-name");
        
        // Update dialog
        $("#target-bocs-name").text(selectedBocsName);
        
        // Open confirmation dialog
        $("#switch-confirmation-dialog").dialog("open");
    });
    
    // Process the Bocs switch
    function processSwitchBocs() {
        if (!selectedBocsId) {
            return;
        }
        
        // Show loading state
        $(".bocs-switch-container").prepend('<div class="bocs-loading">Processing your request...</div>');
        
        // Prepare data for API
        const subscriptionId = '<?php echo esc_js($subscription_id); ?>';
        const data = {
            bocsId: selectedBocsId
        };
        
        // Make API request to switch Bocs
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'switch_bocs_subscription',
                subscription_id: subscriptionId,
                bocs_id: selectedBocsId,
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