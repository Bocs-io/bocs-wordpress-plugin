# BOCS Plugin - Button Redirection Fix

This README explains how to fix the redirection issues with the "Update My Box" and "Switch Bocs" buttons in the BOCS WordPress plugin.

## The Issue

The buttons in the subscription account page are not redirecting properly due to:
1. JavaScript errors from duplicate variable declarations (`modalHelpers`)
2. Issues with URL construction

## Solution

### Option 1: Enqueue the Fix Script (Recommended)

1. Copy the `button-handlers-fix.js` file to your theme's directory
2. Add the following code to your theme's `functions.php` file:

```php
function enqueue_bocs_button_fix() {
    // Only load on account pages
    if (is_account_page()) {
        wp_enqueue_script(
            'bocs-button-fix', 
            get_template_directory_uri() . '/button-handlers-fix.js', 
            array('jquery'), 
            '1.0', 
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'enqueue_bocs_button_fix', 99);
```

### Option 2: Add the Fix Script Directly

If you can't modify your theme files, you can add the script directly to the BOCS plugin:

1. Open `views/bocs_subscriptions_account.php`
2. At the very bottom of the file, add:

```php
<!-- BOCS Button Fix -->
<script>
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
});
</script>
```

### Option 3: Fix the Original Code

For a more permanent solution, consider fixing these issues in the plugin code:

1. Fix the duplicate `modalHelpers` declaration by creating a single global instance
2. Update the button click handlers to use document-level event delegation

## Testing the Fix

After implementing the fix:

1. Login to the WordPress site
2. Go to My Account → BOCS Subscriptions
3. Click on either the "Update My Box" or "Switch Bocs" buttons
4. Verify that you are redirected to the correct page with the subscription ID in the URL 