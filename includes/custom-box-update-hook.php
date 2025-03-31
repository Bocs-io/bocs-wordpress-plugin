<?php
/**
 * Custom Box Update Hook for BOCS WordPress Plugin
 *
 * This file adds a custom JavaScript action to trigger the box update email
 * when the "Save Changes" button is clicked and processing is successful.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Add custom JavaScript to monitor and trigger email notification
 * when the Save Changes button is clicked and successfully processes
 */
function bocs_add_custom_box_update_script() {
    // Only run on the box update page
    global $wp;
    if (!isset($wp->query_vars['bocs-update-box'])) {
        return;
    }
    
    // Get the current subscription ID from URL
    $subscription_id = isset($_GET['subscription_id']) ? sanitize_text_field($_GET['subscription_id']) : '';
    if (empty($subscription_id)) {
        return;
    }
    
    // Create a nonce for security
    $nonce = wp_create_nonce('bocs-box-updated');
    
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Listen for successful box update completion
        // This will override the default behavior and manually trigger our email
        $(document).on('bocs_box_update_success', function(e, response) {
            console.log('Box update success detected, triggering email notification');
            
            // Only send this if we have a successful update
            if (response && response.code === 200) {
                // Prepare the data to send
                var emailData = {
                    action: 'bocs_trigger_box_updated_email',
                    subscription_id: '<?php echo esc_js($subscription_id); ?>',
                    security: '<?php echo esc_js($nonce); ?>'
                };
                
                // Send email notification request
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', emailData)
                    .done(function(emailResponse) {
                        console.log('Email notification triggered:', emailResponse);
                        
                        // Direct emergency fallback - send direct email via PHP
                        $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                            action: 'bocs_direct_email_fallback',
                            subscription_id: '<?php echo esc_js($subscription_id); ?>',
                            security: '<?php echo wp_create_nonce('bocs-direct-email'); ?>'
                        })
                        .done(function(directResponse) {
                            console.log('Direct email fallback attempted:', directResponse);
                        })
                        .fail(function(xhr, status, error) {
                            console.error('Failed to trigger direct email fallback:', error);
                        });
                    })
                    .fail(function(xhr, status, error) {
                        console.error('Failed to trigger email notification:', error);
                    });
            }
        });

        // Override the default Save Changes button behavior
        $('#save-box-changes').off('click').on('click', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var originalText = button.html();
            
            // Filter out products with quantity 0
            var selectedProducts = boxProducts.filter(function(product) {
                return product.quantity > 0;
            });
            
            if (selectedProducts.length === 0) {
                showNotification('error', '<?php _e('Please add at least one product to your box.', 'bocs-wordpress'); ?>');
                return;
            }
            
            // Show loading state
            button.html('<span class="bocs-loading-spinner"></span> <?php _e('Saving...', 'bocs-wordpress'); ?>').prop('disabled', true);
            
            // Generate complete line items
            var lineItems = generateLineItems(selectedProducts, <?php echo floatval(get_option('woocommerce_tax_rate', 0)); ?>, true);
            
            // Make the API request
            $.ajax({
                url: '<?php echo esc_url(BOCS_API_URL); ?>subscriptions/<?php echo esc_js($subscription_id); ?>',
                type: 'PUT',
                data: JSON.stringify({ lineItems: lineItems }),
                contentType: 'application/json',
                timeout: 30000,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('Store', bocsHeaders.store);
                    xhr.setRequestHeader('Organization', bocsHeaders.organization);
                    xhr.setRequestHeader('Authorization', bocsHeaders.authorization);
                },
                success: function(response) {
                    console.log('API Response:', response);
                    if (response && response.code === 200) {
                        // Show success message
                        showNotification('success', '<?php _e('Your box has been updated successfully!', 'bocs-wordpress'); ?>');
                        
                        // Trigger our custom event
                        $(document).trigger('bocs_box_update_success', [response]);
                        
                        // Redirect after a short delay
                        setTimeout(function() {
                            window.location.href = '<?php echo esc_url(wc_get_account_endpoint_url('bocs-subscriptions')); ?>';
                        }, 2000);
                    } else {
                        // Show error message
                        var errorMessage = response && response.message ? response.message : '<?php _e('An error occurred while updating your box.', 'bocs-wordpress'); ?>';
                        showNotification('error', errorMessage);
                        button.html(originalText).prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    showNotification('error', '<?php _e('Unable to update your box. Please try again later.', 'bocs-wordpress'); ?>');
                    button.html(originalText).prop('disabled', false);
                }
            });
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'bocs_add_custom_box_update_script', 100); 