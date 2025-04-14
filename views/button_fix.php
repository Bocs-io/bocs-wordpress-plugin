<?php
/**
 * BOCS Button Fix Script
 * 
 * This is a standalone fix for the button redirect issues
 */

// Don't allow direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Fix script for button handlers -->
<script>
jQuery(document).ready(function($) {
    console.log('Installing failsafe click handlers');
    
    // Fix the duplicate modalHelpers global issue
    window.modalHelpers = window.modalHelpers || { 
        show: function(modalId) { 
            $("#" + modalId).css("display", "flex").hide().fadeIn(200); 
        }, 
        hide: function(modalId) { 
            $("#" + modalId).fadeOut(200); 
        } 
    };
    
    // Add direct document-level handlers for the buttons
    $(document).on("click", ".update-box-link", function(e) {
        console.log("Update Box link clicked from fix script");
        e.preventDefault();
        e.stopPropagation();
        
        var subscriptionId = $(this).data("subscription-id");
        console.log("Subscription ID:", subscriptionId);
        
        if (subscriptionId) {
            var baseUrl = window.location.pathname.split("my-account")[0] + "my-account/";
            var redirectUrl = baseUrl + "bocs-update-box/" + subscriptionId + "/";
            console.log("Redirecting to:", redirectUrl);
            window.location.href = redirectUrl;
        }
    });

    $(document).on("click", ".switch-bocs", function(e) {
        console.log("Switch Bocs link clicked from fix script");
        e.preventDefault();
        e.stopPropagation();
        
        var subscriptionId = $(this).data("subscription-id");
        console.log("Subscription ID:", subscriptionId);
        
        if (subscriptionId) {
            var baseUrl = window.location.pathname.split("my-account")[0] + "my-account/";
            var redirectUrl = baseUrl + "bocs-switch-bocs/" + subscriptionId + "/";
            console.log("Redirecting to:", redirectUrl);
            window.location.href = redirectUrl;
        }
    });
    
    console.log("Fix script loaded - button handlers installed");
});
</script> 