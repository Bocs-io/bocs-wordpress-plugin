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
    
    // Create a nonce for security - use longer expiration
    $nonce = wp_create_nonce('bocs-box-updated');
    error_log('BOCS EMAIL DEBUG: Generated nonce for box update email: ' . $nonce . ' for subscription ID: ' . $subscription_id);
    
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
                
                // Output for debugging
                console.log('Sending email trigger with data:', emailData);
                
                // Send email notification request
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: emailData,
                    success: function(response) {
                        console.log('Email notification response:', response);
                        // Continue with redirect or other actions
                        window.location.href = '<?php echo esc_url(get_permalink(get_option('woocommerce_myaccount_page_id'))); ?>';
                    },
                    error: function(xhr, status, error) {
                        console.error('Failed to send email notification:', error);
                        // Redirect anyway
                        window.location.href = '<?php echo esc_url(get_permalink(get_option('woocommerce_myaccount_page_id'))); ?>';
                    }
                });
            } else {
                // If the update wasn't successful, redirect without email
                window.location.href = '<?php echo esc_url(get_permalink(get_option('woocommerce_myaccount_page_id'))); ?>';
            }
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'bocs_add_custom_box_update_script', 99);

// Register the JavaScript variable and event
function bocs_enqueue_custom_box_update_scripts() {
    // Only on the box update page
    global $wp;
    if (!isset($wp->query_vars['bocs-update-box'])) {
        return;
    }
    
    wp_register_script('bocs-box-update-helper', '', array('jquery'), BOCS_VERSION, true);
    wp_enqueue_script('bocs-box-update-helper');
    
    // Add inline script to define the custom event
    $script = "
        function bocs_trigger_box_update_success(response) {
            jQuery(document).trigger('bocs_box_update_success', [response]);
        }
    ";
    
    wp_add_inline_script('bocs-box-update-helper', $script);
}
add_action('wp_enqueue_scripts', 'bocs_enqueue_custom_box_update_scripts'); 