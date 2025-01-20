/**
 * Custom Stripe Checkout Functionality
 * This script handles the automatic checking and state management of the "Save Card Info" checkbox
 * in the WooCommerce Stripe checkout form.
 */
jQuery(document).ready(function ($) {
    // Function to check if BOCS identifier exists (either in cookies or URL)
    function hasBocsIdentifier() {
        // Check URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const hasBocsParam = urlParams.has('bocs');

        // Check cookies
        const hasBocsCookies = document.cookie.includes('__bocs_id=');

        return hasBocsParam || hasBocsCookies;
    }

    // Function to handle the checkbox
    function setSaveCardCheckbox() {
        // Only proceed if BOCS identifier exists
        if (!hasBocsIdentifier()) return;

        const checkboxDiv = $('.wc-block-components-payment-methods__save-card-info');
        const checkbox = checkboxDiv.find('input[type="checkbox"]');
        if (checkbox.length) {
            // Hide the entire div first
            checkboxDiv.hide();
            
            // Then set the checkbox state
            checkbox.prop('checked', true);
            
            // Add event listener to force checked state
            checkbox.on('change', function() {
                if (!this.checked) {
                    $(this).prop('checked', true);
                }
            });

            // Add event listeners for Stripe form field changes
            $('input[name*="card-number"], input[name*="card-expiry"], input[name*="card-cvc"]').on('change blur keyup', function() {
                setTimeout(() => {
                    checkbox.prop('checked', true);
                }, 100);
            });

            // Add event listener for Place Order button
            $('.wc-block-components-checkout-place-order-button').on('click', function() {
                checkbox.prop('checked', true);
            });
        }
    }

    // Initial setup
    setSaveCardCheckbox();

    // Monitor for dynamic changes (in case the checkout form reloads)
    const observer = new MutationObserver(function(mutations) {
        setSaveCardCheckbox();
    });

    // Start observing the document body for DOM changes
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});