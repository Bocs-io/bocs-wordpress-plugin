/**
 * BOCS Button Handler Fix
 * 
 * This script fixes the redirection issues with the "Switch Bocs" and "Update My Box" buttons.
 * 
 * Add this to your WordPress site via:
 * 1. Upload this file to your theme directory
 * 2. Enqueue it in your theme's functions.php:
 *    wp_enqueue_script('bocs-button-fix', get_template_directory_uri() . '/button-handlers-fix.js', array('jquery'), '1.0', true);
 */

jQuery(document).ready(function($) {
    console.log('BOCS Button Fix Script loaded');
    
    // Global helpers to prevent duplication errors
    window.modalHelpers = window.modalHelpers || { 
        show: function(modalId) { 
            $("#" + modalId).css("display", "flex").hide().fadeIn(200); 
        }, 
        hide: function(modalId) { 
            $("#" + modalId).fadeOut(200); 
        } 
    };
    
    // Remove any existing handlers to prevent conflicts
    $(document).off('click', '.update-box-link');
    $(document).off('click', '.switch-bocs');
    
    // Add robust document-level event handlers
    $(document).on('click', '.update-box-link', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Get the subscription ID
        var subscriptionId = $(this).data('subscription-id');
        console.log('Update Box clicked for subscription:', subscriptionId);
        
        if (subscriptionId) {
            // Construct URL correctly regardless of the current page structure
            var baseUrl = window.location.pathname.split('my-account')[0] + 'my-account/';
            if (!baseUrl.startsWith('/')) {
                baseUrl = '/' + baseUrl;
            }
            var redirectUrl = baseUrl + 'bocs-update-box/' + subscriptionId + '/';
            console.log('Redirecting to:', redirectUrl);
            
            // Perform the redirect
            window.location.href = redirectUrl;
        }
    });
    
    // Add handler for Switch Bocs button
    $(document).on('click', '.switch-bocs', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Get the subscription ID
        var subscriptionId = $(this).data('subscription-id');
        console.log('Switch Bocs clicked for subscription:', subscriptionId);
        
        if (subscriptionId) {
            // Construct URL correctly regardless of the current page structure
            var baseUrl = window.location.pathname.split('my-account')[0] + 'my-account/';
            if (!baseUrl.startsWith('/')) {
                baseUrl = '/' + baseUrl;
            }
            var redirectUrl = baseUrl + 'bocs-switch-bocs/' + subscriptionId + '/';
            console.log('Redirecting to:', redirectUrl);
            
            // Perform the redirect
            window.location.href = redirectUrl;
        }
    });
    
    // Debug output
    console.log('Update Box buttons found:', $('.update-box-link').length);
    console.log('Switch Bocs buttons found:', $('.switch-bocs').length);
}); 